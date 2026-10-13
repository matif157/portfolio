<?php
use App\Core\Url;
ob_start();
$s = $settings ?? [];
$req = $requirements ?? [];
$error = $error ?? '';
$success = $success ?? '';
$val = static fn(string $key, mixed $fallback = '') => htmlspecialchars((string) ($s[$key] ?? $fallback));
?>
<div class="page-head">
    <div>
        <small>DEPLOYMENT</small>
        <h1>Configure CRM</h1>
        <p>Update application, database, company and server paths. Saving rewrites <code>.env</code>, <code>storage/config/local.php</code> and <code>public/.htaccess</code> for the target server.</p>
    </div>
</div>

<?php if ($error !== ''): ?><div class="alert" style="margin-bottom:16px"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="panel" style="margin-bottom:16px;border-color:#9ae6b4;background:#f0fff4;color:#276749"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:18px">
    <?php foreach ($req as $item): ?>
        <div class="panel" style="padding:12px;<?= empty($item['ok']) ? 'border-color:#feb2b2;background:#fff5f5' : '' ?>">
            <small><?= htmlspecialchars($item['label']) ?></small>
            <b style="display:block;margin-top:6px"><?= htmlspecialchars((string) $item['detail']) ?></b>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= Url::to('configure') ?>">
    <section class="panel" style="margin-bottom:16px">
        <h2>Application &amp; deploy path</h2>
        <div class="toolbar" style="flex-wrap:wrap">
            <label style="flex:1 1 220px">App name<input name="app_name" value="<?= $val('app_name') ?>" required></label>
            <label style="flex:1 1 220px">App URL<input name="app_url" value="<?= $val('app_url') ?>" required></label>
            <label style="flex:1 1 220px">Base path<input name="base_path" value="<?= $val('base_path') ?>" required></label>
            <label style="flex:1 1 160px">Timezone<input name="timezone" value="<?= $val('timezone') ?>"></label>
            <label style="flex:0 0 140px">Debug
                <select name="app_debug">
                    <option value="1" <?= !empty($s['app_debug']) ? 'selected' : '' ?>>On</option>
                    <option value="0" <?= empty($s['app_debug']) ? 'selected' : '' ?>>Off</option>
                </select>
            </label>
            <label style="flex:0 0 160px">Secure cookies
                <select name="session_secure">
                    <option value="0" <?= empty($s['session_secure']) ? 'selected' : '' ?>>No (HTTP/XAMPP)</option>
                    <option value="1" <?= !empty($s['session_secure']) ? 'selected' : '' ?>>Yes (HTTPS)</option>
                </select>
            </label>
        </div>
    </section>

    <section class="panel" style="margin-bottom:16px">
        <h2>Company</h2>
        <div class="toolbar" style="flex-wrap:wrap">
            <label style="flex:1 1 220px">Name<input name="company_name" value="<?= $val('company_name') ?>"></label>
            <label style="flex:1 1 220px">Email<input name="company_email" type="email" value="<?= $val('company_email') ?>"></label>
            <label style="flex:1 1 160px">Phone<input name="company_phone" value="<?= $val('company_phone') ?>"></label>
            <label style="flex:1 1 100%">Address<input name="company_address" value="<?= $val('company_address') ?>"></label>
        </div>
    </section>

    <section class="panel" style="margin-bottom:16px">
        <h2>Database</h2>
        <div class="toolbar" style="flex-wrap:wrap">
            <label style="flex:1 1 160px">Host<input name="db_host" value="<?= $val('db_host') ?>" required></label>
            <label style="flex:1 1 100px">Port<input name="db_port" value="<?= $val('db_port') ?>" required></label>
            <label style="flex:1 1 160px">Database<input name="db_database" value="<?= $val('db_database') ?>" required></label>
            <label style="flex:1 1 140px">Username<input name="db_username" value="<?= $val('db_username') ?>" required></label>
            <label style="flex:1 1 160px">Password<input name="db_password" value="<?= $val('db_password') ?>"></label>
        </div>
        <div class="toolbar">
            <button type="submit" name="action" value="test_db">Test database</button>
        </div>
    </section>

    <section class="panel" style="margin-bottom:16px">
        <h2>Admin account</h2>
        <div class="toolbar" style="flex-wrap:wrap">
            <label style="flex:1 1 180px">Name<input name="admin_name" value="<?= $val('admin_name', 'Administrator') ?>"></label>
            <label style="flex:1 1 220px">Email<input name="admin_email" type="email" value="<?= $val('admin_email') ?>"></label>
            <label style="flex:1 1 220px">New password <small style="font-weight:400;color:var(--muted)">(leave blank to keep)</small><input name="admin_password" type="password" value="" placeholder="••••••••"></label>
        </div>
    </section>

    <section class="panel" style="margin-bottom:16px">
        <h2>Mail</h2>
        <div class="toolbar" style="flex-wrap:wrap">
            <label style="flex:1 1 160px">Driver<input name="mail_driver" value="<?= $val('mail_driver', 'log') ?>"></label>
            <label style="flex:1 1 220px">From<input name="mail_from" value="<?= $val('mail_from') ?>"></label>
        </div>
    </section>

    <button class="primary" type="submit" name="action" value="save">Save configuration</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';
