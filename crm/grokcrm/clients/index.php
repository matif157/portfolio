<?php
// Clients / Members List
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Client', 'view');
$pageTitle = 'Clients';

$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 25;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(member_name LIKE ? OR bus_name LIKE ? OR prm_email LIKE ? OR phone LIKE ? OR cell_ph LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s, $s, $s]);
}
if ($status === '1' || $status === '0') {
    $where[] = 'is_active = ?';
    $params[] = (int)$status;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

$sql = "SELECT m.*, 
        (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE mem_id = m.id AND invoice_status IN (1,2)) as outstanding
        FROM members m 
        WHERE $whereSql 
        ORDER BY m.id DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Search name, business, email, phone..." value="<?= e($search) ?>" style="min-width:260px">
        <select name="status" class="form-select form-select-sm" style="width:130px">
            <option value="">All Status</option>
            <option value="1" <?= $status==='1'?'selected':'' ?>>Active</option>
            <option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option>
        </select>
        <button class="btn btn-sm btn-outline-primary">Search</button>
    </form>
    <?php if (has_permission('Client', 'add')): ?>
    <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Add Client</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Business</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Outstanding</th>
                        <th>Status</th>
                        <th width="140">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clients)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No clients found</td></tr>
                    <?php else: ?>
                        <?php foreach ($clients as $i => $c): ?>
                        <tr>
                            <td><?= $offset + $i + 1 ?></td>
                            <td>
                                <a href="view.php?id=<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                    <?= e($c['member_name']) ?>
                                </a>
                                <?php if ($c['is_guest']): ?><span class="badge bg-secondary">Guest</span><?php endif; ?>
                            </td>
                            <td><?= e($c['bus_name'] ?? '-') ?></td>
                            <td class="small"><?= e($c['prm_email'] ?? '-') ?></td>
                            <td class="small"><?= e($c['cell_ph'] ?: $c['phone'] ?: '-') ?></td>
                            <td class="<?= $c['outstanding'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                <?= money($c['outstanding']) ?>
                            </td>
                            <td><?= $c['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></td>
                            <td>
                                <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                <?php if (has_permission('Client', 'edit')): ?>
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($p=1; $p<=$total_pages; $p++): ?>
                <li class="page-item <?= $p==$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= e($status) ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
