<?php
/**
 * GRAVITYH CRM CONTROL CENTER
 * Single-file deployment, configuration, database, diagnostics and AI-assisted
 * repair workspace for the existing SPS/GravityH plain-PHP/MySQL CRM.
 *
 * IMPORTANT:
 * - Put this file in the CRM ROOT, next to index.php/config.php/cron.php.
 * - First run is intentionally conservative: inspect -> backup -> test -> apply.
 * - It never sends passwords, password hashes, DB passwords, API keys or session
 *   secrets to remote AI.
 * - AI suggestions are advisory. Automatic source rewriting is disabled by design.
 * - Database changes are executed only after explicit confirmation and a
 *   database backup attempt.
 * - Remove or lock this file after deployment.
 *
 * Designed for PHP 7.4+ and XAMPP, no Composer dependency.
 */

declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('memory_limit', '768M');
error_reporting(E_ALL);

session_name('gravityh_control_center');
session_start();

const CC_VERSION = '2.0.0';
const CC_DIR = 'gravityh-control-center';
const CC_STATE_FILE = 'gravityh-control-state.json';
const CC_REPORT_FILE = 'gravityh-control-report.json';
const CC_BACKUP_DIR = 'gravityh-control-backups';
const CC_MAX_PHP_FILES = 5000;
const CC_MAX_SQL_FILE_BYTES = 50 * 1024 * 1024;
const CC_MAX_AI_SOURCE_FILES = 12;
const CC_MAX_AI_SOURCE_BYTES_PER_FILE = 24000;

$ROOT = __DIR__;

$defaults = [
    'app' => [
        'name' => 'SPS Professional Services CRM',
        'base_url' => '',
        'timezone' => 'America/New_York',
        'currency' => 'USD',
        'environment' => 'development',
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => '',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'ai' => [
        'enabled' => false,
        'provider' => 'openai',
        'endpoint' => 'https://api.openai.com/v1/responses',
        'model' => '',
        'api_key' => '',
        'timeout' => 60,
    ],
    'security' => [
        'setup_password_hash' => '',
        'locked' => false,
        'allow_ai_source' => false,
        'allow_sql_apply' => false,
        'allow_file_changes' => false,
    ],
    'paths' => [
        'config_file' => '',
        'log_file' => '',
        'php_binary' => '',
    ],
    'scan' => [
        'exclude' => ['vendor','node_modules','.git','uploads','cache',CC_BACKUP_DIR],
    ],
];

function cc_h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function cc_json($v): string {
    return json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
}

function cc_recursive_files(string $root, array $exclude): array {
    $result = [];
    if (!is_dir($root)) return $result;
    $rootReal = realpath($root);
    if ($rootReal === false) return $result;

    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rootReal,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $info) {
            if (!$info->isFile()) continue;
            $path = $info->getPathname();
            $rel = ltrim(str_replace($rootReal, '', $path), DIRECTORY_SEPARATOR . '/');
            $parts = preg_split('#[\\\\/]#', $rel);
            $skip = false;

            foreach ($parts as $part) {
                if (in_array($part, $exclude, true)) {
                    $skip = true;
                    break;
                }
            }

            if (!$skip) $result[] = $path;
            if (count($result) >= CC_MAX_PHP_FILES * 2) break;
        }
    } catch (Throwable $e) {
        // Keep partial results; the report will say the scan was incomplete.
    }

    return $result;
}

function cc_relative(string $root, string $file): string {
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
    $file = str_replace('\\', '/', realpath($file) ?: $file);
    return ltrim(str_replace($root, '', $file), '/');
}

function cc_merge(array $base, array $override): array {
    foreach ($override as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = cc_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

function cc_load_state(string $root, array $defaults): array {
    $path = $root . DIRECTORY_SEPARATOR . CC_STATE_FILE;
    if (!is_file($path)) return $defaults;

    $raw = @file_get_contents($path);
    if ($raw === false) return $defaults;
    $data = json_decode($raw, true);
    if (!is_array($data)) return $defaults;

    return cc_merge($defaults, $data);
}

function cc_save_state(string $root, array $state): bool {
    $path = $root . DIRECTORY_SEPARATOR . CC_STATE_FILE;
    $safe = $state;

    // Never persist a plaintext setup password.
    unset($safe['security']['setup_password']);

    $json = json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    $ok = @file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
    if ($ok) @chmod($path, 0600);
    return $ok;
}

function cc_mask_secret(string $value): string {
    if ($value === '') return '';
    return str_repeat('•', min(12, max(8, strlen($value))));
}

function cc_csrf(): string {
    if (empty($_SESSION['cc_csrf'])) $_SESSION['cc_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['cc_csrf'];
}

function cc_csrf_ok(): bool {
    return isset($_POST['csrf'], $_SESSION['cc_csrf'])
        && hash_equals((string)$_SESSION['cc_csrf'], (string)$_POST['csrf']);
}

function cc_disabled_functions(): array {
    return array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions')));
}

function cc_command_available(): bool {
    $disabled = cc_disabled_functions();
    return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
}

function cc_find_php_binary(array $cfg): ?string {
    $candidates = [];
    if (!empty($cfg['paths']['php_binary'])) $candidates[] = $cfg['paths']['php_binary'];
    if (defined('PHP_BINARY') && PHP_BINARY !== '') $candidates[] = PHP_BINARY;
    $candidates[] = 'php';

    if (DIRECTORY_SEPARATOR === '\\') {
        $candidates[] = 'C:\\xampp\\php\\php.exe';
        $candidates[] = 'C:\\php\\php.exe';
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        if ($candidate === '' || isset($seen[$candidate])) continue;
        $seen[$candidate] = true;
        if ($candidate === 'php' || is_file($candidate)) return $candidate;
    }
    return null;
}

function cc_run_command(array $args, int $timeout = 30): array {
    if (!cc_command_available()) {
        return ['ok'=>false,'stdout'=>'','stderr'=>'proc_open() is unavailable or disabled.','exit'=>-1];
    }

    $parts = [];
    foreach ($args as $arg) $parts[] = escapeshellarg((string)$arg);
    $command = implode(' ', $parts);

    $desc = [
        0 => ['pipe','r'],
        1 => ['pipe','w'],
        2 => ['pipe','w'],
    ];

    $proc = @proc_open($command, $desc, $pipes);
    if (!is_resource($proc)) {
        return ['ok'=>false,'stdout'=>'','stderr'=>'Unable to start the command.','exit'=>-1];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $status = ['running'=>false];

    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);

        if (!$status['running']) break;

        if ((microtime(true) - $start) >= $timeout) {
            @proc_terminate($proc, 9);
            $stderr .= "\nCommand timed out after {$timeout} seconds.";
            break;
        }
        usleep(100000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit = proc_close($proc);
    return [
        'ok' => $exit === 0,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
        'exit' => $exit,
    ];
}

function cc_db_connect(array $db): PDO {
    $dsn = 'mysql:host=' . $db['host']
         . ';port=' . $db['port']
         . ';dbname=' . $db['name']
         . ';charset=' . $db['charset'];

    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function cc_db_test(array $db): array {
    if (trim((string)$db['name']) === '') {
        return ['ok'=>false,'skipped'=>true,'message'=>'Database name is not configured.'];
    }

    try {
        $pdo = cc_db_connect($db);
        $version = (string)$pdo->query('SELECT VERSION()')->fetchColumn();
        $tables = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
        )->fetchColumn();

        return [
            'ok'=>true,
            'skipped'=>false,
            'message'=>'Database connection succeeded.',
            'version'=>$version,
            'tables'=>$tables
        ];
    } catch (Throwable $e) {
        return [
            'ok'=>false,
            'skipped'=>false,
            'message'=>$e->getMessage()
        ];
    }
}

function cc_schema_snapshot(PDO $pdo): array {
    $tables = [];
    $stmt = $pdo->query("
        SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION
        FROM information_schema.tables
        WHERE TABLE_SCHEMA = DATABASE()
        ORDER BY TABLE_NAME
    ");

    foreach ($stmt as $row) {
        $table = $row['TABLE_NAME'];
        $colStmt = $pdo->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
            FROM information_schema.columns
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ");
        $colStmt->execute([$table]);

        $columns = [];
        foreach ($colStmt as $c) {
            $columns[] = [
                'name'=>$c['COLUMN_NAME'],
                'type'=>$c['COLUMN_TYPE'],
                'nullable'=>$c['IS_NULLABLE'],
                'key'=>$c['COLUMN_KEY'],
                'default'=>$c['COLUMN_DEFAULT'],
                'extra'=>$c['EXTRA'],
            ];
        }

        $tables[$table] = [
            'engine'=>$row['ENGINE'],
            'rows'=>$row['TABLE_ROWS'],
            'collation'=>$row['TABLE_COLLATION'],
            'columns'=>$columns,
        ];
    }

    return $tables;
}

function cc_schema_compare(PDO $pdo, string $sqlFile): array {
    if (!is_file($sqlFile) || !is_readable($sqlFile)) {
        return ['ok'=>false,'message'=>'SQL file is missing or unreadable.','missing_tables'=>[]];
    }

    $sql = @file_get_contents($sqlFile);
    if ($sql === false) return ['ok'=>false,'message'=>'Unable to read SQL file.','missing_tables'=>[]];

    $tables = [];
    if (preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([A-Za-z0-9_]+)[`"]?/i', $sql, $m)) {
        $tables = array_values(array_unique($m[1]));
    }

    $existing = [];
    $stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE()");
    foreach ($stmt as $row) $existing[$row['TABLE_NAME']] = true;

    $missing = [];
    foreach ($tables as $table) {
        if (!isset($existing[$table])) $missing[] = $table;
    }

    return [
        'ok'=>true,
        'message'=>'Schema comparison completed.',
        'sql_tables'=>count($tables),
        'existing_tables'=>count($existing),
        'missing_tables'=>$missing,
    ];
}

function cc_safe_sql_split(string $sql): array {
    // Conservative splitter: respects quoted strings and comments sufficiently
    // for typical MySQL dump/migration files. Statements inside DELIMITER
    // blocks are deliberately flagged rather than executed silently.
    if (preg_match('/^\s*DELIMITER\s+/im', $sql)) {
        throw new RuntimeException('This SQL contains DELIMITER blocks. Review/execute that file manually or through mysql CLI.');
    }

    $statements = [];
    $buf = '';
    $len = strlen($sql);
    $quote = '';
    $lineComment = false;
    $blockComment = false;

    for ($i=0; $i<$len; $i++) {
        $ch = $sql[$i];
        $next = $i+1 < $len ? $sql[$i+1] : '';

        if ($lineComment) {
            $buf .= $ch;
            if ($ch === "\n") $lineComment = false;
            continue;
        }

        if ($blockComment) {
            $buf .= $ch;
            if ($ch === '*' && $next === '/') {
                $buf .= '/';
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== '') {
            $buf .= $ch;
            if ($ch === '\\' && $i+1 < $len) {
                $buf .= $sql[++$i];
                continue;
            }
            if ($ch === $quote) $quote = '';
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }

        if ($ch === '#' || ($ch === '-' && $next === '-')) {
            if ($ch === '-') {
                $buf .= '--';
                $i++;
            } else {
                $buf .= '#';
            }
            $lineComment = true;
            continue;
        }

        if ($ch === '/' && $next === '*') {
            $buf .= '/*';
            $i++;
            $blockComment = true;
            continue;
        }

        if ($ch === ';') {
            $statement = trim($buf);
            if ($statement !== '') $statements[] = $statement;
            $buf = '';
            continue;
        }

        $buf .= $ch;
    }

    $last = trim($buf);
    if ($last !== '') $statements[] = $last;

    return $statements;
}

function cc_backup_files(string $root, array $files): array {
    $dir = $root . DIRECTORY_SEPARATOR . CC_BACKUP_DIR . DIRECTORY_SEPARATOR . date('Ymd_His');
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create backup directory.');
    }

    $manifest = [];
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file)) continue;

        $rel = cc_relative($root, $file);
        $dest = $dir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        $destDir = dirname($dest);

        if (!is_dir($destDir)) @mkdir($destDir, 0700, true);
        if (@copy($file, $dest)) {
            $manifest[] = [
                'source'=>$rel,
                'backup'=>cc_relative($root, $dest),
                'sha256'=>hash_file('sha256', $file),
            ];
        }
    }

    @file_put_contents(
        $dir . DIRECTORY_SEPARATOR . 'manifest.json',
        cc_json(['created_at'=>date('c'),'files'=>$manifest]) . PHP_EOL,
        LOCK_EX
    );

    return ['dir'=>$dir,'manifest'=>$manifest];
}

function cc_backup_database(PDO $pdo, string $root, array $db): array {
    $dir = $root . DIRECTORY_SEPARATOR . CC_BACKUP_DIR . DIRECTORY_SEPARATOR . date('Ymd_His') . '_database';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create database backup directory.');
    }

    $file = $dir . DIRECTORY_SEPARATOR . 'database_schema.sql';
    $fh = @fopen($file, 'wb');
    if (!$fh) throw new RuntimeException('Could not create database backup file.');

    fwrite($fh, "-- GravityH Control Center database backup\n");
    fwrite($fh, "-- Database: " . $db['name'] . "\n");
    fwrite($fh, "-- Created: " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

    foreach ($tables as $t) {
        $table = $t[0];

        $createStmt = $pdo->query("SHOW CREATE TABLE `" . str_replace('`','``',$table) . "`")->fetch(PDO::FETCH_NUM);
        $createSql = $createStmt[1] ?? '';
        if ($createSql !== '') {
            fwrite($fh, "DROP TABLE IF EXISTS `" . str_replace('`','``',$table) . "`;\n");
            fwrite($fh, $createSql . ";\n\n");
        }

        $q = $pdo->query("SELECT * FROM `" . str_replace('`','``',$table) . "`");
        while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
            $cols = [];
            $vals = [];
            foreach ($row as $col => $value) {
                $cols[] = '`' . str_replace('`','``',$col) . '`';
                if ($value === null) {
                    $vals[] = 'NULL';
                } elseif (is_numeric($value) && preg_match('/^-?\d+(?:\.\d+)?$/', (string)$value)) {
                    $vals[] = (string)$value;
                } else {
                    $vals[] = $pdo->quote((string)$value);
                }
            }

            fwrite(
                $fh,
                "INSERT INTO `" . str_replace('`','``',$table) . "` ("
                . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n"
            );
        }
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);

    return ['file'=>$file,'tables'=>count($tables)];
}

function cc_execute_sql_file(PDO $pdo, string $sqlFile, bool $dryRun = true): array {
    if (!is_file($sqlFile)) throw new RuntimeException('SQL file not found.');
    if (filesize($sqlFile) > CC_MAX_SQL_FILE_BYTES) {
        throw new RuntimeException('SQL file exceeds the safe single-file size limit.');
    }

    $sql = @file_get_contents($sqlFile);
    if ($sql === false) throw new RuntimeException('Unable to read SQL file.');

    $statements = cc_safe_sql_split($sql);

    $results = [];
    $executed = 0;
    $skipped = 0;

    foreach ($statements as $index => $statement) {
        $trim = ltrim($statement);
        if ($trim === '') continue;

        // Skip common dump metadata that cannot safely be executed as a
        // transaction boundary, while still recording it.
        if (preg_match('/^(SET\s+SQL_MODE|SET\s+@@|START\s+TRANSACTION|COMMIT|USE\s+)/i', $trim)) {
            $skipped++;
            continue;
        }

        if (preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|INSERT|UPDATE|DELETE|REPLACE|CALL)\b/i', $trim) !== 1) {
            $results[] = ['index'=>$index+1,'status'=>'skipped','message'=>'Unsupported/unknown statement class.'];
            $skipped++;
            continue;
        }

        if ($dryRun) {
            $results[] = ['index'=>$index+1,'status'=>'preview','message'=>substr(preg_replace('/\s+/', ' ', $trim),0,240)];
            continue;
        }

        try {
            $pdo->exec($statement);
            $executed++;
            $results[] = ['index'=>$index+1,'status'=>'ok','message'=>substr(preg_replace('/\s+/', ' ', $trim),0,240)];
        } catch (Throwable $e) {
            $results[] = ['index'=>$index+1,'status'=>'error','message'=>$e->getMessage()];
            throw new RuntimeException('SQL statement #' . ($index+1) . ' failed: ' . $e->getMessage(), 0, $e);
        }
    }

    return [
        'statements'=>count($statements),
        'executed'=>$executed,
        'skipped'=>$skipped,
        'results'=>$results,
    ];
}

function cc_redact(string $text): string {
    $patterns = [
        // Key/value secrets
        '/(["\']?(?:password|passwd|pwd|api[_-]?key|secret|token|authorization|access[_-]?token|refresh[_-]?token)["\']?\s*[:=]\s*["\']?)([^"\',\s}\r\n]+)/i'
            => '$1[REDACTED]',
        // Bearer tokens
        '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i' => '$1[REDACTED]',
        // DSN passwords
        '/(mysql:\/\/[^:\/\s]+:)([^@\/\s]+)(@)/i' => '$1[REDACTED]$3',
        // Common OpenAI / Anthropic / Google-style key-looking prefixes
        '/\b(sk-[A-Za-z0-9_\-]{16,})\b/i' => '[REDACTED_API_KEY]',
        '/\b(AIza[0-9A-Za-z_\-]{20,})\b/' => '[REDACTED_API_KEY]',
        '/\b(sk-ant-[A-Za-z0-9_\-]{16,})\b/i' => '[REDACTED_API_KEY]',
    ];
    foreach ($patterns as $p=>$r) {
        $text = (string)preg_replace($p, $r, $text);
    }

    // Never transmit password-hash fields or lines containing obvious hashes.
    $text = preg_replace(
        '/(^|[\r\n])([^\r\n]*(?:password_hash|password\s*=>|passwd|pwd)[^\r\n]*)/im',
        '$1[REDACTED_SENSITIVE_LINE]',
        $text
    );

    return $text;
}

function cc_secret_scan(string $text): array {
    $hits = [];
    $checks = [
        'possible_api_key' => '/\b(?:sk-[A-Za-z0-9_\-]{16,}|AIza[0-9A-Za-z_\-]{20,}|sk-ant-[A-Za-z0-9_\-]{16,})\b/i',
        'password_assignment' => '/(?:password|passwd|pwd)\s*[:=]\s*[\'"][^\'"]+[\'"]/i',
        'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i',
        'bearer_token' => '/Bearer\s+[A-Za-z0-9._~+\/=-]{16,}/i',
    ];
    foreach ($checks as $name=>$rx) {
        if (preg_match($rx, $text)) $hits[] = $name;
    }
    return array_values(array_unique($hits));
}

function cc_lint_all(string $root, array $exclude, array $cfg): array {
    $php = cc_find_php_binary($cfg);
    if (!$php || !cc_command_available()) {
        return [
            'available'=>false,
            'php_binary'=>$php,
            'checked'=>0,
            'errors'=>[],
            'ok'=>[],
            'message'=>'PHP CLI linting unavailable.'
        ];
    }

    $files = cc_recursive_files($root, $exclude);
    $files = array_values(array_filter($files, static function($f) {
        return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'php';
    }));

    $errors = [];
    $ok = [];
    $checked = 0;

    foreach ($files as $file) {
        if ($checked >= CC_MAX_PHP_FILES) break;
        $checked++;
        $r = cc_run_command([$php, '-l', $file], 25);

        if ($r['ok']) $ok[] = cc_relative($root,$file);
        else {
            $errors[] = [
                'file'=>cc_relative($root,$file),
                'message'=>$r['stderr'] !== '' ? $r['stderr'] : $r['stdout']
            ];
        }
    }

    return [
        'available'=>true,
        'php_binary'=>$php,
        'checked'=>$checked,
        'errors'=>$errors,
        'ok'=>$ok,
        'message'=>'Completed'
    ];
}

function cc_runtime_compare(array $cfg): array {
    $php = cc_find_php_binary($cfg);
    $cli = null;

    if ($php && cc_command_available()) {
        $r = cc_run_command([$php, '-r', 'echo PHP_VERSION;'], 10);
        if ($r['ok']) $cli = trim($r['stdout']);
    }

    return [
        'web_php'=>PHP_VERSION,
        'sapi'=>PHP_SAPI,
        'os'=>PHP_OS_FAMILY,
        'cli_php'=>$cli,
        'php_binary'=>$php,
        'same_major_minor'=>($cli !== null)
            ? (preg_match('/^\d+\.\d+/',PHP_VERSION,$a) && preg_match('/^\d+\.\d+/', $cli,$b) && $a[0] === $b[0])
            : null,
        'memory_limit'=>ini_get('memory_limit'),
        'upload_max_filesize'=>ini_get('upload_max_filesize'),
        'post_max_size'=>ini_get('post_max_size'),
    ];
}

function cc_required_files(string $root): array {
    $paths = [
        'index.php','login.php','dashboard.php','clients.php','businesses.php','contacts.php',
        'leads.php','services.php','projects.php','workflows.php','tasks.php','deadlines.php',
        'documents.php','invoices.php','payments.php','analytics.php','reports.php','activities.php',
        'notifications.php','customizer.php','themes.php','settings.php','automation.php',
        'integrations.php','ai_service.php','assistant.php','exceptions.php','portal_login.php',
        'portal_index.php','global_search.php','cron.php','run_automation.php','config.php','db.php',
        'auth.php','functions.php','header.php','footer.php','core/event_bus.php',
        'core/service_engine.php','core/integrations.php','core/ai_engine.php','core/portal.php',
        'gravity.sql',
    ];

    $rows = [];
    foreach ($paths as $rel) {
        $file = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        $rows[] = [
            'file'=>$rel,
            'exists'=>is_file($file),
            'readable'=>is_readable($file),
        ];
    }
    return $rows;
}

function cc_include_scan(string $root, array $exclude): array {
    $files = cc_recursive_files($root, $exclude);
    $phpFiles = array_values(array_filter($files, static function($f){
        return strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'php';
    }));

    $missing = [];
    $checked = 0;

    foreach ($phpFiles as $file) {
        if ($checked >= CC_MAX_PHP_FILES) break;
        $checked++;

        $text = @file_get_contents($file);
        if ($text === false) continue;

        if (preg_match_all(
            "/\b(?:include|include_once|require|require_once)\s*(?:\(\s*)?[\"']([^\"']+)[\"']\s*\)?/i",
            $text, $m
        )) {
            foreach ($m[1] as $target) {
                if (preg_match('/^(?:https?:|php:\/\/|[A-Z]:\\\\|\/)/i', $target)) continue;

                $candidate1 = dirname($file) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $target);
                $candidate2 = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($target,'/\\'));

                if (!is_file($candidate1) && !is_file($candidate2)) {
                    $missing[] = [
                        'file'=>cc_relative($root,$file),
                        'target'=>$target
                    ];
                }
            }
        }
    }

    return ['checked'=>$checked,'missing'=>$missing];
}

function cc_collect_error_logs(string $root, string $configured = ''): array {
    $paths = [];
    if ($configured !== '') $paths[] = $configured;
    $paths[] = $root . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'error.log';
    $paths[] = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'error.log';
    $paths[] = $root . DIRECTORY_SEPARATOR . 'error.log';

    $phpLog = ini_get('error_log');
    if (is_string($phpLog) && $phpLog !== '') $paths[] = $phpLog;

    if (PHP_SAPI !== 'cli' && function_exists('apache_get_modules')) {
        // Apache access/error log locations vary by XAMPP configuration, so do
        // not invent a path. The report can show the runtime setting instead.
    }

    $seen = [];
    $out = [];
    foreach ($paths as $path) {
        if ($path === '' || isset($seen[$path])) continue;
        $seen[$path] = true;

        if (is_file($path) && is_readable($path)) {
            $size = filesize($path);
            $max = 160000;
            $fh = @fopen($path,'rb');
            if (!$fh) continue;
            if ($size !== false && $size > $max) fseek($fh,-$max,SEEK_END);
            $tail = stream_get_contents($fh);
            fclose($fh);

            $out[] = ['file'=>$path,'tail'=>cc_redact((string)$tail)];
        }
    }

    return $out;
}

function cc_permissions(string $root, array $cfg): array {
    $targets = [
        ['label'=>'CRM root','path'=>$root],
        ['label'=>'State file directory','path'=>$root],
        ['label'=>'Config file','path'=>$cfg['paths']['config_file'] !== '' ? $cfg['paths']['config_file'] : $root . DIRECTORY_SEPARATOR . 'config.php'],
        ['label'=>'Backup directory','path'=>$root . DIRECTORY_SEPARATOR . CC_BACKUP_DIR],
    ];

    $rows = [];
    foreach ($targets as $t) {
        $path = $t['path'];
        $rows[] = [
            'label'=>$t['label'],
            'path'=>$path,
            'exists'=>file_exists($path),
            'readable'=>is_readable($path),
            'writable'=>is_writable($path),
        ];
    }
    return $rows;
}

function cc_health_report(string $root, array $cfg): array {
    $db = cc_db_test($cfg['database']);
    $runtime = cc_runtime_compare($cfg);
    $required = cc_required_files($root);
    $lint = cc_lint_all($root, $cfg['scan']['exclude'], $cfg);
    $includes = cc_include_scan($root, $cfg['scan']['exclude']);
    $logs = cc_collect_error_logs($root, $cfg['paths']['log_file']);
    $permissions = cc_permissions($root,$cfg);

    $missingFiles = array_values(array_filter($required, static function($r){return !$r['exists'];}));

    return [
        'meta'=>[
            'tool'=>'GravityH Control Center',
            'version'=>CC_VERSION,
            'timestamp'=>date('c'),
            'root'=>$root,
        ],
        'runtime'=>$runtime,
        'database'=>$db,
        'required_files'=>$required,
        'missing_required_files'=>$missingFiles,
        'lint'=>[
            'available'=>$lint['available'],
            'checked'=>$lint['checked'],
            'errors'=>$lint['errors'],
            'error_count'=>count($lint['errors']),
            'php_binary'=>$lint['php_binary'],
        ],
        'include_scan'=>$includes,
        'error_logs'=>$logs,
        'permissions'=>$permissions,
        'summary'=>[
            'syntax_errors'=>count($lint['errors']),
            'missing_includes'=>count($includes['missing']),
            'missing_required_files'=>count($missingFiles),
            'db_ok'=>(bool)($db['ok'] ?? false),
            'log_sources'=>count($logs),
        ]
    ];
}

function cc_config_candidates(string $root): array {
    $candidates = [
        $root . DIRECTORY_SEPARATOR . 'config.php',
        $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php',
        $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php',
    ];
    return array_values(array_unique(array_filter($candidates, 'is_file')));
}

function cc_extract_config_preview(string $configFile): array {
    if (!is_file($configFile) || !is_readable($configFile)) return ['ok'=>false,'message'=>'Config file unreadable.'];

    $text = @file_get_contents($configFile);
    if ($text === false) return ['ok'=>false,'message'=>'Unable to read config file.'];

    $lines = preg_split('/\R/', $text);
    $items = [];
    foreach ($lines as $no=>$line) {
        if (preg_match('/\b(define|const)\s*\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*,/i', $line, $m)) {
            $items[] = ['line'=>$no+1,'key'=>$m[2],'value'=>'[HIDDEN]'];
        }
    }

    return [
        'ok'=>true,
        'lines'=>count($lines),
        'defined_constants'=>$items,
        'secret_hits'=>cc_secret_scan($text),
    ];
}

function cc_generate_config(array $cfg, string $existing = ''): string {
    $lines = [];
    $lines[] = '<?php';
    $lines[] = '/** GravityH generated configuration. Secrets are read from environment where available. */';
    $lines[] = 'declare(strict_types=1);';
    $lines[] = '';
    $lines[] = "define('APP_NAME', " . var_export($cfg['app']['name'],true) . ');';
    $lines[] = "define('APP_BASE_URL', " . var_export($cfg['app']['base_url'],true) . ');';
    $lines[] = "define('APP_TIMEZONE', " . var_export($cfg['app']['timezone'],true) . ');';
    $lines[] = "define('APP_CURRENCY', " . var_export($cfg['app']['currency'],true) . ');';
    $lines[] = "define('APP_ENVIRONMENT', " . var_export($cfg['app']['environment'],true) . ');';
    $lines[] = '';
    $lines[] = "define('DB_HOST', " . var_export($cfg['database']['host'],true) . ');';
    $lines[] = "define('DB_PORT', " . var_export($cfg['database']['port'],true) . ');';
    $lines[] = "define('DB_NAME', " . var_export($cfg['database']['name'],true) . ');';
    $lines[] = "define('DB_USER', " . var_export($cfg['database']['user'],true) . ');';
    $lines[] = "define('DB_PASS', getenv('GRAVITYH_DB_PASS') !== false ? getenv('GRAVITYH_DB_PASS') : " . var_export($cfg['database']['pass'],true) . ');';
    $lines[] = "define('DB_CHARSET', " . var_export($cfg['database']['charset'],true) . ');';
    $lines[] = '';
    $lines[] = "define('CRM_ROOT', " . var_export($ROOT,true) . ');';
    $lines[] = "define('CRM_LOG_FILE', " . var_export($cfg['paths']['log_file'],true) . ');';
    $lines[] = "define('CRM_CRON_FILE', " . var_export($ROOT . DIRECTORY_SEPARATOR . 'cron.php',true) . ');';
    $lines[] = '';
    $lines[] = "define('CRM_REMOTE_AI_ENABLED', " . var_export((bool)$cfg['ai']['enabled'],true) . ');';
    $lines[] = "define('CRM_AI_PROVIDER', " . var_export($cfg['ai']['provider'],true) . ');';
    $lines[] = "define('CRM_AI_ENDPOINT', " . var_export($cfg['ai']['endpoint'],true) . ');';
    $lines[] = "define('CRM_AI_MODEL', " . var_export($cfg['ai']['model'],true) . ');';
    $lines[] = "define('CRM_AI_TIMEOUT', " . var_export((int)$cfg['ai']['timeout'],true) . ');';
    $lines[] = "define('CRM_AI_API_KEY', getenv('GRAVITYH_AI_KEY') !== false ? getenv('GRAVITYH_AI_KEY') : " . var_export($cfg['ai']['api_key'],true) . ');';
    $lines[] = '';
    $lines[] = 'date_default_timezone_set(APP_TIMEZONE);';
    $lines[] = '';
    $lines[] = '/* Existing application-specific config must remain preserved if it is not managed above. */';
    $lines[] = '';

    if ($existing !== '') {
        $lines[] = '/* NOTE: The Control Center should merge rather than overwrite unknown application settings. */';
    }

    return implode("\n",$lines) . "\n";
}

function cc_ai_call(array $ai, string $system, string $user): array {
    if (!$ai['enabled']) return ['ok'=>false,'message'=>'Remote AI is disabled.'];

    $endpoint = trim((string)$ai['endpoint']);
    $key = trim((string)$ai['api_key']);
    $provider = strtolower(trim((string)$ai['provider']));
    $model = trim((string)$ai['model']);

    if ($endpoint === '' || $key === '') {
        return ['ok'=>false,'message'=>'AI endpoint/API key is not configured.'];
    }
    if ($model === '') return ['ok'=>false,'message'=>'AI model is not configured.'];

    $headers = ['Content-Type: application/json'];
    $payload = [];

    if ($provider === 'openai') {
        $payload = [
            'model'=>$model,
            'input'=>[
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$user],
            ],
        ];
        $headers[] = 'Authorization: Bearer ' . $key;
    } elseif ($provider === 'anthropic') {
        $payload = [
            'model'=>$model,
            'max_tokens'=>5000,
            'system'=>$system,
            'messages'=>[
                ['role'=>'user','content'=>$user]
            ],
        ];
        $headers[] = 'x-api-key: ' . $key;
        $headers[] = 'anthropic-version: 2023-06-01';
    } elseif ($provider === 'gemini') {
        $payload = [
            'system_instruction'=>[
                'parts'=>[['text'=>$system]]
            ],
            'contents'=>[
                ['role'=>'user','parts'=>[['text'=>$user]]]
            ],
        ];
        // Gemini API-key authentication commonly uses query parameter.
        $separator = strpos($endpoint,'?') === false ? '?' : '&';
        $endpoint .= $separator . 'key=' . rawurlencode($key);
    } elseif ($provider === 'ollama') {
        $payload = [
            'model'=>$model,
            'system'=>$system,
            'messages'=>[
                ['role'=>'user','content'=>$user]
            ],
            'stream'=>false,
        ];
        // Local Ollama commonly does not require authentication.
        if ($key !== '') $headers[] = 'Authorization: Bearer ' . $key;
    } elseif ($provider === 'openai_compatible') {
        $payload = [
            'model'=>$model,
            'messages'=>[
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$user],
            ],
        ];
        $headers[] = 'Authorization: Bearer ' . $key;
    } else {
        return ['ok'=>false,'message'=>'Unsupported AI provider: ' . $provider];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT=>max(5,min(180,(int)$ai['timeout'])),
    ]);

    $body = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) return ['ok'=>false,'message'=>'cURL error: ' . $err];

    $decoded = json_decode($body,true);

    if ($http < 200 || $http >= 300) {
        return [
            'ok'=>false,
            'http_code'=>$http,
            'message'=>'AI HTTP ' . $http . ': ' . cc_redact((string)$body)
        ];
    }

    $text = '';

    if ($provider === 'openai' || $provider === 'openai_compatible') {
        if (isset($decoded['output_text'])) $text = (string)$decoded['output_text'];
        if ($text === '' && isset($decoded['choices'][0]['message']['content'])) {
            $text = (string)$decoded['choices'][0]['message']['content'];
        }
        if ($text === '' && isset($decoded['output']) && is_array($decoded['output'])) {
            foreach ($decoded['output'] as $item) {
                if (!empty($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $part) {
                        if (($part['type'] ?? '') === 'output_text') $text .= (string)($part['text'] ?? '');
                    }
                }
            }
        }
    } elseif ($provider === 'anthropic') {
        foreach (($decoded['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'text') $text .= (string)($part['text'] ?? '');
        }
    } elseif ($provider === 'gemini') {
        $text = (string)($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    } elseif ($provider === 'ollama') {
        $text = (string)($decoded['message']['content'] ?? $decoded['response'] ?? '');
    }

    if ($text === '') $text = cc_redact((string)$body);

    return [
        'ok'=>true,
        'http_code'=>$http,
        'provider'=>$provider,
        'model'=>$model,
        'text'=>cc_redact($text),
    ];
}

function cc_ai_prompt(array $report, string $sourceBundle = ''): array {
    $system = <<<'SYS'
You are a senior PHP 7.4+/8.x, MySQL/MariaDB, XAMPP and CRM systems diagnostician.
You are assisting with an existing SPS/GravityH Professional Services CRM.

Rules:
1. Treat supplied diagnostics/source as evidence. Never invent unseen files, tables, columns,
   routes, functions, or configuration.
2. Separate CONFIRMED findings from HYPOTHESES.
3. Rank findings: BLOCKER, HIGH, MEDIUM, LOW.
4. For every finding provide:
   - exact file/path when known
   - line number/range when evidence allows
   - observed evidence
   - root cause
   - minimal safe remediation
   - validation command/test
   - rollback consideration
5. Never recommend disabling authentication, authorization, CSRF, escaping, prepared statements,
   encryption, audit logs, or other security controls merely to make the app run.
6. Do not output passwords, password hashes, API keys, access tokens, session secrets, or full
   customer records. Say [REDACTED] where needed.
7. Do not claim that a fix was applied. This tool only applies changes after explicit confirmation.
8. For SQL, prefer additive/idempotent migrations. Call out destructive SQL separately.
9. If evidence is insufficient, state exactly what file/schema/log/request is needed.
10. Consider the existing architecture: event bus, automation, integrations, outbound queue,
    cron.php, AI orchestrator, portal, permissions, audit logging and regression tests.
SYS;

    $user = "DIAGNOSTIC REPORT:\n" . cc_json($report);

    if ($sourceBundle !== '') {
        $user .= "\n\nREDACTED SOURCE EXCERPTS:\n" . $sourceBundle;
    }

    return ['system'=>$system,'user'=>cc_redact($user)];
}

function cc_collect_ai_sources(string $root, array $report, bool $enabled): string {
    if (!$enabled) return '';

    $files = [];

    foreach (($report['lint']['errors'] ?? []) as $e) {
        if (count($files) >= CC_MAX_AI_SOURCE_FILES) break;
        $files[] = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $e['file']);
    }

    foreach (($report['include_scan']['missing'] ?? []) as $e) {
        if (count($files) >= CC_MAX_AI_SOURCE_FILES) break;
        $files[] = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $e['file']);
    }

    $files = array_values(array_unique(array_filter($files,'is_file')));

    $bundle = '';
    foreach ($files as $file) {
        $text = @file_get_contents($file);
        if ($text === false) continue;

        $text = substr($text,0,CC_MAX_AI_SOURCE_BYTES_PER_FILE);
        $text = cc_redact($text);

        $bundle .= "\n--- " . cc_relative($root,$file) . " ---\n" . $text . "\n";
    }

    return $bundle;
}

function cc_detect_repo_state(string $root): array {
    $files = cc_recursive_files($root, ['vendor','node_modules','.git',CC_BACKUP_DIR]);
    $counts = [
        'php'=>0,'sql'=>0,'js'=>0,'css'=>0,'md'=>0,'other'=>0
    ];
    $bytes = 0;

    foreach ($files as $file) {
        $bytes += (int)@filesize($file);
        $ext = strtolower(pathinfo($file,PATHINFO_EXTENSION));
        if ($ext === 'php') $counts['php']++;
        elseif ($ext === 'sql') $counts['sql']++;
        elseif ($ext === 'js') $counts['js']++;
        elseif ($ext === 'css') $counts['css']++;
        elseif ($ext === 'md') $counts['md']++;
        else $counts['other']++;
    }

    return ['counts'=>$counts,'bytes'=>$bytes,'files'=>count($files)];
}

function cc_critical_config_values(array $cfg): array {
    return [
        'app_name'=>$cfg['app']['name'],
        'environment'=>$cfg['app']['environment'],
        'timezone'=>$cfg['app']['timezone'],
        'currency'=>$cfg['app']['currency'],
        'db_host'=>$cfg['database']['host'],
        'db_port'=>$cfg['database']['port'],
        'db_name'=>$cfg['database']['name'],
        'db_user'=>$cfg['database']['user'],
        'db_password'=>'[HIDDEN]',
        'ai_provider'=>$cfg['ai']['provider'],
        'ai_model'=>$cfg['ai']['model'],
        'ai_api_key'=>'[HIDDEN]',
    ];
}

$cfg = cc_load_state($ROOT,$defaults);

if (empty($cfg['paths']['config_file'])) {
    $cfg['paths']['config_file'] = $ROOT . DIRECTORY_SEPARATOR . 'config.php';
}

$csrf = cc_csrf();
$flash = [];
$report = $_SESSION['cc_report'] ?? null;
$aiResult = $_SESSION['cc_ai_result'] ?? null;
$schemaResult = $_SESSION['cc_schema_result'] ?? null;
$lastAction = '';

$hasSetupPassword = !empty($cfg['security']['setup_password_hash']);
$authenticated = !empty($_SESSION['cc_authenticated']);

if ($hasSetupPassword && !$authenticated && !isset($_POST['unlock'])) {
    $lockedView = true;
} else {
    $lockedView = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cc_csrf_ok()) {
        $flash[] = ['type'=>'error','text'=>'Security token expired. Reload the page and try again.'];
    } else {
        $action = (string)($_POST['action'] ?? '');
        $lastAction = $action;

        try {
            if ($action === 'set_setup_password') {
                $pwd = (string)($_POST['setup_password'] ?? '');
                if (strlen($pwd) < 12) throw new RuntimeException('Use a setup password of at least 12 characters.');
                $cfg['security']['setup_password_hash'] = password_hash($pwd,PASSWORD_DEFAULT);
                $cfg['security']['locked'] = true;
                cc_save_state($ROOT,$cfg);
                $_SESSION['cc_authenticated'] = true;
                $authenticated = true;
                $flash[] = ['type'=>'success','text'=>'Control Center password created and stored only as a one-way hash.'];
            }

            elseif ($action === 'unlock') {
                $pwd = (string)($_POST['unlock_password'] ?? '');
                if (!password_verify($pwd,(string)$cfg['security']['setup_password_hash'])) {
                    throw new RuntimeException('Invalid Control Center password.');
                }
                $_SESSION['cc_authenticated'] = true;
                $authenticated = true;
                $flash[] = ['type'=>'success','text'=>'Control Center unlocked for this session.'];
            }

            elseif ($hasSetupPassword && !$authenticated) {
                throw new RuntimeException('Unlock the Control Center first.');
            }

            elseif ($action === 'save_settings') {
                $cfg['app']['name'] = trim((string)($_POST['app_name'] ?? $cfg['app']['name']));
                $cfg['app']['base_url'] = trim((string)($_POST['base_url'] ?? $cfg['app']['base_url']));
                $cfg['app']['timezone'] = trim((string)($_POST['timezone'] ?? $cfg['app']['timezone']));
                $cfg['app']['currency'] = trim((string)($_POST['currency'] ?? $cfg['app']['currency']));
                $cfg['app']['environment'] = trim((string)($_POST['environment'] ?? $cfg['app']['environment']));

                $cfg['database']['host'] = trim((string)($_POST['db_host'] ?? $cfg['database']['host']));
                $cfg['database']['port'] = (string)(int)($_POST['db_port'] ?? $cfg['database']['port']);
                $cfg['database']['name'] = trim((string)($_POST['db_name'] ?? $cfg['database']['name']));
                $cfg['database']['user'] = trim((string)($_POST['db_user'] ?? $cfg['database']['user']));

                if (isset($_POST['db_pass']) && $_POST['db_pass'] !== '') {
                    $cfg['database']['pass'] = (string)$_POST['db_pass'];
                }

                $cfg['database']['charset'] = trim((string)($_POST['db_charset'] ?? $cfg['database']['charset']));
                $cfg['paths']['config_file'] = trim((string)($_POST['config_file'] ?? $cfg['paths']['config_file']));
                $cfg['paths']['log_file'] = trim((string)($_POST['log_file'] ?? $cfg['paths']['log_file']));
                $cfg['paths']['php_binary'] = trim((string)($_POST['php_binary'] ?? $cfg['paths']['php_binary']));

                $cfg['ai']['enabled'] = !empty($_POST['ai_enabled']);
                $cfg['ai']['provider'] = trim((string)($_POST['ai_provider'] ?? $cfg['ai']['provider']));
                $cfg['ai']['endpoint'] = trim((string)($_POST['ai_endpoint'] ?? $cfg['ai']['endpoint']));
                $cfg['ai']['model'] = trim((string)($_POST['ai_model'] ?? $cfg['ai']['model']));
                if (isset($_POST['ai_key']) && $_POST['ai_key'] !== '') {
                    $cfg['ai']['api_key'] = (string)$_POST['ai_key'];
                }
                $cfg['ai']['timeout'] = max(5,min(180,(int)($_POST['ai_timeout'] ?? $cfg['ai']['timeout'])));

                $cfg['security']['allow_ai_source'] = !empty($_POST['allow_ai_source']);
                $cfg['security']['allow_sql_apply'] = !empty($_POST['allow_sql_apply']);
                $cfg['security']['allow_file_changes'] = !empty($_POST['allow_file_changes']);

                cc_save_state($ROOT,$cfg);
                $flash[] = ['type'=>'success','text'=>'Settings saved locally. Secrets are not shown in the interface.'];
            }

            elseif ($action === 'test_db') {
                $dbResult = cc_db_test($cfg['database']);
                $flash[] = [
                    'type'=>$dbResult['ok']?'success':'error',
                    'text'=>$dbResult['message']
                ];
                $_SESSION['cc_db_result'] = $dbResult;
            }

            elseif ($action === 'scan') {
                $report = cc_health_report($ROOT,$cfg);
                $_SESSION['cc_report'] = $report;
                $schemaResult = null;
                $_SESSION['cc_schema_result'] = null;
                $flash[] = ['type'=>'success','text'=>'Full CRM health scan completed.'];
            }

            elseif ($action === 'save_report') {
                if (!$report) throw new RuntimeException('Run a health scan first.');
                $path = $ROOT . DIRECTORY_SEPARATOR . CC_REPORT_FILE;
                @file_put_contents($path,cc_json($report) . PHP_EOL,LOCK_EX);
                @chmod($path,0600);
                $flash[] = ['type'=>'success','text'=>'Health report saved to ' . $path];
            }

            elseif ($action === 'backup_files') {
                $files = cc_recursive_files($ROOT,$cfg['scan']['exclude']);
                $result = cc_backup_files($ROOT,$files);
                $flash[] = ['type'=>'success','text'=>'File backup created: ' . $result['dir']];
            }

            elseif ($action === 'backup_db') {
                $pdo = cc_db_connect($cfg['database']);
                $result = cc_backup_database($pdo,$ROOT,$cfg['database']);
                $flash[] = ['type'=>'success','text'=>'Database backup created: ' . $result['file']];
            }

            elseif ($action === 'write_config') {
                if (empty($cfg['security']['allow_file_changes'])) {
                    throw new RuntimeException('File changes are disabled. Enable "Allow controlled file changes" first.');
                }

                $path = $cfg['paths']['config_file'];
                if ($path === '') throw new RuntimeException('Config path is empty.');
                if (strpos(realpath(dirname($path)) ?: dirname($path), realpath($ROOT) ?: $ROOT) !== 0) {
                    throw new RuntimeException('Config file must stay inside the CRM root.');
                }

                $existingText = is_file($path) ? (string)@file_get_contents($path) : '';
                $newText = cc_generate_config($cfg,$existingText);

                if (is_file($path)) {
                    cc_backup_files($ROOT,[$path]);
                }

                if (@file_put_contents($path,$newText,LOCK_EX) === false) {
                    throw new RuntimeException('Could not write the configuration file. Check filesystem permissions.');
                }
                @chmod($path,0640);
                $flash[] = ['type'=>'success','text'=>'Configuration file generated. Unknown existing application-specific settings are not merged automatically; review before replacing a production config.'];
            }

            elseif ($action === 'schema_compare') {
                $sqlFile = trim((string)($_POST['sql_file'] ?? ''));
                if ($sqlFile === '') throw new RuntimeException('Choose an SQL file first.');
                $real = realpath($sqlFile);
                if (!$real || strpos($real,realpath($ROOT) ?: $ROOT) !== 0) {
                    throw new RuntimeException('SQL file must be inside the CRM root.');
                }

                $pdo = cc_db_connect($cfg['database']);
                $schemaResult = cc_schema_compare($pdo,$real);
                $_SESSION['cc_schema_result'] = $schemaResult;
                $flash[] = ['type'=>'success','text'=>'Schema comparison completed.'];
            }

            elseif ($action === 'sql_preview') {
                $sqlFile = trim((string)($_POST['sql_file'] ?? ''));
                if ($sqlFile === '') throw new RuntimeException('Choose an SQL file first.');

                $real = realpath($sqlFile);
                if (!$real || strpos($real,realpath($ROOT) ?: $ROOT) !== 0) {
                    throw new RuntimeException('SQL file must be inside the CRM root.');
                }

                $pdo = cc_db_connect($cfg['database']);
                $preview = cc_execute_sql_file($pdo,$real,true);
                $_SESSION['cc_sql_preview'] = $preview;
                $flash[] = ['type'=>'success','text'=>'SQL dry-run parsed successfully. Nothing was changed.'];
            }

            elseif ($action === 'sql_apply') {
                if (empty($cfg['security']['allow_sql_apply'])) {
                    throw new RuntimeException('SQL apply is disabled. Enable it only when you are ready to change the database.');
                }

                if (empty($_POST['confirm_sql'])) {
                    throw new RuntimeException('Explicit SQL confirmation is required.');
                }

                $sqlFile = trim((string)($_POST['sql_file'] ?? ''));
                if ($sqlFile === '') throw new RuntimeException('Choose an SQL file first.');

                $real = realpath($sqlFile);
                if (!$real || strpos($real,realpath($ROOT) ?: $ROOT) !== 0) {
                    throw new RuntimeException('SQL file must be inside the CRM root.');
                }

                $pdo = cc_db_connect($cfg['database']);

                // Attempt a complete logical backup before schema changes.
                $backup = cc_backup_database($pdo,$ROOT,$cfg['database']);

                try {
                    $pdo->beginTransaction();
                    $result = cc_execute_sql_file($pdo,$real,false);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }

                $_SESSION['cc_sql_preview'] = $result;
                $flash[] = [
                    'type'=>'success',
                    'text'=>'SQL applied successfully. Database backup: ' . $backup['file']
                ];
            }

            elseif ($action === 'ask_ai') {
                if (!$report) throw new RuntimeException('Run a health scan before asking the AI.');
                if (!$cfg['ai']['enabled']) throw new RuntimeException('Remote AI is disabled.');

                $sources = cc_collect_ai_sources($ROOT,$report,(bool)$cfg['security']['allow_ai_source']);
                $prompts = cc_ai_prompt($report,$sources);
                $aiResult = cc_ai_call($cfg['ai'],$prompts['system'],$prompts['user']);
                $_SESSION['cc_ai_result'] = $aiResult;

                if (!$aiResult['ok']) {
                    $flash[] = ['type'=>'error','text'=>$aiResult['message']];
                } else {
                    $flash[] = ['type'=>'success','text'=>'AI diagnosis received. No files were changed.'];
                }
            }

            elseif ($action === 'secret_scan') {
                $files = cc_recursive_files($ROOT,$cfg['scan']['exclude']);
                $findings = [];
                foreach ($files as $file) {
                    if (!is_readable($file)) continue;
                    $text = @file_get_contents($file);
                    if ($text === false) continue;
                    $hits = cc_secret_scan($text);
                    if ($hits) {
                        $findings[] = [
                            'file'=>cc_relative($ROOT,$file),
                            'types'=>$hits
                        ];
                    }
                    if (count($findings) >= 500) break;
                }

                $_SESSION['cc_secret_findings'] = $findings;
                $flash[] = [
                    'type'=>empty($findings)?'success':'warning',
                    'text'=>count($findings) . ' files with possible secret patterns found. Values are never displayed.'
                ];
            }

            elseif ($action === 'lock') {
                unset($_SESSION['cc_authenticated']);
                $authenticated = false;
                $flash[] = ['type'=>'success','text'=>'Control Center locked for this session.'];
            }

        } catch (Throwable $e) {
            $flash[] = ['type'=>'error','text'=>$e->getMessage()];
        }
    }
}

$dbResult = $_SESSION['cc_db_result'] ?? null;
$sqlPreview = $_SESSION['cc_sql_preview'] ?? null;
$secretFindings = $_SESSION['cc_secret_findings'] ?? [];

$sqlFiles = [];
foreach (cc_recursive_files($ROOT,['vendor','node_modules','.git',CC_BACKUP_DIR]) as $f) {
    if (strtolower(pathinfo($f,PATHINFO_EXTENSION)) === 'sql') $sqlFiles[] = $f;
}
sort($sqlFiles);

$health = $report;
$issues = [];

if ($health) {
    foreach ($health['lint']['errors'] as $e) {
        $issues[] = ['severity'=>'BLOCKER','type'=>'PHP syntax','file'=>$e['file'],'evidence'=>$e['message']];
    }
    foreach ($health['include_scan']['missing'] as $e) {
        $issues[] = ['severity'=>'HIGH','type'=>'Missing include/require','file'=>$e['file'],'evidence'=>'Missing target: '.$e['target']];
    }
    foreach ($health['missing_required_files'] as $e) {
        $issues[] = ['severity'=>'HIGH','type'=>'Expected CRM file missing','file'=>$e['file'],'evidence'=>$e['file']];
    }
    if (!($health['database']['ok'] ?? false) && !($health['database']['skipped'] ?? false)) {
        $issues[] = ['severity'=>'BLOCKER','type'=>'Database','file'=>'config.php / db.php','evidence'=>$health['database']['message'] ?? 'Database connection failed'];
    }
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GravityH Control Center</title>
<style>
:root{--bg:#0a0e17;--panel:#111827;--panel2:#0f172a;--border:#243047;--text:#eef2ff;--muted:#93a4bf;--primary:#6ea8fe;--good:#3ddc97;--warn:#f5c451;--bad:#ff6b7a;--shadow:0 18px 60px rgba(0,0,0,.28)}
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(135deg,#080c14,#0b1020 45%,#0a1019);color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
.wrap{max-width:1500px;margin:auto;padding:28px}
.top{display:flex;gap:18px;align-items:flex-start;justify-content:space-between;margin-bottom:20px}
.brand h1{margin:0;font-size:28px}.brand p{margin:7px 0 0;color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(12,1fr);gap:18px}
.card{grid-column:span 12;background:linear-gradient(180deg,rgba(17,24,39,.98),rgba(13,19,31,.98));border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow);padding:20px}
.half{grid-column:span 6}
.third{grid-column:span 4}
.card h2{margin:0 0 10px;font-size:18px}.card h3{margin:18px 0 9px;font-size:14px}
.muted{color:var(--muted)}.tiny{font-size:12px;color:var(--muted)}
.form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
label{display:block;color:#cbd5e1;font-size:12px;margin:0 0 6px}
input,select,textarea{width:100%;background:#0b1220;color:var(--text);border:1px solid #2a3853;border-radius:11px;padding:11px 12px;outline:none}
input:focus,select:focus,textarea:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(110,168,254,.12)}
.actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:14px}
button{border:0;border-radius:11px;padding:10px 14px;color:#08111d;background:#e7eefc;font-weight:700;cursor:pointer}
button.secondary{background:#1d2b43;color:#dbe7ff}button.good{background:#2fc98a;color:#03160f}button.warn{background:#e5b84b;color:#1b1507}button.bad{background:#ef6d7c;color:#23070c}
.badge{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800}
.ok{color:var(--good)}.badtxt{color:var(--bad)}.warntxt{color:var(--warn)}
table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px 8px;border-bottom:1px solid #202b40;text-align:left;vertical-align:top}th{color:#9fb0ca}
pre,.mono{white-space:pre-wrap;word-break:break-word;background:#080d16;border:1px solid #202b40;border-radius:12px;padding:12px;font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;max-height:560px;overflow:auto}
.flash{padding:12px 14px;border-radius:12px;margin-bottom:9px;border:1px solid #29405f;background:#10213b}.flash.success{border-color:#225f4c;background:#0c241c}.flash.error{border-color:#71303b;background:#260f15}.flash.warning{border-color:#705722;background:#211a0b}
.statgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px}.stat{background:#0b1220;border:1px solid #202b40;border-radius:14px;padding:14px}.stat b{display:block;font-size:23px;margin-top:5px}
.banner{padding:14px;border-radius:14px;border:1px solid #6c5320;background:#251d0a;margin-bottom:16px}
code{background:#0a1220;border:1px solid #1e2b42;border-radius:6px;padding:2px 5px}
hr{border:0;border-top:1px solid #202b40;margin:18px 0}
@media(max-width:950px){.half,.third{grid-column:span 12}.form{grid-template-columns:1fr}.statgrid{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="wrap">
<div class="top">
  <div class="brand">
    <h1>GravityH Control Center ⚙️</h1>
    <p>Deployment · Database · Diagnostics · AI-assisted troubleshooting · Controlled changes</p>
  </div>
  <div>
    <?php if ($hasSetupPassword && $authenticated): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
        <input type="hidden" name="action" value="lock">
        <button class="secondary">Lock</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php foreach ($flash as $f): ?>
  <div class="flash <?=cc_h($f['type'])?>"><?=cc_h($f['text'])?></div>
<?php endforeach; ?>

<?php if (!$hasSetupPassword): ?>
<section class="card">
  <h2>First-run protection</h2>
  <p class="muted">Create a Control Center password. It is stored as a one-way password hash and is never sent to the AI.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <input type="hidden" name="action" value="set_setup_password">
    <div style="max-width:520px">
      <label>New Control Center password</label>
      <input type="password" name="setup_password" minlength="12" autocomplete="new-password" required>
    </div>
    <div class="actions"><button class="good">Create protected Control Center</button></div>
  </form>
</section>
<?php elseif (!$authenticated): ?>
<section class="card" style="max-width:520px;margin:60px auto">
  <h2>Control Center locked 🔒</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <input type="hidden" name="action" value="unlock">
    <label>Control Center password</label>
    <input type="password" name="unlock_password" autocomplete="current-password" autofocus required>
    <div class="actions"><button class="good">Unlock</button></div>
  </form>
</section>
<?php else: ?>

<?php if (($cfg['app']['environment'] ?? '') === 'production'): ?>
<div class="banner"><b>Production mode:</b> SQL application and controlled file changes remain separate permissions. Back up before changing either.</div>
<?php endif; ?>

<section class="card">
  <h2>1. CRM Configuration</h2>
  <p class="muted">Set deployment values here. Existing CRM-specific settings should be preserved manually or merged with the existing config rather than blindly replaced.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <input type="hidden" name="action" value="save_settings">
    <div class="form">
      <div><label>Application name</label><input name="app_name" value="<?=cc_h($cfg['app']['name'])?>"></div>
      <div><label>Base URL</label><input name="base_url" value="<?=cc_h($cfg['app']['base_url'])?>" placeholder="http://localhost/gravityh"></div>
      <div><label>Timezone</label><input name="timezone" value="<?=cc_h($cfg['app']['timezone'])?>"></div>
      <div><label>Currency</label><input name="currency" value="<?=cc_h($cfg['app']['currency'])?>"></div>
      <div><label>Environment</label>
        <select name="environment">
          <?php foreach (['development','staging','production'] as $env): ?>
            <option value="<?=cc_h($env)?>" <?=$cfg['app']['environment']===$env?'selected':''?>><?=cc_h($env)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Config file</label><input name="config_file" value="<?=cc_h($cfg['paths']['config_file'])?>"></div>
      <div><label>Log file (optional)</label><input name="log_file" value="<?=cc_h($cfg['paths']['log_file'])?>"></div>
      <div><label>PHP CLI binary (optional)</label><input name="php_binary" value="<?=cc_h($cfg['paths']['php_binary'])?>" placeholder="C:\xampp\php\php.exe"></div>

      <div><label>DB host</label><input name="db_host" value="<?=cc_h($cfg['database']['host'])?>"></div>
      <div><label>DB port</label><input name="db_port" value="<?=cc_h($cfg['database']['port'])?>"></div>
      <div><label>DB name</label><input name="db_name" value="<?=cc_h($cfg['database']['name'])?>"></div>
      <div><label>DB user</label><input name="db_user" value="<?=cc_h($cfg['database']['user'])?>"></div>
      <div><label>DB password</label><input type="password" name="db_pass" placeholder="Leave blank to keep saved value"></div>
      <div><label>DB charset</label><input name="db_charset" value="<?=cc_h($cfg['database']['charset'])?>"></div>
    </div>

    <hr>
    <h3>Remote AI</h3>
    <div class="form">
      <div><label>AI provider</label>
        <select name="ai_provider">
          <?php foreach (['openai','anthropic','gemini','ollama','openai_compatible'] as $p): ?>
            <option value="<?=cc_h($p)?>" <?=$cfg['ai']['provider']===$p?'selected':''?>><?=cc_h($p)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>AI model</label><input name="ai_model" value="<?=cc_h($cfg['ai']['model'])?>" placeholder="Provider-specific model"></div>
      <div><label>AI endpoint</label><input name="ai_endpoint" value="<?=cc_h($cfg['ai']['endpoint'])?>"></div>
      <div><label>AI API key</label><input type="password" name="ai_key" placeholder="Leave blank to keep saved key"></div>
      <div><label>AI timeout</label><input name="ai_timeout" value="<?=cc_h($cfg['ai']['timeout'])?>"></div>
    </div>

    <div class="actions">
      <label style="display:flex;align-items:center;gap:8px"><input style="width:auto" type="checkbox" name="ai_enabled" value="1" <?=$cfg['ai']['enabled']?'checked':''?>> Enable remote AI</label>
      <label style="display:flex;align-items:center;gap:8px"><input style="width:auto" type="checkbox" name="allow_ai_source" value="1" <?=$cfg['security']['allow_ai_source']?'checked':''?>> Allow redacted source excerpts to AI</label>
      <label style="display:flex;align-items:center;gap:8px"><input style="width:auto" type="checkbox" name="allow_sql_apply" value="1" <?=$cfg['security']['allow_sql_apply']?'checked':''?>> Allow SQL apply</label>
      <label style="display:flex;align-items:center;gap:8px"><input style="width:auto" type="checkbox" name="allow_file_changes" value="1" <?=$cfg['security']['allow_file_changes']?'checked':''?>> Allow controlled file changes</label>
    </div>
    <div class="actions"><button class="good">Save configuration</button></div>
  </form>
</section>

<section class="grid">
<section class="card half">
  <h2>2. Database</h2>
  <p class="muted">Yes, this control center can connect to the selected database, inspect it, back it up, compare it to SQL, and execute approved SQL files.</p>
  <?php if ($dbResult): ?>
    <div class="flash <?=$dbResult['ok']?'success':'error'?>"><?=cc_h($dbResult['message'])?></div>
  <?php endif; ?>
  <div class="actions">
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="test_db"><button>Test database</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="backup_db"><button class="warn">Backup database</button></form>
  </div>
  <p class="tiny">A database backup is attempted automatically before SQL Apply.</p>
</section>

<section class="card half">
  <h2>3. File / Configuration Actions</h2>
  <div class="actions">
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="backup_files"><button class="warn">Backup CRM files</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="scan"><button class="good">Run full health scan</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="secret_scan"><button>Scan for exposed secrets</button></form>
    <form method="post"><input type="hidden" name="csrf" value="<?=cc_h($csrf)?>"><input type="hidden" name="action" value="save_report"><button>Save health report</button></form>
  </div>
  <p class="tiny">The secret scanner reports file locations/types only; it does not display secret values.</p>
</section>
</section>

<section class="card">
  <h2>4. SQL / Migration Center</h2>
  <p class="muted">This changes the <b>database</b>, not the SQL source file. Choose an SQL file inside the CRM, preview it, compare its CREATE TABLE targets with the current database, then apply it only after explicit confirmation.</p>

  <form method="post">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <div class="form">
      <div>
        <label>SQL file</label>
        <select name="sql_file" required>
          <option value="">Select SQL file...</option>
          <?php foreach ($sqlFiles as $sql): ?>
            <option value="<?=cc_h($sql)?>"><?=cc_h(cc_relative($ROOT,$sql))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Operation</label>
        <div class="actions">
          <button name="action" value="schema_compare">Compare schema</button>
          <button name="action" value="sql_preview" class="secondary">SQL dry-run</button>
        </div>
      </div>
    </div>
  </form>

  <hr>
  <form method="post" onsubmit="return confirm('This will change the DATABASE. A backup will be attempted first. Continue?');">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <input type="hidden" name="action" value="sql_apply">
    <div class="form">
      <div>
        <label>SQL file to apply</label>
        <select name="sql_file" required>
          <option value="">Select SQL file...</option>
          <?php foreach ($sqlFiles as $sql): ?>
            <option value="<?=cc_h($sql)?>"><?=cc_h(cc_relative($ROOT,$sql))?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Explicit confirmation</label>
        <label style="display:flex;align-items:center;gap:8px;margin-top:12px"><input style="width:auto" type="checkbox" name="confirm_sql" value="1" required> I understand this changes the selected database.</label>
      </div>
    </div>
    <div class="actions"><button class="bad">Apply SQL to database</button></div>
  </form>

  <?php if ($schemaResult): ?>
    <h3>Schema comparison</h3>
    <pre><?=cc_h(cc_json($schemaResult))?></pre>
  <?php endif; ?>

  <?php if ($sqlPreview): ?>
    <h3>Last SQL operation</h3>
    <pre><?=cc_h(cc_json($sqlPreview))?></pre>
  <?php endif; ?>

  <p class="tiny">Safety note: SQL containing MySQL DELIMITER blocks is deliberately rejected by this browser executor rather than guessed. Such files should be handled through the MySQL client after review.</p>
</section>

<?php if ($health): ?>
<section class="card">
  <h2>5. Health Summary</h2>
  <div class="statgrid">
    <div class="stat"><span class="muted">PHP syntax errors</span><b class="<?=($health['summary']['syntax_errors']??0)?'badtxt':'ok'?>"><?=cc_h($health['summary']['syntax_errors']??0)?></b></div>
    <div class="stat"><span class="muted">Missing includes</span><b class="<?=($health['summary']['missing_includes']??0)?'badtxt':'ok'?>"><?=cc_h($health['summary']['missing_includes']??0)?></b></div>
    <div class="stat"><span class="muted">Missing expected files</span><b class="<?=($health['summary']['missing_required_files']??0)?'warntxt':'ok'?>"><?=cc_h($health['summary']['missing_required_files']??0)?></b></div>
    <div class="stat"><span class="muted">Database</span><b class="<?=($health['summary']['db_ok']??false)?'ok':'badtxt'?>"><?=($health['summary']['db_ok']??false)?'OK':'FAIL'?></b></div>
    <div class="stat"><span class="muted">Log sources</span><b><?=cc_h($health['summary']['log_sources']??0)?></b></div>
  </div>
</section>

<section class="card half">
  <h2>6. Runtime</h2>
  <table>
    <?php foreach ($health['runtime'] as $k=>$v): ?>
      <tr><th><?=cc_h($k)?></th><td><?=cc_h(is_scalar($v)?$v:cc_json($v))?></td></tr>
    <?php endforeach; ?>
  </table>
</section>

<section class="card half">
  <h2>7. Database Result</h2>
  <pre><?=cc_h(cc_json($health['database']))?></pre>
</section>

<section class="card">
  <h2>8. Detected Issues</h2>
  <?php if (!$issues): ?>
    <div class="flash success">No blocker/high issues were detected by built-in checks. This does not prove the CRM is bug-free.</div>
  <?php else: ?>
  <table>
    <tr><th>Severity</th><th>Type</th><th>File</th><th>Evidence</th></tr>
    <?php foreach (array_slice($issues,0,1000) as $i): ?>
      <tr>
        <td class="<?=in_array($i['severity'],['BLOCKER','HIGH'],true)?'badtxt':'warntxt'?>"><?=cc_h($i['severity'])?></td>
        <td><?=cc_h($i['type'])?></td>
        <td class="mono"><?=cc_h($i['file'])?></td>
        <td class="mono"><?=cc_h($i['evidence'])?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</section>

<section class="card half">
  <h2>9. PHP Lint</h2>
  <p class="muted">Uses PHP's CLI syntax checker, which checks parsing without executing each script.</p>
  <div class="stat"><span class="muted">Checked</span><b><?=cc_h($health['lint']['checked'])?></b></div>
  <?php if (!empty($health['lint']['errors'])): ?>
    <table><tr><th>File</th><th>Error</th></tr>
    <?php foreach (array_slice($health['lint']['errors'],0,250) as $e): ?>
      <tr><td class="mono"><?=cc_h($e['file'])?></td><td class="mono"><?=cc_h($e['message'])?></td></tr>
    <?php endforeach; ?></table>
  <?php else: ?>
    <div class="flash success">No PHP syntax errors were reported for the checked files.</div>
  <?php endif; ?>
</section>

<section class="card half">
  <h2>10. Missing Includes</h2>
  <?php if (!$health['include_scan']['missing']): ?>
    <div class="flash success">No missing literal include/require targets were detected.</div>
  <?php else: ?>
    <table><tr><th>Source</th><th>Target</th></tr>
    <?php foreach (array_slice($health['include_scan']['missing'],0,250) as $e): ?>
      <tr><td><?=cc_h($e['file'])?></td><td class="badtxt"><?=cc_h($e['target'])?></td></tr>
    <?php endforeach; ?></table>
  <?php endif; ?>
</section>

<section class="card">
  <h2>11. Expected GravityH Structure</h2>
  <table><tr><th>Path</th><th>Status</th></tr>
  <?php foreach ($health['required_files'] as $r): ?>
    <tr><td class="mono"><?=cc_h($r['file'])?></td><td class="<?=$r['exists']?'ok':'badtxt'?>"><?=$r['exists']?'Present':'Missing'?></td></tr>
  <?php endforeach; ?>
  </table>
</section>

<section class="card">
  <h2>12. Error Logs</h2>
  <?php if (!$health['error_logs']): ?>
    <div class="flash warning">No configured/discovered log file was found. Check PHP's <code>error_log</code> setting and your XAMPP/Apache log location.</div>
  <?php else: ?>
    <?php foreach ($health['error_logs'] as $log): ?>
      <h3><?=cc_h($log['file'])?></h3>
      <pre><?=cc_h($log['tail'])?></pre>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="card">
  <h2>13. Permissions</h2>
  <table><tr><th>Target</th><th>Readable</th><th>Writable</th><th>Path</th></tr>
  <?php foreach ($health['permissions'] as $p): ?>
    <tr>
      <td><?=cc_h($p['label'])?></td>
      <td class="<?=$p['readable']?'ok':'badtxt'?>"><?=$p['readable']?'Yes':'No'?></td>
      <td class="<?=$p['writable']?'ok':'warntxt'?>"><?=$p['writable']?'Yes':'No'?></td>
      <td class="mono"><?=cc_h($p['path'])?></td>
    </tr>
  <?php endforeach; ?>
  </table>
</section>

<section class="card">
  <h2>14. AI Diagnosis</h2>
  <p class="muted">AI receives the diagnostic report. Source excerpts are optional and always pass through redaction first. No automatic changes are made.</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=cc_h($csrf)?>">
    <input type="hidden" name="action" value="ask_ai">
    <div class="actions"><button class="good">🤖 Ask configured AI to diagnose</button></div>
  </form>
  <?php if ($aiResult): ?>
    <h3>AI result</h3>
    <pre><?=cc_h($aiResult['text'] ?? $aiResult['message'] ?? '')?></pre>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2>15. Security / Secret Scan</h2>
  <?php if (!$secretFindings): ?>
    <div class="flash success">No obvious secret patterns were detected by the lightweight scanner.</div>
  <?php else: ?>
    <table><tr><th>File</th><th>Possible secret types</th></tr>
    <?php foreach ($secretFindings as $f): ?>
      <tr><td class="mono"><?=cc_h($f['file'])?></td><td><?=cc_h(implode(', ',$f['types']))?></td></tr>
    <?php endforeach; ?>
    </table>
  <?php endif; ?>
</section>

<section class="card">
  <h2>16. What this tool is designed to guarantee</h2>
  <p class="muted">
    It can independently lint many PHP files, inspect the live MySQL database, compare selected SQL
    table definitions at a coarse level, preview SQL, back up files/database, execute explicitly
    approved SQL, read configured error logs, and ask multiple AI provider styles for evidence-based
    diagnosis. It does not guarantee that every runtime/business-logic bug is discoverable by static
    checks alone.
  </p>
  <p class="tiny">
    Recommended production loop: <b>Backup → Scan → Diagnose → Review → Apply one controlled change
    → Re-scan → Run regression tests → Verify runtime</b>.
  </p>
</section>

<?php endif; ?>
</div>
</body>
</html>
