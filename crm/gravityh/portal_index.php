<?php
/**
 * GRAVITY CRM - Client Portal Dashboard (SPS 2040 Phase 8, Sec 26)
 * Same core data, client-permission scope.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/core/portal.php';

portal_require_login();
$pu = portal_user();

$flash_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post('action');
    if ($action === 'upload') {
        $res = portal_handle_upload();
        $flash_msg = $res['message'];
    } elseif ($action === 'message') {
        $msg = trim((string)post('message'));
        if ($msg !== '' && portal_message($msg)) {
            $flash_msg = 'Message sent. Thank you.';
        } else {
            $flash_msg = 'Could not send message.';
        }
    }
    header('Location: portal_index.php' . ($flash_msg ? '?msg=' . urlencode($flash_msg) : ''));
    exit;
}

if (isset($_GET['msg'])) { $flash_msg = (string)$_GET['msg']; }

$businesses = portal_businesses();
$services   = portal_services();
$requirements = portal_requirements();
$invoices   = portal_invoices();
$documents  = portal_documents();

$total_balance = 0.0;
foreach ($invoices as $iv) { if (in_array($iv['status'], ['sent','partial','overdue'], true)) { $total_balance += (float)$iv['balance_due']; } }

function portal_status_color(string $s): string {
    $map = ['active'=>'#15803d','in_progress'=>'#1d4ed8','not_started'=>'#ca8a04','requested'=>'#b45309','pending_client'=>'#b45309','received'=>'#15803d','under_review'=>'#1d4ed8','sent'=>'#b45309','paid'=>'#15803d','overdue'=>'#b91c1c','partial'=>'#b45309','cancelled'=>'#6b7280'];
    return $map[$s] ?? '#4b5563';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Portal - <?= e($pu['display_name'] ?: ($pu['first_name'] . ' ' . $pu['last_name'])) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
    body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f8fafc;}
    .navbar-brand{font-weight:700;}
    .card{border:none;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;}
    .badge{font-size:.72rem;}
</style>
</head>
<body>
<nav class="navbar navbar-expand navbar-dark" style="background:#0f172a;">
  <div class="container-fluid">
    <a class="navbar-brand" href="portal_index.php"><i class="bi bi-house-lock"></i> <?= e(get_setting('app_name', APP_NAME)) ?> - Client Portal</a>
    <div class="navbar-nav ms-auto">
      <span class="navbar-text me-3"><?= e($pu['display_name'] ?: ($pu['first_name'] . ' ' . $pu['last_name'])) ?></span>
      <a class="btn btn-outline-light btn-sm" href="portal_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>
<div class="container-fluid py-4">
<?php if ($flash_msg): ?><div class="alert alert-info py-2"><?= e($flash_msg) ?></div><?php endif; ?>

<div class="row">
<div class="col-md-4">
    <div class="card"><div class="card-body">
        <h6 class="text-muted">BUSINESSES</h6>
        <?php if (!$businesses): ?><span class="text-muted">None</span><?php else: ?>
        <ul class="list-unstyled mb-0"><?php foreach ($businesses as $b): ?>
            <li><i class="bi bi-briefcase"></i> <?= e($b['name'] ?? $b['business_name']) ?></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </div></div>
    <div class="card"><div class="card-body">
        <h6 class="text-muted">BALANCE DUE</h6>
        <h3 style="color:#b91c1c;"><?= format_currency($total_balance) ?></h3>
    </div></div>
    <div class="card"><div class="card-body">
        <h6 class="text-muted">OTHERS / FUNDING REQUIRED</h6>
        <span class="text-muted"><?= count($requirements) ?> outstanding request(s)</span>
    </div></div>
</div>

<div class="col-md-8">
    <div class="card"><div class="card-body">
        <h6 class="text-muted">ACTIVE SERVICES</h6>
        <?php if (!$services): ?><span class="text-muted">No active services.</span><?php else: ?>
        <table class="table table-sm" style="font-size:.9rem;">
        <thead><tr><th>Service</th><th>Status</th><th>Started</th></tr></thead>
        <tbody><?php foreach ($services as $s): ?>
        <tr>
            <td><?= e($s['service']) ?></td>
            <td><span class="badge" style="background:<?= portal_status_color($s['status']) ?>;"><?= e($s['status']) ?></span></td>
            <td><?= e(format_date($s['start_date'])) ?></td>
        </tr>
        <?php endforeach; ?></tbody></table><?php endif; ?>
    </div></div>

    <div class="card"><div class="card-body">
        <h6 class="text-muted">DOCUMENTS WE NEED FROM YOU</h6>
        <?php if (!$requirements): ?><span class="text-muted">Nothing outstanding. 🎉</span><?php else: ?>
        <ul class="list-unstyled mb-0"><?php foreach ($requirements as $r): ?>
            <li class="mb-1"><span class="badge" style="background:<?= portal_status_color($r['status']) ?>;"><?= e($r['status']) ?></span>
                <strong><?= e($r['title']) ?></strong> <span class="text-muted">(<?= e($r['service']) ?>)</span></li>
        <?php endforeach; ?></ul><?php endif; ?>
    </div></div>

    <div class="card"><div class="card-body">
        <h6 class="text-muted">INVOICES</h6>
        <table class="table table-sm" style="font-size:.9rem;">
        <thead><tr><th>#</th><th>Date</th><th>Due</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
        <tbody><?php foreach ($invoices as $iv): ?>
        <tr>
            <td><?= e($iv['invoice_number']) ?></td>
            <td><?= e(format_date($iv['invoice_date'])) ?></td>
            <td><?= e(format_date($iv['due_date'])) ?></td>
            <td><?= format_currency($iv['total_amount']) ?></td>
            <td><?= format_currency($iv['balance_due']) ?></td>
            <td><span class="badge" style="background:<?= portal_status_color($iv['status']) ?>;"><?= e($iv['status']) ?></span></td>
        </tr>
        <?php endforeach; ?></tbody></table>
    </div></div>

    <div class="row">
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h6 class="text-muted">UPLOAD A DOCUMENT</h6>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload">
                    <input type="file" name="document" class="form-control form-control-sm" required>
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-upload"></i> Upload</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card"><div class="card-body">
                <h6 class="text-muted">SEND A MESSAGE</h6>
                <form method="POST">
                    <input type="hidden" name="<?= e(CSRF_TOKEN_NAME) ?>" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="message">
                    <textarea name="message" class="form-control form-control-sm" rows="2" required placeholder="Ask us anything..."></textarea>
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-send"></i> Send</button>
                </form>
            </div></div>
        </div>
    </div>

    <?php if ($documents): ?>
    <div class="card"><div class="card-body">
        <h6 class="text-muted">YOUR DOCUMENT VAULT</h6>
        <table class="table table-sm" style="font-size:.9rem;">
        <thead><tr><th>Title</th><th>Type</th><th>Size</th><th>Uploaded</th></tr></thead>
        <tbody><?php foreach ($documents as $d): ?>
        <tr>
            <td><?= e($d['title']) ?></td>
            <td><?= e($d['file_type']) ?></td>
            <td><?= e(format_filesize((int)$d['file_size'])) ?></td>
            <td><?= e(format_date($d['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?></tbody></table>
    </div></div>
    <?php endif; ?>
</div>
</div>
</div>
</body>
</html>