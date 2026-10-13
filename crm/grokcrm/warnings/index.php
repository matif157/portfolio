<?php
require_once __DIR__ . '/../includes/log_in.php';
$pageTitle = 'Client Warnings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("INSERT INTO client_warnings (member_id, warning, created_by, is_active) VALUES (?,?,?,1)")
        ->execute([
            (int)($_POST['member_id'] ?? 0),
            trim($_POST['warning'] ?? ''),
            $_SESSION['user_id']
        ]);
    log_activity('create_warning', 'Client', 'Added warning for client ID ' . ($_POST['member_id'] ?? ''));
    set_flash('Warning added.');
    header('Location: index.php');
    exit;
}

if (isset($_GET['deactivate'])) {
    $pdo->prepare("UPDATE client_warnings SET is_active=0 WHERE id=?")->execute([(int)$_GET['deactivate']]);
    set_flash('Warning deactivated.');
    header('Location: index.php');
    exit;
}

$warnings = $pdo->query("SELECT w.*, m.member_name, m.bus_name, u.full_name as created_by_name 
                         FROM client_warnings w 
                         LEFT JOIN members m ON w.member_id = m.id 
                         LEFT JOIN users u ON w.created_by = u.id 
                         ORDER BY w.is_active DESC, w.created_at DESC")->fetchAll();
$members = $pdo->query("SELECT id, member_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Warning</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-dark">
                <tr><th>Client</th><th>Warning</th><th>Created By</th><th>Date</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($warnings)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No warnings</td></tr>
                <?php else: foreach ($warnings as $w): ?>
                    <tr class="<?= !$w['is_active']?'table-secondary':'' ?>">
                        <td>
                            <a href="../clients/view.php?id=<?= $w['member_id'] ?>"><?= e($w['member_name'] ?? '') ?></a>
                            <?php if ($w['bus_name']): ?><br><small class="text-muted"><?= e($w['bus_name']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($w['warning']) ?></td>
                        <td class="small"><?= e($w['created_by_name'] ?? '-') ?></td>
                        <td class="small"><?= date('d M Y', strtotime($w['created_at'])) ?></td>
                        <td><?= $w['is_active'] ? '<span class="badge bg-warning text-dark">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                        <td>
                            <?php if ($w['is_active']): ?>
                            <a href="?deactivate=<?= $w['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Deactivate?')">Deactivate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Add Client Warning</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Client *</label>
                    <select name="member_id" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['member_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warning *</label>
                    <textarea name="warning" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning">Save Warning</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
