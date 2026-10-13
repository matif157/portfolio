<?php
namespace App\Services;
use App\Core\Database;
use App\Core\Auth;
final class WorkflowEngine{
 public function startForProject(int $projectId,int $serviceId):int{
  $pdo=Database::pdo();$v=$pdo->prepare('SELECT id FROM workflow_versions WHERE service_id=? AND status="published" ORDER BY version_no DESC LIMIT 1');$v->execute([$serviceId]);$version=$v->fetch();if(!$version)throw new \RuntimeException('No published workflow for service.');
  $i=$pdo->prepare('INSERT INTO workflow_instances(tenant_id,project_id,workflow_version_id,status,started_at,created_at) VALUES(1,?,?,"in_progress",NOW(),NOW())');$i->execute([$projectId,$version['id']]);$instance=(int)$pdo->lastInsertId();
  $steps=$pdo->prepare('SELECT * FROM workflow_steps WHERE workflow_version_id=? ORDER BY sort_order');$steps->execute([$version['id']]);$x=$pdo->prepare('INSERT INTO workflow_step_instances(tenant_id,workflow_instance_id,workflow_step_id,status) VALUES(1,?,?,"not_started")');foreach($steps as $step)$x->execute([$instance,$step['id']]);
  return $instance;
 }
 public function advance(int $stepInstanceId,int $userId,?array $value=null):void{Database::pdo()->prepare('UPDATE workflow_step_instances SET status="completed",value_json=?,completed_by=?,completed_at=NOW() WHERE id=?')->execute([$value?json_encode($value):null,$userId,$stepInstanceId]);(new ActivityService)->record('WORKFLOW_STEP_COMPLETED','Workflow','workflow_step',$stepInstanceId,$value?:null);}
 public function skip(int $stepInstanceId,int $userId,string $reason):void{Database::pdo()->prepare('UPDATE workflow_step_instances SET status="skipped",value_json=?,completed_by=?,completed_at=NOW() WHERE id=?')->execute([json_encode(['reason'=>$reason]),$userId,$stepInstanceId]);(new ActivityService)->record('WORKFLOW_STEP_SKIPPED','Workflow','workflow_step',$stepInstanceId,['reason'=>$reason],'stage_3');}
 public function canComplete(int $instanceId):bool{$s=Database::pdo()->prepare('SELECT COUNT(*) FROM workflow_step_instances wsi JOIN workflow_steps ws ON ws.id=wsi.workflow_step_id WHERE wsi.workflow_instance_id=? AND ws.requirement="required" AND wsi.status<>"completed"');$s->execute([$instanceId]);return (int)$s->fetchColumn()===0;}
 public function completeWorkflow(int $instanceId):void{if(!$this->canComplete($instanceId))throw new \RuntimeException('Required workflow steps are not completed.');Database::pdo()->prepare('UPDATE workflow_instances SET status="completed",completed_at=NOW() WHERE id=?')->execute([$instanceId]);(new ActivityService)->record('WORKFLOW_COMPLETED','Workflow','workflow_instance',$instanceId,null,'stage_3');}
}
