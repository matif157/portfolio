<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Report', 'view');
$pageTitle = 'Reports';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

// Summary stats
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total, COALESCE(SUM(paid_amount),0) as paid, COALESCE(SUM(balance),0) as balance 
                       FROM invoices WHERE invoice_date BETWEEN ? AND ?");
$stmt->execute([$from, $to . ' 23:59:59']);
$stats['invoices'] = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
$stmt->execute([$from, $to]);
$stats['payments'] = $stmt->fetch();

$stmt = $pdo->query("SELECT COUNT(*) as cnt, COALESCE(SUM(balance),0) as total FROM invoices WHERE invoice_status IN (1,2) AND balance > 0");
$stats['outstanding'] = $stmt->fetch();

// Top outstanding clients
$top = $pdo->query("SELECT m.id, m.member_name, m.bus_name, SUM(i.balance) as bal, COUNT(i.id) as inv_count
                    FROM invoices i 
                    JOIN members m ON i.mem_id = m.id 
                    WHERE i.invoice_status IN (1,2) AND i.balance > 0
                    GROUP BY m.id 
                    ORDER BY bal DESC 
                    LIMIT 15")->fetchAll();

// Recent payments
$recentPay = $pdo->query("SELECT p.*, m.member_name, i.id as inv_id 
                          FROM payments p 
                          LEFT JOIN members m ON p.mem_id = m.id 
                          LEFT JOIN invoices i ON p.invoice_id = i.id 
                          ORDER BY p.payment_date DESC, p.id DESC LIMIT 15")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<form class="row g-2 mb-4 align-items-end" method="GET">
    <div class="col-auto">
        <label class="form-label small mb-0">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary">Apply</button>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Invoices (period)</div>
                <h4><?= (int)$stats['invoices']['cnt'] ?></h4>
                <div class="small">Total: <?= money($stats['invoices']['total']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Collected (period)</div>
                <h4 class="text-success"><?= money($stats['payments']['total']) ?></h4>
                <div class="small"><?= (int)$stats['payments']['cnt'] ?> payments</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Outstanding Balance</div>
                <h4 class="text-danger"><?= money($stats['outstanding']['total']) ?></h4>
                <div class="small"><?= (int)$stats['outstanding']['cnt'] ?> open invoices</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Paid in period</div>
                <h4><?= money($stats['invoices']['paid']) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Top Outstanding Clients</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Client</th><th>Invoices</th><th>Balance</th></tr></thead>
                    <tbody>
                        <?php if (empty($top)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">None</td></tr>
                        <?php else: foreach ($top as $t): ?>
                            <tr>
                                <td><a href="../clients/view.php?id=<?= $t['id'] ?>"><?= e($t['member_name']) ?></a>
                                    <?php if ($t['bus_name']): ?><br><small class="text-muted"><?= e($t['bus_name']) ?></small><?php endif; ?>
                                </td>
                                <td><?= $t['inv_count'] ?></td>
                                <td class="text-danger fw-bold"><?= money($t['bal']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Recent Payments</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Client</th><th>Invoice</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentPay)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-3">None</td></tr>
                        <?php else: foreach ($recentPay as $p): ?>
                            <tr>
                                <td class="small"><?= $p['payment_date'] ? date('d M Y', strtotime($p['payment_date'])) : '-' ?></td>
                                <td><?= e($p['member_name'] ?? '-') ?></td>
                                <td><?php if ($p['inv_id']): ?><a href="../invoices/view.php?id=<?= $p['inv_id'] ?>">#<?= $p['inv_id'] ?></a><?php else: ?>-<?php endif; ?></td>
                                <td class="text-success"><?= money($p['amount']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
