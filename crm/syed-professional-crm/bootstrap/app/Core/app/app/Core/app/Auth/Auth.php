<?php
/**
 * =====================================================
 * FILE: Auth.php
 * PURPOSE: SPS CRM user authentication aur authorization
 *          ka basic foundation provide karna
 *
 * WORKFLOW:
 * 1. Login credentials receive hoti hain.
 * 2. Database se active user find hota hai.
 * 3. password_verify() password check karta hai.
 * 4. Successful login par session regenerate hoti hai.
 * 5. User identity session mein store hoti hai.
 * 6. Logout par session destroy hoti hai.
 *
 * IMPORTANT:
 * Authentication = user kaun hai?
 *
 * Authorization = user ko kya karne ki permission hai?
 *
 * Dono ko separate rakha jayega.
 * =====================================================
 */

declare(strict_types=1);


/**
 * Auth
 *
 * Bhai ye class login/logout ka central gatekeeper hai.
 *
 * Har module ko khud password/session logic nahi likhna.
 */
class Auth
{
    /**
     * PDO database connection.
     */
    private PDO $pdo;


    /**
     * Session manager.
     */
    private SessionManager $session;


    /**
     * Constructor.
     */
    public function __construct(
        PDO $pdo,
        SessionManager $session
    ) {

        /*
         * Database connection store.
         */
        $this->pdo = $pdo;


        /*
         * Session service store.
         */
        $this->session = $session;


        /*
         * Authentication use karne wale pages mein
         * session automatically ready rahe.
         */
        $this->session->start();
    }


    /**
     * User login attempt.
     *
     * @param string $email User email
     * @param string $password Plain-text password submitted by user
     *
     * @return bool True = login successful
     */
    public function attempt(
        string $email,
        string $password
    ): bool {

        /*
         * User input normalize.
         *
         * strtolower() = PHP built-in function.
         *
         * Email comparison ko consistent rakhne ke liye.
         */
        $email = strtolower(
            trim($email)
        );


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
         * USER LOOKUP
         * =================================================
         *
         * Sirf active/non-deleted account find karna.
         *
         * IMPORTANT:
         * Password directly SQL mein compare nahi karenge.
         *
         * Password hash database se retrieve hoga,
         * phir PHP password_verify() se verify karega.
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
         * prepare() = PDO method.
         *
         * Prepared statement SQL injection risk ko reduce karta hai.
         */
        $stmt = $this->pdo->prepare(
            $sql
        );


        /*
         * execute() = PDO method.
         *
         * Named parameter safely SQL ko value provide karta hai.
         */
        $stmt->execute([
            'email' => $email,
        ]);


        /*
         * fetch() = PDO method.
         *
         * Ek user record retrieve karta hai.
         */
        $user = $stmt->fetch();


        /*
         * User nahi mila to login fail.
         *
         * Hum attacker ko ye nahi batayenge ke
         * email exist karta hai ya nahi.
         */
        if (!$user) {
            return false;
        }


        /*
         * =================================================
         * ACCOUNT STATUS CHECK
         * =================================================
         *
         * Suspended/inactive account login nahi kar sakta.
         *
         * IMPORTANT:
         * Status values hamare final database schema ke
         * according maintain ki jayengi.
         */
        if (
            isset($user['status']) &&
            !in_array(
                $user['status'],
                ['active', '1'],
                true
            )
        ) {
            return false;
        }


        /*
         * =================================================
         * PASSWORD VERIFICATION
         * =================================================
         *
         * password_verify() = PHP built-in security function.
         *
         * User ka submitted plain password:
         *
         * $password
         *
         * database ke hashed password:
         *
         * $user['password_hash']
         *
         * ke against verify hota hai.
         *
         * IMPORTANT:
         * Hum password ko database mein plain text save nahi karenge.
         */
        if (
            !password_verify(
                $password,
                (string) $user['password_hash']
            )
        ) {
            return false;
        }


        /*
         * =================================================
         * SUCCESSFUL AUTHENTICATION
         * =================================================
         *
         * Session ID regenerate.
         *
         * Ye session fixation protection ka important step hai.
         */
        $this->session->regenerate();


        /*
         * Sirf required identity data session mein store.
         *
         * Password hash session mein nahi store karna.
         */
        $this->session->set(
            'user_id',
            (int) $user['id']
        );


        $this->session->set(
            'user_name',
            (string) $user['full_name']
        );


        $this->session->set(
            'user_email',
            (string) $user['email']
        );


        $this->session->set(
            'role_id',
            (int) $user['role_id']
        );


        /*
         * Authentication marker.
         *
         * Isay baad mein Auth::check() use karega.
         */
        $this->session->set(
            'authenticated',
            true
        );


        /*
         * Login successful.
         */
        return true;
    }


    /**
     * Check karta hai ke current user authenticated hai ya nahi.
     */
    public function check(): bool
    {
        /*
         * Session mein authentication marker aur user ID dono
         * available hone chahiye.
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
     * Current logged-in user's ID retrieve karna.
     */
    public function id(): ?int
    {
        /*
         * Agar authenticated nahi hai to null.
         */
        if (!$this->check()) {
            return null;
        }


        /*
         * Session value ko integer mein convert.
         */
        return (int) $this->session->get(
            'user_id'
        );
    }


    /**
     * Current user ka naam.
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
     * Current user ka role ID.
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
         * Authentication session destroy.
         *
         * SessionManager complete session clear karega.
         */
        $this->session->destroy();
    }
}