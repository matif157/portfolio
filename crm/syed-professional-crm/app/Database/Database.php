<?php
/**
 * =====================================================
 * FILE: Database.php
 * PURPOSE: SPS CRM ke MySQL database ke saath PDO
 *          connection manage karna.
 *
 * WORKFLOW:
 * bootstrap/app.php
 *       ↓
 * Database object
 *       ↓
 * PDO MySQL connection
 *       ↓
 * Container mein "db" + "database"
 *       ↓
 * Repositories / Services database use karengi
 *
 * DATABASE:
 * Database name: mera_crm
 * Driver: MySQL
 *
 * NOTE:
 * PDO = PHP ka built-in database access system.
 * Prepared statements later SQL injection protection
 * ke liye repositories mein use hongi.
 * =====================================================
 */

namespace App\Database;

use PDO;
use PDOException;

/**
 * Database
 *
 * Bhai, is class ko database ka "connection manager"
 * samjho.
 *
 * Iska kaam:
 * 1. Database configuration receive karna.
 * 2. PDO connection banana.
 * 3. Same connection ko reuse karwana.
 */
class Database
{
    /**
     * Database configuration.
     */
    private array $config;

    /**
     * PDO connection.
     *
     * Initially null hai kyun ke connection constructor
     * ke andar lazmi nahi banate.
     */
    private ?PDO $pdo = null;

    /**
     * Constructor.
     *
     * @param array $config Database settings.
     */
    public function __construct(array $config)
    {
        // Database configuration save kar rahe hain.
        $this->config = $config;
    }

    /**
     * connection()
     *
     * What:
     * MySQL ke saath PDO connection create/retrieve karta hai.
     *
     * Why:
     * CRM ki repositories ko database access chahiye.
     *
     * @return PDO Active database connection.
     */
    public function connection(): PDO
    {
        // Agar connection already bana hua hai,
        // to dobara connection create nahi karna.
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        // Configuration values retrieve kar rahe hain.
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? '3306';
        $database = $this->config['database'] ?? '';
        $username = $this->config['username'] ?? 'root';
        $password = $this->config['password'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';

        /**
         * DSN = Data Source Name.
         *
         * Is string mein PDO ko bataya ja raha hai:
         * - MySQL use karo
         * - kis host par
         * - kis port par
         * - kis database ko use karna hai
         * - kis character set ke saath
         */
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            /**
             * PDO = PHP built-in class.
             *
             * Ye actual MySQL connection create karti hai.
             *
             * PDO::ATTR_ERRMODE:
             * Database errors ko exceptions ki form mein
             * receive karne ke liye.
             *
             * PDO::ERRMODE_EXCEPTION:
             * SQL/database error aaye to exception throw hoti hai.
             *
             * PDO::ATTR_DEFAULT_FETCH_MODE:
             * Database rows ko associative arrays ki form mein
             * receive karne ke liye.
             */
            $this->pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // Successful PDO connection return kar rahe hain.
            return $this->pdo;

        } catch (PDOException $exception) {

            /**
             * PDOException = PHP PDO ki database exception class.
             *
             * Security reason:
             * Database credentials, DSN ya internal SQL details
             * directly user ko show nahi karni chahiye.
             *
             * Original exception ko RuntimeException mein wrap
             * kar rahe hain taake application layer clean error
             * receive kare.
             */
            throw new \RuntimeException(
                'Database connection failed.',
                0,
                $exception
            );
        }
    }
}