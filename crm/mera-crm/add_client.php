<?php
// =============================================
// FILE: add_client.php
// PURPOSE: Add a new client
// =============================================

require_once 'log_in.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id   = trim($_POST['client_id'] ?? '');
        $full_name   = trim($_POST['full_name'] ?? '');
        $client_type = trim($_POST['client_type'] ?? 'INDIVIDUAL');
        $phone       = trim($_POST['phone'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $address     = trim($_POST['address'] ?? '');
        $balance     = floatval($_POST['balance'] ?? 0);
        $unpaid      = floatval($_POST['unpaid_balance'] ?? 0);

        if ($client_id === '' || $full_name === '') {
            throw new Exception('Client ID and Full Name are required.');
        }

        // Check duplicate client_id
        $check = $conn->prepare("SELECT id FROM clients WHERE client_id = ? AND deleted_at IS NULL");
        $check->execute([$client_id]);
        if ($check->fetch()) {
            throw new Exception('This Client ID already exists.');
        }

        $sql = "INSERT INTO clients 
                (client_id, full_name, client_type, phone, email, address, balance, unpaid_balance, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $client_id, $full_name, $client_type, $phone, $email, $address,
            $balance, $unpaid, $_SESSION['user_id']
        ]);

        $new_id = $conn->lastInsertId();
        log_activity($conn, 'Client', 'Created', $new_id, [
            'client_id' => $client_id,
            'full_name' => $full_name
        ]);

        header("Location: view_client.php?id=" . $new_id . "&msg=created");
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
    <title>Add Client - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="sidebar">
    <div class="brand"><?= SITE_NAME ?></div>
    <ul>
        <li><a href="dashboard.php">🏠 <span>Dashboard</span></a></li>
        <li class="active"><a href="clients.php">👥 <span>Clients</span></a></li>
        <li><a href="invoices.php">📄 <span>Invoices</span></a></li>
        <li><a href="activity_logs.php">📜 <span>Activity Logs</span></a></li>
        <li><a href="users.php">👤 <span>Users</span></a></li>
        <li><a href="logout.php">🚪 <span>Logout</span></a></li>
    </ul>
</div>

<div class="main-content">
    <h1>Add New Client</h1>
    <p class="text-muted mb-20">Fill the form below to create a new client</p>

    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:14px 18px; border-radius:8px; margin-bottom:20px;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                <div class="form-group">
                    <label>Client ID *</label>
                    <input type="text" name="client_id" required value="<?= htmlspecialchars($_POST['client_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Client Type</label>
                    <select name="client_type">
                        <option value="INDIVIDUAL">INDIVIDUAL</option>
                        <option value="CORPORATION">CORPORATION</option>
                        <option value="LLC">LLC</option>
                        <option value="PARTNERSHIP">PARTNERSHIP</option>
                        <option value="SOLE PROPRIETOR">SOLE PROPRIETOR</option>
                        <option value="S-CORPORATION">S-CORPORATION</option>
                        <option value="PMB">PMB</option>
                        <option value="IMMIGRATION">IMMIGRATION</option>
                        <option value="OTHERS">OTHERS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Balance</label>
                    <input type="number" step="0.01" name="balance" value="<?= htmlspecialchars($_POST['balance'] ?? '0') ?>">
                </div>
                <div class="form-group">
                    <label>Unpaid Balance</label>
                    <input type="number" step="0.01" name="unpaid_balance" value="<?= htmlspecialchars($_POST['unpaid_balance'] ?? '0') ?>">
                </div>
            </div>

            <div style="margin-top:25px;">
                <button type="submit" class="btn btn-primary">Save Client</button>
                <a href="clients.php" class="btn btn-secondary" style="margin-left:10px;">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
