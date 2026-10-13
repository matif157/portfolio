<?php
/*  SPS ASSISTANT  ─  JSON endpoint
    -------------------------------------------------------------------------
    Auth-gated POST endpoint. Routes AI actions:
      chat            natural-language message; may run a secure read query
      check_mode      reports which backend (Gemini vs local Ollama) is live
      ocr             extract fields from an uploaded image (Gemini vision)
      preview_client  build a "verify to insert" preview for a new client
      confirm_client  execute the confirmed new-client insert
      preview_invoice / confirm_invoice      invoices (billing_invoice)
      preview_instant / confirm_instant      instant invoices (billing_invoice_instant)
      preview_project / confirm_project      projects
      preview_estimate / confirm_estimate    estimates / quotations
      preview_note    build a preview for adding a note/comment
      confirm_note    execute the confirmed note insert
      export_csv      stream a query result as a downloadable .csv
      export_pdf      stream a query result as a downloadable .pdf (dompdf)
    Reads use a strict SELECT-only validator + START TRANSACTION READ ONLY.
    Writes always pass through preview -> confirm and are gated by ai.write.
    ------------------------------------------------------------------------- */
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
require_once "includes/roles.php";
include "includes/ai_helper.php";
include "includes/activity_log.php";

if (!class_exists('Dompdf\\Dompdf')) { include_once __DIR__ . '/dompdf/autoload.inc.php'; }

if (!function_exists('getInitials')) {
    function getInitials($name)
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) { $initials .= strtoupper(substr($word, 0, 1)); }
        return $initials;
    }
}

/* Drop-in copy of the canonical invoice PDF builder used by client_billing.php,
   so SPS Assistant invoices ship with the same branded PDF and filelink. */
if (!function_exists('regenerate_invoice_pdf')) {
    function regenerate_invoice_pdf($con, $invoice_id)
    {
        $invoice_id = (int) $invoice_id;
        $inv = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_invoice WHERE invoice_id='$invoice_id' LIMIT 1"));
        if (!$inv) { return false; }
        $memberid = (int) $inv['mem_id'];
        $name = $address = $address1 = $email = $phone = '';
        if ($fc = mysqli_fetch_array(mysqli_query($con, "SELECT * FROM members WHERE member_id='$memberid'"))) {
            if ($fc['bus_name'] != '') {
                $name = $fc['bus_name']; $address = $fc['address']; $email = $fc['prm_email'];
            } elseif ($fc['dba'] != '') {
                $name = $fc['dba']; $address = $fc['address']; $email = $fc['prm_email'];
            } else {
                $name = $fc['name'] . " " . $fc['lname']; $address = $fc['hstreet']; $email = $fc['sec_email'];
            }
            $address1 = $fc['hcity'] . ", " . $fc['hstate'] . ", " . $fc['hzip'];
            $phone = $fc['cell_ph'];
        }
        $drows = '';
        $itq = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id' ORDER BY inv_item_id ASC");
        while ($it = mysqli_fetch_array($itq)) {
            $drows .= '<tr class="item"><td><b>' . htmlspecialchars($it['item_description']) . '</b><p style="font-size:12px;margin:0px">' . htmlspecialchars($it['item_sub_description']) . '</p></td><td>$' . number_format($it['unit_price'], 2) . '</td></tr>';
        }
        $sp_name = '';
        if (!empty($inv['service_provider_id'])) {
            if ($spr = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . (int) $inv['service_provider_id'] . "'"))) {
                $sp_name = getInitials($spr['user_name']);
            }
        }
        $pm = mysqli_fetch_assoc(mysqli_query($con, "SELECT payment_method FROM billing_payment_history WHERE inv_id='$invoice_id' ORDER BY his_id DESC LIMIT 1"));
        $payment_methods_str = ($pm && $pm['payment_method']) ? $pm['payment_method'] : '';
        $img_base = 'http://192.168.15.29/storage/uploads/logo/';
        switch ((string) $inv['invoice_status']) {
            case '3': $status_text = 'Paid'; $status_img = $img_base . 'paid.png'; break;
            case '2': $status_text = 'Partial Paid'; $status_img = $img_base . 'partial_payment.jpg'; break;
            default:  $status_text = 'UnPaid'; $status_img = $img_base . 'unpaid.jpg'; break;
        }
        $totalamount = number_format((float) $inv['total_amount'], 2);
        $newpaid     = number_format((float) $inv['paid_amount'], 2);
        $newbalance  = number_format((float) $inv['balance'], 2);
        $discount    = (float) $inv['discount'];
        $created = !empty($inv['invoice_date']) ? date('m/d/Y', strtotime($inv['invoice_date'])) : '';
        $due     = (!empty($inv['due_date']) && $inv['due_date'] != '0000-00-00') ? date('m/d/Y', strtotime($inv['due_date'])) : '';
        $by      = htmlspecialchars((string) ($inv['employee_initials'] ?? ''));
        $description = htmlspecialchars((string) ($inv['payment_details'] ?? ''));
        $paidLine = '';
        if (($inv['invoice_status'] == 2 || $inv['invoice_status'] == 3) && !empty($inv['paid_date']) && $inv['paid_date'] != '0000-00-00') {
            $paidLine = $status_text . ': ' . date('m/d/Y', strtotime($inv['paid_date'])) . '<br>';
        }
        $discountRow = $discount > 0 ? '<tr class="total"><td></td><td style="font-weight:bold;">Discount: $' . number_format($discount, 2) . '</td></tr>' : '';
        $pmLine = $payment_methods_str !== '' ? '<br><b>Payment Method(s):</b> ' . htmlspecialchars($payment_methods_str) : '';
        $content = '
        <style>
            .invoice-box{position:relative;height:99%;width:100%;margin:auto;border:1px solid #eee;box-shadow:0 0 10px rgba(0,0,0,0.15);font-size:16px;line-height:24px;color:#555;}
            .invoice-box .footer-note{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);text-align:center;font-size:14px;}
            .invoice-box .footer-note p{margin:0;font-size:14px;white-space:nowrap;}
            .invoice-box .footer-note h4{margin-top:5px;font-size:16px;}
            .invoice-box table{width:100%;line-height:inherit;text-align:left;border-collapse:collapse;}
            .invoice-box table td{padding:5px;vertical-align:top;}
            .invoice-box table tr td:nth-child(2){text-align:right;}
            .invoice-box table tr.heading td{background:#8b742a;border-bottom:1px solid #ddd;font-weight:bold;color:white;font-size:22px;}
            .invoice-box table tr.item td{border-bottom:1px solid #eee;}
            .invoice-box table tr.total td:nth-child(2){border-top:2px solid #eee;font-weight:bold;}
            .bank-details{font-size:14px;line-height:1.6;margin-top:10px;} .bank-details strong{color:#333;}
        </style>
        <div class="invoice-box">
            <table>
                <tr class="top"><td colspan="2"><table><tr>
                    <td class="title"><img src="' . $img_base . 'logo-dark.png" style="width:60%;max-width:300px;"></td>
                    <td>
                        Invoice #: ' . $invoice_id . '<br>
                        By: ' . $by . '<br>
                        Created: ' . $created . '<br>
                        Due: ' . $due . '<br>
                        ' . $paidLine . '
                        <img src="' . $status_img . '" style="width:30%;margin-top:12px;max-width:300px" />
                    </td>
                </tr></table></td></tr>
                <tr class="information"><td colspan="2"><table><tr>
                    <td>From:<br>Syed Professional Services<br>1012 Coney Island Ave<br>Brooklyn, NY 11230<br>Phone: (718) 859-5392<br>Fax: (718) 859-2237<br>invoice@syedpro.com</td>
                    <td><b>To:</b> <br>' . $name . '<br>' . $address . '<br>' . $address1 . '<br>' . $phone . '<br>' . $email . '</td>
                </tr></table></td></tr>
                <tr class="heading"><td>Service</td><td>Price</td></tr>
                <tr class="details"><td><b>Service Provider:</b> ' . $sp_name . ' (' . $inv['service_provider_id'] . ')</td></tr>
                ' . $drows . '
                <tr class="details"><td colspan="2"><b>Description:</b> ' . $description . '</td></tr>
                <tr class="total"><td></td><td>Total: $' . $totalamount . '</td></tr>
                <tr class="total"><td></td><td>Paid: $' . $newpaid . '</td></tr>
                ' . $discountRow . '
                <tr class="total"><td></td><td>Balance: $' . $newbalance . '</td></tr>
                <tr class="details"><td><div class="bank-details"><strong>Zelle Payment Info:</strong> info@syedpro.com' . $pmLine . '</div></td></tr>
            </table>
            <div class="footer-note">
                <p>A late fee of $35 per month will be applied to any outstanding balance not paid within the due date.
                <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.</p>
                <h4><u>Thank you for choosing Syed Professional Services</u></h4>
            </div>
        </div>';
        $dompdf = new \Dompdf\Dompdf(array('enable_remote' => true));
        $dompdf->loadHtml($content);
        $dompdf->render();
        $US_Date = date('m-d-Y');
        $PDFFileN = "INV-{$invoice_id}-MemID-{$memberid}-{$US_Date}.pdf";
        file_put_contents(__DIR__ . '/invoices/' . $PDFFileN, $dompdf->output());
        mysqli_query($con, "UPDATE billing_invoice SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id='$invoice_id'");
        return true;
    }
}

/* Drop-in copy of the instant-invoice PDF builder used by instant_invoice.php,
   so SPS Assistant instant invoices get the same branded PDF + filelink. */
if (!function_exists('regenerate_instant_invoice_pdf')) {
    function regenerate_instant_invoice_pdf($con, $invoice_id)
    {
        $invoice_id = (int) $invoice_id;
        $inv = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM billing_invoice_instant WHERE invoice_id='$invoice_id' LIMIT 1"));
        if (!$inv) { return false; }
        $name = $inv['business_name'] !== '' ? $inv['business_name'] : $inv['name'];
        $address = (string) $inv['address'];
        $address1 = trim(($inv['city'] ?? '') . ', ' . ($inv['state'] ?? '') . ', ' . ($inv['zip'] ?? ''), " ,");
        $phone = (string) $inv['phone'];
        $email = (string) $inv['email'];
        $drows = '';
        $itq = mysqli_query($con, "SELECT * FROM billing_invoice_items WHERE inv_id='$invoice_id' ORDER BY inv_item_id ASC");
        while ($it = mysqli_fetch_array($itq)) {
            $drows .= '<tr class="item"><td><b>' . htmlspecialchars($it['item_description']) . '</b><p style="font-size:12px;margin:0px">' . htmlspecialchars($it['item_sub_description']) . '</p></td><td>$' . number_format($it['unit_price'], 2) . '</td></tr>';
        }
        $sp_name = '';
        if (!empty($inv['service_provider_id'])) {
            if ($spr = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_name FROM users WHERE user_id='" . (int) $inv['service_provider_id'] . "'"))) {
                $sp_name = getInitials($spr['user_name']);
            }
        }
        $img_base = 'http://192.168.15.29/storage/uploads/logo/';
        switch ((string) $inv['invoice_status']) {
            case '3': $status_text = 'Paid'; $status_img = $img_base . 'paid.png'; $status_width = '40%'; break;
            case '2': $status_text = 'Partial Paid'; $status_img = $img_base . 'partial_payment.jpg'; $status_width = '40%'; break;
            default:  $status_text = 'UnPaid'; $status_img = $img_base . 'unpaid.jpg'; $status_width = '15%'; break;
        }
        $subTotal = number_format((float) $inv['total_amount'], 2);
        $grandTotal = number_format((float) $inv['balance'], 2);
        $discount = (float) $inv['discount'];
        $discountRow = $discount > 0 ? '<tr class="total"><td></td><td style="font-weight:bold;">Discount: $' . number_format($discount, 2) . '</td></tr>' : '';
        $created = !empty($inv['invoice_date']) ? date('m/d/Y', strtotime($inv['invoice_date'])) : '';
        $due = (!empty($inv['due_date']) && $inv['due_date'] != '0000-00-00') ? date('m/d/Y', strtotime($inv['due_date'])) : '';
        $by = htmlspecialchars((string) ($inv['employee_initials'] ?? ''));
        $paidLine = '';
        if (($inv['invoice_status'] == 2 || $inv['invoice_status'] == 3) && !empty($inv['paid_date']) && $inv['paid_date'] != '0000-00-00') {
            $paidLine = 'Paid Date: ' . date('m/d/Y', strtotime($inv['paid_date'])) . '<br>';
        }
        $content = '
        <style>
            .invoice-box{position:relative;height:99%;width:100%;margin:auto;border:1px solid #eee;box-shadow:0 0 10px rgba(0,0,0,0.15);font-size:16px;line-height:24px;color:#555;}
            .invoice-box .footer-note{position:absolute;bottom:-20px;left:50%;transform:translateX(-50%);text-align:center;font-size:14px;}
            .invoice-box .footer-note p{margin:0;font-size:14px;white-space:nowrap;}
            .invoice-box .footer-note h4{margin-top:5px;font-size:16px;}
            .invoice-box table{width:100%;line-height:inherit;text-align:left;border-collapse:collapse;}
            .invoice-box table td{padding:5px;vertical-align:top;}
            .invoice-box table tr td:nth-child(2){text-align:right;}
            .invoice-box table tr.heading td{background:#8b742a;border-bottom:1px solid #ddd;font-weight:bold;color:white;font-size:22px;}
            .invoice-box table tr.item td{border-bottom:1px solid #eee;}
            .invoice-box table tr.total td:nth-child(2){border-top:2px solid #eee;font-weight:bold;}
        </style>
        <div class="invoice-box">
            <table>
                <tr class="top"><td colspan="2"><table><tr>
                    <td class="title"><img src="' . $img_base . 'logo-dark.png" style="width:60%;max-width:300px;"></td>
                    <td>
                        Invoice #: ' . $invoice_id . '<br>
                        By: ' . $by . '<br>
                        Created: ' . $created . '<br>
                        Due: ' . $due . '<br>
                        ' . $paidLine . '
                        <img src="' . $status_img . '" style="width:' . $status_width . ';margin-top:12px;max-width:300px" />
                    </td>
                </tr></table></td></tr>
                <tr class="information"><td colspan="2"><table><tr>
                    <td>From:<br>Syed Professional Services<br>1012 Coney Island Ave<br>Brooklyn, NY 11230<br>Phone: (718) 859-5392<br>Fax: (718) 859-2237<br>invoice@syedpro.com</td>
                    <td><b>To:</b> <br>' . htmlspecialchars($name) . '<br>' . htmlspecialchars($address) . '<br>' . htmlspecialchars($address1) . '<br>' . htmlspecialchars($phone) . '<br>' . htmlspecialchars($email) . '</td>
                </tr></table></td></tr>
                <tr class="heading"><td>Service</td><td>Price</td></tr>
                <tr class="details"><td><b>Service Provider:</b> ' . $sp_name . ' (' . $inv['service_provider_id'] . ')</td></tr>
                ' . $drows . '
                <tr class="total"><td></td><td>Sub Total: $' . $subTotal . '</td></tr>
                ' . $discountRow . '
                <tr class="total"><td></td><td>Grand Total: $' . $grandTotal . '</td></tr>
            </table>
            <div class="footer-note">
                <p>In the event of a returned or unsuccessful payment, a bank fee of $25 will be applied.
                <br>We accept the payment via Zelle,Credit or debit Card,Cash and cheque.</p>
                <h4><u>Thank you for choosing Syed Professional Services</u></h4>
            </div>
        </div>';
        $dompdf = new \Dompdf\Dompdf(array('enable_remote' => true));
        $dompdf->loadHtml($content);
        $dompdf->render();
        $US_Date = date('m-d-Y');
        $PDFFileN = "INV-{$invoice_id}-inst-{$US_Date}.pdf";
        $dir = __DIR__ . '/instant_invoices';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        file_put_contents($dir . '/' . $PDFFileN, $dompdf->output());
        mysqli_query($con, "UPDATE billing_invoice_instant SET filelink='" . mysqli_real_escape_string($con, $PDFFileN) . "' WHERE invoice_id='$invoice_id'");
        return true;
    }
}

if (!ai_can_use()) {
    echo json_encode(array('ok' => false, 'error' => 'You do not have permission to use the SPS Assistant.'));
    exit;
}
if (ai_get('ai_enabled', '1') !== '1') {
    // The launcher is hidden when disabled; if reached anyway, block everything.
    echo json_encode(array('ok' => false, 'error' => 'The SPS Assistant is disabled in AI Settings.'));
    exit;
}
$action = $_POST['action'] ?? '';
$uid = (int) ($_SESSION['user_id'] ?? 0);

/* ---------------- Session wrapper (chat memory) ------------------------- */
/* Each user has at most one 'active' session. Messages are written to that
   session so the assistant remembers the whole conversation. The user ends a
   session by saying "end session" (handled below). */
$curSession = null;
if (!in_array($action, array('session_list', 'session_new', 'session_open'), true)) {
    $curSession = ai_session_active($uid);
    if (!$curSession) {
        $curSession = array('session_id' => ai_session_create($uid), 'title' => 'New Session', 'status' => 'active');
        ai_session_title($uid, $curSession['session_id'], 'Session ' . date('M j, g:i A'));
    }
}
$curSid = $curSession ? (int) $curSession['session_id'] : 0;

/* ---------------- Secure read query runner ------------------------------ */
function ai_run_select($raw)
{
    global $con;
    $sql = trim($raw);
    $sql = preg_replace('/;\s*$/', '', $sql);
    if ($sql === '') {
        return array('error' => 'Empty query.');
    }
    if (preg_match('/;/', $sql)) {
        return array('error' => 'Multiple statements are not allowed.');
    }
    if (!preg_match('/^\s*SELECT/i', $sql)) {
        return array('error' => 'Only SELECT queries are allowed (read-only).');
    }
    $bad = array('insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'grant', 'into', 'outfile', 'dumpfile', 'load_file', 'sleep',
        'benchmark', '@@', 'procedure', 'handler', 'replace ', 'rename', 'call ');
    foreach ($bad as $b) {
        if (stripos($sql, $b) !== false) {
            return array('error' => 'Query blocked (contains disallowed keyword: ' . $b . ').');
        }
    }
    $sql = preg_replace('/\s+LIMIT\s+\d+(?:\s*,\s*\d+)?\s*$/i', ' ', $sql);
    $sql .= ' LIMIT 200';
    $con->query('START TRANSACTION READ ONLY');
    try {
        $res = $con->query($sql);
    } catch (mysqli_sql_exception $e) {
        $con->query('COMMIT');
        return array('error' => 'Query error: ' . $e->getMessage());
    }
    $con->query('COMMIT');
    if (!$res) {
        return array('error' => 'Query error: ' . $con->error);
    }
    $rows = array();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return array('rows' => $rows);
}

/* Pull the first SQL SELECT statement out of a possibly messy LLM reply:
   handles markdown fences, inline backticks, surrounding prose and trailing
   explanatory text. Returns '' when no SELECT is present. */
function ai_extract_select($text)
{
    $t = preg_replace('/```[a-z]*\s*/i', '', $text);
    $t = str_replace('```', '', $t);
    $pos = stripos($t, 'select');
    if ($pos === false) { return ''; }
    $sql = substr($t, $pos);
    $end = strpos($sql, ';');
    if ($end !== false) { $sql = substr($sql, 0, $end + 1); }
    $sql = str_replace('`', '', $sql);
    $sql = trim($sql);
    return $sql;
}

/* ---------------- Text/tool helper --------------------------------------- */
function ai_chat_prompt($transcript = '')
{
    /* On a small/local (Ollama) LLM, use the compact prompt so responses stay fast. */
    if (ai_mode() === 'offline') { return ai_chat_prompt_compact($transcript); }
    $schema = ai_db_schema();
    $mods = ai_module_descriptions();
    $desc = '';
    foreach ($mods as $m => $txt) {
        $desc .= "- " . $m . ": " . $txt . "\n";
    }
    $rels = implode("\n", $schema['relationships']);
    $hist = ($transcript !== '') ? "CONVERSATION SO FAR (remember this; refer back to it when needed):\n$transcript\n\n" : '';
    return "You are the SPS Assistant for a client/accounting CRM (Syed Professional Services). "
        . "You help staff find and understand CRM data, generate files, and (with approval) add data.\n"
        . "$hist"
        . "MODULES:\n$desc\n"
        . "JOIN RELATIONSHIPS (use these to link data):\n$rels\n"
        . "RULES:\n"
        . "- To answer data questions, produce exactly ONE valid MySQL SELECT as plain text: start it with SELECT and end it with a semicolon. No markdown code fences, no backticks, no SQL comments. After the semicolon you may add a short human explanation.\n"
        . "- Use ONLY the real tables in the MODULES list above (members, billing_invoice, billing_invoice_instant, billing_invoice_items, notestab, projects, quotations, orders, memberships, billing_payment_history, settings, etc.) with their real columns. NEVER invent tables or columns (there are no 'clients' or 'invoices' tables).\n"
        . "- To ADD a new client, output exactly a line starting with NEWCLIENT: followed by a single-line JSON object of fields e.g. {name, lname, bus_name, phone, cell_ph, prm_email, address, city, state, zip, type, ss, ein}. Then a short confirmation sentence.\n"
        . "- To ADD a note to a client, output NOTE: <member_id> | <text>.\n"
        . "- To ADD a file to a client, output FILEADD: <member_id> | <filename> | <notes>.\n"
        . "- To ADD an invoice, output exactly a line starting with NEWINVOICE: followed by JSON {mem_id, due_date, billing_cycle, items:[{item_description, unit_price, quantity}]}.\n"
        . "- To ADD an instant/one-off invoice, output exactly a line starting with NEWINSTANT: followed by JSON {name, business_name, email, phone, address, city, state, zip, due_date, items:[{item_description, unit_price, quantity}]}.\n"
        . "- To ADD a project, output exactly a line starting with NEWPROJECT: followed by JSON {mem_id, prod_id, project_title, project_description, project_status, project_type, start_date}.\n"
        . "- To ADD an estimate/quotation, output exactly a line starting with NEWESTIMATE: followed by JSON {member_id, name, phone, email, address, services, sub_total, discount, grand_total}.\n"
        . "- To ADD a payment plan, output exactly a line starting with NEWPLAN: followed by JSON {client_id, plan_name, plan_type, billing_cycle, payment_method, start_date, end_date, total_amount, items:[{service_name, amount, frequency}]}.\n"
        . "- To OPEN a client profile or a module, output exactly OPEN: <member_id> (to open a client) or OPEN: <module> where module is one of clients|invoices|projects|services|payments|calendar|settings|estimates|plans (to open a module page).\n"
        . "- If you are in SEARCH mode and the user wants to find a client by email/name/phone, search with LIKE (e.g. prm_email LIKE '%term%' OR name LIKE '%term%' OR bus_name LIKE '%term%') and if no exact match say 'No exact match — similar records:' and show close matches.\n"
        . "- If a client was just created in this conversation, remember its id and answer later questions about it.\n"
        . "- Never claim to write data without the NEWCLIENT/NOTE/FILEADD/NEWINVOICE/NEWINSTANT/NEWPROJECT/NEWESTIMATE/NEWPLAN markers; instead say what you would add and ask to confirm.\n"
        . "- If the user says 'end session' or 'end', wrap up the current task, ask if there is anything else, and confirm the session is being saved.\n"
        . "- If the user just chats, reply conversationally and helpfully.\n"
        . "- Keep answers concise.";
}

/* Compact system prompt for local/low-spec LLMs (Ollama) — same rules, much
   smaller so small models answer quickly. */
function ai_chat_prompt_compact($transcript = '')
{
    $hist = ($transcript !== '') ? "CONVERSATION SO FAR:\n" . substr($transcript, 0, 800) . "\n\n" : '';
    return "You are SPS Assistant for a client/accounting CRM (Syed Professional Services). Answer briefly and helpfully.\n"
        . "$hist"
        . "DATA QUERIES: answer with ONE valid MySQL SELECT as plain text starting with SELECT and ending with a semicolon (no code fences/backticks, no invented tables — real tables only: members, billing_invoice, billing_invoice_items, notestab, projects, quotations), then a 1-line explanation. Search clients by prm_email/name/bus_name/phone LIKE '%term%'.\n"
        . "CREATE (only when the user clearly asks; always confirm before creating):\n"
        . "  NEWCLIENT: {name,lname,bus_name,phone,cell_ph,prm_email,address,city,state,zip,type,ss,ein}\n"
        . "  NOTE: <member_id> | <text>\n"
        . "  FILEADD: <member_id> | <filename> | <notes>\n"
        . "  NEWINVOICE: {mem_id,due_date,billing_cycle,items:[{item_description,unit_price,quantity}]}\n"
        . "  NEWINSTANT: {name,business_name,email,phone,address,city,state,zip,due_date,items:[{item_description,unit_price,quantity}]}\n"
        . "  NEWPROJECT: {mem_id,prod_id,project_title,project_description,project_status,project_type,start_date}\n"
        . "  NEWESTIMATE: {member_id,name,phone,email,address,services,sub_total,discount,grand_total}\n"
        . "  NEWPLAN: {client_id,plan_name,plan_type,billing_cycle,payment_method,start_date,end_date,total_amount,items:[{service_name,amount,frequency}]}\n"
        . "  OPEN: <member_id> or OPEN: clients|invoices|projects|services|payments|calendar|settings|estimates|plans\n"
        . "Use ONLY CRM tables. Never claim an insert was made without one of the markers above.";
}

/* Per-tab behaviour hints appended to the system prompt (INSERT / SEARCH /
   TALK / REPORT). The active tab is posted with every message. */
function ai_mode_instruction($mode)
{
    switch ($mode) {
        case 'insert':
            return "\n\nCURRENT TAB: INSERT. User wants to ADD data. Focus on extracting NEWCLIENT/NEWINVOICE/NEWINSTANT/NEWPROJECT/NEWESTIMATE/NEWPLAN markers (with confirmation previews). If the user is asking to read data instead, gently switch them to SEARCH.";
        case 'search':
            return "\n\nCURRENT TAB: SEARCH. User wants to LOOK UP data. Answer with ONE valid MySQL SELECT as plain text starting with SELECT and ending with a semicolon (no code fences, no backticks, no comments, no invented tables — use only the real MODULES tables above like members, billing_invoice, billing_invoice_items, notestab, projects, quotations), then a short explanation. Prefer LIKE matching on prm_email/name/bus_name/phone. Matched tables auto-gain Download CSV/PDF buttons.";
        case 'report':
            return "\n\nCURRENT TAB: REPORT. User wants a SUMMARY/REPORT. Write ONE MySQL SELECT that aggregates real data (COUNT/SUM/GROUP BY dates or status) using ONLY the real MODULES tables above (e.g. members, billing_invoice, billing_invoice_instant, billing_invoice_items, notestab, projects, quotations) and their real columns — never invent tables. Output the SQL as plain text starting with SELECT and ending with a semicolon — no code fences, no backticks, no comments, nothing inside the query. After the semicolon, add a short written summary of the key numbers. Example of a correct report: SELECT m.member_id, m.name, m.phone, m.prm_email, COUNT(bi.invoice_id), SUM(bi.balance) FROM members m JOIN billing_invoice bi ON m.member_id = bi.mem_id WHERE bi.balance > 0 GROUP BY m.member_id;";
        case 'talk':
        default:
            return "\n\nCURRENT TAB: TALK. Be conversational and concise. Answer questions, give advice about the CRM, and only run a SELECT or insert marker if the user clearly asks.";
    }
}

/* The front-end sends the editable preview fields as a JSON string. */
function ai_post_fields()
{
    $f = $_POST['fields'] ?? array();
    if (is_string($f)) { $f = json_decode($f, true); }
    return is_array($f) ? $f : array();
}

/* ---------------- Handlers ------------------------------------------------ */
switch ($action) {

    case 'chat': {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') { echo json_encode(array('ok' => false, 'error' => 'Empty message.')); break; }

        /* ---- End-session intent (matches "end", "end session", also voice) --- */
        if (preg_match('/^\s*(end|end session|stop|that' . "'" . 's all|finish|bye session|close session)\s*[.!]?\s*$/i', $message)) {
            ai_chat_append($uid, $curSid, 'user', $message);
            ai_session_end($uid, $curSid);
            $say = "Session #$curSid has ended and the conversation is saved. If you don't want me to remember it, start a new session when you're ready. Is there anything else?";
            ai_chat_append($uid, $curSid, 'assistant', $say);
            echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $say, 'session_ended' => true, 'session_id' => $curSid));
            break;
        }

        /* Store the user message in the active session. */
        ai_chat_append($uid, $curSid, 'user', $message);
        $transcript = ai_build_transcript($uid, $curSid);
        $umode = $_POST['mode'] ?? '';
        $r = ai_backend_call(array(array('text' => $message)), ai_chat_prompt($transcript) . ai_mode_instruction($umode));
        if (isset($r['error'])) {
            if (isset($r['message'])) {
                echo json_encode(array('ok' => false, 'error' => $r['message']));
                break;
            }
            $local = ai_local_answer($message);
            if (isset($local['text'])) {
                echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $local['text']));
            } else {
                echo json_encode(array('ok' => false, 'error' => $r['error']));
            }
            break;
        }
        $text = trim($r['text']);
        /* Remember the assistant reply in the same session. */
        ai_chat_append($uid, $curSid, 'assistant', $text);

        // NEWCLIENT intent
        if (preg_match('/NEWCLIENT:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newclient', 'fields' => $fields, 'text' => 'New client detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NEWINVOICE intent -> invoice preview
        if (preg_match('/NEWINVOICE:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newinvoice', 'fields' => $fields, 'text' => 'New invoice detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NEWINSTANT intent -> instant invoice preview
        if (preg_match('/NEWINSTANT:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newinstant', 'fields' => $fields, 'text' => 'New instant invoice detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NEWPROJECT intent -> project preview
        if (preg_match('/NEWPROJECT:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newproject', 'fields' => $fields, 'text' => 'New project detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NEWESTIMATE intent -> estimate preview
        if (preg_match('/NEWESTIMATE:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newestimate', 'fields' => $fields, 'text' => 'New estimate detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NEWPLAN intent -> payment plan preview
        if (preg_match('/NEWPLAN:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newplan', 'fields' => $fields, 'text' => 'New payment plan detected. Review the preview below.', 'session_id' => $curSid));
            break;
        }
        // NOTE intent
        if (preg_match('/NOTE:\s*(\d+)\s*\|\s*(.+)/is', $text, $m)) {
            echo json_encode(array('ok' => true, 'type' => 'note',
                'member_id' => (int) $m[1], 'text_note' => trim($m[2]),
                'text' => 'Add a note to client #' . (int) $m[1] . '? Preview and confirm below.',
                'session_id' => $curSid));
            break;
        }
        // FILEADD intent -> attach a file to a client (see file_attach action)
        if (preg_match('/FILEADD:\s*(\d+)\s*\|\s*(.+?)\s*\|\s*(.*)/is', $text, $m)) {
            ai_session_title($uid, $curSid, 'Add file to client #' . (int) $m[1]);
            echo json_encode(array('ok' => true, 'type' => 'file_attach',
                'member_id' => (int) $m[1], 'file_hint' => trim($m[2]), 'notes' => trim($m[3]),
                'text' => 'To attach a file to client #' . (int) $m[1] . ', please upload it below.',
                'session_id' => $curSid));
            break;
        }
        // OPEN intent -> navigation
        if (preg_match('/OPEN:\s*(.+)/is', $text, $m)) {
            $target = trim($m[1]);
            echo json_encode(array('ok' => true, 'type' => 'open', 'target' => $target, 'text' => 'Opening ' . htmlspecialchars($target) . '...', 'session_id' => $curSid));
            break;
        }
        // SELECT intent -> run secure read
        $sql = ai_extract_select($text);
        if ($sql !== '') {
            if (ai_get('ai_allow_read', '1') !== '1') {
                echo json_encode(array('ok' => false, 'error' => 'Reading data is disabled in AI settings.')); break;
            }
            $result = ai_run_select($sql);
            if (isset($result['error']) && ai_mode() !== 'offline') {
                /* Self-heal: one corrective pass so the model fixes its own SQL
                   against the real schema instead of the user seeing a raw error. */
                $fix = ai_backend_call(
                    array(array('text' =>
                        "Your previous SQL failed.\nSQL:\n" . $sql .
                        "\n\nError: " . $result['error'] .
                        "\n\nFix it. Reply with ONLY the corrected MySQL SELECT as plain text starting with SELECT and ending with a semicolon (no code fences, no backticks, no comments). Use ONLY the real CRM tables and their real columns: members (member_id, name, mname, lname, bus_name, phone, cell_ph, prm_email, sec_email, address, city, state, zip, type, status), billing_invoice (invoice_id, mem_id, total_amount, paid_amount, balance, discount, invoice_status, due_date, invoice_date, billing_cycle, is_ach, service_provider_id), billing_invoice_items (inv_id, item_description, unit_price, quantity, total_price, mem_id), billing_invoice_instant (invoice_id, mem_id, name, business_name, balance, invoice_status, invoice_date, due_date), notestab (note_id, member_id, notes_detail), projects (order_id, mem_id, prod_id, project_title, project_status, start_date, assign_to), quotations (quotation_id, member_id, name, sub_total, grand_total), users (user_id, user_login, user_name, user_email, user_desig, user_type, user_ban_status). The client id column is mem_id — there is no client_id. There is no username column in users — use user_login for the login name and user_name for the full name.")),
                    ai_chat_prompt($transcript)
                );
                if (!isset($fix['error'])) {
                    $sql2 = ai_extract_select($fix['text']);
                    if ($sql2 !== '') {
                        $result = ai_run_select($sql2);
                        $sql = $sql2;
                    }
                }
            }
            if (isset($result['error'])) {
                echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text . "\n\n[" . $result['error'] . ']'));
                break;
            }
            echo json_encode(array('ok' => true, 'type' => 'table', 'sql' => $sql, 'rows' => $result['rows'], 'text' => $text, 'session_id' => $curSid));
            break;
        }
        echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text, 'session_id' => $curSid));
        break;
    }

    case 'check_mode': {
        $st = ai_backend_status();
        echo json_encode(array('ok' => true, 'mode' => $st));
        break;
    }

    case 'switch_mode': {
        $want = ($_POST['mode'] ?? '') === 'offline' ? 'offline' : 'online';
        $st = ai_backend_status();
        if ($want === 'offline' && !$st['ollama_ok']) {
            echo json_encode(array('ok' => false, 'error' => 'Ollama is not reachable. Start "Ollama" from the Start menu, or choose Online mode.'));
            break;
        }
        if ($want === 'online' && !$st['gemini_ok'] && !$st['groq_ok']) {
            echo json_encode(array('ok' => false, 'error' => 'Neither Gemini nor Groq is reachable. Check your keys/internet in AI Settings.'));
            break;
        }
        $current = ai_switch_mode($want);
        echo json_encode(array('ok' => true, 'mode' => ai_backend_status(), 'current' => $current));
        break;
    }

    case 'ocr': {
        if (ai_get('ai_allow_ocr', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Image OCR is disabled.')); break; }
        $b64 = $_POST['image'] ?? '';
        $mime = $_POST['mime'] ?? 'image/png';
        $target = isset($_POST['target']) && $_POST['target'] !== '' ? $_POST['target'] : 'auto';
        if ($b64 === '') { echo json_encode(array('ok' => false, 'error' => 'No image received.')); break; }
        $prompt = ai_ocr_build_prompt($target, 'image/document');
        $r = gemini_call(array(array('text' => $prompt, 'image_b64' => $b64, 'mime' => $mime)));
        if (isset($r['error'])) { echo json_encode(array('ok' => false, 'error' => $r['error'])); break; }
        $text = trim($r['text']);
        $parsed = ai_ocr_parse($text, $target);
        echo json_encode(array_merge(array('ok' => true, 'type' => 'ocr'), $parsed, array('raw' => $text)));
        break;
    }

    case 'preview_client': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled in AI settings.')); break; }
        $f = ai_post_fields();
        $preview = array();
        $preview['name'] = trim($f['name'] ?? '');
        $preview['lname'] = trim($f['lname'] ?? '');
        $preview['bus_name'] = trim($f['bus_name'] ?? '');
        $preview['phone'] = trim($f['phone'] ?? '');
        $preview['cell_ph'] = trim($f['cell_ph'] ?? '');
        $preview['prm_email'] = trim($f['prm_email'] ?? '');
        $preview['address'] = trim($f['address'] ?? '');
        $preview['city'] = trim($f['city'] ?? '');
        $preview['state'] = trim($f['state'] ?? '');
        $preview['zip'] = trim($f['zip'] ?? '');
        $preview['ss'] = trim($f['ss'] ?? '');
        $preview['ein'] = trim($f['ein'] ?? '');
        $preview['type'] = !empty($f['type']) ? (int) $f['type'] : 1;
        if ($preview['name'] === '') { echo json_encode(array('ok' => false, 'error' => 'A first name is required to add a client.')); break; }
        echo json_encode(array('ok' => true, 'preview' => $preview));
        break;
    }

    case 'confirm_client': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $name = mysqli_real_escape_string($con, trim($f['name'] ?? ''));
        $lname = mysqli_real_escape_string($con, trim($f['lname'] ?? ''));
        $bus_name = mysqli_real_escape_string($con, trim($f['bus_name'] ?? ''));
        $phone = mysqli_real_escape_string($con, trim($f['phone'] ?? ''));
        $cell = mysqli_real_escape_string($con, trim($f['cell_ph'] ?? ''));
        $email = mysqli_real_escape_string($con, trim($f['prm_email'] ?? ''));
        $address = mysqli_real_escape_string($con, trim($f['address'] ?? ''));
        $city = mysqli_real_escape_string($con, trim($f['city'] ?? ''));
        $state = mysqli_real_escape_string($con, trim($f['state'] ?? ''));
        $zip = mysqli_real_escape_string($con, trim($f['zip'] ?? ''));
        $ss = mysqli_real_escape_string($con, trim($f['ss'] ?? ''));
        $ein = mysqli_real_escape_string($con, trim($f['ein'] ?? ''));
        $type = !empty($f['type']) ? (int) $f['type'] : 1;
        if ($name === '') { echo json_encode(array('ok' => false, 'error' => 'A first name is required.')); break; }
        $today = date('Y-m-d');
        $dob = trim($f['dob'] ?? '');
        if ($dob === '') { $dob = $today; }
        $sql = "INSERT INTO members (
            file, name, dba, ein, ss, tf, dob, phone, cell_ph, other_ph, overseas_ph,
            prm_email, sec_email, fax, mem_pic, type, added_by, mname, lname, bus_name, member_pos,
            hzip, hstate, hcity, hstreet, city, state, address, zip,
            occupation, idcard, pmb, hapartment, datelast, dateupdated,
            duedate, closingm, incorp_date, incorpstate, user_id, business_address, client_since, specialClient, bus_start_date,
            hcounty, bcounty, category, ach_payer, payer_frequency, status
        ) VALUES (
            '', '$name', '', '$ein', '$ss', '', '$dob', '$phone', '$cell', '', '',
            '$email', '', '', '', '$type', '$uid', '', '$lname', '$bus_name', '',
            '', '', '', '', '$city', '$state', '$address', '$zip',
            '', '', '', '', '$today', '$today',
            '', '', '$today', '', '$uid', '', '$today', '0', '$today',
            '', '', '', '0', 'None', '1'
        )";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $newId = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'member', $newId, 'AI added client ' . $name);
        }
        ai_chat_append($uid, $curSid, 'assistant', "Client #$newId ($name) was added to the CRM.");
        echo json_encode(array('ok' => true, 'member_id' => $newId, 'message' => "Client #$newId ($name) added."));
        break;
    }

    case 'preview_note': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        $mid = (int) ($_POST['member_id'] ?? 0);
        $note = trim($_POST['text_note'] ?? '');
        if ($mid <= 0 || $note === '') { echo json_encode(array('ok' => false, 'error' => 'A client id and note text are required.')); break; }
        echo json_encode(array('ok' => true, 'preview' => array('member_id' => $mid, 'note' => $note)));
        break;
    }

    case 'confirm_note': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $mid = (int) ($_POST['member_id'] ?? 0);
        $note = mysqli_real_escape_string($con, trim($_POST['text_note'] ?? ''));
        if ($mid <= 0 || $note === '') { echo json_encode(array('ok' => false, 'error' => 'Invalid note.')); break; }
        if (!mysqli_query($con, "INSERT INTO notestab (member_id, notes_detail, user_id) VALUES ('$mid','$note','$uid')")) {
            echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break;
        }
        $logNote = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'note', $mid, 'AI added note to client #' . $mid);
        }
        ai_chat_append($uid, $curSid, 'assistant', "A note was added to client #$mid.");
        echo json_encode(array('ok' => true, 'message' => "Note added to client #$mid."));
        break;
    }

    case 'preview_invoice': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        $f = ai_post_fields();
        $preview = array();
        $preview['mem_id'] = !empty($f['mem_id']) ? (int) $f['mem_id'] : 0;
        $preview['due_date'] = trim($f['due_date'] ?? date('Y-m-d', strtotime('+30 days')));
        $preview['billing_cycle'] = trim($f['billing_cycle'] ?? '');
        $preview['notes'] = trim($f['notes'] ?? '');
        $items = isset($f['items']) && is_array($f['items']) ? $f['items'] : array();
        $cleanItems = array();
        $total = 0;
        foreach ($items as $it) {
            $desc = trim($it['item_description'] ?? '');
            if ($desc === '') continue;
            $price = (float) ($it['unit_price'] ?? 0);
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $cleanItems[] = array('item_description' => $desc, 'unit_price' => number_format($price, 2, '.', ''), 'quantity' => $qty, 'total' => number_format($price * $qty, 2, '.', ''));
            $total += $price * $qty;
        }
        $preview['items'] = $cleanItems;
        $preview['total'] = number_format($total, 2, '.', '');
        if ($preview['mem_id'] <= 0) { echo json_encode(array('ok' => false, 'error' => 'A client (mem_id) is required to create an invoice.')); break; }
        if (empty($cleanItems)) { echo json_encode(array('ok' => false, 'error' => 'At least one invoice item is required.')); break; }
        echo json_encode(array('ok' => true, 'type' => 'preview_invoice', 'preview' => $preview));
        break;
    }

    case 'confirm_invoice': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $memId = (int) ($f['mem_id'] ?? 0);
        $due = mysqli_real_escape_string($con, trim($f['due_date'] ?? date('Y-m-d')));
        $cycle = mysqli_real_escape_string($con, trim($f['billing_cycle'] ?? ''));
        $items = isset($f['items']) && is_array($f['items']) ? $f['items'] : array();
        $cleanItems = array(); $total = 0;
        foreach ($items as $it) {
            $desc = trim($it['item_description'] ?? '');
            if ($desc === '') continue;
            $price = (float) ($it['unit_price'] ?? 0);
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $cleanItems[] = array('desc' => mysqli_real_escape_string($con, $desc), 'price' => number_format($price, 2, '.', ''), 'qty' => $qty);
            $total += $price * $qty;
        }
        if ($memId <= 0 || empty($cleanItems)) { echo json_encode(array('ok' => false, 'error' => 'A client id and at least one item are required.')); break; }
        $empName = $_SESSION['fullname'] ?? '';
        $initials = '';
        foreach (explode(' ', $empName) as $w) { if ($w !== '') $initials .= strtoupper($w[0]); }
        $empTag = substr($initials, 0, 2) . '(' . $uid . ')';
        $invDate = date('m/d/Y h:i:s');
        $invDate2 = date('Y-m-d H:i:s');
        $sql = "INSERT INTO billing_invoice (mem_id, billing_id, temp_id, invoice_date, filelink, due_date, total_amount, paid_amount, discount, balance, invoice_status, payment_type, payment_details, paid_date, billing_cycle, service_provider_id, is_ach, employee_initials)
                VALUES ('$memId','0','0','$invDate','','$due','" . number_format($total, 2, '.', '') . "','0','0','" . number_format($total, 2, '.', '') . "','1','0','0','0000-00-00','$cycle','0','0','$empTag')";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $invId = mysqli_insert_id($con);
        $isql = "INSERT INTO billing_invoice_items (inv_id, temp_id, billing_id, mem_id, item_description, item_sub_description, unit_price, quantity, total_price, date_added, user_id, item_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $istmt = mysqli_prepare($con, $isql);
        $zero = 0;
        $subDesc = '';
        $types = 'iiiissdidsis';
        $insertedItems = 0;
        foreach ($cleanItems as $it) {
            $priceD = (float) $it['price'];
            $qtyI = (int) $it['qty'];
            $lineTotal = $priceD * $qtyI;
            $memIdRef = (int) $memId;
            $invIdRef = (int) $invId;
            mysqli_stmt_bind_param($istmt, $types, $invIdRef, $zero, $zero, $memIdRef, $it['desc'], $subDesc, $priceD, $qtyI, $lineTotal, $invDate2, $uid, $invDate2);
            if (@mysqli_stmt_execute($istmt)) { $insertedItems++; }
        }
        if ($istmt) @mysqli_stmt_close($istmt);
        if ($insertedItems === 0) {
            mysqli_query($con, "DELETE FROM billing_invoice WHERE invoice_id='$invId'");
            echo json_encode(array('ok' => false, 'error' => 'Invoice items failed to save; invoice rolled back.')); break;
        }
        $pdfOk = @regenerate_invoice_pdf($con, $invId);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'billing_invoice', $invId, 'SPS Assistant created invoice #' . $invId . ' for client #' . $memId);
        }
        $msg = "Invoice #$invId was created for client #$memId (total \$$total)";
        if ($pdfOk) { $msg .= " and its PDF saved."; } else { $msg .= "; PDF not generated."; }
        ai_chat_append($uid, $curSid, 'assistant', $msg);
        echo json_encode(array('ok' => true, 'message' => $msg, 'invoice_id' => $invId));
        break;
    }

    case 'preview_instant': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        $f = ai_post_fields();
        $preview = array();
        foreach (array('name','business_name','email','phone','address','city','state','zip','ss') as $k) {
            $preview[$k] = trim($f[$k] ?? '');
        }
        $preview['due_date'] = trim($f['due_date'] ?? date('Y-m-d', strtotime('+30 days')));
        $items = isset($f['items']) && is_array($f['items']) ? $f['items'] : array();
        $cleanItems = array(); $total = 0;
        foreach ($items as $it) {
            $desc = trim($it['item_description'] ?? '');
            if ($desc === '') continue;
            $price = (float) ($it['unit_price'] ?? 0);
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $cleanItems[] = array('item_description' => $desc, 'unit_price' => number_format($price, 2, '.', ''), 'quantity' => $qty, 'total' => number_format($price * $qty, 2, '.', ''));
            $total += $price * $qty;
        }
        $preview['items'] = $cleanItems;
        $preview['total'] = number_format($total, 2, '.', '');
        if (empty($cleanItems)) { echo json_encode(array('ok' => false, 'error' => 'At least one item is required.')); break; }
        echo json_encode(array('ok' => true, 'type' => 'preview_instant', 'preview' => $preview));
        break;
    }

    case 'confirm_instant': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $vals = array();
        $total = 0; $items = isset($f['items']) && is_array($f['items']) ? $f['items'] : array();
        $cleanItems = array();
        foreach ($items as $it) {
            $desc = trim($it['item_description'] ?? '');
            if ($desc === '') continue;
            $price = (float) ($it['unit_price'] ?? 0);
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $cleanItems[] = array('desc' => mysqli_real_escape_string($con, $desc), 'price' => number_format($price, 2, '.', ''), 'qty' => $qty);
            $total += $price * $qty;
        }
        if (empty($cleanItems)) { echo json_encode(array('ok' => false, 'error' => 'At least one item is required.')); break; }
        $due = mysqli_real_escape_string($con, trim($f['due_date'] ?? date('Y-m-d')));
        $empName = $_SESSION['fullname'] ?? ''; $initials = '';
        foreach (explode(' ', $empName) as $w) { if ($w !== '') $initials .= strtoupper($w[0]); }
        $empTag = substr($initials, 0, 2) . '(' . $uid . ')';
        $invDate = date('m/d/Y h:i:s');
        $invDate2 = date('Y-m-d H:i:s');
        foreach (array('name','business_name','email','phone','address','city','state','zip','ssn') as $k) {
            $vals[$k] = mysqli_real_escape_string($con, trim($f[$k] ?? ''));
        }
        if (empty($vals['ssn']) && !empty($f['ss'])) { $vals['ssn'] = mysqli_real_escape_string($con, trim($f['ss'])); }
        $sql = "INSERT INTO billing_invoice_instant (mem_id, name, business_name, email, phone, address, city, state, zip, ssn, billing_id, temp_id, filelink, invoice_date, due_date, total_amount, paid_amount, discount, balance, invoice_status, payment_type, payment_details, paid_date, billing_cycle, is_ach, employee_initials)
                VALUES ('0','" . $vals['name'] . "','" . $vals['business_name'] . "','" . $vals['email'] . "','" . $vals['phone'] . "','" . $vals['address'] . "','" . $vals['city'] . "','" . $vals['state'] . "','" . $vals['zip'] . "','" . $vals['ssn'] . "','0','0','','$invDate','$due','" . number_format($total, 2, '.', '') . "','0','0','" . number_format($total, 2, '.', '') . "','1','0','0','0000-00-00','0','0','$empTag')";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $invId = mysqli_insert_id($con);
        $isql = "INSERT INTO billing_invoice_items (inv_id, temp_id, billing_id, mem_id, item_description, item_sub_description, unit_price, quantity, total_price, date_added, user_id, item_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $istmt = mysqli_prepare($con, $isql);
        $zero = 0;
        $subDesc = '';
        $types = 'iiiissdidsis';
        $insertedItems = 0;
        foreach ($cleanItems as $it) {
            $priceD = (float) $it['price'];
            $qtyI = (int) $it['qty'];
            $lineTotal = $priceD * $qtyI;
            $memIdRef = 0;
            $invIdRef = (int) $invId;
            mysqli_stmt_bind_param($istmt, $types, $invIdRef, $zero, $zero, $memIdRef, $it['desc'], $subDesc, $priceD, $qtyI, $lineTotal, $invDate2, $uid, $invDate2);
            if (@mysqli_stmt_execute($istmt)) { $insertedItems++; }
        }
        if ($istmt) @mysqli_stmt_close($istmt);
        if ($insertedItems === 0) {
            mysqli_query($con, "DELETE FROM billing_invoice_instant WHERE invoice_id = $invId");
            echo json_encode(array('ok' => false, 'error' => 'Instant invoice items failed to save; invoice rolled back.')); break;
        }
        $pdfOk = @regenerate_instant_invoice_pdf($con, $invId);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'billing_invoice_instant', $invId, 'SPS Assistant created instant invoice #' . $invId . ' for ' . ($vals['business_name'] ?: $vals['name']));
        }
        $msg = "Instant invoice #$invId was created (total \$$total)";
        if ($pdfOk) { $msg .= " and its PDF saved."; } else { $msg .= "; PDF not generated."; }
        ai_chat_append($uid, $curSid, 'assistant', $msg);
        echo json_encode(array('ok' => true, 'message' => $msg, 'invoice_id' => $invId));
        break;
    }

    case 'preview_project': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        $f = ai_post_fields();
        $preview = array();
        $preview['mem_id'] = !empty($f['mem_id']) ? (int) $f['mem_id'] : 0;
        $preview['prod_id'] = !empty($f['prod_id']) ? (int) $f['prod_id'] : 0;
        $preview['project_title'] = trim($f['project_title'] ?? '');
        $preview['project_description'] = trim($f['project_description'] ?? '');
        $preview['project_status'] = trim($f['project_status'] ?? 'open');
        $preview['project_type'] = trim($f['project_type'] ?? '');
        $preview['start_date'] = trim($f['start_date'] ?? date('Y-m-d'));
        if ($preview['mem_id'] <= 0) { echo json_encode(array('ok' => false, 'error' => 'A client (mem_id) is required for a project.')); break; }
        if ($preview['project_title'] === '') { echo json_encode(array('ok' => false, 'error' => 'A project title is required.')); break; }
        echo json_encode(array('ok' => true, 'type' => 'preview_project', 'preview' => $preview));
        break;
    }

    case 'confirm_project': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $memId = (int) ($f['mem_id'] ?? 0);
        $prodId = (int) ($f['prod_id'] ?? 0);
        $title = mysqli_real_escape_string($con, trim($f['project_title'] ?? ''));
        $descrip = mysqli_real_escape_string($con, trim($f['project_description'] ?? ''));
        $pStatus = mysqli_real_escape_string($con, trim($f['project_status'] ?? 'open'));
        $pType = mysqli_real_escape_string($con, trim($f['project_type'] ?? ''));
        $startDate = mysqli_real_escape_string($con, trim($f['start_date'] ?? date('Y-m-d')));
        if ($memId <= 0 || $title === '') { echo json_encode(array('ok' => false, 'error' => 'Client and project title are required.')); break; }
        $sql = "INSERT INTO projects (mem_id, prod_id, project_title, project_description, project_status, project_type, start_date, date_added, user_id, assign_to)
                VALUES ('$memId','$prodId','$title','$descrip','$pStatus','$pType','$startDate',NOW(),'$uid','$uid')";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $pid = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'projects', $pid, 'SPS Assistant created project "' . $title . '" for client #' . $memId);
        }
        ai_chat_append($uid, $curSid, 'assistant', "Project \"$title\" was created for client #$memId.");
        echo json_encode(array('ok' => true, 'message' => "Project created for client #$memId.", 'project_id' => $pid));
        break;
    }

    case 'preview_estimate': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        $f = ai_post_fields();
        $preview = array();
        foreach (array('name','phone','email','address','services') as $k) { $preview[$k] = trim($f[$k] ?? ''); }
        $preview['member_id'] = !empty($f['member_id']) ? (int) $f['member_id'] : 0;
        $preview['sub_total'] = trim($f['sub_total'] ?? '0');
        $preview['discount'] = trim($f['discount'] ?? '0');
        $preview['grand_total'] = trim($f['grand_total'] ?? ($preview['sub_total'] - $preview['discount']));
        echo json_encode(array('ok' => true, 'type' => 'preview_estimate', 'preview' => $preview));
        break;
    }

    case 'confirm_estimate': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $memberId = (int) ($f['member_id'] ?? 0);
        $name = mysqli_real_escape_string($con, trim($f['name'] ?? ''));
        $phone = mysqli_real_escape_string($con, trim($f['phone'] ?? ''));
        $email = mysqli_real_escape_string($con, trim($f['email'] ?? ''));
        $address = mysqli_real_escape_string($con, trim($f['address'] ?? ''));
        $services = mysqli_real_escape_string($con, trim($f['services'] ?? ''));
        $subT = mysqli_real_escape_string($con, trim($f['sub_total'] ?? '0'));
        $disc = mysqli_real_escape_string($con, trim($f['discount'] ?? '0'));
        $grand = mysqli_real_escape_string($con, trim($f['grand_total'] ?? ($subT - $disc)));
        if ($name === '') { echo json_encode(array('ok' => false, 'error' => 'A name is required for the estimate.')); break; }
        $sql = "INSERT INTO quotations (member_id, name, phone, email, address, services, sub_total, discount, grand_total, quotation_date, status, created_by)
                VALUES ('$memberId','$name','$phone','$email','$address','$services','$subT','$disc','$grand',NOW(),'1','$uid')";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $qid = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'quotations', $qid, 'SPS Assistant created estimate for ' . $name);
        }
        ai_chat_append($uid, $curSid, 'assistant', "Estimate #$qid was created for $name.");
        echo json_encode(array('ok' => true, 'message' => "Estimate created.", 'quotation_id' => $qid));
        break;
    }

    /* ---- Payment plan: preview ---- */
    case 'preview_plan': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        $f = ai_post_fields();
        $f['client_id'] = (int) ($f['client_id'] ?? 0);
        $f['plan_name'] = trim($f['plan_name'] ?? '');
        if ($f['client_id'] <= 0) { echo json_encode(array('ok' => false, 'error' => 'A client (client_id) is required for a payment plan.')); break; }
        if ($f['plan_name'] === '') { echo json_encode(array('ok' => false, 'error' => 'A plan name is required.')); break; }
        $items = array();
        foreach ((array) ($f['items'] ?? array()) as $it) {
            $nm = trim($it['service_name'] ?? '');
            $amt = (float) ($it['amount'] ?? 0);
            if ($nm === '' && $amt == 0) { continue; }
            $items[] = array('service_name' => $nm, 'amount' => $amt, 'frequency' => trim($it['frequency'] ?? 'Yearly'));
        }
        if (!$items) { echo json_encode(array('ok' => false, 'error' => 'Add at least one plan item.')); break; }
        $planType = trim($f['plan_type'] ?? 'Standard');
        $cycle = trim($f['billing_cycle'] ?? 'Monthly');
        $startDate = trim($f['start_date'] ?? date('Y-m-d'));
        $endDate = trim($f['end_date'] ?? '');
        if ($endDate === '') { $endDate = date('Y-m-d', strtotime($startDate . ' + 12 months')); }
        $totalAmt = round((float) ($f['total_amount'] ?? 0), 2);

        /* Frequency-weighted installment estimate (mirrors sps generate_installment_bills). */
        $installmentCount = 0;
        $installmentAmount = 0.0;
        if (($planType === 'Installment' || $planType === 'Custom') && $startDate !== '' && $endDate !== '') {
            $d1 = new DateTime($startDate); $d2 = new DateTime($endDate);
            $diff = $d1->diff($d2);
            $months = max(1, ($diff->y * 12) + $diff->m);
            $annual = 0.0;
            foreach ($items as $it) {
                $fr = $it['frequency'];
                $annual += ($fr === 'Monthly') ? $it['amount'] * $months
                    : (($fr === 'Quarterly') ? $it['amount'] * max(1, round($months / 3)) : $it['amount'] * max(1, round($months / 12)));
            }
            if ($annual > 0) {
                if ($cycle === 'Monthly') { $installmentCount = $months; }
                elseif ($cycle === 'Quarterly') { $installmentCount = max(1, round($months / 3)); }
                elseif ($cycle === 'Annually') { $installmentCount = max(1, round($months / 12)); }
                else { $installmentCount = 1; }
                $installmentAmount = round($annual / $installmentCount, 2);
            }
        }
        $preview = array(
            'client_id' => $f['client_id'], 'plan_name' => $f['plan_name'], 'plan_type' => $planType,
            'billing_cycle' => $cycle, 'payment_method' => trim($f['payment_method'] ?? ''),
            'start_date' => $startDate, 'end_date' => $endDate, 'total_amount' => $totalAmt,
            'items' => $items,
            'installment_count' => $installmentCount, 'installment_amount' => $installmentAmount,
        );
        echo json_encode(array('ok' => true, 'type' => 'preview_plan', 'preview' => $preview));
        break;
    }

    /* ---- Payment plan: confirm insert ---- */
    case 'confirm_plan': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = ai_post_fields();
        $clientId = (int) ($f['client_id'] ?? 0);
        $planName = mysqli_real_escape_string($con, trim($f['plan_name'] ?? ''));
        $planType = mysqli_real_escape_string($con, trim($f['plan_type'] ?? 'Standard'));
        $cycle = mysqli_real_escape_string($con, trim($f['billing_cycle'] ?? 'Monthly'));
        $method = mysqli_real_escape_string($con, trim($f['payment_method'] ?? ''));
        $other = ($method === 'Others') ? mysqli_real_escape_string($con, trim($f['payment_method_other'] ?? '')) : null;
        $startDate = trim($f['start_date'] ?? date('Y-m-d'));
        $endDate = trim($f['end_date'] ?? '');
        if ($endDate === '') { $endDate = date('Y-m-d', strtotime($startDate . ' + 12 months')); }
        $totalAmt = round((float) ($f['total_amount'] ?? 0), 2);
        $items = array();
        foreach ((array) ($f['items'] ?? array()) as $it) {
            $nm = trim($it['service_name'] ?? '');
            $amt = (float) ($it['amount'] ?? 0);
            if ($nm === '' && $amt == 0) { continue; }
            $items[] = array('service_name' => $nm, 'amount' => $amt, 'frequency' => trim($it['frequency'] ?? 'Yearly'));
        }
        if ($clientId <= 0 || $planName === '') { echo json_encode(array('ok' => false, 'error' => 'Client and plan name are required.')); break; }
        if (!$items) { echo json_encode(array('ok' => false, 'error' => 'Add at least one plan item.')); break; }

        /* Installment figures (recomputed here so amounts match the schedule). */
        $installmentCount = 0; $installmentAmount = 0.0;
        if (($planType === 'Installment' || $planType === 'Custom')) {
            $d1 = new DateTime($startDate); $d2 = new DateTime($endDate);
            $diff = $d1->diff($d2);
            $months = max(1, ($diff->y * 12) + $diff->m);
            $annual = 0.0;
            foreach ($items as $it) {
                $fr = $it['frequency'];
                $annual += ($fr === 'Monthly') ? $it['amount'] * $months
                    : (($fr === 'Quarterly') ? $it['amount'] * max(1, round($months / 3)) : $it['amount'] * max(1, round($months / 12)));
            }
            if ($annual > 0) {
                if ($cycle === 'Monthly') { $installmentCount = $months; }
                elseif ($cycle === 'Quarterly') { $installmentCount = max(1, round($months / 3)); }
                elseif ($cycle === 'Annually') { $installmentCount = max(1, round($months / 12)); }
                else { $installmentCount = 1; }
                $installmentAmount = round($annual / $installmentCount, 2);
            }
        }

        $now = date('Y-m-d H:i:s');
        $stmt = mysqli_prepare($con,
            "INSERT INTO payment_plans
               (client_id, plan_name, plan_type, billing_cycle, payment_method, payment_method_other,
                effective_date, start_date, end_date, status, installment_frequency, next_installment_date,
                installment_amount, total_amount, notes, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st = 'Active';
        $ifo = $cycle;
        $nid = null;
        $notes = '';
        $ined = null;
        mysqli_stmt_bind_param($stmt, "isssssssssssddsis",
            $clientId, $planName, $planType, $cycle, $method, $other, $ined, $startDate, $endDate,
            $st, $ifo, $nid, $installmentAmount, $totalAmt, $notes, $uid, $now);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break;
        }
        $planId = mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        $istmt = mysqli_prepare($con,
            "INSERT INTO payment_plan_items
               (plan_id, service_id, service_name, amount, frequency, details, sort_order)
             VALUES (?,?,?,?,?,?,?)");
        $sid = null; $det = '';
        foreach ($items as $idx => $it) {
            mysqli_stmt_bind_param($istmt, "iisdssi", $planId, $sid, $it['service_name'], $it['amount'], $it['frequency'], $det, $idx);
            mysqli_stmt_execute($istmt);
        }
        mysqli_stmt_close($istmt);

        if ($installmentCount > 0) {
            $imStmt = mysqli_prepare($con,
                "INSERT INTO payment_plan_installments (plan_id, installment_number, amount, due_date, status, created_at)
                 VALUES (?,?,?,?,?,?)");
            $imStatus = 'Pending';
            $dStart = new DateTime($startDate);
            for ($i = 0; $i < $installmentCount; $i++) {
                $due = clone $dStart;
                if ($cycle === 'Monthly') { $due->modify("+$i months"); }
                elseif ($cycle === 'Quarterly') { $due->modify("+" . ($i * 3) . " months"); }
                elseif ($cycle === 'Annually') { $due->modify("+$i years"); }
                $num = $i + 1;
                $dueDate = $due->format('Y-m-d');
                mysqli_stmt_bind_param($imStmt, "iidsss", $planId, $num, $installmentAmount, $dueDate, $imStatus, $now);
                mysqli_stmt_execute($imStmt);
            }
            mysqli_stmt_close($imStmt);
        }

        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'payment_plans', $planId, 'SPS Assistant created payment plan "' . $planName . '" for client #' . $clientId);
        }
        ai_chat_append($uid, $curSid, 'assistant', "Payment plan \"$planName\" was created for client #$clientId.");
        echo json_encode(array('ok' => true, 'message' => "Payment plan created for client #$clientId.", 'plan_id' => $planId, 'installments' => $installmentCount));
        break;
    }

    case 'export_csv': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $sql = $_POST['sql'] ?? '';
        $result = ai_run_select($sql);
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ai_export_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) { fputcsv($out, $row); }
        fclose($out);
        exit;
    }

    case 'export_pdf': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $title = htmlspecialchars($_POST['title'] ?? 'AI Export');
        $html = '<h2>' . $title . '</h2>';
        $sql = $_POST['sql'] ?? '';
        $result = ai_run_select($sql);
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:sans-serif;font-size:10px;width:100%"><thead><tr>';
        foreach (array_keys($rows[0]) as $h) { $html .= '<th style="background:#eee">' . htmlspecialchars($h) . '</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) { $html .= '<tr>'; foreach ($row as $v) { $html .= '<td>' . htmlspecialchars((string) $v) . '</td>'; } $html .= '</tr>'; }
        $html .= '</tbody></table>';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ai_export_' . date('Ymd_His') . '.pdf"');
        include_once 'dompdf/autoload.inc.php';
        $dompdf = new Dompdf\Dompdf(array('isRemoteEnabled' => true));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        echo $dompdf->output();
        exit;
    }

    /* ---------------- Excel (.xlsx) export ---------------------------------- */
    case 'export_xlsx': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $result = ai_run_select($_POST['sql'] ?? '');
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        if (!class_exists('ZipArchive')) { echo json_encode(array('ok' => false, 'error' => 'Excel export needs the Zip extension.')); break; }
        $headers = array_keys($rows[0]);
        if (!function_exists('ai_col_letter')) {
            function ai_col_letter($i)
            {
                $s = '';
                $i++;
                while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); }
                return $s;
            }
        }
        $colLetters = array();
        for ($i = 0; $i < count($headers); $i++) { $colLetters[$i] = ai_col_letter($i); }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheet .= '<row r="1">';
        foreach ($colLetters as $j => $L) { $sheet .= '<c r="' . $L . '1" t="inlineStr"><is><t>' . htmlspecialchars($headers[$j], ENT_XML1 | ENT_QUOTES) . '</t></is></c>'; }
        $sheet .= '</row>';
        $rr = 2;
        foreach ($rows as $row) {
            $sheet .= '<row r="' . $rr . '">';
            foreach ($colLetters as $j => $L) {
                $v = $row[$headers[$j]];
                if ($v === null) { $sheet .= '<c r="' . $L . $rr . '" t="inlineStr"><is><t></t></is></c>'; continue; }
                if (is_numeric($v)) {
                    $sheet .= '<c r="' . $L . $rr . '"><v>' . $v . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $L . $rr . '" t="inlineStr"><is><t>' . htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES) . '</t></is></c>';
                }
            }
            $sheet .= '</row>';
            $rr++;
        }
        $sheet .= '</sheetData></worksheet>';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $relWb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
        if (!function_exists('ai_xlsx_wb')) {
            function ai_xlsx_wb($sheetName) {
                return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    . '<sheets><sheet name="' . htmlspecialchars($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
            }
        }
        $tmpX = tempnam(sys_get_temp_dir(), 'spsx');
        $zip = new ZipArchive();
        if ($zip->open($tmpX, ZipArchive::CREATE) !== true) { echo json_encode(array('ok' => false, 'error' => 'Could not create the Excel zip.')); break; }
        $zip->addFromString('[Content_Types].xml', $xml);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('xl/workbook.xml', ai_xlsx_wb('SPS Export'));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relWb);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ai_export_' . date('Ymd_His') . '.xlsx"');
        readfile($tmpX);
        @unlink($tmpX);
        exit;
    }

    /* ---------------- Word (.docx) export ----------------------------------- */
    case 'export_docx': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $title = trim($_POST['title'] ?? 'SPS Assistant Report');
        $result = ai_run_select($_POST['sql'] ?? '');
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        if (!class_exists('ZipArchive')) { echo json_encode(array('ok' => false, 'error' => 'Word export needs the Zip extension.')); break; }
        $headers = array_keys($rows[0]);
        $body = '<w:p><w:r><w:pPr><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t>' . htmlspecialchars($title) . '</w:t></w:r></w:p>';
        $body .= '<w:tbl><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4"/><w:left w:val="single" w:sz="4"/><w:bottom w:val="single" w:sz="4"/><w:right w:val="single" w:sz="4"/><w:insideH w:val="single" w:sz="4"/><w:insideV w:val="single" w:sz="4"/></w:tblBorders></w:tblPr>';
        $body .= '<w:tr><w:tc><w:tcPr><w:shd w:val="clear" w:fill="EEEEEE"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars($title) . '</w:t></w:r></w:p></w:tc></w:tr>';
        $body .= '<w:tr>';
        foreach ($headers as $h) { $body .= '<w:tc><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>' . htmlspecialchars((string) $h) . '</w:t></w:r></w:p></w:tc>'; }
        $body .= '</w:tr>';
        foreach ($rows as $row) {
            $body .= '<w:tr>';
            foreach ($headers as $h) { $body .= '<w:tc><w:p><w:r><w:t>' . htmlspecialchars((string) ($row[$h] ?? '')) . '</w:t></w:r></w:p></w:tc>'; }
            $body .= '</w:tr>';
        }
        $body .= '</w:tbl>';
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr></w:body></w:document>';
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>';
        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="word/styles.xml"/></Relationships>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:sz w:val="20"/></w:rPr></w:rPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style></w:styles>';
        $tmpD = tempnam(sys_get_temp_dir(), 'spsw');
        $zip = new ZipArchive();
        if ($zip->open($tmpD, ZipArchive::CREATE) !== true) { echo json_encode(array('ok' => false, 'error' => 'Could not create the Word zip.')); break; }
        $zip->addFromString('[Content_Types].xml', $xml);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('word/document.xml', $doc);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="ai_report_' . date('Ymd_His') . '.docx"');
        readfile($tmpD);
        @unlink($tmpD);
        exit;
    }

    /* ---------------- Sessions -------------------------------------------- */
    case 'session_list': {
        $sessions = ai_session_list($uid, 100);
        foreach ($sessions as &$s) {
            $q = mysqli_query($con, "SELECT COUNT(*) c FROM ai_chat_history WHERE session_id=" . (int) $s['session_id']);
            $s['messages'] = ($q) ? (int) $q->fetch_assoc()['c'] : 0;
        }
        echo json_encode(array('ok' => true, 'sessions' => $sessions));
        break;
    }

    case 'session_new': {
        $sid = ai_session_create($uid, 'New Session');
        ai_session_title($uid, $sid, 'Session ' . date('M j, g:i A'));
        echo json_encode(array('ok' => true, 'session_id' => $sid, 'message' => 'New session started. The previous session was saved/ended.'));
        break;
    }

    case 'session_open': {
        $sid = (int) ($_POST['session_id'] ?? 0);
        $s = ai_session_get($uid, $sid);
        if (!$s) { echo json_encode(array('ok' => false, 'error' => 'Session not found.')); break; }
        /* Opening an old session: end any active one, then re-activate this one. */
        ai_chat_append($uid, $sid, 'system', '[User reopened saved session ' . date('M j, g:i A') . ']');
        ai_session_end($uid, 0); // end any currently-active session
        mysqli_query($con, "UPDATE ai_sessions SET status='active', ended_at=NULL WHERE session_id=$sid AND user_id=$uid");
        $hist = ai_chat_history($uid, $sid, 200);
        echo json_encode(array('ok' => true, 'session' => $s, 'history' => $hist));
        break;
    }

    case 'session_end': {
        $sid = (int) ($_POST['session_id'] ?? 0);
        $sid = ($sid > 0) ? $sid : $curSid;
        ai_session_end($uid, $sid);
        echo json_encode(array('ok' => true, 'message' => 'Session #' . $sid . ' ended and saved.', 'session_id' => $sid));
        break;
    }

    /* ---------------- File upload (parse + feed to Gemini) ---------------- */
    case 'upload_file': {
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(array('ok' => false, 'error' => 'No file uploaded, or an upload error occurred.'));
            break;
        }
        $tmp = $_FILES['file']['tmp_name'];
        $orig = basename($_FILES['file']['name']);
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $mime = $_FILES['file']['type'] ?? '';
        $mode = $_POST['mode'] ?? 'talk';
        ai_chat_append($uid, $curSid, 'user', '[Uploaded file: ' . $orig . ']');

        /* Images: Gemini reads the image directly. In INSERT mode we classify
           the document and build an editable insert preview (per-module OCR). */
        if (in_array($ext, array('png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'))) {
            $b64 = base64_encode(@file_get_contents($tmp));
            if ($mode === 'insert') {
                if (ai_get('ai_allow_ocr', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Image OCR is disabled.')); break; }
                $r = gemini_call(array(array('text' => ai_ocr_build_prompt('auto', 'image/document'), 'image_b64' => $b64, 'mime' => $mime ?: 'image/png')));
                if (isset($r['error'])) { echo json_encode(array('ok' => false, 'error' => $r['error'])); break; }
                $text = trim($r['text']);
                $parsed = ai_ocr_parse($text, 'auto');
                ai_chat_append($uid, $curSid, 'assistant', trim($parsed['summary'] ?: $text));
                echo json_encode(array_merge(array('ok' => true, 'type' => 'ocr', 'file' => $orig, 'session_id' => $curSid), $parsed, array('raw' => $text)));
                break;
            }
            $prompt = "This is an uploaded file ($orig). Extract the relevant fields and summarize its contents clearly.";
            $r = gemini_call(array(array('text' => $prompt, 'image_b64' => $b64, 'mime' => $mime ?: 'image/png')));
            if (isset($r['error'])) { echo json_encode(array('ok' => false, 'error' => $r['error'])); break; }
            ai_chat_append($uid, $curSid, 'assistant', trim($r['text']));
            echo json_encode(array('ok' => true, 'type' => 'text', 'text' => trim($r['text']), 'file' => $orig, 'session_id' => $curSid));
            break;
        }

        /* Text-extractable files: parse locally, then ask the backend to act. */
        $parsed = ai_extract_file_text($tmp, $orig);
        if (isset($parsed['error'])) { echo json_encode(array('ok' => false, 'error' => $parsed['error'])); break; }

        /* Persist the file to uploads/ai for reference. */
        $saveDir = __DIR__ . '/uploads/ai';
        if (!is_dir($saveDir)) { @mkdir($saveDir, 0775, true); }
        $saveName = date('Ymd_His') . '_' . $orig;
        @move_uploaded_file($tmp, $saveDir . '/' . $saveName);

        $content = $parsed['text'];

        /* In INSERT mode: turn the file contents into an editable insert preview. */
        if ($mode === 'insert') {
            if (ai_get('ai_allow_ocr', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Document reading is disabled.')); break; }
            $instruction = "Uploaded file \"$orig\". File contents below:\n"
                . "---- FILE CONTENT START ----\n" . $content . "\n---- FILE CONTENT END ----\n\n"
                . ai_ocr_build_prompt('auto', 'uploaded document text');
            $r = ai_backend_call(array(array('text' => $instruction)), '');
            if (isset($r['error'])) {
                echo json_encode(array('ok' => false, 'error' => $r['message'] ?? $r['error'])); break;
            }
            $text = trim($r['text']);
            $ocrp = ai_ocr_parse($text, 'auto');
            ai_chat_append($uid, $curSid, 'assistant', trim($ocrp['summary'] ?: $text));
            echo json_encode(array_merge(array('ok' => true, 'type' => 'ocr', 'file' => $orig, 'session_id' => $curSid), $ocrp, array('raw' => $text)));
            break;
        }

        $transcript = ai_build_transcript($uid, $curSid);
        $instruction = "You just received an uploaded file named \"$orig\". File text/contents below (markers may indicate extraction limits):\n"
            . "---- FILE CONTENT START ----\n" . $content . "\n---- FILE CONTENT END ----\n\n"
            . "Understand the file. If it contains client/contact/invoice data, either answer about it or propose inserting a client (NEWCLIENT:) or note (NOTE:) as appropriate, and mention the file was received.";
        $r = ai_backend_call(array(array('text' => $instruction)), ai_chat_prompt($transcript));
        if (isset($r['error'])) { echo json_encode(array('ok' => false, 'error' => $r['message'] ?? $r['error'])); break; }
        ai_chat_append($uid, $curSid, 'assistant', trim($r['text']));
        echo json_encode(array('ok' => true, 'type' => 'text', 'text' => trim($r['text']), 'file' => $orig, 'session_id' => $curSid));
        break;
    }

    /* ---------------- Attach an already-uploaded file to a client ---------- */
    case 'file_attach': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        $mid = (int) ($_POST['member_id'] ?? 0);
        $fileName = trim($_POST['filename'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($mid <= 0 || $fileName === '') { echo json_encode(array('ok' => false, 'error' => 'A client id and filename are required.')); break; }
        $savedPath = '';
        if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['file']['tmp_name'];
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($fileName));
            $saveDir = __DIR__ . '/uploads/ai';
            if (!is_dir($saveDir)) { @mkdir($saveDir, 0775, true); }
            $savedPath = 'uploads/ai/' . date('Ymd_His') . '_' . $safeName;
            if (!@move_uploaded_file($tmp, __DIR__ . '/' . $savedPath)) { $savedPath = ''; }
        }
        $storeRef = $savedPath !== '' ? $savedPath : basename($fileName);
        $storeRef = mysqli_real_escape_string($con, $storeRef);
        if (!mysqli_query($con, "INSERT INTO documents (client_id, uploaded_by, green_card, citizenship_certificate, nadara_id_card, ssn_certificate, marriage_certificate, divorce_certificate, password) VALUES ('$mid','$uid','','','','','','','$storeRef')")) {
            echo json_encode(array('ok' => false, 'error' => 'Could not attach file: ' . $con->error)); break;
        }
        $did = mysqli_insert_id($con);
        if ($notes !== '') {
            mysqli_query($con, "INSERT INTO notestab (member_id, notes_detail, user_id) VALUES ('$mid','[AI file: $fileName] $notes','$uid')");
        }
        echo json_encode(array('ok' => true, 'message' => "File '$fileName' attached to client #$mid.", 'document_id' => $did));
        break;
    }

    default:
        echo json_encode(array('ok' => false, 'error' => 'Unknown action.'));
        break;
}
