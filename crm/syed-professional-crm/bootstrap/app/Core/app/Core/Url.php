<?php
/**
 * =====================================================
 * FILE: Url.php
 * PURPOSE: SPS CRM ke internal URLs aur redirects ko centralize karna
 *
 * WORKFLOW:
 * 1. Application base URL configuration se obtain hota hai.
 * 2. Internal paths generate hotay hain.
 * 3. Redirect helper correct HTTP header bhejta hai.
 * 4. exit() ke through redirect ke baad execution stop hoti hai.
 * =====================================================
 */

declare(strict_types=1);


/**
 * Url
 *
 * Bhai CRM mein baar baar:
 *
 * header("Location: dashboard.php");
 *
 * likhne ke bajaye centralized helper use karenge.
 *
 * Is se future mein application folder change ho,
 * URL structure change ho, ya production domain aaye,
 * to changes ek controlled jagah se handle hon.
 */
class Url
{
    /**
     * Application base path.
     *
     * Example:
     *
     * /syed-professional-crm/public
     */
    private static string $basePath = '';


    /**
     * Base path configure karna.
     */
    public static function setBasePath(
        string $basePath
    ): void {

        /*
         * Extra slash remove kar rahe hain.
         *
         * rtrim() = PHP built-in function.
         */
        self::$basePath = rtrim(
            $basePath,
            '/'
        );
    }


    /**
     * Internal application URL generate karna.
     *
     * Example:
     *
     * Url::to('dashboard.php')
     *
     * => /syed-professional-crm/public/dashboard.php
     */
    public static function to(
        string $path = ''
    ): string {

        /*
         * Starting slash remove karna.
         *
         * ltrim() = PHP built-in function.
         *
         * Is se duplicate // avoid hota hai.
         */
        $path = ltrim(
            $path,
            '/'
        );


        /*
         * Base path aur requested path combine.
         */
        if ($path === '') {
            return self::$basePath;
        }


        return self::$basePath
            . '/'
            . $path;
    }


    /**
     * Redirect user to internal application URL.
     *
     * @param string $path Target path
     * @param int $status HTTP redirect status
     */
    public static function redirect(
        string $path,
        int $status = 302
    ): never {

        /*
         * header() = PHP built-in function.
         *
         * Browser ko requested location par redirect karta hai.
         */
        header(
            'Location: ' . self::to($path),
            true,
            $status
        );


        /*
         * exit() = PHP built-in language construct.
         *
         * Redirect header send hone ke baad script ko stop karta hai.
         *
         * Ye security ke liye important hai:
         * redirect ke baad protected HTML/code execute nahi hona chahiye.
         */
        exit();
    }


    /**
     * Current base path retrieve karna.
     */
    public static function basePath(): string
    {
        return self::$basePath;
    }
}