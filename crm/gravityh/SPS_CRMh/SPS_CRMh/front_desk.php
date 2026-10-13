<?php
/**
 * GRAVITY CRM — Front Desk / Client Intake Module
 *
 * Yeh module front-desk/onboarding-style client intake karta hai. Yeh quick lead create karta hai —
 * client/company info, service interest notes, phir lead pipeline me move hota hai.
 */
require_once __DIR__ . '/auth.php';
require_login();
if (!user_can('clients.create') && !user_can('leads.manage')) {
    require_permission('clients.create');
}

$services = $pdo->query("SELECT id, service_name, description FROM service_categories ORDER BY id ASC")->fetchAll();

// POST: create lead / client
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'add_lead';

    if ($action === 'add_lead') {
        $contact_name = trim($_POST['contact_name'] ?? '');
        $company      = trim($_POST['company'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
        $service_id    = intval($_POST['service_id'] ?? 0);
        $source        = trim($_POST['source'] ?? 'Front Desk');

        if ($contact_name !== '' && $phone !== '') {
            // Leads me create hota hai — front-desk intake
            $stmt = $pdo->prepare("INSERT INTO leads (title, contact_name, company_name, email, phone, service_interest, source, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
            $stmt->execute([
                $contact_name . ' (Front Desk)',
                $contact_name,
                $company,
                $email,
                $phone,
                $service_id > 0 ? $service_id : null,
                $source,
            ]);
            $lead_id = $pdo->lastInsertId();
            log_activity('Front Desk Lead Created', 'leads', $lead_id, "Front desk intake: {$contact_name} ({$company}) — service #{$service_id}", 'stage1_normal');
            set_flash('success', "Intake complete — Lead #{$lead_id} created.");
            redirect('leads.php');
        }
    }
}

// Existing leads recently added via front desk
$recent_front_leads = $pdo->query("
    SELECT l.*, sc.service_name
    FROM leads l
    LEFT JOIN service_categories sc ON l.service_id = sc.id
    ORDER BY l.id DESC
    LIMIT 10
")->fetchAll();

render_header('Front Desk — Client Intake', 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🛎️ Front Desk &amp; Client Intake</h1>
        <p class="page-subtitle">Walk-in ya phone-in client intake — a lead/contact ban jata hai aur service interest attach hota hai</p>
    </div>
</div>

<?php if (!empty($recent_front_leads)): ?>
    <div class="card">
        <div class="card-title">🕐 Recent Front-Desk Intakes</div>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>ID</th><th>Contact Name</th><th>Company</th><th>Phone</th><th>Service</th><th>Source</th><th>When</th></tr></thead>
                <tbody>
                <?php foreach ($recent_front_leads as $rf): ?>
                    <tr>
                        <td><a href="leads.php">#<?= $rf['id'] ?></a></td>
                        <td><strong><?= e($rf['contact_name']) ?></strong></td>
                        <td><?= e($rf['company_name']) ?></td>
                        <td><?= e($rf['phone']) ?></td>
                        <td><span class="badge badge-info"><?= e($rf['service_name'] ?: '—') ?></span></td>
                        <td><span class="badge badge-secondary"><?= e($rf['source']) ?></span></td>
                        <td><?= format_datetime($rf['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-title">➕ Add a New Lead / Client Intake</div>
    <form method="POST" action="front_desk.php" class="form-row" style="gap:12px; align-items:flex-end;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_lead">
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
        <div class="form-col form-group">
            <label class="form-label">Phone *</label>
            <input type="text" name="phone" class="form-control" placeholder="+1 212 555 0134" required>
        </div>
        <div class="form-col form-group">
            <label class="form-label">Service / Interest</label>
            <select name="service_id" class="form-control">
                <option value="0">— (none — just lead) —</option>
                <?php foreach ($services as $svc): ?>
                    <option value="<?= $svc['id'] ?>"><?= e($svc['service_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-col form-group">
            <label class="form-label">Source</label>
            <input type="text" name="source" class="form-control" value="Front Desk" placeholder="Website, Referral, Walk-in...">
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Create Lead / Client</button>
        </div>
    </form>
</div>

<?php render_footer(); ?>