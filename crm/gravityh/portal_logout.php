<?php
/**
 * GRAVITY CRM - Client Portal Logout
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/core/portal.php';

if (portal_user()) {
    log_security_event('portal_logout', 'Client portal logout', (int)portal_user()['id'], 'info');
}
unset($_SESSION['portal_uid'], $_SESSION['portal_meta']);
redirect('portal_login.php');