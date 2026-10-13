<?php
namespace App\Services;
use App\Support\Database;use App\Support\TenantContext;
final class RequirementEngine {
 public function initialize(int $clientServiceId,int $serviceId,?int $versionId):int{$pdo=Database::pdo();$s=$pdo->prepare('SELECT id FROM service_requirements WHERE tenant_id=? AND service_id=? AND (service_version_id IS NULL OR service_version_id=?) AND is_active=1 ORDER BY sort_order');$s->execute([TenantContext::id(),$serviceId,$versionId]);$i=$pdo->prepare('INSERT IGNORE INTO client_service_requirements(tenant_id,client_service_id,requirement_id,status,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())');$n=0;foreach($s as $r){$i->execute([TenantContext::id(),$clientServiceId,$r['id'],'missing']);$n++;}return $n;}
 public function status(int $clientServiceId):array{$s=Database::pdo()->prepare('SELECT csr.*,r.code,r.name,r.requirement_type,r.required FROM client_service_requirements csr JOIN service_requirements r ON r.id=csr.requirement_id WHERE csr.tenant_id=? AND csr.client_service_id=? ORDER BY r.sort_order');$s->execute([TenantContext::id(),$clientServiceId]);$rows=$s->fetchAll();$missing=count(array_filter($rows,fn($r)=>(int)$r['required']===1&&$r['status']!=='satisfied'));return ['items'=>$rows,'missing'=>$missing,'complete'=>$missing===0];}
}
