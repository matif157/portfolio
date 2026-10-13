<?php
include 'includes/db.php';
include "includes/auth.php";
// error_reporting(0);
error_reporting(E_ALL);
ini_set('display_errors', 0);

/* ────────────────────────── DELETE MEMBER ────────────────────────── */
if (isset($_POST['delete'])) {
   $member_id = $_POST['member_id'];
   $delete_query = mysqli_query($con, "DELETE FROM members WHERE member_id='$member_id'");
   $msg = $delete_query
      ? "<div class='alert alert-success'>Client Has Been Deleted Successfully.</div>"
      : "<div class='alert alert-danger'>Something Went Wrong: " . $con->error . "</div>";
}

/* ────────────────────────── NEW DATE RANGE AND TYPE VARIABLES ────────────────────────── */
$status = isset($_GET['status']) && ctype_digit((string) $_GET['status']) ? (int) $_GET['status'] : 'all';
$start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : '';
$type = isset($_GET['type']) && ctype_digit((string) $_GET['type']) ? (int) $_GET['type'] : 'all';
$membership = isset($_GET['membership']) && ctype_digit((string) $_GET['membership']) ? (int) $_GET['membership'] : 'all';

/* ────────────────────────── PAGINATION ────────────────────────── */
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) && (int) $_GET['page'] > 0 ? (int) $_GET['page'] : 1;
$num_results_on_page = 25;

/* ────────────────────────── BASE QUERY ────────────────────────── */
$query_base = " FROM billing_invoice
                INNER JOIN members ON billing_invoice.mem_id = members.member_id
                INNER JOIN billing_invoice_items bii ON bii.inv_id = billing_invoice.invoice_id
                WHERE members.status = 1 AND members.ach_payer = 0";
$conditions = [];

/* ────────────────────────── STATUS FILTER ────────────────────────── */
if ($status !== 'all') {
   $conditions[] = "billing_invoice.invoice_status = " . (int) $status;
}

/* ────────────────────────── DATE RANGE FILTER ────────────────────────── */
if ($start_date && $end_date) {
   $conditions[] = "billing_invoice.due_date BETWEEN '$start_date' AND '$end_date'";
}

/* ────────────────────────── TYPE FILTER ────────────────────────── */
if ($type !== 'all') {
   $conditions[] = "members.type = " . (int) $type;
}

/* ────────────────────────── MEMBERSHIP FILTER ────────────────────────── */
if ($membership !== 'all') {
   $conditions[] = "bii.item_description = (
                       SELECT membership_name
                       FROM memberships
                       WHERE membership_id = " . (int) $membership . "
                     )";
}

/* ────────────────────────── FINAL QUERY WITH PAGING ────────────────────────── */
$conditions_sql = $conditions ? " AND " . implode(' AND ', $conditions) : '';
$query = "SELECT DISTINCT
               billing_invoice.*,
               members.*" . $query_base . $conditions_sql;
$offset = ($page - 1) * $num_results_on_page;
$query .= " ORDER BY billing_invoice.due_date DESC
            LIMIT $offset, $num_results_on_page";
$result = mysqli_query($con, $query);

/* ────────────────────────── TOTAL FOR PAGINATION ────────────────────────── */
$total_query = "SELECT COUNT(DISTINCT billing_invoice.invoice_id) as total
                FROM billing_invoice
                INNER JOIN members ON billing_invoice.mem_id = members.member_id
                INNER JOIN billing_invoice_items bii ON bii.inv_id = billing_invoice.invoice_id
                WHERE members.status = 1 AND members.ach_payer = 0";
if ($conditions) {
   $total_query .= " AND " . implode(' AND ', $conditions);
}
$total_records = mysqli_fetch_assoc(mysqli_query($con, $total_query))['total'];
$total_pages = ceil($total_records / $num_results_on_page);

if ($total_pages < 1) {
   $total_pages = 1;
}

if ($page > $total_pages) {
   $page = $total_pages;
   $offset = ($page - 1) * $num_results_on_page;
   $query = "SELECT DISTINCT
               billing_invoice.*,
               members.*" . $query_base . $conditions_sql . "
            ORDER BY billing_invoice.due_date DESC
            LIMIT $offset, $num_results_on_page";
   $result = mysqli_query($con, $query);
}

$pagination_filters = [
   'status' => $status === 'all' ? 'all' : (string) $status,
   'type' => $type === 'all' ? 'all' : (string) $type,
   'membership' => $membership === 'all' ? 'all' : (string) $membership,
   'start_date' => $start_date,
   'end_date' => $end_date,
];

$build_page_url = function ($page_number) use ($pagination_filters) {
   return '?' . http_build_query(array_merge($pagination_filters, ['page' => $page_number]));
};
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services - Dashboard</title>
   <?php include "includes/links.php"; ?>
   <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <style>
      .badge-warning {
         background-color: #f0ad4e;
      }

      .dataTable-wrapper .dataTable-top .dataTable-search {
         display: none;
      }

      .dataTable-info {
         display: none !important;
      }

      .invoice-table-toolbar {
         display: flex;
         justify-content: space-between;
         align-items: center;
         gap: 12px;
         flex-wrap: wrap;
         min-width: 0;
         margin: 16px 0 0;
         padding-bottom: 30px;
      }

      .invoice-table-summary {
         color: #555;
         margin: 0;
         white-space: nowrap;
         flex-shrink: 0;
      }

      .invoice-search-box {
         position: relative;
         width: 200px;
         max-width: 100%;
         flex-shrink: 1;
         min-width: 120px;
      }

      .invoice-search-box .form-control {
         margin-top: 0;
      }

      .invoice-table-toolbar-right {
         display: flex;
         align-items: center;
         gap: 10px;
         flex-wrap: wrap;
         min-width: 0;
      }

      #invoice-billings-table th,
      #invoice-billings-table td {
         overflow-wrap: break-word;
         word-break: break-word;
      }

      /* DataTables Buttons toolbar (Copy/Excel/CSV/PDF/Print/Columns) - navy theme */
      div.dt-buttons {
         display: flex;
         flex-wrap: wrap;
         gap: 6px;
         margin-bottom: 0;
      }

      div.dt-buttons .dt-button {
         margin: 0;
         border-radius: 0;
         text-align: center;
         justify-content: center;
         padding: 6px 10px;
         font-size: 12px;
         background: #252161 !important;
         color: #fff !important;
         border: 1px solid #252161 !important;
         font-weight: 500;
         font-size: 13px;
      }

      div.dt-buttons .dt-button i {
         margin-right: 4px;
      }

      div.dt-buttons .dt-button:hover,
      div.dt-buttons .dt-button:focus {
         background: #1b1850 !important;
         color: #fff !important;
         box-shadow: 0 2px 5px rgba(37, 33, 97, 0.35);
      }

      /* Column-visibility dropdown menu (clean light style) */
      .dt-button-collection {
         background: #fff !important;
         border: 1px solid #e3e3ec !important;
         border-radius: 0 !important;
         padding: 6px !important;
         box-shadow: 0 8px 22px rgba(37, 33, 97, 0.18) !important;
         min-width: 200px;
      }

      .dt-button-collection .dt-button {
         display: block !important;
         flex: none !important;
         width: 100% !important;
         margin: 2px 0 !important;
         text-align: left !important;
         background: #fff !important;
         color: #252161 !important;
         border: none !important;
         border-radius: 0 !important;
         box-shadow: none !important;
         padding: 7px 12px !important;
      }

      .dt-button-collection .dt-button span {
         text-align: left !important;
      }

      .dt-button-collection .dt-button:hover,
      .dt-button-collection .dt-button:focus {
         background: #f2f1f8 !important;
         color: #252161 !important;
         box-shadow: none !important;
      }

      .dt-button-collection .dt-button.active {
         background: #eceafa !important;
         color: #252161 !important;
      }

      #invoiceSearchResults {
         top: calc(100% + 4px);
         left: 0;
         width: 100%;
         margin-top: 0;
      }

      .swal2-title,
      .swal2-html-container {
         text-align: left !important;
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
         <div class="page-header">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">Invoice & Billings</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Invoice/Billings</li>
                     </ul>
                  </div>
               </div>
            </div>
         </div>

         <div class="row">
            <div class="col-sm-12">
               <?php if (isset($msg))
                  echo $msg; ?>
               <div class="card">
                  <div class="card-body table-border-style">
                     <!-- ─────────── FILTER FORM ─────────── -->
                     <form method="GET" id="filterForm" class="mb-4">
                        <input type="hidden" name="page" value="1">
                        <div class="row g-2 align-items-end">
                           <div class="col-md-2">
                              <select class="form-control select" name="status">
                                 <option value="all">Invoice Status</option>
                                 <?php
                                 $fs = mysqli_query($con, "SELECT * FROM billing_invoice_status");
                                 while ($r = mysqli_fetch_assoc($fs)) {
                                    $sel = ($status == $r['status_id']) ? 'selected' : '';
                                    echo '<option value="' . $r['status_id'] . '" ' . $sel . '>' . $r['status_text'] . '</option>';
                                 }
                                 ?>
                              </select>
                           </div>

                           <div class="col-md-2">
                              <select class="form-control select" name="type">
                                 <option value="all">Member Type</option>
                                 <?php
                                 $ft = mysqli_query($con, "SELECT * FROM member_type");
                                 while ($t = mysqli_fetch_assoc($ft)) {
                                    $sel = ($type == $t['type_id']) ? 'selected' : '';
                                    echo '<option value="' . $t['type_id'] . '" ' . $sel . '>' . $t['type_name'] . '</option>';
                                 }
                                 ?>
                              </select>
                           </div>

                           <div class="col-md-2">
                              <select class="form-control select" name="membership">
                                 <option value="all">Membership</option>
                                 <?php
                                 $fm = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
                                 while ($m = mysqli_fetch_assoc($fm)) {
                                    $sel = ($membership == $m['membership_id']) ? 'selected' : '';
                                    echo '<option value="' . $m['membership_id'] . '" ' . $sel . '>' . htmlspecialchars($m['membership_name']) . '</option>';
                                 }
                                 ?>
                              </select>
                           </div>

                           <div class="col-md-2">
                              <input type="date" class="form-control" name="start_date"
                                 value="<?= htmlspecialchars($start_date) ?>">
                           </div>

                           <div class="col-md-2">
                              <input type="date" class="form-control" name="end_date"
                                 value="<?= htmlspecialchars($end_date) ?>">
                           </div>

                           <div class="col-md-2 d-grid gap-2">
                              <button type="submit" class="btn btn-success btn-sm">Search</button>
                              <button type="button" id="exportFilteredAjax" class="btn btn-info btn-sm">
                                 Export Filtered CSV
                              </button>
                           </div>
                        </div>
                     </form>

                     <!-- ─────────── SEARCH INPUT ─────────── -->
                     <div class="invoice-table-toolbar">
                        <div class="invoice-table-summary">
                           <?php
                           $start_entry = $total_records > 0 ? $offset + 1 : 0;
                           $end_entry = $total_records > 0 ? min($offset + $num_results_on_page, $total_records) : 0;
                           echo "Showing {$start_entry} to {$end_entry} of {$total_records} entries";
                           ?>
                        </div>
                        <div class="invoice-table-toolbar-right" id="invoiceTableToolbarRight">
                           <div class="invoice-search-box">
                              <input type="text" id="invoiceSearchInput" class="form-control"
                                 placeholder="Search Invoice ID" autocomplete="off">
                              <div id="invoiceSearchResults" class="dropdown-menu"
                                 style="display:none;position:absolute;z-index:1000;"></div>
                           </div>
                        </div>
                     </div>
                     <!-- ─────────── TABLE ─────────── -->
                     <div class="table-responsive">
                        <div class="dataTable-wrapper dataTable-loading no-footer sortable searchable fixed-columns">
                           <div class="dataTable-container">
                              <table class="table" id="invoice-billings-table">
                                 <thead>
                                    <tr>
                                       <th>Client ID</th>
                                       <th>Invoice ID</th>
                                       <th>Name</th>
                                       <th>Phone</th>
                                       <th>Email</th>
                                       <th>Due Date</th>
                                       <th>Invoice Amount</th>
                                       <th>Balance</th>
                                       <th>Services</th>
                                       <th>Status</th>

                                    </tr>
                                 </thead>
                                 <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                       <?php while ($rrow = $result->fetch_assoc()): ?>
                                       <tr>
                                          <td>
                                             <a href="client_billing.php?id=<?= $rrow['mem_id'] ?>"
                                                class="btn btn-outline-primary">
                                                #<?= $rrow['mem_id'] ?>
                                                <?php if (!empty($rrow['ach_payer']) && $rrow['ach_payer'] == 1): ?>
                                                   <span style="font-size:12px; color:#888; margin-left:5px;">(ACH)</span>
                                                <?php endif; ?>
                                             </a>
                                          </td>
                                          <td>
                                             <a href="client_billing.php?id=<?= $rrow['mem_id'] ?>"
                                                class="btn btn-outline-primary">
                                                <?= $rrow['invoice_id'] ?>
                                                <?php if (!empty($rrow['ach_payer']) && $rrow['ach_payer'] == 1): ?>
                                                   <span style="font-size:12px; color:#888; margin-left:5px;">(ACH)</span>
                                                <?php endif; ?>
                                             </a>
                                          </td>
                                          <td>
                                             <a
                                                href="client_details.php?id=<?= $rrow['mem_id'] ?>&status=<?= $rrow['status'] ?>">
                                                <?= trim($rrow['name'] . ' ' . $rrow['mname'] . ' ' . $rrow['lname']) ?>
                                             </a>
                                             <div><?= htmlspecialchars($rrow['bus_name']) ?></div>
                                             <?php
                                             $balQ = mysqli_query($con, "SELECT SUM(balance) AS bal FROM billing_invoice WHERE mem_id='{$rrow['mem_id']}'");
                                             $balR = mysqli_fetch_assoc($balQ);
                                             ?>
                                             <div style="color:#6FD943;">TOTAL BALANCE :
                                                <?= number_format((float) ($balR['bal'] ?? 0), 2) ?>
                                             </div>

                                          </td>

                                          <td>
                                             <?php
                                             $src = '';
                                             $label = '';

                                             foreach (['phone', 'cell_ph', 'other_ph', 'overseas_ph'] as $col) {
                                                if (!empty($rrow[$col])) {
                                                   $src = $rrow[$col];
                                                   $label = ucfirst(str_replace('_', ' ', $col));
                                                   break;
                                                }
                                             }

                                             /* 1-212-555-1234  (212)555-1234  212-555-1234  etc. */
                                             preg_match_all('/
                                              (?:1\s*[-.])?                
                                              (?:\(\s*\d{3}\s\)|\d{3})     
                                              \s*[-.]?\s*                  
                                              \d{3}\s*[-.]?\s*\d{4}        
                                          /x', $src, $m);

                                             $phones = $m[0];          // every matched phone string
                                             $phones = array_map('trim', $phones);   // tidy up
                                          
                                             if ($phones) {
                                                echo htmlspecialchars(implode('<br>', $phones));
                                                echo "<div style='font-size:11px;color:#888;'>$label</div>";
                                             } else {
                                                echo "No Number Available";
                                             }
                                             ?>
                                          </td>

                                          <td>
                                             <?php
                                             if (!empty($rrow['prm_email'])) {
                                                echo $rrow['prm_email'] . "<div style='font-size:11px;color:#888;'>Primary Email</div>";
                                             } elseif (!empty($rrow['sec_email'])) {
                                                echo $rrow['sec_email'] . "<div style='font-size:11px;color:#888;'>Secondary Email</div>";
                                             } else {
                                                echo "No Email Available";
                                             }
                                             ?>
                                          </td>
                                          <td><?= date("m/d/Y", strtotime($rrow["due_date"])) ?></td>
                                          <td>
                                             <?php
                                             $memId = $rrow['mem_id'];
                                             $balQ = mysqli_query($con, "SELECT SUM(balance) AS total_mem_balance 
                                             FROM billing_invoice 
                                             WHERE mem_id = '$memId'");
                                             $memBal = mysqli_fetch_assoc($balQ)['total_mem_balance'] ?? 0;
                                             ?>
                                             <span>Balance: <?= number_format($rrow["balance"], 2) ?></span><br>
                                             <span>Total: <?= number_format($rrow["total_amount"], 2) ?></span><br>
                                          </td>
                                          <td>
                                             <span>Paid: <?= number_format($rrow["paid_amount"], 2) ?></span><br>
                                             <span>Discount:
                                                <?= number_format((float) ($rrow["discount"] ?? 0), 2) ?></span>
                                          </td>

                                          <td style="position:relative;line-height:1.4;text-align:left;width:240px;">
                                             <?php
                                             $invId = $rrow['invoice_id'];
                                             $res = mysqli_query(
                                                $con,
                                                "SELECT item_description, item_sub_description
          FROM   billing_invoice_items
          WHERE  inv_id = '" . mysqli_real_escape_string($con, $invId) . "'"
                                             );

                                             if (mysqli_num_rows($res) == 0) {
                                                echo '<span style="color:#999">No services</span>';
                                             } else {
                                                $list = [];                       // full lines for popup
                                                $firstLine = '';                  // very first line (shortened) shown in cell
                                          
                                                while ($r = mysqli_fetch_assoc($res)) {
                                                   $main = htmlspecialchars(trim($r['item_description']));
                                                   $sub = htmlspecialchars(trim($r['item_sub_description']));

                                                   // build the complete line
                                                   $line = '• ' . $main;
                                                   if ($sub !== '') {
                                                      $line .= "<br><small style='color:#888;font-size:11px;'>$sub</small>";
                                                   }

                                                   $list[] = $line;

                                                   // keep the very first line for the cell display
                                                   if ($firstLine === '') {
                                                      $short = mb_strlen($main) > 20 ? mb_substr($main, 0, 20) . '…' : $main;
                                                      $firstLine = '<div style="font-weight:500;">• ' . $short . '</div>';
                                                   }
                                                }

                                                /* always show the first (short) line */
                                                echo $firstLine;

                                                /* “View all” only if >1 service OR first line was truncated */
                                                if (count($list) > 1 || mb_strlen($list[0]) > 20) {
                                                   $popup = implode('<br><br>', $list);   // double break between services
                                                   echo '
<div style="font-size:.85em;color:#007bff;cursor:pointer;text-align:left;"
     onclick="Swal.fire({
        title:\'All Services\',
        html:\'' . addslashes($popup) . '\',
        icon:\'info\',
        confirmButtonText:\'Close\',
        width:500
     })">
    View all
</div>'; 
                                                }
                                             }
                                             ?>
                                          </td>

                                          <!-- added -->
                                          <td>
                                             <?php
                                             $st = mysqli_fetch_assoc(mysqli_query($con, "SELECT status_text FROM billing_invoice_status WHERE status_id={$rrow['invoice_status']}"));

                                             switch ($st['status_text']) {
                                                case 'Not Paid':
                                                   echo '<span class="badge badge-pill badge-danger">Unpaid</span>';
                                                   break;
                                                case 'Partial Paid':
                                                   echo '<span class="badge badge-pill badge-warning">Partial Paid</span>';
                                                   break;
                                                case 'Paid':
                                                   echo '<span class="badge badge-pill badge-success">Paid</span>';
                                                   break;
                                             }
                                             ?>

                                             <br>

                                             <a href="invoices/<?= $rrow["filelink"] ?>" class="view-file-preview"
                                                data-file="<?= htmlspecialchars($rrow['filelink']) ?>" target="_blank">
                                                View File
                                             </a>
                                          </td>

                                       </tr>
                                       <?php endwhile; ?>
                                    <?php else: ?>
                                       <tr>
                                          <td colspan="10" class="text-center">No invoice records found.</td>
                                       </tr>
                                    <?php endif; ?>
                                 </tbody>
                              </table>
                           </div>

                           <!-- ─────────── PAGINATION ─────────── -->
                           <div class="dataTable-bottom">


                              <?php if ($total_pages > 1): ?>
                                 <ul class="news">
                                    <?php if ($page > 1): ?>
                                        <li class="prev"><a
                                             href="<?= htmlspecialchars($build_page_url($page - 1)) ?>">Prev</a>
                                       </li>
                                    <?php endif; ?>

                                    <?php if ($page > 3): ?>
                                        <li class="start"><a
                                             href="<?= htmlspecialchars($build_page_url(1)) ?>">1</a>
                                       </li>
                                       <li class="dots">...</li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                       <?php if ($i == $page): ?>
                                          <li class="current"><span><?= $i ?></span></li>
                                       <?php else: ?>
                                          <li><a
                                                href="<?= htmlspecialchars($build_page_url($i)) ?>"><?= $i ?></a>
                                          </li>
                                       <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages - 2): ?>
                                       <li class="dots">...</li>
                                        <li class="end"><a
                                             href="<?= htmlspecialchars($build_page_url($total_pages)) ?>"><?= $total_pages ?></a>
                                       </li>
                                    <?php endif; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="next"><a
                                             href="<?= htmlspecialchars($build_page_url($page + 1)) ?>">Next</a>
                                       </li>
                                    <?php endif; ?>
                                 </ul>
                              <?php endif; ?>
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
   <style>
      .news {
         list-style-type: none;
         padding: 10px 0;
         display: inline-flex;
         justify-content: space-between;
         box-sizing: border-box;
      }

      .news li {
         box-sizing: border-box;
         padding-right: 10px;
      }

      .news li a {
         box-sizing: border-box;
         background-color: #e2e6e6;
         padding: 8px;
         text-decoration: none;
         font-size: 12px;
         font-weight: bold;
         color: #616872;
         border-radius: 4px;
      }

      .news li a:hover {
         background-color: #d4dada;
      }

      .news .next a,
      .news .prev a {
         text-transform: uppercase;
         font-size: 12px;
      }

      .news .current span {
         background-color: #518acb;
         color: #fff;
         padding: 8px;
         border-radius: 4px;
      }
   </style>

   <script>
      $(document).ready(function () {
         let invoiceSearchTimeout;

         $('#invoiceSearchInput').on('input', function () {
            clearTimeout(invoiceSearchTimeout);
            let query = $(this).val();

            invoiceSearchTimeout = setTimeout(function () {
               if (query.length > 0) {
                  $.ajax({
                     url: 'search_invoices.php',
                     type: 'POST',
                     data: { invoice_id: query },
                     success: function (data) {
                        $('#invoiceSearchResults').html(data).show();
                     }
                  });
               } else {
                  $('#invoiceSearchResults').hide();
               }
            }, 300);
         });

         $(document).click(function (e) {
            if (!$(e.target).closest('#invoiceSearchResults').length && !$(e.target).is('#invoiceSearchInput')) {
               $('#invoiceSearchResults').hide();
            }
         });

         // Export filtered CSV function
         $('#exportFilteredAjax').on('click', function (e) {
            e.preventDefault(); // Prevent form submission
            const payload = {
               status: '<?= $status ?>',
               type: '<?= $type ?>',
               start_date: '<?= $start_date ?>',
               end_date: '<?= $end_date ?>',
               membership: '<?= htmlspecialchars($membership) ?>'
            };

            $.ajax({
               url: 'inv_bil_export.php',
               type: 'POST',
               data: payload,
               xhrFields: { responseType: 'blob' },          // important: binary
               success: function (blob) {
                  const link = document.createElement('a');
                  link.href = window.URL.createObjectURL(blob);
                  link.download = 'invoices_' + $.now() + '.csv';
                  link.click();
                  window.URL.revokeObjectURL(link.href);
               },
               error: function (xhr) {
                  Swal.fire('Error', 'Could not generate CSV.', 'error');
               }
            });
         });
      });
   </script>
   <!-- to send  -->
   <script>
      /* =========  CENTRED PDF PREVIEW OVERLAY  ========= */
      $(function () {
         const defaultZoom = 100;
         let previewZoom = defaultZoom;

         const $overlay = $(`
         <div id="pdfPreviewOverlay"
         style="position:fixed; top:0; left:0; width:100%; height:100%;
         background:rgba(0,0,0,.65); z-index:9999; display:none;">
         <div id="pdfPreviewPanel" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
         width:86%; max-width:1000px; height:90%; max-height:900px;
         background:#fff; border-radius:6px; overflow:hidden;
         box-shadow:0 5px 25px rgba(0,0,0,.3);">

      <div style="position:absolute; top:8px; left:8px; z-index:2; display:flex; align-items:center; gap:4px;">
        <button id="pdfZoomOut" style="border:none; background:#6c757d; color:#fff; padding:3px 9px; border-radius:3px; cursor:pointer; font-size:16px; line-height:1;">&#8722;</button>
        <span id="pdfZoomValue" style="font-size:13px; font-weight:600; min-width:42px; text-align:center;">100%</span>
        <button id="pdfZoomIn"  style="border:none; background:#6c757d; color:#fff; padding:3px 9px; border-radius:3px; cursor:pointer; font-size:16px; line-height:1;">&#43;</button>
        <button id="pdfZoomReset" style="border:none; background:#adb5bd; color:#fff; padding:3px 8px; border-radius:3px; cursor:pointer; font-size:11px;">Reset</button>
      </div>
 
      <button id="closePdfPreview"
      style="position:absolute; right:8px; top:8px; z-index:2;
      border:none; background:#e74c3c; color:#fff;
      padding:4px 10px; border-radius:3px; cursor:pointer;">&times;</button>

      <div id="pdfPreviewViewport" style="position:absolute; top:40px; left:0; right:0; bottom:0; overflow:auto;">
      <iframe id="pdfPreviewFrame" src="" width="100%" height="100%" frameborder="0"
      style="position:absolute;
      top:0; left:0;
      transform-origin:top left;
      transition:transform .12s ease;">
      </iframe>
      </div>
    </div>
  </div>`).appendTo('body');

         let hoverTimer = null;

         function applyPreviewZoom() {
            const scale = previewZoom / 100;
            $('#pdfPreviewFrame').css({
               transform: 'scale(' + scale + ')',
               width: (100 / scale) + '%',
               height: (100 / scale) + '%'
            });
            $('#pdfZoomValue').text(previewZoom + '%');
         }

         function changeZoom(step) {
            previewZoom = Math.max(60, Math.min(220, previewZoom + step));
            applyPreviewZoom();
         }

         $(document).on('click', '#pdfZoomIn', function () { changeZoom(10); });
         $(document).on('click', '#pdfZoomOut', function () { changeZoom(-10); });
         $(document).on('click', '#pdfZoomReset', function () {
            previewZoom = defaultZoom;
            applyPreviewZoom();
         });

         $(document).on('wheel', '#pdfPreviewViewport', function (e) {
            e.preventDefault();
            changeZoom(e.originalEvent.deltaY < 0 ? 10 : -10);
         });

         $(document).on('mouseenter', '.view-file-preview', function () {
            const pdfPath = 'invoices/' + $(this).data('file');
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(function () {
               previewZoom = defaultZoom;
               applyPreviewZoom();
               $overlay.fadeIn(180);
               $('#pdfPreviewFrame').attr('src', pdfPath);
            }, 400);
         });

         $(document).on('mouseleave', '.view-file-preview', function () {
            clearTimeout(hoverTimer);
         });

         // close when clicking outside the white frame or on the × button
         $overlay.on('click', function (e) {
            if (e.target === this || e.target.id === 'closePdfPreview') {
               $overlay.fadeOut(150);
               $('#pdfPreviewFrame').attr('src', '');   // free memory
            }
         });
      });

   </script>

   <!-- ===== DataTables + Buttons (Copy/Excel/CSV/PDF/Print/Columns) ===== -->
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
   <script>
      jQuery(function ($) {
         if (!$.fn.DataTable.isDataTable('#invoice-billings-table')) {
            var table = $('#invoice-billings-table').DataTable({
               paging: false,
               searching: false,
               info: false,
               ordering: false,
               dom: 'Bt',
               buttons: [
                  { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copy', titleAttr: 'Copy', title: 'Invoices', className: 'btn btn-sm btn-secondary' },
                  { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', titleAttr: 'Excel', title: 'Invoices', className: 'btn btn-sm btn-success' },
                  { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', titleAttr: 'CSV', title: 'Invoices', className: 'btn btn-sm btn-info' },
                  { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', titleAttr: 'PDF', title: 'Invoices', orientation: 'landscape', pageSize: 'A4', className: 'btn btn-sm btn-danger' },
                  { extend: 'print', text: '<i class="fas fa-print"></i> Print', titleAttr: 'Print', title: 'Invoices', className: 'btn btn-sm btn-dark' },
                  { extend: 'colvis', text: '<i class="fas fa-columns"></i> Columns', className: 'btn btn-sm btn-primary' }
               ]
            });

            // Pull the auto-generated buttons row out from above the table and
            // place it on the same line as the search box.
            table.buttons().container().prependTo('#invoiceTableToolbarRight');
         }
      });
   </script>
</body>

</html>
