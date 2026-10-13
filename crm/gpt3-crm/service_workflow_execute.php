<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';
require __DIR__.'/functions.php';

$user = current_user();
$projectId = (int)($_GET['project_id'] ?? 0);
if ($projectId < 1) { http_response_code(400); exit('Project required'); }

$pdo = db();
$project = $pdo->prepare(
    "SELECT p.*, s.name service_name, c.first_name, c.last_name
     FROM projects p
     LEFT JOIN services s ON s.id=p.service_id
     LEFT JOIN clients c ON c.id=p.client_id
     WHERE p.id=? LIMIT 1"
);
$project->execute([$projectId]);
$project = $project->fetch();
if (!$project) { http_response_code(404); exit('Project not found'); }

$execution = $pdo->prepare(
    "SELECT we.*, wv.name workflow_name
     FROM workflow_executions we
     JOIN workflow_versions wv ON wv.id=we.workflow_version_id
     WHERE we.project_id=?
     ORDER BY we.id DESC LIMIT 1"
);
$execution->execute([$projectId]);
$execution = $execution->fetch();

$steps = [];
if ($execution) {
    $q = $pdo->prepare(
        "SELECT wse.id, wse.status, wse.completed_at, ws.title, ws.step_type, ws.requirement, ws.sort_order
         FROM workflow_step_executions wse
         JOIN workflow_steps ws ON ws.id=wse.workflow_step_id
         WHERE wse.workflow_execution_id=?
         ORDER BY ws.sort_order, ws.id"
    );
    $q->execute([$execution['id']]);
    $steps = $q->fetchAll();
}

$title = 'Service Workflow';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($title) ?></title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;color:#18212f;margin:0}
.wrap{max-width:1100px;margin:30px auto;padding:0 16px}
.card{background:#fff;border:1px solid #dfe4ea;border-radius:12px;padding:20px;margin-bottom:16px}
h1{margin:0 0 6px}.muted{color:#6b7280}
.step{display:flex;justify-content:space-between;gap:20px;padding:14px 0;border-bottom:1px solid #eef1f4;align-items:center}
.badge{padding:4px 8px;border-radius:999px;font-size:12px;background:#eef2f7}
.done{background:#dcfce7;color:#166534}.pending{background:#fef3c7;color:#92400e}
button{padding:8px 12px;border:0;border-radius:8px;background:#111827;color:#fff;cursor:pointer}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1><?= htmlspecialchars($project['name']) ?></h1>
    <div class="muted">
      <?= htmlspecialchars(trim(($project['first_name']??'').' '.($project['last_name']??''))) ?>
      · <?= htmlspecialchars($project['service_name'] ?? 'Service') ?>
    </div>
  </div>

  <div class="card">
    <h2><?= htmlspecialchars($execution['workflow_name'] ?? 'Workflow') ?></h2>
    <?php if (!$execution): ?>
      <p class="muted">No workflow has been started for this project yet.</p>
    <?php else: ?>
      <?php foreach ($steps as $step): ?>
        <div class="step">
          <div>
            <strong><?= htmlspecialchars($step['title']) ?></strong>
            <div class="muted"><?= htmlspecialchars($step['step_type']) ?> · <?= htmlspecialchars($step['requirement']) ?></div>
          </div>
          <div>
            <?php if ($step['status']==='completed'): ?>
              <span class="badge done">Completed</span>
            <?php else: ?>
              <span class="badge pending"><?= htmlspecialchars($step['status']) ?></span>
              <button data-step="<?= (int)$step['id'] ?>">Complete</button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<script>
document.querySelectorAll('[data-step]').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const id=btn.dataset.step;
    const path=location.pathname.replace('service_workflow_execute.php','workflow/<?= (int)($execution['id']??0) ?>/steps/')+id;
    const form=new FormData();
    const res=await fetch(path,{method:'POST',body:form});
    if(res.ok) location.reload(); else alert('Unable to update workflow step');
  });
});
</script>
</body>
</html>
