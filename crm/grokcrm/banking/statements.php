<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Banking', 'view');
$pageTitle = 'Bank Statements';

$bank_id = (int)($_GET['bank_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('Banking', 'manage')) {
    $pdo->prepare("INSERT INTO bank_statements (member_id, bank_id, statement_month, statement_year, received, received_date, received_by, notes) 
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            (int)($_POST['member_id'] ?? 0) ?: null,
            (int)($_POST['bank_id'] ?? 0) ?: null,
            (int)($_POST['statement_month'] ?? 0) ?: null,
            (int)($_POST['statement_year'] ?? 0) ?: null,
            isset($_POST['received']) ? 1 : 0,
            $_POST['received_date'] ?? null,
            $_SESSION['user_id'],
            trim($_POST['notes'] ?? '')
        ]);
    log_activity('add_statement', 'Banking', 'Added bank statement record');
    set_flash('Statement record added.');
    header('Location: statements.php' . ($bank_id ? "?bank_id=$bank_id" : ''));
    exit;
}

$where = $bank_id ? "WHERE s.bank_id = $bank_id" : '';
$statements = $pdo->query("SELECT s.*, b.bank_name, m.member_name 
                           FROM bank_statements s 
                           LEFT JOIN bank_accounts b ON s.bank_id = b.id 
                           LEFT JOIN members m ON s.member_id = m.id 
                           $where ORDER BY s.statement_year DESC, s.statement_month DESC")->fetchAll();

$banks = $pdo->query("SELECT id, bank_name FROM bank_accounts ORDER BY bank_name")->fetchAll();
$members = $pdo->query("SELECT id, member_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Bank Accounts</a>
    <?php if (has_permission('Banking', 'manage')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Statement</button>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-dark">
                <tr><th>Bank</th><th>Client</th><th>Month/Year</th><th>Received</th><th>Date</th><th>Notes</th></tr>
            </thead>
            <tbody>
                <?php if (empty($statements)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No statements</td></tr>
                <?php else: foreach ($statements as $s): ?>
                    <tr>
                        <td><?= e($s['bank_name'] ?? '-') ?></td>
                        <td><?= e($s['member_name'] ?? '-') ?></td>
                        <td><?= $s['statement_month'] ?>/<?= $s['statement_year'] ?></td>
                        <td><?= $s['received'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
                        <td class="small"><?= $s['received_date'] ?? '-' ?></td>
                        <td class="small"><?= e($s['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Statement Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Bank</label>
                    <select name="bank_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $bank_id==$b['id']?'selected':'' ?>><?= e($b['bank_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Client</label>
                    <select name="member_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['member_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Month</label>
                        <input type="number" name="statement_month" class="form-control" min="1" max="12" value="<?= date('n') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Year</label>
                        <input type="number" name="statement_year" class="form-control" value="<?= date('Y') ?>">
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="received" id="received" checked>
                        <label class="form-check-label" for="received">Received</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Received Date</label>
                    <input type="date" name="received_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
