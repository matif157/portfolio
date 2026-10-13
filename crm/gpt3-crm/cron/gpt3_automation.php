<?php
require __DIR__.'/../bootstrap/env.php';
spl_autoload_register(function($c){
    if(str_starts_with($c,'App\\')){
        $f=__DIR__.'/../app/'.str_replace('\\','/',substr($c,4)).'.php';
        if(is_file($f))require $f;
    }
});
$pdo=App\Support\Database::pdo();

$overdue=$pdo->exec(
    "UPDATE invoices SET status='overdue',updated_at=NOW()
     WHERE balance>0 AND due_date<CURDATE() AND status IN ('issued','sent','partially_paid')"
);

$due=$pdo->query(
    "SELECT COUNT(*) FROM workflow_step_executions
     WHERE status IN ('not_started','in_progress')"
)->fetchColumn();

echo "GPT3 automation complete. Overdue invoices updated: ".(int)$overdue.
     "; open workflow steps: ".(int)$due.PHP_EOL;
