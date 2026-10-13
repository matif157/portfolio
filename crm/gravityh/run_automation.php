<?php
/**
 * GRAVITY CRM - Automation Runner (Cron)
 * Phase 3: Service Automation & Workflow Engine
 * 
 * Is cron job ke zariye run hota hai (set karo: every 5-10 minutes).
 * Isse scheduled automations check hote hain jaise:
 * - Invoice due soon
 * - Invoice overdue
 * - Lead follow-up reminders
 * - Recurring task reminders
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Only allow via CLI or admin session
$allowed = (php_sapi_name() === 'cli');
if (!$allowed) {
    require_login();
    require_permission('settings', 'view');
}

// execute_automation_rule() and db_columns() are defined in functions.php
$log = [];
$total_rules = 0;
$executed = 0;

/**
 * Check for due soon / overdue invoices
 */
$rules = db_query(
    "SELECT * FROM automation_rules WHERE is_active = 1 AND action_type IN ('notify','send_email','create_task','start_workflow')
     AND trigger_module = 'invoices'"
);

foreach ($rules as $rule) {
    $total_rules++;
    $config = json_decode($rule['action_config'] ?? '{}', true) ?: [];
    
    // Determine which invoices to check based on trigger event
    if ($rule['trigger_event'] === 'due_soon') {
        $due_date = date('Y-m-d', strtotime('+' . (($config['due_in_days'] ?? 3)) . ' days'));
        $invoices = db_query(
            "SELECT i.*, c.first_name, c.last_name, c.email FROM invoices i 
             LEFT JOIN clients c ON i.client_id = c.id 
             WHERE i.status IN ('sent','partial','overdue') 
               AND DATE(i.due_date) = ? 
               AND i.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
               OR (i.due_date <= ? AND i.due_date > DATE_SUB(?, INTERVAL 1 DAY) AND i.status = 'sent')",
            [$due_date, $due_date, $due_date]
        );
        // Simpler version for reliability:
        $invoices = db_query(
            "SELECT i.*, c.first_name, c.last_name, c.email, c.phone FROM invoices i 
             LEFT JOIN clients c ON i.client_id = c.id 
             WHERE i.status IN ('sent','partial','overdue') 
             AND i.due_date <= ?
             AND i.amount_due > 0
             ORDER BY i.due_date LIMIT 50",
            [$due_date]
        );
    } elseif ($rule['trigger_event'] === 'overdue') {
        $invoices = db_query(
            "SELECT i.*, c.first_name, c.last_name, c.email, c.phone FROM invoices i 
             LEFT JOIN clients c ON i.client_id = c.id 
             WHERE i.status = 'sent' 
             AND i.due_date < CURDATE() 
             AND i.amount_due > 0
             ORDER BY i.due_date LIMIT 50"
        );
    } else {
        // Skip non-scheduled events (create/update handled in CRUD pages)
        continue;
    }
    
    foreach ($invoices as $invoice) {
        $context = [
            'record_id' => $invoice['id'],
            'test' => false,
            'client_id' => $invoice['client_id'],
            'email' => $invoice['email'] ?? '',
            'phone' => $invoice['phone'] ?? '',
            'invoice_number' => $invoice['invoice_number'],
            'invoice_total' => $invoice['total_amount'],
            'invoice_due_date' => $invoice['due_date'],
            'client_first_name' => $invoice['first_name'] ?? '',
            'client_last_name' => $invoice['last_name'] ?? '',
        ];
        
        $result = execute_automation_rule($rule, $context);
        db_execute(
            'INSERT INTO automation_logs (rule_id, trigger_module, trigger_event, record_id, action_type, action_result, message, executed_by) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)',
            [$rule['id'], 'invoices', $rule['trigger_event'], $invoice['id'], $rule['action_type'], $result[0] ? 'completed' : 'failed', $result[1]]
        );
        $executed++;
    }
}

// Process lead follow-ups
$lead_rules = db_query(
    "SELECT * FROM automation_rules WHERE is_active = 1 AND trigger_module = 'leads' AND trigger_event = 'follow_up'"
);
foreach ($lead_rules as $rule) {
    $total_rules++;
    $stale_days = 3; // default follow-up after 3 days
    $config = json_decode($rule['action_config'] ?? '{}', true) ?: [];
    $config['due_in_days'] = $config['due_in_days'] ?? $stale_days;
    $stale_date = date('Y-m-d', strtotime('-' . $config['due_in_days'] . ' days'));
    
    // Check if leads table has last_follow_up column
    $columns = db_columns('leads');
    $has_follow_up = in_array('last_follow_up', $columns);
    
    if ($has_follow_up) {
        $leads = db_query(
            "SELECT l.*, u.email AS user_email FROM leads l 
             LEFT JOIN users u ON l.assigned_user_id = u.id 
             WHERE l.status IN ('new','contacted') 
             AND l.updated_at <= ?
             AND (l.last_follow_up IS NULL OR l.last_follow_up <= ?)
             ORDER BY l.updated_at LIMIT 50",
            [$stale_date . ' 23:59:59', $stale_date]
        );
    } else {
        $leads = db_query(
            "SELECT l.*, u.email AS user_email FROM leads l 
             LEFT JOIN users u ON l.assigned_user_id = u.id 
             WHERE l.status IN ('new','contacted') 
             AND l.updated_at <= ?
             ORDER BY l.updated_at LIMIT 50",
            [$stale_date . ' 23:59:59']
        );
    }
    
    foreach ($leads as $lead) {
        $context = [
            'record_id' => $lead['id'],
            'test' => false,
            'email' => $lead['email'] ?? '',
            'phone' => $lead['phone'] ?? '',
            'client_first_name' => $lead['first_name'] ?? '',
            'client_last_name' => $lead['last_name'] ?? '',
        ];
        
        $result = execute_automation_rule($rule, $context);
        db_execute(
            'INSERT INTO automation_logs (rule_id, trigger_module, trigger_event, record_id, action_type, action_result, message, executed_by) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)',
            [$rule['id'], 'leads', 'follow_up', $lead['id'], $rule['action_type'], $result[0] ? 'completed' : 'failed', $result[1]]
        );
        
        // Update last follow up timestamp
        db_execute('UPDATE leads SET last_follow_up = NOW() WHERE id = ?', [$lead['id']]);
        $executed++;
    }
}

// Clean up old logs (keep last 90 days)
db_execute('DELETE FROM automation_logs WHERE executed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');

// Output result
if (php_sapi_name() === 'cli') {
    echo "Automation run complete.\n";
    echo "Rules found: {$total_rules}\n";
    echo "Actions executed: {$executed}\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
}

// Log to system
log_activity('automation', 'cron_run', null, "Automation cron executed. Rules: {$total_rules}, Actions: {$executed}");
