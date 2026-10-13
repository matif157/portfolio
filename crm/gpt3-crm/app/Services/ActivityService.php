<?php
namespace App\Services;
use App\Core\Auth; use App\Core\Database;
final class ActivityService {
    public function record(string $action,string $module,?string $entityType=null,?int $entityId=null,?array $changes=null,string $stage='stage_1',string $severity='info',?string $source='human'):void{
        $actor=Auth::user();
        $old=[];$new=[];
        foreach(($changes??[]) as $field=>$change){
            if(is_array($change)&&array_key_exists('before',$change)&&array_key_exists('after',$change)){$old[$field]=$this->redact($change['before']);$new[$field]=$this->redact($change['after']);}
            else{$new[$field]=$this->redact($change);}
        }
        $s=Database::pdo()->prepare('INSERT INTO activity_logs(tenant_id,user_id,user_name,module_name,action,entity_type,record_id,old_value,new_value,activity_stage,severity,source,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $s->execute([1,Auth::id(),$actor['full_name']??null,$module,$action,$entityType,$entityId,$old?json_encode($old):null,$new?json_encode($new):null,$stage,$severity,$source,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
    }
    public function diff(array $before,array $after,array $secretFields=[]):array{$out=[];foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $k){if(in_array($k,$secretFields,true))continue;$a=$before[$k]??null;$b=$after[$k]??null;if((string)$a!==(string)$b)$out[$k]=['before'=>$a,'after'=>$b];}return $out;}
    private function redact(mixed $v):mixed{if(is_string($v)&&strlen($v)>4000)return substr($v,0,4000).'…';return $v;}
}
