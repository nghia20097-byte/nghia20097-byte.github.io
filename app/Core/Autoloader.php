<?php
namespace App\Core;

class Autoloader
{
    public static function register(string $baseDir): void
    {
        spl_autoload_register(function ($class) use ($baseDir) {
            $prefix = 'App\\';
            $basePath = $baseDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $basePath . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
