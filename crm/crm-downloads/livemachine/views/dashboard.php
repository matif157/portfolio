<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Attendance.php';

Auth::requireLogin();
$pageTitle = 'Dashboard';
$counts = Attendance::dashboardCounts();
$recent = Attendance::recent(20);

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <div class="cards" data-tour="dash-kpis">
                <div class="card kpi"        data-kpi="total">
                    <div class="icon"><i data-lucide="users"></i></div>
                    <div class="stack">
                        <p class="card-title">Total Employees</p>
                        <div class="card-value"><?= $counts['total_employees'] ?></div>
                    </div>
                </div>
                <div class="card kpi success" data-kpi="present">
                    <div class="icon"><i data-lucide="check-circle"></i></div>
                    <div class="stack">
                        <p class="card-title">Present Today</p>
                        <div class="card-value"><?= $counts['present'] ?></div>
                    </div>
                </div>
                <div class="card kpi warn"   data-kpi="late">
                    <div class="icon"><i data-lucide="clock"></i></div>
                    <div class="stack">
                        <p class="card-title">Late Today</p>
                        <div class="card-value"><?= $counts['late'] ?></div>
                    </div>
                </div>
                <div class="card kpi danger" data-kpi="absent">
                    <div class="icon"><i data-lucide="x-circle"></i></div>
                    <div class="stack">
                        <p class="card-title">Absent</p>
                        <div class="card-value"><?= $counts['absent'] ?></div>
                    </div>
                </div>
            </div>

            <section class="panel" data-tour="dash-feed">
                <header>
                    <h3>Live punch feed</h3>
                    <button id="btn-sync-now" class="btn sm primary"><i data-lucide="refresh-cw"></i> Sync now</button>
                </header>
                <div class="body" style="padding:0">
                    <table class="table live-feed" id="live-feed-table" data-dt data-dt-title="live-feed" data-dt-order="[0, &quot;desc&quot;]" data-dt-page="10">
                        <thead><tr>
                            <th>Day &amp; Time</th><th>Employee</th><th>User&nbsp;ID</th><th>Type</th><th>Arrival</th><th>Source</th>
                        </tr></thead>
                        <tbody id="live-feed-body">
                        <?php foreach ($recent as $r): ?>
                            <tr data-id="<?= (int)$r['id'] ?>">
                                <td data-order="<?= htmlspecialchars($r['punch_time']) ?>"><?= htmlspecialchars(date('D, M j · g:i A', strtotime($r['punch_time']))) ?></td>
                                <td><?= htmlspecialchars($r['employee_name'] ?: ('User ' . $r['device_user_id'])) ?></td>
                                <td><code><?= htmlspecialchars($r['device_user_id']) ?></code></td>
                                <td><span class="badge <?= htmlspecialchars($r['type_class'] ?? '') ?>"><?= htmlspecialchars($r['type_label'] ?? '') ?></span></td>
                                <td><?php if (!empty($r['display_status'])): ?><span class="badge <?= htmlspecialchars($r['display_status']) ?>"><?= htmlspecialchars($r['display_status']) ?></span><?php endif; ?></td>
                                <td><span class="badge <?= htmlspecialchars($r['source']) ?>"><?= htmlspecialchars($r['source']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard.js?v=<?= @filemtime(__DIR__ . '/../assets/js/dashboard.js') ?>"></script>
</body></html>
