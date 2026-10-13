<?php
include 'includes/db.php';
include 'includes/auth.php';
error_reporting(0);

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $user_id = intval($_GET['id']); // Convert to integer for security
} else {
    $user_id = $_SESSION['user_id'];
}

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

$stmt = $con->prepare("SELECT user_name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

$initials = getInitials($row['user_name']);

// Get statistics for dashboard cards
// Total clients
$clients_query = "SELECT COUNT(*) as total FROM members WHERE user_id = $user_id";
$clients_result = mysqli_query($con, $clients_query);
$clients_row = mysqli_fetch_assoc($clients_result);
$total_clients = $clients_row['total'];

// Total invoices
$invoices_query = "SELECT COUNT(*) as total FROM billing_invoice WHERE service_provider_id = $user_id";
$invoices_result = mysqli_query($con, $invoices_query);
$invoices_row = mysqli_fetch_assoc($invoices_result);
$total_invoices = $invoices_row['total'];



// Total orders
$orders_query = "SELECT COUNT(*) as total FROM orders WHERE assign_to = $user_id";
$orders_result = mysqli_query($con, $orders_query);
$orders_row = mysqli_fetch_assoc($orders_result);
$total_orders = $orders_row['total'];

// echo $initials . ' - ' . $user_id;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">
    <title>Syed Professional Services - Dashboard</title>
    <?php include "includes/links.php"; ?>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #ffffff;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dash-container {
            padding: 20px;
        }

        .table-responsive {
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            overflow: hidden;
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

        /* Dashboard Stats Cards */
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            flex: 1;
            min-width: 220px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
            color: white;
        }

        .stat-icon.clients {
            background-color: #6FD943;
        }

        .stat-icon.projects {
            background-color: #FF9800;
        }

        .stat-icon.invoices {
            background-color: #2196F3;
        }

        .stat-icon.orders {
            background-color: #9C27B0;
        }

        .stat-details h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }

        .stat-details p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #777;
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
                                <h4 class="m-b-10">Dashboard</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item active">Dashboard</li>
                            </ul>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Dashboard Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon clients">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $total_clients; ?></h3>
                        <p>Total Clients</p>
                    </div>
                </div>



                <div class="stat-card">
                    <div class="stat-icon invoices">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $total_invoices; ?></h3>
                        <p>Total Invoices</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?php echo $total_orders; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
            </div>

            <div class="section-title">
                <h5>Member Details</h5>
            </div>
            <div class="table-container table-responsive">
                <table id="membersTable" class="table table-striped table-bordered">
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
                        <?php
                        $query = "SELECT * FROM members WHERE user_id = '$user_id' ORDER BY date_added DESC";
                        $result = mysqli_query($con, $query);
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>
                                <td>" . $row['member_id'] . "</td>
                               <td>"
                                . $row['name'] . " " . $row['mname'] . " " . $row['lname']
                                . "<br><small>" . $row['bus_name'] . "</small>"
                                . "</td>
                                <td>" . $row['category'] . "</td>
                                
                                <td>" . $row['phone'] . "</td>
                                <td>" . $row['prm_email'] . "</td>
                               <td>" .
                                (!empty($row['client_since']) && $row['client_since'] !== '0000-00-00'
                                    && strtotime($row['client_since']) !== false
                                    ? date('m/d/Y', strtotime($row['client_since']))
                                    : 'N/A')
                                . "</td>

                               <td style='color:" . ($row['status'] ? 'green' : 'red') . "'>"
                                . ($row['status'] ? 'Active' : 'Inactive')
                                . "</td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="section-title">
                <h5>Invoice ➜ Sevice Provider</h5>
            </div>
            <div class="table-container table-responsive">
                <table id="invTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Member</th>
                            <!-- <th>Business</th> -->
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>File</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        /* ---- direct query ---- */
                        $sql = "
            SELECT  i.invoice_id,
                    i.invoice_date,
                    i.due_date,
                    i.total_amount,
                    i.paid_amount,
                    i.balance,
                    i.invoice_status,
                    i.filelink,
                    m.name, m.lname, m.bus_name
            FROM billing_invoice i
            JOIN members m ON m.member_id = i.mem_id
            WHERE i.service_provider_id = $user_id
            ORDER BY i.invoice_id DESC
        ";
                        $res = mysqli_query($con, $sql);

                        while ($row = mysqli_fetch_assoc($res)) {
                            /* ---- status badge ---- */
                            switch ((int) $row['invoice_status']) {
                                case 1: 
                                    $badge = '<span class="badge badge-danger">Un-paid</span>';
                                    break;
                                case 2:
                                    $badge = '<span class="badge bg-warning">Partial-paid</span>';
                                    break;
                                case 3:
                                    $badge = '<span class="badge bg-success">Paid</span>';
                                    break;
                                default:
                                    $badge = '<span class="badge bg-secondary">Unknown</span>';
                            }

                            echo "
                <tr>
                    <td>{$row['invoice_id']}</td>
                    <td>{$row['name']} {$row['lname']}<br><small>{$row['bus_name']}</small></td>
                    <td>" . (!empty($row['invoice_date']) ? date('m/d/Y', strtotime($row['invoice_date'])) : '') . "</td>
                    <td>" . (!empty($row['due_date']) ? date('m/d/Y', strtotime($row['due_date'])) : '') . "</td>
                    <td>\${$row['total_amount']}</td>
                    <td>\${$row['paid_amount']}</td>
                    <td>\${$row['balance']}</td>
                    <td><a href='invoices/{$row['filelink']}' target='_blank'>View</a></td>
                    <td class='text-center'>{$badge}</td>
                </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Orders section -->
            <div class="section-title">
                <h5>Products</h5>
            </div>
            <div class="table-container table-responsive">
                <table id="ordersTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Product</th>
                            <th>Renewal Cycle</th>
                            <th>Start Date</th>
                            <th>Next Renewal Date</th>
                            <th>Inserted By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "
                        SELECT  o.order_id,
                                o.bil_cyc_months,
                                o.start_date,
                                o.exp_date,
                                o.total,
                                o.order_status,
                                CONCAT(m.name,' ',m.lname) AS member,
                                m.bus_name,
                                mb.membership_name AS prod_name,
                                u.user_name AS inserted_by
                        FROM orders o
                        JOIN members m ON m.member_id = o.mem_id
                        JOIN memberships mb ON mb.membership_id = o.prod_id
                        JOIN users u ON u.user_id = o.user_id
                        WHERE o.assign_to = $user_id
                        ORDER BY o.order_id DESC
                        ";

                        $res = mysqli_query($con, $sql);
                        while ($row = mysqli_fetch_assoc($res)) {
                            echo "
                          <tr>
                              <td>{$row['member']}<br><small>{$row['bus_name']}</small></td>
                              <td>{$row['prod_name']}</td>
                              <td>{$row['bil_cyc_months']} Days</td>
                              <td>" . date('m/d/Y', strtotime($row['start_date'])) . "</td>
                              <td>" . date('m/d/Y', strtotime($row['exp_date'])) . "</td>
                              <td>{$row['inserted_by']}</td>
                          </tr>";
                        }

                        ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <!-- jQuery + DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script>
        $(document).ready(function () {
            $('#membersTable').DataTable({
                "pageLength": 10,
                "order": [[0, "desc"]],
                "language": {
                    "search": "<span style='color:#6FD943;'>Search:</span>"
                }
            });
        });
        $(document).ready(function () {
            $('#invTable').DataTable({
                "pageLength": 10,
                order: [[0, 'desc']],
                language: { search: "<span style='color:#6FD943;'>Search:</span>" }
            });
        });
        $('#ordersTable').DataTable({
            pageLength: 10,
            order: [[0, 'desc']],
            language: { search: "<span style='color:#6FD943;'>Search:</span>" }
        });
    </script>

</body>

</html>