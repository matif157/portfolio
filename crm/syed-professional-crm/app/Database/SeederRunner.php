<?php
/**
 * =====================================================
 * FILE: SeederRunner.php
 * PURPOSE: Database seed files ko safely execute karna
 *
 * WORKFLOW:
 * 1. Database connection receive hoti hai.
 * 2. Seeder tracking table ensure hoti hai.
 * 3. Seeder files discover hoti hain.
 * 4. Already executed seeders skip hotay hain.
 * 5. New seeders order mein execute hotay hain.
 * 6. Successful seeders tracking table mein save hotay hain.
 * =====================================================
 */

declare(strict_types=1);

/**
 * SeederRunner
 *
 * Bhai migration structure database ki "structure"
 * banata hai.
 *
 * Seeder structure mein initial data dalta hai.
 *
 * Example:
 *
 * Roles:
 * Super Admin
 * Admin
 * Manager
 *
 * Permissions:
 * clients.view
 * clients.create
 *
 * Yeh sab seeding data hai.
 */
class SeederRunner
{
    /**
     * PDO database connection.
     */
    private PDO $pdo;

    /**
     * Seeder folder.
     */
    private string $seedPath;

    /**
     * Constructor.
     */
    public function __construct(
        PDO $pdo,
        string $seedPath
    ) {
        $this->pdo = $pdo;

        $this->seedPath = rtrim(
            $seedPath,
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * Seeder tracking table ensure karna.
     */
    private function ensureTrackingTable(): void
    {
        /*
         * Yeh table batati hai ke kaun sa seeder
         * already successfully execute ho chuka hai.
         */
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `seeders` (

    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `seeder` VARCHAR(255) NOT NULL,

    `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_seeders_seeder`
        (`seeder`)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
SQL;

        /*
         * PDO::exec() = PHP PDO method.
         *
         * Tracking table create karta hai.
         */
        $this->pdo->exec($sql);
    }

    /**
     * Seeder files retrieve karna.
     */
    private function getSeederFiles(): array
    {
        /*
         * glob() = PHP built-in function.
         *
         * Seed directory ki .sql files find karta hai.
         */
        $files = glob(
            $this->seedPath . DIRECTORY_SEPARATOR . '*.sql'
        );

        if ($files === false) {
            return [];
        }

        /*
         * Seeders numbering ke through correct order mein run hongi.
         */
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Already executed seeders retrieve karna.
     */
    private function getExecutedSeeders(): array
    {
        /*
         * Seeder history database se read kar rahe hain.
         */
        $stmt = $this->pdo->query(
            'SELECT seeder FROM seeders ORDER BY id ASC'
        );

        return $stmt->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /**
     * Pending seeders run karna.
     *
     * @return array Successfully executed seeders.
     */
    public function run(): array
    {
        /*
         * Tracking table ensure.
         */
        $this->ensureTrackingTable();

        /*
         * Seeder files load.
         */
        $files = $this->getSeederFiles();

        /*
         * Existing seeders load.
         */
        $executed = $this->getExecutedSeeders();

        /*
         * Fast lookup map.
         */
        $executedMap = array_flip($executed);

        /*
         * Successful seeders track karne ke liye array.
         */
        $ran = [];

        /*
         * Har seeder file process karna.
         */
        foreach ($files as $file) {

            /*
             * File ka sirf name.
             */
            $seederName = basename($file);

            /*
             * Already executed seeder skip.
             */
            if (isset($executedMap[$seederName])) {
                continue;
            }

            /*
             * Seeder SQL read.
             */
            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException(
                    "Seeder file could not be read: {$seederName}"
                );
            }

            /*
             * Seeder execute karne se pehle transaction.
             */
            $this->pdo->beginTransaction();

            try {

                /*
                 * Seeder SQL execute.
                 */
                $this->pdo->exec($sql);

                /*
                 * Successful seeder ko tracking table mein save.
                 */
                $stmt = $this->pdo->prepare(
                    'INSERT INTO seeders (seeder)
                     VALUES (?)'
                );

                /*
                 * Prepared statement ke through seeder name insert.
                 */
                $stmt->execute([
                    $seederName
                ]);

                /*
                 * Changes commit.
                 */
                $this->pdo->commit();

                /*
                 * Successful list update.
                 */
                $ran[] = $seederName;

            } catch (Throwable $exception) {

                /*
                 * Error ki situation mein rollback.
                 */
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                /*
                 * Original exception preserve kar rahe hain.
                 */
                throw new RuntimeException(
                    "Seeder failed: {$seederName}",
                    0,
                    $exception
                );
            }
        }

        return $ran;
    }
}