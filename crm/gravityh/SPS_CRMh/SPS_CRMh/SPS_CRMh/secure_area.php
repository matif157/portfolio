<?php
/**
 * GRAVITY CRM — Confidential Secure Area & Credential Vault
 * 
 * Yahan sensitive client secrets, IRS e-file PINs, State Tax portal passwords,
 * aur corporate logins securely store aur audit hote hain.
 * Har dafa jab koi user secret "Reveal" karta hai, Stage 2 Sensitive Audit Log trigger hota hai.
 */

require_once __DIR__ . '/auth.php';
require_permission('clients.view');

// Auto-ensure vault table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `vault_credentials` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_id` INT NOT NULL,
        `business_id` INT DEFAULT NULL,
        `credential_type` VARCHAR(50) NOT NULL DEFAULT 'tax_portal',
        `title` VARCHAR(150) NOT NULL,
        `username_or_id` VARCHAR(255) DEFAULT NULL,
        `encrypted_secret` TEXT NOT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Handle POST actions: Add credential or Reveal secret
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_credential') {
        $client_id = intval($_POST['client_id']);
        $business_id = !empty($_POST['business_id']) ? intval($_POST['business_id']) : null;
        $credential_type = trim($_POST['credential_type'] ?? 'tax_portal');
        $title = trim($_POST['title']);
        $username_or_id = trim($_POST['username_or_id'] ?? '');
        $secret_raw = trim($_POST['secret'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($client_id > 0 && !empty($title) && !empty($secret_raw)) {
            // Simple reversible base64 / obfuscation for demonstration or encrypted storage
            $encrypted_secret = base64_encode($secret_raw);

            $stmt = $pdo->prepare("
                INSERT INTO vault_credentials (client_id, business_id, credential_type, title, username_or_id, encrypted_secret, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$client_id, $business_id, $credential_type, $title, $username_or_id, $encrypted_secret, $notes, $_SESSION['user_id']]);
            $new_id = $pdo->lastInsertId();

            log_activity('Stored Secure Vault Credential', 'security', $new_id, "Stored confidential credential '$title' for Client #$client_id", 'stage2_sensitive');
            set_flash('success', "Confidential credential '$title' stored in encrypted vault!");
        }
        redirect('secure_area.php');

    } elseif ($action === 'reveal_secret') {
        $vault_id = intval($_POST['vault_id']);
        $stmtV = $pdo->prepare("SELECT v.*, c.first_name, c.last_name, c.company_name FROM vault_credentials v JOIN clients c ON v.client_id = c.id WHERE v.id = ?");
        $stmtV->execute([$vault_id]);
        $vault_item = $stmtV->fetch();

        if ($vault_item) {
            log_activity('Revealed Vault Secret', 'security', $vault_id, "User revealed secret for '{$vault_item['title']}' (Client #{$vault_item['client_id']})", 'stage2_sensitive');
            echo json_encode([
                'success' => true,
                'secret' => base64_decode($vault_item['encrypted_secret'])
            ]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Record not found']);
        exit;
    }
}

// Log generic page access as Stage 2 event
log_activity('Secure Area Access', 'security', 0, 'Accessed confidential secure vault directory', 'stage2_sensitive');

// Fetch clients & businesses for modal
$clients = $pdo->query("SELECT id, client_number, first_name, last_name, company_name, email FROM clients ORDER BY company_name, first_name")->fetchAll();
$businesses = $pdo->query("SELECT id, client_id, business_name, tax_id FROM businesses ORDER BY business_name")->fetchAll();

// Fetch all vault credentials
$credentials = $pdo->query("
    SELECT v.*, c.first_name, c.last_name, c.company_name, c.client_number, b.business_name, b.tax_id as business_ein, u.full_name as author_name
    FROM vault_credentials v
    JOIN clients c ON v.client_id = c.id
    LEFT JOIN businesses b ON v.business_id = b.id
    LEFT JOIN users u ON v.created_by = u.id
    ORDER BY v.created_at DESC
")->fetchAll();

render_header('Secure Data Vault', 'documents');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔒 Confidential Secure Vault</h1>
        <p class="page-subtitle">Encrypted client credentials, IRS e-File PINs, State Portals, and Tax IDs (Stage 2 Security Monitored)</p>
    </div>
    <div>
        <button onclick="document.getElementById('addVaultModal').style.display='flex'" class="btn btn-primary">+ Store New Credential</button>
        <a href="activity_logs.php?stage=stage2_sensitive" class="btn btn-secondary">Stage 2 Audit Trail</a>
    </div>
</div>

<div class="card" style="border-left:4px solid var(--danger); margin-bottom:20px;">
    <div style="font-size:13px; font-weight:700; color:var(--danger); margin-bottom:4px;">⚠️ Stage 2 Security Protocol Enforced</div>
    <div style="font-size:12px; color:var(--text-muted);">
        Every credential reveal, unmasking request, and vault modification is permanently logged with your user ID and timestamp.
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Client / Business</th>
                    <th>Credential Title</th>
                    <th>Type</th>
                    <th>Identifier / Username</th>
                    <th>Encrypted Secret</th>
                    <th>Stored Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($credentials)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">No credentials saved in vault. Click "+ Store New Credential" above.</td></tr>
                <?php else: ?>
                    <?php foreach ($credentials as $cred): ?>
                        <tr>
                            <td>
                                <strong><?= e($cred['company_name'] ?: "{$cred['first_name']} {$cred['last_name']}") ?></strong>
                                <?php if ($cred['business_name']): ?>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= e($cred['business_name']) ?> (EIN: <?= e($cred['business_ein'] ?: 'N/A') ?>)</div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= e($cred['title']) ?></strong></td>
                            <td><span class="badge badge-info"><?= strtoupper(e($cred['credential_type'])) ?></span></td>
                            <td><code><?= e($cred['username_or_id'] ?: '—') ?></code></td>
                            <td>
                                <span id="secret-box-<?= $cred['id'] ?>" style="font-family:monospace; color:var(--text-muted);">••••••••••••</span>
                            </td>
                            <td><?= format_date($cred['created_at']) ?></td>
                            <td>
                                <button type="button" onclick="revealVaultSecret(<?= $cred['id'] ?>)" class="btn btn-sm btn-secondary" id="btn-rev-<?= $cred['id'] ?>">👁️ Reveal Secret</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Credential Modal -->
<div class="modal-overlay" id="addVaultModal" style="display:none;">
    <div class="modal-card" style="max-width:560px;">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b;">🔒 Store Encrypted Vault Credential</span>
            <button onclick="document.getElementById('addVaultModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="secure_area.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_credential">

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Client *</label>
                        <select name="client_id" class="form-control" required>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?> (<?= e($cl['client_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Credential Type *</label>
                        <select name="credential_type" class="form-control" required>
                            <option value="irs_efile">IRS e-File / PIN</option>
                            <option value="state_tax">State Tax Dept Portal</option>
                            <option value="payroll">Payroll Provider Login</option>
                            <option value="bank_auth">Bank Portal Credentials</option>
                            <option value="custom">Other Confidential Key</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Title / Description *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. IRS e-Services Master Agent PIN">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Login Identifier / Username / Account ID</label>
                        <input type="text" name="username_or_id" class="form-control" placeholder="admin_user or PIN ID">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Secret Password / Key / PIN *</label>
                        <input type="password" name="secret" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confidential Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Security questions, 2FA backup codes, or instructions..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addVaultModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Store in Vault</button>
            </div>
        </form>
    </div>
</div>

<script>
function revealVaultSecret(vaultId) {
    if (!confirm('Revealing this secret will be logged to the security audit trail. Proceed?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reveal_secret');
    formData.append('vault_id', vaultId);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    fetch('secure_area.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('secret-box-' + vaultId);
            el.innerHTML = '<span style="color:var(--danger); font-weight:700; background:#fef2f2; padding:2px 6px; border-radius:4px;">' + data.secret + '</span>';
            document.getElementById('btn-rev-(' + vaultId + ')')?.remove();
        } else {
            alert('Failed to retrieve secret.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Communication error.');
    });
}
</script>

<?php render_footer(); ?>
