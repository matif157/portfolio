<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Card', 'view');
$pageTitle = 'Credit Cards';

$cards = $pdo->query("SELECT c.*, m.member_name, m.bus_name, t.card_type_name 
                      FROM credit_cards c 
                      LEFT JOIN members m ON c.mem_id = m.id 
                      LEFT JOIN card_types t ON c.card_type = t.id 
                      ORDER BY c.id DESC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between mb-3">
    <div></div>
    <?php if (has_permission('Card', 'manage')): ?>
    <a href="create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Card</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Cardholder</th>
                    <th>Type</th>
                    <th>Number</th>
                    <th>Exp</th>
                    <th>Client</th>
                    <th>Added</th>
                    <th width="100">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cards)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No cards on file</td></tr>
                <?php else: foreach ($cards as $i => $c): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= e($c['card_onname'] ?? '-') ?></td>
                        <td><?= e($c['card_type_name'] ?? '-') ?></td>
                        <td><code>****<?= substr(preg_replace('/\D/','',$c['card_number'] ?? ''), -4) ?></code></td>
                        <td><?= $c['card_exp_month'] ?>/<?= $c['card_exp_year'] ?></td>
                        <td>
                            <?php if ($c['mem_id']): ?>
                                <a href="../clients/view.php?id=<?= $c['mem_id'] ?>"><?= e($c['member_name'] ?? '') ?></a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                        <td class="small"><?= $c['date_added'] ? date('d M Y', strtotime($c['date_added'])) : '-' ?></td>
                        <td>
                            <?php if (has_permission('Card', 'manage')): ?>
                            <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-pencil"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
