<?php

namespace App\Tests\Service;


use App\Controller\MetricsController;
use App\Service\FileSystemExtended;
use PHPUnit\Framework\TestCase;

class FileSystemExtendedTest extends TestCase
{
    public function testFileSystemExtended()
    {
        $filesystem = new FileSystemExtended();

        // Проверяем выборку только файлов логов. Расширения логов задаются в MetricsController::GEO_LOGS_EXTENSIONS
        $all_dir_logs = $filesystem->scanDir("tests/test_data/clicklogs");
        $no_dir_logs = $filesystem->scanDir("tests/test_data/no_logs", MetricsController::GEO_LOGS_EXTENSIONS);

        // Проверяем выборку всех файлов из каталога
        $all_dir_files = $filesystem->getDirFiles("tests/test_data/no_logs");
        $no_dir_files = $filesystem->getDirFiles("no_such_directory");

        // Проверяем валидацию каталога
        $is_dir_true = $filesystem->is_dir("tests/test_data/clicklogs");
        $is_dir_false = $filesystem->is_dir("no_such_directory");


        $this->assertNotEmpty($all_dir_logs);
        $this->assertEmpty($no_dir_logs);

        $this->assertNotEmpty($all_dir_files);
        $this->assertEmpty($no_dir_files);

        $this->assertTrue($is_dir_true);
        $this->assertNotTrue($is_dir_false);
    }
}