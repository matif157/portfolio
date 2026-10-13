<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Company holiday calendar + manual per-employee "avail" records.
 *
 * A holiday is a calendar-wide definition (name, date, default paid flag),
 * optionally seeded from the USA federal calendar. Whether a given employee
 * actually gets credited for it is a separate, manual per-employee record
 * (holiday_avails) — nothing is applied automatically company-wide.
 *
 * Approved avails are credited in payroll by Payroll::daily(); see
 * Payroll::approvedHolidayDays(). Unpaid avails are recorded but add no wage.
 */
final class Holiday
{
    /** Insert a custom holiday. Returns new id. Throws on duplicate date. */
    public static function create(string $name, string $date, int $isPaid, ?string $notes = null): int
    {
        if (self::findByDate($date)) {
            throw new \RuntimeException('duplicate_date');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO holidays (name, holiday_date, is_paid, source, notes)
             VALUES (:n, :d, :p, "custom", :notes)'
        );
        $stmt->execute([
            ':n'     => $name,
            ':d'     => $date,
            ':p'     => $isPaid ? 1 : 0,
            ':notes' => $notes,
        ]);
        return (int)Database::pdo()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM holidays WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByDate(string $date): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM holidays WHERE holiday_date = :d AND deleted_at IS NULL');
        $stmt->execute([':d' => $date]);
        return $stmt->fetch() ?: null;
    }

    /** Soft-delete a holiday; existing avail records are left untouched. */
    public static function delete(int $id): void
    {
        Database::pdo()->prepare('UPDATE holidays SET deleted_at = NOW() WHERE id = :id')
            ->execute([':id' => $id]);
    }

    /**
     * Holidays for a calendar year, newest-date-first-in-year (ascending), with
     * a count of how many employees currently avail each one.
     */
    public static function listing(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.*, COUNT(ha.id) AS avail_count
               FROM holidays h
          LEFT JOIN holiday_avails ha ON ha.holiday_id = h.id
              WHERE h.year = :y AND h.deleted_at IS NULL
           GROUP BY h.id
           ORDER BY h.holiday_date'
        );
        $stmt->execute([':y' => $year]);
        return $stmt->fetchAll();
    }

    /** Upsert an employee's avail record for a holiday. Returns the avail id. */
    public static function availEmployee(int $holidayId, int $employeeId, int $isPaid, string $recordedBy): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO holiday_avails (holiday_id, employee_id, is_paid, recorded_by)
             VALUES (:h, :e, :p, :by)
             ON DUPLICATE KEY UPDATE is_paid = :p2, recorded_by = :by2'
        );
        $stmt->execute([
            ':h' => $holidayId, ':e' => $employeeId, ':p' => $isPaid ? 1 : 0, ':by' => $recordedBy,
            ':p2' => $isPaid ? 1 : 0, ':by2' => $recordedBy,
        ]);

        $find = Database::pdo()->prepare(
            'SELECT id FROM holiday_avails WHERE holiday_id = :h AND employee_id = :e'
        );
        $find->execute([':h' => $holidayId, ':e' => $employeeId]);
        return (int)$find->fetchColumn();
    }

    public static function removeAvail(int $availId): void
    {
        Database::pdo()->prepare('DELETE FROM holiday_avails WHERE id = :id')->execute([':id' => $availId]);
    }

    /** All avail records for a calendar year, joined to holiday + employee. */
    public static function avails(int $year): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT ha.*, h.name AS holiday_name, h.holiday_date,
                    e.name AS employee_name, e.device_user_id
               FROM holiday_avails ha
               JOIN holidays h  ON h.id = ha.holiday_id AND h.deleted_at IS NULL
               JOIN employees e ON e.id = ha.employee_id
              WHERE h.year = :y
           ORDER BY h.holiday_date, e.name'
        );
        $stmt->execute([':y' => $year]);
        return $stmt->fetchAll();
    }

    /**
     * Compute the standard 11 USA federal holidays for a year (floating
     * weekday holidays are calculated, not hard-coded per year).
     *
     * @return array<int,array{name:string,date:string}>
     */
    public static function usaFederalHolidays(int $year): array
    {
        return [
            ['name' => "New Year's Day",                      'date' => sprintf('%04d-01-01', $year)],
            ['name' => 'Martin Luther King Jr. Day',           'date' => self::nthWeekdayOfMonth($year, 1, 1, 3)],
            ["name" => "Washington's Birthday",                'date' => self::nthWeekdayOfMonth($year, 2, 1, 3)],
            ['name' => 'Memorial Day',                          'date' => self::lastWeekdayOfMonth($year, 5, 1)],
            ['name' => 'Juneteenth National Independence Day', 'date' => sprintf('%04d-06-19', $year)],
            ['name' => 'Independence Day',                      'date' => sprintf('%04d-07-04', $year)],
            ['name' => 'Labor Day',                             'date' => self::nthWeekdayOfMonth($year, 9, 1, 1)],
            ['name' => 'Columbus Day',                          'date' => self::nthWeekdayOfMonth($year, 10, 1, 2)],
            ['name' => 'Veterans Day',                          'date' => sprintf('%04d-11-11', $year)],
            ['name' => 'Thanksgiving Day',                      'date' => self::nthWeekdayOfMonth($year, 11, 4, 4)],
            ['name' => 'Christmas Day',                         'date' => sprintf('%04d-12-25', $year)],
        ];
    }

    /** Insert the USA federal holidays for a year, skipping dates already present. Returns count added. */
    public static function loadUsaFederalHolidays(int $year): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO holidays (name, holiday_date, is_paid, source)
             VALUES (:n, :d, 1, "usa_federal")'
        );
        $added = 0;
        foreach (self::usaFederalHolidays($year) as $h) {
            $stmt->execute([':n' => $h['name'], ':d' => $h['date']]);
            $added += $stmt->rowCount();
        }
        return $added;
    }

    /** The Nth occurrence (1-based) of a weekday (0=Sun..6=Sat) in a month. */
    private static function nthWeekdayOfMonth(int $year, int $month, int $weekday, int $n): string
    {
        $first = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $offset = ($weekday - (int)$first->format('w') + 7) % 7;
        $first->modify('+' . ($offset + ($n - 1) * 7) . ' days');
        return $first->format('Y-m-d');
    }

    /** The last occurrence of a weekday (0=Sun..6=Sat) in a month. */
    private static function lastWeekdayOfMonth(int $year, int $month, int $weekday): string
    {
        $last = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $last->modify('last day of this month');
        $offset = ((int)$last->format('w') - $weekday + 7) % 7;
        $last->modify('-' . $offset . ' days');
        return $last->format('Y-m-d');
    }
}
