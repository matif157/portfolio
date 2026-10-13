<?php
/**
 * =====================================================
 * FILE: LoginThrottle.php
 * PURPOSE: Repeated failed login attempts ko control karna
 *
 * WORKFLOW:
 * 1. Login identifier + IP ke basis par attempt key banti hai.
 * 2. Failed attempts temporarily record hoti hain.
 * 3. Limit cross hone par login temporarily block hota hai.
 * 4. Successful login par related failure counter reset hota hai.
 *
 * SECURITY:
 * Ye brute-force password guessing ko slow karta hai.
 *
 * IMPORTANT:
 * Ye application-level protection hai.
 * Production mein web-server/WAF/rate-limiting layer bhi useful hogi.
 * =====================================================
 */

declare(strict_types=1);


/**
 * LoginThrottle
 */
class LoginThrottle
{
    /**
     * Maximum failed attempts allowed within window.
     */
    private const MAX_ATTEMPTS = 5;


    /**
     * Time window in seconds.
     *
     * 15 minutes.
     */
    private const WINDOW_SECONDS = 900;


    /**
     * Temporary lock duration.
     *
     * 15 minutes.
     */
    private const LOCK_SECONDS = 900;


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
         * Session service store.
         */
        $this->session = $session;

        /*
         * Session ensure active.
         */
        $this->session->start();
    }


    /**
     * Check karta hai ke current login attempt temporarily blocked hai.
     *
     * @param string $email Login email
     * @param string $ip Client IP
     */
    public function isBlocked(
        string $email,
        string $ip
    ): bool {

        /*
         * Login attempt ki unique key create.
         */
        $key = $this->makeKey(
            $email,
            $ip
        );


        /*
         * Stored attempt information retrieve.
         */
        $data = $this->session->get(
            $key,
            null
        );


        /*
         * Agar koi record nahi hai to user blocked nahi.
         */
        if (!is_array($data)) {
            return false;
        }


        /*
         * Current timestamp.
         *
         * time() = PHP built-in function.
         */
        $now = time();


        /*
         * Lock expiry retrieve.
         */
        $lockedUntil = (int) (
            $data['locked_until'] ?? 0
        );


        /*
         * Agar lock abhi active hai to block.
         */
        if ($lockedUntil > $now) {
            return true;
        }


        /*
         * Lock expire ho gaya hai.
         *
         * Old lock remove kar dete hain.
         */
        if ($lockedUntil > 0) {

            $this->session->remove(
                $key
            );
        }


        /*
         * Failed-attempt window bhi check.
         */
        $firstAttempt = (int) (
            $data['first_attempt'] ?? 0
        );


        /*
         * Agar old window expire ho chuki hai
         * to old counter reset.
         */
        if (
            $firstAttempt > 0 &&
            ($now - $firstAttempt) > self::WINDOW_SECONDS
        ) {

            $this->session->remove(
                $key
            );

            return false;
        }


        return false;
    }


    /**
     * Failed login attempt record karna.
     *
     * @return int Current failed-attempt count
     */
    public function recordFailure(
        string $email,
        string $ip
    ): int {

        /*
         * Unique attempt key.
         */
        $key = $this->makeKey(
            $email,
            $ip
        );


        /*
         * Current record retrieve.
         */
        $data = $this->session->get(
            $key,
            null
        );


        /*
         * Invalid/missing record ko empty array se initialize.
         */
        if (!is_array($data)) {
            $data = [];
        }


        /*
         * Current timestamp.
         */
        $now = time();


        /*
         * First attempt timestamp.
         */
        $firstAttempt = (int) (
            $data['first_attempt'] ?? 0
        );


        /*
         * Agar previous attempt window expire ho gayi,
         * counter fresh start hoga.
         */
        if (
            $firstAttempt === 0 ||
            ($now - $firstAttempt) > self::WINDOW_SECONDS
        ) {

            $firstAttempt = $now;

            $attempts = 0;

        } else {

            $attempts = (int) (
                $data['attempts'] ?? 0
            );
        }


        /*
         * Failed attempt count increase.
         */
        $attempts++;


        /*
         * New record prepare.
         */
        $newData = [
            'first_attempt' => $firstAttempt,
            'attempts' => $attempts,
            'locked_until' => 0,
        ];


        /*
         * Maximum attempts reach ho gaye?
         */
        if ($attempts >= self::MAX_ATTEMPTS) {

            /*
             * Temporary lock start.
             */
            $newData['locked_until'] =
                $now + self::LOCK_SECONDS;
        }


        /*
         * Session mein updated throttling information store.
         */
        $this->session->set(
            $key,
            $newData
        );


        return $attempts;
    }


    /**
     * Successful login ke baad failed attempts reset karna.
     */
    public function clear(
        string $email,
        string $ip
    ): void {

        /*
         * Same key generate.
         */
        $key = $this->makeKey(
            $email,
            $ip
        );


        /*
         * Failed-attempt record remove.
         */
        $this->session->remove(
            $key
        );
    }


    /**
     * Internal throttle key create karna.
     */
    private function makeKey(
        string $email,
        string $ip
    ): string {

        /*
         * Email normalize.
         */
        $normalizedEmail = strtolower(
            trim($email)
        );


        /*
         * hash() = PHP built-in function.
         *
         * Why:
         * Email/IP ko directly session key mein rakhne ke bajaye
         * fixed-length identifier create karta hai.
         */
        $fingerprint = hash(
            'sha256',
            $normalizedEmail . '|' . $ip
        );


        /*
         * Prefix se clear hota hai ke ye login throttle record hai.
         */
        return '_login_throttle_' . $fingerprint;
    }
}