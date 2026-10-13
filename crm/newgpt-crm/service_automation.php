<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!phase3_ready($pdo)) redirect('upgrade_phase3.php');

function run_bank_statement_scan(PDO $pdo): array {
    $stats=['accounts'=>0,'missing_months'=>0,'complete_accounts'=>0,'review_tasks'=>0];
    $accounts=$pdo->query("SELECT ba.*,c.id client_id FROM bank_accounts ba JOIN clients c ON c.id=ba.client_id WHERE ba.active=1")->fetchAll();
    foreach($accounts as $a){
        $stats['accounts']++;
        ensure_periods_phase3($pdo,(int)$a['id'],(int)date('Y'));
        $st=$pdo->prepare('SELECT COUNT(*) FROM bank_statement_periods WHERE bank_account_id=? AND statement_year=? AND status="missing"');
        $st->execute([(int)$a['id'],(int)date('Y')]);$missing=(int)$st->fetchColumn();
        $stats['missing_months'] += $missing;
        $st=$pdo->prepare('SELECT COUNT(*) FROM bank_statement_periods WHERE bank_account_id=? AND statement_year=? AND status IN ("uploaded","validated")');
        $st->execute([(int)$a['id'],(int)date('Y')]);$received=(int)$st->fetchColumn();
        if($missing===0 && $received>=12){
            $stats['complete_accounts']++;
        }
        $rule=$pdo->query("SELECT id FROM automation_rules WHERE event_name='BANK_STATEMENT_CHECK' AND action_name='CREATE_MISSING_REQUIREMENTS' AND enabled=1 LIMIT 1")->fetchColumn();
        automation_log($pdo,'BANK_STATEMENT_CHECK','CREATE_MISSING_REQUIREMENTS',(int)$a['client_id'],null,'success',$missing.' missing months detected for '.$a['bank_name'], $rule?(int)$rule:null);
    }
    return $stats;
}
function ensure_periods_phase3(PDO $pdo,int $accountId,int $year): void {
    $st=$pdo->prepare('SELECT id FROM bank_statement_periods WHERE bank_account_id=? AND statement_year=?');
    $st->execute([$accountId,$year]);
    if($st->fetchColumn()) return;
    $ins=$pdo->prepare('INSERT INTO bank_statement_periods(bank_account_id,statement_year,statement_month,status) VALUES(?,?,?, "missing")');
    for($m=1;$m<=12;$m++)$ins->execute([$accountId,$year,$m]);
}

$msg=flash('success');
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(post_string('action')==='scan'){
        $stats=run_bank_statement_scan($pdo);
        activity($pdo,'automation_scan','service_automation',null,'Bank statement automation scan executed');
        flash('success','Automation scan completed: '.$stats['accounts'].' accounts checked and '.$stats['missing_months'].' missing months identified.');
        redirect('service_automation.php');
    }
}
$stats=['accounts'=>(int)$pdo->query("SELECT COUNT(*) FROM bank_accounts WHERE active=1")->fetchColumn(),
        'missing'=>(int)$pdo->query("SELECT COUNT(*) FROM bank_statement_periods WHERE statement_year=".date('Y')." AND status='missing'")->fetchColumn(),
        'uploaded'=>(int)$pdo->query("SELECT COUNT(*) FROM bank_statement_periods WHERE statement_year=".date('Y')." AND status='uploaded'")->fetchColumn(),
        'validated'=>(int)$pdo->query("SELECT COUNT(*) FROM bank_statement_periods WHERE statement_year=".date('Y')." AND status='validated'")->fetchColumn(),
        'logs'=>(int)$pdo->query("SELECT COUNT(*) FROM automation_logs WHERE created_at>=CURDATE()")->fetchColumn()];
$logs=$pdo->query("SELECT al.*,CONCAT(COALESCE(c.first_name,''),' ',COALESCE(c.last_name,'')) client_name FROM automation_logs al LEFT JOIN clients c ON c.id=al.client_id ORDER BY al.id DESC LIMIT 30")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service Automation · SPS CRM</title><style>body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1200px;margin:auto;padding:28px}.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:18px}.value{font-size:27px;font-weight:850;margin-top:7px}.label{font-size:12px;color:#6d788a}.btn{background:#315ee7;color:#fff;border:0;padding:11px 14px;border-radius:8px;font-weight:bold}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f5;text-align:left;font-size:12px}.success{background:#ebf9f1;color:#176a3c;padding:10px;border-radius:8px;margin-bottom:14px}@media(max-width:900px){.cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:550px){.cards{grid-template-columns:1fr}}</style></head><body><header class="top"><strong>SPS CRM · Service Automation</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap"><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><div class="card" style="margin-bottom:16px"><h1>Automation Control Center</h1><p>Phase 3 uses visible, traceable automation. It does not claim external email/SMS/voice/OCR actions that are not configured.</p><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="scan"><button class="btn">Run Bank Statement Scan</button></form></div><div class="cards"><div class="card"><div class="label">Active Accounts</div><div class="value"><?=$stats['accounts']?></div></div><div class="card"><div class="label">Missing Months</div><div class="value"><?=$stats['missing']?></div></div><div class="card"><div class="label">Uploaded</div><div class="value"><?=$stats['uploaded']?></div></div><div class="card"><div class="label">Validated</div><div class="value"><?=$stats['validated']?></div></div><div class="card"><div class="label">Today's Automation Logs</div><div class="value"><?=$stats['logs']?></div></div></div><div class="card" style="margin-top:16px"><h2>Recent Automation Logs</h2><table class="table"><tr><th>Date</th><th>Event</th><th>Action</th><th>Client</th><th>Status</th><th>Message</th></tr><?php foreach($logs as $l):?><tr><td><?=e($l['created_at'])?></td><td><?=e($l['event_name'])?></td><td><?=e($l['action_name'])?></td><td><?=e(trim($l['client_name'])?:'—')?></td><td><?=e($l['status'])?></td><td><?=e($l['message'])?></td></tr><?php endforeach;?></table><?php if(!$logs):?><p>No automation runs yet.</p><?php endif;?></div></main></body></html>
