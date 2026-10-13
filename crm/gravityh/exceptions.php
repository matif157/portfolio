<?php
/**
 * GRAVITY CRM - Exception Center (SPS 2040 Phase 5, spec Sec 37)
 * Lists auto-opened + manual exceptions; supports assignment,
 * investigation states and resolution.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('exceptions', 'view');

$user = current_user();
$page_title = 'Exceptions';
$active_page = 'exceptions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_permission('exceptions', 'manage');
    $action = post('action');
    $exc_id = (int)post('exception_id', 0);

    $existing = db_row('SELECT * FROM exceptions WHERE id = ?', [$exc_id]);
    if (!$existing) {
        flash('error', 'Exception not found.');
        redirect('exceptions.php');
    }

    if ($action === 'update') {
        $status = sanitize(post('status', $existing['status']));
        $assigned = (int)post('assigned_user_id', 0);
        $note = sanitize(post('resolution_note'));

        $allowed = ['open','assigned','investigating','waiting','resolved','closed'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Invalid status.');
            redirect('exceptions.php');
        }

        db_execute(
            "UPDATE exceptions SET status = ?, assigned_user_id = ?, resolution_note = ?,
                resolved_by = CASE WHEN ? IN ('resolved','closed') THEN ? ELSE resolved_by END,
                resolved_at = CASE WHEN ? IN ('resolved','closed') THEN NOW() ELSE resolved_at END
             WHERE id = ?",
            [$status, $assigned > 0 ? $assigned : null, $note !== '' ? $note : null,
             $status, $user['id'], $status, $exc_id]
        );
        log_activity('exceptions', 'update', $exc_id, "Exception #{$exc_id} -> {$status}");
        flash('success', 'Exception updated.');
    }

    redirect('exceptions.php' . (!empty(post('back_status')) ? '?status=' . urlencode((string)post('back_status')) : ''));
}

$f_status = sanitize(get('status', ''));
$f_cat = sanitize(get('category', ''));

$where = '1=1';
$params = [];
if ($f_status !== '') { $where .= ' AND e.status = ?'; $params[] = $f_status; }
if ($f_cat !== '')    { $where .= ' AND e.category = ?'; $params[] = $f_cat; }

$exceptions = db_query(
    "SELECT e.*, c.first_name AS c_first, c.last_name AS c_last,
            u.first_name AS a_first, u.last_name AS a_last
     FROM exceptions e
     LEFT JOIN clients c ON c.id = e.client_id
     LEFT JOIN users u ON u.id = e.assigned_user_id
     WHERE $where
     ORDER BY FIELD(e.severity,'critical','high','medium','low'), e.created_at DESC
     LIMIT 200",
    $params
);

$stats = [];
foreach (db_query('SELECT status, COUNT(*) n FROM exceptions GROUP BY status') as $r) {
    $stats[$r['status']] = (int)$r['n'];
}
$total_open = $stats['open'] ?? 0;

function exc_sev_badge(string $sev): string {
    $colors = ['critical'=>'#7f1d1d','high'=>'#c2410c','medium'=>'#a16207','low'=>'#4b5563'];
    $bg = $colors[$sev] ?? '#4b5563';
    return '<span style="background:' . $bg . ';color:#fff;padding:2px 8px;border-radius:10px;font-size:11px;text-transform:uppercase;">' . e($sev) . '</span>';
}

include __DIR__ . '/header.php';
?>
<div class="page-title">
    <h1><i class="bi bi-exclamation-triangle"></i> Exceptions</h1>
</div>

<div class="card"><div class="card-body">
    Open: <strong><?= $total_open ?></strong>
    &nbsp;|&nbsp; Assigned: <?= $stats['assigned'] ?? 0 ?>
    &nbsp;|&nbsp; Investigating: <?= $stats['investigating'] ?? 0 ?>
    &nbsp;|&nbsp; Waiting: <?= $stats['waiting'] ?? 0 ?>
    &nbsp;|&nbsp; Resolved: <?= $stats['resolved'] ?? 0 ?>
</div></div>

<div class="card"><div class="card-body">
<form method="GET" class="row g-2">
    <div class="col-md-3"><select name="status" class="form-control-custom">
        <option value="">All statuses</option>
        <?php foreach (['open','assigned','investigating','waiting','resolved','closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $f_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="col-md-4"><select name="category" class="form-control-custom">
        <option value="">All categories</option>
        <?php foreach (['data_conflict','missing_information','document_conflict','workflow_failure','integration_failure','payment_mismatch','authority_rejection','deadline_breach','ai_low_confidence'] as $c): ?>
            <option value="<?= $c ?>" <?= $f_cat === $c ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ',$c)) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="col-md-3"><button class="btn-primary-custom">Filter</button>
    <a href="exceptions.php" class="btn-secondary-custom">Reset</a></div>
</form>
</div></div>

<div class="card">
<div class="card-header"><?= count($exceptions) ?> exception(s)</div>
<div class="card-body">
<?php if (!$exceptions): ?>
    <p class="text-muted">No exceptions match.</p>
<?php else: ?>
<table class="table-custom">
<thead><tr><th>Sev</th><th>Category</th><th>Title</th><th>Client</th><th>Status</th><th>Assigned</th><th>Age</th><th></th></tr></thead>
<tbody>
<?php foreach ($exceptions as $x): ?>
<tr>
    <td><?= exc_sev_badge($x['severity']) ?></td>
    <td><?= e(ucwords(str_replace('_',' ',$x['category']))) ?></td>
    <td><?= e($x['title']) ?><?php if (!empty($x['detail'])): ?><br><small class="text-muted"><?= e(mb_strimwidth((string)$x['detail'],0,90,'...')) ?></small><?php endif; ?></td>
    <td><?php if ($x['client_id']): ?><a href="clients.php?view=<?= (int)$x['client_id'] ?>"><?= e(trim(($x['c_first']??'').' '.($x['c_last']??''))) ?></a><?php else: ?>-<?php endif; ?></td>
    <td><?= e(ucfirst($x['status'])) ?></td>
    <td><?= e(trim(($x['a_first']??'').' '.($x['a_last']??'')) ?: '-') ?></td>
    <td><?= e(format_date($x['created_at'])) ?></td>
    <td>
        <?php if (user_can('exceptions','manage')): ?>
        <button type="button" class="btn-secondary-custom btn-sm" onclick="var r=document.getElementById('ex<?= (int)$x['id'] ?>'); r.style.display = r.style.display==='block'?'none':'block';"><i class="bi bi-pencil"></i></button>
        <div id="ex<?= (int)$x['id'] ?>" style="display:none;margin-top:8px;background:#f8fafc;padding:10px;border-radius:6px;">
        <form method="POST">
            <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="exception_id" value="<?= (int)$x['id'] ?>">
            <input type="hidden" name="back_status" value="<?= e($f_status) ?>">
            <select name="status" class="form-control-custom" style="max-width:160px;display:inline-block;">
                <?php foreach (['open','assigned','investigating','waiting','resolved','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $x['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="assigned_user_id" class="form-control-custom" style="max-width:170px;display:inline-block;">
                <option value="0">Unassigned</option>
                <?php foreach (db_query("SELECT id, first_name, last_name FROM users WHERE status='active'") as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= (int)$x['assigned_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['first_name'].' '.$u['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="resolution_note" class="form-control-custom" placeholder="Note / resolution" value="<?= e($x['resolution_note'] ?? '') ?>" style="display:inline-block;max-width:260px;">
            <button class="btn-primary-custom btn-sm">Save</button>
        </form>
        </div>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div></div>
<?php include __DIR__ . '/footer.php'; ?>
