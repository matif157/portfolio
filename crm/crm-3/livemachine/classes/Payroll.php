<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Daily payroll calculation.
 *
 * Hours model: gross = first-in .. last-out per (employee, day). The break is the
 * larger of a compulsory minimum and the break the employee actually took (summed
 * from their mid-day OUT -> next-IN gaps), so a short or skipped break still costs
 * the minimum and a long break costs its full length. Net = gross - break.
 *
 * A day is classified Full / Half from the *net* worked hours: above the configured
 * full-day minimum it is a full day, otherwise (but with hours worked) a half day.
 *
 * Overtime, for now, is Sunday-only: any net hours worked on a Sunday are paid at
 * the overtime rate in full; every other day is entirely regular hours regardless
 * of length (the overtime_daily_hours threshold is not currently applied).
 *
 * Daily wage = round(net_hours * hourly_rate, 2).
 */
final class Payroll
{
    /** @return array{break_minutes:int,meal_break_minutes:int,full_day_hours:float,overtime_daily_hours:float,overtime_multiplier:float,paid_leave_hours:float,office_out_time:string,late_threshold:string,currency_symbol:string} */
    public static function settings(): array
    {
        $rows = Database::pdo()->query("SELECT `key`,`value` FROM settings")->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['key']] = $r['value'];
        return [
            'break_minutes'        => (int)   ($map['break_minutes']        ?? 45),   // compulsory minimum, always deducted
            'meal_break_minutes'   => (int)   ($map['meal_break_minutes']   ?? 30),   // break >= this = Meal Break, else Short Break
            'full_day_hours'       => (float) ($map['full_day_hours']       ?? 6),    // net hours above this = full day
            'overtime_daily_hours' => (float) ($map['overtime_daily_hours'] ?? 8),    // net hours above this = overtime
            'overtime_multiplier'  => (float) ($map['overtime_multiplier']  ?? 1.5),  // OT pay multiple
            'paid_leave_hours'     => (float) ($map['paid_leave_hours']     ?? 8),    // hours credited per paid-leave day
            'office_out_time'      => (string)($map['office_out_time']      ?? '18:00'),
            'late_threshold'       => (string)($map['late_threshold']       ?? '09:15'),
            'currency_symbol'      => (string)($map['currency_symbol']      ?? 'Rs'),
        ];
    }

    /**
     * Reduce one employee-day's ordered punch list to hours, break and day status.
     *
     * Punches alternate IN, OUT, IN, OUT … — the first punch of the day is always IN
     * (matching Attendance::deriveType). Each OUT -> next-IN gap is a break. The break
     * deducted is max(compulsory minimum, break actually taken); net = gross - break.
     *
     * Each OUT -> next-IN gap is recorded as a discrete break with its start/end clock
     * times and a Meal/Short classification (a break >= meal_break_minutes is a Meal
     * Break, anything shorter is a Short Break). Overtime, for now, is Sunday-only:
     * net hours worked on a Sunday are split out as overtime in full; every other
     * day's net hours are entirely regular (overtime_daily_hours is not applied).
     *
     * @param array<int,string> $punchTimes 'Y-m-d H:i:s' strings (any order; sorted here)
     * @param array{break_minutes:int,meal_break_minutes:int,full_day_hours:float,overtime_daily_hours:float,office_out_time:string} $cfg
     * @param ?string $day 'Y-m-d' date this punch list belongs to, used only to detect
     *        Sunday; defaults to the first punch's date when omitted.
     * @return array{
     *     first_in:?string, last_out:?string,
     *     gross_hours:float, actual_break_hours:float, break_hours:float, net_hours:float,
     *     meal_break_hours:float, short_break_hours:float,
     *     breaks:array<int,array{start:string,end:?string,minutes:int,type:string,ongoing:bool}>,
     *     regular_hours:float, overtime_hours:float,
     *     day_status:string, on_break:bool
     * }
     */
    public static function computeDay(array $punchTimes, array $cfg, ?string $day = null): array
    {
        $punchTimes = array_values(array_filter(
            $punchTimes,
            static fn($t) => $t !== null && $t !== ''
        ));
        sort($punchTimes); // defensive — never trust caller ordering
        $n = count($punchTimes);

        $first = $n ? $punchTimes[0]      : null;
        $last  = $n ? $punchTimes[$n - 1] : null;

        $gross = 0.0;
        if ($n >= 2 && $first !== $last) {
            $gross = max(0.0, (strtotime($last) - strtotime($first)) / 3600.0);
        }

        // Walk each completed break: an OUT (odd index) paired with the next IN. A
        // trailing lone OUT is the day's clock-out, not a break, so only complete
        // pairs are counted here; an unclosed in-progress break is appended below.
        // Classify Meal vs Short by length and keep start/end clock times so every
        // report can show them separately.
        $mealThreshold = $cfg['meal_break_minutes'] ?? 30;
        $breaks   = [];
        $breakSec = 0;
        $mealSec  = 0;
        $shortSec = 0;
        for ($i = 1; $i + 1 < $n; $i += 2) {
            $sec   = max(0, strtotime($punchTimes[$i + 1]) - strtotime($punchTimes[$i]));
            $mins  = (int) round($sec / 60);
            $type  = $mins >= $mealThreshold ? 'meal' : 'short';
            $breakSec += $sec;
            if ($type === 'meal') $mealSec += $sec; else $shortSec += $sec;
            $breaks[] = [
                'start'   => substr($punchTimes[$i], 11, 5),
                'end'     => substr($punchTimes[$i + 1], 11, 5),
                'minutes' => $mins,
                'type'    => $type,
                'ongoing' => false,
            ];
        }
        $actualBreak = max(0.0, $breakSec / 3600.0);

        $minBreak  = $cfg['break_minutes'] / 60.0;
        $deducted  = $gross > 0 ? max($minBreak, $actualBreak) : 0.0;
        $net       = max(0.0, $gross - $deducted);

        // Overtime, for now, applies to Sundays only: every net hour worked on a
        // Sunday counts as overtime; every other day is entirely regular hours
        // regardless of length (the overtime_daily_hours threshold is not applied).
        $day ??= $first !== null ? substr($first, 0, 10) : null;
        $isSunday = $day !== null && date('w', strtotime($day)) === '0';
        if ($isSunday) {
            $overtime = $net;
            $regular  = 0.0;
        } else {
            $overtime = 0.0;
            $regular  = $net;
        }

        if ($net <= 0)                          $status = 'none';
        elseif ($net > $cfg['full_day_hours'])  $status = 'full';
        else                                    $status = 'half';

        // "On break": the last punch is an OUT (even punch count) and the person is
        // expected back — today, before office-out. Lets the UI show "Break Start"
        // instead of a plain time-out, so a noted break never looks like leaving early.
        $onBreak = false;
        if ($last !== null && $n >= 2 && $n % 2 === 0) {
            $isToday = substr($last, 0, 10) === date('Y-m-d');
            if ($isToday && substr($last, 11, 5) < $cfg['office_out_time']) {
                $onBreak = true;
                // Surface the in-progress break (Break Start with no Break End yet).
                $breaks[] = [
                    'start'   => substr($last, 11, 5),
                    'end'     => null,
                    'minutes' => 0,
                    'type'    => 'short',
                    'ongoing' => true,
                ];
            }
        }

        return [
            'first_in'           => $first,
            'last_out'           => $last,
            'gross_hours'        => $gross,
            'actual_break_hours' => $actualBreak,
            'break_hours'        => $deducted,
            'net_hours'          => $net,
            'meal_break_hours'   => $mealSec  / 3600.0,
            'short_break_hours'  => $shortSec / 3600.0,
            'breaks'             => $breaks,
            'regular_hours'      => $regular,
            'overtime_hours'     => $overtime,
            'day_status'         => $status,
            'on_break'           => $onBreak,
        ];
    }

    /**
     * Daily payroll rows for [from..to] inclusive, optionally filtered by employee id.
     *
     * @return array<int,array{
     *     employee_id:int, device_user_id:string, name:string, department:?string,
     *     day:string, first_in:?string, last_out:?string,
     *     gross_hours:float, break_hours:float, actual_break_hours:float, net_hours:float,
     *     day_status:string, on_break:bool, hourly_rate:float, daily_wage:float
     * }>
     */
    public static function daily(string $from, string $to, ?int $employeeId = null): array
    {
        // Match punches to employees by employee_id first; fall back to device_user_id
        // so that orphaned punches (employee deleted then re-added, or punch inserted
        // before the employee row existed) still count toward payroll. GROUP_CONCAT
        // gives us every punch of the day so the break can be measured, not assumed.
        $sql = "
            SELECT e.id              AS employee_id,
                   e.device_user_id,
                   e.name,
                   e.department,
                   e.hourly_rate,
                   DATE(al.punch_time) AS day,
                   GROUP_CONCAT(al.punch_time ORDER BY al.punch_time) AS punch_times
              FROM employees e
              JOIN attendance_logs al
                ON (al.employee_id = e.id OR al.device_user_id = e.device_user_id)
               AND DATE(al.punch_time) BETWEEN :from AND :to
               -- Punches an admin crossed off are kept in the table only to stop
               -- the device re-importing them; they must never be paid.
               AND al.review_status <> 'rejected'
        ";
        $params = [':from' => $from, ':to' => $to];
        if ($employeeId !== null) {
            $sql .= ' WHERE e.id = :eid';
            $params[':eid'] = $employeeId;
        }
        $sql .= ' GROUP BY e.id, DATE(al.punch_time) ORDER BY e.name, day';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $cfg  = self::settings();

        $out = [];
        // Track which (employee, day) cells already have worked punches, so a paid
        // leave on a day the employee actually worked is not double-counted.
        $worked = [];
        foreach ($rows as $r) {
            $times = $r['punch_times'] ? explode(',', $r['punch_times']) : [];
            $d     = self::computeDay($times, $cfg, (string)$r['day']);

            $rate = (float)$r['hourly_rate'];
            // Overtime hours are paid at the same base rate as regular hours —
            // "overtime" here is just a Sunday label, not a pay premium.
            $wage = round(($d['regular_hours'] + $d['overtime_hours']) * $rate, 2);

            $worked[(int)$r['employee_id'] . '|' . $r['day']] = true;

            $out[] = [
                'employee_id'        => (int)$r['employee_id'],
                'device_user_id'     => (string)$r['device_user_id'],
                'name'               => (string)$r['name'],
                'department'         => $r['department'],
                'day'                => (string)$r['day'],
                'first_in'           => $d['first_in'],
                'last_out'           => $d['last_out'],
                'gross_hours'        => round($d['gross_hours'], 2),
                'break_hours'        => round($d['break_hours'], 2),
                'actual_break_hours' => round($d['actual_break_hours'], 2),
                'meal_break_hours'   => round($d['meal_break_hours'], 2),
                'short_break_hours'  => round($d['short_break_hours'], 2),
                'breaks'             => $d['breaks'],
                'net_hours'          => round($d['net_hours'], 2),
                'regular_hours'      => round($d['regular_hours'], 2),
                'overtime_hours'     => round($d['overtime_hours'], 2),
                'day_status'         => $d['day_status'],
                'on_break'           => $d['on_break'],
                'hourly_rate'        => $rate,
                'daily_wage'         => $wage,
            ];
        }

        // Merge approved leave days (paid and unpaid): each such day with no worked
        // punches gets a row so payroll shows exactly what happened, rather than the
        // employee simply appearing absent. Paid leave is credited at the configured
        // paid-leave hours/wage; unpaid leave is an explicit $0 line so the deduction
        // is visible instead of just missing.
        $leaveUsed = [];
        foreach (self::approvedLeaveDays($from, $to, $employeeId) as $lv) {
            $key = $lv['employee_id'] . '|' . $lv['day'];
            if (isset($worked[$key])) continue;    // worked that day — don't double pay
            if (isset($leaveUsed[$key])) continue;  // overlapping leave requests same day
            $leaveUsed[$key] = true;

            $isPaid = (bool)$lv['is_paid'];
            $rate   = (float)$lv['hourly_rate'];
            $hours  = $isPaid ? $cfg['paid_leave_hours'] : 0.0;
            $out[] = [
                'employee_id'        => (int)$lv['employee_id'],
                'device_user_id'     => (string)$lv['device_user_id'],
                'name'               => (string)$lv['name'],
                'department'         => $lv['department'],
                'day'                => (string)$lv['day'],
                'first_in'           => null,
                'last_out'           => null,
                'gross_hours'        => 0.0,
                'break_hours'        => 0.0,
                'actual_break_hours' => 0.0,
                'meal_break_hours'   => 0.0,
                'short_break_hours'  => 0.0,
                'breaks'             => [],
                'net_hours'          => round($hours, 2),
                'regular_hours'      => round($hours, 2),
                'overtime_hours'     => 0.0,
                'day_status'         => $isPaid ? 'leave_paid' : 'leave_unpaid',
                'on_break'           => false,
                'hourly_rate'        => $rate,
                'daily_wage'         => round($hours * $rate, 2),
                'leave_type'         => $lv['leave_type'],
            ];
        }

        // Merge holiday avails (paid or unpaid): a manual per-employee record that an
        // employee avails a calendar holiday (see Holiday::availEmployee). Same $0
        // visibility for unpaid as leave, and never doubles up with worked/leave days.
        foreach (self::approvedHolidayDays($from, $to, $employeeId) as $hv) {
            $key = $hv['employee_id'] . '|' . $hv['day'];
            if (isset($worked[$key])) continue;    // worked that day — don't double pay
            if (isset($leaveUsed[$key])) continue; // a leave already covers this day

            $isPaid = (bool)$hv['is_paid'];
            $rate   = (float)$hv['hourly_rate'];
            $hours  = $isPaid ? $cfg['paid_leave_hours'] : 0.0;
            $out[] = [
                'employee_id'        => (int)$hv['employee_id'],
                'device_user_id'     => (string)$hv['device_user_id'],
                'name'               => (string)$hv['name'],
                'department'         => $hv['department'],
                'day'                => (string)$hv['day'],
                'first_in'           => null,
                'last_out'           => null,
                'gross_hours'        => 0.0,
                'break_hours'        => 0.0,
                'actual_break_hours' => 0.0,
                'meal_break_hours'   => 0.0,
                'short_break_hours'  => 0.0,
                'breaks'             => [],
                'net_hours'          => round($hours, 2),
                'regular_hours'      => round($hours, 2),
                'overtime_hours'     => 0.0,
                'day_status'         => $isPaid ? 'holiday_paid' : 'holiday_unpaid',
                'on_break'           => false,
                'hourly_rate'        => $rate,
                'daily_wage'         => round($hours * $rate, 2),
                'holiday_name'       => $hv['holiday_name'],
            ];
        }

        // Re-sort so merged leave/holiday rows land in date order within each employee.
        usort($out, static fn($a, $b) =>
            [$a['name'], $a['day']] <=> [$b['name'], $b['day']]);

        return $out;
    }

    /**
     * Expand approved leaves (paid or unpaid) overlapping [from..to] into one row per
     * leave day. Weekends are still included — adjust here if a working-day calendar
     * is needed.
     *
     * @return array<int,array{employee_id:int,device_user_id:string,name:string,department:?string,day:string,hourly_rate:float,leave_type:string,is_paid:int}>
     */
    private static function approvedLeaveDays(string $from, string $to, ?int $employeeId): array
    {
        $sql = "SELECT l.employee_id, l.leave_type, l.is_paid, l.from_date, l.to_date,
                       e.device_user_id, e.name, e.department, e.hourly_rate
                  FROM leaves l
                  JOIN employees e ON e.id = l.employee_id
                 WHERE l.status = 'approved'
                   AND l.from_date <= :to AND l.to_date >= :from";
        $params = [':from' => $from, ':to' => $to];
        if ($employeeId !== null) { $sql .= ' AND l.employee_id = :eid'; $params[':eid'] = $employeeId; }

        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            return []; // leaves table not present yet — no leave credit/deduction
        }

        $days  = [];
        $start = strtotime($from);
        $end   = strtotime($to);
        foreach ($stmt->fetchAll() as $r) {
            $lf = max($start, strtotime((string)$r['from_date']));
            $lt = min($end,   strtotime((string)$r['to_date']));
            for ($t = $lf; $t <= $lt; $t += 86400) {
                $days[] = [
                    'employee_id'    => (int)$r['employee_id'],
                    'device_user_id' => (string)$r['device_user_id'],
                    'name'           => (string)$r['name'],
                    'department'     => $r['department'],
                    'day'            => date('Y-m-d', $t),
                    'hourly_rate'    => (float)$r['hourly_rate'],
                    'leave_type'     => (string)$r['leave_type'],
                    'is_paid'        => (int)$r['is_paid'],
                ];
            }
        }
        return $days;
    }

    /**
     * Approved (manually recorded) holiday avails overlapping [from..to]. Holidays
     * are always single dates, so no range expansion is needed (unlike leave).
     *
     * @return array<int,array{employee_id:int,device_user_id:string,name:string,department:?string,day:string,hourly_rate:float,holiday_name:string,is_paid:int}>
     */
    private static function approvedHolidayDays(string $from, string $to, ?int $employeeId): array
    {
        $sql = "SELECT ha.employee_id, ha.is_paid, h.name AS holiday_name, h.holiday_date AS day,
                       e.device_user_id, e.name, e.department, e.hourly_rate
                  FROM holiday_avails ha
                  JOIN holidays h  ON h.id = ha.holiday_id AND h.deleted_at IS NULL
                  JOIN employees e ON e.id = ha.employee_id
                 WHERE h.holiday_date BETWEEN :from AND :to";
        $params = [':from' => $from, ':to' => $to];
        if ($employeeId !== null) { $sql .= ' AND ha.employee_id = :eid'; $params[':eid'] = $employeeId; }

        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (\PDOException $e) {
            return []; // holiday tables not present yet — no holiday credit/deduction
        }

        $days = [];
        foreach ($stmt->fetchAll() as $r) {
            $days[] = [
                'employee_id'    => (int)$r['employee_id'],
                'device_user_id' => (string)$r['device_user_id'],
                'name'           => (string)$r['name'],
                'department'     => $r['department'],
                'day'            => (string)$r['day'],
                'hourly_rate'    => (float)$r['hourly_rate'],
                'holiday_name'   => (string)$r['holiday_name'],
                'is_paid'        => (int)$r['is_paid'],
            ];
        }
        return $days;
    }

    /**
     * Roll up daily rows to one summary line per employee for the same range.
     *
     * @return array<int,array{
     *     employee_id:int, device_user_id:string, name:string, department:?string,
     *     days_worked:int, unpaid_leave_days:int, unpaid_holiday_days:int,
     *     total_hours:float, hourly_rate:float, total_wage:float
     * }>
     */
    public static function summary(string $from, string $to): array
    {
        $rows = self::daily($from, $to);
        $agg  = [];
        foreach ($rows as $r) {
            $id = $r['employee_id'];
            if (!isset($agg[$id])) {
                $agg[$id] = [
                    'employee_id'        => $id,
                    'device_user_id'     => $r['device_user_id'],
                    'name'               => $r['name'],
                    'department'         => $r['department'],
                    'days_worked'        => 0,
                    'unpaid_leave_days'  => 0,
                    'unpaid_holiday_days'=> 0,
                    'total_hours'        => 0.0,
                    'overtime_hours'     => 0.0,
                    'hourly_rate'        => $r['hourly_rate'],
                    'total_wage'         => 0.0,
                ];
            }
            $status = $r['day_status'] ?? '';
            if ($status === 'leave_unpaid') {
                $agg[$id]['unpaid_leave_days']++; // $0 by construction — no wage/hours to add
            } elseif ($status === 'holiday_unpaid') {
                $agg[$id]['unpaid_holiday_days']++;
            } else {
                $agg[$id]['days_worked']++;
                $agg[$id]['total_hours']    += $r['net_hours'];
                $agg[$id]['overtime_hours'] += $r['overtime_hours'] ?? 0.0;
                $agg[$id]['total_wage']     += $r['daily_wage'];
            }
        }
        foreach ($agg as &$a) {
            $a['total_hours']    = round($a['total_hours'], 2);
            $a['overtime_hours'] = round($a['overtime_hours'], 2);
            $a['total_wage']     = round($a['total_wage'],  2);
        }
        unset($a);
        return array_values($agg);
    }

    public static function setHourlyRate(int $employeeId, float $rate): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE employees SET hourly_rate = :r WHERE id = :id'
        );
        $stmt->execute([':r' => $rate, ':id' => $employeeId]);
    }
}
