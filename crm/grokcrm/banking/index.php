<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Banking', 'view');
$pageTitle = 'Bank Accounts';

$accounts = $pdo->query("SELECT b.*, m.member_name, m.bus_name 
                         FROM bank_accounts b 
                         LEFT JOIN members m ON b.client_id = m.id 
                         ORDER BY b.id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <?php if (has_permission('Banking', 'manage')): ?>
    <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Bank Account</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Bank Name</th>
                        <th>Account No</th>
                        <th>Routing</th>
                        <th>Client</th>
                        <th>Added</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accounts)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No bank accounts found</td></tr>
                    <?php else: foreach ($accounts as $i => $a): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><strong><?= e($a['bank_name']) ?></strong></td>
                            <td><code><?= e($a['account_no'] ?? '-') ?></code></td>
                            <td><?= e($a['routing_no'] ?? '-') ?></td>
                            <td>
                                <?php if ($a['client_id']): ?>
                                    <a href="../clients/view.php?id=<?= $a['client_id'] ?>"><?= e($a['member_name'] ?? 'Client') ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="small"><?= $a['date_added'] ? date('d M Y', strtotime($a['date_added'])) : '-' ?></td>
                            <td>
                                <a href="statements.php?bank_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-info" title="Statements"><i class="bi bi-file-earmark-text"></i></a>
                                <?php if (has_permission('Banking', 'manage')): ?>
                                <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
