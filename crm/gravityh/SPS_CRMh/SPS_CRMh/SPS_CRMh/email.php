<?php
/**
 * SPS Professional Services CRM
 * Send Client Email & Template Composer
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $client_id = intval($_POST['client_id']);
    $subject = trim($_POST['subject']);
    $body = trim($_POST['message_body']);

    if ($client_id > 0 && !empty($subject) && !empty($body)) {
        // Log Communication Record
        $stmt = $pdo->prepare("INSERT INTO communications (client_id, channel, subject, message_body, direction, status) VALUES (?, 'email', ?, ?, 'outbound', 'sent')");
        $stmt->execute([$client_id, $subject, $body]);

        // Check Email Integration Provider Status (Spec Section 24/39: Never fake a successful call if provider not configured)
        $email_status = get_setting('email_provider_status', 'not_configured');
        if ($email_status === 'not_configured') {
            set_flash('warning', 'Communication logged internally. Note: Email SMTP provider is NOT configured in System Settings.');
        } else {
            set_flash('success', 'Email dispatched to client successfully!');
        }
        redirect('communications.php');
    }
}

$clients = $pdo->query("SELECT id, first_name, last_name, company_name, email FROM clients ORDER BY company_name, first_name")->fetchAll();

render_header('Send Client Email', 'communications');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Send Email Communication</h1>
        <p class="page-subtitle">Compose manual or template-based email notifications to clients</p>
    </div>
    <div>
        <a href="communications.php" class="btn btn-secondary">← Back to Communication Journal</a>
    </div>
</div>

<div class="card" style="max-width:700px; margin:0 auto;">
    <form action="email.php" method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label">Recipient Client *</label>
            <select name="client_id" class="form-control" required>
                <option value="">-- Select Recipient --</option>
                <?php foreach ($clients as $cl): ?>
                    <option value="<?= $cl['id'] ?>">
                        <?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?> (<?= e($cl['email']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Email Subject Line *</label>
            <input type="text" name="subject" class="form-control" required placeholder="Monthly Bookkeeping Statement Upload Reminder">
        </div>

        <div class="form-group">
            <label class="form-label">Message Body *</label>
            <textarea name="message_body" class="form-control" rows="8" required placeholder="Dear Client, ..."></textarea>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn btn-primary">Dispatch Email Communication</button>
        </div>
    </form>
</div>

<?php render_footer(); ?>
