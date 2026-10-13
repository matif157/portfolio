<?php
/**
 * =====================================================
 * FILE: projects.php
 * PURPOSE: Projects list + create project form
 * FIELDS: Product/Service, Client, Start Date, Due Date,
 *         Title, Description, Assign To (company CRM style)
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// ---------- CREATE PROJECT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    $service_id    = (int)($_POST['service_id'] ?? 0);
    $service_name  = trim($_POST['service_name'] ?? '');
    $client_id     = (int)($_POST['client_id'] ?? 0);
    $client_name   = trim($_POST['client_name'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $start_date    = $_POST['start_date'] ?: null;
    $due_date      = $_POST['due_date'] ?: null;
    $assigned_to   = (int)($_POST['assigned_to'] ?? 0);
    $assigned_name = trim($_POST['assigned_name'] ?? '');

    if ($title === '') {
        $_SESSION['msg'] = 'Project title is required';
        $_SESSION['msg_type'] = 'danger';
        header('Location: projects.php');
        exit;
    }

    // Insert project
    $stmt = $pdo->prepare("INSERT INTO projects
        (service_id, service_name, client_id, client_name, title, description, start_date, due_date, assigned_to, assigned_name, status, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?, 'Open', ?)");
    $stmt->execute([
        $service_id ?: null,
        $service_name,
        $client_id ?: null,
        $client_name,
        $title,
        $description,
        $start_date,
        $due_date,
        $assigned_to ?: null,
        $assigned_name,
        $_SESSION['user_id']
    ]);

    $project_id = (int)$pdo->lastInsertId();

    // Project code like 4913 style
    $project_code = (string)(4000 + $project_id);
    $pdo->prepare("UPDATE projects SET project_code=? WHERE id=?")->execute([$project_code, $project_id]);

    /**
     * Copy pipeline template of this service into project_steps
     * Why: each service can have different steps (I-864 vs Sales Tax)
     */
    $tpl = $pdo->prepare("SELECT * FROM project_pipeline_templates
                          WHERE is_active=1 AND (service_id=? OR service_name=?)
                          ORDER BY sort_order ASC, id ASC");
    $tpl->execute([$service_id, $service_name]);
    $steps = $tpl->fetchAll(PDO::FETCH_ASSOC);

    // Agar template nahi mila to default 3 steps
    if (!$steps) {
        $steps = [
            ['step_title' => 'Collect Documents', 'step_type' => 'checkbox', 'is_required' => 1, 'sort_order' => 1],
            ['step_title' => 'Process / File', 'step_type' => 'checkbox', 'is_required' => 1, 'sort_order' => 2],
            ['step_title' => 'Deliver / Close', 'step_type' => 'checkbox', 'is_required' => 1, 'sort_order' => 3],
        ];
    }

    $ins = $pdo->prepare("INSERT INTO project_steps
        (project_id, step_title, step_type, is_required, sort_order)
        VALUES (?,?,?,?,?)");

    foreach ($steps as $s) {
        $ins->execute([
            $project_id,
            $s['step_title'],
            $s['step_type'] ?? 'checkbox',
            $s['is_required'] ?? 1,
            $s['sort_order'] ?? 0
        ]);
    }

    if (function_exists('log_activity')) {
        log_activity($pdo, 'Project', 'Created', $project_id, ['title' => $title]);
    }

    $_SESSION['msg'] = 'Project created successfully';
    $_SESSION['msg_type'] = 'success';
    header('Location: project_view.php?id=' . $project_id);
    exit;
}

// ---------- LIST DATA ----------
$projects = $pdo->query("SELECT * FROM projects WHERE is_active=1 ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

// Services list (from services table if exists, else fallback names)
$services = [];
try {
    $services = $pdo->query("SELECT id, service_name, default_price FROM services WHERE is_active=1 ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // fallback sample from your CSV style names
    $services = [
        ['id' => 0, 'service_name' => 'I-864', 'default_price' => 130],
        ['id' => 0, 'service_name' => 'I-130 (Spouse)', 'default_price' => 315],
        ['id' => 0, 'service_name' => 'SALES TAX (Quarter)', 'default_price' => 80],
        ['id' => 0, 'service_name' => 'BOOK KEEPING', 'default_price' => 0],
        ['id' => 0, 'service_name' => 'N-400', 'default_price' => 230],
    ];
}

// Clients
$clients = [];
try {
    $clients = $pdo->query("SELECT id, full_name, client_id FROM clients WHERE deleted_at IS NULL OR deleted_at='' ORDER BY full_name LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Staff for Assign To
$staff = [];
try {
    $staff = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Projects</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#f3f4f6; font-family: Poppins, Arial, sans-serif; color:#1e293b; }
        .main { margin-left:320px; padding:24px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; }
        .btn-primary { background:#4f46e5; color:#fff; border-radius:10px; padding:10px 16px; }
        .btn-dark { background:#1e293b; color:#fff; border-radius:10px; padding:8px 12px; font-size:13px; }
        table th { background:#1e293b; color:#fff; font-size:12px; }
        table td { font-size:13px; }
    </style>
</head>
<body>
<div class="main">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-bold">Projects</h1>
            <p class="text-slate-500 text-sm">Create and track service-based project pipelines</p>
        </div>
        <div class="flex gap-2">
            <a href="project_pipeline_customizer.php" class="btn-dark">Pipeline Customizer</a>
            <button onclick="document.getElementById('createBox').classList.toggle('hidden')" class="btn-primary">+ Add New Project</button>
        </div>
    </div>

    <?php if (!empty($_SESSION['msg'])): ?>
        <div class="mb-4 p-3 rounded-lg <?= ($_SESSION['msg_type'] ?? '') === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($_SESSION['msg']); unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        </div>
    <?php endif; ?>

    <!-- CREATE FORM (company CRM fields) -->
    <div id="createBox" class="card p-5 mb-6 hidden">
        <h2 class="font-semibold text-lg mb-4">Create Project</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="text-sm text-slate-600">Product / Service</label>
                <select name="service_id" id="serviceSelect" required class="w-full mt-1 p-2 border rounded-lg"
                        onchange="document.getElementById('serviceName').value=this.options[this.selectedIndex].text">
                    <option value="">Select Product</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['service_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="service_name" id="serviceName">
            </div>

            <div>
                <label class="text-sm text-slate-600">Client</label>
                <select name="client_id" id="clientSelect" class="w-full mt-1 p-2 border rounded-lg"
                        onchange="document.getElementById('clientName').value=this.options[this.selectedIndex].text">
                    <option value="">Select Client</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="client_name" id="clientName">
            </div>

            <div>
                <label class="text-sm text-slate-600">Start Date</label>
                <input type="date" name="start_date" class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div>
                <label class="text-sm text-slate-600">Due Date</label>
                <input type="date" name="due_date" class="w-full mt-1 p-2 border rounded-lg">
            </div>

            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Project Title</label>
                <input type="text" name="title" required class="w-full mt-1 p-2 border rounded-lg" placeholder="e.g. I-864 Joint Sponsor">
            </div>

            <div class="md:col-span-2">
                <label class="text-sm text-slate-600">Project Description</label>
                <textarea name="description" rows="3" class="w-full mt-1 p-2 border rounded-lg"></textarea>
            </div>

            <div>
                <label class="text-sm text-slate-600">Assign To</label>
                <select name="assigned_to" class="w-full mt-1 p-2 border rounded-lg"
                        onchange="document.getElementById('assignedName').value=this.options[this.selectedIndex].text">
                    <option value="">Select Staff</option>
                    <?php foreach ($staff as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= ((int)$u['id'] === (int)$_SESSION['user_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="assigned_name" id="assignedName" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>">
            </div>

            <div class="md:col-span-2">
                <button class="btn-primary">Create Project</button>
            </div>
        </form>
    </div>

    <!-- PROJECTS TABLE -->
    <div class="card overflow-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="p-3 text-left">PROJECT ID</th>
                    <th class="p-3 text-left">CLIENT</th>
                    <th class="p-3 text-left">PRODUCT</th>
                    <th class="p-3 text-left">TITLE</th>
                    <th class="p-3 text-left">ASSIGNED TO</th>
                    <th class="p-3 text-left">START</th>
                    <th class="p-3 text-left">DUE</th>
                    <th class="p-3 text-left">STATUS</th>
                    <th class="p-3 text-left">ACTION</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$projects): ?>
                <tr><td colspan="9" class="p-6 text-center text-slate-500">No projects yet</td></tr>
            <?php endif; ?>
            <?php foreach ($projects as $p): ?>
                <tr class="border-t">
                    <td class="p-3 font-semibold"><?= htmlspecialchars($p['project_code'] ?: $p['id']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['client_name'] ?: '-') ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['service_name'] ?: '-') ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['title']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['assigned_name'] ?: '-') ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['start_date'] ?: '-') ?></td>
                    <td class="p-3"><?= htmlspecialchars($p['due_date'] ?: '-') ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded-full text-xs bg-indigo-100 text-indigo-700"><?= htmlspecialchars($p['status']) ?></span>
                    </td>
                    <td class="p-3">
                        <a class="text-indigo-600 font-medium" href="project_view.php?id=<?= (int)$p['id'] ?>">Open Pipeline</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>