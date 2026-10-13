<?php
namespace App\Services;
use App\Core\Database;use App\Core\Auth;
final class BookkeepingAutomationService {
 public function ensureService():int{
  $pdo=Database::pdo();$s=$pdo->prepare('SELECT id FROM services WHERE name=? LIMIT 1');$s->execute(['Bank Statement Collection & Monthly Bookkeeping Preparation']);if($row=$s->fetch())return (int)$row['id'];
  $pdo->prepare("INSERT INTO services(tenant_id,name,category,description,price,is_active,approval_level,automation_enabled) VALUES(1,?,'Bookkeeping',?,0,1,2,1)")->execute(['Bank Statement Collection & Monthly Bookkeeping Preparation','Automated monthly bank statement collection, missing-period tracking and bookkeeping preparation.']);$id=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO service_automation_profiles(service_id,auto_contact_missing,preferred_channel,voice_after_days,max_voice_attempts,minimum_ai_confidence,approval_level) VALUES(?,1,'email',2,2,0.8500,2)")->execute([$id]);
  $req=$pdo->prepare('INSERT INTO service_requirements(service_id,name,kind,reason,instructions,required,sort_order,is_active) VALUES(?,?,?,?,?,1,?,1)');$req->execute([$id,'Bank account registration','bank_account','A bank account is needed to determine monthly statement requirements.','Register each active bank account and preserve only necessary identifying information.',1]);$req->execute([$id,'Monthly bank statements','document','Every active account requires a statement for each requested period.','Upload PDF/JPG/PNG statements; the CRM records account/month/year and document reference.',2]);
  $pdo->prepare("INSERT INTO workflow_versions(service_id,version_no,name,status) VALUES(?,1,'Bank Statement Collection Workflow v1','published')")->execute([$id]);$ver=(int)$pdo->lastInsertId();
  $steps=[['Register bank accounts','bank_account','required'],['Generate monthly requirements','automation','required'],['Collect statements','document_upload','required'],['Resolve missing periods','communication','required'],['Bookkeeping preparation review','approval','required'],['Complete monthly cycle','completion','required']];$st=$pdo->prepare('INSERT INTO workflow_steps(workflow_version_id,title,step_type,requirement,sort_order,config_json) VALUES(?,?,?,?,?,?)');foreach($steps as $i=>$x)$st->execute([$ver,$x[0],$x[1],$x[2],$i+1,json_encode(['show_initials'=>true])]);return $id;
 }
 public function startForClient(int $clientId,int $businessId,int $year):int{
  $pdo=Database::pdo();$serviceId=$this->ensureService();$existing=$pdo->prepare('SELECT id FROM client_services WHERE client_id=? AND service_id=? AND status NOT IN ("completed","cancelled") ORDER BY id DESC LIMIT 1');$existing->execute([$clientId,$serviceId]);if($e=$existing->fetch())return (int)$e['id'];
  $pdo->beginTransaction();try{
   $name='Bookkeeping Preparation - '.$year;$pdo->prepare('INSERT INTO projects(tenant_id,client_id,business_id,service_id,name,start_date,end_date,status,created_by,updated_by) VALUES(1,?,?,?, ?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY),"open",?,?)')->execute([$clientId,$businessId?:null,$serviceId,$name,Auth::id(),Auth::id()]);$projectId=(int)$pdo->lastInsertId();
   $pdo->prepare('INSERT INTO client_services(tenant_id,client_id,service_id,business_id,project_id,status,started_at) VALUES(1,?,?,?, ?,"in_progress",NOW())')->execute([$clientId,$serviceId,$businessId?:null,$projectId]);$csId=(int)$pdo->lastInsertId();
   (new WorkflowEngine)->startForProject($projectId,$serviceId);$pdo->commit();(new ActivityService)->record('BOOKKEEPING_SERVICE_STARTED','Services','client_service',$csId,['year'=>$year,'project_id'=>$projectId]);return $csId;
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 public function runMissingCommunication(int $year):int{
  $pdo=Database::pdo();$clients=$pdo->query('SELECT DISTINCT client_id FROM bank_accounts WHERE COALESCE(is_active,1)=1')->fetchAll();$n=0;$svc=new BankStatementService();foreach($clients as $c){if($svc->missingMonths((int)$c['client_id'],$year)){$svc->createMissingReminder((int)$c['client_id'],$year,(int)(Auth::id()??1));$n++;}}return $n;
 }
}
