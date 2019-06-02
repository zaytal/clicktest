<?php

namespace App\Service;


use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class FileSystemExtended extends Filesystem
{
    /**
     * Получить файлы из директории.
     * Если задан $extensions, то вернуть только файлы с указанными расширениями.
     *
     * @param String $dir_src
     * @param array  $extensions
     *
     * @return array
     */
    public function scanDir(String $dir_src, $extensions = []) :array
    {
        if(!$this->exists($dir_src)) {
            throw new IOException(sprintf('Requested path %s does not exist.', $dir_src));
        }

        $all_directory_files = $this->getDirFiles($dir_src);

        if(!empty($extensions)) {
            foreach ($all_directory_files as $key => $file_path) {
                $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
                if (!in_array(strtolower($file_extension), $extensions)) {
                    unset($all_directory_files[$key]);
                }
            }
        }

        return $all_directory_files;
    }

    /**
     * Получить все файлы из директории и вложенных поддиректорий
     * Рекурсивный.
     *
     * @param String $dir Дескриптор директории
     *
     * @return array
     */
    public function getDirFiles(String $dir) :array
    {
        $result = [];

        if($this->is_dir($dir)) {
            $root = scandir($dir);
            foreach ($root as $value) {
                if ($value === '.' || $value === '..') {
                    continue;
                }

                $value_path = $dir . DIRECTORY_SEPARATOR . $value;
                $value_extension = pathinfo($value_path, PATHINFO_EXTENSION);

                if ($this->exists($value_path)) {
                    if (!is_null($value_extension) && $value_extension != '') {
                        // Если это файл (разрешение не пустое)
                        $result[] = $value_path;
                        continue;
                    } else {
                        // если это директория, то пройтись по ней и собрать файлы
                        foreach ($this->getDirFiles($value_path) as $value_in_depth) {
                            $result[] = $value_in_depth;
                        }
                    }
                }
            }
        }

        // Возвращаем файлы собранные на текущей итерации
        return $result;
    }

    /**
     * Проверяет, является ли указанный путь, дескриптором папки.
     *
     * @param String $src Путь к объекту
     *
     * @return bool
     */
    public function is_dir(String $src) :bool
    {
        return is_dir($src);
    }
}