<?php
/**
 * =====================================================
 * FILE: env.php
 * PURPOSE: .env file se application configuration read karna
 *
 * WORKFLOW:
 * 1. Project root identify hota hai.
 * 2. .env file locate hoti hai.
 * 3. Har valid KEY=VALUE line read hoti hai.
 * 4. Comments aur blank lines ignore hoti hain.
 * 5. Values $_ENV aur $_SERVER mein available hoti hain.
 *
 * IMPORTANT:
 * Yeh custom helper hai.
 * Iska purpose configuration ko PHP source code se separate rakhna hai.
 * =====================================================
 */

declare(strict_types=1);


/**
 * loadEnv()
 *
 * .env file ko read karke environment variables load karta hai.
 *
 * @param string $filePath .env ka absolute path
 * @return void
 */
function loadEnv(string $filePath): void
{
    /*
     * file_exists() = PHP built-in function.
     *
     * Check karta hai ke .env file available hai ya nahi.
     */
    if (!file_exists($filePath)) {

        /*
         * Development environment mein clear error dena better hai.
         *
         * Missing configuration ke saath CRM ko silently run nahi karna.
         */
        throw new RuntimeException(
            '.env file was not found: ' . $filePath
        );
    }


    /*
     * file() = PHP built-in function.
     *
     * File ko individual lines ke array mein read karta hai.
     *
     * FILE_IGNORE_NEW_LINES:
     * Har line ke end ka newline remove karta hai.
     *
     * FILE_SKIP_EMPTY_LINES:
     * Empty lines ignore karta hai.
     */
    $lines = file(
        $filePath,
        FILE_IGNORE_NEW_LINES |
        FILE_SKIP_EMPTY_LINES
    );


    /*
     * Agar file read nahi ho saki to clear exception.
     */
    if ($lines === false) {

        throw new RuntimeException(
            'Unable to read .env file.'
        );
    }


    /*
     * Har configuration line process karna.
     */
    foreach ($lines as $line) {

        /*
         * trim() = PHP built-in function.
         *
         * Extra spaces remove karta hai.
         */
        $line = trim($line);


        /*
         * Empty line ya comment line skip.
         *
         * '#' se start hone wali line comment hai.
         */
        if (
            $line === '' ||
            str_starts_with($line, '#')
        ) {
            continue;
        }


        /*
         * '=' ke around key/value separate karna.
         *
         * explode() = PHP built-in function.
         */
        $parts = explode(
            '=',
            $line,
            2
        );


        /*
         * Agar valid KEY=VALUE format nahi hai
         * to line ignore karna.
         */
        if (count($parts) !== 2) {
            continue;
        }


        /*
         * Left side = variable name.
         */
        $key = trim($parts[0]);


        /*
         * Right side = variable value.
         */
        $value = trim($parts[1]);


        /*
         * Agar value double ya single quotes mein hai
         * to outer quotes remove karna.
         *
         * Example:
         *
         * APP_NAME="SPS CRM"
         *
         * result:
         *
         * SPS CRM
         */
        if (
            strlen($value) >= 2 &&
            (
                (
                    $value[0] === '"' &&
                    $value[
                        strlen($value) - 1
                    ] === '"'
                ) ||
                (
                    $value[0] === "'" &&
                    $value[
                        strlen($value) - 1
                    ] === "'"
                )
            )
        ) {

            $value = substr(
                $value,
                1,
                -1
            );
        }


        /*
         * Environment variable ko $_ENV mein store karna.
         *
         * $_ENV = PHP predefined superglobal array.
         */
        $_ENV[$key] = $value;


        /*
         * $_SERVER mein bhi store kar rahe hain.
         *
         * Is se different hosting configurations ke saath
         * compatibility better hoti hai.
         */
        $_SERVER[$key] = $value;
    }
}


/**
 * env()
 *
 * Loaded environment value retrieve karne ka helper.
 *
 * @param string $key Environment variable name
 * @param mixed $default Default value
 * @return mixed
 */
function env(
    string $key,
    mixed $default = null
): mixed {

    /*
     * $_ENV se value retrieve.
     */
    if (array_key_exists(
        $key,
        $_ENV
    )) {

        return $_ENV[$key];
    }


    /*
     * Agar $_ENV mein nahi hai to $_SERVER check.
     */
    if (array_key_exists(
        $key,
        $_SERVER
    )) {

        return $_SERVER[$key];
    }


    /*
     * Variable nahi mili to default return.
     */
    return $default;
}