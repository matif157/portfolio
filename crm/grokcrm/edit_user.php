<?php
require_once 'includes/log_in.php';
require_permission('System', 'users');
$pageTitle = 'Edit User';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: users.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { set_flash('User not found.', 'danger'); header('Location: users.php'); exit; }

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE is_active = 1 ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $username    = trim($_POST['username'] ?? '');
    $role_id     = (int)($_POST['role_id'] ?? 0);
    $designation = trim($_POST['designation'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';

    $check = $pdo->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
    $check->execute([$email, $username, $id]);
    if ($check->fetch()) {
        set_flash('Email or Username already exists.', 'danger');
        header("Location: edit_user.php?id=$id");
        exit;
    }

    if ($password !== '') {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET full_name=?, email=?, username=?, password=?, role_id=?, designation=?, phone=? WHERE id=?")
            ->execute([$full_name, $email, $username, $hashed, $role_id, $designation, $phone, $id]);
    } else {
        $pdo->prepare("UPDATE users SET full_name=?, email=?, username=?, role_id=?, designation=?, phone=? WHERE id=?")
            ->execute([$full_name, $email, $username, $role_id, $designation, $phone, $id]);
    }

    log_activity('update_user', 'Users', "Updated user: $full_name");
    set_flash('User updated successfully.');
    header('Location: users.php');
    exit;
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Edit User</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" value="<?= e($user['username']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" minlength="6">
                        <div class="form-text">Leave blank to keep current password</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $r['id']==$user['role_id']?'selected':'' ?>><?= e($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" class="form-control" value="<?= e($user['designation'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Update User</button>
                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
