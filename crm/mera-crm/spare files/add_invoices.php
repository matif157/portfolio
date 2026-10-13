<?php
// =============================================
// FILE NAME        : add_invoice.php
// PURPOSE          : Create a new invoice for a client
// =============================================

// STEP 1: Settings aur security load kar rahe hain
// Why: log_in.php mein database connection ($conn) aur session settings hain
// When: Har protected page pe ye line lagani chahiye
require_once 'log_in.php';

// STEP 2: Security Check
// isset() = PHP built-in function jo check karta hai ke variable maujood hai ya nahi
// Agar user login nahi hai to usko login page pe bhej do
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");  // header() = browser ko redirect karta hai
    exit();                         // exit() = iske baad ka code mat chalao
}

// STEP 3: Active clients list le rahe hain dropdown ke liye
// Why: User ko client select karne ka option chahiye
$clientsStmt = $conn->query("SELECT client_id, full_name, business_name FROM clients WHERE deleted_at IS NULL ORDER BY full_name");
$clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);  // fetchAll() = saare records array mein le aata hai

$error = '';  // Error message ke liye empty variable

// STEP 4: Form submit hua hai ya nahi check kar rahe hain
// $_SERVER["REQUEST_METHOD"] == "POST" = form submit hone pe true hota hai
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Form se data le rahe hain
        // ?? '' = agar value nahi mili to empty string de do (null coalescing operator)
        $client_id     = $_POST['client_id'] ?? '';
        $created_date  = $_POST['created_date'] ?? date('Y-m-d');
        $due_date      = $_POST['due_date'] ?? date('Y-m-d', strtotime('+15 days'));
        $created_by    = $_POST['created_by'] ?? 'MA(166)';
        $service_names = $_POST['service_name'] ?? [];
        $prices        = $_POST['price'] ?? [];
        $providers     = $_POST['service_provider'] ?? [];

        // Simple validation
        if (empty($client_id) || empty($service_names)) {
            throw new Exception("Client and at least one service are required.");
        }

        // STEP 5: Total calculate kar rahe hain
        $sub_total = 0;
        foreach ($prices as $p) {
            $sub_total += floatval($p);  // floatval() = string ko number banata hai
        }
        $grand_total = $sub_total;

        // STEP 6: Invoice number generate kar rahe hain (simple auto increment style)
        $last = $conn->query("SELECT MAX(id) as max_id FROM invoices")->fetch();
        $next_num = ($last['max_id'] ?? 0) + 12500;
        $invoice_number = $next_num;

        // STEP 7: Invoice table mein insert
        // prepare() = SQL injection se bachane ka safe tarika
        $sql = "INSERT INTO invoices (invoice_number, client_id, created_by, created_date, due_date, sub_total, grand_total, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Unpaid')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$invoice_number, $client_id, $created_by, $created_date, $due_date, $sub_total, $grand_total]);

        // lastInsertId() = abhi insert hue record ki ID le aata hai
        $invoice_id = $conn->lastInsertId();
        // =============================================
// ACTIVITY LOG - Invoice Created
// =============================================
// Purpose  : Record karna ke naya invoice banaya gaya
// Why      : Future mein pata chale kaunse user ne kab invoice banaya
// When     : Invoice successfully database mein insert hone ke baad
// Log activity
log_activity($conn, 'Invoice', 'Created', $invoice_id, null, [
    'invoice_number' => $invoice_number,
    'client_id'      => $client_id,
    'grand_total'    => $grand_total,
    'status'         => 'Unpaid'
]);

        // STEP 8: Invoice items (services) insert kar rahe hain
        $itemSql = "INSERT INTO invoice_items (invoice_id, service_provider, service_name, price) VALUES (?, ?, ?, ?)";
        $itemStmt = $conn->prepare($itemSql);

        for ($i = 0; $i < count($service_names); $i++) {
            if (!empty($service_names[$i])) {
                $itemStmt->execute([
                    $invoice_id,
                    $providers[$i] ?? 'KZ(99)',
                    $service_names[$i],
                    floatval($prices[$i] ?? 0)
                ]);
            }
        }

        // Success message + redirect
        echo "<script>alert('Invoice #$invoice_number created successfully!'); window.location='view_invoice.php?id=$invoice_id';</script>";
        exit();

    } catch (Exception $e) {
        // Agar koi error aaye to usko catch kar ke dikhao
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Invoice - Syed Professional Services</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .item-row { display:flex; gap:10px; margin-bottom:10px; align-items:center; }
        .item-row input { flex:1; padding:10px; border:1px solid #ddd; border-radius:6px; }
    </style>
</head>
<body>

<!-- Sidebar (same as other pages) -->
<div class="sidebar" style="width:260px; background:#1e2937; color:white; height:100vh; position:left; padding-top:20px;">
    <div style="padding:25px; text-align:center; font-size:20px; font-weight:700;">Syed Professional Services</div>
    <ul style="list-style:none; padding:0; margin-top:20px;">
        <li style="padding:16px 25px;"><a href="dashboard.php" style="color:white; text-decoration:none;">🏠 Dashboard</a></li>
        <li style="padding:16px 25px;"><a href="clients.php" style="color:white; text-decoration:none;">👥 Manage Clients</a></li>
        <li style="padding:16px 25px;"><a href="invoices.php" style="color:white; text-decoration:none;">📄 Invoices</a></li>
    </ul>
</div>

<div style="margin-left:260px; padding:40px;">
    <h1>Create New Invoice</h1>

    <?php if ($error): ?>
        <div style="background:#fee2e2; color:#ef4444; padding:15px; border-radius:10px; margin-bottom:20px;">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="background:white; padding:30px; border-radius:16px; box-shadow:0 10px 20px rgba(0,0,0,0.08); max-width:900px;">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:25px;">
            <div>
                <label>Select Client *</label>
                <select name="client_id" required style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= htmlspecialchars($c['client_id']) ?>">
                            <?= htmlspecialchars($c['business_name'] ?: $c['full_name']) ?> (ID: <?= htmlspecialchars($c['client_id']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Created By</label>
                <input type="text" name="created_by" value="MA(166)" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <div>
                <label>Created Date</label>
                <input type="date" name="created_date" value="<?= date('Y-m-d') ?>" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
            </div>
            <div>
                <label>Due Date</label>
                <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" style="width:100%; padding:12px; margin-top:8px; border:1px solid #ddd; border-radius:8px;">
            </div>
        </div>

        <h3 style="margin:30px 0 15px 0;">Services / Items</h3>
        <div id="items-container">
            <div class="item-row">
                <input type="text" name="service_provider[]" placeholder="Service Provider (e.g. KZ(99))" value="KZ(99)">
                <input type="text" name="service_name[]" placeholder="Service Name (e.g. Consultation Fee)" required>
                <input type="number" step="0.01" name="price[]" placeholder="Price" required style="max-width:140px;">
            </div>
        </div>

        <button type="button" onclick="addItem()" style="margin:15px 0; padding:10px 20px; background:#64748b; color:white; border:none; border-radius:8px; cursor:pointer;">
            + Add Another Service
        </button>

        <br><br>
        <button type="submit" style="padding:14px 35px; background:#22d3ee; color:white; border:none; border-radius:8px; font-size:16px; cursor:pointer;">
            Create Invoice
        </button>
        <a href="invoices.php" style="margin-left:15px; color:#64748b;">Cancel</a>
    </form>
<<?php require_once 'sidebar.php'; ?>

<div style="margin-left:260px; padding:40px;">
    <!-- Page content yahan -->
</div></div>

<script>
// JavaScript function: naya service row add karne ke liye
function addItem() {
    const container = document.getElementById('items-container');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <input type="text" name="service_provider[]" placeholder="Service Provider" value="KZ(99)">
        <input type="text" name="service_name[]" placeholder="Service Name" required>
        <input type="number" step="0.01" name="price[]" placeholder="Price" required style="max-width:140px;">
        <button type="button" onclick="this.parentElement.remove()" style="padding:8px 12px; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer;">X</button>
    `;
    container.appendChild(div);
}
</script>

</body>
</html>