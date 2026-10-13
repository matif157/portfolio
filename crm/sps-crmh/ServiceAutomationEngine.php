<?php
declare(strict_types=1);

namespace SPS\Automation;

use PDO;
use Exception;

/**
 * ServiceAutomationEngine
 * 
 * Production-grade Service Orchestration Engine for Syed Professional Services.
 * Evaluates client profiles against service requirement rules, updates fulfillment matrix,
 * and emits events to OmnichannelDispatcher when prerequisites are missing.
 */
class ServiceAutomationEngine
{
    private PDO $pdo;
    private OmnichannelDispatcher $dispatcher;

    public function __construct(PDO $pdo, OmnichannelDispatcher $dispatcher)
    {
        $this->pdo = $pdo;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Evaluates a Client's service workflow execution and checks for missing prerequisites.
     *
     * @param int $clientId
     * @param int $serviceId
     * @param int|null $projectId
     * @return array Result array containing status, missing requirements, and emitted actions.
     * @throws Exception
     */
    public function evaluateServiceWorkflow(int $clientId, int $serviceId, ?int $projectId = null): array
    {
        $this->pdo->beginTransaction();
        try {
            // Fetch Client Profile
            $stmtC = $this->pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmtC->execute([$clientId]);
            $client = $stmtC->fetch();

            if (!$client) {
                throw new Exception("Client with ID {$clientId} not found.");
            }

            // Fetch Service Definition
            $stmtS = $this->pdo->prepare("SELECT * FROM services WHERE id = ?");
            $stmtS->execute([$serviceId]);
            $service = $stmtS->fetch();

            if (!$service) {
                throw new Exception("Service with ID {$serviceId} not found.");
            }

            // Fetch Service Requirements
            $stmtR = $this->pdo->prepare("SELECT * FROM service_requirements WHERE service_id = ? AND is_required = 1 ORDER BY sort_order ASC");
            $stmtR->execute([$serviceId]);
            $requirements = $stmtR->fetchAll();

            $missingRequirements = [];
            $fulfilledRequirements = [];

            foreach ($requirements as $req) {
                $fieldKey = $req['field_key'] ?? null;
                $isFulfilled = false;
                $fulfilledValue = null;

                // Check 1: Client profile core column attribute
                if ($fieldKey && isset($client[$fieldKey]) && !empty($client[$fieldKey])) {
                    $isFulfilled = true;
                    $fulfilledValue = (string)$client[$fieldKey];
                }

                // Check 2: Check client_service_requirements fulfillment table
                if (!$isFulfilled) {
                    $stmtCSR = $this->pdo->prepare("
                        SELECT * FROM client_service_requirements 
                        WHERE client_id = ? AND service_id = ? AND requirement_id = ? AND status = 'fulfilled'
                    ");
                    $stmtCSR->execute([$clientId, $serviceId, $req['id']]);
                    $csr = $stmtCSR->fetch();
                    if ($csr) {
                        $isFulfilled = true;
                        $fulfilledValue = $csr['fulfilled_value'];
                    }
                }

                // Upsert requirement fulfillment status record
                $stmtUpsert = $this->pdo->prepare("
                    INSERT INTO client_service_requirements (client_id, service_id, requirement_id, status, fulfilled_value, fulfilled_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), fulfilled_value = VALUES(fulfilled_value), fulfilled_at = VALUES(fulfilled_at)
                ");

                if ($isFulfilled) {
                    $stmtUpsert->execute([$clientId, $serviceId, $req['id'], 'fulfilled', $fulfilledValue, date('Y-m-d H:i:s')]);
                    $fulfilledRequirements[] = $req['title'];
                } else {
                    $stmtUpsert->execute([$clientId, $serviceId, $req['id'], 'pending', null, null]);
                    $missingRequirements[] = $req;
                }
            }

            // Evaluate Overall Workflow Status
            if (!empty($missingRequirements)) {
                // Update Project Status to awaiting_client
                if ($projectId) {
                    $stmtUpPrj = $this->pdo->prepare("UPDATE projects SET status = 'awaiting_client' WHERE id = ?");
                    $stmtUpPrj->execute([$projectId]);
                }

                // Emit event to OmnichannelDispatcher to contact client for missing items
                $dispatchResult = $this->dispatcher->dispatchMissingPrerequisitesTrigger(
                    $clientId,
                    $service['title'],
                    $missingRequirements,
                    $projectId
                );

                $this->pdo->commit();

                return [
                    'status' => 'prerequisites_missing',
                    'client_id' => $clientId,
                    'service_title' => $service['title'],
                    'fulfilled_count' => count($fulfilledRequirements),
                    'missing_count' => count($missingRequirements),
                    'missing_items' => array_column($missingRequirements, 'title'),
                    'dispatcher_result' => $dispatchResult
                ];
            } else {
                // All Prerequisites Fulfilled -> Advance Project to in_progress / review
                if ($projectId) {
                    $stmtUpPrj = $this->pdo->prepare("UPDATE projects SET status = 'in_progress', current_step = 3 WHERE id = ?");
                    $stmtUpPrj->execute([$projectId]);
                }

                $this->pdo->commit();

                return [
                    'status' => 'fully_fulfilled',
                    'client_id' => $clientId,
                    'service_title' => $service['title'],
                    'fulfilled_count' => count($fulfilledRequirements),
                    'missing_count' => 0
                ];
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
