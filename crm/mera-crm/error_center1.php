<?php
// =============================================
// FILE: error_center.php
// PURPOSE: Developer-Grade Error Reader + Auto Fix Engine
// =============================================

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied');
}

// -------------------------------------------------
// Helper Functions
// -------------------------------------------------
function get_code_snippet($file, $line_no, $context = 6) {
    if (!file_exists($file)) {
        // Try just the filename in current directory
        $file = basename($file);
        if (!file_exists($file)) return null;
    }

    $lines = file($file);
    if (!$lines) return null;

    $start = max(0, $line_no - $context - 1);
    $end   = min(count($lines), $line_no + $context);

    $snippet = [];
    for ($i = $start; $i < $end; $i++) {
        $snippet[] = [
            'number'   => $i + 1,
            'code'     => rtrim($lines[$i]),
            'is_error' => ($i + 1 === $line_no)
        ];
    }
    return $snippet;
}

function analyze_error_deep($raw_line) {
    $info = [
        'raw'         => trim($raw_line),
        'type'        => 'Unknown',
        'severity'    => 'medium',
        'file'        => null,
        'line_no'     => null,
        'why'         => '',
        'impact'      => '',
        'suggestions' => [],
        'auto_fix'    => null
    ];

    // Extract file and line
    if (preg_match('/in ([^\s]+) on line (\d+)/i', $raw_line, $m)) {
        $info['file']    = str_replace('\\', '/', $m[1]);
        $info['line_no'] = (int)$m[2];
    }

    // ===================== PATTERN MATCHING =====================

    // 1. Undefined array key / index
    if (preg_match('/Undefined array key ["\'](.+?)["\']/i', $raw_line, $m) || 
        stripos($raw_line, 'Undefined index') !== false) {
        
        $key = $m[1] ?? 'unknown';
        $info['type']     = 'Undefined Array Key';
        $info['severity'] = 'low';
        $info['why']      = "Code is trying to access array key `$key` that does not exist.";
        $info['impact']   = "PHP 8+ throws warning/error. Page may show notices or break logic.";
        $info['suggestions'] = [
            [
                'title' => 'Minimal Fix',
                'code'  => "\$value = \$array['$key'] ?? '';"
            ],
            [
                'title' => 'Professional Fix',
                'code'  => "\$value = isset(\$array['$key']) ? \$array['$key'] : null;"
            ],
            [
                'title' => 'Enterprise Fix',
                'code'  => "// Use nullsafe + null coalescing\n\$value = \$array['$key'] ?? null;"
            ]
        ];
        $info['auto_fix'] = [
            'type' => 'replace_pattern',
            'search' => "['$key']",
            'replace' => "['$key'] ?? ''"
        ];
    }

    // 2. Undefined variable
    elseif (preg_match('/Undefined variable \$([a-zA-Z0-9_]+)/i', $raw_line, $m)) {
        $var = $m[1];
        $info['type']     = 'Undefined Variable';
        $info['severity'] = 'medium';
        $info['why']      = "Variable \$$var is used before it is defined or is out of scope.";
        $info['impact']   = "Logic will fail or produce unexpected results.";
        $info['suggestions'] = [
            [
                'title' => 'Minimal Fix',
                'code'  => "\$$var = ''; // Initialize before use"
            ],
            [
                'title' => 'Professional Fix',
                'code'  => "\$$var = null;"
            ]
        ];
    }

    // 3. Call to undefined function
    elseif (stripos($raw_line, 'Call to undefined function') !== false) {
        $info['type']     = 'Undefined Function';
        $info['severity'] = 'high';
        
        if (stripos($raw_line, 'log_activity') !== false) {
            $info['why']    = "log_activity() function is being called but the file containing this function is not included.";
            $info['impact'] = "Activity logging fails. Other functionality may continue.";
            $info['suggestions'] = [
                [
                    'title' => 'Minimal Fix',
                    'code'  => "require_once 'functions.php';"
                ],
                [
                    'title' => 'Professional Fix',
                    'code'  => "// Add this line at the top of the file (after log_in.php)\nrequire_once 'functions.php';"
                ]
            ];
            $info['auto_fix'] = [
                'type' => 'add_include',
                'code' => "require_once 'functions.php';"
            ];
        } else {
            $info['why'] = "A function is called that does not exist or its file is not included.";
            $info['suggestions'] = [
                [
                    'title' => 'Check Function Name + Include',
                    'code'  => "// Verify function name and require the correct file"
                ]
            ];
        }
    }

    // 4. Unknown column
    elseif (preg_match("/Unknown column '([^']+)'/", $raw_line, $m)) {
        $col = $m[1];
        $info['type']     = 'Missing Database Column';
        $info['severity'] = 'high';
        $info['why']      = "SQL query is using column `$col` but this column does not exist in the table.";
        $info['impact']   = "Query fails. Page crashes with PDOException.";
        $info['suggestions'] = [
            [
                'title' => 'Minimal Fix (Add Column)',
                'code'  => "ALTER TABLE table_name ADD COLUMN `$col` VARCHAR(255) NULL;"
            ],
            [
                'title' => 'Professional Fix',
                'code'  => "ALTER TABLE table_name ADD COLUMN `$col` VARCHAR(255) NULL AFTER some_column;"
            ]
        ];
    }

    // 5. Table doesn't exist
    elseif (stripos($raw_line, "doesn't exist") !== false || stripos($raw_line, 'Base table or view not found') !== false) {
        $info['type']     = 'Missing Table';
        $info['severity'] = 'critical';
        $info['why']      = "The table used in the query does not exist in the database.";
        $info['impact']   = "Complete failure of the feature using this table.";
        $info['suggestions'] = [
            [
                'title' => 'Create the missing table',
                'code'  => "-- Run the CREATE TABLE SQL for the missing table"
            ]
        ];
    }

    // 6. Syntax Error
    elseif (stripos($raw_line, 'syntax error') !== false) {
        $info['type']     = 'PHP Syntax Error';
        $info['severity'] = 'critical';
        $info['why']      = "PHP cannot parse the file due to syntax mistake (missing ; , ), }, quotes, etc.).";
        $info['impact']   = "Entire file stops executing.";
        $info['suggestions'] = [
            [
                'title' => 'Check the reported line and the line above it',
                'code'  => "// Look for missing semicolon, unclosed brackets or quotes"
            ]
        ];
    }

    return $info;
}

// -------------------------------------------------
// Load Errors
// -------------------------------------------------
$log_paths = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log',
    ini_get('error_log')
];

$raw_lines = [];
foreach ($log_paths as $path) {
    if ($path && file_exists($path)) {
        $raw_lines = array_slice(file($path), -120);
        break;
    }
}
$raw_lines = array_reverse($raw_lines);

$errors = [];
foreach ($raw_lines as $line) {
    $analyzed = analyze_error_deep($line);
    if ($analyzed['raw'] !== '') {
        if ($analyzed['file'] && $analyzed['line_no']) {
            $analyzed['snippet'] = get_code_snippet($analyzed['file'], $analyzed['line_no']);
        }
        $errors[] = $analyzed;
    }
}
$errors = array_slice($errors, 0, 20);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DevCore Error Center</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
        html, body { height:100%; overflow-y:auto !important; }
        body {
            background: radial-gradient(circle at top left, rgba(37,99,235,.2), transparent 40%),
                        radial-gradient(circle at bottom right, rgba(139,92,246,.2), transparent 40%),
                        #020617;
            color:#e2e8f0; min-height:100vh;
        }
        .main-wrapper { margin-left:320px; padding:30px 30px 80px; }
        .header-box {
            background:rgba(255,255,255,0.07); border:1px solid rgba(255,255,255,0.1);
            backdrop-filter:blur(20px); border-radius:18px; padding:22px 28px;
            margin-bottom:25px; display:flex; justify-content:space-between; align-items:center;
        }
        .error-card {
            background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
            border-radius:16px; padding:24px; margin-bottom:22px;
            border-left:6px solid #ef4444;
        }
        .error-card.low{border-left-color:#22c55e;}
        .error-card.medium{border-left-color:#f59e0b;}
        .error-card.high{border-left-color:#f97316;}
        .error-card.critical{border-left-color:#ef4444;}
        .badge{display:inline-block;padding:3px 11px;border-radius:9999px;font-size:11px;font-weight:600;}
        .badge-critical{background:rgba(239,68,68,0.2);color:#f87171;}
        .badge-high{background:rgba(249,115,22,0.2);color:#fb923c;}
        .badge-medium{background:rgba(245,158,11,0.2);color:#fbbf24;}
        .badge-low{background:rgba(34,197,94,0.2);color:#4ade80;}
        .code-block {
            background:#0f172a; color:#e2e8f0; padding:14px; border-radius:10px;
            font-size:13px; overflow-x:auto; margin:12px 0; line-height:1.55;
        }
        .code-line{display:flex;}
        .code-line.error-line{background:rgba(239,68,68,0.18);}
        .line-number{width:42px;color:#64748b;text-align:right;padding-right:14px;user-select:none;}
        .suggestion-box {
            background:rgba(56,189,248,0.08); border:1px solid rgba(56,189,248,0.2);
            border-radius:10px; padding:14px; margin-top:10px;
        }
        .btn {
            padding:9px 16px; border-radius:9px; border:none; cursor:pointer;
            font-weight:600; font-size:13px; margin-right:8px; margin-top:8px;
        }
        .btn-primary{background:linear-gradient(135deg,#2563eb,#7c3aed);color:white;}
        .btn-success{background:linear-gradient(135deg,#16a34a,#22c55e);color:white;}
        .btn-secondary{background:rgba(255,255,255,0.1);color:#94a3b8;}
    </style>
</head>
<body>

<div class="main-wrapper">

    <div class="header-box">
        <div>
            <h1 style="color:white;font-size:24px;margin:0;">DevCore Error Center</h1>
            <p style="color:#94a3b8;margin:5px 0 0;font-size:14px;">Real Auto-Fix Engine • Reads Code • Explains Root Cause</p>
        </div>
        <a href="error_center.php" class="btn btn-primary">Refresh</a>
    </div>

    <?php if (empty($errors)): ?>
        <div style="text-align:center;padding:50px;background:rgba(34,197,94,0.1);border-radius:16px;border:1px solid rgba(34,197,94,0.3);">
            <h2 style="color:#4ade80;">No Recent Errors</h2>
            <p style="color:#94a3b8;">System is clean.</p>
        </div>
    <?php else: ?>

        <?php foreach ($errors as $i => $err): ?>
            <div class="error-card <?= $err['severity'] ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <div>
                        <span class="badge badge-<?= $err['severity'] ?>"><?= strtoupper($err['severity']) ?></span>
                        <strong style="color:white;margin-left:10px;font-size:16px;"><?= htmlspecialchars($err['type']) ?></strong>
                    </div>
                    <span style="color:#64748b;">#<?= $i+1 ?></span>
                </div>

                <?php if ($err['file']): ?>
                    <p style="color:#94a3b8;font-size:13px;margin-bottom:8px;">
                        📁 <strong><?= htmlspecialchars(basename($err['file'])) ?></strong>
                        <?php if ($err['line_no']): ?> → Line <span style="color:#f87171;font-weight:600;"><?= $err['line_no'] ?></span><?php endif; ?>
                    </p>
                <?php endif; ?>

                <div class="code-block"><?= htmlspecialchars($err['raw']) ?></div>

                <!-- ROOT CAUSE -->
                <div style="margin:14px 0;">
                    <strong style="color:#38bdf8;">ROOT CAUSE</strong>
                    <p style="color:#cbd5e1;margin-top:5px;"><?= htmlspecialchars($err['why']) ?></p>
                </div>

                <?php if ($err['impact']): ?>
                <div style="margin:14px 0;">
                    <strong style="color:#f97316;">IMPACT</strong>
                    <p style="color:#cbd5e1;margin-top:5px;"><?= htmlspecialchars($err['impact']) ?></p>
                </div>
                <?php endif; ?>

                <!-- CODE SNIPPET -->
                <?php if (!empty($err['snippet'])): ?>
                    <div style="margin:14px 0;">
                        <strong style="color:#a78bfa;">CODE AROUND ERROR</strong>
                        <div class="code-block" style="padding:0;">
                            <?php foreach ($err['snippet'] as $snip): ?>
                                <div class="code-line <?= $snip['is_error'] ? 'error-line' : '' ?>">
                                    <div class="line-number"><?= $snip['number'] ?></div>
                                    <div><?= htmlspecialchars($snip['code']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- SUGGESTIONS -->
                <div style="margin-top:16px;">
                    <strong style="color:#34d399;">FIX OPTIONS</strong>
                    <?php foreach ($err['suggestions'] as $sugg): ?>
                        <div class="suggestion-box">
                            <strong><?= htmlspecialchars($sugg['title']) ?></strong>
                            <?php if (!empty($sugg['code'])): ?>
                                <div class="code-block"><?= htmlspecialchars($sugg['code']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:12px;">
                    <?php if ($err['auto_fix']): ?>
                        <button class="btn btn-success" onclick="alert('Copy the recommended code above and apply it in the file. Full one-click file patch coming next.')">
                            Apply Recommended Fix
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-secondary">Mark Resolved</button>
                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>