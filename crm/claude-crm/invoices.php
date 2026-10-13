<?php
// ============================================================
// SPS CRM - Invoices
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('invoices.manage');

$view_id = (int)($_GET['id'] ?? 0);
$action = $_POST['action'] ?? '';

function next_invoice_number(): string {
    $n = (int)db_val("SELECT COUNT(*) FROM invoices") + 1;
    return 'INV-' . date('Y') . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    verify_csrf();
    $client_id = (int)($_POST['client_id'] ?? 0);
    $business_id = (int)($_POST['business_id'] ?? 0) ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $descriptions = $_POST['item_description'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $prices = $_POST['item_price'] ?? [];

    if (!$client_id) {
        flash_error('Please select a client.');
        redirect('invoices.php');
    }

    $total = 0;
    $items = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc);
        $qty  = (float)($qtys[$i] ?? 0);
        $price = (float)($prices[$i] ?? 0);
        if ($desc && $qty > 0) {
            $items[] = [$desc, $qty, $price];
            $total += $qty * $price;
        }
    }
    if (!$items) {
        flash_error('Add at least one line item.');
        redirect('invoices.php');
    }

    db_begin();
    try {
        $inv_id = db_insert(
            "INSERT INTO invoices (client_id, business_id, invoice_number, status, amount_due, amount_paid, issued_date, due_date, created_by, created_at, updated_at)
             VALUES (?,?,?, 'sent', ?, 0, CURDATE(), ?, ?, NOW(), NOW())",
            [$client_id, $business_id, next_invoice_number(), $total, $due_date, current_user_id()]
        );
        foreach ($items as [$desc, $qty, $price]) {
            db_query("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?,?,?,?)", [$inv_id, $desc, $qty, $price]);
        }
        db_commit();
        log_activity('create', 'invoices', $inv_id, "Invoice created for " . fmt_currency($total), $client_id);
        flash_success('Invoice created.');
        redirect('invoices.php?id=' . $inv_id);
    } catch (Throwable $e) {
        db_rollback();
        error_log('Invoice creation failed: ' . $e->getMessage());
        flash_error('Unable to save invoice. Please try again.');
        redirect('invoices.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel') {
    verify_csrf();
    $iid = (int)($_POST['invoice_id'] ?? 0);
    db_query("UPDATE invoices SET status='cancelled', updated_at=NOW() WHERE id=?", [$iid]);
    log_activity('update', 'invoices', $iid, 'Invoice cancelled');
    flash_success('Invoice cancelled.');
    redirect('invoices.php');
}

// ============================================================
// DETAIL VIEW
// ============================================================
if ($view_id) {
    $invoice = db_row(
        "SELECT i.*, CONCAT(c.first_name,' ',c.last_name) as client_name, b.name as business_name
         FROM invoices i LEFT JOIN clients c ON c.id=i.client_id LEFT JOIN businesses b ON b.id=i.business_id
         WHERE i.id=? AND i.deleted_at IS NULL", [$view_id]
    );
    if (!$invoice) { flash_error('Invoice not found.'); redirect('invoices.php'); }
    $items = db_rows("SELECT * FROM invoice_items WHERE invoice_id=?", [$view_id]);
    $payments = db_rows("SELECT * FROM payments WHERE invoice_id=? ORDER BY payment_date DESC", [$view_id]);

    render_header('Invoice ' . $invoice['invoice_number'], 'invoices');
?>
<?= render_flash() ?>
<div style="margin-bottom:16px"><a href="invoices.php" class="btn btn-secondary btn-sm">← All Invoices</a></div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
<div class="card">
  <div class="card-header">
    <h2>Invoice <?= e($invoice['invoice_number']) ?> <?= status_badge($invoice['status']) ?></h2>
  </div>
  <div class="card-body">
    <div class="form-row" style="margin-bottom:16px">
      <div><div class="text-muted" style="font-size:12px">Client</div><div><a href="client_profile.php?id=<?= $invoice['client_id'] ?>"><?= e($invoice['client_name']) ?></a></div></div>
      <div><div class="text-muted" style="font-size:12px">Business</div><div><?= e($invoice['business_name'] ?: '—') ?></div></div>
      <div><div class="text-muted" style="font-size:12px">Issued</div><div><?= fmt_date($invoice['issued_date']) ?></div></div>
      <div><div class="text-muted" style="font-size:12px">Due</div><div><?= fmt_date($invoice['due_date']) ?></div></div>
    </div>
    <table>
      <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['description']) ?></td><td><?= $it['quantity'] ?></td><td><?= fmt_currency($it['unit_price']) ?></td><td><?= fmt_currency($it['quantity']*$it['unit_price']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3" style="text-align:right;font-weight:600">Amount Due</td><td style="font-weight:600"><?= fmt_currency($invoice['amount_due']) ?></td></tr>
        <tr><td colspan="3" style="text-align:right">Paid</td><td><?= fmt_currency($invoice['amount_paid']) ?></td></tr>
        <tr><td colspan="3" style="text-align:right;font-weight:700">Balance</td><td style="font-weight:700"><?= fmt_currency($invoice['amount_due']-$invoice['amount_paid']) ?></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<div>
  <div class="card" style="margin-bottom:16px">
    <div class="card-header"><h3>Payments</h3></div>
    <div class="card-body">
      <?php if ($payments): foreach ($payments as $p): ?>
      <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;display:flex;justify-content:space-between">
        <span><?= fmt_date($p['payment_date']) ?> · <?= e($p['method'] ?: 'N/A') ?></span><b><?= fmt_currency($p['amount']) ?></b>
      </div>
      <?php endforeach; else: ?>
      <div class="text-muted" style="font-size:13px">No payments recorded yet.</div>
      <?php endif; ?>
      <?php if ($invoice['status'] !== 'cancelled' && $invoice['amount_paid'] < $invoice['amount_due']): ?>
      <a href="payments.php?invoice_id=<?= $view_id ?>&action=add" class="btn btn-primary btn-sm w-full" style="margin-top:12px">+ Record Payment</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($invoice['status'] !== 'cancelled' && $invoice['amount_paid'] == 0): ?>
  <form method="POST" onsubmit="return confirm('Cancel this invoice?')">
    <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="invoice_id" value="<?= $view_id ?>">
    <button class="btn btn-danger btn-sm w-full">Cancel Invoice</button>
  </form>
  <?php endif; ?>
</div>
</div>
<?php render_footer(); exit; ?>
<?php
}

// ============================================================
// LIST VIEW
// ============================================================
$status_filter = $_GET['status'] ?? '';
$where = ['i.deleted_at IS NULL'];
$params = [];
if ($status_filter) { $where[] = 'i.status = ?'; $params[] = $status_filter; }
$where_sql = 'WHERE ' . implode(' AND ', $where);

$invoices = db_rows(
    "SELECT i.*, CONCAT(c.first_name,' ',c.last_name) as client_name
     FROM invoices i LEFT JOIN clients c ON c.id=i.client_id
     $where_sql ORDER BY i.created_at DESC LIMIT 200", $params
);
$totals = db_row("SELECT COALESCE(SUM(amount_due-amount_paid),0) as outstanding, COUNT(*) as cnt FROM invoices WHERE status IN ('sent','overdue') AND deleted_at IS NULL");
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

render_header('Invoices', 'invoices');
?>
<?= render_flash() ?>

<div class="stat-cards">
  <div class="stat-card danger"><div class="stat-value"><?= fmt_currency($totals['outstanding']) ?></div><div class="stat-label">Outstanding</div></div>
  <div class="stat-card"><div class="stat-value"><?= (int)$totals['cnt'] ?></div><div class="stat-label">Unpaid Invoices</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Invoices</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('create-inv-modal')">+ Create Invoice</button>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" class="search-bar">
      <select name="status" class="form-control" style="width:160px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Invoice #</th><th>Client</th><th>Status</th><th>Due</th><th>Paid</th><th>Date</th></tr></thead>
      <tbody>
      <?php if ($invoices): foreach ($invoices as $inv): ?>
      <tr>
        <td><a href="invoices.php?id=<?= $inv['id'] ?>" style="font-weight:500"><?= e($inv['invoice_number']) ?></a></td>
        <td><?= e($inv['client_name'] ?: '—') ?></td>
        <td><?= status_badge($inv['status']) ?></td>
        <td><?= fmt_currency($inv['amount_due']) ?></td>
        <td><?= fmt_currency($inv['amount_paid']) ?></td>
        <td class="text-muted"><?= fmt_date($inv['created_at']) ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No invoices yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="create-inv-modal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header"><h3>Create Invoice</h3><button class="modal-close" onclick="closeModal('create-inv-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>Client <span class="text-danger">*</span></label>
            <select name="client_id" class="form-control" required>
              <option value="">— Select —</option>
              <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Due Date</label><input type="date" name="due_date" class="form-control"></div>
        </div>
        <label style="font-size:13px;font-weight:500">Line Items</label>
        <div id="item-rows">
          <div class="form-row" style="align-items:end">
            <div class="form-group" style="flex:2"><input type="text" name="item_description[]" class="form-control" placeholder="Description"></div>
            <div class="form-group" style="width:80px"><input type="number" name="item_qty[]" class="form-control" placeholder="Qty" value="1" step="0.01"></div>
            <div class="form-group" style="width:110px"><input type="number" name="item_price[]" class="form-control" placeholder="Unit Price" step="0.01"></div>
          </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">+ Add Line</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('create-inv-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Invoice</button>
      </div>
    </form>
  </div>
</div>
<script>
function addItemRow() {
  const wrap = document.getElementById('item-rows');
  const row = wrap.children[0].cloneNode(true);
  row.querySelectorAll('input').forEach(i => i.value = i.name.includes('qty') ? '1' : '');
  wrap.appendChild(row);
}
</script>
<?php render_footer(); ?>
