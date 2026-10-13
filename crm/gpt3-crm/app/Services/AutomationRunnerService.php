<?php
namespace App\Services;

use PDO;

final class AutomationRunnerService
{
    public function __construct(private PDO $pdo) {}

    public function run(): array
    {
        $result=[
            'overdue_invoices'=>0,
            'upcoming_reminders'=>0,
            'queued_communications'=>0,
            'expired_projects'=>0
        ];

        $result['overdue_invoices']=(int)$this->pdo->exec(
            "UPDATE invoices
             SET status='overdue',updated_at=NOW()
             WHERE balance>0 AND due_date<CURDATE()
             AND status IN ('issued','sent','partially_paid')"
        );

        $result['expired_projects']=(int)$this->pdo->exec(
            "UPDATE projects
             SET status='expired',updated_at=NOW()
             WHERE end_date<CURDATE()
             AND status IN ('open','upcoming')"
        );

        $result['upcoming_reminders']=(int)$this->pdo->query(
            "SELECT COUNT(*) FROM reminders
             WHERE start_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 1 DAY)"
        )->fetchColumn();

        $result['queued_communications']=(int)$this->pdo->query(
            "SELECT COUNT(*) FROM communications WHERE status='queued'"
        )->fetchColumn();

        return $result;
    }
}
