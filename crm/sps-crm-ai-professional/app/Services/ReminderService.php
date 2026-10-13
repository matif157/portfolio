<?php
namespace App\Services;
use App\Support\Database;
final class ReminderService {
 public function create(array $d,array $clientIds):int{
  $pdo=Database::pdo();$pdo->beginTransaction();try{
   $s=$pdo->prepare("INSERT INTO reminders(tenant_id,title,description,start_at,recurrence_type,recurrence_config,assigned_user_id,created_by,created_at,updated_at) VALUES(1,?,?,?,?,?,?,?,NOW(),NOW())");
   $s->execute([$d['title'],$d['description']??null,$d['start_at'],$d['recurrence_type']??'none',isset($d['recurrence_config'])?json_encode($d['recurrence_config']):null,$d['assigned_user_id']??null,$d['created_by']??null]);
   $id=(int)$pdo->lastInsertId();$i=$pdo->prepare("INSERT INTO reminder_clients(reminder_id,client_id) VALUES(?,?)");foreach(array_unique($clientIds) as $cid)$i->execute([$id,$cid]);$pdo->commit();return $id;
  }catch(\Throwable $e){$pdo->rollBack();throw $e;}
 }
}
