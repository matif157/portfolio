<?php
namespace App\Services;

use PDO;

final class RequirementService
{
    public function __construct(private PDO $pdo) {}

    public function missingForClientService(int $clientServiceId): array
    {
        $s = $this->pdo->prepare(
            "SELECT csr.*, sr.name, sr.requirement_type, sr.is_required, sr.sort_order
             FROM client_service_requirements csr
             JOIN service_requirements sr ON sr.id = csr.requirement_id
             WHERE csr.client_service_id=? AND csr.status <> 'completed'
             ORDER BY sr.sort_order, sr.id"
        );
        $s->execute([$clientServiceId]);
        return $s->fetchAll();
    }

    public function complete(int $clientServiceRequirementId, int $userId, ?string $note=null): void
    {
        $s = $this->pdo->prepare(
            "UPDATE client_service_requirements
             SET status='completed', completed_by=?, completed_at=NOW(), notes=?
             WHERE id=?"
        );
        $s->execute([$userId, $note, $clientServiceRequirementId]);
    }
}
