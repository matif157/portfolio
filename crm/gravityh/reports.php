<?php
/**
 * GRAVITY CRM - Reports
 * Phase 2: Core CRM Modules
 * 
 * Yahan built-in reports hain:
 * - Client reports
 * - Lead reports
 * - Revenue reports
 * - Project reports
 * - Task reports
 * - Invoice reports
 * CSV export bhi available hai.
 */

require_once __DIR__ . '/functions.php';
require_login();
require_permission('reports', 'view');

$user = current_user();
$page_title = 'Reports';
$active_page = 'reports';

$report_type = get('type', 'clients');
$start_date = get('start_date', date('Y-m-d', strtotime('-30 days')));
$end_date = get('end_date', date('Y-m-d'));
$status = get('status', '');

// CSV Export
if (get('export') !== '') {
    require_permission('reports', 'export');
    $rows = get_report_data($report_type, $start_date, $end_date, $status);
    $columns = get_report_columns($report_type);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Ymd') . '.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, $columns);
    foreach ($rows as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// Get report data
$report_rows = get_report_data($report_type, $start_date, $end_date, $status);
$report_columns = get_report_columns($report_type);

// Summary stats
$summary = get_report_summary($report_type, $start_date, $end_date);

/**
 * Get report columns based on type
 */
function get_report_columns(string $type): array {
    switch ($type) {
        case 'clients':
            return ['Name', 'Type', 'Email', 'Phone', 'Status', 'Source', 'Created', 'Assigned To'];
        case 'leads':
            return ['Name', 'Email', 'Phone', 'Source', 'Status', 'Created', 'Assigned To'];
        case 'revenue':
            return ['Invoice #', 'Client', 'Invoice Date', 'Status', 'Subtotal', 'Tax', 'Total', 'Paid', 'Balance'];
        case 'projects':
            return ['Project', 'Client', 'Status', 'Priority', 'Assigned To', 'Due Date', 'Progress', 'Budget', 'Billed'];
        case 'tasks':
            return ['Task', 'Project', 'Status', 'Assigned To', 'Due Date', 'Priority', 'Progress'];
        case 'services':
            return ['Service', 'Type', 'Billing', 'Rate', 'Clients Using', 'Status'];
        default:
            return [];
    }
}

/**
 * Get report data
 */
function get_report_data(string $type, string $start_date, string $end_date, string $status): array {
    switch ($type) {
        case 'clients':
            $where = '1=1';
            $params = [];
            if ($start_date) { $where .= ' AND c.created_at >= ?'; $params[] = $start_date . ' 00:00:00'; }
            if ($end_date) { $where .= ' AND c.created_at <= ?'; $params[] = $end_date . ' 23:59:59'; }
            if ($status !== '') { $where .= ' AND c.status = ?'; $params[] = $status; }
            return db_query(
                "SELECT CONCAT(c.first_name, ' ', c.last_name) AS Name, ct.name AS Type, c.email AS Email, c.phone AS Phone, c.status AS Status, c.source AS Source, DATE(c.created_at) AS Created, CONCAT(u.first_name, ' ', u.last_name) AS Assigned_To 
                 FROM clients c 
                 LEFT JOIN client_types ct ON c.client_type_id = ct.id 
                 LEFT JOIN users u ON c.assigned_user_id = u.id 
                 WHERE {$where} 
                 ORDER BY c.created_at DESC",
                $params
            );
            
        case 'leads':
            $where = '1=1';
            $params = [];
            if ($start_date) { $where .= ' AND l.created_at >= ?'; $params[] = $start_date . ' 00:00:00'; }
            if ($end_date) { $where .= ' AND l.created_at <= ?'; $params[] = $end_date . ' 23:59:59'; }
            if ($status !== '') { $where .= ' AND l.status = ?'; $params[] = $status; }
            return db_query(
                "SELECT CONCAT(l.first_name, ' ', l.last_name) AS Name, l.email AS Email, l.phone AS Phone, l.source AS Source, l.status AS Status, DATE(l.created_at) AS Created, CONCAT(u.first_name, ' ', u.last_name) AS Assigned_To 
                 FROM leads l 
                 LEFT JOIN users u ON l.assigned_user_id = u.id 
                 WHERE {$where} 
                 ORDER BY l.created_at DESC",
                $params
            );
            
        case 'revenue':
            $where = '1=1';
            $params = [];
            if ($start_date) { $where .= ' AND i.invoice_date >= ?'; $params[] = $start_date; }
            if ($end_date) { $where .= ' AND i.invoice_date <= ?'; $params[] = $end_date; }
            if ($status !== '') { $where .= ' AND i.status = ?'; $params[] = $status; }
            return db_query(
                "SELECT i.invoice_number AS Invoice_No, CONCAT(c.first_name, ' ', c.last_name) AS Client, i.invoice_date AS Invoice_Date, i.status AS Status, i.subtotal AS Subtotal, i.tax_amount AS Tax, i.total_amount AS Total, i.amount_paid AS Paid, i.amount_due AS Balance 
                 FROM invoices i 
                 JOIN clients c ON i.client_id = c.id 
                 WHERE {$where} 
                 ORDER BY i.invoice_date DESC",
                $params
            );
            
        case 'projects':
            $where = '1=1';
            $params = [];
            if ($status !== '') { $where .= ' AND p.status = ?'; $params[] = $status; }
            return db_query(
                "SELECT p.name AS Project, CONCAT(c.first_name, ' ', c.last_name) AS Client, p.status AS Status, p.priority AS Priority, CONCAT(u.first_name, ' ', u.last_name) AS Assigned_To, p.due_date AS Due_Date, CONCAT(p.progress_value, '%') AS Progress, p.budget AS Budget, p.billed_amount AS Billed 
                 FROM projects p 
                 JOIN clients c ON p.client_id = c.id 
                 LEFT JOIN users u ON p.assigned_user_id = u.id 
                 WHERE {$where} 
                 ORDER BY p.created_at DESC",
                $params
            );
            
        case 'tasks':
            $where = '1=1';
            $params = [];
            if ($status !== '') { $where .= ' AND pt.status = ?'; $params[] = $status; }
            return db_query(
                "SELECT pt.title AS Task, p.name AS Project, pt.status AS Status, CONCAT(u.first_name, ' ', u.last_name) AS Assigned_To, pt.due_date AS Due_Date, pt.priority AS Priority, CONCAT(pt.progress_value, '%') AS Progress 
                 FROM project_tasks pt 
                 LEFT JOIN projects p ON pt.project_id = p.id 
                 LEFT JOIN users u ON pt.assigned_user_id = u.id 
                 WHERE {$where} 
                 ORDER BY pt.created_at DESC",
                $params
            );
            
        case 'services':
            return db_query(
                "SELECT s.name AS Service, s.service_type AS Type, s.billing_type AS Billing, s.status AS Status, COUNT(cs.id) AS Clients_Using 
                 FROM services s 
                 LEFT JOIN client_services cs ON s.id = cs.service_id AND cs.status = 'active' 
                 GROUP BY s.id 
                 ORDER BY s.name ASC"
            );
            
        default:
            return [];
    }
}

/**
 * Get report summary stats
 */
function get_report_summary(string $type, string $start_date, string $end_date): array {
    switch ($type) {
        case 'clients':
            $new_clients = (int)db_value("SELECT COUNT(*) FROM clients WHERE created_at >= ? AND created_at <= ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $total = (int)db_value('SELECT COUNT(*) FROM clients');
            $converted_leads = (int)db_value('SELECT COUNT(*) FROM leads WHERE status = "converted"');
            return ['label1' => 'New Clients', 'value1' => $new_clients, 'label2' => 'Total Clients', 'value2' => $total, 'label3' => 'Converted Leads', 'value3' => $converted_leads];
            
        case 'leads':
            $new_leads = (int)db_value("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND created_at <= ?", [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $total = (int)db_value('SELECT COUNT(*) FROM leads');
            $converted = (int)db_value('SELECT COUNT(*) FROM leads WHERE status = "converted"');
            return ['label1' => 'New Leads', 'value1' => $new_leads, 'label2' => 'Total Leads', 'value2' => $total, 'label3' => 'Converted', 'value3' => $converted];
            
        case 'revenue':
            $revenue = (float)db_value("SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE invoice_date >= ? AND invoice_date <= ? AND status NOT IN ('cancelled','draft')", [$start_date, $end_date]);
            $collected = (float)db_value("SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE invoice_date >= ? AND invoice_date <= ? AND status NOT IN ('cancelled','draft')", [$start_date, $end_date]);
            $outstanding = (float)db_value("SELECT COALESCE(SUM(amount_due), 0) FROM invoices WHERE status NOT IN ('paid','cancelled','draft')");
            return ['label1' => 'Revenue', 'value1' => format_currency($revenue), 'label2' => 'Collected', 'value2' => format_currency($collected), 'label3' => 'Outstanding', 'value3' => format_currency($outstanding)];
            
        case 'projects':
            $total = (int)db_value('SELECT COUNT(*) FROM projects');
            $active = (int)db_value("SELECT COUNT(*) FROM projects WHERE status IN ('in_progress','not_started','on_hold')");
            $completed = (int)db_value('SELECT COUNT(*) FROM projects WHERE status = "completed"');
            return ['label1' => 'Total Projects', 'value1' => $total, 'label2' => 'Active', 'value2' => $active, 'label3' => 'Completed', 'value3' => $completed];
            
        default:
            $total = count(get_report_data($type, $start_date, $end_date, ''));
            return ['label1' => 'Total Records', 'value1' => $total, 'label2' => '', 'value2' => '', 'label3' => '', 'value3' => ''];
    }
}
?>
<?php include __DIR__ . '/header.php'; ?>

<div class="page-title">
    <h1><i class="bi bi-graph-up"></i> Reports</h1>
</div>

<!-- Summary Stats -->
<?php if (!empty($summary)): ?>
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dbeafe;color:#1e40af;"><i class="bi bi-bar-chart"></i></div>
            <div class="stat-value"><?= e($summary['value1'] ?? 0) ?></div>
            <div class="stat-label"><?= e($summary['label1'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#dcfce7;color:#166534;"><i class="bi bi-pie-chart"></i></div>
            <div class="stat-value"><?= e($summary['value2'] ?? 0) ?></div>
            <div class="stat-label"><?= e($summary['label2'] ?? '') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:#fef3c7;color:#92400e;"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-value"><?= e($summary['value3'] ?? 0) ?></div>
            <div class="stat-label"><?= e($summary['label3'] ?? '') ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Report Configuration -->
<div class="card">
    <div class="card-header"><i class="bi bi-funnel"></i> Report Configuration</div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label-custom">Report Type</label>
                <select name="type" class="form-control-custom" onchange="this.form.submit()">
                    <option value="clients" <?= $report_type === 'clients' ? 'selected' : '' ?>>Client Report</option>
                    <option value="leads" <?= $report_type === 'leads' ? 'selected' : '' ?>>Lead Report</option>
                    <option value="revenue" <?= $report_type === 'revenue' ? 'selected' : '' ?>>Revenue Report</option>
                    <option value="projects" <?= $report_type === 'projects' ? 'selected' : '' ?>>Project Report</option>
                    <option value="tasks" <?= $report_type === 'tasks' ? 'selected' : '' ?>>Task Report</option>
                    <option value="services" <?= $report_type === 'services' ? 'selected' : '' ?>>Services Report</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">Start Date</label>
                <input type="date" name="start_date" class="form-control-custom" value="<?= e($start_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">End Date</label>
                <input type="date" name="end_date" class="form-control-custom" value="<?= e($end_date) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">Status</label>
                <select name="status" class="form-control-custom">
                    <option value="">All Status</option>
                    <?php if ($report_type === 'clients'): ?>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
                    <?php elseif ($report_type === 'leads'): ?>
                        <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="qualified" <?= $status === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                        <option value="converted" <?= $status === 'converted' ? 'selected' : '' ?>>Converted</option>
                        <option value="lost" <?= $status === 'lost' ? 'selected' : '' ?>>Lost</option>
                    <?php elseif ($report_type === 'revenue'): ?>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    <?php elseif ($report_type === 'projects'): ?>
                        <option value="not_started" <?= $status === 'not_started' ? 'selected' : '' ?>>Not Started</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn-primary-custom"><i class="bi bi-search"></i> Generate Report</button>
                <?php if (user_can('reports', 'export')): ?>
                    <a href="reports.php?export=1&type=<?= e($report_type) ?>&start_date=<?= e($start_date) ?>&end_date=<?= e($end_date) ?>&status=<?= e($status) ?>" class="btn-secondary-custom"><i class="bi bi-download"></i> Export CSV</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Report Results -->
<div class="card">
    <div class="card-header">
        <?= e(ucfirst($report_type)) ?> Report (<?= count($report_rows) ?> records)
    </div>
    <div class="card-body table-responsive">
        <?php if (empty($report_rows)): ?>
            <p class="text-muted">No records found for the selected period.</p>
        <?php else: ?>
            <table class="table-custom">
                <thead>
                    <tr>
                        <?php foreach ($report_columns as $col): ?>
                            <th><?= e(str_replace('_', ' ', $col)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $value): ?>
                                <td><?= e($value) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>