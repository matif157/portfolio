<?php
/*****************************************************************************************
 *  Syed Professional Services – MASTER REPORT PAGE (single file)
 *  Drop-in replacement – simply overwrite the old file.
 *  Professional redesign with #6FD943 and white color theme
 *****************************************************************************************/
include "includes/db.php";
include "includes/auth.php";
include "includes/graph_permissions.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];
$is_admin = (($_SESSION['user_type'] ?? '') == '1');

/* Permission helper alias for use in templates below. */
$canSeeGraph = function (string $key) use ($con): bool { return canSeeGraph($con, $key); };

/* ----------  CLIENT LINK HELPER  ---------- */
/* Renders a clickable client name that opens the live client profile.
   URL pattern: client_details.php?id={member_id}&status={status}
   Color: green when active (status=1), red when inactive (status=0). */
function renderClientLink($member_id, $name, $status = null, $bus_name = ''): string {
    $member_id = (int) $member_id;
    $label = trim((string) $name);
    if ($label === '') {
        $label = $bus_name !== '' ? $bus_name : ('#' . $member_id);
    }
    if ($member_id <= 0) {
        return htmlspecialchars($label);
    }
    $statusInt = ($status === null || $status === '') ? -1 : (int) $status;
    $cls = 'client-link';
    if ($statusInt === 1) {
        $cls .= ' client-link-active';
    } elseif ($statusInt === 0) {
        $cls .= ' client-link-inactive';
    } else {
        $cls .= ' client-link-unknown';
    }
    $href = 'client_details.php?id=' . $member_id . '&status=' . max(0, $statusInt);
    $title = 'Open client profile #' . $member_id . ($statusInt === 1 ? ' (Active)' : ($statusInt === 0 ? ' (Inactive)' : ''));
    return '<a class="' . $cls . '" href="' . htmlspecialchars($href) . '" target="_blank" title="' . htmlspecialchars($title) . '">'
        . htmlspecialchars($label)
        . '</a>';
}

/* ----------  MULTI-SELECT HELPERS  ----------
   Reports now allow selecting multiple clients / users per filter. These two
   helpers keep the POST handling and the option markup short and consistent. */

/* Build a safe "<col> IN (...)" SQL fragment from a multi-select POST value.
   Accepts a scalar or an array, keeps only positive integers, de-dupes, and
   returns '' when nothing usable was selected (so the caller can skip it). */
function inNum($raw, string $col): string {
    $ids = array_values(array_unique(array_filter(
        array_map('intval', (array) $raw),
        static fn($v) => $v > 0
    )));
    return $ids ? "$col IN (" . implode(',', $ids) . ")" : '';
}

/* Return ' selected' when $value is among the values posted for $postKey,
   so a multi-select keeps its chosen options highlighted after submit. */
function msSelected(string $postKey, $value): string {
    $arr = array_map('strval', (array) ($_POST[$postKey] ?? []));
    return in_array((string) $value, $arr, true) ? ' selected' : '';
}

/* ----------  PRE-FETCH ALL DROPDOWN DATA  ---------- */
$memberTypes = $con->query("SELECT * FROM member_type")->fetch_all(MYSQLI_ASSOC);
$members = $con->query("SELECT * FROM members ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
$memberships = $con->query("SELECT membership_name FROM memberships")->fetch_all(MYSQLI_ASSOC);
$businessCategories = $con->query("SELECT busact_id, busact_name FROM busact")->fetch_all(MYSQLI_ASSOC);
$clientNames = $con->query("SELECT member_id, CONCAT(name,' ',lname) AS full_name,name,lname,bus_name FROM members")->fetch_all(MYSQLI_ASSOC);
$businessNames = $con->query("SELECT DISTINCT bus_name FROM members WHERE bus_name IS NOT NULL AND bus_name != '' ORDER BY bus_name")->fetch_all(MYSQLI_ASSOC);
$users = $con->query("SELECT user_id, user_name FROM users ORDER BY user_name ASC")->fetch_all(MYSQLI_ASSOC);
$services = $con->query("SELECT DISTINCT item_description FROM billing_invoice_items ORDER BY item_description ASC")->fetch_all(MYSQLI_ASSOC);

/* ----------  MEMBER SERVICES FILTER OPTIONS (same source as reminder_calender.php)  ----------
   Services (memberships) that are actually linked to clients via orders, auto-renewals or member_services. */
$msServiceOptions = [];
$msSvcRes = $con->query("
    SELECT membership_id, membership_name FROM memberships
    WHERE membership_id IN (
        SELECT prod_id FROM orders WHERE mem_id > 0 AND prod_id > 0
        UNION SELECT prod_id FROM orders_auto_renewed WHERE mem_id > 0 AND prod_id > 0
        UNION SELECT membership_id FROM member_services WHERE member_id > 0 AND membership_id > 0
    )
    ORDER BY membership_name ASC
");
if ($msSvcRes) {
    while ($r = $msSvcRes->fetch_assoc())
        $msServiceOptions[] = ['id' => (int)$r['membership_id'], 'name' => (string)$r['membership_name']];
}

/* ----------  ASSOCIATED SERVICES (orders) FILTER OPTIONS  ----------
   Full membership list used by the Associated Services report tab. */
$asMembershipOptions = $con->query("SELECT membership_id, membership_name FROM memberships ORDER BY membership_name ASC")->fetch_all(MYSQLI_ASSOC);

/* ----------  DASHBOARD CHART DATA  ---------- */
$dash_monthly = $con->query("
    SELECT DATE_FORMAT(STR_TO_DATE(invoice_date,'%m/%d/%Y'),'%Y-%m') AS mo,
           SUM(total_amount) AS revenue, SUM(paid_amount) AS paid
    FROM billing_invoice
    WHERE STR_TO_DATE(invoice_date,'%m/%d/%Y') >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mo ORDER BY mo ASC
")->fetch_all(MYSQLI_ASSOC);

$dash_clientStatus = $con->query("
    SELECT CASE WHEN status=1 THEN 'Active' ELSE 'Inactive' END AS lbl, COUNT(*) AS cnt
    FROM members GROUP BY lbl
")->fetch_all(MYSQLI_ASSOC);

$dash_invStatus = $con->query("
    SELECT bis.status_text AS lbl, COUNT(bi.invoice_id) AS cnt, COALESCE(SUM(bi.total_amount),0) AS total
    FROM billing_invoice bi
    JOIN billing_invoice_status bis ON bis.status_id = bi.invoice_status
    GROUP BY bi.invoice_status
")->fetch_all(MYSQLI_ASSOC);

$dash_projStatus = $con->query("
    SELECT COALESCE(project_status,'Unknown') AS lbl, COUNT(*) AS cnt FROM projects GROUP BY project_status
")->fetch_all(MYSQLI_ASSOC);

$dash_topServices = $con->query("
    SELECT bii.item_description AS lbl, SUM(bii.unit_price * bii.quantity) AS revenue
    FROM billing_invoice_items bii
    GROUP BY bii.item_description ORDER BY revenue DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$dash_revenueStats = $con->query("SELECT COALESCE(SUM(total_amount),0) AS total, COALESCE(SUM(paid_amount),0) AS paid, COALESCE(SUM(balance),0) AS balance FROM billing_invoice")->fetch_assoc();
$dash_activeClients = $con->query("SELECT COUNT(*) AS cnt FROM members WHERE status=1")->fetch_assoc()['cnt'];
$dash_openProjects  = $con->query("SELECT COUNT(*) AS cnt FROM projects WHERE project_status='open'")->fetch_assoc()['cnt'];
$dash_pendingQuotes = $con->query("SELECT COUNT(*) AS cnt FROM quotations WHERE status='pending'")->fetch_assoc()['cnt'];
/* ----------  DEFAULT VARIABLES  ---------- */
$generalFilterResult = $membersResult = $invoicesResult = $extendedResult = null;
$selectedClientType = $selectedStatusClient = $selectedStatusProduct = $selectedClient = $selectedDateRange = $customStart = $customEnd = '';
$member360 = null;
$ms_selected_ids = [];

/* ----------  POST HANDLER  ---------- */
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $buttonClicked = $_POST['action'] ?? '';
    try {

        /* =========================================================
           1.  ORIGINAL GENERAL FILTER (unchanged logic)
           ========================================================= */
        if ($buttonClicked == 'general_filter') {
            // ... (existing general filter code)
            $gf_name_in = inNum($_POST['gf_name'] ?? [], 'm.member_id');
            $gf_bus_name = $con->real_escape_string($_POST['gf_bus_name'] ?? '');
            $gf_client_type = $con->real_escape_string($_POST['gf_client_type'] ?? '');
            $gf_status = $con->real_escape_string($_POST['gf_status'] ?? '');
            $gf_ach_payer = $con->real_escape_string($_POST['gf_ach_payer'] ?? '');
            $gf_payer_frequency = $con->real_escape_string($_POST['gf_payer_frequency'] ?? '');
            $gf_category = $con->real_escape_string($_POST['gf_category'] ?? '');
            $gf_client_since_start = $con->real_escape_string($_POST['gf_client_since_start'] ?? '');
            $gf_client_since_end = $con->real_escape_string($_POST['gf_client_since_end'] ?? '');
            $gf_bus_start_start = $con->real_escape_string($_POST['gf_bus_start_start'] ?? '');
            $gf_bus_start_end = $con->real_escape_string($_POST['gf_bus_start_end'] ?? '');
            $gf_inc_start = $con->real_escape_string($_POST['gf_inc_start'] ?? '');
            $gf_inc_end = $con->real_escape_string($_POST['gf_inc_end'] ?? '');
            $gf_min_balance = $con->real_escape_string($_POST['gf_min_balance'] ?? '');
            $gf_comments = $con->real_escape_string($_POST['gf_comments'] ?? '');
            $gf_order_by = $con->real_escape_string($_POST['gf_order_by'] ?? 'm.name');
            $gf_order_dir = $con->real_escape_string($_POST['gf_order_dir'] ?? 'ASC');

            $whereConditions = [];
            if ($gf_name_in)
            $whereConditions[] = $gf_name_in;
            if ($gf_bus_name)
            $whereConditions[] = "m.bus_name = '$gf_bus_name'";
            if ($gf_client_type)
            $whereConditions[] = "m.type = '$gf_client_type'";
            if ($gf_status !== '')
            $whereConditions[] = "m.status = '$gf_status'";
            if ($gf_ach_payer !== '')
            $whereConditions[] = "m.ach_payer = '$gf_ach_payer'";
            if ($gf_payer_frequency !== '')
            $whereConditions[] = "m.payer_frequency = '$gf_payer_frequency'";
            if ($gf_category)
            $whereConditions[] = "b.busact_id = '$gf_category'";
            if ($gf_client_since_start && $gf_client_since_end)
            $whereConditions[] = "m.client_since BETWEEN '$gf_client_since_start' AND '$gf_client_since_end'";
            if ($gf_bus_start_start && $gf_bus_start_end)
            $whereConditions[] = "m.bus_start_date BETWEEN '$gf_bus_start_start' AND '$gf_bus_start_end'";
            if ($gf_inc_start && $gf_inc_end)
            $whereConditions[] = "m.incorp_date BETWEEN '$gf_inc_start' AND '$gf_inc_end'";
            if ($gf_comments)
            $whereConditions[] = "EXISTS (SELECT 1 FROM comments c WHERE c.member_id = m.member_id AND c.comment_detail LIKE '%$gf_comments%')";

            $whereClause = "WHERE 1=1";
            if ($whereConditions)
            $whereClause .= " AND " . implode(' AND ', $whereConditions);
            $havingClause = ($gf_min_balance !== '') ? "HAVING total_balance > $gf_min_balance" : '';

            $sql = "
            SELECT 
                m.member_id, m.name, m.lname, m.bus_name, m.file, m.status, m.client_since, m.ach_payer, m.payer_frequency,
                mt.type_name, ba.busact_name,
                COALESCE(SUM(bi.balance), 0) AS total_balance
            FROM members m
            LEFT JOIN member_type mt ON m.type = mt.type_id
            LEFT JOIN business b ON m.member_id = b.member_id
            LEFT JOIN busact ba ON b.busact_id = ba.busact_id
            LEFT JOIN billing_invoice bi ON m.member_id = bi.mem_id
            $whereClause
            GROUP BY m.member_id
            $havingClause
            ORDER BY $gf_order_by $gf_order_dir";
            $generalFilterResult = $con->query($sql);
        }  
        // --

        /* =========================================================
           2.  EXTENDED FILTER (search on every Add-Client field)
           ========================================================= */ elseif ($buttonClicked == 'extended_filter') {
            $ef_type = $con->real_escape_string($_POST['ef_type'] ?? '');
            $ef_special_client = $con->real_escape_string($_POST['ef_special_client'] ?? '');
            $ef_ach_payer = $con->real_escape_string($_POST['ef_ach_payer'] ?? '');
            $ef_payer_frequency = $con->real_escape_string($_POST['ef_payer_frequency'] ?? '');
            $ef_category = $con->real_escape_string(trim($_POST['ef_category'] ?? ''));
            $ef_fname = $con->real_escape_string(trim($_POST['ef_fname'] ?? ''));
            $ef_mname = $con->real_escape_string(trim($_POST['ef_mname'] ?? ''));
            $ef_lname = $con->real_escape_string(trim($_POST['ef_lname'] ?? ''));
            $ef_hstreet = $con->real_escape_string(trim($_POST['ef_hstreet'] ?? ''));
            $ef_hcity = $con->real_escape_string(trim($_POST['ef_hcity'] ?? ''));
            $ef_hstate = $con->real_escape_string(trim($_POST['ef_hstate'] ?? ''));
            $ef_hzip = $con->real_escape_string(trim($_POST['ef_hzip'] ?? ''));
            $ef_hcounty = $con->real_escape_string(trim($_POST['ef_hcounty'] ?? ''));
            $ef_bus_name = $con->real_escape_string(trim($_POST['ef_bus_name'] ?? ''));
            $ef_member_pos = $con->real_escape_string(trim($_POST['ef_member_pos'] ?? ''));
            $ef_bstreet = $con->real_escape_string(trim($_POST['ef_bstreet'] ?? ''));
            $ef_bcity = $con->real_escape_string(trim($_POST['ef_bcity'] ?? ''));
            $ef_bstate = $con->real_escape_string(trim($_POST['ef_bstate'] ?? ''));
            $ef_bzip = $con->real_escape_string(trim($_POST['ef_bzip'] ?? ''));
            $ef_bcounty = $con->real_escape_string(trim($_POST['ef_bcounty'] ?? ''));
            $ef_file = $con->real_escape_string(trim($_POST['ef_file'] ?? ''));
            $ef_ssn = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_ssn'] ?? '')));
            $ef_dba = $con->real_escape_string(trim($_POST['ef_dba'] ?? ''));
            $ef_ein = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_ein'] ?? '')));
            $ef_pmb = $con->real_escape_string(trim($_POST['ef_pmb'] ?? ''));
            $ef_dob_from = $con->real_escape_string($_POST['ef_dob_from'] ?? '');
            $ef_dob_to = $con->real_escape_string($_POST['ef_dob_to'] ?? '');
            $ef_phone_work = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_phone_work'] ?? '')));
            $ef_phone_cell = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_phone_cell'] ?? '')));
            $ef_phone_other = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_phone_other'] ?? '')));
            $ef_phone_overseas = $con->real_escape_string(str_replace('-', '', trim($_POST['ef_phone_overseas'] ?? '')));
            $ef_pemail = $con->real_escape_string(trim($_POST['ef_pemail'] ?? ''));
            $ef_semail = $con->real_escape_string(trim($_POST['ef_semail'] ?? ''));
            $ef_client_since_from = $con->real_escape_string($_POST['ef_client_since_from'] ?? '');
            $ef_client_since_to = $con->real_escape_string($_POST['ef_client_since_to'] ?? '');
            $ef_incorp_from = $con->real_escape_string($_POST['ef_incorp_from'] ?? '');
            $ef_incorp_to = $con->real_escape_string($_POST['ef_incorp_to'] ?? '');
            $ef_bus_start_from = $con->real_escape_string($_POST['ef_bus_start_from'] ?? '');
            $ef_bus_start_to = $con->real_escape_string($_POST['ef_bus_start_to'] ?? '');

            $ew = ["1=1"];
            if ($ef_type !== '') $ew[] = "m.type = '$ef_type'";
            if ($ef_special_client !== '') $ew[] = "m.specialClient = '$ef_special_client'";
            if ($ef_ach_payer !== '') $ew[] = "m.ach_payer = '$ef_ach_payer'";
            if ($ef_payer_frequency !== '') $ew[] = "m.payer_frequency = '$ef_payer_frequency'";
            if ($ef_category !== '') $ew[] = "m.category LIKE '%$ef_category%'";
            if ($ef_fname !== '') $ew[] = "m.name LIKE '%$ef_fname%'";
            if ($ef_mname !== '') $ew[] = "m.mname LIKE '%$ef_mname%'";
            if ($ef_lname !== '') $ew[] = "m.lname LIKE '%$ef_lname%'";
            if ($ef_hstreet !== '') $ew[] = "m.hstreet LIKE '%$ef_hstreet%'";
            if ($ef_hcity !== '') $ew[] = "m.hcity LIKE '%$ef_hcity%'";
            if ($ef_hstate !== '') $ew[] = "m.hstate LIKE '%$ef_hstate%'";
            if ($ef_hzip !== '') $ew[] = "m.hzip LIKE '%$ef_hzip%'";
            if ($ef_hcounty !== '') $ew[] = "m.hcounty LIKE '%$ef_hcounty%'";
            if ($ef_bus_name !== '') $ew[] = "m.bus_name LIKE '%$ef_bus_name%'";
            if ($ef_member_pos !== '') $ew[] = "m.member_pos LIKE '%$ef_member_pos%'";
            if ($ef_bstreet !== '') $ew[] = "m.address LIKE '%$ef_bstreet%'";
            if ($ef_bcity !== '') $ew[] = "m.city LIKE '%$ef_bcity%'";
            if ($ef_bstate !== '') $ew[] = "m.state LIKE '%$ef_bstate%'";
            if ($ef_bzip !== '') $ew[] = "m.zip LIKE '%$ef_bzip%'";
            if ($ef_bcounty !== '') $ew[] = "m.bcounty LIKE '%$ef_bcounty%'";
            if ($ef_file !== '') $ew[] = "m.file LIKE '%$ef_file%'";
            if ($ef_ssn !== '') $ew[] = "REPLACE(m.ss, '-', '') LIKE '%$ef_ssn%'";
            if ($ef_dba !== '') $ew[] = "m.dba LIKE '%$ef_dba%'";
            if ($ef_ein !== '') $ew[] = "REPLACE(m.ein, '-', '') LIKE '%$ef_ein%'";
            if ($ef_pmb !== '') $ew[] = "m.tf LIKE '%$ef_pmb%'";
            if ($ef_dob_from && $ef_dob_to) $ew[] = "m.dob BETWEEN '$ef_dob_from' AND '$ef_dob_to'";
            elseif ($ef_dob_from) $ew[] = "m.dob >= '$ef_dob_from'";
            elseif ($ef_dob_to) $ew[] = "m.dob <= '$ef_dob_to'";
            if ($ef_phone_work !== '') $ew[] = "REPLACE(m.phone, '-', '') LIKE '%$ef_phone_work%'";
            if ($ef_phone_cell !== '') $ew[] = "REPLACE(m.cell_ph, '-', '') LIKE '%$ef_phone_cell%'";
            if ($ef_phone_other !== '') $ew[] = "REPLACE(m.other_ph, '-', '') LIKE '%$ef_phone_other%'";
            if ($ef_phone_overseas !== '') $ew[] = "REPLACE(m.overseas_ph, '-', '') LIKE '%$ef_phone_overseas%'";
            if ($ef_pemail !== '') $ew[] = "m.prm_email LIKE '%$ef_pemail%'";
            if ($ef_semail !== '') $ew[] = "m.sec_email LIKE '%$ef_semail%'";
            if ($ef_client_since_from && $ef_client_since_to) $ew[] = "m.client_since BETWEEN '$ef_client_since_from' AND '$ef_client_since_to'";
            elseif ($ef_client_since_from) $ew[] = "m.client_since >= '$ef_client_since_from'";
            elseif ($ef_client_since_to) $ew[] = "m.client_since <= '$ef_client_since_to'";
            if ($ef_incorp_from && $ef_incorp_to) $ew[] = "m.incorp_date BETWEEN '$ef_incorp_from' AND '$ef_incorp_to'";
            elseif ($ef_incorp_from) $ew[] = "m.incorp_date >= '$ef_incorp_from'";
            elseif ($ef_incorp_to) $ew[] = "m.incorp_date <= '$ef_incorp_to'";
            if ($ef_bus_start_from && $ef_bus_start_to) $ew[] = "m.bus_start_date BETWEEN '$ef_bus_start_from' AND '$ef_bus_start_to'";
            elseif ($ef_bus_start_from) $ew[] = "m.bus_start_date >= '$ef_bus_start_from'";
            elseif ($ef_bus_start_to) $ew[] = "m.bus_start_date <= '$ef_bus_start_to'";

            $sql = "SELECT m.*, mt.type_name FROM members m
                    LEFT JOIN member_type mt ON m.type = mt.type_id
                    WHERE " . implode(' AND ', $ew) . "
                    ORDER BY m.name ASC, m.lname ASC";
            $extendedResult = $con->query($sql);
        }

        /* =========================================================
           3.  INDIVIDUAL FILTER BLOCKS (single-purpose)
           ========================================================= */
        /* ----------  INVOICE FILTER  ---------- */ elseif ($buttonClicked == 'invoice_filter') {
            $w_main = ["1=1"];
            $w_inst = ["1=1"];

            if (!empty($_POST['inv_status'])) {
                $stat = $con->real_escape_string($_POST['inv_status']);
                $w_main[] = "bi.invoice_status='$stat'";
                $w_inst[] = "bi.invoice_status='$stat'";
            }

            if (!empty($_POST['inv_product'])) {
                $pd = $con->real_escape_string($_POST['inv_product']);
                $cond = "EXISTS(SELECT 1 FROM billing_invoice_items bii WHERE bii.inv_id=bi.invoice_id AND bii.item_description='$pd')";
                $w_main[] = $cond;
                $w_inst[] = $cond;
            }
            if (!empty($_POST['inv_description'])) {
                $desc = $con->real_escape_string($_POST['inv_description']);
                $descExists = "EXISTS(
            SELECT 1
            FROM billing_invoice_items bii2
            WHERE bii2.inv_id = bi.invoice_id
            AND (
                bii2.item_description LIKE '%$desc%'
                OR bii2.item_sub_description LIKE '%$desc%'
            )
        )";
                $w_main[] = $descExists;
                /* For instant invoices also search the on-row name/business/payment_details */
                $w_inst[] = "(
            $descExists
            OR bi.name LIKE '%$desc%'
            OR bi.business_name LIKE '%$desc%'
            OR bi.payment_details LIKE '%$desc%'
        )";
            }
            if (!empty($_POST['inv_from'])) {
                $from = $con->real_escape_string($_POST['inv_from']);
                $w_main[] = "STR_TO_DATE(bi.invoice_date,'%m/%d/%Y') >= '$from'";
                $w_inst[] = "STR_TO_DATE(bi.invoice_date,'%m/%d/%Y') >= '$from'";
            }

            if (!empty($_POST['inv_to'])) {
                $to = $con->real_escape_string($_POST['inv_to']);
                $w_main[] = "STR_TO_DATE(bi.invoice_date,'%m/%d/%Y') <= '$to'";
                $w_inst[] = "STR_TO_DATE(bi.invoice_date,'%m/%d/%Y') <= '$to'";
            }

            if ($cl = inNum($_POST['inv_client'] ?? [], 'bi.mem_id')) {
                $w_main[] = $cl;
                $w_inst[] = $cl;
            }

            // Created-by user is stored inside employee_initials as "XX(id)" — match each selected id.
            $createdByIds = array_values(array_unique(array_filter(
                array_map('intval', (array) ($_POST['inv_created_by'] ?? [])), static fn($v) => $v > 0
            )));
            if ($createdByIds) {
                $ors = [];
                foreach ($createdByIds as $cb) {
                    $ors[] = "bi.employee_initials LIKE '%($cb)%'";
                    $ors[] = "bi.employee_initials = '$cb'";
                }
                $cond = '(' . implode(' OR ', $ors) . ')';
                $w_main[] = $cond;
                $w_inst[] = $cond;
            }

            if ($sb = inNum($_POST['inv_service_by'] ?? [], 'bi.service_provider_id')) {
                $w_main[] = $sb;
                $w_inst[] = $sb;
            }

            if (isset($_POST['inv_client_status']) && $_POST['inv_client_status'] !== '') {
                $cs = (int) $_POST['inv_client_status'];
                $w_main[] = "m.status = '$cs'";
                $w_inst[] = "m.status = '$cs'";
            }

            $invSource = $_POST['inv_source'] ?? 'all'; // all | regular | instant

            $sql_main = "
SELECT
    bi.invoice_id,
    'regular' AS inv_source,
    DATE_FORMAT(STR_TO_DATE(bi.invoice_date,'%m/%d/%Y'), '%Y-%m-%d') AS inv_date,
    bi.due_date,
    bi.total_amount,
    bi.paid_amount,
    bi.balance,
    bis.status_text,
    bi.is_ach,
    CONCAT(m.name,' ',m.lname) AS client,
    m.bus_name,
    m.member_id AS client_member_id,
    m.status    AS client_status,

    GROUP_CONCAT(DISTINCT bii.item_description ORDER BY bi.invoice_id SEPARATOR ', ') AS service_names,
    GROUP_CONCAT(DISTINCT bii.item_sub_description            ORDER BY bi.invoice_id SEPARATOR ', ') AS service_descriptions,

    (SELECT ph.detail
     FROM billing_payment_history ph
     WHERE ph.inv_id = bi.invoice_id
     ORDER BY ph.date DESC, ph.time DESC
     LIMIT 1) AS last_detail,

    (SELECT ph.payment_method
     FROM billing_payment_history ph
     WHERE ph.inv_id = bi.invoice_id
     ORDER BY ph.date DESC, ph.time DESC
     LIMIT 1) AS last_pay_method

FROM billing_invoice bi
JOIN billing_invoice_status bis ON bis.status_id = bi.invoice_status
JOIN members m ON m.member_id = bi.mem_id
LEFT JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
WHERE " . implode(" AND ", $w_main) . "
GROUP BY bi.invoice_id";

            $sql_inst = "
SELECT
    bi.invoice_id,
    'instant' AS inv_source,
    DATE_FORMAT(STR_TO_DATE(bi.invoice_date,'%m/%d/%Y'), '%Y-%m-%d') AS inv_date,
    bi.due_date,
    bi.total_amount,
    bi.paid_amount,
    bi.balance,
    COALESCE(bis.status_text, '-') AS status_text,
    bi.is_ach,
    COALESCE(NULLIF(TRIM(CONCAT_WS(' ', m.name, m.lname)), ''), bi.name) AS client,
    COALESCE(NULLIF(m.bus_name, ''), bi.business_name) AS bus_name,
    COALESCE(m.member_id, bi.mem_id) AS client_member_id,
    m.status                         AS client_status,

    GROUP_CONCAT(DISTINCT bii.item_description ORDER BY bi.invoice_id SEPARATOR ', ') AS service_names,
    GROUP_CONCAT(DISTINCT bii.item_sub_description            ORDER BY bi.invoice_id SEPARATOR ', ') AS service_descriptions,

    NULLIF(bi.payment_details, '0') AS last_detail,
    NULL AS last_pay_method

FROM billing_invoice_instant bi
LEFT JOIN billing_invoice_status bis ON bis.status_id = bi.invoice_status
LEFT JOIN members m ON m.member_id = bi.mem_id
LEFT JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
WHERE " . implode(" AND ", $w_inst) . "
GROUP BY bi.invoice_id";

            if ($invSource === 'instant') {
                $sql = $sql_inst . " ORDER BY inv_date DESC";
            } elseif ($invSource === 'regular') {
                $sql = $sql_main . " ORDER BY inv_date DESC";
            } else {
                $sql = "($sql_main) UNION ALL ($sql_inst) ORDER BY inv_date DESC";
            }

            $invoiceResult = $con->query($sql);
        }

        /* ----------  PROJECT FILTER  ---------- */ elseif ($buttonClicked == 'project_filter') {
            $w = ["1=1"];
            if (!empty($_POST['proj_status']))
            $w[] = "p.project_status='" . $con->real_escape_string($_POST['proj_status']) . "'";
            if (!empty($_POST['proj_from']))
            $w[] = "p.start_date>='" . $con->real_escape_string($_POST['proj_from']) . "'";
            if (!empty($_POST['proj_to']))
            $w[] = "p.start_date<='" . $con->real_escape_string($_POST['proj_to']) . "'";
            if ($f = inNum($_POST['proj_client'] ?? [], 'p.mem_id'))
            $w[] = $f;
            if ($f = inNum($_POST['proj_user'] ?? [], 'p.user_id'))
            $w[] = $f;
            if (!empty($_POST['proj_service']))
            $w[] = "EXISTS (SELECT 1 FROM billing_invoice_items bii WHERE FIND_IN_SET(bii.inv_id, p.project_invoices) AND bii.item_description='" . $con->real_escape_string($_POST['proj_service']) . "')";

            $sql = "SELECT
    p.order_id,
    p.project_title,
    p.project_status,
    p.start_date, p.exp_date,
    p.project_invoices,CONCAT(m.name,' ',m.lname) AS client,
    m.member_id AS client_member_id,
    m.status    AS client_status,
    u.user_name AS assigned_user,
    GROUP_CONCAT(
        pc.comment 
        ORDER BY pc.created_at DESC 
        SEPARATOR '\n'
    ) AS project_comments
    FROM projects p
    JOIN members m ON m.member_id=p.mem_id
    LEFT JOIN users u ON u.user_id=p.user_id
    LEFT JOIN project_comments pc ON pc.project_id = p.order_id
    WHERE " . implode(" AND ", $w) . "
    GROUP BY p.order_id
    ORDER BY p.start_date DESC";
            $projectResult = $con->query($sql);
        }

        /* ----------  QUOTATION FILTER  ---------- */ elseif ($buttonClicked == 'quotation_filter') {
            $w = ["1=1"];
            if (!empty($_POST['quote_status']))
            $w[] = "q.status='" . $con->real_escape_string($_POST['quote_status']) . "'";
            if (!empty($_POST['quote_from']))
            $w[] = "STR_TO_DATE(q.quotation_date,'%m/%d/%Y') >= '" . $con->real_escape_string($_POST['quote_from']) . "'";
            if (!empty($_POST['quote_to']))
            $w[] = "STR_TO_DATE(q.quotation_date,'%m/%d/%Y') <= '" . $con->real_escape_string($_POST['quote_to']) . "'";
            if ($f = inNum($_POST['quote_client'] ?? [], 'q.member_id'))
            $w[] = $f;

            $sql = "SELECT q.quotation_id, q.quotation_sr, q.name, q.grand_total, q.status,
                         STR_TO_DATE(q.quotation_date,'%m/%d/%Y') AS q_date,
                         CONCAT(m.name,' ',m.lname) AS client,
                         m.member_id AS client_member_id,
                         m.status    AS client_status
                  FROM quotations q
                  JOIN members m ON m.member_id=q.member_id
                  WHERE " . implode(" AND ", $w) . "
                  ORDER BY q_date DESC";
            $quotationResult = $con->query($sql);
        }
        /* ----------  PAYMENT FILTER  ---------- */ elseif ($buttonClicked == 'payment_filter') {
            $w = ["1=1"];
            if (!empty($_POST['pay_from']))
            $w[] = "ph.date>='" . $con->real_escape_string($_POST['pay_from']) . "'";
            if (!empty($_POST['pay_to']))
            $w[] = "ph.date<='" . $con->real_escape_string($_POST['pay_to']) . "'";
            if (!empty($_POST['pay_method']))
            $w[] = "ph.payment_method LIKE '%" . $con->real_escape_string($_POST['pay_method']) . "%'";
            if ($f = inNum($_POST['pay_client'] ?? [], 'ph.mem_id'))
            $w[] = $f;

            // User filter - the id is embedded in the employee column as "MA(166)".
            if ($f = inNum($_POST['pay_user'] ?? [], "SUBSTRING_INDEX(SUBSTRING_INDEX(ph.employee, '(', -1), ')', 1)"))
            $w[] = $f;

            $sql = "SELECT ph.inv_id, ph.paid, ph.balance, ph.detail, ph.date, ph.payment_method,
                         CONCAT(m.name,' ',m.lname) AS client,
                         m.member_id AS client_member_id,
                         m.status    AS client_status,
                         -- NEW: Get user name from extracted employee ID
                         u.user_name AS payment_added_by
                  FROM billing_payment_history ph
                  JOIN members m ON m.member_id=ph.mem_id
                  -- NEW: Join with users table using extracted ID
                  LEFT JOIN users u ON u.user_id = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(ph.employee, '(', -1), ')', 1) AS UNSIGNED)
                  WHERE " . implode(" AND ", $w) . "
                  ORDER BY ph.date DESC";
            $paymentResult = $con->query($sql);
        }

        /* ----------  COMMENT FILTER  ---------- */ elseif ($buttonClicked == 'comment_filter') {
            $kw = $con->real_escape_string($_POST['comm_kw'] ?? '');
            $ty = $_POST['comm_type'] ?? '';
            $clRaw = $_POST['comm_client'] ?? [];
            $from = $_POST['comm_from'] ?? '';
            $to = $_POST['comm_to'] ?? '';
            $userRaw = $_POST['comm_user'] ?? [];

            $parts = [];

            // General comments
            if ($ty == '' || $ty == 'general') {
                $where = ["c.comment_detail LIKE '%$kw%'"];
                if ($f = inNum($clRaw, 'c.member_id'))
                $where[] = $f;
                if ($from)
                $where[] = "DATE(c.comment_time) >= '$from'";
                if ($to)
                $where[] = "DATE(c.comment_time) <= '$to'";
                if ($f = inNum($userRaw, 'c.user_id'))
                $where[] = $f;

                $parts[] = "( SELECT 'general' AS src, c.comment_detail AS txt, c.comment_time AS dt, 
                     c.member_id, u.user_name AS commented_by
                     FROM comments c
                     LEFT JOIN users u ON u.user_id = c.user_id
                     WHERE " . implode(" AND ", $where) . " )";
            }

            // Invoice comments
            if ($ty == '' || $ty == 'invoice') {
                $where = ["nc.comment_detail LIKE '%$kw%'"];
                if ($f = inNum($clRaw, 'nc.member_id'))
                $where[] = $f;
                if ($from)
                $where[] = "DATE(nc.comment_time) >= '$from'";
                if ($to)
                $where[] = "DATE(nc.comment_time) <= '$to'";
                if ($f = inNum($userRaw, 'nc.user_id'))
                $where[] = $f;

                $parts[] = "( SELECT 'invoice' AS src, nc.comment_detail AS txt, nc.comment_time AS dt, 
                     nc.member_id, u.user_name AS commented_by
                     FROM new_comments nc
                     LEFT JOIN users u ON u.user_id = nc.user_id
                     WHERE " . implode(" AND ", $where) . " )";
            }

            // Project comments
            if ($ty == '' || $ty == 'project') {
                $where = ["pc.comment LIKE '%$kw%'"];
                if ($f = inNum($clRaw, 'px.mem_id'))
                $where[] = $f;
                if ($from)
                $where[] = "DATE(pc.created_at) >= '$from'";
                if ($to)
                $where[] = "DATE(pc.created_at) <= '$to'";
                if ($f = inNum($userRaw, 'pc.user_id'))
                $where[] = $f;

                $parts[] = "( SELECT 'project' AS src, pc.comment AS txt, pc.created_at AS dt,
                     px.mem_id AS member_id, u.user_name AS commented_by
                     FROM project_comments pc
                     JOIN projects px ON px.order_id = pc.project_id
                     LEFT JOIN users u ON u.user_id = pc.user_id
                     WHERE " . implode(" AND ", $where) . " )";
            }

            // Alert comments (client warnings)
            if ($ty == '' || $ty == 'alert') {
                $where = ["w.warning LIKE '%$kw%'", "w.is_active=1"];
                if ($f = inNum($clRaw, 'w.member_id'))
                $where[] = $f;
                if ($from)
                $where[] = "DATE(w.created_at) >= '$from'";
                if ($to)
                $where[] = "DATE(w.created_at) <= '$to'";
                if ($f = inNum($userRaw, 'w.created_by'))
                $where[] = $f;

                $parts[] = "( SELECT 'alert' AS src, w.warning AS txt, w.created_at AS dt,
                     w.member_id, u.user_name AS commented_by
                     FROM client_warnings w
                     LEFT JOIN users u ON u.user_id = w.created_by
                     WHERE " . implode(" AND ", $where) . " )";
            }

            $sql = implode(" UNION ", $parts) . " ORDER BY dt DESC";
            $commentResult = $con->query($sql);
        }

        /* ----------  REMINDER FILTER  ---------- */ elseif ($buttonClicked == 'reminder_filter') {
            $w = ["1=1"];

            /* existing filters */
            if (!empty($_POST['rem_type']))
            $w[] = "eh.type='" . $con->real_escape_string($_POST['rem_type']) . "'";
            if (!empty($_POST['rem_from']))
            $w[] = "eh.sent_at>='" . $con->real_escape_string($_POST['rem_from']) . " 00:00:00'";
            if (!empty($_POST['rem_to']))
            $w[] = "eh.sent_at<='" . $con->real_escape_string($_POST['rem_to']) . " 23:59:59'";
            if ($f = inNum($_POST['rem_client'] ?? [], 'eh.client_id'))
            $w[] = $f;

            /* NEW FILTERS */
            // 1. Reminder sent-by user
            if ($f = inNum($_POST['rem_sent_by_user'] ?? [], 'eh.sent_by'))
            $w[] = $f;

            // 2. Product / Membership  (join + sub-query)
            if (!empty($_POST['rem_product'])) {
                $p = $con->real_escape_string($_POST['rem_product']);
                $w[] = "EXISTS(
                  SELECT 1
                  FROM billing_invoice_items bii
                  JOIN billing_invoice bi2 ON bi2.invoice_id = bii.inv_id
                  WHERE bi2.mem_id = m.member_id
                    AND bii.item_description = '$p'
                )";
            }

            // 3. Client type
            if (!empty($_POST['rem_client_type'])) {
                $ct = $con->real_escape_string($_POST['rem_client_type']);
                $w[] = "m.type = '$ct'";
            }

            /* rest of reminder SQL stays identical */
            $sql = "SELECT eh.id, eh.subject, eh.sent_at, eh.To_mail,
           eh.client_id, eh.sent_by, eh.from_mail, eh.invoice_id,
           u.user_name AS sent_by_name,
           CONCAT(m.name,' ',m.lname) AS client,
           m.bus_name AS business_name,
           m.member_id AS client_member_id,
           m.status    AS client_status,
           bi.total_amount AS invoice_amount,
           (SELECT GROUP_CONCAT(bii.item_description SEPARATOR ', ')
              FROM billing_invoice_items bii
              WHERE bii.inv_id = eh.invoice_id) AS service_name
    FROM email_history eh
    JOIN members m ON m.member_id = eh.client_id
    LEFT JOIN users u ON u.user_id = eh.sent_by
    LEFT JOIN billing_invoice bi ON bi.invoice_id = eh.invoice_id
    WHERE " . implode(" AND ", $w) . "
    ORDER BY eh.sent_at DESC";
            $reminderResult = $con->query($sql);
        }

        /* ----------  MEMBER SERVICES FILTER (clients by service, exportable)  ---------- */
        elseif ($buttonClicked == 'member_services_filter') {
            $ms_selected_ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ms_service'] ?? [])), fn($v) => $v > 0)));
            $whereSvc = $ms_selected_ids ? "WHERE src.membership_id IN (" . implode(',', $ms_selected_ids) . ")" : "";
            $sql = "
                SELECT DISTINCT mm.member_id,
                       CONCAT(mm.name,' ',mm.lname) AS client,
                       mm.bus_name AS business_name,
                       mm.status   AS client_status,
                       src.membership_id,
                       src.membership_name
                FROM (
                    SELECT o.mem_id AS member_id, o.prod_id AS membership_id, m.membership_name
                    FROM orders o JOIN memberships m ON m.membership_id = o.prod_id
                    WHERE o.mem_id > 0 AND o.prod_id > 0
                    UNION
                    SELECT oar.mem_id, oar.prod_id, m.membership_name
                    FROM orders_auto_renewed oar JOIN memberships m ON m.membership_id = oar.prod_id
                    WHERE oar.mem_id > 0 AND oar.prod_id > 0
                    UNION
                    SELECT s.member_id, s.membership_id, m.membership_name
                    FROM member_services s JOIN memberships m ON m.membership_id = s.membership_id
                    WHERE s.member_id > 0 AND s.membership_id > 0
                ) src
                JOIN members mm ON mm.member_id = src.member_id
                $whereSvc
                ORDER BY src.membership_name ASC,
                         COALESCE(NULLIF(TRIM(mm.bus_name),''), NULLIF(TRIM(CONCAT(mm.name,' ',mm.lname)),''), mm.member_id) ASC";
            $memberServicesResult = $con->query($sql);
        }

        /* ----------  ASSOCIATED SERVICES FILTER (client products / orders, exportable)  ----------
           Mirrors the "Associated Services" section on client_details.php, which is built from
           the orders table joined to memberships + users, but across all clients. */
        elseif ($buttonClicked == 'associated_services_filter') {
            $w = ["1=1"];
            if ($f = inNum($_POST['as_client'] ?? [], 'o.mem_id'))
                $w[] = $f;
            if (!empty($_POST['as_service']))
                $w[] = "o.prod_id = '" . $con->real_escape_string($_POST['as_service']) . "'";
            if (isset($_POST['as_status']) && $_POST['as_status'] !== '')
                $w[] = "o.order_status = '" . $con->real_escape_string($_POST['as_status']) . "'";
            if ($f = inNum($_POST['as_assigned'] ?? [], 'o.assign_to'))
                $w[] = $f;
            if (!empty($_POST['as_from']))
                $w[] = "o.start_date >= '" . $con->real_escape_string($_POST['as_from']) . "'";
            if (!empty($_POST['as_to']))
                $w[] = "o.start_date <= '" . $con->real_escape_string($_POST['as_to']) . "'";
            if (isset($_POST['as_client_status']) && $_POST['as_client_status'] !== '')
                $w[] = "m.status = '" . (int) $_POST['as_client_status'] . "'";

            $sql = "SELECT
                        o.order_id, o.mem_id, o.prod_id, o.order_status,
                        o.bil_cyc_months, o.start_date, o.exp_date, o.renewal_date, o.due_date,
                        o.source,
                        ms.membership_name,
                        u.user_login  AS inserted_by,
                        au.user_name  AS assigned_to_name,
                        CONCAT(m.name,' ',m.lname) AS client,
                        m.bus_name    AS business_name,
                        m.member_id   AS client_member_id,
                        m.status      AS client_status
                    FROM (
                        SELECT order_id, mem_id, prod_id, order_status, bil_cyc_months,
                               start_date, exp_date, renewal_date, due_date, user_id, assign_to,
                               'Original' AS source
                          FROM orders
                        UNION ALL
                        /* Only genuine engine renewals carry a renewal_date; rows with a
                           NULL renewal_date are mirror copies created by trg_after_order_insert
                           for every orders insert, so they would duplicate the 'Original' row.
                           Group on the period to also collapse any exact-duplicate renewals. */
                        SELECT MAX(order_id), mem_id, prod_id, MAX(order_status), MAX(bil_cyc_months),
                               start_date, exp_date, MAX(renewal_date), MAX(due_date),
                               MAX(user_id), MAX(assign_to),
                               'Auto-Renewed' AS source
                          FROM orders_auto_renewed
                         WHERE renewal_date IS NOT NULL
                         GROUP BY mem_id, prod_id, start_date, exp_date
                    ) o
                    JOIN members m           ON m.member_id     = o.mem_id
                    LEFT JOIN memberships ms ON ms.membership_id = o.prod_id
                    LEFT JOIN users u        ON u.user_id        = o.user_id
                    LEFT JOIN users au       ON au.user_id       = o.assign_to
                    WHERE " . implode(" AND ", $w) . "
                    ORDER BY o.start_date DESC, o.exp_date DESC";
            $associatedServicesResult = $con->query($sql);
        }

        /* ----------  PRODUCT RENEWALS FILTER (renewal lifecycle, exportable)  ----------
           Shows the CURRENT (latest) period for each client+product, drawn from orders +
           genuine engine renewals (orders_auto_renewed rows that carry a renewal_date), with
           a computed days-left and an urgency bucket (Expired / Urgent / Upcoming / Scheduled).
           Mirrors the urgency thresholds used by ToBeRenewed.php and expired_renewal.php. */
        elseif ($buttonClicked == 'product_renewals_filter') {
            /* Attribute filters – applied to candidate rows BEFORE the latest period is chosen. */
            $inner = ["1=1"];
            if ($f = inNum($_POST['pr_service']  ?? [], 'base.prod_id'))   $inner[] = $f;
            if ($f = inNum($_POST['pr_client']   ?? [], 'base.mem_id'))    $inner[] = $f;
            if ($f = inNum($_POST['pr_assigned'] ?? [], 'base.assign_to')) $inner[] = $f;
            if (isset($_POST['pr_status']) && $_POST['pr_status'] !== '')
                $inner[] = "base.order_status = '" . (int) $_POST['pr_status'] . "'";
            if (isset($_POST['pr_client_status']) && $_POST['pr_client_status'] !== '')
                $inner[] = "m.status = '" . (int) $_POST['pr_client_status'] . "'";

            /* Date-range filter on whichever date field the user picked (expiry by default). */
            $pr_field = (($_POST['pr_date_field'] ?? 'exp_date') === 'start_date') ? 'start_date' : 'exp_date';
            if (!empty($_POST['pr_from']))
                $inner[] = "base.$pr_field >= '" . $con->real_escape_string($_POST['pr_from']) . "'";
            if (!empty($_POST['pr_to']))
                $inner[] = "base.$pr_field <= '" . $con->real_escape_string($_POST['pr_to']) . "'";

            /* Outer filters – operate on the computed days_left of the latest period. */
            $outer = ["x.rn = 1"];
            if (isset($_POST['pr_days_left']) && $_POST['pr_days_left'] !== '' && is_numeric($_POST['pr_days_left']))
                $outer[] = "x.days_left <= " . (int) $_POST['pr_days_left'];

            $buckets = array_values(array_intersect(
                ['expired', 'urgent', 'upcoming', 'scheduled'],
                array_map('strval', (array) ($_POST['pr_bucket'] ?? []))
            ));
            if ($buckets) {
                $bmap = [
                    'expired'   => "x.days_left < 0",
                    'urgent'    => "x.days_left BETWEEN 0 AND 7",
                    'upcoming'  => "x.days_left BETWEEN 8 AND 30",
                    'scheduled' => "x.days_left > 30",
                ];
                $ors = [];
                foreach ($buckets as $b) $ors[] = $bmap[$b];
                $outer[] = "(" . implode(" OR ", $ors) . ")";
            }

            $sql = "SELECT x.* FROM (
                        SELECT
                            base.order_id, base.mem_id, base.prod_id, base.order_status,
                            base.bil_cyc_months, base.start_date, base.exp_date,
                            base.renewal_date, base.due_date, base.source,
                            DATEDIFF(base.exp_date, CURDATE()) AS days_left,
                            ms.membership_name,
                            mt.type_name,
                            u.user_name  AS inserted_by,
                            au.user_name AS assigned_to_name,
                            CONCAT(m.name,' ',m.lname) AS client,
                            m.bus_name   AS business_name,
                            m.member_id  AS client_member_id,
                            m.status     AS client_status,
                            ROW_NUMBER() OVER (
                                PARTITION BY base.mem_id, base.prod_id
                                ORDER BY base.exp_date DESC, base.order_id DESC
                            ) AS rn
                        FROM (
                            SELECT order_id, mem_id, prod_id, order_status, bil_cyc_months,
                                   start_date, exp_date, renewal_date, due_date, user_id, assign_to,
                                   'Original' AS source
                              FROM orders
                            UNION ALL
                            SELECT order_id, mem_id, prod_id, order_status, bil_cyc_months,
                                   start_date, exp_date, renewal_date, due_date, user_id, assign_to,
                                   'Auto-Renewed' AS source
                              FROM orders_auto_renewed
                             WHERE renewal_date IS NOT NULL
                        ) base
                        JOIN members m            ON m.member_id     = base.mem_id
                        LEFT JOIN memberships ms  ON ms.membership_id = base.prod_id
                        LEFT JOIN member_type mt  ON mt.type_id       = m.type
                        LEFT JOIN users u         ON u.user_id        = base.user_id
                        LEFT JOIN users au        ON au.user_id       = base.assign_to
                        WHERE " . implode(" AND ", $inner) . "
                    ) x
                    WHERE " . implode(" AND ", $outer) . "
                    ORDER BY (x.days_left IS NULL), x.days_left ASC";
            $productRenewalsResult = $con->query($sql);
        }

        /* =========================================================
           MEMBER 360 – everything for ONE member in one shot
           ========================================================= */
        elseif ($buttonClicked == 'member_360') {
            $m360_id = (int)($_POST['m360_member_id'] ?? 0);
            if ($m360_id > 0) {
                $member360 = ['member_id' => $m360_id];

                /* profile */
                $member360['profile'] = $con->query("
                    SELECT m.*, mt.type_name
                    FROM members m
                    LEFT JOIN member_type mt ON mt.type_id = m.type
                    WHERE m.member_id = $m360_id LIMIT 1
                ")->fetch_assoc();

                /* business records */
                $member360['business'] = $con->query("
                    SELECT b.*, ba.busact_name
                    FROM business b
                    LEFT JOIN busact ba ON ba.busact_id = b.busact_id
                    WHERE b.member_id = $m360_id
                    ORDER BY b.business_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* spouse / relations */
                $member360['spouse'] = $con->query("
                    SELECT * FROM spouse
                    WHERE member_id = $m360_id
                    ORDER BY spouse_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* invoices + line items aggregated count */
                $member360['invoices'] = $con->query("
                    SELECT bi.invoice_id, bi.invoice_date, bi.due_date, bi.total_amount,
                           bi.paid_amount, bi.balance, bis.status_text AS status_text,
                           bi.payment_details, bi.paid_date, bi.filelink,
                           (SELECT COUNT(*) FROM billing_invoice_items bii WHERE bii.inv_id = bi.invoice_id) AS item_count
                    FROM billing_invoice bi
                    LEFT JOIN billing_invoice_status bis ON bis.status_id = bi.invoice_status
                    WHERE bi.mem_id = $m360_id
                    ORDER BY STR_TO_DATE(bi.invoice_date,'%m/%d/%Y') DESC, bi.invoice_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* invoice line items (all) */
                $member360['invoice_items'] = $con->query("
                    SELECT bii.inv_item_id, bii.inv_id, bii.item_description, bii.item_sub_description,
                           bii.unit_price, bii.quantity, bii.total_price, bii.item_date
                    FROM billing_invoice_items bii
                    WHERE bii.mem_id = $m360_id
                    ORDER BY bii.inv_id DESC, bii.inv_item_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* projects */
                $member360['projects'] = $con->query("
                    SELECT p.order_id, p.project_title, p.project_description, p.project_status,
                           p.project_type, p.start_date, p.exp_date, p.date_added,
                           p.project_created_at, p.project_updated_at, u.user_name AS assigned_to_name
                    FROM projects p
                    LEFT JOIN users u ON u.user_id = p.assign_to
                    WHERE p.mem_id = $m360_id
                    ORDER BY p.order_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* orders & orders_completed */
                $member360['orders'] = $con->query("
                    SELECT o.*, ms.membership_name
                    FROM orders o
                    LEFT JOIN memberships ms ON ms.membership_id = o.prod_id
                    WHERE o.mem_id = $m360_id
                    ORDER BY o.order_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* member_services */
                $member360['services'] = $con->query("
                    SELECT ms.id, ms.description, ms.added_at, m2.membership_name, u.user_name
                    FROM member_services ms
                    LEFT JOIN memberships m2 ON m2.membership_id = ms.membership_id
                    LEFT JOIN users u ON u.user_id = ms.user_id
                    WHERE ms.member_id = $m360_id
                    ORDER BY ms.id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* quotations */
                $member360['quotations'] = $con->query("
                    SELECT quotation_id, quotation_sr, name, email, phone, sub_total, discount,
                           grand_total, quotation_date, status, file_name
                    FROM quotations
                    WHERE member_id = $m360_id
                    ORDER BY quotation_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* general comments */
                $member360['comments'] = $con->query("
                    SELECT c.comment_id, c.comment_detail, c.comment_time, c.comment_status, u.user_name
                    FROM comments c
                    LEFT JOIN users u ON u.user_id = c.user_id
                    WHERE c.member_id = $m360_id
                    ORDER BY c.comment_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* invoice / new comments */
                $member360['new_comments'] = $con->query("
                    SELECT nc.comment_id, nc.comment_detail, nc.comment_time, nc.invoice_id, u.user_name
                    FROM new_comments nc
                    LEFT JOIN users u ON u.user_id = nc.user_id
                    WHERE nc.member_id = $m360_id
                    ORDER BY nc.comment_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* notes */
                $member360['notes'] = $con->query("
                    SELECT n.notes_id, n.notes_detail, n.notes_time, u.user_name
                    FROM notestab n
                    LEFT JOIN users u ON u.user_id = n.user_id
                    WHERE n.member_id = $m360_id
                    ORDER BY n.notes_id DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* email / sms history */
                $member360['emails'] = $con->query("
                    SELECT eh.id, eh.subject, eh.body, eh.sent_at, eh.To_mail, eh.from_mail,
                           eh.invoice_id, eh.type, u.user_name AS sent_by_name
                    FROM email_history eh
                    LEFT JOIN users u ON u.user_id = eh.sent_by
                    WHERE eh.client_id = $m360_id
                    ORDER BY eh.sent_at DESC
                ")->fetch_all(MYSQLI_ASSOC);

                /* totals row */
                $member360['totals'] = $con->query("
                    SELECT COUNT(*) AS inv_count,
                           COALESCE(SUM(total_amount),0) AS total_billed,
                           COALESCE(SUM(paid_amount),0)  AS total_paid,
                           COALESCE(SUM(balance),0)      AS total_balance
                    FROM billing_invoice WHERE mem_id = $m360_id
                ")->fetch_assoc();
            }
        }

        /* ----------  ORIGINAL SIMPLE FILTERS (unchanged)  ---------- */ else {
            $clientType = isset($_POST['client_type']) ? $_POST['client_type'] : '';
            $selectedClientType = isset($_POST['client_type']) ? $_POST['client_type'] : '';
            $selectedStatusClient = isset($_POST['status_filter_client']) ? $_POST['status_filter_client'] : 'all';
            $selectedStatusProduct = isset($_POST['status_filter_product']) ? $_POST['status_filter_product'] : 'all';
            $arrangeBy = $_POST['arrange_by'] ?? 'name';
            $order = isset($_POST['order']) && $_POST['order'] == 'Asc' ? 'ASC' : 'DESC';

            $sql = "SELECT m.*, mt.type_name 
            FROM members m 
            LEFT JOIN member_type mt ON m.type = mt.type_id 
            WHERE 1=1";

            if ($buttonClicked == 'view_active') {
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE m.status = 1";
            } elseif ($buttonClicked == 'view_client' && !empty($clientType)) {
                $whereConditions = ["m.type = " . $con->real_escape_string($clientType)];
                if ($selectedStatusClient == 'active')
                $whereConditions[] = "m.status = 1";
                elseif ($selectedStatusClient == 'inactive')
                $whereConditions[] = "m.status = 0";
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE " . implode(' AND ', $whereConditions);
            } elseif ($buttonClicked == 'view_deactive') {
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE m.status = 0";
            } elseif ($buttonClicked == 'view_product_membership') {
                $productName = $con->real_escape_string($_POST['product_mem'] ?? '');
                $productQuery = $con->query("SELECT membership_id FROM memberships WHERE membership_name = '$productName'");
                $productId = ($productQuery->num_rows > 0) ? $productQuery->fetch_assoc()['membership_id'] : 0;
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE m.type = '$productId'";
                if ($selectedStatusProduct == 'active')
                $sql .= " AND m.status = 1";
                elseif ($selectedStatusProduct == 'inactive')
                $sql .= " AND m.status = 0";
            } elseif ($buttonClicked == 'view_clients_by_status_date') {
                $status = $_POST['client_status_filter'] ?? 'active';
                $start = $con->real_escape_string($_POST['client_start_date'] ?? '');
                $end = $con->real_escape_string($_POST['client_end_date'] ?? '');
                $where = [($status === 'active' ? 'm.status = 1' : 'm.status = 0')];
                if ($start && $end)
                $where[] = "m.client_since BETWEEN '$start' AND '$end'";
                $dir = ($_POST['client_order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE " . implode(' AND ', $where) . " ORDER BY m.name $dir";
            } elseif ($buttonClicked == 'view_all_biz_by_date') {
                $start = $con->real_escape_string($_POST['biz_start'] ?? '');
                $end = $con->real_escape_string($_POST['biz_end'] ?? '');
                $where = ["m.bus_name <> ''", "m.status = 1"];
                if ($start && $end)
                $where[] = "m.client_since BETWEEN '$start' AND '$end'";
                $dir = ($_POST['biz_order'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
                $sql = "SELECT m.*, mt.type_name FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id WHERE " . implode(' AND ', $where) . " ORDER BY m.bus_name $dir, m.name $dir";
            } elseif ($buttonClicked == 'view_pending_balance') {
                $clientTypeBalance = $con->real_escape_string($_POST['client_type_balance'] ?? 'all');
                $statusFilter = $con->real_escape_string($_POST['status_filter_balance'] ?? 'all');
                $arrangeBy = $con->real_escape_string($_POST['arrange_by_balance'] ?? 'total_balance');
                $order = $_POST['order_balance'] == 'ASC' ? 'ASC' : 'DESC';
                $statusCondition = ($statusFilter == 'active') ? "AND m.status = 1" : (($statusFilter == 'inactive') ? "AND m.status = 0" : "");
                $clientTypeCondition = ($clientTypeBalance != 'all') ? "AND m.type = '$clientTypeBalance'" : "";
                $sql = "SELECT m.member_id, m.name, m.mname, m.lname, m.bus_name, m.file, m.status, mt.type_name, COALESCE(SUM(bi.balance), 0) AS total_balance FROM members m LEFT JOIN member_type mt ON m.type = mt.type_id LEFT JOIN billing_invoice bi ON m.member_id = bi.mem_id WHERE 1=1 $statusCondition $clientTypeCondition GROUP BY m.member_id HAVING total_balance > 0 ORDER BY $arrangeBy $order";
            } elseif ($buttonClicked == 'view_client_invoices') {
                $selectedClient = $con->real_escape_string($_POST['client_id_invoices'] ?? '');
                $selectedDateRange = $_POST['date_range'] ?? 'last_week';
                $customStart = $_POST['custom_start'] ?? '';
                $customEnd = $_POST['custom_end'] ?? '';
                $arrangeByInvoices = $con->real_escape_string($_POST['arrange_by_invoices'] ?? 'invoice_date');
                $orderInvoices = $_POST['order_invoices'] == 'Asc' ? 'ASC' : 'DESC';
                $today = date('Y-m-d');
                $startDate = '';
                $endDate = $today;
                switch ($selectedDateRange) {
                    case 'last_week':
                        $startDate = date('Y-m-d', strtotime('-1 week'));
                        break;
                    case 'last_month':
                        $startDate = date('Y-m-d', strtotime('-1 month'));
                        break;
                    case 'last_year':
                        $startDate = date('Y-m-d', strtotime('-1 year'));
                        break;
                    case 'custom':
                        $startDate = $con->real_escape_string($customStart);
                        $endDate = $con->real_escape_string($customEnd);
                        break;
                }
                $sql = "SELECT bi.invoice_id, STR_TO_DATE(bi.invoice_date, '%m/%d/%Y %H:%i:%s') AS invoice_date, bi.due_date, bi.total_amount, bi.balance, bi.invoice_status, bi.paid_amount, m.name, m.lname, m.bus_name
                FROM billing_invoice bi
                JOIN members m ON bi.mem_id = m.member_id
                WHERE bi.mem_id = '$selectedClient'
                AND DATE(STR_TO_DATE(bi.invoice_date, '%m/%d/%Y %H:%i:%s')) BETWEEN '$startDate' AND '$endDate'
                ORDER BY $arrangeByInvoices $orderInvoices";
                $invoicesResult = $con->query($sql);
            }

            /* ----------  FINAL SORT FOR SIMPLE FILTERS  ---------- */
            if (!in_array($buttonClicked, ['general_filter', 'extended_filter', 'invoice_filter', 'project_filter', 'quotation_filter', 'payment_filter', 'comment_filter', 'reminder_filter', 'view_pending_balance', 'view_client_invoices'])) {
                $validColumns = ['name', 'ss', 'ein', 'hzip'];
                if (!in_array($arrangeBy, $validColumns))
                $arrangeBy = 'name';
                $sql .= " ORDER BY $arrangeBy $order";
                $membersResult = $con->query($sql);
            }
        }
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}

/* active tab after POST */
$activeTab = 'dashboard';
if (isset($generalFilterResult))   $activeTab = 'general';
elseif (isset($extendedResult))    $activeTab = 'extended';
elseif (isset($invoiceResult))     $activeTab = 'invoices';
elseif (isset($projectResult))     $activeTab = 'projects';
elseif (isset($quotationResult))   $activeTab = 'quotations';
elseif (isset($paymentResult))     $activeTab = 'payments';
elseif (isset($commentResult))     $activeTab = 'comments';
elseif (isset($reminderResult))    $activeTab = 'reminders';
elseif (isset($memberServicesResult)) $activeTab = 'memberServices';
elseif (isset($associatedServicesResult)) $activeTab = 'associatedServices';
elseif (isset($productRenewalsResult)) $activeTab = 'productRenewals';
elseif (!empty($member360))        $activeTab = 'member360';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syed Professional Services – Reports</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #6FD943;
            --primary-dark: #4CAF50;
            --primary-light: #8EE16D;
            --primary-pale: rgba(111,217,67,0.08);
            --primary-color: #6FD943;
            --sidebar-bg: #141c2e;
            --sidebar-hover: #1e2a40;
            --sidebar-section: #0f1520;
            --text-dark: #1e2a3a;
            --text-light: #6b7280;
            --bg-light: #f0f4f8;
            --bg-white: #ffffff;
            --border-color: #e5e8ee;
            --shadow: 0 2px 8px rgba(0,0,0,0.07);
            --shadow-hover: 0 8px 24px rgba(0,0,0,0.13);
            --sidebar-width: 260px;
            --header-height: 66px;
            --radius: 10px;
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--bg-light);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            padding: 0;
            margin: 0;
            line-height: 1.6;
            min-height: 100vh;
            font-size: 0.92rem;
        }

        /* ── Top Bar ── */
        .page-topbar {
            background: linear-gradient(120deg, #1a2235 0%, #141c2e 100%);
            color: white;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.22);
        }
        .page-topbar .logo { height: 34px; border-radius: 0; border: 2px solid rgba(111,217,67,0.4); }
        .page-topbar .topbar-title {
            font-weight: 700; font-size: 1rem; color: #fff; flex: 1;
            display: flex; align-items: center; gap: 10px;
            border: 1px solid rgba(255,255,255,0.18);
            padding: 6px 14px;
        }
        .page-topbar .topbar-title .badge-rc {
            background: rgba(111,217,67,0.18); border: 1px solid rgba(111,217,67,0.35);
            color: var(--primary); font-size: 0.68rem; font-weight: 600;
            padding: 3px 9px; border-radius: 0; letter-spacing: 0.6px; text-transform: uppercase;
        }
        .page-topbar .back-link {
            display: flex; align-items: center; gap: 6px;
            color: #fff; font-size: 0.82rem; font-weight: 600; text-decoration: none;
            padding: 7px 14px; border-radius: 0;
            border: none;
            transition: all .2s;
        }
        .page-topbar .back-link.link-permissions {
            background: #7c3aed;
        }
        .page-topbar .back-link.link-permissions:hover {
            background: #6d28d9;
        }
        .page-topbar .back-link.link-dashboard {
            background: var(--primary-dark);
        }
        .page-topbar .back-link.link-dashboard:hover {
            background: #3d8b3d;
        }

        /* ── Horizontal Tab Nav ── */
        .report-topnav {
            background: #fff;
            border-bottom: 2px solid var(--border-color);
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .report-topnav .nav-tabs {
            border-bottom: none;
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: none;
            gap: 0;
        }
        .report-topnav .nav-tabs::-webkit-scrollbar { display: none; }
        .report-topnav .nav-tabs .nav-link {
            color: #64748b;
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            padding: 13px 18px;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: all .2s ease;
        }
        .report-topnav .nav-tabs .nav-link:hover { color: var(--primary-dark); background: rgba(111,217,67,0.06); }
        .report-topnav .nav-tabs .nav-link.active {
            color: var(--primary-dark);
            border-bottom-color: var(--primary);
            font-weight: 600;
            background: transparent;
        }
        .report-topnav .nav-section-divider {
            width: 1px; background: var(--border-color); margin: 8px 4px; align-self: stretch;
        }

        /* ── Content ── */
        .page-content {
            padding: 24px 28px;
            max-width: 100%;
        }

        /* ── Cards ── */
        .card {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
            margin-bottom: 22px;
            background-color: var(--bg-white);
            transition: box-shadow 0.2s ease;
        }

        .card:hover { box-shadow: var(--shadow-hover); }

        .card-header {
            background: linear-gradient(to right, #fafbfc, #fff);
            border-bottom: 1px solid var(--border-color);
            padding: 14px 20px;
            border-radius: 12px 12px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h4, .card-header h5 {
            margin: 0;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .card-header h4 i, .card-header h5 i {
            color: var(--primary-dark);
            font-size: 0.9rem;
        }

        .card-body { padding: 20px; }

        /* Filter card — prominent left accent */
        .filter-card > .card-header {
            border-left: 4px solid var(--primary);
            border-radius: 12px 12px 0 0 !important;
            background: linear-gradient(to right, rgba(111,217,67,0.04), #fff);
        }

        /* ── Forms ── */
        .form-group { margin-bottom: 18px; }

        .form-group label {
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-light);
            margin-bottom: 6px;
            display: block;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1.5px solid #dde2ec;
            padding: 9px 14px;
            font-size: 0.88rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: var(--bg-white);
            color: var(--text-dark);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(111,217,67,0.18);
            outline: none;
        }

        .form-control::placeholder { color: #b0bac9; font-size: 0.86rem; }

        /* ── Buttons ── */
        .btn {
            border-radius: 8px;
            padding: 9px 22px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            letter-spacing: 0.1px;
        }

        .btn i { font-size: 0.85rem; }

        .btn-primary, .btn-info {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            color: #1a2b0a;
            box-shadow: 0 2px 8px rgba(111,217,67,0.3);
        }

        .btn-primary:hover, .btn-info:hover {
            background: linear-gradient(135deg, #7de34f 0%, #54b858 100%);
            color: #1a2b0a;
            box-shadow: 0 4px 14px rgba(111,217,67,0.4);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #f0f4f8;
            border: 1.5px solid #dde2ec;
            color: var(--text-dark);
        }

        .btn-secondary:hover {
            background-color: #e4e9f0;
            border-color: #ccd3e0;
            color: var(--text-dark);
            transform: translateY(-1px);
        }

        .btn-run {
            padding: 10px 28px;
            font-size: 0.9rem;
            min-width: 160px;
        }

        /* ── Tables ── */
        .table {
            margin-top: 4px;
            font-size: 0.855rem;
        }

        .table thead th {
            background: linear-gradient(135deg, #1a2235 0%, #243050 100%);
            color: #e2e8f0;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            padding: 12px 14px;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 10px 14px;
            vertical-align: middle;
            border-color: #f0f2f7;
        }

        .table tbody tr:nth-of-type(odd) { background-color: #fafbfd; }
        .table tbody tr:hover { background-color: rgba(111,217,67,0.06) !important; }

        .balance-positive { color: #dc3545; font-weight: 700; }
        .no-balance { color: #28a745; font-weight: 700; }

        /* ── Stats cards (dashboard KPIs) ── */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .stat-card {
            background-color: var(--bg-white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s ease;
        }

        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-icon.primary { background: rgba(111,217,67,0.15); color: var(--primary-dark); }
        .stat-icon.info    { background: rgba(23,162,184,0.15); color: #17a2b8; }
        .stat-icon.warning { background: rgba(255,193,7,0.15);  color: #d4a017; }
        .stat-icon.danger  { background: rgba(220,53,69,0.15);  color: #dc3545; }

        .stat-details h3 { margin: 0; font-size: 1.7rem; font-weight: 700; }
        .stat-details p  { margin: 3px 0 0; color: var(--text-light); font-size: 0.82rem; }

        /* ── Filter Section ── */
        .filter-section {
            background: #f7f9fc;
            border-radius: 10px;
            padding: 18px;
            margin-bottom: 18px;
            border: 1px solid var(--border-color);
        }

        .section-title {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: var(--primary); }

        /* ── Advanced Filter Collapse ── */
        .advanced-filter {
            background: #f7f9fc;
            border-radius: 10px;
            padding: 18px;
            margin-top: 16px;
            border: 1px solid var(--border-color);
        }

        .filter-toggle {
            cursor: pointer;
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 0.83rem;
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 7px;
            transition: color 0.2s;
        }

        .filter-toggle i { transition: transform 0.25s ease; }
        .filter-toggle.collapsed i { transform: rotate(-90deg); }
        .filter-toggle:hover { color: #3a8c3e; }

        /* ── DataTables ── */
        .dt-buttons { margin-bottom: 12px; }

        .dt-buttons .dt-button {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #1a2b0a;
            border-radius: 7px;
            padding: 7px 14px;
            margin-right: 6px;
            border: none;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(111,217,67,0.25);
        }

        .dt-buttons .dt-button:hover {
            background: linear-gradient(135deg, #7de34f 0%, #54b858 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(111,217,67,0.35);
        }

        /* ── Select2 ── */
        .select2-container--default .select2-selection--single {
            height: 40px;
            border: 1.5px solid #dde2ec;
            border-radius: 8px;
            background: #fff;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            padding-left: 14px;
            font-size: 0.88rem;
            color: var(--text-dark);
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(111,217,67,0.18);
        }

        /* ── Misc UI ── */
        .tab-content { padding-top: 4px; }

        .no-results { text-align: center; padding: 40px; color: var(--text-light); }
        .no-results i { font-size: 46px; margin-bottom: 14px; color: #d1d8e5; }

        .result-summary {
            background: linear-gradient(to right, rgba(111,217,67,0.08), transparent);
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 18px;
            font-weight: 600;
            font-size: 0.875rem;
            border-left: 4px solid var(--primary);
            color: var(--text-dark);
        }

        /* ── Badge ── */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .badge-success { background: var(--primary); color: #1a2b0a; }

        /* ── Status dots ── */
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-active  { background-color: #28a745; }
        .status-inactive{ background-color: #dc3545; }
        .status-pending { background-color: #f59e0b; }

        /* ── Clickable client links (status-aware) ── */
        .client-link {
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px dashed transparent;
            transition: color .15s ease, border-color .15s ease, background .15s ease;
        }
        .client-link:hover {
            text-decoration: none;
            border-bottom-style: solid;
        }
        .client-link-active {
            color: #1e7e34;
        }
        .client-link-active:hover {
            color: #155724;
            border-bottom-color: #1e7e34;
        }
        .client-link-inactive {
            color: #c82333;
        }
        .client-link-inactive:hover {
            color: #8b1a23;
            border-bottom-color: #c82333;
        }
        .client-link-unknown {
            color: #555;
        }
        .client-link-unknown:hover {
            color: #000;
            border-bottom-color: #555;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 30px;
            padding: 20px 0;
            text-align: center;
            color: var(--text-light);
            font-size: 0.8rem;
            background: var(--bg-white);
            border-top: 1px solid var(--border-color);
        }

        /* ── Report sub-tabs ── */
        .report-subtabs {
            border-bottom: 2px solid var(--border-color);
            gap: 4px;
            margin-bottom: 18px;
        }

        .report-subtabs .nav-item { margin-bottom: -2px; }

        .report-subtabs .nav-link {
            color: var(--text-light);
            border: none;
            border-bottom: 2px solid transparent;
            padding: 9px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 0;
            transition: all 0.2s;
            background: transparent;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .report-subtabs .nav-link:hover:not(.disabled) {
            color: var(--primary-dark);
            border-bottom-color: var(--primary-light);
        }

        .report-subtabs .nav-link.active {
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary);
            background: transparent;
        }

        .report-subtabs .nav-link.disabled {
            opacity: 0.4;
            pointer-events: none;
        }

        .report-subtabs .nav-link .badge {
            font-size: 0.7rem;
            background: var(--primary);
            color: #1a2b0a;
            padding: 2px 7px;
        }

        /* ── Empty graph state ── */
        .empty-graph-state {
            background: #f8fafc;
            border: 2px dashed #dde2ec;
            border-radius: 12px;
            padding: 64px 24px;
            text-align: center;
        }

        .empty-graph-state i { color: #c0cad8; margin-bottom: 14px; }
        .empty-graph-state h6 { color: var(--text-light); font-weight: 600; }
        .empty-graph-state p  { color: #94a3b8; font-size: 0.85rem; margin: 0; }

        /* ── Animations ── */
        .fade-in { animation: fadeIn 0.4s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .report-topnav .nav-tabs .nav-link { padding: 11px 12px; font-size: 0.78rem; }
            .page-content { padding: 16px; }
            .stats-container { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>

<body>
    <!-- Top Bar -->
    <div class="page-topbar">
        <a href="index.php"><img src="storage/uploads/logo/logo-dark.png" alt="Logo" class="logo"></a>
        <div class="topbar-title">
            <i class="fas fa-chart-bar" style="color:var(--primary);"></i>
            Report Center
            <span class="badge-rc">Analytics</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($is_admin): ?>
                <a href="graph_permissions.php" class="back-link link-permissions"><i class="fas fa-user-shield me-1"></i>Graph Permissions</a>
            <?php endif; ?>
            <a href="index.php" class="back-link link-dashboard"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <!-- Horizontal Tab Navigation -->
    <nav class="report-topnav">
        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= isset($invoiceResult)?'':'active' ?>" id="dashboard-tab" data-bs-toggle="tab" href="#dashboard" role="tab"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="nav-section-divider d-none d-md-flex"></li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="general-tab" data-bs-toggle="tab" href="#general" role="tab"><i class="fas fa-users"></i> General</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="extended-tab" data-bs-toggle="tab" href="#extended" role="tab"><i class="fas fa-sliders-h"></i> Extended</a>
            </li>
            <li class="nav-section-divider d-none d-md-flex"></li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="invoices-tab" data-bs-toggle="tab" href="#invoices" role="tab"><i class="fas fa-file-invoice-dollar"></i> Invoices</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="quotations-tab" data-bs-toggle="tab" href="#quotations" role="tab"><i class="fas fa-file-alt"></i> Quotations</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="payments-tab" data-bs-toggle="tab" href="#payments" role="tab"><i class="fas fa-credit-card"></i> Payments</a>
            </li>
            <li class="nav-section-divider d-none d-md-flex"></li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="projects-tab" data-bs-toggle="tab" href="#projects" role="tab"><i class="fas fa-project-diagram"></i> Projects</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="comments-tab" data-bs-toggle="tab" href="#comments" role="tab"><i class="fas fa-comments"></i> Comments</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="reminders-tab" data-bs-toggle="tab" href="#reminders" role="tab"><i class="fas fa-bell"></i> Reminders</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="memberServices-tab" data-bs-toggle="tab" href="#memberServices" role="tab"><i class="fas fa-briefcase"></i> Member Services</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="associatedServices-tab" data-bs-toggle="tab" href="#associatedServices" role="tab"><i class="fas fa-tags"></i> Associated Services</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="productRenewals-tab" data-bs-toggle="tab" href="#productRenewals" role="tab"><i class="fas fa-sync-alt"></i> Product Renewals</a>
            </li>
            <li class="nav-section-divider d-none d-md-flex"></li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="member360-tab" data-bs-toggle="tab" href="#member360" role="tab"><i class="fas fa-id-card"></i> Member Full Data</a>
            </li>
        </ul>
    </nav>

    <!-- Content -->
    <div class="page-content">
        <form method="POST" action="">
                <!-- Tab Content -->
                <div class="tab-content" id="reportTabsContent">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                        <?php
$d_moLabels  = json_encode(array_column($dash_monthly, 'mo'));
$d_moRevenue = json_encode(array_map('floatval', array_column($dash_monthly, 'revenue')));
$d_moPaid    = json_encode(array_map('floatval', array_column($dash_monthly, 'paid')));

$d_csLabels  = json_encode(array_column($dash_clientStatus, 'lbl'));
$d_csData    = json_encode(array_map('intval', array_column($dash_clientStatus, 'cnt')));

$d_isLabels  = json_encode(array_column($dash_invStatus, 'lbl'));
$d_isData    = json_encode(array_map('intval', array_column($dash_invStatus, 'cnt')));

$d_psLabels  = json_encode(array_column($dash_projStatus, 'lbl'));
$d_psData    = json_encode(array_map('intval', array_column($dash_projStatus, 'cnt')));

$d_svcLabels = json_encode(array_column($dash_topServices, 'lbl'));
$d_svcData   = json_encode(array_map('floatval', array_column($dash_topServices, 'revenue')));
                        ?>
                        <?php
                        $show_kpi_revenue = $canSeeGraph('dash_kpi_revenue');
                        $show_kpi_balance = $canSeeGraph('dash_kpi_balance');
                        $show_dash_revenue       = $canSeeGraph('dash_revenue');
                        $show_dash_client_status = $canSeeGraph('dash_client_status');
                        $show_dash_inv_status    = $canSeeGraph('dash_invoice_status');
                        $show_dash_proj_status   = $canSeeGraph('dash_project_status');
                        $show_dash_top_services  = $canSeeGraph('dash_top_services');
                        $show_dash_rev_summary   = $canSeeGraph('dash_revenue_summary');
                        ?>
                        <div class="fade-in">
                            <!-- KPI Row -->
                            <div class="row g-3 mb-4">
                                <?php if ($show_kpi_revenue): ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 h-100" style="background:linear-gradient(135deg,#6FD943,#4CAF50);">
                                        <div class="card-body d-flex align-items-center text-white p-3">
                                            <div class="me-3 fs-1 opacity-75"><i class="fas fa-dollar-sign"></i></div>
                                            <div>
                                                <div class="fw-bold fs-5">$<?= number_format($dash_revenueStats['total'], 0) ?></div>
                                                <div class="small opacity-90">Total Revenue</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 h-100" style="background:linear-gradient(135deg,#17a2b8,#138496);">
                                        <div class="card-body d-flex align-items-center text-white p-3">
                                            <div class="me-3 fs-1 opacity-75"><i class="fas fa-users"></i></div>
                                            <div>
                                                <div class="fw-bold fs-5"><?= $dash_activeClients ?></div>
                                                <div class="small opacity-90">Active Clients</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 h-100" style="background:linear-gradient(135deg,#ffc107,#e0a800);">
                                        <div class="card-body d-flex align-items-center text-white p-3">
                                            <div class="me-3 fs-1 opacity-75"><i class="fas fa-project-diagram"></i></div>
                                            <div>
                                                <div class="fw-bold fs-5"><?= $dash_openProjects ?></div>
                                                <div class="small opacity-90">Open Projects</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($show_kpi_balance): ?>
                                <div class="col-xl-3 col-md-6">
                                    <div class="card border-0 h-100" style="background:linear-gradient(135deg,#dc3545,#c82333);">
                                        <div class="card-body d-flex align-items-center text-white p-3">
                                            <div class="me-3 fs-1 opacity-75"><i class="fas fa-file-invoice-dollar"></i></div>
                                            <div>
                                                <div class="fw-bold fs-5">$<?= number_format($dash_revenueStats['balance'], 0) ?></div>
                                                <div class="small opacity-90">Outstanding Balance</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Revenue Trend + Client Status -->
                            <?php if ($show_dash_revenue || $show_dash_client_status): ?>
                            <div class="row g-3 mb-4">
                                <?php if ($show_dash_revenue): ?>
                                <div class="col-lg-8">
                                    <div class="card h-100">
                                        <div class="card-header"><h4><i class="fas fa-chart-line"></i> Monthly Revenue (Last 12 Months)</h4></div>
                                        <div class="card-body"><canvas id="dashRevenueChart" style="max-height:280px;"></canvas></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($show_dash_client_status): ?>
                                <div class="<?= $show_dash_revenue ? 'col-lg-4' : 'col-lg-12' ?>">
                                    <div class="card h-100">
                                        <div class="card-header"><h4><i class="fas fa-users"></i> Client Status</h4></div>
                                        <div class="card-body d-flex align-items-center justify-content-center">
                                            <canvas id="dashClientChart" style="max-height:250px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Invoice Status + Project Status + Top Services -->
                            <?php if ($show_dash_inv_status || $show_dash_proj_status || $show_dash_top_services): ?>
                            <div class="row g-3 mb-4">
                                <?php if ($show_dash_inv_status): ?>
                                <div class="col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header"><h4><i class="fas fa-file-invoice"></i> Invoice Status</h4></div>
                                        <div class="card-body d-flex align-items-center justify-content-center">
                                            <canvas id="dashInvStatusChart" style="max-height:250px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($show_dash_proj_status): ?>
                                <div class="col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header"><h4><i class="fas fa-project-diagram"></i> Project Status</h4></div>
                                        <div class="card-body d-flex align-items-center justify-content-center">
                                            <canvas id="dashProjChart" style="max-height:250px;"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($show_dash_top_services): ?>
                                <div class="col-lg-4">
                                    <div class="card h-100">
                                        <div class="card-header"><h4><i class="fas fa-chart-bar"></i> Revenue by Service (Top 8)</h4></div>
                                        <div class="card-body"><canvas id="dashSvcChart" style="max-height:250px;"></canvas></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($show_dash_rev_summary): ?>
                            <!-- Revenue Summary Bar -->
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header"><h4><i class="fas fa-chart-bar"></i> Revenue vs Collected vs Outstanding</h4></div>
                                        <div class="card-body"><canvas id="dashRevSummaryChart" style="max-height:200px;"></canvas></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const palette = ['#6FD943','#4CAF50','#17a2b8','#ffc107','#dc3545','#8EE16D','#138496','#e0a800'];

                            <?php if ($show_dash_revenue): ?>
                            // Monthly Revenue Line Chart
                            new Chart(document.getElementById('dashRevenueChart'), {
                                type: 'line',
                                data: {
                                    labels: <?= $d_moLabels ?>,
                                    datasets: [
                                        { label: 'Revenue', data: <?= $d_moRevenue ?>, borderColor:'#6FD943', backgroundColor:'rgba(111,217,67,0.15)', fill:true, tension:0.4, pointRadius:4 },
                                        { label: 'Collected', data: <?= $d_moPaid ?>, borderColor:'#17a2b8', backgroundColor:'rgba(23,162,184,0.1)', fill:true, tension:0.4, pointRadius:4 }
                                    ]
                                },
                                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'top' } }, scales:{ y:{ beginAtZero:true, ticks:{ callback: v => '$'+v.toLocaleString() } } } }
                            });
                            <?php endif; ?>

                            <?php if ($show_dash_client_status): ?>
                            // Client Status Doughnut
                            new Chart(document.getElementById('dashClientChart'), {
                                type: 'doughnut',
                                data: { labels: <?= $d_csLabels ?>, datasets:[{ data: <?= $d_csData ?>, backgroundColor:['#6FD943','#dc3545'], borderWidth:2, borderColor:'#fff' }] },
                                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
                            });
                            <?php endif; ?>

                            <?php if ($show_dash_inv_status): ?>
                            // Invoice Status Doughnut
                            new Chart(document.getElementById('dashInvStatusChart'), {
                                type: 'doughnut',
                                data: { labels: <?= $d_isLabels ?>, datasets:[{ data: <?= $d_isData ?>, backgroundColor: palette, borderWidth:2, borderColor:'#fff' }] },
                                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
                            });
                            <?php endif; ?>

                            <?php if ($show_dash_proj_status): ?>
                            // Project Status Pie
                            new Chart(document.getElementById('dashProjChart'), {
                                type: 'pie',
                                data: { labels: <?= $d_psLabels ?>, datasets:[{ data: <?= $d_psData ?>, backgroundColor:['#6FD943','#ffc107','#17a2b8','#dc3545'], borderWidth:2, borderColor:'#fff' }] },
                                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
                            });
                            <?php endif; ?>

                            <?php if ($show_dash_top_services): ?>
                            // Top Services Horizontal Bar
                            new Chart(document.getElementById('dashSvcChart'), {
                                type: 'bar',
                                data: { labels: <?= $d_svcLabels ?>, datasets:[{ label:'Revenue', data: <?= $d_svcData ?>, backgroundColor:'rgba(111,217,67,0.8)', borderColor:'#6FD943', borderWidth:1 }] },
                                options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ beginAtZero:true, ticks:{ callback: v => '$'+v.toLocaleString() } } } }
                            });
                            <?php endif; ?>

                            <?php if ($show_dash_rev_summary): ?>
                            // Revenue Summary Horizontal Bar
                            new Chart(document.getElementById('dashRevSummaryChart'), {
                                type: 'bar',
                                data: {
                                    labels: ['Revenue', 'Collected', 'Outstanding'],
                                    datasets:[{ data:[<?= floatval($dash_revenueStats['total']) ?>,<?= floatval($dash_revenueStats['paid']) ?>,<?= floatval($dash_revenueStats['balance']) ?>], backgroundColor:['rgba(111,217,67,0.85)','rgba(23,162,184,0.85)','rgba(220,53,69,0.85)'], borderColor:['#6FD943','#17a2b8','#dc3545'], borderWidth:2 }]
                                },
                                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{ callback: v => '$'+v.toLocaleString() } } } }
                            });
                            <?php endif; ?>
                        });
                        </script>
                    </div>

                    <!-- General Filter Tab -->
                    <div class="tab-pane fade" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <ul class="nav report-subtabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($generalFilterResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#gen-filter-pane" type="button" role="tab">
                                    <i class="fas fa-filter"></i> Filters
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($generalFilterResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#gen-graph-pane" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Results &amp; Graphs
                                    <?php if(isset($generalFilterResult)): ?><span class="badge"><?= $generalFilterResult->num_rows ?></span><?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($generalFilterResult)?'show active':'' ?>" id="gen-filter-pane" role="tabpanel">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client Name / ID</label>
                                            <select class="form-control select2-client" name="gf_name[]" multiple data-placeholder="All Clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('gf_name', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Business Name</label>
                                            <select class="form-control select2-client" name="gf_bus_name">
                                                <option value="">All Businesses</option>
                                                <?php foreach ($businessNames as $b)
                                                    echo '<option>' . htmlspecialchars($b['bus_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client Type</label>
                                            <select class="form-control" name="gf_client_type">
                                                <option value="">All Types</option>
                                                <?php foreach ($memberTypes as $t)
                                                    echo '<option value="' . $t['type_id'] . '">' . htmlspecialchars($t['type_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select class="form-control" name="gf_status">
                                                <option value="">All</option>
                                                <option value="1">Active</option>
                                                <option value="0">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>ACH Payer</label>
                                            <select class="form-control" name="gf_ach_payer">
                                                <option value="">All</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Payer Type</label>
                                            <select class="form-control" name="gf_payer_frequency">
                                                <option value="">All</option>
                                                <option value="Monthly">Monthly</option>
                                                <option value="Quarterly">Quarterly</option>
                                                <option value="None">None</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Business Category</label>
                                            <select class="form-control" name="gf_category">
                                                <option value="">All</option>
                                                <?php foreach ($businessCategories as $cat)
                                                    echo '<option value="' . $cat['busact_id'] . '">' . htmlspecialchars($cat['busact_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Min. Pending Balance</label>
                                            <input type="number" step="0.01" class="form-control" name="gf_min_balance"
                                                placeholder="100.00">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Search in Comments</label>
                                            <input type="text" class="form-control" name="gf_comments"
                                                placeholder="keyword">
                                        </div>
                                    </div>
                                </div>

                                <div class="filter-toggle collapsed" data-bs-toggle="collapse"
                                    data-bs-target="#advancedGeneralFilter">
                                    <i class="fas fa-chevron-down"></i> Advanced Options
                                </div>
                                <div id="advancedGeneralFilter" class="collapse advanced-filter">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Client Since (Start)</label>
                                                <input type="date" class="form-control" name="gf_client_since_start">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Client Since (End)</label>
                                                <input type="date" class="form-control" name="gf_client_since_end">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Business Start (Start)</label>
                                                <input type="date" class="form-control" name="gf_bus_start_start">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Business Start (End)</label>
                                                <input type="date" class="form-control" name="gf_bus_start_end">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Incorp. Date (Start)</label>
                                                <input type="date" class="form-control" name="gf_inc_start">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Incorp. Date (End)</label>
                                                <input type="date" class="form-control" name="gf_inc_end">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Sort By</label>
                                                <select class="form-control" name="gf_order_by">
                                                    <option value="m.name">Client Name</option>
                                                    <option value="m.bus_name">Business Name</option>
                                                    <option value="m.client_since">Client Since</option>
                                                    <option value="total_balance">Pending Balance</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Sort Order</label>
                                                <select class="form-control" name="gf_order_dir">
                                                    <option value="ASC">Ascending</option>
                                                    <option value="DESC">Descending</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="general_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /gen-filter-pane -->
                        <div class="tab-pane fade <?= isset($generalFilterResult)?'show active':'' ?>" id="gen-graph-pane" role="tabpanel">
                    <?php if (isset($generalFilterResult)): ?>
                    <?php
$gf_rows = []; $generalFilterResult->data_seek(0);
while ($gr = $generalFilterResult->fetch_assoc()) $gf_rows[] = $gr;
$gf_statusLbls=[]; $gf_typeLbls=[]; $gf_topBal=[];
foreach ($gf_rows as $gr) {
    $sl = $gr['status'] ? 'Active' : 'Inactive';
    $gf_statusLbls[$sl] = ($gf_statusLbls[$sl]??0)+1;
    $tl = $gr['type_name']??'Unknown';
    $gf_typeLbls[$tl] = ($gf_typeLbls[$tl]??0)+1;
    if ($gr['total_balance']>0) $gf_topBal[] = $gr;
}
usort($gf_topBal, fn($a,$b)=>$b['total_balance']<=>$a['total_balance']);
$gf_topBal = array_slice($gf_topBal,0,10);
$gf_totalBal = array_sum(array_column($gf_rows,'total_balance'));
$gf_active = $gf_statusLbls['Active']??0;
$gf_inactive = $gf_statusLbls['Inactive']??0;
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($gf_rows) ?></div><div class="small">Total Clients</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $gf_active ?></div><div class="small">Active</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#dc3545,#c82333)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $gf_inactive ?></div><div class="small">Inactive</div></div></div></div>
                            <?php if ($is_admin): ?>
                            <div class="col-md-3"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold">$<?= number_format($gf_totalBal,0) ?></div><div class="small">Total Balance</div></div></div></div>
                            <?php endif; ?>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('general_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> Status</h5></div><div class="card-body"><canvas id="gfStatusChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-users-cog"></i> By Type</h5></div><div class="card-body"><canvas id="gfTypeChart" style="max-height:200px;"></canvas></div></div></div>
                            <?php if ($is_admin): ?>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-balance-scale"></i> Top Balances</h5></div><div class="card-body"><canvas id="gfBalChart" style="max-height:200px;"></canvas></div></div></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-filter"></i> Results <span class="badge badge-success"><?= count($gf_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="generalFilterTable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>Name</th><th>Business</th><th>Type</th><th>Category</th><th>Status</th><th>Client Since</th><th>ACH</th><th>Payer Type</th><?php if ($is_admin): ?><th>Balance</th><?php endif; ?></tr></thead><tbody>
                        <?php foreach ($gf_rows as $r): ?>
                        <tr><td><?= $r['member_id'] ?></td><td><?= renderClientLink($r['member_id'] ?? 0, trim(($r['name'] ?? '').' '.($r['lname'] ?? '')), $r['status'] ?? null, $r['bus_name'] ?? '') ?></td><td><?= htmlspecialchars($r['bus_name']??'') ?></td><td><?= htmlspecialchars($r['type_name']??'') ?></td><td><?= htmlspecialchars($r['busact_name']??'') ?></td>
                        <td><span class="status-indicator <?= $r['status']?'status-active':'status-inactive' ?>"></span><?= $r['status']?'Active':'Inactive' ?></td>
                        <td><?= $r['client_since'] ?></td><td><?= $r['ach_payer']?'Yes':'No' ?></td><td><?= htmlspecialchars($r['payer_frequency']??'None') ?></td>
                        <?php if ($is_admin): ?><td class="<?= $r['total_balance']>0?'balance-positive':'no-balance' ?>">$<?= number_format($r['total_balance'],2) ?></td><?php endif; ?></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <?php if ($canSeeGraph('general_graphs')): ?>
                    <script>
                    (function(){
                        const pal=['#6FD943','#dc3545','#17a2b8','#ffc107','#4CAF50','#e0a800','#138496','#c82333'];
                        new Chart(document.getElementById('gfStatusChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($gf_statusLbls)) ?>,datasets:[{data:<?= json_encode(array_values($gf_statusLbls)) ?>,backgroundColor:['#6FD943','#dc3545'],borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('gfTypeChart'),{type:'pie',data:{labels:<?= json_encode(array_keys($gf_typeLbls)) ?>,datasets:[{data:<?= json_encode(array_values($gf_typeLbls)) ?>,backgroundColor:pal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        <?php if ($is_admin): ?>
                        new Chart(document.getElementById('gfBalChart'),{type:'bar',data:{labels:<?= json_encode(array_map(fn($r)=>$r['name'].' '.$r['lname'],$gf_topBal)) ?>,datasets:[{label:'Balance',data:<?= json_encode(array_map(fn($r)=>floatval($r['total_balance']),$gf_topBal)) ?>,backgroundColor:'rgba(220,53,69,0.8)',borderColor:'#dc3545',borderWidth:1}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}}}});
                        <?php endif; ?>
                    })();
                    </script>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-chart-pie fa-3x d-block mb-3"></i><h6>No results yet</h6><p>Set your filters and click <strong>Run Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /gen-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /general tab-pane -->

                    <!-- Extended Filter Tab -->
                    <div class="tab-pane fade" id="extended" role="tabpanel" aria-labelledby="extended-tab">
                        <ul class="nav report-subtabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($extendedResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#ext-filter-pane" type="button" role="tab">
                                    <i class="fas fa-sliders-h"></i> Filters
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($extendedResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#ext-result-pane" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Results
                                    <?php if(isset($extendedResult)): ?><span class="badge"><?= $extendedResult->num_rows ?></span><?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($extendedResult)?'show active':'' ?>" id="ext-filter-pane" role="tabpanel">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Client Type</label>
                                            <select class="form-control" name="ef_type">
                                                <option value="">Any</option>
                                                <?php foreach ($memberTypes as $t)
                                                    echo '<option value="' . $t['type_id'] . '">' . htmlspecialchars($t['type_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Sympathetic Client</label>
                                            <select class="form-control" name="ef_special_client">
                                                <option value="">Any</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>ACH Payer</label>
                                            <select class="form-control" name="ef_ach_payer">
                                                <option value="">Any</option>
                                                <option value="1">Yes</option>
                                                <option value="0">No</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Payer Type</label>
                                            <select class="form-control" name="ef_payer_frequency">
                                                <option value="">Any</option>
                                                <option value="None">None</option>
                                                <option value="Monthly">Monthly</option>
                                                <option value="Quarterly">Quarterly</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label>Industry Category / Business Activity</label>
                                            <input type="text" class="form-control" name="ef_category" placeholder="e.g. Construction, Health Care...">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>First Name</label>
                                            <input type="text" class="form-control" name="ef_fname" placeholder="First Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Middle Name</label>
                                            <input type="text" class="form-control" name="ef_mname" placeholder="Middle Name">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Last Name</label>
                                            <input type="text" class="form-control" name="ef_lname" placeholder="Last Name">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Personal Address</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Street</label>
                                            <input type="text" class="form-control" name="ef_hstreet" placeholder="Street">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>City</label>
                                            <input type="text" class="form-control" name="ef_hcity" placeholder="City">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>State</label>
                                            <input type="text" class="form-control" name="ef_hstate" placeholder="State">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Zip</label>
                                            <input type="text" class="form-control" name="ef_hzip" placeholder="Zip">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>County</label>
                                            <input type="text" class="form-control" name="ef_hcounty" placeholder="County">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Business Info</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Business Name</label>
                                            <select class="form-control select2-client" name="ef_bus_name">
                                                <option value="">Any</option>
                                                <?php foreach ($businessNames as $b)
                                                    echo '<option>' . htmlspecialchars($b['bus_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Designation</label>
                                            <input type="text" class="form-control" name="ef_member_pos" placeholder="Designation">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Business Address</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Street</label>
                                            <input type="text" class="form-control" name="ef_bstreet" placeholder="Street">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>City</label>
                                            <input type="text" class="form-control" name="ef_bcity" placeholder="City">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>State</label>
                                            <input type="text" class="form-control" name="ef_bstate" placeholder="State">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Zip</label>
                                            <input type="text" class="form-control" name="ef_bzip" placeholder="Zip">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>County</label>
                                            <input type="text" class="form-control" name="ef_bcounty" placeholder="County">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Identification</h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>File</label>
                                            <input type="text" class="form-control" name="ef_file" placeholder="File No">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>ITIN/SSN</label>
                                            <input type="text" class="form-control" name="ef_ssn" placeholder="XXX-XX-XXXX">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>DBA</label>
                                            <input type="text" class="form-control" name="ef_dba" placeholder="DBA">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>EIN</label>
                                            <input type="text" class="form-control" name="ef_ein" placeholder="XX-XXXXXXX">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>PMB</label>
                                            <input type="text" class="form-control" name="ef_pmb" placeholder="PMB">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Contact</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Phone: Work</label>
                                            <input type="text" class="form-control" name="ef_phone_work" placeholder="XXX-XXX-XXXX">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Phone: Cell</label>
                                            <input type="text" class="form-control" name="ef_phone_cell" placeholder="XXX-XXX-XXXX">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Phone: Other</label>
                                            <input type="text" class="form-control" name="ef_phone_other" placeholder="XXX-XXX-XXXX">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Phone: Overseas</label>
                                            <input type="text" class="form-control" name="ef_phone_overseas" placeholder="Phone: Overseas">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Primary Email</label>
                                            <input type="text" class="form-control" name="ef_pemail" placeholder="Primary Email">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Business Email</label>
                                            <input type="text" class="form-control" name="ef_semail" placeholder="Business Email">
                                        </div>
                                    </div>
                                </div>

                                <h6 class="mt-3">Important Dates</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date of Birth (range)</label>
                                            <div class="d-flex">
                                                <input type="date" class="form-control me-2" name="ef_dob_from">
                                                <input type="date" class="form-control" name="ef_dob_to">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client Since (range)</label>
                                            <div class="d-flex">
                                                <input type="date" class="form-control me-2" name="ef_client_since_from">
                                                <input type="date" class="form-control" name="ef_client_since_to">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Incorporation Date (range)</label>
                                            <div class="d-flex">
                                                <input type="date" class="form-control me-2" name="ef_incorp_from">
                                                <input type="date" class="form-control" name="ef_incorp_to">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Business Start Date (range)</label>
                                            <div class="d-flex">
                                                <input type="date" class="form-control me-2" name="ef_bus_start_from">
                                                <input type="date" class="form-control" name="ef_bus_start_to">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="extended_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search-plus"></i> Run Extended Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /ext-filter-pane -->
                        <div class="tab-pane fade <?= isset($extendedResult)?'show active':'' ?>" id="ext-result-pane" role="tabpanel">
                        <?php if (isset($extendedResult)): ?>
                        <?php
$ef_rows = []; $extendedResult->data_seek(0);
while ($er = $extendedResult->fetch_assoc()) $ef_rows[] = $er;
$ef_active = 0; $ef_inactive = 0;
foreach ($ef_rows as $er) { if (!empty($er['status'])) $ef_active++; else $ef_inactive++; }
                        ?>
                        <div class="mt-4 fade-in">
                            <div class="row g-3 mb-3">
                                <div class="col-md-4"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($ef_rows) ?></div><div class="small">Total Matches</div></div></div></div>
                                <div class="col-md-4"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $ef_active ?></div><div class="small">Active</div></div></div></div>
                                <div class="col-md-4"><div class="card border-0 text-white h-100" style="background:linear-gradient(135deg,#dc3545,#c82333)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $ef_inactive ?></div><div class="small">Inactive</div></div></div></div>
                            </div>
                            <div class="card"><div class="card-header"><h4><i class="fas fa-sliders-h"></i> Results <span class="badge badge-success"><?= count($ef_rows) ?></span></h4></div><div class="card-body">
                            <div class="table-responsive"><table id="extendedFilterTable" class="table table-striped table-hover">
                            <thead><tr>
                                <th>ID</th><th>Name</th><th>Business Name</th><th>Type</th><th>Status</th><th>DOB</th><th>SSN</th><th>EIN</th><th>File</th>
                                <th>Cell Phone</th><th>Primary Email</th><th>Personal Address</th><th>Business Address</th><th>Client Since</th><th>Designation</th><th>Category</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($ef_rows as $r): ?>
                            <tr>
                                <td><?= $r['member_id'] ?></td>
                                <td><?= renderClientLink($r['member_id'] ?? 0, trim(($r['name'] ?? '').' '.($r['lname'] ?? '')), $r['status'] ?? null, $r['bus_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['bus_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['type_name'] ?? '') ?></td>
                                <td><span class="status-indicator <?= $r['status']?'status-active':'status-inactive' ?>"></span><?= $r['status']?'Active':'Inactive' ?></td>
                                <td><?= htmlspecialchars($r['dob'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ss'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['ein'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['file'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['cell_ph'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['prm_email'] ?? '') ?></td>
                                <td><?= htmlspecialchars(trim(($r['hstreet'] ?? '').' '.($r['hcity'] ?? '').' '.($r['hstate'] ?? '').' '.($r['hzip'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars(trim(($r['address'] ?? '').' '.($r['city'] ?? '').' '.($r['state'] ?? '').' '.($r['zip'] ?? ''))) ?></td>
                                <td><?= htmlspecialchars($r['client_since'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['member_pos'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['category'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody></table></div>
                            </div></div>
                        </div>
                        <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-chart-pie fa-3x d-block mb-3"></i><h6>No results yet</h6><p>Set your filters and click <strong>Run Extended Report</strong> to see data here.</p></div>
                        <?php endif; ?>
                        </div><!-- /ext-result-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /extended tab-pane -->

                    <!-- Invoice Filter Tab -->
                    <div class="tab-pane fade" id="invoices" role="tabpanel" aria-labelledby="invoices-tab">
                        <ul class="nav report-subtabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($invoiceResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#inv-filter-pane" type="button" role="tab">
                                    <i class="fas fa-filter"></i> Filters
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($invoiceResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#inv-graph-pane" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Results &amp; Graphs
                                    <?php if(isset($invoiceResult)): ?><span class="badge"><?= $invoiceResult->num_rows ?></span><?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($invoiceResult)?'show active':'' ?>" id="inv-filter-pane" role="tabpanel">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select class="form-control" name="inv_status">
                                                <option value="">Any</option>
                                                <?php $st = $con->query("SELECT status_id,status_text FROM billing_invoice_status");
while ($s = $st->fetch_assoc())
                                                    echo '<option value="' . $s['status_id'] . '">' . htmlspecialchars($s['status_text']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Membership</label>
                                            <select class="form-control select2-client" name="inv_product">
                                                <option value="">Any</option>
                                                <?php
$ms = $con->query("SELECT membership_name FROM memberships ORDER BY membership_name");
while ($m = $ms->fetch_assoc()) {
    echo '<option>' . htmlspecialchars($m['membership_name']) . '</option>';
}
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date from</label>
                                            <input type="date" class="form-control" name="inv_from">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date to</label>
                                            <input type="date" class="form-control" name="inv_to">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="inv_client[]" multiple data-placeholder="All Clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('inv_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Search in Invoice Description / Keywords</label>
                                            <input type="text" class="form-control" name="inv_description"
                                                placeholder="Search items, services & instant-invoice client/payment details...">
                                            <small class="text-muted">Searches both regular &amp; instant invoices when "All" source is selected.</small>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Invoice Source</label>
                                                <?php $invSrcSel = $_POST['inv_source'] ?? 'all'; ?>
                                                <select class="form-control" name="inv_source">
                                                    <option value="all"     <?= $invSrcSel==='all'?'selected':'' ?>>All Invoices (Regular + Instant)</option>
                                                    <option value="regular" <?= $invSrcSel==='regular'?'selected':'' ?>>Regular Invoices Only</option>
                                                    <option value="instant" <?= $invSrcSel==='instant'?'selected':'' ?>>Instant Invoices Only</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Invoice Created By User</label>
                                                <select class="form-control select2-client" name="inv_created_by[]" multiple data-placeholder="All Users (select one or more)">
                                                    <?php foreach ($users as $u): ?>
                                                        <option value="<?= $u['user_id'] ?>"<?= msSelected('inv_created_by', $u['user_id']) ?>>
                                                            <?= htmlspecialchars($u['user_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Invoice Service Provided By User</label>
                                                <select class="form-control select2-client" name="inv_service_by[]" multiple data-placeholder="All Users (select one or more)">
                                                    <?php foreach ($users as $u): ?>
                                                        <option value="<?= $u['user_id'] ?>"<?= msSelected('inv_service_by', $u['user_id']) ?>>
                                                            <?= htmlspecialchars($u['user_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Client Status</label>
                                                <?php $invCsSel = $_POST['inv_client_status'] ?? ''; ?>
                                                <select class="form-control" name="inv_client_status">
                                                    <option value=""  <?= $invCsSel===''  ? 'selected' : '' ?>>All Clients (Active + Inactive)</option>
                                                    <option value="1" <?= $invCsSel==='1' ? 'selected' : '' ?>>Active Clients Only</option>
                                                    <option value="0" <?= $invCsSel==='0' ? 'selected' : '' ?>>Inactive Clients Only</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="invoice_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Invoice Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /inv-filter-pane -->
                        <div class="tab-pane fade <?= isset($invoiceResult)?'show active':'' ?>" id="inv-graph-pane" role="tabpanel">
                    <?php if (isset($invoiceResult)): ?>
                    <?php
$inv_rows=[]; $invoiceResult->data_seek(0);
while($ir=$invoiceResult->fetch_assoc()) $inv_rows[]=$ir;
$inv_total=0; $inv_paid=0; $inv_bal=0; $inv_stCounts=[]; $inv_methodCounts=[];
$inv_regCount=0; $inv_instCount=0;
foreach($inv_rows as $ir){
    $inv_total+=floatval($ir['total_amount']); $inv_paid+=floatval($ir['paid_amount']); $inv_bal+=floatval($ir['balance']);
    $st=$ir['status_text']??'Unknown'; $inv_stCounts[$st]=($inv_stCounts[$st]??0)+1;
    $pm=$ir['last_pay_method']??'N/A'; $inv_methodCounts[$pm]=($inv_methodCounts[$pm]??0)+1;
    if(($ir['inv_source']??'regular')==='instant'){ $inv_instCount++; } else { $inv_regCount++; }
}
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-5 fw-bold">$<?= number_format($inv_total,2) ?></div><div class="small">Total Amount</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#28a745,#20c997)"><div class="card-body text-center"><div class="fs-5 fw-bold">$<?= number_format($inv_paid,2) ?></div><div class="small">Total Paid</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#dc3545,#c82333)"><div class="card-body text-center"><div class="fs-5 fw-bold">$<?= number_format($inv_bal,2) ?></div><div class="small">Outstanding</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-5 fw-bold"><?= count($inv_rows) ?></div><div class="small">Total Invoices</div><div class="small mt-1" style="opacity:.9;">Regular: <?= $inv_regCount ?> &nbsp;|&nbsp; Instant: <?= $inv_instCount ?></div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('invoice_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> By Status</h5></div><div class="card-body"><canvas id="invStatusChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-bar"></i> Revenue vs Paid vs Balance</h5></div><div class="card-body"><canvas id="invMoneyChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-credit-card"></i> Payment Methods</h5></div><div class="card-body"><canvas id="invMethodChart" style="max-height:200px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-file-invoice-dollar"></i> Invoice Results <span class="badge badge-success"><?= count($inv_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="invTable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>Source</th><th>Client</th><th>Business</th><th>Date</th><th>Due</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Service(s)</th><th>Description</th><th>Detail</th><th>Pay Method</th></tr></thead><tbody>
                        <?php foreach($inv_rows as $r):
$isInst = (($r['inv_source'] ?? 'regular') === 'instant');
$srcBadge = $isInst
? '<span class="badge" style="background:#17a2b8;color:#fff;">Instant</span>'
: '<span class="badge" style="background:#6FD943;color:#fff;">Regular</span>';
                        ?>
                        <tr><td><?= $r['invoice_id'] ?></td><td><?= $srcBadge ?></td><td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'], $r['client_status'] ?? null, $r['bus_name'] ?? '') ?></td><td><?= htmlspecialchars($r['bus_name']??'—') ?></td><td><?= $r['inv_date'] ?></td><td><?= $r['due_date'] ?></td>
                        <td>$<?= number_format($r['total_amount'],2) ?></td><td>$<?= number_format($r['paid_amount'],2) ?></td>
                        <td class="<?= $r['balance']>0?'balance-positive':'no-balance' ?>">$<?= number_format($r['balance'],2) ?></td>
                        <td><?= htmlspecialchars($r['status_text']) ?></td><td><?= nl2br(htmlspecialchars($r['service_names']??'—')) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['service_descriptions']??'—')) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['last_detail']??'N/A')) ?></td><td><?= htmlspecialchars($r['last_pay_method']??'N/A') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <?php if ($canSeeGraph('invoice_graphs')): ?>
                    <script>
                    (function(){
                        const pal=['#6FD943','#ffc107','#17a2b8','#dc3545','#4CAF50','#e0a800','#138496','#c82333'];
                        const invCharts = {};
                        function buildInvCharts(){
                            if (typeof Chart === 'undefined') return;
                            const sEl = document.getElementById('invStatusChart');
                            const mEl = document.getElementById('invMoneyChart');
                            const pEl = document.getElementById('invMethodChart');
                            if (!sEl || !mEl || !pEl) return;
                            // Skip if container hidden — chart would draw to 0x0
                            if (sEl.offsetParent === null) return;
                            // Destroy any prior instances so we don't double-draw
                            ['s','m','p'].forEach(k=>{ if (invCharts[k]) { invCharts[k].destroy(); invCharts[k]=null; } });
                            invCharts.s = new Chart(sEl,{type:'doughnut',data:{labels:<?= json_encode(array_keys($inv_stCounts)) ?>,datasets:[{data:<?= json_encode(array_values($inv_stCounts)) ?>,backgroundColor:pal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                            invCharts.m = new Chart(mEl,{type:'bar',data:{labels:['Total','Paid','Balance'],datasets:[{data:[<?= $inv_total ?>,<?= $inv_paid ?>,<?= $inv_bal ?>],backgroundColor:['rgba(111,217,67,0.85)','rgba(40,167,69,0.85)','rgba(220,53,69,0.85)'],borderColor:['#6FD943','#28a745','#dc3545'],borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}}}});
                            invCharts.p = new Chart(pEl,{type:'doughnut',data:{labels:<?= json_encode(array_keys($inv_methodCounts)) ?>,datasets:[{data:<?= json_encode(array_values($inv_methodCounts)) ?>,backgroundColor:pal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        }
                        // Defer one tick so Bootstrap finishes activating tabs
                        setTimeout(buildInvCharts, 0);
                        // Re-render whenever the Invoices outer tab or Results inner tab becomes visible
                        const outerTab = document.getElementById('invoices-tab');
                        if (outerTab) outerTab.addEventListener('shown.bs.tab', buildInvCharts);
                        document.querySelectorAll('[data-bs-target="#inv-graph-pane"]').forEach(el=>{
                            el.addEventListener('shown.bs.tab', buildInvCharts);
                        });
                    })();
                    </script>
                    <?php endif; /* invoice_graphs */ ?>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-file-invoice-dollar fa-3x d-block mb-3"></i><h6>No invoice results yet</h6><p>Set your filters and click <strong>Run Invoice Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /inv-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /invoices tab-pane -->

                    <!-- Project Filter Tab -->
                    <div class="tab-pane fade" id="projects" role="tabpanel" aria-labelledby="projects-tab">
                        <ul class="nav report-subtabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($projectResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#proj-filter-pane" type="button" role="tab">
                                    <i class="fas fa-filter"></i> Filters
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($projectResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#proj-graph-pane" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Results &amp; Graphs
                                    <?php if(isset($projectResult)): ?><span class="badge"><?= $projectResult->num_rows ?></span><?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($projectResult)?'show active':'' ?>" id="proj-filter-pane" role="tabpanel">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select class="form-control" name="proj_status">
                                                <option value="">Any</option>
                                                <option value="open">Open</option>
                                                <option value="completed">Completed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Start from</label>
                                            <input type="date" class="form-control" name="proj_from">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Start to</label>
                                            <input type="date" class="form-control" name="proj_to">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="proj_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('proj_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Assigned User</label>
                                            <select class="form-control select2-client" name="proj_user[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
    echo '<option value="' . $u['user_id'] . '"' . msSelected('proj_user', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Service</label>
                                            <select class="form-control select2-client" name="proj_service">
                                                <option value="">All Services</option>
                                                <?php
$selected_proj_service = $_POST['proj_service'] ?? '';
foreach ($services as $svc) {
    $val = $svc['item_description'];
    if ($val === null || $val === '') continue;
    $sel = ($selected_proj_service !== '' && $selected_proj_service === $val) ? ' selected' : '';
    echo '<option value="' . htmlspecialchars($val, ENT_QUOTES) . '"' . $sel . '>' . htmlspecialchars($val) . '</option>';
}
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="project_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Project Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /proj-filter-pane -->
                        <div class="tab-pane fade <?= isset($projectResult)?'show active':'' ?>" id="proj-graph-pane" role="tabpanel">
                    <?php if (isset($projectResult)): ?>
                    <?php
$proj_rows=[]; $projectResult->data_seek(0);
while($pr=$projectResult->fetch_assoc()) $proj_rows[]=$pr;
$proj_stC=[]; $proj_userC=[];
foreach($proj_rows as $pr){
    $s=$pr['project_status']??'Unknown'; $proj_stC[$s]=($proj_stC[$s]??0)+1;
    $u=$pr['assigned_user']?:'Unassigned'; $proj_userC[$u]=($proj_userC[$u]??0)+1;
}
arsort($proj_userC); $proj_userC=array_slice($proj_userC,0,10,true);
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($proj_rows) ?></div><div class="small">Total Projects</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $proj_stC['open']??0 ?></div><div class="small">Open</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $proj_stC['completed']??0 ?></div><div class="small">Completed</div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('project_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> Status Distribution</h5></div><div class="card-body"><canvas id="projStatusChart" style="max-height:210px;"></canvas></div></div></div>
                            <div class="col-md-7"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-user-tie"></i> Projects by User</h5></div><div class="card-body"><canvas id="projUserChart" style="max-height:210px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-project-diagram"></i> Project Results <span class="badge badge-success"><?= count($proj_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="projTable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>Title</th><th>Invoices</th><th>Client</th><th>Assigned</th><th>Start</th><th>Expiry</th><th>Status</th><th>Comments</th></tr></thead><tbody>
                        <?php foreach($proj_rows as $r): $comments=array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',$r['project_comments']??''))); ?>
                        <tr><td><?= $r['order_id'] ?></td><td><?= htmlspecialchars($r['project_title']) ?></td><td><?= htmlspecialchars($r['project_invoices']??'N/A') ?></td>
                        <td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'], $r['client_status'] ?? null) ?></td><td><?= htmlspecialchars($r['assigned_user']?:'—') ?></td>
                        <td><?= $r['start_date'] ?></td><td><?= $r['exp_date'] ?></td>
                        <td><span class="status-indicator <?= $r['project_status']=='open'?'status-active':'status-pending' ?>"></span><?= htmlspecialchars($r['project_status']) ?></td>
                        <td><?php if($comments): ?><ul style="margin:0;padding-left:16px;font-size:.85rem;"><?php foreach($comments as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?></ul><?php else: ?>—<?php endif; ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <?php if ($canSeeGraph('project_graphs')): ?>
                    <script>
                    (function(){
                        const pal=['#6FD943','#ffc107','#17a2b8','#dc3545','#4CAF50','#e0a800'];
                        new Chart(document.getElementById('projStatusChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($proj_stC)) ?>,datasets:[{data:<?= json_encode(array_values($proj_stC)) ?>,backgroundColor:pal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('projUserChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($proj_userC)) ?>,datasets:[{label:'Projects',data:<?= json_encode(array_values($proj_userC)) ?>,backgroundColor:'rgba(111,217,67,0.8)',borderColor:'#6FD943',borderWidth:1}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}}});
                    })();
                    </script>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-project-diagram fa-3x d-block mb-3"></i><h6>No project results yet</h6><p>Set your filters and click <strong>Run Project Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /proj-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /projects tab-pane -->

                    <!-- Quotation Filter Tab -->
                    <div class="tab-pane fade" id="quotations" role="tabpanel" aria-labelledby="quotations-tab">
                        <ul class="nav report-subtabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($quotationResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#quot-filter-pane" type="button" role="tab">
                                    <i class="fas fa-filter"></i> Filters
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($quotationResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#quot-graph-pane" type="button" role="tab">
                                    <i class="fas fa-chart-bar"></i> Results &amp; Graphs
                                    <?php if(isset($quotationResult)): ?><span class="badge"><?= $quotationResult->num_rows ?></span><?php endif; ?>
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($quotationResult)?'show active':'' ?>" id="quot-filter-pane" role="tabpanel">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Status</label>
                                            <select class="form-control" name="quote_status">
                                                <option value="">Any</option>
                                                <option value="pending">Pending</option>
                                                <option value="accepted">Accepted</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date from</label>
                                            <input type="date" class="form-control" name="quote_from">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date to</label>
                                            <input type="date" class="form-control" name="quote_to">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="quote_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('quote_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="quotation_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Quotation Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /quot-filter-pane -->
                        <div class="tab-pane fade <?= isset($quotationResult)?'show active':'' ?>" id="quot-graph-pane" role="tabpanel">
                    <?php if (isset($quotationResult)): ?>
                    <?php
$q_rows=[]; $quotationResult->data_seek(0);
while($qr=$quotationResult->fetch_assoc()) $q_rows[]=$qr;
$q_stC=[]; $q_stTotals=[];
foreach($q_rows as $qr){
    $s=ucfirst($qr['status']??'Unknown');
    $q_stC[$s]=($q_stC[$s]??0)+1;
    $q_stTotals[$s]=($q_stTotals[$s]??0)+floatval($qr['grand_total']);
}
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($q_rows) ?></div><div class="small">Total Quotes</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $q_stC['Pending']??0 ?></div><div class="small">Pending</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $q_stC['Accepted']??0 ?></div><div class="small">Accepted</div></div></div></div>
                            <div class="col-md-3"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#dc3545,#c82333)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= $q_stC['Rejected']??0 ?></div><div class="small">Rejected</div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('quotation_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> Status Distribution</h5></div><div class="card-body"><canvas id="quoteStChart" style="max-height:210px;"></canvas></div></div></div>
                            <div class="col-md-7"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-dollar-sign"></i> Total Value by Status</h5></div><div class="card-body"><canvas id="quoteTotChart" style="max-height:210px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-file-invoice"></i> Quotation Results <span class="badge badge-success"><?= count($q_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="quoteTable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>SR#</th><th>Client</th><th>Date</th><th>Grand Total</th><th>Status</th></tr></thead><tbody>
                        <?php foreach($q_rows as $r): ?>
                        <tr><td><?= $r['quotation_id'] ?></td><td><?= htmlspecialchars($r['quotation_sr']) ?></td><td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'], $r['client_status'] ?? null) ?></td><td><?= $r['q_date'] ?></td>
                        <td>$<?= number_format($r['grand_total'],2) ?></td>
                        <td><span class="status-indicator <?= $r['status']=='accepted'?'status-active':($r['status']=='rejected'?'status-inactive':'status-pending') ?>"></span><?= htmlspecialchars($r['status']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <?php if ($canSeeGraph('quotation_graphs')): ?>
                    <script>
                    (function(){
                        const qPal=['#ffc107','#6FD943','#dc3545','#17a2b8'];
                        new Chart(document.getElementById('quoteStChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($q_stC)) ?>,datasets:[{data:<?= json_encode(array_values($q_stC)) ?>,backgroundColor:qPal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('quoteTotChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($q_stTotals)) ?>,datasets:[{label:'Value ($)',data:<?= json_encode(array_values($q_stTotals)) ?>,backgroundColor:qPal.map(c=>c+'cc'),borderColor:qPal,borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}}}});
                    })();
                    </script>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-file-alt fa-3x d-block mb-3"></i><h6>No quotation results yet</h6><p>Set your filters and click <strong>Run Quotation Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /quot-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /quotations tab-pane -->

                    <!-- Payment Filter Tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
                        <ul class="nav report-subtabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($paymentResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pay-filter-pane" type="button"><i class="fas fa-filter me-1"></i>Filters</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($paymentResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#pay-graph-pane" type="button"><i class="fas fa-chart-bar me-1"></i>Results &amp; Graphs<?php if(isset($paymentResult)): ?> <span class="badge bg-success ms-1"><?= $paymentResult->num_rows ?></span><?php endif; ?></button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($paymentResult)?'show active':'' ?>" id="pay-filter-pane">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date from</label>
                                            <input type="date" class="form-control" name="pay_from">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date to</label>
                                            <input type="date" class="form-control" name="pay_to">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Method</label>
                                            <input type="text" class="form-control" name="pay_method"
                                                placeholder="cash, check, ach …">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="pay_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('pay_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Payment Added By User</label>
                                            <select class="form-control select2-client" name="pay_user[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
    echo '<option value="' . $u['user_id'] . '"' . msSelected('pay_user', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="payment_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Payment Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /pay-filter-pane -->
                        <div class="tab-pane fade <?= isset($paymentResult)?'show active':'' ?>" id="pay-graph-pane">
                    <?php if (isset($paymentResult)): ?>
                    <?php
$pay_rows=[]; $paymentResult->data_seek(0);
while($pay=$paymentResult->fetch_assoc()) $pay_rows[]=$pay;
$pay_mC=[]; $pay_mT=[]; $pay_daily=[]; $pay_totalPaid=0;
foreach($pay_rows as $pay){
    $pay_totalPaid+=floatval($pay['paid']);
    $m=$pay['payment_method']?:'Unknown'; $pay_mC[$m]=($pay_mC[$m]??0)+1; $pay_mT[$m]=($pay_mT[$m]??0)+floatval($pay['paid']);
    $d=isset($pay['date'])?date('Y-m-d',strtotime($pay['date'])):'N/A'; $pay_daily[$d]=($pay_daily[$d]??0)+floatval($pay['paid']);
}
ksort($pay_daily); arsort($pay_mT);
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($pay_rows) ?></div><div class="small">Total Payments</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-5 fw-bold">$<?= number_format($pay_totalPaid,2) ?></div><div class="small">Total Collected</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($pay_mC) ?></div><div class="small">Payment Methods</div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('payment_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> Method Mix</h5></div><div class="card-body"><canvas id="payMixChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-dollar-sign"></i> Revenue by Method</h5></div><div class="card-body"><canvas id="payRevChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-line"></i> Daily Payments Trend</h5></div><div class="card-body"><canvas id="payTrendChart" style="max-height:200px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-money-bill-wave"></i> Payment Results <span class="badge badge-success"><?= count($pay_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="payTable" class="table table-striped table-hover"><thead><tr><th>Invoice ID</th><th>Client</th><th>Date</th><th>Paid $</th><th>Balance $</th><th>Method</th><th>Description</th><th>Added By</th></tr></thead><tbody>
                        <?php foreach($pay_rows as $r): ?>
                        <tr><td><?= $r['inv_id'] ?></td><td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'], $r['client_status'] ?? null) ?></td>
                        <td><?= isset($r['date'])?date('m/d/Y',strtotime($r['date'])):'-' ?></td>
                        <td>$<?= number_format($r['paid'],2) ?></td><td>$<?= number_format($r['balance'],2) ?></td>
                        <td><?= htmlspecialchars($r['payment_method']??'-') ?></td><td><?= htmlspecialchars($r['detail']) ?></td>
                        <td><?= htmlspecialchars($r['payment_added_by']?:'-') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <script>
                    (function(){
                        <?php if ($canSeeGraph('payment_graphs')): ?>
                        const pPal=['#6FD943','#17a2b8','#ffc107','#dc3545','#4CAF50','#138496','#e0a800','#c82333'];
                        new Chart(document.getElementById('payMixChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($pay_mC)) ?>,datasets:[{data:<?= json_encode(array_values($pay_mC)) ?>,backgroundColor:pPal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('payRevChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($pay_mT)) ?>,datasets:[{label:'Paid',data:<?= json_encode(array_values($pay_mT)) ?>,backgroundColor:'rgba(111,217,67,0.8)',borderColor:'#6FD943',borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}}}});
                        new Chart(document.getElementById('payTrendChart'),{type:'line',data:{labels:<?= json_encode(array_keys($pay_daily)) ?>,datasets:[{label:'Paid',data:<?= json_encode(array_values($pay_daily)) ?>,borderColor:'#6FD943',backgroundColor:'rgba(111,217,67,0.15)',fill:true,tension:0.4,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}}}});
                        <?php endif; ?>
                    })();
                    </script>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-credit-card fa-3x d-block mb-3"></i><h6>No payment results yet</h6><p>Set your filters and click <strong>Run Payment Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /pay-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /payments tab-pane -->

                    <!-- Comment Filter Tab -->
                    <div class="tab-pane fade" id="comments" role="tabpanel" aria-labelledby="comments-tab">
                        <ul class="nav report-subtabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($commentResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#comm-filter-pane" type="button"><i class="fas fa-filter me-1"></i>Filters</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($commentResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#comm-graph-pane" type="button"><i class="fas fa-chart-bar me-1"></i>Results &amp; Graphs<?php if(isset($commentResult)): ?> <span class="badge bg-success ms-1"><?= $commentResult->num_rows ?></span><?php endif; ?></button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($commentResult)?'show active':'' ?>" id="comm-filter-pane">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Keyword</label>
                                            <input type="text" class="form-control" name="comm_kw"
                                                placeholder="search text">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Type</label>
                                            <select class="form-control" name="comm_type">
                                                <option value="">All comments</option>
                                                <option value="general">General</option>
                                                <option value="invoice">Invoice</option>
                                                <option value="project">Project</option>
                                                <option value="alert">Alert</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="comm_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('comm_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Comment By User</label>
                                            <select class="form-control select2-client" name="comm_user[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
    echo '<option value="' . $u['user_id'] . '"' . msSelected('comm_user', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Start Date</label>
                                            <input type="date" class="form-control" name="comm_from">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>End Date</label>
                                            <input type="date" class="form-control" name="comm_to">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="comment_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Comment Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /comm-filter-pane -->
                        <div class="tab-pane fade <?= isset($commentResult)?'show active':'' ?>" id="comm-graph-pane">
                    <?php if (isset($commentResult)): ?>
                    <?php
$comm_rows=[]; $commentResult->data_seek(0);
while($cr=$commentResult->fetch_assoc()) $comm_rows[]=$cr;
$comm_srcC=[]; $comm_userC=[];
foreach($comm_rows as $cr){
    $s=ucfirst($cr['src']??'unknown'); $comm_srcC[$s]=($comm_srcC[$s]??0)+1;
    $u=$cr['commented_by']?:'Unknown'; $comm_userC[$u]=($comm_userC[$u]??0)+1;
}
arsort($comm_userC); $comm_userC=array_slice($comm_userC,0,10,true);
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($comm_rows) ?></div><div class="small">Total Comments</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($comm_srcC) ?></div><div class="small">Comment Types</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($comm_userC) ?></div><div class="small">Users Involved</div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('comment_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> By Source</h5></div><div class="card-body"><canvas id="commSrcChart" style="max-height:210px;"></canvas></div></div></div>
                            <div class="col-md-7"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-users"></i> Comments by User</h5></div><div class="card-body"><canvas id="commUserChart" style="height:<?= max(210, count($comm_userC) * 32) ?>px;max-height:<?= max(210, count($comm_userC) * 32) ?>px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-comments"></i> Comment Results <span class="badge badge-success"><?= count($comm_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="commTable" class="table table-striped table-hover"><thead><tr><th>Type</th><th>Client</th><th>Business</th><th>Date</th><th>Comment</th><th>By</th></tr></thead><tbody>
                        <?php foreach($comm_rows as $r): $client=''; $busName=''; $clientStatus=null; $res=$con->query("SELECT CONCAT(name,' ',lname) AS nm, bus_name, status FROM members WHERE member_id=".(int)$r['member_id']); if($res&&($row=$res->fetch_assoc())){$client=$row['nm'];$busName=$row['bus_name']??'';$clientStatus=$row['status'];} ?>
                        <tr><td><span class="badge bg-<?= $r['src']=='general'?'primary':($r['src']=='invoice'?'info':($r['src']=='project'?'success':'warning')) ?>"><?= ucfirst($r['src']) ?></span></td>
                        <td><?= renderClientLink($r['member_id'] ?? 0, $client, $clientStatus, $busName) ?></td><td><?= htmlspecialchars($busName) ?></td>
                        <td><?= date('m/d/Y',strtotime($r['dt'])) ?></td>
                        <td><?= strip_tags($r['txt'],'<p><br>') ?></td>
                        <td><?= htmlspecialchars($r['commented_by']??'') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <?php if ($canSeeGraph('comment_graphs')): ?>
                    <script>
                    (function(){
                        const cPal=['#17a2b8','#6FD943','#ffc107','#dc3545'];
                        new Chart(document.getElementById('commSrcChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($comm_srcC)) ?>,datasets:[{data:<?= json_encode(array_values($comm_srcC)) ?>,backgroundColor:cPal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('commUserChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($comm_userC)) ?>,datasets:[{label:'Comments',data:<?= json_encode(array_values($comm_userC)) ?>,backgroundColor:'rgba(23,162,184,0.8)',borderColor:'#17a2b8',borderWidth:1}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}},y:{ticks:{autoSkip:false}}}}});
                    })();
                    </script>
                    <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-comments fa-3x d-block mb-3"></i><h6>No comment results yet</h6><p>Set your filters and click <strong>Run Comment Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /comm-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /comments tab-pane -->

                    <!-- Reminder Filter Tab -->
                    <div class="tab-pane fade" id="reminders" role="tabpanel" aria-labelledby="reminders-tab">
                        <ul class="nav report-subtabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !isset($reminderResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#rem-filter-pane" type="button"><i class="fas fa-filter me-1"></i>Filters</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($reminderResult)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#rem-graph-pane" type="button"><i class="fas fa-chart-bar me-1"></i>Results &amp; Graphs<?php if(isset($reminderResult)): ?> <span class="badge bg-success ms-1"><?= $reminderResult->num_rows ?></span><?php endif; ?></button>
                            </li>
                        </ul>
                        <div class="tab-content">
                        <div class="tab-pane fade <?= !isset($reminderResult)?'show active':'' ?>" id="rem-filter-pane">
                        <div class="card filter-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Type</label>
                                            <select class="form-control" name="rem_type">
                                                <option value="">Any</option>
                                                <option value="Email for Unpaid Invoice">Email for Unpaid Invoice</option>
                                                <option value="Email for Paid Invoice">Email for Paid Invoice</option>
                                                <option value="SMS for Unpaid Invoice">SMS for Unpaid Invoice</option>
                                                <option value="SMS for Paid Invoice">SMS for Paid Invoice</option>
                                                <option value="Email for Unpaid Invoice History">Email for Unpaid Invoice History</option>
                                                <option value="Email for Paid Invoice History">Email for Paid Invoice History</option>
                                                <option value="whatsapp">WhatsApp</option>
                                                <option value="general">General</option>
                                                <option value="invoice_reminder">Legacy: Invoice reminder</option>
                                                <option value="SMS">Legacy: SMS</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date from</label>
                                            <input type="date" class="form-control" name="rem_from">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date to</label>
                                            <input type="date" class="form-control" name="rem_to">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="rem_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
$label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
echo '<option value="' . $c['member_id'] . '"' . msSelected('rem_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Reminder Sent By User</label>
                                            <select class="form-control select2-client" name="rem_sent_by_user[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
    echo '<option value="' . $u['user_id'] . '"' . msSelected('rem_sent_by_user', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Product / Membership</label>
                                            <select class="form-control select2-client" name="rem_product">
                                                <option value="">Any</option>
                                                <?php
$ms = $con->query("SELECT membership_name FROM memberships ORDER BY membership_name");
while ($m = $ms->fetch_assoc()) {
    echo '<option>' . htmlspecialchars($m['membership_name']) . '</option>';
}
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client Type</label>
                                            <select class="form-control" name="rem_client_type">
                                                <option value="">All Types</option>
                                                <?php foreach ($memberTypes as $t)
                                                    echo '<option value="' . $t['type_id'] . '">' . htmlspecialchars($t['type_name']) . '</option>'; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="reminder_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Reminder Report
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div><!-- /rem-filter-pane -->
                        <div class="tab-pane fade <?= isset($reminderResult)?'show active':'' ?>" id="rem-graph-pane">
                    <?php if (isset($reminderResult)): ?>
                    <?php
$rem_rows=[]; $reminderResult->data_seek(0);
while($rr=$reminderResult->fetch_assoc()) $rem_rows[]=$rr;
$rem_typeC=[]; $rem_userC=[];
foreach($rem_rows as $rr){
    $t=$rr['type']??'general'; $rem_typeC[$t]=($rem_typeC[$t]??0)+1;
    $u=$rr['sent_by_name']??($rr['sent_by']?:'Unknown'); $rem_userC[$u]=($rem_userC[$u]??0)+1;
}
arsort($rem_userC); $rem_userC=array_slice($rem_userC,0,10,true);
// daily trend
$rem_daily=[];
foreach($rem_rows as $rr){ $d=isset($rr['sent_at'])?date('Y-m-d',strtotime($rr['sent_at'])):'N/A'; $rem_daily[$d]=($rem_daily[$d]??0)+1; }
ksort($rem_daily);
                    ?>
                    <div class="mt-4 fade-in">
                        <!-- KPI -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#6FD943,#4CAF50)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($rem_rows) ?></div><div class="small">Total Reminders</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#17a2b8,#138496)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($rem_typeC) ?></div><div class="small">Reminder Types</div></div></div></div>
                            <div class="col-md-4"><div class="card border-0 text-white" style="background:linear-gradient(135deg,#ffc107,#e0a800)"><div class="card-body text-center"><div class="fs-4 fw-bold"><?= count($rem_userC) ?></div><div class="small">Users Sending</div></div></div></div>
                        </div>
                        <!-- Charts -->
                        <?php if ($canSeeGraph('reminder_graphs')): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-3"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-pie"></i> By Type</h5></div><div class="card-body"><canvas id="remTypeChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-4"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-users"></i> By User</h5></div><div class="card-body"><canvas id="remUserChart" style="max-height:200px;"></canvas></div></div></div>
                            <div class="col-md-5"><div class="card h-100"><div class="card-header"><h5><i class="fas fa-chart-line"></i> Daily Trend</h5></div><div class="card-body"><canvas id="remTrendChart" style="max-height:200px;"></canvas></div></div></div>
                        </div>
                        <?php endif; ?>
                        <!-- Table -->
                        <div class="card"><div class="card-header"><h4><i class="fas fa-bell"></i> Reminder Results <span class="badge badge-success"><?= count($rem_rows) ?></span></h4></div><div class="card-body">
                        <div class="table-responsive"><table id="remTable" class="table table-striped table-hover"><thead><tr><th>ID</th><th>Client ID</th><th>Client / Business</th><th>Subject</th><th>Invoice Amount</th><th>Service Name</th><th>To</th><th>From</th><th>Sent By</th><th>Sent At</th></tr></thead><tbody>
                        <?php foreach($rem_rows as $r): ?>
                        <tr><td><?= $r['id'] ?></td><td><?= $r['client_id'] ?></td>
                        <td><?= renderClientLink($r['client_member_id'] ?? $r['client_id'] ?? 0, $r['client'] ?? '', $r['client_status'] ?? null, $r['business_name'] ?? '') ?><?php if(!empty($r['business_name'])): ?><br><small class="text-muted"><?= htmlspecialchars($r['business_name']) ?></small><?php endif; ?></td>
                        <td><?= htmlspecialchars($r['subject']??'') ?></td>
                        <td><?= isset($r['invoice_amount']) && $r['invoice_amount']!==null ? '$'.number_format((float)$r['invoice_amount'],2) : '-' ?></td>
                        <td>
                        <?php
$svc = $r['service_name'] ?? '';
if ($svc === '' || $svc === null) {
    echo '-';
} else {
    $items = array_values(array_filter(array_map('trim', explode(',', $svc)), fn($v) => $v !== ''));
    $previewCount  = 2;
    $maxCharsPrev  = 60;
    $full          = implode(', ', $items);
    $needsToggle   = (count($items) > $previewCount) || (mb_strlen($full) > $maxCharsPrev);
    if (!$needsToggle) {
        echo htmlspecialchars($full);
    } else {
        $previewItems = array_slice($items, 0, $previewCount);
        $previewText  = implode(', ', $previewItems);
        if (mb_strlen($previewText) > $maxCharsPrev) {
            $previewText = mb_substr($previewText, 0, $maxCharsPrev) . '…';
        }
        $hiddenCount = count($items) - count($previewItems);
        $uid = 'svc_' . $r['id'];
                                ?>
                                    <span class="svc-wrap" style="display:inline-block;max-width:240px;vertical-align:top;">
                                        <span id="<?= $uid ?>_short"><?= htmlspecialchars($previewText) ?> <a href="javascript:void(0)" class="svc-toggle" data-target="<?= $uid ?>" style="white-space:nowrap;">see more (+<?= $hiddenCount ?>)</a></span>
                                        <span id="<?= $uid ?>_full" style="display:none;"><?= htmlspecialchars($full) ?> <a href="javascript:void(0)" class="svc-toggle" data-target="<?= $uid ?>" style="white-space:nowrap;">see less</a></span>
                                    </span>
                                <?php
    }
}
                        ?>
                        </td>
                        <td><?= htmlspecialchars($r['To_mail']??'') ?></td>
                        <td><?= htmlspecialchars($r['from_mail']??'') ?></td><td><?= htmlspecialchars($r['sent_by_name']??$r['sent_by']??'-') ?></td>
                        <td><?= date('m/d/Y H:i',strtotime($r['sent_at'])) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                        </div></div>
                    </div>
                    <script>
                    (function(){
                        <?php if ($canSeeGraph('reminder_graphs')): ?>
                        const rPal=['#6FD943','#17a2b8','#ffc107','#dc3545','#4CAF50'];
                        new Chart(document.getElementById('remTypeChart'),{type:'doughnut',data:{labels:<?= json_encode(array_keys($rem_typeC)) ?>,datasets:[{data:<?= json_encode(array_values($rem_typeC)) ?>,backgroundColor:rPal,borderWidth:2,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
                        new Chart(document.getElementById('remUserChart'),{type:'bar',data:{labels:<?= json_encode(array_keys($rem_userC)) ?>,datasets:[{label:'Sent',data:<?= json_encode(array_values($rem_userC)) ?>,backgroundColor:'rgba(111,217,67,0.8)',borderColor:'#6FD943',borderWidth:1}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{stepSize:1}}}}});
                        new Chart(document.getElementById('remTrendChart'),{type:'line',data:{labels:<?= json_encode(array_keys($rem_daily)) ?>,datasets:[{label:'Reminders',data:<?= json_encode(array_values($rem_daily)) ?>,borderColor:'#6FD943',backgroundColor:'rgba(111,217,67,0.15)',fill:true,tension:0.4,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}});
                        <?php endif; ?>

                        // Service Name "see more / see less" toggle
                        document.querySelectorAll('#remTable .svc-toggle').forEach(function(btn){
                            btn.addEventListener('click', function(e){
                                e.preventDefault();
                                var id = this.getAttribute('data-target');
                                var s = document.getElementById(id + '_short');
                                var f = document.getElementById(id + '_full');
                                if (!s || !f) return;
                                var showFull = s.style.display !== 'none';
                                s.style.display = showFull ? 'none' : '';
                                f.style.display = showFull ? '' : 'none';
                            });
                        });
                    })();
                    </script>
                    <?php else: ?>
                        <div class="empty-graph-state"><i class="fas fa-bell fa-3x d-block mb-3"></i><h6>No reminder results yet</h6><p>Set your filters and click <strong>Run Reminder Report</strong> to see charts &amp; data here.</p></div>
                    <?php endif; ?>
                        </div><!-- /rem-graph-pane -->
                        </div><!-- /tab-content -->
                    </div><!-- /reminders tab-pane -->

                    <!-- ===================== MEMBER SERVICES TAB ===================== -->
                    <div class="tab-pane fade" id="memberServices" role="tabpanel" aria-labelledby="memberServices-tab">
                        <div class="card filter-card">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="fas fa-briefcase"></i> Member Services &mdash; clients by service</h5>
                                <div class="row">
                                    <div class="col-md-9">
                                        <div class="form-group mb-0">
                                            <label>Client/Member service filter</label>
                                            <select class="form-control select2-client" name="ms_service[]" multiple data-placeholder="All services (select one or more)">
                                                <?php foreach ($msServiceOptions as $s):
                                                    $sel = in_array((int)$s['id'], $ms_selected_ids, true) ? 'selected' : ''; ?>
                                                    <option value="<?= (int)$s['id'] ?>" <?= $sel ?>><?= htmlspecialchars($s['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Select one or more services, or <a href="javascript:void(0)" id="msSelectAll">select all</a> / <a href="javascript:void(0)" id="msClearAll">clear</a>. Leave empty to include all.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group mb-0">
                                            <label class="d-none d-md-block">&nbsp;</label>
                                            <button type="submit" name="action" value="member_services_filter" class="btn btn-primary btn-run w-100">
                                                <i class="fas fa-search"></i> Run
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($memberServicesResult)):
                            $ms_rows = [];
                            if ($memberServicesResult) { while ($mr = $memberServicesResult->fetch_assoc()) $ms_rows[] = $mr; }
                            $ms_clientCount = count(array_unique(array_map(fn($r) => (int)$r['member_id'], $ms_rows)));
                            $ms_label = '';
                            if ($ms_selected_ids) {
                                $ms_names = [];
                                foreach ($msServiceOptions as $s) { if (in_array((int)$s['id'], $ms_selected_ids, true)) $ms_names[] = $s['name']; }
                                $ms_label = implode(', ', $ms_names);
                            }
                        ?>
                        <div class="card mt-3 fade-in">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                                <h4 class="mb-0"><i class="fas fa-briefcase"></i> Member Services Results <span class="badge badge-success"><?= count($ms_rows) ?></span></h4>
                                <span class="text-muted small">
                                    <?= $ms_selected_ids
                                        ? ('Showing ' . $ms_clientCount . ' client' . ($ms_clientCount === 1 ? '' : 's') . ' with ' . htmlspecialchars($ms_label) . '.')
                                        : ('Showing all ' . $ms_clientCount . ' clients across ' . count($ms_rows) . ' service records.') ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="memberServicesTable" class="table table-striped table-hover">
                                        <thead><tr><th>Member ID</th><th>Client</th><th>Business</th><th>Status</th><th>Service</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($ms_rows as $r): ?>
                                            <tr>
                                                <td><?= (int)$r['member_id'] ?></td>
                                                <td><?= renderClientLink($r['member_id'] ?? 0, $r['client'] ?? '', $r['client_status'] ?? null, $r['business_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['business_name'] ?? '') ?></td>
                                                <td><?= ((int)($r['client_status'] ?? 0) === 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                                <td><?= htmlspecialchars($r['membership_name'] ?? '') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- /memberServices tab-pane -->

                    <!-- ===================== ASSOCIATED SERVICES TAB ===================== -->
                    <div class="tab-pane fade" id="associatedServices" role="tabpanel" aria-labelledby="associatedServices-tab">
                        <div class="card filter-card">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="fas fa-tags"></i> Associated Services &mdash; client products / orders</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="as_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
                                                    $label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
                                                    echo '<option value="' . $c['member_id'] . '"' . msSelected('as_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Service / Product</label>
                                            <select class="form-control select2-client" name="as_service">
                                                <option value="">All Services</option>
                                                <?php foreach ($asMembershipOptions as $s):
                                                    $sel = (($_POST['as_service'] ?? '') == $s['membership_id']) ? ' selected' : '';
                                                    echo '<option value="' . (int) $s['membership_id'] . '"' . $sel . '>' . htmlspecialchars($s['membership_name']) . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Service Status</label>
                                            <?php $as_st = $_POST['as_status'] ?? ''; ?>
                                            <select class="form-control" name="as_status">
                                                <option value="" <?= $as_st === '' ? 'selected' : '' ?>>Any</option>
                                                <option value="1" <?= $as_st === '1' ? 'selected' : '' ?>>Active</option>
                                                <option value="-1" <?= $as_st === '-1' ? 'selected' : '' ?>>Done</option>
                                                <option value="0" <?= $as_st === '0' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Assigned User</label>
                                            <select class="form-control select2-client" name="as_assigned[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
                                                    echo '<option value="' . (int) $u['user_id'] . '"' . msSelected('as_assigned', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Client Status</label>
                                            <?php $as_cs = $_POST['as_client_status'] ?? ''; ?>
                                            <select class="form-control" name="as_client_status">
                                                <option value="" <?= $as_cs === '' ? 'selected' : '' ?>>All</option>
                                                <option value="1" <?= $as_cs === '1' ? 'selected' : '' ?>>Active</option>
                                                <option value="0" <?= $as_cs === '0' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Start date from</label>
                                            <input type="date" class="form-control" name="as_from" value="<?= htmlspecialchars($_POST['as_from'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Start date to</label>
                                            <input type="date" class="form-control" name="as_to" value="<?= htmlspecialchars($_POST['as_to'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="associated_services_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Associated Services Report
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($associatedServicesResult)):
                            $as_rows = [];
                            if ($associatedServicesResult) { while ($ar = $associatedServicesResult->fetch_assoc()) $as_rows[] = $ar; }
                            $as_clientCount = count(array_unique(array_map(fn($r) => (int) $r['mem_id'], $as_rows)));
                        ?>
                        <div class="card mt-3 fade-in">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                                <h4 class="mb-0"><i class="fas fa-tags"></i> Associated Services Results <span class="badge badge-success"><?= count($as_rows) ?></span></h4>
                                <span class="text-muted small">Showing <?= count($as_rows) ?> service record<?= count($as_rows) === 1 ? '' : 's' ?> across <?= $as_clientCount ?> client<?= $as_clientCount === 1 ? '' : 's' ?>.</span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="associatedServicesTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Member ID</th><th>Client</th><th>Business</th><th>Service</th>
                                                <th>Source</th><th>Status</th><th>Cycle</th><th>Start Date</th><th>End Date</th>
                                                <th>Renewal Date</th><th>Due Date</th><th>Inserted By</th><th>Assigned To</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($as_rows as $r):
                                            $os = (int) ($r['order_status'] ?? 0);
                                            if ($os === 1)        $stBadge = '<span class="badge bg-success">Active</span>';
                                            elseif ($os === -1)   $stBadge = '<span class="badge bg-info">Done</span>';
                                            else                  $stBadge = '<span class="badge bg-secondary">Inactive</span>';
                                            $cyc = (int) ($r['bil_cyc_months'] ?? 0);
                                            $isRenewed = ($r['source'] ?? '') === 'Auto-Renewed';
                                            $srcBadge  = $isRenewed
                                                ? '<span class="badge bg-warning text-dark">Auto-Renewed</span>'
                                                : '<span class="badge bg-light text-dark border">Original</span>';
                                        ?>
                                            <tr>
                                                <td><?= (int) $r['mem_id'] ?></td>
                                                <td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'] ?? '', $r['client_status'] ?? null, $r['business_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['business_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['membership_name'] ?? 'N/A') ?></td>
                                                <td><?= $srcBadge ?></td>
                                                <td><?= $stBadge ?></td>
                                                <td><?= $cyc ?> month<?= $cyc === 1 ? '' : 's' ?></td>
                                                <td><?= !empty($r['start_date']) ? date('M d, Y', strtotime($r['start_date'])) : '—' ?></td>
                                                <td><?= ($os === -1) ? 'Completed' : (!empty($r['exp_date']) ? date('M d, Y', strtotime($r['exp_date'])) : '—') ?></td>
                                                <td><?= !empty($r['renewal_date']) ? date('M d, Y', strtotime($r['renewal_date'])) : '—' ?></td>
                                                <td><?= !empty($r['due_date']) ? date('M d, Y', strtotime($r['due_date'])) : '—' ?></td>
                                                <td><?= htmlspecialchars($r['inserted_by'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($r['assigned_to_name'] ?? '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- /associatedServices tab-pane -->

                    <!-- ===================== PRODUCT RENEWALS TAB ===================== -->
                    <div class="tab-pane fade" id="productRenewals" role="tabpanel" aria-labelledby="productRenewals-tab">
                        <div class="card filter-card">
                            <div class="card-body">
                                <h5 class="mb-3"><i class="fas fa-sync-alt"></i> Product Renewals &mdash; expiries, days left &amp; urgency</h5>
                                
                                <?php
                                    $pr_bucketSel = array_map('strval', (array) ($_POST['pr_bucket'] ?? []));
                                    $pr_isBucket  = fn($b) => in_array($b, $pr_bucketSel, true) ? 'checked' : '';
                                    $pr_status    = $_POST['pr_status'] ?? '';
                                    $pr_cs        = $_POST['pr_client_status'] ?? '';
                                    $pr_df        = ($_POST['pr_date_field'] ?? 'exp_date');
                                ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Service / Product</label>
                                            <select class="form-control select2-client" name="pr_service[]" multiple data-placeholder="All services (select one or more)">
                                                <?php foreach ($asMembershipOptions as $s):
                                                    echo '<option value="' . (int) $s['membership_id'] . '"' . msSelected('pr_service', $s['membership_id']) . '>' . htmlspecialchars($s['membership_name']) . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Client</label>
                                            <select class="form-control select2-client" name="pr_client[]" multiple data-placeholder="All clients (select one or more)">
                                                <?php foreach ($clientNames as $c):
                                                    $label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
                                                    echo '<option value="' . $c['member_id'] . '"' . msSelected('pr_client', $c['member_id']) . '>' . $label . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Assigned User</label>
                                            <select class="form-control select2-client" name="pr_assigned[]" multiple data-placeholder="All users (select one or more)">
                                                <?php foreach ($users as $u):
                                                    echo '<option value="' . (int) $u['user_id'] . '"' . msSelected('pr_assigned', $u['user_id']) . '>' . htmlspecialchars($u['user_name']) . '</option>';
                                                endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Date field</label>
                                            <select class="form-control" name="pr_date_field">
                                                <option value="exp_date" <?= $pr_df === 'exp_date' ? 'selected' : '' ?>>Expiry date</option>
                                                <option value="start_date" <?= $pr_df === 'start_date' ? 'selected' : '' ?>>Start date</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>From</label>
                                            <input type="date" class="form-control" name="pr_from" value="<?= htmlspecialchars($_POST['pr_from'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>To</label>
                                            <input type="date" class="form-control" name="pr_to" value="<?= htmlspecialchars($_POST['pr_to'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Days left &le;</label>
                                            <input type="number" class="form-control" name="pr_days_left" min="0" step="1"
                                                   placeholder="e.g. 30" value="<?= htmlspecialchars($_POST['pr_days_left'] ?? '') ?>">
                                            
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Service Status</label>
                                            <select class="form-control" name="pr_status">
                                                <option value="" <?= $pr_status === '' ? 'selected' : '' ?>>Any</option>
                                                <option value="1" <?= $pr_status === '1' ? 'selected' : '' ?>>Active</option>
                                                <option value="-1" <?= $pr_status === '-1' ? 'selected' : '' ?>>Done</option>
                                                <option value="0" <?= $pr_status === '0' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Client Status</label>
                                            <select class="form-control" name="pr_client_status">
                                                <option value="" <?= $pr_cs === '' ? 'selected' : '' ?>>All</option>
                                                <option value="1" <?= $pr_cs === '1' ? 'selected' : '' ?>>Active</option>
                                                <option value="0" <?= $pr_cs === '0' ? 'selected' : '' ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Urgency</label>
                                            <div class="d-flex flex-wrap align-items-center" style="gap:1rem; padding-top:.35rem;">
                                                <div class="form-check"><input class="form-check-input" type="checkbox" name="pr_bucket[]" value="expired"   id="pr_b_exp" <?= $pr_isBucket('expired') ?>><label class="form-check-label" for="pr_b_exp"><span class="badge bg-danger">Expired</span></label></div>
                                                <div class="form-check"><input class="form-check-input" type="checkbox" name="pr_bucket[]" value="urgent"    id="pr_b_urg" <?= $pr_isBucket('urgent') ?>><label class="form-check-label" for="pr_b_urg"><span class="badge bg-warning text-dark">Urgent (&le;7d)</span></label></div>
                                                <div class="form-check"><input class="form-check-input" type="checkbox" name="pr_bucket[]" value="upcoming"  id="pr_b_upc" <?= $pr_isBucket('upcoming') ?>><label class="form-check-label" for="pr_b_upc"><span class="badge bg-info text-dark">Upcoming (&le;30d)</span></label></div>
                                                <div class="form-check"><input class="form-check-input" type="checkbox" name="pr_bucket[]" value="scheduled" id="pr_b_sch" <?= $pr_isBucket('scheduled') ?>><label class="form-check-label" for="pr_b_sch"><span class="badge bg-success">Scheduled (&gt;30d)</span></label></div>
                                            </div>
                                            
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                                    <button type="submit" name="action" value="product_renewals_filter" class="btn btn-primary btn-run">
                                        <i class="fas fa-search"></i> Run Product Renewals Report
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if (isset($productRenewalsResult)):
                            $pr_rows = [];
                            if ($productRenewalsResult) { while ($pr = $productRenewalsResult->fetch_assoc()) $pr_rows[] = $pr; }
                            $pr_clientCount = count(array_unique(array_map(fn($r) => (int) $r['mem_id'], $pr_rows)));
                            /* tally urgency buckets for the header summary */
                            $pr_tally = ['Expired' => 0, 'Urgent' => 0, 'Upcoming' => 0, 'Scheduled' => 0, 'No Expiry' => 0];
                            foreach ($pr_rows as $r) {
                                $dl = $r['days_left'];
                                if ($dl === null || $dl === '')      $pr_tally['No Expiry']++;
                                elseif ((int) $dl < 0)               $pr_tally['Expired']++;
                                elseif ((int) $dl <= 7)              $pr_tally['Urgent']++;
                                elseif ((int) $dl <= 30)             $pr_tally['Upcoming']++;
                                else                                 $pr_tally['Scheduled']++;
                            }
                        ?>
                        <div class="card mt-3 fade-in">
                            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                                <h4 class="mb-0"><i class="fas fa-sync-alt"></i> Product Renewals Results <span class="badge badge-success"><?= count($pr_rows) ?></span></h4>
                                <span class="text-muted small">
                                    <?= count($pr_rows) ?> renewal record<?= count($pr_rows) === 1 ? '' : 's' ?> across <?= $pr_clientCount ?> client<?= $pr_clientCount === 1 ? '' : 's' ?> &middot;
                                    <span class="badge bg-danger"><?= $pr_tally['Expired'] ?> expired</span>
                                    <span class="badge bg-warning text-dark"><?= $pr_tally['Urgent'] ?> urgent</span>
                                    <span class="badge bg-info text-dark"><?= $pr_tally['Upcoming'] ?> upcoming</span>
                                    <span class="badge bg-success"><?= $pr_tally['Scheduled'] ?> scheduled</span>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="productRenewalsTable" class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Member ID</th><th>Client</th><th>Business</th><th>Member Type</th><th>Service</th>
                                                <th>Source</th><th>Cycle</th><th>Start Date</th><th>Expiry Date</th>
                                                <th>Days Left</th><th>Urgency</th><th>Service Status</th><th>Renewal Date</th><th>Assigned To</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pr_rows as $r):
                                            $dl = ($r['days_left'] === null || $r['days_left'] === '') ? null : (int) $r['days_left'];
                                            if ($dl === null)        { $urg = 'No Expiry';  $uCls = 'bg-secondary'; }
                                            elseif ($dl < 0)         { $urg = 'Expired';    $uCls = 'bg-danger'; }
                                            elseif ($dl <= 7)        { $urg = 'Urgent';     $uCls = 'bg-warning text-dark'; }
                                            elseif ($dl <= 30)       { $urg = 'Upcoming';   $uCls = 'bg-info text-dark'; }
                                            else                     { $urg = 'Scheduled'; $uCls = 'bg-success'; }

                                            if ($dl === null)        $dlTxt = '—';
                                            elseif ($dl < 0)         $dlTxt = abs($dl) . ' day' . (abs($dl) === 1 ? '' : 's') . ' ago';
                                            elseif ($dl === 0)       $dlTxt = 'Due today';
                                            else                     $dlTxt = $dl . ' day' . ($dl === 1 ? '' : 's') . ' left';

                                            $os = (int) ($r['order_status'] ?? 0);
                                            if ($os === 1)        $stBadge = '<span class="badge bg-success">Active</span>';
                                            elseif ($os === -1)   $stBadge = '<span class="badge bg-info text-dark">Done</span>';
                                            else                  $stBadge = '<span class="badge bg-secondary">Inactive</span>';
                                            $cyc = (int) ($r['bil_cyc_months'] ?? 0);
                                            $isRenewed = ($r['source'] ?? '') === 'Auto-Renewed';
                                            $srcBadge  = $isRenewed
                                                ? '<span class="badge bg-warning text-dark">Auto-Renewed</span>'
                                                : '<span class="badge bg-light text-dark border">Original</span>';
                                        ?>
                                            <tr>
                                                <td><?= (int) $r['mem_id'] ?></td>
                                                <td><?= renderClientLink($r['client_member_id'] ?? 0, $r['client'] ?? '', $r['client_status'] ?? null, $r['business_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['business_name'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($r['type_name'] ?? '—') ?></td>
                                                <td><?= htmlspecialchars($r['membership_name'] ?? 'N/A') ?></td>
                                                <td><?= $srcBadge ?></td>
                                                <td><?= $cyc ?> month<?= $cyc === 1 ? '' : 's' ?></td>
                                                <td><?= !empty($r['start_date']) ? date('M d, Y', strtotime($r['start_date'])) : '—' ?></td>
                                                <td><?= !empty($r['exp_date']) ? date('M d, Y', strtotime($r['exp_date'])) : '—' ?></td>
                                                <td data-order="<?= $dl === null ? 999999 : $dl ?>"><?= $dlTxt ?></td>
                                                <td><span class="badge <?= $uCls ?>"><?= $urg ?></span></td>
                                                <td><?= $stBadge ?></td>
                                                <td><?= !empty($r['renewal_date']) ? date('M d, Y', strtotime($r['renewal_date'])) : '—' ?></td>
                                                <td><?= htmlspecialchars($r['assigned_to_name'] ?? '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- /productRenewals tab-pane -->

                    <!-- ===================== MEMBER 360 TAB ===================== -->
                    <div class="tab-pane fade" id="member360" role="tabpanel" aria-labelledby="member360-tab">
                        <ul class="nav report-subtabs mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= empty($member360)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#m360-filter-pane" type="button" role="tab">
                                    <i class="fas fa-filter me-1"></i> Select Member
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= !empty($member360)?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#m360-result-pane" type="button" role="tab">
                                    <i class="fas fa-user-shield me-1"></i> 360° View
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content">

                            <!-- Filter Pane -->
                            <div class="tab-pane fade <?= empty($member360)?'show active':'' ?>" id="m360-filter-pane" role="tabpanel">
                                <div class="card filter-card">
                                    <div class="card-body">
                                        <h5 class="mb-3"><i class="fas fa-id-card"></i> Member 360 &mdash; everything for one member in one place</h5>
                                        <p class="text-muted small mb-3">Pulls profile, business records, spouse, invoices &amp; line items, orders, services, projects, quotations, comments, notes and email/SMS history for the selected member.</p>
                                        <div class="row">
                                            <div class="col-md-9">
                                                <div class="form-group">
                                                    <label>Member</label>
                                                    <select class="form-control select2-client" name="m360_member_id" id="m360_member_id">
                                                        <option value="">-- Select a member --</option>
                                                        <?php foreach ($clientNames as $c):
                                                            $label = $c['member_id'] . ' - ' . htmlspecialchars($c['full_name']) . ' | Bus: ' . htmlspecialchars($c['bus_name'] ?? 'N/A');
                                                            $sel = (!empty($member360) && (int)$member360['member_id'] === (int)$c['member_id']) ? 'selected' : '';
                                                            echo '<option value="' . $c['member_id'] . '" ' . $sel . '>' . $label . '</option>';
                                                        endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <button type="submit" name="action" value="member_360" class="btn btn-primary btn-run w-100"
                                                    onclick="var s=document.getElementById('m360_member_id'); if(!s||!s.value){alert('Please select a member first.'); if(s){s.focus();} return false;} return true;">
                                                    <i class="fas fa-search"></i> All
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /m360-filter-pane -->

                            <!-- Result Pane -->
                            <div class="tab-pane fade <?= !empty($member360)?'show active':'' ?>" id="m360-result-pane" role="tabpanel">
                            <?php if (!empty($member360) && !empty($member360['profile'])):
                                $p   = $member360['profile'];
                                $tot = $member360['totals'] ?? ['inv_count'=>0,'total_billed'=>0,'total_paid'=>0,'total_balance'=>0];
                                $fullName = trim(($p['name'] ?? '') . ' ' . ($p['mname'] ?? '') . ' ' . ($p['lname'] ?? ''));
                            ?>
                            <div class="fade-in">
                                <!-- Header -->
                                <div class="card mb-3">
                                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                                        <div class="flex-grow-1">
                                            <h3 class="mb-1"><?= htmlspecialchars($fullName ?: '(No Name)') ?>
                                                <small class="text-muted">#<?= (int)$p['member_id'] ?></small>
                                                <?php if ((int)($p['status'] ?? 0) === 1): ?>
                                                    <span class="badge bg-success ms-2">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary ms-2">Inactive</span>
                                                <?php endif; ?>
                                            </h3>
                                            <div class="text-muted">
                                                <strong>Business:</strong> <?= htmlspecialchars($p['bus_name'] ?: '-') ?> &nbsp;|&nbsp;
                                                <strong>Type:</strong> <?= htmlspecialchars($p['type_name'] ?: '-') ?> &nbsp;|&nbsp;
                                                <strong>Client Since:</strong> <?= htmlspecialchars($p['client_since'] ?: '-') ?>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($p['prm_email'] ?: '-') ?> &nbsp;
                                                <i class="fas fa-phone ms-2"></i> <?= htmlspecialchars($p['phone'] ?: $p['cell_ph'] ?: '-') ?>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-success me-2" id="m360ExportBtn"
                                                data-member-id="<?= (int)$p['member_id'] ?>"
                                                data-member-name="<?= htmlspecialchars($fullName ?: 'member') ?>">
                                            <i class="fas fa-file-excel"></i> Export Full Profile
                                        </button>
                                        <a class="btn btn-outline-primary <?= ((int)($p['status'] ?? 0) === 1) ? 'border-success text-success' : 'border-danger text-danger' ?>"
                                           href="client_details.php?id=<?= (int)$p['member_id'] ?>&status=<?= (int)($p['status'] ?? 0) ?>"
                                           target="_blank">
                                            <i class="fas fa-external-link-alt"></i> Open Full Details
                                        </a>
                                    </div>
                                </div>

                                <!-- KPI strip -->
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3 col-6">
                                        <div class="card h-100"><div class="card-body">
                                            <div class="small text-muted">Invoices</div>
                                            <div class="fs-4 fw-bold"><?= (int)$tot['inv_count'] ?></div>
                                        </div></div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="card h-100"><div class="card-body">
                                            <div class="small text-muted">Total Billed</div>
                                            <div class="fs-4 fw-bold text-primary">$<?= number_format((float)$tot['total_billed'], 2) ?></div>
                                        </div></div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="card h-100"><div class="card-body">
                                            <div class="small text-muted">Total Paid</div>
                                            <div class="fs-4 fw-bold text-success">$<?= number_format((float)$tot['total_paid'], 2) ?></div>
                                        </div></div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="card h-100"><div class="card-body">
                                            <div class="small text-muted">Outstanding</div>
                                            <div class="fs-4 fw-bold text-danger">$<?= number_format((float)$tot['total_balance'], 2) ?></div>
                                        </div></div>
                                    </div>
                                </div>

                                <!-- Accordion of every dataset -->
                                <div class="accordion" id="m360Acc">

                                    <!-- Profile -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#m360p1"><i class="fas fa-user me-2"></i> Profile</button></h2>
                                        <div id="m360p1" class="accordion-collapse collapse show" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <div class="row small" id="m360ProfileFields">
                                                    <?php
                                                    $profileFields = [
                                                        'File'=>'file','Name'=>'name','Middle'=>'mname','Last'=>'lname',
                                                        'Business'=>'bus_name','Position'=>'member_pos','DBA'=>'dba',
                                                        'EIN'=>'ein','SSN'=>'ss','PMB'=>'pmb','DOB'=>'dob',
                                                        'Phone'=>'phone','Cell'=>'cell_ph','Other Ph'=>'other_ph','Fax'=>'fax',
                                                        'Primary Email'=>'prm_email','Secondary Email'=>'sec_email',
                                                        'Home Apt'=>'hapartment','Home Street'=>'hstreet','Home City'=>'hcity','Home State'=>'hstate','Home Zip'=>'hzip','Home County'=>'hcounty',
                                                        'Business Address'=>'business_address','Bus City'=>'city','Bus State'=>'state','Bus Zip'=>'zip','Bus County'=>'bcounty',
                                                        'Incorp Date'=>'incorp_date','Incorp State'=>'incorpstate','Business Start'=>'bus_start_date',
                                                        'Due Date'=>'duedate','Closing M'=>'closingm','Occupation'=>'occupation',
                                                        'ACH Payer'=>'ach_payer','Payer Frequency'=>'payer_frequency','Category'=>'category',
                                                        'Date Added'=>'date_added','Date Updated'=>'dateupdated','Date Last'=>'datelast'
                                                    ];
                                                    foreach ($profileFields as $lbl => $col):
                                                        $v = $p[$col] ?? '';
                                                        if ($col === 'ach_payer') $v = ((int)$v === 1 ? 'Yes' : 'No');
                                                    ?>
                                                    <div class="col-md-4 mb-2"><strong><?= $lbl ?>:</strong> <?= htmlspecialchars((string)($v === '' ? '-' : $v)) ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Business -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p2"><i class="fas fa-briefcase me-2"></i> Business Records <span class="badge bg-secondary ms-2"><?= count($member360['business']) ?></span></button></h2>
                                        <div id="m360p2" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['business']): ?>
                                                    <div class="text-muted">No business records.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360BusTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Name</th><th>Category</th><th>President</th><th>Bus Address</th><th>Mailing</th><th>Incorp Date</th><th>Year End</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['business'] as $b): ?>
                                                    <tr>
                                                        <td><?= (int)$b['business_id'] ?></td>
                                                        <td><?= htmlspecialchars($b['bus_name']) ?></td>
                                                        <td><?= htmlspecialchars($b['busact_name'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($b['president_name']) ?></td>
                                                        <td><?= htmlspecialchars(trim(($b['bus_street']??'').' '.($b['bus_city']??'').' '.($b['bus_state']??'').' '.($b['bus_zip']??''))) ?></td>
                                                        <td><?= htmlspecialchars(trim(($b['mail_street']??'').' '.($b['mail_city']??'').' '.($b['mail_state']??'').' '.($b['mail_zip']??''))) ?></td>
                                                        <td><?= htmlspecialchars($b['incorp_date']) ?></td>
                                                        <td><?= htmlspecialchars($b['date_year_end']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Spouse -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p3"><i class="fas fa-users me-2"></i> Spouse / Relations <span class="badge bg-secondary ms-2"><?= count($member360['spouse']) ?></span></button></h2>
                                        <div id="m360p3" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['spouse']): ?>
                                                    <div class="text-muted">No spouse / relation records.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360SpouseTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>Relation</th><th>Name</th><th>DOB</th><th>SSN</th><th>Phone</th><th>Email</th><th>Address</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['spouse'] as $s): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($s['sp_relation']) ?></td>
                                                        <td><?= htmlspecialchars(trim($s['sp_name'].' '.$s['sp_mname'].' '.$s['sp_lname'])) ?></td>
                                                        <td><?= htmlspecialchars($s['sp_dob']) ?></td>
                                                        <td><?= htmlspecialchars($s['sp_ss']) ?></td>
                                                        <td><?= htmlspecialchars($s['sp_phone']) ?></td>
                                                        <td><?= htmlspecialchars($s['sp_email']) ?></td>
                                                        <td><?= htmlspecialchars(trim($s['sp_street'].' '.$s['sp_city'].' '.$s['sp_state'].' '.$s['sp_zip'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Invoices -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p4"><i class="fas fa-file-invoice-dollar me-2"></i> Invoices <span class="badge bg-secondary ms-2"><?= count($member360['invoices']) ?></span></button></h2>
                                        <div id="m360p4" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['invoices']): ?>
                                                    <div class="text-muted">No invoices.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360InvTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>Invoice #</th><th>Date</th><th>Due</th><th>Status</th><th>Total</th><th>Paid</th><th>Balance</th><th>Items</th><th>Paid Date</th><th>File</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['invoices'] as $i): ?>
                                                    <tr>
                                                        <td><?= (int)$i['invoice_id'] ?></td>
                                                        <td><?= htmlspecialchars($i['invoice_date']) ?></td>
                                                        <td><?= htmlspecialchars($i['due_date']) ?></td>
                                                        <td><span class="badge bg-info"><?= htmlspecialchars($i['status_text'] ?? '-') ?></span></td>
                                                        <td>$<?= number_format((float)$i['total_amount'],2) ?></td>
                                                        <td>$<?= number_format((float)$i['paid_amount'],2) ?></td>
                                                        <td>$<?= number_format((float)$i['balance'],2) ?></td>
                                                        <td><?= (int)$i['item_count'] ?></td>
                                                        <td><?= htmlspecialchars($i['paid_date']) ?></td>
                                                        <td><?php if(!empty($i['filelink'])): ?><a href="invoices/<?= htmlspecialchars($i['filelink']) ?>" target="_blank"><i class="fas fa-file-pdf"></i></a><?php else: ?>-<?php endif; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Invoice line items -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p5"><i class="fas fa-list me-2"></i> Invoice Services<span class="badge bg-secondary ms-2"><?= count($member360['invoice_items']) ?></span></button></h2>
                                        <div id="m360p5" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['invoice_items']): ?>
                                                    <div class="text-muted">No line items.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360ItemTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>Invoice #</th><th>Description</th><th>Unit Price</th><th>Qty</th><th>Total</th><th>Date</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['invoice_items'] as $it): ?>
                                                    <tr>
                                                        <td><?= (int)$it['inv_id'] ?></td>
                                                        <td><?= htmlspecialchars($it['item_description']) ?><?php if(!empty($it['item_sub_description'])): ?><br><small class="text-muted"><?= htmlspecialchars(strip_tags($it['item_sub_description'])) ?></small><?php endif; ?></td>
                                                        <td>$<?= number_format((float)$it['unit_price'],2) ?></td>
                                                        <td><?= (int)$it['quantity'] ?></td>
                                                        <td>$<?= number_format((float)$it['total_price'],2) ?></td>
                                                        <td><?= htmlspecialchars($it['item_date']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Projects -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p6"><i class="fas fa-project-diagram me-2"></i> Projects <span class="badge bg-secondary ms-2"><?= count($member360['projects']) ?></span></button></h2>
                                        <div id="m360p6" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['projects']): ?>
                                                    <div class="text-muted">No projects.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360ProjTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Title</th><th>Description</th><th>Type</th><th>Status</th><th>Start</th><th>End</th><th>Assigned</th><th>Created</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['projects'] as $pr): ?>
                                                    <tr>
                                                        <td><?= (int)$pr['order_id'] ?></td>
                                                        <td><?= htmlspecialchars($pr['project_title']) ?></td>
                                                        <td><?= htmlspecialchars($pr['project_description']) ?></td>
                                                        <td><?= htmlspecialchars($pr['project_type'] ?? '-') ?></td>
                                                        <td><span class="badge bg-primary"><?= htmlspecialchars($pr['project_status'] ?? '-') ?></span></td>
                                                        <td><?= htmlspecialchars($pr['start_date']) ?></td>
                                                        <td><?= htmlspecialchars($pr['exp_date']) ?></td>
                                                        <td><?= htmlspecialchars($pr['assigned_to_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($pr['date_added']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Orders -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p7"><i class="fas fa-shopping-cart me-2"></i> Orders <span class="badge bg-secondary ms-2"><?= count($member360['orders']) ?></span></button></h2>
                                        <div id="m360p7" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['orders']): ?>
                                                    <div class="text-muted">No orders.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360OrdTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>Order #</th><th>Product</th><th>Start</th><th>End</th><th>Sub</th><th>Discount</th><th>Total</th><th>Status</th><th>Added</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['orders'] as $o):
                                                    $st = (int)($o['order_status'] ?? 0);
                                                    $stTxt = $st === 1 ? 'Active' : ($st === -1 ? 'Completed' : 'Inactive');
                                                    $stCls = $st === 1 ? 'bg-success' : ($st === -1 ? 'bg-info' : 'bg-secondary');
                                                ?>
                                                    <tr>
                                                        <td><?= (int)$o['order_id'] ?></td>
                                                        <td><?= htmlspecialchars($o['membership_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($o['start_date']) ?></td>
                                                        <td><?= htmlspecialchars($o['exp_date']) ?></td>
                                                        <td>$<?= htmlspecialchars($o['sub_total']) ?></td>
                                                        <td><?= htmlspecialchars($o['discount']) ?></td>
                                                        <td>$<?= htmlspecialchars($o['total']) ?></td>
                                                        <td><span class="badge <?= $stCls ?>"><?= $stTxt ?></span></td>
                                                        <td><?= htmlspecialchars($o['date_added']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Services -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p8"><i class="fas fa-concierge-bell me-2"></i> Services <span class="badge bg-secondary ms-2"><?= count($member360['services']) ?></span></button></h2>
                                        <div id="m360p8" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['services']): ?>
                                                    <div class="text-muted">No services assigned.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360ServicesTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Membership</th><th>Description</th><th>Added By</th><th>Added At</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['services'] as $sv): ?>
                                                    <tr>
                                                        <td><?= (int)$sv['id'] ?></td>
                                                        <td><?= htmlspecialchars($sv['membership_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($sv['description'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($sv['user_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($sv['added_at']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quotations -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p9"><i class="fas fa-file-alt me-2"></i> Quotations <span class="badge bg-secondary ms-2"><?= count($member360['quotations']) ?></span></button></h2>
                                        <div id="m360p9" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['quotations']): ?>
                                                    <div class="text-muted">No quotations.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360QuoTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>SR</th><th>Name</th><th>Email</th><th>Phone</th><th>Sub</th><th>Discount</th><th>Total</th><th>Date</th><th>Status</th><th>File</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['quotations'] as $q): ?>
                                                    <tr>
                                                        <td><?= (int)$q['quotation_id'] ?></td>
                                                        <td><?= htmlspecialchars($q['quotation_sr']) ?></td>
                                                        <td><?= htmlspecialchars($q['name']) ?></td>
                                                        <td><?= htmlspecialchars($q['email']) ?></td>
                                                        <td><?= htmlspecialchars($q['phone']) ?></td>
                                                        <td>$<?= htmlspecialchars($q['sub_total']) ?></td>
                                                        <td><?= htmlspecialchars($q['discount']) ?></td>
                                                        <td>$<?= htmlspecialchars($q['grand_total']) ?></td>
                                                        <td><?= htmlspecialchars($q['quotation_date']) ?></td>
                                                        <td><span class="badge bg-info"><?= htmlspecialchars($q['status']) ?></span></td>
                                                        <td><?php if(!empty($q['file_name'])): ?><a href="quotations/<?= htmlspecialchars($q['file_name']) ?>" target="_blank"><i class="fas fa-file-pdf"></i></a><?php else: ?>-<?php endif; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- General Comments -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p10"><i class="fas fa-comments me-2"></i> General Comments <span class="badge bg-secondary ms-2"><?= count($member360['comments']) ?></span></button></h2>
                                        <div id="m360p10" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['comments']): ?>
                                                    <div class="text-muted">No comments.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360CommTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Comment</th><th>By</th><th>Time</th><th>Status</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['comments'] as $cm): ?>
                                                    <tr>
                                                        <td><?= (int)$cm['comment_id'] ?></td>
                                                        <td><?= $cm['comment_detail'] /* HTML allowed by app */ ?></td>
                                                        <td><?= htmlspecialchars($cm['user_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($cm['comment_time']) ?></td>
                                                        <td><?= htmlspecialchars($cm['comment_status']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Invoice Comments -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p11"><i class="fas fa-comment-dollar me-2"></i> Invoice Comments <span class="badge bg-secondary ms-2"><?= count($member360['new_comments']) ?></span></button></h2>
                                        <div id="m360p11" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['new_comments']): ?>
                                                    <div class="text-muted">No invoice comments.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360NewCommTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Invoice #</th><th>Comment</th><th>By</th><th>Time</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['new_comments'] as $nc): ?>
                                                    <tr>
                                                        <td><?= (int)$nc['comment_id'] ?></td>
                                                        <td><?= (int)$nc['invoice_id'] ?></td>
                                                        <td><?= $nc['comment_detail'] ?></td>
                                                        <td><?= htmlspecialchars($nc['user_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($nc['comment_time']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p12"><i class="fas fa-sticky-note me-2"></i> Notes <span class="badge bg-secondary ms-2"><?= count($member360['notes']) ?></span></button></h2>
                                        <div id="m360p12" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['notes']): ?>
                                                    <div class="text-muted">No notes.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360NotesTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Note</th><th>By</th><th>Time</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['notes'] as $n): ?>
                                                    <tr>
                                                        <td><?= (int)$n['notes_id'] ?></td>
                                                        <td><?= $n['notes_detail'] ?></td>
                                                        <td><?= htmlspecialchars($n['user_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($n['notes_time']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Emails / SMS -->
                                    <div class="accordion-item">
                                        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#m360p13"><i class="fas fa-paper-plane me-2"></i> Email &amp; SMS History <span class="badge bg-secondary ms-2"><?= count($member360['emails']) ?></span></button></h2>
                                        <div id="m360p13" class="accordion-collapse collapse" data-bs-parent="#m360Acc">
                                            <div class="accordion-body">
                                                <?php if (!$member360['emails']): ?>
                                                    <div class="text-muted">No email / SMS history.</div>
                                                <?php else: ?>
                                                <div class="table-responsive"><table id="m360MailTable" class="table table-sm table-striped"><thead><tr>
                                                    <th>ID</th><th>Type</th><th>Subject</th><th>To</th><th>From</th><th>Invoice</th><th>Sent By</th><th>Sent At</th>
                                                </tr></thead><tbody>
                                                <?php foreach ($member360['emails'] as $em): ?>
                                                    <tr>
                                                        <td><?= (int)$em['id'] ?></td>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($em['type'] ?? '-') ?></span></td>
                                                        <td><?= htmlspecialchars($em['subject'] ?? '') ?></td>
                                                        <td><?= htmlspecialchars($em['To_mail'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($em['from_mail'] ?? '-') ?></td>
                                                        <td><?= !empty($em['invoice_id']) ? (int)$em['invoice_id'] : '-' ?></td>
                                                        <td><?= htmlspecialchars($em['sent_by_name'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($em['sent_at']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                </tbody></table></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                </div><!-- /accordion -->
                            </div><!-- /fade-in -->

                            <?php elseif (!empty($member360) && empty($member360['profile'])): ?>
                                <div class="empty-graph-state"><i class="fas fa-user-slash fa-3x d-block mb-3"></i><h6>Member not found</h6><p>No record exists for the selected member ID.</p></div>
                            <?php else: ?>
                                <div class="empty-graph-state"><i class="fas fa-id-card fa-3x d-block mb-3"></i><h6>No member selected</h6><p>Pick a member in the <strong>Select Member</strong> tab and click <strong>Pull 360° View</strong>.</p></div>
                            <?php endif; ?>
                            </div><!-- /m360-result-pane -->

                        </div><!-- /tab-content -->
                    </div><!-- /member360 tab-pane -->

                </div><!-- /tab-content -->
        </form>
    </div><!-- /page-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script>
        $(document).ready(function () {
            // Initialize Select2 dropdowns (single + multi). Multi-selects use their
            // own data-placeholder and drop the single "clear" X (the per-tag X handles it).
            $('.select2-client').each(function () {
                var $el = $(this);
                var isMulti = $el.prop('multiple');
                $el.select2({
                    placeholder: $el.data('placeholder') || 'Select an option',
                    width: '100%',
                    allowClear: !isMulti,
                    closeOnSelect: !isMulti
                });
            });

            // Member Services — select all / clear the multi-select
            $('#msSelectAll').on('click', function () {
                $('select[name="ms_service[]"]').find('option').prop('selected', true).end().trigger('change.select2');
            });
            $('#msClearAll').on('click', function () {
                $('select[name="ms_service[]"]').val(null).trigger('change.select2');
            });

            // Initialize DataTables for all result tables
            var tableOptions = {
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-sm btn-success',
                        exportOptions: {
                            format: {
                                // Tag client-name cells (rendered via renderClientLink()) with
                                // markers so `customize` below can turn them into real Excel
                                // hyperlink formulas. Every other column passes through untouched.
                                body: function (data, row, column, node) {
                                    var $link = $(node).find('a.client-link').first();
                                    if (!$link.length) return data;
                                    var url = new URL($link.attr('href'), window.location.href).href;
                                    var label = $link.text().trim();
                                    return '@@XLSX_HYPERLINK@@' + url + '@@XLSX_LABEL@@' + label;
                                }
                            }
                        },
                        customize: function (xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            var marker = '@@XLSX_HYPERLINK@@';
                            var esc = function (s) { return s.replace(/"/g, '""'); };
                            $('c[t="inlineStr"]', sheet).each(function () {
                                var cellEl = this;
                                var $c = $(cellEl);
                                var text = $c.find('t').text();
                                var idx = text.indexOf(marker);
                                if (idx === -1) return;
                                var rest = text.substring(idx + marker.length);
                                var parts = rest.split('@@XLSX_LABEL@@');
                                var url = parts[0];
                                var label = parts[1] || url;
                                // Build <f>/<v> via createElementNS (not jQuery's html parser,
                                // which would stamp them with the xhtml namespace and corrupt
                                // the sheet) so they inherit the spreadsheetml namespace.
                                var ownerDoc = cellEl.ownerDocument;
                                var ns = cellEl.namespaceURI;
                                var f = ownerDoc.createElementNS(ns, 'f');
                                f.textContent = 'HYPERLINK("' + esc(url) + '","' + esc(label) + '")';
                                var v = ownerDoc.createElementNS(ns, 'v');
                                v.textContent = label;
                                $c.attr('t', 'str').empty();
                                cellEl.appendChild(f);
                                cellEl.appendChild(v);
                            });
                        }
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn btn-sm btn-info'
                    }
                ],
                responsive: true,
                pageLength: 25,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search records..."
                }
            };

            <?php if (isset($generalFilterResult)): ?>
                $('#generalFilterTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($extendedResult)): ?>
                $('#extendedFilterTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($invoiceResult)): ?>
                $('#invTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($projectResult)): ?>
                $('#projTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($quotationResult)): ?>
                $('#quoteTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($paymentResult)): ?>
                $('#payTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($commentResult)): ?>
                $('#commTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($reminderResult)): ?>
                $('#remTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($memberServicesResult)): ?>
                $('#memberServicesTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($associatedServicesResult)): ?>
                $('#associatedServicesTable').DataTable(tableOptions);
            <?php endif; ?>

            <?php if (isset($productRenewalsResult)): ?>
                $('#productRenewalsTable').DataTable(Object.assign({}, tableOptions, { order: [] }));
            <?php endif; ?>

            <?php if (!empty($member360) && !empty($member360['profile'])): ?>
                var m360TableOpts = Object.assign({}, tableOptions, { pageLength: 10 });
                ['#m360BusTable','#m360InvTable','#m360ItemTable','#m360ProjTable','#m360OrdTable','#m360QuoTable','#m360CommTable','#m360MailTable']
                    .forEach(function(sel){ if ($(sel).length) { $(sel).DataTable(m360TableOpts); } });

                // Export the whole Member 360 profile to one multi-sheet Excel file
                $('#m360ExportBtn').on('click', function () {
                    var wb = XLSX.utils.book_new();

                    // Profile sheet: label/value pairs plus the KPI totals
                    var profileRows = [];
                    $('#m360ProfileFields > div').each(function () {
                        var text = $(this).text();
                        var idx = text.indexOf(':');
                        if (idx > -1) {
                            profileRows.push([text.substring(0, idx).trim(), text.substring(idx + 1).trim()]);
                        }
                    });
                    profileRows.push(['Invoices', <?= (int)$tot['inv_count'] ?>]);
                    profileRows.push(['Total Billed', <?= (float)$tot['total_billed'] ?>]);
                    profileRows.push(['Total Paid', <?= (float)$tot['total_paid'] ?>]);
                    profileRows.push(['Outstanding', <?= (float)$tot['total_balance'] ?>]);
                    XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet(profileRows), 'Profile');

                    var sections = [
                        ['#m360BusTable', 'Business'],
                        ['#m360SpouseTable', 'Spouse-Relations'],
                        ['#m360InvTable', 'Invoices'],
                        ['#m360ItemTable', 'Invoice Services'],
                        ['#m360ProjTable', 'Projects'],
                        ['#m360OrdTable', 'Orders'],
                        ['#m360ServicesTable', 'Services'],
                        ['#m360QuoTable', 'Quotations'],
                        ['#m360CommTable', 'Comments'],
                        ['#m360NewCommTable', 'Invoice Comments'],
                        ['#m360NotesTable', 'Notes'],
                        ['#m360MailTable', 'Email-SMS History']
                    ];
                    sections.forEach(function (s) {
                        var el = document.querySelector(s[0]);
                        if (el) {
                            XLSX.utils.book_append_sheet(wb, XLSX.utils.table_to_sheet(el), s[1]);
                        }
                    });

                    var memberName = $(this).data('member-name') || 'member';
                    var memberId = $(this).data('member-id') || '';
                    var fileName = ('Member360_' + memberName + '_' + memberId).replace(/[^a-z0-9_\-]/gi, '_') + '.xlsx';
                    XLSX.writeFile(wb, fileName);
                });
            <?php endif; ?>

            // Auto-activate tab that has results (from server POST)
            var serverTab = '<?= $activeTab ?>';
            if (serverTab && serverTab !== 'dashboard') {
                var tabEl = document.getElementById(serverTab + '-tab');
                if (tabEl) {
                    new bootstrap.Tab(tabEl).show();
                    setTimeout(function(){
                        var res = document.querySelector('#' + serverTab + ' .fade-in');
                        if (res) res.scrollIntoView({behavior:'smooth', block:'start'});
                    }, 350);
                }
            }

        });
    </script>
</body>

</html>