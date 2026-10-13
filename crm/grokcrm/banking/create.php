<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Banking', 'manage');
$pageTitle = 'Add Bank Account';

$members = $pdo->query("SELECT id, member_name, bus_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_no = trim($_POST['account_no'] ?? '');
    $routing_no = trim($_POST['routing_no'] ?? '');
    $bank_user_name = trim($_POST['bank_user_name'] ?? '');
    $bank_password = trim($_POST['bank_password'] ?? '');
    $client_id = (int)($_POST['client_id'] ?? 0) ?: null;

    if ($bank_name === '') {
        set_flash('Bank name is required.', 'danger');
        header('Location: create.php'); exit;
    }

    $pdo->prepare("INSERT INTO bank_accounts (bank_name, account_no, routing_no, bank_user_name, bank_password, client_id, added_by, date_added) 
                   VALUES (?,?,?,?,?,?,?,NOW())")
        ->execute([$bank_name, $account_no, $routing_no, $bank_user_name, $bank_password, $client_id, $_SESSION['user_id']]);

    log_activity('create_bank', 'Banking', "Added bank account: $bank_name");
    set_flash('Bank account added.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Add Bank Account</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Bank Name *</label>
                        <input type="text" name="bank_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_no" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Routing Number</label>
                            <input type="text" name="routing_no" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Login Username</label>
                            <input type="text" name="bank_user_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Login Password</label>
                            <input type="text" name="bank_password" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Linked Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['member_name']) ?><?= $m['bus_name']?' — '.e($m['bus_name']):'' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
