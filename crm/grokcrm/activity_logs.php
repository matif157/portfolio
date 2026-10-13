<?php
require_once 'includes/log_in.php';
require_permission('System', 'logs');
$pageTitle = 'Activity Logs';

$limit = 40;
$page  = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$total_pages = max(1, ceil($total / $limit));

$stmt = $pdo->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Date / Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No logs found</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $i => $log): ?>
                        <tr>
                            <td><?= $offset + $i + 1 ?></td>
                            <td class="small">
                                <?= date('d M Y', strtotime($log['created_at'])) ?><br>
                                <span class="text-muted"><?= date('h:i:s A', strtotime($log['created_at'])) ?></span>
                            </td>
                            <td><?= e($log['user_name'] ?? 'System') ?></td>
                            <td><span class="badge bg-primary"><?= e($log['action']) ?></span></td>
                            <td><?= e($log['module'] ?? '-') ?></td>
                            <td class="small"><?= e($log['description'] ?? '-') ?></td>
                            <td><code class="small"><?= e($log['ip_address'] ?? '-') ?></code></td>
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
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
