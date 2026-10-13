<?php
namespace App\Services;
use App\Support\Database;use App\Support\TenantContext;use App\Support\DatabaseTransaction;
final class ServiceLifecycleService {
 public function request(int $clientId,int $serviceId,?int $businessId=null):int{return DatabaseTransaction::run(function($pdo)use($clientId,$serviceId,$businessId){$v=$pdo->prepare('SELECT id,approval_level,risk_level FROM service_versions WHERE tenant_id=? AND service_id=? AND status=\'active\' ORDER BY id DESC LIMIT 1');$v->execute([TenantContext::id(),$serviceId]);$version=$v->fetch();$s=$pdo->prepare('INSERT INTO client_services(tenant_id,client_id,business_id,service_id,service_version_id,status,risk_level,approval_level,requested_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())');$s->execute([TenantContext::id(),$clientId,$businessId,$serviceId,$version['id']??null,'requested',$version['risk_level']??'normal',(int)($version['approval_level']??2)]);$id=(int)$pdo->lastInsertId();(new RequirementEngine)->initialize($id,$serviceId,$version['id']??null);(new ActivityService)->log('client_services','requested','client_service',$id);return $id;});}
}
