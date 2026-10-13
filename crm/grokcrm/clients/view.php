<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Client', 'view');
$pageTitle = 'Client Profile';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();
if (!$client) { set_flash('Client not found.','danger'); header('Location: index.php'); exit; }

// Related data
$invoices = $pdo->prepare("SELECT * FROM invoices WHERE mem_id = ? ORDER BY invoice_date DESC LIMIT 20");
$invoices->execute([$id]);
$invoices = $invoices->fetchAll();

$comments = $pdo->prepare("SELECT c.*, u.full_name FROM client_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.member_id = ? ORDER BY c.comment_time DESC LIMIT 15");
$comments->execute([$id]);
$comments = $comments->fetchAll();

$warnings = $pdo->prepare("SELECT * FROM client_warnings WHERE member_id = ? AND is_active = 1 ORDER BY created_at DESC");
$warnings->execute([$id]);
$warnings = $warnings->fetchAll();

$biz = $pdo->prepare("SELECT * FROM business_details WHERE member_id = ? LIMIT 1");
$biz->execute([$id]);
$business = $biz->fetch();

$outstanding = $pdo->prepare("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE mem_id = ? AND invoice_status IN (1,2)");
$outstanding->execute([$id]);
$outstanding = $outstanding->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($client['member_name']) ?></h4>
        <small class="text-muted"><?= e($client['bus_name'] ?? '') ?></small>
    </div>
    <div class="d-flex gap-2">
        <?php if (has_permission('Client', 'edit')): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <?php if (has_permission('Invoice', 'add')): ?>
        <a href="../invoices/create.php?mem_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-receipt"></i> New Invoice</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Outstanding</div>
                <h4 class="<?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>"><?= money($outstanding) ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Status</div>
                <h5><?= $client['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Email</div>
                <div><?= e($client['prm_email'] ?? '-') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Phone</div>
                <div><?= e($client['cell_ph'] ?: $client['phone'] ?: '-') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <!-- Invoices -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Recent Invoices</strong>
                <a href="../invoices/index.php?mem_id=<?= $id ?>" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">No invoices</td></tr>
                        <?php else: foreach ($invoices as $inv): ?>
                            <tr>
                                <td><a href="../invoices/view.php?id=<?= $inv['id'] ?>">#<?= $inv['id'] ?></a></td>
                                <td><?= $inv['invoice_date'] ? date('d M Y', strtotime($inv['invoice_date'])) : '-' ?></td>
                                <td><?= money($inv['total_amount']) ?></td>
                                <td><?= money($inv['paid_amount']) ?></td>
                                <td class="<?= $inv['balance']>0?'text-danger':'' ?>"><?= money($inv['balance']) ?></td>
                                <td>
                                    <?php
                                    $st = [1=>'Unpaid',2=>'Partial',3=>'Paid',4=>'Cancelled'];
                                    $cls = [1=>'danger',2=>'warning',3=>'success',4=>'secondary'];
                                    $s = $inv['invoice_status'];
                                    echo '<span class="badge bg-'.($cls[$s]??'secondary').'">'.($st[$s]??$s).'</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Comments -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Notes / Comments</strong></div>
            <div class="card-body">
                <?php if (has_permission('Client', 'edit')): ?>
                <form method="POST" action="../notes/add.php" class="mb-3">
                    <input type="hidden" name="member_id" value="<?= $id ?>">
                    <div class="input-group">
                        <input type="text" name="comment_detail" class="form-control" placeholder="Add a note..." required>
                        <button class="btn btn-primary">Add</button>
                    </div>
                </form>
                <?php endif; ?>
                <?php if (empty($comments)): ?>
                    <p class="text-muted small mb-0">No notes yet.</p>
                <?php else: foreach ($comments as $cm): ?>
                    <div class="border-bottom py-2">
                        <div class="small text-muted"><?= e($cm['full_name'] ?? 'User') ?> · <?= date('d M Y H:i', strtotime($cm['comment_time'])) ?></div>
                        <div><?= e($cm['comment_detail']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Warnings -->
        <?php if ($warnings): ?>
        <div class="card border-0 shadow-sm mb-3 border-warning">
            <div class="card-header bg-warning-subtle"><strong>Active Warnings</strong></div>
            <div class="card-body">
                <?php foreach ($warnings as $w): ?>
                    <div class="alert alert-warning py-2 mb-2"><?= e($w['warning']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Business Details -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white"><strong>Business Details</strong></div>
            <div class="card-body small">
                <?php if ($business): ?>
                    <div><strong>President:</strong> <?= e($business['president_name'] ?? '-') ?></div>
                    <div><strong>Bus Address:</strong> <?= e($business['bus_street'] ?? '') ?> <?= e($business['bus_city'] ?? '') ?></div>
                    <div><strong>Client Since:</strong> <?= $business['client_since'] ?? '-' ?></div>
                    <div><strong>Incorp Date:</strong> <?= $business['incorp_date'] ?? '-' ?></div>
                <?php else: ?>
                    <p class="text-muted mb-0">No business details recorded.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contact -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Contact</strong></div>
            <div class="card-body small">
                <div><?= e($client['address'] ?? '') ?></div>
                <div><?= e($client['city'] ?? '') ?> <?= e($client['state'] ?? '') ?> <?= e($client['zip'] ?? '') ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
