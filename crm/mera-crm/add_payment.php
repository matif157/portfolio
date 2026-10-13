<?php
// =============================================
// FILE: add_payment.php
// PURPOSE: Record a payment against an invoice
// =============================================

require_once 'log_in.php';
require_login();

$invoice_id = (int)($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = floatval($_POST['amount'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'Zelle');
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $reference = trim($_POST['reference_no'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero.');
        }

        // Insert payment
        $conn->prepare("INSERT INTO invoice_payments (invoice_id, amount, payment_method, payment_date, reference_no, notes, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?)")
             ->execute([$invoice_id, $amount, $method, $payment_date, $reference, $notes, $_SESSION['user_id']]);

        // Update invoice totals
        $new_paid = (float)$invoice['total_paid'] + $amount;
        $new_balance = (float)$invoice['grand_total'] - $new_paid;

        $new_status = 'Unpaid';
        if ($new_balance <= 0) {
            $new_status = 'Paid';
            $new_balance = 0;
        } elseif ($new_paid > 0) {
            $new_status = 'Partial';
        }

        $conn->prepare("UPDATE invoices SET total_paid = ?, balance = ?, status = ? WHERE id = ?")
             ->execute([$new_paid, $new_balance, $new_status, $invoice_id]);

        log_activity($conn, 'Invoice', 'Payment Added', $invoice_id, [
            'amount' => $amount,
            'payment_method' => $method,
            'new_status' => $new_status
        ]);

        header("Location: view_invoice.php?id=" . $invoice_id);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Payment - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li><a href="clients.php">👥 <span>Clients</span></a></li>
        <li class="active"><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <h1>Add Payment</h1>
    <p class="text-muted mb-20">Invoice: <strong><?= show($invoice['invoice_number']) ?></strong> | Balance: $<?= number_format((float)$invoice['balance'], 2) ?></p>

    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:14px 18px; border-radius:8px; margin-bottom:20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width:500px;">
        <form method="POST">
            <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

            <div class="form-group">
                <label>Amount ($) *</label>
                <input type="number" step="0.01" name="amount" required value="<?= $invoice['balance'] ?>">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="Zelle">Zelle</option>
                    <option value="Credit Card">Credit Card</option>
                    <option value="Debit Card">Debit Card</option>
                    <option value="Cash">Cash</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Payment Date</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Reference No</label>
                <input type="text" name="reference_no">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3"></textarea>
            </div>

            <button type="submit" class="btn btn-success">Save Payment</button>
            <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
        </form>
    </div>
</div>

</body>
</html>
