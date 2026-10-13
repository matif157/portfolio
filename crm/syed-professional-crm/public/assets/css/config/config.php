<?php
/**
 * =====================================================
 * FILE: config/config.php
 * PURPOSE: SPS CRM ki temporary central configuration
 *
 * WORKFLOW:
 *
 * config.php
 *     ↓
 * Database settings
 *     ↓
 * Session start
 *     ↓
 * Application pages
 *
 * Bhai abhi hum framework ke complicated bootstrap ko
 * temporarily side par rakh rahe hain taake CRM ko pehle
 * browser mein properly run karke workflow test kar saken.
 *
 * Baad mein isi configuration ko final architecture ke
 * proper configuration layer mein move kar denge.
 * =====================================================
 */


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
|
| session_start() = PHP built-in function.
|
| Why:
| Login user ki information ko different pages ke darmiyan
| remember karne ke liye PHP session use hoti hai.
|
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE SETTINGS
|--------------------------------------------------------------------------
|
| XAMPP ke default MySQL credentials.
|
*/

define('DB_HOST', '127.0.0.1');

define('DB_PORT', '3306');

define('DB_NAME', 'mera_crm');

define('DB_USER', 'root');

define('DB_PASS', '');

define('DB_CHARSET', 'utf8mb4');


/*
|--------------------------------------------------------------------------
| APPLICATION SETTINGS
|--------------------------------------------------------------------------
*/

define(
    'APP_NAME',
    'Syed Professional Services CRM'
);


/*
|--------------------------------------------------------------------------
| PUBLIC URL
|--------------------------------------------------------------------------
|
| Bhai abhi XAMPP mein hum /public ke through application
| run karenge.
|
*/

define(
    'APP_URL',
    '/syed-professional-crm/public'
);


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION FUNCTION
|--------------------------------------------------------------------------
|
| Ye temporary central connection helper hai.
|
| PDO = PHP ka built-in database access system.
|
| Prepared statements aur exception handling ki wajah se
| future production architecture ke liye bhi suitable base hai.
|
*/

function db(): PDO
{
    /*
     * static = PHP keyword.
     *
     * Why:
     * Ek hi request ke andar baar baar new database
     * connection create na ho.
     */

    static $pdo = null;


    /*
     * Agar connection pehle ban chuka hai to wahi return
     * kar do.
     */

    if ($pdo instanceof PDO) {
        return $pdo;
    }


    /*
     * DSN database connection ki information batata hai.
     */

    $dsn =
        'mysql:host=' . DB_HOST .
        ';port=' . DB_PORT .
        ';dbname=' . DB_NAME .
        ';charset=' . DB_CHARSET;


    /*
     * PDO constructor = PHP built-in class.
     *
     * ATTR_ERRMODE_EXCEPTION:
     * Database error ko exception ke form mein report karega.
     *
     * ATTR_DEFAULT_FETCH_MODE:
     * SELECT result associative array mein milega.
     */

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,
        ]
    );


    return $pdo;
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION HELPER
|--------------------------------------------------------------------------
|
| Temporary MVP mein protected pages ko secure rakhne ke
| liye use hoga.
|
*/

function require_login(): void
{
    /*
     * isset() = PHP built-in function.
     *
     * Check:
     * Kya session mein logged-in user exist karta hai?
     */

    if (!isset($_SESSION['user_id'])) {

        header(
            'Location: ' . APP_URL . '/login.php'
        );

        exit();
    }
}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE HELPER
|--------------------------------------------------------------------------
|
| htmlspecialchars() = PHP built-in security function.
|
| Why:
| Database se aane wale text ko HTML mein print karte waqt
| XSS attack se protection deta hai.
|
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}