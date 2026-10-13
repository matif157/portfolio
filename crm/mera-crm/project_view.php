 <?php
/**
 * =====================================================
 * FILE: project_view.php
 * PURPOSE: Single project pipeline page
 * - show steps
 * - complete checkbox / save link / upload note
 * - auto progress %
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: projects.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$project) {
    exit('Project not found');
}

// Toggle step complete
if (isset($_GET['toggle_step'])) {
    $step_id = (int)$_GET['toggle_step'];
    $pdo->prepare("UPDATE project_steps SET is_completed = IF(is_completed=1,0,1) WHERE id=? AND project_id=?")
        ->execute([$step_id, $id]);

    // recalculate progress
    $total = (int)$pdo->query("SELECT COUNT(*) FROM project_steps WHERE project_id=$id")->fetchColumn();
    $done  = (int)$pdo->query("SELECT COUNT(*) FROM project_steps WHERE project_id=$id AND is_completed=1")->fetchColumn();
    $progress = $total ? round(($done / $total) * 100) : 0;
    $status = $progress >= 100 ? 'Completed' : 'Open';
    $pdo->prepare("UPDATE projects SET progress=?, status=? WHERE id=?")->execute([$progress, $status, $id]);

    header('Location: project_view.php?id=' . $id);
    exit;
}

// Save link/text value
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_step_value') {
    $step_id = (int)$_POST['step_id'];
    $link_url = trim($_POST['link_url'] ?? '');
    $text_value = trim($_POST['text_value'] ?? '');
    $pdo->prepare("UPDATE project_steps SET link_url=?, text_value=? WHERE id=? AND project_id=?")
        ->execute([$link_url, $text_value, $step_id, $id]);
    header('Location: project_view.php?id=' . $id);
    exit;
}

$steps = $pdo->prepare("SELECT * FROM project_steps WHERE project_id=? ORDER BY sort_order ASC, id ASC");
$steps->execute([$id]);
$steps = $steps->fetchAll(PDO::FETCH_ASSOC);

$total = count($steps);
$done = 0;
foreach ($steps as $s) if ((int)$s['is_completed'] === 1) $done++;
$progress = $total ? round(($done / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project #<?= htmlspecialchars($project['project_code'] ?: $project['id']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body{background:#f3f4f6;font-family:Poppins,Arial,sans-serif}
        .main{margin-left:320px;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px}
        .bar{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden}
        .bar>span{display:block;height:100%;background:linear-gradient(90deg,#4f46e5,#7c3aed)}
    </style>
</head>
<body>
<div class="main">
    <a href="projects.php" class="text-indigo-600 text-sm">← Back to Projects</a>

    <div class="card p-5 mt-3 mb-5">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <div class="text-sm text-slate-500">#<?= htmlspecialchars($project['project_code'] ?: $project['id']) ?> • <?= htmlspecialchars($project['service_name'] ?: '') ?></div>
                <h1 class="text-2xl font-bold mt-1"><?= htmlspecialchars($project['title']) ?></h1>
                <div class="text-sm text-slate-600 mt-1">
                    Client: <b><?= htmlspecialchars($project['client_name'] ?: '-') ?></b> |
                    Assigned: <b><?= htmlspecialchars($project['assigned_name'] ?: '-') ?></b>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm text-slate-500">Progress</div>
                <div class="text-2xl font-bold text-indigo-600"><?= $progress ?>%</div>
            </div>
        </div>
        <div class="bar mt-4"><span style="width:<?= $progress ?>%"></span></div>
        <p class="text-sm text-slate-500 mt-3"><?= nl2br(htmlspecialchars($project['description'] ?: '')) ?></p>
    </div>

    <h2 class="font-semibold text-lg mb-3">Pipeline Steps</h2>
    <div class="space-y-3">
        <?php foreach ($steps as $i => $s): ?>
            <div class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">
                            <?= $i+1 ?>. <?= htmlspecialchars($s['step_title']) ?>
                            <?php if ($s['is_required']): ?><span class="text-red-500 text-xs">*required</span><?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">Type: <?= htmlspecialchars($s['step_type']) ?></div>
                    </div>
                    <a href="?id=<?= $id ?>&toggle_step=<?= (int)$s['id'] ?>"
                       class="px-3 py-1 rounded-full text-xs <?= $s['is_completed'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $s['is_completed'] ? 'Completed' : 'Mark Done' ?>
                    </a>
                </div>

                <?php if ($s['step_type'] === 'link' || $s['step_type'] === 'text'): ?>
                    <form method="POST" class="mt-3 flex flex-wrap gap-2">
                        <input type="hidden" name="action" value="save_step_value">
                        <input type="hidden" name="step_id" value="<?= (int)$s['id'] ?>">
                        <?php if ($s['step_type'] === 'link'): ?>
                            <input type="url" name="link_url" value="<?= htmlspecialchars($s['link_url'] ?? '') ?>" placeholder="https://..." class="flex-1 p-2 border rounded-lg text-sm">
                        <?php else: ?>
                            <input type="text" name="text_value" value="<?= htmlspecialchars($s['text_value'] ?? '') ?>" placeholder="Enter value" class="flex-1 p-2 border rounded-lg text-sm">
                        <?php endif; ?>
                        <button class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
                    </form>
                <?php endif; ?>

                <?php if ($s['step_type'] === 'file'): ?>
                    <p class="text-xs text-slate-500 mt-2">File upload path support next step mein add karenge (easy extension).</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>