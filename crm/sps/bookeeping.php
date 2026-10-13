<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "includes/activity_log.php";

// ── Current user initials (from full name stored at login) ────────────────
$currentUser     = $_SESSION['fullname'] ?? $_SESSION['username'] ?? '';
$bkUserId        = (int)($_SESSION['user_id'] ?? 0);
$currentInitials = 'ME';
if ($currentUser) {
    $parts = array_values(array_filter(explode(' ', trim($currentUser))));
    $currentInitials = strtoupper(implode('', array_map(fn($p) => $p[0], $parts)));
}

// ── DB readiness check (tables must be created manually) ──────────────────
$dbOk = isset($con) && $con;

// ── Admin gate: only user_type == 1 can edit the sheet ────────────────────
$bkIsAdmin = ((int)($_SESSION['user_type'] ?? 0)) === 1;

// ── AJAX handlers ──────────────────────────────────────────────────────────
if (!empty($_POST['action'])) {
    header('Content-Type: application/json');

    if (!$bkIsAdmin) {
        echo json_encode(['success' => false, 'msg' => 'Only admins can edit the bookkeeping sheet.']);
        exit;
    }

    if ($_POST['action'] === 'create_project') {
        if (!$dbOk) { echo json_encode(['success' => false, 'msg' => 'DB unavailable']); exit; }
        $name        = trim($_POST['name']        ?? '');
        $corp        = trim($_POST['corp']        ?? '');
        $ye          = trim($_POST['year_end']    ?? '');
        $dd          = trim($_POST['due_date']    ?? '');
        $related     = trim($_POST['related']     ?? '');
        $row_class   = trim($_POST['row_class']   ?? 'row-green');
        $john_note   = trim($_POST['john_note']   ?? '');
        $status_text = trim($_POST['status_text'] ?? '');
        $date_note   = trim($_POST['date_note']   ?? '');
        $allowed_classes = ['row-green', 'row-orange', 'row-blue'];
        if (!in_array($row_class, $allowed_classes)) $row_class = 'row-green';
        if ($name === '') { echo json_encode(['success' => false, 'msg' => 'Project name is required']); exit; }
        $stmt = mysqli_prepare($con, "INSERT INTO bk_projects (name, corp_type, year_end, due_date, related, created_by, row_class, john_note, status_text, date_note) VALUES (?,?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssssssssss', $name, $corp, $ye, $dd, $related, $currentUser, $row_class, $john_note, $status_text, $date_note);
        mysqli_stmt_execute($stmt);
        $newId = (int)mysqli_insert_id($con);
        mysqli_stmt_close($stmt);
        logActivity($con, $bkUserId, 'CREATE', 'bookkeeping', $newId,
            "Added bookkeeping row: \"$name\"" . ($corp !== '' ? " (Type: $corp)" : ''));
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $name, 'corp' => $corp, 'ye' => $ye, 'dd' => $dd, 'related' => $related, 'created_by' => $currentUser, 'row_class' => $row_class, 'john_note' => $john_note, 'status_text' => $status_text, 'date_note' => $date_note]);
        exit;
    }

    if ($_POST['action'] === 'toggle_month') {
        if (!$dbOk) { echo json_encode(['success' => false]); exit; }
        $pid  = (int)($_POST['project_id']  ?? 0);
        $midx = (int)($_POST['month_index'] ?? 0);
        $chk  = (int)($_POST['checked']     ?? 0);
        $init = $chk ? $currentInitials : '';
        $stmt = mysqli_prepare($con, "INSERT INTO bk_month_assignments (project_id, month_index, initials, is_checked, checked_by, checked_at)
            VALUES (?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE initials=VALUES(initials), is_checked=VALUES(is_checked), checked_by=VALUES(checked_by), checked_at=VALUES(checked_at)");
        mysqli_stmt_bind_param($stmt, 'iisis', $pid, $midx, $init, $chk, $currentUser);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Activity log — resolve a friendly month + project name for the description
        $monthNames = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        $mName = $monthNames[$midx] ?? ('month ' . ($midx + 1));
        $pName = '';
        if ($pstmt = mysqli_prepare($con, "SELECT name FROM bk_projects WHERE id=?")) {
            mysqli_stmt_bind_param($pstmt, 'i', $pid);
            mysqli_stmt_execute($pstmt);
            mysqli_stmt_bind_result($pstmt, $pName);
            mysqli_stmt_fetch($pstmt);
            mysqli_stmt_close($pstmt);
        }
        $desc = ($chk ? "Marked $mName done" : "Cleared $mName")
              . ' for bookkeeping row "' . $pName . '"';
        logActivity($con, $bkUserId, 'UPDATE', 'bookkeeping', $pid, $desc);

        echo json_encode(['success' => true, 'initials' => $init]);
        exit;
    }

    echo json_encode(['success' => false, 'msg' => 'Unknown action']);
    exit;
}

// ── Load dynamic bookkeeping projects ─────────────────────────────────────
$dynProjects = [];
if ($dbOk) {
    $result = mysqli_query($con, "
        SELECT p.*,
            GROUP_CONCAT(m.month_index   ORDER BY m.month_index SEPARATOR ',') AS m_idx,
            GROUP_CONCAT(IFNULL(m.initials,'') ORDER BY m.month_index SEPARATOR '|') AS m_init,
            GROUP_CONCAT(IFNULL(m.is_checked,0) ORDER BY m.month_index SEPARATOR ',') AS m_chk
        FROM bk_projects p
        LEFT JOIN bk_month_assignments m ON p.id = m.project_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ");
    if ($result) {
        while ($r = mysqli_fetch_assoc($result)) {
            $mData = array_fill(0, 12, ['initials' => '', 'checked' => 0]);
            if ($r['m_idx'] !== null) {
                $idxs  = explode(',', $r['m_idx']);
                $inits = explode('|', $r['m_init']);
                $chks  = explode(',', $r['m_chk']);
                foreach ($idxs as $i => $midx) {
                    $mData[(int)$midx] = ['initials' => $inits[$i] ?? '', 'checked' => (int)($chks[$i] ?? 0)];
                }
            }
            $r['month_data'] = $mData;
            $dynProjects[] = $r;
        }
        mysqli_free_result($result);
    }
}

// ── Distinct users who have checked any bookkeeping month ────────────────
$bkUsers = [];
if ($dbOk) {
    $uq = mysqli_query($con, "
        SELECT TRIM(checked_by) AS name,
               GROUP_CONCAT(DISTINCT project_id) AS pids
        FROM bk_month_assignments
        WHERE is_checked = 1
          AND checked_by IS NOT NULL
          AND TRIM(checked_by) <> ''
        GROUP BY TRIM(checked_by)
        ORDER BY name ASC
    ");
    if ($uq) {
        while ($u = mysqli_fetch_assoc($uq)) {
            $name  = $u['name'];
            $parts = array_values(array_filter(explode(' ', $name)));
            $init  = strtoupper(implode('', array_map(fn($p) => $p[0], $parts)));
            $pidList = array_values(array_unique(array_filter(array_map('intval', explode(',', $u['pids'] ?? '')))));
            $bkUsers[] = [
                'name'     => $name,
                'initials' => $init !== '' ? $init : strtoupper(substr($name, 0, 1)),
                'pids'     => $pidList,
            ];
        }
        mysqli_free_result($uq);
    }
}

// ── Static sheet data ──────────────────────────────────────────────────────
$sheetRows = [
    [
        'sno' => '1', 'corp' => '1065', 'name' => '21st TRADING LLC', 'row_class' => 'row-orange',
        'months' => ['DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'SR', 'SR', 'SR', 'SR', 'SR'],
        'year_end' => '31-Dec', 'due_date' => '15-Mar',
        'related' => 'spoke to Mr Multani. he said you no need to do any bookkeeping because we did not do any transaction in this account',
        'john' => 'No Answer', 'date_note' => '', 'status' => 'LOGIN'
    ],
   //  [
   //      'sno' => '2', 'corp' => 'C', 'name' => '', 'row_class' => 'row-blue',
   //      'months' => ['closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed'],
   //      'year_end' => '30-Sep', 'due_date' => '15-Jan',
   //      'related' => 'email sent... 10-20-2025', 'john' => '', 'status' => ''
   //  ],
   //  [
   //      'sno' => '3', 'corp' => 'C', 'name' => '5BS CONSTRUCTION CORP', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'SR', 'SR', 'SR', 'SR', 'SR', 'SR', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025 <span class="blue-text">WCI</span>', 'john' => 'Business is closed', 'status' => 'CLOSED'
   //  ],
   //  [
   //      'sno' => '4', 'corp' => 'C', 'name' => '79TH ST LUNCHEONETTE CORP.', 'row_class' => 'row-orange',
   //      'name_class' => 'strike-text',
   //      'months' => ['MP', 'MP', 'MP', 'MP', 'AP', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed', 'closed'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'CLOSED BUSINESS', 'john' => 'He will send', 'date_note' => '5-Jan', 'status' => 'NO'
   //  ],
   //  [
   //      'sno' => '5', 'corp' => '1065', 'name' => '826 ROGERS AVE LLC', 'row_class' => 'row-green',
   //      'months' => ['DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'FG'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025 (ACI+ADD)', 'john' => '', 'date_note' => '20-Jan', 'status' => 'EMA'
   //  ],
   //  [
   //      'sno' => '6', 'corp' => 'C', 'name' => '1069 CONEY ISLAND AVE CORP', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'DJ', 'DJ', 'DJ', 'DJ', 'FG', 'FG', 'FG'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025 <span class="blue-text">WCI</span>', 'john' => '', 'date_note' => '19-Jan', 'status' => 'EMA'
   //  ],
   //  [
   //      'sno' => '7', 'corp' => 'INDIVIDUAL', 'name' => 'A & A IRON WORK', 'row_class' => 'row-green',
   //      'months' => ['NN', 'NN', 'NN', 'NN', 'NN', 'NN', 'NN', 'NN', 'SR', 'SR', 'SR', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025-<span class="blue-text">WCI</span>', 'john' => 'Scheduled 17-Feb-2025', 'date_note' => '', 'status' => 'EMA'
   //  ],
   //  [
   //      'sno' => '8', 'corp' => 'C', 'name' => 'A.M CONSTRUCTION BUILDERS', 'row_class' => 'row-orange',
   //      'months' => ['NEV', 'ER', 'ANS', 'VER', 'CALL', '', '', '', '', '', '', ''],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'NO BANK STATEMENTS', 'john' => '', 'date_note' => '20-Jan', 'status' => 'NO'
   //  ],
   //  [
   //      'sno' => '9', 'corp' => 'C', 'name' => 'AACUSTOMIZEGOLD INC', 'row_class' => 'row-green',
   //      'months' => ['MP', 'MP', 'MM', 'MM', 'MM', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025 - (ACI+ADD) <span class="red-text">SOME CHECKS MISS</span>', 'john' => '', 'date_note' => '26-Jan', 'status' => 'NO ANS/EMA'
   //  ],
   //  [
   //      'sno' => '10', 'corp' => 'S', 'name' => 'AMAN SPORTWARE INC', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025 - (ACI+ADD)', 'john' => '', 'date_note' => '4-Mar', 'status' => 'LOGIN/202'
   //  ],
   //  [
   //      'sno' => '11', 'corp' => '1065', 'name' => 'ANDY & CO LLC', 'row_class' => 'row-green',
   //      'months' => ['DJ', 'DJ', 'DJ', 'DJ', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025-(ACI+ADD)', 'john' => '', 'date_note' => '3-Feb', 'status' => 'NO ANS/SMS'
   //  ],
   //  [
   //      'sno' => '12', 'corp' => 'C', 'name' => 'ANWAR PARTY RENTALS AND OTHER SERVICES INC.', 'row_class' => 'row-green',
   //      'months' => ['DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'FG'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025-<span class="blue-text">WCI</span>', 'john' => '', 'date_note' => '5-Jan', 'status' => 'NO'
   //  ],
   //  [
   //      'sno' => '13', 'corp' => 'C', 'name' => 'ASHI TRADING', 'row_class' => 'row-orange',
   //      'months' => ['NO', 'RE', 'CO', 'RD', '', '', '', '', '', '', '', ''],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'NO RECORD - AVAILABLE TILL 2021', 'john' => '', 'date_note' => '', 'status' => ''
   //  ],
   //  [
   //      'sno' => '14', 'corp' => 'LLC', 'name' => 'ASU WHOLESALE LLC', 'row_class' => 'row-green',
   //      'months' => ['MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'MP', 'DJ', 'DJ', 'DJ', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'Till Dec 2025-(ACI+ADD) <span class="red-text">ONLY MISS (OCT-NOV-DEC)</span>', 'john' => '', 'date_note' => '11-Feb', 'status' => 'ANS/SMS/2'
   //  ],
   //  [
   //      'sno' => '15', 'corp' => 'C', 'name' => 'B B & A CONTRACTING INC.', 'row_class' => 'row-green',
   //      'months' => ['DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025-(ACI+ADD)', 'john' => '', 'date_note' => '21-Feb', 'status' => ''
   //  ],
   //  [
   //      'sno' => '16', 'corp' => 'S', 'name' => 'B & C RECORD & COSEMETIC CORPORATION', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'DJ', 'DJ', 'DJ', 'SR'],
   //      'year_end' => '31-Aug', 'due_date' => '15-Dec',
   //      'related' => 'TILL DEC 2025 - (ACI+ADD)', 'john' => '', 'date_note' => '20-Jan', 'status' => 'LOGIN'
   //  ],
   //  [
   //      'sno' => '17', 'corp' => 'C', 'name' => 'BASAK BAKERY INC', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'SR', 'SR', 'SR', 'SR', 'SR', 'SR', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025 - (ACI+ADD)', 'john' => '', 'date_note' => '3-Feb', 'status' => 'NO ANS/SMS'
   //  ],
   //  [
   //      'sno' => '18', 'corp' => 'C', 'name' => '', 'row_class' => 'row-orange',
   //      'months' => ['NO', 'RE', 'CO', 'RD', '', '', '', '', '', '', '', ''],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => '', 'john' => 'have login already will download', 'date_note' => '', 'status' => 'CLOSED'
   //  ],
   //  [
   //      'sno' => '19', 'corp' => 'S', 'name' => 'BDB CONSTRUCTION ENTERPRISE', 'row_class' => 'row-green',
   //      'months' => ['D', 'D', 'D', 'D', 'D', 'D', 'SR', 'SR', 'SR', 'SR', 'SR', 'SR'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'TILL DEC 2025-(ACI+ADD) <span class="red-text">WIP</span>', 'john' => '', 'date_note' => '9-Feb', 'status' => 'ANS/SMS/8'
   //  ],
   //  [
   //      'sno' => '20', 'corp' => 'C', 'name' => 'BEST BUSINESS GROUP', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'ZA', 'DJ', 'DJ', 'DJ', 'DJ'],
   //      'year_end' => '31-Dec', 'due_date' => '15-Apr',
   //      'related' => 'TILL DEC 2025 (ACI+ADD)', 'john' => '', 'date_note' => '31-Jan', 'status' => 'ANS/SMS/2'
   //  ],
   //  [
   //      'sno' => '21', 'corp' => 'C', 'name' => 'BEST LINE CONSTRUCTION CORP', 'row_class' => 'row-orange',
   //      'months' => ['NEV', 'ER', 'ANS', 'WER', 'CALL', '', '', '', '', '', '', ''],
   //      'year_end' => '31-Dec', 'due_date' => '15-Mar',
   //      'related' => 'NO RECORD', 'john' => '', 'date_note' => '19-Jan', 'status' => 'NO'
   //  ],
   //  [
   //      'sno' => '22', 'corp' => 'C', 'name' => 'BILL SERVICES INC', 'row_class' => 'row-green',
   //      'months' => ['ZA', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ', 'DJ'],
   //      'year_end' => '31-Mar', 'due_date' => '15-Jul',
   //      'related' => 'Till Dec 2025 <span class="red-text">MCI</span>', 'john' => '', 'date_note' => '5-Jan', 'status' => 'SHE'
   //  ],
];

foreach ($sheetRows as &$sheetRow) {
    if (!isset($sheetRow['date_note']))  $sheetRow['date_note']  = '';
    if (!isset($sheetRow['name_class'])) $sheetRow['name_class'] = '';
}
unset($sheetRow);

$emptyRows = 2;
$months    = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Bookkeeping 2025 Sheet</title>
    <?php include "includes/links.php"; ?>
    <style>
        body.theme-3 { background:#fff; }
        .sheet-shell  { background:#fff; padding:10px 12px 18px; }
        .sheet-scroll { overflow:auto; border:1px solid #d9d9d9; }

        .book-sheet {
            min-width: 1940px;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Calibri, Arial, sans-serif;
            font-size: 11px;
            color: #2e3540;
            background: #fff;
        }
        .book-sheet th, .book-sheet td {
            border: 1px dotted #595959;
            padding: 2px 3px;
            vertical-align: top;
            line-height: 1.1;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .book-sheet .solid-cell   { border: 1px solid #3b3b3b; }
        .book-sheet tbody td      { height: 20px; }
        .book-sheet .title-row td { border: none; padding: 10px 6px 8px; font-weight: 700; }

        .back-link       { font-size:17px; color:#1060b6; text-decoration:none; letter-spacing:.4px; }
        .back-link:hover { color:#0a4b8f; text-decoration:underline; }

        .legends-title { font-size:15px; text-align:center; color:#33485f; font-weight:700; }
        .legend-cell   { border:none !important; padding:2px 4px !important; white-space:normal !important; }
        .legend-box    { display:inline-block; width:34px; height:22px; border:1px dashed #4f4f4f;
                         background:linear-gradient(135deg,#fff 0 50%,#ff9d9d 50% 100%); vertical-align:middle; margin-right:6px; }

        .note-red    { color:#d42828; font-weight:700; }
        .year-box    { text-align:center; color:#e28d45; font-size:16px; font-weight:700; border:1px solid #3b3b3b !important; }
        .small-head  { color:#b7782d; font-weight:700; text-align:center; font-size:10px; }
        .person-head { text-align:center; font-weight:700; color:#2b3e55; font-size:11px; }
        .group-head  { text-align:center; font-weight:700; color:#4a4a4a; }
        .group-head .red-big { color:#d72323; font-size:12px; }

        .col-sno     { width:44px;  text-align:center; }
        .col-corp    { width:72px;  text-align:center; }
        .col-name    { width:270px; }
        .month-col   { width:40px;  text-align:center; }
        .col-year    { width:58px;  text-align:center; }
        .col-due     { width:72px;  text-align:center; }
        .col-comment { width:410px; }
        .col-john    { width:185px; }
        .col-date    { width:82px;  text-align:center; }
        .col-status  { width:110px; text-align:center; }

        .center { text-align:center; }
        .bold   { font-weight:700; }
        .muted  { color:#6c737c; }
        .blue-text { color:#0d71c7; font-weight:700; }
        .red-text  { color:#d32020; font-weight:700; }

        .subheader th { text-align:center; font-weight:700; color:#515b66; font-size:10px; vertical-align:middle; }

        .row-green  td.name-cell, .row-green  td.type-cell { background:#cfe5b3; }
        .row-orange td.name-cell, .row-orange td.type-cell { background:#f7b08e; }
        .row-blue   td.name-cell, .row-blue   td.type-cell { background:#8fd7ee; }

        .comment-cyan { background:#bdf5ef; }
        .status-red   { color:#d72626; }
        .right-comment-head { font-size:9px; }
        .main-grid-empty td { height:15px; }
        .comment-cell, .john-cell, .date-cell, .status-cell { vertical-align:top; font-size:10.5px; }
        .date-cell   { text-align:center; }
        .strike-text { text-decoration:line-through; color:#5f5f5f; }
        .sheet-footer-space td { border:none; height:10px; }

        /* Inline month checkboxes in dynamic rows */
        .mchk-wrap { display:flex; flex-direction:column; align-items:center; gap:0; cursor:pointer; }
        .mchk-wrap input[type=checkbox] { width:11px; height:11px; margin:0; cursor:pointer; accent-color:#2a8a3e; }
        .mchk-init { font-size:9px; font-weight:700; color:#1a6e30; min-height:9px; line-height:1; display:block; }

        /* Input row */
        #bk-input-row td {
            background: #fffff0 !important;
            height: auto !important;
            overflow: visible !important;
            white-space: normal !important;
            vertical-align: top;
            padding: 2px 2px;
        }
        #bk-input-row td.name-cell,
        #bk-input-row td.type-cell { background: #fffff0 !important; }

        .cell-inp {
            width: 100%;
            box-sizing: border-box;
            font-family: Calibri, Arial, sans-serif;
            font-size: 10px;
            color: #2e3540;
            border: 1px solid #b0c0d0;
            padding: 1px 2px;
            background: #fffff8;
            outline: none;
            display: block;
        }
        .cell-inp:focus { border-color:#3a7bd5; background:#fff; }
        select.cell-inp { padding:0 1px; }

        .cell-save-btn {
            display: block;
            width: 100%;
            margin-top: 2px;
            background: #2563b0;
            color: #fff;
            border: none;
            padding: 3px 2px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            font-family: Calibri, Arial, sans-serif;
        }
        .cell-save-btn:hover    { background:#1a4d8f; }
        .cell-save-btn:disabled { background:#8aacce; cursor:not-allowed; }

        .color-swatch { display:inline-block; width:11px; height:9px; border:1px solid #999; vertical-align:middle; }

        /* Toolbar */
        .bk-toolbar {
            display:flex; align-items:center; justify-content:space-between;
            margin-bottom:6px; font-family:Calibri,Arial,sans-serif;
        }
        .bk-toggle-lbl {
            display:flex; align-items:center; gap:6px; cursor:pointer;
            font-size:13px; font-weight:700; color:#1a4d8f;
            background:#eaf3ff; border:1.5px solid #5a9bdd;
            border-radius:4px; padding:4px 12px; user-select:none;
        }
        .bk-toggle-lbl input { width:15px; height:15px; cursor:pointer; accent-color:#2563b0; }
        #bk-msg { font-family:Calibri,Arial,sans-serif; font-size:12px; font-weight:700; }
        #bk-msg.ok  { color:#1a7a3e; }
        #bk-msg.err { color:#c0392b; }
        .btn-export {
            background:#1a7a3e; color:#fff; border:none; border-radius:4px;
            padding:5px 14px; font-size:12px; font-weight:700; cursor:pointer;
            font-family:Calibri,Arial,sans-serif;
        }
        .btn-export:hover { background:#145f30; }

        /* User-initials filter bar */
        .bk-user-chips {
            display:flex; flex-wrap:wrap; align-items:center; gap:6px;
            margin-bottom:6px; padding:5px 8px;
            background:#f5f9ff; border:1px solid #cfdcec; border-radius:4px;
            font-family:Calibri,Arial,sans-serif;
        }
        .bk-user-chips-label {
            font-size:12px; font-weight:700; color:#1a4d8f; margin-right:4px;
        }
        .bk-user-chip {
            display:inline-flex; align-items:center; justify-content:center;
            min-width:28px; height:24px; padding:0 9px;
            background:#fff; color:#1a4d8f;
            border:1.5px solid #b9c8db; border-radius:14px;
            font-size:11px; font-weight:700;
            cursor:pointer; user-select:none;
            transition:background .15s ease, border-color .15s ease, transform .15s ease;
        }
        .bk-user-chip:hover {
            background:#eaf3ff; border-color:#5a9bdd; transform:translateY(-1px);
        }
        .bk-user-chip.active {
            color:#fff; border-color:transparent;
            animation:bkChipPulse 1.6s ease-in-out infinite;
        }
        @keyframes bkChipPulse {
            0%   { background:#2563b0; box-shadow:0 0 0 0 rgba(37,99,176,.55); }
            50%  { background:#1a7a3e; box-shadow:0 0 0 7px rgba(26,122,62,0); }
            100% { background:#2563b0; box-shadow:0 0 0 0 rgba(37,99,176,0); }
        }
        .bk-chip-clear {
            background:#f1f1f1; color:#555; border-color:#ccc;
        }
        .bk-chip-clear:hover {
            background:#e0e0e0; border-color:#aaa;
        }
        .bk-filter-info {
            margin-left:auto; font-size:11px; color:#1a4d8f; font-weight:700;
        }
    </style>
</head>
<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track"><div class="loader-fill"></div></div>
    </div>

    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="sheet-shell">

                <!-- Toolbar (read-only sheet) -->
                <div class="bk-toolbar">
                    <span style="font-size:13px;font-weight:700;color:#1a4d8f;background:#eaf3ff;border:1.5px solid #5a9bdd;border-radius:4px;padding:4px 12px;">
                        &#128274; Bookkeeping &mdash; Review Only
                    </span>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span id="bk-msg"></span>
                        <button class="btn-export" onclick="bkExportCSV()">&#8659; Export CSV</button>
                    </div>
                </div>

                <!-- User-initials filter bar -->
                <?php if (!empty($bkUsers)): ?>
                <div class="bk-user-chips" id="bk-user-chips">
                    <span class="bk-user-chips-label">Bookkeeping Initials:</span>
                    <?php foreach ($bkUsers as $u): ?>
                    <span class="bk-user-chip"
                          data-pids="<?php echo htmlspecialchars(implode(',', $u['pids'])); ?>"
                          data-name="<?php echo htmlspecialchars($u['name']); ?>"
                          title="<?php echo htmlspecialchars($u['name']); ?> — <?php echo count($u['pids']); ?> project<?php echo count($u['pids']) === 1 ? '' : 's'; ?>"
                          onclick="bkToggleUserFilter(this)">
                        <?php echo htmlspecialchars($u['initials']); ?>
                    </span>
                    <?php endforeach; ?>
                    <span class="bk-user-chip bk-chip-clear" id="bk-chip-clear" style="display:none;" onclick="bkClearUserFilter()" title="Show all rows">&#10005; Clear</span>
                    <span class="bk-filter-info" id="bk-filter-info"></span>
                </div>
                <?php endif; ?>

                <!-- ONE unified spreadsheet table -->
                <div class="sheet-scroll">
                    <table class="book-sheet" id="bk-sheet-table">
                        <colgroup>
                            <col class="col-sno">
                            <col class="col-corp">
                            <col class="col-name">
                            <?php foreach ($months as $m): ?><col class="month-col"><?php endforeach; ?>
                            <col class="col-year">
                            <col class="col-due">
                            <col class="col-comment">
                            <col class="col-john">
                            <col class="col-date">
                            <col class="col-status">
                        </colgroup>

                        <!-- Legend / title rows -->
                        <tr class="title-row">
                            <td colspan="3"><a class="back-link" href="index.php">BACK TO MAIN PAGE</a></td>
                            <td colspan="3"></td>
                            <td colspan="4" class="legends-title">LEGENDS</td>
                            <td colspan="8" class="legend-cell">
                                <span class="legend-box"></span> = <span class="muted">Some Banks Statements are Pending</span>
                            </td>
                            <td colspan="5"></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="border:none;"></td>
                            <td colspan="3" style="border:none;"></td>
                            <td colspan="3" class="year-box">2025</td>
                            <td colspan="4" class="small-head">BOOKKEEPER'S INITIALS</td>
                            <td colspan="5" class="small-head">USMAN PERVAIZ</td>
                            <td colspan="6" class="person-head">Muhammad Motasim&nbsp;&nbsp;MM</td>
                            <td colspan="4" class="person-head">John Guerriero (JG)</td>
                        </tr>
                        <tr>
                            <td colspan="10" style="border:none;"></td>
                            <td colspan="7" class="legend-cell">= <span class="note-red">Notes added</span> "Keep Cursor on red note"</td>
                            <td colspan="8" style="border:none;"></td>
                        </tr>
                        <tr>
                            <td colspan="16" class="group-head solid-cell">MONTH WISE BOOKKEEPING STATUS</td>
                            <td class="solid-cell center bold" rowspan="2">Year End</td>
                            <td class="solid-cell center bold" rowspan="2">Due Date</td>
                            <td class="solid-cell group-head" rowspan="2">BOOKKEEPING &amp; BANK STATEMENTS RELATED <span class="red-big">(COMMENTS)</span></td>
                            <td class="solid-cell group-head right-comment-head" rowspan="2">NG &amp; BANK STATEMENTS RELATED <span class="red-big">(COMMENTS)</span></td>
                            <td class="solid-cell group-head" rowspan="2"><span class="red-big">DATE</span> ETC</td>
                            <td class="solid-cell group-head" rowspan="2"></td>
                        </tr>
                        <tr class="subheader">
                            <th class="solid-cell">S.No</th>
                            <th class="solid-cell">Corporation<br>Type</th>
                            <th class="solid-cell">Business Name</th>
                            <?php foreach ($months as $m): ?><th class="solid-cell"><?php echo $m; ?></th><?php endforeach; ?>
                        </tr>

                        <!-- Empty rows -->
                        <?php for ($i = 0; $i < $emptyRows; $i++): ?>
                        <tr class="main-grid-empty bk-filter-hide">
                            <td>&nbsp;</td><td></td><td></td>
                            <?php foreach ($months as $m): ?><td></td><?php endforeach; ?>
                            <td class="center bold">31-Dec</td><td class="center bold">15-Mar</td>
                            <td></td><td></td><td></td><td></td>
                        </tr>
                        <?php endfor; ?>

                        <!-- Static rows (exact same as always) -->
                        <?php foreach ($sheetRows as $idx => $row): ?>
                        <tr class="<?php echo $row['row_class']; ?> bk-filter-hide">
                            <td class="center bold"><?php echo htmlspecialchars($row['sno']); ?></td>
                            <td class="center bold type-cell"><?php echo htmlspecialchars($row['corp']); ?></td>
                            <td class="name-cell bold <?php echo htmlspecialchars($row['name_class']); ?>"><?php echo htmlspecialchars($row['name']); ?></td>
                            <?php foreach ($row['months'] as $mv): ?>
                            <td class="center" style="vertical-align:middle;"><?php echo htmlspecialchars($mv); ?></td>
                            <?php endforeach; ?>
                            <td class="center bold" style="vertical-align:middle;"><?php echo htmlspecialchars($row['year_end']); ?></td>
                            <td class="center bold" style="vertical-align:middle;"><?php echo htmlspecialchars($row['due_date']); ?></td>
                            <td class="comment-cell <?php echo in_array($idx,[8,13,21],true)?'comment-cyan':''; ?>"><?php echo $row['related']; ?></td>
                            <td class="john-cell"><?php echo htmlspecialchars($row['john']); ?></td>
                            <td class="date-cell"><?php echo htmlspecialchars($row['date_note']); ?></td>
                            <td class="status-cell center bold <?php echo in_array($row['status'],['CLOSED','NO'],true)?'status-red':''; ?>"><?php echo htmlspecialchars($row['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- Dynamic rows from DB — same structure + inline month checkboxes -->
                        <?php
                        $staticCount = count($sheetRows);
                        foreach ($dynProjects as $di => $proj):
                            $sno       = $staticCount + $di + 1;
                            $rowClass  = $proj['row_class']   ?? 'row-green';
                            $johnNote  = $proj['john_note']   ?? '';
                            $statusTxt = $proj['status_text'] ?? '';
                            $dateNote  = $proj['date_note']   ?? '';
                        ?>
                        <tr class="<?php echo htmlspecialchars($rowClass); ?> bk-dyn-row" id="sheet-row-<?php echo $proj['id']; ?>" data-pid="<?php echo (int)$proj['id']; ?>">
                            <td class="center bold"><?php echo $sno; ?></td>
                            <td class="center bold type-cell"><?php echo htmlspecialchars($proj['corp_type']); ?></td>
                            <td class="name-cell bold"><?php echo htmlspecialchars($proj['name']); ?></td>
                            <?php foreach ($months as $mi => $mLabel):
                                $mInfo    = $proj['month_data'][$mi];
                                $isChk    = $mInfo['checked'];
                                $initials = htmlspecialchars($mInfo['initials']);
                            ?>
                            <td class="center" style="vertical-align:middle;padding:1px;" id="sc_<?php echo $proj['id'].'_'.$mi; ?>">
                                <label class="mchk-wrap" style="cursor:<?php echo $bkIsAdmin ? 'pointer' : 'default'; ?>;">
                                    <?php if ($bkIsAdmin): ?>
                                    <input type="checkbox"
                                           data-pid="<?php echo (int)$proj['id']; ?>"
                                           data-midx="<?php echo (int)$mi; ?>"
                                           onchange="bkToggleMonth(this)"
                                           <?php echo $isChk?'checked':''; ?>>
                                    <?php else: ?>
                                    <input type="checkbox"
                                           disabled
                                           <?php echo $isChk?'checked':''; ?>
                                           style="cursor:not-allowed;">
                                    <?php endif; ?>
                                    <span class="mchk-init" id="init_<?php echo $proj['id'].'_'.$mi; ?>"><?php echo $initials; ?></span>
                                </label>
                            </td>
                            <?php endforeach; ?>
                            <td class="center bold" style="vertical-align:middle;"><?php echo htmlspecialchars($proj['year_end']); ?></td>
                            <td class="center bold" style="vertical-align:middle;"><?php echo htmlspecialchars($proj['due_date']); ?></td>
                            <td class="comment-cell"><?php echo htmlspecialchars($proj['related']); ?></td>
                            <td class="john-cell"><?php echo htmlspecialchars($johnNote); ?></td>
                            <td class="date-cell"><?php echo htmlspecialchars($dateNote); ?></td>
                            <td class="status-cell center bold <?php echo in_array($statusTxt,['CLOSED','NO'],true)?'status-red':''; ?>"><?php echo htmlspecialchars($statusTxt); ?></td>
                        </tr>
                        <?php endforeach; ?>

                        <!-- New-project input row — visible for admins only -->
                        <tr id="bk-input-row" style="display:<?php echo $bkIsAdmin ? 'table-row' : 'none'; ?>;">
                            <td class="center bold" style="color:#1060b6;font-size:9px;vertical-align:middle;">+NEW</td>
                            <td class="type-cell" style="overflow:visible;">
                                <select id="bk-corp" class="cell-inp" onchange="bkCorpChange()">
                                    <option value="">Type</option>
                                    <option value="S-Corp">S-Corp</option>
                                    <option value="C-Corp">C-Corp</option>
                                    <option value="Sole LLC">Sole LLC</option>
                                    <option value="LLC">LLC</option>
                                    <option value="__manual__">Add Manually</option>
                                </select>
                                <input type="text" id="bk-corp-manual" class="cell-inp" placeholder="Custom type" style="display:none;margin-top:2px;">
                            </td>
                            <td class="name-cell" style="overflow:visible;">
                                <input type="text" id="bk-name" class="cell-inp" placeholder="Business Name *">
                                <div style="display:flex;gap:3px;margin-top:2px;align-items:center;">
                                    <label style="cursor:pointer;display:flex;align-items:center;gap:1px;" title="Green">
                                        <input type="radio" name="bk-rowcolor" value="row-green" checked style="width:9px;height:9px;margin:0;">
                                        <span class="color-swatch" style="background:#cfe5b3;"></span>
                                    </label>
                                    <label style="cursor:pointer;display:flex;align-items:center;gap:1px;" title="Orange">
                                        <input type="radio" name="bk-rowcolor" value="row-orange" style="width:9px;height:9px;margin:0;">
                                        <span class="color-swatch" style="background:#f7b08e;"></span>
                                    </label>
                                    <label style="cursor:pointer;display:flex;align-items:center;gap:1px;" title="Blue">
                                        <input type="radio" name="bk-rowcolor" value="row-blue" style="width:9px;height:9px;margin:0;">
                                        <span class="color-swatch" style="background:#8fd7ee;"></span>
                                    </label>
                                </div>
                            </td>
                            <?php for ($mi = 0; $mi < 12; $mi++): ?><td></td><?php endfor; ?>
                            <td style="overflow:visible;"><input type="text" id="bk-yearend" class="cell-inp" placeholder="31-Dec"></td>
                            <td style="overflow:visible;"><input type="text" id="bk-duedate" class="cell-inp" placeholder="15-Mar"></td>
                            <td class="comment-cell" style="overflow:visible;"><input type="text" id="bk-related" class="cell-inp" placeholder="BK &amp; Bank Comments..."></td>
                            <td class="john-cell" style="overflow:visible;"><input type="text" id="bk-john" class="cell-inp" placeholder="NG Notes..."></td>
                            <td class="date-cell" style="overflow:visible;"><input type="text" id="bk-datenote" class="cell-inp" placeholder="Date"></td>
                            <td class="status-cell" style="overflow:visible;">
                                <input type="text" id="bk-status" class="cell-inp" placeholder="Status">
                                <button id="btn-bk-create" class="cell-save-btn" onclick="bkSaveProject()">+ Add Row</button>
                            </td>
                        </tr>

                        <tr class="sheet-footer-space"><td colspan="22"></td></tr>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
    const BK_INITIALS   = <?php echo json_encode($currentInitials); ?>;
    const MONTHS_LABELS = <?php echo json_encode($months); ?>;
    const STATIC_COUNT  = <?php echo count($sheetRows); ?>;

    // Read-only sheet: no toolbar toggle, input row is hidden by default.

    function bkCorpChange() {
        const sel = document.getElementById('bk-corp');
        const txt = document.getElementById('bk-corp-manual');
        if (sel.value === '__manual__') {
            txt.style.display = 'block';
            txt.focus();
        } else {
            txt.style.display = 'none';
            txt.value = '';
        }
    }

    async function bkSaveProject() {
        const name = document.getElementById('bk-name').value.trim();
        if (!name) { showMsg('Client name is required.', false); return; }

        const corpSel    = document.getElementById('bk-corp');
        const corpManual = document.getElementById('bk-corp-manual').value.trim();
        let corpValue    = corpSel.value;
        if (corpValue === '__manual__') {
            if (!corpManual) { showMsg('Please enter the business name.', false); return; }
            corpValue = corpManual;
        }

        const btn = document.getElementById('btn-bk-create');
        btn.disabled = true; btn.textContent = 'Saving…';

        const fd = new FormData();
        fd.append('action',      'create_project');
        fd.append('name',        name);
        fd.append('corp',        corpValue);
        fd.append('year_end',    document.getElementById('bk-yearend').value.trim());
        fd.append('due_date',    document.getElementById('bk-duedate').value.trim());
        fd.append('related',     document.getElementById('bk-related').value.trim());
        fd.append('row_class',   document.querySelector('input[name="bk-rowcolor"]:checked').value);
        fd.append('john_note',   document.getElementById('bk-john').value.trim());
        fd.append('status_text', document.getElementById('bk-status').value.trim());
        fd.append('date_note',   document.getElementById('bk-datenote').value.trim());

        try {
            const res  = await fetch(window.location.href, {method:'POST', body:fd});
            const data = await res.json();
            if (data.success) {
                showMsg('Added: ' + data.name, true);
                injectRow(data);
                ['bk-name','bk-yearend','bk-duedate','bk-related','bk-john','bk-datenote','bk-status']
                    .forEach(id => document.getElementById(id).value = '');
                document.getElementById('bk-corp').value = '';
                document.getElementById('bk-corp-manual').value = '';
                document.getElementById('bk-corp-manual').style.display = 'none';
                document.querySelector('input[name="bk-rowcolor"][value="row-green"]').checked = true;
            } else { showMsg(data.msg || 'Error.', false); }
        } catch(e) { showMsg('Network error.', false); }

        btn.disabled = false; btn.textContent = '+ Add Row';
    }

    function injectRow(data) {
        const inputRow = document.getElementById('bk-input-row');
        const newRow   = document.createElement('tr');
        newRow.className = (data.row_class || 'row-green') + ' bk-dyn-row';
        newRow.id        = 'sheet-row-' + data.id;
        newRow.dataset.pid = data.id;

        const sno = STATIC_COUNT + document.querySelectorAll('[id^="sheet-row-"]').length;
        const sRed = ['CLOSED','NO'].includes((data.status_text||'').toUpperCase()) ? ' status-red' : '';

        let cells = `<td class="center bold">${sno}</td>
            <td class="center bold type-cell">${esc(data.corp)}</td>
            <td class="name-cell bold">${esc(data.name)}</td>`;
        MONTHS_LABELS.forEach((lbl, mi) => {
            cells += `<td class="center" style="vertical-align:middle;padding:1px;" id="sc_${data.id}_${mi}">
                <label class="mchk-wrap">
                    <input type="checkbox" data-pid="${data.id}" data-midx="${mi}" onchange="bkToggleMonth(this)">
                    <span class="mchk-init" id="init_${data.id}_${mi}"></span>
                </label></td>`;
        });
        cells += `<td class="center bold" style="vertical-align:middle;">${esc(data.ye)}</td>
            <td class="center bold" style="vertical-align:middle;">${esc(data.dd)}</td>
            <td class="comment-cell">${esc(data.related)}</td>
            <td class="john-cell">${esc(data.john_note)}</td>
            <td class="date-cell">${esc(data.date_note)}</td>
            <td class="status-cell center bold${sRed}">${esc(data.status_text)}</td>`;
        newRow.innerHTML = cells;
        inputRow.parentNode.insertBefore(newRow, inputRow);
    }

    async function bkToggleMonth(chk) {
        const pid    = chk.dataset.pid;
        const midx   = chk.dataset.midx;
        const chkd   = chk.checked ? 1 : 0;
        const initEl = document.getElementById('init_' + pid + '_' + midx);
        initEl.textContent = chkd ? BK_INITIALS : '';

        const fd = new FormData();
        fd.append('action',      'toggle_month');
        fd.append('project_id',  pid);
        fd.append('month_index', midx);
        fd.append('checked',     chkd);

        try {
            const res  = await fetch(window.location.href, {method:'POST', body:fd});
            const data = await res.json();
            if (!data.success) {
                chk.checked = !chk.checked;
                initEl.textContent = chkd ? '' : BK_INITIALS;
            }
        } catch(e) {
            chk.checked = !chk.checked;
            initEl.textContent = chkd ? '' : BK_INITIALS;
        }
    }

    function bkExportCSV() {
        const lines = [];
        document.querySelectorAll('#bk-sheet-table tr:not(#bk-input-row)').forEach(tr => {
            const vals = [];
            tr.querySelectorAll('th,td').forEach(cell => {
                let txt = cell.innerText.replace(/\n/g,' ').trim();
                if (txt.includes(',') || txt.includes('"')) txt = '"' + txt.replace(/"/g,'""') + '"';
                vals.push(txt);
            });
            if (vals.some(v => v !== '')) lines.push(vals.join(','));
        });
        const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), {href:url, download:'Bookkeeping_2025.csv'});
        document.body.appendChild(a); a.click();
        document.body.removeChild(a); URL.revokeObjectURL(url);
    }
 
    function showMsg(msg, ok) {
        const el = document.getElementById('bk-msg');
        el.textContent = msg; el.className = ok ? 'ok' : 'err';
        setTimeout(() => { el.textContent = ''; el.className = ''; }, 5000);
    }
 
    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function bkToggleUserFilter(chip) {
        const wasActive = chip.classList.contains('active');
        document.querySelectorAll('.bk-user-chip').forEach(c => c.classList.remove('active'));
        if (wasActive) { bkClearUserFilter(); return; }

        chip.classList.add('active');
        const pidSet = new Set((chip.dataset.pids || '').split(',').filter(Boolean));

        document.querySelectorAll('.bk-dyn-row').forEach(row => {
            row.style.display = pidSet.has(row.dataset.pid) ? '' : 'none';
        });
        document.querySelectorAll('.bk-filter-hide').forEach(row => {
            row.style.display = 'none';
        });
        const inputRow = document.getElementById('bk-input-row');
        if (inputRow) inputRow.style.display = 'none';

        document.getElementById('bk-chip-clear').style.display = 'inline-flex';
        const info = document.getElementById('bk-filter-info');
        if (info) info.textContent = 'Filtered by ' + chip.dataset.name + ' (' + pidSet.size + ')';
    }

    function bkClearUserFilter() {
        document.querySelectorAll('.bk-user-chip').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.bk-dyn-row').forEach(row => row.style.display = '');
        document.querySelectorAll('.bk-filter-hide').forEach(row => row.style.display = '');
        const inputRow = document.getElementById('bk-input-row');
        if (inputRow && <?php echo $bkIsAdmin ? 'true' : 'false'; ?>) inputRow.style.display = '';
        const clearBtn = document.getElementById('bk-chip-clear');
        if (clearBtn) clearBtn.style.display = 'none';
        const info = document.getElementById('bk-filter-info');
        if (info) info.textContent = '';
    }
    </script>
</body>
</html>
 