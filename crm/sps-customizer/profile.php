<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

$user_id = (int) $_SESSION['user_id'];
$user_full_name = $_SESSION['fullname'];
$user_type = $_SESSION['user_type'];

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word)
        $initials .= strtoupper(substr($word, 0, 1));
    return $initials ?: 'U';
}
function scalarValue($con, $sql)
{
    $result = mysqli_query($con, $sql);
    if (!$result)
        return 0;
    $row = mysqli_fetch_row($result);
    return $row ? (float) $row[0] : 0;
}
function getPriorityMeta($count, $highAt, $mediumAt)
{
    if ($count >= $highAt)
        return ['High', 'high'];
    if ($count >= $mediumAt)
        return ['Medium', 'medium'];
    return ['Low', 'low'];
}
function formatListDate($value)
{
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00')
        return 'N/A';
    $time = strtotime($value);
    return $time ? date('d M Y', $time) : 'N/A';
}
function previewText($value, $limit = 110)
{
    $value = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/', ' ', $value);
    $value = trim((string) $value);
    if (strlen($value) <= $limit)
        return $value;
    return rtrim(substr($value, 0, $limit - 3)) . '...';
}
function userTypeLabel($value)
{
    $map = [
        '1' => 'Admin',
        '2' => 'Manager',
        '3' => 'Staff',
        '4' => 'User',
        '5' => 'Accountant',
    ];
    $key = trim((string) $value);
    return $map[$key] ?? ($key !== '' ? $key : 'User');
}
function resolveProfileImage($filename)
{
    $filename = trim((string) $filename);
    if ($filename === '')
        return '';

    $relative = 'Uploads/staff_images/' . rawurlencode($filename);
    $absolute = __DIR__ . DIRECTORY_SEPARATOR . 'Uploads' . DIRECTORY_SEPARATOR . 'staff_images' . DIRECTORY_SEPARATOR . $filename;
    return is_file($absolute) ? $relative : '';
}

$user_initials = getInitials($user_full_name);
if ($user_id > 0) {
    $s = mysqli_prepare($con, "UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    if ($s) {
        mysqli_stmt_bind_param($s, "i", $user_id);
        mysqli_stmt_execute($s);
        mysqli_stmt_close($s);
    }
}

// === IMAGE UPLOAD / DELETE ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
    $action = trim($_POST['action'] ?? '');

    if ($action === 'upload_pic' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $ftype = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (in_array($ftype, $allowed_mime, true)) {
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $newname = $user_id . '-' . time() . '.' . $ext;
            $dest = __DIR__ . '/Uploads/staff_images/' . $newname;
            $old_sq = mysqli_prepare($con, "SELECT user_pic FROM users WHERE user_id=? LIMIT 1");
            if ($old_sq) {
                mysqli_stmt_bind_param($old_sq, "i", $user_id);
                mysqli_stmt_execute($old_sq);
                $old_sr = mysqli_stmt_get_result($old_sq);
                if ($old_sr && $old_srow = mysqli_fetch_row($old_sr)) {
                    $oldfile = __DIR__ . '/Uploads/staff_images/' . $old_srow[0];
                    if ($old_srow[0] && is_file($oldfile)) @unlink($oldfile);
                }
                mysqli_stmt_close($old_sq);
            }
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $dest)) {
                $uq = mysqli_prepare($con, "UPDATE users SET user_pic=? WHERE user_id=?");
                if ($uq) {
                    mysqli_stmt_bind_param($uq, "si", $newname, $user_id);
                    mysqli_stmt_execute($uq);
                    mysqli_stmt_close($uq);
                }
            }
        }
        header('Location: profile.php');
        exit();
    }

    if ($action === 'delete_pic') {
        $old_sq = mysqli_prepare($con, "SELECT user_pic FROM users WHERE user_id=? LIMIT 1");
        if ($old_sq) {
            mysqli_stmt_bind_param($old_sq, "i", $user_id);
            mysqli_stmt_execute($old_sq);
            $old_sr = mysqli_stmt_get_result($old_sq);
            if ($old_sr && $old_srow = mysqli_fetch_row($old_sr)) {
                $oldfile = __DIR__ . '/Uploads/staff_images/' . $old_srow[0];
                if ($old_srow[0] && is_file($oldfile)) @unlink($oldfile);
            }
            mysqli_stmt_close($old_sq);
        }
        $uq = mysqli_prepare($con, "UPDATE users SET user_pic='' WHERE user_id=?");
        if ($uq) {
            mysqli_stmt_bind_param($uq, "i", $user_id);
            mysqli_stmt_execute($uq);
            mysqli_stmt_close($uq);
        }
        header('Location: profile.php');
        exit();
    }
}

$user_designation = '';
$user_email = '';
$user_role_label = userTypeLabel($user_type);
$user_profile_image = '';
$user_profile = mysqli_prepare($con, "SELECT user_name,user_email,user_desig,user_pic,user_type FROM users WHERE user_id=? LIMIT 1");
if ($user_profile) {
    mysqli_stmt_bind_param($user_profile, "i", $user_id);
    mysqli_stmt_execute($user_profile);
    $user_profile_result = mysqli_stmt_get_result($user_profile);
    if ($user_profile_result && ($user_profile_row = mysqli_fetch_assoc($user_profile_result))) {
        $user_full_name = trim((string) ($user_profile_row['user_name'] ?? '')) ?: $user_full_name;
        $user_initials = getInitials($user_full_name);
        $user_email = trim((string) ($user_profile_row['user_email'] ?? ''));
        $user_designation = trim((string) ($user_profile_row['user_desig'] ?? ''));
        $user_role_label = userTypeLabel($user_profile_row['user_type'] ?? $user_type);
        $user_profile_image = resolveProfileImage($user_profile_row['user_pic'] ?? '');
    }
    mysqli_stmt_close($user_profile);
}
$user_initials_display = $user_initials . ' (' . $user_id . ')';

$month_start = date('Y-m-01');
$next_7_days = date('Y-m-d', strtotime('+7 days'));

$my_active_clients = (int) scalarValue($con, "SELECT COUNT(*) FROM members WHERE user_id={$user_id} AND status=1");
$my_new_clients_month = (int) scalarValue($con, "SELECT COUNT(*) FROM members WHERE user_id={$user_id} AND DATE(date_added)>='{$month_start}'");
$my_total_invoices = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id}");
$my_paid_invoices = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id} AND invoice_status=3");
$my_partial_invoices = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id} AND invoice_status=2");
$my_unpaid_invoices = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id} AND invoice_status=1");
$my_overdue_invoices = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id} AND due_date<CURDATE() AND invoice_status IN(1,2)");
$my_due_this_week = (int) scalarValue($con, "SELECT COUNT(*) FROM billing_invoice WHERE service_provider_id={$user_id} AND due_date BETWEEN CURDATE() AND '{$next_7_days}' AND invoice_status IN(1,2)");
$my_outstanding_balance = (float) scalarValue($con, "SELECT COALESCE(SUM(balance),0) FROM billing_invoice WHERE service_provider_id={$user_id} AND invoice_status IN(1,2)");
$my_collections_month = (float) scalarValue($con, "SELECT COALESCE(SUM(paid_amount),0) FROM billing_invoice WHERE service_provider_id={$user_id} AND paid_date>='{$month_start}'");
$my_total_projects = (int) scalarValue($con, "SELECT COUNT(*) FROM projects WHERE assign_to='{$user_id}'");
$my_open_projects = (int) scalarValue($con, "SELECT COUNT(*) FROM projects WHERE assign_to='{$user_id}' AND project_status='open'");
$my_completed_projects = (int) scalarValue($con, "SELECT COUNT(*) FROM projects WHERE assign_to='{$user_id}' AND project_status='completed'");
$my_total_quotations = (int) scalarValue($con, "SELECT COUNT(*) FROM quotations WHERE created_by='{$user_id}'");
$my_sent_quotations = (int) scalarValue($con, "SELECT COUNT(*) FROM quotations WHERE created_by='{$user_id}' AND status='sent'");
$quote_send_rate = $my_total_quotations > 0 ? round(($my_sent_quotations / $my_total_quotations) * 100) : 0;

$today_reminders = [];
$today = date('Y-m-d');
$rs = mysqli_prepare($con, "SELECT r.reminder_title,r.reminder_description,r.reminder_date,r.reminder_type,CONCAT(m.name,' ',m.lname) AS client_name FROM client_calender_reminder r JOIN members m ON m.member_id=r.client_id WHERE DATE(r.reminder_date)=? AND r.status=1 AND r.created_by=? ORDER BY r.reminder_date ASC");
if ($rs && $user_id > 0) {
    mysqli_stmt_bind_param($rs, "si", $today, $user_id);
    mysqli_stmt_execute($rs);
    $rr = mysqli_stmt_get_result($rs);
    while ($row = mysqli_fetch_assoc($rr))
        $today_reminders[] = $row;
    mysqli_stmt_close($rs);
}
$my_followups_today = count($today_reminders);
$my_followups_week = (int) scalarValue($con, "SELECT COUNT(*) FROM client_calender_reminder WHERE created_by={$user_id} AND DATE(reminder_date) BETWEEN CURDATE() AND '{$next_7_days}' AND status=1");

$recent_invoices = [];
$ir = mysqli_query($con, "SELECT i.invoice_id,i.total_amount,i.balance,i.invoice_status,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',i.mem_id),'No Client Linked') AS client_name FROM billing_invoice i LEFT JOIN members m ON m.member_id=i.mem_id WHERE i.service_provider_id={$user_id} ORDER BY i.invoice_id DESC LIMIT 4");
if ($ir)
    while ($row = mysqli_fetch_assoc($ir))
        $recent_invoices[] = $row;

$recent_projects = [];
$pr = mysqli_query($con, "SELECT p.order_id,p.project_title,p.project_status,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',p.mem_id),'No Client Linked') AS client_name FROM projects p LEFT JOIN members m ON m.member_id=p.mem_id WHERE p.assign_to='{$user_id}' ORDER BY p.order_id DESC LIMIT 4");
if ($pr)
    while ($row = mysqli_fetch_assoc($pr))
        $recent_projects[] = $row;

$recent_clients_added = [];
$recent_added_sql = "SELECT m.member_id,m.date_added,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',m.member_id)) AS client_name,COALESCE(mt.type_name,'Uncategorised') AS client_type
    FROM members m
    LEFT JOIN member_type mt ON mt.type_id=m.type
    WHERE (m.added_by='{$user_id}' OR ((m.added_by='' OR m.added_by IS NULL) AND m.user_id='{$user_id}'))
    ORDER BY m.date_added DESC, m.member_id DESC
    LIMIT 3";
$recent_added_result = mysqli_query($con, $recent_added_sql);
if ($recent_added_result)
    while ($row = mysqli_fetch_assoc($recent_added_result))
        $recent_clients_added[] = $row;

$recent_clients_updated = [];
$recent_updated_sql = "SELECT m.member_id,m.dateupdated,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',m.member_id)) AS client_name,COALESCE(mt.type_name,'Uncategorised') AS client_type
    FROM members m
    LEFT JOIN member_type mt ON mt.type_id=m.type
    WHERE m.user_id='{$user_id}' AND m.dateupdated IS NOT NULL AND m.dateupdated<>'0000-00-00' AND m.dateupdated<>'0000-00-00 00:00:00' AND m.dateupdated<>''
    ORDER BY m.dateupdated DESC, m.member_id DESC
    LIMIT 3";
$recent_updated_result = mysqli_query($con, $recent_updated_sql);
if ($recent_updated_result)
    while ($row = mysqli_fetch_assoc($recent_updated_result))
        $recent_clients_updated[] = $row;

$recent_invoice_comments = [];
$recent_invoice_comments_sql = "SELECT comment_detail AS comment_text, comment_time,
    COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',cm.member_id),'General') AS client_name
    FROM comments cm
    LEFT JOIN members m ON m.member_id=cm.member_id
    WHERE cm.user_id='{$user_id}'
    ORDER BY cm.comment_time DESC
    LIMIT 3";
$recent_invoice_comments_result = mysqli_query($con, $recent_invoice_comments_sql);
if ($recent_invoice_comments_result)
    while ($row = mysqli_fetch_assoc($recent_invoice_comments_result))
        $recent_invoice_comments[] = $row;

$recent_new_comments = [];
$recent_new_comments_sql = "SELECT comment_detail AS comment_text, comment_time,
    COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',ncm.member_id),'Invoice') AS client_name
    FROM new_comments ncm
    LEFT JOIN members m ON m.member_id=ncm.member_id
    WHERE ncm.user_id='{$user_id}'
    ORDER BY ncm.comment_time DESC
    LIMIT 3";
$recent_new_comments_result = mysqli_query($con, $recent_new_comments_sql);
if ($recent_new_comments_result)
    while ($row = mysqli_fetch_assoc($recent_new_comments_result))
        $recent_new_comments[] = $row;

$total_invoice_comments = (int) scalarValue($con, "SELECT COUNT(*) FROM comments WHERE user_id='{$user_id}'");
$total_new_comments = (int) scalarValue($con, "SELECT COUNT(*) FROM new_comments WHERE user_id='{$user_id}'");
$total_comments_by_user = $total_invoice_comments + $total_new_comments;

$my_total_clients_added = (int) scalarValue($con, "SELECT COUNT(*) FROM members WHERE (added_by='{$user_id}' OR ((added_by='' OR added_by IS NULL) AND user_id='{$user_id}'))");
$my_total_clients_updated = (int) scalarValue($con, "SELECT COUNT(*) FROM members WHERE user_id='{$user_id}' AND dateupdated IS NOT NULL AND dateupdated<>'0000-00-00' AND dateupdated<>'0000-00-00 00:00:00' AND dateupdated<>''");

$all_clients_added = [];
$r = mysqli_query($con, "SELECT m.member_id,m.date_added,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',m.member_id)) AS client_name,COALESCE(mt.type_name,'Uncategorised') AS client_type FROM members m LEFT JOIN member_type mt ON mt.type_id=m.type WHERE (m.added_by='{$user_id}' OR ((m.added_by='' OR m.added_by IS NULL) AND m.user_id='{$user_id}')) ORDER BY m.date_added DESC, m.member_id DESC LIMIT 100");
if ($r) while ($row = mysqli_fetch_assoc($r)) $all_clients_added[] = $row;

$all_clients_updated = [];
$r = mysqli_query($con, "SELECT m.member_id,m.dateupdated,COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',m.member_id)) AS client_name,COALESCE(mt.type_name,'Uncategorised') AS client_type FROM members m LEFT JOIN member_type mt ON mt.type_id=m.type WHERE m.user_id='{$user_id}' AND m.dateupdated IS NOT NULL AND m.dateupdated<>'0000-00-00' AND m.dateupdated<>'0000-00-00 00:00:00' AND m.dateupdated<>'' ORDER BY m.dateupdated DESC, m.member_id DESC LIMIT 100");
if ($r) while ($row = mysqli_fetch_assoc($r)) $all_clients_updated[] = $row;

$all_invoice_comments = [];
$r = mysqli_query($con, "SELECT comment_detail AS comment_text, comment_time, COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',cm.member_id),'General') AS client_name FROM comments cm LEFT JOIN members m ON m.member_id=cm.member_id WHERE cm.user_id='{$user_id}' ORDER BY cm.comment_time DESC LIMIT 100");
if ($r) while ($row = mysqli_fetch_assoc($r)) $all_invoice_comments[] = $row;

$all_new_comments = [];
$r = mysqli_query($con, "SELECT comment_detail AS comment_text, comment_time, COALESCE(NULLIF(TRIM(m.bus_name),''),NULLIF(TRIM(CONCAT(m.name,' ',m.lname)),''),CONCAT('Client #',ncm.member_id),'Invoice') AS client_name FROM new_comments ncm LEFT JOIN members m ON m.member_id=ncm.member_id WHERE ncm.user_id='{$user_id}' ORDER BY ncm.comment_time DESC LIMIT 100");
if ($r) while ($row = mysqli_fetch_assoc($r)) $all_new_comments[] = $row;

$top_services = [];
$top_services_sql = "SELECT bii.item_description, COUNT(*) AS usage_count
    FROM billing_invoice_items bii
    INNER JOIN billing_invoice bi ON bi.invoice_id=bii.inv_id
    WHERE bi.service_provider_id='{$user_id}' AND TRIM(COALESCE(bii.item_description,''))<>''
    GROUP BY bii.item_description
    ORDER BY usage_count DESC, bii.item_description ASC
    LIMIT 3";
$top_services_result = mysqli_query($con, $top_services_sql);
if ($top_services_result)
    while ($row = mysqli_fetch_assoc($top_services_result))
        $top_services[] = $row;

$top_client_type = null;
$top_client_type_sql = "SELECT COALESCE(mt.type_name,'Uncategorised') AS type_name, COUNT(*) AS total_clients
    FROM members m
    LEFT JOIN member_type mt ON mt.type_id=m.type
    WHERE (m.user_id='{$user_id}' OR m.added_by='{$user_id}')
    GROUP BY m.type, mt.type_name
    ORDER BY total_clients DESC, type_name ASC
    LIMIT 1";
$top_client_type_result = mysqli_query($con, $top_client_type_sql);
if ($top_client_type_result)
    $top_client_type = mysqli_fetch_assoc($top_client_type_result);

$queue_overdue = getPriorityMeta($my_overdue_invoices, 1, 1);
$queue_followups = getPriorityMeta($my_followups_today, 1, 1);
$queue_due_week = getPriorityMeta($my_due_this_week, 3, 1);
$queue_projects = getPriorityMeta($my_open_projects, 5, 1);
$queue_unpaid = getPriorityMeta($my_unpaid_invoices, 10, 1);

$greeting = date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening');
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="">

<head>
    <title>CRM Dashboard – Syed Professional Services</title>
    <?php include "includes/links.php"; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===================== HERO ===================== */
        .crm-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 40%, #f093fb 100%);
            border-radius: 20px;
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(102, 126, 234, .45), 0 4px 16px rgba(0, 0, 0, .12);
        }

        .crm-hero::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 260px;
            height: 260px;
            background: rgba(255, 255, 255, .08);
            border-radius: 50%;
        }

        .crm-hero::after {
            content: '';
            position: absolute;
            bottom: -70px;
            right: 90px;
            width: 180px;
            height: 180px;
            background: rgba(255, 255, 255, .05);
            border-radius: 50%;
        }

        .crm-hero h4 {
            color: #fff;
            font-weight: 800;
            margin: 0 0 6px;
            font-size: 24px;
            position: relative;
            letter-spacing: -.3px;
            font-family: 'Inter', sans-serif;
        }

        .crm-hero p {
            color: rgba(255, 255, 255, .82);
            margin: 0;
            font-size: 13.5px;
            position: relative;
            font-family: 'Inter', sans-serif;
        }

        .crm-avatar-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, .18);
            border: 1.5px solid rgba(255, 255, 255, .8);
            border-radius: 999px;
            padding: 6px 18px 6px 6px;
            font-size: 12.5px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 4px 16px rgba(0, 0, 0, .15);
        }

        .crm-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fff 0%, rgba(255, 255, 255, .6) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            color: #667eea;
        }

        .crm-avatar img,
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        /* ===================== KPI CARDS ===================== */
        .crm-stat {
            border-radius: 18px;
            padding: 20px 18px 16px;
            color: #fff;
            min-height: 130px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform .22s cubic-bezier(.34, 1.56, .64, 1), box-shadow .22s ease;
            font-family: 'Inter', sans-serif;
        }

        .crm-stat:hover {
            transform: translateY(-6px) scale(1.02);
        }

        .crm-stat .cs-icon {
            position: absolute;
            top: 14px;
            right: 16px;
            font-size: 42px;
            opacity: .16;
            line-height: 1;
        }

        .crm-stat .cs-lbl {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255, 255, 255, .78);
        }

        .crm-stat .cs-val {
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin: 8px 0 4px;
            letter-spacing: -1px;
        }

        .crm-stat .cs-sub {
            font-size: 11px;
            color: rgba(255, 255, 255, .72);
            line-height: 1.4;
        }

        .crm-stat .cs-badge {
            position: absolute;
            bottom: 14px;
            right: 14px;
            background: rgba(255, 255, 255, .2);
            border-radius: 999px;
            padding: 2px 9px;
            font-size: 10px;
            font-weight: 700;
            color: rgba(255, 255, 255, .9);
        }
 
        /* Card colour schemes */
        .cs-1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 10px 40px rgba(102, 126, 234, .5), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        .cs-2 {
            background: linear-gradient(135deg, #0891b2 0%, #06b6d4 50%, #22d3ee 100%);
            box-shadow: 0 10px 40px rgba(8, 145, 178, .45), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        .cs-3 {
            background: linear-gradient(135deg, #059669 0%, #10b981 60%, #34d399 100%);
            box-shadow: 0 10px 40px rgba(5, 150, 105, .45), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        .cs-4 {
            background: linear-gradient(135deg, #d97706 0%, #f59e0b 60%, #fbbf24 100%);
            box-shadow: 0 10px 40px rgba(217, 119, 6, .45), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        .cs-5 {
            background: linear-gradient(135deg, #db2777 0%, #ec4899 60%, #f472b6 100%);
            box-shadow: 0 10px 40px rgba(219, 39, 119, .45), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        .cs-6 {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 60%, #f87171 100%);
            box-shadow: 0 10px 40px rgba(220, 38, 38, .45), inset 0 1px 0 rgba(255, 255, 255, .15);
        }

        /* ===================== PANELS ===================== */
        .crm-panel {
            border-radius: 18px;
            background: #fff;
            margin-top: 18px;
            overflow: hidden;
            box-shadow:
                0 4px 6px rgba(0, 0, 0, .04),
                0 10px 30px rgba(0, 0, 0, .08),
                0 0 0 1px rgba(0, 0, 0, .04);
            transition: box-shadow .2s ease, transform .2s ease;
        }

        .crm-panel:hover {
            box-shadow:
                0 8px 12px rgba(0, 0, 0, .06),
                0 20px 50px rgba(0, 0, 0, .12),
                0 0 0 1px rgba(0, 0, 0, .04);
            transform: translateY(-2px);
        }

        .crm-panel-hdr {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid #f0f4f8;
        }

        /* Each panel gets its own accent top-border */
        .crm-panel.panel-queue {
            border-top: 4px solid #f59e0b;
        }

        .crm-panel.panel-follow {
            border-top: 4px solid #0ea5e9;
        }

        .crm-panel.panel-inv {
            border-top: 4px solid #10b981;
        }

        .crm-panel.panel-proj {
            border-top: 4px solid #ec4899;
        }

        .crm-panel-hdr {
            background: linear-gradient(90deg, #fbfffa, #fff);
            
        } 

        .crm-panel-hdr h6 {
            margin: 0;
            font-weight: 700;
            font-size: 13.5px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 9px;
            font-family: 'Inter', sans-serif;
        }

        .crm-panel-hdr h6 .hdr-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, .06);
        }

        /* ===================== PILLS ===================== */
        .crm-pill {
            border-radius: 999px;
            padding: 4px 13px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .08);
        }

        .cp-blue {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1d4ed8;
        }

        .cp-green {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .cp-amber {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .cp-red {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        /* ===================== TABLES ===================== */
        .crm-tbl {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Inter', sans-serif;
        }

        .crm-tbl thead th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #a0aec0;
            padding: 10px 20px;
            font-weight: 700;
            border-bottom: 1px solid #f0f4f8;
            background: linear-gradient(0deg, #fafbff, #fff);
        }

        .crm-tbl tbody td {
            padding: 11px 20px;
            font-size: 13px;
            color: #2d3748;
            border-bottom: 1px solid #f7fafc;
            vertical-align: middle;
        }

        .crm-tbl tbody tr:last-child td {
            border-bottom: none;
        }

        .crm-tbl tbody tr {
            transition: background .15s ease;
        }

        .crm-tbl tbody tr:hover td {
            background: linear-gradient(90deg, #f8faff, #f0f7ff);
        }

        /* row number style */
        .row-num {
            font-size: 11px;
            color: #cbd5e1;
            font-weight: 600;
        }

        .client-name {
            font-weight: 600;
            color: #1e293b;
        }

        /* ===================== BADGES ===================== */
        .sbadge {
            border-radius: 8px;
            padding: 4px 11px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: .02em;
            font-family: 'Inter', sans-serif;
        }

        .sb-paid {
            background: #d1fae5;
            color: #065f46;
            box-shadow: 0 1px 4px rgba(5, 150, 105, .2);
        }

        .sb-partial {
            background: #fef3c7;
            color: #92400e;
            box-shadow: 0 1px 4px rgba(217, 119, 6, .2);
        }

        .sb-unpaid {
            background: #fee2e2;
            color: #991b1b;
            box-shadow: 0 1px 4px rgba(220, 38, 38, .2);
        }

        .sb-open {
            background: #fee2e2;
            color: #991b1b;
            box-shadow: 0 1px 4px rgba(220, 38, 38, .2);
        }

        .sb-done {
            background: #d1fae5;
            color: #065f46;
            box-shadow: 0 1px 4px rgba(5, 150, 105, .2);
        }

        .sb-other {
            background: #f1f5f9;
            color: #475569;
        }

        .pri {
            border-radius: 8px;
            padding: 4px 11px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: .02em;
            font-family: 'Inter', sans-serif;
        }

        .pri.high {
            background: #fee2e2;
            color: #991b1b;
            box-shadow: 0 1px 4px rgba(220, 38, 38, .2);
        }

        .pri.medium {
            background: #fef3c7;
            color: #92400e;
            box-shadow: 0 1px 4px rgba(217, 119, 6, .2);
        }

        .pri.low {
            background: #d1fae5;
            color: #065f46;
            box-shadow: 0 1px 4px rgba(5, 150, 105, .2);
        }

        /* queue task icon */
        .q-icon {
            font-size: 16px;
        }

        /* type chip */
        .type-chip {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 3px 9px;
            font-size: 11px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
        }

        .crm-empty {
            padding: 32px;
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
        }

        .crm-empty strong {
            color: #64748b;
        }

        .tbl-scroll {
            overflow-x: auto;
        }

        /* balance colours */
        .bal-red {
            color: #dc2626;
            font-weight: 700;
        }

        .bal-green {
            color: #059669;
            font-weight: 700;
        }

        /* queue count bubble */
        .q-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 2px 8px rgba(102, 126, 234, .4);
            padding: 0 8px;
        }

        .profile-summary {
            display: flex;
            gap: 18px;
            padding: 20px;
            align-items: center;
            flex-wrap: wrap;
            font-family: 'Inter', sans-serif;
        }

        .profile-summary.full-width {
            justify-content: space-between;
        }

        .profile-head {
            display: flex;
            gap: 18px;
            align-items: center;
            flex-wrap: wrap;
        }

        .profile-photo {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(102, 126, 234, .24);
            flex-shrink: 0;
            overflow: hidden;
        }

        .profile-copy h3 {
            margin: 0 0 6px;
            font-size: 23px;
            font-weight: 800;
            color: #0f172a;
        }

        .profile-copy p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .profile-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            padding: 0 20px 20px;
        }

        .mini-stat {
            border-radius: 14px;

            box-shadow: rgba(0, 0, 0, 0.25) 0px 0.0625em 0.0625em, rgba(0, 0, 0, 0.25) 0px 0.125em 0.5em, rgba(255, 255, 255, 0.1) 0px 0px 0px 1px inset;
            border: 1px solid #e2e8f0;
            padding: 14px 16px;
            font-family: 'Inter', sans-serif;
        }

        .mini-stat .lbl {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #94a3b8;
            font-weight: 700;
        }

        .mini-stat .val {
            margin-top: 5px;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        .mini-stat .sub {
            margin-top: 4px;
            font-size: 12px;
            color: #64748b;
        }

        .crm-list {
            list-style: none;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        .crm-list li {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .crm-list li:last-child {
            border-bottom: none;
        }

        .crm-list strong {
            display: block;
            color: #0f172a;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .crm-list span {
            display: block;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .service-rank {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .service-rank .rank-no {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1d4ed8;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 800;
            flex-shrink: 0;
        }

        .service-rank .count-pill {
            margin-left: auto;
            white-space: nowrap;
        }

        @media (max-width: 1199px) {
            .profile-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .profile-stat-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ===================== PHOTO UPLOAD OVERLAY ===================== */
        .profile-photo-wrap {
            position: relative;
            width: 88px;
            height: 88px;
            flex-shrink: 0;
        }

        .profile-photo {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(102, 126, 234, .24);
            overflow: hidden;
            cursor: pointer;
        }

        .profile-photo .initials-label {
            text-align: center;
            line-height: 1.25;
            padding: 4px;
            word-break: break-all;
        }

        .photo-overlay {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(0, 0, 0, .52);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: opacity .22s ease;
        }

        .profile-photo-wrap:hover .photo-overlay {
            opacity: 1;
        }

        .photo-overlay label,
        .photo-overlay button {
            background: none;
            border: none;
            cursor: pointer;
            color: #fff;
            font-size: 18px;
            padding: 4px;
            line-height: 1;
            transition: transform .15s ease;
        }

        .photo-overlay label:hover,
        .photo-overlay button:hover {
            transform: scale(1.2);
        }

        .photo-overlay button.del-btn {
            color: #fca5a5;
        }

        /* ===================== VIEW ALL MODAL ===================== */
        .va-modal .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
        }

        .va-modal .modal-header .btn-close {
            filter: invert(1);
        }

        .va-modal .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,.2);
        }

        .va-modal .va-list {
            list-style: none;
            margin: 0;
            padding: 0;
            max-height: 420px;
            overflow-y: auto;
        }

        .va-modal .va-list li {
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-family: 'Inter', sans-serif;
        }

        .va-modal .va-list li:last-child { border-bottom: none; }
        .va-modal .va-list strong { display: block; color: #0f172a; font-size: 13px; margin-bottom: 2px; }
        .va-modal .va-list span  { display: block; color: #64748b; font-size: 12px; line-height: 1.5; }

        .view-all-btn {
            font-size: 11px;
            font-weight: 700;
            color: #667eea;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            font-family: 'Inter', sans-serif;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .view-all-btn:hover { color: #4f46e5; }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">

            <!-- Breadcrumb — same as your template -->
            <div class="card card-body mb-3">
                <div class="page-block">
                    <div class="row g-3 mb-1">
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-1">
                                <span class="cs-icon">&#128101;</span>
                                <div class="cs-lbl">Active Clients</div>
                                <div class="cs-val"><?php echo $my_active_clients; ?></div>
                                <div class="cs-sub">+<?php echo $my_new_clients_month; ?> new this month</div>
                                <span class="cs-badge">Clients</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-2">
                                <span class="cs-icon">&#128176;</span>
                                <div class="cs-lbl">Collections MTD</div>
                                <div class="cs-val">$<?php echo number_format($my_collections_month, 0); ?></div>
                                <div class="cs-sub">From paid invoices</div>
                                <span class="cs-badge">Revenue</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-3">
                                <span class="cs-icon">&#128200;</span>
                                <div class="cs-lbl">Quote Send Rate</div>
                                <div class="cs-val"><?php echo $quote_send_rate; ?>%</div>
                                <div class="cs-sub"><?php echo $my_sent_quotations; ?> /
                                    <?php echo $my_total_quotations; ?>
                                    sent</div>
                                <span class="cs-badge">Quotes</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-4">
                                <span class="cs-icon">&#128203;</span>
                                <div class="cs-lbl">My Invoices</div>
                                <div class="cs-val"><?php echo $my_total_invoices; ?></div>
                                <div class="cs-sub">Paid <?php echo $my_paid_invoices; ?> &middot; Part.
                                    <?php echo $my_partial_invoices; ?> &middot; Unpaid
                                    <?php echo $my_unpaid_invoices; ?>
                                </div>
                                <span class="cs-badge">Billing</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-5">
                                <span class="cs-icon">&#128188;</span>
                                <div class="cs-lbl">My Projects</div>
                                <div class="cs-val"><?php echo $my_total_projects; ?></div>
                                <div class="cs-sub">Open <?php echo $my_open_projects; ?> &middot; Done
                                    <?php echo $my_completed_projects; ?>
                                </div>
                                <span class="cs-badge">Projects</span>
                            </div>
                        </div>
                        <div class="col-6 col-md-4 col-xl-2">
                            <div class="crm-stat cs-6">
                                <span class="cs-icon">&#9888;</span>
                                <div class="cs-lbl">Outstanding</div>
                                <div class="cs-val">$<?php echo number_format($my_outstanding_balance, 0); ?></div>
                                <div class="cs-sub">Overdue: <?php echo $my_overdue_invoices; ?> inv.</div>
                                <span class="cs-badge">Urgent</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hero -->


            <div class="row g-3">
                <div class="col-12">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#667eea;"></span>User Profile</h6>
                            <span class="crm-pill cp-blue"><?php echo htmlspecialchars($user_role_label); ?></span>
                        </div>
                        <div class="profile-summary full-width">
                            <div class="profile-head">
                                <div class="profile-photo-wrap">
                                    <div class="profile-photo">
                                        <?php if ($user_profile_image): ?>
                                            <img src="<?php echo htmlspecialchars($user_profile_image); ?>"
                                                alt="<?php echo htmlspecialchars($user_full_name); ?>"
                                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                        <?php else: ?>
                                            <?php
                                            $disp = $user_initials_display;
                                            $fs = strlen($disp) > 9 ? '10px' : (strlen($disp) > 6 ? '12px' : (strlen($disp) > 4 ? '14px' : '18px'));
                                            ?>
                                            <span class="initials-label" style="font-size:<?php echo $fs; ?>;"><?php echo htmlspecialchars($disp); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="photo-overlay">
                                        <form id="pic-upload-form" method="POST" enctype="multipart/form-data" style="display:contents;">
                                            <input type="hidden" name="action" value="upload_pic">
                                            <label for="pic-file-input" title="Upload photo"><i class="feather icon-camera"></i></label>
                                            <input type="file" id="pic-file-input" name="profile_pic" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="document.getElementById('pic-upload-form').submit();">
                                        </form>
                                        <?php if ($user_profile_image): ?>
                                        <form method="POST" style="display:contents;">
                                            <input type="hidden" name="action" value="delete_pic">
                                            <button type="submit" class="del-btn" title="Remove photo" onclick="return confirm('Remove profile photo?');"><i class="feather icon-trash-2"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="profile-copy">
                                    <h3><?php echo htmlspecialchars($user_full_name); ?></h3>
                                    <p>
                                        <strong style="color:#667eea;font-size:15px;"><?php echo htmlspecialchars($user_initials_display); ?></strong>
                                    </p>
                                    <p>Designation:
                                        <?php echo htmlspecialchars($user_designation ?: 'Not set'); ?>
                                    </p>
                                    <div class="profile-meta">
                                        <span class="crm-pill cp-green">User ID #<?php echo $user_id; ?></span>
                                        <span class="crm-pill cp-amber"><?php echo htmlspecialchars($user_email ?: 'No email saved'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-stat-grid">
                                <div class="mini-stat">
                                    <div class="lbl">Clients Added</div>
                                    <div class="val"><?php echo $my_total_clients_added; ?></div>
                                    <div class="sub">Total clients added</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="lbl">Client Comments</div>
                                    <div class="val"><?php echo $total_invoice_comments; ?></div>
                                    <div class="sub">Client Comments</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="lbl">Invoice Comments</div>
                                    <div class="val"><?php echo $total_new_comments; ?></div>
                                    <div class="sub">Client Invoice Comments</div>
                                </div>
                                <div class="mini-stat">
                                    <div class="lbl">Top Client Type</div>
                                    <div class="val">
                                        <?php echo htmlspecialchars($top_client_type['type_name'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="sub">
                                        <?php echo isset($top_client_type['total_clients']) ? (int) $top_client_type['total_clients'] . ' clients' : 'No data available'; ?>
                                    </div>
                                </div>
                                <div class="mini-stat">
                                    <div class="lbl">Top Service</div>
                                    <div class="val">
                                        <?php echo htmlspecialchars($top_services[0]['item_description'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="sub">
                                        <?php echo isset($top_services[0]['usage_count']) ? (int) $top_services[0]['usage_count'] . ' uses' : 'No invoice data available'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-12">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#10b981;"></span>Top 3 Provided Services</h6>
                            <span class="crm-pill cp-green">Invoice Data</span>
                        </div>
                        <ul class="crm-list">
                            <?php if (!empty($top_services)):
                                foreach ($top_services as $index => $service): ?>
                                    <li>
                                        <div class="service-rank">
                                            <span class="rank-no"><?php echo $index + 1; ?></span>
                                            <div style="flex:1;">
                                                <strong><?php echo htmlspecialchars($service['item_description']); ?></strong>
                                                <span>Most frequently used service on your invoices</span>
                                            </div>
                                            <span
                                                class="crm-pill cp-blue count-pill"><?php echo (int) $service['usage_count']; ?>
                                                times</span>
                                        </div>
                                    </li>
                                <?php endforeach; else: ?>
                                <li class="crm-empty">No provided services found in invoice data.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- KPI Cards -->


            <div class="row g-3">
                <div class="col-md-6 col-xl-6">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#6366f1;"></span>Clients Added by User</h6>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="crm-pill cp-blue">Recent 3</span>
                                <?php if (count($all_clients_added) > 3): ?>
                                <button class="view-all-btn" onclick="openModal('modal-clients-added')">View All (<?php echo count($all_clients_added); ?>)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="crm-list">
                            <?php if (!empty($recent_clients_added)):
                                foreach ($recent_clients_added as $client): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($client['client_name']); ?></strong>
                                        <span><?php echo htmlspecialchars($client['client_type']); ?> &bull; Added on
                                            <?php echo formatListDate($client['date_added']); ?></span>
                                    </li>
                                <?php endforeach; else: ?>
                                <li class="crm-empty">No recently added clients found for this user.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#f59e0b;"></span>Clients Updated by User</h6>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="crm-pill cp-amber">Recent 3</span>
                                <?php if (count($all_clients_updated) > 3): ?>
                                <button class="view-all-btn" onclick="openModal('modal-clients-updated')">View All (<?php echo count($all_clients_updated); ?>)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="crm-list">
                            <?php if (!empty($recent_clients_updated)):
                                foreach ($recent_clients_updated as $client): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($client['client_name']); ?></strong>
                                        <span><?php echo htmlspecialchars($client['client_type']); ?> &bull; Updated on
                                            <?php echo formatListDate($client['dateupdated']); ?></span>
                                    </li>
                                <?php endforeach; else: ?>
                                <li class="crm-empty">No recently updated clients found for this user.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#ec4899;"></span>Client Comments</h6>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="crm-pill cp-blue">Recent 3</span>
                                <?php if (count($all_invoice_comments) > 3): ?>
                                <button class="view-all-btn" onclick="openModal('modal-client-comments')">View All (<?php echo count($all_invoice_comments); ?>)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="crm-list">
                            <?php if (!empty($recent_invoice_comments)):
                                foreach ($recent_invoice_comments as $comment): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($comment['client_name']); ?></strong>
                                        <span><?php echo htmlspecialchars(previewText($comment['comment_text'])); ?></span>
                                        <span><?php echo formatListDate($comment['comment_time']); ?></span>
                                    </li>
                                <?php endforeach; else: ?>
                                <li class="crm-empty">No client comments found for this user.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6 col-xl-6">
                    <div class="crm-panel">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#8b5cf6;"></span>Invoice Comments</h6>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span class="crm-pill cp-amber">Recent 3</span>
                                <?php if (count($all_new_comments) > 3): ?>
                                <button class="view-all-btn" onclick="openModal('modal-invoice-comments')">View All (<?php echo count($all_new_comments); ?>)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <ul class="crm-list">
                            <?php if (!empty($recent_new_comments)):
                                foreach ($recent_new_comments as $comment): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($comment['client_name']); ?></strong>
                                        <span><?php echo htmlspecialchars(previewText($comment['comment_text'])); ?></span>
                                        <span><?php echo formatListDate($comment['comment_time']); ?></span>
                                    </li>
                                <?php endforeach; else: ?>
                                <li class="crm-empty">No invoice comments found for this user.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>



            <!-- Recent Invoices + Projects -->
            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="crm-panel panel-inv">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#10b981;"></span>&#128203; Recent Invoices</h6>
                            <span class="crm-pill cp-green">Paid <?php echo $my_paid_invoices; ?></span>
                        </div>
                        <div class="tbl-scroll">
                            <table class="crm-tbl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Client</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Balance</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_invoices)):
                                        foreach ($recent_invoices as $inv):
                                            $st = (int) $inv['invoice_status'];
                                            $st_t = $st === 3 ? 'Paid' : ($st === 2 ? 'Partial' : 'Unpaid');
                                            $st_c = $st === 3 ? 'sb-paid' : ($st === 2 ? 'sb-partial' : 'sb-unpaid');
                                            $bal = (float) $inv['balance'];
                                            ?>
                                            <tr>
                                                <td class="row-num">#<?php echo (int) $inv['invoice_id']; ?></td>
                                                <td class="client-name"><?php echo htmlspecialchars($inv['client_name']); ?>
                                                </td>
                                                <td class="text-end" style="font-weight:600;color:#374151;">
                                                    $<?php echo number_format((float) $inv['total_amount'], 2); ?></td>
                                                <td class="text-end <?php echo $bal > 0 ? 'bal-red' : 'bal-green'; ?>">
                                                    $<?php echo number_format($bal, 2); ?></td>
                                                <td class="text-end"><span
                                                        class="sbadge <?php echo $st_c; ?>"><?php echo $st_t; ?></span></td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="5" class="crm-empty">No invoices found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="crm-panel panel-proj">
                        <div class="crm-panel-hdr">
                            <h6><span class="hdr-dot" style="background:#ec4899;"></span>&#128188; Recent Projects</h6>
                            <span class="crm-pill cp-red">Open <?php echo $my_open_projects; ?></span>
                        </div>
                        <div class="tbl-scroll">
                            <table class="crm-tbl">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Project</th>
                                        <th>Client</th>
                                        <th class="text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_projects)):
                                        foreach ($recent_projects as $proj):
                                            $ps = strtolower((string) ($proj['project_status'] ?? ''));
                                            $psc = ($ps === 'completed' || $ps === 'closed') ? 'sb-done' : ($ps === 'open' ? 'sb-open' : 'sb-other');
                                            ?>
                                            <tr>
                                                <td class="row-num">#<?php echo (int) $proj['order_id']; ?></td>
                                                <td class="client-name">
                                                    <?php echo htmlspecialchars((string) ($proj['project_title'] ?: 'Untitled')); ?>
                                                </td>
                                                <td style="color:#64748b;font-size:12px;">
                                                    <?php echo htmlspecialchars($proj['client_name']); ?>
                                                </td>
                                                <td class="text-end"><span
                                                        class="sbadge <?php echo $psc; ?>"><?php echo htmlspecialchars((string) ($proj['project_status'] ?? 'n/a')); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; else: ?>
                                        <tr>
                                            <td colspan="4" class="crm-empty">No projects found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /dash-content -->
    </div><!-- /dash-container -->

    <!-- ===== VIEW ALL MODALS ===== -->

    <!-- Clients Added -->
    <div class="modal fade va-modal" id="modal-clients-added" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-family:'Inter',sans-serif;font-weight:700;">All Clients Added by User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="va-list">
                        <?php if (!empty($all_clients_added)):
                            foreach ($all_clients_added as $i => $c): ?>
                            <li>
                                <strong><?php echo ($i + 1) . '. ' . htmlspecialchars($c['client_name']); ?></strong>
                                <span><?php echo htmlspecialchars($c['client_type']); ?> &bull; Added <?php echo formatListDate($c['date_added']); ?></span>
                            </li>
                        <?php endforeach; else: ?>
                            <li style="padding:24px;text-align:center;color:#94a3b8;">No clients found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Clients Updated -->
    <div class="modal fade va-modal" id="modal-clients-updated" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-family:'Inter',sans-serif;font-weight:700;">All Clients Updated by User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="va-list">
                        <?php if (!empty($all_clients_updated)):
                            foreach ($all_clients_updated as $i => $c): ?>
                            <li>
                                <strong><?php echo ($i + 1) . '. ' . htmlspecialchars($c['client_name']); ?></strong>
                                <span><?php echo htmlspecialchars($c['client_type']); ?> &bull; Updated <?php echo formatListDate($c['dateupdated']); ?></span>
                            </li>
                        <?php endforeach; else: ?>
                            <li style="padding:24px;text-align:center;color:#94a3b8;">No clients found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Comments -->
    <div class="modal fade va-modal" id="modal-client-comments" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-family:'Inter',sans-serif;font-weight:700;">All Client Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="va-list">
                        <?php if (!empty($all_invoice_comments)):
                            foreach ($all_invoice_comments as $i => $c): ?>
                            <li>
                                <strong><?php echo ($i + 1) . '. ' . htmlspecialchars($c['client_name']); ?></strong>
                                <span><?php echo htmlspecialchars(previewText($c['comment_text'], 160)); ?></span>
                                <span><?php echo formatListDate($c['comment_time']); ?></span>
                            </li>
                        <?php endforeach; else: ?>
                            <li style="padding:24px;text-align:center;color:#94a3b8;">No comments found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Comments -->
    <div class="modal fade va-modal" id="modal-invoice-comments" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-family:'Inter',sans-serif;font-weight:700;">All Invoice Comments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="va-list">
                        <?php if (!empty($all_new_comments)):
                            foreach ($all_new_comments as $i => $c): ?>
                            <li>
                                <strong><?php echo ($i + 1) . '. ' . htmlspecialchars($c['client_name']); ?></strong>
                                <span><?php echo htmlspecialchars(previewText($c['comment_text'], 160)); ?></span>
                                <span><?php echo formatListDate($c['comment_time']); ?></span>
                            </li>
                        <?php endforeach; else: ?>
                            <li style="padding:24px;text-align:center;color:#94a3b8;">No comments found.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openModal(id) {
        var el = document.getElementById(id);
        if (!el) return;
        var m = new bootstrap.Modal(el);
        m.show();
    }
    </script>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>
</body>

</html>