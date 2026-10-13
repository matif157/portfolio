<?php
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
include "includes/db.php";
include "includes/auth.php";
$user_id = $_SESSION['user_id'];
function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $w)
        $initials .= strtoupper(substr($w, 0, 1));
    return $initials;
}
$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - To Be Renewed</title>
    <?php include "includes/links.php"; ?>
    <link rel="stylesheet" href="assets/datatable/jquery.dataTables.min.css">
    <link rel="stylesheet" href="assets/datatable/responsive.dataTables.min.css">
    <script src="assets/datatable/jquery-3.7.0.js"></script>
    <script src="assets/datatable/jquery.dataTables.min.js"></script>
    <script src="assets/datatable/dataTables.responsive.min.js"></script>
    <style>
        .badge-urgent {
            background: #dc3545 !important;
            color: #fff
        }

        .badge-pending {
            background: #ffc107 !important;
            color: #212529
        }

        .badge-renewed {
            background: #28a745 !important;
            color: #fff
        }
    </style>
</head>

<body class="theme-3">
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>
    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Renewals – To Be Renewed</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Products</a></li>
                                <li class="breadcrumb-item">Upcoming</li>
                            </ul>
                        </div>
                        <div class="col"></div>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>To Be Renewed (next 30 days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="renewalTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Member Type</th>
                                            <th>Membership</th>
                                            <th>Assigned To</th>
                                            <th>Billing Cycle</th>
                                            <th>Start Date</th>
                                            <th>Expiry Date</th>
                                            <th>Days Left</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $today = date('Y-m-d');
                                        $next30 = date('Y-m-d', strtotime('+30 days'));
                                        // Modified SQL query to include assigned user information
                                        $sql = "SELECT o.order_id,o.mem_id,o.bil_cyc_months,o.start_date,o.exp_date,
                                        m.name AS client_name,m.bus_name,mt.type_name,ms.membership_name,
                                        u.user_name AS assigned_to_name, u.user_email AS assigned_to_email
                                        FROM orders_auto_renewed o
                                        JOIN members m ON o.mem_id=m.member_id
                                        JOIN member_type mt ON m.type=mt.type_id
                                        JOIN memberships ms ON o.prod_id=ms.membership_id
                                        LEFT JOIN users u ON o.assign_to = u.user_id
                                        WHERE o.order_status='1'
                                        AND o.exp_date BETWEEN '$today' AND '$next30'
                                        ORDER BY o.exp_date ASC";
                                        $res = mysqli_query($con, $sql);
                                        if (mysqli_num_rows($res)) {
                                            while ($r = mysqli_fetch_assoc($res)) {
                                                $days = (new DateTime())->diff(new DateTime($r['exp_date']))->days;
                                                if ($days <= 7) {
                                                    $status = 'Urgent';
                                                    $cls = 'badge-urgent';
                                                } elseif ($days <= 30) {
                                                    $status = 'Upcoming'; 
                                                    $cls = 'badge-pending';
                                                } else {
                                                    $status = 'Scheduled';
                                                    $cls = 'badge-renewed';
                                                }
  
                                                // Prepare assigned to information
                                                $assignedTo = !empty($r['assigned_to_name']) ?
                                                $r['assigned_to_name'] : '<span class="text-muted">Not assigned</span>';
                                                echo "<tr>
                                                <td><a href='client_details.php?id={$r['mem_id']}'>{$r['client_name']}</a><br><small class='text-muted'>{$r['bus_name']}</small></td>
                                                <td>{$r['type_name']}</td>
                                                <td>{$r['membership_name']}</td>
                                                <td>{$assignedTo}</td>
                                                <td>{$r['bil_cyc_months']} months</td>
                                                <td>" . date('m/d/y', strtotime($r['start_date'])) . "</td>
                                                <td>" . date('m/d/y', strtotime($r['exp_date'])) . "</td>
                                                <td>{$days} days</td>
                                                <td><span class='badge {$cls}'>{$status}</span></td>
                                                </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='9' class='text-center'>No renewals due in next 30 days</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
    <script>
        new simpleDatatables.DataTable(document.getElementById('renewalTable'), { perPage: 25, searchable: true });
    </script>
</body>

</html>