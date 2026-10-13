<?php
/**
 * =====================================================
 * FILE: MigrationRunner.php
 * PURPOSE: Database migration files ko safely run karna
 *
 * WORKFLOW:
 * 1. Database connection receive hoti hai.
 * 2. Migration folder scan hota hai.
 * 3. Migration tracking table ensure hoti hai.
 * 4. Already-run migrations skip hoti hain.
 * 5. New migrations correct order mein execute hoti hain.
 * 6. Successful migration tracking table mein save hoti hai.
 * =====================================================
 */

declare(strict_types=1);

/**
 * MigrationRunner
 *
 * Bhai is class ko database ka "version manager" samjho.
 *
 * Agar kal hum:
 *
 * 002_clients_fields.sql
 *
 * banate hain, to manually purani 001 file dobara run nahi karni.
 *
 * Runner dekhega:
 *
 * 001 = already completed
 * 002 = pending
 *
 * aur sirf 002 run karega.
 */
class MigrationRunner
{
    /**
     * PDO database connection.
     */
    private PDO $pdo;

    /**
     * Migration files ka folder.
     */
    private string $migrationPath;

    /**
     * Constructor.
     */
    public function __construct(
        PDO $pdo,
        string $migrationPath
    ) {
        /*
         * PDO object save kar rahe hain.
         */
        $this->pdo = $pdo;

        /*
         * Migration directory ka path save kar rahe hain.
         */
        $this->migrationPath = rtrim(
            $migrationPath,
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * Migration tracking table create karna.
     */
    private function ensureTrackingTable(): void
    {
        /*
         * Yeh table sirf migration history ke liye hai.
         *
         * IMPORTANT:
         * Is table mein business data nahi hota.
         */
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `migrations` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `migration` VARCHAR(255) NOT NULL,

    `batch` INT UNSIGNED NOT NULL,

    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_migrations_migration`
        (`migration`),

    KEY `idx_migrations_batch`
        (`batch`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL;

        /*
         * PDO::exec() = PHP PDO method.
         *
         * Is query mein user input nahi hai,
         * isliye prepared statement ki zaroorat nahi.
         */
        $this->pdo->exec($sql);
    }

    /**
     * Migration files find karna.
     */
    private function getMigrationFiles(): array
    {
        /*
         * glob() = PHP built-in function.
         *
         * Directory ke SQL files find karta hai.
         */
        $files = glob(
            $this->migrationPath . DIRECTORY_SEPARATOR . '*.sql'
        );

        /*
         * Agar koi migration nahi mili to empty array.
         */
        if ($files === false) {
            return [];
        }

        /*
         * sort() = PHP built-in function.
         *
         * File names ko ascending order mein arrange karta hai.
         *
         * Example:
         *
         * 001_core.sql
         * 002_clients.sql
         * 003_services.sql
         */
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Already executed migrations retrieve karna.
     */
    private function getExecutedMigrations(): array
    {
        /*
         * Database se migration history read kar rahe hain.
         */
        $stmt = $this->pdo->query(
            'SELECT migration FROM migrations ORDER BY id ASC'
        );

        /*
         * fetchAll() = PDO method.
         *
         * Existing migration names array mein return karta hai.
         */
        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /**
     * Next batch number calculate karna.
     */
    private function getNextBatch(): int
    {
        /*
         * MAX() latest batch number find karta hai.
         *
         * COALESCE() agar koi migration nahi hai
         * to 0 return karta hai.
         */
        $stmt = $this->pdo->query(
            'SELECT COALESCE(MAX(batch), 0) FROM migrations'
        );

        /*
         * fetchColumn() = PDO method.
         *
         * Sirf first column/value return karta hai.
         */
        $lastBatch = (int) $stmt->fetchColumn();

        return $lastBatch + 1;
    }

    /**
     * Pending migrations run karna.
     *
     * @return array Run hui migrations ki list.
     */
    public function run(): array
    {
        /*
         * Sabse pehle tracking table ensure karte hain.
         */
        $this->ensureTrackingTable();

        /*
         * Migration files retrieve kar rahe hain.
         */
        $files = $this->getMigrationFiles();

        /*
         * Already executed migrations retrieve kar rahe hain.
         */
        $executed = $this->getExecutedMigrations();

        /*
         * Fast lookup ke liye existing migrations ko keys bana rahe hain.
         */
        $executedMap = array_flip($executed);

        /*
         * Is run ke andar jo migrations execute hongi
         * unki list.
         */
        $ran = [];

        /*
         * New migration batch number.
         */
        $batch = $this->getNextBatch();

        /*
         * Har migration file process karna.
         */
        foreach ($files as $file) {

            /*
             * basename() = PHP built-in function.
             *
             * Full path se sirf file name nikalta hai.
             */
            $migrationName = basename($file);

            /*
             * Agar migration pehle run ho chuki hai
             * to usay dobara execute nahi karna.
             */
            if (isset($executedMap[$migrationName])) {
                continue;
            }

            /*
             * SQL file read kar rahe hain.
             *
             * file_get_contents() = PHP built-in function.
             */
            $sql = file_get_contents($file);

            /*
             * Agar file read nahi ho saki to clear exception.
             */
            if ($sql === false) {
                throw new RuntimeException(
                    "Migration file could not be read: {$migrationName}"
                );
            }

            /*
             * Transaction start kar rahe hain.
             *
             * Agar migration ke beech mein error aaye,
             * database changes rollback kiye ja sakte hain
             * jahan MySQL statement/engine support karta hai.
             */
            $this->pdo->beginTransaction();

            try {

                /*
                 * Migration SQL execute kar rahe hain.
                 *
                 * Multiple SQL statements wali migration ke liye
                 * PDO/MySQL configuration compatible honi chahiye.
                 */
                $this->pdo->exec($sql);

                /*
                 * Migration successful hui,
                 * ab tracking table mein record save karenge.
                 */
                $stmt = $this->pdo->prepare(
                    'INSERT INTO migrations
                    (migration, batch)
                    VALUES (?, ?)'
                );

                /*
                 * execute() = PDO method.
                 *
                 * Prepared statement SQL injection risk ko avoid karta hai.
                 */
                $stmt->execute([
                    $migrationName,
                    $batch
                ]);

                /*
                 * Transaction commit.
                 */
                $this->pdo->commit();

                /*
                 * Successful migration list mein add.
                 */
                $ran[] = $migrationName;

            } catch (Throwable $exception) {

                /*
                 * Agar transaction active hai to rollback.
                 */
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                /*
                 * Original error ko preserve karke
                 * caller tak send kar rahe hain.
                 */
                throw new RuntimeException(
                    "Migration failed: {$migrationName}",
                    0,
                    $exception
                );
            }
        }

        return $ran;
    }
}