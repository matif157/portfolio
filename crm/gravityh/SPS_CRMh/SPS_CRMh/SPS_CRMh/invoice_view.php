<?php
/**
 * SPS Professional Services CRM
 * Invoice Detailed View & Record Payment Modal
 */
require_once __DIR__ . '/auth.php';
require_permission('invoices.view');

$invoice_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT i.*, c.first_name, c.last_name, c.company_name, c.email, c.address, c.city, c.state, c.zip, b.business_name
    FROM invoices i
    JOIN clients c ON i.client_id = c.id
    LEFT JOIN businesses b ON i.business_id = b.id
    WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    set_flash('danger', 'Invoice record not found.');
    redirect('invoices.php');
}

// Handle Record Payment Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    verify_csrf();
    $amount = floatval($_POST['amount']);
    $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($amount > 0) {
        $next_p_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM payments")->fetchColumn();
        $payment_number = 'PAY-' . date('Y') . '-' . str_pad($next_p_id, 3, '0', STR_PAD_LEFT);

        $stmtP = $pdo->prepare("
            INSERT INTO payments (payment_number, invoice_id, client_id, amount, payment_method, payment_date, reference_no, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtP->execute([$payment_number, $invoice_id, $invoice['client_id'], $amount, $payment_method, $payment_date, $reference_no, $notes]);

        // Update Invoice Paid Balance & Status
        $new_paid = $invoice['paid_amount'] + $amount;
        $new_balance = max(0, $invoice['total_amount'] - $new_paid);
        $new_status = ($new_balance <= 0) ? 'paid' : 'partially_paid';

        $pdo->prepare("UPDATE invoices SET paid_amount = ?, balance_due = ?, status = ? WHERE id = ?")
            ->execute([$new_paid, $new_balance, $new_status, $invoice_id]);

        log_activity('Record Payment', 'invoices', $invoice_id, "Recorded payment $payment_number of " . format_currency($amount) . " for Invoice {$invoice['invoice_number']}");
        set_flash('success', "Payment $payment_number recorded successfully! Invoice status: " . strtoupper($new_status));
        redirect("invoice_view.php?id=$invoice_id");
    }
}

// Line Items
$items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$items->execute([$invoice_id]);
$item_list = $items->fetchAll();

// Payments History
$payments = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC");
$payments->execute([$invoice_id]);
$payment_list = $payments->fetchAll();

render_header("Invoice: {$invoice['invoice_number']}", 'invoices');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Invoice #<?= e($invoice['invoice_number']) ?></h1>
        <p class="page-subtitle">Issued Date: <?= format_date($invoice['issue_date']) ?> • Due Date: <?= format_date($invoice['due_date']) ?></p>
    </div>
    <div>
        <?php if ($invoice['status'] !== 'paid'): ?>
            <button onclick="document.getElementById('payModal').style.display = 'block'" class="btn btn-success">💳 Record Payment</button>
        <?php endif; ?>
        <a href="invoices.php" class="btn btn-secondary">← Back to Invoices</a>
    </div>
</div>

<!-- Printable Invoice Card -->
<div class="card" style="padding:40px;">
    <div style="display:flex; justify-content:space-between; border-bottom:2px solid var(--border-color); padding-bottom:20px; margin-bottom:20px;">
        <div>
            <h2 style="font-size:24px; font-weight:700; color:var(--primary);">SPS AI Professional Services</h2>
            <div style="color:var(--text-muted); font-size:12px;">100 Professional Way, Suite 500</div>
            <div style="color:var(--text-muted); font-size:12px;">billing@spsservices.com</div>
        </div>
        <div style="text-align:right;">
            <h2 style="font-size:20px; color:var(--secondary);"><?= e($invoice['invoice_number']) ?></h2>
            <span class="badge badge-<?= $invoice['status'] === 'paid' ? 'success' : 'danger' ?>" style="font-size:13px; padding:6px 12px; margin-top:6px;">
                <?= strtoupper($invoice['status']) ?>
            </span>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; margin-bottom:30px;">
        <div>
            <strong style="color:var(--text-muted); text-transform:uppercase; font-size:11px;">Billed To:</strong>
            <div style="font-size:16px; font-weight:700; margin-top:4px;"><?= e($invoice['company_name'] ?: "{$invoice['first_name']} {$invoice['last_name']}") ?></div>
            <?php if ($invoice['business_name']): ?>
                <div style="font-weight:600; font-size:13px; color:var(--accent);"><?= e($invoice['business_name']) ?></div>
            <?php endif; ?>
            <div style="color:var(--text-dark); font-size:13px;"><?= e($invoice['address']) ?></div>
            <div style="color:var(--text-dark); font-size:13px;"><?= e("{$invoice['city']}, {$invoice['state']} {$invoice['zip']}") ?></div>
            <div style="color:var(--text-muted); font-size:13px;"><?= e($invoice['email']) ?></div>
        </div>
        <div style="text-align:right; font-size:13px;">
            <div><strong>Issue Date:</strong> <?= format_date($invoice['issue_date']) ?></div>
            <div><strong>Due Date:</strong> <?= format_date($invoice['due_date']) ?></div>
        </div>
    </div>

    <!-- Line Items Table -->
    <table class="table" style="margin-bottom:30px;">
        <thead>
            <tr>
                <th>Item Description</th>
                <th style="text-align:center;">Qty</th>
                <th style="text-align:right;">Unit Price</th>
                <th style="text-align:right;">Total</th>
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

    <div style="display:flex; justify-content:flex-end;">
        <div style="width:300px; font-size:14px;">
            <div style="display:flex; justify-content:space-between; padding:6px 0;">
                <span>Total Amount:</span>
                <strong><?= format_currency($invoice['total_amount']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:6px 0; color:var(--success);">
                <span>Total Paid:</span>
                <strong>- <?= format_currency($invoice['paid_amount']) ?></strong>
            </div>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-top:2px solid var(--border-color); font-size:16px; font-weight:700; color:var(--danger);">
                <span>Balance Due:</span>
                <span><?= format_currency($invoice['balance_due']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form for Recording Payment -->
<div id="payModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:80px auto;">
        <div class="card-title">
            <span>Record Payment</span>
            <button onclick="document.getElementById('payModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="invoice_view.php?id=<?= $invoice_id ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_payment">

            <div class="form-group">
                <label class="form-label">Payment Amount ($) *</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="<?= $invoice['balance_due'] ?>" required>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="bank_transfer">Bank Wire / ACH</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="check">Check</option>
                        <option value="stripe">Stripe / Online</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Payment Date</label>
                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reference / Transaction #</label>
                <input type="text" name="reference_no" class="form-control" placeholder="ACH-94810294">
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('payModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-success">Save Payment Receipt</button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>
