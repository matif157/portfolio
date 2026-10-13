<?php
// ============================================================
// SPS CRM - Quotations
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

function next_quote_number(): string {
    $n = (int)db_val("SELECT COUNT(*) FROM quotations") + 1;
    return 'QUO-' . date('Y') . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    verify_csrf();
    $client_id = (int)($_POST['client_id'] ?? 0);
    $expires   = $_POST['expires_date'] ?: null;
    $descriptions = $_POST['item_description'] ?? [];
    $qtys = $_POST['item_qty'] ?? [];
    $prices = $_POST['item_price'] ?? [];

    if (!$client_id) { flash_error('Please select a client.'); redirect('quotations.php'); }

    $total = 0; $items = [];
    foreach ($descriptions as $i => $desc) {
        $desc = trim($desc); $qty = (float)($qtys[$i] ?? 0); $price = (float)($prices[$i] ?? 0);
        if ($desc && $qty > 0) { $items[] = [$desc, $qty, $price]; $total += $qty * $price; }
    }
    if (!$items) { flash_error('Add at least one line item.'); redirect('quotations.php'); }

    db_begin();
    try {
        $qid = db_insert(
            "INSERT INTO quotations (client_id, quote_number, status, total, issued_date, expires_date, created_by, created_at)
             VALUES (?,?, 'sent', ?, CURDATE(), ?, ?, NOW())",
            [$client_id, next_quote_number(), $total, $expires, current_user_id()]
        );
        foreach ($items as [$desc, $qty, $price]) {
            db_query("INSERT INTO quotation_items (quotation_id, description, quantity, unit_price) VALUES (?,?,?,?)", [$qid, $desc, $qty, $price]);
        }
        db_commit();
        log_activity('create', 'quotations', $qid, "Quotation created for " . fmt_currency($total), $client_id);
        flash_success('Quotation created.');
        redirect('quotations.php?id=' . $qid);
    } catch (Throwable $e) {
        db_rollback();
        error_log('Quotation failed: ' . $e->getMessage());
        flash_error('Unable to save quotation.');
        redirect('quotations.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'convert') {
    verify_csrf();
    $qid = (int)($_POST['quote_id'] ?? 0);
    $quote = db_row("SELECT * FROM quotations WHERE id=?", [$qid]);
    $items = db_rows("SELECT * FROM quotation_items WHERE quotation_id=?", [$qid]);
    if ($quote && $items) {
        db_begin();
        try {
            $inv_id = db_insert(
                "INSERT INTO invoices (client_id, invoice_number, status, amount_due, amount_paid, issued_date, created_by, created_at, updated_at)
                 VALUES (?,?, 'sent', ?, 0, CURDATE(), ?, NOW(), NOW())",
                [$quote['client_id'], next_invoice_number_local(), $quote['total'], current_user_id()]
            );
            foreach ($items as $it) {
                db_query("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price) VALUES (?,?,?,?)", [$inv_id, $it['description'], $it['quantity'], $it['unit_price']]);
            }
            db_query("UPDATE quotations SET status='accepted' WHERE id=?", [$qid]);
            db_commit();
            log_activity('create', 'invoices', $inv_id, "Invoice created from quotation {$quote['quote_number']}", $quote['client_id']);
            flash_success('Quotation converted to invoice.');
            redirect('invoices.php?id=' . $inv_id);
        } catch (Throwable $e) {
            db_rollback();
            error_log('Convert failed: ' . $e->getMessage());
            flash_error('Unable to convert quotation.');
        }
    }
    redirect('quotations.php?id=' . $qid);
}

function next_invoice_number_local(): string {
    $n = (int)db_val("SELECT COUNT(*) FROM invoices") + 1;
    return 'INV-' . date('Y') . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

if ($view_id) {
    $quote = db_row(
        "SELECT q.*, CONCAT(c.first_name,' ',c.last_name) as client_name FROM quotations q
         LEFT JOIN clients c ON c.id=q.client_id WHERE q.id=?", [$view_id]
    );
    if (!$quote) { flash_error('Quotation not found.'); redirect('quotations.php'); }
    $items = db_rows("SELECT * FROM quotation_items WHERE quotation_id=?", [$view_id]);

    render_header('Quotation ' . $quote['quote_number'], 'quotations');
?>
<?= render_flash() ?>
<div style="margin-bottom:16px"><a href="quotations.php" class="btn btn-secondary btn-sm">← All Quotations</a></div>
<div class="card">
  <div class="card-header"><h2>Quotation <?= e($quote['quote_number']) ?> <?= status_badge($quote['status']) ?></h2>
    <?php if ($quote['status'] === 'sent'): ?>
    <form method="POST" onsubmit="return confirm('Convert this quotation into an invoice?')">
      <?= csrf_field() ?><input type="hidden" name="action" value="convert"><input type="hidden" name="quote_id" value="<?= $view_id ?>">
      <button class="btn btn-primary btn-sm">Convert to Invoice</button>
    </form>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="form-row" style="margin-bottom:16px">
      <div><div class="text-muted" style="font-size:12px">Client</div><div><a href="client_profile.php?id=<?= $quote['client_id'] ?>"><?= e($quote['client_name']) ?></a></div></div>
      <div><div class="text-muted" style="font-size:12px">Issued</div><div><?= fmt_date($quote['issued_date']) ?></div></div>
      <div><div class="text-muted" style="font-size:12px">Expires</div><div><?= fmt_date($quote['expires_date']) ?></div></div>
    </div>
    <table>
      <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
      <tr><td><?= e($it['description']) ?></td><td><?= $it['quantity'] ?></td><td><?= fmt_currency($it['unit_price']) ?></td><td><?= fmt_currency($it['quantity']*$it['unit_price']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td colspan="3" style="text-align:right;font-weight:700">Total</td><td style="font-weight:700"><?= fmt_currency($quote['total']) ?></td></tr></tfoot>
    </table>
  </div>
</div>
<?php render_footer(); exit; ?>
<?php
}

$quotes = db_rows("SELECT q.*, CONCAT(c.first_name,' ',c.last_name) as client_name FROM quotations q LEFT JOIN clients c ON c.id=q.client_id ORDER BY q.created_at DESC LIMIT 200");
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

render_header('Quotations', 'quotations');
?>
<?= render_flash() ?>
<div class="card">
  <div class="card-header">
    <h2>Quotations</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('create-quo-modal')">+ Create Quotation</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Quote #</th><th>Client</th><th>Status</th><th>Total</th><th>Expires</th><th>Date</th></tr></thead>
      <tbody>
      <?php if ($quotes): foreach ($quotes as $q): ?>
      <tr>
        <td><a href="quotations.php?id=<?= $q['id'] ?>" style="font-weight:500"><?= e($q['quote_number']) ?></a></td>
        <td><?= e($q['client_name'] ?: '—') ?></td>
        <td><?= status_badge($q['status']) ?></td>
        <td><?= fmt_currency($q['total']) ?></td>
        <td><?= fmt_date($q['expires_date']) ?></td>
        <td class="text-muted"><?= fmt_date($q['created_at']) ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No quotations yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="create-quo-modal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header"><h3>Create Quotation</h3><button class="modal-close" onclick="closeModal('create-quo-modal')">×</button></div>
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
          <div class="form-group"><label>Expires</label><input type="date" name="expires_date" class="form-control"></div>
        </div>
        <label style="font-size:13px;font-weight:500">Line Items</label>
        <div id="q-item-rows">
          <div class="form-row" style="align-items:end">
            <div class="form-group" style="flex:2"><input type="text" name="item_description[]" class="form-control" placeholder="Description"></div>
            <div class="form-group" style="width:80px"><input type="number" name="item_qty[]" class="form-control" placeholder="Qty" value="1" step="0.01"></div>
            <div class="form-group" style="width:110px"><input type="number" name="item_price[]" class="form-control" placeholder="Unit Price" step="0.01"></div>
          </div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addQItemRow()">+ Add Line</button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('create-quo-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Quotation</button>
      </div>
    </form>
  </div>
</div>
<script>
function addQItemRow() {
  const wrap = document.getElementById('q-item-rows');
  const row = wrap.children[0].cloneNode(true);
  row.querySelectorAll('input').forEach(i => i.value = i.name.includes('qty') ? '1' : '');
  wrap.appendChild(row);
}
</script>
<?php render_footer(); ?>
