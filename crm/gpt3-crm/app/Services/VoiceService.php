<?php
namespace App\Services;
use App\Core\Database;use App\Integrations\Voice\TelephonyAdapter;
final class VoiceService {
 public function callMissingRequirement(int $clientId,string $message):array{
  $s=Database::pdo()->prepare('SELECT preferred_channel,voice_enabled,do_not_call,call_start,call_end,timezone FROM communications_preferences WHERE client_id=?');$s->execute([$clientId]);$pref=$s->fetch();
  if(!$pref||empty($pref['voice_enabled'])||!empty($pref['do_not_call']))return ['status'=>'blocked','reason'=>'Client voice contact is not enabled or client opted out.'];
  $cfg=require APP_ROOT.'/config/integrations.example.php';if(empty($cfg['voice']['enabled'])||empty($cfg['voice']['from_number']))return ['status'=>'not_configured','reason'=>'Telephony provider is not configured.'];
  $phone=(new \App\Repositories\ContactRepository)->primaryPhone($clientId);if(!$phone)return ['status'=>'blocked','reason'=>'No primary phone number is available.'];
  return (new TelephonyAdapter())->placeCall($phone,$message);
 }
}
