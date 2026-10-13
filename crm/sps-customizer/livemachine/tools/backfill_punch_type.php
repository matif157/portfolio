<?php
declare(strict_types=1);

/**
 * One-time backfill: re-derive punch_type (IN/OUT) for historical device punches.
 *
 * Mirrors Attendance::deriveType() — for each device_user_id, punches are ordered
 * by time within each calendar day; the first punch of the day becomes IN (0) and
 * every later punch alternates OUT(1), IN(0), … Manual entries are left untouched
 * (admins set those types deliberately), but they still occupy a slot in the
 * per-day ordering so device rows around them alternate correctly.
 *
 * Safe by default: runs as a DRY RUN unless you opt in.
 *   CLI:     php tools/backfill_punch_type.php --apply
 *   Browser: http://localhost/livemachine/tools/backfill_punch_type.php?apply=1
 * Without --apply / ?apply=1 it only reports what WOULD change.
 */

require_once __DIR__ . '/../config/database.php';

$isCli = (PHP_SAPI === 'cli');
$apply = $isCli
    ? in_array('--apply', $argv ?? [], true)
    : (($_GET['apply'] ?? '') === '1');

$nl = $isCli ? "\n" : "<br>\n";
if (!$isCli) { header('Content-Type: text/html; charset=utf-8'); echo "<pre>"; }

$pdo = Database::pdo();

// Pull every punch in chronological order, grouped implicitly by user+day.
$rows = $pdo->query(
    "SELECT id, device_user_id, punch_time, punch_type, source
       FROM attendance_logs
      ORDER BY device_user_id, DATE(punch_time), punch_time, id"
)->fetchAll();

$total      = count($rows);
$updates    = [];          // [id => newType] for device rows that need changing
$curKey     = null;        // device_user_id|YYYY-MM-DD
$position    = 0;           // count of punches seen so far this user-day

foreach ($rows as $r) {
    $day = substr((string)$r['punch_time'], 0, 10);
    $key = $r['device_user_id'] . '|' . $day;

    if ($key !== $curKey) {        // new user-day → reset ordering
        $curKey   = $key;
        $position = 0;
    }

    $expected = $position % 2;     // 0 → IN, 1 → OUT, 2 → IN, …
    $position++;

    // Only correct device punches; manual entries keep their chosen type.
    if ($r['source'] === 'device' && (int)$r['punch_type'] !== $expected) {
        $updates[(int)$r['id']] = $expected;
    }
}

echo "Scanned {$total} punch row(s)." . $nl;
echo count($updates) . " device row(s) " . ($apply ? "will be updated." : "WOULD change (dry run).") . $nl;

if (!$apply) {
    echo $nl . "No changes written. Re-run with " . ($isCli ? "--apply" : "?apply=1") . " to commit." . $nl;
    if (!$isCli) echo "</pre>";
    exit;
}

if ($updates) {
    $stmt = $pdo->prepare('UPDATE attendance_logs SET punch_type = :pt WHERE id = :id');
    $pdo->beginTransaction();
    try {
        foreach ($updates as $id => $pt) {
            $stmt->execute([':pt' => $pt, ':id' => $id]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        echo "FAILED — rolled back: " . $e->getMessage() . $nl;
        if (!$isCli) echo "</pre>";
        exit(1);
    }
}

echo "Done. " . count($updates) . " row(s) updated." . $nl;
if (!$isCli) echo "</pre>";
