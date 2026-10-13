<?php
/**
 * SPS Professional Services CRM
 * Central Document Repository
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$category_filter = trim($_GET['category'] ?? '');

$query = "
    SELECT d.*, c.first_name, c.last_name, c.company_name, u.full_name as uploader_name
    FROM documents d
    JOIN clients c ON d.client_id = c.id
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE 1=1
";
$params = [];
if ($category_filter !== '') {
    $query .= " AND d.category = ?";
    $params[] = $category_filter;
}
$query .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll();

render_header('Document Repository', 'documents');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Document Repository</h1>
        <p class="page-subtitle">Central archive of client uploaded bank statements, tax records, and corporate contracts</p>
    </div>
    <div>
        <a href="bank_statements.php" class="btn btn-primary">🏦 Bank Statement Matrix</a>
        <a href="upload_statement.php" class="btn btn-secondary">Upload Document</a>
    </div>
</div>

<!-- Category Filters -->
<div class="card">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="documents.php" class="btn btn-sm <?= empty($category_filter) ? 'btn-primary' : 'btn-secondary' ?>">All Files</a>
        <a href="documents.php?category=bank_statement" class="btn btn-sm <?= $category_filter === 'bank_statement' ? 'btn-primary' : 'btn-secondary' ?>">Bank Statements</a>
        <a href="documents.php?category=tax_doc" class="btn btn-sm <?= $category_filter === 'tax_doc' ? 'btn-primary' : 'btn-secondary' ?>">Tax Documents</a>
        <a href="documents.php?category=contract" class="btn btn-sm <?= $category_filter === 'contract' ? 'btn-primary' : 'btn-secondary' ?>">Contracts</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Document Title</th>
                    <th>Category</th>
                    <th>Client Entity</th>
                    <th>File Size</th>
                    <th>Uploaded By</th>
                    <th>Date Uploaded</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($documents)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No documents cataloged in repository.</td></tr>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td><strong><?= e($doc['title']) ?></strong><br><span style="font-size:11px; color:var(--text-muted);"><?= e($doc['file_name']) ?></span></td>
                            <td><span class="badge badge-info"><?= ucfirst(str_replace('_', ' ', $doc['category'])) ?></span></td>
                            <td><?= e($doc['company_name'] ?: "{$doc['first_name']} {$doc['last_name']}") ?></td>
                            <td><?= number_format($doc['file_size'] / 1024, 1) ?> KB</td>
                            <td><?= e($doc['uploader_name'] ?: 'Client Portal') ?></td>
                            <td><?= format_date($doc['created_at']) ?></td>
                            <td>
                                <a href="<?= e($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-secondary">Open Document</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
