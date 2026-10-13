<?php
require_once 'dompdf/autoload.inc.php';
// reference the Dompdf namespace
  use Dompdf\Dompdf;
  include 'includes/db.php';
  include "includes/auth.php";


  $client_id='5951';

   $msg='';
    $variable='';
    $drow='';

    $time = time();
    $m = date('m');
    $curDate = date('m/d/Y');
    $curDateMySQL = date('Y-m-d');
    $currentMonth=date("Y-m");
    $new_invoice_date=date("Y-m-d h:i:s");
    $drows='';


     $client_query = mysqli_query($con,"SELECT * FROM billing_invoice where mem_id='5951' AND due_date<'2023-10-27'");
    
     while($fetchdata = mysqli_fetch_array($client_query)){
            $client_id = $fetchdata["mem_id"];
            $invoice_id = $fetchdata["invoice_id"];
            $billing_id = $fetchdata["billing_id"];
            $temp_id = $fetchdata["temp_id"];  
            $invoice_date = $fetchdata["invoice_date"];
            $filelink = $fetchdata["filelink"];
            $due_date = $fetchdata["due_date"];
            $price_single=$total_amount = $fetchdata["total_amount"];
            $paid_amount = $fetchdata["paid_amount"];
            $balance = $fetchdata["balance"];
            $invoice_status = $fetchdata["invoice_status"];
            $payment_type = $fetchdata["payment_type"];
            $payment_details = $fetchdata["payment_details"];
            $paid_date = $fetchdata["paid_date"];
            $billing_days = $fetchdata["billing_cycle"];


             echo "Found<br/>";

              $PDFFileN = "INV-".$client_id."-".$currentMonth."-".$time.".pdf";
              $Filename = "invoices/".$PDFFileN;

              $billing_month=$billing_days; 
              $totalDyas=$billing_month." days";

                   $date=date_create($curDateMySQL);
                   date_add($date,date_interval_create_from_date_string($totalDyas));
                   $duedate=date_format($date,"Y-m-d");

              $insert_billing="INSERT INTO `billing_invoice`(`mem_id`, `billing_id`, `temp_id`, `invoice_date`, `filelink`, `due_date`, `total_amount`, `paid_amount`, `balance`, `invoice_status`, `payment_type`, `payment_details`, `paid_date`,`billing_cycle`) VALUES ('$client_id','$billing_id','$temp_id','$new_invoice_date','$PDFFileN','$duedate','$total_amount','0','$total_amount','1','','','','$billing_days')"; 

             //   $insert_query = mysqli_query($con,$insert_billing);
                $invoice_no = mysqli_insert_id($con);
             
                 $client_query1 = mysqli_query($con,"SELECT * FROM members where member_id='$client_id'");
                 while($fetch_client = mysqli_fetch_array($client_query1)){
                    $name=$fetch_client['name']." ".$fetch_client['lname'];
                    $email=$fetch_client['prm_email'];
                    $address=$fetch_client['address'];
                    $phone=$fetch_client['phone'];
                 } 

     $client_inv_query = mysqli_query($con,"SELECT * FROM billing_invoice_items where inv_id='$invoice_id'");
      while($fetch_inv_client = mysqli_fetch_array($client_inv_query)){
                   
                    $item_description=$fetch_inv_client['item_description'];
                    $total_price=$fetch_inv_client['total_price'];
                    $drows.='<tr class="item"><td>'.$item_description.'</td><td>$'.$total_price.'</td></tr>'; 
                 } 

  $content='<style>
            .invoice-box {
               position:relative;
               height:99%;
               margin: auto;
               border: 1px solid #eee;
               box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
               font-size: 16px;
               line-height: 24px;
               color: #555;
               }
               
               
               .invoice-box h3 {
                  position:absolute;
                  bottom:-15px;
                  left:10px;
               }

               .invoice-box table {
                  line-height: inherit;
                  text-align: left;
                  border-collapse: collapse;
               }

               .invoice-box table td {
                  padding: 5px;
                  vertical-align: top;
               }

               .invoice-box table tr td:nth-child(2) {
                  text-align: right;
               }

               .invoice-box table tr.top table td {
                  padding-bottom: 20px;
               }

               .invoice-box table tr.top table td.title {
                  font-size: 45px;
                  line-height: 45px;
                  color: #333;
               }

               .invoice-box table tr.information table td {
                  padding-bottom: 40px;
               }

               .invoice-box table tr.heading td {
                  background: #8b742a;
                  border-bottom: 1px solid #ddd;
                  font-weight: bold;
                  color: white;
                  font-size:22px;
               }

               .invoice-box table tr.details td {
                  padding-bottom: 20px;
               }

               .invoice-box table tr.item td {
                  border-bottom: 1px solid #eee;
               }

               .invoice-box table tr.item.last td {
                  border-bottom: none;
               }

               .invoice-box table tr.total td:nth-child(2) {
                  border-top: 2px solid #eee;
                  font-weight: bold;
               }
            </style>
            <div class="invoice-box">
               <table style="width:100%">
                  <tr class="top">
                     <td colspan="2">
                        <table style="width:100%">
                           <tr>
                              <td class="title">
                                 <img src="http://192.168.15.55/storage/uploads/logo/logo-dark.png" alt="Company logo" style="width: 60%; max-width: 300px" />
                              </td>

                              <td>
                                 Invoice #: '.$invoice_no.'<br />
                                 Created: '.$curDateMySQL.'<br />
                                 Due: '.$duedate.'
                              </td>
                           </tr>
                        </table>
                     </td>
                  </tr>

                  <tr class="information">
                     <td colspan="2">
                        <table style="width:100%">
                           <tr>
                              <td>
                                <b>From:</b> <br /> 
                                 Syed Professional Services<br />
                                 (718) 859-5392<br />
                                 invoice@syedpro.com<br />
                                 https://syedpro.com/
                              </td>

                              <td>
                              <b>To:</b> <br />
                                 '.$name.'.<br />
                                 '.$address.'<br />
                                 '.$phone.'<br />
                                 '.$email.'<br />
                              </td>
                           </tr>
                        </table>
                     </td>
                  </tr>

                  <tr class="heading">
                     <td>Service</td>

                     <td>Price</td>
                  </tr>'.$drows.'
                  <tr class="total">
                          <td></td>

                          <td style="font-size:20px;font-weight:bold;">Total: $'.$price_single.'</td>
                      </tr>
                  </table>
                  <h3><u>Thank you for choosing Syed Professional Services<u/></h3>
                  </div>';



          // instantiate and use the dompdf class
          $dompdf = new Dompdf(array('enable_remote' => true));
          $dompdf->loadHtml("$content");
          $dompdf->render();

          $output = $dompdf->output();
          file_put_contents($Filename, $output);

            

     }









?>