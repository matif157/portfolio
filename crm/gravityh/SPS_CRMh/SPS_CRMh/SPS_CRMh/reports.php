<?php
/**
 * SPS Professional Services CRM
 * Operational & Financial Reporting Suite
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

// Summary Metrics
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_invoiced = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices")->fetchColumn();
$total_paid = $pdo->query("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices")->fetchColumn();
$received_statements = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE status = 'received'")->fetchColumn();
$missing_statements = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE status = 'missing'")->fetchColumn();

render_header('Executive Reports', 'reports');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Executive & Service Performance Reports</h1>
        <p class="page-subtitle">Analytics across CRM client activity, revenue collection, and statement automation</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Total Clients</span>
        <span class="stat-value"><?= number_format($total_clients) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Total Invoiced</span>
        <span class="stat-value"><?= format_currency($total_invoiced) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Collected Revenue</span>
        <span class="stat-value" style="color:var(--success);"><?= format_currency($total_paid) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Statement Automation Rate</span>
        <?php $tot_st = $received_statements + $missing_statements; $rate = $tot_st > 0 ? round(($received_statements / $tot_st) * 100, 1) : 0; ?>
        <span class="stat-value" style="color:var(--accent);"><?= $rate ?>%</span>
        <span class="stat-desc"><?= $received_statements ?> received vs <?= $missing_statements ?> missing</span>
    </div>
</div>

<div class="card">
    <div class="card-title">Service Performance Breakdown</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Price Fee</th>
                    <th>Active Executions</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Bank Statement Collection & Monthly Bookkeeping Preparation</strong></td>
                    <td><strong>$450.00 / mo</strong></td>
                    <td><span class="badge badge-success">1 Active Delivery</span></td>
                    <td><span class="badge badge-success">FULLY AUTOMATED</span></td>
                </tr>
                <tr>
                    <td>Corporate Tax Return Filing (Form 1120/1120S)</td>
                    <td>$1,200.00 / yr</td>
                    <td><span class="badge badge-secondary">0 Active</span></td>
                    <td><span class="badge badge-info">Standard Workflow</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
