<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include "includes/db.php";
include "includes/auth.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    header('Location: login.php');
    exit();
}
  
$user_id = $_SESSION['user_id'];
$usertype = $_SESSION['user_type'];
$isAdmin = ($usertype == '1' || $usertype == '2');

// Context title
$contextTitle = "All System Activity";
if (!empty($_GET['object_type']) && !empty($_GET['object_id'])) {
    $map = [
        'addibusiness' => 'Associated Business',
        'billing_invoice' => 'Invoice',
        'billing_invoice_instant' => 'Instant Invoices',
        'client_field_notes' => 'Client Note',
        'comments' => 'Comments',
        'invoice' => 'Invoice',
        'member' => 'Client',
        'new_comments' => 'Invoice Comment',
        'orders' => 'Service',
        'orders_auto_renewed' => 'Auto Renew',
        'quotation' => 'Quotation',
        'quotation_note' => 'Quote Note',
        'spouse' => 'Family Member'
    ];
    $raw = strtolower(trim($_GET['object_type']));
    $label = $map[$raw] ?? ucfirst(str_replace('_', ' ', $raw));
    $contextTitle = "Activity Log: $label #" . intval($_GET['object_id']);
}

// Build WHERE clause safely
$whereConditions = [];
$params = [];
$types = '';

if (!$isAdmin) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $user_id;
    $types .= 'i';
}
if ($isAdmin && !empty($_GET['user_id'])) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $_GET['user_id'];
    $types .= 'i';
}
if (!empty($_GET['object_type'])) {
    $whereConditions[] = "al.object_type = ?";
    $params[] = $_GET['object_type'];
    $types .= 's';
}
if (!empty($_GET['object_id']) && is_numeric($_GET['object_id'])) {
    $whereConditions[] = "al.object_id = ?";
    $params[] = $_GET['object_id'];
    $types .= 'i';
}
if (!empty($_GET['from_date'])) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $_GET['from_date'];
    $types .= 's';
}
if (!empty($_GET['to_date'])) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $_GET['to_date'];
    $types .= 's';
}
if (!empty($_GET['client_id']) && is_numeric($_GET['client_id'])) {
    $cid = intval($_GET['client_id']);
    // Match direct member record OR any log whose description references this member/client ID
    $regexp = "Member ID: {$cid}([^0-9]|\$)|member ID: {$cid}([^0-9]|\$)|client ID: {$cid}([^0-9]|\$)";
    $whereConditions[] = "((al.object_type = 'member' AND al.object_id = ?) OR al.description REGEXP ?)";
    $params[] = $cid;
    $params[] = $regexp;
    $types .= 'is';
}
 
$whereSQL = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch all members for client dropdown
$members_q = $con->query("SELECT member_id, name, mname, lname, bus_name FROM members ORDER BY name, lname");

// Stats Queries
function executeQuery($con, $sql, $types = '', $params = [])
{
    $stmt = $con->prepare($sql);
    if (!empty($params))
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Total logs
$total_logs = executeQuery($con, "SELECT COUNT(*) as total FROM activity_logs al $whereSQL", $types, $params)
    ->fetch_assoc()['total'] ?? 0;

// Recent (last hour)
$recent_where = $whereSQL ? $whereSQL . " AND al.created_at >= NOW() - INTERVAL 1 HOUR" : "WHERE al.created_at >= NOW() - INTERVAL 1 HOUR";
$recent_count = executeQuery($con, "SELECT COUNT(*) as recent FROM activity_logs al $recent_where", $types, $params)
    ->fetch_assoc()['recent'] ?? 0;

// Unique modules
$unique_modules = executeQuery($con, "SELECT COUNT(DISTINCT object_type) as mods FROM activity_logs al $whereSQL", $types, $params)
    ->fetch_assoc()['mods'] ?? 0;

// Active users
$active_users = executeQuery($con, "SELECT COUNT(DISTINCT al.user_id) as active FROM activity_logs al $whereSQL", $types, $params)
    ->fetch_assoc()['active'] ?? 0;

// Top 10 users for cards + charts
$user_activity_result = executeQuery(
    $con,
    "SELECT u.user_id, u.user_name, u.user_pic, u.user_type, COUNT(al.log_id) as activity_count
     FROM users u
     LEFT JOIN activity_logs al ON u.user_id = al.user_id $whereSQL
     GROUP BY u.user_id ORDER BY activity_count DESC LIMIT 10",
    $types,
    $params
);

// Hourly heatmap data
$hourly_result = executeQuery(
    $con,
    "SELECT HOUR(created_at) as hour, COUNT(*) as count
     FROM activity_logs al $whereSQL
     GROUP BY HOUR(created_at) ORDER BY hour",
    $types,
    $params
);

// Module activity breakdown (real data)
$module_result = executeQuery(
    $con,
    "SELECT al.object_type, COUNT(*) as count
     FROM activity_logs al $whereSQL
     GROUP BY al.object_type ORDER BY count DESC LIMIT 10",
    $types,
    $params
);

// Main table data
$result = executeQuery(
    $con,
    "SELECT al.*, u.user_name, u.user_pic
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.user_id
     $whereSQL
     ORDER BY al.created_at DESC LIMIT 500",
    $types,
    $params
);

// Helper functions
function getInitials($name)
{
    $name = $name ?: 'System';
    $words = explode(' ', trim($name));
    return strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
}

function getUserRole($type)
{
    $roles = ['1' => 'Administrator', '2' => 'Manager', '3' => 'Employee', '4' => 'Internee', '5' => 'Restricted'];
    return $roles[$type] ?? 'Unknown';
}

function getModuleLabel($raw)
{
    $map = [
        'addibusiness' => 'Associated Business',
        'billing_invoice' => 'Invoice',
        'billing_invoice_instant' => 'Instant Invoice',
        'client_field_notes' => 'Client Note',
        'comments' => 'Comments',
        'invoice' => 'Invoice',
        'member' => 'Client',
        'new_comments' => 'Invoice Comment',
        'orders' => 'Service',
        'orders_auto_renewed' => 'Auto Renew',
        'quotation' => 'Quotation',
        'quotation_note' => 'Quote Note',
        'spouse' => 'Family Member'
    ];
    return $map[strtolower(trim($raw))] ?? ucfirst(str_replace('_', ' ', $raw));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Activity Logs | Professional Audit</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include "includes/links.php"; ?>

    <style>
        :root {
            --brand: #252161;
            --brand-dark: #1d1a4e;
            --brand-light: #3a3593;
            --brand-soft: #ecebf5;
            --brand-shadow: rgba(37, 33, 97, 0.28);
            --primary-bg: #4e73df;
            --success-bg: #1cc88a;
            --warning-bg: #f6c23e;
            --info-bg: #36b9cc;
            --danger-bg: #e74a3b;
            --purple-bg: #6f42c1;
            --pink-bg: #f66d9b;
        }

        .dash-content {
            padding: 2rem !important;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
            transition: all 0.3s ease;
            background: white;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            position: absolute;
            top: -15px;
            right: 20px;
            opacity: 0.9;
        }

        .stat-icon.total {
            background: linear-gradient(135deg, var(--primary-bg), #5a7fde);
        }

        .stat-icon.recent {
            background: linear-gradient(135deg, var(--success-bg), #2dd5a3);
        }

        .stat-icon.mods {
            background: linear-gradient(135deg, var(--warning-bg), #f7d070);
        }

        .stat-icon.users {
            background: linear-gradient(135deg, var(--info-bg), #4dc9de);
        }

        .stat-content {
            padding: 25px;
            margin-right: 70px;
        }

        .stat-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #858796;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #2e2e2e;
        }

        .live-pulse {
            height: 8px;
            width: 8px;
            background: var(--success-bg);
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0.7);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(28, 200, 138, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(28, 200, 138, 0);
            }
        }

        .activity-analytics {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2e2e2e;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 12px;
            color: var(--brand);
            font-size: 1.5rem;
        }

        .user-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .user-card {
            background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .user-card:hover {
            transform: translateY(-5px);
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--brand) 0%, var(--brand-light) 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .user-card:hover::before {
            transform: scaleX(1);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
            margin-right: 15px;
        }

        .user-avatar-placeholder {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(24, 1fr);
            gap: 4px;
        }

        .heatmap-cell {
            height: 40px;
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: white;
            font-weight: 600;
            position: relative;
        }

        .heatmap-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #2e2e2e;
            color: white;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }

        .heatmap-cell:hover .heatmap-tooltip {
            opacity: 1;
        }

        .chart-container {
            height: 320px;
        }

        .table-container {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .badge-category {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        /* ── Brand theming overrides (indigo #252161) ──────────────────── */
        .filter-bar .btn-primary {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
            border-color: var(--brand-dark);
            box-shadow: 0 4px 12px var(--brand-shadow);
            font-weight: 600;
        }
        .filter-bar .btn-primary:hover,
        .filter-bar .btn-primary:focus {
            background: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 100%);
            border-color: var(--brand-dark);
            box-shadow: 0 6px 16px var(--brand-shadow);
        }

        .activity-analytics .btn-outline-primary {
            color: var(--brand);
            border-color: rgba(37, 33, 97, 0.4);
        }
        .activity-analytics .btn-outline-primary:hover,
        .activity-analytics .btn-outline-primary.active {
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%);
            border-color: var(--brand);
            color: #fff;
        }

        /* Table header pill */
        .table-container thead th {
            background: var(--brand-soft);
            color: var(--brand-dark);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.4px;
            border-bottom: 2px solid var(--brand) !important;
        }

        /* ── DataTables export buttons (CSV / Excel / Print) ───────────── */
        /* Selectors stacked to beat Bootstrap's .btn-secondary on .dt-button */
        div.dt-buttons {
            gap: 8px;
            display: inline-flex;
            margin-left: 12px;
        }
        div.dt-buttons > .dt-button,
        div.dt-buttons > button.dt-button,
        .dataTables_wrapper .dt-buttons .dt-button {
            background-color: var(--brand) !important;
            background-image: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%) !important;
            color: #fff !important;
            border: 1px solid var(--brand-dark) !important;
            border-radius: 10px !important;
            padding: 8px 16px !important;
            font-size: 0.82rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.3px !important;
            box-shadow: 0 4px 10px var(--brand-shadow) !important;
            transition: transform .15s ease, box-shadow .2s ease, filter .2s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            text-shadow: none !important;
        }
        div.dt-buttons > .dt-button:hover,
        .dataTables_wrapper .dt-buttons .dt-button:hover {
            background-color: var(--brand-dark) !important;
            background-image: linear-gradient(135deg, var(--brand-dark) 0%, var(--brand) 100%) !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px var(--brand-shadow) !important;
            color: #fff !important;
            border-color: var(--brand-dark) !important;
        }
        div.dt-buttons > .dt-button:focus,
        div.dt-buttons > .dt-button.focus,
        .dataTables_wrapper .dt-buttons .dt-button:focus {
            outline: none !important;
            box-shadow: 0 0 0 3px rgba(37, 33, 97, 0.28) !important;
        }
        div.dt-buttons > .dt-button:active,
        div.dt-buttons > .dt-button.active {
            transform: translateY(0);
            filter: brightness(0.94);
        }
        /* Print button keeps gray-gradient so it reads as a "secondary" action */
        div.dt-buttons > .dt-button.btn-export-print,
        .dataTables_wrapper .dt-buttons .dt-button.btn-export-print {
            background-color: #6c757d !important;
            background-image: linear-gradient(135deg, #6c757d 0%, #495057 100%) !important;
            border-color: #495057 !important;
            box-shadow: 0 4px 10px rgba(73, 80, 87, 0.28) !important;
        }
        div.dt-buttons > .dt-button.btn-export-print:hover {
            background-image: linear-gradient(135deg, #495057 0%, #6c757d 100%) !important;
            box-shadow: 0 8px 18px rgba(73, 80, 87, 0.4) !important;
        }

        /* Recolor activity-count numbers in the user cards (was Bootstrap green) */
        .user-card .text-success {
            color: var(--brand) !important;
        }

        /* ── Activity-table category badges + avatar circles → brand palette ── */
        /* Distinct shades keep categories visually different, all in indigo family */
        .table-container .badge.bg-primary,
        .table-container .rounded-circle.bg-primary {
            background-color: var(--brand) !important;
            background-image: linear-gradient(135deg, var(--brand) 0%, var(--brand-light) 100%) !important;
            color: #fff !important;
        }
        .table-container .badge.bg-success {
            background-color: var(--brand-light) !important;
            background-image: linear-gradient(135deg, var(--brand-light) 0%, #4d49ad 100%) !important;
            color: #fff !important;
        }
        .table-container .badge.bg-info {
            background-color: #5b54c2 !important;
            background-image: linear-gradient(135deg, #5b54c2 0%, #7f7ad6 100%) !important;
            color: #fff !important;
        }
        .table-container .badge.bg-warning {
            background-color: var(--brand-soft) !important;
            background-image: none !important;
            color: var(--brand-dark) !important;
            border: 1px solid rgba(37, 33, 97, 0.15);
        }
        .table-container .badge.bg-secondary {
            background-color: #94a3b8 !important;
            background-image: none !important;
            color: #fff !important;
        }

        /* Round avatar fallback in the user column (FA, SA, AB, AQ) */
        .table-container .rounded-circle.bg-primary {
            border: 2px solid #fff;
            box-shadow: 0 2px 6px var(--brand-shadow);
        }

        /* Length/search controls tidy */
        .table-container .dataTables_length label,
        .table-container .dataTables_filter label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0;
        }
        .table-container .dataTables_length select,
        .table-container .dataTables_filter input {
            border-radius: 8px;
            border: 1px solid #e3e6f0;
            padding: 4px 10px;
        }
        .table-container .dataTables_filter input:focus {
            outline: none;
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-soft);
        }
        .table-container .page-item.active .page-link {
            background-color: var(--brand);
            border-color: var(--brand);
        }
        .table-container .page-link {
            color: var(--brand);
        }
        .table-container .page-link:hover {
            color: var(--brand-dark);
            background-color: var(--brand-soft);
        }
    </style>
</head>

<body class="theme-3">
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800 fw-bold"><?= htmlspecialchars($contextTitle) ?></h1>
                <div class="text-muted">Viewing: <strong><?= $isAdmin ? 'Global Records' : 'My Activity' ?></strong>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-title">Total Records</div>
                            <div class="stat-value animated-counter"><?= number_format($total_logs) ?></div>
                        </div>
                        <div class="stat-icon total"><i class="ti ti-clipboard-list"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-title">Last 60 Minutes</div>
                            <div class="stat-value animated-counter text-success"><?= $recent_count ?></div>
                            <div class="text-success"><span class="live-pulse"></span>Real-time</div>
                        </div>
                        <div class="stat-icon recent"><i class="ti ti-clock-bolt"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-title">Active Modules</div>
                            <div class="stat-value animated-counter text-warning"><?= $unique_modules ?></div>
                        </div>
                        <div class="stat-icon mods"><i class="ti ti-category"></i></div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-content">
                            <div class="stat-title">Active Users</div>
                            <div class="stat-value animated-counter text-info"><?= $active_users ?></div>
                        </div>
                        <div class="stat-icon users"><i class="ti ti-users"></i></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-bar">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2"><label class="form-label fw-bold text-muted">From Date</label><input
                            type="date" name="from_date" class="form-control"
                            value="<?= htmlspecialchars($_GET['from_date'] ?? '') ?>"></div>
                    <div class="col-md-2"><label class="form-label fw-bold text-muted">To Date</label><input type="date"
                            name="to_date" class="form-control" value="<?= htmlspecialchars($_GET['to_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-3"><label class="form-label fw-bold text-muted">Module</label>
                        <select name="object_type" class="form-select">
                            <option value="">All Modules</option>
                            <?php
                            $modules_q = $con->query("SELECT DISTINCT object_type FROM activity_logs ORDER BY object_type");
                            while ($m = $modules_q->fetch_assoc()) {
                                $label = getModuleLabel($m['object_type']);
                                $selected = ($_GET['object_type'] ?? '') === $m['object_type'] ? 'selected' : '';
                                echo "<option value=\"{$m['object_type']}\" $selected>$label</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="col-md-3"><label class="form-label fw-bold text-muted">User</label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php
                                $users_q = $con->query("SELECT user_id, user_name FROM users ORDER BY user_name");
                                while ($u = $users_q->fetch_assoc()) {
                                    $selected = ($_GET['user_id'] ?? '') == $u['user_id'] ? 'selected' : '';
                                    echo "<option value=\"{$u['user_id']}\" $selected>{$u['user_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3"><label class="form-label fw-bold text-muted">Client</label>
                        <select name="client_id" id="clientSelect" class="form-select">
                            <option value="">All Clients</option>
                            <?php
                            $selectedClient = $_GET['client_id'] ?? '';
                            while ($m = $members_q->fetch_assoc()):
                                $fullName = trim($m['name'] . ' ' . $m['mname'] . ' ' . $m['lname']);
                                $busName  = $m['bus_name'] ? ' — ' . $m['bus_name'] : '';
                                $label    = htmlspecialchars($fullName . $busName . ' [#' . $m['member_id'] . ']');
                                $selected = ($selectedClient == $m['member_id']) ? 'selected' : '';
                                echo "<option value=\"{$m['member_id']}\" $selected data-name=\"" . htmlspecialchars(strtolower($fullName)) . "\" data-bus=\"" . htmlspecialchars(strtolower($m['bus_name'] ?? '')) . "\" data-mid=\"{$m['member_id']}\">$label</option>";
                            endwhile;
                            ?>
                        </select>
                    </div>
                    <?php if (!empty($_GET['object_id'])): ?>
                        <input type="hidden" name="object_id" value="<?= htmlspecialchars($_GET['object_id']) ?>">
                    <?php endif; ?>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-1"><a href="activity_logs.php" class="btn btn-outline-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>

            <!-- User Activity Analytics -->
            <div class="activity-analytics">
                <div class="section-header">
                    <div class="section-title"><i class="ti ti-chart-dots-3"></i> User & Module Activity Analytics</div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary active"
                            onclick="showView('cards')">Cards</button>
                        <button type="button" class="btn btn-outline-primary"
                            onclick="showView('chart')">Charts</button>
                        <button type="button" class="btn btn-outline-primary"
                            onclick="showView('heatmap')">Heatmap</button>
                    </div>
                </div>

                <!-- Cards View -->
                <div id="cards-view" class="view-content">
                    <div class="user-cards-container">
                        <?php
                        $user_activity_data = [];
                        while ($row = $user_activity_result->fetch_assoc()) {
                            $user_activity_data[] = $row;
                        }
                        foreach ($user_activity_data as $index => $row):
                            ?>
                            <div class="user-card count-animation" style="animation-delay: <?= $index * 0.1 ?>s">
                                <div class="d-flex align-items-center mb-3">
                                    <?php
                                    $imagePath = 'Uploads/staff_images/' . basename($row['user_pic']);
                                    if (!empty($row['user_pic']) && file_exists($imagePath)): ?>
                                        <img src="<?= $imagePath ?>" alt="User" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar-placeholder"><?= getInitials($row['user_name']) ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="mb-0"><?= htmlspecialchars($row['user_name']) ?></h5>
                                        <div class="text-muted small"><?= getUserRole($row['user_type']) ?></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <div class="text-center">
                                        <div class="h4 mb-0 text-success"><?= $row['activity_count'] ?></div>
                                        <div class="small text-muted">Activities</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="h4 mb-0">#<?= $index + 1 ?></div>
                                        <div class="small text-muted">Rank</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Chart View -->
                <div id="chart-view" class="view-content" style="display:none;">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="userActivityChart"></canvas>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="moduleActivityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Heatmap View -->
                <div id="heatmap-view" class="view-content" style="display:none;">
                    <div class="heatmap-title fw-bold mb-3">Activity Heatmap – Hourly Distribution</div>
                    <div class="heatmap-grid" id="heatmap-grid">
                        <?php
                        $hourly_data = array_fill(0, 24, 0);
                        while ($row = $hourly_result->fetch_assoc()) {
                            $hourly_data[$row['hour']] = $row['count'];
                        }
                        $max_hourly = max($hourly_data) ?: 1;
                        foreach ($hourly_data as $hour => $count):
                            $intensity = $count / $max_hourly;
                            $color = $intensity > 0.75 ? '#e74a3b' : ($intensity > 0.5 ? '#f6c23e' : ($intensity > 0.25 ? '#36b9cc' : '#e9ecef'));
                            $textColor = $intensity > 0.25 ? 'white' : '#858796';
                            ?>
                            <div class="heatmap-cell" style="background:<?= $color ?>;color:<?= $textColor ?>">
                                <div><?= $hour % 12 == 0 ? 12 : $hour % 12 ?><?= $hour < 12 ? 'AM' : 'PM' ?></div>
                                <small><?= $count ?></small>
                                <div class="heatmap-tooltip"><?= $count ?> activities</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Table -->
            <div class="table-container">
                <table id="activityTable" class="table table-hover responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()):
                            $logDate = strtotime($row['created_at']);
                            $isNew = (time() - $logDate) <= 3600;
                            $label = getModuleLabel($row['object_type']);
                            $badgeColor = ['invoice' => 'primary', 'client' => 'success', 'service' => 'warning', 'quotation' => 'info', 'comments' => 'secondary'][strtolower($label)] ?? 'info';
                            ?>
                            <tr>
                                <td class="text-muted"><?= $row['log_id'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $imgPath = 'Uploads/staff_images/' . basename($row['user_pic'] ?? '');
                                        if (!empty($row['user_pic']) && file_exists($imgPath)): ?>
                                            <img src="<?= $imgPath ?>" class="rounded-circle me-2"
                                                style="width:32px;height:32px;object-fit:cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2"
                                                style="width:32px;height:32px;font-size:0.75rem;">
                                                <?= getInitials($row['user_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fw-bold"><?= htmlspecialchars($row['user_name'] ?? 'System') ?></div>
                                    </div>
                                </td>
                                <td><span
                                        class="badge badge-category bg-<?= $badgeColor ?> text-white"><?= htmlspecialchars($label) ?></span>
                                </td>
                                <td style="max-width:400px;white-space:normal;font-size:0.9rem;<?= strtoupper($row['action'] ?? '') === 'DELETE' ? 'color:#dc3545;font-weight:600;' : '' ?>">
                                    <?= htmlspecialchars($row['description']) ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="fw-bold"><?= date('M d, Y', $logDate) ?></span>
                                        <small class="text-muted"><?= date('h:i A', $logDate) ?></small>
                                        <?php if ($isNew): ?><span class="badge bg-success mt-1"
                                                style="font-size:10px;">RECENT</span><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#clientSelect').select2({
                theme: 'bootstrap-5',
                placeholder: 'Search by name, business or ID…',
                allowClear: true,
                width: '100%',
                matcher: function (params, data) {
                    if (!params.term || params.term.trim() === '') return data;
                    var term = params.term.toLowerCase();
                    var $opt = $(data.element);
                    var name = ($opt.data('name') || '').toString();
                    var bus  = ($opt.data('bus')  || '').toString();
                    var mid  = ($opt.data('mid')  || '').toString();
                    if (name.indexOf(term) > -1 || bus.indexOf(term) > -1 || mid.indexOf(term) > -1) return data;
                    return null;
                }
            });

            $('#activityTable').DataTable({
                order: [[4, "desc"]],
                pageLength: 25,
                language: { searchPlaceholder: "Search logs..." },
                // Length selector + export buttons on the left, search on the right
                dom: "<'row mb-2 align-items-center'<'col-sm-8 d-flex align-items-center gap-2'lB><'col-sm-4'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
                buttons: [
                    {
                        extend: 'csvHtml5',
                        text: '<i class="ti ti-file-text"></i> CSV',
                        className: 'btn-export-csv',
                        title: 'activity_logs_' + new Date().toISOString().slice(0, 10),
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="ti ti-file-spreadsheet"></i> Excel',
                        className: 'btn-export-excel',
                        title: 'activity_logs_' + new Date().toISOString().slice(0, 10),
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'print',
                        text: '<i class="ti ti-printer"></i> Print',
                        className: 'btn-export-print',
                        title: 'Activity Logs',
                        exportOptions: { columns: ':visible' }
                    }
                ]
            });

            // Counter animation
            $('.animated-counter').each(function () {
                const $this = $(this);
                const countTo = parseInt($this.text().replace(/,/g, ''));
                $this.text('0');
                $({ countNum: 0 }).animate({ countNum: countTo }, {
                    duration: 1500,
                    easing: 'swing',
                    step: function () { $this.text(Math.floor(this.countNum).toLocaleString()); },
                    complete: function () { $this.text(countTo.toLocaleString()); }
                });
            });
        });

        function showView(view) {
            $('.view-content').hide();
            $('#' + view + '-view').show();
            $('.btn-group .btn').removeClass('active');
            $(`.btn-group .btn:contains('${view.charAt(0).toUpperCase() + view.slice(1)}')`).addClass('active');

            if (view === 'chart') initCharts();
        }

        let userChart = null, moduleChart = null;
        function initCharts() {
            // User Activity Doughnut
            const userCtx = document.getElementById('userActivityChart');
            if (userCtx && !userChart) {
                const userLabels = <?= json_encode(array_column($user_activity_data, 'user_name')) ?>;
                const userData = <?= json_encode(array_column($user_activity_data, 'activity_count')) ?>;
                userChart = new Chart(userCtx, {
                    type: 'doughnut',
                    data: { labels: userLabels, datasets: [{ data: userData, backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1', '#f66d9b', '#20c997', '#fd7e14', '#6c757d'] }] },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' }, title: { display: true, text: 'Top Users by Activity' } } }
                });
            }

            // Module Activity Bar
            const moduleCtx = document.getElementById('moduleActivityChart');
            if (moduleCtx && !moduleChart) {
                const moduleLabels = <?= json_encode(array_map('getModuleLabel', array_column($module_result->fetch_all(MYSQLI_ASSOC), 'object_type'))) ?>;
                const moduleData = <?= json_encode(array_column($module_result->fetch_all(MYSQLI_ASSOC), 'count')) ?>;
                moduleChart = new Chart(moduleCtx, {
                    type: 'bar',
                    data: { labels: moduleLabels, datasets: [{ label: 'Activities', data: moduleData, backgroundColor: 'rgba(78,115,223,0.8)' }] },
                    options: { responsive: true, plugins: { legend: { display: false }, title: { display: true, text: 'Activity by Module' } }, scales: { y: { beginAtZero: true } } }
                });
            }
        }
    </script>
</body>

</html>