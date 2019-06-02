<?php

namespace App\Controller;


use App\Repository\GeoMetricsRepository;
use App\Service\FileSystemExtended;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MetricsController extends AbstractController
{
    private $file_system;
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    private $entityManager;
    /**
     * @var GeoMetricsRepository
     */
    private $metricsRepository;

    public $error = false;
    public $error_messages = [];

    /**
     * Поддерживаемые разрешения
     */
    private const GEO_LOGS_EXTENSIONS = ['csv'];
    private const GEO_HEADERS = ['date', 'geo', 'zone', 'impressions', 'revenue'];
    private const GEO_FIELDS_TYPES = [
        'date'        => 'date#YYYY-MM-DD',
        'geo'         => 'string#2',
        'zone'        => 'string#7',
        'impressions' => 'int',
        'revenue'     => 'float',
    ];
    private const GEO_GROUP_BY_CNT_FIELDS = 3;

    public const DATA_DELIMITER = ',';
    public const DB_PROCESSING_STEP = 1000;


    public function __construct(FileSystemExtended $file_system, EntityManagerInterface $entityManager)
    {
        $this->file_system = $file_system;
        $this->entityManager = $entityManager;
        $this->metricsRepository = $entityManager->getRepository("App\Entity\GeoMetrics");
    }

    /**
     * Обработать все логи из указанной директории.
     * Записать их в таблицу логов.
     *
     * @param String $dir_src
     */
    public function processLogsFromFolder(String $dir_src) :void
    {
        $dir_files = $this->file_system->scanDir($dir_src, self::GEO_LOGS_EXTENSIONS);

        if(!empty($dir_files)) {
            $parsed_data = $this->parseCSVFiles($dir_files);

            $this->upsertGeoMetricsTable($parsed_data);
        } else {
            $this->error = true;
            $this->error_messages[] = sprintf("В запрошенной папке %s нет файлов метрики с расширением .csv", $dir_src);
        }
    }

    /**
     * Парсим все файлы из переданного списка методом fgetcsv и группируем результат.
     * Сохраняет возникающие ошибки для дальнейшего вывода.
     *
     * @param array $files_descriptors Список дескрипторов файлов, которые надо распарсить.
     *
     * @return array Сгруппированные данные из предложенных файлов
     */
    public function parseCSVFiles($files_descriptors) :array
    {
        if(empty($files_descriptors)) {
            return null;
        }

        $files_data = [];

        foreach ($files_descriptors as $file_src) {
            $file_data = [];
            $processing_row = 1;
            if (($handle = fopen($file_src, "r")) !== false) {
                while (($row_data = fgetcsv($handle, 1000, self::DATA_DELIMITER)) !== false) {
                    // Если заголовки файла неверно указаны, значит считаем весь файл невалидным
                    if($processing_row == 1 && $row_data != self::GEO_HEADERS){
                        $this->error = true;
                        $this->error_messages[] = sprintf("Файл метрики %s имеет неверные заголовки", $file_src);
                        break;
                    }

                    if($processing_row > 1) {
                        //Если данные в строке невалидны, то пропустить строку
                        if($this->is_valid($row_data)) {
                            $this->normalize($row_data);
                            $file_data[] = $row_data;
                        } else {
                            $this->error = true;
                            $this->error_messages[] = sprintf("Строка номер %d имеет неверный формат. Файл %s.", $processing_row, $file_src);
                        }
                    }

                    $processing_row++;
                }
                fclose($handle);
            }

            if(!empty($file_data)) {
                $file_data = array_values($this->groupByFirstFields($file_data));
                $files_data[$file_src] = $file_data;
            }
        }

        $merged_files_data = [];
        foreach($files_data as $file_data) {
            $merged_files_data = array_merge($merged_files_data, $file_data);
        }

        $files_data = array_values($this->groupByFirstFields($merged_files_data));

        return $files_data;
    }

    /**
     * Проверяем строку из файла на валидность
     *
     * @param array $fields Поля строки
     *
     * @return bool Результат валидации: false - невалидно, true - валидно
     */
    public function is_valid($fields) :bool
    {
        $is_valid = true;

        if(count($fields) != count(self::GEO_HEADERS)) {
            $is_valid = false;
        }

        foreach($fields as $key => $field) {
            if(!empty($field)) {
                $rules = explode("#", self::GEO_FIELDS_TYPES[self::GEO_HEADERS[$key]]);
                switch($rules[0]){
                    case "date":
                        if($rules[1] == 'YYYY-MM-DD') {
                            if(!preg_match("#(\d{4}-\d{2}-\d{2})#", $field)) {
                                $is_valid = false;
                            }
                        }
                        break;
                    case "string":
                        if(!empty($rules[1])) {
                            if(strlen($field) != $rules[1]) {
                                $is_valid = false;
                            }
                        }
                        break;
                    case "int":
                        // Если хоть одна НЕ цифра
                        if(preg_match("#(\D)#", $field)) {
                            $is_valid = false;
                        }
                        if(!empty($rules[1])) {
                            if(strlen($field) != $rules[1]) {
                                $is_valid = false;
                            }
                        }
                        break;
                    case "float":
                        //Если хоть одна НЕ цифра (за исключением точки) ИЛИ если строка состоит только из точки
                        if(preg_match("#[^\d\.]|^[.]$#", $field)) {
                            $is_valid = false;
                        }
                        if(!empty($rules[1])) {
                            if(strlen($field) != $rules[1]) {
                                $is_valid = false;
                            }
                        }
                        break;
                }
            } else {
                $is_valid = false;
            }


            if(!$is_valid) {
                break;
            }
        }


        return $is_valid;
    }

    /**
     * Нормализовать данные в массиве с учётом заданных типов.
     * Исходные данные при парсинге являются строками. Чтобы с ними можно было работать их нужно привести к соответствующим типам
     *
     * @param $fields
     */
    public function normalize(&$fields) :void
    {
        foreach($fields as $key => &$field) {
            $rules = explode("#", self::GEO_FIELDS_TYPES[self::GEO_HEADERS[$key]]);
            switch($rules[0]){
                case "int":
                    $field = (int)$field;
                    break;
                case "float":
                    $field = (float)$field;
                    break;
            }
        }
    }

    /**
     * Сгруппировать данные по первым N полям.
     *
     *
     * @param array $data_fields Сырые данные для группировки.
     * @param int   $cnt_fields  Число полей для группировки.
     *                           Если $cnt_fields равен 0, NULL или больше чем полей в массиве, то вернуть исходные данные без группировки.
     *
     * @return array Сгруппированный массив
     */
    public function groupByFirstFields($data_fields, int $cnt_fields = self::GEO_GROUP_BY_CNT_FIELDS) :array
    {
        if($cnt_fields == 0 || is_null($cnt_fields) || $cnt_fields >= count($data_fields)) {
            return $data_fields;
        }

        $grouped_data = [];
        foreach ($data_fields as $fields) {
            $group_key = $this->genGroupKey($fields);
            if(array_key_exists($group_key, $grouped_data)) {
                for ($i = $cnt_fields; $i <= count($fields) - 1; $i++) {
                    $grouped_data[$group_key][$i] += $fields[$i];
                }
            } else {
                $grouped_data[$group_key] = $fields;
            }
        }

        return $grouped_data;
    }

    /**
     * Генерация ключа, по которому будет производиться сравнение и группировка
     *
     * @param array $fields
     * @param int   $cnt_fields
     *
     * @return string Сгенерированный ключ
     */
    public function genGroupKey($fields, int $cnt_fields = self::GEO_GROUP_BY_CNT_FIELDS) :string
    {
        $group_key = '';
        for($i = 0; $i <= $cnt_fields - 1; $i++) {
            $group_key .= $fields[$i];
        }

        return $group_key;
    }

    /**
     * Сохранить/обновить финальные сгруппированные значения в таблицу
     *
     * @param array $parsed_data Финальные сгруппированные данные
     */
    public function upsertGeoMetricsTable($parsed_data) :void
    {
        $count_left = count($parsed_data);
        $offset = 0;
        $portion = 1;
        while ($count_left > 0) {
            $process_data_part = $this->getArrayPart($parsed_data, $offset, self::DB_PROCESSING_STEP);
            $upserting_result = $this->metricsRepository->upsertGeoMetrics($process_data_part);

            if(!$upserting_result) {
                $this->error = true;
                $this->error_messages[] = sprintf("Произошла непредвиденная ошибка при обработке %d-й порции", $portion);
            }

            $offset += self::DB_PROCESSING_STEP;
            $count_left -= self::DB_PROCESSING_STEP;
            $portion++;
        }

    }

    /**
     * Взять часть данных из массива. Используется для пагинации по масссиву.
     *
     * @param array $arr    Исходный массив
     * @param int   $offset Смещение
     * @param int   $limit  Вернуть число элементов
     *
     * @return array Обрезанный массив
     */
    public function getArrayPart($arr, int $offset, int $limit) :array
    {
        if(empty($arr)) {
            return [];
        }

        return array_slice($arr, $offset, $limit);
    }
}