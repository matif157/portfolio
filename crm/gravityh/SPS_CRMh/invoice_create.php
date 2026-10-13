<?php
/**
 * SPS Professional Services CRM
 * Create Invoice Form
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.manage');

$prefill_client_id = intval($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $client_id = intval($_POST['client_id']);
    $business_id = !empty($_POST['business_id']) ? intval($_POST['business_id']) : null;
    $project_id = !empty($_POST['project_id']) ? intval($_POST['project_id']) : null;
    $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
    $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
    $items = $_POST['item_desc'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];

    if ($client_id > 0 && !empty($items)) {
        $next_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM invoices")->fetchColumn();
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        $total_amount = 0;
        foreach ($items as $idx => $desc) {
            $p = floatval($prices[$idx] ?? 0);
            $q = intval($qtys[$idx] ?? 1);
            $total_amount += ($p * $q);
        }

        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, client_id, business_id, project_id, status, issue_date, due_date, total_amount, paid_amount, balance_due)
            VALUES (?, ?, ?, ?, 'unpaid', ?, ?, ?, 0.00, ?)
        ");
        $stmt->execute([$invoice_number, $client_id, $business_id, $project_id, $issue_date, $due_date, $total_amount, $total_amount]);
        $invoice_id = $pdo->lastInsertId();

        // Insert Line Items
        $stmtItem = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
        foreach ($items as $idx => $desc) {
            if (!empty(trim($desc))) {
                $p = floatval($prices[$idx] ?? 0);
                $q = intval($qtys[$idx] ?? 1);
                $tot = $p * $q;
                $stmtItem->execute([$invoice_id, trim($desc), $q, $p, $tot]);
            }
        }

        log_activity('Create Invoice', 'invoices', $invoice_id, "Generated invoice $invoice_number for total " . format_currency($total_amount));
        set_flash('success', "Invoice $invoice_number created successfully!");
        redirect("invoice_view.php?id=$invoice_id");
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();
$businesses = $pdo->query("SELECT id, business_name, client_id FROM businesses ORDER BY business_name")->fetchAll();
$projects = $pdo->query("SELECT id, title, project_code, client_id FROM projects ORDER BY project_code DESC")->fetchAll();

render_header('Create Invoice', 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Generate New Invoice</h1>
        <p class="page-subtitle">Create client service billing invoice with itemized line items</p>
    </div>
    <div>
        <a href="invoices.php" class="btn btn-secondary">← Back to Invoices</a>
    </div>
</div>

<div class="card" style="max-width:800px; margin:0 auto;">
    <form action="invoice_create.php" method="POST" id="invoiceForm">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Client Owner *</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= $prefill_client_id == $cl['id'] ? 'selected' : '' ?>>
                            <?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Business (Optional)</label>
                <select name="business_id" class="form-control">
                    <option value="">-- Direct Client Invoice --</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['business_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Issue Date *</label>
                <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Payment Due Date *</label>
                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" required>
            </div>
        </div>

        <h3 style="font-size:14px; font-weight:600; margin:20px 0 10px 0;">Invoice Line Items</h3>

        <div id="itemsContainer">
            <div class="form-row item-row" style="margin-bottom:10px;">
                <div style="flex:3;">
                    <input type="text" name="item_desc[]" class="form-control" placeholder="Monthly Bookkeeping Service - Jan 2026" required>
                </div>
                <div style="flex:1;">
                    <input type="number" name="item_qty[]" class="form-control" placeholder="Qty" value="1" required>
                </div>
                <div style="flex:1;">
                    <input type="number" step="0.01" name="item_price[]" class="form-control" placeholder="Price ($)" value="450.00" required>
                </div>
            </div>
        </div>

        <button type="button" onclick="addItemRow()" class="btn btn-sm btn-secondary" style="margin-bottom:20px;">+ Add Line Item</button>

        <div style="margin-top:20px; text-align:right;">
            <button type="submit" class="btn btn-primary">Generate Invoice</button>
        </div>
    </form>
</div>

<script>
function addItemRow() {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.className = 'form-row item-row';
    div.style.marginBottom = '10px';
    div.innerHTML = `
        <div style="flex:3;">
            <input type="text" name="item_desc[]" class="form-control" placeholder="Service Item Description" required>
        </div>
        <div style="flex:1;">
            <input type="number" name="item_qty[]" class="form-control" placeholder="Qty" value="1" required>
        </div>
        <div style="flex:1;">
            <input type="number" step="0.01" name="item_price[]" class="form-control" placeholder="Price ($)" value="100.00" required>
        </div>
    `;
    container.appendChild(div);
}
</script>

<?php render_footer(); ?>
