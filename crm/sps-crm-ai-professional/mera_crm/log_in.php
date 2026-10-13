<?php
// =============================================
// FILE: log_in.php
// PURPOSE: Main configuration + Database connection
// WHY: Every protected page includes this file first.
//      It starts the session, connects to MySQL,
//      and loads helper functions.
// =============================================

// 1. Load global error handler FIRST
// Why: So even connection errors are shown nicely
require_once 'error_handler.php';

// 2. Start PHP session
// Why: Session stores login information (user_id, user_name)
//      so we know who is logged in across different pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Database connection settings
// Change these only if your XAMPP MySQL password is different
$host     = 'localhost';
$dbname   = 'mera_crm';          // Database name
$username = 'root';             // Default XAMPP username
$password = '';                 // Default XAMPP password is empty

// 4. Site constants (used in many pages)
define('SITE_NAME', 'Syed Professional Services');
define('SITE_EMAIL', 'invoice@syedpro.com');
define('SITE_PHONE', '(718) 859-5392');
define('SITE_ADDRESS', '1012 Coney Island Ave, Brooklyn, NY 11230');

try {
    // 5. Create PDO connection
    // PDO is modern and safer than old mysql_ functions
    // Why prepared statements: Prevent SQL Injection attacks
    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Return arrays with column names
            PDO::ATTR_EMULATE_PREPARES   => false                    // Use real prepared statements
        ]
    );
} catch (PDOException $e) {
    // If connection fails, show clear message
    die("
    <div style='font-family:Arial; max-width:600px; margin:50px auto; padding:30px; background:#fee2e2; border-radius:12px; color:#991b1b;'>
        <h2>Database Connection Failed</h2>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please check:</p>
        <ul>
            <li>XAMPP MySQL is running</li>
            <li>Database <strong>mera_crm</strong> exists</li>
            <li>You imported database/schema.sql</li>
        </ul>
    </div>");
}

// 6. Load helper functions
require_once 'functions.php';
