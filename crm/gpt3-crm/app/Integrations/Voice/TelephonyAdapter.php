<?php
namespace App\Integrations\Voice;
use App\Integrations\Contract\TelephonyInterface;
final class TelephonyAdapter implements TelephonyInterface{
 public function call(string $to,string $from,string $script,array $context=[]):array{return ['status'=>'not_configured','to'=>$to,'from'=>$from,'note'=>'Provider credentials, verified caller ID and compliance configuration required.'];}
 public function placeCall(string $to,string $script):array{ $cfg=require APP_ROOT.'/config/integrations.example.php'; return $this->call($to,$cfg['voice']['from_number']??'', $script, []); }
}
