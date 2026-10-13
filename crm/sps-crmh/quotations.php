<?php
/**
 * SPS Professional Services CRM
 * Quotations & Fee Proposals List
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.view');

$quotations = $pdo->query("
    SELECT q.*, c.first_name, c.last_name, c.company_name
    FROM quotations q
    JOIN clients c ON q.client_id = c.id
    ORDER BY q.created_at DESC
")->fetchAll();

render_header('Quotations & Proposals', 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Service Quotations & Proposals</h1>
        <p class="page-subtitle">Generate fee estimates and professional service proposals for clients</p>
    </div>
    <div>
        <a href="quotation_create.php" class="btn btn-primary">+ Create New Quotation</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Client Entity</th>
                    <th>Total Estimate</th>
                    <th>Valid Until</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No quotations generated.</td></tr>
                <?php else: ?>
                    <?php foreach ($quotations as $q): ?>
                        <tr>
                            <td><a href="quotation_view.php?id=<?= $q['id'] ?>"><strong><?= e($q['quotation_number']) ?></strong></a></td>
                            <td><?= e($q['company_name'] ?: "{$q['first_name']} {$q['last_name']}") ?></td>
                            <td><strong><?= format_currency($q['total_amount']) ?></strong></td>
                            <td><?= format_date($q['valid_until']) ?></td>
                            <td><span class="badge badge-info"><?= ucfirst($q['status']) ?></span></td>
                            <td>
                                <a href="quotation_view.php?id=<?= $q['id'] ?>" class="btn btn-sm btn-secondary">View Proposal</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
