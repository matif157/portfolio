<?php
/**
 * =====================================================
 * FILE: Url.php
 * PURPOSE: Application ke URLs ko central place se manage karna.
 * WORKFLOW:
 * 1. Base path set hota hai.
 * 2. URL generate hota hai.
 * 3. Future mein production domain change ho to sirf
 *    ye class update karni padegi.
 * =====================================================
 */

namespace App\Core;

/*
|--------------------------------------------------------------------------
| URL Class
|--------------------------------------------------------------------------
|
| Ye custom class hai.
| Iska kaam application ke internal URLs banana hai.
|
| Abhi hum XAMPP par:
|
| /syed-professional-crm/public
|
| ko application ka public base path maan rahe hain.
|
*/

class Url
{
    /*
    |--------------------------------------------------------------------------
    | Application Base Path
    |--------------------------------------------------------------------------
    |
    | Ye static property application ka base URL store karti hai.
    |
    */
    private static string $basePath = '';

    /**
     * Base path set karna.
     *
     * self = PHP keyword hai jo isi class ko refer karta hai.
     *
     * trim() = PHP built-in function.
     * Why:
     * extra "/" ko remove karne ke liye.
     */
    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Application ka base URL return karta hai.
     *
     * Example:
     *
     * Url::base()
     *
     * Result:
     *
     * /syed-professional-crm/public
     */
    public static function base(): string
    {
        return self::$basePath;
    }

    /**
     * Application ke andar URL generate karta hai.
     *
     * Example:
     *
     * Url::to('dashboard')
     *
     * Result:
     *
     * /syed-professional-crm/public/dashboard
     */
    public static function to(string $path = ''): string
    {
        /*
        |--------------------------------------------------------------------------
        | Extra slash remove karna
        |--------------------------------------------------------------------------
        |
        | ltrim() = PHP built-in function.
        | Why:
        | Agar developer "/dashboard" ya "dashboard" dono mein se
        | koi bhi pass kare to URL broken na ho.
        |
        */
        $path = ltrim($path, '/');

        /*
        |--------------------------------------------------------------------------
        | Agar path empty hai
        |--------------------------------------------------------------------------
        |
        | Root application URL return hoga.
        */
        if ($path === '') {
            return self::$basePath;
        }

        /*
        |--------------------------------------------------------------------------
        | Final URL
        |--------------------------------------------------------------------------
        |
        | slash ko correctly combine kar rahe hain.
        */
        return self::$basePath . '/' . $path;
    }

    /**
     * Asset URL generate karta hai.
     *
     * Example:
     *
     * Url::asset('css/app.css')
     *
     * Result:
     *
     * /syed-professional-crm/public/assets/css/app.css
     */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');

        return self::$basePath . '/assets/' . $path;
    }
}