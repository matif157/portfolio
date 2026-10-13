<?php
// =============================================
// FILE: error_center.php
// PURPOSE: Developer-like AI Error Reader + Fixer
// =============================================

require_once 'log_in.php';
//require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied');
}

// -------------------------------------------------
// Helper: Read file content safely
// -------------------------------------------------
function read_file_lines($filepath) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return null;
    }
    return file($filepath);
}

// -------------------------------------------------
// Helper: Get code snippet around error line
// -------------------------------------------------
function get_code_snippet($filepath, $line_no, $context = 5) {
    $lines = read_file_lines($filepath);
    if (!$lines) return null;

    $start = max(0, $line_no - $context - 1);
    $end   = min(count($lines), $line_no + $context);

    $snippet = [];
    for ($i = $start; $i < $end; $i++) {
        $snippet[] = [
            'number' => $i + 1,
            'code'   => rtrim($lines[$i]),
            'is_error' => ($i + 1 == $line_no)
        ];
    }
    return $snippet;
}

// -------------------------------------------------
// Smart Analyzer (Developer Style)
// -------------------------------------------------
function deep_analyze($line) {
    $info = [
        'raw'         => trim($line),
        'type'        => 'Unknown',
        'why'         => 'Error ki exact wajah identify nahi ho saki.',
        'file'        => null,
        'line_no'     => null,
        'suggestions' => [],
        'severity'    => 'medium',
        'can_auto'    => false
    ];

    if (preg_match('/in ([^\s]+) on line (\d+)/i', $line, $m)) {
        $info['file']    = str_replace('\\', '/', $m[1]);
        $info['line_no'] = (int)$m[2];
    }

    // ===== Pattern: Undefined array key =====
    if (stripos($line, 'Undefined array key') !== false || stripos($line, 'Undefined index') !== false) {
        $info['type'] = 'Undefined Array Key';
        $info['severity'] = 'low';
        $info['why'] = 'Aap kisi array se aisi key access kar rahe ho jo exist nahi karti. PHP 8+ mein ye warning/error deta hai.';
        $info['suggestions'] = [
            [
                'title' => 'Best Fix (Recommended)',
                'desc'  => 'Null coalescing operator use karo',
                'code'  => '$value = $array[\'key\'] ?? \'\';'
            ],
            [
                'title' => 'Alternative',
                'desc'  => 'isset() check karo',
                'code'  => 'if (isset($array[\'key\'])) { $value = $array[\'key\']; }'
            ]
        ];
    }

    // ===== Pattern: Undefined variable =====
    elseif (stripos($line, 'Undefined variable') !== false) {
        $info['type'] = 'Undefined Variable';
        $info['severity'] = 'medium';
        $info['why'] = 'Variable use se pehle define nahi hua, ya spelling galat hai, ya scope ke bahar hai.';
        $info['suggestions'] = [
            [
                'title' => 'Initialize Variable',
                'desc'  => 'Variable ko pehle empty value do',
                'code'  => '$variable = \'\'; // ya null'
            ],
            [
                'title' => 'Check Spelling',
                'desc'  => 'Variable name ki spelling check karo'
            ]
        ];
    }

    // ===== Pattern: Undefined function =====
    elseif (stripos($line, 'Call to undefined function') !== false) {
        $info['type'] = 'Undefined Function';
        $info['severity'] = 'high';
        $info['why'] = 'Function call ho rahi hai lekin us function ki definition load nahi hui (file include nahi hui ya function naam galat hai).';

        if (stripos($line, 'log_activity') !== false) {
            $info['suggestions'] = [
                [
                    'title' => 'Include functions.php',
                    'desc'  => 'log_in.php ya current file ke top pe ye line add karo',
                    'code'  => "require_once 'functions.php';"
                ]
            ];
            $info['can_auto'] = true;
        } else {
            $info['suggestions'] = [
                [
                    'title' => 'Check Function Name',
                    'desc'  => 'Function ka spelling aur file include check karo'
                ]
            ];
        }
    }

    // ===== Pattern: Unknown column =====
    elseif (stripos($line, 'Unknown column') !== false) {
        $info['type'] = 'Missing Database Column';
        $info['severity'] = 'high';
        preg_match("/Unknown column '(.*?)'/", $line, $m);
        $col = $m[1] ?? 'unknown';
        $info['why'] = "Database table mein `$col` column maujood nahi hai, lekin query us column ko use kar rahi hai.";
        $info['suggestions'] = [
            [
                'title' => 'Add Column (SQL)',
                'desc'  => 'phpMyAdmin mein ye query run karo',
                'code'  => "ALTER TABLE table_name ADD COLUMN `$col` VARCHAR(255) NULL;"
            ],
            [
                'title' => 'Remove from Query',
                'desc'  => 'Agar column zaroori nahi to SELECT/INSERT se hata do'
            ]
        ];
    }

    // ===== Pattern: Table not found =====
    elseif (stripos($line, "doesn't exist") !== false || stripos($line, 'Base table or view not found') !== false) {
        $info['type'] = 'Missing Table';
        $info['severity'] = 'critical';
        $info['why'] = 'Jo table query mein use ho rahi hai wo database mein exist nahi karti.';
        $info['suggestions'] = [
            [
                'title' => 'Create Table',
                'desc'  => 'Table create karne ka SQL chahiye to mujhe batao'
            ]
        ];
    }

    // ===== Pattern: Syntax Error =====
    elseif (stripos($line, 'syntax error') !== false) {
        $info['type'] = 'PHP Syntax Error';
        $info['severity'] = 'critical';
        $info['why'] = 'PHP code ki grammar galat hai (missing semicolon, bracket, quote etc.).';
        $info['suggestions'] = [
            [
                'title' => 'Check the Line',
                'desc'  => 'Error wali line aur uske just upar wali line carefully dekho'
            ]
        ];
    }

    return $info;
}

// -------------------------------------------------
// Load & Analyze Errors
// -------------------------------------------------
$log_paths = [
    'C:/xampp/apache/logs/error.log',
    'C:/xampp/php/logs/php_error_log',
    ini_get('error_log')
];

$raw = [];
foreach ($log_paths as $path) {
    if ($path && file_exists($path)) {
        $raw = array_slice(file($path), -150);
        break;
    }
}
$raw = array_reverse($raw);

$errors = [];
foreach ($raw as $line) {
    $analyzed = deep_analyze($line);
    if ($analyzed && $analyzed['raw'] !== '') {
        // Code snippet load karo agar file mil jaye
        if ($analyzed['file'] && $analyzed['line_no']) {
            $local_file = $analyzed['file'];
            // Try relative path too
            if (!file_exists($local_file)) {
                $local_file = basename($analyzed['file']);
            }
            $analyzed['snippet'] = get_code_snippet($local_file, $analyzed['line_no']);
        }
        $errors[] = $analyzed;
    }
}
$errors = array_slice($errors, 0, 25);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Error Center</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins', sans-serif; }
        html, body {
            height: 100%;
            overflow-y: auto !important;   /* SCROLLBAR FIX */
        }
        body {
            background: radial-gradient(circle at top left, rgba(37,99,235,.2), transparent 40%),
                        radial-gradient(circle at bottom right, rgba(139,92,246,.2), transparent 40%),
                        #020617;
            color: #e2e8f0;
            min-height: 100vh;
        }
        .main-wrapper {
            margin-left: 320px;
            padding: 30px;
            min-height: 100vh;
            padding-bottom: 100px;
        }
        .header-box {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .error-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 6px solid #ef4444;
        }
        .error-card.low { border-left-color: #22c55e; }
        .error-card.medium { border-left-color: #f59e0b; }
        .error-card.high { border-left-color: #f97316; }
        .error-card.critical { border-left-color: #ef4444; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-critical { background: rgba(239,68,68,0.2); color: #f87171; }
        .badge-high { background: rgba(249,115,22,0.2); color: #fb923c; }
        .badge-medium { background: rgba(245,158,11,0.2); color: #fbbf24; }
        .badge-low { background: rgba(34,197,94,0.2); color: #4ade80; }
        pre, .code-block {
            background: #0f172a;
            color: #e2e8f0;
            padding: 14px;
            border-radius: 10px;
            font-size: 13px;
            overflow-x: auto;
            margin: 12px 0;
            line-height: 1.5;
        }
        .code-line { display: flex; }
        .code-line.error-line { background: rgba(239,68,68,0.15); }
        .line-number {
            width: 40px;
            color: #64748b;
            text-align: right;
            padding-right: 12px;
            user-select: none;
        }
        .suggestion-box {
            background: rgba(56,189,248,0.08);
            border: 1px solid rgba(56,189,248,0.2);
            border-radius: 12px;
            padding: 15px;
            margin-top: 12px;
        }
        .btn {
            padding: 9px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            margin-right: 8px;
            margin-top: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; }
        .btn-success { background: linear-gradient(135deg, #16a34a, #22c55e); color: white; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: #94a3b8; }
    </style>
</head>
<body>

<div class="main-wrapper">

    <div class="header-box">
        <div>
            <h1 style="color:white; font-size:26px; margin:0;">🛠️ Developer Error Center</h1>
            <p style="color:#94a3b8; margin:6px 0 0;">Reads code • Explains why • Gives exact fix steps</p>
        </div>
        <a href="error_center.php" class="btn btn-primary">Refresh Logs</a>
    </div>

    <?php if (empty($errors)): ?>
        <div style="text-align:center; padding:60px; background:rgba(34,197,94,0.1); border-radius:16px; border:1px solid rgba(34,197,94,0.3);">
            <h2 style="color:#4ade80;">✅ No Recent Errors Found</h2>
            <p style="color:#94a3b8;">System clean hai.</p>
        </div>
    <?php else: ?>

        <?php foreach ($errors as $index => $err): ?>
            <div class="error-card <?= $err['severity'] ?>">
                
                <!-- Header -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div>
                        <span class="badge badge-<?= $err['severity'] ?>"><?= strtoupper($err['severity']) ?></span>
                        <strong style="color:white; margin-left:10px; font-size:17px;"><?= htmlspecialchars($err['type']) ?></strong>
                    </div>
                    <span style="color:#64748b;">#<?= $index + 1 ?></span>
                </div>

                <!-- File + Line -->
                <?php if ($err['file']): ?>
                    <p style="color:#94a3b8; font-size:13px; margin-bottom:10px;">
                        📁 <strong><?= htmlspecialchars(basename($err['file'])) ?></strong>
                        <?php if ($err['line_no']): ?>
                            → Line <span style="color:#f87171; font-weight:600;"><?= $err['line_no'] ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <!-- Raw Error -->
                <div class="code-block"><?= htmlspecialchars($err['raw']) ?></div>

                <!-- Why -->
                <div style="margin:15px 0;">
                    <strong style="color:#38bdf8;">❓ Why is this happening?</strong>
                    <p style="color:#cbd5e1; margin-top:6px;"><?= htmlspecialchars($err['why']) ?></p>
                </div>

                <!-- Code Snippet -->
                <?php if (!empty($err['snippet'])): ?>
                    <div style="margin:15px 0;">
                        <strong style="color:#a78bfa;">📄 Code around the error:</strong>
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

                <!-- Suggestions -->
                <div style="margin-top:18px;">
                    <strong style="color:#34d399;">💡 Fix Suggestions:</strong>
                    <?php foreach ($err['suggestions'] as $sugg): ?>
                        <div class="suggestion-box">
                            <strong><?= htmlspecialchars($sugg['title']) ?></strong>
                            <p style="color:#94a3b8; margin:5px 0;"><?= htmlspecialchars($sugg['desc']) ?></p>
                            <?php if (!empty($sugg['code'])): ?>
                                <div class="code-block"><?= htmlspecialchars($sugg['code']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Actions -->
                <div style="margin-top:15px;">
                    <?php if ($err['can_auto']): ?>
                        <button class="btn btn-success" onclick="alert('Auto-apply feature next update mein fully enable hoga. Abhi suggestion manually apply karein.')">
                            ✨ I can do this for you
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-secondary">Mark as Resolved</button>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>