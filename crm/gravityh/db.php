<?php
/**
 * GRAVITY CRM - Database Connection
 * Phase 1: Foundation
 * 
 * Yahan PDO use ho raha hai kyun ke PDO prepared statements
 * provide karta hai jo SQL injection se bachata hai.
 */

require_once __DIR__ . '/config.php';

/**
 * PDO connection singleton
 * 
 * @return PDO
 */
function db(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . (defined('DB_PORT') ? DB_PORT : '3307') . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Friendly error - technical details log file mein jayenge
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed. Please check your configuration and try again.');
        }
    }
    
    return $pdo;
}

/**
 * Run a query and return all rows
 * 
 * @param string $sql
 * @param array $params
 * @return array
 */
function db_query(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run a query and return a single row
 * 
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function db_row(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Run a query and return a single value
 * 
 * @param string $sql
 * @param array $params
 * @return mixed
 */
function db_value(string $sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * Execute an INSERT/UPDATE/DELETE query
 * 
 * @param string $sql
 * @param array $params
 * @return int affected rows
 */
function db_execute(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Get the last inserted ID
 * 
 * @return int
 */
function db_last_id(): int {
    return (int) db()->lastInsertId();
}

/**
 * Alias for db_last_id
 * 
 * @return int
 */
function db_insert_id(): int {
    return db_last_id();
}

/**
 * Begin a transaction
 */
function db_begin(): void {
    db()->beginTransaction();
}

/**
 * Commit a transaction
 */
function db_commit(): void {
    db()->commit();
}

/**
 * Rollback a transaction
 */
function db_rollback(): void {
    db()->rollback();
}

/**
 * Run a closure inside a transaction
 * 
 * @param callable $callback
 * @return mixed
 * @throws Throwable
 */
function db_transaction(callable $callback) {
    db_begin();
    try {
        $result = $callback();
        db_commit();
        return $result;
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}