<?php
namespace App\Services;
use App\Core\Database;
final class ClientKnowledgeService {
 public function set(int $clientId,string $key,mixed $value,string $source='human',float $confidence=1,bool $verified=false):void{
  $s=Database::pdo()->prepare('INSERT INTO client_knowledge(client_id,key_name,value_json,source,confidence,verified_at,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),source=VALUES(source),confidence=VALUES(confidence),verified_at=VALUES(verified_at),updated_at=NOW()');
  $s->execute([$clientId,$key,json_encode($value),$source,$confidence,$verified?date('Y-m-d H:i:s'):null]);
 }
 public function get(int $clientId,string $key):mixed{$s=Database::pdo()->prepare('SELECT value_json FROM client_knowledge WHERE client_id=? AND key_name=?');$s->execute([$clientId,$key]);$v=$s->fetchColumn();return $v===false?null:json_decode($v,true);}
}
