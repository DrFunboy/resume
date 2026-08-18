<?php
namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            $db = $config['db'];

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $db['host'],
                $db['port'],
                $db['name']
            );

            try {
                self::$instance = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                $msg = htmlspecialchars($e->getMessage());
                file_put_contents(
                    __DIR__ . '/logs/' . date('y-m-d') . '.txt',
                    date('c') . ": Database connection error: $msg"
                );
                http_response_code(500);
                die('Database connection error: ' . $msg);
            }
        }

        return self::$instance;
    }
}
