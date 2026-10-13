<?php
// =============================================
// FILE: edit_invoice.php
// PURPOSE: Edit complete invoice (date, items, status etc.)
// =============================================

require_once 'log_in.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$id = $_GET['id'] ?? 0;

// Get invoice
$stmt = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    echo "Invoice not found";
    exit();
}

// Get current items
$itemsStmt = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get clients for dropdown
$clients = $conn->query("SELECT client_id, full_name, business_name FROM clients ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $client_id    = $_POST['client_id'];
        $created_date = $_POST['created_date'];
        $due_date     = $_POST['due_date'];
        $status       = $_POST['status'];
        $service_names = $_POST['service_name'] ?? [];
        $prices        = $_POST['price'] ?? [];
        $providers     = $_POST['service_provider'] ?? [];

        // Calculate new total
        $sub_total = 0;
        foreach ($prices as $p) {
            $sub_total += floatval($p);
        }

        // Update invoice
        $sql = "UPDATE invoices SET 
                    client_id = ?, created_date = ?, due_date = ?, 
                    status = ?, sub_total = ?, grand_total = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$client_id, $created_date, $due_date, $status, $sub_total, $sub_total, $id]);
        // =============================================
// ACTIVITY LOG - Invoice Updated
// =============================================
// Purpose  : Record karna ke invoice edit hua
// Why      : Kisne kya change kiya ye track karne ke liye
// When     : Invoice successfully update hone ke baad
// Log activity
log_activity($conn, 'Invoice', 'Updated', $id, null, [
    'status'      => $status,
    'grand_total' => $sub_total
]);

        // Delete old items
        $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);

        // Insert new items
        $itemSql = "INSERT INTO invoice_items (invoice_id, service_provider, service_name, price) VALUES (?, ?, ?, ?)";
        $itemStmt = $conn->prepare($itemSql);

        for ($i = 0; $i < count($service_names); $i++) {
            if (!empty($service_names[$i])) {
                $itemStmt->execute([
                    $id,
                    $providers[$i] ?? 'KZ(99)',
                    $service_names[$i],
                    floatval($prices[$i] ?? 0)
                ]);
            }
        }

        echo "<script>alert('Invoice updated successfully!'); window.location='view_invoice.php?id=$id';</script>";
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
    <title>Edit Invoice #<?= htmlspecialchars($inv['invoice_number']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .item-row { display:flex; gap:10px; margin-bottom:10px; align-items:center; }
        .item-row input { flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; }
    </style>
</head>
<body>

<div style="max-width:900px; margin:40px auto; background:white; padding:40px; border-radius:16px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
    <h1>Edit Invoice #<?= htmlspecialchars($inv['invoice_number']) ?></h1>

    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#ef4444; padding:15px; border-radius:10px; margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
            <div>
                <label>Client</label>
                <select name="client_id" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['client_id'] ?>" <?= $c['client_id'] == $inv['client_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['business_name'] ?: $c['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="status" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
                    <option value="Unpaid" <?= $inv['status']=='Unpaid'?'selected':'' ?>>Unpaid</option>
                    <option value="Partial" <?= $inv['status']=='Partial'?'selected':'' ?>>Partial</option>
                    <option value="Paid" <?= $inv['status']=='Paid'?'selected':'' ?>>Paid</option>
                </select>
            </div>
            <div>
                <label>Created Date</label>
                <input type="date" name="created_date" value="<?= $inv['created_date'] ?>" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <div>
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= $inv['due_date'] ?>" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
            </div>
        </div>

        <h3>Services</h3>
        <div id="items-container">
            <?php foreach ($items as $item): ?>
            <div class="item-row">
                <input type="text" name="service_provider[]" value="<?= htmlspecialchars($item['service_provider']) ?>">
                <input type="text" name="service_name[]" value="<?= htmlspecialchars($item['service_name']) ?>" required>
                <input type="number" step="0.01" name="price[]" value="<?= $item['price'] ?>" required style="max-width:140px;">
                <button type="button" onclick="this.parentElement.remove()" style="padding:8px 12px; background:#ef4444; color:white; border:none; border-radius:6px;">X</button>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" onclick="addItem()" style="margin:15px 0; padding:10px 20px; background:#64748b; color:white; border:none; border-radius:8px;">+ Add Service</button>

        <br><br>
        <button type="submit" style="padding:14px 35px; background:#22d3ee; color:white; border:none; border-radius:8px;">Update Invoice</button>
        <a href="view_invoice.php?id=<?= $id ?>" style="margin-left:15px;">Cancel</a>
    </form>
</div>

<script>
function addItem() {
    const container = document.getElementById('items-container');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <input type="text" name="service_provider[]" value="KZ(99)">
        <input type="text" name="service_name[]" placeholder="Service Name" required>
        <input type="number" step="0.01" name="price[]" placeholder="Price" required style="max-width:140px;">
        <button type="button" onclick="this.parentElement.remove()" style="padding:8px 12px; background:#ef4444; color:white; border:none; border-radius:6px;">X</button>
    `;
    container.appendChild(div);
}
</script>

</body>
</html>