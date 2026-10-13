<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$checks = [];
$checks['PHP version (8.0+)'] = version_compare(PHP_VERSION, '8.0.0', '>=');
try {
    $pdo = db();
    $checks['Database connection'] = true;
    $checks['users table'] = (bool)$pdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();
    $checks['clients table'] = (bool)$pdo->query('SELECT 1 FROM clients LIMIT 1')->fetchColumn();
    $checks['activities table'] = (bool)$pdo->query('SELECT 1 FROM activities LIMIT 1')->fetchColumn();
    $checks['roles table'] = (bool)$pdo->query('SELECT 1 FROM roles LIMIT 1')->fetchColumn();
    $checks['Phase 2 tables'] = phase2_ready($pdo);
    $checks['Phase 3 tables'] = phase3_ready($pdo);
    $checks['Phase 4 tables'] = table_exists($pdo, 'payments') && table_exists($pdo, 'documents');
    $checks['Phase 5 tables'] = table_exists($pdo, 'quotations') && table_exists($pdo, 'settings');
    $checks['Phase 6 tables'] = table_exists($pdo, 'password_resets') && table_exists($pdo, 'approvals');
    $checks['Login password hashing'] = function_exists('password_verify') && function_exists('password_hash');
} catch (Throwable $e) {
    $checks['Database connection'] = false;
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SPS CRM Health Check</title>
<style>body{font-family:Arial;background:#f5f7fb;padding:30px;color:#172033}.box{max-width:700px;margin:auto;background:#fff;padding:25px;border-radius:15px}.item{display:flex;justify-content:space-between;padding:12px;border-bottom:1px solid #eee}.ok{color:#16804a;font-weight:bold}.bad{color:#b33131;font-weight:bold}code{background:#eef2f7;padding:3px 6px;border-radius:4px}</style></head><body><div class="box"><h1>SPS CRM Health Check</h1><p>Use this page after importing <code>crm.sql</code>. Delete or protect this file in production.</p>
<?php foreach ($checks as $name=>$ok): ?><div class="item"><span><?= e($name) ?></span><span class="<?= $ok?'ok':'bad' ?>"><?= $ok?'PASS':'FAIL' ?></span></div><?php endforeach; ?>
<p><a href="index.php">Open CRM</a></p></div></body></html>
