<?php
namespace App\Services;

use PDO;

final class DocumentRequirementService
{
    public function __construct(private PDO $pdo) {}

    public function attach(int $documentId, int $clientServiceRequirementId, int $userId): void
    {
        $this->pdo->beginTransaction();
        try {
            $check=$this->pdo->prepare(
                "SELECT id FROM documents WHERE id=? LIMIT 1"
            );
            $check->execute([$documentId]);
            if(!$check->fetchColumn()) throw new \RuntimeException('Document not found');

            $link=$this->pdo->prepare(
                "INSERT INTO document_requirement_links
                 (tenant_id,document_id,client_service_requirement_id,linked_by,created_at)
                 VALUES (1,?,?,?,NOW())
                 ON DUPLICATE KEY UPDATE linked_by=VALUES(linked_by)"
            );
            $link->execute([$documentId,$clientServiceRequirementId,$userId]);

            $complete=$this->pdo->prepare(
                "UPDATE client_service_requirements
                 SET status='completed', completed_by=?, completed_at=NOW()
                 WHERE id=?"
            );
            $complete->execute([$userId,$clientServiceRequirementId]);

            $this->pdo->commit();
        } catch(\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
