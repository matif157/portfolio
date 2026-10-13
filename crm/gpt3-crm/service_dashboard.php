<?php
require __DIR__.'/auth.php';
require __DIR__.'/db.php';
require __DIR__.'/functions.php';
require_login();
$pdo=db();

$metrics=[
 'active_services'=>(int)$pdo->query("SELECT COUNT(*) FROM client_services WHERE status IN ('requested','active','in_progress')")->fetchColumn(),
 'missing_requirements'=>(int)$pdo->query("SELECT COUNT(*) FROM client_service_requirements WHERE status<>'completed'")->fetchColumn(),
 'queued_messages'=>(int)$pdo->query("SELECT COUNT(*) FROM communications WHERE status='queued'")->fetchColumn(),
 'overdue_invoices'=>(int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE balance>0 AND due_date<CURDATE()")->fetchColumn()
];
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Service Automation</title>
<style>
body{margin:0;background:#f6f8fb;font-family:Arial,sans-serif;color:#152033}.wrap{max-width:1200px;margin:32px auto;padding:0 16px}
h1{margin-bottom:5px}.muted{color:#667085}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}
.card{background:#fff;border:1px solid #e4e7ec;border-radius:12px;padding:20px}.card b{font-size:28px;display:block;margin-top:8px}
@media(max-width:800px){.cards{grid-template-columns:1fr 1fr}}@media(max-width:500px){.cards{grid-template-columns:1fr}}
</style></head>
<body><div class="wrap">
<h1>Service Automation</h1>
<p class="muted">Operational view of active client services and automation queues.</p>
<div class="cards">
<div class="card"><span>Active Services</span><b><?=$metrics['active_services']?></b></div>
<div class="card"><span>Missing Requirements</span><b><?=$metrics['missing_requirements']?></b></div>
<div class="card"><span>Queued Messages</span><b><?=$metrics['queued_messages']?></b></div>
<div class="card"><span>Overdue Invoices</span><b><?=$metrics['overdue_invoices']?></b></div>
</div>
<div class="card">
<h2>Automation sequence</h2>
<p>Requirement detected → client request → document uploaded → requirement completed → workflow advances → task/approval generated.</p>
</div>
</div></body></html>
