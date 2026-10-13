<?php
/**
 * SPS Professional Services CRM
 * Create Quotation / Proposal
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $client_id = intval($_POST['client_id']);
    $valid_until = $_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
    $items = $_POST['item_desc'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];

    if ($client_id > 0 && !empty($items)) {
        $next_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM quotations")->fetchColumn();
        $quotation_number = 'QT-' . date('Y') . '-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        $total_amount = 0;
        foreach ($items as $idx => $desc) {
            $p = floatval($prices[$idx] ?? 0);
            $q = intval($qtys[$idx] ?? 1);
            $total_amount += ($p * $q);
        }

        $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, client_id, status, total_amount, valid_until) VALUES (?, ?, 'sent', ?, ?)");
        $stmt->execute([$quotation_number, $client_id, $total_amount, $valid_until]);
        $quotation_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO quotation_items (quotation_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $idx => $desc) {
            if (!empty(trim($desc))) {
                $p = floatval($prices[$idx] ?? 0);
                $q = intval($qtys[$idx] ?? 1);
                $tot = $p * $q;
                $stmtItem->execute([$quotation_id, trim($desc), $q, $p, $tot]);
            }
        }

        log_activity('Create Quotation', 'invoices', $quotation_id, "Generated quotation $quotation_number");
        set_flash('success', "Quotation $quotation_number created!");
        redirect("quotation_view.php?id=$quotation_id");
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();

render_header('Create Quotation', 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Generate Fee Proposal</h1>
        <p class="page-subtitle">Create a formal service quotation for prospective or existing clients</p>
    </div>
    <div>
        <a href="quotations.php" class="btn btn-secondary">← Back to Quotations</a>
    </div>
</div>

<div class="card" style="max-width:750px; margin:0 auto;">
    <form action="quotation_create.php" method="POST">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Client Owner *</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Proposal Valid Until</label>
                <input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
            </div>
        </div>

        <h3 style="font-size:14px; font-weight:600; margin:15px 0 10px 0;">Proposed Services & Fees</h3>

        <div class="form-row" style="margin-bottom:10px;">
            <div style="flex:3;">
                <input type="text" name="item_desc[]" class="form-control" placeholder="Annual Corporate Tax Preparation & Bookkeeping Setup" required>
            </div>
            <div style="flex:1;">
                <input type="number" name="item_qty[]" class="form-control" value="1" required>
            </div>
            <div style="flex:1;">
                <input type="number" step="0.01" name="item_price[]" class="form-control" value="1200.00" required>
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Generate Proposal</button>
        </div>
    </form>
</div>

<?php render_footer(); ?>
