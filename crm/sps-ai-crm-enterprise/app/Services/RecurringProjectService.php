<?php
namespace App\Services;
use App\Support\Database;
final class RecurringProjectService {
 public function process():int{
  $pdo=Database::pdo();$rows=$pdo->query("SELECT * FROM projects WHERE is_recurring=1 AND end_date<=CURDATE() AND renewal_processed_at IS NULL")->fetchAll();$n=0;
  foreach($rows as $p){
   $pdo->beginTransaction();try{
    $status=$p['status']==='completed'?'completed':'expired';
    $u=$pdo->prepare("UPDATE projects SET status=?,renewal_processed_at=NOW(),updated_at=NOW() WHERE id=?");$u->execute([$status,$p['id']]);
    $days=max(1,(int)$p['recurring_cycle_days']);$start=$p['end_date'];$end=date('Y-m-d',strtotime("$start +$days days"));
    $i=$pdo->prepare("INSERT INTO projects(tenant_id,client_id,business_id,service_id,name,start_date,end_date,status,is_recurring,recurring_cycle_days,parent_project_id,series_id,cycle_number,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?,1,?,?,?,?,NOW(),NOW())");
    $i->execute([$p['client_id'],$p['business_id'],$p['service_id'],$p['name'],$start,$end,'upcoming',$days,$p['id'],$p['series_id']?:$p['id'],((int)$p['cycle_number'])+1]);$pdo->commit();$n++;
   }catch(\Throwable $e){$pdo->rollBack();throw $e;}
  }return $n;
 }
}
