<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Invoice', 'add');
$pageTitle = 'Create Invoice';

$mem_id = (int)($_GET['mem_id'] ?? 0);
$members = $pdo->query("SELECT id, member_name, bus_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mem_id      = (int)($_POST['mem_id'] ?? 0);
    $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
    $due_date    = $_POST['due_date'] ?? null;
    $discount    = (float)($_POST['discount'] ?? 0);
    $notes       = trim($_POST['notes'] ?? '');

    // Line items
    $descriptions = $_POST['item_description'] ?? [];
    $prices       = $_POST['unit_price'] ?? [];
    $qtys         = $_POST['quantity'] ?? [];

    $total = 0;
    $items = [];
    for ($i = 0; $i < count($descriptions); $i++) {
        $desc = trim($descriptions[$i] ?? '');
        if ($desc === '') continue;
        $price = (float)($prices[$i] ?? 0);
        $qty   = (float)($qtys[$i] ?? 1);
        $line  = $price * $qty;
        $total += $line;
        $items[] = compact('desc', 'price', 'qty', 'line');
    }

    $total = max(0, $total - $discount);
    $balance = $total;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO invoices (mem_id, invoice_date, due_date, total_amount, paid_amount, balance, invoice_status, discount, last_edited_by) 
                               VALUES (?,?,?,?,0,?,1,?,?)");
        $stmt->execute([$mem_id ?: null, $invoice_date, $due_date ?: null, $total, $balance, $discount, $_SESSION['user_id']]);
        $invId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("INSERT INTO invoice_items (inv_id, mem_id, item_description, unit_price, quantity, total_price, user_id) VALUES (?,?,?,?,?,?,?)");
        foreach ($items as $it) {
            $itemStmt->execute([$invId, $mem_id ?: null, $it['desc'], $it['price'], $it['qty'], $it['line'], $_SESSION['user_id']]);
        }

        $pdo->commit();
        log_activity('create_invoice', 'Invoice', "Created invoice #$invId total " . money($total));
        set_flash("Invoice #$invId created successfully.");
        header("Location: view.php?id=$invId");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('Error creating invoice: ' . $e->getMessage(), 'danger');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Create New Invoice</div>
            <div class="card-body">
                <form method="POST" id="invoiceForm">
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Client *</label>
                            <select name="mem_id" class="form-select" required>
                                <option value="">Select Client</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $mem_id==$m['id']?'selected':'' ?>>
                                        <?= e($m['member_name']) ?><?= $m['bus_name'] ? ' — '.e($m['bus_name']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" name="discount" class="form-control" value="0">
                        </div>
                    </div>

                    <h6>Line Items</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50%">Description</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" name="item_description[]" class="form-control form-control-sm" required></td>
                                    <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm price" value="0"></td>
                                    <td><input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm qty" value="1"></td>
                                    <td class="line-total">0.00</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addRow">+ Add Line</button>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">Create Invoice</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('addRow').addEventListener('click', function() {
    const tbody = document.querySelector('#itemsTable tbody');
    const row = tbody.rows[0].cloneNode(true);
    row.querySelectorAll('input').forEach(i => { if(i.type!=='number') i.value=''; else if(i.classList.contains('qty')) i.value=1; else i.value=0; });
    row.querySelector('.line-total').textContent = '0.00';
    tbody.appendChild(row);
});
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-row')) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) e.target.closest('tr').remove();
    }
});
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('price') || e.target.classList.contains('qty')) {
        const row = e.target.closest('tr');
        const p = parseFloat(row.querySelector('.price').value) || 0;
        const q = parseFloat(row.querySelector('.qty').value) || 0;
        row.querySelector('.line-total').textContent = (p*q).toFixed(2);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
