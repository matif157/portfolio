<?php
/**
 * =====================================================
 * FILE: Database.php
 * PURPOSE: SPS CRM ke liye centralized PDO database connection
 *
 * WORKFLOW:
 * 1. Configuration se database credentials receive hoti hain.
 * 2. DSN prepare hota hai.
 * 3. PDO MySQL connection create hoti hai.
 * 4. Secure PDO attributes configure hotay hain.
 * 5. Application ko ready PDO object milta hai.
 *
 * IMPORTANT:
 * CRM mein database queries ke liye PDO + prepared statements
 * use kiye jayenge.
 * =====================================================
 */

declare(strict_types=1);


/**
 * Database
 *
 * Bhai yeh class tumhare purane:
 *
 * mysqli_connect(...)
 *
 * ko replace kar rahi hai.
 *
 * Hum yahan PDO use kar rahe hain kyun ke:
 *
 * - prepared statements strong hain
 * - exceptions properly handle hoti hain
 * - transactions easy hain
 * - large CRM architecture ke liye clean abstraction milti hai
 */
class Database
{
    /**
     * PDO connection.
     */
    private PDO $pdo;


    /**
     * Constructor.
     *
     * @param array $config Database configuration
     */
    public function __construct(
        array $config
    ) {

        /*
         * Configuration values retrieve kar rahe hain.
         *
         * ?? = PHP null coalescing operator.
         *
         * Agar key missing ho to default value use hoti hai.
         */
        $host = $config['host'] ?? '127.0.0.1';

        $port = (string) (
            $config['port'] ?? '3306'
        );

        $database = $config['database'] ?? '';

        $username = $config['username'] ?? 'root';

        $password = $config['password'] ?? '';

        $charset = $config['charset'] ?? 'utf8mb4';


        /*
         * Database name empty ho to application ko
         * immediately clear error deni chahiye.
         *
         * Silent connection failure se debugging mushkil hoti hai.
         */
        if ($database === '') {

            throw new InvalidArgumentException(
                'Database name is not configured.'
            );
        }


        /*
         * DSN = Data Source Name.
         *
         * Yeh PDO ko batata hai:
         *
         * MySQL
         * kis host par
         * kis port par
         * kis database se
         * kis charset ke saath connect karna hai.
         */
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );


        try {

            /*
             * PDO = PHP ka built-in database access extension.
             *
             * Yahan actual MySQL connection create ho rahi hai.
             */
            $this->pdo = new PDO(
                $dsn,
                $username,
                $password,
                [

                    /*
                     * ERRMODE_EXCEPTION:
                     *
                     * Database error ko silently ignore karne ke
                     * bajaye PDO exception throw karega.
                     *
                     * Is se ErrorHandler issue ko properly log kar sakta hai.
                     */
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,


                    /*
                     * FETCH_ASSOC:
                     *
                     * SELECT result default associative arrays mein milega.
                     *
                     * Example:
                     *
                     * $user['full_name']
                     */
                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,


                    /*
                     * Emulated prepares disable.
                     *
                     * MySQL native prepared statements use karne ke liye.
                     */
                    PDO::ATTR_EMULATE_PREPARES =>
                        false,


                    /*
                     * Persistent connection use nahi kar rahe.
                     *
                     * Beginner-friendly aur predictable application
                     * behavior ke liye normal connection.
                     */
                    PDO::ATTR_PERSISTENT =>
                        false,
                ]
            );


        } catch (PDOException $exception) {

            /*
             * Original database exception ko generic runtime
             * exception mein wrap kar rahe hain.
             *
             * IMPORTANT:
             * Password ya complete DSN user ko expose nahi karenge.
             */
            throw new RuntimeException(
                'CRM database connection could not be established.',
                0,
                $exception
            );
        }
    }


    /**
     * PDO object return karna.
     */
    public function connection(): PDO
    {
        /*
         * Central PDO connection return.
         */
        return $this->pdo;
    }


    /**
     * Transaction start karna.
     */
    public function beginTransaction(): bool
    {
        /*
         * PDO built-in method.
         *
         * Multiple related database operations ko
         * ek transaction mein group karta hai.
         */
        return $this->pdo->beginTransaction();
    }


    /**
     * Transaction commit karna.
     */
    public function commit(): bool
    {
        /*
         * Successful transaction ko permanently save karta hai.
         */
        return $this->pdo->commit();
    }


    /**
     * Transaction rollback karna.
     */
    public function rollBack(): bool
    {
        /*
         * Failed transaction ke changes reverse karta hai.
         */
        return $this->pdo->rollBack();
    }


    /**
     * Check karna ke transaction active hai ya nahi.
     */
    public function inTransaction(): bool
    {
        /*
         * PDO built-in method.
         *
         * True:
         * transaction active.
         */
        return $this->pdo->inTransaction();
    }
}