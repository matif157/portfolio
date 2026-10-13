<?php
/**
 * SPS Professional Services CRM
 * Register & Manage Business Bank Accounts
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$business_id = intval($_GET['business_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_account') {
    verify_csrf();
    $target_bus_id = intval($_POST['business_id']);
    $bank_name = trim($_POST['bank_name']);
    $account_name = trim($_POST['account_name']);
    $last4 = trim($_POST['account_number_last4']);
    $account_type = $_POST['account_type'] ?? 'checking';

    if (!empty($bank_name) && !empty($last4) && $target_bus_id > 0) {
        $stmt = $pdo->prepare("INSERT INTO bank_accounts (business_id, bank_name, account_name, account_number_last4, account_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$target_bus_id, $bank_name, $account_name, $last4, $account_type]);
        $acc_id = $pdo->lastInsertId();

        // Auto initialize 12 months for 2026 for this account
        $stmtM = $pdo->prepare("INSERT IGNORE INTO bank_statements (bank_account_id, period_year, period_month, status) VALUES (?, 2026, ?, 'missing')");
        for ($m = 1; $m <= 12; $m++) {
            $stmtM->execute([$acc_id, $m]);
        }

        log_activity('Add Bank Account', 'bank_accounts', $acc_id, "Registered bank account $bank_name (x-$last4) and generated 2026 statement checklist.");
        set_flash('success', "Bank Account '$bank_name (x-$last4)' registered and statement checklist initialized!");
        redirect("bank_statements.php?business_id=$target_bus_id");
    }
}

$query = "
    SELECT ba.*, b.business_name, c.first_name, c.last_name, c.company_name
    FROM bank_accounts ba
    JOIN businesses b ON ba.business_id = b.id
    JOIN clients c ON b.client_id = c.id
    WHERE 1=1
";
$params = [];
if ($business_id > 0) {
    $query .= " AND ba.business_id = ?";
    $params[] = $business_id;
}
$query .= " ORDER BY ba.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$all_businesses = $pdo->query("SELECT id, business_name FROM businesses ORDER BY business_name")->fetchAll();

render_header('Bank Accounts Management', 'bank_statements');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Business Bank Accounts</h1>
        <p class="page-subtitle">Registered checking, savings, and credit card accounts for monthly statement collection</p>
    </div>
    <div>
        <button onclick="document.getElementById('addAccModal').style.display = 'block'" class="btn btn-primary">+ Register New Bank Account</button>
        <a href="bank_statements.php" class="btn btn-secondary">⚡ Bank Statement Matrix</a>
    </div>
</div>

<div id="addAccModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:80px auto;">
        <div class="card-title">
            <span>Register Bank Account</span>
            <button onclick="document.getElementById('addAccModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="bank_accounts.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_account">

            <div class="form-group">
                <label class="form-label">Business Owner *</label>
                <select name="business_id" class="form-control" required>
                    <option value="">-- Select Business Entity --</option>
                    <?php foreach ($all_businesses as $bus): ?>
                        <option value="<?= $bus['id'] ?>" <?= $business_id == $bus['id'] ? 'selected' : '' ?>><?= e($bus['business_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Financial Institution / Bank Name *</label>
                <input type="text" name="bank_name" class="form-control" placeholder="Chase, Bank of America, Wells Fargo" required>
            </div>

            <div class="form-group">
                <label class="form-label">Account Label / Title *</label>
                <input type="text" name="account_name" class="form-control" placeholder="Operating Checking Account" required>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Last 4 Digits of Account # *</label>
                    <input type="text" name="account_number_last4" maxlength="4" class="form-control" placeholder="4829" required>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Account Type</label>
                    <select name="account_type" class="form-control">
                        <option value="checking">Operating Checking</option>
                        <option value="savings">Business Savings</option>
                        <option value="credit_card">Corporate Credit Card</option>
                        <option value="merchant">Merchant Account</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addAccModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Register Account</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Bank Name</th>
                    <th>Account Name</th>
                    <th>Last 4</th>
                    <th>Type</th>
                    <th>Business Owner</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No bank accounts registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($accounts as $a): ?>
                        <tr>
                            <td><strong><?= e($a['bank_name']) ?></strong></td>
                            <td><?= e($a['account_name']) ?></td>
                            <td><code>x-<?= e($a['account_number_last4']) ?></code></td>
                            <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $a['account_type'])) ?></span></td>
                            <td>
                                <strong><?= e($a['business_name']) ?></strong><br>
                                <span style="font-size:12px; color:var(--text-muted);"><?= e($a['company_name'] ?: "{$a['first_name']} {$a['last_name']}") ?></span>
                            </td>
                            <td><span class="badge badge-success"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <a href="bank_statements.php?account_id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">Statement Matrix</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
