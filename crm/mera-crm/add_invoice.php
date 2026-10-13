<?php
// =============================================
// FILE: add_invoice.php
// PURPOSE: Create a new invoice
// =============================================

require_once 'log_in.php';
require_login();

$error = '';

// Get all active clients for dropdown
$clients = $conn->query("SELECT id, client_id, full_name FROM clients WHERE deleted_at IS NULL ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id      = (int)($_POST['client_id'] ?? 0);
        $created_date   = $_POST['created_date'] ?? date('Y-m-d');
        $due_date       = $_POST['due_date'] ?? null;
        $service_name   = trim($_POST['service_name'] ?? '');
        $unit_price     = floatval($_POST['unit_price'] ?? 0);
        $quantity       = floatval($_POST['quantity'] ?? 1);
        $service_provider = trim($_POST['service_provider'] ?? '');

        if ($client_id <= 0 || $service_name === '') {
            throw new Exception('Client and Service Name are required.');
        }

        $total = $unit_price * $quantity;

        // Generate simple invoice number
        $last = $conn->query("SELECT MAX(id) FROM invoices")->fetchColumn();
        $invoice_number = 'INV-' . str_pad(($last ? $last + 1 : 1), 5, '0', STR_PAD_LEFT);

        // Insert invoice
        $sql = "INSERT INTO invoices 
                (invoice_number, client_id, created_date, due_date, status, sub_total, grand_total, balance, created_by)
                VALUES (?, ?, ?, ?, 'Unpaid', ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $invoice_number, $client_id, $created_date, $due_date,
            $total, $total, $total, $_SESSION['user_id']
        ]);

        $invoice_id = $conn->lastInsertId();

        // Insert item
        $conn->prepare("INSERT INTO invoice_items (invoice_id, service_name, quantity, unit_price, total, service_provider)
                        VALUES (?, ?, ?, ?, ?, ?)")
             ->execute([$invoice_id, $service_name, $quantity, $unit_price, $total, $service_provider]);

        log_activity($conn, 'Invoice', 'Created', $invoice_id, [
            'invoice_number' => $invoice_number,
            'client_id' => $client_id,
            'grand_total' => $total
        ]);

        header("Location: view_invoice.php?id=" . $invoice_id);
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$preselect = (int)($_GET['client_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Invoice - <?= SITE_NAME ?></title>
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
    <h1>Create Invoice</h1>
    <p class="text-muted mb-20">Fill the details below</p>

    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:14px 18px; border-radius:8px; margin-bottom:20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="form-group">
                    <label>Client *</label>
                    <select name="client_id" required>
                        <option value="">-- Select Client --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $preselect === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['full_name'] . ' (' . $c['client_id'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Created Date</label>
                    <input type="date" name="created_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>">
                </div>
                <div class="form-group">
                    <label>Service Name *</label>
                    <input type="text" name="service_name" required>
                </div>
                <div class="form-group">
                    <label>Unit Price ($)</label>
                    <input type="number" step="0.01" name="unit_price" value="0">
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" step="0.01" name="quantity" value="1">
                </div>
                <div class="form-group">
                    <label>Service Provider</label>
                    <input type="text" name="service_provider" placeholder="e.g. MA(166)">
                </div>
            </div>

            <div style="margin-top:25px;">
                <button type="submit" class="btn btn-primary">Create Invoice</button>
                <a href="invoices.php" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
