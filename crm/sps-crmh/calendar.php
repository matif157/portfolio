<?php
/**
 * SPS Professional Services CRM
 * Interactive Calendar & Multi-Client Reminder Scheduler (Spec Section 15)
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

// Handle Add Reminder with Multi-Client Support
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_reminder') {
    verify_csrf();
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $reminder_date = $_POST['reminder_date'];
    $reminder_time = $_POST['reminder_time'] ?? '09:00:00';
    $recurrence = $_POST['recurrence'] ?? 'none';
    $assigned_user_id = !empty($_POST['assigned_user_id']) ? intval($_POST['assigned_user_id']) : $_SESSION['user_id'];
    $selected_client_ids = $_POST['client_ids'] ?? [];

    if (!empty($title) && !empty($reminder_date)) {
        $stmt = $pdo->prepare("INSERT INTO reminders (title, description, reminder_date, reminder_time, recurrence, assigned_user_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $reminder_date, $reminder_time, $recurrence, $assigned_user_id]);
        $reminder_id = $pdo->lastInsertId();

        // Attach Multiple Selected Clients
        if (!empty($selected_client_ids) && is_array($selected_client_ids)) {
            $stmtR = $pdo->prepare("INSERT INTO reminder_clients (reminder_id, client_id) VALUES (?, ?)");
            foreach ($selected_client_ids as $cid) {
                $stmtR->execute([$reminder_id, intval($cid)]);
            }
        }

        log_activity('Add Reminder', 'calendar', $reminder_id, "Created reminder '$title' for date $reminder_date with " . count($selected_client_ids) . " clients.");
        set_flash('success', "Reminder '$title' scheduled for $reminder_date.");
        redirect("calendar.php?date=$reminder_date");
    }
}

$selected_date = $_GET['date'] ?? date('Y-m-d');
$month = date('m', strtotime($selected_date));
$year = date('Y', strtotime($selected_date));

// Fetch Reminders for selected date
$reminders_stmt = $pdo->prepare("
    SELECT r.*, u.full_name as assigned_staff,
           (SELECT GROUP_CONCAT(COALESCE(c.company_name, CONCAT(c.first_name, ' ', c.last_name)) SEPARATOR ', ')
            FROM reminder_clients rc JOIN clients c ON rc.client_id = c.id WHERE rc.reminder_id = r.id) as client_names
    FROM reminders r
    LEFT JOIN users u ON r.assigned_user_id = u.id
    WHERE r.reminder_date = ?
    ORDER BY r.reminder_time ASC
");
$reminders_stmt->execute([$selected_date]);
$date_reminders = $reminders_stmt->fetchAll();

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();
$staff = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active'")->fetchAll();

render_header('Operations Calendar & Reminders', 'calendar');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Operations Calendar & Reminders</h1>
        <p class="page-subtitle">Schedule client deadline reminders, tax filing notices, and follow-up activities</p>
    </div>
    <div>
        <button onclick="document.getElementById('addRemModal').style.display = 'block'" class="btn btn-primary">+ Add Reminder</button>
        <a href="reminders.php" class="btn btn-secondary">Manage All Reminders</a>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
    <!-- Left: Calendar View -->
    <div class="card">
        <div class="card-title">
            <span><?= date('F Y', strtotime($selected_date)) ?></span>
            <div>
                <a href="calendar.php?date=<?= date('Y-m-d', strtotime('-1 month', strtotime($selected_date))) ?>" class="btn btn-sm btn-secondary">← Prev Month</a>
                <a href="calendar.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-secondary">Today</a>
                <a href="calendar.php?date=<?= date('Y-m-d', strtotime('+1 month', strtotime($selected_date))) ?>" class="btn btn-sm btn-secondary">Next Month →</a>
            </div>
        </div>

        <?php
        $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
        $days_in_month = date('t', $first_day_of_month);
        $day_of_week = date('w', $first_day_of_month);
        ?>

        <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:6px; text-align:center; font-weight:600; margin-bottom:8px; font-size:12px; color:var(--text-muted);">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>

        <div style="display:grid; grid-template-columns: repeat(7, 1fr); gap:6px;">
            <?php
            // Empty slots before month start
            for ($i = 0; $i < $day_of_week; $i++) {
                echo '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; min-height:75px;"></div>';
            }

            for ($d = 1; $d <= $days_in_month; $d++) {
                $curr_date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $is_selected = ($curr_date_str === $selected_date);
                $is_today = ($curr_date_str === date('Y-m-d'));

                // Count reminders for this day
                $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM reminders WHERE reminder_date = ?");
                $count_stmt->execute([$curr_date_str]);
                $day_rem_count = $count_stmt->fetchColumn();

                $bg = $is_selected ? '#dbeafe' : ($is_today ? '#fef3c7' : '#ffffff');
                $border = $is_selected ? '2px solid var(--accent)' : '1px solid var(--border-color)';

                echo '<a href="calendar.php?date=' . $curr_date_str . '" style="text-decoration:none; color:inherit;">';
                echo '<div style="background:' . $bg . '; border:' . $border . '; border-radius:6px; padding:6px; min-height:75px; display:flex; flex-direction:column; justify-space-between;">';
                echo '<div style="font-weight:700; font-size:13px;">' . $d . '</div>';
                if ($day_rem_count > 0) {
                    echo '<div><span class="badge badge-danger" style="font-size:10px;">' . $day_rem_count . ' Reminders</span></div>';
                }
                echo '</div>';
                echo '</a>';
            }
            ?>
        </div>
    </div>

    <!-- Right: Selected Date Reminders Details (Spec Section 15) -->
    <div>
        <div class="card" style="border-top:4px solid var(--accent);">
            <div class="card-title">
                <span>Date: <?= format_date($selected_date) ?></span>
                <button onclick="document.getElementById('addRemModal').style.display = 'block'" class="btn btn-sm btn-primary">+ Add Reminder</button>
            </div>

            <?php if (empty($date_reminders)): ?>
                <p style="font-size:13px; color:var(--text-muted);">No reminders scheduled for this date.</p>
            <?php else: ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php foreach ($date_reminders as $rem): ?>
                        <div style="padding:12px; background:#f8fafc; border:1px solid var(--border-color); border-radius:8px;">
                            <div style="font-weight:700; font-size:14px; color:var(--secondary); margin-bottom:4px;">
                                <?= e($rem['title']) ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">
                                🕒 Time: <?= date('g:i A', strtotime($rem['reminder_time'])) ?> • Assigned: <?= e($rem['assigned_staff'] ?: 'Self') ?>
                            </div>
                            <?php if ($rem['client_names']): ?>
                                <div style="font-size:12px; font-weight:600; color:var(--accent); margin-bottom:4px;">
                                    Clients: <?= e($rem['client_names']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($rem['description']): ?>
                                <div style="font-size:12px; color:var(--text-dark);"><?= e($rem['description']) ?></div>
                            <?php endif; ?>
                            <div style="margin-top:6px; font-size:11px;">
                                <span class="badge badge-info">Recurrence: <?= ucfirst($rem['recurrence']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Form for Schedule Reminder (Multi-Client & Recurrence) -->
<div id="addRemModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:550px; margin:60px auto; max-height:90vh; overflow-y:auto;">
        <div class="card-title">
            <span>Schedule Client Reminder</span>
            <button onclick="document.getElementById('addRemModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="calendar.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_reminder">

            <div class="form-group">
                <label class="form-label">Associated Clients (Search / Multi-Select) *</label>
                <select name="client_ids[]" class="form-control" multiple style="height:110px;">
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>">
                            <?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size:11px; color:var(--text-muted); margin-top:3px;">Hold Ctrl / Cmd to select multiple clients</div>
            </div>

            <div class="form-group">
                <label class="form-label">Reminder Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Tax filing deadline reminder">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="reminder_date" class="form-control" value="<?= e($selected_date) ?>" required>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Time</label>
                    <input type="time" name="reminder_time" class="form-control" value="09:00">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Recurrence Schedule</label>
                    <select name="recurrence" class="form-control">
                        <option value="none">None (One-time)</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="custom">Custom Schedule</option>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Assigned User</label>
                    <select name="assigned_user_id" class="form-control">
                        <option value="">-- Current Logged-in User --</option>
                        <?php foreach ($staff as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= e($st['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Instructions</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Notes for reminder follow-up..."></textarea>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addRemModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Reminder</button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>
