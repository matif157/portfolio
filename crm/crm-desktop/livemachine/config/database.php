<?php
declare(strict_types=1);

/**
 * PDO singleton for MySQL/MariaDB via XAMPP.
 */
final class Database
{
    private const DB_HOST = '127.0.0.1';
   private const DB_PORT = 3306;
    private const DB_NAME = 'attendance_system';
    private const DB_USER = 'root';
    private const DB_PASS = 'CHANGE_ME_DB_PASSWORD';

    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            self::DB_HOST, self::DB_PORT, self::DB_NAME
        );

        self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+00:00'",
        ]);

        return self::$instance;
    }
}
