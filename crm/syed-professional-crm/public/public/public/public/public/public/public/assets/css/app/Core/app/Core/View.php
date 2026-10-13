<?php
/**
 * =====================================================
 * FILE: View.php
 * PURPOSE: PHP views/templates ko render karna.
 * WORKFLOW:
 * Controller/Page
 *      ↓
 * View::render()
 *      ↓
 * resources/views/
 *      ↓
 * HTML browser ko return
 * =====================================================
 */

namespace App\Core;

class View
{
    /**
     * View directory ka path.
     *
     * Ye static property application ke views ka central location
     * store karegi.
     */
    private static string $viewPath;

    /**
     * View system initialize karna.
     *
     * __construct() ki jagah static setup use kar rahe hain
     * kyun ke abhi application simple rakhi ja rahi hai.
     */
    public static function setPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/\\');
    }

    /**
     * View render karna.
     *
     * $view:
     * Example: dashboard
     *
     * $data:
     * Example:
     *
     * [
     *     'title' => 'Dashboard'
     * ]
     *
     * extract() = PHP built-in function.
     * Ye array ke keys ko variables mein convert karta hai.
     */
    public static function render(
        string $view,
        array $data = []
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Data ko local variables mein convert karna
        |--------------------------------------------------------------------------
        */
        extract($data);

        /*
        |--------------------------------------------------------------------------
        | View file ka exact path
        |--------------------------------------------------------------------------
        */
        $file = self::$viewPath . '/' . $view . '.php';

        /*
        |--------------------------------------------------------------------------
        | View file exist karti hai ya nahi
        |--------------------------------------------------------------------------
        |
        | is_file() = PHP built-in function.
        | Agar view missing ho to blank page ke bajaye useful error mile.
        */
        if (!is_file($file)) {
            throw new \RuntimeException(
                "View not found: " . $view
            );
        }

        /*
        |--------------------------------------------------------------------------
        | View execute karna
        |--------------------------------------------------------------------------
        |
        | require = PHP built-in language construct.
        |
        | Ye PHP file ko load karta hai aur uska HTML browser ke output
        | mein bhej deta hai.
        */
        require $file;
    }
}