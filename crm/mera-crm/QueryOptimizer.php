<?php
/**
 * =====================================================
 * FILE: QueryOptimizer.php
 * PURPOSE: Schema-based index suggestions + performance insights
 * =====================================================
 */

class QueryOptimizer
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Suggest indexes for common unindexed foreign-key style columns
     * Why: Columns used in JOINs / WHERE without indexes cause slow scans
     */
    public function suggestIndexes(): array
    {
        $items = [];
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $columns = $this->pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            $indexes = $this->pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            $indexedCols = array_unique(array_column($indexes, 'Column_name'));

            foreach ($columns as $col) {
                $field = $col['Field'];
                $type  = strtolower($col['Type']);

                // Heuristic: *_id columns that are not indexed
                if (preg_match('/_id$/', $field) && !in_array($field, $indexedCols, true) && $col['Key'] !== 'PRI') {
                    $idxName = "idx_{$table}_{$field}";
                    $items[] = [
                        'title'  => "Missing index on {$table}.{$field}",
                        'sql'    => "CREATE INDEX `{$idxName}` ON `{$table}` (`{$field}`);",
                        'reason' => 'Foreign-key style column without index often slows JOINs and filters.'
                    ];
                }

                // Email / status columns often filtered
                if (in_array($field, ['email', 'status', 'username'], true) && !in_array($field, $indexedCols, true)) {
                    $idxName = "idx_{$table}_{$field}";
                    $items[] = [
                        'title'  => "Consider index on {$table}.{$field}",
                        'sql'    => "CREATE INDEX `{$idxName}` ON `{$table}` (`{$field}`);",
                        'reason' => 'Frequently filtered lookup column.'
                    ];
                }
            }
        }

        return ['success' => true, 'items' => $items];
    }

    /**
     * Basic slow-query style insights
     * Note: Full slow query log needs MySQL slow_query_log enabled.
     * Here we provide safe, actionable diagnostics from information_schema.
     */
    public function analyzePerformance(): array
    {
        $items = [];

        // Tables without primary key (bad for InnoDB)
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $cols = $this->pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            $hasPk = false;
            foreach ($cols as $c) {
                if ($c['Key'] === 'PRI') { $hasPk = true; break; }
            }
            if (!$hasPk) {
                $items[] = [
                    'title'  => "Table `{$table}` has no PRIMARY KEY",
                    'detail' => "ALTER TABLE `{$table}` ADD COLUMN `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY FIRST;",
                    'reason' => 'InnoDB tables without PK can cause performance and replication issues.'
                ];
            }
        }

        // Large tables heuristic via information_schema (approximate)
        try {
            $sql = "SELECT table_name, table_rows, data_length, index_length
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    ORDER BY data_length DESC
                    LIMIT 5";
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $sizeMb = round((($r['data_length'] ?? 0) + ($r['index_length'] ?? 0)) / 1024 / 1024, 2);
                $items[] = [
                    'title'  => "Table size insight: {$r['table_name']}",
                    'detail' => "Approx rows: {$r['table_rows']} • Size: {$sizeMb} MB",
                    'reason' => 'Large tables benefit most from proper indexes and selective queries.'
                ];
            }
        } catch (Exception $e) {
            // ignore if no permission on information_schema
        }

        if (empty($items)) {
            $items[] = [
                'title'  => 'No critical structural issues detected',
                'detail' => 'Enable MySQL slow_query_log for deeper runtime analysis.',
                'reason' => ''
            ];
        }

        return ['success' => true, 'items' => $items];
    }
}