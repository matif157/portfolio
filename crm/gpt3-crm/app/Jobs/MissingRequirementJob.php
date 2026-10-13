<?php
namespace App\Jobs;
use App\Core\Database;use App\Services\{RequirementEngine,CommunicationService,VoiceService};
final class MissingRequirementJob{
 public function handle():int{
  $pdo=Database::pdo();$cs=$pdo->query('SELECT cs.id,cs.client_id,cs.service_id,COALESCE(c.email,\'\') email FROM client_services cs JOIN clients c ON c.id=cs.client_id WHERE cs.status NOT IN (\'completed\',\'cancelled\')')->fetchAll();$n=0;
  foreach($cs as $row){$missing=(new RequirementEngine)->missing((int)$row['id']);if(!$missing)continue;foreach(array_slice($missing,0,3) as $req){
    $recent=$pdo->prepare('SELECT id FROM communications WHERE tenant_id=1 AND client_id=? AND subject=? AND created_at>=DATE_SUB(NOW(),INTERVAL 48 HOUR) LIMIT 1');$recent->execute([$row['client_id'],'Action required for your service - '.$req['name']]);if($recent->fetchColumn())continue;
    if($row['email']!=='')(new CommunicationService)->queue((int)$row['client_id'],'email','Action required for your service - '.$req['name'],"We still need: {$req['name']}\n\nWhy it is required: {$req['reason']}");
    $pref=$pdo->prepare('SELECT voice_enabled,do_not_call FROM communications_preferences WHERE client_id=?');$pref->execute([$row['client_id']]);$vp=$pref->fetch();if($vp&&!empty($vp['voice_enabled'])&&empty($vp['do_not_call'])){(new VoiceService)->callMissingRequirement((int)$row['client_id'],"We need {$req['name']} to continue your service.");}
    $n++;
  }}return $n;
 }
}
