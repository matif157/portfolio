<?php
include 'includes/db.php';
include 'includes/auth.php';
error_reporting(E_ALL);
ini_set('display_errors', 0); // Log errors but don't display

// Validate user ID
if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = intval($_GET['id']);
} else {
    $user_id = $_SESSION['user_id'] ?? 0;
}

if ($user_id <= 0) {
    die('<div class="alert alert-danger">Invalid user ID.</div>');
}

// Function to get user initials
function getInitials($name) {
    if (empty($name)) return '??';
    $words = explode(' ', trim($name));
    $initials = '';
    $count = 0;
    foreach ($words as $word) {
        if (!empty($word) && $count < 2) { // Limit to 2 initials
            $initials .= strtoupper(substr($word, 0, 1));
            $count++;
        }
    }
    return $initials ?: '??';
}

// ===== FETCH USER INFORMATION =====
$user_query = $con->prepare("SELECT * FROM users WHERE user_id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    die('<div class="alert alert-danger">User not found.</div>');
}

// Safe defaults
$user_name     = $user_data['user_name']     ?? 'Unknown User';
$user_email    = $user_data['user_email']    ?? 'N/A';
$user_desig    = $user_data['user_desig']    ?? 'N/A';
$user_pic      = $user_data['user_pic']      ?? 'default.png';
$user_phone    = $user_data['user_phone']    ?? 'N/A';
$user_initials = getInitials($user_name);

// Helper function for safe queries
function safeQuery($con, $sql, $user_id) {
    $sql = str_replace('?', intval($user_id), $sql); // Temporary fix for mixing prepared/raw
    $result = mysqli_query($con, $sql);
    if (!$result) {
        error_log("Query failed: " . mysqli_error($con));
        return false;
    }
    return $result;
}

// ===== TOTAL REVENUE STATISTICS =====
$paid_revenue = mysqli_fetch_assoc(safeQuery($con, "SELECT COALESCE(SUM(total_amount), 0) as total FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 3", $user_id))['total'] ?? 0;
$unpaid_revenue = mysqli_fetch_assoc(safeQuery($con, "SELECT COALESCE(SUM(balance), 0) as total FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 1", $user_id))['total'] ?? 0;
$partial_revenue = mysqli_fetch_assoc(safeQuery($con, "SELECT COALESCE(SUM(balance), 0) as total FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 2", $user_id))['total'] ?? 0;
$total_revenue = $paid_revenue + $unpaid_revenue + $partial_revenue;

// ===== CREATED INVOICES BY USER =====
$created_invoices_paid    = mysqli_num_rows(safeQuery($con, "SELECT invoice_id FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 3", $user_id));
$created_invoices_unpaid  = mysqli_num_rows(safeQuery($con, "SELECT invoice_id FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 1", $user_id));
$created_invoices_partial = mysqli_num_rows(safeQuery($con, "SELECT invoice_id FROM billing_invoice WHERE service_provider_id = $user_id AND invoice_status = 2", $user_id));
$total_created_invoices   = $created_invoices_paid + $created_invoices_unpaid + $created_invoices_partial;

// Recent 3 Invoices
$recent_invoices = safeQuery($con, "
    SELECT i.invoice_id, i.total_amount, i.paid_amount, i.balance, i.invoice_status, COALESCE(m.bus_name, 'N/A') as bus_name
    FROM billing_invoice i
    LEFT JOIN members m ON i.mem_id = m.member_id
    WHERE i.service_provider_id = $user_id
    ORDER BY i.invoice_id DESC LIMIT 3
", $user_id);

// ===== PROJECTS BY USER =====
$user_projects_total  = mysqli_num_rows(safeQuery($con, "SELECT order_id FROM projects WHERE assign_to = $user_id", $user_id));
$user_projects_open   = mysqli_num_rows(safeQuery($con, "SELECT order_id FROM projects WHERE assign_to = $user_id AND project_status = 'open'", $user_id));
$user_projects_closed = mysqli_num_rows(safeQuery($con, "SELECT order_id FROM projects WHERE assign_to = $user_id AND project_status = 'completed'", $user_id));

// Project Linked Invoices
$project_invoices_paid    = mysqli_fetch_assoc(safeQuery($con, "
    SELECT COALESCE(SUM(i.total_amount), 0) as total FROM billing_invoice i
    JOIN projects p ON FIND_IN_SET(i.invoice_id, p.project_invoices)
    WHERE p.assign_to = $user_id AND i.invoice_status = 3
", $user_id))['total'] ?? 0;
$project_invoices_unpaid  = mysqli_fetch_assoc(safeQuery($con, "
    SELECT COALESCE(SUM(i.balance), 0) as total FROM billing_invoice i
    JOIN projects p ON FIND_IN_SET(i.invoice_id, p.project_invoices)
    WHERE p.assign_to = $user_id AND i.invoice_status = 1
", $user_id))['total'] ?? 0;
$project_invoices_partial = mysqli_fetch_assoc(safeQuery($con, "
    SELECT COALESCE(SUM(i.balance), 0) as total FROM billing_invoice i
    JOIN projects p ON FIND_IN_SET(i.invoice_id, p.project_invoices)
    WHERE p.assign_to = $user_id AND i.invoice_status = 2
", $user_id))['total'] ?? 0;
$project_linked_total = $project_invoices_paid + $project_invoices_unpaid + $project_invoices_partial;

// Recent 3 Project Comments
$project_comments = safeQuery($con, "
    SELECT COALESCE(pc.comment, 'No comment') as comment, pc.created_at, p.order_id 
    FROM project_comments pc
    JOIN projects p ON pc.project_id = p.order_id
    WHERE p.assign_to = $user_id
    ORDER BY pc.created_at DESC LIMIT 3
", $user_id);

// ===== TOTAL COMMENTS BY USER =====
$total_comments = mysqli_num_rows(safeQuery($con, "SELECT bc.comment_id FROM billing_comments bc WHERE bc.user_id = $user_id", $user_id));

// Recent 3 Billing Comments
$recent_comments = safeQuery($con, "
    SELECT COALESCE(bc.comment_detail, 'No comment') as comment_detail, bc.comment_time 
    FROM billing_comments bc
    WHERE bc.user_id = $user_id
    ORDER BY bc.comment_time DESC LIMIT 3
", $user_id);

// ===== CLIENTS ADDED BY USER =====
$clients_added = mysqli_num_rows(safeQuery($con, "SELECT member_id FROM members WHERE user_id = $user_id", $user_id));

// Recent 3 Clients Added
$recent_clients_added = safeQuery($con, "
    SELECT member_id, 
           COALESCE(CONCAT(name, ' ', lname), 'Unnamed Client') as client_name, 
           COALESCE(bus_name, 'N/A') as bus_name, 
           date_added
    FROM members
    WHERE user_id = $user_id
    ORDER BY date_added DESC LIMIT 3
", $user_id);

// ===== USER ACTIVITIES AND RANK =====
$total_activities = mysqli_num_rows(safeQuery($con, "SELECT log_id FROM activity_logs WHERE user_id = $user_id", $user_id));

$rank_query = safeQuery($con, "
    SELECT u.user_id, COUNT(i.invoice_id) as invoice_count
    FROM users u
    LEFT JOIN billing_invoice i ON u.user_id = i.service_provider_id
    GROUP BY u.user_id
    ORDER BY invoice_count DESC
", $user_id);
$rank = 0;
$rank_position = 'N/A';
if ($rank_query) {
    while ($rank_row = mysqli_fetch_assoc($rank_query)) {
        $rank++;
        if ($rank_row['user_id'] == $user_id) {
            $rank_position = $rank;
            break;
        }
    }
}

// ===== TOP 3 FREQUENT CLIENTS =====
$top_clients = safeQuery($con, "
    SELECT COALESCE(m.bus_name, 'Unknown Client') as client_name, 
           COUNT(i.invoice_id) as frequency, 
           COALESCE(SUM(i.total_amount),0) as total_amount
    FROM billing_invoice i
    LEFT JOIN members m ON i.mem_id = m.member_id
    WHERE i.service_provider_id = $user_id
    GROUP BY m.bus_name
    ORDER BY frequency DESC LIMIT 3
", $user_id);

// ===== TOP CLIENT TYPES =====
$top_client_types = safeQuery($con, "
    SELECT COALESCE(m.category, 'Uncategorized') as category, 
           COUNT(i.invoice_id) as frequency, 
           COALESCE(SUM(i.total_amount),0) as total_amount
    FROM billing_invoice i
    LEFT JOIN members m ON i.mem_id = m.member_id
    WHERE i.service_provider_id = $user_id
    GROUP BY m.category
    ORDER BY frequency DESC LIMIT 3
", $user_id);

// Data for Client/Business Distribution Chart
$services_for_chart = safeQuery($con, "
    SELECT COALESCE(m.bus_name, 'Unknown Client') as label_name, COUNT(i.invoice_id) as frequency
    FROM billing_invoice i
    LEFT JOIN members m ON i.mem_id = m.member_id
    WHERE i.service_provider_id = $user_id
    GROUP BY m.bus_name
    ORDER BY frequency DESC LIMIT 7
", $user_id);
$service_labels = [];
$service_data   = [];
if ($services_for_chart) {
    while ($s = mysqli_fetch_assoc($services_for_chart)) {
        $service_labels[] = $s['label_name'];
        $service_data[]   = (int)$s['frequency'];
    }
}

// Data for Client Types Chart
$client_types_chart = safeQuery($con, "
    SELECT COALESCE(m.category, 'Uncategorized') as client_type, COUNT(i.invoice_id) as frequency
    FROM billing_invoice i
    LEFT JOIN members m ON i.mem_id = m.member_id
    WHERE i.service_provider_id = $user_id
    GROUP BY m.category
    ORDER BY frequency DESC LIMIT 7
", $user_id);
$client_type_labels = [];
$client_type_data   = [];
if ($client_types_chart) {
    while ($ct = mysqli_fetch_assoc($client_types_chart)) {
        $client_type_labels[] = $ct['client_type'];
        $client_type_data[]   = (int)$ct['frequency'];
    }
}

// Profile image path validation
$profile_image_path = 'uploads/profile/' . $user_pic;
$profile_image_exists = !empty($user_pic) && $user_pic !== 'default.png' && file_exists(__DIR__ . '/' . $profile_image_path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Syed Professional Services - User Dashboard</title>
    <?php include "includes/links.php"; ?>
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Fixed spacing and alignment */
        .stat-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .stat-card .card-body {
            padding: 1.5rem;
        }
        .chart-card {
            height: 100%;
        }
        .chart-card .card-body {
            padding: 1.5rem;
            position: relative;
            height: 320px; /* Fixed height for charts */
        }
        .section-spacing {
            margin-bottom: 2.5rem;
        }
        .icon-box {
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 12px;
            font-size: 1.2rem;
        }
        /* Ensure equal height cards in rows */
        .row-equal-height {
            display: flex;
            flex-wrap: wrap;
        }
        .row-equal-height > [class*="col-"] {
            display: flex;
            flex-direction: column;
            margin-bottom: 1.5rem;
        }
        .row-equal-height .card {
            flex: 1;
        }
        /* Profile image styling */
        .profile-img-container {
            width: 120px;
            height: 120px;
            overflow: hidden;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        /* Table improvements */
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        .badge {
            font-weight: 500;
            padding: 0.5em 0.8em;
        }
        /* List group improvements */
        .list-group-item {
            border-left: none;
            border-right: none;
            padding: 1rem 1.25rem;
        }
        .list-group-item:first-child {
            border-top: none;
        }
        .list-group-item:last-child {
            border-bottom: none;
        }
        /* Chart canvas container */
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
    </style>
</head>
<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <!-- Page Header -->
            <div class="card card-body mb-4">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10"><i class="fas fa-user-tie me-2"></i>User Profile & Workflow Dashboard</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                                <li class="breadcrumb-item"><i class="fas fa-user me-1"></i>User Profile</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Header Card -->
            <div class="card mb-5">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-wrap">
                        <div class="flex-shrink-0 mb-3 mb-md-0">
                            <?php if ($profile_image_exists): ?>
                                <div class="profile-img-container">
                                    <img src="<?php echo htmlspecialchars($profile_image_path); ?>" alt="Profile">
                                </div>
                            <?php else: ?>
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:120px;height:120px;font-size:48px;">
                                    <?php echo htmlspecialchars($user_initials); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 ms-4">
                            <h3 class="mb-1"><i class="fas fa-id-badge text-primary me-2"></i><?php echo htmlspecialchars($user_name); ?></h3>
                            <p class="text-muted mb-1"><i class="fas fa-briefcase text-info me-2"></i><?php echo htmlspecialchars($user_desig); ?></p>
                            <p class="mb-1"><i class="fas fa-envelope text-secondary me-2"></i> <?php echo htmlspecialchars($user_email); ?></p>
                            <p class="mb-0"><i class="fas fa-phone text-success me-2"></i> <?php echo htmlspecialchars($user_phone); ?></p>
                        </div>
                        <div class="text-end ms-auto mt-3 mt-md-0">
                            <div class="d-inline-block text-center px-4 py-2 bg-light rounded">
                                <h5 class="mb-1 text-warning"><i class="fas fa-trophy me-2"></i>Rank #<?php echo $rank_position; ?></h5>
                                <p class="text-muted mb-0 small"><i class="fas fa-chart-line me-1"></i>Total Activities: <?php echo number_format($total_activities); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Statistics Cards -->
            <div class="row row-equal-height section-spacing">
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-success">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-success text-white">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($total_revenue); ?></h4>
                                    <small class="text-muted">Total Revenue</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-small">
                                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Paid: <?php echo number_format($paid_revenue); ?></small>
                                <small class="text-danger"><i class="fas fa-times-circle me-1"></i>Unpaid: <?php echo number_format($unpaid_revenue); ?></small>
                                <small class="text-warning"><i class="fas fa-adjust me-1"></i>Partial: <?php echo number_format($partial_revenue); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-primary">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-primary text-white">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($total_created_invoices); ?></h4>
                                    <small class="text-muted">Invoices Created</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-small">
                                <small class="text-success"><i class="fas fa-check me-1"></i>Paid: <?php echo number_format($created_invoices_paid); ?></small>
                                <small class="text-danger"><i class="fas fa-times me-1"></i>Unpaid: <?php echo number_format($created_invoices_unpaid); ?></small>
                                <small class="text-warning"><i class="fas fa-minus me-1"></i>Partial: <?php echo number_format($created_invoices_partial); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-info">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-info text-white">
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($user_projects_total); ?></h4>
                                    <small class="text-muted">Projects Assigned</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-small">
                                <small class="text-warning"><i class="fas fa-folder-open me-1"></i>Open: <?php echo number_format($user_projects_open); ?></small>
                                <small class="text-success"><i class="fas fa-check-double me-1"></i>Completed: <?php echo number_format($user_projects_closed); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-warning">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-warning text-white">
                                    <i class="fas fa-link"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($project_linked_total); ?></h4>
                                    <small class="text-muted">Project-Linked Revenue</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between text-small">
                                <small class="text-success"><i class="fas fa-check-circle me-1"></i>Paid: <?php echo number_format($project_invoices_paid); ?></small>
                                <small class="text-danger"><i class="fas fa-times-circle me-1"></i>Unpaid: <?php echo number_format($project_invoices_unpaid); ?></small>
                                <small class="text-warning"><i class="fas fa-adjust me-1"></i>Partial: <?php echo number_format($project_invoices_partial); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-secondary">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-secondary text-white">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($clients_added); ?></h4>
                                    <small class="text-muted">Clients Added</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6">
                    <div class="card stat-card border-start border-4 border-dark">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="icon-box bg-dark text-white">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0"><?php echo number_format($total_comments); ?></h4>
                                    <small class="text-muted">Total Comments</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity Section -->
            <div class="row row-equal-height section-spacing">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Recent Invoices</h5>
                            <span class="badge bg-light text-primary"><?php echo mysqli_num_rows($recent_invoices); ?> items</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Client</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">Balance</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_invoices && mysqli_num_rows($recent_invoices) > 0):
                                            while ($inv = mysqli_fetch_assoc($recent_invoices)): ?>
                                                <tr>
                                                    <td><span class="badge bg-light text-dark">#<?php echo $inv['invoice_id']; ?></span></td>
                                                    <td><i class="fas fa-building text-muted me-1 small"></i><?php echo htmlspecialchars($inv['bus_name']); ?></td>
                                                    <td class="text-end fw-bold"><?php echo number_format($inv['total_amount'], 2); ?></td>
                                                    <td class="text-end"><?php echo number_format($inv['balance'], 2); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($inv['invoice_status'] == 3) {
                                                            echo '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Paid</span>';
                                                        } elseif ($inv['invoice_status'] == 2) {
                                                            echo '<span class="badge bg-warning text-dark"><i class="fas fa-adjust me-1"></i>Partial</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Unpaid</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; 
                                            mysqli_data_seek($recent_invoices, 0);
                                        else: ?>
                                            <tr><td colspan="5" class="text-center text-muted py-4"><i class="fas fa-inbox me-2 fa-2x mb-2 d-block"></i>No recent invoices</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="row g-3 h-100">
                        <div class="col-12">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Recent Project Comments</h5>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php if ($project_comments && mysqli_num_rows($project_comments) > 0):
                                            while ($pc = mysqli_fetch_assoc($project_comments)): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                                    <div class="me-auto">
                                                        <i class="fas fa-comment-dots text-info me-2"></i>
                                                        <?php echo htmlspecialchars($pc['comment']); ?>
                                                    </div>
                                                    <small class="text-muted text-nowrap ms-2">
                                                        <i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($pc['created_at'])); ?>
                                                        <span class="badge bg-light text-dark ms-1">#<?php echo $pc['order_id']; ?></span>
                                                    </small>
                                                </li>
                                            <?php endwhile;
                                            mysqli_data_seek($project_comments, 0);
                                        else: ?>
                                            <li class="list-group-item text-center text-muted py-4"><i class="fas fa-comment-slash me-2 fa-2x mb-2 d-block"></i>No recent project comments</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card h-100">
                                <div class="card-header bg-secondary text-white">
                                    <h5 class="mb-0"><i class="fas fa-comment-alt me-2"></i>Recent Billing Comments</h5>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php if ($recent_comments && mysqli_num_rows($recent_comments) > 0):
                                            while ($rc = mysqli_fetch_assoc($recent_comments)): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <span><i class="fas fa-comment text-secondary me-2"></i><?php echo htmlspecialchars($rc['comment_detail']); ?></span>
                                                    <small class="text-muted text-nowrap ms-2"><i class="far fa-clock me-1"></i><?php echo date('M d, Y', strtotime($rc['comment_time'])); ?></small>
                                                </li>
                                            <?php endwhile;
                                            mysqli_data_seek($recent_comments, 0);
                                        else: ?>
                                            <li class="list-group-item text-center text-muted py-4"><i class="fas fa-comment-slash me-2 fa-2x mb-2 d-block"></i>No recent comments</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clients Section -->
            <div class="row row-equal-height section-spacing">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i>Recent Clients Added</h5>
                            <span class="badge bg-light text-success"><?php echo mysqli_num_rows($recent_clients_added); ?> items</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Business</th>
                                            <th>Date Added</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recent_clients_added && mysqli_num_rows($recent_clients_added) > 0):
                                            while ($ca = mysqli_fetch_assoc($recent_clients_added)): ?>
                                                <tr>
                                                    <td><span class="badge bg-light text-dark">#<?php echo $ca['member_id']; ?></span></td>
                                                    <td><i class="fas fa-user text-muted me-1 small"></i><?php echo htmlspecialchars($ca['client_name']); ?></td>
                                                    <td><i class="fas fa-building text-muted me-1 small"></i><?php echo htmlspecialchars($ca['bus_name']); ?></td>
                                                    <td><i class="far fa-calendar text-muted me-1 small"></i><?php echo date('M d, Y', strtotime($ca['date_added'])); ?></td>
                                                </tr>
                                            <?php endwhile;
                                        else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="fas fa-inbox me-2 fa-2x mb-2 d-block"></i>No clients added</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>

            <!-- Top Clients & Types + Charts -->
            <div class="row row-equal-height">
                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-star me-2"></i>Top 3 Most Frequent Clients</h5>
                        </div>
                        <div class="card-body">
                            <ol class="list-group list-group-numbered">
                                <?php if ($top_clients && mysqli_num_rows($top_clients) > 0):
                                    while ($tc = mysqli_fetch_assoc($top_clients)): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><i class="fas fa-building text-primary me-2"></i><?php echo htmlspecialchars($tc['client_name']); ?></div>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo $tc['frequency']; ?> invoices<br>
                                                <small><?php echo number_format($tc['total_amount']); ?></small>
                                            </span>
                                        </li>
                                    <?php endwhile;
                                else: ?>
                                    <li class="list-group-item"><i class="fas fa-exclamation-circle me-2"></i>No data available</li>
                                <?php endif; ?>
                            </ol>
                        </div>
                    </div>

                    <div class="card chart-card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Client Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="clientDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Top 3 Client Types</h5>
                        </div>
                        <div class="card-body">
                            <ol class="list-group list-group-numbered">
                                <?php if ($top_client_types && mysqli_num_rows($top_client_types) > 0):
                                    while ($ct = mysqli_fetch_assoc($top_client_types)): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="ms-2 me-auto">
                                                <div class="fw-bold"><i class="fas fa-tag text-info me-2"></i><?php echo htmlspecialchars($ct['category']); ?></div>
                                            </div>
                                            <span class="badge bg-info rounded-pill text-white">
                                                <?php echo $ct['frequency']; ?> invoices<br>
                                                <small><?php echo number_format($ct['total_amount']); ?></small>
                                            </span>
                                        </li>
                                    <?php endwhile;
                                else: ?>
                                    <li class="list-group-item"><i class="fas fa-exclamation-circle me-2"></i>No data available</li>
                                <?php endif; ?>
                            </ol>
                        </div>
                    </div>

                    <div class="card chart-card">
                        <div class="card-header bg-dark text-white">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Client Types Distribution</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="clientTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <!-- Charts Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Chart defaults
            Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Arial', sans-serif";
            Chart.defaults.color = '#666';
            
            // Common chart options
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.parsed;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((context.parsed / total) * 100).toFixed(1);
                                return label + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            };

            // Client/Business Distribution Chart
            const clientCtx = document.getElementById('clientDistributionChart');
            if (clientCtx) {
                new Chart(clientCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(!empty($service_labels) ? $service_labels : ['No Data']); ?>,
                        datasets: [{
                            data: <?php echo json_encode(!empty($service_data) ? $service_data : [1]); ?>,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: commonOptions
                });
            }

            // Client Types Distribution Chart
            const typesCtx = document.getElementById('clientTypesChart');
            if (typesCtx) {
                new Chart(typesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode(!empty($client_type_labels) ? $client_type_labels : ['No Data']); ?>,
                        datasets: [{
                            data: <?php echo json_encode(!empty($client_type_data) ? $client_type_data : [1]); ?>,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: commonOptions
                });
            }
        });
    </script>
</body>
</html>