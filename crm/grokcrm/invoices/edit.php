<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Invoice', 'edit');
$pageTitle = 'Edit Invoice';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) { set_flash('Invoice not found.','danger'); header('Location: index.php'); exit; }

$items = $pdo->prepare("SELECT * FROM invoice_items WHERE inv_id = ? ORDER BY id");
$items->execute([$id]);
$items = $items->fetchAll();

$members = $pdo->query("SELECT id, member_name, bus_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mem_id       = (int)($_POST['mem_id'] ?? 0);
    $invoice_date = $_POST['invoice_date'] ?? null;
    $due_date     = $_POST['due_date'] ?? null;
    $discount     = (float)($_POST['discount'] ?? 0);
    $status       = (int)($_POST['invoice_status'] ?? 1);

    $descriptions = $_POST['item_description'] ?? [];
    $prices       = $_POST['unit_price'] ?? [];
    $qtys         = $_POST['quantity'] ?? [];
    $item_ids     = $_POST['item_id'] ?? [];

    $total = 0;
    $newItems = [];
    for ($i = 0; $i < count($descriptions); $i++) {
        $desc = trim($descriptions[$i] ?? '');
        if ($desc === '') continue;
        $price = (float)($prices[$i] ?? 0);
        $qty   = (float)($qtys[$i] ?? 1);
        $line  = $price * $qty;
        $total += $line;
        $newItems[] = [
            'id' => (int)($item_ids[$i] ?? 0),
            'desc' => $desc,
            'price' => $price,
            'qty' => $qty,
            'line' => $line
        ];
    }
    $total = max(0, $total - $discount);
    $paid  = $inv['paid_amount'];
    $balance = max(0, $total - $paid);
    if ($balance <= 0) $status = 3;
    elseif ($paid > 0) $status = 2;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE invoices SET mem_id=?, invoice_date=?, due_date=?, total_amount=?, balance=?, invoice_status=?, discount=?, last_edited_by=? WHERE id=?")
            ->execute([$mem_id ?: null, $invoice_date, $due_date ?: null, $total, $balance, $status, $discount, $_SESSION['user_id'], $id]);

        // Simple approach: delete old items and re-insert
        $pdo->prepare("DELETE FROM invoice_items WHERE inv_id = ?")->execute([$id]);
        $itemStmt = $pdo->prepare("INSERT INTO invoice_items (inv_id, mem_id, item_description, unit_price, quantity, total_price, user_id) VALUES (?,?,?,?,?,?,?)");
        foreach ($newItems as $it) {
            $itemStmt->execute([$id, $mem_id ?: null, $it['desc'], $it['price'], $it['qty'], $it['line'], $_SESSION['user_id']]);
        }

        $pdo->commit();
        log_activity('update_invoice', 'Invoice', "Updated invoice #$id");
        set_flash('Invoice updated.');
        header("Location: view.php?id=$id");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('Error: ' . $e->getMessage(), 'danger');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">Edit Invoice #<?= $id ?></div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Client</label>
                            <select name="mem_id" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $inv['mem_id']==$m['id']?'selected':'' ?>>
                                        <?= e($m['member_name']) ?><?= $m['bus_name']?' — '.e($m['bus_name']):'' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= $inv['invoice_date'] ? date('Y-m-d', strtotime($inv['invoice_date'])) : '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= $inv['due_date'] ?? '' ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" name="discount" class="form-control" value="<?= $inv['discount'] ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">Status</label>
                            <select name="invoice_status" class="form-select">
                                <option value="1" <?= $inv['invoice_status']==1?'selected':'' ?>>Unpaid</option>
                                <option value="2" <?= $inv['invoice_status']==2?'selected':'' ?>>Partial</option>
                                <option value="3" <?= $inv['invoice_status']==3?'selected':'' ?>>Paid</option>
                                <option value="4" <?= $inv['invoice_status']==4?'selected':'' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <h6>Line Items</h6>
                    <table class="table table-sm" id="itemsTable">
                        <thead class="table-light"><tr><th>Description</th><th>Price</th><th>Qty</th><th></th></tr></thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                            <tr>
                                <td><input type="text" name="item_description[]" class="form-control form-control-sm"></td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm" value="0"></td>
                                <td><input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm" value="1"></td>
                                <td></td>
                            </tr>
                            <?php else: foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="item_id[]" value="<?= $it['id'] ?>">
                                    <input type="text" name="item_description[]" class="form-control form-control-sm" value="<?= e($it['item_description']) ?>">
                                </td>
                                <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm" value="<?= $it['unit_price'] ?>"></td>
                                <td><input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm" value="<?= $it['quantity'] ?>"></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="addRow">+ Add Line</button>

                    <div>
                        <button type="submit" class="btn btn-primary">Update Invoice</button>
                        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('addRow').addEventListener('click', function() {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="hidden" name="item_id[]" value="0"><input type="text" name="item_description[]" class="form-control form-control-sm"></td>
                    <td><input type="number" step="0.01" name="unit_price[]" class="form-control form-control-sm" value="0"></td>
                    <td><input type="number" step="0.01" name="quantity[]" class="form-control form-control-sm" value="1"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>`;
    tbody.appendChild(tr);
});
document.addEventListener('click', e => {
    if (e.target.classList.contains('remove-row')) {
        const tbody = document.querySelector('#itemsTable tbody');
        if (tbody.rows.length > 1) e.target.closest('tr').remove();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
