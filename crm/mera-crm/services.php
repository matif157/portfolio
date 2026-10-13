<?php
/**
 * =====================================================
 * FILE: services.php
 * PURPOSE: Manage services (list/add/edit/toggle/delete)
 * =====================================================
 */

require_once 'log_in.php';
require_login();
require_once 'sidebar.php';

// ---------- ADD / EDIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action'] ?? '';
    $id             = (int)($_POST['id'] ?? 0);
    $service_name   = trim($_POST['service_name'] ?? '');
    $default_price  = (float)($_POST['default_price'] ?? 0);
    $client_type_id = (int)($_POST['client_type_id'] ?? 0);
    $description    = trim($_POST['description'] ?? '');
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    if ($service_name === '') {
        $_SESSION['msg'] = 'Service name is required';
        $_SESSION['msg_type'] = 'danger';
        header('Location: services.php');
        exit;
    }

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO services (service_name, default_price, client_type_id, description, is_active)
                                   VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([
                $service_name,
                $default_price,
                $client_type_id > 0 ? $client_type_id : null,
                $description
            ]);
            $_SESSION['msg'] = 'Service added successfully';
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE services
                                   SET service_name=?, default_price=?, client_type_id=?, description=?, is_active=?
                                   WHERE id=?");
            $stmt->execute([
                $service_name,
                $default_price,
                $client_type_id > 0 ? $client_type_id : null,
                $description,
                $is_active,
                $id
            ]);
            $_SESSION['msg'] = 'Service updated successfully';
        }

        $_SESSION['msg_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['msg'] = $e->getMessage();
        $_SESSION['msg_type'] = 'danger';
    }

    header('Location: services.php');
    exit;
}

// ---------- TOGGLE ----------
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE services SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
    $_SESSION['msg'] = 'Service status updated';
    $_SESSION['msg_type'] = 'success';
    header('Location: services.php');
    exit;
}

// ---------- DELETE ----------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM services WHERE id=?")->execute([$id]);
    $_SESSION['msg'] = 'Service deleted';
    $_SESSION['msg_type'] = 'success';
    header('Location: services.php');
    exit;
}

// ---------- DATA ----------
$types = [];
try {
    $types = $pdo->query("SELECT id, type_name FROM client_types WHERE is_active=1 ORDER BY type_name")->fetchAll();
} catch (Exception $e) {}

$services = $pdo->query("SELECT s.*, ct.type_name
                         FROM services s
                         LEFT JOIN client_types ct ON ct.id = s.client_type_id
                         ORDER BY s.is_active DESC, s.service_name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - <?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#f3f4f6; font-family: Poppins, Arial, sans-serif; color:#1e293b; }
        .main { margin-left:320px; padding:24px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; }
        @media(max-width:900px){ .main{margin-left:0;padding:16px;} }
    </style>
</head>
<body>
<div class="main">

    <div class="flex flex-wrap justify-between items-center gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">Services</h1>
            <p class="text-slate-500 text-sm">Total services: <?= count($services) ?></p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="px-4 py-2 rounded-lg bg-indigo-600 text-white">
            + Add Service
        </button>
    </div>

    <?php if (!empty($_SESSION['msg'])): ?>
        <div class="mb-4 p-3 rounded-lg <?= ($_SESSION['msg_type'] ?? '') === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card overflow-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-900 text-white">
                <tr>
                    <th class="p-3 text-left">ID</th>
                    <th class="p-3 text-left">Service Name</th>
                    <th class="p-3 text-left">Client Type</th>
                    <th class="p-3 text-left">Price</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$services): ?>
                <tr>
                    <td colspan="6" class="p-6 text-center text-slate-500">
                        No services found. Please run services INSERT SQL + UPDATE is_active=1
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($services as $s): ?>
                <tr class="border-t">
                    <td class="p-3"><?= (int)$s['id'] ?></td>
                    <td class="p-3 font-medium"><?= htmlspecialchars($s['service_name']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($s['type_name'] ?? '-') ?></td>
                    <td class="p-3">$<?= number_format((float)$s['default_price'], 2) ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs <?= ((int)$s['is_active'] === 1) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' ?>">
                            <?= ((int)$s['is_active'] === 1) ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td class="p-3 space-x-2">
                        <button class="text-amber-600"
                            onclick='editService(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>Edit</button>
                        <a class="text-slate-600" href="?toggle=<?= (int)$s['id'] ?>">Toggle</a>
                        <a class="text-red-600" href="?delete=<?= (int)$s['id'] ?>" onclick="return confirm('Delete this service?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MODAL -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <form method="POST" class="card p-5 w-full max-w-md">
        <input type="hidden" name="action" value="add">
        <h3 class="font-semibold text-lg mb-3">Add Service</h3>

        <label class="text-sm">Service Name</label>
        <input name="service_name" required class="w-full p-2 border rounded mb-3">

        <label class="text-sm">Default Price</label>
        <input type="number" step="0.01" name="default_price" value="0" class="w-full p-2 border rounded mb-3">

        <label class="text-sm">Client Type</label>
        <select name="client_type_id" class="w-full p-2 border rounded mb-3">
            <option value="">None</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="text-sm">Description</label>
        <textarea name="description" class="w-full p-2 border rounded mb-3"></textarea>

        <div class="flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-3 py-2 bg-slate-200 rounded">Cancel</button>
            <button class="px-3 py-2 bg-indigo-600 text-white rounded">Save</button>
        </div>
    </form>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <form method="POST" class="card p-5 w-full max-w-md">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <h3 class="font-semibold text-lg mb-3">Edit Service</h3>

        <label class="text-sm">Service Name</label>
        <input name="service_name" id="edit_name" required class="w-full p-2 border rounded mb-3">

        <label class="text-sm">Default Price</label>
        <input type="number" step="0.01" name="default_price" id="edit_price" class="w-full p-2 border rounded mb-3">

        <label class="text-sm">Client Type</label>
        <select name="client_type_id" id="edit_type" class="w-full p-2 border rounded mb-3">
            <option value="">None</option>
            <?php foreach ($types as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="text-sm">Description</label>
        <textarea name="description" id="edit_desc" class="w-full p-2 border rounded mb-3"></textarea>

        <label class="text-sm inline-flex items-center gap-2 mb-3">
            <input type="checkbox" name="is_active" id="edit_active"> Active
        </label>

        <div class="flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-3 py-2 bg-slate-200 rounded">Cancel</button>
            <button class="px-3 py-2 bg-indigo-600 text-white rounded">Update</button>
        </div>
    </form>
</div>

<script>
function editService(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_name').value = s.service_name || '';
    document.getElementById('edit_price').value = s.default_price || 0;
    document.getElementById('edit_type').value = s.client_type_id || '';
    document.getElementById('edit_desc').value = s.description || '';
    document.getElementById('edit_active').checked = String(s.is_active) === '1';
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>