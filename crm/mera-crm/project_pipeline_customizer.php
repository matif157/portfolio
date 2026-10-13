<?php
/**
 * =====================================================
 * FILE: project_pipeline_customizer.php
 * PURPOSE: Manage pipeline steps for each service
 * You can add: checkbox / file / link / text steps
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied');
}

// Add step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $service_name = trim($_POST['service_name'] ?? '');
    $step_title   = trim($_POST['step_title'] ?? '');
    $step_type    = $_POST['step_type'] ?? 'checkbox';
    $is_required  = isset($_POST['is_required']) ? 1 : 0;
    $sort_order   = (int)($_POST['sort_order'] ?? 0);

    if ($service_name && $step_title) {
        $pdo->prepare("INSERT INTO project_pipeline_templates
            (service_name, step_title, step_type, is_required, sort_order)
            VALUES (?,?,?,?,?)")
            ->execute([$service_name, $step_title, $step_type, $is_required, $sort_order]);
    }
    header('Location: project_pipeline_customizer.php?service=' . urlencode($service_name));
    exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM project_pipeline_templates WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: project_pipeline_customizer.php');
    exit;
}

$service_filter = trim($_GET['service'] ?? '');

$services = [];
try {
    $services = $pdo->query("SELECT DISTINCT service_name FROM services WHERE is_active=1 ORDER BY service_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $services = ['I-864','I-130 (Spouse)','SALES TAX (Quarter)','BOOK KEEPING','N-400','IMMIGRATION'];
}

$sql = "SELECT * FROM project_pipeline_templates WHERE is_active=1";
$params = [];
if ($service_filter !== '') {
    $sql .= " AND service_name=?";
    $params[] = $service_filter;
}
$sql .= " ORDER BY service_name, sort_order, id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pipeline Customizer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body{background:#f3f4f6;font-family:Poppins,Arial,sans-serif}
        .main{margin-left:320px;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px}
    </style>
</head>
<body>
<div class="main">
    <div class="flex justify-between items-center mb-5">
        <div>
            <h1 class="text-2xl font-bold">Pipeline Customizer</h1>
            <p class="text-sm text-slate-500">Har service ke liye alag steps set karein</p>
        </div>
        <a href="projects.php" class="text-indigo-600">← Projects</a>
    </div>

    <div class="card p-5 mb-5">
        <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="text-sm">Service</label>
                <select name="service_name" required class="w-full p-2 border rounded-lg">
                    <?php foreach ($services as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>" <?= $service_filter===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-sm">Step Title</label>
                <input name="step_title" required class="w-full p-2 border rounded-lg" placeholder="Collect passport copy">
            </div>
            <div>
                <label class="text-sm">Type</label>
                <select name="step_type" class="w-full p-2 border rounded-lg">
                    <option value="checkbox">Checkbox</option>
                    <option value="file">File Upload</option>
                    <option value="link">Link / URL</option>
                    <option value="text">Text Field</option>
                </select>
            </div>
            <div>
                <label class="text-sm">Order</label>
                <input type="number" name="sort_order" value="1" class="w-full p-2 border rounded-lg">
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" checked> Required</label>
                <button class="block w-full mt-2 p-2 bg-indigo-600 text-white rounded-lg">Add Step</button>
            </div>
        </form>
    </div>

    <div class="card overflow-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-900 text-white">
                <tr>
                    <th class="p-3 text-left">Service</th>
                    <th class="p-3 text-left">Step</th>
                    <th class="p-3 text-left">Type</th>
                    <th class="p-3 text-left">Required</th>
                    <th class="p-3 text-left">Order</th>
                    <th class="p-3 text-left">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="border-t">
                    <td class="p-3"><?= htmlspecialchars($r['service_name']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($r['step_title']) ?></td>
                    <td class="p-3"><?= htmlspecialchars($r['step_type']) ?></td>
                    <td class="p-3"><?= $r['is_required'] ? 'Yes' : 'No' ?></td>
                    <td class="p-3"><?= (int)$r['sort_order'] ?></td>
                    <td class="p-3"><a class="text-red-600" href="?delete=<?= (int)$r['id'] ?>" onclick="return confirm('Delete step?')">Delete</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>