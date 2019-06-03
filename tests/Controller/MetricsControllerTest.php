<?php
namespace App\Tests\test_data\Controller;


use App\Controller\MetricsController;
use App\Service\FileSystemExtended;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MetricsControllerTest extends TestCase
{
    public function testMetricsController()
    {
        $file_system = new FileSystemExtended();
        $entityManager = $this->getMockBuilder(EntityManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metricsController = new MetricsController($file_system, $entityManager);

        //Проверим парсинг файлов
        $parsed_data_csv = $metricsController->parseCSVFiles(['tests/test_data/clicklogs/geo_logs.csv']);
        $parsed_data_not_csv = $metricsController->parseCSVFiles([
            'tests/test_data/clicklogs/dsfsd',
            'incorrect_file_descriptor'
        ]);

        // Проверим валидатор данных
        $is_valid_valid = $metricsController->is_valid($parsed_data_csv[0]);
        $is_valid_invalid = $metricsController->is_valid([]);

        // Проверим группировку
        $grouped_data = $metricsController->groupByFirstFields($parsed_data_csv);
        $un_grouped_data = $metricsController->groupByFirstFields($parsed_data_csv, count($parsed_data_csv));

        // Проверим создание группировочного ключа
        $grouped_key = $metricsController->genGroupKey($parsed_data_csv[0]);
        $grouped_key_empty = $metricsController->genGroupKey($parsed_data_csv[0], 0);

        // Проверяем пагинацию
        $ar_part = $metricsController->getArrayPart($parsed_data_csv, 0, 2);
        $ar_part_full = $metricsController->getArrayPart($parsed_data_csv, 0, count($parsed_data_csv));


        $this->assertNotEmpty($parsed_data_csv);
        $this->assertEmpty($parsed_data_not_csv);

        $this->assertTrue($is_valid_valid);
        $this->assertNotTrue($is_valid_invalid);

        $this->assertCount(6, $grouped_data);
        $this->assertEquals($parsed_data_csv, $un_grouped_data);

        $this->assertEquals("2018-01-01RU1111111", $grouped_key);
        $this->assertEmpty($grouped_key_empty);

        $this->assertCount(2, $ar_part);
        $this->assertEquals($parsed_data_csv, $ar_part_full);
    }
}