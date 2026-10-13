<?php
/**
 * GRAVITY CRM — Pro Dialer Module
 * 
 * Yahan user phone number enter karta hai to system pehle clients, phir contacts,
 * phir leads me se number match karta hai. Agar client mil jata hai to uski poori
 * profile + call history dikhati hai aur call log (comments, status, duration)
 * communications table me save hota hai. Agar number kisi client ka nahi hai to
 * "Move to Lead/Contact Workflow" option se lead ban jata hai.
 */
require_once __DIR__ . '/auth.php';
require_login();
if (!user_can('communications.manage') && !user_can('clients.view')) {
    require_permission('communications.manage');
}

$lookup_phone = trim($_GET['phone'] ?? '');
$lookup_phone = preg_replace('/[^0-9+]/', '', $lookup_phone);
$found_client = null;
$matched_contacts = [];
$matched_leads = [];

if ($lookup_phone !== '') {
    // Client lookup — Yahan hum clients table me phone match kar rahe hain
    $stmt = $pdo->prepare("SELECT c.*, b.business_name FROM clients c LEFT JOIN businesses b ON b.client_id = c.id WHERE c.phone LIKE ? LIMIT 1");
    $stmt->execute(['%' . $lookup_phone . '%']);
    $found_client = $stmt->fetch();

    // Contacts lookup
    $stmt = $pdo->prepare("SELECT ct.*, cl.first_name, cl.last_name FROM contacts ct LEFT JOIN clients cl ON ct.client_id = cl.id WHERE ct.phone LIKE ? LIMIT 10");
    $stmt->execute(['%' . $lookup_phone . '%']);
    $matched_contacts = $stmt->fetchAll();

    // Leads lookup
    $stmt = $pdo->prepare("SELECT * FROM leads WHERE phone LIKE ? LIMIT 10");
    $stmt->execute(['%' . $lookup_phone . '%']);
    $matched_leads = $stmt->fetchAll();
}

// Handle POST actions: log_call aur create_lead
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'log_call') {
        $client_id  = intval($_POST['client_id'] ?? 0);
        $phone      = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
        $direction  = ($_POST['direction'] ?? 'outbound') === 'inbound' ? 'inbound' : 'outbound';
        $status_map = ['completed' => 'completed', 'no_answer' => 'failed', 'missed' => 'failed', 'busy' => 'failed', 'calling' => 'calling', 'failed' => 'failed'];
        $status     = $status_map[$_POST['status'] ?? 'completed'] ?? 'completed';
        $duration   = max(0, intval($_POST['duration'] ?? 0));
        $comments   = trim($_POST['comments'] ?? '');

        if ($client_id > 0 && $phone !== '') {
            // Call log communications table me — comment area call discussion ke liye
            $stmt = $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status, call_duration) VALUES (?, 'voice', ?, ?, ?, ?, ?)");
            $stmt->execute([
                $client_id,
                'Dialer Call ' . $phone,
                $comments !== '' ? $comments : 'Call logged via CRM dialer (' . $direction . ', status: ' . $status . ').',
                $direction,
                $status,
                $duration
            ]);
            log_activity('Dialer Call Logged', 'communications', $client_id, "Call {$direction} to {$phone} (status: {$status}, duration: {$duration}s)", 'stage1_normal');
            set_flash('success', 'Call logged successfully with discussion notes.');
            redirect('dialer.php?phone=' . urlencode($phone));
        }
    } elseif ($action === 'create_lead') {
        $contact_name = trim($_POST['contact_name'] ?? '');
        $phone        = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $company      = trim($_POST['company'] ?? '');
        if ($contact_name !== '' && $phone !== '') {
            // Unknown number — lead workflow me move karte hain (move-to-contact)
            $stmt = $pdo->prepare("INSERT INTO leads (title, contact_name, company_name, email, phone, source, status) VALUES (?, ?, ?, ?, ?, 'Dialer', 'new')");
            $stmt->execute([$contact_name . ' (Dialer)', $contact_name, $company, $email, $phone]);
            $lead_id = $pdo->lastInsertId();
            log_activity('Dialer Lead Created', 'leads', $lead_id, "Unknown number {$phone} moved to lead workflow as {$contact_name}", 'stage1_normal');
            set_flash('success', "Unknown number moved to Leads — Lead #{$lead_id} created.");
            redirect('leads.php');
        }
    }
}

// Recent dialer call history (channel = voice)
$recent_calls = $pdo->query("
    SELECT cm.*, c.first_name, c.last_name, c.company_name, c.phone
    FROM communications cm
    LEFT JOIN clients c ON cm.client_id = c.id
    WHERE cm.channel = 'voice'
    ORDER BY cm.id DESC
    LIMIT 25
")->fetchAll();

// Client call history for the found client
$client_calls = [];
if ($found_client) {
    $stmt = $pdo->prepare("SELECT * FROM communications WHERE client_id = ? AND channel = 'voice' ORDER BY id DESC LIMIT 20");
    $stmt->execute([$found_client['id']]);
    $client_calls = $stmt->fetchAll();
}

render_header('CRM Dialer & Call Center', 'communications');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">📞 CRM Dialer &amp; Call Center</h1>
        <p class="page-subtitle">Call a client by phone number — system fetches client info, logs call history and supports unknown-number lead routing</p>
    </div>
</div>

<div class="card">
    <div class="card-title">Find a Number / Start a Call</div>
    <form method="GET" action="dialer.php" class="form-row" style="gap:12px; align-items:flex-end;">
        <div class="form-col form-group" style="flex:2;">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="+1 212 555 0199" value="<?= e($lookup_phone) ?>" required>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">🔍 Fetch Client</button>
        </div>
    </form>
</div>

<?php if ($lookup_phone !== '' && $found_client): ?>
    <div class="card" style="border-top:4px solid var(--success);">
        <div class="card-title">✅ Existing Client Found</div>
        <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div><div style="font-size:11px;color:var(--text-muted);font-weight:700;">CLIENT</div>
                <div style="margin-top:6px;font-weight:700;"><?= e($found_client['first_name'] . ' ' . $found_client['last_name']) ?></div>
                <a href="client_profile.php?id=<?= $found_client['id'] ?>" class="btn btn-sm btn-secondary" style="margin-top:8px;">Open Profile →</a>
            </div>
            <div><div style="font-size:11px;color:var(--text-muted);font-weight:700;">BUSINESS</div>
                <div style="margin-top:6px;"><?= e($found_client['company_name'] ?: $found_client['business_name'] ?: '—') ?></div>
            </div>
            <div><div style="font-size:11px;color:var(--text-muted);font-weight:700;">PHONE</div>
                <div style="margin-top:6px;"><?= e($found_client['phone']) ?></div>
            </div>
            <div><div style="font-size:11px;color:var(--text-muted);font-weight:700;">EMAIL</div>
                <div style="margin-top:6px;"><?= e($found_client['email']) ?></div>
            </div>
        </div>

        <div style="margin-top:18px;">
            <h3 style="font-size:15px; font-weight:600; margin-bottom:10px;">📝 Log This Call</h3>
            <form method="POST" action="dialer.php" class="form-row" style="gap:12px; align-items:flex-end;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="log_call">
                <input type="hidden" name="client_id" value="<?= $found_client['id'] ?>">
                <input type="hidden" name="phone" value="<?= e($lookup_phone) ?>">
                <div class="form-col form-group">
                    <label class="form-label">Direction</label>
                    <select name="direction" class="form-control">
                        <option value="outbound">Outbound</option>
                        <option value="inbound">Inbound</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="completed">✅ Completed</option>
                        <option value="no_answer">❌ No Answer</option>
                        <option value="missed">📵 Missed</option>
                        <option value="busy">🟠 Busy</option>
                        <option value="calling">📞 Calling</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Duration (sec)</label>
                    <input type="number" name="duration" class="form-control" value="0" min="0">
                </div>
                <div class="form-col form-group" style="flex:2;">
                    <label class="form-label">Call Discussion / Comments</label>
                    <input type="text" name="comments" class="form-control" placeholder="What was discussed? e.g. Client will send W-2 by Friday...">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Call Log</button>
                </div>
            </form>
        </div>
        <?php if (!empty($client_calls)): ?>
        <div style="margin-top:22px;">
            <h3 style="font-size:15px; font-weight:600; margin-bottom:10px;">📜 Call History — <?= e($found_client['first_name'] . ' ' . $found_client['last_name']) ?></h3>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Date/Time</th><th>Direction</th><th>Status</th><th>Duration</th><th>Discussion / Notes</th></tr></thead>
                    <tbody>
                    <?php foreach ($client_calls as $cc): ?>
                        <tr>
                            <td><?= format_datetime($cc['created_at']) ?></td>
                            <td><span class="badge badge-info"><?= ucfirst($cc['direction']) ?></span></td>
                            <td><span class="badge badge-<?= $cc['status'] === 'completed' ? 'success' : ($cc['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= ucfirst($cc['status']) ?></span></td>
                            <td><?= intval($cc['call_duration']) ?>s</td>
                            <td><?= e($cc['message_body']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php elseif ($lookup_phone !== ''): ?>
    <div class="card" style="border-top:4px solid var(--warning);">
        <div class="card-title">ℹ️ Number Not Found in Clients</div>
        <?php if ($matched_contacts || $matched_leads): ?>
            <?php foreach ($matched_contacts as $mc): ?>
                <div style="padding:8px 0; border-bottom:1px solid var(--border-color);">
                    <strong><?= e($mc['name']) ?></strong> — <?= e($mc['phone']) ?> <em>(Contact of <?= e($mc['first_name'] . ' ' . $mc['last_name']) ?>)</em>
                </div>
            <?php endforeach; ?>
            <?php foreach ($matched_leads as $ml): ?>
                <div style="padding:8px 0; border-bottom:1px solid var(--border-color);">
                    <strong><?= e($ml['contact_name']) ?></strong> — <?= e($ml['phone']) ?> <em>(Lead, status: <?= e($ml['status']) ?>)</em>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top:14px;">
            <h3 style="font-size:15px; font-weight:600; margin-bottom:10px;">➕ Move to Contact / Lead Workflow</h3>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:10px;">Yeh number kisi existing client ka nahi hai. Neeche info bhar kar lead workflow me move karein.</p>
            <form method="POST" action="dialer.php" class="form-row" style="gap:12px; align-items:flex-end;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_lead">
                <input type="hidden" name="phone" value="<?= e($lookup_phone) ?>">
                <div class="form-col form-group">
                    <label class="form-label">Contact Name *</label>
                    <input type="text" name="contact_name" class="form-control" required placeholder="John Smith">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Company</label>
                    <input type="text" name="company" class="form-control" placeholder="ABC Corp">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="john@example.com">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Create Lead</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">🕐 Recent Dialer Activity (All Calls)</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Client</th><th>Phone</th><th>Direction</th><th>Status</th><th>Duration</th><th>When</th><th>Discussion / Notes</th></tr></thead>
            <tbody>
            <?php if (empty($recent_calls)): ?>
                <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No calls logged yet. Use the dialer above to log your first call.</td></tr>
            <?php else: ?>
                <?php foreach ($recent_calls as $rc): ?>
                    <tr>
                        <td><a href="client_profile.php?id=<?= $rc['client_id'] ?>"><?= e($rc['first_name'] . ' ' . $rc['last_name']) ?></a></td>
                        <td><?= e($rc['phone'] ?: '—') ?></td>
                        <td><span class="badge badge-info"><?= ucfirst($rc['direction']) ?></span></td>
                        <td><span class="badge badge-<?= $rc['status'] === 'completed' ? 'success' : ($rc['status'] === 'failed' ? 'danger' : 'secondary') ?>"><?= ucfirst($rc['status']) ?></span></td>
                        <td><?= intval($rc['call_duration']) ?>s</td>
                        <td><?= format_datetime($rc['created_at']) ?></td>
                        <td><?= e($rc['message_body']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
