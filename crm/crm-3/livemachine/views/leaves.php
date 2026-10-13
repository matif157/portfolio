<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Leave.php';

Auth::requireLogin();
$pageTitle = 'Leaves';

$year   = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : (int)date('Y');
$emp    = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;
$status = in_array($_GET['status'] ?? '', ['pending','approved','rejected'], true) ? $_GET['status'] : null;

$from = sprintf('%04d-01-01', $year);
$to   = sprintf('%04d-12-31', $year);

$requests  = Leave::listing($status, $emp, $from, $to);
$balances  = Leave::balances($year, $emp);
$employees = Employee::all('', 1, 500);

$pending  = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approved = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));

/** Employees as a JSON list for the modal selects. */
$empJson = json_encode(array_map(fn($e) => [
    'id' => (int)$e['id'], 'name' => $e['name'], 'uid' => $e['device_user_id'],
], $employees), JSON_UNESCAPED_UNICODE);
$typeJson = json_encode(Leave::TYPES, JSON_UNESCAPED_UNICODE);

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content" data-employees='<?= htmlspecialchars($empJson, ENT_QUOTES) ?>' data-types='<?= htmlspecialchars($typeJson, ENT_QUOTES) ?>' data-year="<?= $year ?>">

            <section class="panel">
                <header>
                    <h3>Leaves <span class="text-mute" style="font-weight:normal">(<?= $year ?>)</span></h3>
                    <div class="flex">
                        <form method="get" class="flex" style="gap:6px;flex-wrap:wrap">
                            <input type="hidden" name="route" value="leaves">
                            <input type="number" class="btn" name="year" value="<?= $year ?>" style="width:90px">
                            <select name="emp" class="btn">
                                <option value="">All employees</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= (int)$e['id'] ?>" <?= $emp === (int)$e['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['device_user_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="btn">
                                <option value="">All status</option>
                                <?php foreach (['pending','approved','rejected'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn primary"><i data-lucide="filter"></i> Show</button>
                        </form>
                        <span data-tour="lv-actions" class="flex" style="gap:10px">
                        <button id="btn-add-leave"    class="btn primary"><i data-lucide="plus"></i> Add leave</button>
                        <button id="btn-assign-leave" class="btn"><i data-lucide="sliders-horizontal"></i> Assign balance</button>
                        </span>
                        <a class="btn" href="api/export_leaves.php?year=<?= $year ?>&emp=<?= (int)$emp ?>&status=<?= htmlspecialchars((string)$status) ?>"><i data-lucide="download"></i> CSV</a>
                    </div>
                </header>

                <!-- KPI strip -->
                <div class="body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface-2)" data-tour="lv-kpis">
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Pending approval</div>
                        <div style="font-size:22px;font-weight:700;color:var(--primary)"><?= $pending ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Approved</div>
                        <div style="font-size:22px;font-weight:700"><?= $approved ?></div>
                    </div>
                    <div>
                        <div class="text-mute" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em">Total records</div>
                        <div style="font-size:22px;font-weight:700"><?= count($requests) ?></div>
                    </div>
                </div>

                <div class="body" style="padding:0;overflow-x:auto" data-tour="lv-table">
                    <?php if (!$requests): ?>
                        <p class="empty" style="padding:40px">No leave records for this filter.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead><tr>
                                <th>Employee</th><th>Type</th><th>Paid</th>
                                <th>From</th><th>To</th><th class="text-right">Days</th>
                                <th>Reason</th><th>Status</th><th>Decided by</th><th>Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($requests as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['employee_name']) ?>
                                        <span class="text-mute" style="font-size:12px">(<?= htmlspecialchars($r['device_user_id']) ?>)</span>
                                    </td>
                                    <td><?= htmlspecialchars(Leave::typeLabel($r['leave_type'])) ?></td>
                                    <td><?= $r['is_paid'] ? '<span class="badge present">Paid</span>' : '<span class="badge">Unpaid</span>' ?></td>
                                    <td><?= htmlspecialchars($r['from_date']) ?></td>
                                    <td><?= htmlspecialchars($r['to_date']) ?></td>
                                    <td class="text-right"><?= rtrim(rtrim(number_format((float)$r['days'], 1), '0'), '.') ?></td>
                                    <td><?= htmlspecialchars((string)($r['reason'] ?? '')) ?></td>
                                    <td>
                                        <?php $cls = $r['status'] === 'approved' ? 'present' : ($r['status'] === 'rejected' ? 'late' : ''); ?>
                                        <span class="badge <?= $cls ?>"><?= ucfirst($r['status']) ?></span>
                                    </td>
                                    <td class="text-mute" style="font-size:12px">
                                        <?= htmlspecialchars((string)($r['decided_by'] ?? '')) ?>
                                        <?php if ($r['decision_note']): ?><br><em><?= htmlspecialchars($r['decision_note']) ?></em><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex" style="gap:4px">
                                            <?php if ($r['status'] !== 'approved'): ?>
                                                <button class="btn sm" data-approve="<?= (int)$r['id'] ?>" title="Approve"><i data-lucide="check"></i></button>
                                            <?php endif; ?>
                                            <?php if ($r['status'] !== 'rejected'): ?>
                                                <button class="btn sm" data-reject="<?= (int)$r['id'] ?>" title="Reject"><i data-lucide="x"></i></button>
                                            <?php endif; ?>
                                            <button class="btn sm" data-edit-leave="<?= (int)$r['id'] ?>" data-edit-type="<?= htmlspecialchars($r['leave_type']) ?>" data-edit-paid="<?= (int)$r['is_paid'] ?>" title="Edit type / paid status"><i data-lucide="pencil"></i></button>
                                            <button class="btn sm danger" data-del-leave="<?= (int)$r['id'] ?>" title="Delete"><i data-lucide="trash-2"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel mt-3" data-tour="lv-balances">
                <header><h3>Balances <span class="text-mute" style="font-weight:normal">(assigned vs used, <?= $year ?>)</span></h3></header>
                <div class="body" style="padding:0;overflow-x:auto">
                    <?php if (!$balances): ?>
                        <p class="empty" style="padding:40px">No allocations yet.</p>
                    <?php else: ?>
                        <table class="table" data-dt data-dt-title="leave-balances" data-dt-order="[0, &quot;asc&quot;]">
                            <thead><tr>
                                <th>Employee</th><th>Type</th>
                                <th class="text-right">Assigned</th>
                                <th class="text-right">Used</th>
                                <th class="text-right">Pending</th>
                                <th class="text-right">Remaining</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($balances as $b): ?>
                                <tr>
                                    <td><?= htmlspecialchars($b['name']) ?>
                                        <span class="text-mute" style="font-size:12px">(<?= htmlspecialchars($b['device_user_id']) ?>)</span>
                                    </td>
                                    <td><?= htmlspecialchars(Leave::typeLabel($b['leave_type'])) ?></td>
                                    <td class="text-right"><?= number_format($b['allocated'], 1) ?></td>
                                    <td class="text-right"><?= number_format($b['used'], 1) ?></td>
                                    <td class="text-right text-mute"><?= number_format($b['pending'], 1) ?></td>
                                    <td class="text-right"><strong style="color:<?= $b['remaining'] < 0 ? 'var(--danger,#c0392b)' : 'var(--primary)' ?>"><?= number_format($b['remaining'], 1) ?></strong></td>
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
<script src="<?= BASE_URL ?>/assets/js/leaves.js?v=<?= @filemtime(__DIR__ . '/../assets/js/leaves.js') ?>"></script>
</body></html>
