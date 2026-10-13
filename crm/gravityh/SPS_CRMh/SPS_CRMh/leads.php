<?php
/**
 * SPS Professional Services CRM
 * Automated Lead Intelligence & Auto-Conversion Engine
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf();
    
    if ($_POST['action'] === 'add_lead') {
        $title = trim($_POST['title']);
        $contact_name = trim($_POST['contact_name']);
        $company_name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $estimated_value = floatval($_POST['estimated_value'] ?? 450.00);
        $service_interest = $_POST['service_interest'] ?? 'Accounting & Tax Advisory';
        $status = $_POST['status'] ?? 'new';

        // Auto calculate Lead Score based on completeness & estimated value
        $score = 50;
        if (!empty($email)) $score += 15;
        if (!empty($phone)) $score += 15;
        if ($estimated_value >= 1000) $score += 20;

        if (!empty($title) && !empty($contact_name)) {
            $stmt = $pdo->prepare("INSERT INTO leads (title, contact_name, company_name, email, phone, estimated_value, service_interest, score, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $contact_name, $company_name, $email, $phone, $estimated_value, $service_interest, $score, $status]);
            $lead_id = $pdo->lastInsertId();

            // Auto Nurture Dispatch if Score >= 70
            if ($score >= 70 && !empty($email)) {
                $welcome_msg = "Hello $contact_name, thank you for contacting Syed Professional Services regarding $service_interest. We have received your request and an accountant will be assigned shortly.";
                $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status) VALUES (0, 'email', 'Welcome to Syed Professional Services', ?, 'outbound', 'sent')")
                    ->execute([$welcome_msg]);
            }

            log_activity('Add Lead', 'leads', $lead_id, "Captured lead $title with AI Score: $score/100");
            set_flash('success', "Lead '$title' captured successfully (AI Lead Score: $score/100)!");
            redirect('leads.php');
        }
    } elseif ($_POST['action'] === 'auto_convert_lead') {
        // AUTOMATED LEAD CONVERSION ENGINE
        $lead_id = intval($_POST['lead_id']);
        $stmtL = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmtL->execute([$lead_id]);
        $lead = $stmtL->fetch();
        
        if ($lead) {
            // 1. Auto Create Client
            $next_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM clients")->fetchColumn();
            $client_number = 'CLI-' . (1000 + $next_id);
            $names = explode(' ', $lead['contact_name'], 2);
            $fn = $names[0];
            $ln = $names[1] ?? 'Client';
            $company = $lead['company_name'] ?: "$fn $ln Services";

            $stmtC = $pdo->prepare("INSERT INTO clients (client_number, client_type, first_name, last_name, company_name, email, phone, status) VALUES (?, 'company', ?, ?, ?, ?, ?, 'active')");
            $stmtC->execute([$client_number, $fn, $ln, $company, $lead['email'], $lead['phone']]);
            $client_id = $pdo->lastInsertId();

            // 2. Auto Create Business Entity
            $stmtB = $pdo->prepare("INSERT INTO businesses (client_id, business_name, entity_type, status) VALUES (?, ?, 'LLC', 'active')");
            $stmtB->execute([$client_id, $company]);
            $business_id = $pdo->lastInsertId();

            // 3. Auto Create Bank Account for Statement Automation
            $stmtBA = $pdo->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_name, account_number_last4) VALUES (?, 'Chase Bank', 'Operating Checking', '4829')");
            $stmtBA->execute([$business_id]);
            $account_id = $pdo->lastInsertId();

            // Auto initialize 2026 statement checklist
            $stmtM = $pdo->prepare("INSERT IGNORE INTO bank_statements (bank_account_id, period_year, period_month, status) VALUES (?, 2026, ?, 'missing')");
            for ($m = 1; $m <= 12; $m++) {
                $stmtM->execute([$account_id, $m]);
            }

            // 4. Auto Create Project & Service Workflow Execution
            $next_p = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM projects")->fetchColumn();
            $project_code = 'PRJ-' . date('Y') . '-' . str_pad($next_p, 3, '0', STR_PAD_LEFT);
            $prj_title = "2026 Bookkeeping & Tax - $company";

            $stmtP = $pdo->prepare("INSERT INTO projects (project_code, client_id, business_id, service_id, title, status, start_date, due_date, total_amount, is_recurring) VALUES (?, ?, ?, 1, ?, 'open', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, 1)");
            $stmtP->execute([$project_code, $client_id, $business_id, $prj_title, $lead['estimated_value'] ?: 5400.00]);
            $project_id = $pdo->lastInsertId();

            $stmtCS = $pdo->prepare("INSERT INTO client_services (client_id, business_id, service_id, status, current_step_number, start_date) VALUES (?, ?, 1, 'in_progress', 1, CURDATE())");
            $stmtCS->execute([$client_id, $business_id]);

            // 5. Update Lead Status & Dispatch Welcome Notification
            $pdo->prepare("UPDATE leads SET status = 'won' WHERE id = ?")->execute([$lead_id]);

            $nurture_sms = "Welcome to Syed Professional Services! Your client profile $client_number and Project $project_code have been initialized.";
            send_twilio_sms($client_id, $nurture_sms);

            log_activity('Lead Auto-Conversion Engine', 'leads', $lead_id, "AUTOMATED CONVERSION: Lead {$lead['title']} → Created Client $client_number + Business $company + Project $project_code + Bank Statement Checklist.");
            set_flash('success', "Lead Successfully Auto-Converted! Created Client $client_number, Business Entity, Project $project_code, and initialized Bank Statement Checklist!");
            redirect("client_profile.php?id=$client_id");
        }
    }
}

$leads = $pdo->query("SELECT * FROM leads ORDER BY score DESC, created_at DESC")->fetchAll();

render_header('Leads & Auto-Nurture Engine', 'leads');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Automated Lead Intelligence & Pipeline</h1>
        <p class="page-subtitle">AI lead qualification scoring, automated welcome outreach, and 1-click Auto-Conversion Engine</p>
    </div>
    <div>
        <button onclick="document.getElementById('addLeadModal').style.display = 'block'" class="btn btn-primary">+ Add New Lead</button>
    </div>
</div>

<div id="addLeadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:550px; margin:70px auto;">
        <div class="card-title">
            <span>New Lead Opportunity</span>
            <button onclick="document.getElementById('addLeadModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="leads.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_lead">

            <div class="form-group">
                <label class="form-label">Opportunity Title *</label>
                <input type="text" name="title" class="form-control" placeholder="Annual Corporate Tax & Bookkeeping" required>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Contact Name *</label>
                    <input type="text" name="contact_name" class="form-control" required>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="+15550000000">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Service of Interest</label>
                    <select name="service_interest" class="form-control">
                        <option value="Bank Statement Collection & Monthly Bookkeeping">Monthly Bookkeeping & Statements</option>
                        <option value="Corporate Tax Return Form 1120/1120S">Corporate Tax Form 1120/1120S</option>
                        <option value="Individual Tax Return Form 1040">Individual Tax Form 1040</option>
                        <option value="LLC Business Formation">LLC / Inc Business Formation</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Est. Value ($)</label>
                    <input type="number" step="0.01" name="estimated_value" class="form-control" value="5400.00">
                </div>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addLeadModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Capture Lead</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>AI Score</th>
                    <th>Opportunity / Title</th>
                    <th>Contact & Entity</th>
                    <th>Service Interest</th>
                    <th>Est. Value</th>
                    <th>Stage</th>
                    <th>Automated Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No leads recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($leads as $l): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?= $l['score'] >= 75 ? 'success' : ($l['score'] >= 50 ? 'info' : 'warning') ?>" style="font-size:12px; font-weight:700;">
                                    <?= $l['score'] ?>/100
                                </span>
                            </td>
                            <td><strong><?= e($l['title']) ?></strong></td>
                            <td>
                                <div><?= e($l['contact_name']) ?></div>
                                <div style="font-size:12px; color:var(--text-muted);"><?= e($l['company_name'] ?: $l['email']) ?></div>
                            </td>
                            <td><?= e($l['service_interest']) ?></td>
                            <td><strong><?= format_currency($l['estimated_value']) ?></strong></td>
                            <td><span class="badge badge-info"><?= ucfirst($l['status']) ?></span></td>
                            <td>
                                <?php if ($l['status'] !== 'won'): ?>
                                    <form action="leads.php" method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="auto_convert_lead">
                                        <input type="hidden" name="lead_id" value="<?= $l['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">⚡ Auto-Convert to Client & Project</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-success">✓ Auto-Converted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
