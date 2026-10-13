<?php
/**
 * SPS Professional Services CRM
 * Manage Business & Client Contacts
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_contact') {
    verify_csrf();
    $client_id = intval($_POST['client_id']);
    $business_id = !empty($_POST['business_id']) ? intval($_POST['business_id']) : null;
    $name = trim($_POST['name']);
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $position = trim($_POST['position'] ?? '');

    if (!empty($name) && $client_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO contacts (client_id, business_id, name, email, phone, position) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $business_id, $name, $email, $phone, $position]);
        set_flash('success', "Contact '$name' added successfully.");
        redirect("contacts.php");
    }
}

$contacts = $pdo->query("
    SELECT ct.*, c.first_name as client_fn, c.last_name as client_ln, c.company_name as client_company, b.business_name
    FROM contacts ct
    JOIN clients c ON ct.client_id = c.id
    LEFT JOIN businesses b ON ct.business_id = b.id
    ORDER BY ct.created_at DESC
")->fetchAll();

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();
$businesses = $pdo->query("SELECT id, business_name, client_id FROM businesses ORDER BY business_name")->fetchAll();

render_header('Business Contacts', 'contacts');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Contacts Directory</h1>
        <p class="page-subtitle">Key stakeholder contacts associated with client businesses</p>
    </div>
    <div>
        <button onclick="document.getElementById('addContactModal').style.display = 'block'" class="btn btn-primary">+ Add Contact</button>
    </div>
</div>

<div id="addContactModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:80px auto;">
        <div class="card-title">
            <span>Add Contact Person</span>
            <button onclick="document.getElementById('addContactModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="contacts.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_contact">

            <div class="form-group">
                <label class="form-label">Client Owner *</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Business (Optional)</label>
                <select name="business_id" class="form-control">
                    <option value="">-- Direct Client Contact --</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['business_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Contact Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="Jane Doe">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Position / Role</label>
                <input type="text" name="position" class="form-control" placeholder="CFO, Accountant, Manager">
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addContactModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Contact</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Contact Name</th>
                    <th>Position</th>
                    <th>Client / Business</th>
                    <th>Email</th>
                    <th>Phone</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contacts)): ?>
                    <tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No contacts recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($contacts as $ct): ?>
                        <tr>
                            <td><strong><?= e($ct['name']) ?></strong></td>
                            <td><?= e($ct['position'] ?: 'Staff') ?></td>
                            <td>
                                <?= e($ct['client_company'] ?: "{$ct['client_fn']} {$ct['client_ln']}") ?>
                                <?php if ($ct['business_name']): ?>
                                    <br><span style="font-size:12px; color:var(--text-muted);">(<?= e($ct['business_name']) ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($ct['email'] ?: '-') ?></td>
                            <td><?= e($ct['phone'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
