<?php
/**
 * SPS Professional Services CRM
 * Upload Bank Statement PDF Handler & Document Intelligence Parser
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$account_id = intval($_GET['account_id'] ?? $_POST['account_id'] ?? 0);
$prefill_year = intval($_GET['year'] ?? 2026);
$prefill_month = intval($_GET['month'] ?? 1);

$all_accounts = $pdo->query("
    SELECT ba.id, ba.bank_name, ba.account_name, ba.account_number_last4, b.business_name, c.company_name, c.first_name, c.last_name, b.client_id, b.id as business_id
    FROM bank_accounts ba
    JOIN businesses b ON ba.business_id = b.id
    JOIN clients c ON b.client_id = c.id
    WHERE ba.is_active = 1
    ORDER BY b.business_name, ba.bank_name
")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    $target_acc_id = intval($_POST['account_id']);
    $period_year = intval($_POST['period_year']);
    $period_month = intval($_POST['period_month']);
    $notes = trim($_POST['notes'] ?? '');

    // Fetch account info
    $stmtAcc = $pdo->prepare("
        SELECT ba.*, b.client_id, b.id as business_id, b.business_name 
        FROM bank_accounts ba 
        JOIN businesses b ON ba.business_id = b.id 
        WHERE ba.id = ?
    ");
    $stmtAcc->execute([$target_acc_id]);
    $acc_info = $stmtAcc->fetch();

    if (!$acc_info) {
        $errors[] = 'Invalid bank account selected.';
    }

    if (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a valid PDF file to upload.';
    } else {
        $file = $_FILES['statement_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, ALLOWED_FILE_EXT)) {
            $errors[] = 'Invalid file extension. Only PDF, JPG, PNG, and DOC files are allowed.';
        }
        if ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'File size exceeds maximum allowed limit (10MB).';
        }

        if (empty($errors)) {
            // Ensure uploads directory exists
            $upload_dir = __DIR__ . '/uploads/statements';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $safe_filename = "Statement_Acc{$target_acc_id}_{$period_year}_{$period_month}_" . time() . '.' . $ext;
            $target_path = $upload_dir . '/' . $safe_filename;
            $relative_path = 'uploads/statements/' . $safe_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Check if existing statement already uploaded (Duplicate Detection)
                $stmtCheck = $pdo->prepare("SELECT id, status FROM bank_statements WHERE bank_account_id = ? AND period_year = ? AND period_month = ?");
                $stmtCheck->execute([$target_acc_id, $period_year, $period_month]);
                $existing = $stmtCheck->fetch();

                $is_duplicate = ($existing && $existing['status'] === 'received') ? 1 : 0;

                if ($existing) {
                    $stmtUpdate = $pdo->prepare("
                        UPDATE bank_statements 
                        SET status = 'received', file_name = ?, file_path = ?, uploaded_at = NOW(), is_duplicate = ?, notes = ? 
                        WHERE id = ?
                    ");
                    $stmtUpdate->execute([$file['name'], $relative_path, $is_duplicate, $notes, $existing['id']]);
                } else {
                    $stmtIns = $pdo->prepare("
                        INSERT INTO bank_statements (bank_account_id, period_year, period_month, status, file_name, file_path, uploaded_at, is_duplicate, notes)
                        VALUES (?, ?, ?, 'received', ?, ?, NOW(), ?, ?)
                    ");
                    $stmtIns->execute([$target_acc_id, $period_year, $period_month, $file['name'], $relative_path, $is_duplicate, $notes]);
                }

                // Catalogue in central documents repository
                $stmtDoc = $pdo->prepare("
                    INSERT INTO documents (client_id, business_id, title, file_name, file_path, file_size, mime_type, category, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'bank_statement', ?)
                ");
                $month_name = get_month_name($period_month);
                $stmtDoc->execute([
                    $acc_info['client_id'], $acc_info['business_id'],
                    "{$acc_info['bank_name']} Statement - {$month_name} {$period_year}",
                    $safe_filename, $relative_path, $file['size'], $file['type'], $_SESSION['user_id'] ?? null
                ]);

                // AUTOMATION ENGINE CHECK: Check if all missing months are now resolved for this business
                $stmtMissCheck = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM bank_statements bs
                    JOIN bank_accounts ba ON bs.bank_account_id = ba.id
                    WHERE ba.business_id = ? AND bs.status = 'missing'
                ");
                $stmtMissCheck->execute([$acc_info['business_id']]);
                $remaining_missing = $stmtMissCheck->fetchColumn();

                if ($remaining_missing == 0) {
                    // All statements received! Automatically advance client service workflow
                    $pdo->prepare("UPDATE client_services SET current_step_number = 3, status = 'review' WHERE business_id = ? AND service_id = 1")
                        ->execute([$acc_info['business_id']]);
                    
                    // Create staff reconciliation task
                    $pdo->prepare("INSERT INTO tasks (client_id, title, description, status, priority, due_date) VALUES (?, ?, 'All monthly bank statements uploaded. Perform monthly trial balance reconciliation.', 'pending', 'high', DATE_ADD(CURDATE(), INTERVAL 2 DAY))")
                        ->execute([$acc_info['client_id'], "Reconcile Bookkeeping - {$acc_info['business_name']}"]);

                    log_activity('Workflow Auto-Advanced', 'automation', $acc_info['business_id'], "All 100% statements uploaded for {$acc_info['business_name']}. Advanced workflow to Bookkeeping Reconciliation & created staff task.");
                }

                log_activity('Upload Statement', 'bank_statements', $target_acc_id, "Uploaded statement for {$acc_info['bank_name']} (x-{$acc_info['account_number_last4']}) - Month: $month_name $period_year");
                set_flash('success', "Statement for $month_name $period_year uploaded successfully!");
                redirect("bank_statements.php?year=$period_year&business_id={$acc_info['business_id']}");
            } else {
                $errors[] = 'Failed to save uploaded file to disk. Check folder permissions.';
            }
        }
    }
}

render_header('Upload Bank Statement PDF', 'bank_statements');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Upload Bank Statement PDF</h1>
        <p class="page-subtitle">Upload monthly financial statements for automated classification and reconciliation</p>
    </div>
    <div>
        <a href="bank_statements.php" class="btn btn-secondary">← Back to Statement Matrix</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div style="background:#fff5f5; color:#742a2a; border-left:4px solid #e53e3e; padding:12px; margin-bottom:20px; border-radius:4px;">
        <?php foreach ($errors as $err): ?>
            <div>• <?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width:650px; margin:0 auto;">
    <form action="upload_statement.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label">Target Bank Account *</label>
            <select name="account_id" class="form-control" required>
                <option value="">-- Select Bank Account --</option>
                <?php foreach ($all_accounts as $acc): ?>
                    <option value="<?= $acc['id'] ?>" <?= $account_id == $acc['id'] ? 'selected' : '' ?>>
                        <?= e($acc['business_name']) ?> — <?= e($acc['bank_name']) ?> (<?= e($acc['account_name']) ?> x-<?= e($acc['account_number_last4']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label class="form-label">Statement Year *</label>
                <select name="period_year" class="form-control" required>
                    <option value="2026" <?= $prefill_year == 2026 ? 'selected' : '' ?>>2026</option>
                    <option value="2025" <?= $prefill_year == 2025 ? 'selected' : '' ?>>2025</option>
                </select>
            </div>
            <div class="form-col form-group">
                <label class="form-label">Statement Month *</label>
                <select name="period_month" class="form-control" required>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $prefill_month == $m ? 'selected' : '' ?>><?= get_month_name($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Statement Document File (PDF / Images) *</label>
            <input type="file" name="statement_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="padding:6px;">
            <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Supported formats: PDF, JPG, PNG (Max size: 10MB)</div>
        </div>

        <div class="form-group">
            <label class="form-label">Notes / Discrepancy Comments</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes regarding large withdrawals, unclassified deposits, etc."></textarea>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Process Statement Upload</button>
            <a href="bank_statements.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php render_footer(); ?>
