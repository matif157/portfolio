<?php
declare(strict_types=1);

/**
 * Remove duplicate punch rows left behind by syncs that ran without a working
 * dedup anchor, and backfill the anchor so it cannot happen again.
 *
 * How the duplicates got there: uniqueness sits on (device_user_id,
 * device_punch_time), but rows inserted by the older sync code left
 * device_punch_time NULL — and SQL lets a unique index hold unlimited NULLs.
 * So every sync re-imported the device's entire buffer as brand-new rows, and
 * hours, reports and payroll counted each punch several times over.
 *
 * What this keeps: one row per (device_user_id, punch_time), preferring
 *   1. a row someone reviewed or hand-corrected (that judgement is not repeatable)
 *   2. a row that already carries the device anchor
 *   3. a manual entry over a device one (it was typed in deliberately)
 *   4. failing all that, the oldest row
 *
 * Nothing is deleted until the whole table has been copied to a backup table,
 * and a dry run is the default. Run from the CLI:
 *   php livemachine/tools/dedupe_attendance_logs.php          # report only
 *   php livemachine/tools/dedupe_attendance_logs.php --apply  # back up, then delete
 */

require_once __DIR__ . '/../config/database.php';

$apply = in_array('--apply', $argv, true);
$pdo   = Database::pdo();

$total = (int)$pdo->query('SELECT COUNT(*) FROM attendance_logs')->fetchColumn();
echo "attendance_logs holds {$total} rows.\n";

// Pull only the columns the keep/drop decision needs.
$rows = $pdo->query(
    'SELECT id, device_user_id, punch_time, device_punch_time, source, review_status, edited
       FROM attendance_logs
      ORDER BY device_user_id, punch_time, id'
)->fetchAll();

/** Higher score wins; ties fall back to the lowest id. */
$score = static function (array $r): int {
    $s = 0;
    if (($r['review_status'] ?? 'unreviewed') !== 'unreviewed') $s += 8;
    if (!empty($r['edited']))                                   $s += 4;
    if (!empty($r['device_punch_time']))                        $s += 2;
    if (($r['source'] ?? '') === 'manual')                      $s += 1;
    return $s;
};

$best = [];   // group key => row kept so far
$drop = [];   // ids to delete
foreach ($rows as $r) {
    $key = $r['device_user_id'] . "\0" . $r['punch_time'];
    if (!isset($best[$key])) { $best[$key] = $r; continue; }

    $incumbent = $best[$key];
    if ($score($r) > $score($incumbent)) {
        $drop[]      = (int)$incumbent['id'];
        $best[$key]  = $r;
    } else {
        $drop[] = (int)$r['id'];
    }
}

$groups = count($best);
$dupes  = count($drop);
printf("%d distinct punches, %d duplicate row(s) to remove (%.0f%% of the table).\n",
    $groups, $dupes, $total > 0 ? $dupes / $total * 100 : 0);

$missingAnchor = 0;
foreach ($best as $r) {
    if ($r['source'] === 'device' && empty($r['device_punch_time'])) $missingAnchor++;
}
echo "{$missingAnchor} surviving device row(s) still need a device_punch_time anchor.\n";

if (!$dupes && !$missingAnchor) { echo "\nNothing to do.\n"; exit; }

if (!$apply) {
    echo "\nDry run — nothing changed. Re-run with --apply to back up and repair.\n";
    exit;
}

// ── apply ──────────────────────────────────────────────────────────────────
$backup = 'attendance_logs_backup_' . date('Ymd_His');
$pdo->exec("CREATE TABLE `{$backup}` AS SELECT * FROM `attendance_logs`");
$backedUp = (int)$pdo->query("SELECT COUNT(*) FROM `{$backup}`")->fetchColumn();
if ($backedUp !== $total) {
    fwrite(STDERR, "Backup holds {$backedUp} of {$total} rows — aborting without deleting anything.\n");
    exit(1);
}
echo "Backed up {$total} rows to `{$backup}`.\n";

$pdo->beginTransaction();
try {
    // Delete in chunks so the IN() list stays a sane size.
    $deleted = 0;
    foreach (array_chunk($drop, 500) as $chunk) {
        $in   = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE id IN ({$in})");
        $stmt->execute($chunk);
        $deleted += $stmt->rowCount();
    }

    // With one row per punch left, the anchor can be filled in safely.
    $anchored = $pdo->exec(
        "UPDATE attendance_logs
            SET device_punch_time = punch_time
          WHERE source = 'device' AND device_punch_time IS NULL"
    );

    $pdo->commit();
    echo "Deleted {$deleted} duplicate row(s); anchored {$anchored} device row(s).\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Failed, rolled back: {$e->getMessage()}\n");
    fwrite(STDERR, "The table is unchanged; `{$backup}` can be dropped.\n");
    exit(1);
}

$now = (int)$pdo->query('SELECT COUNT(*) FROM attendance_logs')->fetchColumn();
echo "attendance_logs now holds {$now} rows.\n";
echo "\nNext: php tools/migrate_punch_review.php  (creates the unique anchor)\n";
echo "Keep `{$backup}` until the numbers on the Attendance page look right, then drop it.\n";
