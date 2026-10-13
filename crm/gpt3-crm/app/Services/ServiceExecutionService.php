<?php
namespace App\Services;

use PDO;

final class ServiceExecutionService
{
    public function __construct(private PDO $pdo) {}

    public function ensureProjectWorkflow(int $projectId, int $serviceVersionId): int
    {
        $q = $this->pdo->prepare(
            "SELECT id FROM workflow_executions WHERE project_id = ? AND workflow_version_id = ? LIMIT 1"
        );
        $q->execute([$projectId, $serviceVersionId]);
        $existing = $q->fetchColumn();
        if ($existing) return (int)$existing;

        $this->pdo->beginTransaction();
        try {
            $s = $this->pdo->prepare(
                "INSERT INTO workflow_executions
                (tenant_id,project_id,workflow_version_id,status,started_at,created_at)
                VALUES (1,?,?, 'in_progress', NOW(), NOW())"
            );
            $s->execute([$projectId, $serviceVersionId]);
            $executionId = (int)$this->pdo->lastInsertId();

            $steps = $this->pdo->prepare(
                "SELECT id FROM workflow_steps WHERE workflow_version_id = ? ORDER BY sort_order, id"
            );
            $steps->execute([$serviceVersionId]);

            $ins = $this->pdo->prepare(
                "INSERT INTO workflow_step_executions
                (tenant_id,workflow_execution_id,workflow_step_id,status)
                VALUES (1,?,?, 'not_started')"
            );
            foreach ($steps as $step) {
                $ins->execute([$executionId, $step['id']]);
            }

            $this->pdo->commit();
            return $executionId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function completeStep(int $executionId, int $stepExecutionId, int $userId, ?array $value = null): void
    {
        $s = $this->pdo->prepare(
            "UPDATE workflow_step_executions
             SET status='completed', value_json=?, completed_by=?, completed_at=NOW()
             WHERE id=? AND workflow_execution_id=?"
        );
        $s->execute([
            $value ? json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
            $userId, $stepExecutionId, $executionId
        ]);
    }
}
