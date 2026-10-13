<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
include "includes/db.php";
include "includes/auth.php";
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_full_name = $_SESSION['fullname'] ?? '';

function initials($name)
{
   $o = '';
   foreach (preg_split('/\s+/', trim((string) $name)) as $w) {
      if ($w !== '')
         $o .= strtoupper($w[0]);
   }
   return $o;
}
function jres($a, $c = 200)
{
   http_response_code($c);
   header('Content-Type: application/json; charset=utf-8');
   echo json_encode($a, JSON_UNESCAPED_UNICODE);
   exit;
}
function clientLabel($r)
{
   $b = trim((string) ($r['bus_name'] ?? ''));
   $p = trim(preg_replace('/\s+/', ' ', trim(($r['name'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? ''))));
   if ($b && $p)
      return "$b ($p)";
   if ($b)
      return $b;
   if ($p)
      return $p;
   return 'Client #' . (int) ($r['member_id'] ?? 0);
}
function metaEnc($m)
{
   return '<!--REM_META:' . base64_encode(json_encode($m, JSON_UNESCAPED_UNICODE)) . '-->';
}
function metaDec($s)
{
   $m = [];
   if (preg_match('/<!--REM_META:([A-Za-z0-9+\/=]+)-->\s*$/', (string) $s, $x)) {
      $d = base64_decode($x[1], true);
      $j = $d !== false ? json_decode($d, true) : null;
      if (is_array($j))
         $m = $j;
   }
   return $m;
}
function descPlain($s)
{
   return trim((string) preg_replace('/\s*<!--REM_META:[A-Za-z0-9+\/=]+-->\s*$/', '', (string) $s));
}
function descStore($plain, $meta)
{
   $plain = trim((string) $plain);
   return $plain . ($plain !== '' ? "\n\n" : '') . metaEnc($meta);
}
function recurLabel($meta, $fallback = '')
{
   $t = strtolower((string) ($meta['recurrence_type'] ?? ''));
   $i = (int) ($meta['custom_interval'] ?? 0);
   $u = strtolower((string) ($meta['custom_unit'] ?? 'days'));
   if ($t === 'none')
      return 'One time';
   if ($t === 'daily')
      return 'Daily';
   if ($t === 'weekly')
      return 'Weekly';
   if ($t === 'monthly')
      return 'Monthly';
   if ($t === 'custom' && $i > 0) {
      $n = rtrim($u, 's');
      if ($i !== 1)
         $n .= 's';
      return "Every $i $n";
   }
   if ($t === 'specific_dates') {
      $c = (int) ($meta['dates_count'] ?? 0);
      return $c > 0 ? "Specific dates ($c)" : 'Specific dates';
   }
   return $fallback ?: 'One time';
}
// Expand a recurring rule into concrete occurrence datetimes so each repeat is
// stored as its own row (the calendar pills and the "Reminder Due" popups both
// read one DB row per occurrence). Bounded to a 12-month horizon, capped at
// $MAX rows so daily/hourly rules can't explode. 'none' returns the single date.
function recurOccurrences($startStr, $rtype, $cint, $cunit)
{
   $start = date_create((string) $startStr);
   if (!$start)
      return [(string) $startStr];
   if ($rtype === 'daily') {
      $amt = 1;
      $unit = 'days';
   } elseif ($rtype === 'weekly') {
      $amt = 1;
      $unit = 'weeks';
   } elseif ($rtype === 'monthly') {
      $amt = 1;
      $unit = 'months';
   } elseif ($rtype === 'custom') {
      $amt = max(1, (int) $cint);
      $unit = in_array($cunit, ['days', 'weeks', 'months', 'hours'], true) ? $cunit : 'days';
   } else {
      // 'none' (or anything non-recurring) — a single occurrence.
      return [$start->format('Y-m-d H:i:s')];
   }
   $MAX = 366;
   $baseStr = $start->format('Y-m-d H:i:s');
   $horizon = date_create($baseStr);
   $horizon->modify('+12 months');
   $out = [];
   for ($i = 0; $i < $MAX; $i++) {
      // Anchor every step to the original start date so monthly/weekly rules
      // don't drift across the series.
      $occ = date_create($baseStr);
      if (!$occ)
         break;
      $occ->modify('+' . ($amt * $i) . ' ' . $unit);
      if ($i > 0 && $occ > $horizon)
         break;
      $out[] = $occ->format('Y-m-d H:i:s');
   }
   if (!$out)
      $out = [$start->format('Y-m-d H:i:s')];
   return $out;
}
function groupedReminders($con, $uid)
{
   $uid = (int) $uid;
   $out = [];
   $sql = "SELECT r.reminder_id,r.client_id,r.reminder_title,r.reminder_description,r.reminder_date,r.reminder_type,r.created_by,m.member_id,m.name,m.mname,m.lname,m.bus_name,m.status AS client_status,u.user_name assigned_user_name FROM client_calender_reminder r LEFT JOIN members m ON m.member_id=r.client_id LEFT JOIN users u ON u.user_id=r.created_by WHERE r.status=1 AND r.created_by=? ORDER BY r.reminder_date,r.reminder_id";
   if ($st = mysqli_prepare($con, $sql)) {
      mysqli_stmt_bind_param($st, "i", $uid);
      mysqli_stmt_execute($st);
      $rs = mysqli_stmt_get_result($st);
      while ($r = mysqli_fetch_assoc($rs)) {
         $pd = descPlain($r['reminder_description'] ?? '');
         $meta = metaDec($r['reminder_description'] ?? '');
         $key = sha1(json_encode(['u' => (int) $r['created_by'], 't' => $r['reminder_title'] ?? '', 'd' => $pd, 'dt' => $r['reminder_date'] ?? '', 'rt' => $r['reminder_type'] ?? '', 'm' => $meta], JSON_UNESCAPED_UNICODE));
         if (!isset($out[$key]))
            $out[$key] = ['group_key' => $key, 'title' => (string) ($r['reminder_title'] ?? ''), 'description' => $pd, 'date' => (string) ($r['reminder_date'] ?? ''), 'reminder_type' => (string) ($r['reminder_type'] ?? ''), 'recurrence_label' => recurLabel($meta, $r['reminder_type'] ?? ''), 'recurrence_type' => (string) ($meta['recurrence_type'] ?? ''), 'custom_interval' => (int) ($meta['custom_interval'] ?? 0), 'custom_unit' => (string) ($meta['custom_unit'] ?? 'days'), 'series_uid' => (string) ($meta['series_uid'] ?? ''), 'assigned_user_id' => (int) $r['created_by'], 'assigned_user_name' => (string) ($r['assigned_user_name'] ?? ''), 'row_ids' => [], 'client_ids' => [], 'clients' => []];
         $cid = (int) $r['client_id'];
         $out[$key]['row_ids'][] = (int) $r['reminder_id'];
         if (!in_array($cid, $out[$key]['client_ids'], true)) {
            $out[$key]['client_ids'][] = $cid;
            $out[$key]['clients'][] = ['id' => $cid, 'name' => clientLabel($r), 'status' => (int) ($r['client_status'] ?? 1)];
         }
      }
      mysqli_stmt_close($st);
   }
   return array_values($out);
}

if (isset($_GET['ajax'])) {
   if ($_GET['ajax'] === 'load_reminders')
      jres(['success' => true, 'reminders' => groupedReminders($con, $user_id)]);
   if ($_GET['ajax'] === 'invoice_services') {
      $client_ids = $_POST['client_ids'] ?? $_GET['client_ids'] ?? [];
      if (!is_array($client_ids))
         $client_ids = [$client_ids];
      $client_ids = array_values(array_unique(array_filter(array_map('intval', $client_ids))));
      $cutoff = date('Y-m-d', strtotime('-2 years'));
      if (!$client_ids)
         jres(['success' => true, 'cutoff' => $cutoff, 'services' => []]);
      $idsStr = implode(',', array_map('intval', $client_ids));
      $sql = "SELECT bi.mem_id, bii.item_description, bi.due_date,
                     m.name, m.mname, m.lname, m.bus_name
              FROM billing_invoice bi
              INNER JOIN billing_invoice_items bii ON bii.inv_id = bi.invoice_id
              LEFT JOIN members m ON m.member_id = bi.mem_id
              WHERE bi.mem_id IN ($idsStr)
                AND bi.due_date >= '$cutoff'
                AND TRIM(bii.item_description) <> ''
              ORDER BY bi.due_date DESC, bii.inv_item_id DESC";
      $rs = mysqli_query($con, $sql);
      $byClient = [];
      foreach ($client_ids as $cid)
         $byClient[$cid] = ['client_id' => $cid, 'client_name' => '', 'services' => [], '_seen' => []];
      if ($rs) {
         while ($row = mysqli_fetch_assoc($rs)) {
            $cid = (int) $row['mem_id'];
            $svc = trim(strip_tags((string) $row['item_description']));
            $svc = trim(preg_replace('/\s+/', ' ', $svc));
            if ($svc === '')
               continue;
            if (!isset($byClient[$cid]))
               $byClient[$cid] = ['client_id' => $cid, 'client_name' => '', 'services' => [], '_seen' => []];
            if ($byClient[$cid]['client_name'] === '')
               $byClient[$cid]['client_name'] = clientLabel($row);
            $key = mb_strtolower($svc);
            if (!isset($byClient[$cid]['_seen'][$key])) {
               $byClient[$cid]['_seen'][$key] = true;
               $byClient[$cid]['services'][] = ['description' => $svc, 'date' => (string) ($row['due_date'] ?? '')];
            }
         }
      }
      foreach ($byClient as &$g) {
         if ($g['client_name'] === '')
            $g['client_name'] = 'Client #' . (int) $g['client_id'];
         unset($g['_seen']);
      }
      unset($g);
      jres(['success' => true, 'cutoff' => $cutoff, 'services' => array_values($byClient)]);
   }
   if ($_GET['ajax'] === 'save_reminder') {
      if ($_SERVER['REQUEST_METHOD'] !== 'POST')
         jres(['success' => false, 'message' => 'Invalid request.'], 405);
      $title = trim((string) ($_POST['title'] ?? ''));
      $desc = trim((string) ($_POST['description'] ?? ''));
      $dt = trim((string) ($_POST['reminder_date'] ?? ''));
      $rtype = strtolower(trim((string) ($_POST['recurrence_type'] ?? 'daily')));
      $cint = (int) ($_POST['custom_interval'] ?? 0);
      $cunit = strtolower(trim((string) ($_POST['custom_unit'] ?? 'days')));
      $assigned = (int) ($_POST['assigned_user_id'] ?? 0);
      if ($assigned <= 0)
         $assigned = $user_id;
      $client_ids = $_POST['client_ids'] ?? [];
      if (!is_array($client_ids))
         $client_ids = [$client_ids];
      $client_ids = array_values(array_unique(array_filter(array_map('intval', $client_ids))));
      $row_ids = $_POST['row_ids'] ?? [];
      if (!is_array($row_ids))
         $row_ids = [$row_ids];
      $row_ids = array_values(array_unique(array_filter(array_map('intval', $row_ids))));
      $datesIn = $_POST['dates'] ?? [];
      if (!is_array($datesIn))
         $datesIn = [$datesIn];
      if ($title === '')
         jres(['success' => false, 'message' => 'Title is required.'], 422);
      if (!$client_ids)
         jres(['success' => false, 'message' => 'Select at least one client.'], 422);
      $okTypes = ['none', 'daily', 'weekly', 'monthly', 'custom', 'specific_dates'];
      if (!in_array($rtype, $okTypes, true))
         $rtype = 'daily';
      $okUnits = ['days', 'weeks', 'months', 'hours'];
      if (!in_array($cunit, $okUnits, true))
         $cunit = 'days';
      if ($rtype !== 'custom')
         $cint = 0;
      if ($rtype === 'custom' && $cint <= 0)
         jres(['success' => false, 'message' => 'Custom interval must be greater than zero.'], 422);
      $sqlDates = [];
      if ($rtype === 'specific_dates') {
         foreach ($datesIn as $d) {
            $d = trim((string) $d);
            if ($d === '')
               continue;
            $dob2 = date_create($d);
            if (!$dob2)
               continue;
            $sqlDates[] = $dob2->format('Y-m-d H:i:s');
         }
         $sqlDates = array_values(array_unique($sqlDates));
         sort($sqlDates);
         if (!$sqlDates)
            jres(['success' => false, 'message' => 'Please add at least one date for specific-dates recurrence.'], 422);
         $sqlDt = $sqlDates[0];
      } else {
         if ($dt === '')
            jres(['success' => false, 'message' => 'Date and time are required.'], 422);
         $dob = date_create($dt);
         if (!$dob)
            jres(['success' => false, 'message' => 'Invalid date/time.'], 422);
         $sqlDt = $dob->format('Y-m-d H:i:s');
         // Materialize one row per repeat for daily/weekly/monthly/custom rules;
         // 'none' yields a single occurrence.
         $sqlDates = recurOccurrences($sqlDt, $rtype, $cint, $cunit);
      }
      $valid = [];
      foreach ($client_ids as $cid) {
         $q = mysqli_query($con, "SELECT member_id FROM members WHERE member_id=" . (int) $cid . " LIMIT 1");
         if ($q && mysqli_num_rows($q))
            $valid[] = (int) $cid;
      }
      $valid = array_values(array_unique($valid));
      if (!$valid)
         jres(['success' => false, 'message' => 'Selected clients were not found.'], 422);
      $uq = mysqli_query($con, "SELECT user_id FROM users WHERE user_id=" . (int) $assigned . " LIMIT 1");
      if (!$uq || !mysqli_num_rows($uq))
         $assigned = $user_id;
      $meta = ['recurrence_type' => $rtype, 'custom_interval' => $cint, 'custom_unit' => $cunit];
      // Any recurring rule gets a series id so all of its occurrence rows can be
      // regrouped and replaced together when the series is edited.
      if ($rtype !== 'none') {
         try {
            $meta['series_uid'] = bin2hex(random_bytes(8));
         } catch (Exception $eUid) {
            $meta['series_uid'] = substr(sha1(uniqid('', true) . mt_rand()), 0, 16);
         }
         $meta['dates_count'] = count($sqlDates);
      }
      $storedDesc = descStore($desc, $meta);
      $storedType = recurLabel($meta, ucfirst($rtype));
      mysqli_begin_transaction($con);
      try {
         if ($row_ids) {
            $ids = implode(',', array_map('intval', $row_ids));
            if (!mysqli_query($con, "UPDATE client_calender_reminder SET status=0, updated_at=NOW() WHERE reminder_id IN ($ids)"))
               throw new Exception('Unable to update old reminder rows.');
         }
         $st = mysqli_prepare($con, "INSERT INTO client_calender_reminder (client_id,reminder_title,reminder_description,reminder_date,reminder_type,status,created_by,created_at,updated_at) VALUES (?,?,?, ?,?,1,?,NOW(),NOW())");
         if (!$st)
            throw new Exception('Unable to prepare reminder insert.');
         foreach ($sqlDates as $dRow) {
            foreach ($valid as $cid) {
               mysqli_stmt_bind_param($st, "issssi", $cid, $title, $storedDesc, $dRow, $storedType, $assigned);
               if (!mysqli_stmt_execute($st))
                  throw new Exception('Unable to save reminder.');
            }
         }
         mysqli_stmt_close($st);
         mysqli_commit($con);
         jres(['success' => true, 'message' => $row_ids ? 'Reminder updated successfully.' : 'Reminder added successfully.', 'reminders' => groupedReminders($con, $user_id)]);
      } catch (Exception $e) {
         mysqli_rollback($con);
         jres(['success' => false, 'message' => $e->getMessage()], 500);
      }
   }
   jres(['success' => false, 'message' => 'Unknown action.'], 404);
}

$clients = [];
$clientServices = [];
$serviceFilters = [];
$sr = mysqli_query($con, "
   SELECT src.member_id, src.membership_id, src.membership_name
   FROM (
      SELECT o.mem_id AS member_id, o.prod_id AS membership_id, m.membership_name
      FROM orders o
      JOIN memberships m ON m.membership_id = o.prod_id
      WHERE o.mem_id > 0 AND o.prod_id > 0

      UNION

      SELECT oar.mem_id AS member_id, oar.prod_id AS membership_id, m.membership_name
      FROM orders_auto_renewed oar
      JOIN memberships m ON m.membership_id = oar.prod_id
      WHERE oar.mem_id > 0 AND oar.prod_id > 0

      UNION

      SELECT s.member_id, s.membership_id, m.membership_name
      FROM member_services s
      JOIN memberships m ON m.membership_id = s.membership_id
      WHERE s.member_id > 0 AND s.membership_id > 0
   ) src
   ORDER BY src.membership_name ASC
");
if ($sr) {
   while ($r = mysqli_fetch_assoc($sr)) {
      $cid = (int) ($r['member_id'] ?? 0);
      $sid = (int) ($r['membership_id'] ?? 0);
      $sname = trim((string) ($r['membership_name'] ?? ''));
      if ($cid <= 0 || $sid <= 0 || $sname === '')
         continue;
      if (!isset($clientServices[$cid]))
         $clientServices[$cid] = [];
      $clientServices[$cid][$sid] = $sname;
      $serviceFilters[$sid] = $sname;
   }
}
if ($serviceFilters)
   natcasesort($serviceFilters);
$cr = mysqli_query($con, "SELECT member_id,name,mname,lname,bus_name FROM members ORDER BY COALESCE(NULLIF(TRIM(bus_name),''),NULLIF(TRIM(CONCAT(name,' ',lname)),''),member_id) ASC");
if ($cr) {
   while ($r = mysqli_fetch_assoc($cr)) {
      $cid = (int) $r['member_id'];
      $svcMap = $clientServices[$cid] ?? [];
      $clients[] = [
         'id' => $cid,
         'name' => clientLabel($r),
         'service_ids' => array_map('intval', array_keys($svcMap))
      ];
   }
}
$serviceOptions = [];
foreach ($serviceFilters as $sid => $sname)
   $serviceOptions[] = ['id' => (int) $sid, 'name' => (string) $sname];
$users = [];
$ur = mysqli_query($con, "SELECT user_id,user_name FROM users WHERE user_login_status=1 OR user_id=" . (int) $user_id . " ORDER BY user_name ASC");
if ($ur) {
   while ($r = mysqli_fetch_assoc($ur))
      $users[] = ['id' => (int) $r['user_id'], 'name' => (string) $r['user_name']];
}
$initialReminders = groupedReminders($con, $user_id);
$user_initials = initials($user_full_name);
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Calendar Reminder</title>
   <?php include "includes/links.php"; ?>
   <style>
      :root {
         --cal-primary: #252161;
         --cal-primary-2: #3a3593;
         --cal-primary-soft: #ecebf5;
         --cal-primary-ink: #1d1a4e;
         --cal-text: #0f172a;
         --cal-text-2: #334155;
         --cal-muted: #64748b;
         --cal-faint: #94a3b8;
         --cal-border: #e2e8f0;
         --cal-border-strong: #cbd5e1;
         --cal-page: #f5f7fb;
         --cal-panel: #f8fafc;
         --cal-surface: #fff;
         --cal-shadow-sm: 0 1px 2px rgba(15, 23, 42, .04), 0 1px 1px rgba(15, 23, 42, .03);
         --cal-shadow: 0 8px 24px rgba(15, 23, 42, .06), 0 2px 6px rgba(15, 23, 42, .04);
         --cal-shadow-lg: 0 20px 48px rgba(15, 23, 42, .12);
         --recur-none: #64748b;
         --recur-daily: #252161;
         --recur-weekly: #0ea5e9;
         --recur-monthly: #8b5cf6;
         --recur-custom: #f59e0b;
         --tone-today: #0ea5e9;
         --tone-overdue: #f43f5e;
         --tone-upcoming: #f59e0b;
      }

      .dash-content {
         background: linear-gradient(180deg, #f8fafc 0%, #eeedf5 100%);
         min-height: 100vh;
         padding-bottom: 24px;
      }

      .stat-strip {
         display: grid;
         grid-template-columns: repeat(4, minmax(0, 1fr));
         gap: 14px;
         margin-bottom: 16px;
      }

      .stat-card {
         background: var(--cal-surface);
         border: 1px solid var(--cal-border);
         border-radius: 16px;
         padding: 16px 18px;
         display: flex;
         align-items: center;
         gap: 14px;
         box-shadow: var(--cal-shadow-sm);
         position: relative;
         overflow: hidden;
         transition: transform .18s ease, box-shadow .18s ease;
      }

      .stat-card::before {
         content: "";
         position: absolute;
         left: 0;
         top: 0;
         bottom: 0;
         width: 4px;
         background: var(--cal-primary);
      }

      .stat-card.tone-today::before {
         background: var(--tone-today);
      }

      .stat-card.tone-upcoming::before {
         background: var(--tone-upcoming);
      }

      .stat-card.tone-overdue::before {
         background: var(--tone-overdue);
      }

      .stat-card:hover {
         transform: translateY(-1px);
         box-shadow: var(--cal-shadow);
      }

      .stat-icon {
         width: 44px;
         height: 44px;
         border-radius: 12px;
         display: flex;
         align-items: center;
         justify-content: center;
         font-size: 1.25rem;
         color: var(--cal-primary);
         background: var(--cal-primary-soft);
         flex-shrink: 0;
      }

      .stat-card.tone-today .stat-icon {
         color: var(--tone-today);
         background: #e0f2fe;
      }

      .stat-card.tone-upcoming .stat-icon {
         color: var(--tone-upcoming);
         background: #fef3c7;
      }

      .stat-card.tone-overdue .stat-icon {
         color: var(--tone-overdue);
         background: #ffe4e6;
      }

      .rem-shell {
         background: var(--cal-surface);
         border: 1px solid var(--cal-border);
         border-radius: 20px;
         overflow: hidden;
         box-shadow: var(--cal-shadow);
      }

      .rem-head {
         padding: 22px 26px;
         border-bottom: 1px solid var(--cal-border);
         background:
            radial-gradient(120% 200% at 100% 0%, rgba(37, 33, 97, .09) 0%, rgba(37, 33, 97, 0) 60%),
            linear-gradient(180deg, #fff 0%, #fafafd 100%);
         display: flex;
         align-items: center;
         justify-content: space-between;
         flex-wrap: wrap;
         gap: 14px;
      }

      .rem-head h2 {
         margin: 0;
         color: var(--cal-text);
         font-size: 1.35rem;
         font-weight: 700;
         letter-spacing: -.01em;
         display: flex;
         align-items: center;
         gap: 10px;
      }

      .rem-head h2 .head-mark {
         width: 36px;
         height: 36px;
         display: inline-flex;
         align-items: center;
         justify-content: center;
         border-radius: 10px;
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         color: #fff;
         font-size: 1.15rem;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
      }

      .rem-head p {
         margin: 6px 0 0;
         color: var(--cal-muted);
         max-width: 760px;
         font-size: .92rem;
      }

      .rem-wrap {
         display: grid;
         grid-template-columns: 290px minmax(0, 1fr) 360px;
         min-height: 800px;
      }

      .rem-left,
      .rem-right {
         background: var(--cal-panel);
      }

      .rem-left {
         border-right: 1px solid var(--cal-border);
      }

      .rem-right {
         border-left: 1px solid var(--cal-border);
      }

      .rem-mid {
         background: #fff;
      }

      .rem-left,
      .rem-mid,
      .rem-right {
         padding: 22px;
      }

      .box {
         background: #fff;
         border: 1px solid var(--cal-border);
         border-radius: 14px;
         padding: 16px;
         margin-bottom: 14px;
         box-shadow: var(--cal-shadow-sm);
      }

      .box h6,
      .rem-right h6 {
         margin: 0 0 12px;
         font-weight: 700;
         color: var(--cal-text);
         font-size: .9rem;
         display: flex;
         align-items: center;
         gap: 8px;
      }

      .box h6 i {
         color: var(--cal-primary);
         font-size: 1rem;
      }

      .stat-l {
         font-size: .68rem;
         color: var(--cal-muted);
         text-transform: uppercase;
         letter-spacing: .14em;
         font-weight: 700;
      }

      .stat-v {
         font-size: 1.7rem;
         font-weight: 700;
         color: var(--cal-text);
         line-height: 1.05;
         margin-top: 4px;
         letter-spacing: -.01em;
      }

      .toolbar,
      .panel-h {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 12px;
         margin-bottom: 18px;
      }

      .month,
      .panel-date {
         font-size: 1.15rem;
         font-weight: 700;
         color: var(--cal-text);
         letter-spacing: -.01em;
      }

      .navs {
         display: inline-flex;
         border: 1px solid var(--cal-border-strong);
         border-radius: 10px;
         overflow: hidden;
         background: #fff;
      }

      .navs button {
         border: 0;
         background: transparent;
         padding: 8px 12px;
         color: var(--cal-text-2);
         transition: background .14s ease, color .14s ease;
         display: inline-flex;
         align-items: center;
         justify-content: center;
      }

      .navs button+button {
         border-left: 1px solid var(--cal-border);
      }

      .navs button:hover {
         background: var(--cal-primary-soft);
         color: var(--cal-primary);
      }

      .act {
         border: 1px solid var(--cal-border-strong);
         background: #fff;
         padding: 9px 14px;
         border-radius: 10px;
         font-weight: 600;
         font-size: .88rem;
         color: var(--cal-text-2);
         transition: .16s ease;
         display: inline-flex;
         align-items: center;
         gap: 6px;
      }

      .act i {
         font-size: 1rem;
      }

      .act:hover {
         background: var(--cal-primary-soft);
         border-color: rgba(37, 33, 97, .4);
         color: var(--cal-primary);
      }

      .act.primary {
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         border-color: transparent;
         color: #fff;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
      }

      .act.primary:hover {
         filter: brightness(1.05);
         color: #fff;
         box-shadow: 0 8px 18px rgba(37, 33, 97, .35);
      }

      .view-switch {
         display: inline-flex;
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         border-radius: 10px;
         padding: 4px;
         gap: 2px;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
      }

      .view-switch button {
         border: 0;
         background: transparent;
         color: rgba(255, 255, 255, .78);
         font-weight: 600;
         font-size: .82rem;
         padding: 7px 14px;
         border-radius: 7px;
         cursor: pointer;
         transition: background .14s ease, color .14s ease;
         text-transform: lowercase;
         letter-spacing: .01em;
      }

      .view-switch button:hover {
         color: #fff;
         background: rgba(255, 255, 255, .08);
      }

      .view-switch button.active {
         background: rgba(255, 255, 255, .22);
         color: #fff;
         box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
      }

      .all-list {
         display: flex;
         flex-direction: column;
         gap: 10px;
         max-height: 720px;
         overflow-y: auto;
         padding-right: 4px;
      }

      .all-list .day-group {
         margin-top: 4px;
      }

      .all-list .day-group:first-child {
         margin-top: 0;
      }

      .all-list .day-head {
         font-size: .78rem;
         font-weight: 700;
         color: var(--cal-muted);
         text-transform: uppercase;
         letter-spacing: .08em;
         padding: 6px 2px;
         border-bottom: 1px solid var(--cal-border);
         margin-bottom: 8px;
      }

      .all-list .all-empty {
         padding: 40px 20px;
         text-align: center;
         color: var(--cal-muted);
      }

      .day-single {
         border: 1px solid var(--cal-border);
         border-radius: 14px;
         background: #fff;
         padding: 18px;
         min-height: 320px;
      }

      .day-single .ds-head {
         font-size: 1rem;
         font-weight: 700;
         color: var(--cal-text);
         margin-bottom: 14px;
         padding-bottom: 10px;
         border-bottom: 1px solid var(--cal-border);
      }

      .week {
         display: grid;
         grid-template-columns: repeat(7, minmax(0, 1fr));
         border: 1px solid var(--cal-border);
         border-bottom: 0;
         border-radius: 14px 14px 0 0;
         overflow: hidden;
         background: #fafafd;
      }

      .grid {
         display: grid;
         grid-template-columns: repeat(7, minmax(0, 1fr));
         border-left: 1px solid var(--cal-border);
         border-right: 1px solid var(--cal-border);
         border-bottom: 1px solid var(--cal-border);
         border-radius: 0 0 14px 14px;
         overflow: hidden;
      }

      .wd {
         text-align: left;
         font-size: .7rem;
         font-weight: 700;
         color: var(--cal-muted);
         text-transform: uppercase;
         letter-spacing: .1em;
         padding: 12px 14px;
         border-right: 1px solid var(--cal-border);
      }

      .wd:last-child {
         border-right: 0;
      }

      .day {
         background: #fff;
         min-height: 122px;
         padding: 9px 9px 8px;
         border-right: 1px solid var(--cal-border);
         border-top: 1px solid var(--cal-border);
         display: flex;
         flex-direction: column;
         gap: 6px;
         cursor: pointer;
         transition: background .14s ease;
         position: relative;
      }

      .day:nth-child(7n) {
         border-right: 0;
      }

      .day:hover {
         background: #f7f6fb;
      }

      .day.other {
         background: #fbfcfe;
         color: var(--cal-faint);
      }

      .day.other .num {
         color: var(--cal-faint);
      }

      .day.today {
         background: #f0f9ff;
      }

      .day.sel {
         background: var(--cal-primary-soft);
         box-shadow: inset 0 0 0 2px rgba(37, 33, 97, .35);
         z-index: 1;
      }

      .num {
         font-size: .8rem;
         font-weight: 700;
         color: var(--cal-text);
         width: 26px;
         height: 26px;
         border-radius: 50%;
         display: flex;
         align-items: center;
         justify-content: center;
         transition: background .14s ease, color .14s ease;
      }

      .day.today .num {
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         color: #fff;
         box-shadow: 0 4px 10px rgba(37, 33, 97, .35);
      }

      .pills {
         display: flex;
         flex-direction: column;
         gap: 3px;
         min-height: 0;
      }

      .pill {
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         border-left: 3px solid var(--cal-primary);
         border-radius: 4px;
         padding: 3px 6px;
         font-size: .7rem;
         font-weight: 600;
         line-height: 1.35;
         white-space: nowrap;
         overflow: hidden;
         text-overflow: ellipsis;
         transition: filter .14s ease;
      }

      .pill:hover {
         filter: brightness(.96);
      }

      .pill.recur-none {
         background: #f1f5f9;
         color: #475569;
         border-left-color: var(--recur-none);
      }

      .pill.recur-daily {
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         border-left-color: var(--recur-daily);
      }

      .pill.recur-weekly {
         background: #e0f2fe;
         color: #075985;
         border-left-color: var(--recur-weekly);
      }

      .pill.recur-monthly {
         background: #f3e8ff;
         color: #5b21b6;
         border-left-color: var(--recur-monthly);
      }

      .pill.recur-custom {
         background: #fef3c7;
         color: #92400e;
         border-left-color: var(--recur-custom);
      }

      .more {
         font-size: .7rem;
         color: var(--cal-muted);
         font-weight: 600;
         padding-left: 4px;
         margin-top: 1px;
      }

      .list {
         display: flex;
         flex-direction: column;
         gap: 10px;
      }

      .item {
         background: #fff;
         border: 1px solid var(--cal-border);
         border-radius: 12px;
         padding: 14px;
         cursor: pointer;
         transition: .16s ease;
         position: relative;
         overflow: hidden;
      }

      .item::before {
         content: "";
         position: absolute;
         left: 0;
         top: 0;
         bottom: 0;
         width: 3px;
         background: var(--cal-primary);
      }

      .item.recur-none::before {
         background: var(--recur-none);
      }

      .item.recur-daily::before {
         background: var(--recur-daily);
      }

      .item.recur-weekly::before {
         background: var(--recur-weekly);
      }

      .item.recur-monthly::before {
         background: var(--recur-monthly);
      }

      .item.recur-custom::before {
         background: var(--recur-custom);
      }

      .item:hover {
         border-color: rgba(37, 33, 97, .35);
         box-shadow: var(--cal-shadow);
         transform: translateY(-1px);
      }

      .it-c {
         color: var(--cal-primary);
         font-size: .78rem;
         font-weight: 700;
         margin-bottom: 6px;
         display: flex;
         align-items: flex-start;
         gap: 6px;
         line-height: 1.4;
      }

      .it-c i {
         font-size: .95rem;
         line-height: 1.2;
         flex-shrink: 0;
         margin-top: 1px;
      }

      .it-c span {
         flex: 1;
         min-width: 0;
         word-break: break-word;
      }

      .it-t {
         font-weight: 700;
         color: var(--cal-text);
         margin-bottom: 6px;
         font-size: .95rem;
         line-height: 1.4;
      }

      .it-m {
         font-size: .8rem;
         color: var(--cal-muted);
         line-height: 1.5;
         display: flex;
         align-items: center;
         gap: 8px;
         flex-wrap: wrap;
      }

      .it-m > i {
         font-size: .95rem;
         line-height: 1;
         flex-shrink: 0;
      }

      .it-m .it-time {
         display: inline-flex;
         align-items: center;
      }

      .it-m .dot-sep {
         width: 3px;
         height: 3px;
         border-radius: 50%;
         background: var(--cal-faint);
         display: inline-block;
         flex-shrink: 0;
      }

      .it-m .recur-tag {
         display: inline-flex;
         align-items: center;
         gap: 4px;
         padding: 3px 10px;
         border-radius: 999px;
         font-size: .72rem;
         font-weight: 600;
         line-height: 1.4;
      }

      .it-m .recur-tag i {
         font-size: .85rem;
         line-height: 1;
      }

      .recur-tag.recur-none {
         background: #f1f5f9;
         color: #475569;
      }

      .recur-tag.recur-daily {
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
      }

      .recur-tag.recur-weekly {
         background: #e0f2fe;
         color: #075985;
      }

      .recur-tag.recur-monthly {
         background: #f3e8ff;
         color: #5b21b6;
      }

      .recur-tag.recur-custom {
         background: #fef3c7;
         color: #92400e;
      }

      .muted {
         font-size: .82rem;
         color: var(--cal-muted);
         line-height: 1.45;
      }

      .empty {
         background: #fff;
         border: 1px dashed var(--cal-border-strong);
         border-radius: 14px;
         padding: 32px 18px;
         text-align: center;
         color: var(--cal-muted);
      }

      .empty-icon {
         width: 48px;
         height: 48px;
         border-radius: 50%;
         background: var(--cal-primary-soft);
         color: var(--cal-primary);
         display: inline-flex;
         align-items: center;
         justify-content: center;
         font-size: 1.4rem;
         margin-bottom: 10px;
      }

      .sum {
         display: flex;
         gap: 10px;
         padding: 10px 0;
         border-top: 1px solid var(--cal-border);
      }

      .sum:first-child {
         border-top: 0;
         padding-top: 0;
      }

      .sum .dot {
         width: 8px;
         height: 8px;
         border-radius: 50%;
         background: var(--cal-primary);
         margin-top: 7px;
         flex-shrink: 0;
      }

      .legend-row {
         display: flex;
         align-items: center;
         gap: 10px;
         padding: 6px 0;
         font-size: .85rem;
         color: var(--cal-text-2);
      }

      .legend-dot {
         width: 12px;
         height: 12px;
         border-radius: 4px;
         display: inline-block;
      }

      .legend-dot.recur-none {
         background: var(--recur-none);
      }

      .legend-dot.recur-daily {
         background: var(--recur-daily);
      }

      .legend-dot.recur-weekly {
         background: var(--recur-weekly);
      }

      .legend-dot.recur-monthly {
         background: var(--recur-monthly);
      }

      .legend-dot.recur-custom {
         background: var(--recur-custom);
      }

      .selcount {
         display: inline-flex;
         align-items: center;
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         padding: 8px 14px;
         border-radius: 999px;
         font-weight: 700;
         font-size: .82rem;
      }

      .chips {
         display: flex;
         flex-wrap: wrap;
         gap: 8px;
      }

      .chip {
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         padding: 6px 12px;
         border-radius: 999px;
         font-size: .8rem;
         font-weight: 600;
         border: 1px solid rgba(37, 33, 97, .18);
      }

      a.chip-link {
         text-decoration: none;
         transition: background .14s ease, color .14s ease, box-shadow .14s ease;
      }

      a.chip-link:hover,
      a.chip-link:focus {
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         color: #fff;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
         text-decoration: none;
         border-color: transparent;
      }

      .drow {
         margin-bottom: 16px;
      }

      .dl {
         font-size: .7rem;
         text-transform: uppercase;
         letter-spacing: .12em;
         color: var(--cal-muted);
         margin-bottom: 5px;
         font-weight: 700;
      }

      .dv {
         color: var(--cal-text);
         font-weight: 500;
         line-height: 1.55;
         font-size: .92rem;
      }

      .choices__inner,
      .form-control,
      .form-select {
         border-radius: 10px !important;
         border-color: var(--cal-border-strong) !important;
         min-height: 44px;
         font-size: .92rem;
      }

      .form-control:focus,
      .form-select:focus,
      .choices__inner:focus-within {
         border-color: rgba(37, 33, 97, .5) !important;
         box-shadow: 0 0 0 .2rem rgba(37, 33, 97, .15) !important;
      }

      .choices__list--multiple .choices__item {
         background: var(--cal-primary);
         border-color: var(--cal-primary);
      }

      .form-label {
         font-weight: 600;
         color: var(--cal-text-2);
         font-size: .85rem;
         margin-bottom: 6px;
      }

      .modal-content {
         border: 0;
         border-radius: 18px;
         overflow: hidden;
         box-shadow: var(--cal-shadow-lg);
      }

      .modal-header {
         background: linear-gradient(180deg, #fff 0%, #fafafd 100%);
         border-bottom: 1px solid var(--cal-border);
         padding: 18px 22px;
      }

      .modal-title {
         font-weight: 700;
         color: var(--cal-text);
         letter-spacing: -.01em;
      }

      .modal-body {
         padding: 18px;
         background: #fcfcfe;
      }

      /* Reminder form rendered inside a SweetAlert popup. */
      .reminder-swal {
         width: min(46rem, 96vw) !important;
         padding: 0 0 1.25rem !important;
      }

      .reminder-swal.client-swal {
         width: min(34rem, 96vw) !important;
      }

      .reminder-swal .swal2-title {
         padding: 16px 24px 2px;
         text-align: left;
         font-size: 1.15rem;
         color: var(--cal-text);
      }

      .reminder-swal .swal2-html-container {
         margin: 0 !important;
         padding: 8px 24px 4px !important;
         text-align: left !important;
         overflow: visible !important;
         max-height: none !important;
         color: var(--cal-text);
      }

      .reminder-swal .swal2-actions {
         margin: 8px 24px 0;
         justify-content: flex-end;
         width: auto;
         padding-top: 10px;
         border-top: 1px solid var(--cal-border);
      }

      .reminder-swal .swal2-close {
         font-size: 1.8rem;
      }

      #reminderForm .mb-3 {
         margin-bottom: .55rem !important;
      }

      #reminderForm .form-control,
      #reminderForm .form-select {
         min-height: 40px;
      }

      #reminderForm #descInput {
         min-height: 54px;
      }

      /* Clients summary shown at the top of the reminder-details popup. */
      .rem-clients {
         background: var(--cal-panel);
         border: 1px solid var(--cal-border);
         border-radius: 12px;
         padding: 10px 12px;
         margin-bottom: 10px;
      }

      .rem-clients-empty {
         color: var(--cal-muted);
         font-size: .85rem;
         padding: 2px 0 10px;
      }

      .btn-select-clients {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         border: 1px solid var(--cal-primary);
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         font-weight: 600;
         font-size: .85rem;
         border-radius: 9px;
         padding: 8px 14px;
         transition: .14s ease;
      }

      .btn-select-clients:hover {
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         color: #fff;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
      }

      .client-picker {
         background: var(--cal-panel);
         border: 1px solid var(--cal-border);
         border-radius: 12px;
         padding: 12px;
         display: flex;
         flex-direction: column;
      }

      .cpk-chips {
         display: none;
         flex-wrap: wrap;
         gap: 6px;
         margin-bottom: 8px;
      }

      .cpk-chip {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         background: var(--cal-primary);
         color: #fff;
         border-radius: 999px;
         padding: 3px 6px 3px 11px;
         font-size: .74rem;
         font-weight: 600;
         max-width: 100%;
      }

      .cpk-chip > span,
      .cpk-chip {
         overflow: hidden;
      }

      .cpk-chip .cpk-chip-x {
         border: 0;
         background: rgba(255, 255, 255, .25);
         color: #fff;
         border-radius: 50%;
         width: 16px;
         height: 16px;
         line-height: 1;
         font-size: .8rem;
         display: inline-flex;
         align-items: center;
         justify-content: center;
         flex-shrink: 0;
         cursor: pointer;
      }

      .cpk-chip .cpk-chip-x:hover {
         background: rgba(255, 255, 255, .45);
      }

      .client-picker-head {
         display: flex;
         align-items: center;
         justify-content: space-between;
         gap: 10px;
         flex-wrap: wrap;
         margin-bottom: 10px;
      }

      .btn-clear-clients {
         border: 1px solid var(--cal-border-strong);
         background: #fff;
         color: var(--cal-text-2);
         border-radius: 8px;
         padding: 4px 10px;
         font-size: .78rem;
         font-weight: 600;
         display: inline-flex;
         align-items: center;
         gap: 4px;
         transition: .14s ease;
      }

      .btn-clear-clients:hover {
         color: var(--tone-overdue);
         border-color: var(--tone-overdue);
      }

      .cpk-search {
         position: relative;
      }

      .cpk-search > i {
         position: absolute;
         left: 12px;
         top: 50%;
         transform: translateY(-50%);
         color: var(--cal-faint);
         font-size: 1.05rem;
         pointer-events: none;
      }

      .cpk-search .form-control {
         padding-left: 36px;
      }

      .client-pick-list {
         background: #fff;
         border: 1px solid var(--cal-border);
         border-radius: 10px;
         padding: 4px;
      }

      .cpk-section {
         font-size: .68rem;
         font-weight: 700;
         text-transform: uppercase;
         letter-spacing: .1em;
         color: var(--cal-muted);
         padding: 8px 10px 4px;
      }

      .cpk-row {
         display: flex;
         align-items: center;
         gap: 10px;
         padding: 6px 10px;
         border-radius: 8px;
         cursor: pointer;
         margin: 0;
         font-size: .86rem;
         color: var(--cal-text);
         transition: background .12s ease;
      }

      .cpk-row:hover {
         background: var(--cal-primary-soft);
      }

      .cpk-row.is-sel {
         background: var(--cal-primary-soft);
         color: var(--cal-primary-ink);
         font-weight: 600;
      }

      .cpk-row .cpk-cb {
         width: 17px;
         height: 17px;
         flex-shrink: 0;
         accent-color: var(--cal-primary);
         cursor: pointer;
      }

      .cpk-row .cpk-name {
         flex: 1;
         min-width: 0;
         word-break: break-word;
         line-height: 1.35;
      }

      .cpk-empty {
         padding: 28px 14px;
         text-align: center;
         color: var(--cal-muted);
         font-size: .86rem;
      }

      .invoice-services-box {
         background: var(--cal-panel);
         border: 1px solid var(--cal-border);
         border-radius: 10px;
         padding: 10px 12px;
         max-height: 128px;
         overflow-y: auto;
      }

      .dates-list {
         background: var(--cal-panel);
         border: 1px solid var(--cal-border);
         border-radius: 10px;
         padding: 8px 10px;
         max-height: 220px;
         overflow-y: auto;
      }

      .dates-list .date-row {
         display: flex;
         gap: 8px;
         align-items: center;
         margin-bottom: 6px;
      }

      .dates-list .date-row:last-child {
         margin-bottom: 0;
      }

      .dates-list .date-row .form-control {
         flex: 1 1 auto;
      }

      .dates-list .date-row .date-row-remove {
         border: 1px solid var(--cal-border);
         background: #fff;
         color: var(--cal-muted);
         border-radius: 8px;
         width: 34px;
         height: 34px;
         display: inline-flex;
         align-items: center;
         justify-content: center;
         flex-shrink: 0;
      }

      .dates-list .date-row .date-row-remove:hover {
         color: var(--tone-overdue);
         border-color: var(--tone-overdue);
      }

      .dates-list .dates-empty {
         color: var(--cal-muted);
         font-size: .85rem;
         padding: 6px 2px;
      }

      .invoice-services-row + .invoice-services-row {
         border-top: 1px dashed var(--cal-border);
         margin-top: 8px;
         padding-top: 8px;
      }

      .invoice-services-row .chips {
         display: flex;
         flex-wrap: wrap;
         gap: 6px;
      }

      .invoice-services-row .chip {
         background: #fff;
         border: 1px solid var(--cal-border);
         color: var(--cal-text-2);
         border-radius: 999px;
         padding: 3px 10px;
         font-size: .78rem;
         max-width: 100%;
         overflow: hidden;
         text-overflow: ellipsis;
         white-space: nowrap;
         display: inline-block;
         line-height: 1.5;
      }

      .invoice-services-row .chip .chip-date {
         color: var(--cal-muted);
         font-size: .72rem;
         margin-left: 6px;
      }

      .modal-footer {
         border-top: 1px solid var(--cal-border);
         padding: 14px 22px;
         background: #fff;
      }

      .modal-footer .btn-primary {
         background: linear-gradient(135deg, var(--cal-primary) 0%, var(--cal-primary-2) 100%);
         border-color: transparent;
         font-weight: 600;
         padding: 9px 18px;
         border-radius: 10px;
         box-shadow: 0 6px 14px rgba(37, 33, 97, .28);
      }

      .modal-footer .btn-primary:hover {
         filter: brightness(1.05);
      }

      .modal-footer .btn-light,
      .modal-footer .btn-outline-primary {
         font-weight: 600;
         padding: 9px 18px;
         border-radius: 10px;
      }

      .runtime-reminder-popup {
         display: none !important;
      }

      @media (max-width: 1200px) {
         .rem-wrap {
            grid-template-columns: 1fr;
         }

         .rem-left,
         .rem-right {
            border: 0;
            border-bottom: 1px solid var(--cal-border);
         }

         .stat-strip {
            grid-template-columns: repeat(2, minmax(0, 1fr));
         }
      }

      @media (max-width: 768px) {

         .rem-left,
         .rem-mid,
         .rem-right,
         .rem-head {
            padding: 16px;
         }

         .day {
            min-height: 92px;
            padding: 6px;
         }

         .toolbar,
         .panel-h {
            flex-direction: column;
            align-items: stretch;
         }

         .month,
         .panel-date {
            font-size: 1rem;
         }

         .wd {
            padding: 9px 10px;
            font-size: .65rem;
         }

         .stat-strip {
            grid-template-columns: 1fr;
         }

         .stat-v {
            font-size: 1.4rem;
         }

         .rem-head {
            flex-direction: column;
            align-items: flex-start;
         }
      }
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
         <!-- <div class="card card-body">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">Calendar Reminder</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Calendar Reminder</li>
                     </ul>
                  </div>
               </div>
            </div>
         </div> -->
         <div class="stat-strip">
            <div class="stat-card">
               <div class="stat-icon"><i class="ti ti-bell-ringing"></i></div>
               <div>
                  <div class="stat-l">Assigned to you</div>
                  <div class="stat-v" id="totalReminderCount">0</div>
               </div>
            </div>
            <div class="stat-card tone-today">
               <div class="stat-icon"><i class="ti ti-calendar-event"></i></div>
               <div>
                  <div class="stat-l">Today</div>
                  <div class="stat-v" id="todayReminderCount">0</div>
               </div>
            </div>
            <div class="stat-card tone-upcoming">
               <div class="stat-icon"><i class="ti ti-clock-hour-4"></i></div>
               <div>
                  <div class="stat-l">Next 7 days</div>
                  <div class="stat-v" id="weekReminderCount">0</div>
               </div>
            </div>
            <div class="stat-card tone-overdue">
               <div class="stat-icon"><i class="ti ti-alert-circle"></i></div>
               <div>
                  <div class="stat-l">Overdue</div>
                  <div class="stat-v" id="overdueReminderCount">0</div>
               </div>
            </div>
         </div>

         <div class="rem-shell">
            <div class="rem-head">
               <div>
                  <h2><span class="head-mark"><i class="ti ti-calendar-stats"></i></span> Client Reminder Calendar</h2>
               </div>
               <button type="button" class="btn act primary" id="headAddBtn"><i class="ti ti-plus"></i> New Reminder</button>
            </div>
            <div class="rem-wrap">
               <aside class="rem-left">
                  <div class="box">
                     <h6><i class="ti ti-bolt"></i> Quick actions</h6>
                     <button type="button" class="btn act primary w-100 mb-2" id="leftAddBtn"><i class="ti ti-plus"></i> Add reminder</button>
                     <button type="button" class="btn act w-100" id="goTodayBtn"><i class="ti ti-calendar"></i> Go to today</button>
                  </div>
                  <div class="box">
                     <h6><i class="ti ti-history"></i> Upcoming reminders</h6>
                     <div id="upcomingList"></div>
                  </div>
                  <div class="box">
                     <h6><i class="ti ti-color-swatch"></i> Recurrence legend</h6>
                     <div class="legend-row"><span class="legend-dot recur-none"></span> One time</div>
                     <div class="legend-row"><span class="legend-dot recur-daily"></span> Daily</div>
                     <div class="legend-row"><span class="legend-dot recur-weekly"></span> Weekly</div>
                     <div class="legend-row"><span class="legend-dot recur-monthly"></span> Monthly</div>
                     <div class="legend-row"><span class="legend-dot recur-custom"></span> Custom interval</div>
                  </div>
               </aside>
               <main class="rem-mid">
                  <div class="toolbar">
                     <div>
                        <div class="month" id="monthLabel"></div>
                        <div class="muted small">Click any day to view or add reminders</div>
                     </div>
                     <div class="d-flex gap-2 flex-wrap align-items-center">
                        <div class="navs">
                           <button type="button" id="prevBtn" aria-label="Previous"><i class="ti ti-chevron-left"></i></button>
                           <button type="button" id="nextBtn" aria-label="Next"><i class="ti ti-chevron-right"></i></button>
                        </div>
                        <button type="button" class="btn act" id="todayBtn"><i class="ti ti-target"></i> Today</button>
                        <div class="view-switch" id="viewSwitch" role="tablist" aria-label="Calendar view">
                           <button type="button" class="active" data-view="month">month</button>
                           <button type="button" data-view="week">week</button>
                           <button type="button" data-view="day">day</button>
                           <button type="button" data-view="list">list</button>
                        </div>
                     </div>
                  </div>
                  <div id="viewMonth">
                     <div class="week" id="weekHead"></div>
                     <div class="grid" id="calendarGrid"></div>
                  </div>
                  <div id="viewWeek" style="display:none;">
                     <div class="week" id="weekHead2"></div>
                     <div class="grid" id="weekGrid"></div>
                  </div>
                  <div id="viewDay" style="display:none;">
                     <div class="day-single">
                        <div class="ds-head" id="dayHead"></div>
                        <div class="list" id="dayList"></div>
                     </div>
                  </div>
                  <div id="viewList" style="display:none;">
                     <div class="all-list" id="allList"></div>
                  </div>
               </main>
               <aside class="rem-right">
                  <div class="panel-h">
                     <div>
                        <div class="panel-date" id="selDateLabel"></div>
                        <div class="muted small" id="selDateCount">No reminders selected yet</div>
                     </div>
                     <button type="button" class="btn act primary" id="panelAddBtn"><i class="ti ti-plus"></i> Add</button>
                  </div>
                  <div class="list" id="selDateList"></div>
               </aside>
            </div>
         </div>
      </div>
   </div>

   <!-- Two SweetAlert popups share these hidden hosts. The reminder-details form
        and the client picker each get moved into their popup on open and returned
        here on close, so field values and event wiring persist across the bounce. -->
   <div id="reminderFormHost" style="display:none">
      <form id="reminderForm" onsubmit="return false;">
         <input type="hidden" id="rowIds" value="[]">
         <div class="rem-clients">
            <div class="client-picker-head">
               <label class="form-label mb-0"><i class="ti ti-users"></i> Clients</label>
               <div class="d-flex align-items-center gap-2">
                  <span class="selcount js-selcount">Selected: 0</span>
                  <button type="button" class="btn-clear-clients js-clear-clients" style="display:none;"><i
                        class="ti ti-x"></i> Clear</button>
               </div>
            </div>
            <div class="cpk-chips js-selected-chips" id="selectedChips"></div>
            <div class="rem-clients-empty" id="remClientsEmpty">No clients selected yet.</div>
            <button type="button" class="btn-select-clients" id="openClientPickerBtn"><i class="ti ti-users-group"></i>
               Select clients</button>
         </div>
         <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Title</label><input type="text" class="form-control"
                  id="titleInput" required></div>
            <div class="col-md-6 mb-3" id="singleDateWrap"><label class="form-label">Date and time</label><input
                  type="datetime-local" class="form-control" id="dateInput"></div>
         </div>
         <div class="row">
            <div class="col-12 mb-3"><label class="form-label">Recurrence</label><select class="form-select"
                  id="rtypeInput">
                  <option value="none">None</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="custom">Custom interval</option>
                  <option value="specific_dates">Specific dates</option>
               </select></div>
            <div class="col-md-6 mb-3" id="cintWrap" style="display:none;"><label class="form-label">Custom
                  interval</label><input type="number" min="1" class="form-control" id="cintInput" value="1"></div>
            <div class="col-md-6 mb-3" id="cunitWrap" style="display:none;"><label class="form-label">Interval
                  unit</label><select class="form-select" id="cunitInput">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                  <option value="hours">Hours</option>
               </select></div>
         </div>
         <div class="mb-3" id="datesWrap" style="display:none;">
            <label class="form-label d-flex align-items-center justify-content-between mb-1">
               <span>Specific dates <span class="muted small fw-normal" id="datesCountLbl"></span></span>
               <button type="button" class="btn btn-sm btn-outline-primary" id="addDateBtn"><i class="ti ti-plus"></i>
                  Add date</button>
            </label>
            <div class="form-text mb-2">Pick one or more specific date/time occurrences. A separate reminder is created
               for each date.</div>
            <div id="datesList" class="dates-list"></div>
         </div>
         <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="descInput"
               rows="2"></textarea></div>
         <div class="mb-3"><label class="form-label">Assign to user</label><select class="form-select" id="assignInput">
               <option value="">Current logged-in user</option><?php foreach ($users as $u): ?>
                  <option value="<?php echo (int) $u['id']; ?>" <?php echo (int) $u['id'] === $user_id ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option><?php endforeach; ?>
            </select>
         </div>
      </form>
   </div>

   <div id="clientPickerHost" style="display:none">
      <div id="clientPicker" class="client-picker">
         <div class="client-picker-head">
            <label class="form-label mb-0"><i class="ti ti-users"></i> Clients</label>
            <div class="d-flex align-items-center gap-2">
               <span class="selcount js-selcount">Selected: 0</span>
               <button type="button" class="btn-clear-clients js-clear-clients" style="display:none;"><i
                     class="ti ti-x"></i> Clear</button>
            </div>
         </div>
         <select id="serviceFilterInput" class="form-select mb-2">
            <option value="">All services</option>
         </select>
         <div class="cpk-search mb-2">
            <i class="ti ti-search"></i>
            <input type="text" id="clientSearchInput" class="form-control" placeholder="Search clients by name...">
         </div>
         <div class="cpk-chips js-selected-chips" id="pickerChips"></div>
         <div class="form-text mb-1" id="clientFilterStatus" aria-live="polite">Search or filter to find clients.</div>
         <div id="clientPickList" class="client-pick-list"></div>
         <div class="mt-2">
            <label class="form-label d-flex align-items-center justify-content-between mb-1">
               <span>Services <span class="muted small fw-normal">(from previous invoices)</span></span>
               <span class="muted small" id="invoiceServicesRange">Last 2 years</span>
            </label>
            <div id="invoiceServicesBox" class="invoice-services-box">
               <span class="muted small">Tick clients to see their services from past invoices.</span>
            </div>
         </div>
      </div>
   </div>
   <div class="modal fade" id="detailModal" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title">Reminder details</h5><button type="button" class="btn-close"
                  data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-primary"
                  id="editBtn">Edit</button><button type="button" class="btn btn-light"
                  data-bs-dismiss="modal">Close</button></div>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   <script>
      (function() {
         const popups = document.querySelectorAll('.runtime-reminder-popup');
         popups.forEach(function(popup) {
            popup.remove();
         });
      })();
   </script>
   <script>
      (function() {
         const currentUserId = <?php echo (int) $user_id; ?>,
            currentUserName = <?php echo json_encode($user_full_name, JSON_UNESCAPED_UNICODE); ?>,
            clients = <?php echo json_encode($clients, JSON_UNESCAPED_UNICODE); ?>,
            users = <?php echo json_encode($users, JSON_UNESCAPED_UNICODE); ?>,
            serviceFilters = <?php echo json_encode($serviceOptions, JSON_UNESCAPED_UNICODE); ?>,
            preselectClientId = <?php echo (int) ($_GET['client_id'] ?? 0); ?>;
         clients.forEach(c => {
            c.id = Number(c.id);
            c.service_ids = Array.isArray(c.service_ids) ? c.service_ids.map(Number) : [];
         });
         serviceFilters.forEach(s => {
            s.id = Number(s.id);
         });
         let reminders = <?php echo json_encode($initialReminders, JSON_UNESCAPED_UNICODE); ?>,
            selectedDate = new Date(),
            currentMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1),
            currentView = 'month',
            selectedClientIds = [],
            editingReminder = null,
            currentDetailReminder = null;
         const week = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            q = s => document.querySelector(s),
            grid = q('#calendarGrid'),
            weekHead = q('#weekHead');
         const monthLabel = q('#monthLabel'),
            selDateLabel = q('#selDateLabel'),
            selDateCount = q('#selDateCount'),
            selDateList = q('#selDateList'),
            upcomingList = q('#upcomingList'),
            totalReminderCount = q('#totalReminderCount'),
            todayReminderCount = q('#todayReminderCount'),
            weekReminderCount = q('#weekReminderCount'),
            overdueReminderCount = q('#overdueReminderCount'),
            serviceFilterInput = q('#serviceFilterInput'),
            clientSearchInput = q('#clientSearchInput'),
            clientFilterStatus = q('#clientFilterStatus'),
            clientPickList = q('#clientPickList');
         const rowIds = q('#rowIds'),
            titleInput = q('#titleInput'),
            descInput = q('#descInput'),
            dateInput = q('#dateInput'),
            rtypeInput = q('#rtypeInput'),
            cintInput = q('#cintInput'),
            cunitInput = q('#cunitInput'),
            cintWrap = q('#cintWrap'),
            cunitWrap = q('#cunitWrap'),
            singleDateWrap = q('#singleDateWrap'),
            datesWrap = q('#datesWrap'),
            datesList = q('#datesList'),
            datesCountLbl = q('#datesCountLbl'),
            addDateBtn = q('#addDateBtn'),
            assignInput = q('#assignInput'),
            detailBody = q('#detailBody');
         const detailModal = new bootstrap.Modal(q('#detailModal'));
         serviceFilters.forEach(s => {
            const o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.name;
            serviceFilterInput.appendChild(o);
         });
         const clientNameMap = new Map(clients.map(c => [Number(c.id), c.name]));
         const serviceNameMap = new Map(serviceFilters.map(s => [Number(s.id), s.name]));
         const weekHead2 = q('#weekHead2'),
            weekGrid = q('#weekGrid'),
            dayHead = q('#dayHead'),
            dayList = q('#dayList'),
            allList = q('#allList'),
            viewMonth = q('#viewMonth'),
            viewWeek = q('#viewWeek'),
            viewDay = q('#viewDay'),
            viewList = q('#viewList');
         week.forEach(d => {
            const n = document.createElement('div');
            n.className = 'wd';
            n.textContent = d;
            weekHead.appendChild(n);
            const n2 = document.createElement('div');
            n2.className = 'wd';
            n2.textContent = d;
            weekHead2.appendChild(n2);
         });
         const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
         } [m]));
         const dkey = d => [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-');
         const dtDisp = v => new Date(String(v).replace(' ', 'T')).toLocaleString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
         });
         const dayDisp = d => d.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
         });
         const toLocal = v => {
            const d = new Date(String(v).replace(' ', 'T'));
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}T${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
         };

         function normalize(r) {
            r.row_ids = (r.row_ids || []).map(Number);
            r.client_ids = (r.client_ids || []).map(Number);
            r.clients = Array.isArray(r.clients) ? r.clients : [];
            return r;
         }

         function recurClass(r) {
            const t = String(r && r.recurrence_type || '').toLowerCase();
            if (t === 'none' || t === 'daily' || t === 'weekly' || t === 'monthly' || t === 'custom') return 'recur-' + t;
            if (t === 'specific_dates') return 'recur-custom';
            return 'recur-weekly';
         }

         function recurTag(r) {
            const t = String(r && r.recurrence_type || '').toLowerCase();
            if (!t || t === 'none') return '';
            const lbl = String(r && r.recurrence_label || '').trim();
            if (!lbl || lbl.toLowerCase() === 'general') return '';
            return `<span class="dot-sep"></span><span class="recur-tag ${recurClass(r)}"><i class="ti ti-repeat"></i> ${esc(lbl)}</span>`;
         }

         function byDate() {
            const m = {};
            reminders.forEach(r => {
               const k = String(r.date).split(' ')[0];
               if (!m[k]) m[k] = [];
               m[k].push(r);
            });
            return m;
         }

         function clientSummary(r) {
            if (!r.clients || !r.clients.length) return 'No clients linked';
            if (r.clients.length === 1) return r.clients[0].name;
            return `${r.clients[0].name} +${r.clients.length - 1} more`;
         }

         function renderCalendar() {
            if (currentView === 'week') return renderWeekView();
            if (currentView === 'day') return renderDayView();
            if (currentView === 'list') return renderListView();
            renderMonthView();
         }

         function renderMonthView() {
            const map = byDate();
            grid.innerHTML = '';
            monthLabel.textContent = currentMonth.toLocaleDateString('en-US', {
               month: 'long',
               year: 'numeric'
            });
            const first = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1),
               fd = first.getDay(),
               dim = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0).getDate(),
               prev = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 0).getDate(),
               today = dkey(new Date());
            for (let i = fd - 1; i >= 0; i--) grid.appendChild(cell(new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, prev - i), true, map, today));
            for (let d = 1; d <= dim; d++) grid.appendChild(cell(new Date(currentMonth.getFullYear(), currentMonth.getMonth(), d), false, map, today));
            const need = 42 - grid.children.length;
            for (let d = 1; d <= need; d++) grid.appendChild(cell(new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, d), true, map, today));
         }

         function weekStart(d) {
            const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            x.setDate(x.getDate() - x.getDay());
            return x;
         }

         function renderWeekView() {
            const map = byDate(),
               today = dkey(new Date()),
               start = weekStart(selectedDate);
            weekGrid.innerHTML = '';
            const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
            const sameMonth = start.getMonth() === end.getMonth();
            monthLabel.textContent = sameMonth
               ? `${start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' })} – ${end.getDate()}, ${end.getFullYear()}`
               : `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            for (let i = 0; i < 7; i++) {
               const d = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i);
               weekGrid.appendChild(cell(d, false, map, today));
            }
         }

         function renderDayView() {
            const key = dkey(selectedDate),
               items = reminders.filter(r => String(r.date).split(' ')[0] === key).sort((a, b) => new Date(String(a.date).replace(' ', 'T')) - new Date(String(b.date).replace(' ', 'T')));
            monthLabel.textContent = dayDisp(selectedDate);
            dayHead.textContent = `${items.length} reminder${items.length === 1 ? '' : 's'} on this day`;
            if (!items.length) {
               dayList.innerHTML = '<div class="empty"><div class="empty-icon"><i class="ti ti-calendar-off"></i></div><div class="fw-semibold mb-1" style="color:var(--cal-text)">No reminders on this date</div><div class="small">Use the Add button to create a new reminder for one or multiple clients.</div></div>';
               return;
            }
            dayList.innerHTML = items.map(r => `<div class="item ${recurClass(r)}" data-id="${esc(r.id)}"><div class="it-c"><i class="ti ti-users"></i><span>${esc(clientSummary(r))}</span></div><div class="it-t">${esc(r.title)}</div><div class="it-m"><i class="ti ti-clock"></i><span class="it-time">${esc(dtDisp(r.date))}</span>${recurTag(r)}</div></div>`).join('');
            Array.from(dayList.querySelectorAll('.item')).forEach((n, i) => n.onclick = () => openDetail(items[i]));
         }

         function renderListView() {
            monthLabel.textContent = 'All reminders';
            const items = reminders.slice().sort((a, b) => new Date(String(a.date).replace(' ', 'T')) - new Date(String(b.date).replace(' ', 'T')));
            if (!items.length) {
               allList.innerHTML = '<div class="all-empty"><i class="ti ti-calendar-off" style="font-size:1.6rem;"></i><div class="fw-semibold mt-2" style="color:var(--cal-text)">No reminders yet</div><div class="small">Add a reminder to see it listed here.</div></div>';
               return;
            }
            const groups = {};
            items.forEach(r => {
               const k = String(r.date).split(' ')[0];
               (groups[k] = groups[k] || []).push(r);
            });
            const html = Object.keys(groups).map(k => {
               const d = new Date(k + 'T00:00:00');
               const head = d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
               const rows = groups[k].map(r => `<div class="item ${recurClass(r)}" data-id="${esc(r.id)}"><div class="it-c"><i class="ti ti-users"></i><span>${esc(clientSummary(r))}</span></div><div class="it-t">${esc(r.title)}</div><div class="it-m"><i class="ti ti-clock"></i><span class="it-time">${esc(dtDisp(r.date))}</span>${recurTag(r)}</div></div>`).join('');
               return `<div class="day-group"><div class="day-head">${esc(head)}</div>${rows}</div>`;
            }).join('');
            allList.innerHTML = html;
            const flat = [];
            Object.keys(groups).forEach(k => groups[k].forEach(r => flat.push(r)));
            Array.from(allList.querySelectorAll('.item')).forEach((n, i) => n.onclick = () => openDetail(flat[i]));
         }

         function setView(v) {
            currentView = v;
            viewMonth.style.display = v === 'month' ? '' : 'none';
            viewWeek.style.display = v === 'week' ? '' : 'none';
            viewDay.style.display = v === 'day' ? '' : 'none';
            viewList.style.display = v === 'list' ? '' : 'none';
            Array.from(document.querySelectorAll('#viewSwitch button')).forEach(b => b.classList.toggle('active', b.dataset.view === v));
            renderCalendar();
         }

         function cell(date, other, map, today) {
            const k = dkey(date),
               items = map[k] || [],
               el = document.createElement('div');
            el.className = 'day' + (other ? ' other' : '') + (k === today ? ' today' : '') + (k === dkey(selectedDate) ? ' sel' : '');
            el.innerHTML = `<div class="num">${date.getDate()}</div><div class="pills"></div>`;
            const wrap = el.querySelector('.pills');
            items.slice(0, 3).forEach(r => {
               const p = document.createElement('div');
               p.className = 'pill ' + recurClass(r);
               p.title = r.title;
               p.textContent = `${new Date(String(r.date).replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })} ${r.title}`;
               wrap.appendChild(p);
            });
            if (items.length > 3) {
               const m = document.createElement('div');
               m.className = 'more';
               m.textContent = `+${items.length - 3} more`;
               wrap.appendChild(m);
            }
            el.onclick = () => {
               selectedDate = date;
               if (other) currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
               renderCalendar();
               renderPanel();
            };
            return el;
         }

         function renderUpcoming() {
            const now = Date.now(),
               items = reminders.slice().sort((a, b) => new Date(String(a.date).replace(' ', 'T')) - new Date(String(b.date).replace(' ', 'T'))).filter(r => new Date(String(r.date).replace(' ', 'T')).getTime() >= now).slice(0, 5);
            if (!items.length) {
               upcomingList.innerHTML = '<div class="muted small">No upcoming reminders.</div>';
               return;
            }
            upcomingList.innerHTML = items.map(r => `<div class="sum"><div class="dot" style="background:var(--${recurClass(r)})"></div><div><div class="fw-semibold">${esc(r.title)}</div><div class="muted small">${esc(clientSummary(r))}</div><div class="muted small"><i class="ti ti-clock"></i> ${esc(dtDisp(r.date))}</div></div></div>`).join('');
         }

         function renderPanel() {
            const key = dkey(selectedDate),
               items = reminders.filter(r => String(r.date).split(' ')[0] === key).sort((a, b) => new Date(String(a.date).replace(' ', 'T')) - new Date(String(b.date).replace(' ', 'T')));
            selDateLabel.textContent = dayDisp(selectedDate);
            selDateCount.textContent = `${items.length} reminder${items.length === 1 ? '' : 's'} on this date`;
            if (!items.length) {
               selDateList.innerHTML = '<div class="empty"><div class="empty-icon"><i class="ti ti-calendar-off"></i></div><div class="fw-semibold mb-1" style="color:var(--cal-text)">No reminders on this date</div><div class="small">Use the Add button to create a new reminder for one or multiple clients.</div></div>';
               return;
            }
            selDateList.innerHTML = items.map((r, i) => `<div class="item ${recurClass(r)}" data-i="${i}"><div class="it-c"><i class="ti ti-users"></i><span>${esc(clientSummary(r))}</span></div><div class="it-t">${esc(r.title)}</div><div class="it-m"><i class="ti ti-clock"></i><span class="it-time">${esc(dtDisp(r.date))}</span>${recurTag(r)}</div></div>`).join('');
            Array.from(selDateList.querySelectorAll('.item')).forEach((n, i) => n.onclick = () => openDetail(items[i]));
         }

         function renderStats() {
            totalReminderCount.textContent = String(reminders.length);
            const now = new Date(),
               todayK = dkey(now),
               nowMs = now.getTime(),
               weekMs = nowMs + 7 * 24 * 60 * 60 * 1000;
            let today = 0,
               week = 0,
               overdue = 0;
            reminders.forEach(r => {
               const t = new Date(String(r.date).replace(' ', 'T')).getTime();
               if (String(r.date).split(' ')[0] === todayK) today++;
               if (t >= nowMs && t <= weekMs) week++;
               if (t < nowMs && String(r.date).split(' ')[0] !== todayK) overdue++;
            });
            if (todayReminderCount) todayReminderCount.textContent = String(today);
            if (weekReminderCount) weekReminderCount.textContent = String(week);
            if (overdueReminderCount) overdueReminderCount.textContent = String(overdue);
            renderUpcoming();
         }

         function refresh() {
            reminders = reminders.map(normalize);
            renderStats();
            renderCalendar();
            renderPanel();
         }

         // Count, chips and the empty-hint appear in BOTH popups (reminder + picker),
         // so update every instance by class rather than a single element.
         function updateCount() {
            const n = selectedClientIds.length;
            document.querySelectorAll('.js-selcount').forEach(el => el.textContent = `Selected: ${n}`);
            document.querySelectorAll('.js-clear-clients').forEach(el => el.style.display = n ? '' : 'none');
         }

         function renderSelectedChips() {
            const has = selectedClientIds.length > 0;
            const html = has ? selectedClientIds.map(id => {
               const name = clientNameMap.get(Number(id)) || ('Client #' + id);
               return `<span class="cpk-chip" title="${esc(name)}"><span>${esc(name)}</span><button type="button" class="cpk-chip-x" data-id="${Number(id)}" aria-label="Remove">&times;</button></span>`;
            }).join('') : '';
            document.querySelectorAll('.js-selected-chips').forEach(box => {
               box.innerHTML = html;
               box.style.display = has ? 'flex' : 'none';
            });
            const emptyEl = document.getElementById('remClientsEmpty');
            if (emptyEl) emptyEl.style.display = has ? 'none' : '';
         }

         function clientHasService(client, sid) {
            sid = Number(sid || 0);
            if (!sid) return true;
            return Array.isArray(client && client.service_ids) && client.service_ids.map(Number).includes(sid);
         }

         // Only a handful of matches are rendered at once so the picker never needs a
         // scrollbar; the search box + service filter narrow things down, and already
         // selected clients are shown as removable chips above the list.
         const CLIENT_LIST_SHOW = 8;

         function clientRowHtml(c, selectedSet) {
            const id = Number(c.id),
               sel = selectedSet.has(id);
            return `<label class="cpk-row${sel ? ' is-sel' : ''}" data-id="${id}"><input type="checkbox" class="cpk-cb"${sel ? ' checked' : ''}><span class="cpk-name">${esc(c.name)}</span></label>`;
         }

         function renderClientPickList() {
            if (!clientPickList) return;
            const sid = Number(serviceFilterInput.value || 0),
               term = (clientSearchInput.value || '').trim().toLowerCase(),
               selectedSet = new Set(selectedClientIds.map(Number));
            let matched = sid ? clients.filter(c => clientHasService(c, sid)) : clients.slice();
            if (term) matched = matched.filter(c => String(c.name).toLowerCase().includes(term));
            const shown = matched.slice(0, CLIENT_LIST_SHOW);
            clientPickList.innerHTML = shown.length
               ? shown.map(c => clientRowHtml(c, selectedSet)).join('')
               : `<div class="cpk-empty">No clients match your search${sid ? ' and service filter' : ''}.</div>`;
            serviceFilterInput.disabled = !serviceFilters.length;
            let status;
            if (matched.length > CLIENT_LIST_SHOW)
               status = `Showing ${CLIENT_LIST_SHOW} of ${matched.length}${term ? ` matching "${term}"` : ''} — type to narrow.`;
            else {
               let base = `${matched.length} client${matched.length === 1 ? '' : 's'}`;
               if (term) base += ` matching "${term}"`;
               if (sid) base += ` with ${serviceNameMap.get(sid) || 'the selected service'}`;
               status = base + '.';
            }
            clientFilterStatus.textContent = status;
            updateCount();
            renderSelectedChips();
         }

         let invoiceReloadTimer = null;

         function scheduleInvoiceReload() {
            if (invoiceReloadTimer) clearTimeout(invoiceReloadTimer);
            invoiceReloadTimer = setTimeout(() => {
               loadInvoiceServices(selectedClientIds, 'invoiceServicesBox', 'invoiceServicesRange');
            }, 350);
         }

         function setClients(ids) {
            selectedClientIds = Array.from(new Set(ids.map(Number).filter(n => n > 0)));
            renderClientPickList();
         }

         function chipForService(s) {
            const desc = String(s && s.description || '');
            const short = desc.length > 80 ? desc.slice(0, 77).trim() + '...' : desc;
            const dateTxt = s && s.date && s.date !== '0000-00-00' ? esc(s.date) : '';
            const tip = (desc + (dateTxt ? ' — ' + dateTxt : '')).replace(/"/g, '&quot;');
            const dateBadge = dateTxt ? `<span class="chip-date">${dateTxt}</span>` : '';
            return `<span class="chip" title="${tip}">${esc(short)}${dateBadge}</span>`;
         }

         function loadInvoiceServices(clientIds, targetBoxId, rangeLblId) {
            const box = q('#' + (targetBoxId || 'invoiceServicesBox'));
            const rangeLbl = rangeLblId ? q('#' + rangeLblId) : null;
            if (!box) return;
            if (!Array.isArray(clientIds) || !clientIds.length) {
               box.innerHTML = '<span class="muted small">Select clients to see their services from past invoices.</span>';
               return;
            }
            box.innerHTML = '<span class="muted small"><i class="ti ti-loader-2"></i> Loading services from invoices in the last 2 years...</span>';
            $.ajax({
               url: 'reminder_calender.php?ajax=invoice_services',
               type: 'POST',
               data: { client_ids: clientIds },
               dataType: 'json'
            }).done(r => {
               if (!r || !r.success) {
                  box.innerHTML = '<span class="muted small text-danger">Unable to load services.</span>';
                  return;
               }
               if (rangeLbl && r.cutoff)
                  rangeLbl.textContent = `Last 2 years (since ${r.cutoff})`;
               const groups = Array.isArray(r.services) ? r.services : [];
               if (!groups.length) {
                  box.innerHTML = '<span class="muted small">No services billed to the selected client(s) in the prior two years.</span>';
                  return;
               }
               box.innerHTML = groups.map(g => {
                  const name = esc(g.client_name || ('Client #' + g.client_id));
                  const svcs = Array.isArray(g.services) ? g.services : [];
                  const inner = svcs.length
                     ? `<div class="chips">${svcs.map(chipForService).join('')}</div>`
                     : '<span class="muted small">No services found in the last 2 years.</span>';
                  return `<div class="invoice-services-row"><div class="fw-semibold small mb-1">${name}</div>${inner}</div>`;
               }).join('');
            }).fail(() => {
               box.innerHTML = '<span class="muted small text-danger">Network error while loading services.</span>';
            });
         }

         function updateDatesCountLbl() {
            const n = datesList.querySelectorAll('.date-row').length;
            datesCountLbl.textContent = n ? `(${n} ${n === 1 ? 'date' : 'dates'})` : '';
         }

         function addDateRow(val) {
            const row = document.createElement('div');
            row.className = 'date-row';
            const inp = document.createElement('input');
            inp.type = 'datetime-local';
            inp.className = 'form-control form-control-sm date-row-input';
            if (val) inp.value = val;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'date-row-remove';
            btn.title = 'Remove this date';
            btn.innerHTML = '<i class="ti ti-x"></i>';
            btn.onclick = () => {
               row.remove();
               updateDatesCountLbl();
               renderDatesEmpty();
            };
            row.appendChild(inp);
            row.appendChild(btn);
            const empty = datesList.querySelector('.dates-empty');
            if (empty) empty.remove();
            datesList.appendChild(row);
            updateDatesCountLbl();
         }

         function renderDatesEmpty() {
            if (!datesList.querySelector('.date-row') && !datesList.querySelector('.dates-empty')) {
               const e = document.createElement('div');
               e.className = 'dates-empty';
               e.textContent = 'No dates added yet. Click "Add date" to pick the first one.';
               datesList.appendChild(e);
            }
         }

         function resetDatesUI() {
            datesList.innerHTML = '';
            updateDatesCountLbl();
         }

         function toggleRecurrenceUI() {
            const v = rtypeInput.value;
            const isCustom = v === 'custom';
            const isSpecific = v === 'specific_dates';
            cintWrap.style.display = isCustom ? '' : 'none';
            cunitWrap.style.display = isCustom ? '' : 'none';
            datesWrap.style.display = isSpecific ? '' : 'none';
            singleDateWrap.style.display = isSpecific ? 'none' : '';
            dateInput.required = !isSpecific;
            if (isSpecific) {
               if (!datesList.querySelector('.date-row')) {
                  const seed = dateInput.value || `${dkey(selectedDate)}T09:00`;
                  addDateRow(seed);
               }
               renderDatesEmpty();
            }
         }
         const toggleCustom = toggleRecurrenceUI;

         function openForm(rem = null) {
            editingReminder = rem;
            // Editing seeds the selection from the reminder; a fresh add keeps
            // whatever the caller pre-set (empty, or a profile's ?client_id).
            if (rem) selectedClientIds = Array.from(new Set((rem.client_ids || []).map(Number).filter(n => n > 0)));
            serviceFilterInput.value = '';
            clientSearchInput.value = '';
            renderClientPickList();
            titleInput.value = rem ? rem.title || '' : '';
            descInput.value = rem ? rem.description || '' : '';
            rtypeInput.value = rem && rem.recurrence_type ? rem.recurrence_type : 'none';
            cintInput.value = rem && Number(rem.custom_interval) > 0 ? rem.custom_interval : 1;
            cunitInput.value = rem && rem.custom_unit ? rem.custom_unit : 'days';
            assignInput.value = rem && rem.assigned_user_id ? String(rem.assigned_user_id) : String(currentUserId);
            resetDatesUI();
            if (rem && String(rem.recurrence_type).toLowerCase() === 'specific_dates' && rem.series_uid) {
               const sid = String(rem.series_uid);
               const series = reminders.filter(x => String(x.series_uid || '') === sid);
               const allRowIds = [];
               const seenDates = new Set();
               const datesArr = [];
               series.forEach(x => {
                  (x.row_ids || []).forEach(id => { if (!allRowIds.includes(id)) allRowIds.push(id); });
                  const k = String(x.date || '');
                  if (k && !seenDates.has(k)) { seenDates.add(k); datesArr.push(k); }
               });
               datesArr.sort();
               rowIds.value = JSON.stringify(allRowIds.length ? allRowIds : (rem.row_ids || []));
               dateInput.value = datesArr[0] ? toLocal(datesArr[0]) : `${dkey(selectedDate)}T09:00`;
               toggleRecurrenceUI();
               resetDatesUI();
               (datesArr.length ? datesArr : [rem.date]).forEach(d => addDateRow(toLocal(d)));
               renderDatesEmpty();
            } else if (rem && rem.series_uid && ['daily', 'weekly', 'monthly', 'custom'].includes(String(rem.recurrence_type).toLowerCase())) {
               // Recurring series: gather every occurrence row so saving replaces
               // the whole series, and seed the single date with the start.
               const sid = String(rem.series_uid);
               const series = reminders.filter(x => String(x.series_uid || '') === sid);
               const allRowIds = [];
               const datesArr = [];
               series.forEach(x => {
                  (x.row_ids || []).forEach(id => { if (!allRowIds.includes(id)) allRowIds.push(id); });
                  if (x.date) datesArr.push(String(x.date));
               });
               datesArr.sort();
               rowIds.value = JSON.stringify(allRowIds.length ? allRowIds : (rem.row_ids || []));
               dateInput.value = toLocal(datesArr[0] || rem.date);
               toggleRecurrenceUI();
            } else {
               rowIds.value = JSON.stringify(rem ? rem.row_ids || [] : []);
               dateInput.value = rem ? toLocal(rem.date) : `${dkey(selectedDate)}T09:00`;
               toggleRecurrenceUI();
            }
            showReminderPopup();
         }

         // When true, closing the reminder popup means "go pick clients" rather than
         // cancel, so the .then handler opens the client picker instead of discarding.
         let goToClientPicker = false;

         // The reminder-details popup. Kept separate from openForm() so it can be
         // re-shown (with all field values intact) after bouncing to the client
         // picker — no re-initialisation of the form.
         function showReminderPopup() {
            const formEl = document.getElementById('reminderForm');
            Swal.fire({
               title: editingReminder ? 'Edit reminder' : 'Add reminder',
               html: formEl,
               showCancelButton: true,
               showCloseButton: true,
               focusConfirm: false,
               reverseButtons: true,
               heightAuto: false,
               confirmButtonText: 'Done',
               cancelButtonText: 'Cancel',
               confirmButtonColor: '#252161',
               allowOutsideClick: () => !Swal.isLoading(),
               customClass: { popup: 'reminder-swal' },
               didOpen: () => {
                  updateCount();
                  renderSelectedChips();
               },
               preConfirm: async () => {
                  const payload = collectReminderPayload();
                  if (!payload) return false;
                  try {
                     const r = await $.ajax({
                        url: 'reminder_calender.php?ajax=save_reminder',
                        type: 'POST',
                        data: payload,
                        dataType: 'json'
                     });
                     if (!r || !r.success) {
                        Swal.showValidationMessage(r && r.message ? r.message : 'Something went wrong.');
                        return false;
                     }
                     return r;
                  } catch (err) {
                     Swal.showValidationMessage('Unable to save. Please try again.');
                     return false;
                  }
               },
               willClose: () => {
                  const host = document.getElementById('reminderFormHost');
                  if (host && formEl) host.appendChild(formEl);
               }
            }).then(result => {
               if (goToClientPicker) {
                  goToClientPicker = false;
                  openClientPicker();
                  return;
               }
               if (result.isConfirmed && result.value) {
                  reminders = (result.value.reminders || []).map(normalize);
                  selectedClientIds = [];
                  editingReminder = null;
                  currentDetailReminder = null;
                  refresh();
                  Swal.fire({
                     icon: 'success',
                     title: 'Saved',
                     text: result.value.message || 'Reminder saved successfully.'
                  });
               }
            });
         }

         // The second popup: search/filter and tick clients. Selection is applied
         // live, so closing it (any way) simply returns to the reminder popup.
         function openClientPicker() {
            const pickerEl = document.getElementById('clientPicker');
            renderClientPickList();
            Swal.fire({
               title: 'Select clients',
               html: pickerEl,
               showCloseButton: true,
               showCancelButton: false,
               heightAuto: false,
               confirmButtonText: 'Done',
               confirmButtonColor: '#252161',
               customClass: { popup: 'reminder-swal client-swal' },
               didOpen: () => {
                  renderClientPickList();
                  loadInvoiceServices(selectedClientIds, 'invoiceServicesBox', 'invoiceServicesRange');
               },
               willClose: () => {
                  const host = document.getElementById('clientPickerHost');
                  if (host && pickerEl) host.appendChild(pickerEl);
               }
            }).then(() => showReminderPopup());
         }

         function openDetail(rem) {
            currentDetailReminder = rem;
            detailBody.innerHTML = `<div class="drow"><div class="dl">Title</div><div class="dv">${esc(rem.title)}</div></div><div class="drow"><div class="dl">Description</div><div class="dv">${esc(rem.description || 'No description')}</div></div><div class="drow"><div class="dl">Clients</div><div class="chips">${(rem.clients || []).map(c => `<a class="chip chip-link" href="client_details.php?id=${Number(c.id)}&status=${Number(c.status ?? 1)}" target="_blank" rel="noopener">${esc(c.name)}</a>`).join('')}</div></div><div class="drow"><div class="dl">Date and time</div><div class="dv">${esc(dtDisp(rem.date))}</div></div><div class="drow"><div class="dl">Assigned user</div><div class="dv">${esc(rem.assigned_user_name || currentUserName)}</div></div><div class="drow"><div class="dl">Recurrence</div><div class="dv">${esc(rem.recurrence_label || 'One time')}</div></div><div class="drow"><div class="dl d-flex align-items-center justify-content-between"><span>Services from previous invoices</span><span class="text-lowercase fw-normal" style="letter-spacing:0;" id="detailServicesRange">Last 2 years</span></div><div class="form-text mb-2" style="margin-top:-2px;">Services billed to the selected client(s) from invoices issued during the prior two-year period.</div><div class="dv"><div id="detailServicesBox" class="invoice-services-box"><span class="muted small">Loading...</span></div></div></div>`;
            detailModal.show();
            loadInvoiceServices((rem.client_ids || []).map(Number), 'detailServicesBox', 'detailServicesRange');
         }

         function loadReminders() {
            $.get('reminder_calender.php', {
               ajax: 'load_reminders'
            }).done(r => {
               if (r && r.success) {
                  reminders = (r.reminders || []).map(normalize);
                  refresh();
               }
            });
         }

         // Gather + validate the reminder form. Returns the POST payload, or null
         // (after surfacing the reason via Swal's inline validation message) so the
         // SweetAlert popup stays open for the user to fix it.
         function collectReminderPayload() {
            if (!selectedClientIds.length) {
               Swal.showValidationMessage('Please select at least one client.');
               return null;
            }
            const isSpecific = rtypeInput.value === 'specific_dates';
            let dates = [];
            if (isSpecific) {
               dates = Array.from(datesList.querySelectorAll('.date-row-input')).map(i => (i.value || '').trim()).filter(Boolean);
               dates = Array.from(new Set(dates));
               if (!dates.length) {
                  Swal.showValidationMessage('Please add at least one date for specific-dates recurrence.');
                  return null;
               }
            }
            const payload = {
               title: titleInput.value.trim(),
               description: descInput.value.trim(),
               reminder_date: isSpecific ? (dates[0] || '') : dateInput.value,
               recurrence_type: rtypeInput.value,
               custom_interval: cintInput.value,
               custom_unit: cunitInput.value,
               assigned_user_id: assignInput.value || currentUserId,
               client_ids: selectedClientIds,
               row_ids: JSON.parse(rowIds.value || '[]'),
               dates: dates
            };
            if (!payload.title) {
               Swal.showValidationMessage('Please enter a reminder title.');
               return null;
            }
            if (!isSpecific && !payload.reminder_date) {
               Swal.showValidationMessage('Please select a date and time.');
               return null;
            }
            return payload;
         }
         clientPickList.addEventListener('change', e => {
            const cb = e.target.closest('.cpk-cb');
            if (!cb) return;
            const row = cb.closest('.cpk-row'),
               id = Number(row && row.dataset.id || 0);
            if (!id) return;
            if (cb.checked) {
               if (!selectedClientIds.includes(id)) selectedClientIds.push(id);
            } else {
               selectedClientIds = selectedClientIds.filter(x => x !== id);
            }
            // Toggle in place so the search results and typed query are kept.
            row.classList.toggle('is-sel', cb.checked);
            updateCount();
            renderSelectedChips();
            scheduleInvoiceReload();
         });
         // The "Select clients" button opens the second popup. Set the flag first so
         // the reminder popup's .then handler routes to the picker instead of cancel.
         q('#openClientPickerBtn').addEventListener('click', () => {
            goToClientPicker = true;
            Swal.close();
         });
         // Chip × and Clear live in both popups, so delegate at the document level.
         document.addEventListener('click', e => {
            const x = e.target.closest('.cpk-chip-x');
            if (x) {
               const id = Number(x.dataset.id || 0);
               if (id) {
                  selectedClientIds = selectedClientIds.filter(v => v !== id);
                  renderClientPickList();
                  updateCount();
                  renderSelectedChips();
                  scheduleInvoiceReload();
               }
               return;
            }
            if (e.target.closest('.js-clear-clients')) {
               selectedClientIds = [];
               renderClientPickList();
               updateCount();
               renderSelectedChips();
               scheduleInvoiceReload();
            }
         });
         serviceFilterInput.addEventListener('change', renderClientPickList);
         clientSearchInput.addEventListener('input', renderClientPickList);
         rtypeInput.onchange = toggleRecurrenceUI;
         addDateBtn.onclick = () => {
            const last = datesList.querySelector('.date-row:last-of-type .date-row-input');
            const seed = (last && last.value) || dateInput.value || `${dkey(selectedDate)}T09:00`;
            addDateRow(seed);
         };
         q('#prevBtn').onclick = () => {
            if (currentView === 'week') {
               selectedDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate() - 7);
            } else if (currentView === 'day') {
               selectedDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate() - 1);
            } else if (currentView === 'list') {
               return;
            } else {
               currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() - 1, 1);
            }
            renderCalendar();
         };
         q('#nextBtn').onclick = () => {
            if (currentView === 'week') {
               selectedDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate() + 7);
            } else if (currentView === 'day') {
               selectedDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate() + 1);
            } else if (currentView === 'list') {
               return;
            } else {
               currentMonth = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 1);
            }
            renderCalendar();
         };
         q('#todayBtn').onclick = q('#goTodayBtn').onclick = () => {
            selectedDate = new Date();
            currentMonth = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
            refresh();
         };
         Array.from(document.querySelectorAll('#viewSwitch button')).forEach(b => {
            b.onclick = () => setView(b.dataset.view);
         });
         q('#leftAddBtn').onclick = q('#panelAddBtn').onclick = q('#headAddBtn').onclick = () => {
            selectedClientIds = [];
            openForm(null);
         };
         q('#editBtn').onclick = () => {
            if (!currentDetailReminder) return;
            detailModal.hide();
            openForm(currentDetailReminder);
         };
         renderClientPickList();
         refresh();
         loadReminders();

         // If launched from a client profile (?client_id=N), open the add-reminder
         // form pre-selected for that client so the user can add a detailed one.
         if (preselectClientId > 0 && clients.some(c => Number(c.id) === preselectClientId)) {
            setTimeout(() => {
               editingReminder = null;
               selectedClientIds = [preselectClientId];
               updateCount();
               openForm(null);
            }, 150);
         }
      })();
   </script>
</body>

</html>