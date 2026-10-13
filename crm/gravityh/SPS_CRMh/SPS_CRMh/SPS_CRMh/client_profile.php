<?php
/**
 * SPS Professional Services CRM
 * Comprehensive 360-Degree Client Profile Page & Multi-Channel Twilio Logs
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$client_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, u.full_name as assigned_staff 
    FROM clients c 
    LEFT JOIN users u ON c.assigned_staff_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('danger', 'Client record not found.');
    redirect('clients.php');
}

// POST Handling for Communications & Comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    
    if ($_POST['action'] === 'add_comment') {
        $comment_text = trim($_POST['comment'] ?? '');
        if (!empty($comment_text)) {
            $stmtC = $pdo->prepare("INSERT INTO client_comments (client_id, user_id, comment) VALUES (?, ?, ?)");
            $stmtC->execute([$client_id, $_SESSION['user_id'], $comment_text]);
            log_activity('Add Client Comment', 'clients', $client_id, "Added comment to client #{$client['client_number']}");
            set_flash('success', 'Comment saved successfully.');
        }
    } elseif ($_POST['action'] === 'trigger_twilio_call') {
        $script = trim($_POST['call_script'] ?? "Hello {$client['first_name']}, this is an automated reminder from Syed Professional Services regarding your outstanding accounting deliverables.");
        $res = send_twilio_call($client_id, $script);
        set_flash('success', "Twilio Voice Call initiated to {$client['phone']}! Duration: {$res['duration']}s logged to communication history.");
    } elseif ($_POST['action'] === 'trigger_twilio_sms') {
        $sms_text = trim($_POST['sms_body'] ?? "SPS Alert: Please check your portal for pending tax/bookkeeping documents.");
        send_twilio_sms($client_id, $sms_text);
        set_flash('success', "Twilio SMS dispatched to {$client['phone']}!");
    }
    redirect("client_profile.php?id=$client_id#communications");
}

// Fetch Linked Sub-Entities
$businesses = $pdo->prepare("SELECT * FROM businesses WHERE client_id = ? ORDER BY id DESC");
$businesses->execute([$client_id]);
$business_list = $businesses->fetchAll();

$services = $pdo->prepare("
    SELECT cs.*, s.title as service_title, s.price, b.business_name 
    FROM client_services cs
    JOIN services s ON cs.service_id = s.id
    LEFT JOIN businesses b ON cs.business_id = b.id
    WHERE cs.client_id = ? ORDER BY cs.id DESC
");
$services->execute([$client_id]);
$service_list = $services->fetchAll();

$projects = $pdo->prepare("SELECT * FROM projects WHERE client_id = ? ORDER BY id DESC");
$projects->execute([$client_id]);
$project_list = $projects->fetchAll();

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE client_id = ? ORDER BY issue_date DESC");
$invoices->execute([$client_id]);
$invoice_list = $invoices->fetchAll();

$payments = $pdo->prepare("SELECT p.*, i.invoice_number FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE p.client_id = ? ORDER BY p.payment_date DESC");
$payments->execute([$client_id]);
$payment_list = $payments->fetchAll();

$bank_accounts = $pdo->prepare("
    SELECT ba.*, b.business_name,
           (SELECT COUNT(*) FROM bank_statements bs WHERE bs.bank_account_id = ba.id AND bs.status = 'missing') as missing_count
    FROM bank_accounts ba
    JOIN businesses b ON ba.business_id = b.id
    WHERE b.client_id = ?
");
$bank_accounts->execute([$client_id]);
$account_list = $bank_accounts->fetchAll();

// Multi-Channel Communications Timeline (Calls, Emails, SMS, WhatsApp)
$communications = $pdo->prepare("SELECT * FROM communications WHERE client_id = ? ORDER BY sent_at DESC");
$communications->execute([$client_id]);
$comm_list = $communications->fetchAll();

$comments = $pdo->prepare("
    SELECT cc.*, u.full_name 
    FROM client_comments cc 
    JOIN users u ON cc.user_id = u.id 
    WHERE cc.client_id = ? 
    ORDER BY cc.created_at DESC
");
$comments->execute([$client_id]);
$comment_list = $comments->fetchAll();

render_header("Profile: {$client['client_number']} - " . ($client['company_name'] ?: "{$client['first_name']} {$client['last_name']}"), 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($client['company_name'] ?: "{$client['first_name']} {$client['last_name']}") ?></h1>
        <p class="page-subtitle">Client #<?= e($client['client_number']) ?> • Assigned Staff: <?= e($client['assigned_staff'] ?: 'Unassigned') ?></p>
    </div>
    <div>
        <a href="edit_client.php?id=<?= $client_id ?>" class="btn btn-secondary">Edit Profile</a>
        <a href="bank_statements.php?client_id=<?= $client_id ?>" class="btn btn-primary">🏦 Bank Statement Matrix</a>
    </div>
</div>

<!-- Overview Banner -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Client Status</span>
        <span class="stat-value" style="font-size:20px;">
            <span class="badge badge-<?= $client['status'] === 'active' ? 'success' : 'warning' ?>"><?= ucfirst($client['status']) ?></span>
        </span>
        <span class="stat-desc">Registered <?= format_date($client['created_at']) ?></span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Businesses</span>
        <span class="stat-value"><?= count($business_list) ?></span>
        <span class="stat-desc">Corporate entities</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Projects</span>
        <span class="stat-value"><?= count($project_list) ?></span>
        <span class="stat-desc">Active service contracts</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Communication Touchpoints</span>
        <span class="stat-value" style="color:var(--accent);"><?= count($comm_list) ?></span>
        <span class="stat-desc">Calls, Emails, SMS, WhatsApp</span>
    </div>
</div>

<!-- Information & Operations Layout -->
<div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px;">
    <!-- Left Column: Details & Businesses -->
    <div>
        <div class="card">
            <div class="card-title">Contact & Address Details</div>
            <table style="width:100%; font-size:13px;" class="table">
                <tr><th>Email:</th><td><?= e($client['email']) ?></td></tr>
                <tr><th>Phone:</th><td><strong><?= e($client['phone'] ?: '-') ?></strong></td></tr>
                <tr><th>Client Type:</th><td><?= ucfirst($client['client_type']) ?></td></tr>
                <tr><th>Address:</th><td><?= e($client['address'] ?: '-') ?></td></tr>
                <tr><th>City/State/Zip:</th><td><?= e("{$client['city']}, {$client['state']} {$client['zip']}") ?></td></tr>
            </table>
        </div>

        <div class="card">
            <div class="card-title">
                <span>Businesses (<?= count($business_list) ?>)</span>
                <a href="businesses.php?client_id=<?= $client_id ?>" class="btn btn-sm btn-secondary">+ Add</a>
            </div>
            <?php if (empty($business_list)): ?>
                <p style="font-size:13px; color:var(--text-muted);">No businesses registered.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <?php foreach ($business_list as $b): ?>
                        <div style="padding:10px; background:#f8fafc; border:1px solid var(--border-color); border-radius:6px; font-size:13px;">
                            <strong><?= e($b['business_name']) ?></strong> (<?= e($b['entity_type']) ?>)<br>
                            <span style="font-size:11px; color:var(--text-muted);">Tax ID: <?= e($b['tax_id'] ?: 'N/A') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Multi-Channel Communication History (Calls, SMS, Email, WhatsApp) -->
    <div>
        <!-- Twilio Telephony Quick Actions -->
        <div class="card" style="border-top:4px solid var(--accent);" id="communications">
            <div class="card-title">
                <span>📞 Dispatch Multi-Channel Communication</span>
                <span class="badge badge-info">Twilio & SMTP API</span>
            </div>

            <div style="display:flex; gap:12px; margin-bottom:15px;">
                <button onclick="document.getElementById('callForm').style.display='block'; document.getElementById('smsForm').style.display='none';" class="btn btn-sm btn-primary">📞 Initiate Twilio AI Voice Call</button>
                <button onclick="document.getElementById('smsForm').style.display='block'; document.getElementById('callForm').style.display='none';" class="btn btn-sm btn-secondary">💬 Dispatch Twilio SMS</button>
                <a href="email.php?client_id=<?= $client_id ?>" class="btn btn-sm btn-secondary">📧 Send Email</a>
            </div>

            <div id="callForm" style="display:none; padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color); margin-bottom:15px;">
                <form action="client_profile.php?id=<?= $client_id ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="trigger_twilio_call">
                    <div class="form-group">
                        <label class="form-label">AI Voice Call Script to Read Aloud to Client</label>
                        <textarea name="call_script" class="form-control" rows="2" required>Hello <?= e($client['first_name']) ?>. This is an automated reminder from Syed Professional Services regarding your missing monthly bank statements. Please upload them via your client portal.</textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning">Execute Twilio Call Now</button>
                </form>
            </div>

            <div id="smsForm" style="display:none; padding:12px; background:#f8fafc; border-radius:6px; border:1px solid var(--border-color); margin-bottom:15px;">
                <form action="client_profile.php?id=<?= $client_id ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="trigger_twilio_sms">
                    <div class="form-group">
                        <label class="form-label">SMS Message Body</label>
                        <input type="text" name="sms_body" class="form-control" required value="SPS Notice: Please upload your March 2026 bank statements. Reply to this SMS if you have questions.">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Send Twilio SMS Now</button>
                </form>
            </div>

            <h4 style="font-size:13px; font-weight:600; margin-bottom:10px; color:var(--secondary);">Communication Timeline Journal (<?= count($comm_list) ?>)</h4>

            <?php if (empty($comm_list)): ?>
                <p style="font-size:13px; color:var(--text-muted);">No communication history recorded.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($comm_list as $comm): ?>
                        <div style="padding:10px 12px; background:#ffffff; border:1px solid var(--border-color); border-radius:6px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:12px;">
                                <strong>
                                    <span class="badge badge-<?= $comm['channel'] === 'voice' ? 'warning' : 'info' ?>"><?= strtoupper($comm['channel']) ?></span>
                                    <?= e($comm['subject']) ?>
                                </strong>
                                <span style="color:var(--text-muted);"><?= format_datetime($comm['sent_at']) ?></span>
                            </div>
                            <div style="font-size:13px; color:var(--text-dark); margin-bottom:4px;"><?= e($comm['message_body']) ?></div>
                            <div style="font-size:11px; color:var(--text-muted);">
                                Status: <span class="badge badge-success"><?= ucfirst($comm['status']) ?></span>
                                <?php if ($comm['call_duration'] > 0): ?>
                                    • Call Duration: <strong><?= $comm['call_duration'] ?>s</strong>
                                <?php endif; ?>
                                <?php if ($comm['external_sid']): ?>
                                    • SID: <code><?= e($comm['external_sid']) ?></code>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Invoices -->
        <div class="card">
            <div class="card-title">Invoices & Receivables</div>
            <?php if (empty($invoice_list)): ?>
                <p style="font-size:13px; color:var(--text-muted);">No invoices generated.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Total</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_list as $inv): ?>
                            <tr>
                                <td><a href="invoice_view.php?id=<?= $inv['id'] ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
                                <td><?= format_currency($inv['total_amount']) ?></td>
                                <td><strong><?= format_currency($inv['balance_due']) ?></strong></td>
                                <td><span class="badge badge-<?= $inv['status'] === 'paid' ? 'success' : 'danger' ?>"><?= ucfirst($inv['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php render_footer(); ?>
