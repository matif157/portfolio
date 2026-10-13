<?php
/**
 * =====================================================
 * FILE: self-healing.php
 * PURPOSE: Self-Healing Codebase Dashboard + Diff Viewer
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied – Developer role required');
}

// Safe list of upgradable modules (whitelist only)
$availableModules = [
    'clients'   => 'Clients Module',
    'invoices'  => 'Invoices Module',
    'users'     => 'Users / Staff Module',
    'projects'  => 'Projects Module',
    'sandbox'   => 'Code Sandbox',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Self-Healing Upgrader – SPS OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Poppins:wght@400;500;600&display=swap');
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #020617;
            color: #e2e8f0;
            min-height: 100vh;
        }
        .main-wrapper { margin-left: 320px; padding: 24px 28px; min-height: 100vh; }
        .glass {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
        }
        .diff-pane {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            line-height: 1.55;
            overflow: auto;
            max-height: 520px;
            white-space: pre;
        }
        .line-del { background: rgba(239, 68, 68, 0.15); color: #fca5a5; }
        .line-add { background: rgba(34, 197, 94, 0.15); color: #86efac; }
        .line-ctx { color: #94a3b8; }
        .line-num {
            display: inline-block;
            width: 40px;
            color: #475569;
            user-select: none;
            text-align: right;
            padding-right: 12px;
        }
    </style>
</head>
<body>
<div class="main-wrapper">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Self-Healing Codebase</h1>
        <p class="text-slate-400 text-sm mt-1">Autonomous PHP upgrades powered by Rector (PHP 8.x modern syntax)</p>
    </div>

    <!-- Control Card -->
    <div class="glass p-5 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-sm text-slate-400 mb-1">Target Module</label>
                <select id="moduleSelect" class="w-full px-4 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-blue-500">
                    <option value="">-- Select Module --</option>
                    <?php foreach ($availableModules as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button onclick="analyzeModule()" id="btnAnalyze"
                    class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-violet-600 text-white font-medium text-sm hover:opacity-90">
                <i class="fa-solid fa-magnifying-glass-chart mr-2"></i> Analyze & Preview Upgrade
            </button>

            <button onclick="applyUpgrade()" id="btnApply" disabled
                    class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-medium text-sm opacity-50 cursor-not-allowed">
                <i class="fa-solid fa-wand-magic-sparkles mr-2"></i> Apply Upgrade
            </button>
        </div>

        <div id="statusBox" class="mt-4 text-sm text-slate-400"></div>
    </div>

    <!-- Diff Viewer -->
    <div class="glass overflow-hidden">
        <div class="px-5 py-3 border-b border-white/10 flex justify-between items-center">
            <span class="text-sm font-medium text-slate-300">Git-Style Diff Viewer</span>
            <span id="diffSummary" class="text-xs text-slate-500">No analysis yet</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 divide-x divide-white/5">
            <!-- Original -->
            <div>
                <div class="px-4 py-2 bg-red-500/10 text-red-300 text-xs font-medium border-b border-white/5">
                    ORIGINAL (Legacy)
                </div>
                <div class="diff-pane p-4" id="diffOriginal">
                    Select a module and run analysis...
                </div>
            </div>

            <!-- Upgraded -->
            <div>
                <div class="px-4 py-2 bg-emerald-500/10 text-emerald-300 text-xs font-medium border-b border-white/5">
                    UPGRADED (Modern PHP)
                </div>
                <div class="diff-pane p-4" id="diffUpgraded">
                    Waiting for Rector output...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let lastDiffData = null;

async function analyzeModule() {
    const module = document.getElementById('moduleSelect').value;
    if (!module) {
        alert('Please select a module');
        return;
    }

    setStatus('Analyzing with Rector (dry-run)... Please wait');
    document.getElementById('btnAnalyze').disabled = true;

    try {
        const res = await fetch('fetch-diff.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ module, mode: 'dry-run' })
        });
        const data = await res.json();

        document.getElementById('btnAnalyze').disabled = false;

        if (!data.success) {
            setStatus('<span class="text-red-400">' + escapeHtml(data.error) + '</span>');
            return;
        }

        lastDiffData = data;
        renderDiff(data);
        setStatus('<span class="text-emerald-400">Analysis complete. Review the diff before applying.</span>');
        document.getElementById('btnApply').disabled = false;
        document.getElementById('btnApply').classList.remove('opacity-50', 'cursor-not-allowed');
    } catch (e) {
        document.getElementById('btnAnalyze').disabled = false;
        setStatus('<span class="text-red-400">Network error</span>');
    }
}

async function applyUpgrade() {
    if (!lastDiffData || !confirm('This will modify files on disk. Continue?')) return;

    const module = document.getElementById('moduleSelect').value;
    setStatus('Applying upgrades...');

    const res = await fetch('fetch-diff.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ module, mode: 'apply' })
    });
    const data = await res.json();

    if (data.success) {
        setStatus('<span class="text-emerald-400">Upgrade applied successfully.</span>');
        document.getElementById('btnApply').disabled = true;
    } else {
        setStatus('<span class="text-red-400">' + escapeHtml(data.error) + '</span>');
    }
}

function renderDiff(data) {
    const summary = data.files_changed + ' file(s) • ' + data.total_changes + ' change(s)';
    document.getElementById('diffSummary').innerText = summary;

    // Simple side-by-side from unified diff lines
    let originalHtml = '';
    let upgradedHtml = '';

    (data.diff_lines || []).forEach((line, i) => {
        const num = i + 1;
        if (line.startsWith('-') && !line.startsWith('---')) {
            originalHtml += `<div class="line-del"><span class="line-num">${num}</span>${escapeHtml(line.substring(1))}</div>`;
        } else if (line.startsWith('+') && !line.startsWith('+++')) {
            upgradedHtml += `<div class="line-add"><span class="line-num">${num}</span>${escapeHtml(line.substring(1))}</div>`;
        } else {
            const txt = line.startsWith(' ') ? line.substring(1) : line;
            originalHtml += `<div class="line-ctx"><span class="line-num">${num}</span>${escapeHtml(txt)}</div>`;
            upgradedHtml += `<div class="line-ctx"><span class="line-num">${num}</span>${escapeHtml(txt)}</div>`;
        }
    });

    document.getElementById('diffOriginal').innerHTML = originalHtml || 'No changes detected';
    document.getElementById('diffUpgraded').innerHTML = upgradedHtml || 'No changes detected';
}

function setStatus(html) {
    document.getElementById('statusBox').innerHTML = html;
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
</body>
</html>