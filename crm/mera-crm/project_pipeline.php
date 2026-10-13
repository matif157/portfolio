<?php
// =============================================
// FILE: project_pipeline.php
// PURPOSE: SPS OS - Active Project Execution (Kanban)
// Theme  : Dark glassmorphism matching CRM
// =============================================

require_once 'log_in.php';
require_once 'sidebar.php';

// Project ID URL se lo
$project_id = $_GET['id'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Pipeline - SPS OS</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Poppins', sans-serif; }

        body {
            background: radial-gradient(circle at top left, rgba(37,99,235,.15), transparent 40%),
                        radial-gradient(circle at bottom right, rgba(139,92,246,.12), transparent 40%),
                        #020617;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .main-wrapper {
            margin-left: 320px;
            padding: 24px 28px;
            min-height: 100vh;
        }

        .glass {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(16px);
            border-radius: 16px;
        }

        .kanban-board {
            display: flex;
            gap: 18px;
            overflow-x: auto;
            padding-bottom: 20px;
            min-height: 65vh;
        }

        .kanban-column {
            min-width: 300px;
            max-width: 320px;
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
        }

        .kanban-header {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .kanban-body {
            padding: 12px;
            flex: 1;
            overflow-y: auto;
            max-height: 60vh;
        }

        .task-card {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: grab;
            transition: all 0.2s ease;
        }

        .task-card:hover {
            border-color: rgba(56,189,248,0.4);
            transform: translateY(-2px);
        }

        .task-card.done {
            opacity: 0.65;
            border-color: rgba(34,197,94,0.3);
        }

        .progress-track {
            height: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>

<div class="main-wrapper">

    <!-- ================= HEADER ================= -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="projects.php" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/15 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div class="text-sm text-slate-400" id="projectClient">Loading...</div>
                <h1 class="text-2xl font-bold text-white" id="projectTitle">Project Pipeline</h1>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="text-right">
                <div class="text-xs text-slate-400">Overall Progress</div>
                <div class="text-xl font-bold text-white" id="progressPercent">0%</div>
            </div>
            <div class="w-40">
                <div class="progress-track">
                    <div class="progress-fill" id="progressBar" style="width: 0%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= KANBAN BOARD ================= -->
    <div class="kanban-board" id="kanbanBoard">
        <!-- Columns will be rendered by JS -->
    </div>

</div>

<script>
// =====================================================
// SAMPLE PROJECT DATA (later database se aayega)
// =====================================================
const projectData = {
    id: '<?= htmlspecialchars($project_id ?: 'PRJ-1001') ?>',
    title: 'Luxury BK Real Estate Ledger',
    client: 'Brooklyn Heights Realty',
    progress: 42,
    stages: [
        {
            id: 'stage-1',
            name: 'Intake & Setup',
            color: '#3b82f6',
            tasks: [
                { id: 't1', title: 'Collect Client Documents', type: 'checkbox', done: true },
                { id: 't2', title: 'Setup Chart of Accounts', type: 'checkbox', done: true },
                { id: 't3', title: 'Secure Vault - Bank Login', type: 'vault', done: false, secret: '••••••••' }
            ]
        },
        {
            id: 'stage-2',
            name: 'Monthly Execution',
            color: '#8b5cf6',
            tasks: [
                { id: 't4', title: 'January Books Recon', type: 'checkbox', done: true },
                { id: 't5', title: 'February Books Recon', type: 'checkbox', done: false },
                { id: 't6', title: 'March Books Recon', type: 'checkbox', done: false },
                { id: 't7', title: 'Upload Bank Statements', type: 'upload', done: false }
            ]
        },
        {
            id: 'stage-3',
            name: 'Review & Delivery',
            color: '#10b981',
            tasks: [
                { id: 't8', title: 'Internal Review', type: 'checkbox', done: false },
                { id: 't9', title: 'Send Monthly Report Email', type: 'email', done: false },
                { id: 't10', title: 'Client Approval SMS', type: 'sms', done: false }
            ]
        },
        {
            id: 'stage-4',
            name: 'Billing',
            color: '#f59e0b',
            tasks: [
                { id: 't11', title: 'Generate Invoice', type: 'invoice', done: false, amount: 850 },
                { id: 't12', title: 'Payment Received', type: 'checkbox', done: false }
            ]
        }
    ]
};

// =====================================================
// RENDER KANBAN
// =====================================================
function renderKanban() {
    document.getElementById('projectTitle').innerText = projectData.title;
    document.getElementById('projectClient').innerText = projectData.client;
    updateProgress();

    const board = document.getElementById('kanbanBoard');
    board.innerHTML = projectData.stages.map(stage => `
        <div class="kanban-column">
            <div class="kanban-header">
                <div class="flex items-center gap-2">
                    <div class="w-2.5 h-2.5 rounded-full" style="background:${stage.color}"></div>
                    <span>${stage.name}</span>
                </div>
                <span class="text-xs text-slate-400 bg-white/10 px-2 py-0.5 rounded-full">${stage.tasks.length}</span>
            </div>
            <div class="kanban-body">
                ${stage.tasks.map(task => renderTaskCard(task, stage.id)).join('')}
            </div>
        </div>
    `).join('');
}

function renderTaskCard(task, stageId) {
    const typeIcons = {
        checkbox: 'fa-square-check',
        upload: 'fa-cloud-arrow-up',
        vault: 'fa-lock',
        email: 'fa-envelope',
        sms: 'fa-comment-sms',
        invoice: 'fa-file-invoice-dollar'
    };

    return `
    <div class="task-card ${task.done ? 'done' : ''}" id="task-${task.id}">
        <div class="flex items-start gap-3">
            <input type="checkbox" ${task.done ? 'checked' : ''} 
                   onchange="toggleTask('${task.id}', '${stageId}')"
                   class="mt-1 rounded border-slate-500">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fa-solid ${typeIcons[task.type] || 'fa-circle'} text-xs text-slate-400"></i>
                    <span class="text-sm font-medium ${task.done ? 'line-through text-slate-500' : 'text-white'}">${task.title}</span>
                </div>

                ${task.type === 'invoice' ? `<div class="text-xs text-amber-400 mt-1">Amount: $${task.amount || 0}</div>` : ''}
                ${task.type === 'vault' ? `<div class="text-xs text-slate-400 mt-1">Secure • ${task.secret || '••••'}</div>` : ''}

                <div class="flex gap-2 mt-3">
                    ${task.type === 'upload' ? `<button class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/15">Upload</button>` : ''}
                    ${task.type === 'email' ? `<button class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/15">Send Email</button>` : ''}
                    ${task.type === 'sms' ? `<button class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/15">Send SMS</button>` : ''}
                    ${task.type === 'vault' ? `<button class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/15">Reveal</button>` : ''}
                </div>
            </div>
        </div>
    </div>`;
}

// =====================================================
// PROGRESS CALCULATION
// =====================================================
function updateProgress() {
    let total = 0;
    let done = 0;

    projectData.stages.forEach(stage => {
        stage.tasks.forEach(task => {
            total += 1;
            if (task.done) done += 1;
        });
    });

    const percent = total === 0 ? 0 : Math.round((done / total) * 100);
    projectData.progress = percent;

    document.getElementById('progressPercent').innerText = percent + '%';
    document.getElementById('progressBar').style.width = percent + '%';
}

// =====================================================
// TOGGLE TASK
// =====================================================
function toggleTask(taskId, stageId) {
    const stage = projectData.stages.find(s => s.id === stageId);
    if (!stage) return;

    const task = stage.tasks.find(t => t.id === taskId);
    if (!task) return;

    task.done = !task.done;
    updateProgress();
    renderKanban();
}

// Initial load
renderKanban();
</script>

</body>
</html>