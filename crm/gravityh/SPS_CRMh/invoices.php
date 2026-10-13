<?php
/**
 * SPS Professional Services CRM
 * Invoices Directory & Receivable Management
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.view');

$status_filter = trim($_GET['status'] ?? '');

$query = "
    SELECT i.*, c.first_name, c.last_name, c.company_name, b.business_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN businesses b ON i.business_id = b.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== '') {
    $query .= " AND i.status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY i.issue_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// KPI Receivables Totals
$total_invoiced = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices")->fetchColumn();
$total_paid = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices")->fetchColumn();
$total_unpaid = $pdo->query("SELECT COALESCE(SUM(balance_due), 0) FROM invoices WHERE status IN ('unpaid', 'overdue', 'partially_paid')")->fetchColumn();

render_header('Invoices Directory', 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Invoices & Receivables</h1>
        <p class="page-subtitle">Manage client billing, service invoicing, and payment collection tracking</p>
    </div>
    <div>
        <a href="invoice_create.php" class="btn btn-primary">+ Create New Invoice</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Total Invoiced</span>
        <span class="stat-value"><?= format_currency($total_invoiced) ?></span>
        <span class="stat-desc">Billed service value</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Collected</span>
        <span class="stat-value" style="color:var(--success);"><?= format_currency($total_paid) ?></span>
        <span class="stat-desc">Received client payments</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Outstanding Receivables</span>
        <span class="stat-value" style="color:var(--danger);"><?= format_currency($total_unpaid) ?></span>
        <span class="stat-desc">Unpaid & overdue balances</span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Client / Entity</th>
                    <th>Issue Date</th>
                    <th>Due Date</th>
                    <th>Total Amount</th>
                    <th>Paid Amount</th>
                    <th>Balance Due</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="9" style="text-align:center; color:var(--text-muted);">No invoices recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><a href="invoice_view.php?id=<?= $inv['id'] ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
                            <td>
                                <strong><?= e($inv['company_name'] ?: "{$inv['first_name']} {$inv['last_name']}") ?></strong>
                                <?php if ($inv['business_name']): ?>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= e($inv['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= format_date($inv['issue_date']) ?></td>
                            <td><?= format_date($inv['due_date']) ?></td>
                            <td><?= format_currency($inv['total_amount']) ?></td>
                            <td><span style="color:var(--success); font-weight:600;"><?= format_currency($inv['paid_amount']) ?></span></td>
                            <td><strong style="color:<?= $inv['balance_due'] > 0 ? 'var(--danger)' : 'inherit' ?>"><?= format_currency($inv['balance_due']) ?></strong></td>
                            <td>
                                <?php
                                $badge = 'badge-secondary';
                                if ($inv['status'] === 'paid') $badge = 'badge-success';
                                if ($inv['status'] === 'unpaid') $badge = 'badge-danger';
                                if ($inv['status'] === 'overdue') $badge = 'badge-danger';
                                ?>
                                <span class="badge <?= $badge ?>"><?= ucfirst($inv['status']) ?></span>
                            </td>
                            <td>
                                <a href="invoice_view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-secondary">View / Pay</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
