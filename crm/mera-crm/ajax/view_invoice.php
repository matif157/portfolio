<?php
// =======================================================
// FILE: ajax/view_invoice_modal.php
// PURPOSE:
// Invoice details ko popup ke andar show karna.
//
// Ye complete page nahi hai.
// Sirf modal ka content return karega.
//
// Jab user View button press karega:
// invoices.php AJAX se ye file call karega.
// =======================================================


require_once '../log_in.php';


// Security check
if (!isset($_SESSION['user_id'])) {

    exit("Unauthorized");

}



// Invoice ID receive kar rahe hain

$id = $_GET['id'] ?? 0;



// Invoice aur client information

$stmt = $pdo->prepare("
SELECT 
i.*,
c.full_name,
c.business_name,
c.email,
c.phone

FROM invoices i

LEFT JOIN clients c

ON i.client_id=c.client_id

WHERE i.id=?

");


$stmt->execute([$id]);


$invoice=$stmt->fetch(PDO::FETCH_ASSOC);



if(!$invoice){

    exit("
    <div class='error-box'>
    Invoice not found
    </div>
    ");

}





// Invoice items

$itemStmt=$pdo->prepare("
SELECT *

FROM invoice_items

WHERE invoice_id=?

");


$itemStmt->execute([$id]);


$items=$itemStmt->fetchAll(PDO::FETCH_ASSOC);




// Payments history

$payStmt=$pdo->prepare("
SELECT *

FROM invoice_payments

WHERE invoice_id=?

ORDER BY payment_date DESC

");


$payStmt->execute([$id]);


$payments=$payStmt->fetchAll(PDO::FETCH_ASSOC);



?>



<div class="invoice-popup">


<h2>
Invoice #<?=htmlspecialchars($invoice['invoice_number'])?>
</h2>



<div class="invoice-grid">


<div>

<strong>Client</strong>

<p>
<?=htmlspecialchars(
$invoice['business_name']
?:$invoice['full_name']
)?>
</p>

</div>




<div>

<strong>Status</strong>

<p>

<span class="invoice-status">

<?=htmlspecialchars($invoice['status'])?>

</span>

</p>

</div>





<div>

<strong>Created</strong>

<p>
<?=$invoice['created_date']?>
</p>

</div>




<div>

<strong>Due Date</strong>

<p>
<?=$invoice['due_date']?>
</p>

</div>


</div>





<hr>



<h3>
Services
</h3>



<table class="modal-table">


<tr>

<th>
Provider
</th>


<th>
Service
</th>


<th>
Amount
</th>


</tr>



<?php foreach($items as $item): ?>

<tr>

<td>
<?=htmlspecialchars($item['service_provider'])?>
</td>


<td>
<?=htmlspecialchars($item['service_name'])?>
</td>


<td>
$
<?=number_format($item['price'],2)?>
</td>


</tr>


<?php endforeach; ?>



</table>





<h3 style="margin-top:25px">

Payment History

</h3>



<table class="modal-table">


<tr>

<th>
Date
</th>


<th>
Method
</th>


<th>
Amount
</th>


</tr>



<?php foreach($payments as $p): ?>


<tr>

<td>
<?=$p['payment_date']?>
</td>


<td>
<?=$p['payment_method']?>
</td>


<td>
$
<?=number_format($p['amount'],2)?>
</td>


</tr>


<?php endforeach; ?>



</table>





<div class="invoice-total">


Total:

<strong>

$
<?=number_format(
$invoice['grand_total'],
2
)?>

</strong>


</div>



</div>