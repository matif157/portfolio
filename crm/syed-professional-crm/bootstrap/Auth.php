<?php
/**
 * =====================================================
 * FILE: Auth.php
 * PURPOSE: SPS CRM authentication aur login security
 *
 * WORKFLOW:
 * 1. Login credentials receive hoti hain.
 * 2. Login throttle check hota hai.
 * 3. Database se user retrieve hota hai.
 * 4. Account status check hota hai.
 * 5. password_verify() password verify karta hai.
 * 6. Successful login par session regenerate hoti hai.
 * 7. Failed-attempt counter clear hota hai.
 * 8. Security event record hota hai.
 *
 * SECURITY:
 * - Password hashing verification
 * - Session regeneration
 * - Login throttling
 * - Generic authentication errors
 * - Security event logging
 * =====================================================
 */

declare(strict_types=1);


/**
 * Auth
 */
class Auth
{
    /**
     * Database connection.
     */
    private PDO $pdo;


    /**
     * Session manager.
     */
    private SessionManager $session;


    /**
     * Login throttling service.
     */
    private LoginThrottle $throttle;


    /**
     * Security logger.
     */
    private ?SecurityLogger $securityLogger;


    /**
     * Constructor.
     */
    public function __construct(
        PDO $pdo,
        SessionManager $session,
        LoginThrottle $throttle,
        ?SecurityLogger $securityLogger = null
    ) {

        /*
         * Database connection.
         */
        $this->pdo = $pdo;


        /*
         * Session service.
         */
        $this->session = $session;


        /*
         * Login throttling service.
         */
        $this->throttle = $throttle;


        /*
         * Security logger optional rakha gaya hai taake
         * testing/bootstrap stages mein dependency hard fail na kare.
         */
        $this->securityLogger = $securityLogger;


        /*
         * Authentication use karne wale pages mein session ready.
         */
        $this->session->start();
    }


    /**
     * User login attempt.
     */
    public function attempt(
        string $email,
        string $password
    ): bool {

        /*
         * Email normalize.
         */
        $email = strtolower(
            trim($email)
        );


        /*
         * Client IP.
         */
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';


        /*
         * Ensure IP string.
         */
        $ip = is_string($ip)
            ? $ip
            : '';


        /*
         * Empty credentials immediately reject.
         */
        if (
            $email === '' ||
            $password === ''
        ) {
            return false;
        }


        /*
         * =================================================
         * LOGIN THROTTLING
         * =================================================
         *
         * Too many previous failures hain to database query
         * bhi avoid karenge.
         */
        if (
            $this->throttle->isBlocked(
                $email,
                $ip
            )
        ) {

            /*
             * Security event.
             */
            $this->securityLogger?->log(
                'login_blocked',
                [
                    'email' => $email,
                ]
            );


            /*
             * Authentication fail.
             */
            return false;
        }


        /*
         * =================================================
         * USER LOOKUP
         * =================================================
         */
        $sql = "
            SELECT
                u.id,
                u.full_name,
                u.email,
                u.password_hash,
                u.role_id,
                u.status
            FROM users u
            WHERE LOWER(u.email) = :email
              AND u.deleted_at IS NULL
            LIMIT 1
        ";


        /*
         * PDO prepare().
         *
         * Prepared statement SQL injection risk reduce karta hai.
         */
        $stmt = $this->pdo->prepare(
            $sql
        );


        /*
         * Execute with named parameter.
         */
        $stmt->execute([
            'email' => $email,
        ]);


        /*
         * Single user record.
         */
        $user = $stmt->fetch();


        /*
         * User not found.
         *
         * Generic failure behavior maintain.
         */
        if (!$user) {

            /*
             * Failure record.
             */
            $this->throttle->recordFailure(
                $email,
                $ip
            );


            /*
             * Security event.
             */
            $this->securityLogger?->log(
                'login_failure',
                [
                    'reason' => 'invalid_credentials',
                    'email' => $email,
                ]
            );


            return false;
        }


        /*
         * =================================================
         * ACCOUNT STATUS
         * =================================================
         */
        if (
            isset($user['status']) &&
            !in_array(
                $user['status'],
                ['active', '1'],
                true
            )
        ) {

            /*
             * Failed attempt record.
             */
            $this->throttle->recordFailure(
                $email,
                $ip
            );


            /*
             * Security event.
             *
             * Exact account status expose nahi karna.
             */
            $this->securityLogger?->log(
                'login_failure',
                [
                    'reason' => 'account_not_active',
                    'email' => $email,
                ]
            );


            return false;
        }


        /*
         * =================================================
         * PASSWORD VERIFICATION
         * =================================================
         *
         * password_verify() = PHP built-in security function.
         *
         * Plain password ko stored password hash ke against verify.
         */
        if (
            !password_verify(
                $password,
                (string) $user['password_hash']
            )
        ) {

            /*
             * Failed attempt record.
             */
            $attempts = $this->throttle->recordFailure(
                $email,
                $ip
            );


            /*
             * Security event.
             */
            $this->securityLogger?->log(
                'login_failure',
                [
                    'reason' => 'invalid_credentials',
                    'email' => $email,
                    'attempts' => $attempts,
                ]
            );


            return false;
        }


        /*
         * =================================================
         * SUCCESSFUL AUTHENTICATION
         * =================================================
         */


        /*
         * Session ID regenerate.
         *
         * Session fixation protection.
         */
        $this->session->regenerate();


        /*
         * Authenticated user ID.
         */
        $this->session->set(
            'user_id',
            (int) $user['id']
        );


        /*
         * User display name.
         */
        $this->session->set(
            'user_name',
            (string) $user['full_name']
        );


        /*
         * User email.
         */
        $this->session->set(
            'user_email',
            (string) $user['email']
        );


        /*
         * Role ID.
         */
        $this->session->set(
            'role_id',
            (int) $user['role_id']
        );


        /*
         * Authentication marker.
         */
        $this->session->set(
            'authenticated',
            true
        );


        /*
         * Successful login:
         * previous failed attempts clear.
         */
        $this->throttle->clear(
            $email,
            $ip
        );


        /*
         * Security audit event.
         */
        $this->securityLogger?->log(
            'login_success',
            [
                'user_id' => (int) $user['id'],
                'email' => $email,
            ]
        );


        return true;
    }


    /**
     * Authentication status.
     */
    public function check(): bool
    {
        /*
         * Authenticated marker + user ID dono required.
         */
        return (
            $this->session->get(
                'authenticated',
                false
            ) === true
            &&
            $this->session->has(
                'user_id'
            )
        );
    }


    /**
     * Current user ID.
     */
    public function id(): ?int
    {
        /*
         * Guest ke liye null.
         */
        if (!$this->check()) {
            return null;
        }


        return (int) $this->session->get(
            'user_id'
        );
    }


    /**
     * Current user's name.
     */
    public function name(): ?string
    {
        /*
         * Authentication required.
         */
        if (!$this->check()) {
            return null;
        }


        return (string) $this->session->get(
            'user_name'
        );
    }


    /**
     * Current user's role ID.
     */
    public function roleId(): ?int
    {
        /*
         * Authentication required.
         */
        if (!$this->check()) {
            return null;
        }


        return (int) $this->session->get(
            'role_id'
        );
    }


    /**
     * Logout.
     */
    public function logout(): void
    {
        /*
         * Current user ID logout se pehle capture.
         */
        $userId = $this->id();


        /*
         * Security event logout se pehle record.
         */
        $this->securityLogger?->log(
            'logout',
            [
                'user_id' => $userId,
            ]
        );


        /*
         * Complete session destroy.
         */
        $this->session->destroy();
    }
}