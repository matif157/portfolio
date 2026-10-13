<?php
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    log_activity('logout', 'Auth', 'User logged out');
}

session_destroy();
header('Location: index.php');
exit;
