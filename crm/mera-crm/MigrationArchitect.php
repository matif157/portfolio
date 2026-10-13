<?php
/**
 * =====================================================
 * FILE: MigrationArchitect.php
 * PURPOSE: Natural language → safe SQL migration generator
 * =====================================================
 * Note: This is a deterministic rule-based generator mapped
 * to your live schema. It does NOT execute arbitrary SQL.
 */

class MigrationArchitect
{
    private PDO $pdo;
    private string $migrationsDir;

    public function __construct(PDO $pdo, string $projectRoot)
    {
        $this->pdo = $pdo;
        $this->migrationsDir = rtrim($projectRoot, '/\\') . '/database/migrations';

        if (!is_dir($this->migrationsDir)) {
            @mkdir($this->migrationsDir, 0755, true);
        }
    }

    /**
     * Load current schema for context-aware generation
     */
    public function getSchema(): array
    {
        $tables = [];
        $stmt = $this->pdo->query('SHOW TABLES');
        $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableNames as $name) {
            $cols = $this->pdo->query("DESCRIBE `{$name}`")->fetchAll(PDO::FETCH_ASSOC);
            $tables[] = ['name' => $name, 'columns' => $cols];
        }
        return $tables;
    }

    /**
     * Convert plain English into a safe migration SQL script
     * Supported patterns (examples):
     * - add X column to Y table
     * - create table X with ...
     * - add index on table.column
     */
    public function generateFromPrompt(string $prompt): array
    {
        $prompt = trim($prompt);
        $lower  = strtolower($prompt);
        $schema = $this->getSchema();
        $tableNames = array_map(fn($t) => strtolower($t['name']), $schema);

        // ---------- Pattern: ADD COLUMN ----------
        // e.g. "Add phone column to clients table"
        // e.g. "Add nullable email_verified_at timestamp to users"
        if (preg_match('/add\s+(?:a\s+)?(\w+)\s+column\s+to\s+(\w+)/i', $prompt, $m) ||
            preg_match('/add\s+(?:a\s+)?(?:nullable\s+)?(\w+)\s+(?:varchar|text|int|timestamp|datetime|boolean)?\s*(?:column\s+)?to\s+(?:the\s+)?(\w+)/i', $prompt, $m)) {

            $column = $this->safeIdent($m[1]);
            $table  = $this->safeIdent($m[2]);

            if (!in_array(strtolower($table), $tableNames, true)) {
                return ['success' => false, 'error' => "Table `{$table}` not found in schema"];
            }

            $type = 'VARCHAR(255) NULL';
            if (stripos($prompt, 'timestamp') !== false) $type = 'TIMESTAMP NULL';
            if (stripos($prompt, 'datetime') !== false)  $type = 'DATETIME NULL';
            if (stripos($prompt, 'int') !== false)       $type = 'INT NULL';
            if (stripos($prompt, 'text') !== false)      $type = 'TEXT NULL';
            if (stripos($prompt, 'boolean') !== false || stripos($prompt, 'bool') !== false) $type = 'TINYINT(1) DEFAULT 0';

            $sql = "-- Migration generated from: {$prompt}\n";
            $sql .= "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$type};\n";

            return ['success' => true, 'sql' => $sql];
        }

        // ---------- Pattern: CREATE TABLE ----------
        if (preg_match('/create\s+(?:a\s+)?table\s+(\w+)/i', $prompt, $m)) {
            $table = $this->safeIdent($m[1]);

            $sql  = "-- Migration generated from: {$prompt}\n";
            $sql .= "CREATE TABLE IF NOT EXISTS `{$table}` (\n";
            $sql .= "  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n";
            $sql .= "  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
            $sql .= "  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n";

            // Optional FK hint
            if (preg_match('/client_id|foreign\s+key/i', $prompt)) {
                $sql .= "\n-- Optional: add foreign key (uncomment if clients table exists)\n";
                $sql .= "-- ALTER TABLE `{$table}` ADD COLUMN `client_id` INT UNSIGNED NULL;\n";
                $sql .= "-- ALTER TABLE `{$table}` ADD CONSTRAINT fk_{$table}_client FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL;\n";
            }

            return ['success' => true, 'sql' => $sql];
        }

        // ---------- Pattern: ADD INDEX ----------
        if (preg_match('/add\s+index\s+on\s+(\w+)\.(\w+)/i', $prompt, $m) ||
            preg_match('/index\s+on\s+(\w+)\((\w+)\)/i', $prompt, $m)) {

            $table  = $this->safeIdent($m[1]);
            $column = $this->safeIdent($m[2]);
            $idx    = "idx_{$table}_{$column}";

            $sql  = "-- Migration generated from: {$prompt}\n";
            $sql .= "CREATE INDEX `{$idx}` ON `{$table}` (`{$column}`);\n";

            return ['success' => true, 'sql' => $sql];
        }

        // Fallback help
        return [
            'success' => false,
            'error'   => "Could not map prompt to a safe migration pattern.\nSupported examples:\n- Add phone column to clients table\n- Create table project_notes\n- Add index on clients.email"
        ];
    }

    /**
     * Save migration file with timestamp versioning
     */
    public function saveMigration(string $sql): array
    {
        $filename = date('Y_m_d_His') . '_auto_migration.sql';
        $path = $this->migrationsDir . DIRECTORY_SEPARATOR . $filename;

        if (file_put_contents($path, $sql) === false) {
            return ['success' => false, 'error' => 'Unable to write migration file'];
        }

        return ['success' => true, 'file' => 'database/migrations/' . $filename];
    }

    private function safeIdent(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }
}