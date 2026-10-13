<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";

$user_id   = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Make sure the table exists.
mysqli_query($con, "CREATE TABLE IF NOT EXISTS ach_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    total_payment DECIMAL(12,2) NOT NULL DEFAULT 0,
    initiated_at DATETIME NULL,
    collection_at DATETIME NULL,
    bank_name VARCHAR(150) NULL,
    note TEXT NULL,
    status ENUM('view','collected','cancelled') NOT NULL DEFAULT 'view',
    collected_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY member_id (member_id),
    KEY status (status)
)");

// ── AJAX endpoint ───────────────────────────────────────────────
// The client profile (client_details.php) POSTs add/collect/cancel actions
// here. Respond with a bare 'OK' (or an error string) and stop before any
// HTML is emitted. Buffer first so stray include output can't corrupt the body.
if (($_POST['action'] ?? '') !== '') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    ob_start();

    $action = $_POST['action'];
    $id     = (int) ($_POST['id'] ?? 0);
    $resp   = '';
    $code   = 200;

    try {
        switch ($action) {

            case 'save':
                $mid   = (int) ($_POST['member_id'] ?? 0);
                $total = (float) ($_POST['total_payment'] ?? 0);
                $init  = trim($_POST['initiated_at'] ?? '');
                $coll  = trim($_POST['collection_at'] ?? '');
                $bank  = trim($_POST['bank_name'] ?? '');
                $note  = trim($_POST['note'] ?? '');

                if (!$mid)       throw new Exception('member_id missing');
                if ($total <= 0) throw new Exception('Total payment is required');
                if (!$init)      throw new Exception('Payment initiated date is required');
                if (!$coll)      throw new Exception('Collection date is required');

                // datetime-local sends "YYYY-MM-DDTHH:MM" -> MySQL DATETIME.
                $init = str_replace('T', ' ', $init);
                $coll = str_replace('T', ' ', $coll);
                if (strlen($init) === 16) $init .= ':00';
                if (strlen($coll) === 16) $coll .= ':00';

                $stmt = $con->prepare("INSERT INTO ach_alerts
                    (member_id, total_payment, initiated_at, collection_at, bank_name, note, status, created_by)
                    VALUES (?,?,?,?,?,?, 'view', ?)");
                $stmt->bind_param('idssssi', $mid, $total, $init, $coll, $bank, $note, $user_id);
                $stmt->execute();
                $newId = $stmt->insert_id;

                logActivity($con, $user_id, 'CREATE', 'ach_alerts', $newId, "ACH alert added for member #{$mid} amount {$total}");
                $resp = 'OK';
                break;

            case 'collect':
            case 'cancel':
                $newStatus = $action === 'collect' ? 'collected' : 'cancelled';

                $chk = $con->prepare("SELECT id FROM ach_alerts WHERE id=?");
                $chk->bind_param('i', $id);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) throw new Exception('Alert not found');

                $stmt = $con->prepare("UPDATE ach_alerts SET status=?, collected_at=NOW() WHERE id=?");
                $stmt->bind_param('si', $newStatus, $id);
                $stmt->execute();

                logActivity($con, $user_id, 'UPDATE', 'ach_alerts', $id, "ACH alert #{$id} marked {$newStatus}");
                $resp = 'OK';
                break;

            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $code = 400;
        $resp = $e->getMessage();
    }

    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $resp;
    exit;
}

// Optional status filter.
$filter = $_GET['status'] ?? '';
$where  = '';
if (in_array($filter, ['view', 'collected', 'cancelled'], true)) {
    $where = "WHERE a.status = '" . mysqli_real_escape_string($con, $filter) . "'";
}

$sql = "SELECT a.*, m.bus_name, m.name, m.lname
        FROM ach_alerts a
        LEFT JOIN members m ON m.member_id = a.member_id
        $where
        ORDER BY a.id DESC";
$res = mysqli_query($con, $sql);

$rows = [];
$sum_total = 0;
$sum_pending = 0;
$sum_collected = 0;
while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
    $sum_total += (float) $r['total_payment'];
    if ($r['status'] === 'view')      $sum_pending   += (float) $r['total_payment'];
    if ($r['status'] === 'collected') $sum_collected += (float) $r['total_payment'];
}

$busLabel = function ($r) {
    $bus = trim($r['bus_name'] ?? '');
    if ($bus !== '') return $bus;
    $person = trim(($r['name'] ?? '') . ' ' . ($r['lname'] ?? ''));
    return $person !== '' ? $person : '—';
};

$fmtDt = function ($v) {
    return (!empty($v) && $v !== '0000-00-00 00:00:00') ? date('M d, Y g:i A', strtotime($v)) : '—';
};

// ── CSV export of the current (filtered) result set ─────────────
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ach_payments_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Alert #', 'Client ID', 'Business Name', 'Total Amount', 'Initiated', 'Collection', 'Submitted/Collected Date', 'Bank', 'Status', 'Note']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['member_id'],
            $busLabel($r),
            number_format((float) $r['total_payment'], 2, '.', ''),
            $fmtDt($r['initiated_at']),
            $fmtDt($r['collection_at']),
            $fmtDt($r['collected_at']),
            $r['bank_name'],
            ucfirst($r['status']),
            $r['note'],
        ]);
    }
    fclose($out);
    exit;
}

$statusBadge = ['view' => 'bg-warning text-dark', 'collected' => 'bg-success', 'cancelled' => 'bg-secondary'];
?>
<!DOCTYPE html>
<html lang="en" dir="">

<head>
   <title>ACH Payment Collector — Syed Professional Services</title>
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

         <div class="row g-3 mb-3">
            <div class="col-md-4">
               <div class="card text-bg-primary h-100"><div class="card-body">
                  <div class="small text-uppercase">Total (All)</div>
                  <h4 class="mb-0">$<?= number_format($sum_total, 2) ?></h4>
               </div></div>
            </div>
            <div class="col-md-4">
               <div class="card text-bg-warning h-100"><div class="card-body">
                  <div class="small text-uppercase">Pending</div>
                  <h4 class="mb-0">$<?= number_format($sum_pending, 2) ?></h4>
               </div></div>
            </div>
            <div class="col-md-4">
               <div class="card text-bg-success h-100"><div class="card-body">
                  <div class="small text-uppercase">Collected</div>
                  <h4 class="mb-0">$<?= number_format($sum_collected, 2) ?></h4>
               </div></div>
            </div>
         </div>

         <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
               <h5 class="mb-0"><i class="fas fa-university me-2"></i>ACH Payment Collector</h5>
               <span class="badge bg-primary"><?= count($rows) ?></span>

               <div class="ms-auto d-flex align-items-center gap-2">
                  <form method="get" class="d-flex align-items-center gap-2 mb-0">
                     <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="view"      <?= $filter === 'view' ? 'selected' : '' ?>>Pending</option>
                        <option value="collected" <?= $filter === 'collected' ? 'selected' : '' ?>>Collected</option>
                        <option value="cancelled" <?= $filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                     </select>
                  </form>
                  <a href="?export=csv<?= $filter ? '&status=' . urlencode($filter) : '' ?>" class="btn btn-sm btn-success">
                     <i class="fas fa-file-csv me-1"></i> Generate Report
                  </a>
               </div>
            </div>

            <div class="card-body">
               <?php if (empty($rows)): ?>
                  <div class="alert alert-secondary mb-0">
                     <i class="fas fa-info-circle me-2"></i>No ACH payments stored yet.
                  </div>
               <?php else: ?>
                  <div class="table-responsive">
                     <table class="table datatable1" id="tblAch" style="width:100%;">
                        <thead>
                           <tr>
                              <th>#</th>
                              <th>Client ID</th>
                              <th>Business Name</th>
                              <th>Total Amount</th>
                              <th>Initiated</th>
                              <th>Collection</th>
                              <th>Submitted/Collected</th>
                              <th>Bank</th>
                              <th>Status</th>
                           </tr>
                        </thead>
                        <tbody>
                           <?php foreach ($rows as $r): ?>
                              <tr>
                                 <td>#<?= (int) $r['id'] ?></td>
                                 <td>
                                    <a href="client_details.php?id=<?= (int) $r['member_id'] ?>" target="_blank" class="fw-semibold">
                                       <?= (int) $r['member_id'] ?>
                                    </a>
                                 </td>
                                 <td><?= htmlspecialchars($busLabel($r)) ?></td>
                                 <td class="fw-bold">$<?= number_format((float) $r['total_payment'], 2) ?></td>
                                 <td data-order="<?= htmlspecialchars($r['initiated_at'] ?? '') ?>"><?= $fmtDt($r['initiated_at']) ?></td>
                                 <td data-order="<?= htmlspecialchars($r['collection_at'] ?? '') ?>"><?= $fmtDt($r['collection_at']) ?></td>
                                 <td data-order="<?= htmlspecialchars($r['collected_at'] ?? '') ?>"><?= $fmtDt($r['collected_at']) ?></td>
                                 <td><?= htmlspecialchars($r['bank_name'] ?: '—') ?></td>
                                 <td><span class="badge <?= $statusBadge[$r['status']] ?? 'bg-secondary' ?> text-capitalize"><?= htmlspecialchars($r['status']) ?></span></td>
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
               var tbl = document.getElementById('tblAch');
               if (tbl && typeof simpleDatatables !== 'undefined') {
                  new simpleDatatables.DataTable(tbl, {
                     perPage: 15,
                     perPageSelect: [10, 15, 25, 50, 100]
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
