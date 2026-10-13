<?php
namespace App\Services;

use PDO;

final class NotificationCenterService
{
    public function __construct(private PDO $pdo) {}

    public function unread(int $userId, int $limit=20): array
    {
        $limit=max(1,min(100,$limit));
        $q=$this->pdo->prepare(
            "SELECT id,type,title,message,entity_type,entity_id,created_at
             FROM notifications
             WHERE tenant_id=1 AND user_id=? AND read_at IS NULL
             ORDER BY created_at DESC
             LIMIT $limit"
        );
        $q->execute([$userId]);
        return $q->fetchAll();
    }

    public function markRead(int $notificationId,int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE notifications SET read_at=NOW()
             WHERE id=? AND user_id=?"
        )->execute([$notificationId,$userId]);
    }
}
