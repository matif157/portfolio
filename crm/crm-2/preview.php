<?php
// Include necessary files and initialize database connection
include 'includes/db.php';

// Get the selected option from the form
$exportOption = isset($_POST['export_option']) ? mysqli_real_escape_string($con, $_POST['export_option']) : 'all';

// Fetch data based on active or inactive status
switch ($exportOption) {
    case 'active':
        $query = "SELECT * FROM members WHERE status = 1";
        break;
    case 'inactive':
        $query = "SELECT * FROM members WHERE status = 0";
        break;
    default:
        $query = "SELECT * FROM members";
}

$result = mysqli_query($con, $query);

// Check for errors in the query execution
if (!$result) {
    die("Database query failed: " . mysqli_error($con));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Preview</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        body {
            background-color: #f4f4f9;
            font-family: 'Arial', sans-serif;
        }

        .container {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .table {
            margin-top: 20px;
        }

        .table thead th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table tbody tr {
            text-align: center;
        }

        .table tbody tr:nth-child(odd) {
            background-color: #f9f9f9;
        }

        .badge-success {
            background-color: #28a745;
            color: white;
        }

        .badge-danger {
            background-color: #dc3545;
            color: white;
        }

        .text-center {
            color: #555;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #007bff;
            border: none;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        /* Align the export button to the top-left */
        .export-button {
            position: absolute;
            top: 10px;
            left: 10px;
        }
    </style>
</head>

<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between mb-3">
            <h2>Member Preview</h2>
            <form action="export_to_excel.php" method="post">
                <input type="hidden" name="export_option" value="<?php echo htmlspecialchars($exportOption); ?>">
                <button type="submit" class="btn btn-success">Export to Excel</button>
            </form>
        </div>

        <!-- Display data in a table -->
        <table id="clientTable" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Name</th>
                    <th>File</th>
                    <th>SSN</th>
                    <th>EIN</th>
                    <th>TF</th>
                    <th>Phone</th>
                    <th>Cell Phone</th>
                    <th>Other Phone</th>
                    <th>Overseas Phone</th>
                    <th>Prm_Email</th>
                    <th>Sec_Email</th>

                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['member_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name'] . ' ' . $row['mname'] . ' ' . $row['lname']); ?></td>
                            <td><?php echo htmlspecialchars($row['file']); ?></td>
                            <td><?php echo htmlspecialchars($row['ss']); ?></td>
                            <td><?php echo htmlspecialchars($row['ein']); ?></td>
                            <td><?php echo htmlspecialchars($row['tf']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['cell_ph']); ?></td>
                            <td><?php echo htmlspecialchars($row['other_ph']); ?></td>
                            <td><?php echo htmlspecialchars($row['overseas_ph']); ?></td>
                            <td><?php echo htmlspecialchars($row['prm_email']); ?></td>
                            <td><?php echo htmlspecialchars($row['sec_email']); ?></td>
                            <td>
                                <?php echo $row['status'] == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center">No records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>


    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function () {
            $('#clientTable').DataTable({
                "paging": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "lengthMenu": [10, 25, 50, 100]
            });
        });
    </script>
</body>

</html>

<?php
// Close the database connection
mysqli_close($con);
?>