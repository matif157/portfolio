<?php
require_once 'dompdf/autoload.inc.php';        // only if you later PDF-export
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
   <title>Syed Professional Services – Expired After Renewal</title>
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
                        <h4 class="m-b-10">Renewals – Expired After Renewal</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Products</a></li>
                        <li class="breadcrumb-item">Expired After Renewal</li>
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
                     <h5>Expired Again (latest renewal period over)</h5>
                  </div>
                  <div class="card-body">
                     <div class="table-responsive">
                        <table id="expiredRenewalTable" class="table table-hover">
                           <thead>
                              <tr>
                                 <th>Client</th>
                                 <th>Member Type</th>
                                 <th>Membership</th>
                                 <th>Assigned To</th>
                                 <th>Billing Cycle</th>
                                 <th>Renewed Period</th>
                                 <th>Expired On</th>
                                 <th>Renewed By</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $today = date('Y-m-d');

                              /* 1.  latest renewal row for every order
                                 2.  keep only those whose expiry ≤ today
                                 3.  pull client / membership names */
                              $sql = "
SELECT  latest.order_id,
        latest.mem_id,
        latest.exp_date,
        latest.bil_cyc_months,
        latest.date_added  AS renewed_on,
        u.user_name        AS renewed_by_name,
        us.user_name       AS assigned_to_name,
        m.name             AS client_name,
        m.bus_name,
        mt.type_name,
        ms.membership_name
FROM (
      SELECT oar.*,
             ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY exp_date DESC) AS rn
      FROM orders_auto_renewed oar
     ) latest
JOIN users u        ON u.user_id   = latest.user_id
LEFT JOIN users us  ON us.user_id  = latest.assign_to
JOIN members m      ON m.member_id = latest.mem_id
JOIN member_type mt ON mt.type_id  = m.type
JOIN memberships ms ON ms.membership_id = latest.prod_id
WHERE latest.rn = 1
  AND latest.exp_date <= DATE(NOW())
ORDER BY latest.exp_date DESC";
 
                              $res = mysqli_query($con, $sql);
                              if (mysqli_num_rows($res)) {
                                 while ($r = mysqli_fetch_assoc($res)) {
                                    $start = (new DateTime($r['exp_date']))
                                       ->sub(new DateInterval('P' . $r['bil_cyc_months'] . 'D'))
                                       ->format('n/j/Y');
                                    $end = (new DateTime($r['exp_date']))->format('n/j/Y');

                                    echo "<tr>
                              <td><a href='client_details.php?id={$r['mem_id']}'>{$r['client_name']}</a><br>
                                  <small class='text-muted'>{$r['bus_name']}</small></td>
                              <td>{$r['type_name']}</td>
                              <td>{$r['membership_name']}</td>
                              <td>" . (!empty($r['assigned_to_name']) ? $r['assigned_to_name'] : '<span class="text-muted">Not assigned</span>') . "</td>
                              <td>{$r['bil_cyc_months']} months</td>
                              <td>{$start} – {$end}</td>
                              <td>{$end}</td>
                              <td>{$r['renewed_by_name']}</td>
                             </tr>";
                                 }
                              } else {
                                 echo "<tr><td colspan='8' class='text-center'>No memberships have expired again after renewal.</td></tr>";
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
      new simpleDatatables.DataTable(document.getElementById('expiredRenewalTable'),
         { perPage: 25, searchable: true });
   </script>
</body>

</html>