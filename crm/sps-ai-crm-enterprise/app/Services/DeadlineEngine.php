<?php
namespace App\Services;
use App\Support\Database;use App\Support\TenantContext;
final class DeadlineEngine {
 public function generateForWorkflow(int $executionId,int $clientServiceId):int{$pdo=Database::pdo();$q=$pdo->prepare('SELECT ws.id,ws.title,wd.offset_days,wd.trigger_type,wd.name FROM workflow_step_executions wse JOIN workflow_steps ws ON ws.id=wse.workflow_step_id JOIN workflow_deadlines wd ON wd.workflow_step_id=ws.id WHERE wse.workflow_execution_id=? AND wd.is_active=1');$q->execute([$executionId]);$n=0;foreach($q as $r){$days=(int)$r['offset_days'];$due=date('Y-m-d H:i:s',strtotime(($days>=0?'+':'').$days.' days'));$i=$pdo->prepare('INSERT INTO deadlines(tenant_id,client_service_id,workflow_execution_id,name,due_at,type,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())');$i->execute([TenantContext::id(),$clientServiceId,$executionId,$r['name'],$due,'workflow']);$n++;}return $n;}
 public function sweep():int{$pdo=Database::pdo();$s=$pdo->query("SELECT * FROM deadlines WHERE tenant_id=".(int)TenantContext::id()." AND status='open' AND due_at<=NOW()");$n=0;foreach($s as $d){$u=$pdo->prepare("UPDATE deadlines SET status='overdue',escalation_level=escalation_level+1,updated_at=NOW() WHERE id=?");$u->execute([$d['id']]);$n++;}return $n;}
}
