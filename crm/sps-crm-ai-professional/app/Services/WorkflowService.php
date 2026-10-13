<?php
namespace App\Services;
use App\Support\Database;use App\Services\ActivityService;
final class WorkflowService {
 public function start(int $projectId,int $versionId):int{
  $pdo=Database::pdo();$pdo->beginTransaction();
  try{
   $s=$pdo->prepare("INSERT INTO workflow_executions(tenant_id,project_id,workflow_version_id,status,started_at,created_at) VALUES(1,?,?,?,NOW(),NOW())");$s->execute([$projectId,$versionId,'in_progress']);$id=(int)$pdo->lastInsertId();
   $q=$pdo->prepare("SELECT id FROM workflow_steps WHERE workflow_version_id=? ORDER BY sort_order");$q->execute([$versionId]);
   $i=$pdo->prepare("INSERT INTO workflow_step_executions(tenant_id,workflow_execution_id,workflow_step_id,status) VALUES(1,?,?,?)");
   foreach($q as $r)$i->execute([$id,$r['id'],'not_started']);$pdo->commit();return $id;
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}
 }
 public function completeStep(int $executionId,int $stepId,int $userId,?array $value=null):void{
  $s=Database::pdo()->prepare("UPDATE workflow_step_executions SET status='completed',value_json=?,completed_by=?,completed_at=NOW() WHERE workflow_execution_id=? AND workflow_step_id=?");
  $s->execute([$value?json_encode($value):null,$userId,$executionId,$stepId]);
 }
}
