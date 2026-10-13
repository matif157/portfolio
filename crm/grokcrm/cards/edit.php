<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Card', 'manage');
$pageTitle = 'Edit Credit Card';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM credit_cards WHERE id = ?");
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) { set_flash('Not found.','danger'); header('Location: index.php'); exit; }

$members = $pdo->query("SELECT id, member_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();
$types = $pdo->query("SELECT * FROM card_types ORDER BY id")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save history snapshot
    $pdo->prepare("INSERT INTO credit_card_history (card_id, edited_by, edit_date, card_name, card_no, card_exp_month, card_exp_year, card_cvv, card_zip, card_address) 
                   VALUES (?,?,NOW(),?,?,?,?,?,?,?)")
        ->execute([$id, $_SESSION['user_id'], $card['card_onname'], $card['card_number'], $card['card_exp_month'], $card['card_exp_year'], $card['card_cvv'], $card['card_zip'], $card['card_address']]);

    $pdo->prepare("UPDATE credit_cards SET mem_id=?, card_type=?, card_onname=?, card_number=?, card_exp_month=?, card_exp_year=?, card_cvv=?, card_zip=?, card_address=? WHERE id=?")
        ->execute([
            (int)($_POST['mem_id'] ?? 0) ?: null,
            (int)($_POST['card_type'] ?? 0) ?: null,
            trim($_POST['card_onname'] ?? ''),
            trim($_POST['card_number'] ?? ''),
            (int)($_POST['card_exp_month'] ?? 0) ?: null,
            (int)($_POST['card_exp_year'] ?? 0) ?: null,
            trim($_POST['card_cvv'] ?? ''),
            trim($_POST['card_zip'] ?? ''),
            trim($_POST['card_address'] ?? ''),
            $id
        ]);
    log_activity('update_card', 'Card', "Updated credit card ID $id");
    set_flash('Card updated.');
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Edit Credit Card</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Client</label>
                        <select name="mem_id" class="form-select">
                            <option value="">—</option>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $card['mem_id']==$m['id']?'selected':'' ?>><?= e($m['member_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cardholder Name</label>
                        <input type="text" name="card_onname" class="form-control" value="<?= e($card['card_onname'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Type</label>
                            <select name="card_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $card['card_type']==$t['id']?'selected':'' ?>><?= e($t['card_type_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Card Number</label>
                            <input type="text" name="card_number" class="form-control" value="<?= e($card['card_number'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <label class="form-label">Exp Month</label>
                            <input type="number" name="card_exp_month" class="form-control" value="<?= $card['card_exp_month'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Exp Year</label>
                            <input type="number" name="card_exp_year" class="form-control" value="<?= $card['card_exp_year'] ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">CVV</label>
                            <input type="text" name="card_cvv" class="form-control" value="<?= e($card['card_cvv'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="card_zip" class="form-control" value="<?= e($card['card_zip'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Billing Address</label>
                        <input type="text" name="card_address" class="form-control" value="<?= e($card['card_address'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Update</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
