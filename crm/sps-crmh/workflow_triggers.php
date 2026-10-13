<?php
/**
 * SPS Professional Services CRM
 * Automated Workflow Triggers Manager
 * Configure event-based triggers that auto-fire Twilio calls, SMS, tasks, and emails
 */
require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

// Handle Add / Toggle / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_trigger') {
        $title = trim($_POST['title'] ?? '');
        $event_type = $_POST['event_type'] ?? '';
        $action_type = $_POST['action_type'] ?? '';
        $payload = trim($_POST['action_payload'] ?? '');
        $delay_minutes = intval($_POST['delay_minutes'] ?? 0);

        if ($title && $event_type && $action_type) {
            $pdo->prepare("
                INSERT INTO workflow_triggers (title, event_type, action_type, action_payload, delay_minutes, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ")->execute([$title, $event_type, $action_type, $payload, $delay_minutes]);

            log_activity('Add Workflow Trigger', 'automation', 0, "Created trigger: $title on $event_type → $action_type");
            set_flash('success', "Automation trigger '{$title}' created successfully!");
        }
    } elseif ($action === 'toggle_trigger') {
        $id = intval($_POST['trigger_id']);
        $pdo->prepare("UPDATE workflow_triggers SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        set_flash('success', 'Trigger status toggled.');
    } elseif ($action === 'delete_trigger') {
        $id = intval($_POST['trigger_id']);
        $pdo->prepare("DELETE FROM workflow_triggers WHERE id = ?")->execute([$id]);
        set_flash('success', 'Automation trigger removed.');
    } elseif ($action === 'run_trigger_now') {
        $id = intval($_POST['trigger_id']);
        $stmtT = $pdo->prepare("SELECT * FROM workflow_triggers WHERE id = ?");
        $stmtT->execute([$id]);
        $trigger = $stmtT->fetch();

        if ($trigger) {
            // Run against all active clients
            $clients = $pdo->query("SELECT id FROM clients WHERE status = 'active'")->fetchAll();
            $count = 0;
            foreach ($clients as $cl) {
                fire_workflow_event($trigger['event_type'], ['client_id' => $cl['id']]);
                $count++;
            }
            log_activity('Manual Trigger Execution', 'automation', $id, "Manually fired trigger '{$trigger['title']}' for $count active clients.");
            set_flash('success', "Trigger '{$trigger['title']}' executed against {$count} active clients!");
        }
    }

    redirect('workflow_triggers.php');
}

$triggers = $pdo->query("SELECT * FROM workflow_triggers ORDER BY is_active DESC, id ASC")->fetchAll();

$event_types = [
    'client_created' => '👤 Client Created',
    'lead_qualified' => '⭐ Lead Qualified',
    'statement_missing' => '🏦 Statement Still Missing',
    'invoice_overdue' => '💰 Invoice Overdue',
    'project_stalled' => '📋 Project Status Stalled',
    'service_step_advanced' => '⚙️ Service Workflow Step Advanced',
    'document_uploaded' => '📎 Document Uploaded',
    'custom' => '🔧 Custom Event',
];

$action_types = [
    'twilio_call' => '📞 Twilio Voice Call',
    'twilio_sms' => '💬 Twilio SMS',
    'create_task' => '✅ Create Staff Task',
    'send_email' => '📧 Send Email Notification',
];

render_header('Workflow Automation Triggers', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">⚡ Automated Workflow Triggers Engine</h1>
        <p class="page-subtitle">Configure event-based triggers: auto call, SMS, email, and task creation when business events fire</p>
    </div>
    <div>
        <button onclick="document.getElementById('addTriggerModal').style.display='block'" class="btn btn-primary">+ Add Automation Trigger</button>
    </div>
</div>

<!-- Add Trigger Modal -->
<div id="addTriggerModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:580px; margin:70px auto;">
        <div class="card-title" style="display:flex; justify-content:space-between;">
            <span>⚡ Create Automation Trigger Rule</span>
            <button onclick="document.getElementById('addTriggerModal').style.display='none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>

        <form action="workflow_triggers.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_trigger">

            <div class="form-group">
                <label class="form-label">Trigger Rule Name *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Call client when invoice overdue 3 days" required>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">When This Event Fires *</label>
                    <select name="event_type" class="form-control" required>
                        <option value="">-- Select Event Type --</option>
                        <?php foreach ($event_types as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Execute This Action *</label>
                    <select name="action_type" class="form-control" required>
                        <option value="">-- Select Action --</option>
                        <?php foreach ($action_types as $val => $label): ?>
                            <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Action Content / Call Script / Task Description</label>
                <textarea name="action_payload" class="form-control" rows="3" placeholder="Message to send or task description to create. For calls: the TwiML voice script. For tasks: the task description."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Delay Before Action (minutes)</label>
                <input type="number" name="delay_minutes" class="form-control" value="0" min="0">
                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">0 = Immediately on event fire</div>
            </div>

            <div style="margin-top:20px; text-align:right;">
                <button type="button" onclick="document.getElementById('addTriggerModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Trigger</button>
            </div>
        </form>
    </div>
</div>

<!-- Trigger Rules Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Trigger Rule Name</th>
                    <th>Event That Fires It</th>
                    <th>Automated Action</th>
                    <th>Delay</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($triggers)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">
                            No automation triggers configured yet. Create your first trigger above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($triggers as $t): ?>
                        <tr style="<?= !$t['is_active'] ? 'opacity:0.55;' : '' ?>">
                            <td>
                                <span class="badge badge-<?= $t['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $t['is_active'] ? '● ACTIVE' : '○ PAUSED' ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($t['title']) ?></strong>
                                <?php if ($t['action_payload']): ?>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:3px; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        <?= e(substr($t['action_payload'], 0, 80)) ?>...
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= e($event_types[$t['event_type']] ?? $t['event_type']) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?= e($action_types[$t['action_type']] ?? $t['action_type']) ?></span>
                            </td>
                            <td><?= $t['delay_minutes'] > 0 ? $t['delay_minutes'] . ' mins' : 'Immediate' ?></td>
                            <td>
                                <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                    <!-- Run Now -->
                                    <form action="workflow_triggers.php" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="run_trigger_now">
                                        <input type="hidden" name="trigger_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary" title="Run against all active clients now">⚡ Run Now</button>
                                    </form>

                                    <!-- Toggle -->
                                    <form action="workflow_triggers.php" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_trigger">
                                        <input type="hidden" name="trigger_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary"><?= $t['is_active'] ? 'Pause' : 'Activate' ?></button>
                                    </form>

                                    <!-- Delete -->
                                    <form action="workflow_triggers.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this trigger?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_trigger">
                                        <input type="hidden" name="trigger_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Automation Event Hooks Reference -->
<div class="card" style="border-top:3px solid var(--accent);">
    <div class="card-title">Automation Event Hook Reference</div>
    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; font-size:12px;">
        <?php foreach ($event_types as $key => $label): ?>
            <div style="padding:10px; background:#f8fafc; border:1px solid var(--border-color); border-radius:6px;">
                <strong><?= $label ?></strong>
                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;"><code>fire_workflow_event('<?= $key ?>', [...])</code></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php render_footer(); ?>
