<?php
namespace App\Services;
use App\Core\Database;
final class RequirementEngine {
    public function missing(int $clientServiceId): array {
        $s=Database::pdo()->prepare('SELECT r.id,r.name,r.kind,r.reason,r.instructions FROM service_requirements r JOIN client_services cs ON cs.service_id=r.service_id WHERE cs.id=? AND r.is_active=1 AND NOT EXISTS(SELECT 1 FROM requirement_completions rc WHERE rc.client_service_id=? AND rc.service_id_requirement_id=r.id AND rc.status="satisfied") ORDER BY r.sort_order');$s->execute([$clientServiceId,$clientServiceId]);return $s->fetchAll();
    }
}
