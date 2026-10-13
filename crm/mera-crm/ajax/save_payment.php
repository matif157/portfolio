<?php
// =======================================================
// FILE:
// ajax/save_payment.php
//
// PURPOSE:
// Receive popup payment form
// Save payment
// Update invoice status
// Create activity log
// =======================================================



require_once '../log_in.php';



header(
'Content-Type: application/json'
);



if(!isset($_SESSION['user_id'])){


echo json_encode([

"success"=>false,

"message"=>"Unauthorized"

]);


exit;


}





try{


$invoice_id =
$_POST['invoice_id'];



$amount =
floatval(
$_POST['amount']
);



$date =
$_POST['payment_date'];



$method =
$_POST['payment_method'];



$notes =
$_POST['notes'];





if($amount<=0){


throw new Exception(
"Invalid amount"
);


}





// Payment insert


$stmt=$pdo->prepare("

INSERT INTO invoice_payments

(
invoice_id,
amount,
payment_date,
payment_method,
notes
)

VALUES (?,?,?,?,?)

");



$stmt->execute([

$invoice_id,

$amount,

$date,

$method,

$notes

]);







// Invoice total

$stmt=$pdo->prepare("

SELECT *

FROM invoices

WHERE id=?

");


$stmt->execute([$invoice_id]);


$invoice=$stmt->fetch(PDO::FETCH_ASSOC);







// Already paid amount

$stmt=$pdo->prepare("

SELECT SUM(amount)

FROM invoice_payments

WHERE invoice_id=?

");


$stmt->execute([$invoice_id]);


$paid =
$stmt->fetchColumn() ?? 0;







// Status calculation

$status="Unpaid";


if($paid >= $invoice['grand_total']){


$status="Paid";


}

elseif($paid>0){


$status="Partial";


}







$pdo->prepare("

UPDATE invoices

SET status=?

WHERE id=?

")

->execute([

$status,

$invoice_id

]);








// Activity log

 






echo json_encode([

"success"=>true

]);





}

catch(Exception $e){



echo json_encode([

"success"=>false,

"message"=>$e->getMessage()

]);



}