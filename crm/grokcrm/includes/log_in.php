<?php
// ============================================================
// FILE: includes/log_in.php - Security Gatekeeper
// Place at the top of every protected page
// ============================================================

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Fetch current logged-in user + role
$sql = "SELECT u.id, u.full_name, u.username, u.email, u.role_id, u.designation, 
               u.is_active, r.role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? 
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch();

if (!$currentUser || $currentUser['is_active'] != 1) {
    session_destroy();
    header("Location: index.php");
    exit();
}

$_SESSION['role_name'] = $currentUser['role_name'];
$_SESSION['role_id']   = $currentUser['role_id'];
$_SESSION['user_name'] = $currentUser['full_name'];
