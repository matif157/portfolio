<?php
/**
 * =====================================================
 * FILE: Csrf.php
 * PURPOSE: SPS CRM ke POST forms ko CSRF attacks se protect karna
 *
 * WORKFLOW:
 * 1. Session mein secure random token generate hota hai.
 * 2. Form mein hidden token add hota hai.
 * 3. Form submit hone par submitted token retrieve hota hai.
 * 4. Token session token ke saath compare hota hai.
 * 5. Invalid token par request reject hoti hai.
 *
 * SECURITY:
 * CSRF = Cross-Site Request Forgery.
 *
 * Is protection ki wajah se doosri website user ke browser se
 * SPS CRM ke sensitive POST actions ko easily trigger nahi kar sakti.
 * =====================================================
 */

declare(strict_types=1);


/**
 * Csrf
 *
 * Bhai is class ko CRM ke forms ka security stamp samjho.
 *
 * Har important state-changing POST form:
 *
 * - Client create
 * - Client update
 * - Invoice create
 * - Payment record
 * - User create
 * - Service update
 *
 * mein CSRF token hona chahiye.
 */
class Csrf
{
    /**
     * Session key jahan token store hoga.
     */
    private const SESSION_KEY = '_csrf_token';


    /**
     * Session manager.
     */
    private SessionManager $session;


    /**
     * Constructor.
     */
    public function __construct(
        SessionManager $session
    ) {
        /*
         * Session service store karna.
         */
        $this->session = $session;

        /*
         * Session ensure active.
         */
        $this->session->start();
    }


    /**
     * CSRF token retrieve ya generate karta hai.
     */
    public function token(): string
    {
        /*
         * Check karo existing token already available hai ya nahi.
         */
        $existing = $this->session->get(
            self::SESSION_KEY
        );


        /*
         * Agar valid string token already hai
         * to wahi reuse karenge.
         */
        if (
            is_string($existing) &&
            $existing !== ''
        ) {
            return $existing;
        }


        /*
         * random_bytes() = PHP built-in cryptographic function.
         *
         * Why:
         * Strong unpredictable random bytes generate karta hai.
         *
         * CSRF token guess karna difficult hona chahiye.
         */
        $token = bin2hex(
            random_bytes(32)
        );


        /*
         * bin2hex() = PHP built-in function.
         *
         * Random binary bytes ko hexadecimal readable string
         * mein convert karta hai.
         */
        $this->session->set(
            self::SESSION_KEY,
            $token
        );


        return $token;
    }


    /**
     * HTML form ke liye hidden input generate karta hai.
     */
    public function field(): string
    {
        /*
         * Current CSRF token retrieve/generate.
         */
        $token = $this->token();


        /*
         * htmlspecialchars() = PHP built-in security function.
         *
         * Token ko HTML attribute mein safely place karta hai.
         */
        $safeToken = htmlspecialchars(
            $token,
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * Hidden input return.
         *
         * Browser is value ko form submit ke waqt POST karega.
         */
        return
            '<input type="hidden" name="_csrf_token" value="'
            . $safeToken
            . '">';
    }


    /**
     * Submitted token verify karta hai.
     */
    public function validate(
        ?string $submittedToken
    ): bool {

        /*
         * Missing token automatically invalid hai.
         */
        if (
            $submittedToken === null ||
            $submittedToken === ''
        ) {
            return false;
        }


        /*
         * Session mein stored token retrieve.
         */
        $storedToken = $this->session->get(
            self::SESSION_KEY
        );


        /*
         * Stored token missing ho to request reject.
         */
        if (
            !is_string($storedToken) ||
            $storedToken === ''
        ) {
            return false;
        }


        /*
         * hash_equals() = PHP built-in security function.
         *
         * Why:
         * Do secret strings ko timing-safe manner mein compare karta hai.
         *
         * Normal === comparison ke bajaye secret tokens ke liye
         * hash_equals() preferred hai.
         */
        return hash_equals(
            $storedToken,
            $submittedToken
        );
    }


    /**
     * Current request ka CSRF token verify karta hai.
     */
    public function validateRequest(): bool
    {
        /*
         * $_POST = PHP predefined superglobal.
         *
         * Form submitted CSRF token retrieve karna.
         */
        $submittedToken = $_POST['_csrf_token'] ?? null;


        /*
         * Sirf string token acceptable hai.
         */
        if (!is_string($submittedToken)) {
            return false;
        }


        /*
         * Actual token validation.
         */
        return $this->validate(
            $submittedToken
        );
    }
}