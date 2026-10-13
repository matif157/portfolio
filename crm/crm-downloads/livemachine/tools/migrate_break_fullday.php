<?php
declare(strict_types=1);

/**
 * One-time settings migration for the new attendance/payroll rules:
 *   - break_minutes        -> 45  (compulsory break; longer actual break deducted in full)
 *   - full_day_hours       -> 6   (net worked hours above this = full day)  [insert if missing]
 *   - half_day_threshold   -> removed (half-day is no longer clock-time based)
 *   - break_threshold_hours-> removed (break is always compulsory)
 *
 * Idempotent — safe to run more than once. Run from the CLI:
 *   php livemachine/tools/migrate_break_fullday.php
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::pdo();

echo "Before:\n";
foreach ($pdo->query("SELECT `key`,`value` FROM settings ORDER BY `key`") as $r) {
    echo "  {$r['key']} = {$r['value']}\n";
}

// Compulsory break is now 45 minutes.
$pdo->prepare("UPDATE settings SET `value`='45' WHERE `key`='break_minutes'")->execute();

// Seed the new full-day threshold only if it is not already set.
$pdo->prepare(
    "INSERT INTO settings (`key`,`value`) VALUES ('full_day_hours','6')
     ON DUPLICATE KEY UPDATE `value` = `value`"
)->execute();

// Retire the keys that no longer drive any logic.
$pdo->prepare("DELETE FROM settings WHERE `key` IN ('half_day_threshold','break_threshold_hours')")->execute();

echo "\nAfter:\n";
foreach ($pdo->query("SELECT `key`,`value` FROM settings ORDER BY `key`") as $r) {
    echo "  {$r['key']} = {$r['value']}\n";
}

echo "\nDone.\n";
