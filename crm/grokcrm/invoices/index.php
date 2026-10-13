<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Invoice', 'view');
$pageTitle = 'Invoices';

$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$mem_id  = (int)($_GET['mem_id'] ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 30;
$offset  = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(i.id LIKE ? OR m.member_name LIKE ? OR m.bus_name LIKE ?)';
    $s = "%$search%";
    $params = array_merge($params, [$s, $s, $s]);
}
if ($status !== '' && is_numeric($status)) {
    $where[] = 'i.invoice_status = ?';
    $params[] = (int)$status;
}
if ($mem_id > 0) {
    $where[] = 'i.mem_id = ?';
    $params[] = $mem_id;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN members m ON i.mem_id = m.id WHERE $whereSql");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

$sql = "SELECT i.*, m.member_name, m.bus_name 
        FROM invoices i 
        LEFT JOIN members m ON i.mem_id = m.id 
        WHERE $whereSql 
        ORDER BY i.id DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$statusLabels = [1=>'Unpaid', 2=>'Partial', 3=>'Paid', 4=>'Cancelled'];
$statusClass  = [1=>'danger', 2=>'warning', 3=>'success', 4=>'secondary'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <form class="d-flex gap-2" method="GET">
        <?php if ($mem_id): ?><input type="hidden" name="mem_id" value="<?= $mem_id ?>"><?php endif; ?>
        <input type="text" name="q" class="form-control form-control-sm" placeholder="Invoice # or client..." value="<?= e($search) ?>" style="min-width:200px">
        <select name="status" class="form-select form-select-sm" style="width:130px">
            <option value="">All Status</option>
            <?php foreach ($statusLabels as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $status===(string)$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-outline-primary">Filter</button>
    </form>
    <?php if (has_permission('Invoice', 'add')): ?>
    <a href="create.php<?= $mem_id ? '?mem_id='.$mem_id : '' ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Invoice</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Due</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th width="100">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No invoices found</td></tr>
                    <?php else: foreach ($invoices as $inv): ?>
                        <tr>
                            <td><a href="view.php?id=<?= $inv['id'] ?>" class="fw-semibold">#<?= $inv['id'] ?></a></td>
                            <td>
                                <?php if ($inv['mem_id']): ?>
                                    <a href="../clients/view.php?id=<?= $inv['mem_id'] ?>"><?= e($inv['member_name'] ?? 'Client') ?></a>
                                    <?php if ($inv['bus_name']): ?><br><small class="text-muted"><?= e($inv['bus_name']) ?></small><?php endif; ?>
                                <?php else: ?>
                                    <?= e($inv['guest_name'] ?? 'Guest') ?>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= $inv['invoice_date'] ? date('d M Y', strtotime($inv['invoice_date'])) : '-' ?></td>
                            <td class="small"><?= $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '-' ?></td>
                            <td><?= money($inv['total_amount']) ?></td>
                            <td><?= money($inv['paid_amount']) ?></td>
                            <td class="<?= $inv['balance']>0?'text-danger fw-bold':'' ?>"><?= money($inv['balance']) ?></td>
                            <td><span class="badge bg-<?= $statusClass[$inv['invoice_status']] ?? 'secondary' ?>"><?= $statusLabels[$inv['invoice_status']] ?? $inv['invoice_status'] ?></span></td>
                            <td>
                                <a href="view.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <?php if (has_permission('Invoice', 'edit')): ?>
                                <a href="edit.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer">
        <ul class="pagination pagination-sm mb-0 justify-content-center">
            <?php for ($p=1;$p<=$total_pages;$p++): ?>
            <li class="page-item <?= $p==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&status=<?= e($status) ?>&mem_id=<?= $mem_id ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
