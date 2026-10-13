<?php
/**
 * SPS Professional Services CRM
 * View Quotation / Proposal Document
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.view');

$quotation_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT q.*, c.first_name, c.last_name, c.company_name, c.email
    FROM quotations q
    JOIN clients c ON q.client_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$quotation = $stmt->fetch();

if (!$quotation) {
    set_flash('danger', 'Quotation not found.');
    redirect('quotations.php');
}

$items = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$items->execute([$quotation_id]);
$item_list = $items->fetchAll();

render_header("Quotation: {$quotation['quotation_number']}", 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Proposal #<?= e($quotation['quotation_number']) ?></h1>
        <p class="page-subtitle">Client: <?= e($quotation['company_name'] ?: "{$quotation['first_name']} {$quotation['last_name']}") ?> • Valid Until: <?= format_date($quotation['valid_until']) ?></p>
    </div>
    <div>
        <a href="quotations.php" class="btn btn-secondary">← Back to Proposals</a>
    </div>
</div>

<div class="card" style="padding:40px; max-width:800px; margin:0 auto;">
    <h2 style="color:var(--primary); font-size:22px; font-weight:700; margin-bottom:15px;">Professional Service Fee Proposal</h2>

    <div style="margin-bottom:20px; font-size:13px; color:var(--text-muted);">
        Prepared for: <strong><?= e($quotation['company_name'] ?: "{$quotation['first_name']} {$quotation['last_name']}") ?></strong> (<?= e($quotation['email']) ?>)
    </div>

    <table class="table" style="margin-bottom:20px;">
        <thead>
            <tr>
                <th>Service / Scope Description</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Unit Fee</th>
                <th style="text-align:right;">Total Fee</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($item_list as $it): ?>
                <tr>
                    <td><strong><?= e($it['description']) ?></strong></td>
                    <td style="text-align:center;"><?= $it['quantity'] ?></td>
                    <td style="text-align:right;"><?= format_currency($it['unit_price']) ?></td>
                    <td style="text-align:right;"><strong><?= format_currency($it['total_price']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align:right; font-size:18px; font-weight:700; color:var(--primary);">
        Total Estimated Fee: <?= format_currency($quotation['total_amount']) ?>
    </div>
</div>

<?php render_footer(); ?>
