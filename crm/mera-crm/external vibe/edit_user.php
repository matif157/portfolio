<?php
// =============================================
// FILE: edit_user.php
// =============================================

require_once 'log_in.php';

$allowed_roles = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'], $allowed_roles)) {
    exit('Access Denied');
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE is_active = 1")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $username    = trim($_POST['username']);
    $role_id     = (int)$_POST['role_id'];
    $designation = trim($_POST['designation']);
    $phone       = trim($_POST['phone']);
    $password    = $_POST['password'] ?? '';

    $sql = "UPDATE users SET full_name=?, email=?, username=?, role_id=?, designation=?, phone=?";
    $params = [$full_name, $email, $username, $role_id, $designation, $phone];

    if (!empty($password)) {
        $sql .= ", password=?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id=?";
    $params[] = $id;

    $pdo->prepare($sql)->execute($params);

    $_SESSION['msg'] = 'User updated successfully!';
    $_SESSION['msg_type'] = 'success';
    header('Location: users.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require_once 'sidebar.php'; ?>

<div style="margin-left:260px; padding:40px;">
    <h3>Edit User</h3>

    <form method="POST" class="card p-4 shadow-sm" style="max-width:600px;">
        <div class="mb-3">
            <label>Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Username</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
        </div>
        <div class="mb-3">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control">
        </div>
        <div class="mb-3">
            <label>Role</label>
            <select name="role_id" class="form-select" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $role['id'] == $user['role_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Designation</label>
            <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($user['designation'] ?? '') ?>">
        </div>
        <div class="mb-3">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-primary">Update User</button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
    </form>
<<?php require_once 'sidebar.php'; ?>

<div style="margin-left:260px; padding:40px;">
    <!-- Page content yahan -->
</div></div>
</body>
</html>