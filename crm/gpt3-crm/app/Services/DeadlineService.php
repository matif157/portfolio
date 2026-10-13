<?php
namespace App\Services;
use App\Core\Database;
final class DeadlineService {
    public function generate(int $workflowInstanceId): int { $s=Database::pdo()->prepare('SELECT step_id, due_offset_days FROM workflow_step_deadlines WHERE workflow_instance_id=? AND generated=0');$s->execute([$workflowInstanceId]);$n=0;foreach($s as $r){Database::pdo()->prepare('INSERT INTO deadlines(tenant_id,workflow_instance_id,title,due_at,status,created_at) VALUES(1,?,?,DATE_ADD(NOW(),INTERVAL ? DAY),"open",NOW())')->execute([$workflowInstanceId,'Workflow step #'.$r['step_id'],(int)$r['due_offset_days']]);Database::pdo()->prepare('UPDATE workflow_step_deadlines SET generated=1 WHERE workflow_instance_id=? AND step_id=?')->execute([$workflowInstanceId,$r['step_id']]);$n++;}return $n; }
}
