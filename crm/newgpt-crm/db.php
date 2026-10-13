<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        error_log('[SPS CRM DB] ' . $e->getMessage());
        http_response_code(503);
        $message = 'Database connection is not available. Start MySQL in XAMPP and import crm.sql.';
        if (PHP_SAPI === 'cli') {
            throw $e;
        }
        die('<!doctype html><html><head><meta charset="utf-8"><title>SPS CRM - Database</title>
        <style>body{font-family:Arial,sans-serif;background:#f4f7fb;padding:40px}.box{max-width:700px;margin:auto;background:#fff;padding:30px;border-radius:14px;box-shadow:0 10px 30px #0001}h1{margin-top:0;color:#172033}code{background:#f1f3f6;padding:3px 6px;border-radius:5px}</style></head>
        <body><div class="box"><h1>SPS CRM</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p>Database: <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</code></p></div></body></html>');
    }
    return $pdo;
}

function db_transaction(callable $callback): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
?>
