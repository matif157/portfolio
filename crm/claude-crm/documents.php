<?php
// ============================================================
// SPS CRM - Documents
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/layout.php';
require_login();
require_permission('documents.manage');

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
    verify_csrf();
    $client_id = (int)($_POST['client_id'] ?? 0) ?: null;
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if (empty($_FILES['file']['name'])) {
        flash_error('Please choose a file.');
    } else {
        $result = handle_file_upload($_FILES['file'], 'documents');
        if (!$result['success']) {
            flash_error($result['error']);
        } else {
            $did = db_insert(
                "INSERT INTO documents (client_id, title, filename, original_filename, file_size, category, uploaded_by, created_at)
                 VALUES (?,?,?,?,?,?,?,NOW())",
                [$client_id, $title ?: $result['original'], $result['filename'], $result['original'], $result['size'], $category ?: null, current_user_id()]
            );
            log_activity('upload', 'documents', $did, "Document uploaded: " . ($title ?: $result['original']), $client_id);
            flash_success('Document uploaded.');
        }
    }
    redirect('documents.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    verify_csrf();
    $did = (int)($_POST['doc_id'] ?? 0);
    $doc = db_row("SELECT * FROM documents WHERE id=?", [$did]);
    if ($doc) {
        $path = UPLOAD_PATH . 'documents/' . $doc['filename'];
        if (is_file($path)) @unlink($path);
        db_query("DELETE FROM documents WHERE id=?", [$did]);
        log_activity('delete', 'documents', $did, "Document deleted: {$doc['title']}", $doc['client_id']);
        flash_success('Document deleted.');
    }
    redirect('documents.php');
}

$client_filter = (int)($_GET['client_id'] ?? 0);
$where = [];
$params = [];
if ($client_filter) { $where[] = 'd.client_id = ?'; $params[] = $client_filter; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$docs = db_rows(
    "SELECT d.*, CONCAT(c.first_name,' ',c.last_name) as client_name, CONCAT(u.first_name,' ',u.last_name) as uploaded_by_name
     FROM documents d LEFT JOIN clients c ON c.id=d.client_id LEFT JOIN users u ON u.id=d.uploaded_by
     $where_sql ORDER BY d.created_at DESC LIMIT 200", $params
);
$clients_list = db_rows("SELECT id, first_name, last_name FROM clients WHERE deleted_at IS NULL ORDER BY first_name");

render_header('Documents', 'documents');
?>
<?= render_flash() ?>

<div class="card">
  <div class="card-header">
    <h2>Documents<?= $client_filter ? ' — Filtered' : '' ?></h2>
    <button class="btn btn-primary btn-sm" onclick="openModal('upload-doc-modal')">+ Upload Document</button>
  </div>
  <div class="table-wrapper">
    <table>
      <thead><tr><th>Title</th><th>Client</th><th>Category</th><th>Size</th><th>Uploaded By</th><th>Date</th><th></th></tr></thead>
      <tbody>
      <?php if ($docs): foreach ($docs as $d): ?>
      <tr>
        <td style="font-weight:500"><?= e($d['title']) ?></td>
        <td><?= $d['client_name'] ? '<a href="client_profile.php?id='.$d['client_id'].'">'.e($d['client_name']).'</a>' : '—' ?></td>
        <td><?= e($d['category'] ?: '—') ?></td>
        <td><?= number_format($d['file_size']/1024, 1) ?> KB</td>
        <td><?= e($d['uploaded_by_name'] ?: '—') ?></td>
        <td class="text-muted"><?= fmt_date($d['created_at']) ?></td>
        <td>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
            <button class="btn btn-xs btn-danger">🗑</button>
          </form>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No documents uploaded yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="upload-doc-modal">
  <div class="modal">
    <div class="modal-header"><h3>Upload Document</h3><button class="modal-close" onclick="closeModal('upload-doc-modal')">×</button></div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <div class="modal-body">
        <div class="form-group"><label>File <span class="text-danger">*</span></label><input type="file" name="file" class="form-control" required></div>
        <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" placeholder="Defaults to file name"></div>
        <div class="form-group"><label>Client</label>
          <select name="client_id" class="form-control">
            <option value="">— None —</option>
            <?php foreach ($clients_list as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Tax, Legal, ID"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('upload-doc-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Upload</button>
      </div>
    </form>
  </div>
</div>
<?php render_footer(); ?>
