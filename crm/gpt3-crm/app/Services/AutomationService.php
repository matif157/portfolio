<?php
namespace App\Services;
use App\Core\Database;
final class AutomationService {
    public function dispatch(string $event,array $context=[]): int { $s=Database::pdo()->prepare('SELECT * FROM automation_rules WHERE tenant_id=1 AND is_active=1 AND trigger_event=? ORDER BY priority DESC');$s->execute([$event]);$n=0;foreach($s as $rule){$conditions=json_decode($rule['conditions_json']??'{}',true)?:[];if(!$this->conditionsPass($conditions,$context))continue;$actions=json_decode($rule['actions_json']??'[]',true)?:[];$this->actions($actions,$context);$n++;}return $n; }
    private function conditionsPass(array $conditions,array $context):bool { foreach($conditions as $k=>$v)if(($context[$k]??null)!==$v)return false;return true; }
    private function actions(array $actions,array $context):void { foreach($actions as $a){$type=$a['type']??'';if($type==='activity')(new ActivityService)->record($a['action']??'AUTOMATION','Automation',null,null,null,'stage_1','info','system');if($type==='notification'&&isset($context['user_id']))Database::pdo()->prepare('INSERT INTO notifications(tenant_id,user_id,title,message,created_at) VALUES(1,?,?,?,NOW())')->execute([(int)$context['user_id'],$a['title']??'Automation',$a['message']??'']);} }
}
