<?php
/**
 * =====================================================
 * FILE: RectorRunner.php
 * PURPOSE: Safely execute Rector against whitelisted modules
 * =====================================================
 */

class RectorRunner
{
    private string $projectRoot;
    private string $rectorBin;
    private array $allowedModules;

    public function __construct(string $projectRoot, array $allowedModules)
    {
        $this->projectRoot     = rtrim($projectRoot, '/\\');
        $this->rectorBin       = $this->projectRoot . '/vendor/bin/rector';
        $this->allowedModules  = $allowedModules;
    }

    /**
     * Validate module key and resolve safe path
     * Why: Prevent directory traversal attacks
     */
    public function resolveModulePath(string $moduleKey): ?string
    {
        if (!isset($this->allowedModules[$moduleKey])) {
            return null;
        }

        // Map module keys to actual folders/files (customize as needed)
        $map = [
            'clients'  => $this->projectRoot,
            'invoices' => $this->projectRoot,
            'users'    => $this->projectRoot,
            'projects' => $this->projectRoot,
            'sandbox'  => $this->projectRoot,
        ];

        $path = $map[$moduleKey] ?? null;
        if (!$path || !is_dir($path)) {
            return null;
        }

        // Realpath check to block traversal
        $real = realpath($path);
        if ($real === false || strpos($real, $this->projectRoot) !== 0) {
            return null;
        }

        return $real;
    }

    /**
     * Build a temporary rector.php config for modern PHP upgrades
     */
    public function buildTempConfig(string $pathsCsv): string
    {
        $configContent = <<<PHP
<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([{$pathsCsv}])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    ->withSkip([
        // Add any files you never want touched
    ]);
PHP;

        $tmpFile = sys_get_temp_dir() . '/rector_crm_' . uniqid() . '.php';
        file_put_contents($tmpFile, $configContent);
        return $tmpFile;
    }

    /**
     * Run Rector
     * @param string $modulePath
     * @param bool $dryRun  true = preview only
     */
    public function run(string $modulePath, bool $dryRun = true): array
    {
        if (!file_exists($this->rectorBin)) {
            return [
                'success' => false,
                'error'   => 'Rector not installed. Run: composer require rector/rector --dev'
            ];
        }

        // Target only .php files in root for safety in this demo
        // You can narrow to specific files later
        $pathsCsv = "'" . str_replace('\\', '/', $modulePath) . "'";
        $configFile = $this->buildTempConfig($pathsCsv);

        $cmd = escapeshellarg(PHP_BINARY) . ' ' .
               escapeshellarg($this->rectorBin) . ' process ' .
               escapeshellarg($modulePath) .
               ' --config=' . escapeshellarg($configFile) .
               ' --output-format=json';

        if ($dryRun) {
            $cmd .= ' --dry-run';
        }

        $cmd .= ' 2>&1';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->projectRoot);

        if (!is_resource($process)) {
            @unlink($configFile);
            return ['success' => false, 'error' => 'Failed to start Rector process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        @unlink($configFile);

        return [
            'success'   => true,
            'exit_code' => $exitCode,
            'stdout'    => $stdout,
            'stderr'    => $stderr,
            'dry_run'   => $dryRun
        ];
    }
}