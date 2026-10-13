<?php
require_once 'includes/log_in.php';
$pageTitle = 'Dashboard';

// Quick stats
$totalUsers   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalRoles   = $pdo->query("SELECT COUNT(*) FROM roles WHERE is_active=1")->fetchColumn();
$recentLogs   = $pdo->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 8")->fetchAll();

require_once 'includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Users</div>
                        <h3 class="mb-0"><?= $totalUsers ?></h3>
                    </div>
                    <i class="bi bi-people fs-2 text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Active Roles</div>
                        <h3 class="mb-0"><?= $totalRoles ?></h3>
                    </div>
                    <i class="bi bi-shield-check fs-2 text-success"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Phase</div>
                        <h3 class="mb-0">1 / 3</h3>
                    </div>
                    <i class="bi bi-layers fs-2 text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <div class="text-muted small">Status</div>
                        <h3 class="mb-0 text-success">Ready</h3>
                    </div>
                    <i class="bi bi-check-circle fs-2 text-success"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <strong>Recent Activity</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No activity yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="small"><?= date('d M H:i', strtotime($log['created_at'])) ?></td>
                                    <td><?= e($log['user_name'] ?? 'System') ?></td>
                                    <td><span class="badge bg-primary"><?= e($log['action']) ?></span></td>
                                    <td class="small"><?= e($log['description'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white"><strong>Phase 1 Complete</strong></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Authentication</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Users Management</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Roles & Permissions</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Activity Logs</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Full Database Schema</li>
                    <li class="mb-2"><i class="bi bi-hourglass text-warning"></i> Clients (Phase 2)</li>
                    <li class="mb-2"><i class="bi bi-hourglass text-warning"></i> Invoices (Phase 2)</li>
                    <li class="mb-2"><i class="bi bi-hourglass text-muted"></i> Banking (Phase 3)</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
