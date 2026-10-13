<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Banking', 'manage');
$pageTitle = 'Edit Bank Account';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id = ?");
$stmt->execute([$id]);
$acc = $stmt->fetch();
if (!$acc) { set_flash('Not found.','danger'); header('Location: index.php'); exit; }

$members = $pdo->query("SELECT id, member_name, bus_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE bank_accounts SET bank_name=?, account_no=?, routing_no=?, bank_user_name=?, bank_password=?, client_id=?, date_updated=NOW() WHERE id=?")
        ->execute([
            trim($_POST['bank_name'] ?? ''),
            trim($_POST['account_no'] ?? ''),
            trim($_POST['routing_no'] ?? ''),
            trim($_POST['bank_user_name'] ?? ''),
            trim($_POST['bank_password'] ?? ''),
            (int)($_POST['client_id'] ?? 0) ?: null,
            $id
        ]);
    log_activity('update_bank', 'Banking', "Updated bank account ID $id");
    set_flash('Bank account updated.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Edit Bank Account</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Bank Name *</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= e($acc['bank_name']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_no" class="form-control" value="<?= e($acc['account_no'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Routing Number</label>
                            <input type="text" name="routing_no" class="form-control" value="<?= e($acc['routing_no'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Login Username</label>
                            <input type="text" name="bank_user_name" class="form-control" value="<?= e($acc['bank_user_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Login Password</label>
                            <input type="text" name="bank_password" class="form-control" value="<?= e($acc['bank_password'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Linked Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $acc['client_id']==$m['id']?'selected':'' ?>><?= e($m['member_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
