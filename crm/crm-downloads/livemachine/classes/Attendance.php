<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Auth.php';   // deletePunch() stamps who crossed the row

final class Attendance
{
    /**
     * SQL fragment excluding punches an admin has crossed off (rejected). A
     * rejected punch is deliberately kept in the table — it is what stops the
     * device re-importing it — but must not reach any count, report or wage.
     */
    private static function notRejected(string $alias): string
    {
        return " AND {$alias}.review_status <> 'rejected'";
    }

    /**
     * Insert a single punch row; returns true if a new row was inserted.
     *
     * For device logs, $devicePunchTime is the timestamp exactly as the device
     * reported it and defaults to $punchTime. It is stored once and never
     * edited, so it stays a stable dedup anchor: a punch that has been corrected
     * or crossed off still blocks its original from being re-imported. Manual
     * entries store NULL there — they have no device original to dedup against.
     */
    public static function insertLog(
        string $deviceUserId,
        string $punchTime,
        int $punchType = 0,
        string $source = 'device',
        ?string $devicePunchTime = null
    ): bool {
        if ($source === 'device' && $devicePunchTime === null) {
            $devicePunchTime = $punchTime;
        }

        // Resolve employee_id (nullable — orphan punches kept for visibility)
        $empStmt = Database::pdo()->prepare(
            'SELECT id FROM employees WHERE device_user_id = :u LIMIT 1'
        );
        $empStmt->execute([':u' => $deviceUserId]);
        $employeeId = $empStmt->fetchColumn() ?: null;

        // Device-reported punch_type is unreliable (many ZK units stamp every
        // scan with the same type), so the first scan of a day can wrongly land
        // as OUT. Derive IN/OUT from the scan's order within its calendar day:
        // the day's first punch is always IN, each later punch alternates
        // OUT, IN, OUT … Manual entries keep the admin-selected type.
        if ($source === 'device') {
            $punchType = self::deriveType($deviceUserId, $punchTime);
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO attendance_logs
                    (device_user_id, employee_id, punch_time, device_punch_time,
                     punch_type, source, status)
                 VALUES
                    (:u, :e, :t, :dt, :pt, :src, :st)'
            );
            $stmt->execute([
                ':u'   => $deviceUserId,
                ':e'   => $employeeId,
                ':t'   => $punchTime,
                ':dt'  => $devicePunchTime,
                ':pt'  => $punchType,
                ':src' => $source,
                ':st'  => self::classify($punchTime),
            ]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            // 23000 = UNIQUE violation → already stored, nothing to do
            if ($e->getCode() === '23000') return false;
            throw $e;
        }
    }

    /**
     * Device logs already stored, as a set of "userId\0deviceTime" keys, so a
     * sync can tell "new" from "seen before" without attempting an insert per
     * row. Bounded to the window the device actually returned.
     *
     * @param array<int,array{user_id:mixed,timestamp:string}> $logs
     * @return array<string,true>
     */
    public static function knownDeviceLogs(array $logs): array
    {
        if (!$logs) return [];
        $times = array_column($logs, 'timestamp');
        $stmt  = Database::pdo()->prepare(
            'SELECT device_user_id, device_punch_time
               FROM attendance_logs
              WHERE device_punch_time BETWEEN :lo AND :hi'
        );
        $stmt->execute([':lo' => min($times), ':hi' => max($times)]);

        $known = [];
        foreach ($stmt->fetchAll() as $r) {
            $known[$r['device_user_id'] . "\0" . $r['device_punch_time']] = true;
        }
        return $known;
    }

    /**
     * Derive IN (0) / OUT (1) from the punch's order within its calendar day.
     * The first punch of the day is always IN; every later punch alternates.
     * Counting strictly-earlier punches (rather than all punches) keeps the
     * first-of-day = IN guarantee even when a sync delivers rows out of order.
     */
    private static function deriveType(string $deviceUserId, string $punchTime): int
    {
        // Count punches earlier the same day. Two distinct placeholders for the
        // day-start and the punch itself — native prepared statements (emulation
        // is off in config/database.php) reject reusing one named placeholder.
        $dayStart = substr($punchTime, 0, 10) . ' 00:00:00';
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM attendance_logs al
              WHERE al.device_user_id = :u
                AND al.punch_time >= :dayStart
                AND al.punch_time <  :t'
            . self::notRejected('al')
        );
        $stmt->execute([':u' => $deviceUserId, ':dayStart' => $dayStart, ':t' => $punchTime]);
        $earlier = (int)$stmt->fetchColumn();
        return $earlier % 2; // 0 earlier → IN(0); 1 earlier → OUT(1); 2 → IN; …
    }

    /**
     * Arrival status for a punch: 'late' if after the late threshold, else 'present'.
     *
     * Half-day is NOT decided here any more — it depended only on the clock time, so
     * every afternoon/evening punch (break-outs, final outs) got wrongly flagged. A
     * half day is now a per-day judgement on actual hours worked (Payroll::computeDay),
     * surfaced in the day-level views. This per-punch status only marks lateness.
     */
    public static function classify(string $punchDatetime): string
    {
        static $settings = null;
        if ($settings === null) {
            $rows = Database::pdo()->query('SELECT `key`,`value` FROM settings')->fetchAll();
            $settings = [];
            foreach ($rows as $r) $settings[$r['key']] = $r['value'];
        }
        $time = substr($punchDatetime, 11, 5); // HH:MM
        if ($time > ($settings['late_threshold'] ?? '09:15')) return 'late';
        return 'present';
    }

    /**
     * Human label for a single punch, derived from its order within the day.
     * Punches alternate IN, OUT, IN, OUT …, so:
     *   first punch            -> "Time In"
     *   later IN (after break)  -> "Break End"
     *   OUT with a later punch  -> "Break Start"  (they came back)
     *   final OUT of the day    -> "Time Out", unless it is still today and before
     *                              office-out, in which case the person is on break
     *                              and it shows "Break Start" — never a bare time-out.
     *
     * @param int    $earlier   number of the employee's punches earlier that day
     * @param bool   $hasLater  whether a later punch exists that day
     */
    public static function punchLabel(int $earlier, bool $hasLater, string $punchTime, string $officeOut): string
    {
        if ($earlier === 0)     return 'Time In';
        if ($earlier % 2 === 0) return 'Break End';   // an IN following a break
        // odd $earlier -> this punch is an OUT
        if ($hasLater)          return 'Break Start';
        $isToday = substr($punchTime, 0, 10) === date('Y-m-d');
        if ($isToday && substr($punchTime, 11, 5) < $officeOut) return 'Break Start';
        return 'Time Out';
    }

    /**
     * Arrival badge for display. Only the day's first punch carries it (late / present);
     * later punches return '' so break-outs and final outs are not painted late/present.
     */
    public static function displayStatus(int $earlier, string $punchTime, string $lateThreshold): string
    {
        if ($earlier !== 0) return '';
        return substr($punchTime, 11, 5) > $lateThreshold ? 'late' : 'present';
    }

    /**
     * Attach `type_label` and `display_status` to punch rows that carry the
     * `earlier_cnt` / `has_later` companions (from listing() / recent()).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function enrichPunchRows(array $rows): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $s = [];
            foreach (Database::pdo()->query('SELECT `key`,`value` FROM settings')->fetchAll() as $r) {
                $s[$r['key']] = $r['value'];
            }
            $cfg = [
                'office_out' => $s['office_out_time'] ?? '18:00',
                'late'       => $s['late_threshold']  ?? '09:15',
            ];
        }
        static $labelClass = [
            'Time In'     => 'present',
            'Break End'   => 'present',
            'Break Start' => 'late',
            'Time Out'    => '',        // base badge — neutral
        ];
        foreach ($rows as &$r) {
            $earlier  = (int)($r['earlier_cnt'] ?? 0);
            $hasLater = !empty($r['has_later']);
            $label    = self::punchLabel($earlier, $hasLater, (string)$r['punch_time'], $cfg['office_out']);
            $r['type_label']     = $label;
            $r['type_class']     = $labelClass[$label] ?? '';
            $r['display_status'] = self::displayStatus($earlier, (string)$r['punch_time'], $cfg['late']);
        }
        unset($r);
        return $rows;
    }

    /**
     * Filtered listing for the Attendance page.
     *
     * Crossed-off punches ARE returned here — the admin has to see them to undo
     * a cross or to know which day still needs a manual correction. $review
     * ('unreviewed' | 'approved' | 'rejected') narrows to one review state;
     * 'active' drops the rejected ones, which is what exports and reports want.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listing(?string $from, ?string $to, ?string $employeeQuery, int $limit = 500, ?int $employeeId = null, ?string $review = null): array
    {
        $sql = 'SELECT al.*, e.name AS employee_name, e.department,'
             . self::PUNCH_ORDER_SQL . '
                  FROM attendance_logs al
                  LEFT JOIN employees e
                    ON e.id = al.employee_id
                    OR (al.employee_id IS NULL AND e.device_user_id = al.device_user_id)
                 WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND al.punch_time >= :from'; $params[':from'] = $from . ' 00:00:00'; }
        if ($to)   { $sql .= ' AND al.punch_time <= :to';   $params[':to']   = $to   . ' 23:59:59'; }
        if ($employeeQuery) {
            $sql .= ' AND (e.name LIKE :q OR al.device_user_id LIKE :q)';
            $params[':q'] = '%' . $employeeQuery . '%';
        }
        // Filter on the joined employee, so punches with a NULL employee_id that
        // resolve by device_user_id are still matched.
        if ($employeeId !== null) {
            $sql .= ' AND e.id = :eid';
            $params[':eid'] = $employeeId;
        }
        if ($review === 'active') {
            $sql .= self::notRejected('al');
        } elseif (in_array($review, ['unreviewed', 'approved', 'rejected'], true)) {
            $sql .= ' AND al.review_status = :rev';
            $params[':rev'] = $review;
        }
        $sql .= ' ORDER BY al.punch_time DESC LIMIT ' . (int)$limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return self::enrichPunchRows($stmt->fetchAll());
    }

    /**
     * Per-punch companions used by enrichPunchRows(): how many of this employee's
     * punches fall earlier the same day, and whether any fall later. Bounded by the
     * calendar day so the (device_user_id, punch_time) index can be used.
     */
    private const PUNCH_ORDER_SQL = "
        (SELECT COUNT(*) FROM attendance_logs x
           WHERE x.device_user_id = al.device_user_id
             AND x.punch_time >= DATE(al.punch_time)
             AND x.punch_time <  al.punch_time
             AND x.review_status <> 'rejected') AS earlier_cnt,
        EXISTS(SELECT 1 FROM attendance_logs y
           WHERE y.device_user_id = al.device_user_id
             AND y.punch_time >  al.punch_time
             AND y.punch_time <  DATE(al.punch_time) + INTERVAL 1 DAY
             AND y.review_status <> 'rejected') AS has_later";

    /** Most-recent N rows for live feed; optional newer-than filter. */
    public static function recent(int $limit = 20, ?int $afterId = null): array
    {
        // Fall back to device_user_id match so punches with NULL employee_id
        // (inserted before the employee row existed) still show a real name.
        $sql = 'SELECT al.id, al.device_user_id, al.punch_time, al.punch_type, al.source,
                       al.status, al.review_status, al.synced_at, e.name AS employee_name,'
             . self::PUNCH_ORDER_SQL . '
                  FROM attendance_logs al
                  LEFT JOIN employees e
                    ON e.id = al.employee_id
                    OR (al.employee_id IS NULL AND e.device_user_id = al.device_user_id)
                 WHERE 1=1' . self::notRejected('al');
        $params = [];
        if ($afterId !== null) {
            $sql .= ' AND al.id > :aid';
            $params[':aid'] = $afterId;
        }
        $sql .= ' ORDER BY al.id DESC LIMIT ' . (int)$limit;
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return self::enrichPunchRows($stmt->fetchAll());
    }

    public static function dashboardCounts(): array
    {
        $pdo = Database::pdo();
        $today = date('Y-m-d');
        $lateThreshold = (string)($pdo->query(
            "SELECT `value` FROM settings WHERE `key`='late_threshold'"
        )->fetchColumn() ?: '09:15');

        $totalEmp  = (int)$pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();

        // Present = distinct employees with any punch today. Late = those whose first
        // punch of the day is after the late threshold (one count per person, not per
        // punch — every afternoon punch is "after" the threshold and must not count).
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS present_count,
                SUM(CASE WHEN TIME(first_in) > :late THEN 1 ELSE 0 END) AS late_count
             FROM (
                SELECT employee_id, MIN(punch_time) AS first_in
                  FROM attendance_logs al
                 WHERE DATE(al.punch_time) = :d AND al.employee_id IS NOT NULL"
                 . self::notRejected('al') . "
                 GROUP BY employee_id
             ) t"
        );
        $stmt->execute([':d' => $today, ':late' => $lateThreshold]);
        $row = $stmt->fetch() ?: ['present_count' => 0, 'late_count' => 0];

        $present = (int)$row['present_count'];
        $late    = (int)$row['late_count'];
        $absent  = max(0, $totalEmp - $present);

        return [
            'total_employees' => $totalEmp,
            'present'         => $present,
            'late'            => $late,
            'absent'          => $absent,
        ];
    }

    /** Hourly punch volume for today (for the dashboard line chart). */
    public static function hourlyToday(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT HOUR(al.punch_time) AS h, COUNT(*) AS c
               FROM attendance_logs al
              WHERE DATE(al.punch_time) = CURDATE()"
              . self::notRejected('al') . "
           GROUP BY HOUR(al.punch_time)
           ORDER BY h"
        );
        $by = array_fill(0, 24, 0);
        foreach ($stmt->fetchAll() as $r) $by[(int)$r['h']] = (int)$r['c'];
        return $by;
    }

    /** Monthly per-employee report. Optionally filter to a single employee. */
    public static function monthlyReport(string $yyyymm, ?int $employeeId = null): array
    {
        [$y, $m] = array_map('intval', explode('-', $yyyymm));
        $first = sprintf('%04d-%02d-01', $y, $m);
        $last  = date('Y-m-t', strtotime($first));

        $sql =
            "SELECT e.id, e.device_user_id, e.name, e.department,
                    DATE(al.punch_time) AS day,
                    MIN(al.punch_time) AS first_in,
                    MAX(al.punch_time) AS last_out,
                    COUNT(*) AS punches,
                    GROUP_CONCAT(al.punch_time ORDER BY al.punch_time) AS punch_times
               FROM employees e
               LEFT JOIN attendance_logs al
                 ON al.employee_id = e.id
                AND DATE(al.punch_time) BETWEEN :from AND :to"
                . self::notRejected('al');
        $params = [':from' => $first, ':to' => $last];
        if ($employeeId !== null) {
            $sql .= ' WHERE e.id = :eid';
            $params[':eid'] = $employeeId;
        }
        $sql .= ' GROUP BY e.id, DATE(al.punch_time) ORDER BY e.name, day';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Per-employee, per-day working-hours report for an arbitrary [from..to] range
     * (inclusive). Unlike monthlyReport() this is not capped to a single month —
     * pass any span of days. Optionally filter to a single employee.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function rangeReport(string $from, string $to, ?int $employeeId = null): array
    {
        $sql =
            "SELECT e.id, e.device_user_id, e.name, e.department,
                    DATE(al.punch_time) AS day,
                    MIN(al.punch_time) AS first_in,
                    MAX(al.punch_time) AS last_out,
                    COUNT(*) AS punches,
                    GROUP_CONCAT(al.punch_time ORDER BY al.punch_time) AS punch_times
               FROM employees e
               JOIN attendance_logs al
                 ON (al.employee_id = e.id OR al.device_user_id = e.device_user_id)
                AND DATE(al.punch_time) BETWEEN :from AND :to"
                . self::notRejected('al');
        $params = [':from' => $from, ':to' => $to];
        if ($employeeId !== null) {
            $sql .= ' WHERE e.id = :eid';
            $params[':eid'] = $employeeId;
        }
        $sql .= ' GROUP BY e.id, DATE(al.punch_time) ORDER BY e.name, day';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function lastSyncedId(): int
    {
        return (int)Database::pdo()->query('SELECT MAX(id) FROM attendance_logs')->fetchColumn();
    }

    /**
     * Move a punch to a new time (and optionally retype it). Used to correct a
     * forgotten Break End or a mis-stamped punch from the Attendance log. The
     * arrival status is re-derived from the new time; IN/OUT ordering is still
     * computed for display, so only the timestamp and optional type are stored.
     *
     * device_punch_time is deliberately left alone, so the row keeps blocking
     * the device's original from being re-imported. A hand-corrected punch is
     * by definition a reviewed one, so it is marked ticked.
     */
    public static function updatePunch(int $id, string $punchTime, ?int $punchType = null): void
    {
        $ts = strtotime($punchTime);
        if ($ts === false) {
            throw new \InvalidArgumentException('bad_time');
        }
        $mysql = date('Y-m-d H:i:s', $ts);

        if ($punchType === null) {
            $stmt = Database::pdo()->prepare(
                'UPDATE attendance_logs
                    SET punch_time = :t, status = :st, source = "manual", edited = 1,
                        review_status = "approved", reviewed_by = :by, reviewed_at = :at
                  WHERE id = :id'
            );
            $stmt->execute([
                ':t' => $mysql, ':st' => self::classify($mysql),
                ':by' => Auth::user()['name'] ?: 'admin', ':at' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
        } else {
            $stmt = Database::pdo()->prepare(
                'UPDATE attendance_logs
                    SET punch_time = :t, punch_type = :pt, status = :st, source = "manual", edited = 1,
                        review_status = "approved", reviewed_by = :by, reviewed_at = :at
                  WHERE id = :id'
            );
            $stmt->execute([
                ':t' => $mysql, ':pt' => $punchType, ':st' => self::classify($mysql),
                ':by' => Auth::user()['name'] ?: 'admin', ':at' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
        }
    }

    /**
     * Tick (approved) or cross (rejected) a punch, or clear it to unreviewed.
     *
     * A rejected punch is NOT removed: the row is the only thing stopping the
     * device from handing the same bad timestamp back on the next sync. It stays
     * visible on the Attendance page, struck through, and drops out of every
     * count, report and wage figure — so the admin crosses the bad punch and
     * adds the correct one manually, and the totals come out right.
     */
    public static function setReview(int $id, string $status, ?string $note, string $by): void
    {
        if (!in_array($status, ['unreviewed', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('bad_status');
        }
        $clear = $status === 'unreviewed';
        $stmt = Database::pdo()->prepare(
            'UPDATE attendance_logs
                SET review_status = :s,
                    review_note   = :n,
                    reviewed_by   = :by,
                    reviewed_at   = :at
              WHERE id = :id'
        );
        $stmt->execute([
            ':s'  => $status,
            ':n'  => $clear ? null : ($note !== '' ? $note : null),
            ':by' => $clear ? null : $by,
            ':at' => $clear ? null : date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    /**
     * Remove a punch. Manual entries are deleted outright — they have no device
     * original, so nothing can bring them back. A device punch is crossed off
     * instead: deleting it would only free the device to re-import the same row
     * on the next sync, which is the bug this whole flow exists to stop.
     *
     * @return string 'deleted' or 'crossed'
     */
    public static function deletePunch(int $id): string
    {
        $row = self::findPunch($id);
        if ($row === null) return 'deleted';

        if (!empty($row['device_punch_time'])) {
            self::setReview($id, 'rejected', 'Removed by admin', Auth::user()['name'] ?: 'admin');
            return 'crossed';
        }

        Database::pdo()->prepare('DELETE FROM attendance_logs WHERE id = :id')
            ->execute([':id' => $id]);
        return 'deleted';
    }

    /**
     * Review tallies for a date range, for the Attendance page filter chips.
     * @return array{unreviewed:int, approved:int, rejected:int}
     */
    public static function reviewCounts(?string $from, ?string $to, ?int $employeeId = null): array
    {
        // Same join as listing(), so the chip counts match the rows on screen even
        // for punches whose employee_id is NULL but resolve by device_user_id.
        $sql = 'SELECT al.review_status, COUNT(*) AS c
                  FROM attendance_logs al
                  LEFT JOIN employees e
                    ON e.id = al.employee_id
                    OR (al.employee_id IS NULL AND e.device_user_id = al.device_user_id)
                 WHERE 1=1';
        $params = [];
        if ($from) { $sql .= ' AND al.punch_time >= :from'; $params[':from'] = $from . ' 00:00:00'; }
        if ($to)   { $sql .= ' AND al.punch_time <= :to';   $params[':to']   = $to   . ' 23:59:59'; }
        if ($employeeId !== null) {
            $sql .= ' AND e.id = :eid';
            $params[':eid'] = $employeeId;
        }
        $sql .= ' GROUP BY al.review_status';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $out = ['unreviewed' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($stmt->fetchAll() as $r) $out[$r['review_status']] = (int)$r['c'];
        return $out;
    }

    public static function findPunch(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM attendance_logs WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
