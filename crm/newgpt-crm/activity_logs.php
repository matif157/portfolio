<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();
$items = $pdo->query("SELECT a.*, COALESCE(CONCAT(u.first_name,' ',u.last_name),'System') AS user_name FROM activities a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 200")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Activity Logs · SPS CRM</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:20px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1200px;margin:auto;padding:28px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px;overflow:auto}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:12px;border-bottom:1px solid #edf0f5;font-size:13px}.table th{color:#6d788a;font-size:11px;text-transform:uppercase}</style></head>
<body><header class="top"><strong>SPS CRM · Activity Logs</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap"><h1>Activity Logs</h1><div class="card"><table class="table"><thead><tr><th>Date</th><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>IP</th></tr></thead><tbody>
<?php foreach ($items as $x): ?><tr><td><?= e($x['created_at']) ?></td><td><?= e($x['user_name']) ?></td><td><?= e($x['action']) ?></td><td><?= e($x['module']) ?></td><td><?= e($x['description']) ?></td><td><?= e($x['ip_address']) ?></td></tr><?php endforeach; ?>
<?php if (!$items): ?><tr><td colspan="6">No activity recorded.</td></tr><?php endif; ?></tbody></table></div></main></body></html>
