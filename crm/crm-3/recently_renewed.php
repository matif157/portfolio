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
    <title>Syed Professional Services - Recently Renewed</title>
    <?php include "includes/links.php"; ?>
    <link rel="stylesheet" href="assets/datatable/jquery.dataTables.min.css">
    <link rel="stylesheet" href="assets/datatable/responsive.dataTables.min.css">
    <script src="assets/datatable/jquery-3.7.0.js"></script>
    <script src="assets/datatable/jquery.dataTables.min.js"></script>
    <script src="assets/datatable/dataTables.responsive.min.js"></script>
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
                                <h4 class="m-b-10">Renewals – Recently Renewed</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Products</a></li>
                                <li class="breadcrumb-item">Renewed Recently</li>
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
                            <h5>Recently Renewed (last 30 days)</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="renewedTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Member Type</th>
                                            <th>Products</th>
                                            <th>Assigned To</th>
                                            <th>Billing Cycle</th>
                                            <th>Previous Period</th>
                                            <th>New Period</th>
                                            <th>Renewed On</th>
                                            <th>Renewed By</th>
                                        </tr> 
                                    </thead>
                                    <tbody>
                                        <?php
                                        $last30 = date('Y-m-d', strtotime('-30 days'));
                                        $sql = "SELECT ar.*,m.name AS client_name,m.bus_name,mt.type_name,ms.membership_name,u.user_name AS renewed_by_name, us.user_name AS assigned_to_name
FROM orders_auto_renewed ar
JOIN members m ON ar.mem_id=m.member_id
JOIN member_type mt ON m.type=mt.type_id
JOIN memberships ms ON ar.prod_id=ms.membership_id
JOIN users u ON ar.user_id=u.user_id
LEFT JOIN users us ON ar.assign_to = us.user_id
WHERE ar.date_added >= '$last30'
ORDER BY ar.date_added DESC"; 
                                        $res = mysqli_query($con, $sql);
                                        if (mysqli_num_rows($res)) {
                                            while ($r = mysqli_fetch_assoc($res)) {
                                                $start = new DateTime($r['start_date']);
                                                $exp = new DateTime($r['exp_date']);
                                                $months = $start->diff($exp)->m + ($start->diff($exp)->y * 12);
                                                $prevStart = (clone $start)->modify("-$months months");
                                                $prevEnd = (clone $exp)->modify("-$months months");
                                                echo "<tr>
            <td><a href='client_details.php?id={$r['mem_id']}'>{$r['client_name']}</a><br><small class='text-muted'>{$r['bus_name']}</small></td>
            <td>{$r['type_name']}</td>
            <td>{$r['membership_name']}</td>
            <td>" . (!empty($r['assigned_to_name']) ? $r['assigned_to_name'] : '<span class="text-muted">Not assigned</span>') . "</td>
            <td>{$r['bil_cyc_months']} Days</td>
            <td>{$prevStart->format('n/j/Y')} – {$prevEnd->format('n/j/Y')}</td>
            <td>{$start->format('n/j/Y')} - {$exp->format('n/j/Y')}</td>
            <td>" . date('n/j/Y', strtotime($r['date_added'])) . "</td>
            <td>{$r['renewed_by_name']}</td>
          </tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='9' class='text-center'>No renewals processed in last 30 days</td></tr>";
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
        new simpleDatatables.DataTable(document.getElementById('renewedTable'), { perPage: 25, searchable: true });
    </script>
</body>

</html>