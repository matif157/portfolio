<?php
namespace App\Services;
use App\Support\Database;
final class AutomationService {
 public function dispatch(string $event,array $context=[]):int{
  $s=Database::pdo()->prepare("SELECT * FROM automation_rules WHERE tenant_id=1 AND is_active=1 AND trigger_event=?");$s->execute([$event]);$n=0;
  foreach($s as $rule){$actions=json_decode($rule['actions_json']??'[]',true)?:[];foreach($actions as $action){$this->runAction($action,$context);}$n++;}
  return $n;
 }
 private function runAction(array $a,array $c):void{
  if(($a['type']??'')==='log_activity')(new ActivityService)->log($c['module']??'automation',$a['action']??'automation');
  if(($a['type']??'')==='notification'&&isset($c['user_id']))(new NotificationService)->create((int)$c['user_id'],$a['title']??'Automation',$a['message']??'');
 }
}
