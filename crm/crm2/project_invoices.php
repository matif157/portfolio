<?php
include 'includes/db.php';
include 'includes/auth.php';

$limit = 500;
$page = isset($_GET['start']) ? (int) $_GET['start'] : 0;

$sql = "
    SELECT 
        bi.invoice_id,
        bi.invoice_date,
        bi.due_date,
        bi.total_amount,
        bi.paid_amount,
        bi.balance,
        bi.discount,
        bi.invoice_status,
        bi.filelink,
        bi.mem_id,
        bi.service_provider_id,
        m.name, m.mname, m.lname, m.bus_name,
        u.user_name AS service_provider,
        GROUP_CONCAT(p.order_id SEPARATOR ', ') AS project_ids
    FROM billing_invoice bi
    JOIN members m ON m.member_id = bi.mem_id
    LEFT JOIN users u ON u.user_id = bi.service_provider_id
    INNER JOIN projects p ON FIND_IN_SET(bi.invoice_id, p.project_invoices)
    GROUP BY bi.invoice_id
    ORDER BY bi.invoice_date DESC
    LIMIT $limit";

$result = mysqli_query($con, $sql);

$extraCSS = '
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
';

 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Project-related Invoices</title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f6f9;
        }

        .dash-container {
            padding: 20px;
        }

        .page-header {
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: #333;
        }

        .table {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #6fd943;
            color: white;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .table td {
            vertical-align: middle;
        }

        .badge-success {
            background-color: #28a745 !important;
        }

        .badge-warning {
            background-color: #ffc107 !important;
            color: black !important;
        }

        .badge-danger {
            background-color: #dc3545 !important;
        }
    </style>
</head>

<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>
    <?php

    $limit = 500;
    $page = isset($_GET['start']) ? (int) $_GET['start'] : 0;

    $sql = "
        SELECT 
            bi.invoice_id,
            bi.invoice_date,
            bi.due_date,
            bi.total_amount,
            bi.paid_amount,
            bi.balance,
            bi.discount,
            bi.invoice_status,
            bi.filelink,
            bi.mem_id,
            bi.service_provider_id,
            m.name, m.mname, m.lname, m.bus_name,
            u.user_name AS service_provider,
            GROUP_CONCAT(p.order_id SEPARATOR ', ') AS project_ids
        FROM billing_invoice bi
        JOIN members m ON m.member_id = bi.mem_id
        LEFT JOIN users u ON u.user_id = bi.service_provider_id
        INNER JOIN projects p ON FIND_IN_SET(bi.invoice_id, p.project_invoices)
        GROUP BY bi.invoice_id
        ORDER BY bi.invoice_date DESC
        LIMIT $limit";

    $result = mysqli_query($con, $sql);
    ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <h4>Project-related Invoices</h4>
            </div>

            <div class="table-responsive">
                <table id="projInv" class="table table-bordered table-sm bg-white w-100">
                    <thead class="table-success">
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Business</th>
                            <th>Service Provider</th>
                            <th>Date</th>
                            <th>Due</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Projects</th>

                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $badge = $row['invoice_status'] == 3 ? 'success' : ($row['invoice_status'] == 2 ? 'warning' : 'danger');
                            $text = $row['invoice_status'] == 3 ? 'Paid' : ($row['invoice_status'] == 2 ? 'Partial' : 'Unpaid');
                            ?>
                            <tr>
                                <td><?= $row['invoice_id'] ?></td>
                                <td><?= htmlspecialchars(trim($row['name'] . ' ' . $row['mname'] . ' ' . $row['lname'])) ?>
                                </td>
                                <td><?= htmlspecialchars($row['bus_name']) ?></td>
                                <td><?= htmlspecialchars($row['service_provider']) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['invoice_date'])) ?></td>
                                <td><?= date('m/d/Y', strtotime($row['due_date'])) ?></td>
                                <td>$<?= number_format($row['total_amount'], 2) ?></td>
                                <td>$<?= number_format($row['paid_amount'], 2) ?></td>
                                <td>$<?= number_format($row['balance'], 2) ?></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= $text ?></span></td>
                                <td><?= $row['project_ids'] ?: '-' ?></td>

                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include "includes/scripts.php"; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(function () {
            $('#projInv').DataTable({
                paging: true,
                searching: true,
                lengthChange: true,
                pageLength: 25,
                language: { search: "Quick filter:" },
                responsive: true
            });
        });
    </script>
</body>

</html>