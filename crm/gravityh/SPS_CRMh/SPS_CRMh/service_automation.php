<?php
/**
 * SPS Professional Services CRM
 * Central Automation Engine & Event Dispatcher
 */
require_once __DIR__ . '/auth.php';
require_permission('services.manage');

// Handle Manual Automation Scan Trigger
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_scan') {
    verify_csrf();
    
    // 1. Scan for missing bank statements for all active accounts
    $missing_items = $pdo->query("
        SELECT bs.id, bs.period_year, bs.period_month, ba.bank_name, ba.account_number_last4, b.business_name, c.id as client_id, c.first_name, c.email
        FROM bank_statements bs
        JOIN bank_accounts ba ON bs.bank_account_id = ba.id
        JOIN businesses b ON ba.business_id = b.id
        JOIN clients c ON b.client_id = c.id
        WHERE bs.status = 'missing'
    ")->fetchAll();

    $reminders_created = 0;
    foreach ($missing_items as $item) {
        $month_name = get_month_name($item['period_month']);
        $title = "Missing {$month_name} {$item['period_year']} Statement";
        $msg = "Reminder: Please upload {$month_name} {$item['period_year']} bank statement for {$item['bank_name']} (x-{$item['account_number_last4']}).";
        
        // Log Outbound Communication (Email Priority)
        $stmtComm = $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status) VALUES (?, 'email', ?, ?, 'outbound', 'sent')");
        $stmtComm->execute([$item['client_id'], $title, $msg]);
        
        $reminders_created++;
    }

    // 2. Auto advance completed workflows
    // Check if all statements received for any active bookkeeping client service
    $active_services = $pdo->query("SELECT id, client_id, business_id FROM client_services WHERE service_id = 1 AND status = 'in_progress'")->fetchAll();
    $workflows_advanced = 0;
    
    foreach ($active_services as $cs) {
        $missing_count = $pdo->prepare("
            SELECT COUNT(*) 
            FROM bank_statements bs
            JOIN bank_accounts ba ON bs.bank_account_id = ba.id
            WHERE ba.business_id = ? AND bs.status = 'missing'
        ");
        $missing_count->execute([$cs['business_id']]);
        if ($missing_count->fetchColumn() == 0) {
            // All statements received! Advance workflow step to 3 (Bookkeeping Reconciliation) & auto-create staff task
            $pdo->prepare("UPDATE client_services SET current_step_number = 3, status = 'review' WHERE id = ?")->execute([$cs['id']]);
            
            // Create staff review task
            $pdo->prepare("INSERT INTO tasks (client_id, title, description, status, priority, due_date) VALUES (?, 'Complete Monthly Reconciliation', 'All 2026 bank statements received. Prepare bookkeeping entries.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 3 DAY))")->execute([$cs['client_id']]);
            
            $workflows_advanced++;
        }
    }

    log_activity('Automation Engine Run', 'automation', 0, "Executed automation scan: Created $reminders_created notifications, Advanced $workflows_advanced workflows.");
    set_flash('success', "Automation Engine Execution Complete! Created $reminders_created automated reminder logs & updated $workflows_advanced service workflows.");
    redirect('service_automation.php');
}

// Fetch Automation Logs
$activity_logs = $pdo->query("SELECT * FROM activities WHERE module IN ('automation', 'service_automation') ORDER BY created_at DESC LIMIT 15")->fetchAll();
$communications = $pdo->query("SELECT c.*, cl.first_name, cl.last_name, cl.company_name FROM communications c JOIN clients cl ON c.client_id = cl.id ORDER BY c.created_at DESC LIMIT 10")->fetchAll();

render_header('Service Automation Engine', 'services');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Central Service Automation Engine</h1>
        <p class="page-subtitle">Event dispatcher, statement collection cron scanner, and multi-channel notification rules</p>
    </div>
    <div>
        <form action="service_automation.php" method="POST" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_scan">
            <button type="submit" class="btn btn-primary">⚡ Execute Automation Scan Now</button>
        </form>
    </div>
</div>

<!-- Automation Architecture Rules Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Automation Channel Priority</span>
        <span class="stat-value" style="font-size:18px; color:var(--accent);">Portal → Email → SMS → AI Voice</span>
        <span class="stat-desc">Multi-channel reminder escalation sequence</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">AI Document Intelligence</span>
        <span class="stat-value" style="font-size:18px;">Auto-Match & Deduplicate</span>
        <span class="stat-desc">PDF year/month matching from filename</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Workflow Escalation</span>
        <span class="stat-value" style="font-size:18px; color:var(--success);">Auto-Advance Step</span>
        <span class="stat-desc">Triggers staff task on 100% statement receipt</span>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
    <!-- Communication Automation History -->
    <div class="card">
        <div class="card-title">Automated Outbound Communications Log</div>
        <?php if (empty($communications)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No automated messages dispatched yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Channel</th>
                            <th>Subject</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($communications as $comm): ?>
                            <tr>
                                <td><strong><?= e($comm['company_name'] ?: "{$comm['first_name']} {$comm['last_name']}") ?></strong></td>
                                <td><span class="badge badge-info"><?= strtoupper($comm['channel']) ?></span></td>
                                <td><?= e($comm['subject']) ?></td>
                                <td><span style="font-size:11px; color:var(--text-muted);"><?= format_datetime($comm['sent_at']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- System Automation Execution Logs -->
    <div class="card">
        <div class="card-title">Automation Execution Trail</div>
        <?php if (empty($activity_logs)): ?>
            <p style="font-size:13px; color:var(--text-muted);">No recent automation logs.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($activity_logs as $log): ?>
                    <div style="padding:10px; background:#f8fafc; border-left:3px solid var(--success); border-radius:4px; font-size:13px;">
                        <div style="display:flex; justify-space-between; margin-bottom:2px;">
                            <strong><?= e($log['action']) ?></strong>
                            <span style="font-size:11px; color:var(--text-muted);"><?= format_datetime($log['created_at']) ?></span>
                        </div>
                        <div style="color:var(--text-muted);"><?= e($log['description']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_footer(); ?>
