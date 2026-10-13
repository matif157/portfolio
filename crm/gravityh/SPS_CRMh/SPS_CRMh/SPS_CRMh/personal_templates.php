<?php
/**
 * GRAVITY CRM — Personal Templates Studio
 * 
 * Yahan user apne personal reusable templates create, edit, download aur manage kar sakta hai.
 * Formats: Word Document templates, Excel CSV spreadsheets, aur Notepad text notes/checklists.
 */

require_once __DIR__ . '/auth.php';
require_login();

$user = current_user();
$user_id = $user['id'];

// Handle Template Actions (Create, Update, Delete, Download)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_template') {
        $template_id = intval($_POST['template_id'] ?? 0);
        $title = trim($_POST['title'] ?? 'Untitled Template');
        $template_type = $_POST['template_type'] ?? 'notepad';
        $content = $_POST['content'] ?? '';
        $is_shared = isset($_POST['is_shared']) ? 1 : 0;

        if ($template_id > 0) {
            // Update existing template
            $stmt = $pdo->prepare("
                UPDATE user_templates 
                SET title = ?, template_type = ?, content = ?, is_shared = ?, updated_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$title, $template_type, $content, $is_shared, $template_id, $user_id]);
            log_activity('Update Template', 'templates', $template_id, "Updated personal template '$title' ($template_type)", 'stage1_normal');
            set_flash('success', "Template '$title' updated successfully!");
        } else {
            // Insert new template
            $stmt = $pdo->prepare("
                INSERT INTO user_templates (user_id, title, template_type, content, is_shared)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $title, $template_type, $content, $is_shared]);
            $new_id = $pdo->lastInsertId();
            log_activity('Create Template', 'templates', $new_id, "Created personal template '$title' ($template_type)", 'stage1_normal');
            set_flash('success', "Personal template '$title' created successfully!");
        }
        redirect('personal_templates.php');

    } elseif ($action === 'delete_template') {
        $template_id = intval($_POST['template_id']);
        // Fetch snapshot before delete for soft recovery
        $stmtSnap = $pdo->prepare("SELECT * FROM user_templates WHERE id = ? AND user_id = ?");
        $stmtSnap->execute([$template_id, $user_id]);
        $tpl = $stmtSnap->fetch();

        if ($tpl) {
            log_deleted_record('user_templates', $template_id, $tpl, 'templates', "Deleted personal template '{$tpl['title']}'");
            $pdo->prepare("DELETE FROM user_templates WHERE id = ? AND user_id = ?")->execute([$template_id, $user_id]);
            set_flash('success', 'Template deleted. You can restore it from Audit Logs if needed.');
        }
        redirect('personal_templates.php');
    }
}

// Download Handler
if (isset($_GET['download']) && intval($_GET['download']) > 0) {
    $dl_id = intval($_GET['download']);
    $stmt = $pdo->prepare("SELECT * FROM user_templates WHERE id = ? AND (user_id = ? OR is_shared = 1)");
    $stmt->execute([$dl_id, $user_id]);
    $tpl = $stmt->fetch();

    if ($tpl) {
        $clean_title = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tpl['title']);
        if ($tpl['template_type'] === 'word') {
            header("Content-Type: application/msword");
            header("Content-Disposition: attachment; filename=\"{$clean_title}.doc\"");
            echo "<html><head><meta charset='utf-8'></head><body>";
            echo nl2br(htmlspecialchars($tpl['content']));
            echo "</body></html>";
            exit;
        } elseif ($tpl['template_type'] === 'excel') {
            header("Content-Type: text/csv; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"{$clean_title}.csv\"");
            echo $tpl['content'];
            exit;
        } else {
            header("Content-Type: text/plain; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"{$clean_title}.txt\"");
            echo $tpl['content'];
            exit;
        }
    }
}

// Active template for editing
$edit_id = intval($_GET['id'] ?? 0);
$active_tpl = null;
if ($edit_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM user_templates WHERE id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $active_tpl = $stmt->fetch();
}

// Fetch all templates for current user + shared templates
$templates = $pdo->prepare("
    SELECT t.*, u.full_name as author_name 
    FROM user_templates t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.user_id = ? OR t.is_shared = 1 
    ORDER BY t.updated_at DESC
");
$templates->execute([$user_id]);
$all_templates = $templates->fetchAll();

render_header('Personal Templates Studio', 'profile');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">?? Personal Templates Studio</h1>
        <p class="page-subtitle">Create, manage, and download custom Word, Excel, and Notepad templates</p>
    </div>
    <div>
        <a href="personal_templates.php?action=new" class="btn btn-primary">+ Create New Template</a>
        <a href="profile.php" class="btn btn-secondary">? Back to Profile</a>
    </div>
</div>

<div class="form-row" style="align-items:flex-start;">
    <!-- Left Column: Template Editor / Creator -->
    <div style="flex:1.3; min-width:360px;">
        <div class="card">
            <div class="card-title">
                <span><?= $active_tpl ? '?? Edit Template: ' . e($active_tpl['title']) : '? New Personal Template' ?></span>
                <?php if ($active_tpl): ?>
                    <a href="personal_templates.php" class="btn btn-sm btn-secondary">New Template</a>
                <?php endif; ?>
            </div>

            <form action="personal_templates.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="template_id" value="<?= $active_tpl['id'] ?? 0 ?>">

                <div class="form-group">
                    <label class="form-label">Template Title *</label>
                    <input type="text" name="title" value="<?= e($active_tpl['title'] ?? '') ?>" class="form-control" required placeholder="e.g. Monthly Bookkeeping Client Checklist">
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label class="form-label">Template Format / Type *</label>
                        <select name="template_type" id="tplTypeSelect" class="form-control" onchange="updateEditorPlaceholder()">
                            <option value="notepad" <?= ($active_tpl['template_type'] ?? '') === 'notepad' ? 'selected' : '' ?>>?? Notepad / Plain Text</option>
                            <option value="word" <?= ($active_tpl['template_type'] ?? '') === 'word' ? 'selected' : '' ?>>?? Word Document (.doc)</option>
                            <option value="excel" <?= ($active_tpl['template_type'] ?? '') === 'excel' ? 'selected' : '' ?>>?? Excel Spreadsheet (.csv)</option>
                            <option value="custom" <?= ($active_tpl['template_type'] ?? '') === 'custom' ? 'selected' : '' ?>>?? Custom Script / Note</option>
                        </select>
                    </div>
                    <div class="form-col form-group" style="display:flex; align-items:center; padding-top:24px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:500; cursor:pointer;">
                            <input type="checkbox" name="is_shared" value="1" <?= !empty($active_tpl['is_shared']) ? 'checked' : '' ?>>
                            <span>Share with entire CRM team</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Template Content / Data *</label>
                    <textarea name="content" id="tplContentArea" class="form-control" rows="12" required style="font-family:monospace; font-size:13px;"><?= e($active_tpl['content'] ?? '') ?></textarea>
                    <div id="typeHint" style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                        For Excel templates, enter comma-separated values (CSV) like: Month, Account, Status, Amount
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:20px;">
                    <div>
                        <?php if ($active_tpl): ?>
                            <a href="personal_templates.php?download=<?= $active_tpl['id'] ?>" class="btn btn-secondary">?? Download File</a>
                        <?php endif; ?>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <?php if ($active_tpl): ?>
                            <button type="button" onclick="confirmDelete(<?= $active_tpl['id'] ?>)" class="btn btn-danger">Delete</button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary"><?= $active_tpl ? 'Update Template' : 'Save Template' ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Right Column: My & Shared Templates List -->
    <div style="flex:1; min-width:320px;">
        <div class="card">
            <div class="card-title">
                <span>?? Available Templates</span>
                <span class="badge badge-info"><?= count($all_templates) ?> Total</span>
            </div>

            <?php if (empty($all_templates)): ?>
                <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">
                    No templates saved yet. Create your first template using the form on the left.
                </div>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($all_templates as $tpl): ?>
                        <div style="padding:14px; background:<?= ($active_tpl['id'] ?? 0) == $tpl['id'] ? '#eef2ff' : '#f8fafc' ?>; border:1px solid <?= ($active_tpl['id'] ?? 0) == $tpl['id'] ? 'var(--primary)' : 'var(--border-color)' ?>; border-radius:8px; transition:all 0.15s;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <strong style="font-size:14px; color:var(--secondary);"><?= e($tpl['title']) ?></strong>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        By <?= e($tpl['author_name']) ?> • <?= format_date($tpl['updated_at']) ?>
                                        <?php if ($tpl['is_shared']): ?>
                                            <span class="badge badge-info" style="font-size:9px; padding:1px 4px;">Shared</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($tpl['template_type'] === 'word'): ?>
                                        <span class="badge badge-info">Word (.doc)</span>
                                    <?php elseif ($tpl['template_type'] === 'excel'): ?>
                                        <span class="badge badge-success">Excel (.csv)</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Notepad (.txt)</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="margin-top:10px; font-size:12px; color:var(--text-muted); max-height:40px; overflow:hidden; text-overflow:ellipsis;">
                                <?= e(substr($tpl['content'], 0, 90)) ?>...
                            </div>

                            <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid rgba(0,0,0,0.05); padding-top:8px;">
                                <a href="personal_templates.php?download=<?= $tpl['id'] ?>" class="btn btn-sm btn-secondary">?? Download</a>
                                <?php if ($tpl['user_id'] == $user_id): ?>
                                    <a href="personal_templates.php?id=<?= $tpl['id'] ?>" class="btn btn-sm btn-primary">Edit Template ?</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteForm" action="personal_templates.php" method="POST" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_template">
    <input type="hidden" name="template_id" id="deleteTemplateId">
</form>

<script>
function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this template? (You can restore it via Audit Logs if needed)")) {
        document.getElementById('deleteTemplateId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

function updateEditorPlaceholder() {
    const type = document.getElementById('tplTypeSelect').value;
    const hint = document.getElementById('typeHint');
    const area = document.getElementById('tplContentArea');

    if (type === 'excel') {
        hint.innerHTML = '?? Enter CSV formatted rows (e.g. Month, Account, Status, Tax Category, Amount)';
        if (!area.value.trim()) {
            area.value = "Date,Client Name,Service,Amount,Status\n2026-03-01,John Smith,Bookkeeping,450.00,Completed\n2026-03-05,ABC LLC,Sales Tax,199.00,Pending";
        }
    } else if (type === 'word') {
        hint.innerHTML = '?? Enter standard document text or letter formatting for export to Microsoft Word';
        if (!area.value.trim()) {
            area.value = "CLIENT SERVICE AGREEMENT & ENGAGEMENT LETTER\n\nClient Name: [CLIENT_NAME]\nService: Monthly Bookkeeping & Tax Compliance\nDate: " + new Date().toISOString().slice(0,10) + "\n\n1. Scope of Work:\nWe will maintain the monthly accounting ledger, reconcile bank accounts, and prepare annual tax return filings.";
        }
    } else {
        hint.innerHTML = '?? Enter quick plain text notes, client checklists, or phone discussion templates';
    }
}
</script>

<?php render_footer(); ?>
