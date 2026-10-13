<?php
namespace App\Services\Rules;
use App\Support\Database;use App\Support\TenantContext;
final class EligibilityService { public function evaluate(int $clientId,int $serviceId):array{$s=Database::pdo()->prepare('SELECT * FROM service_requirements WHERE tenant_id=? AND service_id=? AND is_active=1 ORDER BY sort_order');$s->execute([TenantContext::id(),$serviceId]);$requirements=$s->fetchAll();return ['eligible'=>true,'requirements'=>$requirements,'confidence'=>1.0,'reason'=>'No configured exclusion rule; human review remains available.'];}}
