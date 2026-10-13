<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load env early for Pusher auth
$env = [];
$envPath = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envPath)) {
    $lines = file($envPath);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^([A-Za-z0-9_]+)\s*=\s*"?([^"]*)"?$/', $line, $m)) {
            $env[$m[1]] = $m[2];
        }
    }
}

$pusher_app_id = $env['app_id'] ?? '';
$pusher_key = $env['key'] ?? '';
$pusher_secret = $env['secret'] ?? '';
$pusher_cluster = $env['cluster'] ?? 'us3';

// Pusher private channel auth before any auth guards
if (
    (isset($_GET['action']) && $_GET['action'] === 'pusher_auth') ||
    (isset($_POST['action']) && $_POST['action'] === 'pusher_auth') ||
    (isset($_POST['socket_id']) && isset($_POST['channel_name']))
) {
    header('Content-Type: application/json');
    $socket_id = isset($_POST['socket_id']) ? trim($_POST['socket_id']) : '';
    $channel_name = isset($_POST['channel_name']) ? trim($_POST['channel_name']) : '';
    if ($socket_id === '' || $channel_name === '') {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $rawPost = [];
            parse_str($raw, $rawPost);
            if ($socket_id === '' && isset($rawPost['socket_id']))
                $socket_id = trim($rawPost['socket_id']);
            if ($channel_name === '' && isset($rawPost['channel_name']))
                $channel_name = trim($rawPost['channel_name']);
        }
    }
    if ($socket_id === '' || $channel_name === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'reason' => 'missing_params']);
        exit;
    }
    if ($pusher_key === '' || $pusher_secret === '') {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'reason' => 'missing_keys']);
        exit;
    }
    if (!preg_match('/^private-(chat|typing)\.user\.(\d+)$/', $channel_name) && !preg_match('/^private-chat\.group\.(\d+)$/', $channel_name)) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden']);
        exit;
    }
    $stringToSign = $socket_id . ':' . $channel_name;
    $signature = hash_hmac('sha256', $stringToSign, $pusher_secret);
    $auth = $pusher_key . ':' . $signature;
    echo json_encode(['auth' => $auth]);
    exit;
}

include "includes/db.php";
include "includes/auth.php";
$user_id = $_SESSION['user_id'];
$users = [];

function getInitials($name)
{
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

function ensureGroupChatTables($con)
{
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(120) NOT NULL UNIQUE,
        product_topic VARCHAR(120) DEFAULT NULL,
        created_by INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        last_read_message_id INT NOT NULL DEFAULT 0,
        joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        INDEX idx_member_user (user_id),
        INDEX idx_member_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($con, "CREATE TABLE IF NOT EXISTS chat_group_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        mentions_csv VARCHAR(1000) DEFAULT '',
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_group_messages (group_id, id),
        INDEX idx_sender (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function seedDefaultProductGroups($con, $currentUserId)
{
    $defaults = [
        ['Payroll Tax Team', 'Payroll Tax'],
        ['Sales Tax Team', 'Sales Tax'],
        ['Bookkeeping Team', 'Bookkeeping'],
        ['Audit Team', 'Audit']
    ];
    foreach ($defaults as $g) {
        $groupName = mysqli_real_escape_string($con, $g[0]);
        $topic = mysqli_real_escape_string($con, $g[1]);
        mysqli_query($con, "INSERT IGNORE INTO chat_groups (group_name, product_topic, created_by) VALUES ('{$groupName}', '{$topic}', " . (int) $currentUserId . ")");
        $gr = mysqli_query($con, "SELECT id, created_by FROM chat_groups WHERE group_name = '{$groupName}' LIMIT 1");
        $groupId = 0;
        $createdBy = 0;
        if ($gr && $row = mysqli_fetch_assoc($gr)) {
            $groupId = (int) $row['id'];
            $createdBy = (int) $row['created_by'];
        }
        if ($groupId > 0 && $createdBy > 0) {
            mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, user_id) VALUES ({$groupId}, {$createdBy})");
        }
    }
}

function parseMentionIdsFromCsv($csv)
{
    $csv = trim((string) $csv);
    if ($csv === '') {
        return [];
    }
    $parts = explode(',', trim($csv, ','));
    $ids = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && ctype_digit($p)) {
            $ids[] = (int) $p;
        }
    }
    return array_values(array_unique($ids));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    header('Content-Type: application/json');
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $recipient_id = isset($_POST['recipient_id']) ? trim($_POST['recipient_id']) : 'all';
    if ($message === '' || $pusher_app_id === '' || $pusher_key === '' || $pusher_secret === '') {
        http_response_code(400);
        echo json_encode(['ok' => false]);
        exit;
    }
    $channel = 'chat';
    $to_user_id = null;
    if (ctype_digit($recipient_id)) {
        $to_user_id = (int) $recipient_id;
        if ($to_user_id > 0) {
            $channel = 'private-chat.user.' . $to_user_id;
        }
    }
    $payload = [
        'user_id' => $user_id,
        'name' => $_SESSION['fullname'],
        'initials' => getInitials($_SESSION['fullname']),
        'text' => $message,
        'timestamp' => time(),
        'to_user_id' => $to_user_id
    ];
    $eventBody = json_encode([
        'name' => 'message',
        'channel' => $channel,
        'data' => json_encode($payload)
    ]);
    $body_md5 = md5($eventBody);
    $auth_timestamp = time();
    $params = [
        'auth_key' => $pusher_key,
        'body_md5' => $body_md5,
        'auth_timestamp' => $auth_timestamp,
        'auth_version' => '1.0'
    ];
    ksort($params);
    $path = '/apps/' . $pusher_app_id . '/events';
    $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $stringToSign = 'POST' . "\n" . $path . "\n" . $queryString;
    $signature = hash_hmac('sha256', $stringToSign, $pusher_secret);
    $url = 'https://api-' . $pusher_cluster . '.pusher.com' . $path . '?' . $queryString . '&auth_signature=' . $signature;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $eventBody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err || $code < 200 || $code >= 300) {
        http_response_code(500);
        echo json_encode(['ok' => false]);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

$json = function ($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_group') {
        ensureGroupChatTables($con);
        $group_name = isset($_POST['group_name']) ? trim($_POST['group_name']) : '';
        $product_topic = isset($_POST['product_topic']) ? trim($_POST['product_topic']) : '';
        $member_ids_raw = isset($_POST['member_ids']) ? trim($_POST['member_ids']) : '';
        if ($group_name === '') {
            http_response_code(400);
            $json(['status' => 'error', 'message' => 'Group name required']);
        }
        $stmt = mysqli_prepare($con, "INSERT INTO chat_groups (group_name, product_topic, created_by) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $group_name, $product_topic, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            http_response_code(400);
            $json(['status' => 'error', 'message' => 'Group already exists or cannot be created']);
        }
        $group_id = mysqli_insert_id($con);
        $memberIds = [];
        if ($member_ids_raw !== '') {
            $parts = explode(',', $member_ids_raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if (ctype_digit($p)) {
                    $memberIds[] = (int) $p;
                }
            }
        }
        if (empty($memberIds)) {
            $uRes = mysqli_query($con, "SELECT user_id FROM users");
            while ($uRes && $u = mysqli_fetch_assoc($uRes)) {
                $memberIds[] = (int) $u['user_id'];
            }
        }
        $memberIds[] = (int) $user_id;
        $memberIds = array_values(array_unique($memberIds));
        foreach ($memberIds as $mid) {
            mysqli_query($con, "INSERT IGNORE INTO chat_group_members (group_id, user_id) VALUES (" . (int) $group_id . ", " . (int) $mid . ")");
        }
        $json(['status' => 'success', 'group_id' => (int) $group_id]);
    }
    if ($_POST['action'] === 'send_group_message') {
        ensureGroupChatTables($con);
        $group_id = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        $mentions_raw = isset($_POST['mentioned_user_ids']) ? trim($_POST['mentioned_user_ids']) : '';
        if ($group_id <= 0 || $message === '') {
            http_response_code(400);
            $json(['status' => 'error']);
        }
        $memberCheck = mysqli_query($con, "SELECT 1 FROM chat_group_members WHERE group_id = {$group_id} AND user_id = " . (int) $user_id . " LIMIT 1");
        if (!$memberCheck || !mysqli_fetch_assoc($memberCheck)) {
            http_response_code(403);
            $json(['status' => 'error', 'message' => 'Not in group']);
        }
        $mentionIds = [];
        if ($mentions_raw !== '') {
            $parts = explode(',', $mentions_raw);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p !== '' && ctype_digit($p)) {
                    $mentionIds[] = (int) $p;
                }
            }
            $mentionIds = array_values(array_unique($mentionIds));
        }
        $mentions_csv = '';
        if (!empty($mentionIds)) {
            $mentions_csv = ',' . implode(',', $mentionIds) . ',';
        }
        $stmt = mysqli_prepare($con, "INSERT INTO chat_group_messages (group_id, sender_id, message, mentions_csv, sent_at) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iiss", $group_id, $user_id, $message, $mentions_csv);
        mysqli_stmt_execute($stmt);
        $insert_id = mysqli_insert_id($con);
        mysqli_query($con, "UPDATE chat_group_members SET last_read_message_id = {$insert_id} WHERE group_id = {$group_id} AND user_id = " . (int) $user_id);

        $payload = [
            'id' => (int) $insert_id,
            'group_id' => (int) $group_id,
            'sender_id' => (int) $user_id,
            'sender_name' => $_SESSION['fullname'],
            'message' => $message,
            'mentions_csv' => $mentions_csv,
            'mentioned_user_ids' => $mentionIds,
            'sent_at' => date('Y-m-d H:i:s')
        ];
        if ($pusher_app_id !== '' && $pusher_key !== '' && $pusher_secret !== '') {
            $eventBody = json_encode(['name' => 'message', 'channel' => 'private-chat.group.' . $group_id, 'data' => json_encode($payload)]);
            $body_md5 = md5($eventBody);
            $auth_timestamp = time();
            $params = ['auth_key' => $pusher_key, 'body_md5' => $body_md5, 'auth_timestamp' => $auth_timestamp, 'auth_version' => '1.0'];
            ksort($params);
            $path = '/apps/' . $pusher_app_id . '/events';
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $stringToSign = 'POST' . "\n" . $path . "\n" . $queryString;
            $signature = hash_hmac('sha256', $stringToSign, $pusher_secret);
            $url = 'https://api-' . $pusher_cluster . '.pusher.com' . $path . '?' . $queryString . '&auth_signature=' . $signature;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $eventBody);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_exec($ch);
            curl_close($ch);
        }
        $json(['status' => 'success', 'message_id' => (int) $insert_id, 'mentioned_user_ids' => $mentionIds]);
    }
    if ($_POST['action'] === 'send_message') {
        $recipient_id = isset($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        if ($recipient_id <= 0 || $message === '') {
            http_response_code(400);
            $json(['status' => 'error']);
        }
        $stmt = mysqli_prepare($con, "INSERT INTO chat_messages (sender_id, recipient_id, message, sent_at) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "iis", $user_id, $recipient_id, $message);
        mysqli_stmt_execute($stmt);
        $insert_id = mysqli_insert_id($con);
        $payload = [
            'id' => $insert_id,
            'sender_id' => $user_id,
            'recipient_id' => $recipient_id,
            'message' => $message,
            'sent_at' => date('Y-m-d H:i:s'),
            'is_read' => 0,
            'name' => $_SESSION['fullname'],
            'initials' => getInitials($_SESSION['fullname']),
            'timestamp' => time(),
            'to_user_id' => $recipient_id
        ];
        $eventBody = json_encode(['name' => 'message', 'channel' => 'private-chat.user.' . $recipient_id, 'data' => json_encode($payload)]);
        $body_md5 = md5($eventBody);
        $auth_timestamp = time();
        $params = ['auth_key' => $pusher_key, 'body_md5' => $body_md5, 'auth_timestamp' => $auth_timestamp, 'auth_version' => '1.0'];
        ksort($params);
        $path = '/apps/' . $pusher_app_id . '/events';
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $stringToSign = 'POST' . "\n" . $path . "\n" . $queryString;
        $signature = hash_hmac('sha256', $stringToSign, $pusher_secret);
        $url = 'https://api-' . $pusher_cluster . '.pusher.com' . $path . '?' . $queryString . '&auth_signature=' . $signature;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $eventBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
        $json(['status' => 'success', 'message_id' => $insert_id]);
    }
    if ($_POST['action'] === 'heartbeat') {
        $stmt = mysqli_prepare($con, "UPDATE users SET last_seen = NOW() WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $json(['status' => 'success']);
    }
    if ($_POST['action'] === 'mark_read') {
        $sender_id = isset($_POST['sender_id']) ? (int) $_POST['sender_id'] : 0;
        if ($sender_id > 0) {
            $stmt = mysqli_prepare($con, "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0");
            mysqli_stmt_bind_param($stmt, "ii", $sender_id, $user_id);
            mysqli_stmt_execute($stmt);
        }
        $json(['status' => 'success']);
    }
    if ($_POST['action'] === 'typing') {
        $recipient_id = isset($_POST['recipient_id']) ? (int) $_POST['recipient_id'] : 0;
        if ($recipient_id > 0) {
            $payload = ['from_user_id' => $user_id, 'timestamp' => time()];
            $eventBody = json_encode(['name' => 'typing', 'channel' => 'private-typing.user.' . $recipient_id, 'data' => json_encode($payload)]);
            $body_md5 = md5($eventBody);
            $auth_timestamp = time();
            $params = ['auth_key' => $pusher_key, 'body_md5' => $body_md5, 'auth_timestamp' => $auth_timestamp, 'auth_version' => '1.0'];
            ksort($params);
            $path = '/apps/' . $pusher_app_id . '/events';
            $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
            $stringToSign = 'POST' . "\n" . $path . "\n" . $queryString;
            $signature = hash_hmac('sha256', $stringToSign, $pusher_secret);
            $url = 'https://api-' . $pusher_cluster . '.pusher.com' . $path . '?' . $queryString . '&auth_signature=' . $signature;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $eventBody);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_exec($ch);
            curl_close($ch);
        }
        $json(['status' => 'success']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'get_group_messages') {
        ensureGroupChatTables($con);
        $group_id = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
        $last_id = isset($_GET['last_message_id']) ? (int) $_GET['last_message_id'] : 0;
        if ($group_id <= 0) {
            http_response_code(400);
            $json(['status' => 'error']);
        }
        $memberCheck = mysqli_query($con, "SELECT 1 FROM chat_group_members WHERE group_id = {$group_id} AND user_id = " . (int) $user_id . " LIMIT 1");
        if (!$memberCheck || !mysqli_fetch_assoc($memberCheck)) {
            http_response_code(403);
            $json(['status' => 'error']);
        }
        $stmt = mysqli_prepare($con, "SELECT m.id,m.group_id,m.sender_id,m.message,m.mentions_csv,m.sent_at,u.user_name AS sender_name FROM chat_group_messages m JOIN users u ON m.sender_id=u.user_id WHERE m.group_id=? AND m.id>? ORDER BY m.id ASC");
        mysqli_stmt_bind_param($stmt, "ii", $group_id, $last_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $messages = [];
        $maxId = $last_id;
        while ($row = mysqli_fetch_assoc($res)) {
            $row['mentioned_user_ids'] = parseMentionIdsFromCsv(isset($row['mentions_csv']) ? $row['mentions_csv'] : '');
            if ((int) $row['id'] > $maxId) {
                $maxId = (int) $row['id'];
            }
            $messages[] = $row;
        }
        if ($maxId > 0) {
            mysqli_query($con, "UPDATE chat_group_members SET last_read_message_id = GREATEST(last_read_message_id, {$maxId}) WHERE group_id = {$group_id} AND user_id = " . (int) $user_id);
        }
        $json(['status' => 'success', 'messages' => $messages, 'last_message_id' => $maxId]);
    }
    if ($_GET['action'] === 'get_messages') {
        $recipient_id = isset($_GET['recipient_id']) ? (int) $_GET['recipient_id'] : 0;
        $last_id = isset($_GET['last_message_id']) ? (int) $_GET['last_message_id'] : 0;
        $stmt = mysqli_prepare($con, "SELECT m.id,m.sender_id,m.recipient_id,m.message,m.sent_at,m.is_read,u.user_name AS sender_name FROM chat_messages m JOIN users u ON m.sender_id=u.user_id WHERE ((m.sender_id=? AND m.recipient_id=?) OR (m.sender_id=? AND m.recipient_id=?)) AND m.id>? ORDER BY m.sent_at ASC");
        mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $recipient_id, $recipient_id, $user_id, $last_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $messages = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $messages[] = $row;
        }
        if (!empty($messages)) {
            $stmt2 = mysqli_prepare($con, "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0");
            mysqli_stmt_bind_param($stmt2, "ii", $recipient_id, $user_id);
            mysqli_stmt_execute($stmt2);
        }
        $json(['status' => 'success', 'messages' => $messages]);
    }
    if ($_GET['action'] === 'get_chat_list') {
        ensureGroupChatTables($con);
        $stmt = mysqli_prepare($con, "SELECT DISTINCT CASE WHEN sender_id=? THEN recipient_id ELSE sender_id END AS chat_user_id FROM chat_messages WHERE sender_id=? OR recipient_id=?");
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $chat_user_ids = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $chat_user_ids[] = (int) $row['chat_user_id'];
        }
        $chat_list = [];
        foreach ($chat_user_ids as $cid) {
            $q = "SELECT u.user_id,u.user_name,u.user_pic,u.last_seen,(SELECT message FROM chat_messages WHERE (sender_id={$user_id} AND recipient_id={$cid}) OR (sender_id={$cid} AND recipient_id={$user_id}) ORDER BY sent_at DESC LIMIT 1) AS last_message,(SELECT sent_at FROM chat_messages WHERE (sender_id={$user_id} AND recipient_id={$cid}) OR (sender_id={$cid} AND recipient_id={$user_id}) ORDER BY sent_at DESC LIMIT 1) AS last_message_time,(SELECT COUNT(*) FROM chat_messages WHERE sender_id={$cid} AND recipient_id={$user_id} AND is_read=0) AS unread_count FROM users u WHERE u.user_id={$cid}";
            $r = mysqli_query($con, $q);
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $is_online = !empty($row['last_seen']) && (strtotime($row['last_seen']) > (time() - 60));
                $row['is_online'] = $is_online ? 1 : 0;
                $chat_list[] = $row;
            }
        }
        usort($chat_list, function ($a, $b) {
            if ($a['is_online'] === $b['is_online']) {
                return strcmp((string) $b['last_message_time'], (string) $a['last_message_time']);
            }
            return $b['is_online'] - $a['is_online'];
        });
        $users_res = mysqli_query($con, "SELECT user_id,user_name,user_pic,last_seen FROM users WHERE user_id <> " . (int) $user_id . " ORDER BY user_name");
        $all_users = [];
        while ($u = mysqli_fetch_assoc($users_res)) {
            $is_online = !empty($u['last_seen']) && (strtotime($u['last_seen']) > (time() - 60));
            $u['is_online'] = $is_online ? 1 : 0;
            $all_users[] = $u;
        }
        $groups = [];
        $groupSql = "SELECT g.id,g.group_name,g.product_topic,gm.last_read_message_id,
            (SELECT m2.message FROM chat_group_messages m2 WHERE m2.group_id=g.id ORDER BY m2.id DESC LIMIT 1) AS last_message,
            (SELECT m2.sent_at FROM chat_group_messages m2 WHERE m2.group_id=g.id ORDER BY m2.id DESC LIMIT 1) AS last_message_time,
            (SELECT COUNT(*) FROM chat_group_messages m3 WHERE m3.group_id=g.id AND m3.id > gm.last_read_message_id AND m3.sender_id <> " . (int) $user_id . ") AS unread_count,
            (SELECT COUNT(*) FROM chat_group_messages m4 WHERE m4.group_id=g.id AND m4.id > gm.last_read_message_id AND m4.sender_id <> " . (int) $user_id . " AND m4.mentions_csv LIKE '%," . (int) $user_id . ",%') AS mention_count
            FROM chat_group_members gm
            JOIN chat_groups g ON g.id = gm.group_id
            WHERE gm.user_id = " . (int) $user_id . "
            ORDER BY COALESCE(last_message_time, g.created_at) DESC";
        $groupRes = mysqli_query($con, $groupSql);
        while ($groupRes && $g = mysqli_fetch_assoc($groupRes)) {
            $gid = (int) $g['id'];
            $members = [];
            $mRes = mysqli_query($con, "SELECT u.user_id, COALESCE(u.user_name, u.user_login) AS user_name FROM chat_group_members gm JOIN users u ON u.user_id = gm.user_id WHERE gm.group_id = {$gid} ORDER BY user_name");
            while ($mRes && $m = mysqli_fetch_assoc($mRes)) {
                $members[] = ['user_id' => (int) $m['user_id'], 'user_name' => $m['user_name']];
            }
            $g['members'] = $members;
            $groups[] = $g;
        }
        $json(['status' => 'success', 'chat_list' => $chat_list, 'all_users' => $all_users, 'groups' => $groups]);
    }
}


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

$userSql = "SELECT user_id, COALESCE(user_name, user_login) AS name FROM users WHERE user_id <> " . (int) $user_id . " ORDER BY name";
$userRes = mysqli_query($con, $userSql);
if ($userRes) {
    while ($row = mysqli_fetch_assoc($userRes)) {
        $users[] = ['id' => (int) $row['user_id'], 'name' => $row['name']];
    }
}

?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
    <title>Syed Professional Services</title>
    <?php include "includes/links.php"; ?>


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
            <div class="card card-body">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Messenger</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Messenger</li>
                            </ul>
                        </div>

                    </div>
                </div>
                <div class="card card-body">
                    <style>
                        :root {
                            --bg: #0a1014;
                            --panel: #111b21;
                            --panel-2: #202c33;
                            --text: #e9edef;
                            --muted: #8696a0;
                            --accent: #25d366;
                            --bubble-me: #005c4b;
                            --bubble-me-text: #e9edef;
                            --bubble-other: #202c33;
                            --bubble-other-text: #e9edef;
                            --border: #202c33;
                            --shadow: rgba(0, 0, 0, 0.2);
                        }

                        .theme-light {
                            --bg: #f6f7f9;
                            --panel: #ffffff;
                            --panel-2: #f0f2f5;
                            --text: #1f2a33;
                            --muted: #6b7a88;
                            --accent: #19a04a;
                            --bubble-me: #d7f6e4;
                            --bubble-me-text: #1f2a33;
                            --bubble-other: #ffffff;
                            --bubble-other-text: #1f2a33;
                            --border: #e3e7ec;
                            --shadow: rgba(0, 0, 0, 0.08);
                        }

                        .messenger {
                            display: grid;
                            grid-template-columns: minmax(240px, 320px) 1fr;
                            gap: 12px;
                            min-height: 520px;
                            height: calc(100vh - 220px);
                            max-height: 820px;
                            width: 100%;
                            background: var(--bg)
                        }

                        .left {
                            border-radius: 6px;
                            overflow: hidden;
                            display: flex;
                            flex-direction: column;
                            min-height: 0;
                            background: var(--panel);
                            color: var(--text);
                            box-shadow: 0 6px 20px var(--shadow)
                        }

                        .search {
                            padding: 8px;
                            border-bottom: 1px solid var(--border)
                        }

                        .search-top {
                            display: flex;
                            gap: 8px;
                            margin-bottom: 8px
                        }

                        .search input {
                            width: 100%;
                            padding: 8px;
                            border-radius: 99px;
                            border: none;
                            background: var(--panel-2);
                            color: var(--text)
                        }

                        .search input:focus {
                            outline: none;
                            box-shadow: none
                        }

                        .list {
                            flex: 1;
                            overflow: auto
                        }

                        .item {
                            padding: 10px 12px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            border-bottom: 1px solid var(--border)
                        }

                        .item:hover {
                            background: var(--panel-2)
                        }

                        .item.active {
                            background: var(--panel-2)
                        }

                        .item .avatar {
                            width: 36px;
                            height: 36px;
                            border-radius: 50%;
                            background: #ccc;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            color: #fff;
                            font-size: 12px;
                            font-weight: 700
                        }

                        .item.group .avatar {
                            background: #157347
                        }

                        .item .avatar-img {
                            width: 36px;
                            height: 36px;
                            border-radius: 50%;
                            object-fit: cover;
                            display: inline-block
                        }

                        .item .meta {
                            flex: 1;
                            min-width: 0
                        }

                        .item .name {
                            font-weight: 600;
                            display: flex;
                            align-items: center;
                            gap: 6px;
                            font-size: 14px
                        }

                        .dot {
                            width: 8px;
                            height: 8px;
                            border-radius: 50%;
                            display: inline-block
                        }

                        .dot.online {
                            background: var(--accent)
                        }

                        .dot.offline {
                            background: var(--muted)
                        }

                        .item .preview {
                            font-size: 12px;
                            color: var(--muted);
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis
                        }

                        .item .badge {
                            background: var(--accent);
                            color: var(--panel);
                            border-radius: 10px;
                            padding: 0 6px;
                            font-size: 11px;
                            font-weight: 600
                        }

                        .item .badge.mention {
                            background: #ffcc00;
                            color: #222
                        }

                        .section-title {
                            padding: 6px 12px;
                            font-size: 11px;
                            font-weight: 700;
                            letter-spacing: .08em;
                            text-transform: uppercase;
                            color: var(--muted)
                        }

                        .right {
                            border-radius: 6px;
                            display: flex;
                            flex-direction: column;
                            min-height: 0;
                            overflow: hidden;
                            background: var(--bg);
                            box-shadow: 0 6px 20px var(--shadow)
                        }

                        .header {
                            padding: 10px 12px;
                            border-bottom: 1px solid var(--border);
                            display: flex;
                            align-items: center;
                            gap: 10px;
                            background: var(--panel-2);
                            color: var(--text)
                        }

                        .header .title {
                            font-weight: 600;
                            font-size: 15px
                        }

                        .header .avatar-img {
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            object-fit: cover
                        }

                        .header .presence {
                            font-size: 12px;
                            color: var(--muted)
                        }

                        .typing {
                            font-size: 12px;
                            color: var(--muted)
                        }

                        .messages {
                            flex: 1;
                            overflow-y: auto;
                            padding: 12px;
                            background: var(--bg);
                            min-height: 0
                        }

                        .bubble {
                            max-width: 70%;
                            margin-bottom: 8px;
                            padding: 8px 10px;
                            border-radius: 12px;
                            font-size: 14px;
                            line-height: 1.4;
                            word-wrap: break-word;
                            white-space: pre-wrap
                        }

                        .bubble.me {
                            margin-left: auto;
                            background: var(--bubble-me);
                            color: var(--bubble-me-text);
                            border-bottom-right-radius: 2px
                        }

                        .bubble.other {
                            margin-right: auto;
                            background: var(--bubble-other);
                            color: var(--bubble-other-text);
                            border-bottom-left-radius: 2px
                        }

                        .bubble .time {
                            font-size: 11px;
                            color: var(--muted);
                            margin-left: 8px
                        }

                        .bubble .sender {
                            display: block;
                            margin-bottom: 3px;
                            font-size: 11px;
                            font-weight: 700;
                            color: var(--muted)
                        }

                        .mention-pill {
                            background: #c7f0ff;
                            color: #0b3a4a;
                            border-radius: 4px;
                            padding: 1px 4px
                        }

                        .mention-pill.me {
                            background: #ffefae;
                            color: #5e4700
                        }

                        .composer {
                            padding: 10px;
                            border-top: 1px solid var(--border);
                            display: flex;
                            gap: 8px;
                            align-items: center;
                            background: var(--panel-2);
                            position: relative
                        }

                        .composer input {
                            flex: 1;
                            padding: 8px 10px;
                            border-radius: 99px;
                            border: none;
                            background: var(--panel);
                            color: var(--text)
                        }

                        .composer input:focus {
                            outline: none;
                            box-shadow: none
                        }

                        .emoji-btn {
                            padding: 0 8px;
                            border: none
                        }

                        .emoji-panel {
                            border-top: 1px solid var(--border);
                            padding: 6px 10px;
                            background: var(--panel-2);
                            display: flex;
                            flex-wrap: wrap;
                            gap: 4px;
                            max-height: 140px;
                            overflow: auto
                        }

                        .emoji-panel button {
                            border: none;
                            background: transparent;
                            cursor: pointer;
                            font-size: 18px;
                            line-height: 1.2
                        }

                        .theme-toggle {
                            margin-left: auto;
                            padding: 4px 10px;
                            border-radius: 99px;
                            border: 1px solid var(--border);
                            background: var(--panel);
                            color: var(--text);
                            font-size: 12px;
                            cursor: pointer
                        }

                        .mention-box {
                            position: absolute;
                            left: 56px;
                            right: 88px;
                            bottom: 56px;
                            max-height: 180px;
                            overflow: auto;
                            border-radius: 8px;
                            background: var(--panel);
                            border: 1px solid var(--border);
                            box-shadow: 0 6px 16px var(--shadow);
                            z-index: 12
                        }

                        .mention-item {
                            padding: 8px 10px;
                            cursor: pointer;
                            color: var(--text);
                            font-size: 13px
                        }

                        .mention-item:hover {
                            background: var(--panel-2)
                        }

                        .list::-webkit-scrollbar,
                        .messages::-webkit-scrollbar,
                        .emoji-panel::-webkit-scrollbar {
                            width: 10px
                        }

                        .list::-webkit-scrollbar-thumb,
                        .messages::-webkit-scrollbar-thumb,
                        .emoji-panel::-webkit-scrollbar-thumb {
                            background: var(--border);
                            border-radius: 8px
                        }

                        .list::-webkit-scrollbar-track,
                        .messages::-webkit-scrollbar-track,
                        .emoji-panel::-webkit-scrollbar-track {
                            background: transparent
                        }

                        @media (max-width: 768px) {
                            .messenger {
                                grid-template-columns: 1fr;
                                gap: 8px;
                                height: calc(100vh - 180px)
                            }

                            .left,
                            .right {
                                display: none
                            }

                            .messenger.mobile .left {
                                display: flex
                            }

                            .messenger.mobile.chat-open .left {
                                display: none
                            }

                            .messenger.mobile.chat-open .right {
                                display: flex
                            }

                            .item .avatar-img {
                                width: 28px;
                                height: 28px
                            }

                            .header .avatar-img {
                                width: 24px;
                                height: 24px
                            }

                            .header .title {
                                font-size: 14px
                            }

                            .messages {
                                padding: 8px
                            }

                            .composer input {
                                font-size: 14px
                            }
                        }
                    </style>
                    <div class="messenger" id="messenger">
                        <div class="left">
                            <div class="search">
                                <div class="search-top">
                                    <button type="button" class="btn btn-success btn-sm" id="create-group-btn">New Product Group</button>
                                </div>
                                <input type="text" id="search" placeholder="Search people or groups">
                            </div>
                            <div class="list" id="chat-list"></div>
                        </div>
                        <div class="right">
                            <div class="header">
                                <button class="btn btn-light btn-sm" id="back-btn" style="display:none">Back</button>
                                <img class="avatar-img" id="chat-avatar" src="images/default-user.png" alt=""
                                    style="display:none">
                                <span class="title" id="chat-title">Select a conversation</span>
                                <span class="dot offline" id="chat-status"></span>
                                <span class="presence" id="presence-text"></span>
                                <span class="typing" id="typing-indicator" style="display:none">typing...</span>
                                <button type="button" class="theme-toggle" id="theme-toggle">Light</button>
                            </div>
                            <div class="messages" id="messages"></div>
                            <div class="composer">
                                <button type="button" class="btn btn-light emoji-btn" id="emoji-btn">😊</button>
                                <input type="text" id="message-input" placeholder="Type a message (@name in groups)">
                                <button class="btn btn-success" id="send-btn">Send</button>
                                <div class="mention-box" id="mention-box" style="display:none"></div>
                            </div>
                            <div class="emoji-panel" id="emoji-panel" style="display:none"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <script src="https://js.pusher.com/8.2/pusher.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            var PUSHER_KEY = <?php echo json_encode($pusher_key); ?>;
            var PUSHER_CLUSTER = <?php echo json_encode($pusher_cluster); ?>;
            var currentUser = <?php echo json_encode(['id' => $user_id, 'name' => $user_full_name, 'initials' => $user_initials]); ?>;
            var usersForMention = <?php echo json_encode($users); ?>;
            var authUrl = window.location.pathname + '?action=pusher_auth';
            var baseUrl = window.location.pathname;
            var pusher = new Pusher(PUSHER_KEY, { cluster: PUSHER_CLUSTER, forceTLS: true, authEndpoint: authUrl });
            window.pusher = pusher;
            var dmChannel = pusher.subscribe('private-chat.user.' + String(currentUser.id));
            var typingChannel = pusher.subscribe('private-typing.user.' + String(currentUser.id));
            var groupChannels = {};

            document.addEventListener('DOMContentLoaded', function () {
                var selectedUserId = null;
                var selectedGroup = null;
                var lastMessageId = 0;
                var selectedUserName = '';
                var selectedUserOnline = 0;
                var selectedUserLastSeen = null;
                var messageIds = {};
                var isSending = false;
                function escapeHtml(str) {
                    return String(str || '').replace(/[&<>"']/g, function (s) {
                        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[s];
                    });
                }
                function escapeRegExp(str) {
                    return String(str || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                }
                function getMentionNameById(uid) {
                    if (selectedGroup && selectedGroup.members) {
                        var inGroup = selectedGroup.members.find(function (m) { return Number(m.user_id) === Number(uid); });
                        if (inGroup) return inGroup.user_name || '';
                    }
                    var fromAll = usersForMention.find(function (u) { return Number(u.id) === Number(uid); });
                    return fromAll ? fromAll.name : '';
                }
                function hideMentionBox() {
                    var box = document.getElementById('mention-box');
                    if (!box) return;
                    box.style.display = 'none';
                    box.innerHTML = '';
                }
                var EMOJIS = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😍', '😘', '😜', '🤔', '🙄', '😎', '😢', '😭', '😡', '😴', '😪'];
                function applyTheme(mode) {
                    var body = document.body;
                    if (mode === 'light') {
                        body.classList.add('theme-light');
                    } else {
                        body.classList.remove('theme-light');
                    }
                    var btn = document.getElementById('theme-toggle');
                    if (btn) btn.textContent = mode === 'light' ? 'Dark' : 'Light';
                    localStorage.setItem('chatTheme', mode);
                }
                (function initTheme() {
                    var saved = localStorage.getItem('chatTheme');
                    var mode = saved === 'light' ? 'light' : 'dark';
                    applyTheme(mode);
                })();
                function setMobileMode() {
                    var m = document.getElementById('messenger');
                    var back = document.getElementById('back-btn');
                    if (window.innerWidth <= 768) {
                        m.classList.add('mobile');
                        if (back) back.style.display = 'inline-block';
                        if (!selectedUserId) {
                            m.classList.remove('chat-open');
                        }
                    } else {
                        m.classList.remove('mobile');
                        m.classList.remove('chat-open');
                        if (back) back.style.display = 'none';
                    }
                }
                if (window && window.addEventListener) window.addEventListener('resize', setMobileMode);
                setMobileMode();
                function renderChatList(data) {
                    var list = document.getElementById('chat-list');
                    list.innerHTML = '';
                    var search = document.getElementById('search').value.toLowerCase();
                    function buildAvatarSrc(pic) {
                        if (!pic || String(pic).toLowerCase() === 'default.png') return 'images/default-user.png';
                        return 'uploads/staff_images/' + String(pic);
                    }
                    function addSection(title) {
                        var sec = document.createElement('div');
                        sec.className = 'section-title';
                        sec.textContent = title;
                        list.appendChild(sec);
                    }
                    function addItem(u, preview, unread) {
                        if (search && String(u.user_name).toLowerCase().indexOf(search) === -1) return;
                        var item = document.createElement('div');
                        item.className = 'item' + (!selectedGroup && Number(selectedUserId) === Number(u.user_id) ? ' active' : '');
                        var avatar = document.createElement('img');
                        avatar.className = 'avatar-img';
                        avatar.alt = '';
                        avatar.src = buildAvatarSrc(u.user_pic);
                        avatar.onerror = function () { this.src = 'images/default-user.png'; };
                        var meta = document.createElement('div');
                        meta.className = 'meta';
                        var name = document.createElement('div');
                        name.className = 'name';
                        var status = document.createElement('span');
                        status.className = 'dot ' + (u.is_online ? 'online' : 'offline');
                        name.textContent = u.user_name || ('User #' + u.user_id);
                        name.appendChild(status);
                        var prev = document.createElement('div');
                        prev.className = 'preview';
                        prev.textContent = preview || '';
                        meta.appendChild(name);
                        meta.appendChild(prev);
                        var badge = document.createElement('span');
                        if (unread && Number(unread) > 0) {
                            badge.className = 'badge';
                            badge.textContent = unread;
                        }
                        item.appendChild(avatar);
                        item.appendChild(meta);
                        if (badge.className) item.appendChild(badge);
                        if (item && item.addEventListener) item.addEventListener('click', function () {
                            openConversation(u.user_id, u.user_name, u.is_online, u.last_seen || '', u.user_pic || '');
                        });
                        list.appendChild(item);
                    }
                    function addGroupItem(g) {
                        var gName = g.group_name || ('Group #' + g.id);
                        var gTopic = g.product_topic || '';
                        if (search && (gName + ' ' + gTopic).toLowerCase().indexOf(search) === -1)
                            return;
                        var item = document.createElement('div');
                        item.className = 'item group' + (selectedGroup && Number(selectedGroup.id) === Number(g.id) ? ' active' : '');
                        var avatar = document.createElement('div');
                        avatar.className = 'avatar';
                        avatar.textContent = '#';
                        var meta = document.createElement('div');
                        meta.className = 'meta';
                        var name = document.createElement('div');
                        name.className = 'name';
                        name.textContent = gName;
                        var prev = document.createElement('div');
                        prev.className = 'preview';
                        prev.textContent = g.last_message || (gTopic ? ('Topic: ' + gTopic) : 'No messages yet');
                        meta.appendChild(name);
                        meta.appendChild(prev);
                        item.appendChild(avatar);
                        item.appendChild(meta);
                        if (Number(g.mention_count || 0) > 0) {
                            var mBadge = document.createElement('span');
                            mBadge.className = 'badge mention';
                            mBadge.textContent = '@' + String(g.mention_count);
                            item.appendChild(mBadge);
                        } else if (Number(g.unread_count || 0) > 0) {
                            var uBadge = document.createElement('span');
                            uBadge.className = 'badge';
                            uBadge.textContent = g.unread_count;
                            item.appendChild(uBadge);
                        }
                        item.addEventListener('click', function () { openGroupConversation(g); });
                        list.appendChild(item);
                    }
                    var groups = Array.isArray(data.groups) ? data.groups : [];
                    if (groups.length) {
                        addSection('Product Groups');
                        groups.forEach(addGroupItem);
                        groups.forEach(function (g) {
                            var gid = Number(g.id);
                            if (!gid || groupChannels[gid]) return;
                            var ch = pusher.subscribe('private-chat.group.' + String(gid));
                            ch.bind('message', function (payload) {
                                var msg = typeof payload === 'string' ? JSON.parse(payload) : payload;
                                if (selectedGroup && Number(selectedGroup.id) === Number(msg.group_id || gid)) {
                                    addBubble(msg);
                                }
                                fetchChatList();
                            });
                            groupChannels[gid] = ch;
                        });
                    }
                    addSection('Direct Messages');
                    data.chat_list.forEach(function (c) {
                        addItem(c, c.last_message, c.unread_count);
                    });
                    data.all_users.forEach(function (u) {
                        var inList = data.chat_list.find(function (c) { return Number(c.user_id || c.chat_user_id) === Number(u.user_id); });
                        if (!inList) addItem(u, '', 0);
                    });
                    if (selectedGroup) {
                        var currentGroup = groups.find(function (g) { return Number(g.id) === Number(selectedGroup.id); });
                        if (currentGroup) {
                            selectedGroup.members = currentGroup.members || [];
                            selectedGroup.product_topic = currentGroup.product_topic || '';
                            updateHeaderPresence();
                        }
                    } else if (selectedUserId) {
                        var current = data.chat_list.find(function (c) { return Number(c.user_id) === Number(selectedUserId) || Number(c.chat_user_id) === Number(selectedUserId); });
                        if (!current) current = data.all_users.find(function (u) { return Number(u.user_id) === Number(selectedUserId); });
                        if (current) {
                            selectedUserOnline = current.is_online ? 1 : 0;
                            selectedUserLastSeen = current.last_seen || '';
                            updateHeaderPresence();
                        }
                    }
                }
                function formatLastSeen(ts) {
                    if (!ts) return '';
                    var d = new Date(ts.replace(' ', 'T'));
                    var now = new Date();
                    var diff = Math.floor((now - d) / 60000);
                    if (diff < 1) return 'last seen just now';
                    if (diff < 60) return 'last seen ' + diff + 'm ago';
                    var hours = Math.floor(diff / 60);
                    if (hours < 24) return 'last seen ' + hours + 'h ago';
                    return 'last seen ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
                }
                function updateHeaderPresence() {
                    var statusEl = document.getElementById('chat-status');
                    var presenceEl = document.getElementById('presence-text');
                    if (selectedGroup) {
                        statusEl.className = 'dot online';
                        var memberCount = selectedGroup.members ? selectedGroup.members.length : 0;
                        presenceEl.textContent = (selectedGroup.product_topic ? (selectedGroup.product_topic + ' · ') : '') + memberCount + ' members';
                        return;
                    }
                    statusEl.className = 'dot ' + (selectedUserOnline ? 'online' : 'offline');
                    presenceEl.textContent = selectedUserOnline ? 'Online' : formatLastSeen(selectedUserLastSeen);
                }
                function openConversation(id, name, online, lastSeen, pic) {
                    selectedGroup = null;
                    selectedUserId = id;
                    selectedUserName = name || ('User #' + id);
                    selectedUserOnline = online ? 1 : 0;
                    selectedUserLastSeen = lastSeen || '';
                    lastMessageId = 0;
                    messageIds = {};
                    document.getElementById('chat-title').textContent = selectedUserName;
                    updateHeaderPresence();
                    var headerAvatar = document.getElementById('chat-avatar');
                    headerAvatar.src = (function (p) { if (!p || String(p).toLowerCase() === 'default.png') return 'images/default-user.png'; return 'uploads/staff_images/' + String(p); })(pic);
                    headerAvatar.onerror = function () { this.src = 'images/default-user.png'; };
                    headerAvatar.style.display = 'inline-block';
                    document.getElementById('messages').innerHTML = '';
                    fetchMessages();
                    markRead(id);
                    var m = document.getElementById('messenger');
                    if (m.classList.contains('mobile')) {
                        m.classList.add('chat-open');
                    }
                }
                function openGroupConversation(g) {
                    selectedGroup = g;
                    selectedUserId = 'group_' + String(g.id);
                    selectedUserName = g.group_name || ('Group #' + g.id);
                    selectedUserOnline = 1;
                    selectedUserLastSeen = '';
                    lastMessageId = 0;
                    messageIds = {};
                    document.getElementById('chat-title').textContent = selectedUserName;
                    updateHeaderPresence();
                    document.getElementById('chat-avatar').style.display = 'none';
                    document.getElementById('messages').innerHTML = '';
                    fetchMessages();
                    var m = document.getElementById('messenger');
                    if (m.classList.contains('mobile')) {
                        m.classList.add('chat-open');
                    }
                }
                function addBubble(m) {
                    if (m.client_id && messageIds[m.client_id]) return;
                    if (m.id && messageIds[m.id]) return;
                    if (m.client_id) messageIds[m.client_id] = true;
                    if (m.id) messageIds[m.id] = true;
                    var wrap = document.getElementById('messages');
                    var div = document.createElement('div');
                    var me = Number(m.sender_id) === Number(currentUser.id);
                    div.className = 'bubble ' + (me ? 'me' : 'other');
                    if (m.client_id) div.dataset.clientId = m.client_id;
                    if (m.id) div.dataset.msgId = m.id;
                    var tsRaw = m.timestamp ? new Date(m.timestamp * 1000) : (m.sent_at ? new Date((m.sent_at + '').replace(' ', 'T')) : new Date());
                    var timeStr = tsRaw.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                    var msgText = (m.message || m.text || '');
                    var mentionIds = Array.isArray(m.mentioned_user_ids) ? m.mentioned_user_ids : [];
                    var msgHtml = escapeHtml(msgText);
                    mentionIds.forEach(function (uid) {
                        var uname = getMentionNameById(uid);
                        if (!uname) return;
                        var cls = Number(uid) === Number(currentUser.id) ? 'mention-pill me' : 'mention-pill';
                        var re = new RegExp('@' + escapeRegExp(uname), 'g');
                        msgHtml = msgHtml.replace(re, '<span class="' + cls + '">@' + escapeHtml(uname) + '</span>');
                    });
                    if (selectedGroup && !me) {
                        var senderName = m.sender_name || m.name || ('User #' + String(m.sender_id || ''));
                        msgHtml = '<span class="sender">' + escapeHtml(senderName) + '</span>' + msgHtml;
                    }
                    div.innerHTML = msgHtml;
                    var spanTime = document.createElement('span');
                    spanTime.className = 'time';
                    spanTime.textContent = timeStr;
                    div.appendChild(spanTime);
                    wrap.appendChild(div);
                    wrap.scrollTop = wrap.scrollHeight;
                    if (m.id && m.id > lastMessageId) lastMessageId = m.id;
                }
                function fetchChatList() {
                    fetch(baseUrl + '?action=get_chat_list').then(function (r) { return r.json(); }).then(function (d) { if (d.status === 'success') { renderChatList(d); } });
                }
                function fetchMessages() {
                    if (!selectedUserId) return;
                    var url = selectedGroup
                        ? (baseUrl + '?action=get_group_messages&group_id=' + encodeURIComponent(selectedGroup.id) + '&last_message_id=' + String(lastMessageId))
                        : (baseUrl + '?action=get_messages&recipient_id=' + encodeURIComponent(selectedUserId) + '&last_message_id=' + String(lastMessageId));
                    fetch(url)
                        .then(function (r) { return r.json(); })
                        .then(function (d) { if (d.status === 'success') { d.messages.forEach(addBubble); } });
                }
                function sendMessage() {
                    if (!selectedUserId || isSending) return;
                    var input = document.getElementById('message-input');
                    var text = input.value.trim();
                    if (!text) return;
                    isSending = true;
                    var clientId = 'c_' + Date.now() + '_' + Math.floor(Math.random() * 1000000);
                    var mentionIds = [];
                    if (selectedGroup && selectedGroup.members) {
                        selectedGroup.members.forEach(function (m) {
                            var name = String(m.user_name || '').trim();
                            if (!name) return;
                            var re = new RegExp('(^|\\s)@' + escapeRegExp(name) + '(?=\\s|$|[.,!?])', 'i');
                            if (re.test(text)) mentionIds.push(Number(m.user_id));
                        });
                    }
                    addBubble({ sender_id: currentUser.id, sender_name: currentUser.name, message: text, sent_at: new Date().toISOString(), client_id: clientId, mentioned_user_ids: mentionIds });
                    var body = new URLSearchParams();
                    if (selectedGroup) {
                        body.set('action', 'send_group_message');
                        body.set('group_id', String(selectedGroup.id));
                        body.set('mentioned_user_ids', mentionIds.join(','));
                    } else {
                        body.set('action', 'send_message');
                        body.set('recipient_id', String(selectedUserId));
                    }
                    body.set('message', text);
                    fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.status === 'success') {
                                messageIds[d.message_id] = true;
                                var temp = document.querySelector('.bubble[data-client-id="' + clientId + '"]');
                                if (temp) temp.dataset.msgId = d.message_id;
                                document.getElementById('message-input').value = '';
                                hideMentionBox();
                                fetchChatList();
                            } else {
                                var tempFail = document.querySelector('.bubble[data-client-id="' + clientId + '"]');
                                if (tempFail) tempFail.remove();
                            }
                            isSending = false;
                        })
                        .catch(function () {
                            var tempErr = document.querySelector('.bubble[data-client-id="' + clientId + '"]');
                            if (tempErr) tempErr.remove();
                            isSending = false;
                        });
                }
                function markRead(senderId) {
                    if (selectedGroup) return;
                    var body = new URLSearchParams();
                    body.set('action', 'mark_read');
                    body.set('sender_id', String(senderId));
                    fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                }
                function heartbeat() {
                    var body = new URLSearchParams();
                    body.set('action', 'heartbeat');
                    fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                }
                var typingTimeout = null;
                function sendTyping() {
                    if (!selectedUserId || selectedGroup) return;
                    var body = new URLSearchParams();
                    body.set('action', 'typing');
                    body.set('recipient_id', String(selectedUserId));
                    fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                }
                var sendBtn = document.getElementById('send-btn');
                if (sendBtn) sendBtn.addEventListener('click', sendMessage);
                var themeBtn = document.getElementById('theme-toggle');
                if (themeBtn) themeBtn.addEventListener('click', function () {
                    var isLight = document.body.classList.contains('theme-light');
                    applyTheme(isLight ? 'dark' : 'light');
                });
                var backBtn = document.getElementById('back-btn');
                if (backBtn) backBtn.addEventListener('click', function () {
                    var m = document.getElementById('messenger');
                    m.classList.remove('chat-open');
                    selectedUserId = null;
                    selectedGroup = null;
                    document.getElementById('chat-title').textContent = 'Select a conversation';
                    document.getElementById('chat-status').className = 'dot offline';
                    document.getElementById('presence-text').textContent = '';
                    document.getElementById('chat-avatar').style.display = 'none';
                    document.getElementById('messages').innerHTML = '';
                    hideMentionBox();
                });
                var msgInput = document.getElementById('message-input');
                if (msgInput) msgInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                        return;
                    }
                    if (typingTimeout) return;
                    sendTyping();
                    typingTimeout = setTimeout(function () { typingTimeout = null; }, 2000);
                });
                function showMentionSuggestions() {
                    if (!selectedGroup) {
                        hideMentionBox();
                        return;
                    }
                    var input = document.getElementById('message-input');
                    var box = document.getElementById('mention-box');
                    if (!input || !box) return;
                    var pos = input.selectionStart || 0;
                    var before = input.value.slice(0, pos);
                    var match = before.match(/(?:^|\s)@([A-Za-z0-9_.\-\s]*)$/);
                    if (!match) {
                        hideMentionBox();
                        return;
                    }
                    var term = String(match[1] || '').trim().toLowerCase();
                    var members = (selectedGroup.members || []).filter(function (m) {
                        var n = String(m.user_name || '').toLowerCase();
                        if (!n || Number(m.user_id) === Number(currentUser.id)) return false;
                        if (!term) return true;
                        return n.indexOf(term) !== -1;
                    }).slice(0, 8);
                    if (!members.length) {
                        hideMentionBox();
                        return;
                    }
                    box.innerHTML = '';
                    members.forEach(function (m) {
                        var row = document.createElement('div');
                        row.className = 'mention-item';
                        row.textContent = '@' + m.user_name;
                        row.addEventListener('click', function () {
                            var atStart = before.lastIndexOf('@');
                            var after = input.value.slice(pos);
                            input.value = input.value.slice(0, atStart) + '@' + m.user_name + ' ' + after;
                            input.focus();
                            var caret = atStart + m.user_name.length + 2;
                            if (input.setSelectionRange) input.setSelectionRange(caret, caret);
                            hideMentionBox();
                        });
                        box.appendChild(row);
                    });
                    box.style.display = 'block';
                }
                if (msgInput) msgInput.addEventListener('input', showMentionSuggestions);
                document.addEventListener('click', function (e) {
                    var box = document.getElementById('mention-box');
                    if (!box) return;
                    if (e.target.id === 'message-input') return;
                    if (box.contains(e.target)) return;
                    hideMentionBox();
                });
                var searchInput = document.getElementById('search');
                if (searchInput) searchInput.addEventListener('input', fetchChatList);
                function openCreateGroupModal() {
                    if (typeof Swal === 'undefined') {
                        alert('SweetAlert is not loaded. Please refresh and try again.');
                        return;
                    }
                    function initials(name) {
                        var parts = String(name || '').trim().split(/\s+/);
                        var out = '';
                        for (var i = 0; i < parts.length; i++) {
                            if (!parts[i]) continue;
                            out += parts[i].charAt(0).toUpperCase();
                            if (out.length >= 2) break;
                        }
                        return out || 'U';
                    }
                    var sortedUsers = (usersForMention || []).slice().sort(function (a, b) {
                        return String(a.name || '').localeCompare(String(b.name || ''));
                    });
                    var memberRowsHtml = sortedUsers.map(function (u) {
                        var nm = String(u.name || ('User #' + String(u.id)));
                        return ''
                            + '<label class="sw-user-row" data-name="' + escapeHtml(nm.toLowerCase()) + '">'
                            + '<input type="checkbox" class="sw-user-check" value="' + String(u.id) + '">'
                            + '<span class="sw-user-avatar">' + escapeHtml(initials(nm)) + '</span>'
                            + '<span class="sw-user-name">' + escapeHtml(nm) + '</span>'
                            + '</label>';
                    }).join('');
                    Swal.fire({
                        title: 'Create Product Group',
                        width: 700,
                        customClass: { popup: 'sw-group-popup' },
                        html:
                            '<div class="sw-group-wrap">' +
                            '<input id="swal-group-name" class="swal2-input sw-field" placeholder="Group name (e.g. Payroll Tax Team)">' +
                            '<input id="swal-group-topic" class="swal2-input sw-field" placeholder="Product topic (e.g. Payroll Tax)">' +
                            '<div class="sw-user-toolbar">' +
                            '<input id="swal-member-search" class="swal2-input sw-search" placeholder="Search members...">' +
                            '<div class="sw-user-actions">' +
                            '<button type="button" id="swal-select-all" class="swal2-styled sw-btn sw-btn-green">Select All</button>' +
                            '<button type="button" id="swal-clear-all" class="swal2-styled sw-btn sw-btn-gray">Clear</button>' +
                            '</div>' +
                            '</div>' +
                            '<div class="sw-count" id="swal-selected-count">0 selected</div>' +
                            '<div class="sw-user-list" id="swal-user-list">' + memberRowsHtml + '</div>' +
                            '</div>',
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Create Group',
                        confirmButtonColor: '#198754',
                        didOpen: function () {
                            if (!document.getElementById('sw-group-style')) {
                                var style = document.createElement('style');
                                style.id = 'sw-group-style';
                                style.textContent =
                                    '.sw-group-popup .sw-group-wrap{text-align:left;padding:0 8px}' +
                                    '.sw-group-popup .sw-field{width:100%;margin:.4em 0}' +
                                    '.sw-group-popup .sw-user-toolbar{display:flex;gap:8px;align-items:center;margin-top:8px}' +
                                    '.sw-group-popup .sw-search{margin:0;flex:1;height:40px}' +
                                    '.sw-group-popup .sw-user-actions{display:flex;gap:6px}' +
                                    '.sw-group-popup .sw-btn{margin:0;padding:7px 10px;font-size:12px}' +
                                    '.sw-group-popup .sw-btn-green{background:#198754}' +
                                    '.sw-group-popup .sw-btn-gray{background:#6c757d}' +
                                    '.sw-group-popup .sw-count{font-size:12px;color:#6b7280;margin:8px 2px}' +
                                    '.sw-group-popup .sw-user-list{max-height:240px;overflow:auto;border:1px solid #e5e7eb;border-radius:10px;padding:4px;background:#fff}' +
                                    '.sw-group-popup .sw-user-row{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:8px;cursor:pointer;margin:2px 0}' +
                                    '.sw-group-popup .sw-user-row:hover{background:#f4f6f8}' +
                                    '.sw-group-popup .sw-user-avatar{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#198754;color:#fff;font-size:11px;font-weight:700}' +
                                    '.sw-group-popup .sw-user-name{font-size:13px;color:#1f2937}' +
                                    '.sw-group-popup .sw-user-row[hidden]{display:none !important}';
                                document.head.appendChild(style);
                            }
                            var nameEl = document.getElementById('swal-group-name');
                            var searchEl = document.getElementById('swal-member-search');
                            var listEl = document.getElementById('swal-user-list');
                            var countEl = document.getElementById('swal-selected-count');
                            var allBtn = document.getElementById('swal-select-all');
                            var clearBtn = document.getElementById('swal-clear-all');
                            var checks = listEl ? listEl.querySelectorAll('.sw-user-check') : [];
                            var rows = listEl ? listEl.querySelectorAll('.sw-user-row') : [];
                            function selectedCount() {
                                var c = 0;
                                Array.prototype.forEach.call(checks, function (ch) { if (ch.checked) c++; });
                                return c;
                            }
                            function updateState() {
                                var c = selectedCount();
                                if (countEl) countEl.textContent = c + ' selected';
                                var confirmBtn = Swal.getConfirmButton();
                                if (confirmBtn) {
                                    var validName = nameEl && String(nameEl.value || '').trim().length > 0;
                                    confirmBtn.disabled = !(validName && c > 0);
                                }
                            }
                            if (nameEl) nameEl.addEventListener('input', updateState);
                            Array.prototype.forEach.call(checks, function (ch) {
                                ch.addEventListener('change', updateState);
                            });
                            if (searchEl) {
                                searchEl.addEventListener('input', function () {
                                    var q = String(searchEl.value || '').trim().toLowerCase();
                                    Array.prototype.forEach.call(rows, function (row) {
                                        var n = row.getAttribute('data-name') || '';
                                        row.hidden = q ? n.indexOf(q) === -1 : false;
                                    });
                                });
                            }
                            if (allBtn) {
                                allBtn.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    Array.prototype.forEach.call(checks, function (ch) { if (!ch.closest('.sw-user-row').hidden) ch.checked = true; });
                                    updateState();
                                });
                            }
                            if (clearBtn) {
                                clearBtn.addEventListener('click', function (e) {
                                    e.preventDefault();
                                    Array.prototype.forEach.call(checks, function (ch) { ch.checked = false; });
                                    updateState();
                                });
                            }
                            updateState();
                        },
                        preConfirm: function () {
                            var nameEl = document.getElementById('swal-group-name');
                            var topicEl = document.getElementById('swal-group-topic');
                            var name = nameEl ? String(nameEl.value || '').trim() : '';
                            var topic = topicEl ? String(topicEl.value || '').trim() : '';
                            var memberIds = [];
                            var checks = document.querySelectorAll('.sw-user-check:checked');
                            if (checks && checks.length) {
                                Array.prototype.forEach.call(checks, function (ch) { memberIds.push(ch.value); });
                            }
                            if (!name) {
                                Swal.showValidationMessage('Group name is required.');
                                return false;
                            }
                            if (!memberIds.length) {
                                Swal.showValidationMessage('Please select at least one member.');
                                return false;
                            }
                            return { name: name, topic: topic, memberIds: memberIds };
                        }
                    }).then(function (res) {
                        if (!res.isConfirmed || !res.value) return;
                        var body = new URLSearchParams();
                        body.set('action', 'create_group');
                        body.set('group_name', res.value.name);
                        body.set('product_topic', res.value.topic || '');
                        body.set('member_ids', res.value.memberIds.join(','));
                        fetch(baseUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (d.status === 'success') {
                                    fetchChatList();
                                    Swal.fire({ icon: 'success', title: 'Group created', timer: 1300, showConfirmButton: false });
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Unable to create group', text: d.message || 'Please try again.' });
                                }
                            })
                            .catch(function () {
                                Swal.fire({ icon: 'error', title: 'Unable to create group', text: 'Network/server error.' });
                            });
                    });
                }
                var createGroupBtn = document.getElementById('create-group-btn');
                if (createGroupBtn) createGroupBtn.addEventListener('click', openCreateGroupModal);
                setInterval(heartbeat, 20000);
                setInterval(fetchChatList, 10000);
                setInterval(fetchMessages, 4000);
                fetchChatList();
                dmChannel.bind('message', function (data) {
                    try {
                        var m = typeof data === 'string' ? JSON.parse(data) : data;
                        var fromId = m.sender_id || m.user_id;
                        if (selectedUserId && Number(fromId) === Number(selectedUserId)) {
                            addBubble(m);
                            markRead(selectedUserId);
                        }
                        fetchChatList();
                    } catch (e) { }
                });
                typingChannel.bind('typing', function (data) {
                    var d = typeof data === 'string' ? JSON.parse(data) : data;
                    if (selectedUserId && Number(d.from_user_id) === Number(selectedUserId)) {
                        var el = document.getElementById('typing-indicator');
                        el.style.display = 'inline';
                        setTimeout(function () { el.style.display = 'none'; }, 1500);
                    }
                });
                (function initEmojiPicker() {
                    var panel = document.getElementById('emoji-panel');
                    var btn = document.getElementById('emoji-btn');
                    var input = document.getElementById('message-input');
                    if (!panel || !btn || !input) return;
                    EMOJIS.forEach(function (ch) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.textContent = ch;
                        if (b && b.addEventListener) b.addEventListener('click', function () {
                            input.value += ch;
                            input.focus();
                        });
                        panel.appendChild(b);
                    });
                    if (btn) btn.addEventListener('click', function () {
                        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
                    });
                })();
            });
        </script>
        <?php include "includes/scripts.php"; ?>
        <?php include "includes/scripts.php"; ?>
</body>
