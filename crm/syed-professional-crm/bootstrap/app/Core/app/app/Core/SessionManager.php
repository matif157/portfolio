<?php
/**
 * =====================================================
 * FILE: SessionManager.php
 * PURPOSE: SPS CRM ki PHP session ko safely manage karna
 *
 * WORKFLOW:
 * 1. Session start hoti hai.
 * 2. Secure session settings apply hoti hain.
 * 3. User/session values store aur retrieve hoti hain.
 * 4. Login ke waqt session ID regenerate hoti hai.
 * 5. Logout ke waqt session completely destroy hoti hai.
 *
 * SECURITY:
 * Session fixation attacks ko reduce karne ke liye
 * successful login ke baad session_regenerate_id() use hoga.
 * =====================================================
 */

declare(strict_types=1);


/**
 * SessionManager
 *
 * Bhai is class ka kaam sirf session manage karna hai.
 *
 * Authentication ka decision Auth class karegi.
 * SessionManager sirf session ki storage/handling karega.
 */
class SessionManager
{
    /**
     * Check karta hai ke session already active hai ya nahi.
     */
    private function isStarted(): bool
    {
        /*
         * session_status() = PHP built-in function.
         *
         * Current PHP session ki state return karta hai.
         */
        return session_status() === PHP_SESSION_ACTIVE;
    }


    /**
     * Secure session start karna.
     */
    public function start(): void
    {
        /*
         * Agar session already active hai
         * to dobara session_start() nahi karna.
         */
        if ($this->isStarted()) {
            return;
        }


        /*
         * Session cookie ko JavaScript se inaccessible banata hai.
         *
         * httponly = security protection against certain
         * client-side script attacks.
         */
        ini_set(
            'session.use_only_cookies',
            '1'
        );


        /*
         * Session ID URL mein use na ho.
         *
         * Is se session ID URL ke through leak hone ka risk reduce hota hai.
         */
        ini_set(
            'session.use_trans_sid',
            '0'
        );


        /*
         * Secure cookie setting.
         *
         * Local XAMPP HTTP par false.
         *
         * Production HTTPS par application configuration ke
         * according true ki jayegi.
         */
        $secure = (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );


        /*
         * session_set_cookie_params() = PHP built-in function.
         *
         * Session cookie ki security properties define karta hai.
         */
        session_set_cookie_params([
            'lifetime' => 0,

            'path' => '/',

            'secure' => $secure,

            'httponly' => true,

            'samesite' => 'Lax',
        ]);


        /*
         * session_start() = PHP built-in function.
         *
         * Browser ki session establish karta hai.
         */
        session_start();
    }


    /**
     * Session value store karna.
     */
    public function set(
        string $key,
        mixed $value
    ): void {

        /*
         * Session active hona zaroori hai.
         */
        $this->start();


        /*
         * $_SESSION = PHP predefined superglobal.
         *
         * User/session data ko current session mein store karta hai.
         */
        $_SESSION[$key] = $value;
    }


    /**
     * Session value retrieve karna.
     */
    public function get(
        string $key,
        mixed $default = null
    ): mixed {

        /*
         * Session ensure active.
         */
        $this->start();


        /*
         * array_key_exists() = PHP built-in function.
         *
         * Check karta hai ke session key actually exist karti hai.
         */
        if (!array_key_exists(
            $key,
            $_SESSION
        )) {
            return $default;
        }


        return $_SESSION[$key];
    }


    /**
     * Session value delete karna.
     */
    public function remove(
        string $key
    ): void {

        /*
         * Session active.
         */
        $this->start();


        /*
         * unset() = PHP built-in language construct.
         *
         * Specific session value remove karta hai.
         */
        unset($_SESSION[$key]);
    }


    /**
     * Check karta hai ke session key exist karti hai.
     */
    public function has(
        string $key
    ): bool {

        /*
         * Session active.
         */
        $this->start();


        /*
         * isset() = PHP built-in function.
         *
         * Check karta hai ke value set aur non-null hai.
         */
        return isset(
            $_SESSION[$key]
        );
    }


    /**
     * Login ke baad session ID regenerate karna.
     */
    public function regenerate(): void
    {
        /*
         * Session active hona zaroori hai.
         */
        $this->start();


        /*
         * session_regenerate_id() = PHP built-in function.
         *
         * Current session ID ko new ID se replace karta hai.
         *
         * Why?
         * Successful authentication ke baad old session ID reuse
         * hone ke risk ko reduce karne ke liye.
         */
        session_regenerate_id(true);
    }


    /**
     * Session completely destroy karna.
     */
    public function destroy(): void
    {
        /*
         * Agar session active nahi hai to kuch destroy karne ki
         * zaroorat nahi.
         */
        if (!$this->isStarted()) {
            return;
        }


        /*
         * $_SESSION ko empty karna.
         */
        $_SESSION = [];


        /*
         * Session cookie ko expire karna.
         *
         * session_get_cookie_params() = PHP built-in function.
         * Existing cookie configuration retrieve karta hai.
         */
        $params = session_get_cookie_params();


        /*
         * setcookie() = PHP built-in function.
         *
         * Existing session cookie ko expired timestamp ke saath
         * browser se remove karne ka signal deta hai.
         */
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,

                'path' => $params['path'],

                'domain' => $params['domain'],

                'secure' => $params['secure'],

                'httponly' => $params['httponly'],

                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );


        /*
         * session_destroy() = PHP built-in function.
         *
         * Server-side session data destroy karta hai.
         */
        session_destroy();
    }


    /**
     * Flash message store karna.
     *
     * Example:
     *
     * "Client successfully created."
     *
     * Next request mein message show karke remove kiya ja sakta hai.
     */
    public function flash(
        string $key,
        mixed $value
    ): void {

        /*
         * Temporary session message store.
         */
        $this->set(
            '_flash_' . $key,
            $value
        );
    }


    /**
     * Flash message retrieve + remove.
     */
    public function pullFlash(
        string $key,
        mixed $default = null
    ): mixed {

        /*
         * Temporary message retrieve.
         */
        $value = $this->get(
            '_flash_' . $key,
            $default
        );


        /*
         * Read hone ke baad message remove.
         */
        $this->remove(
            '_flash_' . $key
        );


        return $value;
    }
}