<?php
namespace App\Services;
use App\Support\Database;
final class NotificationService {
 public function create(int $userId,string $title,string $message):void{
  Database::pdo()->prepare("INSERT INTO notifications(tenant_id,user_id,type,title,message,created_at) VALUES(1,?,?,?,?,NOW())")->execute([$userId,'system',$title,$message]);
 }
}
