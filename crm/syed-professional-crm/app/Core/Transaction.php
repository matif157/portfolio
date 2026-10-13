<?php
/**
 * =====================================================
 * FILE: Transaction.php
 * PURPOSE: Multiple database operations ko ek safe transaction mein chalana
 * WORKFLOW:
 * 1. Transaction start hoti hai.
 * 2. Multiple database operations execute hoti hain.
 * 3. Sab successful hon to commit hota hai.
 * 4. Error aaye to rollback hota hai.
 * 5. Error dobara application ko inform kiya jata hai.
 * =====================================================
 */

declare(strict_types=1);

/**
 * Transaction
 *
 * Bhai isko "all-or-nothing" system samjho.
 *
 * Example:
 *
 * Client create
 *      ↓
 * Service create
 *      ↓
 * Workflow create
 *      ↓
 * Initial task create
 *
 * Agar workflow create fail ho gaya:
 *
 * Client bhi incomplete state mein nahi rehna chahiye.
 *
 * Isliye rollback.
 */
class Transaction
{
    /**
     * Database manager.
     */
    private Database $database;

    /**
     * Transaction service initialize karna.
     */
    public function __construct(Database $database)
    {
        /*
         * Database object save kar rahe hain.
         */
        $this->database = $database;
    }

    /**
     * Transaction ke andar callback execute karna.
     *
     * @param callable $callback Database operations
     * @return mixed Callback ka result
     */
    public function run(callable $callback): mixed
    {
        /*
         * Pehle database transaction start kar rahe hain.
         */
        $this->database->beginTransaction();

        try {

            /*
             * call_user_func() = PHP built-in function.
             *
             * Callback ko execute karta hai.
             *
             * Future mein callback ke andar multiple database
             * operations ho sakti hain.
             */
            $result = call_user_func(
                $callback,
                $this->database
            );

            /*
             * Sab operations successfully complete ho gayi hain.
             *
             * Ab changes permanently save kar do.
             */
            $this->database->commit();

            /*
             * Original operation ka result return kar rahe hain.
             */
            return $result;

        } catch (Throwable $exception) {

            /*
             * Agar koi error/exception aaye to transaction rollback.
             *
             * Is se incomplete database changes remove ho jati hain.
             */
            if ($this->database->connection()->inTransaction()) {
                $this->database->rollBack();
            }

            /*
             * throw:
             * Original error ko application ke upper layer tak bhej raha hai.
             *
             * Hum error ko silently swallow nahi karenge.
             */
            throw $exception;
        }
    }
}