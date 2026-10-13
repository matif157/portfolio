<?php
/**
 * SPS Professional Services CRM
 * BANK STATEMENT COLLECTION & MONTHLY BOOKKEEPING PREPARATION (FIRST AUTOMATED SERVICE)
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$selected_year = intval($_GET['year'] ?? 2026);
$business_id = intval($_GET['business_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);

// Handle Automated Reminder Dispatch Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reminder') {
    verify_csrf();
    $target_account_id = intval($_POST['account_id']);
    
    // Fetch account details & client
    $stmtAcc = $pdo->prepare("
        SELECT ba.*, b.business_name, c.id as client_id, c.first_name, c.last_name, c.email 
        FROM bank_accounts ba
        JOIN businesses b ON ba.business_id = b.id
        JOIN clients c ON b.client_id = c.id
        WHERE ba.id = ?
    ");
    $stmtAcc->execute([$target_account_id]);
    $acc_info = $stmtAcc->fetch();

    if ($acc_info) {
        // Fetch missing months for selected year
        $stmtMiss = $pdo->prepare("SELECT period_month FROM bank_statements WHERE bank_account_id = ? AND period_year = ? AND status = 'missing' ORDER BY period_month ASC");
        $stmtMiss->execute([$target_account_id, $selected_year]);
        $missing_months = $stmtMiss->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($missing_months)) {
            $month_names = array_map('get_month_name', $missing_months);
            $months_str = implode(', ', $month_names);
            
            $subject = "Action Required: Missing {$selected_year} Bank Statements for {$acc_info['business_name']}";
            $body = "Hello {$acc_info['first_name']},\n\nWe are preparing your monthly bookkeeping service for {$acc_info['business_name']}.\n\nWe still require bank statement uploads for the following period(s):\n• Months: {$months_str} {$selected_year}\n• Bank: {$acc_info['bank_name']}\n• Account ending: {$acc_info['account_number_last4']}\n\nPlease upload the PDF statements through your client portal or reply directly to this email.\n\nThank you,\nSPS Professional Services Team";

            // Log Outbound Multi-channel Communication
            $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status) VALUES (?, 'email', ?, ?, 'outbound', 'sent')")
                ->execute([$acc_info['client_id'], $subject, $body]);

            // Create Staff Followup Task
            $pdo->prepare("INSERT INTO tasks (client_id, title, description, status, priority, due_date) VALUES (?, ?, ?, 'pending', 'medium', DATE_ADD(CURDATE(), INTERVAL 2 DAY))")
                ->execute([$acc_info['client_id'], "Follow up: Missing {$selected_year} Statements", "Sent email reminder to {$acc_info['first_name']} for {$acc_info['business_name']} ({$acc_info['bank_name']} x-{$acc_info['account_number_last4']})."]);

            log_activity('Dispatched Statement Reminder', 'bank_statements', $target_account_id, "Generated automated email reminder to {$acc_info['email']} for missing months: $months_str");
            set_flash('success', "Automated reminder dispatched to {$acc_info['email']} for missing months ({$months_str})!");
        } else {
            set_flash('info', 'No missing months found for this account.');
        }
    }
    redirect("bank_statements.php?year=$selected_year" . ($business_id ? "&business_id=$business_id" : ""));
}

// Fetch Accounts List matching filters
$query = "
    SELECT ba.*, b.business_name, c.id as client_id, c.first_name, c.last_name, c.company_name
    FROM bank_accounts ba
    JOIN businesses b ON ba.business_id = b.id
    JOIN clients c ON b.client_id = c.id
    WHERE ba.is_active = 1
";
$params = [];
if ($business_id > 0) {
    $query .= " AND ba.business_id = ?";
    $params[] = $business_id;
}
if ($client_id > 0) {
    $query .= " AND c.id = ?";
    $params[] = $client_id;
}
$query .= " ORDER BY b.business_name, ba.bank_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

// Fetch Statement Matrix Data for selected year
$matrix = [];
if (!empty($accounts)) {
    $acc_ids = array_column($accounts, 'id');
    $in_clause = implode(',', array_fill(0, count($acc_ids), '?'));
    $stmtM = $pdo->prepare("SELECT * FROM bank_statements WHERE bank_account_id IN ($in_clause) AND period_year = ?");
    $stmtM->execute(array_merge($acc_ids, [$selected_year]));
    $raw_matrix = $stmtM->fetchAll();

    foreach ($raw_matrix as $row) {
        $matrix[$row['bank_account_id']][$row['period_month']] = $row;
    }
}

// Overall Status Metrics for Selected Year
$total_missing = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE period_year = $selected_year AND status = 'missing'")->fetchColumn();
$total_received = $pdo->query("SELECT COUNT(*) FROM bank_statements WHERE period_year = $selected_year AND status IN ('received', 'verified')")->fetchColumn();

render_header('Bank Statement Collection Matrix', 'bank_statements');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Bank Statement Collection Matrix (<?= $selected_year ?>)</h1>
        <p class="page-subtitle">Automated monthly statement collection engine for SPS Bookkeeping & Reconciliation Services</p>
    </div>
    <div>
        <a href="upload_statement.php" class="btn btn-primary">📤 Upload Statement PDF</a>
        <a href="bank_accounts.php" class="btn btn-secondary">+ Register Bank Account</a>
    </div>
</div>

<!-- Statement Automation Overview Bar -->
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-label">Monitored Accounts</span>
        <span class="stat-value"><?= count($accounts) ?></span>
        <span class="stat-desc">Registered business accounts</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Received Statements</span>
        <span class="stat-value" style="color:var(--success);"><?= number_format($total_received) ?></span>
        <span class="stat-desc">Verified & uploaded months</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Missing Statements</span>
        <span class="stat-value" style="color:var(--danger);"><?= number_format($total_missing) ?></span>
        <span class="stat-desc">Requires client reminder</span>
    </div>

    <div class="stat-card">
        <span class="stat-label">Automation Dispatch</span>
        <a href="service_automation.php" class="btn btn-sm btn-primary" style="margin-top:10px;">Run Auto-Scanner ⚡</a>
    </div>
</div>

<!-- Year & Filter Selector Bar -->
<div class="card">
    <form method="GET" action="bank_statements.php" style="display:flex; gap:16px; align-items:flex-end;">
        <div>
            <label class="form-label">Fiscal Year</label>
            <select name="year" class="form-control" onchange="this.form.submit()">
                <option value="2026" <?= $selected_year == 2026 ? 'selected' : '' ?>>2026 Fiscal Year</option>
                <option value="2025" <?= $selected_year == 2025 ? 'selected' : '' ?>>2025 Fiscal Year</option>
            </select>
        </div>

        <div>
            <button type="submit" class="btn btn-secondary">Filter Matrix</button>
            <a href="bank_statements.php" class="btn btn-secondary">Reset Filters</a>
        </div>
    </form>
</div>

<!-- Statement Collection Matrix Grid -->
<?php if (empty($accounts)): ?>
    <div class="card" style="text-align:center; padding:40px;">
        <h3>No active bank accounts found.</h3>
        <p style="color:var(--text-muted); margin:10px 0;">Please register business bank accounts to begin automated statement tracking.</p>
        <a href="bank_accounts.php" class="btn btn-primary">+ Register Bank Account</a>
    </div>
<?php else: ?>
    <?php foreach ($accounts as $acc): ?>
        <?php
        $acc_missing_count = 0;
        for ($m = 1; $m <= 12; $m++) {
            $st = $matrix[$acc['id']][$m]['status'] ?? 'missing';
            if ($st === 'missing') $acc_missing_count++;
        }
        ?>
        <div class="card">
            <div class="card-title" style="margin-bottom:12px;">
                <div>
                    <strong style="font-size:16px; color:var(--secondary);"><?= e($acc['business_name']) ?></strong> — 
                    <span style="font-weight:500; color:var(--text-muted);"><?= e($acc['bank_name']) ?> (<?= e($acc['account_name']) ?> x-<?= e($acc['account_number_last4']) ?>)</span>
                </div>
                <div>
                    <?php if ($acc_missing_count > 0): ?>
                        <form action="bank_statements.php?year=<?= $selected_year ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send_reminder">
                            <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning">🔔 Send Automated Reminder (<?= $acc_missing_count ?> Missing)</button>
                        </form>
                    <?php else: ?>
                        <span class="badge badge-success">✓ 100% Statements Complete</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 12 Months Grid Bar -->
            <div class="matrix-grid">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <?php
                    $m_name = date('M', mktime(0, 0, 0, $m, 1));
                    $item = $matrix[$acc['id']][$m] ?? ['status' => 'missing'];
                    $cellClass = $item['status'];
                    ?>
                    <div class="matrix-cell <?= $cellClass ?>">
                        <div style="font-size:11px; text-transform:uppercase; margin-bottom:4px; font-weight:700;"><?= $m_name ?></div>
                        <?php if ($item['status'] === 'received' || $item['status'] === 'verified'): ?>
                            <div style="font-size:14px; margin-bottom:2px;">✓</div>
                            <a href="upload_statement.php?account_id=<?= $acc['id'] ?>&year=<?= $selected_year ?>&month=<?= $m ?>" style="font-size:10px; color:#15803d;">View PDF</a>
                        <?php else: ?>
                            <div style="font-size:14px; margin-bottom:2px;">✗</div>
                            <a href="upload_statement.php?account_id=<?= $acc['id'] ?>&year=<?= $selected_year ?>&month=<?= $m ?>" style="font-size:10px; color:#b91c1c; text-decoration:underline;">+ Upload</a>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php render_footer(); ?>
