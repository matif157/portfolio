<?php
// =======================================================
// FILE: ajax/add_payment_modal.php
// PURPOSE:
// Invoice payment form inside popup modal.
//
// Difference:
// Old system:
// User leaves invoice page -> add_payment.php
//
// New CRM system:
// Payment popup opens on same invoice page.
//
// Existing database logic is preserved.
// =======================================================


require_once '../log_in.php';


// Login security

if (!isset($_SESSION['user_id'])) {

    exit("Unauthorized");

}



// Invoice ID popup se receive kar rahe hain

$invoice_id = $_GET['invoice_id'] ?? 0;




// Invoice information

$stmt = $pdo->prepare("
SELECT *

FROM invoices

WHERE id=?

");


$stmt->execute([$invoice_id]);


$inv=$stmt->fetch(PDO::FETCH_ASSOC);



if(!$inv){

    exit("
    <div class='error-box'>
    Invoice not found
    </div>
    ");

}





// Already received payment calculate karna

$payStmt=$pdo->prepare("
SELECT SUM(amount) total

FROM invoice_payments

WHERE invoice_id=?

");


$payStmt->execute([$invoice_id]);


$totalPaid=$payStmt->fetch()['total'] ?? 0;




// Remaining balance

$balance =
$inv['grand_total']
-
$totalPaid;



?>





<div class="payment-popup">


<h2>

Add Payment

</h2>



<p class="invoice-reference">

Invoice:

<strong>

#<?=htmlspecialchars($inv['invoice_number'])?>

</strong>


<br>


Remaining Balance:

<strong>

$
<?=number_format($balance,2)?>

</strong>


</p>





<form id="paymentForm">



<input 
type="hidden"
name="invoice_id"
value="<?=$invoice_id?>"
>




<div class="form-group">


<label>

Amount

</label>


<input

type="number"

step="0.01"

name="amount"

value="<?=$balance?>"

required

>


</div>







<div class="form-group">


<label>

Payment Date

</label>


<input

type="date"

name="payment_date"

value="<?=date('Y-m-d')?>"

>


</div>






<div class="form-group">


<label>

Payment Method

</label>



<select name="payment_method">


<option>
Zelle
</option>


<option>
Credit Card
</option>


<option>
Debit Card
</option>


<option>
Cash
</option>


<option>
Cheque
</option>


</select>


</div>








<div class="form-group">


<label>

Notes

</label>



<textarea

name="notes"

rows="3"

></textarea>


</div>







<button

type="submit"

class="save-payment-btn"

>


Save Payment


</button>



</form>



</div>







<script>


/*
=================================================
Payment AJAX Submit

Purpose:

Form submit hone par:

1. Page reload nahi hogi
2. Payment save hoga
3. Invoice status update hoga
4. Popup close hoga

=================================================
*/



document
.getElementById("paymentForm")
.addEventListener(
"submit",

function(e){


e.preventDefault();




let formData =
new FormData(this);





fetch(
"ajax/save_payment.php",
{

method:"POST",

body:formData

}

)



.then(res=>res.json())



.then(data=>{


if(data.success){



alert(
"Payment Added Successfully"
);



closeModal(
"paymentModal"
);



// Invoice table refresh

location.reload();



}

else{


alert(data.message);



}


});





}

);



</script>