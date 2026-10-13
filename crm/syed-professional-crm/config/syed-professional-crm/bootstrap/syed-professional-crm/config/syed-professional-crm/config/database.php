<?php
/**
 * =====================================================
 * FILE: database.php
 * PURPOSE: SPS CRM database configuration
 *
 * WORKFLOW:
 * 1. Database credentials .env se read hoti hain.
 * 2. Database host/port identify hota hai.
 * 3. Database name identify hota hai.
 * 4. Username/password retrieve hotay hain.
 * 5. PDO ke liye complete configuration return hoti hai.
 * =====================================================
 */

declare(strict_types=1);


/*
 * Database configuration return.
 *
 * Actual connection Database.php class banayegi.
 *
 * Yeh file sirf configuration provide karti hai.
 */
return [

    /*
     * MySQL server host.
     */
    'host' => env(
        'DB_HOST',
        '127.0.0.1'
    ),


    /*
     * MySQL server port.
     */
    'port' => env(
        'DB_PORT',
        '3306'
    ),


    /*
     * CRM database name.
     */
    'database' => env(
        'DB_DATABASE',
        'mera_crm'
    ),


    /*
     * MySQL username.
     */
    'username' => env(
        'DB_USERNAME',
        'root'
    ),


    /*
     * MySQL password.
     *
     * XAMPP default mein blank ho sakta hai.
     */
    'password' => env(
        'DB_PASSWORD',
        ''
    ),


    /*
     * Database character set.
     *
     * utf8mb4 modern Unicode support provide karta hai.
     */
    'charset' => env(
        'DB_CHARSET',
        'utf8mb4'
    ),
];