<?php
// =======================================================
// FILE: ajax/save_invoice_edit.php
//
// PURPOSE:
// Save edited invoice from popup.
//
// Flow:
//
// invoices.php
//      |
//      | Edit Button
//      ↓
// edit_invoice_modal.php
//      |
//      | Submit AJAX
//      ↓
// save_invoice_edit.php
//
// Responsibilities:
//
// 1. Update invoice information
// 2. Remove old services
// 3. Insert updated services
// 4. Recalculate total
// 5. Create activity log
//
// =======================================================


require_once '../log_in.php';



// Response JSON format

header(
    "Content-Type: application/json"
);





// Security check

if(!isset($_SESSION['user_id'])){


    echo json_encode([

        "success"=>false,

        "message"=>"Unauthorized"

    ]);


    exit;

}





try{


// =======================================================
// Receive Form Data
// =======================================================


// Invoice ID

$id =
$_POST['invoice_id'];



// Client

$client_id =
$_POST['client_id'];



// Dates

$created_date =
$_POST['created_date'];


$due_date =
$_POST['due_date'];



// Status

$status =
$_POST['status'];




// Services arrays

$providers =
$_POST['provider'] ?? [];


$services =
$_POST['service'] ?? [];


$prices =
$_POST['price'] ?? [];






// =======================================================
// Calculate New Total
//
// Yahan hum services ke prices add kar rahe hain.
//
// Example:
//
// Service 1 = 100
// Service 2 = 200
//
// Total = 300
//
// Ye total invoice me save hoga.
// =======================================================


$total = 0;



foreach($prices as $price){


    $total += floatval($price);


}








// =======================================================
// Update Main Invoice
// =======================================================


$stmt=$pdo->prepare("

UPDATE invoices SET


client_id=?,

created_date=?,

due_date=?,

status=?,

sub_total=?,

grand_total=?


WHERE id=?


");



$stmt->execute([


$client_id,

$created_date,

$due_date,

$status,

$total,

$total,

$id


]);









// =======================================================
// Remove Previous Services
//
// Kyun?
//
// User ne edit me service change ki ho sakti hai.
//
// Example:
//
// Old:
// Website Design
//
// New:
// Website Design
// SEO
//
// Isliye old records delete karke
// fresh insert karte hain.
// =======================================================


$delete=$pdo->prepare("


DELETE FROM invoice_items

WHERE invoice_id=?


");


$delete->execute([$id]);








// =======================================================
// Insert Updated Services
// =======================================================


$itemStmt=$pdo->prepare("


INSERT INTO invoice_items


(

invoice_id,

service_provider,

service_name,

price

)


VALUES

(?,?,?,?)


");







for($i=0;$i<count($services);$i++){



    // Empty service ignore

    if(
        empty($services[$i])
    ){

        continue;

    }




    $itemStmt->execute([


        $id,


        $providers[$i] 
        ?? 
        'KZ(99)',


        $services[$i],


        floatval(
            $prices[$i] ?? 0
        )

    ]);



}








// =======================================================
// Activity Log
//
// Purpose:
//
// Future me admin dekh sake:
//
// Kisne invoice change kiya
// Kab change kiya
// Kya total tha
//
// =======================================================


 







echo json_encode([


"success"=>true,


"message"=>"Invoice updated"


]);







}

catch(Exception $e){



echo json_encode([


"success"=>false,


"message"=>$e->getMessage()


]);


}