<?php
ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE);
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/db.php';
require_once 'includes/auth.php';

ob_end_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($q === '') {
    echo json_encode(['match' => null]);
    exit;
}

$qEsc = mysqli_real_escape_string($con, $q);

if (ctype_digit($q)) {
    $idSql = "SELECT member_id, name, lname, bus_name, status
              FROM members
              WHERE member_id = '$qEsc'
              LIMIT 1";
    $idRes = mysqli_query($con, $idSql);
    if ($idRes && ($row = mysqli_fetch_assoc($idRes))) {
        $clientName = trim($row['name'] . ' ' . $row['lname']);
        $label = !empty($row['bus_name']) ? $row['bus_name'] : ($clientName ?: ('Client #' . $row['member_id']));
        echo json_encode([
            'match' => [
                'id'    => $row['member_id'],
                'label' => $label,
                'link'  => 'client_details.php?id=' . $row['member_id'] . '&status=' . (int) $row['status']
            ]
        ]);
        exit;
    }
}

$sql = "SELECT member_id, name, lname, bus_name, status
        FROM members
        WHERE bus_name LIKE '%$qEsc%'
           OR CONCAT_WS(' ', name, lname) LIKE '%$qEsc%'
           OR name LIKE '%$qEsc%'
           OR lname LIKE '%$qEsc%'
        ORDER BY
          (CASE
             WHEN LOWER(bus_name) = LOWER('$qEsc') THEN 0
             WHEN LOWER(CONCAT_WS(' ', name, lname)) = LOWER('$qEsc') THEN 0
             WHEN LOWER(bus_name) LIKE LOWER('$qEsc%') THEN 1
             WHEN LOWER(name) LIKE LOWER('$qEsc%') THEN 1
             WHEN LOWER(lname) LIKE LOWER('$qEsc%') THEN 1
             ELSE 2
           END),
          status DESC
        LIMIT 1";

$res = mysqli_query($con, $sql);
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $clientName = trim($row['name'] . ' ' . $row['lname']);
    $label = !empty($row['bus_name']) ? $row['bus_name'] : ($clientName ?: ('Client #' . $row['member_id']));
    echo json_encode([
        'match' => [
            'id'    => $row['member_id'],
            'label' => $label,
            'link'  => 'client_details.php?id=' . $row['member_id'] . '&status=' . (int) $row['status']
        ]
    ]);
    exit;
}

$soundex = soundex($q);
$sxEsc = mysqli_real_escape_string($con, $soundex);
$sxSql = "SELECT member_id, name, lname, bus_name, status
          FROM members
          WHERE SOUNDEX(bus_name) = '$sxEsc'
             OR SOUNDEX(CONCAT_WS(' ', name, lname)) = '$sxEsc'
             OR SOUNDEX(name) = '$sxEsc'
             OR SOUNDEX(lname) = '$sxEsc'
          ORDER BY status DESC
          LIMIT 1";

$res2 = mysqli_query($con, $sxSql);
if ($res2 && ($row = mysqli_fetch_assoc($res2))) {
    $clientName = trim($row['name'] . ' ' . $row['lname']);
    $label = !empty($row['bus_name']) ? $row['bus_name'] : ($clientName ?: ('Client #' . $row['member_id']));
    echo json_encode([
        'match' => [
            'id'    => $row['member_id'],
            'label' => $label,
            'link'  => 'client_details.php?id=' . $row['member_id'] . '&status=' . (int) $row['status']
        ]
    ]);
    exit;
}

echo json_encode(['match' => null]);
