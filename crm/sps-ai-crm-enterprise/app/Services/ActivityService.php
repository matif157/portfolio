<?php
namespace App\Services;
use App\Support\Database;use App\Support\Auth;
final class ActivityService {
 public function log(string $module,string $action,?string $entity=null,?int $entityId=null,?array $changes=null,string $stage='stage_1',string $severity='info'):void{
  $s=Database::pdo()->prepare("INSERT INTO activities(tenant_id,user_id,module,action,entity_type,entity_id,changes_json,visibility,severity,ip_address,user_agent,created_at) VALUES(1,?,?,?,?,?,?,?,?,?,?,NOW())");
  $s->execute([Auth::id(),$module,$action,$entity,$entityId,$changes?json_encode($changes):null,$stage,$severity,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]);
 }
 public function diff(array $before,array $after,array $secretKeys=[]):array{
  $keys=array_unique(array_merge(array_keys($before),array_keys($after)));$out=[];
  foreach($keys as $k){if(in_array($k,$secretKeys,true))continue;$a=$before[$k]??null;$b=$after[$k]??null;if((string)$a!==(string)$b)$out[$k]=['before'=>$a,'after'=>$b];}
  return $out;
 }
}
