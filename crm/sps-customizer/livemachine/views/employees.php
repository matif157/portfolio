<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';

Auth::requireLogin();
$pageTitle = 'Employees';

/* All rows rendered — DataTables handles search / sort / pagination client-side */
$q     = '';
$rows  = Employee::all('', 1, 100000);
$total = Employee::count('');

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <section class="panel">
                <header>
                    <h3>Employees <span class="text-mute" style="font-weight:normal">(<?= $total ?>)</span></h3>
                    <div class="flex" data-tour="emp-add">
                        <button id="btn-sync-from-device" class="btn"><i data-lucide="cloud-download"></i> Sync from device</button>
                        <button id="btn-add-employee"    class="btn primary"><i data-lucide="user-plus"></i> Add</button>
                    </div>
                </header>
                <div class="body" style="padding:0">
                    <table class="table" data-tour="emp-table"<?= $rows ? ' data-dt data-dt-title="employees" data-dt-order="[1, &quot;asc&quot;]" data-dt-noorder="[7]"' : '' ?>>
                        <thead><tr>
                            <th>User ID</th><th>Name</th><th>Department</th><th>Designation</th>
                            <th>Card&nbsp;#</th><th>Role</th><th>Fingerprint</th><th></th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="8" class="empty">No employees yet. Click <strong>Sync from device</strong> to import.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr
                                data-id="<?= (int)$r['id'] ?>"
                                data-devid="<?= htmlspecialchars($r['device_user_id']) ?>"
                                data-name="<?= htmlspecialchars($r['name']) ?>"
                                data-dept="<?= htmlspecialchars($r['department'] ?? '') ?>"
                                data-desg="<?= htmlspecialchars($r['designation'] ?? '') ?>"
                                data-card="<?= htmlspecialchars($r['card_no'] ?? '') ?>"
                                data-role="<?= (int)$r['role'] ?>"
                            >
                                <td><code><?= htmlspecialchars($r['device_user_id']) ?></code></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['department'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['designation'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['card_no'] ?? '') ?></td>
                                <td><?= (int)$r['role'] === 14 ? 'Admin' : 'User' ?></td>
                                <td><?= $r['fingerprint_enrolled'] ? 'Yes' : '-' ?></td>
                                <td class="text-right">
                                    <button class="btn sm ghost" data-edit  ><i data-lucide="pencil"></i></button>
                                    <button class="btn sm ghost" data-delete><i data-lucide="trash-2"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/employees.js"></script>
</body></html>
