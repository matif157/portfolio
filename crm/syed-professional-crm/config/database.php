<?php
/**
 * =====================================================
 * FILE: database.php
 * PURPOSE: Environment se database configuration prepare karna
 * WORKFLOW:
 * 1. .env values Env class se read hoti hain.
 * 2. Database settings ek central array mein prepare hoti hain.
 * 3. Database class isi array ko use karke PDO connection banati hai.
 * =====================================================
 */

declare(strict_types=1);

return [

    /*
     * MySQL server hostname.
     */
    'host' => Env::get(
        'DB_HOST',
        'localhost'
    ),

    /*
     * CRM database name.
     */
    'database' => Env::get(
        'DB_DATABASE',
        'sps_professional_crm'
    ),

    /*
     * MySQL username.
     */
    'username' => Env::get(
        'DB_USERNAME',
        'root'
    ),

    /*
     * MySQL password.
     *
     * Default local XAMPP installation mein blank ho sakta hai.
     */
    'password' => Env::get(
        'DB_PASSWORD',
        ''
    ),

    /*
     * Character set.
     */
    'charset' => Env::get(
        'DB_CHARSET',
        'utf8mb4'
    ),

    /*
     * MySQL port.
     *
     * Env::int() string "3306" ko integer 3306 mein convert karega.
     */
    'port' => Env::int(
        'DB_PORT',
        3306
    ),
];