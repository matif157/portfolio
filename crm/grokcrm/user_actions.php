<?php
require_once 'includes/log_in.php';
require_permission('System', 'users');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $role_id     = (int)($_POST['role_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');

    if (!$full_name || !$email || !$username || !$password || $role_id <= 0) {
        set_flash('All required fields must be filled.', 'danger');
        header('Location: users.php');
        exit;
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->execute([$email, $username]);
    if ($check->fetch()) {
        set_flash('Email or Username already exists.', 'danger');
        header('Location: users.php');
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password, role_id, designation, phone) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$full_name, $email, $username, $hashed, $role_id, $designation, $phone]);

    log_activity('create_user', 'Users', "Created user: $full_name");
    set_flash('User added successfully.');
    header('Location: users.php');
    exit;
}

if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash('You cannot deactivate yourself.', 'warning');
    } else {
        $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        log_activity('toggle_user', 'Users', "Toggled status for user ID: $id");
        set_flash('User status updated.');
    }
    header('Location: users.php');
    exit;
}

header('Location: users.php');
exit;
