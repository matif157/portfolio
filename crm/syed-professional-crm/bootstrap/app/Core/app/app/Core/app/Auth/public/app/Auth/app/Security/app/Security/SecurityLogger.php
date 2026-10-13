<?php
/**
 * =====================================================
 * FILE: SecurityLogger.php
 * PURPOSE: Authentication aur security-related events ko
 *          structured audit log mein record karna
 *
 * WORKFLOW:
 * 1. Security event receive hota hai.
 * 2. Timestamp + IP + event information prepare hoti hai.
 * 3. Application logger ko event forward hota hai.
 *
 * EXAMPLES:
 * - login_success
 * - login_failure
 * - login_blocked
 * - logout
 * - csrf_failure
 *
 * IMPORTANT:
 * Passwords ya sensitive secrets kabhi log nahi karne.
 * =====================================================
 */

declare(strict_types=1);


/**
 * SecurityLogger
 */
class SecurityLogger
{
    /**
     * Application logger.
     */
    private Logger $logger;


    /**
     * Constructor.
     */
    public function __construct(
        Logger $logger
    ) {
        /*
         * Central logger store.
         */
        $this->logger = $logger;
    }


    /**
     * Security event record.
     *
     * @param string $event Event name
     * @param array<string,mixed> $context Safe event context
     */
    public function log(
        string $event,
        array $context = []
    ): void {

        /*
         * Client IP retrieve.
         *
         * REMOTE_ADDR PHP server variable hai.
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';


        /*
         * Security event ke basic metadata prepare.
         *
         * IMPORTANT:
         * Password intentionally include nahi kiya.
         */
        $context = array_merge(
            [
                'event' => $event,
                'ip' => is_string($ip) ? $ip : '',
            ],
            $context
        );


        /*
         * Central logger ko security message send.
         */
        $this->logger->warning(
            'Security event',
            $context
        );
    }
}