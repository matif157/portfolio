<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Payroll.php';

Auth::requireLogin();
$pageTitle = 'Salary Setup';

/* All rows rendered — DataTables handles search / sort / pagination client-side */
$rows  = Employee::all('', 1, 100000);
$total = Employee::count('');

$cfg = Payroll::settings();

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <section class="panel">
                <header>
                    <h3>Salary Setup
                        <span class="text-mute" style="font-weight:normal">(hourly rate per employee)</span>
                    </h3>
                </header>
                <div class="body" style="padding:14px 20px;border-bottom:1px solid var(--border);background:var(--surface-2)">
                    <small class="text-mute">
                        Currency: <strong><?= htmlspecialchars($cfg['currency_symbol']) ?></strong> ·
                        Compulsory break: <strong><?= (int)$cfg['break_minutes'] ?> min</strong> ·
                        Full day &ge; <strong><?= (float)$cfg['full_day_hours'] ?>h</strong> worked
                    </small>
                </div>
                <div class="body" style="padding:0" data-tour="sal-table">
                    <table class="table"<?= $rows ? ' data-dt data-dt-title="salary-setup" data-dt-order="[1, &quot;asc&quot;]" data-dt-noorder="[4,5]"' : '' ?>>
                        <thead><tr>
                            <th>User&nbsp;ID</th><th>Name</th><th>Department</th><th>Designation</th>
                            <th style="width:200px">Hourly Rate (<?= htmlspecialchars($cfg['currency_symbol']) ?>)</th>
                            <th style="width:120px"></th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6" class="empty">No employees yet. Add them on the <a href="?route=employees">Employees</a> page.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr data-id="<?= (int)$r['id'] ?>">
                                <td><code><?= htmlspecialchars($r['device_user_id']) ?></code></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['department']  ?? '') ?></td>
                                <td><?= htmlspecialchars($r['designation'] ?? '') ?></td>
                                <td>
                                    <input
                                        type="number" step="0.01" min="0"
                                        class="btn rate-input"
                                        style="padding:7px 10px;width:100%;text-align:right"
                                        value="<?= number_format((float)$r['hourly_rate'], 2, '.', '') ?>"
                                        data-original="<?= number_format((float)$r['hourly_rate'], 2, '.', '') ?>">
                                </td>
                                <td class="text-right">
                                    <button class="btn sm primary" data-save>
                                        <i data-lucide="save"></i> Save
                                    </button>
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
<script src="<?= BASE_URL ?>/assets/js/salary.js"></script>
</body></html>
