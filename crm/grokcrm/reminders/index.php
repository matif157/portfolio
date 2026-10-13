<?php
require_once __DIR__ . '/../includes/log_in.php';
$pageTitle = 'Reminders';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("INSERT INTO reminders (client_id, reminder_title, reminder_description, reminder_date, reminder_type, status, created_by) 
                   VALUES (?,?,?,?,?,'pending',?)")
        ->execute([
            (int)($_POST['client_id'] ?? 0) ?: null,
            trim($_POST['reminder_title'] ?? ''),
            trim($_POST['reminder_description'] ?? ''),
            $_POST['reminder_date'] ?? null,
            trim($_POST['reminder_type'] ?? 'general'),
            $_SESSION['user_id']
        ]);
    log_activity('create_reminder', 'Reminders', 'Created reminder: ' . ($_POST['reminder_title'] ?? ''));
    set_flash('Reminder added.');
    header('Location: index.php');
    exit;
}

if (isset($_GET['done'])) {
    $pdo->prepare("UPDATE reminders SET status='done' WHERE id=?")->execute([(int)$_GET['done']]);
    set_flash('Marked as done.');
    header('Location: index.php');
    exit;
}

$reminders = $pdo->query("SELECT r.*, m.member_name FROM reminders r LEFT JOIN members m ON r.client_id = m.id ORDER BY r.reminder_date ASC, r.id DESC")->fetchAll();
$members = $pdo->query("SELECT id, member_name FROM members WHERE is_active=1 ORDER BY member_name")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Reminder</button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-dark">
                <tr><th>Date</th><th>Title</th><th>Client</th><th>Type</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (empty($reminders)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No reminders</td></tr>
                <?php else: foreach ($reminders as $r): ?>
                    <tr class="<?= $r['status']==='done'?'table-secondary':'' ?>">
                        <td class="small"><?= $r['reminder_date'] ? date('d M Y H:i', strtotime($r['reminder_date'])) : '-' ?></td>
                        <td>
                            <strong><?= e($r['reminder_title']) ?></strong>
                            <?php if ($r['reminder_description']): ?><br><small class="text-muted"><?= e($r['reminder_description']) ?></small><?php endif; ?>
                        </td>
                        <td><?= e($r['member_name'] ?? '-') ?></td>
                        <td><span class="badge bg-info text-dark"><?= e($r['reminder_type'] ?? '') ?></span></td>
                        <td><?= $r['status']==='done' ? '<span class="badge bg-success">Done</span>' : '<span class="badge bg-warning text-dark">Pending</span>' ?></td>
                        <td>
                            <?php if ($r['status']!=='done'): ?>
                            <a href="?done=<?= $r['id'] ?>" class="btn btn-sm btn-outline-success">Done</a>
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
            <div class="modal-header"><h5 class="modal-title">Add Reminder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="reminder_title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="reminder_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= e($m['member_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Date / Time</label>
                        <input type="datetime-local" name="reminder_date" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Type</label>
                        <select name="reminder_type" class="form-select">
                            <option value="general">General</option>
                            <option value="followup">Follow-up</option>
                            <option value="payment">Payment</option>
                            <option value="tax">Tax</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
