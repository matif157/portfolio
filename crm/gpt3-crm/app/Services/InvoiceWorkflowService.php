<?php
namespace App\Services;

use PDO;

final class InvoiceWorkflowService
{
    public function __construct(private PDO $pdo) {}

    public function syncInvoiceState(int $invoiceId): void
    {
        $s=$this->pdo->prepare("SELECT id,status,project_id,balance FROM invoices WHERE id=? LIMIT 1");
        $s->execute([$invoiceId]);
        $invoice=$s->fetch();
        if(!$invoice) return;

        if($invoice['status']==='paid' && !empty($invoice['project_id'])){
            $q=$this->pdo->prepare(
                "UPDATE workflow_step_executions wse
                 JOIN workflow_executions we ON we.id=wse.workflow_execution_id
                 JOIN workflow_steps ws ON ws.id=wse.workflow_step_id
                 SET wse.status='completed', wse.completed_at=NOW()
                 WHERE we.project_id=? AND ws.step_type IN ('payment','paid','invoice_paid')"
            );
            $q->execute([(int)$invoice['project_id']]);
        }
    }
}
