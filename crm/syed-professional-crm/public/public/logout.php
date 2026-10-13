<?php
/**
 * =====================================================
 * FILE: logout.php
 * PURPOSE: Current SPS user ka session safely end karna
 *
 * WORKFLOW:
 *
 * Logout click
 *     ↓
 * Session destroy
 *     ↓
 * Login page
 * =====================================================
 */

require_once dirname(__DIR__) . '/config/config.php';


/*
|--------------------------------------------------------------------------
| SESSION DATA REMOVE
|--------------------------------------------------------------------------
|
| $_SESSION = PHP built-in session array.
|
| Empty karne se current user's login information remove
| ho jati hai.
|
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| SESSION DESTROY
|--------------------------------------------------------------------------
|
| session_destroy() = PHP built-in function.
|
| Why:
| Server-side login session ko terminate karta hai.
|
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| LOGIN PAGE
|--------------------------------------------------------------------------
*/

header(
    'Location: ' . APP_URL . '/login.php'
);

exit();