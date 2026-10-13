<?php
/**
 * GRAVITY CRM — Activity, 3-Stage Audit Trail & Data Restoration Engine
 * 
 * Yahan pore CRM ki activity tracking hoti hai:
 * Stage 1: Normal operational actions (login, create, view)
 * Stage 2: Sensitive vault / billing / security actions
 * Stage 3: Field-level old vs new diff changes
 * Plus: Soft-deleted records ka restore mechanism jo deleted data ko instantly wapas database me restore kar deta hai.
 */

require_once __DIR__ . '/auth.php';
require_permission('admin.settings');

// Handle Data Restoration Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_record') {
    verify_csrf();
    $activity_id = intval($_POST['activity_id']);

    // Fetch activity entry with restore payload
    $stmtAct = $pdo->prepare("SELECT * FROM activities WHERE id = ? AND is_deleted_record = 1 AND is_restored = 0");
    $stmtAct->execute([$activity_id]);
    $act = $stmtAct->fetch();

    if ($act && !empty($act['restore_payload'])) {
        $payload = json_decode($act['restore_payload'], true);
        $module = $act['module'];
        $restored = false;

        // Restore according to module
        if ($module === 'templates' && isset($payload['title'])) {
            $stmt = $pdo->prepare("
                INSERT INTO user_templates (id, user_id, title, template_type, content, is_shared, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()
            ");
            $stmt->execute([
                $payload['id'] ?? null,
                $payload['user_id'] ?? current_user()['id'],
                $payload['title'],
                $payload['template_type'] ?? 'notepad',
                $payload['content'] ?? '',
                $payload['is_shared'] ?? 0,
                $payload['created_at'] ?? date('Y-m-d H:i:s')
            ]);
            $restored = true;

        } elseif ($module === 'customizer' && isset($payload['field_name'])) {
            $stmt = $pdo->prepare("
                INSERT INTO custom_fields (entity_type, field_name, field_label, field_type, is_required, is_visible, dropdown_options, default_value, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $payload['entity_type'] ?? 'client',
                $payload['field_name'],
                $payload['field_label'],
                $payload['field_type'] ?? 'text',
                $payload['is_required'] ?? 0,
                $payload['is_visible'] ?? 1,
                $payload['dropdown_options'] ?? null,
                $payload['default_value'] ?? null,
                $payload['sort_order'] ?? 99
            ]);
            $restored = true;
        }

        if ($restored) {
            $pdo->prepare("UPDATE activities SET is_restored = 1 WHERE id = ?")->execute([$activity_id]);
            log_activity('Restore Data Record', $module, $activity_id, "Restored deleted record from activity #$activity_id ({$act['description']})", 'stage2_sensitive');
            set_flash('success', "Record restored successfully into $module module!");
        } else {
            set_flash('warning', 'Unable to auto-restore this record type. Payload preserved in audit logs.');
        }
    }
    redirect('activity_logs.php?stage=deleted_records');
}

$stage_filter = trim($_GET['stage'] ?? '');
$module_filter = trim($_GET['module'] ?? '');

$query = "
    SELECT a.*, u.full_name, u.username
    FROM activities a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($stage_filter === 'deleted_records') {
    $query .= " AND a.is_deleted_record = 1";
} elseif ($stage_filter !== '') {
    $query .= " AND a.stage = ?";
    $params[] = $stage_filter;
}

if ($module_filter !== '') {
    $query .= " AND a.module = ?";
    $params[] = $module_filter;
}
$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Pre-fetch activity changes for Stage 3 logs
$changes_by_act = [];
$stage3_ids = array_column(array_filter($activities, fn($a) => $a['stage'] === 'stage3_datachange'), 'id');
if (!empty($stage3_ids)) {
    $in_clause = implode(',', array_fill(0, count($stage3_ids), '?'));
    $stmtC = $pdo->prepare("SELECT * FROM activity_changes WHERE activity_id IN ($in_clause)");
    $stmtC->execute($stage3_ids);
    foreach ($stmtC->fetchAll() as $chg) {
        $changes_by_act[$chg['activity_id']][] = $chg;
    }
}

render_header('Activity & Audit Trail', 'admin');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">📜 Audit Trail &amp; Data Recovery Engine</h1>
        <p class="page-subtitle">3-Stage audit logging: Normal Actions, Sensitive Vault Access, Field-Level Diffs, and One-Click Deleted Record Restoration</p>
    </div>
</div>

<!-- Stage Selector Filters -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="activity_logs.php" class="btn btn-sm <?= empty($stage_filter) ? 'btn-primary' : 'btn-secondary' ?>">All Audit Logs</a>
        <a href="activity_logs.php?stage=stage1_normal" class="btn btn-sm <?= $stage_filter === 'stage1_normal' ? 'btn-primary' : 'btn-secondary' ?>">Stage 1: Normal Actions</a>
        <a href="activity_logs.php?stage=stage2_sensitive" class="btn btn-sm <?= $stage_filter === 'stage2_sensitive' ? 'btn-primary' : 'btn-secondary' ?>">Stage 2: Sensitive Access</a>
        <a href="activity_logs.php?stage=stage3_datachange" class="btn btn-sm <?= $stage_filter === 'stage3_datachange' ? 'btn-primary' : 'btn-secondary' ?>">Stage 3: Data Changes</a>
        <a href="activity_logs.php?stage=deleted_records" class="btn btn-sm <?= $stage_filter === 'deleted_records' ? 'btn-primary' : 'btn-secondary' ?>" style="color:var(--danger); font-weight:600;">🗑️ Recoverable Deleted Records</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Audit Stage</th>
                    <th>Description &amp; Field Diffs</th>
                    <th>Recovery</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($activities)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:30px;">No activity logs recorded for selected filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <tr>
                            <td><span style="font-size:12px; color:var(--text-muted);"><?= format_datetime($act['created_at']) ?></span></td>
                            <td><strong><?= e($act['full_name'] ?: 'System / Automated') ?></strong></td>
                            <td><strong><?= e($act['action']) ?></strong></td>
                            <td><span class="badge badge-info"><?= e($act['module']) ?></span></td>
                            <td>
                                <?php
                                $sBadge = 'badge-secondary';
                                if ($act['stage'] === 'stage2_sensitive') $sBadge = 'badge-warning';
                                if ($act['stage'] === 'stage3_datachange') $sBadge = 'badge-danger';
                                if ($act['is_deleted_record']) $sBadge = 'badge-danger';
                                ?>
                                <span class="badge <?= $sBadge ?>">
                                    <?= $act['is_deleted_record'] ? 'Deleted Record' : ucfirst(str_replace('_', ' ', $act['stage'])) ?>
                                </span>
                            </td>
                            <td>
                                <div><?= e($act['description']) ?></div>
                                <?php if (!empty($changes_by_act[$act['id']])): ?>
                                    <div style="margin-top:6px; padding:6px; background:#fff0f0; border-radius:4px; font-size:11px;">
                                        <strong>Field Changes:</strong>
                                        <?php foreach ($changes_by_act[$act['id']] as $chg): ?>
                                            <div>• <code><?= e($chg['field_name']) ?></code>: "<?= e($chg['old_value']) ?>" → "<?= e($chg['new_value']) ?>"</div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($act['is_deleted_record']): ?>
                                    <?php if ($act['is_restored']): ?>
                                        <span class="badge badge-success">✓ Restored</span>
                                    <?php else: ?>
                                        <form action="activity_logs.php" method="POST" style="display:inline;" onsubmit="return confirm('Restore this deleted record back to CRM?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="restore_record">
                                            <input type="hidden" name="activity_id" value="<?= $act['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success">♻️ Restore</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:11px;">—</span>
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
