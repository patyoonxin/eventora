<?php
namespace App\Models;

use PDO;
use PDOException;

class Database {

    private static ?PDO $instance = null;

    public static function connect(): PDO {

        if (self::$instance === null) {

            $settings = (require __DIR__ . '/../../config/settings.php')['db'];

            $dsn = "mysql:host={$settings['host']};port={$settings['port']};dbname={$settings['database']};charset={$settings['charset']}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    $settings['username'],
                    $settings['password'],
                    $options
                );

            } catch (PDOException $e) {
                throw new \RuntimeException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}