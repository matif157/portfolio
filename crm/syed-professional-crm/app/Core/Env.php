<?php
/**
 * =====================================================
 * FILE: Env.php
 * PURPOSE: .env file ki configuration values load karna
 * WORKFLOW:
 * 1. .env file locate hoti hai.
 * 2. Har valid KEY=VALUE line read hoti hai.
 * 3. Comments aur blank lines ignore hoti hain.
 * 4. Values application memory mein store hoti hain.
 * 5. Configuration classes in values ko access karti hain.
 * =====================================================
 */

declare(strict_types=1);

/**
 * Env
 *
 * Bhai is class ka kaam sirf configuration read karna hai.
 *
 * Example:
 *
 * .env:
 *
 * DB_HOST=localhost
 *
 * Application:
 *
 * Env::get('DB_HOST')
 *
 * Result:
 *
 * localhost
 */
class Env
{
    /**
     * Loaded environment values.
     */
    private static array $values = [];

    /**
     * .env file load karna.
     *
     * @param string $filePath .env ka complete path
     */
    public static function load(string $filePath): void
    {
        /*
         * is_file() = PHP built-in function.
         *
         * Check karta hai ke .env file actually exist karti hai
         * ya nahi.
         */
        if (!is_file($filePath)) {
            throw new RuntimeException(
                '.env file was not found.'
            );
        }

        /*
         * file() = PHP built-in function.
         *
         * File ko lines ki array mein read karta hai.
         *
         * FILE_IGNORE_NEW_LINES:
         * Har line ke end ka newline remove karta hai.
         *
         * FILE_SKIP_EMPTY_LINES:
         * Empty lines automatically ignore karta hai.
         */
        $lines = file(
            $filePath,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
        );

        /*
         * Har configuration line process kar rahe hain.
         */
        foreach ($lines as $line) {

            /*
             * trim() = PHP built-in function.
             *
             * Beginning/end ki unnecessary spaces remove karta hai.
             */
            $line = trim($line);

            /*
             * Agar line comment hai to ignore kar do.
             *
             * Hum "#" aur ";" dono common comment styles support kar rahe hain.
             */
            if (
                $line === '' ||
                str_starts_with($line, '#') ||
                str_starts_with($line, ';')
            ) {
                continue;
            }

            /*
             * "=" ke around key/value separate kar rahe hain.
             *
             * limit 2 isliye hai taa-ke value ke andar "=" aaye
             * to unnecessary splitting na ho.
             */
            $parts = explode('=', $line, 2);

            /*
             * Agar proper KEY=VALUE structure nahi hai to
             * malformed line ignore kar denge.
             */
            if (count($parts) !== 2) {
                continue;
            }

            /*
             * Configuration key clean kar rahe hain.
             */
            $key = trim($parts[0]);

            /*
             * Configuration value clean kar rahe hain.
             */
            $value = trim($parts[1]);

            /*
             * Agar value double quotes mein wrapped hai to quotes
             * remove kar rahe hain.
             *
             * Example:
             *
             * APP_NAME="Syed Professional Services CRM"
             *
             * becomes:
             *
             * Syed Professional Services CRM
             */
            if (
                strlen($value) >= 2 &&
                $value[0] === '"' &&
                $value[strlen($value) - 1] === '"'
            ) {
                $value = substr(
                    $value,
                    1,
                    -1
                );
            }

            /*
             * Final key/value memory mein save kar rahe hain.
             */
            self::$values[$key] = $value;
        }
    }

    /**
     * Environment value lena.
     *
     * @param string $key Configuration key
     * @param mixed $default Agar key available na ho
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        /*
         * ?? PHP ka null coalescing operator hai.
         *
         * Agar key available nahi hai to default return hoga.
         */
        return self::$values[$key] ?? $default;
    }

    /**
     * Boolean environment value lena.
     *
     * Example:
     *
     * APP_DEBUG=true
     *
     * ko actual PHP boolean true banana.
     */
    public static function bool(
        string $key,
        bool $default = false
    ): bool {
        /*
         * Raw environment value le rahe hain.
         */
        $value = self::get($key);

        /*
         * Agar value available nahi hai to supplied default use karo.
         */
        if ($value === null) {
            return $default;
        }

        /*
         * filter_var() = PHP built-in function.
         *
         * FILTER_VALIDATE_BOOLEAN common text values ko
         * proper true/false mein convert karta hai.
         */
        $result = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        /*
         * Agar conversion fail ho jaye to default return karenge.
         */
        return $result ?? $default;
    }

    /**
     * Integer environment value lena.
     */
    public static function int(
        string $key,
        int $default = 0
    ): int {
        /*
         * Raw value read kar rahe hain.
         */
        $value = self::get($key);

        /*
         * Agar value missing hai to default.
         */
        if ($value === null || $value === '') {
            return $default;
        }

        /*
         * (int) PHP ka type cast hai.
         *
         * String numeric value ko integer mein convert karta hai.
         */
        return (int) $value;
    }
}