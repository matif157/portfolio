<?php
declare(strict_types=1);

/**
 * Bring an attendance_logs table up to the punch-review schema.
 *
 * Why any of this exists: dedup on re-sync used to key on (device_user_id,
 * punch_time). Correcting a punch changed punch_time, so the device's original
 * timestamp was no longer present in the table and the next sync re-imported the
 * wrong row. Deleting had the same effect. The wrong entries always came back.
 *
 *   device_punch_time  the timestamp EXACTLY as the device reported it, never
 *                      edited — a stable dedup anchor, so a corrected or
 *                      crossed-off punch keeps blocking its own original.
 *   review_status      unreviewed / approved (tick) / rejected (cross). A
 *                      rejected punch stays in the table so it keeps blocking
 *                      re-import, but drops out of every count, report and wage
 *                      figure — the manual entry beside it is what counts.
 *
 * Idempotent — safe to run more than once. Run from the CLI:
 *   php livemachine/tools/migrate_punch_review.php
 *
 * This script only touches STRUCTURE. If the table already holds duplicate rows
 * from syncs that ran while the anchor was missing, clean those up first with
 * tools/dedupe_attendance_logs.php — the unique anchor cannot be created while
 * duplicates are present.
 */

require_once __DIR__ . '/../config/database.php';

$pdo = Database::pdo();
$db  = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$hasColumn = static function (string $table, string $column) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
};

$hasIndex = static function (string $table, string $index) use ($pdo, $db): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND INDEX_NAME = :i'
    );
    $stmt->execute([':db' => $db, ':t' => $table, ':i' => $index]);
    return (int)$stmt->fetchColumn() > 0;
};

$addColumns = static function (string $table, array $clauses) use ($pdo, $hasColumn): void {
    foreach ($clauses as $col => $clause) {
        if ($hasColumn($table, $col)) { echo "  = {$table}.{$col} already present\n"; continue; }
        $pdo->exec("ALTER TABLE `{$table}` {$clause}");
        echo "  + {$table}.{$col}\n";
    }
};

echo "Migrating `{$db}`…\n";

$addColumns('attendance_logs', [
    'device_punch_time' => "ADD COLUMN `device_punch_time` DATETIME NULL AFTER `punch_time`",
    'review_status'     => "ADD COLUMN `review_status` ENUM('unreviewed','approved','rejected') NOT NULL DEFAULT 'unreviewed' AFTER `status`",
    'reviewed_by'       => "ADD COLUMN `reviewed_by` VARCHAR(128) NULL AFTER `review_status`",
    'reviewed_at'       => "ADD COLUMN `reviewed_at` DATETIME NULL AFTER `reviewed_by`",
    'review_note'       => "ADD COLUMN `review_note` VARCHAR(255) NULL AFTER `reviewed_at`",
    'edited'            => "ADD COLUMN `edited` TINYINT(1) NOT NULL DEFAULT 0 AFTER `review_note`",
]);

// Device rows that predate the anchor: their punch_time is still exactly what
// the device reported (nothing had re-stamped it), so it is a sound anchor.
// Rows corrected before this existed were flipped to source='manual' and keep a
// NULL anchor — the device may hand their original back once more, after which
// it can be crossed off for good.
try {
    $backfilled = $pdo->exec(
        "UPDATE `attendance_logs`
            SET `device_punch_time` = `punch_time`
          WHERE `source` = 'device' AND `device_punch_time` IS NULL"
    );
    echo "  + backfilled device_punch_time on {$backfilled} device row(s)\n";
} catch (\PDOException $e) {
    // Two rows share a (user, punch_time) pair, so they cannot both take the
    // same anchor — the table still holds duplicates from anchor-less syncs.
    if (!str_contains($e->getMessage(), '1062')) throw $e;
    echo "  ! backfill skipped — duplicate punches are still in the table.\n"
       . "    Run tools/dedupe_attendance_logs.php first, then re-run this script.\n";
}

// (device_user_id, punch_time) must NOT be unique: a manual correction may land
// on the same wall-clock minute as the rejected punch it replaces. Uniqueness
// belongs on the device anchor alone.
if ($hasIndex('attendance_logs', 'uk_user_time')) {
    $pdo->exec("ALTER TABLE `attendance_logs` DROP INDEX `uk_user_time`");
    $pdo->exec("ALTER TABLE `attendance_logs` ADD INDEX `idx_user_time` (`device_user_id`,`punch_time`)");
    echo "  + relaxed uk_user_time to a plain idx_user_time\n";
}

foreach ([
    'uk_device_log' => "ADD UNIQUE KEY `uk_device_log` (`device_user_id`,`device_punch_time`)",
    'idx_user_time' => "ADD INDEX `idx_user_time` (`device_user_id`,`punch_time`)",
    'idx_review'    => "ADD INDEX `idx_review` (`review_status`,`punch_time`)",
] as $idx => $clause) {
    if ($hasIndex('attendance_logs', $idx)) { echo "  = attendance_logs.{$idx} already present\n"; continue; }
    try {
        $pdo->exec("ALTER TABLE `attendance_logs` {$clause}");
        echo "  + attendance_logs.{$idx}\n";
    } catch (\PDOException $e) {
        if ($idx === 'uk_device_log' && str_contains($e->getMessage(), '1062')) {
            echo "  ! uk_device_log NOT created — the table still holds duplicate device logs.\n"
               . "    Run tools/dedupe_attendance_logs.php first, then re-run this script.\n";
            continue;
        }
        throw $e;
    }
}

$addColumns('sync_history', [
    'device_time'        => "ADD COLUMN `device_time` DATETIME NULL AFTER `completed_at`",
    'clock_skew_seconds' => "ADD COLUMN `clock_skew_seconds` INT NULL AFTER `device_time`",
    'records_skipped'    => "ADD COLUMN `records_skipped` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `records_inserted`",
]);

$pdo->prepare(
    "INSERT INTO settings (`key`,`value`) VALUES ('clock_drift_tolerance_seconds','120')
     ON DUPLICATE KEY UPDATE `value` = `value`"
)->execute();
echo "  + setting clock_drift_tolerance_seconds\n";

echo "\nDone.\n";
