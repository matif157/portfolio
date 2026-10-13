<?php
namespace App\Services;
use App\Core\Database;
final class CommunicationService {
 public function queue(int $clientId,string $channel,string $subject,string $body,?int $serviceId=null):int{
  $s=Database::pdo()->prepare('INSERT INTO communications(tenant_id,client_id,channel,direction,subject,body,status,created_at) VALUES(1,?,? ,"outbound",?,? ,"queued",NOW())');$s->execute([$clientId,$channel,$subject,$body]);$id=(int)Database::pdo()->lastInsertId();(new ActivityService)->record('COMMUNICATION_QUEUED','Communications','communication',$id,null,'stage_2');return $id;
 }
 public function queueMissingRequirement(int $clientId,int $serviceId,string $requirementName,string $reason):int{
  $subject='Action required for your service';$body="We need the following information/document for your service: {$requirementName}.\n\nReason: {$reason}";
  $channel='email';$p=Database::pdo()->prepare('SELECT preferred_channel,email_enabled,sms_enabled,whatsapp_enabled,voice_enabled,do_not_call FROM communications_preferences WHERE client_id=?');$p->execute([$clientId]);$pref=$p->fetch();if($pref){if(!empty($pref['do_not_call'])&&($pref['preferred_channel']==='voice'))$channel='email';else $channel=$pref['preferred_channel']?:'email';}
  return $this->queue($clientId,$channel,$subject,$body,$serviceId);
 }
}
