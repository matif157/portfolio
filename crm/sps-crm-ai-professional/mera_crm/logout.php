<?php
// =============================================
// FILE: logout.php
// PURPOSE: Destroy session and log user out
// =============================================

require_once 'log_in.php';

if (isset($_SESSION['user_id'])) {
    log_activity($conn, 'Auth', 'Logout', $_SESSION['user_id']);
}

// Clear all session data
$_SESSION = [];
session_destroy();

header("Location: index.php");
exit();
