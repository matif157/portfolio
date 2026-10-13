<?php
/**
 * =====================================================
 * FILE: AuthGuard.php
 * PURPOSE: Protected CRM pages ko unauthorized access se protect karna
 *
 * WORKFLOW:
 * 1. Current authentication status check hota hai.
 * 2. Logged-in user ko requested page continue karne diya jata hai.
 * 3. Guest user ko login page par bheja jata hai.
 * 4. Safe internal return path login URL mein attach hota hai.
 *
 * SECURITY:
 * User-provided redirect destination SafeRedirect se validate hoti hai.
 * =====================================================
 */

declare(strict_types=1);


/**
 * AuthGuard
 */
class AuthGuard
{
    /**
     * Central authentication service.
     */
    private Auth $auth;


    /**
     * Constructor.
     */
    public function __construct(
        Auth $auth
    ) {
        /*
         * Auth service store.
         */
        $this->auth = $auth;
    }


    /**
     * Current user ko authenticated hona zaroori hai.
     */
    public function requireLogin(): void
    {
        /*
         * Auth::check() custom application method hai.
         *
         * True:
         * user authenticated.
         *
         * False:
         * user guest.
         */
        if ($this->auth->check()) {
            return;
        }


        /*
         * Current requested URI retrieve.
         *
         * $_SERVER = PHP predefined superglobal.
         */
        $returnTo = $_SERVER['REQUEST_URI'] ?? '';


        /*
         * Sirf internal path allow.
         *
         * Agar current URI mein external URL ho to
         * SafeRedirect usay reject karega.
         */
        $safeReturnTo = SafeRedirect::path(
            is_string($returnTo)
                ? $returnTo
                : null,
            'dashboard.php'
        );


        /*
         * urlencode() = PHP built-in function.
         *
         * Query parameter ke andar path ko safely encode karta hai.
         */
        $loginUrl =
            'login.php?return_to=' .
            urlencode($safeReturnTo);


        /*
         * Centralized application redirect.
         *
         * redirect ke baad execution stop hoti hai.
         */
        Url::redirect(
            $loginUrl
        );
    }


    /**
     * Guest-only page ke liye helper.
     *
     * Example:
     * login.php
     */
    public function redirectIfAuthenticated(
        string $destination = 'dashboard.php'
    ): void {

        /*
         * Already logged in user ko login page par
         * unnecessarily nahi rehna chahiye.
         */
        if (!$this->auth->check()) {
            return;
        }


        /*
         * Safe destination ensure.
         */
        $safeDestination = SafeRedirect::path(
            $destination,
            'dashboard.php'
        );


        /*
         * Dashboard/default destination.
         */
        Url::redirect(
            $safeDestination
        );
    }
}