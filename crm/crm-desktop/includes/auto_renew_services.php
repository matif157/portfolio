<?php
/**
 * Automatic service renewal engine.
 *
 * Renewal rules are stored in the `service_renewal_rules` table — one row per
 * service membership. Nothing about period length, due-day, or weekend-shift
 * is hard-coded in this file; change a rule via SQL (or a future admin UI)
 * and the engine picks it up on the next run.
 *
 * Triggered on every authenticated page load via includes/auth.php; a
 * once-per-day file lock keeps the actual work to a single run per day.
 */

if (!function_exists('auto_renew_install_schema')) {
    /**
     * Idempotent self-install: create the rules table and add the date columns
     * if they don't already exist. Runs once per process at engine start so
     * dropping these PHP files onto a fresh machine "just works" with no
     * manual SQL setup.
     */
    function auto_renew_install_schema(mysqli $con): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        @mysqli_query($con, "
            CREATE TABLE IF NOT EXISTS service_renewal_rules (
                rule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                membership_id INT NOT NULL,
                period_length INT NOT NULL DEFAULT 1,
                period_unit ENUM('day','month') NOT NULL DEFAULT 'month',
                due_anchor ENUM('none','day_of_next_month','last_day_of_next_month') NOT NULL DEFAULT 'none',
                due_day TINYINT NULL,
                shift_weekend_to_monday TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes VARCHAR(255) NULL,
                PRIMARY KEY (rule_id),
                UNIQUE KEY uniq_membership (membership_id)
            ) ENGINE=InnoDB
        ");

        // ADD COLUMN IF NOT EXISTS is MariaDB-only; on MySQL it errors out
        // silently (we have @) which is fine because the columns will already
        // be there from a prior install. To be safe on MySQL too, check first.
        foreach (['orders', 'orders_auto_renewed'] as $tbl) {
            foreach (['renewal_date', 'due_date'] as $col) {
                $res = @mysqli_query($con, "SHOW COLUMNS FROM `$tbl` LIKE '$col'");
                if ($res && mysqli_num_rows($res) === 0) {
                    @mysqli_query($con, "ALTER TABLE `$tbl` ADD COLUMN `$col` DATE NULL");
                }
            }
        }
    }
}

if (!function_exists('auto_renew_seed_default_rules')) {
    /**
     * One-time seed: if service_renewal_rules is empty, populate it by
     * matching memberships by normalized name. Safe to call on every run —
     * the UNIQUE(membership_id) constraint + IGNORE prevents duplicates.
     */
    function auto_renew_seed_default_rules(mysqli $con): void
    {
        $check = mysqli_query($con, "SELECT COUNT(*) AS c FROM service_renewal_rules");
        if (!$check) return;
        $row = mysqli_fetch_assoc($check);
        if ((int) $row['c'] > 0) return;

        // Default rule shapes per service kind. Anyone can override these later
        // by UPDATE-ing the row.
        $defaults = [
            'SALES TAX'   => ['period_length' => 3, 'period_unit' => 'month', 'due_anchor' => 'day_of_next_month',       'due_day' => 20,   'shift' => 1, 'notes' => 'Quarterly; due 20th of next month'],
            'PAYROLL TAX' => ['period_length' => 3, 'period_unit' => 'month', 'due_anchor' => 'last_day_of_next_month',  'due_day' => null, 'shift' => 1, 'notes' => 'Quarterly; due last day of next month'],
            'PAYROLL'     => ['period_length' => 3, 'period_unit' => 'month', 'due_anchor' => 'last_day_of_next_month',  'due_day' => null, 'shift' => 1, 'notes' => 'Quarterly; due last day of next month'],
            'BOOK KEEPING'=> ['period_length' => 1, 'period_unit' => 'month', 'due_anchor' => 'none',                    'due_day' => null, 'shift' => 0, 'notes' => 'Monthly; no due date'],
            'BOOKKEEPING' => ['period_length' => 1, 'period_unit' => 'month', 'due_anchor' => 'none',                    'due_day' => null, 'shift' => 0, 'notes' => 'Monthly; no due date'],
        ];

        $res = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships");
        if (!$res) return;

        while ($m = mysqli_fetch_assoc($res)) {
            $norm = strtoupper(preg_replace('/[^A-Z0-9]+/i', ' ', $m['membership_name']));
            $norm = trim(preg_replace('/\s+/', ' ', $norm));
            if (!isset($defaults[$norm])) continue;

            $d   = $defaults[$norm];
            $mid = (int) $m['membership_id'];
            $pl  = (int) $d['period_length'];
            $pu  = mysqli_real_escape_string($con, $d['period_unit']);
            $da  = mysqli_real_escape_string($con, $d['due_anchor']);
            $dd  = $d['due_day'] === null ? 'NULL' : (int) $d['due_day'];
            $sh  = (int) $d['shift'];
            $nt  = mysqli_real_escape_string($con, $d['notes']);

            mysqli_query($con,
                "INSERT IGNORE INTO service_renewal_rules
                    (membership_id, period_length, period_unit, due_anchor, due_day, shift_weekend_to_monday, is_active, notes)
                 VALUES
                    ($mid, $pl, '$pu', '$da', $dd, $sh, 1, '$nt')");
        }
    }
}

if (!function_exists('auto_renew_load_rules')) {
    /**
     * @return array<int,array{period_length:int,period_unit:string,due_anchor:string,due_day:?int,shift:int}>
     */
    function auto_renew_load_rules(mysqli $con): array
    {
        $out = [];
        $res = mysqli_query($con, "SELECT membership_id, period_length, period_unit, due_anchor, due_day, shift_weekend_to_monday FROM service_renewal_rules WHERE is_active = 1");
        if (!$res) return $out;
        while ($r = mysqli_fetch_assoc($res)) {
            $out[(int) $r['membership_id']] = [
                'period_length' => (int) $r['period_length'],
                'period_unit'   => $r['period_unit'],
                'due_anchor'    => $r['due_anchor'],
                'due_day'       => $r['due_day'] === null ? null : (int) $r['due_day'],
                'shift'         => (int) $r['shift_weekend_to_monday'],
            ];
        }
        return $out;
    }
}

if (!function_exists('auto_renew_shift_to_monday')) {
    function auto_renew_shift_to_monday(DateTime $d): DateTime
    {
        $dow = (int) $d->format('N'); // 1=Mon ... 7=Sun
        if ($dow === 6) $d->modify('+2 days');
        elseif ($dow === 7) $d->modify('+1 day');
        return $d;
    }
}

if (!function_exists('auto_renew_compute_next_period')) {
    /**
     * Compute the next period using a DB-loaded rule.
     *
     * @param array{period_length:int,period_unit:string,due_anchor:string,due_day:?int,shift:int} $rule
     * @return array{start:string,end:string,renewal:string,due:?string}|null
     */
    function auto_renew_compute_next_period(array $rule, string $prev_exp_date): ?array
    {
        if ($rule['period_length'] <= 0) return null;

        $start = (new DateTime($prev_exp_date))->modify('+1 day');

        $unit_word = $rule['period_unit'] === 'day' ? 'days' : 'months';
        $end = (clone $start)->modify('+' . $rule['period_length'] . ' ' . $unit_word)->modify('-1 day');
        $renewal = (clone $end)->modify('+1 day');

        $due = null;
        if ($rule['due_anchor'] !== 'none') {
            $anchor = (clone $end)->modify('first day of next month');
            if ($rule['due_anchor'] === 'day_of_next_month') {
                $day = $rule['due_day'] !== null ? max(1, min(31, (int) $rule['due_day'])) : 1;
                // Clamp to last day of that month so day=31 in Feb doesn't overflow.
                $lastOfMonth = (int) (clone $anchor)->modify('last day of this month')->format('j');
                $day = min($day, $lastOfMonth);
                $due = $anchor->setDate((int) $anchor->format('Y'), (int) $anchor->format('n'), $day);
            } elseif ($rule['due_anchor'] === 'last_day_of_next_month') {
                $due = $anchor->modify('last day of this month');
            }
            if ($due && $rule['shift']) {
                $due = auto_renew_shift_to_monday($due);
            }
        }

        return [
            'start'   => $start->format('Y-m-d'),
            'end'     => $end->format('Y-m-d'),
            'renewal' => $renewal->format('Y-m-d'),
            'due'     => $due ? $due->format('Y-m-d') : null,
        ];
    }
}

if (!function_exists('run_service_auto_renewals')) {
    /**
     * @return array{ran:bool,created:int,skipped:int,errors:int}
     */
    function run_service_auto_renewals(mysqli $con, bool $force = false): array
    {
        $lockFile = __DIR__ . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . '.auto_renew_last_run';
        $logFile  = __DIR__ . DIRECTORY_SEPARATOR . 'error' . DIRECTORY_SEPARATOR . 'cron_auto_extend.log';
        $today    = date('Y-m-d');

        if (!$force && is_file($lockFile)) {
            $last = trim((string) @file_get_contents($lockFile));
            if ($last === $today) {
                return ['ran' => false, 'created' => 0, 'skipped' => 0, 'errors' => 0];
            }
        }
        @file_put_contents($lockFile, $today);

        auto_renew_install_schema($con);
        auto_renew_seed_default_rules($con);
        $rules = auto_renew_load_rules($con);

        if (empty($rules)) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] no active rules in service_renewal_rules" . PHP_EOL, FILE_APPEND);
            return ['ran' => true, 'created' => 0, 'skipped' => 0, 'errors' => 0];
        }

        $ids_csv = implode(',', array_map('intval', array_keys($rules)));
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        // Find the latest active period per (mem_id, prod_id) across BOTH tables.
        // `orders_auto_renewed` is populated both by us AND by trg_after_order_insert
        // (which mirrors every new orders row), so we treat the two tables as one
        // logical timeline and only roll forward from the most recent end.
        $sql = "
            SELECT t.mem_id, t.prod_id, MAX(t.exp_date) AS latest_exp,
                   MAX(t.bil_cyc_id) AS bil_cyc_id, MAX(t.bil_cyc_type) AS bil_cyc_type,
                   MAX(t.bil_cyc_months) AS bil_cyc_months,
                   MAX(t.sub_total) AS sub_total, MAX(t.discount) AS discount,
                   MAX(t.total) AS total, MAX(t.user_id) AS user_id,
                   MAX(t.assign_to) AS assign_to
              FROM (
                SELECT mem_id, prod_id, exp_date, bil_cyc_id, bil_cyc_type, bil_cyc_months,
                       sub_total, discount, total, user_id, assign_to
                  FROM orders
                 WHERE order_status = 1 AND prod_id IN ($ids_csv)
                UNION ALL
                SELECT mem_id, prod_id, exp_date, bil_cyc_id, bil_cyc_type, bil_cyc_months,
                       sub_total, discount, total, user_id, assign_to
                  FROM orders_auto_renewed
                 WHERE order_status = 1 AND prod_id IN ($ids_csv)
              ) t
             GROUP BY t.mem_id, t.prod_id
            HAVING MAX(t.exp_date) < '$today'
        ";
        $res = mysqli_query($con, $sql);
        if (!$res) {
            @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] query failed: " . mysqli_error($con) . PHP_EOL, FILE_APPEND);
            return ['ran' => true, 'created' => 0, 'skipped' => 0, 'errors' => 1];
        }

        $candidates = [];
        while ($r = mysqli_fetch_assoc($res)) $candidates[] = $r;

        foreach ($candidates as $row) {
            $mem_id   = (int) $row['mem_id'];
            $prod_id  = (int) $row['prod_id'];
            $rule     = $rules[$prod_id] ?? null;
            if ($rule === null) { $skipped++; continue; }

            $rolling_exp = $row['latest_exp'];
            $last_inserted_id = 0;
            $iterations = 0;

            // Each renewal is stamped with the currently active parent order_id so the
            // UI can group renewals under their actual parent (not just by prod_id).
            $pq = mysqli_query($con,
                "SELECT order_id FROM orders
                  WHERE mem_id = $mem_id AND prod_id = $prod_id AND order_status = 1
                  ORDER BY order_id DESC LIMIT 1");
            $parent_oid = ($pq && ($pr = mysqli_fetch_assoc($pq))) ? (int) $pr['order_id'] : 0;

            while ($rolling_exp < $today && $iterations++ < 24) {
                $period = auto_renew_compute_next_period($rule, $rolling_exp);
                if (!$period) { $skipped++; break; }

                $start_esc = mysqli_real_escape_string($con, $period['start']);
                $dup = mysqli_query($con,
                    "SELECT auto_id FROM orders_auto_renewed
                      WHERE mem_id = $mem_id AND prod_id = $prod_id
                        AND start_date = '$start_esc'
                        AND order_status = 1 LIMIT 1");
                if ($dup && mysqli_num_rows($dup) > 0) {
                    $skipped++;
                    $rolling_exp = $period['end'];
                    continue;
                }

                $end_esc      = mysqli_real_escape_string($con, $period['end']);
                $renewal_esc  = mysqli_real_escape_string($con, $period['renewal']);
                $due_esc      = $period['due'] !== null ? "'" . mysqli_real_escape_string($con, $period['due']) . "'" : 'NULL';
                $bil_cyc_id   = (int) $row['bil_cyc_id'];
                $bil_cyc_type = (int) $row['bil_cyc_type'];
                $bil_cyc_mon  = mysqli_real_escape_string($con, (string) $row['bil_cyc_months']);
                $sub_total    = mysqli_real_escape_string($con, (string) $row['sub_total']);
                $discount     = mysqli_real_escape_string($con, (string) $row['discount']);
                $total        = mysqli_real_escape_string($con, (string) $row['total']);
                $user_id      = (int) $row['user_id'];
                $assign_to    = mysqli_real_escape_string($con, (string) $row['assign_to']);

                $ins = "INSERT INTO orders_auto_renewed
                        (order_id, mem_id, prod_id, bil_cyc_id, bil_cyc_type, bil_cyc_months,
                         start_date, exp_date, renewal_date, due_date,
                         sub_total, discount, total, user_id, order_status, assign_to)
                        VALUES
                        ($parent_oid, $mem_id, $prod_id, $bil_cyc_id, $bil_cyc_type, '$bil_cyc_mon',
                         '$start_esc', '$end_esc', '$renewal_esc', $due_esc,
                         '$sub_total', '$discount', '$total', $user_id, 1, '$assign_to')";

                if (mysqli_query($con, $ins)) {
                    $created++;
                    $last_inserted_id = mysqli_insert_id($con);
                    $rolling_exp = $period['end'];
                } else {
                    $errors++;
                    @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] insert failed mem=$mem_id prod=$prod_id: " . mysqli_error($con) . PHP_EOL, FILE_APPEND);
                    break;
                }
            }

            if ($last_inserted_id > 0) {
                $orig_exp = mysqli_real_escape_string($con, $row['latest_exp']);

                // Snapshot every active source-orders row that's expiring into orders_completed,
                // then mark them Done.  Mark the corresponding mirrors in orders_auto_renewed Done too.
                @mysqli_query($con,
                    "INSERT INTO orders_completed
                        (mem_id, prod_id, bil_cyc_id, bil_cyc_type, bil_cyc_months,
                         start_date, exp_date, sub_total, discount, total,
                         date_added, user_id, order_status, assign_to)
                     SELECT mem_id, prod_id, bil_cyc_id, bil_cyc_type, bil_cyc_months,
                            start_date, exp_date, sub_total, discount, total,
                            CURDATE(), user_id, -1, assign_to
                       FROM orders
                      WHERE mem_id = $mem_id AND prod_id = $prod_id
                        AND order_status = 1 AND exp_date <= '$orig_exp'");

                mysqli_query($con,
                    "UPDATE orders SET order_status = -1
                      WHERE mem_id = $mem_id AND prod_id = $prod_id
                        AND order_status = 1 AND exp_date <= '$orig_exp'");

                // Close out every period whose exp_date has already passed —
                // including intermediate fill rows we just inserted to catch up.
                mysqli_query($con,
                    "UPDATE orders_auto_renewed SET order_status = -1
                      WHERE mem_id = $mem_id AND prod_id = $prod_id
                        AND order_status = 1 AND exp_date < '$today'
                        AND auto_id <> $last_inserted_id");

                if (function_exists('logActivity')) {
                    @logActivity($con, $row['user_id'] ?: 0, 'CREATE', 'orders_auto_renewed', $last_inserted_id,
                        "Auto-renewed service prod_id=$prod_id for client $mem_id (latest_exp=$orig_exp)");
                }
            }
        }

        @file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] run complete: created=$created skipped=$skipped errors=$errors" . PHP_EOL, FILE_APPEND);

        return ['ran' => true, 'created' => $created, 'skipped' => $skipped, 'errors' => $errors];
    }
}
