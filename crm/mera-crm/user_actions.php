<?php
// =============================================
// FILE: user_actions.php
// PURPOSE: Handle Add User + Toggle Active/Inactive
// =============================================

require_once 'log_in.php';

$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed_roles)) {
    exit('Access Denied');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ===================== ADD NEW USER =====================
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $role_id     = (int)($_POST['role_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($full_name) || empty($email) || empty($username) || empty($password) || $role_id <= 0) {
        $_SESSION['msg'] = 'Please fill all required fields.';
        $_SESSION['msg_type'] = 'danger';
        header('Location: users.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['msg'] = 'Password must be at least 6 characters.';
        $_SESSION['msg_type'] = 'danger';
        header('Location: users.php');
        exit;
    }

    // Duplicate check
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->execute([$email, $username]);
    if ($check->fetch()) {
        $_SESSION['msg'] = 'Email or Username already exists!';
        $_SESSION['msg_type'] = 'danger';
        header('Location: users.php');
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, email, username, password, role_id, designation, phone) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$full_name, $email, $username, $hashed, $role_id, $designation, $phone]);
        
        // Activity Log
        if (function_exists('log_activity')) {
            log_activity($pdo, 'Users', 'Created', $pdo->lastInsertId(), [
                'full_name' => $full_name,
                'username'  => $username
            ]);
        }

        $_SESSION['msg'] = 'Staff added successfully!';
        $_SESSION['msg_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['msg'] = 'Error: ' . $e->getMessage();
        $_SESSION['msg_type'] = 'danger';
    }

    header('Location: users.php');
    exit;
}

// ===================== TOGGLE ACTIVE =====================
if ($action === 'toggle' && isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    // Khud ko deactivate na kar sake
    if ($id === (int)$_SESSION['user_id']) {
        $_SESSION['msg'] = 'You cannot deactivate your own account!';
        $_SESSION['msg_type'] = 'warning';
        header('Location: users.php');
        exit;
    }

    $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);

    if (function_exists('log_activity')) {
        log_activity($pdo, 'Users', 'Status Changed', $id);
    }

    $_SESSION['msg'] = 'User status updated!';
    $_SESSION['msg_type'] = 'success';
    header('Location: users.php');
    exit;
}

header('Location: users.php');
exit;