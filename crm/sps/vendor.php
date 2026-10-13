<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_type = (string) ($_SESSION['user_type'] ?? '0');
$user_full_name = $_SESSION['fullname'] ?? 'Client User';
$user_email = $_SESSION['user_email'] ?? '';
$is_admin = in_array($user_type, ['1', 'admin'], true);

function getInitials($name)
{
   $words = explode(' ', trim((string) $name));
   $initials = '';
   foreach ($words as $word) {
      if ($word !== '') {
         $initials .= strtoupper(substr($word, 0, 1));
      }
   }
   return $initials ?: 'NA';
}

function invoiceStatusText($statusText, $statusId)
{
   if (!empty($statusText)) {
      return $statusText;
   }
   $map = [
      '1' => 'Not Paid',
      '2' => 'Partial Paid',
      '3' => 'Paid'
   ];
   $key = (string) $statusId;
   return $map[$key] ?? 'Unknown';
}

function invoiceBadgeClass($statusText)
{
   $s = strtolower((string) $statusText);
   if ($s === 'paid') {
      return 'status-paid';
   }
   if ($s === 'partial paid') {
      return 'status-partial';
   }
   return 'status-pending';
}

function maskCardNumber($raw)
{
   $digits = preg_replace('/\D+/', '', (string) $raw);
   if ($digits === '') {
      return 'N/A';
   }
   $last4 = substr($digits, -4);
   return '**** **** **** ' . $last4;
}

function formatPortalDate($date)
{
   if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
      return '-';
   }
   $ts = strtotime($date);
   if ($ts === false) {
      return '-';
   }
   return date('Y-m-d', $ts);
}

function computeProjectProgress($startDate, $endDate, $status)
{
   if (strtolower((string) $status) === 'completed') {
      return 100;
   }

   $start = strtotime((string) $startDate);
   $end = strtotime((string) $endDate);
   $now = time();

   if ($start && $end && $end > $start) {
      $total = $end - $start;
      $elapsed = $now - $start;
      $pct = (int) round(($elapsed / $total) * 100);
      if ($pct < 5) {
         $pct = 5;
      }
      if ($pct > 95) {
         $pct = 95;
      }
      return $pct;
   }

   return 35;
}

$requested_member_id = 0;
if (isset($_GET['client_id'])) {
   $requested_member_id = (int) $_GET['client_id'];
} elseif (isset($_GET['id'])) {
   $requested_member_id = (int) $_GET['id'];
} elseif (isset($_GET['member_id'])) {
   $requested_member_id = (int) $_GET['member_id'];
}

$member = null;
$member_id = 0;
$memberQuery = null;

if ($requested_member_id > 0) {
   if ($is_admin) {
      $memberQuery = mysqli_query($con, "SELECT * FROM members WHERE member_id='{$requested_member_id}' LIMIT 1");
   } else {
      $safeEmail = mysqli_real_escape_string($con, $user_email);
      $memberQuery = mysqli_query($con, "SELECT * FROM members WHERE member_id='{$requested_member_id}' AND (user_id='{$user_id}' OR prm_email='{$safeEmail}' OR sec_email='{$safeEmail}') LIMIT 1");
   }
}

if (!$memberQuery || mysqli_num_rows($memberQuery) === 0) {
   $memberQuery = mysqli_query($con, "SELECT * FROM members WHERE user_id='{$user_id}' ORDER BY member_id DESC LIMIT 1");
}

if ((!$memberQuery || mysqli_num_rows($memberQuery) === 0) && $user_email !== '') {
   $safeEmail = mysqli_real_escape_string($con, $user_email);
   $memberQuery = mysqli_query($con, "SELECT * FROM members WHERE prm_email='{$safeEmail}' OR sec_email='{$safeEmail}' ORDER BY member_id DESC LIMIT 1");
}

if ($memberQuery && mysqli_num_rows($memberQuery) > 0) {
   $member = mysqli_fetch_assoc($memberQuery);
   $member_id = (int) $member['member_id'];
}

$portal_notice = '';
if ($member_id <= 0) {
   $portal_notice = 'No linked client record found for this login. Ask admin to map this user to members.user_id.';
}

$user_initials = getInitials($user_full_name);
$member_name = $user_full_name;
$member_company = 'N/A';
$member_email = $user_email !== '' ? $user_email : 'N/A';
$member_phone = 'N/A';
$member_address = 'N/A';

if ($member) {
   $fullName = trim(($member['name'] ?? '') . ' ' . ($member['mname'] ?? '') . ' ' . ($member['lname'] ?? ''));
   if ($fullName !== '') {
      $member_name = $fullName;
   }
   $member_company = $member['bus_name'] ?: ($member['dba'] ?: 'N/A');
   $member_email = $member['prm_email'] ?: ($member['sec_email'] ?: $member_email);
   $member_phone = $member['cell_ph'] ?: ($member['phone'] ?: 'N/A');
   $street = $member['address'] ?: ($member['hstreet'] ?? '');
   $city = $member['hcity'] ?? '';
   $state = $member['hstate'] ?? '';
   $zip = $member['hzip'] ?? '';
   $member_address = trim($street . ', ' . $city . ', ' . $state . ' ' . $zip, ', ');
   if ($member_address === '') {
      $member_address = 'N/A';
   }
}

$invoices = [];
$projects = [];
$services = [];
$cards = [];
$bank_summary = ['active_banks' => 0, 'received_current_year' => 0];
$portal_stats = [
   'open_projects' => 0,
   'pending_invoices' => 0,
   'active_services' => 0,
   'new_comments' => 0
];

if ($member_id > 0) {
   $invoiceSql = "
      SELECT bi.invoice_id, bi.invoice_date, bi.due_date, bi.total_amount, bi.balance, bi.invoice_status,
             bis.status_text
      FROM billing_invoice bi
      LEFT JOIN billing_invoice_status bis ON bis.status_id = bi.invoice_status
      WHERE bi.mem_id = '{$member_id}'
      ORDER BY bi.invoice_id DESC
      LIMIT 10
   ";
   $invoiceRes = mysqli_query($con, $invoiceSql);
   if ($invoiceRes) {
      while ($row = mysqli_fetch_assoc($invoiceRes)) {
         $statusText = invoiceStatusText($row['status_text'] ?? '', $row['invoice_status'] ?? '');
         if (in_array(strtolower($statusText), ['not paid', 'partial paid'], true)) {
            $portal_stats['pending_invoices']++;
         }
         $invoices[] = [
            'invoice_no' => 'INV-' . $row['invoice_id'],
            'date' => formatPortalDate($row['invoice_date']),
            'due' => formatPortalDate($row['due_date']),
            'amount' => '$' . number_format((float) $row['total_amount'], 2),
            'status' => $statusText,
            'status_class' => invoiceBadgeClass($statusText)
         ];
      }
   }

   $projectSql = "
      SELECT p.order_id, p.project_title, p.project_description, p.start_date, p.exp_date, p.project_status,
             p.project_updated_at, p.prod_id, m.membership_name, u.user_name AS owner_name,
             (SELECT pc.comment FROM project_comments pc WHERE pc.project_id = p.order_id ORDER BY pc.created_at DESC LIMIT 1) AS latest_comment
      FROM projects p
      LEFT JOIN memberships m ON m.membership_id = p.prod_id
      LEFT JOIN users u ON u.user_id = p.assign_to
      WHERE p.mem_id = '{$member_id}'
      ORDER BY CASE WHEN p.project_status='open' THEN 0 ELSE 1 END, p.project_updated_at DESC, p.order_id DESC
      LIMIT 8
   ";
   $projectRes = mysqli_query($con, $projectSql);
   if ($projectRes) {
      while ($row = mysqli_fetch_assoc($projectRes)) {
         $progress = computeProjectProgress($row['start_date'], $row['exp_date'], $row['project_status']);
         if (strtolower((string) $row['project_status']) === 'open') {
            $portal_stats['open_projects']++;
         }
         $projects[] = [
            'order_id' => (int) $row['order_id'],
            'name' => $row['project_title'] ?: ('Project #' . $row['order_id']),
            'service' => $row['membership_name'] ?: 'General Service',
            'progress' => $progress,
            'owner' => $row['owner_name'] ?: 'Unassigned',
            'last_update' => $row['latest_comment'] ?: ($row['project_description'] ?: 'No recent comment'),
            'status' => ucfirst((string) $row['project_status'])
         ];
      }
   }

   $serviceSql = "
      SELECT s.id, s.member_id, s.membership_id, s.description, s.added_at,
             m.membership_name, u.user_login
      FROM member_services s
      JOIN memberships m ON m.membership_id = s.membership_id
      LEFT JOIN users u ON u.user_id = s.user_id
      WHERE s.member_id = '{$member_id}'
      ORDER BY s.added_at DESC
      LIMIT 12
   ";
   $serviceRes = mysqli_query($con, $serviceSql);
   if ($serviceRes) {
      while ($row = mysqli_fetch_assoc($serviceRes)) {
         $membership_id = (int) $row['membership_id'];
         $commentText = 'No project comment yet';
         $projectLink = '#';

         $commentRes = mysqli_query($con, "
            SELECT p.order_id,
                   (SELECT pc.comment FROM project_comments pc WHERE pc.project_id = p.order_id ORDER BY pc.created_at DESC LIMIT 1) AS latest_comment
            FROM projects p
            WHERE p.mem_id = '{$member_id}' AND p.prod_id = '{$membership_id}'
            ORDER BY p.project_updated_at DESC, p.order_id DESC
            LIMIT 1
         ");
         if ($commentRes && mysqli_num_rows($commentRes) > 0) {
            $commentRow = mysqli_fetch_assoc($commentRes);
            if (!empty($commentRow['latest_comment'])) {
               $commentText = $commentRow['latest_comment'];
            }
            if (!empty($commentRow['order_id'])) {
               $projectLink = 'project_details.php?id=' . (int) $commentRow['order_id'];
            }
         }

         $services[] = [
            'service' => $row['membership_name'],
            'record' => $row['description'] ?: ('Added on ' . formatPortalDate($row['added_at'])),
            'comments' => $commentText,
            'sharepoint' => $projectLink
         ];
      }
   }
   $portal_stats['active_services'] = count($services);

   $commentCountRes = mysqli_query($con, "
      SELECT COUNT(*) AS total
      FROM project_comments pc
      JOIN projects p ON p.order_id = pc.project_id
      WHERE p.mem_id = '{$member_id}'
      AND pc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
   ");
   if ($commentCountRes && mysqli_num_rows($commentCountRes) > 0) {
      $portal_stats['new_comments'] = (int) mysqli_fetch_assoc($commentCountRes)['total'];
   }

   $cardRes = mysqli_query($con, "
      SELECT bc.card_id, bc.card_onname, bc.card_number, bc.card_exp_month, bc.card_exp_year, bct.card_type_name
      FROM billing_card bc
      LEFT JOIN billing_card_type bct ON bct.card_type_id = bc.card_type
      WHERE bc.mem_id = '{$member_id}'
      ORDER BY bc.card_id DESC
      LIMIT 6
   ");
   if ($cardRes) {
      while ($row = mysqli_fetch_assoc($cardRes)) {
         $cards[] = [
            'type' => $row['card_type_name'] ?: 'Card',
            'name' => $row['card_onname'] ?: '-',
            'number' => maskCardNumber($row['card_number']),
            'exp' => (int) $row['card_exp_month'] . '/' . (int) $row['card_exp_year']
         ];
      }
   }

   $bankRes = mysqli_query($con, "SELECT COUNT(*) AS total FROM client_banks WHERE member_id = '{$member_id}' AND is_active = 1");
   if ($bankRes && mysqli_num_rows($bankRes) > 0) {
      $bank_summary['active_banks'] = (int) mysqli_fetch_assoc($bankRes)['total'];
   }

   $year = date('Y');
   $stmtRes = mysqli_query($con, "SELECT COUNT(*) AS total FROM bank_statements WHERE member_id = '{$member_id}' AND statement_year = '{$year}' AND received = 1");
   if ($stmtRes && mysqli_num_rows($stmtRes) > 0) {
      $bank_summary['received_current_year'] = (int) mysqli_fetch_assoc($stmtRes)['total'];
   }
}
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <style>
      .portal-banner { background: linear-gradient(135deg, #0c4a6e, #2563eb); color: #fff; border-radius: 14px; padding: 22px; }
      .portal-avatar { width: 52px; height: 52px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
      .portal-stat { border-radius: 12px; border: 1px solid #e5e7eb; padding: 14px; background: #fff; }
      .portal-stat h3 { margin: 0; color: #0f172a; }
      .portal-stat p { margin: 0; color: #64748b; font-size: 12px; }
      .section-card { border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04); }
      .status-chip { font-size: 11px; padding: 5px 10px; border-radius: 999px; font-weight: 600; }
      .status-paid { background: #dcfce7; color: #166534; }
      .status-partial { background: #fef3c7; color: #92400e; }
      .status-pending { background: #ffedd5; color: #9a3412; }
      .progress { height: 8px; border-radius: 999px; }
      .mini-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; background: #f8fafc; margin-bottom: 8px; }
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
         <div class="page-block mb-4">
            <div class="card card-body section-card">
               <div class="portal-banner">
                  <div class="d-flex justify-content-between align-items-start flex-wrap">
                     <div class="d-flex align-items-center mb-3 mb-md-0">
                        <span class="portal-avatar me-3"><?php echo htmlspecialchars($user_initials); ?></span>
                        <div>
                           <h4 class="mb-1 text-white">Client & Vendor Secure Portal</h4>
                           <p class="mb-0">
                              <?php echo htmlspecialchars($member_company); ?> | Member ID: <?php echo $member_id > 0 ? (int) $member_id : 'N/A'; ?>
                           </p>
                        </div>
                     </div>
                     <div class="text-md-end">
                        <small class="d-block">Role: <?php echo htmlspecialchars(ucfirst((string) $user_type)); ?></small>
                        <small class="d-block">Portal Security: Active Session</small>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <?php if ($portal_notice !== ''): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($portal_notice); ?></div>
         <?php endif; ?>

         <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="portal-stat"><h3><?php echo $portal_stats['open_projects']; ?></h3><p>Ongoing Projects</p></div></div>
            <div class="col-md-3"><div class="portal-stat"><h3><?php echo $portal_stats['pending_invoices']; ?></h3><p>Pending Invoices</p></div></div>
            <div class="col-md-3"><div class="portal-stat"><h3><?php echo $portal_stats['active_services']; ?></h3><p>Active Services</p></div></div>
            <div class="col-md-3"><div class="portal-stat"><h3><?php echo $portal_stats['new_comments']; ?></h3><p>New Work Comments (30d)</p></div></div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-lg-7">
               <div class="card section-card h-100">
                  <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Invoices</h5></div>
                  <div class="card-body">
                     <div class="table-responsive">
                        <table class="table align-middle">
                           <thead>
                              <tr><th>Invoice #</th><th>Date</th><th>Due</th><th>Amount</th><th>Status</th></tr>
                           </thead>
                           <tbody>
                              <?php if (!empty($invoices)): ?>
                                 <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                       <td><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                                       <td><?php echo htmlspecialchars($invoice['date']); ?></td>
                                       <td><?php echo htmlspecialchars($invoice['due']); ?></td>
                                       <td><?php echo htmlspecialchars($invoice['amount']); ?></td>
                                       <td><span class="status-chip <?php echo htmlspecialchars($invoice['status_class']); ?>"><?php echo htmlspecialchars($invoice['status']); ?></span></td>
                                    </tr>
                                 <?php endforeach; ?>
                              <?php else: ?>
                                 <tr><td colspan="5" class="text-center text-muted">No invoices found for this client.</td></tr>
                              <?php endif; ?>
                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-5">
               <div class="card section-card mb-3">
                  <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Bank Statements</h5></div>
                  <div class="card-body">
                     <p class="text-muted mb-2">Active banks: <strong><?php echo (int) $bank_summary['active_banks']; ?></strong></p>
                     <p class="text-muted mb-3">Received statements (<?php echo date('Y'); ?>): <strong><?php echo (int) $bank_summary['received_current_year']; ?></strong></p>
                     <?php if ($member_id > 0): ?>
                        <a href="bank_statements.php?id=<?php echo (int) $member_id; ?>" class="btn btn-primary btn-sm">Open Statements Area (Upload Enabled)</a>
                     <?php else: ?>
                        <button class="btn btn-secondary btn-sm" disabled>Open Statements Area</button>
                     <?php endif; ?>
                  </div>
               </div>
               <div class="card section-card">
                  <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                     <h5 class="mb-0">Card Management</h5>
                     <?php if ($member_id > 0): ?>
                        <a href="secured_area.php?client_id=<?php echo (int) $member_id; ?>" class="btn btn-success btn-sm">Manage Cards</a>
                     <?php endif; ?>
                  </div>
                  <div class="card-body">
                     <?php if (!empty($cards)): ?>
                        <?php foreach ($cards as $card): ?>
                           <div class="mini-card">
                              <strong><?php echo htmlspecialchars($card['type']); ?></strong> | <?php echo htmlspecialchars($card['number']); ?><br>
                              <small><?php echo htmlspecialchars($card['name']); ?> | Exp: <?php echo htmlspecialchars($card['exp']); ?></small>
                           </div>
                        <?php endforeach; ?>
                     <?php else: ?>
                        <p class="text-muted mb-0">No saved cards found.</p>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>

         <div class="row g-3 mb-4">
            <div class="col-lg-6">
               <div class="card section-card h-100">
                  <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Personal & Company Details</h5></div>
                  <div class="card-body">
                     <div class="row">
                        <div class="col-sm-6 mb-2"><small class="text-muted d-block">Contact Person</small><strong><?php echo htmlspecialchars($member_name); ?></strong></div>
                        <div class="col-sm-6 mb-2"><small class="text-muted d-block">Portal Email</small><strong><?php echo htmlspecialchars($member_email); ?></strong></div>
                        <div class="col-sm-6 mb-2"><small class="text-muted d-block">Company Name</small><strong><?php echo htmlspecialchars($member_company); ?></strong></div>
                        <div class="col-sm-6 mb-2"><small class="text-muted d-block">Phone</small><strong><?php echo htmlspecialchars($member_phone); ?></strong></div>
                        <div class="col-sm-12 mb-2"><small class="text-muted d-block">Address</small><strong><?php echo htmlspecialchars($member_address); ?></strong></div>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-6">
               <div class="card section-card h-100">
                  <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Ongoing Work & Projects</h5></div>
                  <div class="card-body">
                     <?php if (!empty($projects)): ?>
                        <?php foreach ($projects as $project): ?>
                           <div class="mb-3 pb-3 border-bottom">
                              <div class="d-flex justify-content-between">
                                 <strong><?php echo htmlspecialchars($project['name']); ?></strong>
                                 <span><?php echo (int) $project['progress']; ?>%</span>
                              </div>
                              <small class="text-muted d-block mb-2">
                                 <?php echo htmlspecialchars($project['service']); ?> | Owner: <?php echo htmlspecialchars($project['owner']); ?> | Status: <?php echo htmlspecialchars($project['status']); ?>
                              </small>
                              <div class="progress mb-2"><div class="progress-bar" role="progressbar" style="width: <?php echo (int) $project['progress']; ?>%;"></div></div>
                              <small><?php echo htmlspecialchars($project['last_update']); ?></small><br>
                              <a href="project_details.php?id=<?php echo (int) $project['order_id']; ?>" class="btn btn-link btn-sm p-0 mt-1">Open Project</a>
                           </div>
                        <?php endforeach; ?>
                     <?php else: ?>
                        <p class="text-muted mb-0">No projects found for this client.</p>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>

         <div class="card section-card">
            <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Services Sheet, Records, Comments & SharePoint</h5></div>
            <div class="card-body">
               <div class="table-responsive">
                  <table class="table align-middle">
                     <thead><tr><th>Service</th><th>Record</th><th>Latest Comment</th><th>SharePoint/Work Area</th></tr></thead>
                     <tbody>
                        <?php if (!empty($services)): ?>
                           <?php foreach ($services as $item): ?>
                              <tr>
                                 <td><?php echo htmlspecialchars($item['service']); ?></td>
                                 <td><?php echo htmlspecialchars($item['record']); ?></td>
                                 <td><?php echo htmlspecialchars($item['comments']); ?></td>
                                 <td>
                                    <?php if ($item['sharepoint'] !== '#'): ?>
                                       <a href="<?php echo htmlspecialchars($item['sharepoint']); ?>" class="btn btn-light btn-sm">View Work</a>
                                    <?php else: ?>
                                       <span class="text-muted">No linked workspace</span>
                                    <?php endif; ?>
                                 </td>
                              </tr>
                           <?php endforeach; ?>
                        <?php else: ?>
                           <tr><td colspan="4" class="text-center text-muted">No service records found.</td></tr>
                        <?php endif; ?>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
   </div>
   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
</body>

</html>
