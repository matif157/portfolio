<?php
/**
 * GRAVITY CRM — System Permissions Registry
 * 
 * Yahan tamam security privilege tokens registered hain jo modules, actions, aur AI tools par
 * access control enforce karte hain. Super Administrator yahan naye permission tokens register kar sakta hai.
 */

require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_permission') {
        $code = preg_replace('/[^a-z0-9_.]/', '', strtolower(trim($_POST['code'] ?? '')));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $description = trim($_POST['description'] ?? '');

        if (!empty($code) && !empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO permissions (code, name, category, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $name, $category, $description]);
            $new_id = $pdo->lastInsertId();

            // Auto-grant new token to Super Administrator (Role #1)
            $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (1, ?)")->execute([$new_id]);

            log_activity('Create Permission Token', 'permissions', $new_id, "Registered permission token '$code' ($name)", 'stage2_sensitive');
            set_flash('success', "Permission token '$code' created and assigned to Administrator!");
        }
        redirect('permissions.php');
    }
}

$permissions = $pdo->query("
    SELECT p.*, (SELECT COUNT(*) FROM role_permissions WHERE permission_id = p.id) as assigned_roles_count 
    FROM permissions p 
    ORDER BY p.category, p.code ASC
")->fetchAll();

render_header('System Permissions Directory', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🔑 System Permissions Directory</h1>
        <p class="page-subtitle">Security privilege tokens mapped to CRM modules, features, and AI access rules</p>
    </div>
    <div>
        <button onclick="document.getElementById('addPermModal').style.display='flex'" class="btn btn-primary">+ Register Permission Token</button>
        <a href="roles.php" class="btn btn-secondary">← Access Roles Matrix</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Permission Token</th>
                    <th>Display Name</th>
                    <th>Module Category</th>
                    <th>Description</th>
                    <th>Assigned Roles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions as $p): ?>
                    <tr>
                        <td><code><?= e($p['code']) ?></code></td>
                        <td><strong><?= e($p['name']) ?></strong></td>
                        <td><span class="badge badge-info"><?= e($p['category']) ?></span></td>
                        <td><?= e($p['description']) ?></td>
                        <td><span class="badge badge-secondary"><?= $p['assigned_roles_count'] ?> Roles</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Permission Modal -->
<div class="modal-overlay" id="addPermModal" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <span style="font-weight:700; color:#1e293b;">Register New Permission Token</span>
            <button onclick="document.getElementById('addPermModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
        </div>
        <form action="permissions.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_permission">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Permission Code (e.g. tax.efile or audit.view) *</label>
                    <input type="text" name="code" class="form-control" required placeholder="module.action">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Display Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="E-File Tax Returns">
                    </div>
                    <div class="form-col form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="Clients">Clients</option>
                            <option value="Services">Services</option>
                            <option value="Operations">Operations</option>
                            <option value="Finance">Finance</option>
                            <option value="Documents">Documents</option>
                            <option value="Communication">Communication</option>
                            <option value="AI">AI Assistant</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description &amp; Scope</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Explains what actions this token authorizes..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="document.getElementById('addPermModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Token</button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>
