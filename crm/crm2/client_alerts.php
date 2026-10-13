<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
 
$user_id = $_SESSION['user_id'];

function getInitials($name)
{
   $words = explode(' ', $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return $initials;
}


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>


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
         <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                
               <h5 class="mb-0">Client Alerts</h5>
               <?php
               $alerts_result = mysqli_query($con,
                  "SELECT w.id, w.member_id, w.warning, w.created_at, u.user_login, m.status AS member_status
                   FROM client_warnings w
                   LEFT JOIN users u ON u.user_id = w.created_by
                   LEFT JOIN members m ON m.member_id = w.member_id
                   WHERE w.is_active = 1
                   ORDER BY w.id DESC"
               );
               $alerts = [];
               while ($row = mysqli_fetch_assoc($alerts_result)) {
                  $alerts[] = $row;
               }
               ?>
               <span class="badge bg-warning text-dark"><?= count($alerts) ?></span>
            </div>
            <div class="card-body">
               <?php if (empty($alerts)): ?>
                  <div class="alert alert-secondary mb-0">
                     <i class="fas fa-check-circle me-2"></i>No active client alerts.
                  </div>
               <?php else: ?>
                  <div class="table-responsive">
                     <table class="table datatable1" id="tblClientAlerts" style="width:100%;">
                        <thead>
                           <tr>
                              <th>Alert #</th>
                              <th>Client ID</th>
                              <th>Alert Message</th>
                              <th>Created By</th>
                              <th>Date</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($alerts as $alert): ?>
                              <tr>
                                 <td>
                                    <span class="badge bg-warning text-dark fw-bold">
                                       #<?= htmlspecialchars($alert['id']) ?>
                                    </span>
                                 </td>
                                 <td>
                                    <a href="client_details.php?id=<?= htmlspecialchars($alert['member_id']) ?>&status=<?= htmlspecialchars($alert['member_status'] ?? '') ?>" target="_blank" class="fw-semibold">
                                       <?= htmlspecialchars($alert['member_id']) ?>
                                    </a>
                                 </td>
                                 <td><?= nl2br(htmlspecialchars($alert['warning'])) ?></td>
                                 <td><?= htmlspecialchars($alert['user_login'] ?? '—') ?></td>
                                 <td data-order="<?= htmlspecialchars($alert['created_at']) ?>">
                                    <?= htmlspecialchars($alert['created_at']) ?>
                                 </td>
                              </tr>
                           <?php endforeach; ?>
                        </tbody>
                     </table>
                  </div>
               <?php endif; ?>
            </div>
         </div>

         <script>
            document.addEventListener('DOMContentLoaded', function () {
               var tbl = document.getElementById('tblClientAlerts');
               if (tbl && typeof simpleDatatables !== 'undefined') {
                  new simpleDatatables.DataTable(tbl, {
                     perPage: 10,
                     perPageSelect: [5, 10, 25, 50],
                     columns: [
                        { select: 0, width: '80px' },
                        { select: 1, width: '100px' },
                        { select: 3, width: '130px' },
                        { select: 4, width: '160px' }
                     ]
                  });
               }
            });
         </script>
      </div>
   </div>
   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
</body>

</html>