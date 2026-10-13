<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Leave management: allocations (assigned balance), requests/records and the
 * approval workflow (pending -> approved / rejected).
 *
 * Approved *paid* leaves are credited in payroll by Payroll::daily(); see
 * Payroll::approvedPaidLeaveDays(). Unpaid leaves are recorded and counted toward
 * balances but never add to wages.
 */
final class Leave
{
    /** Known leave types and their default paid flag. Admin can override per request. */
    public const TYPES = [
        'annual' => ['label' => 'Annual',  'paid' => 1],
        'sick'   => ['label' => 'Sick',    'paid' => 1],
        'casual' => ['label' => 'Casual',  'paid' => 1],
        'unpaid' => ['label' => 'Unpaid',  'paid' => 0],
    ];

    public static function typeLabel(string $key): string
    {
        return self::TYPES[$key]['label'] ?? ucfirst($key);
    }

    /** Inclusive whole-day count between two Y-m-d dates. */
    public static function dayCount(string $from, string $to): int
    {
        $a = strtotime($from);
        $b = strtotime($to);
        if ($a === false || $b === false || $b < $a) return 0;
        return (int) floor(($b - $a) / 86400) + 1;
    }

    /** Create a leave record (defaults to pending). Returns new id. */
    public static function create(array $data): int
    {
        $from = (string)$data['from_date'];
        $to   = (string)($data['to_date'] ?: $from);
        if (strtotime($to) < strtotime($from)) { $tmp = $from; $from = $to; $to = $tmp; }

        $type = (string)($data['leave_type'] ?? 'annual');
        $paid = array_key_exists('is_paid', $data)
            ? (int)(bool)$data['is_paid']
            : (int)(self::TYPES[$type]['paid'] ?? 1);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO leaves
                (employee_id, leave_type, is_paid, from_date, to_date, days, reason, status)
             VALUES
                (:e, :t, :p, :f, :to, :d, :r, :s)'
        );
        $stmt->execute([
            ':e'  => (int)$data['employee_id'],
            ':t'  => $type,
            ':p'  => $paid,
            ':f'  => $from,
            ':to' => $to,
            ':d'  => self::dayCount($from, $to),
            ':r'  => ($data['reason'] ?? '') !== '' ? (string)$data['reason'] : null,
            ':s'  => in_array($data['status'] ?? '', ['pending','approved','rejected'], true)
                        ? $data['status'] : 'pending',
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    /** Update a leave's type/paid flag (e.g. correcting Paid/Unpaid after the fact). */
    public static function update(int $id, array $data): void
    {
        $type = (string)($data['leave_type'] ?? 'annual');
        $paid = array_key_exists('is_paid', $data)
            ? (int)(bool)$data['is_paid']
            : (int)(self::TYPES[$type]['paid'] ?? 1);

        $stmt = Database::pdo()->prepare(
            'UPDATE leaves SET leave_type = :t, is_paid = :p WHERE id = :id'
        );
        $stmt->execute([':t' => $type, ':p' => $paid, ':id' => $id]);
    }

    /** Approve or reject a leave; records who decided and an optional note. */
    public static function decide(int $id, string $status, string $by, string $note = ''): void
    {
        if (!in_array($status, ['approved','rejected','pending'], true)) {
            throw new \InvalidArgumentException('bad_status');
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE leaves
                SET status = :s, decided_by = :by, decided_on = NOW(), decision_note = :n
              WHERE id = :id'
        );
        $stmt->execute([
            ':s'  => $status,
            ':by' => $by,
            ':n'  => $note !== '' ? $note : null,
            ':id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM leaves WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Leave records joined to employee, newest first. Optional filters.
     * @return array<int,array<string,mixed>>
     */
    public static function listing(?string $status = null, ?int $employeeId = null, ?string $from = null, ?string $to = null): array
    {
        $sql = "SELECT l.*, e.name AS employee_name, e.device_user_id, e.department
                  FROM leaves l
                  JOIN employees e ON e.id = l.employee_id
                 WHERE 1=1";
        $params = [];
        if ($status)            { $sql .= ' AND l.status = :st';      $params[':st']  = $status; }
        if ($employeeId !== null){ $sql .= ' AND l.employee_id = :e'; $params[':e']   = $employeeId; }
        if ($from)              { $sql .= ' AND l.to_date >= :f';     $params[':f']   = $from; }
        if ($to)                { $sql .= ' AND l.from_date <= :t';   $params[':t']   = $to; }
        $sql .= ' ORDER BY (l.status = "pending") DESC, l.from_date DESC, l.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM leaves WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Upsert an allocation (assigned days) for an employee / type / year. */
    public static function assign(int $employeeId, string $type, int $year, float $days): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO leave_assignments (employee_id, leave_type, year, allocated_days)
             VALUES (:e, :t, :y, :d)
             ON DUPLICATE KEY UPDATE allocated_days = :d2'
        );
        $stmt->execute([
            ':e' => $employeeId, ':t' => $type, ':y' => $year,
            ':d' => $days, ':d2' => $days,
        ]);
    }

    /**
     * Per-employee balance table for a year: allocated (assigned) vs approved-used
     * vs pending days, per leave type. Returns one row per (employee, type) that
     * has either an allocation or any leave in that year.
     *
     * @return array<int,array{employee_id:int,name:string,device_user_id:string,leave_type:string,allocated:float,used:float,pending:float,remaining:float}>
     */
    public static function balances(int $year, ?int $employeeId = null): array
    {
        $yStart = sprintf('%04d-01-01', $year);
        $yEnd   = sprintf('%04d-12-31', $year);
        $pdo = Database::pdo();

        // Allocations
        $aSql = 'SELECT a.employee_id, a.leave_type, a.allocated_days,
                        e.name, e.device_user_id
                   FROM leave_assignments a
                   JOIN employees e ON e.id = a.employee_id
                  WHERE a.year = :y';
        $aParams = [':y' => $year];
        if ($employeeId !== null) { $aSql .= ' AND a.employee_id = :e'; $aParams[':e'] = $employeeId; }
        $aStmt = $pdo->prepare($aSql); $aStmt->execute($aParams);

        $rows = [];
        $key = static fn($e, $t) => $e . '|' . $t;
        foreach ($aStmt->fetchAll() as $r) {
            $rows[$key($r['employee_id'], $r['leave_type'])] = [
                'employee_id'   => (int)$r['employee_id'],
                'name'          => (string)$r['name'],
                'device_user_id'=> (string)$r['device_user_id'],
                'leave_type'    => (string)$r['leave_type'],
                'allocated'     => (float)$r['allocated_days'],
                'used'          => 0.0,
                'pending'       => 0.0,
            ];
        }

        // Used (approved) and pending days within the year
        $uSql = "SELECT l.employee_id, l.leave_type, l.status, SUM(l.days) AS d,
                        e.name, e.device_user_id
                   FROM leaves l
                   JOIN employees e ON e.id = l.employee_id
                  WHERE l.status IN ('approved','pending')
                    AND l.from_date <= :ye AND l.to_date >= :ys";
        $uParams = [':ye' => $yEnd, ':ys' => $yStart];
        if ($employeeId !== null) { $uSql .= ' AND l.employee_id = :e'; $uParams[':e'] = $employeeId; }
        $uSql .= ' GROUP BY l.employee_id, l.leave_type, l.status';
        $uStmt = $pdo->prepare($uSql); $uStmt->execute($uParams);

        foreach ($uStmt->fetchAll() as $r) {
            $k = $key($r['employee_id'], $r['leave_type']);
            $rows[$k] ??= [
                'employee_id'   => (int)$r['employee_id'],
                'name'          => (string)$r['name'],
                'device_user_id'=> (string)$r['device_user_id'],
                'leave_type'    => (string)$r['leave_type'],
                'allocated'     => 0.0,
                'used'          => 0.0,
                'pending'       => 0.0,
            ];
            if ($r['status'] === 'approved') $rows[$k]['used']    = (float)$r['d'];
            else                              $rows[$k]['pending'] = (float)$r['d'];
        }

        foreach ($rows as &$row) {
            $row['remaining'] = round($row['allocated'] - $row['used'], 1);
        }
        unset($row);

        usort($rows, static fn($a, $b) =>
            [$a['name'], $a['leave_type']] <=> [$b['name'], $b['leave_type']]);

        return array_values($rows);
    }

    /** Count of leaves awaiting a decision (for nav badges, etc.). */
    public static function pendingCount(): int
    {
        try {
            return (int)Database::pdo()
                ->query("SELECT COUNT(*) FROM leaves WHERE status = 'pending'")
                ->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }
}
