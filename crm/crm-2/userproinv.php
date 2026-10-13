<?php
include "includes/auth.php";
include "includes/db.php";

$uid = (int) ($_GET['uid'] ?? 0);
if (!$uid) {
    header("Location: users.php");
    exit;
}

/* ---------- staff info ---------- */
$staff = mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT user_name FROM users WHERE user_id=$uid"
));
if (!$staff) {
    header("Location: users.php");
    exit;
}

/* ---------- projects created by this staff ---------- */
$openProjects = mysqli_query(
    $con,
    "SELECT p.order_id,
            p.mem_id,
            p.date_added,
            p.project_title,
            p.project_description,
            p.project_type,
            p.project_status,
            p.project_invoices,
            p.start_date,
            p.exp_date,
            CONCAT(m.name,' ',m.lname) AS client
     FROM projects p
     JOIN members m ON m.member_id = p.mem_id
     WHERE p.assign_to = $uid AND p.project_status = 'open'
     ORDER BY p.order_id DESC"
);

$completedProjects = mysqli_query(
    $con,
    "SELECT p.order_id,
            p.mem_id,
            p.date_added,
            p.project_title,
            p.project_description,
            p.project_type,
            p.project_status,
            p.project_invoices,
            p.start_date,
            p.exp_date,
            CONCAT(m.name,' ',m.lname) AS client
     FROM projects p
     JOIN members m ON m.member_id = p.mem_id
     WHERE p.assign_to = $uid AND p.project_status = 'completed'
     ORDER BY p.order_id DESC"
);

/* ---------- helper: fetch linked invoice details for a project ---------- */
function getProjectInvoices($con, $invoiceCsv) {
    if (empty($invoiceCsv)) return [];
    $ids = array_filter(array_map('intval', explode(',', $invoiceCsv)));
    if (empty($ids)) return [];
    $idStr = implode(',', $ids);
    $res = mysqli_query($con, "
      SELECT invoice_id, total_amount, paid_amount, balance, invoice_status
      FROM billing_invoice
      WHERE invoice_id IN ($idStr)
      ORDER BY invoice_id DESC
   ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    return $rows;
}

function renderInvoicePrices($invoices) {
    if (empty($invoices)) return '<span class="text-muted">—</span>';
    $out = [];
    $total = 0;
    foreach ($invoices as $inv) {
        $total += (float) $inv['total_amount'];
        $out[] = '<div><small class="text-muted">#' . (int) $inv['invoice_id'] . '</small> $' . number_format($inv['total_amount'], 2) . '</div>';
    }
    if (count($invoices) > 1) {
        $out[] = '<div class="mt-1"><strong>Total: $' . number_format($total, 2) . '</strong></div>';
    }
    return implode('', $out);
}

function renderInvoiceStatuses($invoices) {
    if (empty($invoices)) return '<span class="text-muted">—</span>';
    $map = [
        1 => ['label' => 'Unpaid',  'class' => 'badge-danger'],
        2 => ['label' => 'Partial', 'class' => 'badge-warning'],
        3 => ['label' => 'Paid',    'class' => 'badge-primary'],
    ];
    $out = [];
    foreach ($invoices as $inv) {
        $st = (int) $inv['invoice_status'];
        $info = $map[$st] ?? ['label' => 'Unknown', 'class' => 'badge-secondary'];
        $out[] = '<div class="mb-1"><span class="badge ' . $info['class'] . '">' . $info['label'] . '</span></div>';
    }
    return implode('', $out);
}

// Calculate total projects
$totalProjects = mysqli_num_rows($openProjects) + mysqli_num_rows($completedProjects);

$clients = mysqli_query(
    $con,
    "SELECT  * FROM members WHERE user_id = $uid ORDER BY date_added DESC"
);


$lists = mysqli_query($con, "
    SELECT project_invoices
    FROM projects
    WHERE user_id = $uid
      AND project_invoices IS NOT NULL
      AND project_invoices != ''
");
$raw = [];
while ($row = mysqli_fetch_assoc($lists)) {
    $raw = array_merge($raw, explode(',', $row['project_invoices']));
}
$ids = array_unique(array_map('intval', $raw));
$ids = implode(',', $ids) ?: '0';

// Unpaid invoices (status = 1)
$unpaidInvoices = mysqli_query($con, "
    SELECT 
        i.invoice_id,
        i.mem_id,
        CONCAT(m.name,' ',m.lname) AS client,
        m.member_id,
        i.total_amount,
        i.paid_amount,
        i.balance,
        i.due_date,
        i.paid_date,
        i.invoice_date,
        i.discount,
        (CASE 
            WHEN i.invoice_status=3 THEN 'Paid'
            WHEN i.invoice_status=2 THEN 'Partial'
            ELSE 'Unpaid' END) AS status
    FROM billing_invoice i
    JOIN members m ON m.member_id = i.mem_id
    WHERE i.invoice_id IN ($ids) AND i.invoice_status = 1
    ORDER BY i.invoice_id DESC
");


// Partial invoices (status = 2)
$partialInvoices = mysqli_query($con, "
    SELECT 
        i.invoice_id,
        i.mem_id,
        CONCAT(m.name,' ',m.lname) AS client,
        m.member_id,
        i.total_amount,
        i.paid_amount,
        i.balance,
        i.due_date,
        i.paid_date,
        i.invoice_date,
        i.discount,
        (CASE 
            WHEN i.invoice_status=3 THEN 'Paid'
            WHEN i.invoice_status=2 THEN 'Partial'
            ELSE 'Unpaid' END) AS status
    FROM billing_invoice i
    JOIN members m ON m.member_id = i.mem_id
    WHERE i.invoice_id IN ($ids) AND i.invoice_status = 2
    ORDER BY i.invoice_id DESC
");

// Paid invoices (status = 3)
$paidInvoices = mysqli_query($con, "
    SELECT 
        i.invoice_id,
        i.mem_id,
        CONCAT(m.name,' ',m.lname) AS client,
        m.member_id,
        i.total_amount,
        i.paid_amount,
        i.balance,
        i.due_date,
        i.paid_date,
        i.invoice_date,
        i.discount,
        (CASE 
            WHEN i.invoice_status=3 THEN 'Paid'
            WHEN i.invoice_status=2 THEN 'Partial'
            ELSE 'Unpaid' END) AS status
    FROM billing_invoice i
    JOIN members m ON m.member_id = i.mem_id
    WHERE i.invoice_id IN ($ids) AND i.invoice_status = 3
    ORDER BY i.invoice_id DESC
");

// Calculate total invoices
$totalInvoices = mysqli_num_rows($unpaidInvoices) + mysqli_num_rows($partialInvoices) + mysqli_num_rows($paidInvoices);

// Calculate invoice amounts by status
$unpaidAmount = 0;
$result = mysqli_query($con, "
    SELECT SUM(total_amount) AS total
    FROM billing_invoice
    WHERE invoice_id IN ($ids) AND invoice_status = 1
");
if ($row = mysqli_fetch_assoc($result)) {
    $unpaidAmount = $row['total'];
}

$partialAmount = 0;
$result = mysqli_query($con, "
    SELECT SUM(paid_amount) AS total
    FROM billing_invoice
    WHERE invoice_id IN ($ids) AND invoice_status = 2
");
if ($row = mysqli_fetch_assoc($result)) {
    $partialAmount = $row['total'];
}

$paidAmount = 0;
$result = mysqli_query($con, "
    SELECT SUM(total_amount) AS total
    FROM billing_invoice
    WHERE invoice_id IN ($ids) AND invoice_status = 3
");
if ($row = mysqli_fetch_assoc($result)) {
    $paidAmount = $row['total'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
   <title>Projects & Invoices - <?= htmlspecialchars($staff['user_name']) ?></title>
   <?php include "includes/links.php"; ?>
   <link href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css" rel="stylesheet">
   <style>
      body {
         font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
         background-color: #f8f9fa;
      }

      .page-header {
         background: linear-gradient(135deg, #6FD943, #5bb934);
         color: white;
         padding: 15px 20px;
         border-radius: 6px;
         margin-bottom: 20px;
      }

      .page-header-title h4 {
         margin: 0;
         font-size: 1.5rem;
         font-weight: 600;
      }

      .breadcrumb {
         background: none;
         padding: 0;
         margin-top: 5px;
      }

      .breadcrumb-item a {
         color: #e6f0ff !important;
         text-decoration: none;
      }

      .breadcrumb-item.active {
         color: #ffffff;
         font-weight: 500;
      }

      .card {
         border: 1px solid #e9ecef;
         border-radius: 6px;
         margin-bottom: 20px;
      }

      .card-header {
         background-color: #6FD943;
         color: white;
         padding: 12px 15px;
         border-radius: 6px 6px 0 0;
      }

      .card-header h5 {
         margin: 0;
         font-size: 1.1rem;
         font-weight: 500;
      }

      .card-body {
         padding: 15px;
      }

   
      .table td {
         padding: 10px;
         vertical-align: middle;

      }

      

      .badge {
         font-size: 11px;
         padding: 5px 10px;
         border-radius: 10px;
      }

      .badge-success {
         background-color: #6FD943;
         color: white;
      }

      .badge-warning {
         background-color: #ffc107;
         color: #333;
      }

      .badge-primary {
         background-color: #28a745;
         color: white;
      }

      .badge-danger {
         background-color: #dc3545;
         color: white;
      }

      .dataTables_wrapper {
         padding: 10px;
      }

      .dataTables_paginate .paginate_button {
         background: #6FD943;
         color: white !important;
         border-radius: 4px;
         margin: 0 5px;
         padding: 5px 10px;
      }

      .dataTables_paginate .paginate_button:hover {
         background: #5bb934;
      }

      .dataTables_filter input {
         border: 1px solid #6FD943;
         border-radius: 4px;
         padding: 5px;
      }

      .table-responsive {
         padding: 15px;
      }

      /* Tab styles */
      .nav-tabs {
         border-bottom: 2px solid #e9ecef;
         margin-bottom: 15px;
      }

      .nav-tabs .nav-link {
         color: #6c757d;
         border: none;
         border-bottom: 2px solid transparent;
         padding: 10px 15px;
         margin-right: 5px;
      }

      .nav-tabs .nav-link.active {
         color: #6FD943;
         font-weight: 600;
         background: none;
         border: none;
         border-bottom: 2px solid #6FD943;
      }

      .nav-tabs .nav-link:hover {
         border-bottom: 2px solid #6FD943;
         color: #6FD943;
      }

      .tab-content {
         padding-top: 10px;
      }

      .tab-pane {
         display: none;
      }

      .tab-pane.active {
         display: block;
      }

      .desc-preview,
      .desc-full {
         display: inline-block;
         max-width: 250px;
      }

      .desc-full {
         white-space: pre-wrap;
         line-height: 1.4;
      }

      .see-more,
      .see-less {
         cursor: pointer;
         color: #6FD943;
         font-weight: 500;
      }

      /* Stats Card Styles */
      .stats-card {
         border-radius: 8px;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
         transition: transform 0.3s ease, box-shadow 0.3s ease;
         overflow: hidden;
      }

      .stats-card:hover {
         transform: translateY(-5px);
         box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
      }

      .stats-card .card-body {
         padding: 20px;
         position: relative;
      }

      .stats-card .stats-icon {
         position: absolute;
         right: 20px;
         top: 20px;
         font-size: 2.5rem;
         opacity: 0.2;
      }

      .stats-card h3 {
         font-size: 2rem;
         font-weight: 700;
         margin-bottom: 5px;
      }

      .stats-card p {
         margin: 0;
         font-size: 1rem;
         font-weight: 500;
      }

      .stats-card.projects {
         background: linear-gradient(135deg, #6FD943, #5bb934);
         color: white;
      }

      .stats-card.unpaid {
         background: linear-gradient(135deg, #dc3545, #c82333);
         color: white;
      }

      .stats-card.partial {
         background: linear-gradient(135deg, #ffc107, #e0a800);
         color: white;
      }

      .stats-card.paid {
         background: linear-gradient(135deg, #28a745, #218838);
         color: white;
      }

      .stats-card.invoices {
         background: linear-gradient(135deg, #17a2b8, #138496);
         color: white;
      }
      table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            width: 100% !important;
        }

        table.dataTable thead th {
            background-color: #6FD943 !important;
            color: black;
            font-weight: bold;
            /* text-align: center; */
            padding: 12px;
        }

        table.dataTable tbody td {
            padding: 10px;
            /* text-align: center; */
            vertical-align: middle;
        }

        table.dataTable tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table.dataTable tbody tr:hover {
            background-color: #e0f8d8;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            background-color: #6FD943 !important;
            color: #fff !important;
            border-radius: 5px;
            margin: 2px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background-color: #baefa3ff !important;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 2px solid #6FD943;
            border-radius: 5px;
            padding: 5px 10px;
            margin-left: 5px;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 2px solid #6FD943;
            border-radius: 5px;
            padding: 5px;
        }

        .table-container {
            border-radius: 12px;
            overflow: hidden;
        }

        .section-title {
            background-color: #6FD943;
            /* Light gray background, you can change */
            padding: 20px 30px;
            /* Top/Bottom 20px, Left/Right 30px */
            margin: 20px 0;
            /* Top/Bottom 20px, Left/Right 0 */
            border-radius: 8px;
            /* Slightly rounded corners */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            /* Optional subtle shadow */
        }

        .section-title h5 {
            margin: 0;
            /* Remove default margin */
            font-size: 1.2rem;
            /* Adjust font size */
            color: #333;
            /* Text color */
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
                        <h4 class="m-b-10">
                           Projects & Invoices - <?= htmlspecialchars($staff['user_name']) ?>
                        </h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="users.php">Staff</a></li>
                        <li class="breadcrumb-item active">Projects & Invoices</li>
                     </ul>
                  </div>
               </div>
            </div>
         </div>

         <!-- Statistics Cards -->
         <div class="row mb-4">
            <div class="col-md-3">
               <div class="card stats-card projects">
                  <div class="card-body">
                     <i class="fas fa-tasks stats-icon"></i>
                     <h3><?= $totalProjects ?></h3>
                     <p>Total Projects</p>
                  </div>
               </div>
            </div>
            <div class="col-md-3">
               <div class="card stats-card invoices">
                  <div class="card-body">
                     <i class="fas fa-file-invoice-dollar stats-icon"></i>
                     <h3><?= $totalInvoices ?></h3>
                     <p>Total Invoices</p>
                  </div>
               </div>
            </div>
            <div class="col-md-2">
               <div class="card stats-card unpaid">
                  <div class="card-body">
                     <i class="fas fa-exclamation-circle stats-icon"></i>
                     <h3>$<?= isset($unpaidAmount) ? number_format((float) $unpaidAmount, 2) : '' ?></h3>

                     <p>Unpaid</p>
                  </div>
               </div>
            </div>
            <div class="col-md-2">
               <div class="card stats-card partial">
                  <div class="card-body">
                     <i class="fas fa-clock stats-icon"></i>
                     <h3>$<?= isset($partialAmount) ? number_format((float) $partialAmount, 2) : '' ?></h3>

                     <p>Partial</p>
                  </div>
               </div>
            </div>
            <div class="col-md-2">
               <div class="card stats-card paid">
                  <div class="card-body">
                     <i class="fas fa-check-circle stats-icon"></i>
                     <h3>$<?= isset($paidAmount) ? number_format((float) $paidAmount, 2) : '' ?></h3>
                     <p>Paid</p>
                  </div>
               </div>
            </div>
         </div>

         <!-- Projects -->
         <div class="row">
            <div class="col-md-12">
               <div class="card">
                  <div class="card-header">
                     <h5>Projects</h5>
                  </div>
                  <div class="card-body table-border-style">
                     <!-- Tabs Navigation -->
                     <ul class="nav nav-tabs" id="projectTabs" role="tablist">
                        <li class="nav-item">
                           <a class="nav-link active" id="open-tab" data-toggle="tab" href="#open" role="tab">
                              Open (<?= mysqli_num_rows($openProjects) ?>)
                           </a>
                        </li>
                        <li class="nav-item">
                           <a class="nav-link" id="completed-tab" data-toggle="tab" href="#completed" role="tab">
                              Completed (<?= mysqli_num_rows($completedProjects) ?>)
                           </a>
                        </li>

                     </ul>

                     <!-- Tabs Content -->
                     <div class="tab-content" id="projectTabsContent">
                        <!-- Open Projects Tab -->
                        <div class="tab-pane fade show active" id="open" role="tabpanel">
                           <div class="table-responsive">
                              <table class="table table-hover" id="openProjectsTable">
                                 <thead>
                                    <tr>
                                       <th>Project ID</th>
                                       <th>Member ID</th>
                                       <th>Client Name</th>
                                       <th>Project Title</th>
                                       <th>Project Description</th>
                                       <th>Date Added</th>
                                       <th>Start Date</th>
                                       <th>End Date</th>
                                       <th>Status</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($p = mysqli_fetch_assoc($openProjects)): ?>
                                       <tr>
                                          <td><?= htmlspecialchars($p['order_id']) ?></td>
                                          <td><?= htmlspecialchars($p['mem_id']) ?></td>
                                          <td><?= htmlspecialchars($p['client']) ?></td>
                                          <td><?= htmlspecialchars($p['project_title']) ?></td>
                                          <td>
                                             <span
                                                class="desc-preview"><?= htmlspecialchars(mb_substr($p['project_description'], 0, 30)) ?>
                                                <?php if (mb_strlen($p['project_description']) > 30): ?>
                                                   … <a href="javascript:void(0)" class="see-more">see&nbsp;more</a>
                                                <?php endif; ?>
                                             </span>
                                             <span
                                                class="desc-full d-none"><?= nl2br(htmlspecialchars($p['project_description'])) ?>
                                                <a href="javascript:void(0)"
                                                   class="see-less text-muted"><small>&nbsp;less</small></a>
                                             </span>
                                          </td>
                                          <td><?= date("m/d/Y", strtotime($p['date_added'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($p['start_date'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($p['exp_date'])) ?></td>
                                          <td>
                                             <span class="badge badge-warning">
                                                <?= ucfirst($p['project_status']) ?>
                                             </span>
                                          </td>
                                       </tr>
                                    <?php endwhile; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>

                        <!-- Completed Projects Tab -->
                        <div class="tab-pane fade" id="completed" role="tabpanel">
                           <div class="table-responsive">
                              <table class="table table-hover" id="completedProjectsTable">
                                 <thead>
                                    <tr>
                                       <th>Project ID</th>
                                       <th>Member ID</th>
                                       <th>Client Name</th>
                                       <th>Project Title</th>
                                       <th>Project Description</th>
                                       <th>Date Added</th>
                                       <th>Start Date</th>
                                       <th>End Date</th>
                                       <th>Status</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($p = mysqli_fetch_assoc($completedProjects)): ?>
                                       <tr>
                                          <td><?= htmlspecialchars($p['order_id']) ?></td>
                                          <td><?= htmlspecialchars($p['mem_id']) ?></td>
                                          <td><?= htmlspecialchars($p['client']) ?></td>
                                          <td><?= htmlspecialchars($p['project_title']) ?></td>
                                          <td>
                                             <span
                                                class="desc-preview"><?= htmlspecialchars(mb_substr($p['project_description'], 0, 30)) ?>
                                                <?php if (mb_strlen($p['project_description']) > 30): ?>
                                                   … <a href="javascript:void(0)" class="see-more">see&nbsp;more</a>
                                                <?php endif; ?>
                                             </span>
                                             <span
                                                class="desc-full d-none"><?= nl2br(htmlspecialchars($p['project_description'])) ?>
                                                <a href="javascript:void(0)"
                                                   class="see-less text-muted"><small>&nbsp;less</small></a>
                                             </span>
                                          </td>
                                          <td><?= date("m/d/Y", strtotime($p['date_added'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($p['start_date'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($p['exp_date'])) ?></td>
                                          <td>
                                             <span class="badge badge-success">
                                                <?= ucfirst($p['project_status']) ?>
                                             </span>
                                          </td>
                                       </tr>
                                    <?php endwhile; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>


                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Clients -->
         <div class="row">
            <div class="col-md-12">
               <div class="card">
                  <div class="card-header">
                     <h5>Clients Added (<?= mysqli_num_rows($clients) ?>)</h5>
                  </div>
                  <div class="card-body table-border-style">
                     <div class="table-responsive">
                        <table class="table table-hover" id="clientsTable">
                           <thead>
                              <tr>
                                 <th>Member ID</th>
                                 <th>Name</th>
                                 <th>Category</th>
                                 <!-- <th>Business Name</th> -->
                                 <th>Phone</th>
                                 <th>Email</th>
                                 <th>Client Since</th>
                                 <th>Status</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php while ($c = mysqli_fetch_assoc($clients)): ?>
                                 <tr>
                                    <td><?= htmlspecialchars($c['member_id'] ?? '') ?></td>

                                    <td>
                                       <?= htmlspecialchars(
    trim(
        ($c['name'] ?? '') . ' ' .
        ($c['mname'] ?? '') . ' ' .
        ($c['lname'] ?? '')
    )
                                       ) ?>
                                       <br>
                                       <small><?= htmlspecialchars($c['bus_name'] ?? '') ?></small>
                                    </td>

                                    <td><?= htmlspecialchars($c['category'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['phone'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($c['prm_email'] ?? '') ?></td>

                                    <td>
                                       <?php
if (!empty($c['client_since']) && $c['client_since'] !== '0000-00-00' && strtotime($c['client_since']) !== false) {
    echo date('m/d/Y', strtotime($c['client_since']));
} else {
    echo 'N/A';
}
                                       ?>
                                    </td>

                                    <td style="color:<?= !empty($c['status']) ? 'green' : 'red' ?>">
                                       <?= !empty($c['status']) ? 'Active' : 'Inactive' ?>
                                    </td>
                                 </tr>
                              <?php endwhile; ?>

                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Invoices -->
         <div class="row">
            <div class="col-md-12">
               <div class="card">
                  <div class="card-header">
                     <h5>Invoices</h5>
                  </div>
                  <div class="card-body table-border-style">
                     <!-- Tabs Navigation -->
                     <ul class="nav nav-tabs" id="invoiceTabs" role="tablist">
                        <li class="nav-item">
                           <a class="nav-link active" id="unpaid-tab" data-toggle="tab" href="#unpaid" role="tab">
                              Unpaid (<?= mysqli_num_rows($unpaidInvoices) ?>)
                           </a>
                        </li>
                        <li class="nav-item">
                           <a class="nav-link" id="partial-tab" data-toggle="tab" href="#partial" role="tab">
                              Partial (<?= mysqli_num_rows($partialInvoices) ?>)
                           </a>
                        </li>
                        <li class="nav-item">
                           <a class="nav-link" id="paid-tab" data-toggle="tab" href="#paid" role="tab">
                              Paid (<?= mysqli_num_rows($paidInvoices) ?>)
                           </a>
                        </li>
                     </ul>

                     <!-- Tabs Content -->
                     <div class="tab-content" id="invoiceTabsContent">
                        <!-- Unpaid Invoices Tab -->
                        <div class="tab-pane fade show active" id="unpaid" role="tabpanel">
                           <div class="table-responsive">
                              <table class="table table-hover" id="unpaidInvoicesTable">
                                 <thead>
                                    <tr>
                                       <th>Invoice ID</th>
                                       <th>Member ID</th>
                                       <th>Client Name</th>
                                       <th>Due Date</th>
                                       <th>Paid Date</th>
                                       <th>Total</th>
                                       <th>Paid</th>
                                       <th>Discount</th>
                                       <th>Balance</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($inv = mysqli_fetch_assoc($unpaidInvoices)): ?>
                                       <tr>
                                          <td><?= htmlspecialchars($inv['invoice_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['member_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['client']) ?? '' ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['due_date'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['paid_date'])) ?></td>
                                          <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['paid_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['discount'], 2) ?></td>
                                          <td>$<?= number_format($inv['balance'], 2) ?></td>

                                       </tr>
                                    <?php endwhile; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>

                        <!-- Partial Invoices Tab -->
                        <div class="tab-pane fade" id="partial" role="tabpanel">
                           <div class="table-responsive">
                              <table class="table table-hover" id="partialInvoicesTable">
                                 <thead>
                                    <tr>
                                       <th>Invoice ID</th>
                                       <th>Member ID</th>
                                       <th>Client Name</th>
                                       <th>Due Date</th>
                                       <th>Paid Date</th>
                                       <th>Total</th>
                                       <th>Paid</th>
                                       <th>Discount</th>
                                       <th>Balance</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($inv = mysqli_fetch_assoc($partialInvoices)): ?>
                                       <tr>
                                          <td><?= htmlspecialchars($inv['invoice_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['member_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['client']) ?? '' ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['due_date'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['paid_date'])) ?></td>
                                          <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['paid_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['discount'], 2) ?></td>
                                          <td>$<?= number_format($inv['balance'], 2) ?></td>

                                       </tr>
                                    <?php endwhile; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>

                        <!-- Paid Invoices Tab -->
                        <div class="tab-pane fade" id="paid" role="tabpanel">
                           <div class="table-responsive">
                              <table class="table table-hover" id="paidInvoicesTable">
                                 <thead>
                                    <tr>
                                       <th>Invoice ID</th>
                                       <th>Member ID</th>
                                       <th>Client Name</th>
                                       <th>Due Date</th>
                                       <th>Paid Date</th>
                                       <th>Total</th>
                                       <th>Paid</th>
                                       <th>Discount</th>
                                       <th>Balance</th>
                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php while ($inv = mysqli_fetch_assoc($paidInvoices)): ?>
                                       <tr>
                                          <td><?= htmlspecialchars($inv['invoice_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['member_id']) ?></td>
                                          <td><?= htmlspecialchars($inv['client']) ?? '' ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['due_date'])) ?></td>
                                          <td><?= date("m/d/Y", strtotime($inv['paid_date'])) ?></td>
                                          <td>$<?= number_format($inv['total_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['paid_amount'], 2) ?></td>
                                          <td>$<?= number_format($inv['discount'], 2) ?></td>
                                          <td>$<?= number_format($inv['balance'], 2) ?></td>

                                       </tr>
                                    <?php endwhile; ?>
                                 </tbody>
                              </table>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
   <script>
      $(document).ready(function () {
         // Initialize DataTables for Open Projects
         var openProjectsTable = $('#openProjectsTable').DataTable({
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });

         // Initialize DataTables for Completed Projects
         var completedProjectsTable = $('#completedProjectsTable').DataTable({
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });



         // Initialize DataTables for Clients
         $('#clientsTable').DataTable({
            paging: true,
            order: [[0, "desc"]],
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });

         // Initialize DataTables for Unpaid Invoices
         var unpaidInvoicesTable = $('#unpaidInvoicesTable').DataTable({
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });

         // Initialize DataTables for Partial Invoices
         var partialInvoicesTable = $('#partialInvoicesTable').DataTable({
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });

         // Initialize DataTables for Paid Invoices
         var paidInvoicesTable = $('#paidInvoicesTable').DataTable({
            paging: true,
            pageLength: 10,
            searching: true,
            ordering: true,
            info: true,
            responsive: true
         });
 
         // Handle tab clicks to redraw DataTables
         $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href");

            // Redraw the appropriate DataTable when tab is shown
            if (target === "#open") {
               openProjectsTable.columns.adjust().responsive.recalc();
            } else if (target === "#completed") {
               completedProjectsTable.columns.adjust().responsive.recalc();
            } else if (target === "#unpaid") {
               unpaidInvoicesTable.columns.adjust().responsive.recalc();
            } else if (target === "#partial") {
               partialInvoicesTable.columns.adjust().responsive.recalc();
            } else if (target === "#paid") {
               paidInvoicesTable.columns.adjust().responsive.recalc();
            }
         });
      });
      // ---------------------------------------------------------
      //  4.  JavaScript  (append inside $(document).ready(...))
      // ---------------------------------------------------------
      $(document).on('click', '.see-more', function () {
         $(this).closest('td').find('.desc-preview').addClass('d-none');
         $(this).closest('td').find('.desc-full').removeClass('d-none');
      });
      $(document).on('click', '.see-less', function () {
         $(this).closest('td').find('.desc-full').addClass('d-none');
         $(this).closest('td').find('.desc-preview').removeClass('d-none');
      });
   </script>
</body>

</html>