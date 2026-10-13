<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';require_login();$pdo=db();
$periodStart=(string)($_GET['start']??date('Y-m-01'));
$periodEnd=(string)($_GET['end']??date('Y-m-d'));
$summary=[
    'clients'=>(int)$pdo->query("SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL")->fetchColumn(),
    'active_services'=>table_exists($pdo,'client_services')?(int)$pdo->query("SELECT COUNT(*) FROM client_services WHERE status NOT IN ('completed','closed')")->fetchColumn():0,
    'projects'=>table_exists($pdo,'projects')?(int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('open','upcoming')")->fetchColumn():0,
    'invoiced'=>0.0,'paid'=>0.0,'missing_statements'=>0
];
if(table_exists($pdo,'invoices')){
    $st=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE issue_date BETWEEN ? AND ?");
    $st->execute([$periodStart,$periodEnd]);$summary['invoiced']=(float)$st->fetchColumn();
}
if(table_exists($pdo,'payments')){
    $st=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN ? AND ?");
    $st->execute([$periodStart,$periodEnd]);$summary['paid']=(float)$st->fetchColumn();
}
if(table_exists($pdo,'bank_statement_periods')){
    $summary['missing_statements']=(int)$pdo->query("SELECT COUNT(*) FROM bank_statement_periods WHERE statement_year=".((int)date('Y'))." AND status='missing'")->fetchColumn();
}
$activityRows=[];
if(table_exists($pdo,'activities')){
    $st=$pdo->prepare("SELECT module,action,COUNT(*) total FROM activities WHERE created_at BETWEEN ? AND ? GROUP BY module,action ORDER BY total DESC LIMIT 30");
    $st->execute([$periodStart.' 00:00:00',$periodEnd.' 23:59:59']);
    $activityRows=$st->fetchAll();
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reports · SPS CRM</title><style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1150px;margin:auto;padding:28px}.cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px;margin-bottom:16px}.label{font-size:12px;color:#6d788a}.value{font-size:28px;font-weight:850;margin-top:6px}.filter{display:flex;gap:10px;flex-wrap:wrap}.input{padding:10px;border:1px solid #d8dfeb;border-radius:8px}.btn{background:#315ee7;color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:bold}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f5;text-align:left;font-size:13px}@media(max-width:800px){.cards{grid-template-columns:1fr 1fr}}@media(max-width:520px){.cards{grid-template-columns:1fr}}</style></head>
<body><header class="top"><strong>SPS CRM · Reports</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap"><div class="card"><form class="filter" method="get"><label>From <input class="input" type="date" name="start" value="<?=e($periodStart)?>"></label><label>To <input class="input" type="date" name="end" value="<?=e($periodEnd)?>"></label><button class="btn">Refresh Report</button></form></div>
<div class="cards"><div class="card"><div class="label">Total Clients</div><div class="value"><?=$summary['clients']?></div></div><div class="card"><div class="label">Active Services</div><div class="value"><?=$summary['active_services']?></div></div><div class="card"><div class="label">Open Projects</div><div class="value"><?=$summary['projects']?></div></div><div class="card"><div class="label">Invoiced In Period</div><div class="value">$<?=money($summary['invoiced'])?></div></div><div class="card"><div class="label">Payments In Period</div><div class="value">$<?=money($summary['paid'])?></div></div><div class="card"><div class="label">Missing Bank Statements</div><div class="value"><?=$summary['missing_statements']?></div></div></div>
<div class="card"><h2>Activity Breakdown</h2><table class="table"><tr><th>Module</th><th>Action</th><th>Count</th></tr><?php foreach($activityRows as $r):?><tr><td><?=e($r['module'])?></td><td><?=e($r['action'])?></td><td><?=e($r['total'])?></td></tr><?php endforeach;?></table></div></main></body></html>
