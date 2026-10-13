<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Holiday.php';

Auth::requireLogin();
$pageTitle = 'Holidays';

$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : (int)date('Y');

$holidays  = Holiday::listing($year);
$avails    = Holiday::avails($year);
$employees = Employee::all('', 1, 500);

$paidCount   = count(array_filter($holidays, fn($h) => (int)$h['is_paid'] === 1));
$unpaidCount = count($holidays) - $paidCount;
$availCount  = count($avails);

/** Employees + holidays as JSON for the modal selects. */
$empJson = json_encode(array_map(fn($e) => [
    'id' => (int)$e['id'], 'name' => $e['name'], 'uid' => $e['device_user_id'],
], $employees), JSON_UNESCAPED_UNICODE);
$holidayJson = json_encode(array_map(fn($h) => [
    'id' => (int)$h['id'], 'name' => $h['name'], 'date' => $h['holiday_date'], 'paid' => (int)$h['is_paid'],
], $holidays), JSON_UNESCAPED_UNICODE);

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content" data-employees='<?= htmlspecialchars($empJson, ENT_QUOTES) ?>' data-holidays='<?= htmlspecialchars($holidayJson, ENT_QUOTES) ?>' data-year="<?= $year ?>">

            <section class="panel">
                <header>
                    <h3>Holidays <span class="text-mute" style="font-weight:normal">(<?= $year ?>)</span></h3>
                    <div class="flex">
                        <form method="get" class="flex" style="gap:6px;flex-wrap:wrap">
                            <input type="hidden" name="route" value="holidays">
                            <input type="number" class="btn" name="year" value="<?= $year ?>" style="width:90px">
                            <button class="btn primary"><i data-lucide="filter"></i> Show</button>
                        </form>
                        <span data-tour="hol-actions" class="flex" style="gap:10px">
                        <button id="btn-load-usa"    class="btn"><i data-lucide="flag"></i> Load USA holidays</button>
                        <button id="btn-add-holiday" class="btn primary"><i data-lucide="plus"></i> Add holiday</button>
                        </span>
                    </div>
                </header>

                <!-- KPI strip -->
                <div class="body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface-2)">
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Holidays this year</div>
                        <div style="font-size:22px;font-weight:700"><?= count($holidays) ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Paid by default</div>
                        <div style="font-size:22px;font-weight:700;color:var(--primary)"><?= $paidCount ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Unpaid by default</div>
                        <div style="font-size:22px;font-weight:700"><?= $unpaidCount ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Avail records</div>
                        <div style="font-size:22px;font-weight:700"><?= $availCount ?></div>
                    </div>
                </div>

                <div class="body" style="padding:0;overflow-x:auto" data-tour="hol-table">
                    <?php if (!$holidays): ?>
                        <p class="empty" style="padding:40px">No holidays for <?= $year ?> yet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr>
                                <th>Name</th><th>Date</th><th>Default</th><th>Source</th>
                                <th class="text-right">Availed</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($holidays as $h): ?>
                                <tr>
                                    <td><?= htmlspecialchars($h['name']) ?></td>
                                    <td><?= htmlspecialchars($h['holiday_date']) ?></td>
                                    <td><?= $h['is_paid'] ? '<span class="badge present">Paid</span>' : '<span class="badge">Unpaid</span>' ?></td>
                                    <td class="text-mute" style="font-size:12px"><?= $h['source'] === 'usa_federal' ? 'USA federal' : 'Custom' ?></td>
                                    <td class="text-right"><?= (int)$h['avail_count'] ?></td>
                                    <td>
                                        <button class="btn sm danger" data-del-holiday="<?= (int)$h['id'] ?>" title="Delete"><i data-lucide="trash-2"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel mt-3" data-tour="hol-avails">
                <header>
                    <h3>Avails <span class="text-mute" style="font-weight:normal">(who is availing which holiday, <?= $year ?>)</span></h3>
                    <button id="btn-record-avail" class="btn primary"><i data-lucide="user-check"></i> Record avail</button>
                </header>
                <div class="body" style="padding:0;overflow-x:auto">
                    <?php if (!$avails): ?>
                        <p class="empty" style="padding:40px">No avail records yet.</p>
                    <?php else: ?>
                        <table class="table" data-dt data-dt-title="holiday-avails" data-dt-order="[1, &quot;asc&quot;]">
                            <thead><tr>
                                <th>Employee</th><th>Holiday</th><th>Date</th><th>Paid</th>
                                <th>Recorded by</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($avails as $a): ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['employee_name']) ?>
                                        <span class="text-mute" style="font-size:12px">(<?= htmlspecialchars($a['device_user_id']) ?>)</span>
                                    </td>
                                    <td><?= htmlspecialchars($a['holiday_name']) ?></td>
                                    <td><?= htmlspecialchars($a['holiday_date']) ?></td>
                                    <td><?= $a['is_paid'] ? '<span class="badge present">Paid</span>' : '<span class="badge">Unpaid</span>' ?></td>
                                    <td class="text-mute" style="font-size:12px"><?= htmlspecialchars((string)($a['recorded_by'] ?? '')) ?></td>
                                    <td>
                                        <button class="btn sm danger" data-remove-avail="<?= (int)$a['id'] ?>" title="Remove"><i data-lucide="x"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/holidays.js?v=<?= @filemtime(__DIR__ . '/../assets/js/holidays.js') ?>"></script>
</body></html>
