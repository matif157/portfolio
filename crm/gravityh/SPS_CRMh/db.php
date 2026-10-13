<?php
/**
 * GRAVITY CRM — PDO Database Connection & Helper Functions
 * 
 * Yahan hum PDO (PHP Data Objects) use kar rahe hain MySQL ke sath secure connection ke liye.
 * PDO prepared statements provide karta hai jis se SQL Injection attacks mukammal rokey ja sakte hain.
 * Har query parameter sanitize hokar execute hoti hai.
 */

require_once __DIR__ . '/config.php';

/**
 * Database Connection Singleton Function
 * 
 * Yeh function ensure karta hai ke pore page lifecycle me sirf aik hi database connection instance bane.
 * Agar database exist nahi karta to user ko clear instruction provide karega.
 */
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // DSN string build kar rahe hain utf8mb4 charset ke sath taake emojis aur special characters support hon
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Query fail hone par exception throw hogi
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Associative array format me records return honge
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Native prepared statements use honge security ke liye
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Agar database connect na ho sake to user friendly help screen display karenge
            try {
                $dsn_no_db = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
                $test_pdo = new PDO($dsn_no_db, DB_USER, DB_PASS);
                die('<div style="font-family:sans-serif; padding:40px; background:#fff0f0; border-left:5px solid #e53e3e; margin:40px auto; max-width:700px; border-radius:8px;">' .
                    '<h2 style="color:#c53030; margin-top:0;">Gravity CRM — Database Connection Error</h2>' .
                    '<p>Connected to MySQL server, but database <strong>' . DB_NAME . '</strong> was not found.</p>' .
                    '<p><strong>Quick Fix for XAMPP:</strong> Open phpMyAdmin (<a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a>), create a database named <code>' . DB_NAME . '</code>, and import <code>crm.sql</code>.</p>' .
                    '</div>');
            } catch (PDOException $ex) {
                die('<div style="font-family:sans-serif; padding:40px; background:#fff0f0; border-left:5px solid #e53e3e; margin:40px auto; max-width:700px; border-radius:8px;">' .
                    '<h2 style="color:#c53030; margin-top:0;">MySQL Server Offline</h2>' .
                    '<p>Could not connect to MySQL server at <code>' . DB_HOST . ':' . DB_PORT . '</code>.</p>' .
                    '<p>Please start MySQL module in XAMPP Control Panel.</p>' .
                    '<p>Error details: ' . htmlspecialchars($e->getMessage()) . '</p>' .
                    '</div>');
            }
        }
    }
    return $pdo;
}

// Global PDO shortcut instance
// Har page par direct $pdo variable se database queries execute ki ja sakti hain
$pdo = get_db();

