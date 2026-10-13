<?php
use App\Core\Url;
$d = $defaults ?? [];
$req = $requirements ?? [];
$error = $error ?? '';
$val = static fn(string $key, mixed $fallback = '') => htmlspecialchars((string) ($d[$key] ?? $fallback));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Install · SPS CRM</title>
    <link rel="stylesheet" href="<?= Url::asset('assets/css/app.css') ?>">
    <style>
        body{background:linear-gradient(160deg,#eef5fb,#f8fafc)}
        .wrap{max-width:920px;margin:40px auto;padding:0 16px}
        .card{background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:28px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .grid .full{grid-column:1/-1}
        label{display:block;font-size:12px;font-weight:700;margin-top:8px}
        input,select{width:100%;height:40px;margin-top:6px;border:1px solid var(--border);border-radius:7px;padding:0 12px}
        .checks{display:grid;gap:8px;margin:18px 0}
        .checks div{padding:10px 12px;border-radius:8px;border:1px solid var(--border);display:flex;justify-content:space-between}
        .ok{background:#f0fff4;color:#1b6b3a}.bad{background:#fff5f5;color:#9b2c2c}
        .actions{display:flex;gap:10px;margin-top:22px}
        h1{margin:0 0 6px}p.lead{color:var(--muted);margin-top:0}
        @media(max-width:700px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <small style="color:var(--primary);font-weight:800;letter-spacing:1px">XAMPP / SERVER SETUP</small>
        <h1>Install Syed Professional CRM</h1>
        <p class="lead">Configure database, company details, admin login and deploy paths in one step.</p>

        <?php if ($error !== ''): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="checks">
            <?php foreach ($req as $item): ?>
                <div class="<?= !empty($item['ok']) ? 'ok' : 'bad' ?>">
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <strong><?= htmlspecialchars((string) $item['detail']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?= Url::to('install') ?>">
            <h2>Application</h2>
            <div class="grid">
                <div class="full"><label>App name</label><input name="app_name" value="<?= $val('app_name') ?>" required></div>
                <div><label>App URL</label><input name="app_url" value="<?= $val('app_url') ?>" required></div>
                <div><label>Base path (XAMPP folder)</label><input name="base_path" value="<?= $val('base_path') ?>" placeholder="/syed-professional-crm/public" required></div>
                <div><label>Timezone</label><input name="timezone" value="<?= $val('timezone', 'America/New_York') ?>"></div>
                <div><label>Debug mode</label><select name="app_debug"><option value="1" selected>On (local)</option><option value="0">Off (production)</option></select></div>
            </div>

            <h2>Company</h2>
            <div class="grid">
                <div><label>Company name</label><input name="company_name" value="<?= $val('company_name') ?>"></div>
                <div><label>Company email</label><input name="company_email" type="email" value="<?= $val('company_email') ?>"></div>
                <div><label>Phone</label><input name="company_phone" value="<?= $val('company_phone') ?>"></div>
                <div class="full"><label>Address</label><input name="company_address" value="<?= $val('company_address') ?>"></div>
            </div>

            <h2>MySQL (XAMPP defaults work out of the box)</h2>
            <div class="grid">
                <div><label>Host</label><input name="db_host" value="<?= $val('db_host', '127.0.0.1') ?>" required></div>
                <div><label>Port</label><input name="db_port" value="<?= $val('db_port', '3306') ?>" required></div>
                <div><label>Database name</label><input name="db_database" value="<?= $val('db_database', 'sps_professional_crm') ?>" required></div>
                <div><label>Username</label><input name="db_username" value="<?= $val('db_username', 'root') ?>" required></div>
                <div class="full"><label>Password</label><input name="db_password" value="<?= $val('db_password') ?>" placeholder="Usually blank on XAMPP"></div>
            </div>

            <h2>Admin account</h2>
            <div class="grid">
                <div><label>Name</label><input name="admin_name" value="<?= $val('admin_name', 'Administrator') ?>" required></div>
                <div><label>Email</label><input name="admin_email" type="email" value="<?= $val('admin_email', 'admin@sps.local') ?>" required></div>
                <div class="full"><label>Password</label><input name="admin_password" type="password" value="<?= $val('admin_password', 'admin123') ?>" required></div>
            </div>

            <h2>Mail (optional)</h2>
            <div class="grid">
                <div><label>Mail driver</label><input name="mail_driver" value="<?= $val('mail_driver', 'log') ?>"></div>
                <div><label>From address</label><input name="mail_from" value="<?= $val('mail_from', 'noreply@sps.local') ?>"></div>
            </div>

            <div class="actions">
                <button class="primary" type="submit">Install &amp; Configure CRM</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
