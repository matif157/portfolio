<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): void
    {
        if (self::$pdo !== null) return;

        $host = Config::get('db.host');
        $port = Config::get('db.port');
        $name = Config::get('db.name');
        $user = Config::get('db.user');
        $pass = Config::get('db.pass');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (Config::get('app.debug')) {
                die('Database connection failed: ' . e($e->getMessage()) .
                    '<br><br>Create database <code>' . e($name) . '</code> and import <code>database/install.sql</code>. ' .
                    'Also copy <code>.env.example</code> to <code>.env</code> and set credentials.');
            }
            die('Database connection failed. Contact administrator.');
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
    }

    public static function commit(): void
    {
        self::pdo()->commit();
    }

    public static function rollBack(): void
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
