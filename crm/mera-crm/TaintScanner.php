<?php
/**
 * =====================================================
 * FILE: TaintScanner.php
 * PURPOSE: Run Psalm with taint analysis and parse results
 * =====================================================
 */

class TaintScanner
{
    private string $projectRoot;
    private string $psalmBin;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/\\');
        $this->psalmBin    = $this->projectRoot . '/vendor/bin/psalm';
    }

    /**
     * Execute Psalm taint analysis
     * Why: Tracks untrusted input ($_GET, $_POST, etc.) to dangerous sinks
     */
    public function scan(): array
    {
        if (!file_exists($this->psalmBin)) {
            return [
                'success' => false,
                'error'   => 'Psalm not installed. Run: composer require --dev vimeo/psalm'
            ];
        }

        // --taint-analysis enables source-to-sink tracking
        // --output-format=json gives machine-readable report
        $cmd = escapeshellarg(PHP_BINARY) . ' ' .
               escapeshellarg($this->psalmBin) .
               ' --taint-analysis --output-format=json --no-cache 2>&1';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->projectRoot);

        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'Failed to start Psalm process'];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $this->parseReport($stdout, $stderr);
    }

    /**
     * Parse Psalm JSON output into structured vulnerabilities
     */
    private function parseReport(string $stdout, string $stderr): array
    {
        $data = json_decode($stdout, true);

        // Psalm sometimes outputs errors as a list
        if (!is_array($data)) {
            // Fallback: try to extract useful message
            return [
                'success' => false,
                'error'   => 'Unable to parse Psalm output. ' . substr($stderr ?: $stdout, 0, 300)
            ];
        }

        $vulnerabilities = [];
        $stats = ['critical' => 0, 'high' => 0, 'medium' => 0, 'total' => 0];

        // Psalm JSON structure can be a list of issues
        $issues = isset($data[0]) ? $data : ($data['issues'] ?? $data);

        if (!is_array($issues)) {
            $issues = [];
        }

        foreach ($issues as $issue) {
            if (!is_array($issue)) continue;

            $type    = $issue['type'] ?? $issue['message'] ?? 'TaintIssue';
            $message = $issue['message'] ?? '';
            $file    = $issue['file_name'] ?? $issue['file_path'] ?? 'unknown';
            $line    = $issue['line_from'] ?? $issue['line'] ?? 0;
            $severity= strtolower($issue['severity'] ?? 'medium');

            // Map Psalm severity to our risk levels
            $risk = 'medium';
            if (in_array($severity, ['error', 'critical'])) $risk = 'critical';
            elseif ($severity === 'high' || str_contains(strtolower($type), 'taint')) $risk = 'high';
            elseif ($severity === 'low' || $severity === 'info') $risk = 'low';

            // Heuristic: taint-related issues are high/critical
            if (stripos($type, 'Tainted') !== false || stripos($message, 'taint') !== false) {
                if ($risk === 'medium') $risk = 'high';
            }

            $stats[$risk] = ($stats[$risk] ?? 0) + 1;
            $stats['total']++;

            // Build a simple trace if path data exists
            $trace = [];
            if (!empty($issue['taint_trace']) && is_array($issue['taint_trace'])) {
                foreach ($issue['taint_trace'] as $step) {
                    $trace[] = [
                        'label' => $step['label'] ?? ($step['message'] ?? 'Step'),
                        'file'  => $step['file_name'] ?? null,
                        'line'  => $step['line_from'] ?? null
                    ];
                }
            } else {
                // Minimal source → sink
                $trace = [
                    ['label' => 'Untrusted Source (user input)', 'file' => null, 'line' => null],
                    ['label' => $message ?: $type, 'file' => $file, 'line' => $line],
                    ['label' => 'Dangerous Sink', 'file' => $file, 'line' => $line]
                ];
            }

            $vulnerabilities[] = [
                'type'    => $type,
                'message' => $message,
                'file'    => basename($file),
                'full_path' => $file,
                'line'    => $line,
                'risk'    => $risk,
                'sink'    => $issue['selected_code'] ?? '',
                'trace'   => $trace
            ];
        }

        // Sort: critical first
        usort($vulnerabilities, function ($a, $b) {
            $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            return ($order[$a['risk']] ?? 9) <=> ($order[$b['risk']] ?? 9);
        });

        return [
            'success'         => true,
            'vulnerabilities' => $vulnerabilities,
            'stats'           => $stats,
            'raw_count'       => count($vulnerabilities)
        ];
    }
}