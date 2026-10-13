<?php
/**
 * =====================================================
 * FILE: sandbox.php
 * PURPOSE: AST-Driven Custom Code Sandbox UI
 * Theme : Dark glassmorphism matching CRM
 * =====================================================
 */

require_once 'log_in.php';
//require_once 'sidebar.php';

// Only allow trusted roles
require_login();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code Sandbox – SPS OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Poppins:wght@400;500;600&display=swap');
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: #020617;
            color: #e2e8f0;
            min-height: 100vh;
        }
        .main-wrapper { margin-left: 320px; padding: 20px 24px; min-height: 100vh; }
        .panel {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            overflow: hidden;
        }
        #codeEditor {
            width: 100%;
            height: 100%;
            min-height: 420px;
            background: #0f172a;
            color: #e2e8f0;
            border: none;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13.5px;
            line-height: 1.55;
            resize: none;
            outline: none;
        }
        .tab-btn.active { background: #2563eb; color: white; }
        .console-output {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .ast-node { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
        .metric-pill {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="main-wrapper">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div>
            <h1 class="text-2xl font-semibold text-white">AST Code Sandbox</h1>
            <p class="text-slate-400 text-sm mt-1">Safe PHP playground with AST security analysis</p>
        </div>
        <div class="flex gap-2">
            <button onclick="runSafetyCheck()" class="px-4 py-2 rounded-lg bg-amber-600/90 hover:bg-amber-600 text-sm font-medium">
                <i class="fa-solid fa-shield-halved mr-1"></i> Run Safety Check
            </button>
            <button onclick="executeCode()" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-medium">
                <i class="fa-solid fa-play mr-1"></i> Execute Code
            </button>
            <button onclick="clearConsole()" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-sm font-medium">
                Clear
            </button>
        </div>
    </div>

    <!-- Split Pane -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4" style="min-height: 520px;">

        <!-- LEFT: Code Editor -->
        <div class="panel flex flex-col">
            <div class="px-4 py-3 border-b border-white/10 flex justify-between items-center">
                <span class="text-sm font-medium text-slate-300">PHP Code Editor</span>
                <span class="text-xs text-slate-500">Untrusted code is analyzed via AST</span>
            </div>
            <textarea id="codeEditor" spellcheck="false"><?php echo htmlspecialchars('<?php
// Example safe code
$clients = ["Ahmed", "Sara", "Ali"];
foreach ($clients as $name) {
    echo "Client: $name\\n";
}
return count($clients);
'); ?></textarea>
        </div>

        <!-- RIGHT: Results + AST -->
        <div class="panel flex flex-col">
            <div class="px-4 py-2 border-b border-white/10 flex gap-2">
                <button class="tab-btn active px-3 py-1.5 rounded-md text-sm" data-tab="results" onclick="switchTab('results')">
                    Execution Results
                </button>
                <button class="tab-btn px-3 py-1.5 rounded-md text-sm text-slate-400" data-tab="ast" onclick="switchTab('ast')">
                    AST Token Tree
                </button>
            </div>

            <!-- Results Tab -->
            <div id="tab-results" class="flex-1 p-4 overflow-auto">
                <div class="flex flex-wrap gap-2 mb-4" id="metricsBox"></div>
                <div class="console-output text-slate-300" id="outputBox">
                    Ready. Write PHP code and run Safety Check or Execute.
                </div>
            </div>

            <!-- AST Tab -->
            <div id="tab-ast" class="flex-1 p-4 overflow-auto hidden">
                <div class="ast-node text-slate-400" id="astBox">
                    AST tree will appear here after analysis.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    document.getElementById('tab-results').classList.toggle('hidden', tab !== 'results');
    document.getElementById('tab-ast').classList.toggle('hidden', tab !== 'ast');
}

function clearConsole() {
    document.getElementById('outputBox').innerText = 'Console cleared.';
    document.getElementById('metricsBox').innerHTML = '';
    document.getElementById('astBox').innerText = 'AST tree will appear here after analysis.';
}

async function runSafetyCheck() {
    const code = document.getElementById('codeEditor').value;
    const res = await fetch('execute-sandbox.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ code, mode: 'analyze' })
    });
    const data = await res.json();
    renderResponse(data);
    switchTab(data.ast ? 'ast' : 'results');
}

async function executeCode() {
    const code = document.getElementById('codeEditor').value;
    const res = await fetch('execute-sandbox.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ code, mode: 'execute' })
    });
    const data = await res.json();
    renderResponse(data);
    switchTab('results');
}

function renderResponse(data) {
    const output = document.getElementById('outputBox');
    const metrics = document.getElementById('metricsBox');
    const astBox = document.getElementById('astBox');

    if (!data.success) {
        output.innerHTML = `<span class="text-red-400">${escapeHtml(data.error || 'Unknown error')}</span>`;
        metrics.innerHTML = '';
        if (data.ast) astBox.innerText = data.ast;
        return;
    }

    let html = '';
    if (data.security === 'passed') {
        html += `<span class="text-emerald-400">✓ Security check passed</span>\n\n`;
    }
    if (data.output !== undefined) {
        html += `<strong>Output:</strong>\n${escapeHtml(data.output || '(empty)')}\n\n`;
    }
    if (data.return_value !== undefined && data.return_value !== null) {
        html += `<strong>Return:</strong> ${escapeHtml(JSON.stringify(data.return_value))}\n`;
    }
    output.innerHTML = html || 'No output.';

    metrics.innerHTML = '';
    if (data.execution_time_ms !== undefined) {
        metrics.innerHTML += `<span class="metric-pill">Time: ${data.execution_time_ms} ms</span>`;
    }
    if (data.peak_memory_kb !== undefined) {
        metrics.innerHTML += `<span class="metric-pill">Peak Memory: ${data.peak_memory_kb} KB</span>`;
    }

    if (data.ast) {
        astBox.innerText = data.ast;
    }
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
</body>
</html>
