<?php
// ============================================================
// SPS CRM - Notifications
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    verify_csrf();
    db_query("UPDATE notifications SET is_read=1 WHERE user_id=?", [current_user_id()]);
    redirect('notifications.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    verify_csrf();
    db_query("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?", [(int)($_POST['id'] ?? 0), current_user_id()]);
    redirect('notifications.php');
}

$filter = $_GET['filter'] ?? 'all';
$where = 'user_id = ?';
$params = [current_user_id()];
if ($filter === 'unread') { $where .= ' AND is_read = 0'; }

$notifications = db_rows("SELECT n.*, CONCAT(c.first_name,' ',c.last_name) as client_name FROM notifications n LEFT JOIN clients c ON c.id=n.client_id WHERE $where ORDER BY n.created_at DESC LIMIT 200", $params);

render_header('Notifications', 'notifications');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Notifications</h2>
    <div style="display:flex;gap:8px">
      <a href="notifications.php?filter=all" class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-secondary' ?>">All</a>
      <a href="notifications.php?filter=unread" class="btn btn-sm <?= $filter==='unread'?'btn-primary':'btn-secondary' ?>">Unread</a>
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-secondary btn-sm">Mark All Read</button></form>
    </div>
  </div>
  <div style="padding:0">
  <?php if ($notifications): foreach ($notifications as $n): ?>
  <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:14px 20px;border-bottom:1px solid var(--border);background:<?= $n['is_read']?'#fff':'var(--primary-light)' ?>">
    <div>
      <div style="font-weight:<?= $n['is_read']?400:600 ?>;font-size:13px"><?= e($n['title']) ?></div>
      <?php if ($n['message']): ?><div style="font-size:13px;color:var(--text-muted);margin-top:2px"><?= e($n['message']) ?></div><?php endif; ?>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
        <?= time_ago($n['created_at']) ?><?= $n['client_name'] ? ' · <a href="client_profile.php?id='.$n['client_id'].'">'.e($n['client_name']).'</a>' : '' ?>
        <?= $n['link'] ? ' · <a href="'.e($n['link']).'">Open</a>' : '' ?>
      </div>
    </div>
    <?php if (!$n['is_read']): ?>
    <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-xs btn-secondary">Mark Read</button></form>
    <?php endif; ?>
  </div>
  <?php endforeach; else: ?>
  <div class="text-muted text-center" style="padding:40px">No notifications.</div>
  <?php endif; ?>
  </div>
</div>
<?php render_footer(); ?>
