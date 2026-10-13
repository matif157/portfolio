<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Employee.php';

Auth::requireLogin();
$pageTitle = 'Attendance';

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$to   = $_GET['to']   ?? date('Y-m-d');
$q    = trim((string)($_GET['q'] ?? ''));
$emp  = isset($_GET['emp']) && $_GET['emp'] !== '' ? (int)$_GET['emp'] : null;

// Review filter: '' = everything, 'active' = hide crossed-off, or one exact state.
$review = (string)($_GET['review'] ?? '');
$rows   = Attendance::listing($from, $to, $q !== '' ? $q : null, 500, $emp, $review !== '' ? $review : null);
$counts = Attendance::reviewCounts($from, $to, $emp);

// Shared by the Add Break / Manual punch pickers and the Employee filter below.
$employees = Employee::all('', 1, 500);
$empJson   = json_encode(array_map(fn($e) => [
    'uid' => $e['device_user_id'], 'name' => $e['name'],
], $employees), JSON_UNESCAPED_UNICODE);

$csvQs = http_build_query(array_filter([
    'kind' => 'attendance', 'from' => $from, 'to' => $to, 'q' => $q, 'emp' => $emp,
    'review' => $review,
]));

require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content" data-employees='<?= htmlspecialchars($empJson, ENT_QUOTES) ?>'>
            <section class="panel">
                <header>
                    <h3>Attendance log</h3>
                    <div class="flex" data-tour="att-actions">
                        <label class="flex"><input type="checkbox" id="chk-autosync"> <span class="text-mute" style="font-size:12px">Auto-sync 30s</span></label>
                        <button id="btn-sync"          class="btn primary"><i data-lucide="refresh-cw"></i> Sync now</button>
                        <button id="btn-add-break"     class="btn"><i data-lucide="coffee"></i> Add break</button>
                        <button id="btn-manual-punch"  class="btn"><i data-lucide="plus"></i> Manual punch</button>
                        <a class="btn" href="api/export_csv.php?<?= htmlspecialchars($csvQs) ?>"><i data-lucide="download"></i> CSV</a>
                    </div>
                </header>

                <div class="body" style="padding:14px 20px">
                    <form method="get" class="row" style="margin:0" data-tour="att-filter">
                        <input type="hidden" name="route" value="attendance">
                        <div class="field"><label>From</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>"></div>
                        <div class="field"><label>To</label>  <input type="date" name="to"   value="<?= htmlspecialchars($to) ?>"></div>
                        <div class="field">
                            <label>Employee</label>
                            <select name="emp">
                                <option value="">All employees</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?= (int)$e['id'] ?>" <?= $emp === (int)$e['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['device_user_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Review</label>
                            <select name="review">
                                <?php
                                $reviewOpts = [
                                    ''           => 'All punches',
                                    'active'     => 'Counted only (hide rejected)',
                                    'unreviewed' => 'Not reviewed (' . $counts['unreviewed'] . ')',
                                    'approved'   => 'Marked correct (' . $counts['approved'] . ')',
                                    'rejected'   => 'Marked wrong (' . $counts['rejected'] . ')',
                                ];
                                foreach ($reviewOpts as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= $review === $val ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn primary"><i data-lucide="filter"></i> Filter</button>
                    </form>

                </div>

                <div style="padding:0" data-tour="att-table">
                    <table class="table attendance-log"<?= $rows ? ' data-dt data-dt-title="attendance-log" data-dt-order="[0, &quot;desc&quot;]" data-dt-noorder="[8]"' : '' ?>>
                        <thead><tr>
                            <th>Punch Time</th><th>Employee</th><th>User ID</th><th>Department</th>
                            <th>Type</th><th>Source</th><th>Status</th><th>Review</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="9" class="empty">No punches in the selected range.</td></tr>
                        <?php else: foreach ($rows as $r):
                            $rev     = $r['review_status'] ?? 'unreviewed';
                            $devTime = $r['device_punch_time'] ?? null;
                            // Show what the device originally said once the punch has been
                            // hand-corrected, so the change is on the face of the record.
                            $edited  = !empty($r['edited']) && $devTime
                                    && substr((string)$devTime, 0, 16) !== substr((string)$r['punch_time'], 0, 16);
                        ?>
                            <tr class="review-<?= htmlspecialchars($rev) ?>">
                                <td data-order="<?= htmlspecialchars($r['punch_time']) ?>">
                                    <?= htmlspecialchars(date('D, M j · g:i A', strtotime($r['punch_time']))) ?>
                                    <?php if ($edited): ?>
                                        <br><span class="text-mute" style="font-size:11px" title="What the device originally reported">
                                            device said <?= htmlspecialchars(date('g:i A', strtotime((string)$devTime))) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($r['employee_name'] ?: ('User ' . $r['device_user_id'])) ?></td>
                                <td><code><?= htmlspecialchars($r['device_user_id']) ?></code></td>
                                <td><?= htmlspecialchars($r['department'] ?? '') ?></td>
                                <td><span class="badge <?= htmlspecialchars($r['type_class'] ?? '') ?>"><?= htmlspecialchars($r['type_label'] ?? '') ?></span></td>
                                <td><span class="badge <?= htmlspecialchars($r['source']) ?>"><?= htmlspecialchars($r['source']) ?></span></td>
                                <td><?php if (!empty($r['display_status'])): ?><span class="badge <?= htmlspecialchars($r['display_status']) ?>"><?= htmlspecialchars($r['display_status']) ?></span><?php endif; ?></td>
                                <td data-order="<?= htmlspecialchars($rev) ?>">
                                    <?php $revTitle = htmlspecialchars(trim(($r['review_note'] ?? '') . ' — ' . ($r['reviewed_by'] ?? ''), ' —')); ?>
                                    <?php if ($rev === 'approved'): ?>
                                        <span class="badge present" title="<?= $revTitle ?>">✓ correct</span>
                                    <?php elseif ($rev === 'rejected'): ?>
                                        <span class="badge danger" title="<?= $revTitle ?>">✗ wrong — not counted</span>
                                    <?php else: ?>
                                        <span class="text-mute" style="font-size:12px">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex" style="gap:4px">
                                        <button class="btn sm <?= $rev === 'approved' ? 'primary' : '' ?>" title="Mark this punch correct"
                                                data-review-punch="<?= (int)$r['id'] ?>"
                                                data-status="<?= $rev === 'approved' ? 'unreviewed' : 'approved' ?>"><i data-lucide="check"></i></button>
                                        <button class="btn sm <?= $rev === 'rejected' ? 'danger' : '' ?>" title="Mark this punch wrong — kept on record but not counted"
                                                data-review-punch="<?= (int)$r['id'] ?>"
                                                data-status="<?= $rev === 'rejected' ? 'unreviewed' : 'rejected' ?>"><i data-lucide="x"></i></button>
                                        <button class="btn sm" title="Edit punch time"
                                                data-edit-punch="<?= (int)$r['id'] ?>"
                                                data-time="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($r['punch_time']))) ?>"
                                                data-label="<?= htmlspecialchars($r['type_label'] ?? '') ?>"><i data-lucide="pencil"></i></button>
                                        <button class="btn sm danger" title="<?= $devTime ? 'Remove from the record (kept as a crossed-off row so it cannot re-import)' : 'Delete punch' ?>"
                                                data-del-punch="<?= (int)$r['id'] ?>"
                                                data-from-device="<?= $devTime ? '1' : '0' ?>"><i data-lucide="trash-2"></i></button>
                                    </div>
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
<script src="<?= BASE_URL ?>/assets/js/attendance.js?v=<?= @filemtime(__DIR__ . '/../assets/js/attendance.js') ?>"></script>
</body></html>
