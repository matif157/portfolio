<?php
/**
 * =====================================================
 * FILE: logout.php
 * PURPOSE: Current SPS CRM user ko securely logout karna
 *
 * WORKFLOW:
 * 1. Application bootstrap load hota hai.
 * 2. Authentication service initialize hoti hai.
 * 3. Current session destroy hoti hai.
 * 4. User login page par redirect hota hai.
 *
 * SECURITY:
 * Logout ke baad authentication session completely clear hoti hai.
 * =====================================================
 */

declare(strict_types=1);


/*
 * =====================================================
 * 1. APPLICATION BOOTSTRAP
 * =====================================================
 *
 * Database, configuration, Container aur core services
 * initialize karna.
 */
require_once dirname(__DIR__)
    . '/bootstrap/app.php';


/*
 * =====================================================
 * 2. AUTH CLASSES
 * =====================================================
 */

require_once dirname(__DIR__)
    . '/app/Core/SessionManager.php';

require_once dirname(__DIR__)
    . '/app/Auth/Auth.php';


/*
 * Session manager create.
 */
$session = new SessionManager();


/*
 * Central PDO connection retrieve.
 */
$pdo = $container->get(
    'db'
);


/*
 * Authentication service create.
 */
$auth = new Auth(
    $pdo,
    $session
);


/*
 * =====================================================
 * 3. LOGOUT
 * =====================================================
 *
 * Auth::logout() session ko completely destroy karta hai.
 */
$auth->logout();


/*
 * =====================================================
 * 4. LOGIN PAGE
 * =====================================================
 *
 * Logout ke baad user ko login screen par bhejna.
 */
Url::redirect(
    'login.php'
);