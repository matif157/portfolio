<?php
declare(strict_types=1);
require_once __DIR__.'/auth.php';
require_login();
$pdo=db();
if(!phase3_ready($pdo)) redirect('upgrade_phase3.php');

function ensure_service_instance(PDO $pdo, int $clientServiceId): int {
    $st=$pdo->prepare('SELECT id FROM service_instances WHERE client_service_id=?');
    $st->execute([$clientServiceId]);
    $found=$st->fetchColumn();
    if($found) return (int)$found;

    $st=$pdo->prepare('SELECT cs.*,s.name service_name FROM client_services cs JOIN services s ON s.id=cs.service_id WHERE cs.id=?');
    $st->execute([$clientServiceId]); $cs=$st->fetch();
    if(!$cs) throw new RuntimeException('Client service not found.');

    $steps=$pdo->prepare('SELECT * FROM service_workflow_steps WHERE service_id=? ORDER BY sort_order,id');
    $steps->execute([(int)$cs['service_id']]); $steps=$steps->fetchAll();
    $pdo->beginTransaction();
    try{
        $first=$steps[0]['id']??null;
        $st=$pdo->prepare('INSERT INTO service_instances(client_service_id,current_step_id,status,started_at) VALUES(?,?,?,NOW())');
        $st->execute([$clientServiceId,$first,'active']);
        $instanceId=(int)$pdo->lastInsertId();

        foreach($steps as $step){
            $st=$pdo->prepare('INSERT INTO service_step_instances(service_instance_id,workflow_step_id,status) VALUES(?,?,?)');
            $st->execute([$instanceId,(int)$step['id'],'pending']);
        }
        if($first){
            $pdo->prepare('UPDATE service_step_instances SET status="in_progress",started_at=NOW() WHERE service_instance_id=? AND workflow_step_id=?')
                ->execute([$instanceId,(int)$first]);
        }
        $pdo->commit();
        return $instanceId;
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$clientServiceId=(int)($_GET['client_service_id']??$_POST['client_service_id']??0);
if(!$clientServiceId){die('Missing client service ID.');}

try{
    $instanceId=ensure_service_instance($pdo,$clientServiceId);
    $st=$pdo->prepare('SELECT si.*,cs.client_id, s.name service_name FROM service_instances si JOIN client_services cs ON cs.id=si.client_service_id JOIN services s ON s.id=cs.service_id WHERE si.id=?');
    $st->execute([$instanceId]);$instance=$st->fetch();
    $st=$pdo->prepare('SELECT ssi.*,sws.name step_name,sws.description, sws.step_type,sws.sort_order FROM service_step_instances ssi JOIN service_workflow_steps sws ON sws.id=ssi.workflow_step_id WHERE ssi.service_instance_id=? ORDER BY sws.sort_order,sws.id');
    $st->execute([$instanceId]);$steps=$st->fetchAll();

    if($_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();
        $action=post_string('action');
        if($action==='complete_step'){
            $stepId=post_int('step_id');
            $pdo->beginTransaction();
            try{
                $pdo->prepare('UPDATE service_step_instances SET status="completed",completed_at=NOW() WHERE id=? AND service_instance_id=?')
                    ->execute([$stepId,$instanceId]);
                $next=$pdo->prepare('SELECT ssi.id,ssi.workflow_step_id FROM service_step_instances ssi JOIN service_workflow_steps sws ON sws.id=ssi.workflow_step_id WHERE ssi.service_instance_id=? AND ssi.status="pending" ORDER BY sws.sort_order,sws.id LIMIT 1');
                $next->execute([$instanceId]);$n=$next->fetch();
                if($n){
                    $pdo->prepare('UPDATE service_step_instances SET status="in_progress",started_at=NOW() WHERE id=?')->execute([(int)$n['id']]);
                    $pdo->prepare('UPDATE service_instances SET current_step_id=?,status="active",last_automation_at=NOW() WHERE id=?')->execute([(int)$n['workflow_step_id'],$instanceId]);
                }else{
                    $pdo->prepare('UPDATE service_instances SET current_step_id=NULL,status="completed",completed_at=NOW(),last_automation_at=NOW() WHERE id=?')->execute([$instanceId]);
                    $pdo->prepare('UPDATE client_services SET status="completed",completed_at=NOW() WHERE id=?')->execute([$clientServiceId]);
                }
                $pdo->commit();
                activity($pdo,'workflow_step_complete','service_workflow',$instanceId,'Workflow step completed');
                flash('success','Workflow updated.');
            }catch(Throwable $e){
                if($pdo->inTransaction())$pdo->rollBack();
                flash('success','Workflow update failed.');
                error_log('[SPS WORKFLOW] '.$e->getMessage());
            }
            redirect('service_workflow.php?client_service_id='.$clientServiceId);
        }
    }
    $message=flash('success');
}catch(Throwable $e){
    error_log('[SPS SERVICE WORKFLOW] '.$e->getMessage());
    die('Unable to load service workflow.');
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service Workflow · SPS CRM</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f5f7fb;font-family:Arial;color:#172033}.top{background:#fff;border-bottom:1px solid #e5eaf2;padding:18px 28px;display:flex;justify-content:space-between}.top a{color:#315ee7;text-decoration:none}.wrap{max-width:1000px;margin:auto;padding:28px}.card{background:#fff;border:1px solid #e5eaf2;border-radius:16px;padding:22px;margin-bottom:16px}.success{background:#ebf9f1;color:#176a3c;padding:11px;border-radius:9px;margin-bottom:16px}.step{display:flex;gap:15px;padding:16px 0;border-bottom:1px solid #edf0f5;align-items:flex-start}.dot{width:28px;height:28px;border-radius:50%;display:grid;place-items:center;background:#e9edf4;color:#6c788c;font-weight:800;flex:0 0 28px}.done .dot{background:#ddf5e6;color:#16713b}.active .dot{background:#e8eeff;color:#315ee7}.status{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.muted{color:#6d788a}.btn{background:#315ee7;color:#fff;border:0;border-radius:9px;padding:9px 12px;font-weight:800;cursor:pointer}.btn.secondary{background:#eef2ff;color:#315ee7}</style></head><body>
<header class="top"><strong>SPS CRM · Service Workflow</strong><a href="dashboard.php">← Dashboard</a></header>
<main class="wrap"><?php if($message):?><div class="success"><?=e($message)?></div><?php endif;?>
<div class="card"><h1><?=e($instance['service_name'])?></h1><div class="muted">Workflow instance #<?=e($instanceId)?> · Status: <?=e($instance['status'])?></div></div>
<div class="card"><h2>Workflow Progress</h2>
<?php foreach($steps as $i=>$step):$cl=$step['status']==='completed'?'done':($step['status']==='in_progress'?'active':'');?>
<div class="step <?=$cl?>"><div class="dot"><?=$step['status']==='completed'?'✓':($i+1)?></div><div style="flex:1"><strong><?=e($step['step_name'])?></strong><div class="muted"><?=e($step['description']?:'')?></div><div class="status"><?=e($step['status'])?></div>
<?php if($step['status']==='in_progress'):?><form method="post" style="margin-top:10px"><?=csrf_field()?><input type="hidden" name="action" value="complete_step"><input type="hidden" name="client_service_id" value="<?=$clientServiceId?>"><input type="hidden" name="step_id" value="<?=$step['id']?>"><button class="btn">Complete Step</button></form><?php endif;?>
</div></div>
<?php endforeach;?>
</div></main></body></html>
