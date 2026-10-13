<?php
include('./includes/db.php');
session_start();

if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id'])) {
    echo '<div class="dropdown-item text-danger">Access Denied: User not logged in.</div>';
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user_type = mysqli_real_escape_string($con, $_SESSION['user_type']);

$createTableSQL = "CREATE TABLE IF NOT EXISTS recent_searches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    search_text VARCHAR(255) NOT NULL,
    clicked_member_id INT NULL,
    searched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (search_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($con, $createTableSQL);

$colCheck = mysqli_query($con, "SHOW COLUMNS FROM recent_searches LIKE 'clicked_member_id'");
if (!$colCheck || mysqli_num_rows($colCheck) === 0) {
    mysqli_query($con, "ALTER TABLE recent_searches ADD COLUMN clicked_member_id INT NULL");
}

$checkAccessQuery = "SELECT view_clients_list FROM dashboard_access WHERE user_type = '$user_type' LIMIT 1";
$accessResult = mysqli_query($con, $checkAccessQuery);

if (!$accessResult || mysqli_num_rows($accessResult) == 0) {
    echo '<div class="dropdown-item text-danger">Access Denied: Access rule not found.</div>';
    exit;
}

$accessRow = mysqli_fetch_assoc($accessResult);
if (strtolower($accessRow['view_clients_list']) !== 'yes') {
    echo '<div class="dropdown-item text-danger">Access Denied: You do not have permission to view clients.</div>';
    exit;
}

// Track a click on a search result so the keyword becomes linked to the opened profile
if (isset($_POST['action']) && $_POST['action'] === 'track_click') {
    $clickedId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $clickedSearch = isset($_POST['search']) ? trim($_POST['search']) : '';
    if ($clickedId > 0 && $clickedSearch !== '') {
        $clickedSearchEsc = mysqli_real_escape_string($con, $clickedSearch);
        $upd = mysqli_query($con, "UPDATE recent_searches
                                   SET clicked_member_id = $clickedId, searched_at = CURRENT_TIMESTAMP
                                   WHERE user_id = $user_id AND search_text = '$clickedSearchEsc'");
        if ($upd && mysqli_affected_rows($con) === 0) {
            mysqli_query($con, "INSERT INTO recent_searches (user_id, search_text, clicked_member_id)
                                VALUES ($user_id, '$clickedSearchEsc', $clickedId)");
        }
        mysqli_query($con, "DELETE FROM recent_searches
                            WHERE user_id = $user_id
                            AND id NOT IN (
                                SELECT id FROM (
                                    SELECT id FROM recent_searches
                                    WHERE user_id = $user_id
                                    ORDER BY searched_at DESC
                                    LIMIT 8
                                ) AS subquery
                            )");
    }
    echo 'ok';
    exit;
}

if (!isset($_POST['search']) || trim($_POST['search']) === '') {
    $recentQuery = "SELECT rs.search_text, rs.clicked_member_id,
                           m.name, m.mname, m.lname, m.bus_name, m.status
                    FROM recent_searches rs
                    LEFT JOIN members m ON m.member_id = rs.clicked_member_id
                    WHERE rs.user_id = $user_id
                    ORDER BY rs.searched_at DESC
                    LIMIT 8";
    $recentResult = mysqli_query($con, $recentQuery);

    if ($recentResult && mysqli_num_rows($recentResult) > 0) {
        echo '<style>
            .recent-search-row {
                display:flex; align-items:center; gap:8px;
                padding:7px 12px; font-size:13px; color:#333;
                text-decoration:none; border-radius:6px; margin:1px 4px;
                white-space:nowrap; overflow:hidden;
                transition:background .15s ease;
            }
            .recent-search-row:hover { background:#f1f8ee; color:#2f7a18; }
            .recent-search-row .rs-icon { font-size:11px; color:#9aa0a6; flex-shrink:0; }
            .recent-search-row:hover .rs-icon { color:#6fd943; }
            .recent-search-row .rs-keyword {
                font-weight:500; color:#222; flex-shrink:0; max-width:90px;
                overflow:hidden; text-overflow:ellipsis;
            }
            .recent-search-row .rs-sep { color:#cfd3d8; flex-shrink:0; }
            .recent-search-row .rs-profile {
                color:#6fd943; font-size:12px; font-weight:500;
                overflow:hidden; text-overflow:ellipsis; min-width:0; flex:1;
            }
            .recent-search-row .rs-profile .rs-bus { color:#888; font-weight:400; }
            .recent-search-row .rs-profile .fa-user-circle { font-size:11px; margin-right:3px; }
        </style>';
        echo '<div class="dropdown-header">Recent Searches</div>';
        while ($r = mysqli_fetch_assoc($recentResult)) {
            $hasProfile = !empty($r['clicked_member_id']) && !empty($r['name']);
            $dataAttrs = 'data-search="' . htmlspecialchars($r['search_text']) . '"';
            $tipText = $r['search_text'];
            if ($hasProfile) {
                $dataAttrs .= ' data-clicked-id="' . htmlspecialchars($r['clicked_member_id']) . '"';
                $dataAttrs .= ' data-clicked-status="' . htmlspecialchars($r['status']) . '"';
                $profileName = trim($r['name'] . ' ' . $r['mname'] . ' ' . $r['lname']);
                $tipText .= '  →  ' . $profileName;
                if (!empty($r['bus_name'])) {
                    $tipText .= ' - ' . $r['bus_name'];
                }
            }
            echo '<a href="#" class="recent-search recent-search-row" ' . $dataAttrs
                . ' title="' . htmlspecialchars($tipText) . '">'
                . '<i class="fa fa-history rs-icon"></i>'
                . '<span class="rs-keyword">' . htmlspecialchars($r['search_text']) . '</span>';
            if ($hasProfile) {
                echo '<span class="rs-sep">|</span>'
                    . '<span class="rs-profile"><i class="fa fa-user-circle"></i>'
                    . htmlspecialchars($profileName);
                if (!empty($r['bus_name'])) {
                    echo ' <span class="rs-bus">- ' . htmlspecialchars($r['bus_name']) . '</span>';
                }
                echo '</span>';
            }
            echo '</a>';
        }
    } else {
        echo '<div class="dropdown-item text-muted">No recent searches</div>';
    }
    exit;
}

$search = trim($_POST['search']);
$searchRaw = $search;
$statusFilter = isset($_POST['profile_status']) ? (int) $_POST['profile_status'] : 1;
if ($statusFilter !== 0 && $statusFilter !== 1)
    $statusFilter = 1;

// Get search_by parameter (default to 'all' if not set)
$searchBy = isset($_POST['search_by']) ? $_POST['search_by'] : 'all';

$search = mysqli_real_escape_string($con, $search);
$search_no_hyphen = str_replace('-', '', $search);

if (strlen($search) >= 3) {
    // Touch the timestamp if the keyword already exists (preserves clicked_member_id);
    // otherwise insert a new row.
    $existsRes = mysqli_query($con, "SELECT id FROM recent_searches WHERE user_id = $user_id AND search_text = '$search' LIMIT 1");
    if ($existsRes && mysqli_num_rows($existsRes) > 0) {
        mysqli_query($con, "UPDATE recent_searches SET searched_at = CURRENT_TIMESTAMP
                            WHERE user_id = $user_id AND search_text = '$search'");
    } else {
        mysqli_query($con, "INSERT INTO recent_searches (user_id, search_text) VALUES ($user_id, '$search')");
    }
    mysqli_query($con, "DELETE FROM recent_searches
                        WHERE user_id = $user_id
                        AND id NOT IN (
                            SELECT id FROM (
                                SELECT id FROM recent_searches
                                WHERE user_id = $user_id
                                ORDER BY searched_at DESC
                                LIMIT 8
                            ) AS subquery
                        )");
}

$globalMatchedFields = [];
$searchTypeMessage = '';

// Build query based on search_by selection
if ($searchBy === 'instant_invoice') {
    $query = "SELECT * FROM members WHERE 1=0 LIMIT 0";
    $searchTypeMessage = "Searching Instant Invoices";
} elseif ($searchBy === 'invoice') {
    $query = "SELECT * FROM members WHERE 1=0 LIMIT 0";
    $searchTypeMessage = "Searching Invoices";
} elseif ($searchBy === 'member_id') {
    $query = "SELECT * FROM members 
              WHERE member_id = '$search'
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by Member ID";
} elseif ($searchBy === 'name') {
    $searchWords = explode(' ', $search);
    $conditions = [];
    foreach ($searchWords as $word) {
        $word = mysqli_real_escape_string($con, $word);
        $conditions[] = "(name LIKE '%$word%' OR mname LIKE '%$word%' OR lname LIKE '%$word%')";
    }
    $query = "SELECT * FROM members 
              WHERE (" . implode(" AND ", $conditions) . ") 
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by Name";
} elseif ($searchBy === 'phone') {
    $query = "SELECT * FROM members 
              WHERE (
                    REPLACE(phone, '-', '') LIKE '%$search_no_hyphen%'
                    OR REPLACE(cell_ph, '-', '') LIKE '%$search_no_hyphen%'
                    OR REPLACE(other_ph, '-', '') LIKE '%$search_no_hyphen%'
                    OR REPLACE(overseas_ph, '-', '') LIKE '%$search_no_hyphen%'
                    OR REPLACE(fax, '-', '') LIKE '%$search_no_hyphen%'
              )
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by Phone Number";
} elseif ($searchBy === 'email') {
    $query = "SELECT * FROM members 
              WHERE (prm_email LIKE '%$search%' OR sec_email LIKE '%$search%')
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by Email";
} elseif ($searchBy === 'business') {
    $query = "SELECT * FROM members 
              WHERE bus_name LIKE '%$search%'
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by Business Name";
} elseif ($searchBy === 'ein') {
    $query = "SELECT * FROM members 
              WHERE ein LIKE '%$search%'
              AND status = $statusFilter 
              LIMIT 10";
    $searchTypeMessage = "Searching by EIN";
} elseif ($searchBy === 'ssn') {
    $query = "SELECT * FROM members
              WHERE REPLACE(ss, '-', '') LIKE '%$search_no_hyphen%'
              AND status = $statusFilter
              LIMIT 10";
    $searchTypeMessage = "Searching by SSN";
} elseif ($searchBy === 'pmb') {
    $query = "SELECT * FROM members
              WHERE pmb LIKE '%$search%'
              AND status = $statusFilter
              LIMIT 10";
    $searchTypeMessage = "Searching by PMB";
} else {
    // Default 'all' - Original logic
    if (is_numeric($search) || is_numeric($search_no_hyphen)) {
        $query = "SELECT * FROM members 
                  WHERE (
                        member_id = '$search'
                        OR REPLACE(phone, '-', '') LIKE '%$search_no_hyphen%'
                        OR REPLACE(cell_ph, '-', '') LIKE '%$search_no_hyphen%'
                        OR REPLACE(other_ph, '-', '') LIKE '%$search_no_hyphen%'
                        OR REPLACE(overseas_ph, '-', '') LIKE '%$search_no_hyphen%'
                        OR REPLACE(fax, '-', '') LIKE '%$search_no_hyphen%'
                        OR ein LIKE '%$search%'
                        OR REPLACE(ss, '-', '') LIKE '%$search_no_hyphen%'
                  )
                  AND status = $statusFilter 
                  LIMIT 10";
        $searchTypeMessage = "Searching by Member ID, Contact Number, EIN, or SSN";
    } else {
        $searchWords = explode(' ', $search);
        $conditions = [];
        foreach ($searchWords as $word) {
            $word = mysqli_real_escape_string($con, $word);
            $conditions[] = "(
                name LIKE '%$word%' 
                OR mname LIKE '%$word%' 
                OR lname LIKE '%$word%' 
                OR bus_name LIKE '%$word%' 
                OR prm_email LIKE '%$word%' 
                OR sec_email LIKE '%$word%'
                OR ein LIKE '%$word%'
                OR ss LIKE '%$word%'
            )";
        }
        $query = "SELECT * FROM members 
                  WHERE (" . implode(" AND ", $conditions) . ") 
                  AND status = $statusFilter 
                  LIMIT 10";
        $searchTypeMessage = "Searching by Name, Business Name, Email, EIN, or SSN";
    }
}

$result = mysqli_query($con, $query);

echo '
<div class="search-type-message" style="margin-bottom: 10px; font-size: 14px; color: #333; background-color: #f4f4f4; padding: 6px 10px; border-radius: 4px; border-left: 4px solid #6fd943; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
    <i class="fa fa-search me-2"></i>' . $searchTypeMessage . '
</div>';

$allResults = [];
while ($row = mysqli_fetch_assoc($result)) {
    $matchedFieldsThisRow = [];

    // Determine matched fields based on search_by
    if ($searchBy === 'member_id') {
        if ($row['member_id'] == $search)
            $matchedFieldsThisRow[] = "Member ID";
    } elseif ($searchBy === 'name') {
        if (stripos($row['name'], $search) !== false)
            $matchedFieldsThisRow[] = "First Name";
        if (stripos($row['mname'], $search) !== false)
            $matchedFieldsThisRow[] = "Middle Name";
        if (stripos($row['lname'], $search) !== false)
            $matchedFieldsThisRow[] = "Last Name";
    } elseif ($searchBy === 'phone') {
        if (strpos(str_replace('-', '', $row['phone']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Phone";
        if (strpos(str_replace('-', '', $row['cell_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Cell";
        if (strpos(str_replace('-', '', $row['other_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Other Phone";
        if (strpos(str_replace('-', '', $row['overseas_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Overseas Phone";
        if (strpos(str_replace('-', '', $row['fax']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Fax";
    } elseif ($searchBy === 'email') {
        if (stripos($row['prm_email'], $search) !== false)
            $matchedFieldsThisRow[] = "Primary Email";
        if (stripos($row['sec_email'], $search) !== false)
            $matchedFieldsThisRow[] = "Secondary Email";
    } elseif ($searchBy === 'business') {
        if (stripos($row['bus_name'], $search) !== false)
            $matchedFieldsThisRow[] = "Business Name";
    } elseif ($searchBy === 'ein') {
        if (stripos($row['ein'], $search) !== false)
            $matchedFieldsThisRow[] = "EIN";
    } elseif ($searchBy === 'ssn') {
        if (strpos(str_replace('-', '', $row['ss']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "SSN";
    } elseif ($searchBy === 'pmb') {
        if (stripos($row['pmb'], $search) !== false)
            $matchedFieldsThisRow[] = "PMB";
    } else {
        // Default 'all' - check all fields
        if ($row['member_id'] == $search)
            $matchedFieldsThisRow[] = "Member ID";
        if (strpos(str_replace('-', '', $row['phone']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Phone";
        if (strpos(str_replace('-', '', $row['cell_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Cell";
        if (strpos(str_replace('-', '', $row['other_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Other Phone";
        if (strpos(str_replace('-', '', $row['overseas_ph']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Overseas Phone";
        if (strpos(str_replace('-', '', $row['fax']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "Fax";
        if (stripos($row['ein'], $search) !== false)
            $matchedFieldsThisRow[] = "EIN";
        if (strpos(str_replace('-', '', $row['ss']), $search_no_hyphen) !== false)
            $matchedFieldsThisRow[] = "SSN";
        if (stripos($row['name'], $search) !== false)
            $matchedFieldsThisRow[] = "First Name";
        if (stripos($row['mname'], $search) !== false)
            $matchedFieldsThisRow[] = "Middle Name";
        if (stripos($row['lname'], $search) !== false)
            $matchedFieldsThisRow[] = "Last Name";
        if (stripos($row['bus_name'], $search) !== false)
            $matchedFieldsThisRow[] = "Business Name";
        if (stripos($row['prm_email'], $search) !== false)
            $matchedFieldsThisRow[] = "Primary Email";
        if (stripos($row['sec_email'], $search) !== false)
            $matchedFieldsThisRow[] = "Secondary Email";
    }

    foreach ($matchedFieldsThisRow as $f) {
        if (!in_array($f, $globalMatchedFields))
            $globalMatchedFields[] = $f;
    }

    $row['matched'] = $matchedFieldsThisRow;
    $allResults[] = $row;
}

if (!empty($globalMatchedFields)) {
    echo '<div style="background:#eefbea;padding:6px 10px;border-radius:4px;font-size:13px;margin-bottom:8px;border-left:4px solid #6fd943;">
            <strong>Matched On:</strong> ' . implode(', ', $globalMatchedFields) . '
          </div>';
}

foreach ($allResults as $row) {
    $matchedDisplay = '';
    if (!empty($row['matched'])) {
        $matchedDisplay = '<div style="font-size:12px;color:#555;font-weight:bold;margin-top:3px;">
            Matched On: ' . implode(', ', $row['matched']) . '
        </div>';
    }

    echo '
    <a href="http://192.168.15.29/client_details.php?id=' . (int) $row['member_id'] . '&status=' . (int) $row['status'] . '"
       class="dropdown-item d-flex align-items-center search-result-link"
       data-member-id="' . (int) $row['member_id'] . '"
       data-search-text="' . htmlspecialchars($searchRaw) . '"
       style="padding:8px 10px;">

        <div class="me-3">
            <i class="fa fa-user-circle" style="font-size:16px;color:#6fd943;"></i>
        </div>

        <div>
            <strong>' . htmlspecialchars($row['name'] . ' ' . $row['mname'] . ' ' . $row['lname']) . '</strong><br>
            <strong>' . htmlspecialchars($row['bus_name']) . '</strong>
            <div style="font-size:12px;color:' . ($row['status'] ? 'green' : 'red') . ';">
                ' . ($row['status'] ? 'Active' : 'Inactive') . '
            </div>

            ' . $matchedDisplay . '
        </div>
    </a>';
}

$invoiceRows = [];
$invoiceMessage = '';
$invoiceQuery = '';

if ($searchBy === 'invoice' || $searchBy === 'all' || $searchBy === 'member_id') {
    if (is_numeric($search) || is_numeric($search_no_hyphen)) {
        $invoiceQuery = "SELECT bi.*, m.name, m.mname, m.lname, m.bus_name, m.prm_email, m.phone AS member_phone, m.status AS member_status
                         FROM billing_invoice bi
                         LEFT JOIN members m ON m.member_id = bi.mem_id
                         WHERE (bi.invoice_id LIKE '%$search%' OR bi.mem_id LIKE '%$search%')
                         ORDER BY bi.invoice_id DESC
                         LIMIT 10";
        $invoiceMessage = "Matching Invoices by Invoice ID or Member ID";
    } elseif ($searchBy === 'invoice') {
        $searchWords = explode(' ', $search);
        $invoiceConditions = [];
        foreach ($searchWords as $word) {
            $word = mysqli_real_escape_string($con, $word);
            $invoiceConditions[] = "(m.name LIKE '%$word%' OR m.mname LIKE '%$word%' OR m.lname LIKE '%$word%' OR m.bus_name LIKE '%$word%' OR m.prm_email LIKE '%$word%')";
        }
        $invoiceQuery = "SELECT bi.*, m.name, m.mname, m.lname, m.bus_name, m.prm_email, m.phone AS member_phone, m.status AS member_status
                         FROM billing_invoice bi
                         LEFT JOIN members m ON m.member_id = bi.mem_id
                         WHERE (" . implode(" AND ", $invoiceConditions) . ")
                         ORDER BY bi.invoice_id DESC
                         LIMIT 10";
        $invoiceMessage = "Matching Invoices by Client Name, Business or Email";
    }
}

if (!empty($invoiceQuery)) {
    $invoiceResult = mysqli_query($con, $invoiceQuery);
    while ($invRow = mysqli_fetch_assoc($invoiceResult)) {
        $invoiceRows[] = $invRow;
    }
}

if (!empty($invoiceRows)) {
    echo '<div style="background:#fff7e6;padding:6px 10px;border-radius:4px;font-size:13px;margin:8px 0;border-left:4px solid #f0a23a;">
            <strong>Invoices:</strong> ' . htmlspecialchars($invoiceMessage) . '
          </div>';

    foreach ($invoiceRows as $invRow) {
        $clientName = trim(($invRow['name'] ?? '') . ' ' . ($invRow['mname'] ?? '') . ' ' . ($invRow['lname'] ?? ''));
        if ($clientName === '') $clientName = 'Unknown Client';
        $busLine = !empty($invRow['bus_name'])
            ? '<div style="font-size:12px;color:#666;">' . htmlspecialchars($invRow['bus_name']) . '</div>'
            : '';
        $amount = isset($invRow['total_amount']) ? number_format((float) $invRow['total_amount'], 2) : '0.00';
        $balance = isset($invRow['balance']) ? number_format((float) $invRow['balance'], 2) : '0.00';

        $memId = (int) ($invRow['mem_id'] ?? 0);
        $memStatus = isset($invRow['member_status']) ? (int) $invRow['member_status'] : 1;
        $viewInvoiceUrl = !empty($invRow['filelink'])
            ? 'invoices/' . $invRow['filelink']
            : 'client_billing.php?id=' . $memId;
        $clientProfileUrl = 'client_details.php?id=' . $memId . '&status=' . $memStatus;

        echo '
        <div class="dropdown-item d-flex align-items-start" style="padding:8px 10px;">
            <div class="me-3">
                <i class="fas fa-file-invoice-dollar" style="font-size:16px;color:#f0a23a;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <strong>Invoice #' . htmlspecialchars($invRow['invoice_id']) . '</strong><br>
                <strong>' . htmlspecialchars($clientName) . '</strong>
                ' . $busLine . '
                <div style="font-size:12px;color:#666;">
                    Member ID: ' . htmlspecialchars($invRow['mem_id']) . ' | Total: $' . $amount . ' | Balance: $' . $balance . '
                </div>
                <div class="invoice-result-actions">
                    <a href="' . htmlspecialchars($viewInvoiceUrl) . '" class="inv-action inv-action-view" target="_blank" rel="noopener">
                        <i class="fas fa-file-invoice-dollar"></i> View Invoice
                    </a>';
        if ($memId > 0) {
            echo '
                    <a href="' . htmlspecialchars($clientProfileUrl) . '" class="inv-action inv-action-profile">
                        <i class="fa fa-user-circle"></i> Client Profile
                    </a>';
        }
        echo '
                </div>
            </div>
        </div>';
    }
}

$instantInvoiceRows = [];
$instantInvoiceMessage = '';

if ($searchBy === 'invoice') {
    // Skip instant invoice block when user explicitly selected Invoice
} elseif ($searchBy === 'instant_invoice') {
    if (is_numeric($search) || is_numeric($search_no_hyphen)) {
        $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                                WHERE (
                                    invoice_id LIKE '%$search%'
                                    OR mem_id LIKE '%$search%'
                                    OR REPLACE(phone, '-', '') LIKE '%$search_no_hyphen%'
                                )
                                ORDER BY invoice_id DESC
                                LIMIT 10";
        $instantInvoiceMessage = "Searching Instant Invoices by Invoice ID, Member ID, or Phone";
    } else {
        $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                                WHERE (
                                    name LIKE '%$search%'
                                    OR email LIKE '%$search%'
                                )
                                ORDER BY invoice_id DESC
                                LIMIT 10";
        $instantInvoiceMessage = "Searching Instant Invoices by Name or Email";
    }
} elseif ($searchBy === 'member_id') {
    $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                            WHERE mem_id LIKE '%$search%'
                            ORDER BY invoice_id DESC
                            LIMIT 10";
    $instantInvoiceMessage = "Matching Instant Invoices by Member ID";
} elseif ($searchBy === 'name') {
    $searchWords = explode(' ', $search);
    $invoiceConditions = [];
    foreach ($searchWords as $word) {
        $word = mysqli_real_escape_string($con, $word);
        $invoiceConditions[] = "name LIKE '%$word%'";
    }
    $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                            WHERE (" . implode(" AND ", $invoiceConditions) . ")
                            ORDER BY invoice_id DESC
                            LIMIT 10";
    $instantInvoiceMessage = "Matching Instant Invoices by Name";
} elseif ($searchBy === 'phone') {
    $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                            WHERE REPLACE(phone, '-', '') LIKE '%$search_no_hyphen%'
                            ORDER BY invoice_id DESC
                            LIMIT 10";
    $instantInvoiceMessage = "Matching Instant Invoices by Phone";
} elseif ($searchBy === 'email') {
    $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                            WHERE email LIKE '%$search%'
                            ORDER BY invoice_id DESC
                            LIMIT 10";
    $instantInvoiceMessage = "Matching Instant Invoices by Email";
} else {
    if (is_numeric($search) || is_numeric($search_no_hyphen)) {
        $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                                WHERE (
                                    invoice_id LIKE '%$search%'
                                    OR mem_id LIKE '%$search%'
                                    OR REPLACE(phone, '-', '') LIKE '%$search_no_hyphen%'
                                )
                                ORDER BY invoice_id DESC
                                LIMIT 10";
        $instantInvoiceMessage = "Matching Instant Invoices by Invoice ID, Member ID, or Phone";
    } else {
        $searchWords = explode(' ', $search);
        $invoiceConditions = [];
        foreach ($searchWords as $word) {
            $word = mysqli_real_escape_string($con, $word);
            $invoiceConditions[] = "(name LIKE '%$word%' OR email LIKE '%$word%')";
        }
        $instantInvoiceQuery = "SELECT * FROM billing_invoice_instant
                                WHERE (" . implode(" AND ", $invoiceConditions) . ")
                                ORDER BY invoice_id DESC
                                LIMIT 10";
        $instantInvoiceMessage = "Matching Instant Invoices by Name or Email";
    }
}

if (!empty($instantInvoiceQuery)) {
    $instantInvoiceResult = mysqli_query($con, $instantInvoiceQuery);
    while ($invoiceRow = mysqli_fetch_assoc($instantInvoiceResult)) {
        $instantInvoiceRows[] = $invoiceRow;
    }
}

if (!empty($instantInvoiceRows)) {
    echo '<div style="background:#eef3ff;padding:6px 10px;border-radius:4px;font-size:13px;margin:8px 0;border-left:4px solid #4e73df;">
            <strong>Instant Invoices:</strong> ' . htmlspecialchars($instantInvoiceMessage) . '
          </div>';

    foreach ($instantInvoiceRows as $invoiceRow) {
        $invoiceMatchFields = [];

        if ($searchBy === 'instant_invoice' || $searchBy === 'all') {
            if ($invoiceRow['invoice_id'] == $search) {
                $invoiceMatchFields[] = "Invoice ID";
            }
        }
        if (($searchBy === 'member_id' || $searchBy === 'instant_invoice' || $searchBy === 'all') && $invoiceRow['mem_id'] == $search) {
            $invoiceMatchFields[] = "Member ID";
        }
        if (($searchBy === 'phone' || $searchBy === 'instant_invoice' || $searchBy === 'all')
            && strpos(str_replace('-', '', $invoiceRow['phone']), $search_no_hyphen) !== false) {
            $invoiceMatchFields[] = "Phone";
        }
        if (($searchBy === 'name' || $searchBy === 'instant_invoice' || $searchBy === 'all')
            && stripos($invoiceRow['name'], $search) !== false) {
            $invoiceMatchFields[] = "Name";
        }
        if (($searchBy === 'email' || $searchBy === 'instant_invoice' || $searchBy === 'all')
            && stripos($invoiceRow['email'], $search) !== false) {
            $invoiceMatchFields[] = "Email";
        }

        $invoiceMatchedDisplay = '';
        if (!empty($invoiceMatchFields)) {
            $invoiceMatchedDisplay = '<div style="font-size:12px;color:#555;font-weight:bold;margin-top:3px;">
                Matched On: ' . implode(', ', array_unique($invoiceMatchFields)) . '
            </div>';
        }

        echo '
        <a href="instant_invoice.php" class="dropdown-item d-flex align-items-center" style="padding:8px 10px;">
            <div class="me-3">
                <i class="fas fa-file-invoice" style="font-size:16px;color:#4e73df;"></i>
            </div>
            <div>
                <strong>Instant Invoice #' . htmlspecialchars($invoiceRow['invoice_id']) . '</strong><br>
                <strong>' . htmlspecialchars($invoiceRow['name']) . '</strong>
                <div style="font-size:12px;color:#666;">
                     Phone: ' . htmlspecialchars($invoiceRow['phone']) . '
                </div>
                ' . $invoiceMatchedDisplay . '
            </div>
        </a>';
    }
}

if (empty($allResults) && empty($instantInvoiceRows) && empty($invoiceRows)) {
    echo '<div class="dropdown-item">No results found</div>';
    exit;
}

mysqli_close($con);
?>
