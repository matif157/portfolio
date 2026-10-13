<?php
// =============================================
// FILE: clients.php
// PURPOSE: Manage Clients with Search & Filter
// =============================================

require_once 'log_in.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Search & Filter values le rahe hain
$search = $_GET['search'] ?? '';
$type   = $_GET['type'] ?? '';

// Base query
$sql = "SELECT * FROM clients WHERE deleted_at IS NULL";
$params = [];

// Search by Client ID or Name
if (!empty($search)) {
    $sql .= " AND (client_id LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Filter by Client Type
if (!empty($type)) {
    $sql .= " AND client_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY client_id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Clients</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="app-shell">
<?php $activePage = 'clients.php'; require_once 'sidebar.php'; ?>
<div class="main-panel">
<?php $pageTitle='Clients'; $pageSubtitle='Manage client records, contacts and billing'; require_once 'assets/inc/topbar.php'; ?>
<div class="page-shell">
    <div class="page-header">
        <div class="page-title">
            <h2>Manage Clients</h2>
            <p><?= count($clients) ?> client records</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="add_client.php">+ Add Client</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-grid" style="margin-bottom:16px;">
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input class="form-control" type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by client ID or name">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="Corporation" <?= $type == 'Corporation' ? 'selected' : '' ?>>Corporation</option>
                        <option value="Individual" <?= $type == 'Individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="PMB" <?= $type == 'PMB' ? 'selected' : '' ?>>PMB</option>
                        <option value="Immigration" <?= $type == 'Immigration' ? 'selected' : '' ?>>Immigration</option>
                    </select>
                </div>
                <div class="form-group" style="justify-content:flex-end;">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
                <div class="form-group" style="justify-content:flex-end;">
                    <a class="btn btn-secondary" href="clients.php">Clear</a>
                </div>
            </form>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Balance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clients) > 0): ?>
                            <?php foreach($clients as $client): ?>
                            <tr>
                                <td><?= htmlspecialchars($client['client_id']) ?></td>
                                <td><?= htmlspecialchars($client['full_name']) ?></td>
                                <td><?= htmlspecialchars($client['client_type']) ?></td>
                                <td><?= htmlspecialchars($client['phone']) ?></td>
                                <td><span class="badge badge-danger">$<?= number_format($client['balance'], 2) ?></span></td>
                                <td>
                                    <div class="page-actions">
                                        <a class="btn btn-sm btn-outline" href="view_client.php?id=<?= (int)$client['id'] ?>">View</a>
                                        <a class="btn btn-sm btn-outline" href="edit_client.php?id=<?= (int)$client['id'] ?>">Edit</a>
                                        <a class="btn btn-sm btn-danger" href="delete_client.php?id=<?= (int)$client['id'] ?>" onclick="return confirm('Soft delete this client?')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:32px; color:var(--text-muted);">No clients found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>