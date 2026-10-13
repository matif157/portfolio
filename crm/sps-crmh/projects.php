<?php
/**
 * SPS Professional Services CRM
 * Projects Management & Status Dashboard Cards
 */
require_once __DIR__ . '/auth.php';
require_permission('services.view');

$status_filter = trim($_GET['status'] ?? '');

// Handle Create Project Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project') {
    verify_csrf();
    $client_id = intval($_POST['client_id']);
    $business_id = !empty($_POST['business_id']) ? intval($_POST['business_id']) : null;
    $service_id = !empty($_POST['service_id']) ? intval($_POST['service_id']) : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $total_amount = floatval($_POST['total_amount'] ?? 0);
    $due_date = $_POST['due_date'] ?? null;
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence_pattern = $_POST['recurrence_pattern'] ?? 'monthly';

    if (!empty($title) && $client_id > 0) {
        $next_id = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM projects")->fetchColumn();
        $project_code = 'PRJ-' . date('Y') . '-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO projects 
            (project_code, client_id, business_id, service_id, title, description, status, priority, start_date, due_date, total_amount, is_recurring, recurrence_pattern)
            VALUES (?, ?, ?, ?, ?, ?, 'open', ?, CURDATE(), ?, ?, ?, ?)
        ");
        $stmt->execute([$project_code, $client_id, $business_id, $service_id, $title, $description, $priority, $due_date, $total_amount, $is_recurring, $recurrence_pattern]);
        $prj_id = $pdo->lastInsertId();

        log_activity('Add Project', 'projects', $prj_id, "Created project $project_code ($title)");
        set_flash('success', "Project $project_code created successfully!");
        redirect("project_view.php?id=$prj_id");
    }
}

// Stats per status as required by Spec Section 16 (Count, Invoice Total, Paid, Outstanding)
$statuses = ['open', 'upcoming', 'expired', 'completed', 'closed'];
$status_stats = [];
foreach ($statuses as $st) {
    $stmtS = $pdo->prepare("
        SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(paid_amount), 0) as paid
        FROM projects WHERE status = ?
    ");
    $stmtS->execute([$st]);
    $res = $stmtS->fetch();
    $status_stats[$st] = [
        'count' => $res['cnt'],
        'total' => $res['total'],
        'paid' => $res['paid'],
        'outstanding' => $res['total'] - $res['paid']
    ];
}

$query = "
    SELECT p.*, c.first_name, c.last_name, c.company_name, b.business_name
    FROM projects p
    JOIN clients c ON p.client_id = c.id
    LEFT JOIN businesses b ON p.business_id = b.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== '') {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}
$query .= " ORDER BY p.due_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

$clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();
$services = $pdo->query("SELECT id, title FROM services ORDER BY title")->fetchAll();

render_header('Projects Directory', 'projects');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Project Deliverables & Operations</h1>
        <p class="page-subtitle">Track service execution cycles, milestone completion, and recurring series</p>
    </div>
    <div>
        <button onclick="document.getElementById('addPrjModal').style.display = 'block'" class="btn btn-primary">+ Create Project</button>
        <a href="recurring_projects.php" class="btn btn-secondary">🔄 Recurring Series</a>
    </div>
</div>

<!-- Project Status Executive Cards (Section 16 Requirement) -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
    <?php foreach ($statuses as $st): ?>
        <?php $stat = $status_stats[$st]; ?>
        <div class="stat-card" style="border-top:3px solid <?= $st === 'open' ? 'var(--accent)' : ($st === 'completed' ? 'var(--success)' : 'var(--border-color)') ?>;">
            <span class="stat-label"><?= strtoupper($st) ?> PROJECTS</span>
            <span class="stat-value" style="font-size:24px;"><?= $stat['count'] ?></span>
            <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">
                Total: <?= format_currency($stat['total']) ?><br>
                Paid: <?= format_currency($stat['paid']) ?><br>
                <strong style="color:var(--danger);">Due: <?= format_currency($stat['outstanding']) ?></strong>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Project Code</th>
                    <th>Title</th>
                    <th>Client / Entity</th>
                    <th>Total Contract</th>
                    <th>Due Date</th>
                    <th>Recurring</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr><td colspan="8" style="text-align:center; color:var(--text-muted);">No projects found.</td></tr>
                <?php else: ?>
                    <?php foreach ($projects as $p): ?>
                        <tr>
                            <td><a href="project_view.php?id=<?= $p['id'] ?>"><strong><?= e($p['project_code']) ?></strong></a></td>
                            <td><?= e($p['title']) ?></td>
                            <td>
                                <strong><?= e($p['company_name'] ?: "{$p['first_name']} {$p['last_name']}") ?></strong>
                                <?php if ($p['business_name']): ?>
                                    <div style="font-size:11px; color:var(--text-muted);"><?= e($p['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= format_currency($p['total_amount']) ?></strong></td>
                            <td><?= format_date($p['due_date']) ?></td>
                            <td>
                                <?php if ($p['is_recurring']): ?>
                                    <span class="badge badge-info">🔄 <?= ucfirst($p['recurrence_pattern']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">One-time</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= $p['status'] === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td>
                                <a href="project_view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="addPrjModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:550px; margin:80px auto;">
        <div class="card-title">
            <span>Create New Project</span>
            <button onclick="document.getElementById('addPrjModal').style.display = 'none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <form action="projects.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_project">

            <div class="form-group">
                <label class="form-label">Client Owner *</label>
                <select name="client_id" class="form-control" required>
                    <option value="">-- Select Client --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Project Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="2026 Monthly Bookkeeping & Advisory">
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Service Type</label>
                    <select name="service_id" class="form-control">
                        <option value="">-- Select Service --</option>
                        <?php foreach ($services as $srv): ?>
                            <option value="<?= $srv['id'] ?>"><?= e($srv['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Total Fee / Contract Amount ($)</label>
                    <input type="number" step="0.01" name="total_amount" class="form-control" value="5400.00">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label class="form-label">Target Completion Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-12-31') ?>">
                </div>
                <div class="form-col form-group">
                    <label class="form-label">Priority Level</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    <input type="checkbox" name="is_recurring" value="1" checked> Recurring Project Series (Auto-renews next cycle)
                </label>
            </div>

            <div style="margin-top:15px; text-align:right;">
                <button type="button" onclick="document.getElementById('addPrjModal').style.display = 'none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>

<?php render_footer(); ?>
