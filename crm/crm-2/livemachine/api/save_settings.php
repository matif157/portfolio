<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Auth::requireCsrf();
$in = api_input();

$cfg = DeviceConfig::load();
$cfg->name     = trim((string)($in['device_name'] ?? $cfg->name));
$cfg->ip       = trim((string)($in['device_ip']   ?? $cfg->ip));
$cfg->port     = (int)($in['device_port']     ?? $cfg->port);
$cfg->password = (int)($in['device_password'] ?? $cfg->password);
$cfg->brand    = trim((string)($in['device_brand'] ?? $cfg->brand));
$cfg->save();

$pdo = Database::pdo();
foreach ([
    'office_in_time','office_out_time','late_threshold','full_day_hours','auto_sync_seconds',
    'break_minutes','meal_break_minutes','paid_leave_hours','currency_symbol',
] as $k) {
    if (!isset($in[$k])) continue;
    // NB: distinct placeholders for the two value slots — with
    // PDO::ATTR_EMULATE_PREPARES=false a named param can't be reused.
    $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (:k,:v)
                   ON DUPLICATE KEY UPDATE `value` = :v2')
        ->execute([':k' => $k, ':v' => (string)$in[$k], ':v2' => (string)$in[$k]]);
}

api_ok(['saved' => true]);
