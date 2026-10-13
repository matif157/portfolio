<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load Pusher env early (used for real-time broadcast + private channel auth)
$env = [];
$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    foreach (file($envPath) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (preg_match('/^([A-Za-z0-9_]+)\s*=\s*"?([^"]*)"?$/', $line, $m)) {
            $env[$m[1]] = $m[2];
        } 
    }
}
$pusher_app_id  = $env['app_id'] ?? '';
$pusher_key     = $env['key'] ?? '';
$pusher_secret  = $env['secret'] ?? '';
$pusher_cluster = $env['cluster'] ?? 'us3';

// Pusher private-channel auth — must run before session-guard redirects (POST has no fullname check)
if (
    (isset($_GET['action']) && $_GET['action'] === 'pusher_auth') ||
    (isset($_POST['action']) && $_POST['action'] === 'pusher_auth') ||
    (isset($_POST['socket_id']) && isset($_POST['channel_name']))
) {
    header('Content-Type: application/json');
    $socket_id    = isset($_POST['socket_id']) ? trim($_POST['socket_id']) : '';
    $channel_name = isset($_POST['channel_name']) ? trim($_POST['channel_name']) : '';
    if ($socket_id === '' || $channel_name === '') {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $rawPost = [];
            parse_str($raw, $rawPost);
            if ($socket_id === '' && isset($rawPost['socket_id'])) $socket_id = trim($rawPost['socket_id']);
            if ($channel_name === '' && isset($rawPost['channel_name'])) $channel_name = trim($rawPost['channel_name']);
        }
    }
    if ($socket_id === '' || $channel_name === '') { http_response_code(400); echo json_encode(['status' => 'error', 'reason' => 'missing_params']); exit; }
    if ($pusher_key === '' || $pusher_secret === '') { http_response_code(500); echo json_encode(['status' => 'error', 'reason' => 'missing_keys']); exit; }
    if (!preg_match('/^private-chat\.user\.(\d+)$/', $channel_name)) {
        http_response_code(403); echo json_encode(['status' => 'forbidden']); exit;
    }
    $signature = hash_hmac('sha256', $socket_id . ':' . $channel_name, $pusher_secret);
    echo json_encode(['auth' => $pusher_key . ':' . $signature]);
    exit;
}

include "includes/db.php";
include "includes/auth.php";

$user_id = (int) ($_SESSION['user_id'] ?? 0);

// Marks the current user as active. Comm Hub has no page-load heartbeat of its
// own, so presence would go stale while sitting in the chat pane; the chat
// polling endpoints call this to keep users.last_seen fresh.
function comm_hub_touch_presence($con, $uid)
{
    if ($uid <= 0) return;
    $stmt = mysqli_prepare($con, "UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Returns presence info for a single user: ['is_online' => bool, 'last_seen' => string|null].
function comm_hub_presence_of($con, $uid)
{
    $out = ['is_online' => false, 'last_seen' => null];
    if ($uid <= 0) return $out;
    $stmt = mysqli_prepare($con, "SELECT last_seen, (last_seen IS NOT NULL AND last_seen > NOW() - INTERVAL 60 SECOND) AS is_online FROM users WHERE user_id = ? LIMIT 1");
    if (!$stmt) return $out;
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($r = mysqli_fetch_assoc($res))) {
        $out['is_online'] = !empty($r['is_online']);
        $out['last_seen'] = $r['last_seen'];
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// Broadcasts a payload to a Pusher channel. Silently no-ops if keys aren't configured.
function comm_hub_pusher_broadcast($channel, $event, $payload)
{
    global $pusher_app_id, $pusher_key, $pusher_secret, $pusher_cluster;
    if ($pusher_app_id === '' || $pusher_key === '' || $pusher_secret === '') return;
    $eventBody = json_encode(['name' => $event, 'channel' => $channel, 'data' => json_encode($payload)]);
    $params = [
        'auth_key'       => $pusher_key,
        'body_md5'       => md5($eventBody),
        'auth_timestamp' => time(),
        'auth_version'   => '1.0',
    ];
    ksort($params);
    $path = '/apps/' . $pusher_app_id . '/events';
    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $signature = hash_hmac('sha256', 'POST' . "\n" . $path . "\n" . $queryString, $pusher_secret);
    $url = 'https://api-' . $pusher_cluster . '.pusher.com' . $path . '?' . $queryString . '&auth_signature=' . $signature;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $eventBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);
}

function getInitials($name)
{
   $words = explode(' ', (string) $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return substr($initials, 0, 2);
}

$user_full_name = $_SESSION['fullname'] ?? '';
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'] ?? 0;

// ============================================================
// AJAX ACTION ROUTER (kept inside curdir.php only)
// ============================================================
if (!empty($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax_action'];

    $jsonOut = function ($payload) {
        echo json_encode($payload);
        exit;
    };

    if ($user_id <= 0) {
        $jsonOut(['status' => 'error', 'message' => 'Not authenticated']);
    }

    try {
        switch ($action) {

            // --------------- SMS: SEND ---------------
            case 'sms_send': {
                $toRaw = trim($_POST['to_phone'] ?? '');
                $body  = trim($_POST['body'] ?? '');
                if ($toRaw === '' || $body === '') {
                    $jsonOut(['status' => 'error', 'message' => 'Phone and message are required.']);
                }
                $digits = preg_replace('/\D/', '', $toRaw);
                if (preg_match('/^[2-9]\d{9}$/', $digits)) {
                    $phoneE164 = '+92' . $digits;          // PK fallback (matches send_manual_sms.php)
                } elseif (preg_match('/^1[2-9]\d{9}$/', $digits)) {
                    $phoneE164 = '+' . $digits;            // US/CA
                } elseif (preg_match('/^[1-9]\d{7,14}$/', $digits)) {
                    $phoneE164 = '+' . $digits;            // already international
                } else {
                    $jsonOut(['status' => 'error', 'message' => 'Invalid phone number.']);
                }

                $twilioOk  = false;
                $twilioErr = '';
                $autoloadPath = __DIR__ . '/Twilio/autoload.php';
                if (file_exists($autoloadPath)) {
                    require_once $autoloadPath;
                    try {
                        $twilio = new \Twilio\Rest\Client(
                            'ACXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
                            'XXXXXXXXXX_AUTH_TOKEN_XXXXXXXXXX'
                        );
                        $twilio->messages->create($phoneE164, [
                            'messagingServiceSid' => 'MG28b94ac8c108df135551ebc5e0ecaebe',
                            'body' => $body
                        ]);
                        $twilioOk = true;
                    } catch (Exception $e) {
                        $twilioErr = $e->getMessage();
                    }
                } else {
                    $twilioErr = 'Twilio SDK not found';
                }

                $now      = date('Y-m-d H:i:s');
                $bodySql  = mysqli_real_escape_string($con, $body);
                $toSql    = mysqli_real_escape_string($con, $phoneE164);
                $sentBy   = $user_id;
                mysqli_query($con, "
                    INSERT INTO email_history
                    (client_id, invoice_id, subject, body, sent_at, sent_by, To_mail, From_mail, type)
                    VALUES
                    (0, 0, 'Manual SMS', '$bodySql', '$now', $sentBy, '$toSql', '+18669635928', 'SMS')
                ");

                if ($twilioOk) {
                    $jsonOut(['status' => 'success', 'message' => 'Message sent.']);
                } else {
                    $jsonOut(['status' => 'error', 'message' => 'SMS provider error: ' . $twilioErr]);
                }
            }

            // --------------- SMS: INBOX LIST ---------------
            case 'sms_inbox': {
                // Inbound SMS rows are stored in email_history with type='SMS_INBOX'.
                // Provider webhooks would populate this table; UI lists whatever's there.
                $rows = [];
                $res = mysqli_query($con, "
                    SELECT id, To_mail, From_mail, body, subject, sent_at
                    FROM email_history
                    WHERE type = 'SMS_INBOX'
                    ORDER BY sent_at DESC, id DESC
                    LIMIT 200
                ");
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                }
                $jsonOut(['status' => 'success', 'messages' => $rows]);
            }

            // --------------- SMS: SENT LIST ---------------
            case 'sms_sent': {
                $rows = [];
                $res = mysqli_query($con, "
                    SELECT eh.id, eh.To_mail, eh.From_mail, eh.body, eh.subject, eh.sent_at,
                           COALESCE(u.user_name, '') AS sent_by_name
                    FROM email_history eh
                    LEFT JOIN users u ON u.user_id = eh.sent_by
                    WHERE eh.type = 'SMS'
                    ORDER BY eh.sent_at DESC, eh.id DESC
                    LIMIT 200
                ");
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                }
                $jsonOut(['status' => 'success', 'messages' => $rows]);
            }

            // --------------- CHAT: USERS LIST ---------------
            case 'chat_users': {
                $me = $user_id;
                comm_hub_touch_presence($con, $me);
                $sql = "
                    SELECT u.user_id, COALESCE(u.user_name, u.user_login) AS user_name, u.user_pic, u.user_type,
                        u.last_seen,
                        (u.last_seen IS NOT NULL AND u.last_seen > NOW() - INTERVAL 60 SECOND) AS is_online,
                        (SELECT m.message
                           FROM chat_messages m
                           WHERE (m.sender_id = u.user_id AND m.recipient_id = $me)
                              OR (m.sender_id = $me AND m.recipient_id = u.user_id)
                           ORDER BY m.id DESC LIMIT 1) AS last_message,
                        (SELECT m.sent_at
                           FROM chat_messages m
                           WHERE (m.sender_id = u.user_id AND m.recipient_id = $me)
                              OR (m.sender_id = $me AND m.recipient_id = u.user_id)
                           ORDER BY m.id DESC LIMIT 1) AS last_at,
                        (SELECT COUNT(*) FROM chat_messages m
                           WHERE m.sender_id = u.user_id
                             AND m.recipient_id = $me
                             AND m.is_read = 0) AS unread
                    FROM users u
                    WHERE u.user_id <> $me
                      AND COALESCE(u.user_ban_status,0) = 0
                      AND COALESCE(u.user_login_status,1) = 1
                    ORDER BY (last_at IS NULL), last_at DESC, user_name ASC
                ";
                $rows = [];
                $res = mysqli_query($con, $sql);
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) {
                        $r['is_online'] = !empty($r['is_online']);
                        $r['user_pic_url'] = '';
                        if (!empty($r['user_pic'])) {
                            $fname = basename($r['user_pic']);
                            foreach (['Uploads/staff_images/', 'uploads/staff_images/'] as $dir) {
                                if (is_file(__DIR__ . '/' . $dir . $fname)) {
                                    $r['user_pic_url'] = $dir . rawurlencode($fname);
                                    break;
                                }
                            }
                        }
                        $rows[] = $r;
                    }
                }
                $jsonOut(['status' => 'success', 'users' => $rows]);
            }

            // --------------- CHAT: MESSAGES ---------------
            case 'chat_messages': {
                $other = (int) ($_POST['other_id'] ?? 0);
                if ($other <= 0) $jsonOut(['status' => 'error', 'message' => 'Bad recipient']);
                $me = $user_id;
                $rows = [];
                $res = mysqli_query($con, "
                    SELECT m.id, m.sender_id, m.recipient_id, m.message, m.sent_at, m.is_read,
                           COALESCE(u.user_name, u.user_login) AS sender_name
                    FROM chat_messages m
                    JOIN users u ON u.user_id = m.sender_id
                    WHERE (m.sender_id = $me AND m.recipient_id = $other)
                       OR (m.sender_id = $other AND m.recipient_id = $me)
                    ORDER BY m.id ASC
                    LIMIT 500
                ");
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                }
                mysqli_query($con, "UPDATE chat_messages SET is_read = 1 WHERE recipient_id = $me AND sender_id = $other AND is_read = 0");
                comm_hub_touch_presence($con, $me);
                $jsonOut(['status' => 'success', 'messages' => $rows, 'presence' => comm_hub_presence_of($con, $other)]);
            }

            // --------------- CHAT: SEND ---------------
            case 'chat_send': {
                $other = (int) ($_POST['recipient_id'] ?? 0);
                $msg   = trim($_POST['message'] ?? '');
                if ($other <= 0 || $msg === '') $jsonOut(['status' => 'error', 'message' => 'Recipient and message required']);
                $stmt = mysqli_prepare($con, "INSERT INTO chat_messages (sender_id, recipient_id, message, sent_at) VALUES (?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, 'iis', $user_id, $other, $msg);
                $ok = mysqli_stmt_execute($stmt);
                $newId = mysqli_insert_id($con);
                mysqli_stmt_close($stmt);
                if (!$ok) $jsonOut(['status' => 'error', 'message' => 'DB error']);
                $row = mysqli_fetch_assoc(mysqli_query($con, "
                    SELECT m.id, m.sender_id, m.recipient_id, m.message, m.sent_at, m.is_read,
                           COALESCE(u.user_name, u.user_login) AS sender_name
                    FROM chat_messages m JOIN users u ON u.user_id = m.sender_id
                    WHERE m.id = $newId LIMIT 1"));
                if ($row) {
                    comm_hub_pusher_broadcast('private-chat.user.' . $other, 'message', $row);
                }
                $jsonOut(['status' => 'success', 'message_row' => $row]);
            }

            // --------------- CHAT: POLL NEW ---------------
            case 'chat_poll': {
                $other  = (int) ($_POST['other_id'] ?? 0);
                $lastId = (int) ($_POST['last_id'] ?? 0);
                if ($other <= 0) $jsonOut(['status' => 'error', 'message' => 'Bad recipient']);
                $me = $user_id;
                $rows = [];
                $res = mysqli_query($con, "
                    SELECT m.id, m.sender_id, m.recipient_id, m.message, m.sent_at, m.is_read,
                           COALESCE(u.user_name, u.user_login) AS sender_name
                    FROM chat_messages m
                    JOIN users u ON u.user_id = m.sender_id
                    WHERE m.id > $lastId
                      AND ((m.sender_id = $me AND m.recipient_id = $other)
                        OR (m.sender_id = $other AND m.recipient_id = $me))
                    ORDER BY m.id ASC
                ");
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
                }
                if (!empty($rows)) {
                    mysqli_query($con, "UPDATE chat_messages SET is_read = 1 WHERE recipient_id = $me AND sender_id = $other AND is_read = 0");
                }
                comm_hub_touch_presence($con, $me);
                $jsonOut(['status' => 'success', 'messages' => $rows, 'presence' => comm_hub_presence_of($con, $other)]);
            }

            default:
                $jsonOut(['status' => 'error', 'message' => 'Unknown action']);
        }
    } catch (Throwable $e) {
        $jsonOut(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
}

// ============================================================
// END action router — UI render below
// ============================================================
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Messages — Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
   <style>
      :root {
         --brand: #252161;
         --brand-dark: #1a174b;
         --brand-soft: #ecebf5;
         --brand-rgb: 37,33,97;
         --ink: #0f172a;
         --ink-2: #334155;
         --ink-3: #64748b;
         --line: #e5e7eb;
         --bg-soft: #f8fafc;
         --danger: #ef4444;
         --info: #3b82f6;
         --warn: #f59e0b;
         --shadow-sm: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
         --shadow-md: 0 4px 6px -1px rgba(15,23,42,.05), 0 2px 4px -2px rgba(15,23,42,.05);
         --shadow-lg: 0 10px 25px -5px rgba(15,23,42,.08), 0 8px 10px -6px rgba(15,23,42,.05);
      }
      .msg-app { font-family: 'Inter', system-ui, -apple-system, sans-serif; color:var(--ink); }
      .msg-app * { box-sizing: border-box; }

      /* Hero header */
      .msg-hero { background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px 22px; margin-bottom:18px; box-shadow: var(--shadow-sm); }
      .msg-hero-row { display:flex; align-items:center; justify-content:space-between; gap:18px; flex-wrap:wrap; }
      .msg-hero-title { font-size:22px; font-weight:700; margin:0; display:flex; align-items:center; gap:12px; color:var(--ink); }
      .msg-hero-icon { width:42px; height:42px; border-radius:11px; background:linear-gradient(135deg,#3d3a7a,#252161); color:#fff; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow: 0 4px 12px rgba(37,33,97,.35); }
      .msg-hero-sub { font-size:13px; color:var(--ink-3); margin-top:4px; }
      .msg-breadcrumb { font-size:12px; color:var(--ink-3); margin-top:2px; }
      .msg-breadcrumb a { color:var(--ink-3); text-decoration:none; }
      .msg-breadcrumb a:hover { color:var(--brand); }

      /* Stat cards */
      .stat-row { display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-top:18px; }
      @media (max-width: 1024px){ .stat-row { grid-template-columns:repeat(2, 1fr);} }
      @media (max-width: 540px){ .stat-row { grid-template-columns:1fr;} }
      .stat-card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:14px 16px; display:flex; gap:13px; align-items:center; transition:all .15s; }
      .stat-card:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
      .stat-ico { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
      .stat-ico.green { background:rgba(37,33,97,.13); color:#252161; }
      .stat-ico.blue { background:rgba(37,33,97,.10); color:#3d3a7a; }
      .stat-ico.amber { background:rgba(245,158,11,.14); color:#b45309; }
      .stat-ico.purple { background:rgba(37,33,97,.18); color:#1a174b; }
      .stat-val { font-size:22px; font-weight:700; line-height:1; color:var(--ink); }
      .stat-lbl { font-size:11px; color:var(--ink-3); text-transform:uppercase; letter-spacing:.05em; font-weight:600; margin-top:5px; }

      /* Main shell with tabs */
      .msg-shell { background:#fff; border:1px solid var(--line); border-radius:14px; box-shadow: var(--shadow-sm); overflow:hidden; }
      .tab-bar { display:flex; gap:6px; padding:12px 16px; border-bottom:1px solid var(--line); background:linear-gradient(180deg,#fafbfc,#fff); align-items:center; flex-wrap:wrap; }
      .tab-btn { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; font-size:13px; font-weight:500; color:var(--ink-3); background:transparent; border:none; border-radius:8px; cursor:pointer; transition:all .15s; font-family: inherit; }
      .tab-btn:hover { color:var(--ink); background:rgba(37,33,97,.08); }
      .tab-btn.active { background:var(--brand); color:#fff; box-shadow:0 2px 6px rgba(37,33,97,.4); }
      .tab-btn.active:hover { background:var(--brand-dark); color:#fff; }
      .tab-btn .pip { background:#ef4444; color:#fff; border-radius:999px; font-size:10px; padding:1px 7px; min-width:18px; text-align:center; line-height:1.5; font-weight:700; }
      .tab-btn.active .pip { background:#fff; color:#ef4444; }
      .pane { display:none; padding:20px 22px; min-height:540px; }
      .pane.active { display:block; }
      .pane-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; gap:10px; flex-wrap:wrap; }
      .pane-head h3 { margin:0; font-size:16px; font-weight:600; color:var(--ink); display:flex; align-items:center; gap:9px; }
      .pane-cnt { background:#f1f5f9; color:var(--ink-3); padding:2px 9px; border-radius:999px; font-size:11px; font-weight:600; }
      .btn-ghost { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:500; color:var(--ink-2); background:#f1f5f9; border:1px solid transparent; cursor:pointer; transition:all .15s; font-family:inherit; }
      .btn-ghost:hover { background:#e2e8f0; color:var(--ink); }
      .btn-brand { display:inline-flex; align-items:center; gap:8px; padding:10px 18px; border-radius:9px; font-size:13px; font-weight:600; color:#fff; background:var(--brand); border:none; cursor:pointer; transition:all .15s; box-shadow:0 1px 3px rgba(37,33,97,.4); font-family:inherit; }
      .btn-brand:hover { background:var(--brand-dark); transform: translateY(-1px); box-shadow:0 4px 10px rgba(37,33,97,.4); }
      .btn-brand:disabled { opacity:.55; cursor:not-allowed; transform:none; box-shadow:none; }

      /* Mail-style DataTable */
      .msg-table-wrap { background:#fff; border-radius:10px; }
      table.msg-table { width:100% !important; border-collapse: separate; border-spacing:0; font-size:13px; }
      table.msg-table thead th { background:#f8fafc; color:var(--ink-3); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; padding:11px 12px; border-bottom:1px solid var(--line); white-space:nowrap; }
      table.msg-table tbody td { padding:12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; color:var(--ink-2); }
      table.msg-table tbody tr { transition:background .12s; cursor:pointer; }
      table.msg-table tbody tr:hover { background:#f8fafc; }
      table.msg-table tbody tr:last-child td { border-bottom:none; }
      table.msg-table td.col-avatar { width:50px; padding-right:0; }
      table.msg-table td.col-phone { width:160px; }
      table.msg-table td.col-date { width:110px; text-align:right; color:var(--ink-3); font-size:12px; white-space:nowrap; }
      table.msg-table td.col-by { width:130px; font-size:12px; color:var(--ink-3); white-space:nowrap; }
      table.msg-table td.col-act { width:100px; text-align:right; }
      .mail-avatar { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; flex-shrink:0; }
      .cell-phone { font-weight:600; color:var(--ink); font-size:13px; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
      .cell-phone .mail-tag { display:inline-block; font-size:9px; padding:1px 6px; border-radius:4px; font-weight:700; background:#ecebf5; color:#252161; text-transform:uppercase; letter-spacing:.04em; }
      .cell-phone .mail-tag.out { background:#252161; color:#fff; }
      .cell-preview { font-size:12.5px; color:var(--ink-2); line-height:1.5; max-width:600px; display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }
      .mail-action { display:inline-flex; align-items:center; gap:5px; font-size:11px; padding:5px 10px; border-radius:6px; background:#f1f5f9; border:1px solid transparent; color:var(--ink-2); cursor:pointer; font-weight:500; transition:all .15s; font-family:inherit; }
      .mail-action:hover { background:var(--brand); color:#fff; }

      /* DataTables theme overrides */
      .dataTables_wrapper { padding-top:6px; }
      .dataTables_wrapper .dataTables_filter { float:right; margin-bottom:14px; }
      .dataTables_wrapper .dataTables_filter label { font-size:12px; color:var(--ink-3); font-weight:500; }
      .dataTables_wrapper .dataTables_filter input { border:1.5px solid var(--line) !important; border-radius:9px !important; padding:7px 12px !important; font-size:12px !important; min-width:240px; margin-left:8px !important; transition:all .15s; }
      .dataTables_wrapper .dataTables_filter input:focus { outline:none; border-color:var(--brand) !important; box-shadow:0 0 0 3px rgba(37,33,97,.12); }
      .dataTables_wrapper .dataTables_length { float:left; margin-bottom:14px; font-size:12px; color:var(--ink-3); }
      .dataTables_wrapper .dataTables_length select { border:1.5px solid var(--line); border-radius:7px; padding:5px 26px 5px 10px; font-size:12px; margin:0 6px; background:#fff; }
      .dataTables_wrapper .dataTables_info { font-size:12px; color:var(--ink-3); padding-top:14px; }
      .dataTables_wrapper .dataTables_paginate { padding-top:10px; }
      .dataTables_wrapper .dataTables_paginate .paginate_button {
         padding:6px 12px !important; margin:0 2px !important; border-radius:7px !important;
         font-size:12px !important; border:1px solid transparent !important; background:#fff !important;
         color:var(--ink-2) !important; cursor:pointer; transition:all .15s;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
         background:#f1f5f9 !important; border-color:var(--line) !important; color:var(--ink) !important;
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button.current,
      .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
         background:var(--brand) !important; border-color:var(--brand) !important; color:#fff !important;
         box-shadow:0 1px 3px rgba(37,33,97,.4);
      }
      .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
      .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
         color:#cbd5e1 !important; background:transparent !important; cursor:not-allowed;
      }
      table.dataTable thead .sorting:after, table.dataTable thead .sorting_asc:after, table.dataTable thead .sorting_desc:after { opacity:.4; }
      table.msg-table.dataTable { border:none; }

      /* Empty states */
      .empty-state { padding:60px 24px; text-align:center; color:var(--ink-3); }
      .empty-state .es-ico { width:72px; height:72px; margin:0 auto 16px; background:linear-gradient(135deg,#f1f5f9,#e2e8f0); border-radius:18px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:28px; }
      .empty-state h4 { font-size:15px; font-weight:600; color:var(--ink-2); margin:0 0 6px; }
      .empty-state p { font-size:13px; margin:0; max-width:380px; margin-left:auto; margin-right:auto; line-height:1.55; }

      /* Skeleton */
      .skel-row { display:grid; grid-template-columns:44px 1fr auto; gap:12px; padding:12px 14px; align-items:center; }
      .skel { background:linear-gradient(90deg,#eef2f7 0%,#f8fafc 50%,#eef2f7 100%); background-size:200% 100%; animation: skel-shimmer 1.3s linear infinite; border-radius:6px; }
      .skel-circle { width:44px; height:44px; border-radius:50%; }
      .skel-line { height:11px; }
      .skel-line.short { width:40%; }
      @keyframes skel-shimmer { 0%{background-position:200% 0;} 100%{background-position:-200% 0;} }

      /* Compose layout */
      .compose-grid { display:grid; grid-template-columns: 1fr 300px; gap:24px; align-items:start; }
      @media (max-width: 900px){ .compose-grid{ grid-template-columns:1fr; } }
      .compose-card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:22px; box-shadow: var(--shadow-sm); }
      .form-label { display:block; font-size:12px; font-weight:600; color:var(--ink-2); margin-bottom:7px; letter-spacing:.02em; }
      .form-input, .form-textarea { width:100%; border:1.5px solid #e5e7eb; border-radius:10px; padding:11px 14px; font-size:13px; transition: all .15s; background:#fff; color:var(--ink); font-family:inherit; }
      .form-input:focus, .form-textarea:focus { outline:none; border-color:var(--brand); box-shadow:0 0 0 4px rgba(37,33,97,.12); }
      .form-textarea { min-height:180px; resize:vertical; line-height:1.55; }
      .form-hint { font-size:11px; color:var(--ink-3); margin-top:6px; line-height:1.4; }
      .form-row { margin-bottom:18px; }
      .compose-counter { display:flex; justify-content:space-between; align-items:center; font-size:11px; color:var(--ink-3); margin-top:7px; }
      .compose-counter .segs { background:#f1f5f9; padding:3px 9px; border-radius:6px; font-weight:600; color:var(--ink-2); }
      .send-row { display:flex; align-items:center; gap:12px; margin-top:6px; flex-wrap:wrap; }
      .send-status { font-size:12px; font-weight:500; display:flex; align-items:center; gap:6px; }

      /* Phone preview card */
      .phone-preview { background:linear-gradient(180deg,#1f2937,#111827); border-radius:28px; padding:14px 12px 16px; position:sticky; top:14px; box-shadow: var(--shadow-lg); border:6px solid #0f172a; }
      .phone-bar { display:flex; align-items:center; justify-content:space-between; padding:0 10px 8px; font-size:10px; color:#94a3b8; font-weight:600; }
      .phone-bar .right { display:flex; gap:5px; align-items:center; }
      .phone-screen { background:#000; border-radius:18px; padding:14px 10px 16px; min-height:360px; display:flex; flex-direction:column; gap:8px; justify-content:flex-end; position:relative; overflow:hidden; }
      .phone-screen::before { content:''; position:absolute; left:50%; top:0; transform:translateX(-50%); width:80px; height:18px; background:#0f172a; border-radius:0 0 14px 14px; }
      .phone-contact { padding:24px 12px 14px; text-align:center; border-bottom:1px solid #1f2937; margin-bottom:8px; }
      .phone-contact .pc-name { font-size:11px; color:#cbd5e1; font-weight:600; }
      .phone-bubble { background:#252161; color:#fff; border-radius:18px; padding:9px 13px; font-size:12px; max-width:85%; align-self:flex-end; border-bottom-right-radius:5px; word-break:break-word; white-space:pre-wrap; line-height:1.45; box-shadow:0 1px 2px rgba(0,0,0,.3); }
      .phone-bubble.placeholder { background:#374151; color:#9ca3af; font-style:italic; }
      .phone-bubble .pb-ts { display:block; font-size:9px; opacity:.75; margin-top:3px; text-align:right; }
      .phone-foot { font-size:10px; color:#64748b; text-align:center; margin-top:10px; }

      /* CRM Chat */
      .chat-shell { display:grid; grid-template-columns: 320px 1fr; grid-template-rows: 1fr; gap:0; height:660px; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow: var(--shadow-sm); }
      @media (max-width: 820px){ .chat-shell{ grid-template-columns:1fr; height:auto; } }
      .cl-side { background:#fafbfc; border-right:1px solid var(--line); display:flex; flex-direction:column; min-width:0; height:660px; overflow:hidden; }
      .cl-head { padding:14px; border-bottom:1px solid var(--line); background:#fff; }
      .cl-head-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
      .cl-head-row h4 { margin:0; font-size:14px; font-weight:600; color:var(--ink); }
      .cl-cnt { background:#f1f5f9; color:var(--ink-3); font-size:11px; font-weight:600; padding:2px 9px; border-radius:999px; }
      .cl-search { position:relative; }
      .cl-search input { width:100%; border:1px solid #e5e7eb; border-radius:9px; padding:9px 13px 9px 36px; font-size:12px; background:#f8fafc; transition:all .15s; font-family:inherit; }
      .cl-search input:focus { outline:none; border-color:var(--brand); background:#fff; box-shadow:0 0 0 3px rgba(37,33,97,.12); }
      .cl-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12px; }
      .cl-list { flex:1; overflow-y:auto; padding:6px; }
      .cl-user { display:grid; grid-template-columns:42px 1fr auto; gap:10px; padding:10px; border-radius:10px; cursor:pointer; transition:background .15s; align-items:center; }
      .cl-user:hover { background:#fff; }
      .cl-user.active { background:var(--brand-soft); }
      .cl-user.active .cl-name { color:#252161; }
      .cl-avatar { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:13px; flex-shrink:0; position:relative; }
      .cl-avatar .cl-init { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; }
      .cl-avatar .dot { position:absolute; bottom:0; right:0; width:11px; height:11px; background:#252161; border:2px solid #fafbfc; border-radius:50%; z-index:2; }
      .cl-avatar .dot.off { background:#c2c6d0; }
      .cl-meta { min-width:0; }
      .cl-name { font-size:13px; font-weight:600; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
      .cl-preview { font-size:11px; color:var(--ink-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
      .cl-right { display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
      .cl-time { font-size:10px; color:#94a3b8; }
      .cl-unread { position:absolute; top:-4px; right:-4px; background:#ef4444; color:#fff; border-radius:999px; padding:1px 6px; font-size:10px; font-weight:700; min-width:18px; text-align:center; line-height:1.6; border:2px solid #fafbfc; z-index:3; }

      .cw-main { display:flex; flex-direction:column; min-width:0; height:660px; overflow:hidden; background:#fff; }
      .cw-header { padding:14px 18px; border-bottom:1px solid var(--line); background:#fff; display:flex; align-items:center; gap:12px; }
      .cw-header .cw-avatar { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:600; font-size:14px; overflow:hidden; }
      .cw-header .cw-avatar img { width:100%; height:100%; object-fit:cover; }
      .cw-name { font-size:15px; font-weight:600; color:var(--ink); }
      .cw-status { font-size:11px; color:#252161; margin-top:1px; display:flex; align-items:center; gap:6px; font-weight:500; }
      .cw-status .pulse { width:8px; height:8px; background:#252161; border-radius:50%; box-shadow:0 0 0 0 rgba(37,33,97,.5); animation:cw-pulse 2s infinite; }
      /* Offline: grey, static dot and muted label — no pulse. */
      .cw-status.is-offline { color:#8b90a0; }
      .cw-status.is-offline .pulse { background:#c2c6d0; box-shadow:none; animation:none; }
      @keyframes cw-pulse { 0%{box-shadow:0 0 0 0 rgba(37,33,97,.5);} 70%{box-shadow:0 0 0 8px rgba(37,33,97,0);} 100%{box-shadow:0 0 0 0 rgba(37,33,97,0);} }
      .cw-body { flex:1; min-height:0; overflow-y:auto; padding:16px 14px 10px; background:#e9eef5; display:flex; flex-direction:column; gap:0; }
      .cw-date-divider { text-align:center; margin:14px 0 10px; font-size:11px; color:#667781; font-weight:500; }
      .cw-date-divider span { background:rgba(255,255,255,.85); padding:5px 16px; border-radius:999px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
      .cw-row { display:flex; align-items:flex-end; gap:6px; margin-top:2px; }
      .cw-row.new-group { margin-top:10px; }
      .cw-row.mine { justify-content:flex-end; }
      .cw-row.theirs { justify-content:flex-start; }
      .cw-mini-av { width:30px; height:30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:10px; font-weight:700; flex-shrink:0; overflow:hidden; position:relative; }
      .cw-mini-av.invisible { visibility:hidden; }
      .cw-bubble { max-width:65%; padding:7px 12px 5px; border-radius:12px; font-size:13px; line-height:1.5; word-break:break-word; white-space:pre-wrap; animation: bubble-in .15s ease-out; }
      @keyframes bubble-in { from { transform: translateY(5px); opacity:0; } to { transform: translateY(0); opacity:1; } }
      .cw-bubble.mine { background:var(--brand); color:#fff; border-bottom-right-radius:3px; box-shadow:0 1px 2px rgba(37,33,97,.25); }
      .cw-bubble.theirs { background:#fff; color:var(--ink); border-bottom-left-radius:3px; box-shadow:0 1px 2px rgba(0,0,0,.08); }
      .cw-bubble.mine.grouped { border-bottom-right-radius:12px; }
      .cw-bubble.theirs.grouped { border-bottom-left-radius:12px; }
      .cw-bubble .ts { display:flex; align-items:center; gap:4px; font-size:10px; opacity:.8; margin-top:3px; justify-content:flex-end; }
      .cw-bubble.theirs .ts { color:var(--ink-3); }
      .cw-bubble .ts i { font-size:11px; }
      .cw-bubble.mine .ts .read { color:#c4c1e8; }

      .cw-input-wrap { padding:12px 14px; border-top:1px solid var(--line); background:#fff; display:flex; gap:10px; align-items:flex-end; flex-shrink:0; }
      .chat-shell:not(.has-chat) .cw-header,
      .chat-shell:not(.has-chat) .cw-input-wrap { display:none !important; }
      .cw-input { flex:1; border:1.5px solid #e5e7eb; border-radius:22px; padding:10px 16px; font-size:13px; resize:none; min-height:42px; max-height:120px; font-family:inherit; line-height:1.5; background:#fafbfc; transition: all .15s; }
      .cw-input:focus { outline:none; border-color:var(--brand); background:#fff; box-shadow:0 0 0 4px rgba(37,33,97,.12); }
      .cw-send { width:42px; height:42px; border-radius:50%; background:var(--brand); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px; transition: all .15s; flex-shrink:0; box-shadow:0 2px 6px rgba(37,33,97,.4); }
      .cw-send:hover { background:var(--brand-dark); transform:scale(1.06); }
      .cw-send:disabled { opacity:.5; cursor:not-allowed; transform:none; }
      .cw-empty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--ink-3); padding:40px; text-align:center; background:linear-gradient(180deg,#f8fafc,#f1f5f9); }
      .cw-empty .es-ico { width:84px; height:84px; background:#fff; border-radius:24px; box-shadow: var(--shadow-md); display:flex; align-items:center; justify-content:center; font-size:32px; color:var(--brand); margin-bottom:18px; }
      .cw-empty h4 { font-size:16px; font-weight:600; color:var(--ink-2); margin:0 0 6px; }
      .cw-empty p { font-size:13px; margin:0; max-width:280px; line-height:1.55; }

      /* Avatar gradient palette */
      .av-g-1 { background:linear-gradient(135deg,#3d3a7a,#252161); }
      .av-g-2 { background:linear-gradient(135deg,#5b56b8,#252161); }
      .av-g-3 { background:linear-gradient(135deg,#f59e0b,#b45309); }
      .av-g-4 { background:linear-gradient(135deg,#ef4444,#b91c1c); }
      .av-g-5 { background:linear-gradient(135deg,#8b5cf6,#6d28d9); }
      .av-g-6 { background:linear-gradient(135deg,#06b6d4,#0e7490); }
      .av-g-7 { background:linear-gradient(135deg,#ec4899,#be185d); }
      .av-g-8 { background:linear-gradient(135deg,#14b8a6,#0f766e); }
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
      <div class="dash-content msg-app">

         <!-- HERO -->
         <div class="msg-hero">
            <div class="msg-hero-row">
               <div>
                  <h1 class="msg-hero-title">
                     <span class="msg-hero-icon"><i class="fas fa-comment-dots"></i></span>
                     Messages
                  </h1>
                  <div class="msg-breadcrumb">
                     <a href="index.php">Dashboard</a> <span style="margin:0 6px;">/</span> Messages
                  </div>
                 
               </div>
               <div>
                  <button class="btn-brand" onclick="activateTab('compose')">
                     <i class="fas fa-paper-plane"></i> New SMS
                  </button>
               </div>
            </div>

            <div class="stat-row">
               <div class="stat-card">
                  <div class="stat-ico blue"><i class="fas fa-inbox"></i></div>
                  <div>
                     <div class="stat-val" id="stat-inbox">—</div>
                     <div class="stat-lbl">Inbox</div>
                  </div>
               </div>
               <div class="stat-card">
                  <div class="stat-ico green"><i class="fas fa-paper-plane"></i></div>
                  <div>
                     <div class="stat-val" id="stat-sent">—</div>
                     <div class="stat-lbl">Sent SMS</div>
                  </div>
               </div>
               <div class="stat-card">
                  <div class="stat-ico purple"><i class="fas fa-users"></i></div>
                  <div>
                     <div class="stat-val" id="stat-chats">—</div>
                     <div class="stat-lbl">Active Chats</div>
                  </div>
               </div>
               <div class="stat-card">
                  <div class="stat-ico amber"><i class="fas fa-bell"></i></div>
                  <div>
                     <div class="stat-val" id="stat-unread">—</div>
                     <div class="stat-lbl">Unread Chats</div>
                  </div>
               </div>
            </div>
         </div>

         <!-- TABS -->
         <div class="msg-shell">
            <div class="tab-bar" id="msg-tabs">
               <button class="tab-btn active" data-tab="inbox">
                  <i class="fas fa-inbox"></i> Inbox
                  <span class="pip" id="inbox-pip" style="display:none">0</span>
               </button>
               <button class="tab-btn" data-tab="sent">
                  <i class="fas fa-paper-plane"></i> Sent
               </button>
               <button class="tab-btn" data-tab="compose">
                  <i class="fas fa-pen"></i> Compose SMS
               </button>
               <button class="tab-btn" data-tab="chat">
                  <i class="fas fa-comments"></i> CRM Chat
                  <span class="pip" id="chat-pip" style="display:none">0</span>
               </button>
            </div>

            <!-- INBOX -->
            <div class="pane active" id="pane-inbox">
               <div class="pane-head">
                  <h3><i class="fas fa-inbox" style="color:#252161;"></i> Incoming SMS <span class="pane-cnt" id="inbox-count">0</span></h3>
                  <button class="btn-ghost" onclick="loadInbox()"><i class="fas fa-rotate"></i> Refresh</button>
               </div>
               <div id="inbox-empty" style="display:none;"></div>
               <div class="msg-table-wrap" id="inbox-wrap">
                  <table class="msg-table" id="inbox-table" style="width:100%">
                     <thead>
                        <tr>
                           <th></th>
                           <th>From</th>
                           <th>Message</th>
                           <th>Date</th>
                           <th></th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
               </div>
            </div>

            <!-- SENT -->
            <div class="pane" id="pane-sent">
               <div class="pane-head">
                  <h3><i class="fas fa-paper-plane" style="color:#252161;"></i> Sent SMS <span class="pane-cnt" id="sent-count">0</span></h3>
                  <button class="btn-ghost" onclick="loadSent()"><i class="fas fa-rotate"></i> Refresh</button>
               </div>
               <div id="sent-empty" style="display:none;"></div>
               <div class="msg-table-wrap" id="sent-wrap">
                  <table class="msg-table" id="sent-table" style="width:100%">
                     <thead>
                        <tr>
                           <th></th>
                           <th>To</th>
                           <th>Message</th>
                           <th>Sent By</th>
                           <th>Date</th>
                           <th></th>
                        </tr>
                     </thead>
                     <tbody></tbody>
                  </table>
               </div>
            </div>

            <!-- COMPOSE -->
            <div class="pane" id="pane-compose">
               <div class="pane-head">
                  <h3><i class="fas fa-pen" style="color:#252161;"></i> Compose SMS</h3>
               </div>
               <div class="compose-grid">
                  <div class="compose-card">
                     <div class="form-row">
                        <label class="form-label" for="sms_to">To <span style="color:#ef4444;">*</span></label>
                        <input type="tel" id="sms_to" class="form-input" placeholder="e.g. +1 555 123 4567 or 03001234567" autocomplete="off">
                        <div class="form-hint"><i class="fas fa-info-circle"></i> US/CA (1XXXXXXXXXX), PK (03XXXXXXXXX), or full international (+CC…). Numbers are auto-normalized.</div>
                     </div>
                     <div class="form-row">
                        <label class="form-label" for="sms_body">Message <span style="color:#ef4444;">*</span></label>
                        <textarea id="sms_body" class="form-textarea" placeholder="Type your message here…" maxlength="1600"></textarea>
                        <div class="compose-counter">
                           <span><span class="segs"><span id="sms_segs">1</span> segment(s)</span></span>
                           <span><span id="sms_count">0</span> / 1600</span>
                        </div>
                     </div>
                     <div class="send-row">
                        <button class="btn-brand" id="sms_send_btn" onclick="sendSms()"><i class="fas fa-paper-plane"></i> Send SMS</button>
                        <button class="btn-ghost" type="button" onclick="clearCompose()"><i class="fas fa-eraser"></i> Clear</button>
                        <span id="sms_status" class="send-status"></span>
                     </div>
                  </div>

                  <div>
                     <div class="phone-preview">
                        <div class="phone-bar">
                           <span><i class="fas fa-signal"></i></span>
                           <span class="right"><i class="fas fa-wifi"></i> <i class="fas fa-battery-full"></i></span>
                        </div>
                        <div class="phone-screen" id="phone_screen">
                           <div class="phone-contact">
                              <div class="pc-name" id="pc_name">+1 555 123 4567</div>
                           </div>
                           <div class="phone-bubble placeholder" id="phone_bubble">Your message preview will appear here…</div>
                        </div>
                        <div class="phone-foot">Preview · SMS</div>
                     </div>
                  </div>
               </div>
            </div>

            <!-- CRM CHAT -->
            <div class="pane" id="pane-chat" style="padding:18px;">
               <div class="chat-shell">
                  <div class="cl-side">
                     <div class="cl-head">
                        <div class="cl-head-row">
                           <h4>Team Chats</h4>
                           <span class="cl-cnt" id="chat-user-count">0</span>
                        </div>
                        <div class="cl-search">
                           <i class="fas fa-search"></i>
                           <input type="text" id="chat_user_search" placeholder="Search teammates…">
                        </div>
                     </div>
                     <div class="cl-list" id="chat_users_list">
                        <div class="skel-row"><div class="skel skel-circle"></div><div style="flex:1;display:flex;flex-direction:column;gap:8px;"><div class="skel skel-line short"></div><div class="skel skel-line"></div></div></div>
                        <div class="skel-row"><div class="skel skel-circle"></div><div style="flex:1;display:flex;flex-direction:column;gap:8px;"><div class="skel skel-line short"></div><div class="skel skel-line"></div></div></div>
                        <div class="skel-row"><div class="skel skel-circle"></div><div style="flex:1;display:flex;flex-direction:column;gap:8px;"><div class="skel skel-line short"></div><div class="skel skel-line"></div></div></div>
                     </div>
                  </div>
                  <div class="cw-main">
                     <div class="cw-header" id="chat_header">
                        <div class="cw-avatar" id="chat_header_avatar"></div>
                        <div>
                           <div class="cw-name" id="chat_header_name"></div>
                           <div class="cw-status" id="chat_header_status"><span class="pulse"></span> <span id="chat_header_status_text">Offline</span></div>
                        </div>
                     </div>
                     <div class="cw-body" id="chat_body">
                        <div class="cw-empty" id="chat_empty">
                           <div class="es-ico"><i class="fas fa-comments"></i></div>
                           <h4>Pick a teammate to start chatting</h4>
                           <p>Select someone from the left to view your conversation or send a new message.</p>
                        </div>
                     </div>
                     <div class="cw-input-wrap" id="chat_input_wrap">
                        <textarea id="chat_input" class="cw-input" placeholder="Type a message… (Enter to send, Shift+Enter for new line)" rows="1"></textarea>
                        <button class="cw-send" onclick="sendChat()" title="Send"><i class="fas fa-paper-plane"></i></button>
                     </div>
                  </div>
               </div>
            </div>

         </div>

      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <script>
   (function () {
      const ME          = <?= json_encode($user_id) ?>;
      const MY_NAME     = <?= json_encode($user_full_name) ?>;
      const ENDPOINT    = 'comm_hub.php';
      const PUSHER_KEY  = <?= json_encode($pusher_key) ?>;
      const PUSHER_CLUS = <?= json_encode($pusher_cluster) ?>;

      function $(sel, root) { return (root || document).querySelector(sel); }
      function $$(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

      function esc(s) {
         return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      }
      function initials(name) {
         if (!name) return '?';
         const parts = String(name).trim().split(/\s+/).slice(0, 2);
         return (parts.map(p => p.charAt(0).toUpperCase()).join('') || '?').slice(0, 2);
      }
      function avatarGradient(key) {
         const s = String(key || '');
         let h = 0;
         for (let i = 0; i < s.length; i++) h = ((h << 5) - h) + s.charCodeAt(i);
         return 'av-g-' + (Math.abs(h) % 8 + 1);
      }
      function avatarImg(picUrl) {
         if (!picUrl) return '';
         // initials sit behind; img overlays. onerror hides broken pic so initials remain.
         return '<img src="' + esc(picUrl) + '" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();">';
      }
      function timeShort(dt) {
         if (!dt) return '';
         const d = new Date(String(dt).replace(' ', 'T'));
         if (isNaN(d.getTime())) return dt;
         const now = new Date();
         const sameDay = d.toDateString() === now.toDateString();
         if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
         const diff = (now - d) / 86400000;
         if (diff < 7) return d.toLocaleDateString([], { weekday: 'short' });
         return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
      }
      function timeFull(dt) {
         if (!dt) return '';
         const d = new Date(String(dt).replace(' ', 'T'));
         if (isNaN(d.getTime())) return dt;
         return d.toLocaleString();
      }
      function dayLabel(dt) {
         const d = new Date(String(dt).replace(' ', 'T'));
         if (isNaN(d.getTime())) return '';
         const now = new Date();
         const same = (a, b) => a.toDateString() === b.toDateString();
         const yest = new Date(now); yest.setDate(yest.getDate() - 1);
         if (same(d, now)) return 'Today';
         if (same(d, yest)) return 'Yesterday';
         return d.toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' });
      }

      function post(action, data) {
         const fd = new FormData();
         fd.append('ajax_action', action);
         Object.keys(data || {}).forEach(k => fd.append(k, data[k]));
         return fetch(ENDPOINT, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json());
      }

      function setPip(id, n) {
         const el = $('#' + id);
         if (!el) return;
         if (n > 0) { el.style.display = 'inline-block'; el.textContent = n; }
         else el.style.display = 'none';
      }
      function setText(id, v) { const el = $('#' + id); if (el) el.textContent = v; }

      /* ------------ TABS ------------ */
      $$('.tab-btn').forEach(btn => {
         btn.addEventListener('click', () => activateTab(btn.dataset.tab));
      });
      window.activateTab = function (name) {
         $$('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === name));
         $$('.pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
         if (name === 'inbox') loadInbox();
         else if (name === 'sent') loadSent();
         else if (name === 'chat') loadChatUsers();
         else if (name === 'compose') setTimeout(() => $('#sms_to').focus(), 50);
      };

      /* ------------ SMS DATATABLES ------------ */
      let inboxDT = null, sentDT = null;

      function emptyStateHtml(mode) {
         return '<div class="empty-state">' +
            '<div class="es-ico"><i class="fas fa-' + (mode === 'inbox' ? 'inbox' : 'paper-plane') + '"></i></div>' +
            '<h4>' + (mode === 'inbox' ? 'No incoming messages yet' : 'No sent messages yet') + '</h4>' +
            '<p>' + (mode === 'inbox'
               ? 'When customers reply to your SMS, their messages will appear here.'
               : 'Compose your first SMS from the “New SMS” button — sent messages will be archived here.') +
            '</p>' +
         '</div>';
      }

      function buildAvatarCell(phone) {
         const key = phone || '';
         return '<div class="mail-avatar ' + avatarGradient(key) + '"><i class="fas fa-mobile-alt"></i></div>';
      }
      function buildPhoneCell(phone, mode) {
         const tag = mode === 'sent'
            ? '<span class="mail-tag out">Sent</span>'
            : '<span class="mail-tag">Inbound</span>';
         return '<div class="cell-phone">' + esc(phone || '—') + tag + '</div>';
      }
      function buildPreviewCell(body) {
         return '<div class="cell-preview">' + esc(body || '') + '</div>';
      }
      function buildDateCell(dt) {
         return '<span title="' + esc(timeFull(dt)) + '">' + esc(timeShort(dt)) + '</span>';
      }
      function buildActionCell() {
         return '<button class="mail-action" data-act="reply"><i class="fas fa-reply"></i> Reply</button>';
      }

      function initDataTable(tableId, config) {
         if (typeof window.DataTable !== 'function') return null;
         try {
            return new window.DataTable('#' + tableId, config);
         } catch (e) {
            console.error('DataTable init failed for #' + tableId, e);
            return null;
         }
      }
      function destroyDataTable(dt, tableId) {
         if (!dt) return;
         try { dt.destroy(); } catch (e) { /* ignore */ }
         const tbody = $('#' + tableId + ' tbody');
         if (tbody) tbody.innerHTML = '';
      }

      function rebuildInboxTable(rows) {
         const wrap = $('#inbox-wrap'), empty = $('#inbox-empty');
         destroyDataTable(inboxDT, 'inbox-table'); inboxDT = null;
         if (!rows || !rows.length) {
            wrap.style.display = 'none';
            empty.style.display = 'block';
            empty.innerHTML = emptyStateHtml('inbox');
            return;
         }
         empty.style.display = 'none';
         wrap.style.display = 'block';
         const tbody = $('#inbox-table tbody');
         tbody.innerHTML = rows.map(r => {
            const phone = r.From_mail || '';
            return '<tr data-phone="' + esc(phone) + '" data-ts="' + esc(r.sent_at || '') + '">' +
               '<td class="col-avatar">' + buildAvatarCell(phone) + '</td>' +
               '<td class="col-phone">' + buildPhoneCell(phone, 'inbox') + '</td>' +
               '<td>' + buildPreviewCell(r.body) + '</td>' +
               '<td class="col-date" data-order="' + esc(r.sent_at || '') + '">' + buildDateCell(r.sent_at) + '</td>' +
               '<td class="col-act">' + buildActionCell() + '</td>' +
            '</tr>';
         }).join('');
         inboxDT = initDataTable('inbox-table', {
            order: [[3, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            columnDefs: [
               { targets: [0, 4], orderable: false, searchable: false }
            ],
            language: { search: '', searchPlaceholder: 'Search messages…' }
         });
      }

      function rebuildSentTable(rows) {
         const wrap = $('#sent-wrap'), empty = $('#sent-empty');
         destroyDataTable(sentDT, 'sent-table'); sentDT = null;
         if (!rows || !rows.length) {
            wrap.style.display = 'none';
            empty.style.display = 'block';
            empty.innerHTML = emptyStateHtml('sent');
            return;
         }
         empty.style.display = 'none';
         wrap.style.display = 'block';
         const tbody = $('#sent-table tbody');
         tbody.innerHTML = rows.map(r => {
            const phone = r.To_mail || '';
            return '<tr data-phone="' + esc(phone) + '" data-ts="' + esc(r.sent_at || '') + '">' +
               '<td class="col-avatar">' + buildAvatarCell(phone) + '</td>' +
               '<td class="col-phone">' + buildPhoneCell(phone, 'sent') + '</td>' +
               '<td>' + buildPreviewCell(r.body) + '</td>' +
               '<td class="col-by">' + esc(r.sent_by_name || '—') + '</td>' +
               '<td class="col-date" data-order="' + esc(r.sent_at || '') + '">' + buildDateCell(r.sent_at) + '</td>' +
               '<td class="col-act">' + buildActionCell() + '</td>' +
            '</tr>';
         }).join('');
         sentDT = initDataTable('sent-table', {
            order: [[4, 'desc']],
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            columnDefs: [
               { targets: [0, 5], orderable: false, searchable: false }
            ],
            language: { search: '', searchPlaceholder: 'Search messages…' }
         });
      }

      // Single document-level delegation for inbox/sent row clicks.
      // Works regardless of DataTable re-inits.
      document.addEventListener('click', (e) => {
         const tr = e.target.closest('#inbox-table tbody tr, #sent-table tbody tr');
         if (!tr) return;
         const phone = tr.dataset.phone || '';
         if (!phone) return;
         const isReplyBtn = !!e.target.closest('.mail-action');
         $('#sms_to').value = phone;
         activateTab('compose');
         if (isReplyBtn) $('#sms_body').focus();
      });

      window.loadInbox = function () {
         post('sms_inbox', {}).then(d => {
            if (d.status !== 'success') {
               $('#inbox-wrap').style.display = 'none';
               $('#inbox-empty').style.display = 'block';
               $('#inbox-empty').innerHTML = '<div class="empty-state"><div class="es-ico"><i class="fas fa-triangle-exclamation"></i></div><h4>Could not load inbox</h4><p>' + esc(d.message || '') + '</p></div>';
               return;
            }
            rebuildInboxTable(d.messages || []);
            const n = (d.messages || []).length;
            setText('inbox-count', n);
            setText('stat-inbox', n);
            setPip('inbox-pip', n);
         }).catch(() => {
            $('#inbox-wrap').style.display = 'none';
            $('#inbox-empty').style.display = 'block';
            $('#inbox-empty').innerHTML = '<div class="empty-state"><div class="es-ico"><i class="fas fa-wifi"></i></div><h4>Network error</h4><p>Could not reach the server.</p></div>';
         });
      };

      window.loadSent = function () {
         post('sms_sent', {}).then(d => {
            if (d.status !== 'success') {
               $('#sent-wrap').style.display = 'none';
               $('#sent-empty').style.display = 'block';
               $('#sent-empty').innerHTML = '<div class="empty-state"><div class="es-ico"><i class="fas fa-triangle-exclamation"></i></div><h4>Could not load sent</h4><p>' + esc(d.message || '') + '</p></div>';
               return;
            }
            rebuildSentTable(d.messages || []);
            const n = (d.messages || []).length;
            setText('sent-count', n);
            setText('stat-sent', n);
         }).catch(() => {
            $('#sent-wrap').style.display = 'none';
            $('#sent-empty').style.display = 'block';
            $('#sent-empty').innerHTML = '<div class="empty-state"><div class="es-ico"><i class="fas fa-wifi"></i></div><h4>Network error</h4><p>Could not reach the server.</p></div>';
         });
      };

      /* ------------ COMPOSE ------------ */
      const smsBody    = $('#sms_body');
      const smsTo      = $('#sms_to');
      const smsCount   = $('#sms_count');
      const smsSegs    = $('#sms_segs');
      const phoneBub   = $('#phone_bubble');
      const phoneName  = $('#pc_name');

      function updateComposePreview() {
         const text = smsBody.value;
         smsCount.textContent = text.length;
         const seg = text.length === 0 ? 1 : Math.ceil(text.length / 160);
         smsSegs.textContent = seg;
         if (text.length === 0) {
            phoneBub.classList.add('placeholder');
            phoneBub.textContent = 'Your message preview will appear here…';
         } else {
            phoneBub.classList.remove('placeholder');
            phoneBub.textContent = text;
         }
      }
      function updatePreviewName() {
         const t = smsTo.value.trim();
         phoneName.textContent = t || '+1 555 123 4567';
      }
      smsBody.addEventListener('input', updateComposePreview);
      smsTo.addEventListener('input', updatePreviewName);

      window.clearCompose = function () {
         smsTo.value = '';
         smsBody.value = '';
         updateComposePreview();
         updatePreviewName();
         const s = $('#sms_status');
         s.textContent = '';
      };

      window.sendSms = function () {
         const to = smsTo.value.trim();
         const body = smsBody.value.trim();
         const btn = $('#sms_send_btn');
         const stat = $('#sms_status');
         if (!to || !body) {
            stat.style.color = '#dc2626';
            stat.innerHTML = '<i class="fas fa-circle-exclamation"></i> Phone and message are required.';
            return;
         }
         btn.disabled = true;
         stat.style.color = 'var(--ink-3)';
         stat.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
         post('sms_send', { to_phone: to, body: body }).then(d => {
            btn.disabled = false;
            if (d.status === 'success') {
               stat.style.color = '#252161';
               stat.innerHTML = '<i class="fas fa-check-circle"></i> Message sent.';
               clearCompose();
               loadSent();
            } else {
               stat.style.color = '#dc2626';
               stat.innerHTML = '<i class="fas fa-circle-exclamation"></i> ' + esc(d.message || 'Send failed');
            }
         }).catch(() => {
            btn.disabled = false;
            stat.style.color = '#dc2626';
            stat.innerHTML = '<i class="fas fa-wifi"></i> Network error.';
         });
      };

      /* ------------ CRM CHAT ------------ */
      let activeChatUser = null;
      let chatLastId = 0;
      let chatPollTimer = null;
      let cachedChatUsers = [];

      function renderChatUsers(filter) {
         const list = $('#chat_users_list');
         const filt = (filter || '').trim().toLowerCase();
         const items = cachedChatUsers.filter(u =>
            !filt || (u.user_name || '').toLowerCase().includes(filt)
         );
         setText('chat-user-count', cachedChatUsers.length);
         if (!items.length) {
            list.innerHTML = '<div class="empty-state" style="padding:30px 16px;"><div class="es-ico" style="width:50px;height:50px;border-radius:14px;font-size:20px;"><i class="fas fa-user-slash"></i></div><h4 style="font-size:13px;">No teammates found</h4><p style="font-size:12px;">Try a different search term.</p></div>';
            return;
         }
         let totalUnread = 0, activeChats = 0;
         list.innerHTML = items.map(u => {
            const unread = parseInt(u.unread || 0, 10);
            totalUnread += unread;
            if (u.last_at) activeChats++;
            const isActive = activeChatUser && parseInt(activeChatUser.id, 10) === parseInt(u.user_id, 10);
            const grad = avatarGradient(u.user_name || u.user_id);
            const picUrl = u.user_pic_url || '';
            return '<div class="cl-user' + (isActive ? ' active' : '') + '" data-uid="' + u.user_id + '">' +
               '<div class="cl-avatar ' + grad + '">' +
                  '<span class="cl-init">' + esc(initials(u.user_name)) + '</span>' +
                  avatarImg(picUrl) +
                  '<span class="dot' + (u.is_online ? '' : ' off') + '"></span>' +
                  (unread > 0 ? '<span class="cl-unread">' + unread + '</span>' : '') +
               '</div>' +
               '<div class="cl-meta">' +
                  '<div class="cl-name">' + esc(u.user_name || '') + '</div>' +
                  '<div class="cl-preview">' + esc(u.last_message || 'No messages yet') + '</div>' +
               '</div>' +
               '<div class="cl-right">' +
                  (u.last_at ? '<span class="cl-time">' + esc(timeShort(u.last_at)) + '</span>' : '<span class="cl-time"></span>') +
               '</div>' +
            '</div>';
         }).join('');
         setPip('chat-pip', totalUnread);
         setText('stat-chats', activeChats);
         setText('stat-unread', totalUnread);
      }

      function loadChatUsers() {
         post('chat_users', {}).then(d => {
            if (d.status !== 'success') {
               $('#chat_users_list').innerHTML = '<div class="empty-state"><div class="es-ico"><i class="fas fa-triangle-exclamation"></i></div><h4>Could not load users</h4><p>' + esc(d.message || '') + '</p></div>';
               return;
            }
            cachedChatUsers = d.users || [];
            renderChatUsers($('#chat_user_search').value);
         });
      }
      $('#chat_user_search').addEventListener('input', (e) => renderChatUsers(e.target.value));

      // Delegated click — survives re-renders (renderChatUsers replaces innerHTML every 15s)
      $('#chat_users_list').addEventListener('click', (e) => {
         const row = e.target.closest('.cl-user');
         if (!row) return;
         const uid = row.dataset.uid;
         if (!uid) return;
         const u = cachedChatUsers.find(x => String(x.user_id) === String(uid));
         if (u) openChat(u);
      });

      // "Last seen 4m ago" / "Last seen Tue" — coarse, since last_seen only
      // refreshes while the teammate has a page open.
      function lastSeenLabel(dt) {
         if (!dt) return 'Offline';
         const d = new Date(String(dt).replace(' ', 'T'));
         if (isNaN(d.getTime())) return 'Offline';
         const mins = Math.floor((Date.now() - d.getTime()) / 60000);
         if (mins < 1) return 'Last seen just now';
         if (mins < 60) return 'Last seen ' + mins + 'm ago';
         if (mins < 1440) return 'Last seen ' + Math.floor(mins / 60) + 'h ago';
         return 'Last seen ' + timeShort(dt);
      }

      function setChatPresence(presence) {
         const wrap = $('#chat_header_status');
         if (!wrap) return;
         const online = !!(presence && presence.is_online);
         wrap.classList.toggle('is-offline', !online);
         $('#chat_header_status_text').textContent = online ? 'Online' : lastSeenLabel(presence && presence.last_seen);
         // Keep the sidebar dot in step with the header for the open chat.
         if (activeChatUser) {
            const u = cachedChatUsers.find(x => String(x.user_id) === String(activeChatUser.id));
            if (u && u.is_online !== online) {
               u.is_online = online;
               if (presence) u.last_seen = presence.last_seen;
               renderChatUsers($('#chat_user_search').value);
            }
         }
      }

      function openChat(user) {
         activeChatUser = { id: user.user_id, name: user.user_name, pic: user.user_pic_url || '' };
         chatLastId = 0;
         lastBubbleDay = '';
         const grad = avatarGradient(user.user_name || user.user_id);
         document.querySelector('.chat-shell').classList.add('has-chat');
         // Belt-and-braces: clear any cached inline display:none from older HTML and force visible.
         $('#chat_header').style.display = 'flex';
         $('#chat_input_wrap').style.display = 'flex';
         $('#chat_header_name').textContent = user.user_name || '';
         setChatPresence({ is_online: !!user.is_online, last_seen: user.last_seen || null });
         const ha = $('#chat_header_avatar');
         ha.className = 'cw-avatar ' + grad;
         ha.style.position = 'relative';
         ha.innerHTML = esc(initials(user.user_name)) + avatarImg(user.user_pic_url || '');
         const body = $('#chat_body');
         body.innerHTML = '<div class="cw-empty" style="background:transparent;"><div class="es-ico"><i class="fas fa-spinner fa-spin"></i></div><h4>Loading conversation…</h4></div>';
         renderChatUsers($('#chat_user_search').value);

         post('chat_messages', { other_id: user.user_id }).then(d => {
            if (d.status !== 'success') {
               body.innerHTML = '<div class="cw-empty"><div class="es-ico"><i class="fas fa-triangle-exclamation"></i></div><h4>Could not load</h4><p>' + esc(d.message || '') + '</p></div>';
               return;
            }
            if (d.presence) setChatPresence(d.presence);
            body.innerHTML = '';
            (d.messages || []).forEach(m => appendBubble(m));
            if (!d.messages || !d.messages.length) {
               body.innerHTML =
                  '<div class="cw-empty" style="background:transparent;">' +
                     '<div class="es-ico"><i class="fas fa-hand-wave"></i></div>' +
                     '<h4>Say hi to ' + esc(user.user_name || 'them') + '</h4>' +
                     '<p>No messages yet — start the conversation below.</p>' +
                  '</div>';
            }
            body.scrollTop = body.scrollHeight;
            startChatPolling();
            const u = cachedChatUsers.find(x => String(x.user_id) === String(user.user_id));
            if (u) u.unread = 0;
            renderChatUsers($('#chat_user_search').value);
         });
      }

      function appendDayDivider(label) {
         const body = $('#chat_body');
         const div = document.createElement('div');
         div.className = 'cw-date-divider';
         div.innerHTML = '<span>' + esc(label) + '</span>';
         body.appendChild(div);
      }

      let lastBubbleDay = '';
      function appendBubble(m) {
         const body = $('#chat_body');
         // skip if this message id is already rendered (Pusher + poll race)
         if (m && m.id && body.querySelector('.cw-row[data-id="' + parseInt(m.id, 10) + '"]')) return;
         // strip "empty" state if present
         const empty = body.querySelector('.cw-empty');
         if (empty) empty.remove();

         const dl = dayLabel(m.sent_at);
         if (dl && dl !== lastBubbleDay) {
            appendDayDivider(dl);
            lastBubbleDay = dl;
         }

         const mine = String(m.sender_id) === String(ME);

         // --- grouping: check previous rendered row ---
         const prevRows = body.querySelectorAll('.cw-row[data-sender]');
         const prevRow = prevRows.length ? prevRows[prevRows.length - 1] : null;
         const prevSender = prevRow ? prevRow.dataset.sender : null;
         const grouped = prevSender !== null && prevSender === String(m.sender_id);

         if (grouped && prevRow) {
            // Previous row loses its tail, current is a continuation
            prevRow.classList.add('grouped');
            const prevBubble = prevRow.querySelector('.cw-bubble');
            if (prevBubble) prevBubble.classList.add('grouped');
            // Hide the avatar on the previous row (only last in group shows it)
            const prevAv = prevRow.querySelector('.cw-mini-av');
            if (prevAv) prevAv.classList.add('invisible');
         }

         const row = document.createElement('div');
         row.className = 'cw-row ' + (mine ? 'mine' : 'theirs') + (grouped ? '' : ' new-group');
         row.dataset.id = m.id;
         row.dataset.sender = m.sender_id;

         const tickIcon = mine
            ? (parseInt(m.is_read || 0, 10) === 1
                  ? '<i class="fas fa-check-double read"></i>'
                  : '<i class="fas fa-check"></i>')
            : '';

         // Mini avatar for received messages (left side, aligned to bottom of bubble)
         let avatarHtml = '';
         if (!mine && activeChatUser) {
            const aGrad = avatarGradient(activeChatUser.name || activeChatUser.id);
            const aInit = initials(activeChatUser.name || '?');
            const aPic = activeChatUser.pic ? avatarImg(activeChatUser.pic) : '';
            avatarHtml = '<div class="cw-mini-av ' + aGrad + '" style="position:relative;">' + esc(aInit) + aPic + '</div>';
         }

         row.innerHTML = avatarHtml +
            '<div class="cw-bubble ' + (mine ? 'mine' : 'theirs') + '">' +
               esc(m.message) +
               '<span class="ts">' + esc(timeShort(m.sent_at)) + (tickIcon ? ' ' + tickIcon : '') + '</span>' +
            '</div>';
         body.appendChild(row);
         chatLastId = Math.max(chatLastId, parseInt(m.id, 10));
      }

      function pollChat() {
         if (!activeChatUser) return;
         post('chat_poll', { other_id: activeChatUser.id, last_id: chatLastId }).then(d => {
            if (d.status !== 'success') return;
            if (d.presence) setChatPresence(d.presence);
            if (d.messages && d.messages.length) {
               const body = $('#chat_body');
               const nearBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 100;
               d.messages.forEach(m => appendBubble(m));
               if (nearBottom) body.scrollTop = body.scrollHeight;
            }
         });
      }
      function startChatPolling() {
         stopChatPolling();
         chatPollTimer = setInterval(pollChat, 4000);
      }
      function stopChatPolling() {
         if (chatPollTimer) { clearInterval(chatPollTimer); chatPollTimer = null; }
      }

      window.sendChat = function () {
         if (!activeChatUser) return;
         const input = $('#chat_input');
         const text = input.value.trim();
         if (!text) return;
         input.value = '';
         input.style.height = 'auto';
         post('chat_send', { recipient_id: activeChatUser.id, message: text }).then(d => {
            if (d.status === 'success' && d.message_row) {
               appendBubble(d.message_row);
               const body = $('#chat_body');
               body.scrollTop = body.scrollHeight;
               const u = cachedChatUsers.find(x => String(x.user_id) === String(activeChatUser.id));
               if (u) { u.last_message = text; u.last_at = d.message_row.sent_at; }
               renderChatUsers($('#chat_user_search').value);
            } else {
               input.value = text;
               alert(d.message || 'Send failed');
            }
         });
      };

      const chatInput = $('#chat_input');
      chatInput.addEventListener('keydown', (e) => {
         if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChat();
         }
      });
      chatInput.addEventListener('input', () => {
         chatInput.style.height = 'auto';
         chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
      });

      // Periodic refresh of users list (for previews/unread updates)
      setInterval(() => {
         if ($('#pane-chat').classList.contains('active')) loadChatUsers();
      }, 15000);

      /* ------------ REAL-TIME (Pusher) ------------ */
      // Subscribe to our private channel so inbound messages from any teammate
      // arrive instantly — no waiting for the 4s poll.
      if (PUSHER_KEY && typeof Pusher === 'function') {
         try {
            const rtPusher = new Pusher(PUSHER_KEY, {
               cluster: PUSHER_CLUS,
               forceTLS: true,
               authEndpoint: 'comm_hub.php?action=pusher_auth'
            });
            const myChan = rtPusher.subscribe('private-chat.user.' + String(ME));
            myChan.bind('message', (data) => {
               const m = (typeof data === 'string') ? JSON.parse(data) : data;
               if (!m || !m.id) return;
               const senderId = String(m.sender_id);
               // If this conversation is open, append the bubble live + mark read on server
               if (activeChatUser && String(activeChatUser.id) === senderId) {
                  // Skip if we already have this id (avoids dup with poll race)
                  if (!$('#chat_body').querySelector('.cw-row[data-id="' + parseInt(m.id, 10) + '"]')) {
                     const body = $('#chat_body');
                     const nearBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 100;
                     appendBubble(m);
                     if (nearBottom) body.scrollTop = body.scrollHeight;
                  }
                  // mark read so unread badge clears on sender side via next poll
                  post('chat_messages', { other_id: activeChatUser.id });
               }
               // Refresh sidebar previews/unread counts
               if ($('#pane-chat').classList.contains('active')) loadChatUsers();
            });
         } catch (e) { /* Pusher unavailable — polling fallback still runs */ }
      }

      // Initial loads — populate stats on first paint
      loadInbox();
      loadSent();
      loadChatUsers();
      updateComposePreview();
   })();
   </script>

</body>

</html>
