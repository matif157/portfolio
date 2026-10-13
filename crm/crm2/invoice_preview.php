<?php
include 'includes/db.php';
include "includes/auth.php";
error_reporting(0);

// Handle export option filtering
$exportOption = isset($_POST['export_option']) ? mysqli_real_escape_string($con, $_POST['export_option']) : 'all';
$type = isset($_POST['type']) && $_POST['type'] !== '' ? mysqli_real_escape_string($con, $_POST['type']) : 'all';
$start_date = isset($_POST['start_date']) && $_POST['start_date'] !== '' ? mysqli_real_escape_string($con, $_POST['start_date']) : '';
$end_date = isset($_POST['end_date']) && $_POST['end_date'] !== '' ? mysqli_real_escape_string($con, $_POST['end_date']) : '';

// Build query based on filter option
$base_query = "
    SELECT 
        billing_invoice.*, 
        members.name AS name, 
        members.mname AS mname,
        members.lname AS lname, 
        members.phone AS phone,
        members.bus_name AS bus_name
    FROM billing_invoice 
    JOIN members ON billing_invoice.mem_id = members.member_id
    WHERE members.status = 1";
$conditions = [];

switch ($exportOption) {
    case 'paid':
        $conditions[] = "billing_invoice.invoice_status = 3";
        $conditions[] = "members.ach_payer = 0"; // Exclude ACH payers
        break;
    case 'unpaid':
        $conditions[] = "billing_invoice.invoice_status = 1";
        $conditions[] = "members.ach_payer = 0"; // Exclude ACH payers
        break;
    case 'partialpaid':
        $conditions[] = "billing_invoice.invoice_status = 2";
        $conditions[] = "members.ach_payer = 0"; // Exclude ACH payers
        break;
    case 'ach':
        $conditions[] = "members.ach_payer = 1"; // Only ACH payers
        break;
    default:
        $conditions[] = "members.ach_payer = 0"; // Exclude ACH payers for 'all'
}

if ($type !== 'all') {
    $conditions[] = "members.type = '$type'";
}

if ($start_date && $end_date) {
    $conditions[] = "billing_invoice.due_date BETWEEN '$start_date' AND '$end_date'";
}

if ($conditions) {
    $base_query .= " AND " . implode(' AND ', $conditions);
}

$result = mysqli_query($con, $base_query);

if (!$result) {
    die("Database query failed: " . mysqli_error($con));
}
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services - Invoices Preview</title>
    <?php include "includes/links.php"; ?>
    <style>
        .invoice-preview-container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .table thead th {
            background-color: #6FD943 !important;
            color: black;
            text-align: center;
        }

        .table tbody tr {
            text-align: center;
        }

        .table tbody tr:nth-child(odd) {
            background-color: white;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .badge-warning {
            background-color: #ffc107;
            color: black;
        }

        .filter-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>

<body class="theme-3">
    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>
    <!-- [ Main Content ] start -->
    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Invoices Preview</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Invoices Preview</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <!-- [ Main Content ] start -->
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover datatable">
                                    <thead>
                                        <tr>
                                            <th>Client ID</th>
                                            <th>Client Name</th>
                                            <th>Business Name</th>
                                            <th>Phone</th>
                                            <th>Due Date</th>
                                            <th>Total Amount</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Invoice File</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($rrow = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><a href="client_billing.php?id=<?php echo $rrow['mem_id']; ?>"
                                                        class="btn btn-outline-primary">#<?php echo $rrow['mem_id']; ?></a>
                                                </td>
                                                <td><?php echo $rrow['name'] . " " . $rrow['mname'] . " " . $rrow['lname']; ?>
                                                </td>
                                                <td><?php echo $rrow['bus_name']; ?></td>
                                                <td>
                                                    <p
                                                        style="overflow: hidden; text-overflow: ellipsis; white-space: initial; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical;">
                                                        <?php echo $rrow['phone']; ?>
                                                    </p>
                                                </td>
                                                <td>
                                                    <?php echo date("D d M, Y", strtotime($rrow["due_date"])); ?>
                                                </td>
                                                <td>
                                                    <span>Total: <?php echo $rrow["total_amount"]; ?></span><br />
                                                    <span>Paid: <?php echo $rrow["balance"]; ?></span>
                                                </td>
                                                <td><?php echo $rrow["balance"]; ?></td>
                                                <td>
                                                    <?php
                                                    $fetch_status = mysqli_query($con, "SELECT * FROM billing_invoice_status WHERE status_id={$rrow['invoice_status']}");
                                                    $status_value = mysqli_fetch_assoc($fetch_status)['status_text'];
                                                    ?>
                                                    <?php if ($status_value == 'Not Paid') { ?>
                                                        <span class="badge badge-pill badge-danger">Unpaid</span>
                                                    <?php } elseif ($status_value == 'Partial Paid') { ?>
                                                        <span class="badge badge-pill badge-warning">Partial Paid</span>
                                                    <?php } elseif ($status_value == 'Paid') { ?>
                                                        <span class="badge badge-pill badge-success">Paid</span>
                                                    <?php } ?>
                                                </td>
                                                <td><a href="invoices/<?php echo $rrow["filelink"]; ?>">View File</a></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- [ Main Content ] end -->
                </div>
            </div>
        </div>
    </div>
    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        $(document).ready(function () {
            $('.datatable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "language": {
                    "search": "Search records:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
        });
    </script>
</body>

</html>

<?php
mysqli_close($con);
?>  