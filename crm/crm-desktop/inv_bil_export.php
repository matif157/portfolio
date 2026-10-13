<?php
// TempCode.php – returns CSV for the currently-filtered invoice list
require_once 'includes/db.php';

/* 1. incoming filters (same names as the JS object) */
$status = $_POST['status'] ?? 'all';
$type = $_POST['type'] ?? 'all';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$membership = $_POST['membership'] ?? 'all';

/* 2. identical WHERE you already use in the list page */
$conditions = [];
if ($status !== 'all')
    $conditions[] = "bi.invoice_status = '" . mysqli_real_escape_string($con, $status) . "'";
if ($type !== 'all')
    $conditions[] = "m.type           = '" . mysqli_real_escape_string($con, $type) . "'";
if ($start_date && $end_date) {
    $conditions[] = "bi.due_date BETWEEN '" . mysqli_real_escape_string($con, $start_date) . "' AND '" . mysqli_real_escape_string($con, $end_date) . "'";
}
if ($membership !== 'all') {
    $conditions[] = "bii.item_description = (SELECT membership_name FROM memberships WHERE membership_id='" . mysqli_real_escape_string($con, $membership) . "')";
}
$where = $conditions ? "AND " . implode(" AND ", $conditions) : "";

/* 3. same joins */
$sql = "
SELECT
    m.member_id,
    m.name, m.mname, m.lname, m.bus_name,
    m.prm_email, m.phone, m.cell_ph,
    bi.invoice_id,
    bi.due_date,
    bi.total_amount,
    bi.paid_amount,
    bi.balance,
    bi.discount,
    s.status_text
FROM billing_invoice bi
JOIN members                 m   ON bi.mem_id = m.member_id
JOIN billing_invoice_items   bii ON bii.inv_id = bi.invoice_id
JOIN billing_invoice_status  s   ON s.status_id = bi.invoice_status
WHERE m.status = 1 AND m.ach_payer = 0
{$where}
ORDER BY bi.due_date DESC
";

$result = mysqli_query($con, $sql);
if (!$result) {
    http_response_code(500);
    exit;
}

/* 4. stream CSV */
$filename = 'invoices_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Client ID', 'Invoice ID', 'Name', 'Business', 'Email', 'Phone', 'Due Date', 'Total', 'Paid', 'Balance', 'Discount', 'Status']);

while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($out, [
        $row['member_id'],
        $row['invoice_id'],
        trim($row['name'] . ' ' . $row['mname'] . ' ' . $row['lname']),
        $row['bus_name'],
        $row['prm_email'],
        $row['phone'] ?: $row['cell_ph'],
        date('m/d/Y', strtotime($row['due_date'])),
        $row['total_amount'],
        $row['paid_amount'],
        $row['balance'],
        $row['discount'],
        $row['status_text']
    ]);
}
// to send
fclose($out);
exit;
?>