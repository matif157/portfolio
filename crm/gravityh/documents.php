<?php
/**
 * GRAVITY CRM - Documents Management
 * Phase 2: Core CRM Modules
 * 
 * Yahan documents upload/download/share hote hain.
 * Files secure upload path mein save hoti hain.
 * Confidential documents par access control hota hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('documents', 'view');

$user = current_user();
$page_title = 'Documents';
$active_page = 'documents';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    
    if ($action === 'upload') {
        require_permission('documents', 'create');
        
        $title = sanitize(post('title'));
        $client_id = (int)post('client_id', 0);
        $project_id = (int)post('project_id', 0);
        $category_id = (int)post('category_id', 0);
        $description = sanitize(post('description'));
        $is_confidential = post('is_confidential') ? 1 : 0;
        $is_shared = post('is_shared') ? 1 : 0;
        
        $errors = [];
        validate_required($title, 'Document title', $errors);
        
        if (empty($errors) && !empty($_FILES['document_file'])) {
            $upload = handle_upload('document_file', 'documents');
            if (!$upload[0]) {
                $errors[] = $upload[2];
            } else {
                $file_path = $upload[1];
                $file_name = $_FILES['document_file']['name'];
                $file_size = $_FILES['document_file']['size'];
                $file_type = $_FILES['document_file']['type'];
                
                db_execute(
                    'INSERT INTO documents (client_id, project_id, category_id, title, description, file_path, file_name, file_size, file_type, is_confidential, is_shared, uploaded_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$client_id ?: null, $project_id ?: null, $category_id ?: null, $title, $description, $file_path, $file_name, $file_size, $file_type, $is_confidential, $is_shared, $user['id']]
                );
                $doc_id = db_last_id();
        bus_emit(EVT_DOCUMENT_UPLOADED, [], ['source' => 'document', 'source_id' => $doc_id, 'client_id' => (int)($client_id ?? 0)]);
                
                log_activity('documents', 'upload', $doc_id, 'Uploaded document: ' . $title);
                flash('success', 'Document uploaded successfully.');
                redirect('documents.php');
            }
        }
        
        foreach ($errors as $err) {
            flash('error', $err);
        }
    }
    
    if ($action === 'delete') {
        require_permission('documents', 'delete');
        $doc_id = (int)post('document_id', 0);
        
        $existing = db_row('SELECT * FROM documents WHERE id = ?', [$doc_id]);
        if (!$existing) {
            flash('error', 'Document not found.');
            redirect('documents.php');
        }
        
        // Delete file from disk
        $file_path = UPLOAD_PATH . '/' . $existing['file_path'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        db_execute('DELETE FROM documents WHERE id = ?', [$doc_id]);
        log_activity('documents', 'delete', $doc_id, 'Deleted document: ' . $existing['title']);
        flash('success', 'Document deleted successfully.');
        redirect('documents.php');
    }
}

// Filters
$where = '1=1';
$params = [];

$search = trim(get('search', ''));
if ($search !== '') {
    $where .= ' AND (d.title LIKE ? OR d.file_name LIKE ? OR d.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$category_filter = (int)get('category', 0);
if ($category_filter > 0) {
    $where .= ' AND d.category_id = ?';
    $params[] = $category_filter;
}

$client_filter = (int)get('client', 0);
if ($client_filter > 0) {
    $where .= ' AND d.client_id = ?';
    $params[] = $client_filter;
}

$confidential_filter = get('confidential', '');
if ($confidential_filter !== '') {
    $where .= ' AND d.is_confidential = ?';
    $params[] = $confidential_filter === '1' ? 1 : 0;
}

// Non-admins can only see non-confidential docs or shared docs
if ((int)$user['role_id'] !== 1) {
    $where .= ' AND (d.is_confidential = 0 OR d.is_shared = 1 OR d.uploaded_by = ?)';
    $params[] = $user['id'];
}

// Pagination
$per_page = (int)get_setting('items_per_page', 20);
$page = max(1, (int)get('page', 1));
$offset = ($page - 1) * $per_page;

$total = (int)db_value("SELECT COUNT(*) FROM documents d WHERE {$where}", $params);
$documents = db_query(
    "SELECT d.*, c.first_name AS client_first, c.last_name AS client_last, ct.name AS category_name, p.name AS project_name, u.first_name AS uploader_first, u.last_name AS uploader_last 
     FROM documents d 
     LEFT JOIN clients c ON d.client_id = c.id 
     LEFT JOIN document_categories ct ON d.category_id = ct.id 
     LEFT JOIN projects p ON d.project_id = p.id 
     LEFT JOIN users u ON d.uploaded_by = u.id 
     WHERE {$where} 
     ORDER BY d.uploaded_at DESC 
     LIMIT {$per_page} OFFSET {$offset}",
    $params
);
$total_pages = max(1, ceil($total / $per_page));

// Stats
$total_docs = (int)db_value('SELECT COUNT(*) FROM documents');
$total_size = (int)db_value('SELECT COALESCE(SUM(file_size), 0) FROM documents');
$confidential_docs = (int)db_value('SELECT COUNT(*) FROM documents WHERE is_confidential = 1');

// Dropdown data
$clients = db_query('SELECT id, first_name, last_name FROM clients WHERE status != "archived" ORDER BY first_name');
$categories = db_query('SELECT * FROM document_categories ORDER BY name');
$projects = db_query('SELECT id, name FROM projects WHERE status NOT IN ("completed","cancelled") ORDER BY name');

// View document
$view_document = null;
if (get('view')) {
    $view_document = db_row(
        'SELECT d.*, c.first_name AS client_first, c.last_name AS client_last, ct.name AS category_name, p.name AS project_name, u.first_name AS uploader_first, u.last_name AS uploader_last 
         FROM documents d 
         LEFT JOIN clients c ON d.client_id = c.id 
         LEFT JOIN document_categories ct ON d.category_id = ct.id 
         LEFT JOIN projects p ON d.project_id = p.id 
         LEFT JOIN users u ON d.uploaded_by = u.id 
         WHERE d.id = ?',
        [(int)get('view')]
    );
    if ($view_document) {
        log_activity('documents', 'view', $view_document['id'], 'Viewed document: ' . $view_document['title']);
    }
}

// Download action
if (get('download')) {
    $doc_id = (int)get('download');
    $doc = db_row('SELECT * FROM documents WHERE id = ?', [$doc_id]);
    if ($doc) {
        // Check access for non-admin
        if ((int)$user['role_id'] !== 1) {
            if ($doc['is_confidential'] && !$doc['is_shared'] && $doc['uploaded_by'] != $user['id']) {
                flash('error', 'You do not have access to download this document.');
                redirect('documents.php');
            }
        }
        
        $file_path = UPLOAD_PATH . '/' . $doc['file_path'];
        if (file_exists($file_path)) {
            log_activity('documents', 'download', $doc['id'], 'Downloaded document: ' . $doc['title']);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($doc['file_name']) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
        flash('error', 'File not found on server.');
    }
    redirect('documents.php');
}
?>
<?php include __DIR__ . '/header.php'; ?>

<?php if ($view_document): ?>
    <!-- Document Profile View -->
    <div class="page-title">
        <h1><i class="bi bi-file-earmark-text"></i> <?= e($view_document['title']) ?></h1>
        <div>
            <a href="documents.php?download=<?= $view_document['id'] ?>" class="btn-primary-custom" target="_blank"><i class="bi bi-download"></i> Download</a>
            <a href="documents.php" class="btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Document Information</div>
                <div class="card-body">
                    <p><strong>File Name:</strong> <?= e($view_document['file_name']) ?></p>
                    <p><strong>Category:</strong> <?= e($view_document['category_name'] ?? 'Uncategorized') ?></p>
                    <p><strong>Client:</strong> <?= $view_document['client_id'] ? '<a href="clients.php?view=' . $view_document['client_id'] . '">' . e($view_document['client_first'] . ' ' . $view_document['client_last']) . '</a>' : 'Not linked' ?></p>
                    <p><strong>Project:</strong> <?= $view_document['project_id'] ? '<a href="projects.php?view=' . $view_document['project_id'] . '">' . e($view_document['project_name']) . '</a>' : 'Not linked' ?></p>
                    <p><strong>Confidential:</strong> <?= $view_document['is_confidential'] ? '<span class="badge badge-danger">Confidential</span>' : '<span class="badge badge-success">Public</span>' ?></p>
                    <p><strong>Shared:</strong> <?= $view_document['is_shared'] ? 'Yes' : 'No' ?></p>
                    <p><strong>Size:</strong> <?= number_format($view_document['file_size'] / 1024, 1) ?> KB</p>
                    <p><strong>Uploaded By:</strong> <?= e($view_document['uploader_first'] . ' ' . $view_document['uploader_last']) ?></p>
                    <p><strong>Uploaded At:</strong> <?= e(format_datetime($view_document['uploaded_at'])) ?></p>
                    <?php if ($view_document['description']): ?>
                        <hr>
                        <p><strong>Description:</strong></p>
                        <p><?= e($view_document['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Preview</div>
                <div class="card-body text-center">
                    <?php
                    $ext = strtolower(pathinfo($view_document['file_name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                        <img src="<?= e(UPLOAD_URL . '/' . $view_document['file_path']) ?>" alt="Document preview" style="max-width:100%;max-height:400px;border-radius:8px;" class="img-fluid">
                    <?php elseif ($ext === 'pdf'): ?>
                        <iframe src="<?= e(UPLOAD_URL . '/' . $view_document['file_path']) ?>" style="width:100%;height:400px;border:none;border-radius:8px;"></iframe>
                    <?php else: ?>
                        <div class="p-5">
                            <i class="bi bi-file-earmark" style="font-size:4rem;color:#9ca3af;"></i>
                            <p class="mt-3 text-muted"><?= e(strtoupper($ext)) ?> file. <a href="documents.php?download=<?= $view_document['id'] ?>" class="btn-primary-custom btn-sm"><i class="bi bi-download"></i> Download to view</a></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Documents List -->
    <div class="page-title">
        <h1><i class="bi bi-file-earmark-text"></i> Documents</h1>
        <button class="btn-primary-custom" onclick="toggleUploadForm()"><i class="bi bi-upload"></i> Upload Document</button>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-files"></i></div>
                <div class="stat-value"><?= $total_docs ?></div>
                <div class="stat-label">Total Documents</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-hdd"></i></div>
                <div class="stat-value"><?= number_format($total_size / 1048576, 1) ?> MB</div>
                <div class="stat-label">Total Storage</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fee2e2;color:#991b1b;"><i class="bi bi-shield-lock"></i></div>
                <div class="stat-value"><?= $confidential_docs ?></div>
                <div class="stat-label">Confidential</div>
            </div>
        </div>
    </div>
    
    <!-- Upload Form -->
    <div class="card" id="uploadForm" style="display:none;">
        <div class="card-header"><i class="bi bi-upload"></i> Upload New Document</div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload">
                
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label-custom">Document Title *</label>
                        <input type="text" name="title" class="form-control-custom" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">File *</label>
                        <input type="file" name="document_file" class="form-control-custom" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Category</label>
                        <select name="category_id" class="form-control-custom">
                            <option value="0">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label-custom">Client</label>
                        <select name="client_id" class="form-control-custom">
                            <option value="0">No Client</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= e($client['first_name'] . ' ' . $client['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Project</label>
                        <select name="project_id" class="form-control-custom">
                            <option value="0">No Project</option>
                            <?php foreach ($projects as $proj): ?>
                                <option value="<?= $proj['id'] ?>"><?= e($proj['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">Description</label>
                        <input type="text" name="description" class="form-control-custom" placeholder="Optional">
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_confidential" value="1" class="form-check-input">
                            <label class="form-check-label">Confidential (restricted access)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="is_shared" value="1" class="form-check-input">
                            <label class="form-check-label">Shared (visible to all staff)</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary-custom"><i class="bi bi-upload"></i> Upload</button>
                <button type="button" class="btn-secondary-custom" onclick="toggleUploadForm()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control-custom" placeholder="Search documents..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control-custom">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="client" class="form-control-custom">
                        <option value="0">All Clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $client_filter == $client['id'] ? 'selected' : '' ?>><?= e($client['first_name'] . ' ' . $client['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Filter</button>
                    <a href="documents.php" class="btn-secondary-custom">Reset</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Documents Table -->
    <div class="card">
        <div class="card-header">Documents (<?= $total ?>)</div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <p class="text-muted">No documents found.</p>
            <?php else: ?>
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Client</th>
                            <th>Size</th>
                            <th>Access</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><a href="documents.php?view=<?= $doc['id'] ?>"><strong><?= e($doc['title']) ?></strong></a>
                                    <div class="text-muted" style="font-size:0.8rem;"><?= e($doc['file_name']) ?></div>
                                </td>
                                <td><?= e($doc['category_name'] ?? '-') ?></td>
                                <td><?= $doc['client_id'] ? e($doc['client_first'] . ' ' . $doc['client_last']) : '-' ?></td>
                                <td><?= number_format($doc['file_size'] / 1024, 1) ?> KB</td>
                                <td>
                                    <?= $doc['is_confidential'] ? '<span class="badge badge-danger">Confidential</span>' : '' ?>
                                    <?= $doc['is_shared'] ? '<span class="badge badge-info">Shared</span>' : '' ?>
                                </td>
                                <td><?= e(time_ago($doc['uploaded_at'])) ?></td>
                                <td>
                                    <a href="documents.php?view=<?= $doc['id'] ?>" class="btn-secondary-custom btn-sm" target="_blank"><i class="bi bi-eye"></i></a>
                                    <a href="documents.php?download=<?= $doc['id'] ?>" class="btn-secondary-custom btn-sm"><i class="bi bi-download"></i></a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document?')">
                                        <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                        <button type="submit" class="btn-danger-custom btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?= pagination_links($total_pages, $page, 'documents.php?' . http_build_query(array_filter(['search' => $search, 'category' => $category_filter, 'client' => $client_filter]))) ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleUploadForm() {
    const form = document.getElementById('uploadForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
