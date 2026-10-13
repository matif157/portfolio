<?php
// --- 1.  ABSOLUTELY NO OUTPUT BEFORE JSON -----------------------------
ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
error_reporting(0);
ini_set('display_errors', 0);

// --- 2.  LOAD DEPENDENCIES -------------------------------------------
require_once 'includes/db.php';
require_once 'includes/auth.php';

// --- 3.  FORCE CLEAN JSON HEADER -------------------------------------
ob_end_clean();                         // discard anything that leaked
header('Content-Type: application/json');
header('Cache-Control: no-cache');      // optional but safe

// Function to highlight search term in text
function highlightSearchTerm($text, $searchTerm)
{
    if (empty($text) || empty($searchTerm)) {
        return $text;
    }
 
    // Escape special regex characters
    $escapedTerm = preg_quote($searchTerm, '/');

    // Use regex to find and highlight all occurrences (case-insensitive)
    $pattern = "/($escapedTerm)/i";
    $replacement = '<span class="highlight">$1</span>';

    return preg_replace($pattern, $replacement, $text);
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(array('error' => 'Unauthorized access'));
    exit;
}

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $searchTerm = mysqli_real_escape_string($con, $_GET['q']);
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];

    // Initialize results array
    $results = array();

    // Search in members/clients
    $clientQuery = "SELECT member_id, name, mname, lname, bus_name, phone, cell_ph, prm_email, ein, pmb ,status
    FROM members 
    WHERE (name LIKE '%$searchTerm%' 
    OR lname LIKE '%$searchTerm%' 
    OR bus_name LIKE '%$searchTerm%' 
    OR phone LIKE '%$searchTerm%' 
    OR cell_ph LIKE '%$searchTerm%'
    OR prm_email LIKE '%$searchTerm%'
    OR ein LIKE '%$searchTerm%'
    OR pmb LIKE '%$searchTerm%')
    OR ein LIKE '%{$searchTerm}%'
    OR ss LIKE '%{$searchTerm}%'
    OR prm_email LIKE '%{$searchTerm}%'
    OR sec_email LIKE '%{$searchTerm}%'
    LIMIT 10";

    $clientResult = mysqli_query($con, $clientQuery);
    if ($clientResult) {
        $clients = array();
        while ($row = mysqli_fetch_assoc($clientResult)) {

            $contact_parts = [];
            if (!empty($row['phone']))
                $contact_parts[] = "Phone: " . highlightSearchTerm($row['phone'], $searchTerm);
            if (!empty($row['prm_email']))
                $contact_parts[] = "Email: " . highlightSearchTerm($row['prm_email'], $searchTerm);
            if (!empty($row['ein']))
                $contact_parts[] = "EIN: " . highlightSearchTerm($row['ein'], $searchTerm);
            if (!empty($row['pmb']))
                $contact_parts[] = "PMB: " . highlightSearchTerm($row['pmb'], $searchTerm);

            $status_label = ($row['status'] == 1) ?
                '<span style="color:green; font-weight:bold;">Active</span>' :
                '<span style="color:red; font-weight:bold;">Inactive</span>';

            $clientName = trim($row['name'] . ' ' . $row['lname']);

            $clients[] = array(
                'id' => $row['member_id'],
                'title' => highlightSearchTerm($clientName, $searchTerm),
                'subtitle' => 'Client ID: ' . $row['member_id'] .
                    ' | Status: ' . $status_label .
                    ' | ' . implode(' | ', $contact_parts),
                'link' => 'client_details.php?id=' . $row['member_id'] . '&status=' . $row['status'],
                'type' => 'Client',
                'status' => $row['status']
            );
        }

        if (!empty($clients)) {
            $results['Clients'] = $clients;
        }
    }

    // Search in invoices
    /* ----------  INVOICE SEARCH  ------------------------------------------ */
    $invoiceQuery = "
SELECT 
    bi.invoice_id,
    bi.mem_id,
    bi.total_amount,
    bi.due_date,
    bi.balance,
    bi.invoice_status,
    m.name,
    m.lname,
    m.bus_name,
    m.phone,
    m.prm_email
FROM billing_invoice bi
JOIN members m ON bi.mem_id = m.member_id
WHERE (
        bi.invoice_id     LIKE '%$searchTerm%'
    OR  m.name            LIKE '%$searchTerm%'
    OR  m.lname           LIKE '%$searchTerm%'
    OR  m.bus_name        LIKE '%$searchTerm%'
    OR  m.phone           LIKE '%$searchTerm%'
    OR  m.prm_email       LIKE '%$searchTerm%'
    OR  bi.invoice_id IN (
            SELECT DISTINCT inv_id
            FROM   billing_invoice_items
            WHERE  item_description     LIKE '%$searchTerm%'
               OR  item_sub_description LIKE '%$searchTerm%'
        )
)
LIMIT 10";

    $invoiceResult = mysqli_query($con, $invoiceQuery);
    if ($invoiceResult && mysqli_num_rows($invoiceResult)) {
        $invoices = [];

        while ($row = mysqli_fetch_assoc($invoiceResult)) {

            /* ---- 1.  header fields (always highlighted) -------------------- */
            $title = 'Invoice #' . highlightSearchTerm($row['invoice_id'], $searchTerm);
            $client = highlightSearchTerm(trim($row['name'] . ' ' . $row['lname']), $searchTerm);
            $business = highlightSearchTerm($row['bus_name'], $searchTerm);
            $status = ($row['balance'] <= 0) ? 'Paid' : 'Unpaid';

            /* ---- 2.  service lines (highlight each line individually) ------ */
            $itemResult = mysqli_query(
                $con,
                "SELECT item_description, item_sub_description
             FROM   billing_invoice_items
             WHERE  inv_id = {$row['invoice_id']}
             ORDER  BY inv_item_id"
            );

            $itemPieces = [];
            while ($it = mysqli_fetch_assoc($itemResult)) {
                $svc = highlightSearchTerm(trim($it['item_description']), $searchTerm);
                $des = highlightSearchTerm(trim($it['item_sub_description']), $searchTerm);

                $itemPieces[] = '• ' . $svc .
                    '<br><span style="color:green;">Description: ' . $des . '</span>';
            }
            $itemHtml = $itemPieces ? '<br>' . implode('<br>', $itemPieces) : '';

            /* ---- 3.  build the row ----------------------------------------- */
            $invoices[] = [
                'id' => $row['invoice_id'],
                'title' => $title,
                'subtitle' => 'Client: ' . $client .
                    ' | Business: ' . $business .
                    ' | Total: $' . number_format($row['total_amount'], 2) .
                    ' | Status: ' . $status .
                    $itemHtml,
                'link' => 'client_billing.php?id=' . $row['mem_id'],
                'type' => 'Invoice'
            ];
        }
        $results['Invoices'] = $invoices;
    }
    /* ----------  END INVOICE SEARCH  -------------------------------------- */

    /* ----------  INSTANT INVOICE SEARCH  ---------------------------------- */
    $instInvoiceQuery = "
SELECT
    bii.invoice_id,
    bii.mem_id,
    bii.name,
    bii.business_name,
    bii.ssn,
    bii.phone,
    bii.email,
    bii.address,
    bii.city,
    bii.state,
    bii.zip,
    bii.total_amount,
    bii.paid_amount,
    bii.balance,
    bii.due_date,
    bii.paid_date,
    bii.invoice_status,
    bii.payment_details,
    bii.employee_initials,
    bii.last_edited_by,
    bii.filelink
FROM billing_invoice_instant bii
WHERE (
        bii.invoice_id        LIKE '%$searchTerm%'
    OR  bii.name              LIKE '%$searchTerm%'
    OR  bii.business_name     LIKE '%$searchTerm%'
    OR  bii.ssn               LIKE '%$searchTerm%'
    OR  bii.phone             LIKE '%$searchTerm%'
    OR  bii.email             LIKE '%$searchTerm%'
    OR  bii.address           LIKE '%$searchTerm%'
    OR  bii.city              LIKE '%$searchTerm%'
    OR  bii.state             LIKE '%$searchTerm%'
    OR  bii.zip               LIKE '%$searchTerm%'
    OR  bii.payment_details   LIKE '%$searchTerm%'
    OR  bii.employee_initials LIKE '%$searchTerm%'
    OR  bii.last_edited_by    LIKE '%$searchTerm%'
    OR  bii.invoice_id IN (
            SELECT DISTINCT inv_id
            FROM   billing_invoice_items
            WHERE  item_description     LIKE '%$searchTerm%'
               OR  item_sub_description LIKE '%$searchTerm%'
        )
)
ORDER BY bii.invoice_id DESC
LIMIT 10";

    $instInvoiceResult = mysqli_query($con, $instInvoiceQuery);
    if ($instInvoiceResult && mysqli_num_rows($instInvoiceResult)) {
        $instantInvoices = [];

        while ($row = mysqli_fetch_assoc($instInvoiceResult)) {

            /* ---- header fields ---------------------------------------- */
            $title    = 'Instant Invoice #' . highlightSearchTerm($row['invoice_id'], $searchTerm);
            $client   = highlightSearchTerm(trim($row['name']), $searchTerm);
            $business = highlightSearchTerm($row['business_name'], $searchTerm);

            switch ($row['invoice_status']) {
                case '3': $status = 'Paid'; break;
                case '2': $status = 'Partial'; break;
                default:  $status = 'Unpaid';
            }

            /* ---- contact / personal pieces --------------------------- */
            $contactParts = [];
            if (!empty($row['phone']))
                $contactParts[] = 'Phone: ' . highlightSearchTerm($row['phone'], $searchTerm);
            if (!empty($row['email']))
                $contactParts[] = 'Email: ' . highlightSearchTerm($row['email'], $searchTerm);
            if (!empty($row['ssn']))
                $contactParts[] = 'SSN: ' . highlightSearchTerm($row['ssn'], $searchTerm);

            $addressBits = array_filter([
                $row['address'], $row['city'], $row['state'], $row['zip']
            ]);
            $addressLine = $addressBits
                ? '<br><span style="color:#555;">Address: ' .
                    highlightSearchTerm(implode(', ', $addressBits), $searchTerm) . '</span>'
                : '';

            /* ---- payment-method "other" details ---------------------- */
            $paymentLine = '';
            if (!empty($row['payment_details'])) {
                $paymentLine = '<br><span style="color:#0066cc;">Payment Details: ' .
                    highlightSearchTerm($row['payment_details'], $searchTerm) . '</span>';
            }

            /* ---- created/edited by ----------------------------------- */
            $byParts = [];
            if (!empty($row['employee_initials']))
                $byParts[] = 'Created By: ' . highlightSearchTerm($row['employee_initials'], $searchTerm);
            if (!empty($row['last_edited_by']))
                $byParts[] = 'Edited By: ' . highlightSearchTerm($row['last_edited_by'], $searchTerm);
            $byLine = $byParts ? '<br><span style="color:#777;">' . implode(' | ', $byParts) . '</span>' : '';

            /* ---- service lines (service name + description) --------- */
            $itemResult = mysqli_query(
                $con,
                "SELECT item_description, item_sub_description
                 FROM   billing_invoice_items
                 WHERE  inv_id = " . (int) $row['invoice_id'] . "
                 ORDER  BY inv_item_id"
            );

            $itemPieces = [];
            if ($itemResult) {
                while ($it = mysqli_fetch_assoc($itemResult)) {
                    $svc = highlightSearchTerm(trim($it['item_description']), $searchTerm);
                    $des = highlightSearchTerm(trim($it['item_sub_description']), $searchTerm);
                    $itemPieces[] = '• ' . $svc .
                        '<br><span style="color:green;">Description: ' . $des . '</span>';
                }
            }
            $itemHtml = $itemPieces ? '<br>' . implode('<br>', $itemPieces) : '';

            /* ---- build the row --------------------------------------- */
            $instantInvoices[] = [
                'id'       => $row['invoice_id'],
                'title'    => $title,
                'subtitle' => 'Client: ' . ($client ?: 'N/A') .
                    ' | Business: ' . ($business ?: 'N/A') .
                    ' | Total: $' . number_format($row['total_amount'], 2) .
                    ' | Status: ' . $status .
                    ($contactParts ? '<br>' . implode(' | ', $contactParts) : '') .
                    $addressLine .
                    $paymentLine .
                    $byLine .
                    $itemHtml,
                'link'     => 'instant_invoice.php?id=' . $row['mem_id'] . '&invoice_id=' . $row['invoice_id'],
                'type'     => 'Instant Invoice'
            ];
        }
        $results['Instant Invoices'] = $instantInvoices;
    }
    /* ----------  END INSTANT INVOICE SEARCH  ------------------------------ */

    // Search in projects
    $projectQuery = "SELECT p.order_id, p.project_title, p.project_status, p.assign_to,
                    m.name, m.lname, m.bus_name, m.phone
                    FROM projects p
                    JOIN members m ON p.mem_id = m.member_id";

    // Add user filter if not admin
    if ($user_type != '1' && $user_type != '2') {
        $projectQuery .= " WHERE p.assign_to = '$user_id'";
    }

    $projectQuery .= " AND (p.project_title LIKE '%$searchTerm%' 
                          OR p.order_id LIKE '%$searchTerm%' 
                          OR m.name LIKE '%$searchTerm%' 
                          OR m.lname LIKE '%$searchTerm%' 
                          OR m.bus_name LIKE '%$searchTerm%'
                          OR m.phone LIKE '%$searchTerm%')
                    LIMIT 10";

    $projectResult = mysqli_query($con, $projectQuery);
    if ($projectResult) {
        $projects = array();
        while ($row = mysqli_fetch_assoc($projectResult)) {
            $projectTitle = $row['project_title'] ?: 'Project #' . $row['order_id'];
            $clientName = trim($row['name'] . ' ' . $row['lname']);

            $projects[] = array(
                'id' => $row['order_id'],
                'title' => highlightSearchTerm($projectTitle, $searchTerm),
                'subtitle' => 'Client: ' . highlightSearchTerm($clientName, $searchTerm) .
                    ' | Status: ' . ucfirst($row['project_status']) .
                    ' | Assigned To: ' . highlightSearchTerm($row['assign_to'], $searchTerm),
                'link' => 'project_details.php?id=' . $row['order_id'],
                'type' => 'Project'
            );
        }
        if (!empty($projects)) {
            $results['Projects'] = $projects;
        }
    }

    // Search in quotations
    $quotationQuery = "SELECT q.quotation_id, q.quotation_sr, q.name, q.email, q.grand_total, q.status,
                      m.bus_name, m.ein
                      FROM quotations q
                      LEFT JOIN members m ON q.member_id = m.member_id
                      WHERE (q.quotation_id LIKE '%$searchTerm%' 
                             OR q.quotation_sr LIKE '%$searchTerm%' 
                             OR q.name LIKE '%$searchTerm%' 
                             OR q.email LIKE '%$searchTerm%'
                             OR m.bus_name LIKE '%$searchTerm%'
                             OR m.ein LIKE '%$searchTerm%')
                      LIMIT 10";

    $quotationResult = mysqli_query($con, $quotationQuery);
    if ($quotationResult) {
        $quotations = array();
        while ($row = mysqli_fetch_assoc($quotationResult)) {
            $quotations[] = array(
                'id' => $row['quotation_id'],
                'title' => 'Quotation #' . highlightSearchTerm($row['quotation_sr'], $searchTerm),
                'subtitle' => 'Client: ' . highlightSearchTerm($row['name'], $searchTerm) .
                    ' | Business: ' . highlightSearchTerm($row['bus_name'], $searchTerm) .
                    ' | Total: $' . $row['grand_total'] .
                    ' | Status: ' . highlightSearchTerm($row['status'], $searchTerm),
                'link' => 'quotations.php?id=' . $row['quotation_id'],
                'type' => 'Quotation'
            );
        }
        if (!empty($quotations)) {
            $results['Quotations'] = $quotations;
        }
    }

    // Search in payments
    $paymentQuery = "SELECT ph.his_id, ph.paid, ph.date, ph.payment_method,
                        bi.invoice_id, bi.filelink, m.name, m.lname, m.bus_name
                 FROM billing_payment_history ph
                 JOIN billing_invoice bi ON ph.inv_id = bi.invoice_id
                 JOIN members m ON bi.mem_id = m.member_id
                 WHERE (ph.his_id LIKE '%$searchTerm%' 
                        OR m.name LIKE '%$searchTerm%' 
                        OR m.lname LIKE '%$searchTerm%'
                        OR ph.payment_method LIKE '%$searchTerm%')
                 LIMIT 10";

    $paymentResult = mysqli_query($con, $paymentQuery);
    if ($paymentResult) {
        $payments = array();
        while ($row = mysqli_fetch_assoc($paymentResult)) {
            $clientName = trim($row['name'] . ' ' . $row['lname']);

            $payments[] = array(
                'id' => $row['his_id'],
                'title' => 'Payment #' . highlightSearchTerm($row['his_id'], $searchTerm),
                'subtitle' => 'Invoice: #' . highlightSearchTerm($row['invoice_id'], $searchTerm) .
                    ' | Client: ' . highlightSearchTerm($clientName, $searchTerm) .
                    ' | Amount: $' . number_format($row['paid'], 2) .
                    ' | Date: ' . $row['date'] .
                    ' | Method: ' . highlightSearchTerm($row['payment_method'], $searchTerm),
                // Link directly to the PDF file
                'link' => $row['filelink'],
                'type' => 'Payment'
            );
        }
        if (!empty($payments)) {
            $results['Payments'] = $payments;
        }
    }

    // Search in staff/users
    if ($user_type == '1' || $user_type == '2') {
        $staffQuery = "SELECT user_id, user_name, user_email, user_desig
                      FROM users 
                      WHERE (user_name LIKE '%$searchTerm%' 
                             OR user_email LIKE '%$searchTerm%' 
                             OR user_desig LIKE '%$searchTerm%')
                      LIMIT 10";

        $staffResult = mysqli_query($con, $staffQuery);
        if ($staffResult) {
            $staff = array();
            while ($row = mysqli_fetch_assoc($staffResult)) {
                $staff[] = array(
                    'id' => $row['user_id'],
                    'title' => highlightSearchTerm($row['user_name'], $searchTerm),
                    'subtitle' => 'ID: ' . $row['user_id'] .
                        ' | Email: ' . highlightSearchTerm($row['user_email'], $searchTerm) .
                        ' | Designation: ' . highlightSearchTerm($row['user_desig'], $searchTerm),
                    'link' => 'users.php?id=' . $row['user_id'],
                    'type' => 'Staff'
                );
            }
            if (!empty($staff)) {
                $results['Staff'] = $staff;
            }
        }
    }

    // Search in comments table
    $commentsQuery = "SELECT c.comment_id, c.comment_detail, c.comment_time, c.member_id, c.user_id, c.comment_status, c.comment_updated_by,
                     m.name, m.lname, m.bus_name, u.user_name as author_name
                     FROM comments c
                     LEFT JOIN members m ON c.member_id = m.member_id
                     LEFT JOIN users u ON c.user_id = u.user_id
                     WHERE (c.comment_detail LIKE '%$searchTerm%' 
                            OR c.comment_status LIKE '%$searchTerm%'
                            OR m.name LIKE '%$searchTerm%'
                            OR m.lname LIKE '%$searchTerm%'
                            OR m.bus_name LIKE '%$searchTerm%'
                            OR u.user_name LIKE '%$searchTerm%')
                     LIMIT 10";

    $commentsResult = mysqli_query($con, $commentsQuery);
    if ($commentsResult) {
        $comments = array();
        while ($row = mysqli_fetch_assoc($commentsResult)) {
            $clientName = trim($row['name'] . ' ' . $row['lname']);
            $clientName = $clientName ?: $row['bus_name'];
            $authorName = $row['author_name'] ?: 'Unknown';

            // Truncate comment detail for subtitle
            $commentDetail = substr($row['comment_detail'], 0, 100);
            if (strlen($row['comment_detail']) > 100) {
                $commentDetail .= '...';
            }

            $comments[] = array(
                'id' => $row['comment_id'],
                'title' => 'Comment #' . $row['comment_id'] . ' by ' . highlightSearchTerm($authorName, $searchTerm),
                'subtitle' => 'Client: ' . highlightSearchTerm($clientName, $searchTerm) .
                    ' | Date: ' . $row['comment_time'] .
                    ' | Status: ' . highlightSearchTerm($row['comment_status'], $searchTerm) .
                    '<br>' . highlightSearchTerm($commentDetail, $searchTerm),
                'link' => 'client_details.php?id=' . $row['member_id'] . '#comments',
                'type' => 'Comment'
            );
        }
        if (!empty($comments)) {
            $results['Comments'] = $comments;
        }
    }

    // Search in general_comments table
    $generalCommentsQuery = "SELECT gc.comment_id, gc.comment_detail, gc.comment_time, gc.user_id, gc.comment_to, gc.comment_updated_by,
                            u.user_name as author_name, u_to.user_name as to_user_name
                            FROM general_comments gc
                            LEFT JOIN users u ON gc.user_id = u.user_id
                            LEFT JOIN users u_to ON gc.comment_to = u_to.user_id
                            WHERE (gc.comment_detail LIKE '%$searchTerm%' 
                                   OR u.user_name LIKE '%$searchTerm%'
                                   OR u_to.user_name LIKE '%$searchTerm%')
                            LIMIT 10";

    $generalCommentsResult = mysqli_query($con, $generalCommentsQuery);
    if ($generalCommentsResult) {
        $generalComments = array();
        while ($row = mysqli_fetch_assoc($generalCommentsResult)) {
            $authorName = $row['author_name'] ?: 'Unknown';
            $toUserName = $row['to_user_name'] ?: 'All';

            // Truncate comment detail for subtitle
            $commentDetail = substr($row['comment_detail'], 0, 100);
            if (strlen($row['comment_detail']) > 100) {
                $commentDetail .= '...';
            }

            $generalComments[] = array(
                'id' => $row['comment_id'],
                'title' => 'General Comment #' . $row['comment_id'] . ' by ' . highlightSearchTerm($authorName, $searchTerm),
                'subtitle' => 'To: ' . highlightSearchTerm($toUserName, $searchTerm) .
                    ' | Date: ' . $row['comment_time'] .
                    '<br>' . highlightSearchTerm($commentDetail, $searchTerm),
                'link' => 'general_comments.php?id=' . $row['comment_id'],
                'type' => 'General Comment'
            );
        }
        if (!empty($generalComments)) {
            // If Comments array already exists, merge with it
            if (isset($results['Comments'])) {
                $results['Comments'] = array_merge($results['Comments'], $generalComments);
            } else {
                $results['Comments'] = $generalComments;
            }
        }
    }

    // Search in new_comments table
    $newCommentsQuery = "SELECT nc.comment_id, nc.comment_detail, nc.comment_time, nc.member_id, nc.invoice_id, nc.user_id, nc.comment_status, nc.comment_updated_by,
                        m.name, m.lname, m.bus_name, u.user_name as author_name
                        FROM new_comments nc
                        LEFT JOIN members m ON nc.member_id = m.member_id
                        LEFT JOIN users u ON nc.user_id = u.user_id
                        WHERE (nc.comment_detail LIKE '%$searchTerm%' 
                               OR m.name LIKE '%$searchTerm%'
                               OR m.lname LIKE '%$searchTerm%'
                               OR m.bus_name LIKE '%$searchTerm%'
                               OR u.user_name LIKE '%$searchTerm%')
                        LIMIT 10";

    $newCommentsResult = mysqli_query($con, $newCommentsQuery);
    if ($newCommentsResult) {
        $newComments = array();
        while ($row = mysqli_fetch_assoc($newCommentsResult)) {
            $clientName = trim($row['name'] . ' ' . $row['lname']);
            $clientName = $clientName ?: $row['bus_name'];
            $authorName = $row['author_name'] ?: 'Unknown';

            // Truncate comment detail for subtitle
            $commentDetail = substr($row['comment_detail'], 0, 100);
            if (strlen($row['comment_detail']) > 100) {
                $commentDetail .= '...';
            }
 
            $newComments[] = array(
                'id' => $row['comment_id'],
                'title' => 'Invoice Comment #' . $row['comment_id'] . ' by ' . highlightSearchTerm($authorName, $searchTerm),
                'subtitle' => 'Client: ' . highlightSearchTerm($clientName, $searchTerm) .
                    ' | Invoice: #' . $row['invoice_id'] .
                    ' | Date: ' . $row['comment_time'] .
                    '<br>' . highlightSearchTerm($commentDetail, $searchTerm),
                'link' => 'client_billing.php?id=' . $row['member_id'] . '&invoice_id=' . $row['invoice_id'] . '#comments',
                'type' => 'Invoice Comment'
            );
        } 
        if (!empty($newComments)) {
            // If Comments array already exists, merge with it
            if (isset($results['Comments'])) {
                $results['Comments'] = array_merge($results['Comments'], $newComments);
            } else {
                $results['Comments'] = $newComments;
            }
        }
    }

    /* ----------  INSTANT INVOICE COMMENTS SEARCH  ------------------------- */
    $instantCommentsQuery = "SELECT ic.comment_id, ic.comment_detail, ic.comment_time, ic.member_id, ic.ins_invoice_id, ic.user_id, ic.comment_status,
                            m.name, m.lname, m.bus_name,
                            bii.name AS inst_name, bii.business_name AS inst_bus, bii.phone AS inst_phone, bii.email AS inst_email,
                            u.user_name as author_name
                            FROM instant_comments ic
                            LEFT JOIN members m ON ic.member_id = m.member_id
                            LEFT JOIN billing_invoice_instant bii ON ic.ins_invoice_id = bii.invoice_id
                            LEFT JOIN users u ON ic.user_id = u.user_id
                            WHERE (ic.comment_detail LIKE '%$searchTerm%'
                                   OR ic.ins_invoice_id LIKE '%$searchTerm%'
                                   OR m.name LIKE '%$searchTerm%'
                                   OR m.lname LIKE '%$searchTerm%'
                                   OR m.bus_name LIKE '%$searchTerm%'
                                   OR bii.name LIKE '%$searchTerm%'
                                   OR bii.business_name LIKE '%$searchTerm%'
                                   OR bii.phone LIKE '%$searchTerm%'
                                   OR bii.email LIKE '%$searchTerm%'
                                   OR u.user_name LIKE '%$searchTerm%')
                            LIMIT 10";

    $instantCommentsResult = mysqli_query($con, $instantCommentsQuery);
    if ($instantCommentsResult) {
        $instantComments = array();
        while ($row = mysqli_fetch_assoc($instantCommentsResult)) {
            $clientName = trim($row['name'] . ' ' . $row['lname']);
            $clientName = $clientName ?: $row['bus_name'];
            $clientName = $clientName ?: trim($row['inst_name']);
            $clientName = $clientName ?: $row['inst_bus'];
            $authorName = $row['author_name'] ?: 'Unknown';

            // Truncate comment detail for subtitle
            $commentDetail = substr($row['comment_detail'], 0, 100);
            if (strlen($row['comment_detail']) > 100) {
                $commentDetail .= '...';
            }

            $instantComments[] = array(
                'id' => $row['comment_id'],
                'title' => 'Instant Invoice Comment #' . $row['comment_id'] . ' by ' . highlightSearchTerm($authorName, $searchTerm),
                'subtitle' => 'Client: ' . highlightSearchTerm($clientName, $searchTerm) .
                    ' | Instant Invoice: #' . highlightSearchTerm($row['ins_invoice_id'], $searchTerm) .
                    ' | Date: ' . $row['comment_time'] .
                    '<br>' . highlightSearchTerm($commentDetail, $searchTerm),
                'link' => 'instant_invoice.php?id=' . $row['member_id'] . '&invoice_id=' . $row['ins_invoice_id'] . '#comments',
                'type' => 'Instant Invoice Comment'
            );
        }
        if (!empty($instantComments)) {
            // Merge into Comments group if it already exists
            if (isset($results['Comments'])) {
                $results['Comments'] = array_merge($results['Comments'], $instantComments);
            } else {
                $results['Comments'] = $instantComments;
            }
        }
    }
    /* ----------  END INSTANT INVOICE COMMENTS SEARCH  --------------------- */

    echo json_encode($results);
} else {
    echo json_encode(array('error' => 'No search term provided'));
}
?>