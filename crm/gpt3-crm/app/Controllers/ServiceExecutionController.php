<?php
namespace App\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Services\ServiceExecutionService;

final class ServiceExecutionController
{
    public function start(int $projectId): never
    {
        Auth::requireLogin();
        $serviceVersionId = (int)($_POST['workflow_version_id'] ?? 0);
        if ($serviceVersionId < 1) {
            Response::json(['error' => 'Workflow version is required'], 422);
        }
        try {
            $id = (new ServiceExecutionService(Database::pdo()))->ensureProjectWorkflow($projectId, $serviceVersionId);
            Response::json(['success' => true, 'workflow_execution_id' => $id], 201);
        } catch (\Throwable $e) {
            Response::json(['error' => 'Unable to start workflow'], 500);
        }
    }

    public function completeStep(int $executionId, int $stepExecutionId): never
    {
        Auth::requireLogin();
        $payload = $_POST['value'] ?? null;
        (new ServiceExecutionService(Database::pdo()))->completeStep(
            $executionId,
            $stepExecutionId,
            (int)Auth::id(),
            $payload !== null ? ['value' => $payload] : null
        );
        Response::json(['success' => true]);
    }
}
