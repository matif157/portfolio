<?php
/**
 * =====================================================
 * FILE: db-autopilot.php
 * PURPOSE: Database Schema Evolution & Query Auto-Pilot UI
 * =====================================================
 */

require_once 'log_in.php';
//require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied – Database tools are developer-only');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Auto-Pilot – SPS OS</title>
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
            background: rgba(15, 23, 42, 0.88);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
        }
        .mono { font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
        .neon-border { border: 1px solid rgba(56, 189, 248, 0.35); box-shadow: 0 0 20px rgba(56, 189, 248, 0.08); }
        textarea:focus, input:focus, select:focus { outline: none; border-color: #38bdf8; }
    </style>
</head>
<body>
<div class="main-wrapper">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">DB Auto-Pilot</h1>
        <p class="text-slate-400 text-sm mt-1">Schema Evolution • Migration Generator • Query Optimizer</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

        <!-- 1. Natural Language Migration -->
        <div class="glass p-5 xl:col-span-2 neon-border">
            <h2 class="text-lg font-medium text-white mb-3">
                <i class="fa-solid fa-wand-magic-sparkles text-cyan-400 mr-2"></i>
                Natural Language → Migration
            </h2>
            <p class="text-sm text-slate-400 mb-3">Describe the change in plain English. Example: “Add phone column to clients table” or “Create invoices table with client_id foreign key”</p>

            <textarea id="nlPrompt" rows="4"
                class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white text-sm mono"
                placeholder="e.g. Add a nullable email_verified_at timestamp column to the users table"></textarea>

            <div class="flex flex-wrap gap-2 mt-3">
                <button onclick="generateMigration()" class="px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-sm font-medium">
                    Generate Migration
                </button>
                <button onclick="saveMigration()" id="btnSaveMig" disabled
                    class="px-4 py-2 rounded-lg bg-emerald-600 text-sm font-medium opacity-50 cursor-not-allowed">
                    Save to /database/migrations
                </button>
            </div>

            <pre id="migrationOutput" class="mt-4 p-4 rounded-xl bg-black/40 border border-white/5 mono text-cyan-100 min-h-[140px] overflow-auto">Generated SQL will appear here...</pre>
        </div>

        <!-- 2. Quick Actions -->
        <div class="glass p-5 space-y-4">
            <h2 class="text-lg font-medium text-white">
                <i class="fa-solid fa-gauge-high text-violet-400 mr-2"></i>
                Performance
            </h2>

            <button onclick="analyzeSlowQueries()" class="w-full px-4 py-3 rounded-xl bg-violet-600/80 hover:bg-violet-600 text-sm font-medium text-left">
                <i class="fa-solid fa-stopwatch mr-2"></i> Analyze Slow Queries
            </button>

            <button onclick="suggestIndexes()" class="w-full px-4 py-3 rounded-xl bg-fuchsia-600/80 hover:bg-fuchsia-600 text-sm font-medium text-left">
                <i class="fa-solid fa-bolt mr-2"></i> Auto-Optimize Indexes
            </button>

            <button onclick="loadSchema()" class="w-full px-4 py-3 rounded-xl bg-slate-600 hover:bg-slate-500 text-sm font-medium text-left">
                <i class="fa-solid fa-database mr-2"></i> Refresh Schema Explorer
            </button>

            <div id="perfStatus" class="text-xs text-slate-400 mt-2"></div>
        </div>
    </div>

    <!-- 3. Schema Explorer + Recommendations -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">

        <div class="glass overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10 text-sm font-medium text-slate-300">
                Interactive Schema Explorer
            </div>
            <div id="schemaExplorer" class="p-4 max-h-[420px] overflow-y-auto mono text-sm text-slate-300">
                Click “Refresh Schema Explorer” to load tables...
            </div>
        </div>

        <div class="glass overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10 text-sm font-medium text-slate-300">
                Index Recommendations & Slow Query Insights
            </div>
            <div id="optimizePanel" class="p-4 max-h-[420px] overflow-y-auto text-sm text-slate-300">
                Run Analyze / Auto-Optimize to see recommendations.
            </div>
        </div>
    </div>
</div>

<script>
let lastMigrationSQL = '';

async function generateMigration() {
    const prompt = document.getElementById('nlPrompt').value.trim();
    if (!prompt) return alert('Please enter a description');

    document.getElementById('migrationOutput').innerText = 'Generating...';

    const res = await fetch('db-autopilot-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'generate_migration', prompt })
    });
    const data = await res.json();

    if (!data.success) {
        document.getElementById('migrationOutput').innerText = data.error || 'Failed';
        document.getElementById('btnSaveMig').disabled = true;
        return;
    }

    lastMigrationSQL = data.sql;
    document.getElementById('migrationOutput').innerText = data.sql;
    document.getElementById('btnSaveMig').disabled = false;
    document.getElementById('btnSaveMig').classList.remove('opacity-50', 'cursor-not-allowed');
}

async function saveMigration() {
    if (!lastMigrationSQL) return;

    const res = await fetch('db-autopilot-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'save_migration', sql: lastMigrationSQL })
    });
    const data = await res.json();
    alert(data.success ? 'Saved: ' + data.file : (data.error || 'Save failed'));
}

async function loadSchema() {
    document.getElementById('schemaExplorer').innerText = 'Loading schema...';
    const res = await fetch('db-autopilot-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'schema' })
    });
    const data = await res.json();

    if (!data.success) {
        document.getElementById('schemaExplorer').innerText = data.error || 'Failed';
        return;
    }

    let html = '';
    (data.tables || []).forEach(t => {
        html += `<div class="mb-4">
            <div class="text-cyan-400 font-medium mb-1">📦 ${escapeHtml(t.name)}</div>
            <div class="pl-3 text-slate-400">`;
        (t.columns || []).forEach(c => {
            html += `<div>• ${escapeHtml(c.Field)} <span class="text-slate-500">${escapeHtml(c.Type)}</span> ${c.Key === 'PRI' ? '<span class="text-amber-400">PK</span>' : ''}</div>`;
        });
        html += `</div></div>`;
    });
    document.getElementById('schemaExplorer').innerHTML = html || 'No tables found';
}

async function analyzeSlowQueries() {
    document.getElementById('perfStatus').innerText = 'Analyzing...';
    const res = await fetch('db-autopilot-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'slow_queries' })
    });
    const data = await res.json();
    document.getElementById('perfStatus').innerText = data.success ? 'Analysis complete' : (data.error || 'Failed');

    if (data.success) {
        document.getElementById('optimizePanel').innerHTML = formatList(data.items || [], 'Slow / notable queries');
    }
}

async function suggestIndexes() {
    document.getElementById('perfStatus').innerText = 'Computing index suggestions...';
    const res = await fetch('db-autopilot-api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'suggest_indexes' })
    });
    const data = await res.json();
    document.getElementById('perfStatus').innerText = data.success ? 'Suggestions ready' : (data.error || 'Failed');

    if (data.success) {
        document.getElementById('optimizePanel').innerHTML = formatList(data.items || [], 'Index recommendations');
    }
}

function formatList(items, title) {
    if (!items.length) return `<p class="text-emerald-400">No issues found.</p>`;
    let html = `<div class="text-slate-400 mb-2">${title}</div>`;
    items.forEach(it => {
        html += `<div class="mb-3 p-3 rounded-lg bg-white/5 border border-white/5">
            <div class="text-white text-sm">${escapeHtml(it.title || '')}</div>
            <div class="mono text-xs text-cyan-200 mt-1">${escapeHtml(it.sql || it.detail || '')}</div>
            ${it.reason ? `<div class="text-xs text-slate-500 mt-1">${escapeHtml(it.reason)}</div>` : ''}
        </div>`;
    });
    return html;
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t || '';
    return d.innerHTML;
}

// Auto-load schema on page open
loadSchema();
</script>
</body>
</html>