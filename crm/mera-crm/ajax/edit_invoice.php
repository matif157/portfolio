<?php
// =======================================================
// FILE: ajax/edit_invoice_modal.php
//
// PURPOSE:
// Invoice edit form inside CRM popup.
//
// Old flow:
// invoices.php
//       ↓
// edit_invoice.php (new page)
//
// New flow:
// invoices.php
//       ↓
// Edit popup
//
// Same database logic.
// Better user experience.
//
// =======================================================


require_once '../log_in.php';



if(!isset($_SESSION['user_id'])){

    exit("Unauthorized");

}




// Invoice ID popup se receive karna

$id = $_GET['id'] ?? 0;




// Invoice information

$stmt=$pdo->prepare("

SELECT *

FROM invoices

WHERE id=?

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





// Existing services

$itemStmt=$pdo->prepare("

SELECT *

FROM invoice_items

WHERE invoice_id=?

");


$itemStmt->execute([$id]);


$items=$itemStmt->fetchAll(PDO::FETCH_ASSOC);






// Client list

$clients=$pdo->query("

SELECT 

client_id,

full_name,

business_name

FROM clients

ORDER BY full_name

")->fetchAll(PDO::FETCH_ASSOC);



?>



<div class="edit-invoice-popup">



<h2>

Edit Invoice

#

<?=htmlspecialchars($invoice['invoice_number'])?>

</h2>





<form id="editInvoiceForm">



<input

type="hidden"

name="invoice_id"

value="<?=$id?>"

>




<div class="form-grid">



<div>

<label>

Client

</label>



<select name="client_id">


<?php foreach($clients as $client): ?>


<option

value="<?=$client['client_id']?>"

<?=

$client['client_id']==$invoice['client_id']

?'selected'

:''

?>

>


<?=htmlspecialchars(

$client['business_name']

?:$client['full_name']

)?>


</option>



<?php endforeach; ?>


</select>


</div>







<div>


<label>

Status

</label>


<select name="status">


<option

<?=

$invoice['status']=="Unpaid"

?'selected':''

?>

>

Unpaid

</option>


<option

<?=

$invoice['status']=="Partial"

?'selected':''

?>

>

Partial

</option>


<option

<?=

$invoice['status']=="Paid"

?'selected':''

?>

>

Paid

</option>


</select>


</div>







<div>

<label>

Created Date

</label>


<input

type="date"

name="created_date"

value="<?=$invoice['created_date']?>"

>

</div>







<div>

<label>

Due Date

</label>


<input

type="date"

name="due_date"

value="<?=$invoice['due_date']?>"

>


</div>



</div>








<h3>

Services

</h3>




<div id="serviceContainer">



<?php foreach($items as $item): ?>



<div class="service-row">



<input

type="text"

name="provider[]"

value="<?=htmlspecialchars($item['service_provider'])?>"

placeholder="Provider"

>




<input

type="text"

name="service[]"

value="<?=htmlspecialchars($item['service_name'])?>"

placeholder="Service"

>




<input

type="number"

step="0.01"

name="price[]"

value="<?=$item['price']?>"

placeholder="Price"

>




<button

type="button"

class="remove-service"

onclick="this.parentElement.remove()"

>

X

</button>



</div>




<?php endforeach; ?>



</div>






<button

type="button"

class="add-service"

onclick="addServiceRow()"

>

+ Add Service

</button>







<br><br>





<button

class="save-invoice-btn"

>

Update Invoice

</button>





</form>


</div>







<script>


/*
================================================

Add New Service Row

Purpose:

Agar user invoice me extra service add karna chahta hai

to JavaScript new row create karegi.

================================================
*/


function addServiceRow(){



let container =
document.getElementById(
"serviceContainer"
);



let row=document.createElement(
"div"
);



row.className="service-row";



row.innerHTML=`

<input 
name="provider[]"
placeholder="Provider"
value="KZ(99)"
>


<input 
name="service[]"
placeholder="Service Name"
>


<input 
type="number"
name="price[]"
placeholder="Price"
>


<button

type="button"

class="remove-service"

onclick="this.parentElement.remove()"

>

X

</button>

`;



container.appendChild(row);


}









/*
================================================

Save Invoice

AJAX submit

No page reload

================================================
*/


document

.getElementById(
"editInvoiceForm"
)

.addEventListener(
"submit",

function(e){


e.preventDefault();



let data=new FormData(this);




fetch(

"ajax/save_invoice_edit.php",

{

method:"POST",

body:data

}

)



.then(
response=>response.json()
)



.then(result=>{


if(result.success){


alert(
"Invoice Updated Successfully"
);


closeModal(
"editInvoiceModal"
);



location.reload();



}

else{


alert(result.message);


}



});




});



</script>