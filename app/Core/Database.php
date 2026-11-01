<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function init(array $config): void
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset'] ?? 'utf8mb4'
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $config['user'], $config['password'], $options);
            } catch (PDOException $e) {
                Response::error(
                    Response::ERR_SERVER_ERROR,
                    'Database connection failed: ' . $e->getMessage(),
                    500
                );
            }
        }
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Database has not been initialized.');
        }
        return self::$instance;
    }
}
