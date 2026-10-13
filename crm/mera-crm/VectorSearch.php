<?php
/**
 * =====================================================
 * FILE: VectorSearch.php
 * PURPOSE: Lightweight local context indexer + semantic search
 * =====================================================
 * 
 * This class indexes:
 * - Database table schemas
 * - Important helper functions
 * - Common CRM modules
 * 
 * It stores data in a local SQLite database for fast search.
 * Real vector embeddings require external APIs, so we use
 * a practical keyword + scoring approach that works offline.
 */

class VectorSearch
{
    private $db;          // SQLite connection
    private $dbPath = __DIR__ . '/ai_context.sqlite';

    public function __construct()
    {
        // Create / open SQLite database
        $this->db = new PDO('sqlite:' . $this->dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();
    }

    /**
     * Create required tables if they do not exist
     */
    private function createTables()
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS context_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,          -- table, function, module
                name TEXT NOT NULL,
                content TEXT NOT NULL,       -- description + schema / code
                keywords TEXT,               -- searchable keywords
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Index database schema (call this once or when schema changes)
     */
    public function indexDatabaseSchema(PDO $pdo)
    {
        // Clear old schema entries
        $this->db->exec("DELETE FROM context_items WHERE type = 'table'");

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $columns = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);

            $colList = [];
            foreach ($columns as $col) {
                $colList[] = $col['Field'] . ' (' . $col['Type'] . ')';
            }

            $content = "Table: $table\nColumns: " . implode(', ', $colList);
            $keywords = $table . ' ' . implode(' ', array_column($columns, 'Field'));

            $stmt = $this->db->prepare("
                INSERT INTO context_items (type, name, content, keywords)
                VALUES ('table', ?, ?, ?)
            ");
            $stmt->execute([$table, $content, $keywords]);
        }
    }

    /**
     * Index important helper functions
     */
    public function indexHelpers()
    {
        $helpers = [
            [
                'name' => 'log_activity',
                'content' => 'function log_activity($conn, $module, $action, $record_id = null, $data = []) – Records user actions into activity_logs table.',
                'keywords' => 'log activity audit trail history'
            ],
            [
                'name' => 'password_hash',
                'content' => 'Use password_hash($password, PASSWORD_DEFAULT) and password_verify() for secure password handling.',
                'keywords' => 'password hash security login'
            ]
        ];

        $this->db->exec("DELETE FROM context_items WHERE type = 'function'");

        $stmt = $this->db->prepare("
            INSERT INTO context_items (type, name, content, keywords)
            VALUES ('function', ?, ?, ?)
        ");

        foreach ($helpers as $h) {
            $stmt->execute([$h['name'], $h['content'], $h['keywords']]);
        }
    }

    /**
     * Semantic-ish search
     * Returns most relevant context items for a query
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = strtolower(trim($query));
        $words = preg_split('/\s+/', $query);

        $results = [];
        $items = $this->db->query("SELECT * FROM context_items")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $score = 0;
            $text = strtolower($item['name'] . ' ' . $item['keywords'] . ' ' . $item['content']);

            foreach ($words as $word) {
                if (strlen($word) < 2) continue;
                if (strpos($text, $word) !== false) {
                    $score += 2;
                }
            }

            // Boost exact name match
            if (strpos(strtolower($item['name']), $query) !== false) {
                $score += 5;
            }

            if ($score > 0) {
                $item['score'] = $score;
                $results[] = $item;
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }
}