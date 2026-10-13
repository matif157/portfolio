<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include 'includes/db.php';
include 'includes/auth.php';
include 'includes/activity_log.php';
include 'includes/customizer.php';

$clients = [];
$drows = '';
$current_date = date('m/d/Y');

$clients_result = mysqli_query($con, "SELECT member_id, name, mname, lname, bus_name FROM members");
while ($row = mysqli_fetch_assoc($clients_result)) {
    $clients[] = $row;
}
$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'];

$showInvoiceModal = $current_user_type == 1 && isset($_GET['status']) && $_GET['status'] === 'created' && isset($_GET['project_id']);

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

// Lookup helpers used to build human-readable project change history
function projectUserName($con, $id)
{
    $id = (int) $id;
    if (!$id) {
        return 'Unassigned';
    }
    $row = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id = $id LIMIT 1"));
    return $row ? $row['user_name'] : "User #$id";
}

function projectClientName($con, $id)
{
    $id = (int) $id;
    if (!$id) {
        return 'N/A';
    }
    $row = mysqli_fetch_assoc(mysqli_query($con, "SELECT name, lname, bus_name FROM members WHERE member_id = $id LIMIT 1"));
    if (!$row) {
        return "Client #$id";
    }
    $name = trim($row['name'] . ' ' . $row['lname']);
    if (!empty($row['bus_name'])) {
        $name = $name !== '' ? $name . ' (' . $row['bus_name'] . ')' : $row['bus_name'];
    }
    return $name !== '' ? $name : "Client #$id";
}

function projectProductName($con, $id)
{
    $id = (int) $id;
    if (!$id) {
        return 'N/A';
    }
    $row = mysqli_fetch_assoc(mysqli_query($con, "SELECT membership_name FROM memberships WHERE membership_id = $id LIMIT 1"));
    return $row ? $row['membership_name'] : "Product #$id";
}

function getProjectInvoices($con, $project_id)
{
    $invoices = [];

    $project_query = mysqli_query($con, "SELECT project_invoices FROM projects WHERE order_id = '$project_id'");
    $project_data = mysqli_fetch_assoc($project_query);
    $invoice_ids = $project_data['project_invoices'];

    if (!empty($invoice_ids)) {
        $ids = explode(',', $invoice_ids);
        $id_list = "'" . implode("','", $ids) . "'";

        $query = "SELECT invoice_id, invoice_date, filelink, invoice_status, due_date, total_amount, 
                         paid_amount, balance, discount, mem_id, service_provider_id, payment_details 
                  FROM billing_invoice 
                  WHERE invoice_id IN ($id_list)
                  ORDER BY invoice_date DESC";
        $result = mysqli_query($con, $query);
        while ($row = mysqli_fetch_assoc($result)) {
            $invoices[] = $row;
        }
    }
    return $invoices;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['complete_project'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $comment = mysqli_real_escape_string($con, $_POST['completion_comment']);

        $query = "UPDATE projects SET project_status = 'completed', project_updated_at = NOW() WHERE order_id = '$project_id'";
        $project_completed = mysqli_query($con, $query);

        $comment_query = "INSERT INTO project_comments (project_id, user_id, comment, created_at) 
                          VALUES ('$project_id', '$current_user_id', '$comment', NOW())";
        mysqli_query($con, $comment_query);

        if ($project_completed) {
            logActivity($con, $current_user_id, 'UPDATE', 'project', (int) $project_id, "Marked project #$project_id as completed");
        }

        header("Location: show_projects.php?status=completed&tab=completed");
        exit();
    }

    if (isset($_POST['reopen_project'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $query = "UPDATE projects SET project_status = 'open', project_updated_at = NOW() WHERE order_id = '$project_id'";
        $project_reopened = mysqli_query($con, $query);
        if ($project_reopened) {
            logActivity($con, $current_user_id, 'UPDATE', 'project', (int) $project_id, "Reopened project #$project_id");
        }
        header("Location: show_projects.php?status=reopened");
        exit();
    }

    if (isset($_POST['change_assignee'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);

        // Snapshot the current values so we can record what actually changed
        $old = mysqli_fetch_assoc(mysqli_query(
            $con,
            "SELECT assign_to, mem_id, prod_id, project_title, start_date, exp_date
             FROM projects WHERE order_id = '$project_id' LIMIT 1"
        ));

        $updates = [];
        $changes = [];

        if (!empty($_POST['new_assignee'])) {
            $new_assignee = mysqli_real_escape_string($con, $_POST['new_assignee']);
            $updates[] = "assign_to = '$new_assignee'";
            if ($old && (string) $old['assign_to'] !== (string) $new_assignee) {
                $changes[] = "Reassigned from " . projectUserName($con, $old['assign_to'])
                    . " to " . projectUserName($con, $new_assignee);
            }
        }

        if (!empty($_POST['new_client'])) {
            $new_client = mysqli_real_escape_string($con, $_POST['new_client']);
            $updates[] = "mem_id = '$new_client'";
            if ($old && (string) $old['mem_id'] !== (string) $new_client) {
                $changes[] = "Client changed from " . projectClientName($con, $old['mem_id'])
                    . " to " . projectClientName($con, $new_client);
            }
        }

        if (!empty($_POST['new_product'])) {
            $new_product = mysqli_real_escape_string($con, $_POST['new_product']);
            $updates[] = "prod_id = '$new_product'";
            if ($old && (string) $old['prod_id'] !== (string) $new_product) {
                $changes[] = "Product changed from " . projectProductName($con, $old['prod_id'])
                    . " to " . projectProductName($con, $new_product);
            }
        }

        if (!empty($_POST['project_title'])) {
            $project_title = mysqli_real_escape_string($con, $_POST['project_title']);
            $updates[] = "project_title = '$project_title'";
            if ($old && (string) $old['project_title'] !== (string) $_POST['project_title']) {
                $changes[] = "Title changed from \"" . $old['project_title'] . "\" to \"" . $_POST['project_title'] . "\"";
            }
        }

        if (!empty($_POST['project_description'])) {
            $project_description = mysqli_real_escape_string($con, $_POST['project_description']);
            $updates[] = "project_description = '$project_description'";
            $changes[] = "Description updated";
        }

        if (!empty($_POST['start_date'])) {
            $start_date = mysqli_real_escape_string($con, $_POST['start_date']);
            $updates[] = "start_date = '$start_date'";
            if ($old && (string) $old['start_date'] !== (string) $start_date) {
                $changes[] = "Start date changed to " . $start_date;
            }
        }

        if (!empty($_POST['exp_date'])) {
            $exp_date = mysqli_real_escape_string($con, $_POST['exp_date']);
            $updates[] = "exp_date = '$exp_date'";
            if ($old && (string) $old['exp_date'] !== (string) $exp_date) {
                $changes[] = "Due date changed to " . $exp_date;
            }
        }

        if (!empty($updates)) {
            $updates[] = "project_updated_at = NOW()";
            $update_query = "UPDATE projects SET " . implode(', ', $updates) . " WHERE order_id = '$project_id'";
            $project_updated = mysqli_query($con, $update_query);
            if ($project_updated) {
                $desc = !empty($changes) ? implode('; ', $changes) : "Updated project #$project_id details";
                logActivity($con, $current_user_id, 'UPDATE', 'project', (int) $project_id, $desc);
            }
        }

        // Persist custom (EAV) field values for this project.
        $cuzCustom = function_exists('customizer_custom_fields') ? customizer_custom_fields($con, 'projects') : array();
        if (!empty($cuzCustom)) {
            $cuzVals = customizer_extract_custom_values($cuzCustom);
            customizer_save_record_values($con, 'projects', (int) $project_id, $cuzVals, $current_user_id, ($_SESSION['fullname'] ?? ''));
        }

        header("Location: show_projects.php?status=assignee_changed");
        exit();
    }

    if (isset($_POST["createinvoice"])) {
        $time = time();
        $m = date('m');
        $curDateMySQL = date('m/d/Y');
        $currentMonth = date("Y-m");

        $client_id = mysqli_real_escape_string($con, $_POST['client_id']);
        $project_id = isset($_POST['project_id']) ? mysqli_real_escape_string($con, $_POST['project_id']) : 0;

        $PDFFileN = "INV-" . $client_id . "-" . $currentMonth . "-" . $time . ".pdf";
        $Filename = "invoices/" . $PDFFileN;

        $services = $_POST['service'];
        $price = $_POST['unit_price'];
        $description = $_POST['description'];

        // Create a function to clean and format numbers
        $cleanNumber = function ($value) {
            if (is_numeric($value))
                return (float) $value;
            return (float) str_replace(['$', ','], '', $value);
        };

        // Use the cleaner function
        $sub_total = $cleanNumber($_POST['sub_total']);
        $discount = $cleanNumber($_POST['discount']);
        $grand_total = $cleanNumber($_POST['grand_total']);

        // Format consistently
        $sub_total = number_format($sub_total, 2, '.', '');
        $discount = number_format($discount, 2, '.', '');
        $grand_total = number_format($grand_total, 2, '.', '');

        $discountRow = '';

        $service_provider_id = $_POST['Service_Provider'];
        $service_provider_name = '';

        if (!empty($service_provider_id)) {
            $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
            if ($sp_result = mysqli_fetch_assoc($sp_query)) {
                $service_provider_name = $sp_result['user_name'];
            }
        }
        $service_provider_name = getInitials($service_provider_name);

        $user_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $current_user_id) . "'");
        if ($user_result = mysqli_fetch_assoc($user_query)) {
            $user_initials = getInitials($user_result['user_name']);
        } else {
            $user_initials = 'NA';
        }

        // Remove redundant formatting checks
        if ($discount > 0) {
            $discountRow = ' <tr class="total">
                    <td></td>
                    <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
                </tr>';
        }

        $size = count($services);

        $invoice_date = date("m/d/Y h:i:s");
        $invoice_date2 = date("Y-m-d h:i:s");
        $duedate = $_POST['billing_cycle'];

        $insert_billing = "INSERT INTO billing_invoice(mem_id, billing_id, temp_id, invoice_date, filelink, due_date, total_amount, paid_amount,discount, balance, invoice_status, payment_type, payment_details, paid_date,billing_cycle, service_provider_id) 
                   VALUES ('$client_id','0','0','$invoice_date','$PDFFileN','$duedate','$sub_total','0','$discount','$grand_total','1','0','0','$duedate','', '$service_provider_id')";
        $insert_query = mysqli_query($con, $insert_billing);
        $invoice_no = mysqli_insert_id($con);

        if ($project_id) {
            $project_query = mysqli_query($con, "SELECT project_invoices FROM projects WHERE order_id = '$project_id'");
            $project_data = mysqli_fetch_assoc($project_query);
            $existing_invoices = $project_data['project_invoices'];

            $new_invoices = $existing_invoices ? $existing_invoices . ',' . $invoice_no : $invoice_no;

            $update_query = "UPDATE projects SET project_invoices = '$new_invoices' WHERE order_id = '$project_id'";
            mysqli_query($con, $update_query);
        }

        $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$client_id'");
        while ($fetch_client = mysqli_fetch_array($client_query)) {
            $bus_name = $fetch_client['bus_name'];
            $dba = $fetch_client['dba'];
            if ($bus_name != '') {
                $name = $bus_name;
                $address = $fetch_client['address'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $email = $fetch_client['prm_email'];
            } else if ($dba != '') {
                $name = $dba;
                $address = $fetch_client['address'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $email = $fetch_client['prm_email'];
            } else {
                $name = $fetch_client['name'] . " " . $fetch_client['lname'];
                $email = $fetch_client['sec_email'];
                $address = $fetch_client['hstreet'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $phone = $fetch_client['phone'];
            }
            $phone = $fetch_client['cell_ph'];
        }

        for ($i = 0; $i < $size; $i++) {
            $servicess = $services[$i];
            // Clean and format each price
            $priceee = $cleanNumber($price[$i]);
            $priceee = number_format($priceee, 2, '.', '');

            $descriptionn = $description[$i];
            $drows .= '<tr class="item">
               <td><b>' . $services[$i] . '</b><p style="font-size:12px;margin:0px">' . $descriptionn . '</p></td>
               <td>$' . $priceee . '</td>
            </tr>';
            $que = "INSERT INTO `billing_invoice_items`(`inv_id`, `temp_id`, `billing_id`, `mem_id`, `item_description`, `item_sub_description`, `unit_price`, `quantity`, `total_price`, `date_added`, `user_id`, `item_date`) VALUES ('$invoice_no', 0, 0,'$client_id','$servicess','$descriptionn','$priceee','1','$priceee','$invoice_date2','$current_user_id','$invoice_date2')";
            $insert_queryy = mysqli_query($con, $que);
        }

        $originalFormat = 'Y-m-d';
        $desiredFormat = 'm/d/Y';
        $dateTime = DateTime::createFromFormat($originalFormat, $duedate);
        if ($dateTime) {
            $duedate = $dateTime->format($desiredFormat);
        }

        $content = '<style>
.invoice-box {
  position:relative;
  height:99%;
  width:100%;
  margin: auto;
  border: 1px solid #eee;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  font-size: 16px;
  line-height: 24px;
  color: #555;
  }
  
  
.invoice-box .footer-note {
  position: absolute;
  bottom: -20px;  /* Adjust this value to fit both lines at the bottom */
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 14px;
}

.invoice-box .footer-note p {
  margin: 0;
  font-size: 14px;
  white-space: nowrap; /* Keeps the bank fee message on a single line */
}

.invoice-box .footer-note h4 {
  margin-top: 5px;  /* Adds space between the two messages */
  font-size: 16px;
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
                           <img src="http://192.168.15.55/storage/uploads/logo/logo-dark.png" alt="Company logo " style="width: 60%; max-width: 300px" />
                        </td>
                        <td>
                           Invoice #: ' . $invoice_no . '<br />
                           By: ' . $user_initials . '(' . $current_user_id . ')<br/>
                           Created: ' . $curDateMySQL . '<br />
                           Due: ' . $duedate . '<br />
                           <img src="http://192.168.15.55/storage/uploads/logo/unpaid.jpg" alt="Company logo" style="width: 15%;margin-top:12px; max-width: 300px" />
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
                           1012 Coney Island Ave<br/>Brooklyn, NY 11230<br />
                           Phone: (718) 859-5392<br />
                           Fax: (718) 859-2237<br />
                           invoice@syedpro.com<br />
                        </td>
                        <td>
                        <b>To:</b> <br />
                           ' . $name . '<br />
                           ' . $address . '<br />
                           ' . $address1 . '<br />
                           ' . $phone . '<br />
                           ' . $email . '<br />
                        </td>
                     </tr>
                  </table>
               </td>
            </tr>
            <tr class="heading">
               <td>Service</td>
               <td>Price</td>
            </tr>
           <tr class="details">
  <td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td>
</tr>
            ' . $drows . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Sub Total: $' . $sub_total . '</td>
                </tr>' . $discountRow . '
             <tr class="total">
                    <td></td>
                    <td style="font-weight:bold;">Grand Total: $' . $grand_total . '</td>
                </tr>  
                <tr class="details">
         <td><b>Description:</b></td>
      </tr>
            </table>
           <div class="footer-note">
  <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.</p>
  <h4><u>Thank you for choosing Syed Professional Services</u></h4>
</div>
            </div>';
        $dompdf = new Dompdf(array('enable_remote' => true));
        $dompdf->loadHtml("$content");
        $dompdf->render();
        $output = $dompdf->output();
        file_put_contents($Filename, $output);

        if (!empty($project_id)) {
            logActivity($con, $current_user_id, 'CREATE', 'project', (int) $project_id, "Created invoice #$invoice_no from project #$project_id");
        }

        header("Location: show_projects.php?status=invoice_created&project_id=$project_id");
        exit();
    }

    // Add Payment Handler
    if (isset($_POST['addpayment'])) {
        $msg = '';
        $newstatus = '';
        $invoice_id = $_POST['invoice_id'];
        $totalamount = $_POST['totalamount'];
        $balance = $_POST['balance'];
        $recievedamount = $_POST['recievedamount'];
        $paidamount = $_POST['paidamount'];
        $duedate = $_POST['duedate'];
        $memberid = $_POST['memberid'];
        $date = date_create($duedate);
        $drows = '';
        $after_discount = $_POST['after_discount'];
        $old_discount = $_POST['old_discount'];

        $discount1 = $after_discount - $old_discount;
        $newpaid = $recievedamount;
        $newbalance = $balance - $newpaid - $discount1;
        $newpaid = $newpaid + $paidamount;
        $description = $_POST['description'];
        $payment_types = [];
        if (!empty($_POST['payment_type'])) {
            $payment_types = json_decode($_POST['payment_type'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $payment_types = is_array($_POST['payment_type']) ? $_POST['payment_type'] : [$_POST['payment_type']];
            }
        } elseif (!empty($_POST['payment_types'])) {
            $payment_types = is_array($_POST['payment_types']) ? $_POST['payment_types'] : [$_POST['payment_types']];
        }

        $payment_types = array_filter(array_map('trim', $payment_types));
        $payment_methods_str = !empty($payment_types) ?
            implode(', ', array_map('htmlspecialchars', $payment_types)) :
            'N/A';

        $invoice_query = mysqli_query($con, "SELECT service_provider_id FROM billing_invoice WHERE invoice_id='$invoice_id'");
        $invoice_data = mysqli_fetch_assoc($invoice_query);
        $service_provider_id = $invoice_data['service_provider_id'] ?? null;
        $service_provider_name = '';

        if (!empty($service_provider_id)) {
            $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
            if ($sp_result = mysqli_fetch_assoc($sp_query)) {
                $service_provider_name = $sp_result['user_name'];
            }
        }
        $service_provider_name = getInitials($service_provider_name);

        $filepath = '';

        if ($newbalance <= 0) {
            $newstatus = '3';
            $filepath .= '<img src="http://192.168.15.55/storage/uploads/logo/paid.png" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
            $status_text = 'Paid';
        } elseif ($newbalance < $totalamount) {
            $newstatus = '2';
            $filepath .= '<img src="http://192.168.15.55/storage/uploads/logo/partial_payment.jpg" alt="Company logo"  style="width: 30%;margin-top:12px; max-width: 300px" />';
            $status_text = 'Partial Paid';
        } else {
            $newstatus = '1';
            $filepath .= '<img src="http://192.168.15.55/storage/uploads/logo/unpaid.jpg" alt="Company logo" style="width: 30%;margin-top:12px; max-width: 300px" />';
            $status_text = 'UnPaid';
        }

        $time = time();
        $m = date('m');
        $curDate = date('m/d/Y');
        $curDateMySQL = date('m/d/Y');
        $currentMonth = date("Y-m");

        $PDFFileN = "INV-" . $currentMonth . "-" . $time . ".pdf";
        $Filename = "invoices/" . $PDFFileN;


        // Get client details
        $client_query = mysqli_query($con, "SELECT * FROM members where member_id='$memberid'");
        while ($fetch_client = mysqli_fetch_array($client_query)) {
            $bus_name = $fetch_client['bus_name'];
            $dba = $fetch_client['dba'];
            if ($bus_name != '') {
                $name = $bus_name;
                $address = $fetch_client['address'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $email = $fetch_client['prm_email'];
            } else if ($dba != '') {
                $name = $dba;
                $address = $fetch_client['address'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $email = $fetch_client['prm_email'];
            } else {
                $name = $fetch_client['name'] . " " . $fetch_client['lname'];
                $email = $fetch_client['prm_email'];
                $address = $fetch_client['hstreet'];
                $address1 = $fetch_client['hcity'] . ", " . $fetch_client['hstate'] . ", " . $fetch_client['hzip'];
                $phone = $fetch_client['phone'];
            }
            $phone = $fetch_client['cell_ph'];
        }

        $drows = '';
        $fetch_item_query = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id'");
        while ($fetch_inv_items = mysqli_fetch_array($fetch_item_query)) {
            $drows .= '<tr class="item">
                   <td>
                       <b>' . htmlspecialchars($fetch_inv_items['item_description']) . '</b>
                       <p style="font-size:12px;margin:0px">' . htmlspecialchars($fetch_inv_items['item_sub_description']) . '</p>
                   </td>
                   <td>$' . number_format($fetch_inv_items['unit_price'], 2) . '</td>
                </tr>';
        }

        function getNameInitials($name)
        {
            $initials = '';
            $parts = explode(' ', $name);
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper($part[0]);
                }
            }
            return $initials;
        }

        $initials_name = getNameInitials($_SESSION["fullname"]);
        $user_initials = $initials_name . '(' . $current_user_id . ')';

        $totalamount = number_format($totalamount, 2);
        $newbalance = number_format($newbalance, 2);
        $newpaid = number_format($newpaid, 2);


        $queryy = "UPDATE billing_invoice SET 
               total_amount='$totalamount', 
               balance='$newbalance', 
               paid_amount='$newpaid', 
               invoice_status='$newstatus', 
               filelink='$PDFFileN', 
               payment_details='$description', 
               discount='$after_discount' 
               WHERE invoice_id='$invoice_id'";

        $insert_query = mysqli_query($con, $queryy);
        $date2 = date('Y-m-d h:i:s');
        $query = "INSERT INTO `billing_payment_history` (`mem_id`, `inv_id`, `paid`, `balance`, `detail`, `date`,`employee`) 
              VALUES('$memberid','$invoice_id','$newpaid','$balance','$description','$date2','$user_initials')";

        mysqli_query($con, $query);

        if ($insert_query) {
            $originalFormat = 'Y-m-d';
            $desiredFormat = 'm/d/Y';
            $dateTime = DateTime::createFromFormat($originalFormat, $duedate);
            if ($dateTime) {
                $duedate = $dateTime->format($desiredFormat);
            }

            $discountRow = '';
            $discount = number_format($totalamount - ($newpaid + $newbalance), 2);

            if ($discount > 0) {
                $discountRow = '<tr class="total">
                <td></td>
                <td style="font-size:20px;font-weight:bold;">Discount: $' . $discount . '</td>
            </tr>';
            }

            $content = '
                <style>
        .invoice-box {
            position:relative;
            height:99%;
            width:100%;
            margin: auto;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            color: #555;
        }
       .invoice-box .footer-note {
  position: absolute;
  bottom: -20px;
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 14px;
}

.invoice-box .footer-note p {
  margin: 0;
  font-size: 14px;
  white-space: nowrap; 
}

.invoice-box .footer-note h4 {
  margin-top: 5px; 
  font-size: 16px;
}
        .invoice-box table {
            width: 100%;
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
        .invoice-box table tr.heading td {
            background: #8b742a;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            color: white;
            font-size:22px;
        }
        .invoice-box table tr.item td {
            border-bottom: 1px solid #eee;
        }
        .invoice-box table tr.total td:nth-child(2) {
            border-top: 2px solid #eee;
            font-weight: bold;
        }
        </style>
                <div class="invoice-box">
                    <table>
                        <tr class="top">
                            <td colspan="2">
                                <table>
                                    <tr>
                                        <td class="title">
                                            <img src="http://192.168.15.55/storage/uploads/logo/logo-dark.png" style="width:60%; max-width:300px;">
                                        </td>
                                        <td>
                                            Invoice #: ' . $invoice_id . '<br>
                                            By: ' . $user_initials . ' <br>
                                            Created: ' . $curDateMySQL . '<br>
                                            Due: ' . $duedate . '<br>
                                            ' . $status_text . ': ' . $current_date . '<br>
                                            ' . $filepath . '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr class="information">
                            <td colspan="2">
                                <table>
                                    <tr>
                                     <td>
                                             <b>From:</b> <br /> 
                                              Syed Professional Services<br />
                                              1012 Coney Island Ave<br/>Brooklyn, NY 11230<br />
                                              Phone: (718) 859-5392<br />
                                              Fax: (718) 859-2237<br />
                                              invoice@syedpro.com<br />
                                     </td>
                                        <td>
                                            To:<br>
                                            ' . $name . '<br>
                                            ' . $address . '<br>
                                            ' . $address1 . '<br>
                                            ' . $phone . '<br>
                                            ' . $email . '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr class="heading">
                            <td>Service</td>
                            <td>Price</td>
                        </tr>
                       <tr class="details">
  <td><b>Service Provider:</b> ' . $service_provider_name . ' (' . $service_provider_id . ')</td>
</tr>
                        ' . $drows . '
                        <tr class="total">
                            <td></td>
                            <td>Total: $' . $totalamount . '</td>
                        </tr>
                        <tr class="total">
                            <td></td>
                            <td>Paid: $' . $newpaid . '</td>
                        </tr>
                        ' . $discountRow . '
                        <tr class="total">
                            <td></td>
                            <td>Balance: $' . $newbalance . '</td>
                        </tr>
                        <tr class="details">
                            <td><b>Description:</b> ' . htmlspecialchars($description) . '</td>
                        </tr>
                        <tr class="details">
    <td><b>Payment Method(s):</b> ' . $payment_methods_str . '</td>
</tr>

                    </table>
                    <div class="footer-note">
                        <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.</p>
                        <h4><u>Thank you for choosing Syed Professional Services</u></h4>
                    </div>
                </div>';

            $dompdf = new Dompdf(array('enable_remote' => true));
            $dompdf->loadHtml($content);
            $dompdf->render();
            $output = $dompdf->output();
            file_put_contents($Filename, $output);

            $msg .= "<div class='alert alert-success'>Payment Has Been Added.</div>";
        } else {
            $msg .= "<div class='alert alert-danger'>Something Went Wrong.</div>" . mysqli_error($con);
        }

        if (!empty($_POST['return_to']) && $_POST['return_to'] === 'project_details') {
            $rpid = isset($_POST['return_project_id']) ? (int) $_POST['return_project_id'] : 0;
            if ($rpid > 0) {
                header("Location: project_details.php?id=$rpid&status=payment_added");
                exit();
            }
        }
    }

    // Edit Invoice Handler
    if (isset($_POST['update_invoice'])) {
        $invoice_id = $_POST['invoice_id'];
        $due_date = $_POST['edit_due_date'];
        $total_amount = $_POST['edit_total_amount'];
        $paid_amount = $_POST['edit_paid_amount'];
        $balance = $_POST['edit_balance'];
        $description = $_POST['description'];
        $user_id = $_SESSION["user_id"];
        $discount = $_POST['edit_discount'];

        $orig = mysqli_fetch_assoc(mysqli_query($con, "SELECT mem_id FROM billing_invoice WHERE invoice_id = $invoice_id LIMIT 1"));
        $client_id = (int) ($orig['mem_id'] ?? 0);
        $service_provider_id = $_POST['edit_service_provider'];

        $service_provider_name = '';

        if (!empty($service_provider_id)) {
            $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $service_provider_id) . "'");
            if ($sp_result = mysqli_fetch_assoc($sp_query)) {
                $service_provider_name = $sp_result['user_name'];
            }
        }
        $service_provider_name = getInitials($service_provider_name);

        function getNameInitials($name)
        {
            $initials = '';
            $parts = explode(' ', $name);
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $initials .= strtoupper($part[0]);
                }
            }
            return $initials;
        }

        $initials_name = getNameInitials($_SESSION["fullname"]);
        $user_initials = $initials_name . '(' . $current_user_id . ')';
        // First get all items from the old invoice before deleting it
        $items_query = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id'");
        $invoice_items = array();
        while ($item = mysqli_fetch_assoc($items_query)) {
            $invoice_items[] = $item;
        }

        $delete_query = "DELETE FROM billing_invoice WHERE invoice_id = '$invoice_id'";
        if (mysqli_query($con, $delete_query)) {
            $curDateMySQL = date('m/d/Y');
            $PDFFileN = "INV-" . $client_id . "-" . date("Y-m") . "-" . time() . ".pdf";
            $Filename = "invoices/" . $PDFFileN;

            if ($paid_amount >= $total_amount) {
                $payment_status = 3;
            } elseif ($paid_amount > 0 && $paid_amount < $total_amount) {
                $payment_status = 2;
            } else {
                $payment_status = 1;
            }

            $insert_billing = "INSERT INTO billing_invoice(mem_id, billing_id, temp_id, invoice_date, filelink, due_date, total_amount, paid_amount, discount, balance, invoice_status, payment_type, payment_details, paid_date, billing_cycle, service_provider_id)
                           VALUES ('$client_id', '0', '0', NOW(), '$PDFFileN', '$due_date', '$total_amount', '$paid_amount', '$discount', '$balance', '$payment_status', '0', '0', '$due_date', '', '$service_provider_id')";

            if (mysqli_query($con, $insert_billing)) {
                $new_invoice_id = mysqli_insert_id($con);
                mysqli_query($con, "
    UPDATE projects
    SET project_invoices = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', project_invoices, ','), ',$invoice_id,', ',$new_invoice_id,'))
    WHERE FIND_IN_SET('$invoice_id', project_invoices)
");
                // Reinsert all items with the new invoice ID
                foreach ($invoice_items as $item) {
                    $insert_item = "INSERT INTO billing_invoice_items(inv_id, temp_id, billing_id, mem_id, item_description, item_sub_description, unit_price, quantity, total_price, date_added, user_id, item_date)
                               VALUES ('$new_invoice_id', '0', '0', '$client_id', 
                                      '" . mysqli_real_escape_string($con, $item['item_description']) . "', 
                                      '" . mysqli_real_escape_string($con, $item['item_sub_description']) . "', 
                                      '" . $item['unit_price'] . "', 
                                      '" . $item['quantity'] . "', 
                                      '" . $item['total_price'] . "', 
                                      NOW(), 
                                      '$user_id', 
                                      NOW())";
                    mysqli_query($con, $insert_item);
                }

                $status_query = "SELECT invoice_status FROM billing_invoice WHERE invoice_id='$new_invoice_id'";
                $status_result = mysqli_query($con, $status_query);
                $status_row = mysqli_fetch_assoc($status_result);
                $payment_status = $status_row['invoice_status'];

                switch ($payment_status) {
                    case '3':
                        $status_text = "Paid";
                        $image_src = "http://192.168.15.55/storage/uploads/logo/paid.png";
                        break;
                    case '2':
                        $status_text = "Partial Paid";
                        $image_src = "http://192.168.15.55/storage/uploads/logo/partial_payment.jpg";
                        break;
                    case '1':
                    default:
                        $status_text = "Unpaid";
                        $image_src = "http://192.168.15.55/storage/uploads/logo/unpaid.jpg";
                        break;
                }

                // Fetch client details
                $client_query = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
                $client_details = mysqli_fetch_array($client_query);
                $name = $client_details['bus_name'] ?: $client_details['dba'] ?: $client_details['name'] . " " . $client_details['lname'];
                $address = $client_details['address'];
                $address1 = $client_details['hcity'] . ", " . $client_details['hstate'] . ", " . $client_details['hzip'];
                $email = $client_details['prm_email'];
                $phone = $client_details['cell_ph'];

                // Fetch items for the new invoice
                $items_query = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$new_invoice_id'");
                $drows = '';
                while ($item = mysqli_fetch_array($items_query)) {
                    $drows .= '<tr class="item">
                          <td><b>' . htmlspecialchars($item['item_description']) . '</b><p style="font-size:12px;margin:0px">' . htmlspecialchars($item['item_sub_description']) . '</p></td>
                          <td>$' . number_format($item['unit_price'], 2) . '</td>
                       </tr>';
                }

                // Prepare content for the PDF
                $content = <<<EOD
<style>
.invoice-box {
  position:relative;
  height:99%;
  width:100%;
  margin: auto;
  border: 1px solid #eee;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
  font-size: 16px;
  line-height: 24px;
  color: #555;
  }
  
  
.invoice-box .footer-note {
  position: absolute;
  bottom: -20px;  /* Adjust this value to fit both lines at the bottom */
  left: 50%;
  transform: translateX(-50%);
  text-align: center;
  font-size: 14px;
}

.invoice-box .footer-note p {
  margin: 0;
  font-size: 14px;
  white-space: nowrap; /* Keeps the bank fee message on a single line */
}

.invoice-box .footer-note h4 {
  margin-top: 5px;  /* Adds space between the two messages */
  font-size: 16px;
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
                          Invoice #: {$new_invoice_id}<br />
                          By: {$user_initials}({$current_user_id})<br />
                          Created: {$curDateMySQL}<br />
                          Edit By: {$user_initials}({$current_user_id})<br />
                          Due: {$due_date}<br />
                          {$status_text}: $current_date <br/>
                          <img src="{$image_src}" alt="Company logo" style="width: 40%; margin-top: 12px; max-width: 300px" /> 
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
                           1012 Coney Island Ave<br/>Brooklyn, NY 11230<br />
                           Phone: (718) 859-5392<br />
                           Fax: (718) 859-2237<br />
                           invoice@syedpro.com<br />
                        </td>
                      <td>
                          <b>To:</b> <br />
                          {$name}.<br />
                          {$address}<br />
                          {$address1}<br />
                          {$phone}<br />
                          {$email}<br />
                      </td>
                  </tr>
              </table>
          </td>
      </tr>
      <tr class="heading">
          <td>Service</td>
          <td>Price</td>
      </tr>
      <tr class="details">
          <td><b>Service Provider:</b> {$service_provider_name} ({$service_provider_id})</td>
      </tr>
      {$drows}
      <tr class="total">
          <td></td>
          <td>Discount: \${$discount}</td>
      </tr>
      <tr class="total">
          <td></td>
          <td>Total: \${$total_amount}</td>
      </tr>
      <tr class="total">
          <td></td>
          <td>Paid: \${$paid_amount}</td>
      </tr>
      <tr class="total">
          <td></td>
          <td>Balance: \${$balance}</td>
      </tr>
      <tr class="details">
          <td><b>Description:</b> {$description}</td> 
      </tr>
  </table>
  <div class="footer-note">
      <p>In the event of a returned or unsuccessful payment, a bank fee of \$25 will be applied.</p>
      <h4><u>Thank you for choosing Syed Professional Services</u></h4>
  </div>
</div>
EOD;

                // Generate PDF
                $dompdf = new Dompdf(array('enable_remote' => true));
                $dompdf->loadHtml($content);
                $dompdf->render();
                $output = $dompdf->output();
                file_put_contents($Filename, $output);

                $msg .= "<div class='alert alert-success'>Invoice updated and new invoice created successfully.</div>";
            } else {
                $msg .= "<div class='alert alert-danger'>Error creating new invoice: " . mysqli_error($con) . "</div>";
            }
        } else {
            $msg .= "<div class='alert alert-danger'>Error deleting previous invoice: " . mysqli_error($con) . "</div>";
        }
    }

    if (isset($_POST['select_existing_invoice'])) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $invoice_id = mysqli_real_escape_string($con, $_POST['invoice_id']);

        $project_query = mysqli_query($con, "SELECT mem_id, project_invoices FROM projects WHERE order_id = '$project_id'");
        if ($project_query && mysqli_num_rows($project_query) > 0) {
            $project_data = mysqli_fetch_assoc($project_query);
            $project_client_id = $project_data['mem_id'];
            $existing_invoices = $project_data['project_invoices'];

            $invoice_check_query = mysqli_query($con, "SELECT mem_id FROM billing_invoice WHERE invoice_id = '$invoice_id' LIMIT 1");
            if (!$invoice_check_query || mysqli_num_rows($invoice_check_query) == 0) {
                echo "<div class='alert alert-danger'>Invoice not found.</div>";
                exit();
            }
            $invoice_data = mysqli_fetch_assoc($invoice_check_query);
            if ((string) $invoice_data['mem_id'] !== (string) $project_client_id) {
                echo "<div class='alert alert-warning'>Only invoices from this project's client can be attached.</div>";
                exit();
            }

            $invoice_ids = $existing_invoices ? explode(',', $existing_invoices) : [];
            if (!in_array($invoice_id, $invoice_ids)) {
                $new_invoices = $existing_invoices ? $existing_invoices . ',' . $invoice_id : $invoice_id;
                $update_query = "UPDATE projects SET project_invoices = '$new_invoices' WHERE order_id = '$project_id'";
                if (mysqli_query($con, $update_query)) {
                    logActivity($con, $current_user_id, 'UPDATE', 'project', (int) $project_id, "Attached invoice #$invoice_id to project #$project_id");
                    if (!empty($_POST['return_to']) && $_POST['return_to'] === 'project_details') {
                        header("Location: project_details.php?id=$project_id&status=invoice_associated");
                    } else {
                        header("Location: show_projects.php?status=invoice_associated&project_id=$project_id");
                    }
                    exit();
                } else {
                    echo "<div class='alert alert-danger'>Error: " . mysqli_error($con) . "</div>";
                }
            } else {
                echo "<div class='alert alert-warning'>Invoice already associated.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Project not found.</div>";
        }
    }
    
    if (isset($_POST['detach_invoice']) && $current_user_type == 1) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);
        $invoice_id = mysqli_real_escape_string($con, $_POST['invoice_id']);
        
        $detach_query = "UPDATE projects 
                        SET project_invoices = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', project_invoices, ','), ',$invoice_id,', ','))
                        WHERE order_id = '$project_id'";
        
        if (mysqli_query($con, $detach_query)) {
            logActivity($con, $current_user_id, 'UPDATE', 'project', (int) $project_id, "Detached invoice #$invoice_id from project #$project_id");
            if (!empty($_POST['return_to']) && $_POST['return_to'] === 'project_details') {
                header("Location: project_details.php?id=$project_id&status=invoice_detached");
            } else {
                header("Location: show_projects.php?status=invoice_detached&project_id=$project_id");
            }
            exit();
        } else {
            $error_message = urlencode(mysqli_error($con));
            header("Location: show_projects.php?status=error&message=$error_message");
            exit();
        }
    }

    if (isset($_POST['delete_project']) && $current_user_type == 1) {
        $project_id = mysqli_real_escape_string($con, $_POST['project_id']);

        // Begin transaction
        mysqli_begin_transaction($con);

        try {
            // Delete associated comments
            $delete_comments_query = "DELETE FROM project_comments WHERE project_id = '$project_id'";
            if (!mysqli_query($con, $delete_comments_query)) {
                throw new Exception("Error deleting project comments: " . mysqli_error($con));
            }

            // Delete the project
            $delete_project_query = "DELETE FROM projects WHERE order_id = '$project_id'";
            if (!mysqli_query($con, $delete_project_query)) {
                throw new Exception("Error deleting project: " . mysqli_error($con));
            }

            // Commit transaction
            mysqli_commit($con);
            logActivity($con, $current_user_id, 'DELETE', 'project', (int) $project_id, "Deleted project #$project_id");
            header("Location: show_projects.php?status=project_deleted");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($con);
            $error_message = urlencode($e->getMessage());
            header("Location: show_projects.php?status=error&message=$error_message");
            exit();
        }
    }

}
$perms = [
    'edit_project' => 'No',
    'delete_project' => 'No',
    'mark_project_completed' => 'No',
    'create_invoice' => 'No',
    'view_invoices' => 'No',
    'reopen_project' => 'No',
    'view_all_projects' => 'No'
];
$res = mysqli_query(
    $con,
    "SELECT edit_project, delete_project, mark_project_completed,
            create_invoice, view_invoices, reopen_project, view_all_projects
     FROM   user_permissions
     WHERE  user_id = '{$_SESSION['user_id']}'
     LIMIT  1"
);
if ($res && mysqli_num_rows($res)) {
    $perms = mysqli_fetch_assoc($res);
}

// Admins (user_type 1) always see everything; other staff need the
// "View All Staff Projects" permission to see beyond their own projects.
$can_view_all = ($current_user_type == 1) || (($perms['view_all_projects'] ?? 'No') === 'Yes');
// Admins always see project invoices; other staff need the "View Invoices" permission.
$can_view_invoices = ($current_user_type == 1) || (($perms['view_invoices'] ?? 'No') === 'Yes');
// How many projects to show per page
$limit = 10;

// What page are we on? (default is 1)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;

// Calculate offset for SQL
$completed_page = isset($_GET['completed_page']) && is_numeric($_GET['completed_page']) ? (int) $_GET['completed_page'] : 1;
$completed_offset = ($completed_page - 1) * $limit;
$offset = ($page - 1) * $limit;
$users_result = mysqli_query($con, "SELECT user_id, user_name FROM users order by user_name asc");
$clients_result = mysqli_query($con, "SELECT member_id, name FROM members");
$products_result = mysqli_query($con, "SELECT membership_id, membership_name FROM memberships order by membership_name");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>All Projects</title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* DataTables professional layout tweaks */
        .dt-toolbar-buttons div.dt-buttons {
            display: flex;
            width: 100%;
            margin-bottom: 0;
            gap: 8px;
        }

        div.dt-buttons .dt-button {
            flex: 1 1 0;
            margin: 0;
            border-radius: 5px;
            text-align: center;
            justify-content: center;
            padding-top: 7px;
            padding-bottom: 7px;
        }

        div.dt-buttons .dt-button span {
            width: 100%;
            text-align: center;
        }

        div.dt-buttons .dt-button i {
            margin-right: 4px;
        }

        /* Brand navy background for export buttons */
        div.dt-buttons .dt-button {
            background: #252161 !important;
            color: #fff !important;
            border: 1px solid #252161 !important;
            font-weight: 500;
        }

        div.dt-buttons .dt-button:hover,
        div.dt-buttons .dt-button:focus {
            background: #1b1850 !important;
            color: #fff !important;
            box-shadow: 0 2px 5px rgba(37, 33, 97, 0.35);
        }

        /* Column-visibility dropdown menu (clean light style) */
        .dt-button-collection {
            background: #fff !important;
            border: 1px solid #e3e3ec !important;
            border-radius: 8px !important;
            padding: 6px !important;
            box-shadow: 0 8px 22px rgba(37, 33, 97, 0.18) !important;
            min-width: 200px;
        }

        .dt-button-collection .dt-button {
            display: block !important;
            flex: none !important;
            width: 100% !important;
            margin: 2px 0 !important;
            text-align: left !important;
            background: #fff !important;
            color: #252161 !important;
            border: none !important;
            border-radius: 5px !important;
            box-shadow: none !important;
            padding: 7px 12px !important;
        }

        .dt-button-collection .dt-button span {
            text-align: left !important;
        }

        .dt-button-collection .dt-button:hover,
        .dt-button-collection .dt-button:focus {
            background: #f2f1f8 !important;
            color: #252161 !important;
            box-shadow: none !important;
        }

        .dt-button-collection .dt-button.active {
            background: #eceafa !important;
            color: #252161 !important;
        }

        /* Keep length menu, export buttons and search on a single row */
        .dt-toolbar-buttons {
            text-align: center;
        }

        .dataTables_wrapper .dataTables_filter {
            text-align: right;
            margin: 0;
            padding-right: 4px;
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_filter label {
            width: 100%;
            margin: 0;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
            max-width: 260px;
            box-sizing: border-box;
            margin: 0;
        }

        .dataTables_wrapper .dataTables_length {
            margin: 0;
        }

        table.dataTable thead th {
            white-space: nowrap;
        }

        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #ced4da;
            border-radius: 5px;
            padding: 5px 10px;
        }

        .dataTables_wrapper .dataTables_length {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #555;
        }

        .dataTables_wrapper .dataTables_length label {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 5px 28px 5px 10px;
            background-color: #fff;
            color: #333;
            font-size: 0.9rem;
            line-height: 1.4;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23555'%3E%3Cpath d='M4 6l4 4 4-4'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .dataTables_wrapper .dataTables_length select:focus {
            outline: none;
            border-color: #252161;
            box-shadow: 0 0 0 0.15rem rgba(37, 33, 97, 0.25);
        }

        td.child-invoice-cell {
            background-color: #f8f9fa;
        }
    </style>


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

        .nav-tabs {
            border-bottom: 2px solid #252161;
        }

        .nav-tabs .nav-link {
            border: none;
            padding: 10px 20px;
            font-weight: bold;
            color: #252161;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background-color: #252161;
            color: white;
            border-radius: 5px 5px 0 0;
        }

        .table {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background-color: #252161;
            color: white;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        .table td {
            vertical-align: middle;
        }

        .invoice-badge {
            margin: 3px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .invoice-badge.paid {
            background-color: #28a745;
            color: white;
        }

        .invoice-badge.unpaid {
            background-color: #dc3545;
            color: white;
        }

        .invoice-badge.other {
            background-color: #6c757d;
            color: white;
        }

        .project-invoices {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-top: 10px;
        }

        .invoice-table th {
            font-size: 0.85rem;
        }

        .invoice-status {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .toggle-invoices {
            font-size: 0.9rem;
        }

        .btn-sm {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
        }

        .invoice-actions {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        @media (max-width: 768px) {

            .table th,
            .table td {
                font-size: 0.8rem;
                padding: 8px;
            }

            .invoice-actions {
                justify-content: flex-start;
            }

            .btn-sm {
                width: 100%;
                margin-bottom: 5px;
            }

            .modal-dialog {
                max-width: 95%;
            }
        }

        @media (max-width: 576px) {
            .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.9rem;
            }

            .page-header {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <h4>Project List</h4>
            </div>

            <?php if (isset($_GET['status'])): ?>
                <div class="alert alert-<?php echo ($_GET['status'] == 'error') ? 'danger' : 'success'; ?>">
                    <?php
                    if ($_GET['status'] == 'created')
                        echo "Project created successfully!";
                    elseif ($_GET['status'] == 'completed')
                        echo "Project marked as completed!";
                    elseif ($_GET['status'] == 'reopened')
                        echo "Project reopened!";
                    elseif ($_GET['status'] == 'assignee_changed')
                        echo "Project updated successfully!";
                    elseif ($_GET['status'] == 'invoice_created')
                        echo "Invoice created successfully!";
                    elseif ($_GET['status'] == 'invoice_associated')
                        echo "Invoice associated successfully!";
                    elseif ($_GET['status'] == 'invoice_detached')
                        echo "Attached invoice removed from project successfully!";
                    elseif ($_GET['status'] == 'project_deleted')
                        echo "Project deleted successfully!";
                    elseif ($_GET['status'] == 'error')
                        echo "Error: " . htmlspecialchars(urldecode($_GET['message']));
                    ?>
                </div>
            <?php endif; ?>
            <div class="col-md-12 mt-4">
                <div class="row">
                    <?php
                    $user_type = $_SESSION['user_type'];
                    $user_id = $_SESSION['user_id'];
                    $base_query = "SELECT 
                     (SELECT COUNT(*) FROM projects) AS total_projects,
                     (SELECT COUNT(*) FROM projects WHERE project_status = 'open') AS open_projects,
                     (SELECT COUNT(*) FROM projects WHERE project_status = 'completed') AS completed_projects,
                     (SELECT COUNT(*) FROM projects WHERE project_invoices IS NOT NULL AND project_invoices <> '') AS project_invoices";
                    if ($user_type != '1' && $user_type != '2' && !$can_view_all) {
                        $base_query = "SELECT
                        (SELECT COUNT(*) FROM projects WHERE assign_to = '$user_id') AS total_projects,
                        (SELECT COUNT(*) FROM projects WHERE project_status = 'open' AND assign_to = '$user_id') AS open_projects,
                        (SELECT COUNT(*) FROM projects WHERE project_status = 'completed' AND assign_to = '$user_id') AS completed_projects,
                        (SELECT COUNT(*) FROM projects WHERE project_invoices IS NOT NULL AND project_invoices <> '' AND assign_to = '$user_id') AS project_invoices";
                    }
                    $result = mysqli_query($con, $base_query);
                    if (!$result) {
                        echo "Query Error: " . mysqli_error($con);
                        $data = ['total_projects' => 0, 'open_projects' => 0, 'completed_projects' => 0, 'project_invoices' => 0];
                    } else {
                        $data = mysqli_fetch_assoc($result);
                        $data = array_map('intval', $data);
                    }
                    ?>
                    <div class="col-lg-3 col-6">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="theme-avtar bg-primary">
                                        <i class="ti ti-briefcase"></i>
                                    </div>
                                    <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                    <a href="show_projects.php">
                                        <h6 class="mb-3">Total Projects</h6>
                                    </a>
                                    <h3 class="mb-0"><?php echo $data['total_projects']; ?></h3>
                                </div>
                                <div style="flex: 1; display: flex; justify-content: flex-end;">
                                    <canvas id="totalProjectsChart" style="height: 200px; width: 100%;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="theme-avtar bg-info">
                                        <i class="ti ti-folder"></i>
                                    </div>
                                    <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                    <a href="show_projects.php">
                                        <h6 class="mb-3">Open Projects</h6>
                                    </a>
                                    <h3 class="mb-0"><?php echo $data['open_projects']; ?></h3>
                                </div>
                                <div style="flex: 1; display: flex; justify-content: flex-end;">
                                    <canvas id="openProjectsChart" style="height: 200px; width: 100%;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="card">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="theme-avtar bg-success">
                                        <i class="ti ti-check"></i>
                                    </div>
                                    <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                    <a href="show_projects.php">
                                        <h6 class="mb-3">Completed Projects</h6>
                                    </a>
                                    <h3 class="mb-0"><?php echo $data['completed_projects']; ?></h3>
                                </div>
                                <div style="flex: 1; display: flex; justify-content: flex-end;">
                                    <canvas id="completedProjectsChart" style="height: 200px; width: 100%;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($user_type == '1' || $user_type == '2') { ?>
                        <div class="col-lg-3 col-6">
                            <div class="card">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="theme-avtar bg-warning">
                                            <i class="ti ti-file-invoice"></i>
                                        </div>
                                        <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                        <a href="show_projects.php">
                                            <h6 class="mb-3">Project Invoices</h6>
                                        </a>
                                        <h3 class="mb-0"><?php echo $data['project_invoices']; ?></h3>
                                    </div>
                                    <div style="flex: 1; display: flex; justify-content: flex-end;">
                                        <canvas id="projectInvoicesChart" style="height: 200px; width: 100%;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($can_view_all): ?>
                <div class="card mb-3" style="box-shadow: rgba(100, 100, 111, 0.2) 0px 7px 29px 0px;">
                    <div class="card-body">
                        <form method="GET" action="" id="filterForm">
                            <input type="hidden" name="tab"
                                value="<?= isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'open' ?>">
                            <div class="row">
                                <div class="col-md-5">
                                    <label class="form-label">Filter by User</label>
                                    <select name="filter_user" class="form-select select2">
                                        <option value="">All Users</option>
                                        <?php
                                        mysqli_data_seek($users_result, 0);
                                        while ($user = mysqli_fetch_assoc($users_result)):
                                            $selected = (isset($_GET['filter_user']) && $_GET['filter_user'] == $user['user_id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $user['user_id'] ?>" <?= $selected ?>>
                                                <?= htmlspecialchars($user['user_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Filter by Product</label>
                                    <select name="filter_product" class="form-select select2">
                                        <option value="">All Products</option>
                                        <?php
                                        mysqli_data_seek($products_result, 0);
                                        while ($product = mysqli_fetch_assoc($products_result)):
                                            $selected = (isset($_GET['filter_product']) && $_GET['filter_product'] == $product['membership_id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $product['membership_id'] ?>" <?= $selected ?>>
                                                <?= htmlspecialchars($product['membership_name']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                    <a href="show_projects.php?tab=<?= isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'open' ?>"
                                        class="btn btn-secondary">Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <ul class="nav nav-tabs mb-3" id="projectTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] === 'open') ? 'active' : '' ?>"
                        id="open-tab" data-bs-toggle="tab" data-bs-target="#open-projects" type="button" role="tab">Open
                        Projects</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button
                        class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] === 'completed') ? 'active' : '' ?>"
                        id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-projects" type="button"
                        role="tab">Completed Projects</button>
                </li>
            </ul>

            <div class="tab-content" id="projectTabsContent">
                <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] === 'open') ? 'show active' : '' ?>"
                    id="open-projects" role="tabpanel">
                        <table id="openProjectsTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Project ID</th>
                                    <th>Client</th>
                                    <th>Business</th>
                                    <th>Product</th>
                                    <th>Title</th>
                                    <th>Assigned To</th>
                                    <th>Start Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <?php if ($current_user_type == 1): ?>
                                        <th>Invoices</th>
                                    <?php endif; ?>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Optimized: Fetch all open projects first
                                $open_projects = [];
                                $open_query = "SELECT p.*, m.name AS client_name, m.mname, m.lname, m.bus_name, pr.membership_name AS product_name, u.user_name AS assigned_to 
               FROM projects p
               JOIN members m ON p.mem_id = m.member_id
               JOIN memberships pr ON p.prod_id = pr.membership_id
               JOIN users u ON p.assign_to = u.user_id
               WHERE p.project_status = 'open'";

                                // Admin filters
                                if ($can_view_all) {
                                    if (!empty($_GET['filter_user'])) {
                                        $filter_user = mysqli_real_escape_string($con, $_GET['filter_user']);
                                        $open_query .= " AND p.assign_to = '$filter_user'";
                                    }
                                    if (!empty($_GET['filter_product'])) {
                                        $filter_product = mysqli_real_escape_string($con, $_GET['filter_product']);
                                        $open_query .= " AND p.prod_id = '$filter_product'";
                                    }
                                } else {
                                    $open_query .= " AND p.assign_to = '$current_user_id'";
                                }
                                $open_query .= " ORDER BY p.order_id DESC";
                                $open_result = mysqli_query($con, $open_query);

                                $all_open_invoice_ids = [];
                                while ($row = mysqli_fetch_assoc($open_result)) {
                                    $open_projects[] = $row;
                                    if ($can_view_invoices && !empty($row['project_invoices'])) {
                                        $ids = explode(',', $row['project_invoices']);
                                        $all_open_invoice_ids = array_merge($all_open_invoice_ids, $ids);
                                    }
                                }
                                $all_open_invoice_ids = array_unique($all_open_invoice_ids);

                                $open_invoices_by_id = [];
                                if ($can_view_invoices && !empty($all_open_invoice_ids)) {
                                    $id_list = implode(',', array_map('intval', $all_open_invoice_ids));
                                    $query = "SELECT invoice_id, invoice_date, filelink, invoice_status, due_date, total_amount, 
                                                     paid_amount, balance, discount, mem_id, service_provider_id, payment_details 
                                              FROM billing_invoice 
                                              WHERE invoice_id IN ($id_list)
                                              ORDER BY invoice_date DESC";
                                    $result = mysqli_query($con, $query);
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $open_invoices_by_id[$row['invoice_id']] = $row;
                                    }
                                }

                                foreach ($open_projects as &$project) {
                                    $invoices = [];
                                    if ($can_view_invoices && !empty($project['project_invoices'])) {
                                        $ids = explode(',', $project['project_invoices']);
                                        foreach ($ids as $id) {
                                            if (isset($open_invoices_by_id[$id])) {
                                                $invoices[] = $open_invoices_by_id[$id];
                                            }
                                        }
                                    }
                                    $project['invoices'] = $invoices;
                                }
                                unset($project);
                                $count_open_query = "SELECT COUNT(*) AS total FROM projects p WHERE p.project_status = 'open'";
                                if ($can_view_all) {
                                    if (!empty($_GET['filter_user'])) {
                                        $filter_user = mysqli_real_escape_string($con, $_GET['filter_user']);
                                        $count_open_query .= " AND p.assign_to = '$filter_user'";
                                    }
                                    if (!empty($_GET['filter_product'])) {
                                        $filter_product = mysqli_real_escape_string($con, $_GET['filter_product']);
                                        $count_open_query .= " AND p.prod_id = '$filter_product'";
                                    }
                                } else {
                                    $count_open_query .= " AND p.assign_to = '$current_user_id'";
                                }
                                $count_open_result = mysqli_query($con, $count_open_query);
                                $total_open = mysqli_fetch_assoc($count_open_result)['total'];
                                $total_pages = ceil($total_open / $limit);
                                // Hidden store of invoice detail blocks (open + completed),
                                // rendered on demand as DataTables child rows. Looked up by ID in JS.
                                $invoice_store = '';
                                // Now loop through pre-fetched projects
                                foreach ($open_projects as $row):
                                    $invoices = $can_view_invoices ? $row['invoices'] : [];
                                    ?>
                                    <tr class="project-row" data-project-id="<?= $row['order_id'] ?>">
                                        <td>
                                            <a href="project_details.php?id=<?= $row['order_id']; ?>"
                                                style="text-decoration: none; color: #252161; border: 1px solid #252161; padding: 5px 10px; border-radius: 5px;"
                                                onmouseover="this.style.backgroundColor='#252161'; this.style.color='white';"
                                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#252161';"
                                                target="_blank">
                                                <?= $row['order_id']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            // 1-time lookup for this row
                                            $sid = (int) $row['mem_id'];
                                            $st = mysqli_fetch_assoc(mysqli_query($con, "SELECT status FROM members WHERE member_id=$sid LIMIT 1"));
                                            $stat = (int) ($st['status'] ?? 0);
                                            ?>
                                            <a href="client_details.php?id=<?= $sid ?>&status=<?= $stat ?>"
                                                style="text-decoration:none; color:#252161;">
                                                <?= htmlspecialchars($row['client_name'] . ' ' . $row['mname'] . ' ' . $row['lname']) ?>
                                            </a>
                                        </td>

                                        <td><?= $row['bus_name'] ?></td>
                                        <td><?= $row['product_name'] ?></td>
                                        <td><?= $row['project_title'] ?></td>
                                        <td><?= $row['assigned_to'] ?></td>
                                        <td><?= date("m/d/Y", strtotime($row['start_date'])) ?></td>
                                        <td><?= date("m/d/Y", strtotime($row['exp_date'])) ?></td>

                                        <td><?= ucfirst($row['project_status']) ?></td>
                                        <?php if ($current_user_type == 1): ?>
                                            <td>
                                                <?php if (!empty($invoices)): ?>
                                                    <?php foreach ($invoices as $invoice):
                                                        $status_class = ($invoice['invoice_status'] == 1) ? 'unpaid' : (($invoice['invoice_status'] == 2) ? 'paid' : 'other');
                                                        ?>
                                                        <?php
                                                        $date = !empty($invoice['invoice_date']) ? date('m/d/Y', strtotime($invoice['invoice_date'])) : 'No Date';
                                                        ?>
                                                        <a href="invoices/<?= $invoice['filelink'] ?>" target="_blank"
                                                            class="badge invoice-badge <?= $status_class ?>"
                                                            title="Invoice #<?= $invoice['invoice_id'] ?> - <?= $date ?>">
                                                            <i class="fas fa-file-invoice me-1"></i>#<?= $invoice['invoice_id'] ?>
                                                        </a>

                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary invoice-badge">No invoices</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($perms['mark_project_completed'] === 'Yes'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="project_id" value="<?= $row['order_id'] ?>">
                                                    <button type="button" class="btn btn-success btn-sm"
                                                        title="Mark as Completed" data-bs-toggle="modal"
                                                        data-bs-target="#completeModal<?= $row['order_id'] ?>"
                                                        data-modal-type="complete" data-project-id="<?= $row['order_id'] ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($perms['edit_project'] === 'Yes'): ?>
                                                <button class="btn btn-info btn-sm" title="Edit Project" data-bs-toggle="modal"
                                                    data-bs-target="#changeModal<?php echo $row['order_id']; ?>"
                                                    data-modal-type="edit" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-user-edit"></i>
                                                </button>
                                            <?php endif; ?>

                                            <button class="btn btn-primary btn-sm" title="View Comments History"
                                                data-bs-toggle="modal"
                                                data-bs-target="#commentsModal<?= $row['order_id'] ?>"
                                                data-modal-type="comments" data-project-id="<?= $row['order_id'] ?>">
                                                <i class="fas fa-comment-dots"></i>
                                            </button>

                                            <button class="btn btn-sm text-white" title="View Update / Assignment History"
                                                style="background-color:#C9A227;border-color:#C9A227;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#historyModal<?= $row['order_id'] ?>"
                                                data-modal-type="history" data-project-id="<?= $row['order_id'] ?>">
                                                <i class="fas fa-history"></i>
                                            </button>

                                            <?php if ($perms['create_invoice'] === 'Yes'): ?>
                                                <button class="btn btn-info btn-sm create-invoice-btn" title="Create Invoice"
                                                    data-project-id="<?= $row['order_id'] ?>"
                                                    data-client-id="<?= $row['mem_id'] ?>">
                                                    <i class="fas fa-file-invoice"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($can_view_invoices): ?>
                                                <button class="btn btn-secondary btn-sm toggle-invoice-btn"
                                                    title="Show Invoices" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($perms['delete_project'] === 'Yes'): ?>
                                                <button class="btn btn-danger btn-sm" title="Delete Project"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['order_id'] ?>"
                                                    data-modal-type="delete" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                    <?php if ($can_view_invoices): ob_start(); ?>
                                        <div id="invoice-detail-<?= $row['order_id'] ?>" class="invoice-detail-store d-none">
                                            <div class="project-invoices">
                                                    <?php if (!empty($invoices)): ?>
                                                        <table class="invoice-table table table-bordered text-center">
                                                            <thead>
                                                                <tr>
                                                                    <th>Invoice ID</th>
                                                                    <th>Date</th>
                                                                    <th>Due Date</th>
                                                                    <th>Total Amount</th>
                                                                    <th>Balance</th>
                                                                    <th>Status</th>
                                                                    <th>Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($invoices as $invoice):
                                                                    $status_text = '';
                                                                    $status_class = '';
                                                                    switch ($invoice['invoice_status']) {
                                                                        case 1:
                                                                            $status_text = 'Unpaid';
                                                                            $status_class = 'unpaid';
                                                                            break;
                                                                        case 2:
                                                                            $status_text = 'Partial Paid';
                                                                            $status_class = 'partial';
                                                                            break;
                                                                        case 3:
                                                                            $status_text = 'Paid';
                                                                            $status_class = 'paid';
                                                                            break;
                                                                        default:
                                                                            $status_text = 'Unknown';
                                                                            $status_class = '';
                                                                    }
                                                                    $invoice_date = !empty($invoice['invoice_date']) ?
                                                                        date('m/d/Y', strtotime($invoice['invoice_date'])) : 'N/A';
                                                                    $due_date = !empty($invoice['due_date']) ?
                                                                        date('m/d/Y', strtotime($invoice['due_date'])) : 'N/A';
                                                                    $total_amount = isset($invoice['total_amount']) ?
                                                                        number_format($invoice['total_amount'], 2) : '0.00';
                                                                    ?>
                                                                    <tr>
                                                                        <td><?= $invoice['invoice_id'] ?></td>
                                                                        <td><?= $invoice_date ?></td>
                                                                        <td><?= $due_date ?></td>
                                                                        <td>$<?= $total_amount ?></td>
                                                                        <td>$<?= $invoice['balance'] ?></td>
                                                                        <td><span
                                                                                class="invoice-status <?= $status_class ?>"><?= $status_text ?></span>
                                                                        </td>
                                                                        <td class="invoice-actions">
                                                                            <a href="invoices/<?= $invoice['filelink'] ?>"
                                                                                target="_blank" class="btn btn-sm btn-primary"
                                                                                title="View Invoice">
                                                                                <i class="fas fa-eye"></i>
                                                                            </a>
                                                                            <?php if ($current_user_type == 1): ?>
                                                                            <button class="btn btn-sm btn-success add-payment-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-total-amount="<?= $invoice['total_amount'] ?>"
                                                                                data-paid-amount="<?= $invoice['paid_amount'] ?>"
                                                                                data-balance="<?= $invoice['balance'] ?>"
                                                                                data-discount="<?= $invoice['discount'] ?>"
                                                                                data-client-id="<?= $invoice['mem_id'] ?>"
                                                                                data-service-provider-id="<?= $invoice['service_provider_id'] ?>"
                                                                                data-service-provider-name="<?=
                                                                                    $service_provider_name = '';
                                                                                if (!empty($invoice['service_provider_id'])) {
                                                                                    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $invoice['service_provider_id']) . "'");
                                                                                    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
                                                                                        $service_provider_name = getInitials($sp_result['user_name']);
                                                                                    }
                                                                                }
                                                                                echo htmlspecialchars($service_provider_name);
                                                                                ?>" data-bs-toggle="modal"
                                                                                data-bs-target="#addPaymentModal" title="Add Payment">
                                                                                <i class="fas fa-money-bill"></i>
                                                                            </button>
                                                                            <button class="btn btn-sm btn-warning edit-invoice-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#editInvoiceModal" title="Edit Invoice">
                                                                                <i class="fas fa-edit"></i>
                                                                            </button>
                                                                            <button class="btn btn-sm btn-info payment-history-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-client-id="<?= $invoice['mem_id'] ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#paymentHistoryModal"
                                                                                title="Payment History">
                                                                                <i class="fas fa-history"></i>
                                                                            </button>
                                                                            <form method="POST" class="d-inline"
                                                                                onsubmit="return confirm('Remove this attached invoice from the project?');">
                                                                                <input type="hidden" name="detach_invoice" value="1">
                                                                                <input type="hidden" name="project_id"
                                                                                    value="<?= $row['order_id'] ?>">
                                                                                <input type="hidden" name="invoice_id"
                                                                                    value="<?= $invoice['invoice_id'] ?>">
                                                                                <button type="submit"
                                                                                    class="btn btn-sm btn-danger"
                                                                                    title="Remove Attached Invoice">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </form>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="mt-3">No invoices found for this project.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                    <?php $invoice_store .= ob_get_clean(); endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>

                <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] === 'completed') ? 'show active' : '' ?>"
                    id="completed-projects" role="tabpanel">
                        <table id="completedProjectsTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Project ID</th>
                                    <th>Client</th>
                                    <th>Business</th>
                                    <th>Product</th>
                                    <th>Title</th>
                                    <th>Assigned To</th>
                                    <th>Start Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <?php if ($current_user_type == 1): ?>
                                        <th>Invoices</th>
                                    <?php endif; ?>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Optimized: Fetch all completed projects first
                                $completed_projects = [];
                                $completed_query = "SELECT p.*, m.name AS client_name, m.mname, m.lname, m.bus_name, pr.membership_name AS product_name, u.user_name AS assigned_to 
                    FROM projects p
                    JOIN members m ON p.mem_id = m.member_id
                    JOIN memberships pr ON p.prod_id = pr.membership_id
                    JOIN users u ON p.assign_to = u.user_id
                    WHERE p.project_status = 'completed'";

// Admin filters
if ($can_view_all) {
    if (!empty($_GET['filter_user'])) {
        $filter_user = mysqli_real_escape_string($con, $_GET['filter_user']);
        $completed_query .= " AND p.assign_to = '$filter_user'";
    }
    if (!empty($_GET['filter_product'])) {
        $filter_product = mysqli_real_escape_string($con, $_GET['filter_product']);
        $completed_query .= " AND p.prod_id = '$filter_product'";
    }
} else {
    $completed_query .= " AND p.assign_to = '$current_user_id'";
}
$completed_query .= " ORDER BY p.order_id DESC";
                                $completed_result = mysqli_query($con, $completed_query);

                                $all_completed_invoice_ids = [];
                                while ($row = mysqli_fetch_assoc($completed_result)) {
                                    $completed_projects[] = $row;
                                    if ($can_view_invoices && !empty($row['project_invoices'])) {
                                        $ids = explode(',', $row['project_invoices']);
                                        $all_completed_invoice_ids = array_merge($all_completed_invoice_ids, $ids);
                                    }
                                }
                                $all_completed_invoice_ids = array_unique($all_completed_invoice_ids);

                                $completed_invoices_by_id = [];
                                if ($can_view_invoices && !empty($all_completed_invoice_ids)) {
                                    $id_list = implode(',', array_map('intval', $all_completed_invoice_ids));
                                    $query = "SELECT invoice_id, invoice_date, filelink, invoice_status, due_date, total_amount, 
                                                     paid_amount, balance, discount, mem_id, service_provider_id, payment_details 
                                              FROM billing_invoice 
                                              WHERE invoice_id IN ($id_list)
                                              ORDER BY invoice_date DESC";
                                    $result = mysqli_query($con, $query);
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        $completed_invoices_by_id[$row['invoice_id']] = $row;
                                    }
                                }

                                foreach ($completed_projects as &$project) {
                                    $invoices = [];
                                    if ($can_view_invoices && !empty($project['project_invoices'])) {
                                        $ids = explode(',', $project['project_invoices']);
                                        foreach ($ids as $id) {
                                            if (isset($completed_invoices_by_id[$id])) {
                                                $invoices[] = $completed_invoices_by_id[$id];
                                            }
                                        }
                                    }
                                    $project['invoices'] = $invoices;
                                }
                                unset($project);
                               $count_completed_query = "SELECT COUNT(*) AS total FROM projects p WHERE p.project_status = 'completed'";
if ($can_view_all) {
    if (!empty($_GET['filter_user'])) {
        $filter_user = mysqli_real_escape_string($con, $_GET['filter_user']);
        $count_completed_query .= " AND p.assign_to = '$filter_user'";
    }
    if (!empty($_GET['filter_product'])) {
        $filter_product = mysqli_real_escape_string($con, $_GET['filter_product']);
        $count_completed_query .= " AND p.prod_id = '$filter_product'";
    }
} else {
    $count_completed_query .= " AND p.assign_to = '$current_user_id'";
}
                                $count_completed_result = mysqli_query($con, $count_completed_query);
                                $total_completed = mysqli_fetch_assoc($count_completed_result)['total'];
                                $total_completed_pages = ceil($total_completed / $limit);
                                // Now loop through pre-fetched projects
                                foreach ($completed_projects as $row):
                                    $invoices = $can_view_invoices ? $row['invoices'] : [];
                                    ?>
                                    <tr class="project-row" data-project-id="<?= $row['order_id'] ?>">
                                        <td>
                                            <a href="project_details.php?id=<?= $row['order_id']; ?>"
                                                style="text-decoration: none; color: #252161; border: 1px solid #252161; padding: 5px 10px; border-radius: 5px;"
                                                onmouseover="this.style.backgroundColor='#252161'; this.style.color='white';"
                                                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#252161';"
                                                target="_blank">
                                                <?= $row['order_id']; ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php
                                            // 1-time lookup for this row
                                            $sid = (int) $row['mem_id'];
                                            $st = mysqli_fetch_assoc(mysqli_query($con, "SELECT status FROM members WHERE member_id=$sid LIMIT 1"));
                                            $stat = (int) ($st['status'] ?? 0);
                                            ?>
                                            <a href="client_details.php?id=<?= $sid ?>&status=<?= $stat ?>"
                                                style="text-decoration:none; color:#252161;">
                                                <?= htmlspecialchars($row['client_name'] . ' ' . $row['mname'] . ' ' . $row['lname']) ?>
                                            </a>
                                        </td>

                                        <td><?= $row['bus_name'] ?></td>
                                        <td><?= $row['product_name'] ?></td>
                                        <td><?= $row['project_title'] ?></td>
                                        <td><?= $row['assigned_to'] ?></td>
                                        <td><?= date("m/d/Y", strtotime($row['start_date'])) ?></td>
                                        <td><?= date("m/d/Y", strtotime($row['exp_date'])) ?></td>
                                        <td><?= ucfirst($row['project_status']) ?></td>
                                        <?php if ($current_user_type == 1): ?>
                                            <td>
                                                <?php if (!empty($invoices)): ?>
                                                    <?php foreach ($invoices as $invoice):
                                                        $status_class = ($invoice['invoice_status'] == 1) ? 'unpaid' : (($invoice['invoice_status'] == 2) ? 'paid' : 'other');
                                                        ?>
                                                        <a href="invoices/<?= $invoice['filelink'] ?>" target="_blank"
                                                            class="badge invoice-badge <?= $status_class ?>" title="Invoice #<?= $invoice['invoice_id'] ?> - 
   <?= !empty($invoice['invoice_date']) ? date('m/d/Y', strtotime($invoice['invoice_date'])) : 'No Date' ?>">
                                                            <i class="fas fa-file-invoice me-1"></i>#<?= $invoice['invoice_id'] ?>
                                                        </a>

                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary invoice-badge">No invoices</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php if ($perms['reopen_project'] === 'Yes'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="project_id" value="<?= $row['order_id'] ?>">
                                                    <button type="button" class="btn btn-warning btn-sm reopen-btn"
                                                        data-pid="<?= $row['order_id'] ?>" title="Reopen" <?= ($current_user_type != 1) ? 'disabled' : '' ?>>
                                                        <i class="fas fa-redo-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($perms['edit_project'] === 'Yes'): ?>
                                                <button class="btn btn-info btn-sm" title="Edit Project" data-bs-toggle="modal"
                                                    data-bs-target="#changeModal<?php echo $row['order_id']; ?>"
                                                    data-modal-type="edit" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-user-edit"></i>
                                                </button>
                                            <?php endif; ?>


                                            <button class="btn btn-primary btn-sm" title="View Comments History"
                                                data-bs-toggle="modal"
                                                data-bs-target="#commentsModal<?= $row['order_id'] ?>"
                                                data-modal-type="comments" data-project-id="<?= $row['order_id'] ?>"
                                                <?= ($current_user_type != 1) ? 'disabled' : '' ?>>
                                                <i class="fas fa-comment-dots"></i>
                                            </button>

                                            <button class="btn btn-sm text-white" title="View Update / Assignment History"
                                                style="background-color:#C9A227;border-color:#C9A227;"
                                                data-bs-toggle="modal"
                                                data-bs-target="#historyModal<?= $row['order_id'] ?>"
                                                data-modal-type="history" data-project-id="<?= $row['order_id'] ?>">
                                                <i class="fas fa-history"></i>
                                            </button>


                                            <?php if ($perms['create_invoice'] === 'Yes'): ?>
                                                <button class="btn btn-info btn-sm create-invoice-btn" title="Create Invoice"
                                                    data-project-id="<?= $row['order_id'] ?>"
                                                    data-client-id="<?= $row['mem_id'] ?>">
                                                    <i class="fas fa-file-invoice"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($can_view_invoices): ?>
                                                <button class="btn btn-secondary btn-sm toggle-invoice-btn"
                                                    title="Show Invoices" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-chevron-down"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($perms['delete_project'] === 'Yes'): ?>
                                                <button class="btn btn-danger btn-sm" title="Delete Project"
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['order_id'] ?>"
                                                    data-modal-type="delete" data-project-id="<?= $row['order_id'] ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>

                                        </td>
                                    </tr>
                                    <?php if ($can_view_invoices): ob_start(); ?>
                                        <div id="invoice-detail-<?= $row['order_id'] ?>" class="invoice-detail-store d-none">
                                            <div class="project-invoices">
                                                    <?php if (!empty($invoices)): ?>
                                                        <table class="invoice-table table table-bordered text-center">
                                                            <thead>
                                                                <tr>
                                                                    <th>Invoice ID</th>
                                                                    <th>Date</th>
                                                                    <th>Due Date</th>
                                                                    <th>Total Amount</th>
                                                                    <th>Balance</th>
                                                                    <th>Status</th>
                                                                    <th>Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($invoices as $invoice):
                                                                    $status_text = '';
                                                                    $status_class = '';
                                                                    switch ($invoice['invoice_status']) {
                                                                        case 1:
                                                                            $status_text = 'Unpaid';
                                                                            $status_class = 'unpaid';
                                                                            break;
                                                                        case 2:
                                                                            $status_text = 'Partial Paid';
                                                                            $status_class = 'partial';
                                                                            break;
                                                                        case 3:
                                                                            $status_text = 'Paid';
                                                                            $status_class = 'paid';
                                                                            break;
                                                                        default:
                                                                            $status_text = 'Unknown';
                                                                            $status_class = '';
                                                                    }
                                                                    $invoice_date = !empty($invoice['invoice_date']) ?
                                                                        date('m/d/Y', strtotime($invoice['invoice_date'])) : 'N/A';
                                                                    $due_date = !empty($invoice['due_date']) ?
                                                                        date('m/d/Y', strtotime($invoice['due_date'])) : 'N/A';
                                                                    $total_amount = isset($invoice['total_amount']) ?
                                                                        number_format($invoice['total_amount'], 2) : '0.00';
                                                                    ?>
                                                                    <tr>
                                                                        <td><?= $invoice['invoice_id'] ?></td>
                                                                        <td><?= $invoice_date ?></td>
                                                                        <td><?= $due_date ?></td>
                                                                        <td>$<?= $total_amount ?></td>
                                                                        <td>$<?= $invoice['balance'] ?></td>
                                                                        <td><span
                                                                                class="invoice-status <?= $status_class ?>"><?= $status_text ?></span>
                                                                        </td>
                                                                        <td class="invoice-actions">
                                                                            <a href="invoices/<?= $invoice['filelink'] ?>"
                                                                                target="_blank" class="btn btn-sm btn-primary"
                                                                                title="View Invoice">
                                                                                <i class="fas fa-eye"></i>
                                                                            </a>
                                                                            <?php if ($current_user_type == 1): ?>
                                                                            <button class="btn btn-sm btn-success add-payment-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-total-amount="<?= $invoice['total_amount'] ?>"
                                                                                data-paid-amount="<?= $invoice['paid_amount'] ?>"
                                                                                data-balance="<?= $invoice['balance'] ?>"
                                                                                data-discount="<?= $invoice['discount'] ?>"
                                                                                data-client-id="<?= $invoice['mem_id'] ?>"
                                                                                data-service-provider-id="<?= $invoice['service_provider_id'] ?>"
                                                                                data-service-provider-name="<?=
                                                                                    $service_provider_name = '';
                                                                                if (!empty($invoice['service_provider_id'])) {
                                                                                    $sp_query = mysqli_query($con, "SELECT user_name FROM users WHERE user_id = '" . mysqli_real_escape_string($con, $invoice['service_provider_id']) . "'");
                                                                                    if ($sp_result = mysqli_fetch_assoc($sp_query)) {
                                                                                        $service_provider_name = getInitials($sp_result['user_name']);
                                                                                    }
                                                                                }
                                                                                echo htmlspecialchars($service_provider_name);
                                                                                ?>" data-bs-toggle="modal"
                                                                                data-bs-target="#addPaymentModal" title="Add Payment">
                                                                                <i class="fas fa-money-bill"></i>
                                                                            </button>
                                                                            <button class="btn btn-sm btn-warning edit-invoice-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#editInvoiceModal" title="Edit Invoice">
                                                                                <i class="fas fa-edit"></i>
                                                                            </button>
                                                                            <button class="btn btn-sm btn-info payment-history-btn"
                                                                                data-invoice-id="<?= $invoice['invoice_id'] ?>"
                                                                                data-client-id="<?= $invoice['mem_id'] ?>"
                                                                                data-bs-toggle="modal"
                                                                                data-bs-target="#paymentHistoryModal"
                                                                                title="Payment History">
                                                                                <i class="fas fa-history"></i>
                                                                            </button>
                                                                            <form method="POST" class="d-inline"
                                                                                onsubmit="return confirm('Remove this attached invoice from the project?');">
                                                                                <input type="hidden" name="detach_invoice" value="1">
                                                                                <input type="hidden" name="project_id"
                                                                                    value="<?= $row['order_id'] ?>">
                                                                                <input type="hidden" name="invoice_id"
                                                                                    value="<?= $invoice['invoice_id'] ?>">
                                                                                <button type="submit"
                                                                                    class="btn btn-sm btn-danger"
                                                                                    title="Remove Attached Invoice">
                                                                                    <i class="fas fa-trash"></i>
                                                                                </button>
                                                                            </form>
                                                                            <?php endif; ?>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php else: ?>
                                                        <p class="mt-3">No invoices found for this project.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                    <?php $invoice_store .= ob_get_clean(); endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                </div>
            </div>

            <!-- Hidden invoice-detail blocks, injected as DataTables child rows on demand -->
            <?php if ($can_view_invoices) echo $invoice_store; ?>

            <div id="modalContainer"></div>

            <!-- Step 1: Ask Modal -->
            <div class="modal fade" id="askInvoiceModal" tabindex="-1" aria-labelledby="askInvoiceLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Create or Select Invoice</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            Do you want to create a new invoice or select an existing invoice for this project?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmCreateInvoice">Create New
                                Invoice</button>
                            <button type="button" class="btn btn-info" id="selectExistingInvoice">Select Existing
                                Invoice</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="selectInvoiceModal" tabindex="-1" aria-labelledby="selectInvoiceLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="selectInvoiceLabel">Select Existing Invoice</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="selectInvoiceForm">
                                <input type="hidden" id="select_invoice_project_id" name="project_id">
                                <input type="hidden" id="select_invoice_client_id" name="client_id">
                                <?php if ($current_user_type == 1): ?>
                                <div class="mb-3">
                                    <label class="form-label d-block">Invoice Scope</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="invoice_scope" id="invoiceScopeClient" value="client" checked>
                                        <label class="form-check-label" for="invoiceScopeClient">Client Invoices</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="invoice_scope" id="invoiceScopeAll" value="all">
                                        <label class="form-check-label" for="invoiceScopeAll">All Invoices</label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="invoiceSelect" class="form-label">Select Invoice</label>
                                    <select class="form-select select2" id="invoiceSelect" name="invoice_id"
                                        style="width: 100%;">
                                        <option value="">-- Select an Invoice --</option>
                                        <?php
                                        $invoices_query = "SELECT bi.*, m.name, m.mname, m.lname, m.bus_name 
                                              FROM billing_invoice bi 
                                              JOIN members m ON bi.mem_id = m.member_id 
                                              ORDER BY bi.invoice_date DESC";
                                        $invoices_result = mysqli_query($con, $invoices_query);

                                        if (!$invoices_result) {
                                            echo '<option disabled>Error: ' . htmlspecialchars(mysqli_error($con)) . '</option>';
                                        } elseif (mysqli_num_rows($invoices_result) == 0) {
                                            echo '<option disabled>No invoices found</option>';
                                        } else {
                                            while ($invoice = mysqli_fetch_assoc($invoices_result)) {
                                                $client_name = $invoice['bus_name'] ?:
                                                    trim($invoice['name'] . ' ' . $invoice['mname'] . ' ' . $invoice['lname']);
                                                $invoice_date = !empty($invoice['invoice_date']) ?
                                                    date('m/d/Y', strtotime($invoice['invoice_date'])) : 'N/A';
                                                $total_amount = isset($invoice['total_amount']) ?
                                                    number_format($invoice['total_amount'], 2) : '0.00';
                                                echo '<option value="' . htmlspecialchars($invoice['invoice_id']) . '" data-client-id="' . htmlspecialchars($invoice['mem_id']) . '">' .
                                                    '# ' . htmlspecialchars($invoice['invoice_id']) . ' - ' .
                                                    htmlspecialchars($client_name) .
                                                    ' (Date: ' . htmlspecialchars($invoice_date) .
                                                    ', Amount: $' . htmlspecialchars($total_amount) . ')' .
                                                    '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="confirmSelectInvoice">Select
                                Invoice</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Invoice Form Modal -->
            <div class="modal fade" id="associatedProducts">
                <div class="modal-dialog modal-lg">
                    <form method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title">Add Invoice Item</h4>
                            </div>

                            <div class="modal-body">
                                <input type="hidden" name="project_id" id="project_id_field" value="">
                                <div class="form-group">
                                    <label>Client</label>
                                    <select name="client_id" class="form-control select2" required>
                                        <option value="">Select Client</option>
                                        <?php
                                        try {
                                            $clients_result = mysqli_query($con, "SELECT * FROM members");

                                            if (!$clients_result) {
                                                throw new Exception("Query failed: " . mysqli_error($con));
                                            }

                                            if (mysqli_num_rows($clients_result) > 0) {
                                                mysqli_data_seek($clients_result, 0);
                                                while ($client = mysqli_fetch_assoc($clients_result)) {
                                                    $fullName = trim($client['name'] . ' ' . $client['mname'] . ' ' . $client['lname']);
                                                    echo '<option value="' . htmlspecialchars($client['member_id']) . '">';
                                                    echo 'Member ID: ' . htmlspecialchars($client['member_id']) . str_repeat('&nbsp;', 10) .
                                                        'Name: ' . htmlspecialchars($fullName) . str_repeat('&nbsp;', 10) .
                                                        'Business: ' . htmlspecialchars($client['bus_name']);
                                                    echo '</option>';
                                                }
                                            } else {
                                                echo '<option disabled>No clients found</option>';
                                            }
                                        } catch (Exception $e) {
                                            error_log($e->getMessage());
                                            echo '<option disabled>Error loading clients</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <?php
                                $queryy = mysqli_query($con, "SELECT * FROM memberships");
                                while ($services1 = mysqli_fetch_array($queryy)) {
                                    ?>
                                    <div class="form-group mt-2"
                                        id="category-dropdown<?php echo $services1['membership_id']; ?>"></div>
                                <?php } ?>

                                <p style="color: #428bca; cursor: pointer;" onclick="$('#myDIV').toggle();">
                                    <b><i class="fa fa-plus" aria-hidden="true"></i> Add another service</b>
                                </p>

                                <div id="myDIV" style="display: block;">
                                    <select class="form-control select2 services-select" multiple="multiple"
                                        name="vehicle2[]" id="services_select"
                                        data-placeholder="Select Services...">
                                        <?php
                                        $query = mysqli_query($con, "SELECT * FROM memberships ORDER BY membership_name ASC");
                                        while ($services = mysqli_fetch_array($query)) {
                                            echo '<option value="' . htmlspecialchars($services['membership_id'], ENT_QUOTES) . '">'
                                                . htmlspecialchars($services['membership_name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-md-12">
                                        <label for="Service_Provider" class="form-label">Service Provider</label>
                                        <select id="Service_Provider" name="Service_Provider"
                                            class="form-control select2" required>
                                            <option value="">Select Service Provider</option>
                                            <?php
                                            $result = mysqli_query($con, "SELECT user_id, user_name FROM users");
                                            while ($user = mysqli_fetch_assoc($result)) {
                                                echo '<option value="' . htmlspecialchars($user['user_id']) . '">' . htmlspecialchars($user['user_name']) . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-md-12">
                                        <label for="billing_cycle" class="form-label">Invoice Expiry Cycle (Billing
                                            Cycle):</label>
                                        <input type="date" class="form-control" required id="billing_cycle"
                                            name="billing_cycle">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-10 text-end">
                                        <label class="form-label"><b>Subtotal</b></label>
                                    </div>
                                    <div class="col-md-2">
                                        <input class="form-control" required name="sub_total" type="number" step="0.01"
                                            placeholder="Sub Total" id="sub_total" value="0" oninput="changetotal()">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-10 text-end"></div>
                                    <div class="col-md-2">
                                        <label class="form-label"><input type="checkbox" name="dissbox" id="dissbox"
                                                onchange="$('#discountrow').toggle();"> Add Discount</label>
                                    </div>
                                </div>

                                <div class="row" id="discountrow" style="display: none;">
                                    <div class="col-md-10 text-end">
                                        <label class="form-label"><b>Discount</b></label>
                                    </div>
                                    <div class="col-md-2">
                                        <input class="form-control" name="discount" type="number" step="0.01"
                                            id="discount" value="0" oninput="changetotal()">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-10 text-end">
                                        <label class="form-label"><b>Total</b></label>
                                    </div>
                                    <div class="col-md-2">
                                        <input class="form-control" required name="grand_total" type="number"
                                            step="0.01" id="grand_total" value="0">
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-info" data-dismiss="modal"
                                    id="closeButton">Close</button>
                                <button type="submit" class="btn btn-primary" name="createinvoice">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Payment Modal -->
            <div class="modal fade" id="addPaymentModal" tabindex="-1" aria-labelledby="addPaymentModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add Payment For Invoice #<span id="addPaymentInvoiceId"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="invoice_id" id="add_payment_invoice_id">
                            <input type="hidden" name="paidamount" id="add_payment_paidamount">
                            <input type="hidden" name="memberid" id="add_payment_memberid">
                            <input type="hidden" name="old_discount" id="add_payment_old_discount">

                            <div class="row mb-2">
                                <div class="col-md-6 mt-2">
                                    <label for="duedate" class="form-label">Date</label>
                                    <input class="form-control" type="date" name="duedate" id="add_payment_duedate">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label for="after_discount" class="form-label">Discount</label>
                                    <input type="text" id='add_payment_after_discount' name='after_discount'
                                        class='form-control'>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label for="employee_initials" class="form-label">Employee Initials</label>
                                    <input class="form-control" id="employee_initials" name="employee_initials"
                                        value="">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label for="startdate" class="form-label">Total Amount</label>
                                    <input class="form-control" required name="totalamount" type="text"
                                        id="add_payment_totalamount">
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label for="expirydate" class="form-label">Balance</label>
                                    <input class="form-control" required name="balance" type="text"
                                        id="add_payment_balance">
                                </div>

                                <div class="col-md-12 mt-2">
                                    <label class="form-label">Payment Type</label>
                                    <select class="form-control" id="paymentTypeSelect" name="payment_type"
                                        onchange="handlePaymentTypeChange(this)">
                                        <option value="">Select Payment Type</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Check">Check</option>
                                        <option value="Card">Card</option>
                                        <option value="ACH">ACH</option>
                                        <option value="Zelle">Zelle</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <input type="text" class="form-control mt-2 d-none" id="otherPaymentInput"
                                        placeholder="Please specify payment type">
                                </div>

                                <div class="col-md-12 mt-2">
                                    <label for="service_provider" class="form-label">Service Provider</label>
                                    <input type="text" class="form-control" id="add_payment_service_provider" readonly>
                                </div>

                                <div class="col-md-12 mt-2">
                                    <label for="expirydate" class="form-label">Amount Received</label>
                                    <input class="form-control" required name="recievedamount" type="text">
                                </div>
                                <div class="col-md-12 mt-2">
                                    <label for="description" class="form-label">Description</label>
                                    <input class="form-control" required id="description" name="description" type="text"
                                        value="">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="addpayment">Submit</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Invoice Modal -->
            <div class="modal fade" id="editInvoiceModal" tabindex="-1" aria-labelledby="editInvoiceModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <form method="POST" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Invoice #<span id="editInvoiceId"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="invoice_id" id="edit_invoice_id">

                            <div class="row mb-2">
                                <div class="col-md-6">
                                    <label for="edit_due_date" class="form-label">Due Date</label>
                                    <input type="date" class="form-control" name="edit_due_date" id="edit_due_date"
                                        required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_total_amount" class="form-label">Total Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="edit_total_amount"
                                        id="edit_total_amount" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_paid_amount" class="form-label">Paid Amount</label>
                                    <input type="number" step="0.01" class="form-control" name="edit_paid_amount"
                                        id="edit_paid_amount" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_balance" class="form-label">Balance</label>
                                    <input type="number" step="0.01" class="form-control" name="edit_balance"
                                        id="edit_balance" required>
                                </div>
                                <div class="col-md-6 mt-2">
                                    <label for="edit_discount" class="form-label">Discount</label>
                                    <input type="number" step="0.01" id="edit_discount" name="edit_discount"
                                        class="form-control" required>
                                </div>

                                <div class="col-md-12 mt-2">
                                    <label for="edit_service_provider" class="form-label">Service Provider</label>
                                    <select id="edit_service_provider" name="edit_service_provider" class="form-control"
                                        required>
                                        <option value="">Select Service Provider</option>
                                        <?php
                                        $query = "SELECT user_id, user_name FROM users";
                                        $result = mysqli_query($con, $query);
                                        while ($user = mysqli_fetch_assoc($result)) {
                                            echo '<option value="' . htmlspecialchars($user['user_id']) . '">' .
                                                htmlspecialchars($user['user_name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-12 mt-2">
                                    <label for="description" class="form-label">Description</label>
                                    <input class="form-control" required="required" id="edit_description"
                                        name="description" type="text">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" name="update_invoice">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment History Modal -->
            <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Payment History for Invoice #<span
                                    id="paymentHistoryInvoiceId"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Balance</th>
                                        <th>Description</th>
                                        <th>Employee</th>
                                    </tr>
                                </thead>
                                <tbody id="paymentHistoryBody">

                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function calculateSum() {
                    const inputFields = document.querySelectorAll('.sum');
                    const totalSpan = document.getElementById("sub_total").value;
                    var discount = document.getElementById("discount").value;
                    var grand_total = document.getElementById("grand_total").value;
                    let sum = 0;

                    inputFields.forEach(input => {
                        sum += parseFloat(input.value) || 0;
                    });

                    //totalSpan.textContent = sum;
                    document.getElementById("sub_total").value = sum;

                    var total = sum - discount;
                    document.getElementById("grand_total").value = total;

                }
                function changetotal() {
                    var sub_total = document.getElementById("sub_total").value;
                    var discount = document.getElementById("discount").value;
                    var grand_total = document.getElementById("grand_total").value;

                    var total = sub_total - discount;
                    document.getElementById("grand_total").value = total;
                }

                function getdiscount() {
                    var sub_total = document.getElementById("sub_total").value;
                    var discount = document.getElementById("discount").value;
                    var grand_total = document.getElementById("grand_total").value;

                    // console.log("Sub Total=" + sub_total);
                    // console.log("Discount=" + discount);
                    // console.log("Grand=" + grand_total);

                    var total = sub_total - discount;
                    document.getElementById("grand_total").value = total;
                }
                function myFunction() {
                    var x = document.getElementById("myDIV");
                    if (x.style.display === "none") {
                        x.style.display = "block";
                    } else {
                        x.style.display = "none";
                    }
                }

                function mydiscountrow() {
                    var x = document.getElementById("discountrow");
                    var totalSpan = document.getElementById("sub_total").value;
                    if (x.style.display === "none") {
                        x.style.display = "flex";
                    } else {
                        x.style.display = "none";
                        document.getElementById("grand_total").value = totalSpan;
                        document.getElementById("discount").value = 0;
                    }
                }

                $(document).ready(function () {
                    changetotal();

                    $(document).on('select2:select', '#services_select', function (e) {
                        var id = e.params.data.id;
                        $.ajax({
                            url: "add-another-service.php",
                            type: "POST",
                            data: {
                                main_id: id,
                                sub_total: $('#sub_total').val(),
                                discount: $('#discount').val(),
                                grand_total: $('#grand_total').val()
                            },
                            cache: false,
                            success: function (result) {
                                $("#category-dropdown" + id).html(result);
                            }
                        });
                    });

                    $(document).on('select2:unselect', '#services_select', function (e) {
                        var id = e.params.data.id;
                        $("#category-dropdown" + id).empty();
                    });

                    $('#closeButton').click(function () {
                        location.reload(true);
                    });

                    // Handle create invoice button click
                    $(document).on('click', '.create-invoice-btn', function () {
                        var projectId = $(this).data('project-id');
                        var clientId = $(this).data('client-id');
                        $('#project_id_field').val(projectId);
                        $('#select_invoice_client_id').val(clientId);
                        $('#associatedProducts').find('select[name="client_id"]').val(clientId); // Pre-select the client
                        var askModal = new bootstrap.Modal(document.getElementById('askInvoiceModal'));
                        askModal.show();
                    });

                    $('#confirmCreateInvoice').on('click', function () {
                        var askModal = bootstrap.Modal.getInstance(document.getElementById('askInvoiceModal'));
                        askModal.hide();
                        var invoiceModal = new bootstrap.Modal(document.getElementById('associatedProducts'));
                        invoiceModal.show();
                    });

                    <?php if ($showInvoiceModal): ?>
                        var askModal = new bootstrap.Modal(document.getElementById('askInvoiceModal'));
                        askModal.show();
                        $('#project_id_field').val(<?= $_GET['project_id'] ?>);

                        $('#confirmCreateInvoice').on('click', function () {
                            askModal.hide();
                            var invoiceModal = new bootstrap.Modal(document.getElementById('associatedProducts'));
                            invoiceModal.show();
                        });
                    <?php endif; ?>

                    // NOTE: the invoice expand/collapse is handled as a DataTables child
                    // row in the initialization block near the bottom of this page.

                    $(document).on('shown.bs.modal', function (event) {
                        var modal = $(event.target);
                        modal.find('.select2').each(function () {
                            if ($(this).hasClass('select2-hidden-accessible')) {
                                $(this).select2('destroy');
                            }
                            $(this).select2({
                                dropdownParent: modal
                            });
                            // Trigger change to ensure Select2 updates the UI for pre-selected client
                            if ($(this).attr('name') === 'client_id') {
                                $(this).trigger('change');
                            }
                        });
                    });

                    $(document).on('click', '.add-payment-btn', function () {
                        const invoiceId = $(this).data('invoice-id');
                        const totalAmount = $(this).data('total-amount');
                        const paidAmount = $(this).data('paid-amount');
                        const balance = $(this).data('balance');
                        const discount = $(this).data('discount');
                        const clientId = $(this).data('client-id');
                        const serviceProviderName = $(this).data('service-provider-name');


                        $('#addPaymentInvoiceId').text(invoiceId);
                        $('#add_payment_invoice_id').val(invoiceId);
                        $('#add_payment_totalamount').val(totalAmount);
                        $('#add_payment_balance').val(balance);
                        $('#add_payment_paidamount').val(paidAmount);
                        $('#add_payment_old_discount').val(discount);
                        $('#add_payment_memberid').val(clientId);
                        $('#add_payment_after_discount').val(discount);
                        $('#add_payment_service_provider').val(serviceProviderName);

                        const today = new Date().toISOString().split('T')[0];
                        $('#add_payment_duedate').val(today);

                        const nameParts = "<?= $_SESSION['fullname'] ?>".split(' ');
                        let initials = '';
                        if (nameParts.length > 0) initials += nameParts[0][0];
                        if (nameParts.length > 1) initials += nameParts[1][0];
                        $('#employee_initials').val(initials.toUpperCase());

                        $('#paymentTypeSelect').val('');
                        $('#otherPaymentInput').val('').addClass('d-none');
                    });
                });

                function handlePaymentTypeChange(select) {
                    const otherInput = document.getElementById('otherPaymentInput');

                    if (select.value === 'Other') {
                        otherInput.classList.remove('d-none');
                        otherInput.value = '';
                        otherInput.focus();
                    } else {
                        otherInput.classList.add('d-none');
                        otherInput.value = '';
                    }
                }

                let allInvoiceOptions = null;
                function initInvoiceSelect() {
                    const $select = $('#invoiceSelect');
                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }
                    $select.select2({
                        placeholder: "-- Select an Invoice --",
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#selectInvoiceModal')
                    });
                }

                $(document).ready(function () {
                    const $select = $('#invoiceSelect');
                    allInvoiceOptions = $select.find('option').clone();
                    initInvoiceSelect();
                });

                function filterInvoicesByClient(clientId) {
                    const $select = $('#invoiceSelect');
                    if (!allInvoiceOptions) {
                        allInvoiceOptions = $select.find('option').clone();
                    }

                    const $placeholder = allInvoiceOptions.filter(function () {
                        return !$(this).val();
                    }).first().clone();

                    const $matched = allInvoiceOptions.filter(function () {
                        const val = $(this).val();
                        const optionClientId = $(this).data('client-id');
                        return !!val && String(optionClientId) === String(clientId);
                    }).clone();

                    $select.empty().append($placeholder);
                    if ($matched.length > 0) {
                        $select.append($matched);
                    } else {
                        $select.append('<option value="__no_client_invoice__" disabled>No invoices found for this client</option>');
                    }

                    $select.val('');
                    initInvoiceSelect();
                }

                function showAllInvoices() {
                    const $select = $('#invoiceSelect');
                    if (!allInvoiceOptions) {
                        allInvoiceOptions = $select.find('option').clone();
                    }

                    $select.empty().append(allInvoiceOptions.clone());
                    $select.val('');
                    initInvoiceSelect();
                }

                $('#selectExistingInvoice').on('click', function () {
                    var askModal = bootstrap.Modal.getInstance(document.getElementById('askInvoiceModal'));
                    var projectId = $('#project_id_field').val();
                    var clientId = $('#select_invoice_client_id').val() || $('#associatedProducts').find('select[name="client_id"]').val();
                    $('#select_invoice_project_id').val(projectId);
                    $('#select_invoice_client_id').val(clientId);
                    $('input[name="invoice_scope"][value="client"]').prop('checked', true);
                    filterInvoicesByClient(clientId);
                    askModal.hide();
                    var selectInvoiceModal = new bootstrap.Modal(document.getElementById('selectInvoiceModal'));
                    selectInvoiceModal.show();
                });

                $(document).on('change', 'input[name="invoice_scope"]', function () {
                    var clientId = $('#select_invoice_client_id').val();
                    if ($(this).val() === 'all') {
                        showAllInvoices();
                    } else {
                        filterInvoicesByClient(clientId);
                    }
                });

                $('#confirmSelectInvoice').on('click', function () {
                    var invoiceId = $('#invoiceSelect').val();
                    var projectId = $('#select_invoice_project_id').val();

                    if (!invoiceId) {
                        alert('Please select an invoice.');
                        return;
                    }

                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            select_existing_invoice: true,
                            project_id: projectId,
                            invoice_id: invoiceId
                        },
                        success: function () {
                            window.location.href = 'show_projects.php?status=invoice_associated&project_id=' + projectId;
                        },
                        error: function () {
                            alert('Error associating invoice.');
                        }
                    });

                    var selectInvoiceModal = bootstrap.Modal.getInstance(document.getElementById('selectInvoiceModal'));
                    selectInvoiceModal.hide();
                });

                $(document).on('click', '.payment-history-btn', function () {
                    const invoiceId = $(this).data('invoice-id');
                    const clientId = $(this).data('client-id');

                    // Show invoice number in modal header
                    $('#paymentHistoryInvoiceId').text(invoiceId);

                    // Clear old table rows
                    $('#paymentHistoryBody').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');

                    // Get payment history via AJAX from your existing PHP file
                    $.getJSON('invoicehistory.php', { invoice_id: invoiceId }, function (data) {
                        if (data.length > 0) {
                            let rows = '';
                            data.forEach(row => {
                                rows += `
                    <tr>
                        <td>${row.date}</td>
                        <td>${parseFloat(row.paid).toFixed(2)}</td>
                        <td>${parseFloat(row.balance).toFixed(2)}</td>
                        <td>${row.detail}</td>
                        <td>${row.employee}</td>
                    </tr>
                `;
                            });
                            $('#paymentHistoryBody').html(rows);
                        } else {
                            $('#paymentHistoryBody').html('<tr><td colspan="5" class="text-center">No payment history found</td></tr>');
                        }
                    });

                    // Show modal
                    $('#paymentHistoryModal').modal('show');
                });

            </script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {

                    document.querySelectorAll('[data-bs-toggle="modal"][data-modal-type]').forEach(button => {
                        button.addEventListener('click', function (e) {
                            e.preventDefault();                       // stop Bootstrap’s native show
                            const projectId = this.dataset.projectId;
                            const modalType = this.dataset.modalType; // complete / edit / comments / delete
                            const modalTarget = this.dataset.bsTarget; // #completeModal123 etc.

                            fetch('get_modal.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: new URLSearchParams({
                                    project_id: projectId,
                                    modal_type: modalType,
                                    current_user_type: <?= json_encode($current_user_type) ?>,
                                    current_user_id: <?= json_encode($current_user_id) ?>
                                })
                            })
                                .then(r => r.text())
                                .then(html => {
                                    // inject into container
                                    document.getElementById('modalContainer').innerHTML = html;

                                    // show modal
                                    const el = document.querySelector(modalTarget);
                                    if (!el) return console.warn('Modal element not found:', modalTarget);
                                    const modal = new bootstrap.Modal(el);
                                    modal.show();

                                    // auto-remove from DOM when hidden
                                    el.addEventListener('hidden.bs.modal', () => el.remove(), { once: true });
                                })
                                .catch(err => console.error('Ajax modal error:', err));
                        });
                    });
                });
            </script>
            <script>
                const urlParams = new URLSearchParams(window.location.search);
                const tab = urlParams.get('tab') || 'open';
                const tabEl = document.querySelector(`#${tab}-tab`);
                if (tabEl) {
                    new bootstrap.Tab(tabEl).show();
                }
            </script>
            <script>
                <?php
                // Preload all invoice data for edit modal (no AJAX)
                $edit_invoice_data = [];
                if ($current_user_type == 1) {
                    $query = "SELECT invoice_id, due_date, total_amount, paid_amount, balance, discount, service_provider_id, payment_details 
                  FROM billing_invoice";
                    $result = mysqli_query($con, $query);
                    while ($inv = mysqli_fetch_assoc($result)) {
                        $edit_invoice_data[$inv['invoice_id']] = $inv;
                    }
                }
                ?>
                const invoiceData = <?= json_encode($edit_invoice_data) ?>;

                $(document).on('click', '.edit-invoice-btn', function () {
                    const invoiceId = $(this).data('invoice-id');
                    const data = invoiceData[invoiceId];

                    if (data) {
                        $('#editInvoiceId').text(invoiceId);
                        $('#edit_invoice_id').val(invoiceId);
                        $('#edit_due_date').val(data.due_date);
                        $('#edit_total_amount').val(data.total_amount);
                        $('#edit_paid_amount').val(data.paid_amount);
                        $('#edit_balance').val(data.balance);
                        $('#edit_discount').val(parseFloat(data.discount) || 0);
                        $('#edit_description').val(data.payment_details);
                        $('#edit_service_provider').val(data.service_provider_id);
                    }
                });

                // 🔹 Auto-update calculations
                function recalcInvoiceFields() {
                    let total = parseFloat($('#edit_total_amount').val()) || 0;
                    let paid = parseFloat($('#edit_paid_amount').val()) || 0;
                    let discount = parseFloat($('#edit_discount').val()) || 0;

                    // Apply discount first
                    let discountedTotal = total - discount;
                    if (discountedTotal < 0) discountedTotal = 0;

                    // Calculate balance
                    let balance = discountedTotal - paid;
                    if (balance < 0) balance = 0;

                    // Update fields
                    $('#edit_balance').val(balance.toFixed(2));
                }

                // 🔹 Trigger recalculation on input change
                $(document).on('input', '#edit_total_amount, #edit_paid_amount, #edit_discount', recalcInvoiceFields);
            </script>

        </div>
    </div>


    <?php include "includes/scripts.php"; ?>
    <?php include "includes/footer.php"; ?>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            console.log(<?php echo json_encode($data); ?>);
            try {
                var ctxTotalProjects = document.getElementById('totalProjectsChart');
                if (!ctxTotalProjects) throw new Error('Canvas #totalProjectsChart not found');
                new Chart(ctxTotalProjects.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['Open', 'Completed'],
                        datasets: [{
                            data: [<?php echo max(0, $data['open_projects']); ?>, <?php echo max(0, $data['completed_projects']); ?>],
                            backgroundColor: ['#252161', '#C9A227']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 10 } } }
                        }
                    }
                });
            } catch (e) {
                console.error('Error initializing totalProjectsChart:', e);
            }
            try {
                var ctxOpenProjects = document.getElementById('openProjectsChart');
                if (!ctxOpenProjects) throw new Error('Canvas #openProjectsChart not found');
                new Chart(ctxOpenProjects.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Open', 'Others'],
                        datasets: [{
                            data: [<?php echo max(0, $data['open_projects']); ?>, <?php echo max(0, $data['total_projects'] - $data['open_projects']); ?>],
                            backgroundColor: ['#252161', '#C9A227']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 10 } } }
                        }
                    }
                });
            } catch (e) {
                console.error('Error initializing openProjectsChart:', e);
            }
            try {
                var ctxCompletedProjects = document.getElementById('completedProjectsChart');
                if (!ctxCompletedProjects) throw new Error('Canvas #completedProjectsChart not found');
                new Chart(ctxCompletedProjects.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Completed', '       Others'],
                        datasets: [{
                            data: [<?php echo max(0, $data['completed_projects']); ?>, <?php echo max(0, $data['total_projects'] - $data['completed_projects']); ?>],
                            backgroundColor: ['#252161', '#C9A227']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 8.5 } } }
                        }
                    }
                });
            } catch (e) {
                console.error('Error initializing completedProjectsChart:', e);
            }
            <?php if ($user_type == '1' || $user_type == '2') { ?>
                try {
                    var ctxProjectInvoices = document.getElementById('projectInvoicesChart');
                    if (!ctxProjectInvoices) throw new Error('Canvas #projectInvoicesChart not found');
                    new Chart(ctxProjectInvoices.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['       Invoiced', 'Non-Invoiced'],
                            datasets: [{
                                data: [<?php echo max(0, $data['project_invoices']); ?>, <?php echo max(0, $data['total_projects'] - $data['project_invoices']); ?>],
                                backgroundColor: ['#252161', '#C9A227']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { font: { size: 9 } } }
                            }
                        }
                    });
                } catch (e) {
                    console.error('Error initializing projectInvoicesChart:', e);
                }
            <?php } ?>
        });
        /* ====== jQuery plugin (SweetAlert2) confirm for RE-OPEN ====== */
        $(document).on('click', '.reopen-btn', function () {
            const projectId = $(this).data('pid');

            Swal.fire({
                title: 'Re-open project?',
                text: 'Project #' + projectId + ' will be set to OPEN again.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, re-open it!'
            }).then((result) => {
                if (result.isConfirmed) {

                    const $form = $('<form method="POST"></form>');
                    $form.append('<input name="project_id" value="' + projectId + '">');
                    $form.append('<input name="reopen_project" value="1">');
                    $('body').append($form);
                    $form.submit();          // send it
                }
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- ===== DataTables + Buttons (export) stack ===== -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script>
        jQuery(function ($) {
            // Export only the data columns (skip the trailing "Action" column)
            var exportCols = ':visible:not(:last-child)';

            function buildButtons(title) {
                return [
                    { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copy', titleAttr: 'Copy', title: title, className: 'btn btn-sm btn-secondary', exportOptions: { columns: exportCols } },
                    { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', titleAttr: 'Excel', title: title, className: 'btn btn-sm btn-success', exportOptions: { columns: exportCols } },
                    { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', titleAttr: 'CSV', title: title, className: 'btn btn-sm btn-info', exportOptions: { columns: exportCols } },
                    { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', titleAttr: 'PDF', title: title, orientation: 'landscape', pageSize: 'A4', className: 'btn btn-sm btn-danger', exportOptions: { columns: exportCols } },
                    { extend: 'print', text: '<i class="fas fa-print"></i> Print', titleAttr: 'Print', title: title, className: 'btn btn-sm btn-dark', exportOptions: { columns: exportCols } },
                    { extend: 'colvis', text: '<i class="fas fa-columns"></i> Columns', className: 'btn btn-sm btn-primary' }
                ];
            }

            function buildTable(selector, title) {
                var $t = $(selector);
                if (!$t.length || $.fn.DataTable.isDataTable($t)) return null;
                var lastIdx = $t.find('thead th').length - 1;
                return $t.DataTable({
                    dom: "<'row align-items-center mb-2 gx-2'<'col-md-auto col-12'l><'col-md dt-toolbar-buttons'B><'col-md-auto col-12'f>>" +
                         "r<'table-responsive't>" +
                         "<'row mt-2 align-items-center'<'col-md-5'i><'col-md-7'p>>",
                    order: [[0, 'desc']],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                    columnDefs: [
                        { orderable: false, searchable: false, targets: lastIdx }
                    ],
                    language: {
                        search: '_INPUT_',
                        searchPlaceholder: 'Search projects...',
                        lengthMenu: '_MENU_',
                        info: 'Showing _START_ to _END_ of _TOTAL_ projects',
                        infoEmpty: 'No projects found',
                        infoFiltered: '(filtered from _MAX_ total projects)',
                        zeroRecords: 'No matching projects found'
                    },
                    buttons: buildButtons(title)
                });
            }

            buildTable('#openProjectsTable', 'Open Projects');
            buildTable('#completedProjectsTable', 'Completed Projects');

            // Fix column widths when a hidden tab becomes visible
            $('#projectTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });

            // Expand / collapse the invoice details as a DataTables child row
            $(document).on('click', '.toggle-invoice-btn', function () {
                var btn = $(this);
                var $tableEl = btn.closest('table');
                if (!$.fn.DataTable.isDataTable($tableEl)) return;

                var dt = $tableEl.DataTable();
                var row = dt.row(btn.closest('tr'));
                var icon = btn.find('i');

                if (row.child.isShown()) {
                    row.child.hide();
                    icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    btn.attr('title', 'Show Invoices');
                } else {
                    var pid = btn.data('project-id');
                    var html = $('#invoice-detail-' + pid).html() ||
                        '<div class="p-3 text-muted">No invoices found for this project.</div>';
                    row.child($('<div>').html(html).contents(), 'child-invoice-cell').show();
                    icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    btn.attr('title', 'Hide Invoices');
                }
            });
        });
    </script>
</body>

</html>
