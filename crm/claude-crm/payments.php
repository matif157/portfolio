<?php
// ============================================================
// SPS CRM - Payments
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('payments.manage');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    verify_csrf();
    $invoice_id = (int)($_POST['invoice_id'] ?? 0) ?: null;
    $client_id  = (int)($_POST['client_id'] ?? 0);
    $amount     = (float)($_POST['amount'] ?? 0);
    $date       = $_POST['payment_date'] ?: date('Y-m-d');
    $method     = trim($_POST['method'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($invoice_id) {
        $inv = db_row("SELECT * FROM invoices WHERE id=?", [$invoice_id]);
        if ($inv) $client_id = $inv['client_id'];
    }

    if (!$client_id || $amount <= 0) {
        flash_error('Please select a client and enter a valid amount.');
        redirect('payments.php');
    }

    db_begin();
    try {
        $pid = db_insert(
            "INSERT INTO payments (invoice_id, client_id, amount, payment_date, method, notes, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())",
            [$invoice_id, $client_id, $amount, $date, $method ?: null, $notes ?: null, current_user_id()]
        );

        if ($invoice_id) {
            db_query("UPDATE invoices SET amount_paid = amount_paid + ?, updated_at=NOW() WHERE id=?", [$amount, $invoice_id]);
            $inv = db_row("SELECT * FROM invoices WHERE id=?", [$invoice_id]);
            $new_status = $inv['amount_paid'] >= $inv['amount_due'] ? 'paid' : $inv['status'];
            if ($new_status !== $inv['status']) {
                db_query("UPDATE invoices SET status=? WHERE id=?", [$new_status, $invoice_id]);
                log_activity('update', 'invoices', $invoice_id, "Invoice marked paid — automatically completed via payment", $client_id);
            }
        }

        db_commit();
        log_activity('create', 'payments', $pid, "Payment of " . fmt_currency($amount) . " recorded", $client_id);
        flash_success('Payment recorded.');
    } catch (Throwable $e) {
        db_rollback();
        error_log('Payment failed: ' . $e->getMessage());
        flash_error('Unable to save payment. Please try again.');
    }
    redirect($invoice_id ? 'invoices.php?id=' . $invoice_id : 'payments.php');
}

$payments = db_rows(
    "SELECT p.*, CONCAT(c.first_name,' ',c.last_name) as client_name, i.invoice_number
     FROM payments p LEFT JOIN clients c ON c.id=p.client_id LEFT JOIN invoices i ON i.id=p.invoice_id
     ORDER BY p.payment_date DESC, p.created_at DESC LIMIT 200"
);
$total_this_month = (float)(db_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(payment_date)=MONTH(CURRENT_DATE) AND YEAR(payment_date)=YEAR(CURRENT_DATE)") ?: 0);
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

$prefill_invoice_id = (int)($_GET['invoice_id'] ?? 0);
$auto_open = ($_GET['action'] ?? '') === 'add';
$prefill_invoice = $prefill_invoice_id ? db_row("SELECT * FROM invoices WHERE id=?", [$prefill_invoice_id]) : null;

render_header('Payments', 'payments');
?>
<?= render_flash() ?>

<div class="stat-cards">
  <div class="stat-card success"><div class="stat-value"><?= fmt_currency($total_this_month) ?></div><div class="stat-label">Payments This Month</div></div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Payments</h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('add-pay-modal')">+ Record Payment</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Date</th><th>Client</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead>
      <tbody>
      <?php if ($payments): foreach ($payments as $p): ?>
      <tr>
        <td><?= fmt_date($p['payment_date']) ?></td>
        <td><?= e($p['client_name'] ?: '—') ?></td>
        <td><?= $p['invoice_number'] ? '<a href="invoices.php?id='.$p['invoice_id'].'">'.e($p['invoice_number']).'</a>' : '—' ?></td>
        <td style="font-weight:600"><?= fmt_currency($p['amount']) ?></td>
        <td><?= e($p['method'] ?: '—') ?></td>
        <td class="text-muted"><?= e($p['notes'] ?: '—') ?></td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No payments recorded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="add-pay-modal">
  <div class="modal">
    <div class="modal-header"><h3>Record Payment</h3><button class="modal-close" onclick="closeModal('add-pay-modal')">×</button></div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <?php if ($prefill_invoice): ?>
        <div class="alert alert-info">Applying to invoice <b><?= e($prefill_invoice['invoice_number']) ?></b> — balance <?= fmt_currency($prefill_invoice['amount_due']-$prefill_invoice['amount_paid']) ?></div>
        <input type="hidden" name="invoice_id" value="<?= $prefill_invoice['id'] ?>">
        <?php else: ?>
        <div class="form-group"><label>Client <span class="text-danger">*</span></label>
          <select name="client_id" class="form-control" required>
            <option value="">— Select —</option>
            <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-row">
          <div class="form-group"><label>Amount <span class="text-danger">*</span></label><input type="number" step="0.01" name="amount" class="form-control" required></div>
          <div class="form-group"><label>Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div class="form-group"><label>Method</label>
          <select name="method" class="form-control">
            <option value="">— Select —</option>
            <option>Cash</option><option>Check</option><option>Bank Transfer</option><option>Credit Card</option><option>ACH</option>
          </select>
        </div>
        <div class="form-group"><label>Notes</label><input type="text" name="notes" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('add-pay-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Payment</button>
      </div>
    </form>
  </div>
</div>
<?php if ($auto_open): ?><script>openModal('add-pay-modal');</script><?php endif; ?>
<?php render_footer(); ?>
