<?php
/**
 * SPS Professional Services CRM
 * Tasks Management & Operational Checklist
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

// Task Completion Toggle
if (isset($_GET['toggle_id'])) {
    $task_id = intval($_GET['toggle_id']);
    $stmtT = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
    $stmtT->execute([$task_id]);
    $curr = $stmtT->fetchColumn();
    $new_status = ($curr === 'completed') ? 'pending' : 'completed';
    $completed_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
    
    $pdo->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ?")->execute([$new_status, $completed_at, $task_id]);
    log_activity('Toggle Task', 'tasks', $task_id, "Updated task status to $new_status");
    set_flash('success', "Task marked as $new_status.");
    redirect('tasks.php');
}

// Create Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    verify_csrf();
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? date('Y-m-d');
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $_SESSION['user_id'];

    if (!empty($title)) {
        $stmt = $pdo->prepare("INSERT INTO tasks (client_id, title, description, priority, due_date, assigned_to) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $title, $description, $priority, $due_date, $assigned_to]);
        set_flash('success', 'New operational task created.');
        redirect('tasks.php');
    }
}
$filter = $_GET['filter'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$query = "
    SELECT t.*, c.first_name, c.last_name, c.company_name, u.full_name as assigned_staff
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE 1=1
";
$params = [];

if ($filter === 'my_tasks') {
    $query .= " AND t.assigned_to = ?";
    $params[] = $_SESSION['user_id'];
}
if (!empty($priority_filter)) {
    $query .= " AND t.priority = ?";
    $params[] = $priority_filter;
}

$query .= " ORDER BY CASE WHEN t.status = 'pending' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END, t.due_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();
$staff = $pdo->query("SELECT id, full_name FROM users WHERE status = 'active'")->fetchAll();

render_header('Operations Tasks', 'tasks');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tasks & Operations List</h1>
        <p class="page-subtitle">Track bookkeeping, reconciliation, and client document follow-up assignments</p>
    </div>
    <div>
        <button onclick="document.getElementById('addTaskModal').style.display = 'block'" class="btn btn-primary">+ Create New Task</button>
    </div>
</div>

<div id="addTaskModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:80px auto;">
        <div class="card-title">
            <span>Create New Task</span>
            <button onclick="document.getElementById('addTaskModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="tasks.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_task">

            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Review March 2026 Bank Statements">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Client (Optional)</label>
                    <select name="client_id" class="form-control">
                        <option value="">-- General Task --</option>
                        <?php foreach ($clients as $cl): ?>
                            <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-control">
                        <?php foreach ($staff as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= $_SESSION['user_id'] == $st['id'] ? 'selected' : '' ?>><?= e($st['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Task Instructions</label>
                <textarea name="description" class="form-control" rows="2"></textarea>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addTaskModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Task</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px;">Done</th>
                    <th>Task Description</th>
                    <th>Client</th>
                    <th>Assigned To</th>
                    <th>Priority</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No tasks recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <tr <?= $t['status'] === 'completed' ? 'style="opacity:0.6; background:#f8fafc;"' : '' ?>>
                            <td>
                                <a href="tasks.php?toggle_id=<?= $t['id'] ?>" style="font-size:18px; text-decoration:none;">
                                    <?= $t['status'] === 'completed' ? '☑' : '☐' ?>
                                </a>
                            </td>
                            <td>
                                <strong <?= $t['status'] === 'completed' ? 'style="text-decoration:line-through;"' : '' ?>><?= e($t['title']) ?></strong>
                                <?php if ($t['description']): ?>
                                    <div style="font-size:12px; color:var(--text-muted);"><?= e($t['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['company_name'] ?: "{$t['first_name']} {$t['last_name']}") ?></td>
                            <td><?= e($t['assigned_staff'] ?: 'Unassigned') ?></td>
                            <td><span class="badge badge-<?= $t['priority'] === 'urgent' || $t['priority'] === 'high' ? 'danger' : 'secondary' ?>"><?= ucfirst($t['priority']) ?></span></td>
                            <td><span class="badge badge-warning"><?= format_date($t['due_date']) ?></span></td>
                            <td><span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
