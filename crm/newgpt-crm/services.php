<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!phase3_ready($pdo)) redirect('upgrade_phase3.php');
$msg=flash('success');

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=post_string('action');
    try{
        if($action==='start_service'){
            $clientId=post_int('client_id');$serviceId=post_int('service_id');
            if(!$clientId||!$serviceId) throw new RuntimeException('Client and service are required.');
            $st=$pdo->prepare('SELECT id,name FROM services WHERE id=? AND status="active"');$st->execute([$serviceId]);$service=$st->fetch();
            if(!$service) throw new RuntimeException('Service is not available.');
            $pdo->beginTransaction();
            $st=$pdo->prepare('INSERT INTO client_services(client_id,service_id,status,started_at) VALUES(?,?, "active",NOW())');$st->execute([$clientId,$serviceId]);
            $clientServiceId=(int)$pdo->lastInsertId();
            $steps=$pdo->prepare('SELECT * FROM service_workflow_steps WHERE service_id=? ORDER BY sort_order,id');$steps->execute([$serviceId]);$steps=$steps->fetchAll();
            $first=$steps[0]['id']??null;
            $st=$pdo->prepare('INSERT INTO service_instances(client_service_id,current_step_id,status,started_at) VALUES(?,?, "active",NOW())');$st->execute([$clientServiceId,$first]);$instanceId=(int)$pdo->lastInsertId();
            foreach($steps as $step){$st=$pdo->prepare('INSERT INTO service_step_instances(service_instance_id,workflow_step_id,status) VALUES(?,?,?)');$st->execute([$instanceId,$step['id'],'pending']);}
            if($first){$pdo->prepare('UPDATE service_step_instances SET status="in_progress",started_at=NOW() WHERE service_instance_id=? AND workflow_step_id=?')->execute([$instanceId,$first]);}
            $pdo->commit();
            activity($pdo,'start','services',$clientServiceId,'Service started: '.$service['name']);
            flash('success','Service started and workflow created.');
            redirect('service_workflow.php?client_service_id='.$clientServiceId);
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('[SPS SERVICES] '.$e->getMessage());
        flash('success',$e->getMessage());
        redirect('services.php');
    }
}
$clients=$pdo->query("SELECT id,first_name,last_name FROM clients WHERE deleted_at IS NULL ORDER BY last_name,first_name")->fetchAll();
$services=$pdo->query("SELECT s.*, (SELECT COUNT(*) FROM service_workflow_steps sw WHERE sw.service_id=s.id) step_count FROM services s WHERE s.status='active' ORDER BY s.category,s.name")->fetchAll();
$active=$pdo->query("SELECT cs.id,cs.status,CONCAT(c.first_name,' ',c.last_name) client_name,s.name service_name,si.id instance_id,si.status instance_status FROM client_services cs JOIN clients c ON c.id=cs.client_id JOIN services s ON s.id=cs.service_id LEFT JOIN service_instances si ON si.client_service_id=cs.id WHERE cs.status NOT IN ('completed','closed') ORDER BY cs.id DESC LIMIT 100")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Services · SPS CRM</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1150px;margin:auto;padding:28px}.grid{display:grid;grid-template-columns:1fr 1.5fr;gap:16px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:15px;padding:20px;margin-bottom:16px}.field{margin:11px 0}.field label{display:block;font-size:12px;font-weight:800;margin-bottom:6px}.field select{width:100%;padding:11px;border:1px solid #d8dfeb;border-radius:9px}.btn{background:#315ee7;color:#fff;border:0;border-radius:9px;padding:11px 14px;font-weight:800}.table{width:100%;border-collapse:collapse}.table th,.table td{text-align:left;padding:11px;border-bottom:1px solid #edf0f5;font-size:13px}.muted{color:#6d788a}.success{background:#ebf9f1;color:#176a3c;padding:10px;border-radius:8px;margin-bottom:14px}.link{color:#315ee7;text-decoration:none;font-weight:800}@media(max-width:820px){.grid{grid-template-columns:1fr}}</style></head><body><header class="top"><strong>SPS CRM · Services</strong><a href="dashboard.php">← Dashboard</a></header><main class="wrap"><?php if($msg):?><div class="success"><?=e($msg)?></div><?php endif;?><div class="grid"><div class="card"><h2>Start Service</h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="start_service"><div class="field"><label>Client</label><select name="client_id" required><option value="">Select client</option><?php foreach($clients as $c):?><option value="<?=$c['id']?>"><?=e($c['first_name'].' '.$c['last_name'])?></option><?php endforeach;?></select></div><div class="field"><label>Service</label><select name="service_id" required><option value="">Select service</option><?php foreach($services as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?> (<?=e($s['step_count'])?> workflow steps)</option><?php endforeach;?></select></div><button class="btn">Start Workflow</button></form></div><div class="card"><h2>Service Catalog</h2><?php foreach($services as $s):?><div style="padding:12px 0;border-bottom:1px solid #edf0f5"><strong><?=e($s['name'])?></strong><div class="muted"><?=e($s['category']?:'General')?> · <?=e($s['step_count'])?> workflow steps</div></div><?php endforeach;?></div></div><div class="card"><h2>Active Client Services</h2><table class="table"><tr><th>Client</th><th>Service</th><th>Service Status</th><th>Workflow</th></tr><?php foreach($active as $x):?><tr><td><?=e($x['client_name'])?></td><td><?=e($x['service_name'])?></td><td><?=e($x['status'])?></td><td><?php if($x['instance_id']):?><a class="link" href="service_workflow.php?client_service_id=<?=$x['id']?>">Open Workflow</a><?php else:?>No workflow<?php endif;?></td></tr><?php endforeach;?><?php if(!$active):?><tr><td colspan="4">No active client services.</td></tr><?php endif;?></table></div></main></body></html>
