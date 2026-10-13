<?php
/**
 * =====================================================
 * FILE: SafeRedirect.php
 * PURPOSE: User-provided redirect destinations ko safely validate karna
 *
 * WORKFLOW:
 * 1. Requested return URL receive hoti hai.
 * 2. Absolute external URL reject hoti hai.
 * 3. Protocol-based URL reject hoti hai.
 * 4. Sirf application ke internal paths allow hotay hain.
 * 5. Invalid value ki jagah safe default destination return hoti hai.
 *
 * SECURITY:
 * Open Redirect vulnerability ko prevent karna.
 * =====================================================
 */

declare(strict_types=1);


/**
 * SafeRedirect
 *
 * Bhai login ke baad user ko originally requested page par
 * bhejna useful hai, lekin user-provided URL blindly trust karna
 * dangerous hai.
 */
class SafeRedirect
{
    /**
     * Safe internal redirect path validate karna.
     *
     * @param string|null $destination User-provided destination
     * @param string $default Safe fallback page
     */
    public static function path(
        ?string $destination,
        string $default = 'dashboard.php'
    ): string {

        /*
         * Empty destination par default page.
         */
        if (
            $destination === null ||
            trim($destination) === ''
        ) {
            return $default;
        }


        /*
         * Extra spaces remove.
         */
        $destination = trim(
            $destination
        );


        /*
         * Backslash reject.
         *
         * Why:
         * Different URL parsing behavior ko avoid karne ke liye.
         */
        if (str_contains(
            $destination,
            '\\'
        )) {
            return $default;
        }


        /*
         * Protocol-based destinations reject.
         *
         * Example:
         *
         * https://evil.com
         * http://evil.com
         * javascript:...
         *
         * Ye application ke internal redirects nahi hain.
         */
        if (
            preg_match(
                '/^[a-z][a-z0-9+\-.]*:/i',
                $destination
            )
        ) {
            return $default;
        }


        /*
         * Protocol-relative external URLs reject.
         *
         * Example:
         *
         * //evil.com
         */
        if (
            str_starts_with(
                $destination,
                '//'
            )
        ) {
            return $default;
        }


        /*
         * Absolute paths ko normalize.
         *
         * /clients.php
         *
         * ko:
         *
         * clients.php
         *
         * banaya ja sakta hai.
         */
        $destination = ltrim(
            $destination,
            '/'
        );


        /*
         * Empty result reject.
         */
        if ($destination === '') {
            return $default;
        }


        /*
         * Internal application path return.
         */
        return $destination;
    }
}