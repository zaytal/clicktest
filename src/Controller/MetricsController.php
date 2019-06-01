<?php

namespace App\Controller;


use App\Service\FileSystemExtended;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MetricsController extends AbstractController
{
    private $file_system;
    public $error = false;
    public $error_messages = [];

    const GEO_HEADERS = ['date', 'geo', 'zone', 'impressions', 'revenue'];
    const GEO_FIELDS_TYPES = [
        'date'        => 'date#YYYY-MM-DD',
        'geo'         => 'string#2',
        'zone'        => 'string#7',
        'impressions' => 'int',
        'revenue'     => 'float',
    ];
    const GEO_GROUP_BY_CNT_FIELDS = 3;
    const DATA_DELIMITER = ',';


    public function __construct(FileSystemExtended $file_system)
    {
        $this->file_system = $file_system;
    }

    public function processLogsFromFolder(String$dir_src)
    {
        $dir_files = $this->file_system->scanDir($dir_src, ['csv']);

        file_put_contents("/home/vagrant/clicktest/var/log/parse_logs.log",
            "dir_files: " . print_r($dir_files, true) . "\r\n", FILE_APPEND);
        if(!empty($dir_files)) {
            $parsed_data = $this->parseCSVFiles($dir_files);
            //@todo сохранить сгруппированные данные в базу

            file_put_contents("/home/vagrant/clicktest/var/log/parse_logs.log",
                "parsed_data: " . print_r($parsed_data, true) . "\r\n", FILE_APPEND);
        } else {
            $this->error = true;
            $this->error_messages[] = sprintf("В запрошенной папке %s нет файлов метрики с расширением .csv", $dir_src);
        }
    }

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
                    if($processing_row == 1 && $row_data != self::GEO_HEADERS){
                        $this->error = true;
                        $this->error_messages[] = sprintf("Файл метрики %s имеет неверные заголовки", $file_src);
                        // Если заголовки файла неверно указаны, значит считаем весь файл невалидным
                        break;
                    }

                    if($processing_row > 1) {
                        //Если данные в строке невалидны, то пропустить строку
                        if($this->is_valid($row_data)) {
                            $this->normalize($row_data);
                            $file_data[] = $row_data;
                        } else {
                            $this->error = true;
                            $this->error_messages[] = sprintf("Строка номер %d имеет неверный формат", $processing_row);
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

        $files_data = $this->groupByFirstFields($merged_files_data);

        return $files_data;
    }

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

    public function normalize(&$fields)
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

    public function groupByFirstFields($data_fields, int $cnt_fields = self::GEO_GROUP_BY_CNT_FIELDS) :array
    {
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

    public function genGroupKey($fields, $cnt_fields = self::GEO_GROUP_BY_CNT_FIELDS)
    {
        $group_key = '';
        for($i = 0; $i <= $cnt_fields - 1; $i++) {
            $group_key .= $fields[$i];
        }

        return $group_key;
    }
}