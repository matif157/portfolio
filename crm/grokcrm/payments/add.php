<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Invoice', 'payment');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../invoices/index.php');
    exit;
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
$mem_id     = (int)($_POST['mem_id'] ?? 0) ?: null;
$amount     = (float)($_POST['amount'] ?? 0);
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');
$method     = trim($_POST['payment_method'] ?? '');
$reference  = trim($_POST['reference'] ?? '');
$notes      = trim($_POST['notes'] ?? '');

if ($invoice_id <= 0 || $amount <= 0) {
    set_flash('Invalid payment data.', 'danger');
    header("Location: ../invoices/view.php?id=$invoice_id");
    exit;
}

// Get current invoice
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoice_id]);
$inv = $stmt->fetch();
if (!$inv) {
    set_flash('Invoice not found.', 'danger');
    header('Location: ../invoices/index.php');
    exit;
}

if ($amount > $inv['balance']) {
    set_flash('Payment amount cannot exceed balance.', 'danger');
    header("Location: ../invoices/view.php?id=$invoice_id");
    exit;
}

$pdo->beginTransaction();
try {
    // Insert payment
    $pdo->prepare("INSERT INTO payments (invoice_id, mem_id, amount, payment_date, payment_method, reference, notes, received_by) 
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$invoice_id, $mem_id, $amount, $payment_date, $method, $reference, $notes, $_SESSION['user_id']]);

    // Update invoice
    $newPaid    = $inv['paid_amount'] + $amount;
    $newBalance = $inv['total_amount'] - $newPaid;
    $newStatus  = $newBalance <= 0 ? 3 : ($newPaid > 0 ? 2 : 1);
    $paidDate   = $newBalance <= 0 ? $payment_date : $inv['paid_date'];

    $pdo->prepare("UPDATE invoices SET paid_amount=?, balance=?, invoice_status=?, paid_date=?, payment_details=CONCAT(COALESCE(payment_details,''), ?) WHERE id=?")
        ->execute([$newPaid, max(0,$newBalance), $newStatus, $paidDate, "\nPayment $amount on $payment_date ($method)", $invoice_id]);

    $pdo->commit();
    log_activity('add_payment', 'Invoice', "Payment of " . money($amount) . " on invoice #$invoice_id");
    set_flash('Payment recorded successfully.');
} catch (Exception $e) {
    $pdo->rollBack();
    set_flash('Error: ' . $e->getMessage(), 'danger');
}

header("Location: ../invoices/view.php?id=$invoice_id");
exit;
