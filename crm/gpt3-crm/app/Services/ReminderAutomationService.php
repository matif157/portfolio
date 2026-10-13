<?php
namespace App\Services;

use PDO;

final class ReminderAutomationService
{
    public function __construct(private PDO $pdo) {}

    public function queueMissingRequirementReminder(
        int $clientId,
        ?int $businessId,
        ?int $serviceId,
        string $subject,
        string $body,
        string $channel='email'
    ): int {
        $s = $this->pdo->prepare(
            "INSERT INTO communications
            (tenant_id,client_id,business_id,service_id,channel,direction,subject,body,status,created_at)
            VALUES (1,?,?,?,?, 'outbound',?,?, 'queued', NOW())"
        );
        $s->execute([$clientId,$businessId,$serviceId,$channel,$subject,$body]);
        return (int)$this->pdo->lastInsertId();
    }

    public function upcomingReminders(int $days=3): array
    {
        $s=$this->pdo->prepare(
            "SELECT r.*, GROUP_CONCAT(CONCAT(c.first_name,' ',c.last_name) SEPARATOR ', ') client_names
             FROM reminders r
             LEFT JOIN reminder_clients rc ON rc.reminder_id=r.id
             LEFT JOIN clients c ON c.id=rc.client_id
             WHERE r.start_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL ? DAY)
             GROUP BY r.id
             ORDER BY r.start_at"
        );
        $s->execute([$days]);
        return $s->fetchAll();
    }
}
