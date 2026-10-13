<?php
namespace App\Services;
use App\Support\Database;use App\Support\TenantContext;
final class TaskGenerationService { public function fromRequirements(int $clientServiceId,int $clientId,?int $projectId=null):int{$s=Database::pdo()->prepare("SELECT r.name FROM client_service_requirements csr JOIN service_requirements r ON r.id=csr.requirement_id WHERE csr.tenant_id=? AND csr.client_service_id=? AND csr.status='missing' AND r.required=1");$s->execute([TenantContext::id(),$clientServiceId]);$n=0;$engine=new TaskEngine;foreach($s as $r){$engine->create('Collect: '.$r['name'],$clientId,$projectId);$n++;}return $n;}}
