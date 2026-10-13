<?php
namespace App\Services;

use PDO;

final class ServiceStatusService
{
    public function __construct(private PDO $pdo) {}

    public function summary(int $clientServiceId): array
    {
        $required = $this->pdo->prepare(
            "SELECT COUNT(*) FROM client_service_requirements csr
             JOIN service_requirements sr ON sr.id=csr.requirement_id
             WHERE csr.client_service_id=? AND sr.is_required=1"
        );
        $required->execute([$clientServiceId]);

        $completed = $this->pdo->prepare(
            "SELECT COUNT(*) FROM client_service_requirements csr
             JOIN service_requirements sr ON sr.id=csr.requirement_id
             WHERE csr.client_service_id=? AND sr.is_required=1 AND csr.status='completed'"
        );
        $completed->execute([$clientServiceId]);

        $missing = $this->pdo->prepare(
            "SELECT COUNT(*) FROM client_service_requirements
             WHERE client_service_id=? AND status<>'completed'"
        );
        $missing->execute([$clientServiceId]);

        $requiredCount=(int)$required->fetchColumn();
        $completedCount=(int)$completed->fetchColumn();

        return [
            'required'=>$requiredCount,
            'completed'=>$completedCount,
            'missing'=>(int)$missing->fetchColumn(),
            'progress'=>$requiredCount ? (int)round(($completedCount/$requiredCount)*100) : 100
        ];
    }
}
