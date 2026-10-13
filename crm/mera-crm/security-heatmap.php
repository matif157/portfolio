<?php
/**
 * =====================================================
 * FILE: security-heatmap.php
 * PURPOSE: Live Taint Analysis & Vulnerability Heatmap UI
 * =====================================================
 */

require_once 'log_in.php';
require_once 'sidebar.php';

$allowed = ['Super Admin', 'Admin'];
if (!in_array($currentUser['role_name'] ?? '', $allowed)) {
    exit('Access Denied – Security module is developer-only');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Heatmap – SPS OS</title>
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
        .risk-critical { border-left: 4px solid #ef4444; }
        .risk-high     { border-left: 4px solid #f97316; }
        .risk-medium   { border-left: 4px solid #eab308; }
        .risk-low      { border-left: 4px solid #22c55e; }

        .trace-step {
            position: relative;
            padding-left: 28px;
            margin-bottom: 14px;
        }
        .trace-step::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 8px;
            bottom: -14px;
            width: 2px;
            background: rgba(239, 68, 68, 0.4);
        }
        .trace-step:last-child::before { display: none; }
        .trace-dot {
            position: absolute;
            left: 0;
            top: 6px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ef4444;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
        }
        .trace-dot.source { background: #f97316; box-shadow: 0 0 10px rgba(249, 115, 22, 0.6); }
        .trace-dot.sink   { background: #ef4444; }
        .mono { font-family: 'JetBrains Mono', monospace; font-size: 12.5px; }
    </style>
</head>
<body>
<div class="main-wrapper">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-white">Live Taint Analysis</h1>
            <p class="text-slate-400 text-sm mt-1">Vulnerability Heatmap • Source → Sink Data Flow Tracker</p>
        </div>
        <button onclick="runScan()" id="btnScan"
                class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-orange-600 text-white font-medium text-sm hover:opacity-90">
            <i class="fa-solid fa-shield-virus mr-2"></i> Run Taint Scan
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" id="statsRow">
        <div class="glass p-4 text-center">
            <div class="text-2xl font-bold text-red-400" id="statCritical">0</div>
            <div class="text-xs text-slate-400 mt-1">Critical</div>
        </div>
        <div class="glass p-4 text-center">
            <div class="text-2xl font-bold text-orange-400" id="statHigh">0</div>
            <div class="text-xs text-slate-400 mt-1">High</div>
        </div>
        <div class="glass p-4 text-center">
            <div class="text-2xl font-bold text-yellow-400" id="statMedium">0</div>
            <div class="text-xs text-slate-400 mt-1">Medium</div>
        </div>
        <div class="glass p-4 text-center">
            <div class="text-2xl font-bold text-emerald-400" id="statTotal">0</div>
            <div class="text-xs text-slate-400 mt-1">Total Issues</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-5">

        <!-- Vulnerability List -->
        <div class="xl:col-span-2 glass overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10 text-sm font-medium text-slate-300">
                Detected Vulnerabilities
            </div>
            <div id="vulnList" class="max-h-[560px] overflow-y-auto p-3 space-y-2">
                <p class="text-slate-500 text-sm p-4">Click “Run Taint Scan” to start analysis.</p>
            </div>
        </div>

        <!-- Journey Tracer -->
        <div class="xl:col-span-3 glass overflow-hidden">
            <div class="px-4 py-3 border-b border-white/10 text-sm font-medium text-slate-300">
                Variable Journey Tracer
            </div>
            <div id="tracerPanel" class="p-5 min-h-[400px]">
                <p class="text-slate-500 text-sm">Select a vulnerability to see the tainted data flow (Source → Path → Sink).</p>
            </div>
        </div>
    </div>
</div>

<script>
let vulnerabilities = [];

async function runScan() {
    const btn = document.getElementById('btnScan');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Scanning...';

    try {
        const res = await fetch('fetch-security-report.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'scan' })
        });
        const data = await res.json();

        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-shield-virus mr-2"></i> Run Taint Scan';

        if (!data.success) {
            document.getElementById('vulnList').innerHTML =
                `<p class="text-red-400 text-sm p-4">${escapeHtml(data.error)}</p>`;
            return;
        }

        vulnerabilities = data.vulnerabilities || [];
        renderList();
        updateStats(data.stats || {});
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-shield-virus mr-2"></i> Run Taint Scan';
        document.getElementById('vulnList').innerHTML =
            `<p class="text-red-400 text-sm p-4">Network / server error</p>`;
    }
}

function updateStats(stats) {
    document.getElementById('statCritical').innerText = stats.critical || 0;
    document.getElementById('statHigh').innerText     = stats.high || 0;
    document.getElementById('statMedium').innerText   = stats.medium || 0;
    document.getElementById('statTotal').innerText    = stats.total || 0;
}

function renderList() {
    const box = document.getElementById('vulnList');
    if (!vulnerabilities.length) {
        box.innerHTML = `<p class="text-emerald-400 text-sm p-4">No taint issues found.</p>`;
        return;
    }

    box.innerHTML = vulnerabilities.map((v, i) => `
        <div class="p-3 rounded-xl bg-white/5 hover:bg-white/10 cursor-pointer transition risk-${v.risk}"
             onclick="showTrace(${i})">
            <div class="flex justify-between items-start gap-2">
                <div>
                    <div class="text-sm font-medium text-white">${escapeHtml(v.type)}</div>
                    <div class="text-xs text-slate-400 mt-1 mono">${escapeHtml(v.file)}:${v.line}</div>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white/10 uppercase">${v.risk}</span>
            </div>
            <div class="text-xs text-slate-500 mt-2">${escapeHtml(v.sink || '')}</div>
        </div>
    `).join('');
}

function showTrace(index) {
    const v = vulnerabilities[index];
    if (!v) return;

    let html = `
        <div class="mb-4">
            <div class="text-lg font-semibold text-white">${escapeHtml(v.type)}</div>
            <div class="text-sm text-slate-400 mt-1">${escapeHtml(v.message || '')}</div>
        </div>
        <div class="mono text-xs text-slate-500 mb-4">${escapeHtml(v.file)} — Line ${v.line}</div>
    `;

    if (v.trace && v.trace.length) {
        html += `<div class="text-sm font-medium text-slate-300 mb-3">Data Flow Path</div>`;
        v.trace.forEach((step, idx) => {
            const cls = idx === 0 ? 'source' : (idx === v.trace.length - 1 ? 'sink' : '');
            html += `
            <div class="trace-step">
                <div class="trace-dot ${cls}"></div>
                <div class="text-sm text-slate-200">${escapeHtml(step.label || step)}</div>
                ${step.file ? `<div class="text-xs text-slate-500 mono mt-0.5">${escapeHtml(step.file)}${step.line ? ':' + step.line : ''}</div>` : ''}
            </div>`;
        });
    } else {
        html += `<p class="text-slate-500 text-sm">No detailed trace available for this finding.</p>`;
    }

    document.getElementById('tracerPanel').innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
</body>
</html>