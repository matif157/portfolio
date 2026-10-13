<?php
/**
 * =====================================================
 * FILE: client_types.php
 * PURPOSE: Manage Client Types (Add/Edit/Disable/Delete)
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied');
}

// ---------- ADD / EDIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $type_name = trim($_POST['type_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($type_name === '') {
        $_SESSION['msg'] = 'Type name required';
        $_SESSION['msg_type'] = 'danger';
        header('Location: client_types.php');
        exit;
    }

    try {
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO client_types (type_name, description) VALUES (?, ?)");
            $stmt->execute([$type_name, $description]);
            if (function_exists('log_activity')) {
                log_activity($pdo, 'ClientType', 'Created', $pdo->lastInsertId(), ['type_name' => $type_name]);
            }
            $_SESSION['msg'] = 'Client type added';
        }

        if ($action === 'edit' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE client_types SET type_name=?, description=? WHERE id=?");
            $stmt->execute([$type_name, $description, $id]);
            if (function_exists('log_activity')) {
                log_activity($pdo, 'ClientType', 'Updated', $id, ['type_name' => $type_name]);
            }
            $_SESSION['msg'] = 'Client type updated';
        }
        $_SESSION['msg_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['msg'] = $e->getMessage();
        $_SESSION['msg_type'] = 'danger';
    }
    header('Location: client_types.php');
    exit;
}

// ---------- DISABLE / ENABLE ----------
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE client_types SET is_active = IF(is_active=1,0,1) WHERE id=?")->execute([$id]);
    $_SESSION['msg'] = 'Status updated';
    $_SESSION['msg_type'] = 'success';
    header('Location: client_types.php');
    exit;
}

// ---------- DELETE ----------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];

    // Safety: if services exist under this type, block delete
    $count = $pdo->prepare("SELECT COUNT(*) FROM services WHERE client_type_id=?");
    $count->execute([$id]);
    if ((int)$count->fetchColumn() > 0) {
        $_SESSION['msg'] = 'Cannot delete. Services exist under this client type. Disable instead.';
        $_SESSION['msg_type'] = 'warning';
        header('Location: client_types.php');
        exit;
    }

    $pdo->prepare("DELETE FROM client_types WHERE id=?")->execute([$id]);
    $_SESSION['msg'] = 'Client type deleted';
    $_SESSION['msg_type'] = 'success';
    header('Location: client_types.php');
    exit;
}

$types = $pdo->query("
    SELECT ct.*,
           (SELECT COUNT(*) FROM services s WHERE s.client_type_id = ct.id) AS service_count,
           (SELECT COUNT(*) FROM clients c WHERE c.client_type_id = ct.id) AS client_count
    FROM client_types ct
    ORDER BY ct.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Client Types</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body{background:#020617;color:#e2e8f0;font-family:Poppins,Arial,sans-serif}
        .main{margin-left:320px;padding:24px}
        .card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:16px}
    </style>
</head>
<body>
<?php if (file_exists('header_widget.php')) require_once 'header_widget.php'; ?>
<div class="main">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Client Types</h1>
            <p class="text-slate-400 text-sm">First define types, then attach services under each type</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="px-4 py-2 rounded-xl bg-blue-600 text-white">+ Add Client Type</button>
    </div>

    <?php if (!empty($_SESSION['msg'])): ?>
        <div class="mb-4 p-3 rounded-lg <?= ($_SESSION['msg_type']??'')==='success'?'bg-emerald-500/20 text-emerald-300':'bg-red-500/20 text-red-300' ?>">
            <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        </div>
    <?php endif; ?>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5 text-slate-300">
                <tr>
                    <th class="text-left p-3">#</th>
                    <th class="text-left p-3">Type Name</th>
                    <th class="text-left p-3">Description</th>
                    <th class="text-left p-3">Services</th>
                    <th class="text-left p-3">Clients</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($types as $i => $t): ?>
                <tr class="border-t border-white/5">
                    <td class="p-3"><?= $i+1 ?></td>
                    <td class="p-3 font-medium text-white"><?= htmlspecialchars($t['type_name']) ?></td>
                    <td class="p-3 text-slate-400"><?= htmlspecialchars($t['description'] ?? '-') ?></td>
                    <td class="p-3"><?= (int)$t['service_count'] ?></td>
                    <td class="p-3"><?= (int)$t['client_count'] ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs <?= $t['is_active'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300' ?>">
                            <?= $t['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td class="p-3 space-x-2">
                        <a class="text-cyan-400" href="services.php?type_id=<?= $t['id'] ?>">Services</a>
                        <button class="text-amber-300" onclick='editType(<?= json_encode($t) ?>)'>Edit</button>
                        <a class="text-slate-300" href="?toggle=<?= $t['id'] ?>" onclick="return confirm('Toggle status?')">Disable/Enable</a>
                        <a class="text-red-400" href="?delete=<?= $t['id'] ?>" onclick="return confirm('Delete this type?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <form method="POST" class="card p-5 w-full max-w-md">
        <input type="hidden" name="action" value="add">
        <h3 class="text-lg text-white mb-3">Add Client Type</h3>
        <label class="text-sm text-slate-400">Type Name</label>
        <input name="type_name" required class="w-full mb-3 mt-1 p-2 rounded bg-white/5 border border-white/10 text-white">
        <label class="text-sm text-slate-400">Description</label>
        <textarea name="description" class="w-full mb-3 mt-1 p-2 rounded bg-white/5 border border-white/10 text-white"></textarea>
        <div class="flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-3 py-2 rounded bg-white/10">Cancel</button>
            <button class="px-3 py-2 rounded bg-blue-600 text-white">Save</button>
        </div>
    </form>
</div>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <form method="POST" class="card p-5 w-full max-w-md">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <h3 class="text-lg text-white mb-3">Edit Client Type</h3>
        <label class="text-sm text-slate-400">Type Name</label>
        <input name="type_name" id="edit_name" required class="w-full mb-3 mt-1 p-2 rounded bg-white/5 border border-white/10 text-white">
        <label class="text-sm text-slate-400">Description</label>
        <textarea name="description" id="edit_desc" class="w-full mb-3 mt-1 p-2 rounded bg-white/5 border border-white/10 text-white"></textarea>
        <div class="flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-3 py-2 rounded bg-white/10">Cancel</button>
            <button class="px-3 py-2 rounded bg-blue-600 text-white">Update</button>
        </div>
    </form>
</div>

<script>
function editType(t){
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_name').value = t.type_name;
    document.getElementById('edit_desc').value = t.description || '';
    document.getElementById('editModal').classList.remove('hidden');
}
</script>
</body>
</html>