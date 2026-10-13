<?php
include 'includes/db.php';

if (isset($_POST['invoice_id'])) {
    $invoice_id = mysqli_real_escape_string($con, $_POST['invoice_id']);

    $query = "SELECT * FROM billing_invoice WHERE invoice_id='$invoice_id'";
    $result = mysqli_query($con, $query);

    if (mysqli_num_rows($result) > 0) {
        echo '<div class="container mt-3">';
        while ($invoice = mysqli_fetch_assoc($result)) {
            echo '<div class="card mb-3 shadow-sm" style="max-width: 300px;">';
            echo '<div class="card-body">';
           echo '<h5 class="card-title">
        <strong>Invoice ID:</strong> 
        <a href="client_billing.php?id=' . $invoice['mem_id'] . '">
            ' . $invoice['invoice_id'] . '
        </a>
      </h5>';

          echo '<p class="card-text">
        <strong>Member ID:</strong> 
        <a href="client_details.php?id=' . $invoice['mem_id'] . '&status=1">' . $invoice['mem_id'] . '</a>
      </p>';

            $fileUrl = 'invoices/' . $invoice['filelink'];
            echo '<a href="' . $fileUrl . '" class="btn btn-primary btn-sm" target="_blank">
                    <i class="fa-solid fa-file-arrow-down"></i> View Invoice
                  </a>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div style="text-align: center; margin: 20px; color: #ff9800; font-size: 20px;">No invoices found</div>';

    }
}
?>