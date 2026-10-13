<?php
namespace App\Services;

use PDO;

final class CommunicationQueueService
{
    public function __construct(private PDO $pdo) {}

    public function queueMissingRequirement(
        int $clientId,
        ?int $businessId,
        int $serviceId,
        string $subject,
        string $body,
        string $channel = 'email'
    ): int {
        $s = $this->pdo->prepare(
            "INSERT INTO communications
            (tenant_id,client_id,business_id,service_id,channel,direction,subject,body,status,created_at)
            VALUES (1,?,?,?,?, 'outbound',?,?, 'queued', NOW())"
        );
        $s->execute([$clientId, $businessId, $serviceId, $channel, $subject, $body]);
        return (int)$this->pdo->lastInsertId();
    }
}
