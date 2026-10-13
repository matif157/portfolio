<?php
// Establish your database connection
include 'includes/db.php';

header('Content-Type: application/json');

// Get invoice_id from AJAX request (cast to int — also prevents SQL injection)
$invoice_id = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;

// Query to fetch data
$query = "SELECT * FROM billing_payment_history WHERE inv_id = $invoice_id ORDER BY his_id ASC";
$result = mysqli_query($con, $query);

// Store data in an array
$data = array();
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

// Convert array to JSON format
echo json_encode($data);

// Note: do NOT call mysqli_close($con) here. PHP closes the connection at the
// end of the request anyway, and closing it early causes the db.php shutdown
// handler to fatal ("mysqli object is already closed"), which appends an HTML
// error block to this response and breaks JSON.parse() on the client.
