<?php
/**
 * =====================================================
 * FILE: Request.php
 * PURPOSE: HTTP request data ko safely handle karna
 *
 * WORKFLOW:
 * 1. Request method identify hota hai.
 * 2. GET/POST values controlled way mein read hoti hain.
 * 3. Input values trim hoti hain.
 * 4. Missing values ke liye default use hota hai.
 * 5. Future modules isi class ko request data ke liye use karenge.
 * =====================================================
 */

declare(strict_types=1);


/**
 * Request
 *
 * Bhai is class ka kaam browser se aane wali request ko
 * organize karna hai.
 *
 * Example:
 *
 * $_POST['email']
 *
 * direct use karne ke bajaye:
 *
 * Request::post('email')
 *
 * use karenge.
 *
 * Is se poore CRM mein request handling consistent rahegi.
 */
class Request
{
    /**
     * Check karta hai ke request POST hai ya nahi.
     */
    public static function isPost(): bool
    {
        /*
         * $_SERVER = PHP predefined superglobal.
         *
         * REQUEST_METHOD batata hai browser ne
         * GET, POST, PUT etc. mein se kya request bheji.
         */
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }


    /**
     * Check karta hai ke request GET hai ya nahi.
     */
    public static function isGet(): bool
    {
        /*
         * GET request normally pages aur query parameters
         * ke liye use hoti hai.
         */
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }


    /**
     * POST value retrieve karna.
     *
     * @param string $key Input field name
     * @param mixed $default Agar field missing ho
     */
    public static function post(
        string $key,
        mixed $default = null
    ): mixed {

        /*
         * array_key_exists() = PHP built-in function.
         *
         * Check karta hai ke POST field actually exist karti hai.
         */
        if (!array_key_exists(
            $key,
            $_POST
        )) {
            return $default;
        }


        /*
         * Value retrieve.
         */
        $value = $_POST[$key];


        /*
         * Agar value string hai to extra spaces remove.
         *
         * trim() = PHP built-in function.
         *
         * Example:
         *
         * "  Atif  "
         *
         * becomes:
         *
         * "Atif"
         */
        if (is_string($value)) {
            return trim($value);
        }


        /*
         * Arrays ya other values ko as-is return.
         */
        return $value;
    }


    /**
     * GET value retrieve karna.
     *
     * Example:
     *
     * clients.php?id=25
     *
     * Request::get('id')
     *
     * => 25
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {

        /*
         * GET parameter available hai ya nahi.
         */
        if (!array_key_exists(
            $key,
            $_GET
        )) {
            return $default;
        }


        /*
         * GET value retrieve.
         */
        $value = $_GET[$key];


        /*
         * String values trim.
         */
        if (is_string($value)) {
            return trim($value);
        }


        /*
         * Non-string value as-is.
         */
        return $value;
    }


    /**
     * POST value ko required string ki form mein retrieve karna.
     *
     * Validation ke initial layer ke liye useful.
     */
    public static function postString(
        string $key,
        string $default = ''
    ): string {

        /*
         * post() helper se value retrieve.
         */
        $value = self::post(
            $key,
            $default
        );


        /*
         * String conversion.
         *
         * IMPORTANT:
         * Business validation yahan nahi hogi.
         * Sirf input type normalize kar rahe hain.
         */
        return is_string($value)
            ? $value
            : $default;
    }


    /**
     * GET value ko string mein retrieve karna.
     */
    public static function getString(
        string $key,
        string $default = ''
    ): string {

        /*
         * GET helper se value retrieve.
         */
        $value = self::get(
            $key,
            $default
        );


        /*
         * String ensure.
         */
        return is_string($value)
            ? $value
            : $default;
    }


    /**
     * Request URI retrieve karna.
     */
    public static function uri(): string
    {
        /*
         * REQUEST_URI PHP server variable hai.
         *
         * Example:
         *
         * /crm/public/clients.php?id=5
         */
        return (string) (
            $_SERVER['REQUEST_URI'] ?? '/'
        );
    }


    /**
     * Client IP address retrieve karna.
     *
     * IMPORTANT:
     * IP ko automatically trusted identity nahi samjha jayega.
     * Security decisions ke liye later proper proxy configuration
     * aur audit rules use honge.
     */
    public static function ip(): string
    {
        /*
         * REMOTE_ADDR normally web server se client IP provide karta hai.
         */
        return (string) (
            $_SERVER['REMOTE_ADDR'] ?? ''
        );
    }
}