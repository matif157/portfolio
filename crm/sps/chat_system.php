<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

// Get user_id and user_type
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 0;

// Handle chat operations
if (isset($_POST['action']) && $_POST['action'] == 'send_message') {
   $recipient_id = $_POST['recipient_id'];
   $message = $_POST['message'];

   $query = "INSERT INTO chat_messages (sender_id, recipient_id, message, sent_at) VALUES (?, ?, ?, NOW())";
   $stmt = mysqli_prepare($con, $query);
   mysqli_stmt_bind_param($stmt, "iis", $user_id, $recipient_id, $message);
   mysqli_stmt_execute($stmt);

   echo json_encode(['status' => 'success']);
   exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
   $update_query = "UPDATE users SET last_seen = NOW() WHERE user_id = ?";
   $update_stmt = mysqli_prepare($con, $update_query);
   mysqli_stmt_bind_param($update_stmt, "i", $user_id);
   mysqli_stmt_execute($update_stmt);

   echo json_encode(['status' => 'success']);
   exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'heartbeat') {
   $update_query = "UPDATE users SET last_seen = NOW() WHERE user_id = ?";
   $update_stmt = mysqli_prepare($con, $update_query);
   mysqli_stmt_bind_param($update_stmt, "i", $user_id);
   mysqli_stmt_execute($update_stmt);

   $still_logged_in = isset($_SESSION['user_id']);

   echo json_encode([
      'status' => 'success',
      'timestamp' => date('Y-m-d H:i:s'),
      'logged_in' => $still_logged_in
   ]);
   exit;
}

// === SOS ALERT: Admin sends emergency message to ALL users ===
if (isset($_POST['action']) && $_POST['action'] === 'send_sos') {
   if ($user_type != 1) {
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Admin only']);
      exit;
   }

   $message = trim($_POST['message'] ?? '');
   if (empty($message)) {
      echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
      exit;
   }
  
   // Get admin name
   $name_query = "SELECT user_name FROM users WHERE user_id = ?";
   $name_stmt = mysqli_prepare($con, $name_query);
   mysqli_stmt_bind_param($name_stmt, "i", $user_id);
   mysqli_stmt_execute($name_stmt);
   mysqli_stmt_bind_result($name_stmt, $admin_name);
   $admin_name = mysqli_stmt_fetch($name_stmt) ? $admin_name : 'Admin';
   mysqli_stmt_close($name_stmt);

   // Insert SOS alert
   $insert_query = "INSERT INTO sos_alerts (message, sent_by, sent_by_name) VALUES (?, ?, ?)";
   $insert_stmt = mysqli_prepare($con, $insert_query);
   mysqli_stmt_bind_param($insert_stmt, "sis", $message, $user_id, $admin_name);
   mysqli_stmt_execute($insert_stmt);

   echo json_encode(['status' => 'success', 'message' => 'SOS Alert sent to all users']);
   exit;
}

// === SOS ALERT: Get latest active alert ===
if (isset($_GET['action']) && $_GET['action'] === 'get_latest_sos') {
   $query = "SELECT id, message, sent_by_name, sent_at 
              FROM sos_alerts 
              WHERE is_active = 1 
              ORDER BY sent_at DESC 
              LIMIT 1";

   $result = mysqli_query($con, $query);

   if (mysqli_num_rows($result) > 0) {
      $alert = mysqli_fetch_assoc($result);
      echo json_encode(['status' => 'success', 'alert' => $alert]);
   } else {
      echo json_encode(['status' => 'success', 'alert' => null]);
   }
   exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_messages') {
   $recipient_id = $_GET['recipient_id'];
   $last_message_id = isset($_GET['last_message_id']) ? $_GET['last_message_id'] : 0;

   $query = "SELECT m.*, u.user_name as sender_name FROM chat_messages m 
              JOIN users u ON m.sender_id = u.user_id 
              WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)) 
              AND m.id > ? 
              ORDER BY m.sent_at ASC";
   $stmt = mysqli_prepare($con, $query);
   mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $recipient_id, $recipient_id, $user_id, $last_message_id);
   mysqli_stmt_execute($stmt);
   $result = mysqli_stmt_get_result($stmt);

   $messages = [];
   while ($row = mysqli_fetch_assoc($result)) {
      $messages[] = $row;
   }

   if (!empty($messages)) {
      $update_query = "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0";
      $update_stmt = mysqli_prepare($con, $update_query);
      mysqli_stmt_bind_param($update_stmt, "ii", $recipient_id, $user_id);
      mysqli_stmt_execute($update_stmt);
   }

   echo json_encode(['status' => 'success', 'messages' => $messages]);
   exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_chat_list') {
   $query = "SELECT DISTINCT 
              CASE 
                WHEN sender_id = ? THEN recipient_id 
                ELSE sender_id 
              END as chat_user_id,
              u.user_name, u.user_pic, u.last_seen,
              (SELECT message FROM chat_messages 
               WHERE (sender_id = ? AND recipient_id = chat_user_id) OR (sender_id = chat_user_id AND recipient_id = ?) 
               ORDER BY sent_at DESC LIMIT 1) as last_message,
              (SELECT sent_at FROM chat_messages 
               WHERE (sender_id = ? AND recipient_id = chat_user_id) OR (sender_id = chat_user_id AND recipient_id = ?) 
               ORDER BY sent_at DESC LIMIT 1) as last_message_time,
              (SELECT COUNT(*) FROM chat_messages 
               WHERE sender_id = chat_user_id AND recipient_id = ? AND is_read = 0) as unread_count
              FROM chat_messages
              JOIN users u ON (CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END) = u.user_id
              WHERE (sender_id = ? OR recipient_id = ?)
              AND u.user_ban_status = 0
              ORDER BY last_message_time DESC";

   $stmt = mysqli_prepare($con, $query);
   mysqli_stmt_bind_param($stmt, "iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
   mysqli_stmt_execute($stmt);
   $result = mysqli_stmt_get_result($stmt);

   $chat_list = [];
   while ($row = mysqli_fetch_assoc($result)) {
      $is_online = false;
      if (!empty($row['last_seen'])) {
         $is_online = (strtotime($row['last_seen']) > (time() - 60));
      }
      $row['is_online'] = $is_online;
      $chat_list[] = $row;
   }
   usort($chat_list, function ($a, $b) {
      return $b['is_online'] - $a['is_online'];
   });

   $users_query = "SELECT user_id, user_name, user_pic, last_seen 
                   FROM users 
                   WHERE user_id != ? AND user_ban_status = 0 
                   ORDER BY user_name";
   $users_stmt = mysqli_prepare($con, $users_query);
   mysqli_stmt_bind_param($users_stmt, "i", $user_id);
   mysqli_stmt_execute($users_stmt);
   $users_result = mysqli_stmt_get_result($users_stmt);

   $all_users = [];
   while ($row = mysqli_fetch_assoc($users_result)) {
      $is_online = !empty($row['last_seen']) && (strtotime($row['last_seen']) > (time() - 60));
      $row['is_online'] = $is_online;
      $all_users[] = $row;
   }
   usort($all_users, function ($a, $b) {
      return $b['is_online'] - $a['is_online'];
   });

   $total_unread = 0;
   foreach ($chat_list as $chat) {
      $total_unread += intval($chat['unread_count']);
   }

   echo json_encode([
      'status' => 'success',
      'chat_list' => $chat_list,
      'all_users' => $all_users,
      'total_unread' => $total_unread
   ]);
   exit;
}
// ---- SOS History: admin only ----
if (isset($_GET['action']) && $_GET['action'] === 'get_sos_history') {
   if ($user_type != 1) {
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
      exit;
   }
   $q = "SELECT id, message, sent_by_name, sent_at, is_active
          FROM   sos_alerts
          ORDER  BY sent_at DESC
          LIMIT  100";
   $res = mysqli_query($con, $q);
   $history = [];
   while ($row = mysqli_fetch_assoc($res))
      $history[] = $row;
   echo json_encode(['status' => 'success', 'history' => $history]);
   exit;
}
// ---- SOS Delete: admin only ----
if (isset($_POST['action']) && $_POST['action'] === 'delete_sos') {
   if ($user_type != 1) {
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
      exit;
   }
   $id = intval($_POST['id'] ?? 0);
   if ($id <= 0) {
      echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
      exit;
   }
   $stmt = mysqli_prepare($con, "DELETE FROM sos_alerts WHERE id = ?");
   mysqli_stmt_bind_param($stmt, 'i', $id);
   mysqli_stmt_execute($stmt);
   mysqli_stmt_close($stmt);
   echo json_encode(['status' => 'success']);
   exit;
}
// ---- SOS Disable/Enable: admin only ----
if (isset($_POST['action']) && $_POST['action'] === 'toggle_sos') {
   if ($user_type != 1) {
      echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
      exit;
   }
   $id = intval($_POST['id'] ?? 0);
   if ($id <= 0) {
      echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
      exit;
   }
   $stmt = mysqli_prepare($con, "UPDATE sos_alerts SET is_active = NOT is_active WHERE id = ?");
   mysqli_stmt_bind_param($stmt, 'i', $id);
   mysqli_stmt_execute($stmt);
   // Return the new state
   $sel = mysqli_prepare($con, "SELECT is_active FROM sos_alerts WHERE id = ?");
   mysqli_stmt_bind_param($sel, 'i', $id);
   mysqli_stmt_execute($sel);
   mysqli_stmt_bind_result($sel, $new_state);
   mysqli_stmt_fetch($sel);
   mysqli_stmt_close($sel);
   mysqli_stmt_close($stmt);
   echo json_encode(['status' => 'success', 'is_active' => (int)$new_state]);
   exit;
}
if (isset($_GET['action']) && $_GET['action'] == 'load_chat_ui') {
   ?>
   <!DOCTYPE html>
   <html lang="en">

   <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Chat System</title>
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <!-- SweetAlert2 -->
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <style>
         :root {
            --primary-color: #6FD943;
            --primary-dark: #5cbf38;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --light-text: #666;
            --border-color: #eee;
            --online-color: #28a745;
            --offline-color: #6c757d;
            --unread-color: #dc3545;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s ease;
         }

         .chat-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            height: 500px;
            background: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            display: none;
            flex-direction: column;
            z-index: 1000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: var(--transition);
         }

         .chat-container.active {
            display: flex;
            animation: slideIn 0.3s ease-out;
         }

         @keyframes slideIn {
            from {
               transform: translateY(20px);
               opacity: 0;
            }

            to {
               transform: translateY(0);
               opacity: 1;
            }
         }

         .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
         }

         .chat-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
         }

         .chat-close,
         .back-to-chat-list {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
         }

         .chat-close:hover,
         .back-to-chat-list:hover {
            background: rgba(255, 255, 255, 0.2);
         }

         .chat-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
         }

         .chat-tab {
            flex: 1;
            padding: 12px 10px;
            text-align: center;
            cursor: pointer;
            background: var(--secondary-color);
            border: none;
            outline: none;
            font-weight: 500;
            position: relative;
         }

         .chat-tab.active {
            background: white;
            color: var(--primary-color);
            font-weight: 600;
         }

         .chat-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-color);
         }

         .chat-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
         }

         .chat-user {
            display: flex;
            align-items: center;
            padding: 12px;
            cursor: pointer;
            border-radius: 8px;
            margin-bottom: 8px;
            position: relative;
            transition: var(--transition);
         }

         .chat-user.online {
            background-color: rgba(111, 217, 67, 0.1);
            border: 1px solid rgba(111, 217, 67, 0.3);
         }

         .chat-user:hover {
            background: var(--secondary-color);
            transform: translateX(-3px);
         }

         .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 12px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            flex-shrink: 0;
         }

         .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
         }

         .user-info {
            flex: 1;
            min-width: 0;
         }

         .user-name {
            font-weight: 500;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
         }

         .last-message {
            font-size: 12px;
            color: var(--light-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
         }

         .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--unread-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
         }

         .online-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 3px solid white;
         }

         .online-status.online {
            background-color: var(--online-color);
         }

         .online-status.offline {
            background-color: var(--offline-color);
         }

         .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: none;
            background: #f9f9f9;
         }

         .chat-messages.active {
            display: block;
         }

         .message {
            margin-bottom: 15px;
            display: flex;
            animation: fadeIn 0.3s ease-out;
         }

         @keyframes fadeIn {
            from {
               opacity: 0;
               transform: translateY(5px);
            }

            to {
               opacity: 1;
               transform: translateY(0);
            }
         }

         .message.sent {
            justify-content: flex-end;
         }

         .message.received {
            justify-content: flex-start;
         }

         .message-content {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
         }

         .message.sent .message-content {
            background: var(--primary-color);
            color: white;
            border-top-right-radius: 5px;
         }

         .message.received .message-content {
            background: white;
            color: var(--text-color);
            border-top-left-radius: 5px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
         }

         .message-time {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
            text-align: right;
         }

         .message.received .message-time {
            color: var(--light-text);
            text-align: left;
         }

         .chat-input {
            display: flex;
            padding: 12px;
            border-top: 1px solid var(--border-color);
            background: white;
         }

         .chat-input input {
            flex: 1;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 10px 15px;
            outline: none;
         }

         .chat-input input:focus {
            border-color: var(--primary-color);
         }

         .chat-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            margin-left: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
         }

         .chat-input button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
         }

         .chat-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 999;
         }

         .fab-main {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow);
            position: relative;
            font-size: 20px;
         }

         .fab-main:hover {
            transform: scale(1.05);
            background: var(--primary-dark);
         }

         .fab-actions {
            position: absolute;
            bottom: 72px;
            right: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            opacity: 0;
            pointer-events: none;
            transform: translateY(8px);
            transition: var(--transition);
         }

         .chat-fab.open .fab-actions {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
         }

         .fab-action {
            width: 52px;
            height: 52px;
            border: none;
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            position: relative;
            font-size: 18px;
         }

         .fab-action.chat-action {
            background: var(--primary-color);
         }

         .fab-action.chat-action:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
         }

         .chat-notification {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--unread-color);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
         }

         @keyframes pulse {
            0% {
               box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
               box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }

            100% {
               box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
         }

         .typing-indicator {
            display: none;
            padding: 10px 15px;
            color: var(--light-text);
            font-style: italic;
            font-size: 14px;
         }

         .typing-indicator.active {
            display: block;
         }

         .typing-dots span {
            display: inline-block;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background-color: var(--light-text);
            margin: 0 2px;
            animation: typing 1.4s infinite;
         }

         .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
         }

         .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
         }

         @keyframes typing {

            0%,
            60%,
            100% {
               transform: translateY(0);
            }

            30% {
               transform: translateY(-10px);
            }
         }

         .back-to-chat-list {
            display: none;
         }

         .chat-header.with-chat .back-to-chat-list {
            display: block;
         }

         .chat-header.with-chat h5 {
            margin-left: 10px;
         }

         .user-status {
            display: flex;
            align-items: center;
            font-size: 12px;
            color: var(--light-text);
            margin-top: 3px;
         }

         .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
         }

         .status-dot.online {
            background-color: var(--online-color);
         }

         .status-dot.offline {
            background-color: var(--offline-color);
         }

         .emoji-picker {
            position: absolute;
            bottom: 60px;
            right: 10px;
            width: 300px;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 10px;
            display: none;
            grid-template-columns: repeat(10, 1fr);
            gap: 5px;
            z-index: 1001;
            max-height: 250px;
            overflow-y: auto;
         }

         .emoji-picker span {
            cursor: pointer;
            text-align: center;
            font-size: 22px;
            padding: 5px;
            border-radius: 4px;
         }

         .emoji-picker span:hover {
            background: var(--secondary-color);
            transform: scale(1.2);
         }

         #emojiToggle {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            margin-right: 8px;
         }

         #emojiToggle:hover {
            transform: scale(1.1);
         }

         /* SOS action button */
         @keyframes pulse-red {
            0% {
               box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }

            70% {
               box-shadow: 0 0 0 15px rgba(220, 53, 69, 0);
            }

            100% {
               box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
         }

         #adminSOSButton {
            background: #dc3545;
         }

         #adminSOSButton:hover {
            transform: scale(1.1);
            background: #c82333;
         }

         .chat-list::-webkit-scrollbar,
         .chat-messages::-webkit-scrollbar,
         .emoji-picker::-webkit-scrollbar {
            width: 6px;
         }

         .chat-list::-webkit-scrollbar-track,
         .chat-messages::-webkit-scrollbar-track,
         .emoji-picker::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
         }

         .chat-list::-webkit-scrollbar-thumb,
         .chat-messages::-webkit-scrollbar-thumb,
         .emoji-picker::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 10px;
         }

         @media (max-width: 480px) {
            .chat-container {
               width: calc(100% - 40px);
               right: 20px;
               left: 20px;
            }
         }

         /* -------- SOS History tab -------- */
         .sos-history-list {
            padding: 10px;
            overflow-y: auto;
            flex: 1;
         }

         .sos-history-item {
            background: #fff5f5;
            border-left: 4px solid #dc3545;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 10px;
            font-size: 14px;
            line-height: 1.4;
         }

         .sos-history-item .sos-meta {
            font-size: 12px;
            color: #6c757d;
            margin-top: 4px;
         }

         .sos-history-item {
            position: relative;
         }

         .sos-delete-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 14px;
            padding: 2px 5px;
            border-radius: 4px;
            opacity: 0.6;
            transition: opacity 0.2s;
         }

         .sos-delete-btn:hover {
            opacity: 1;
            background: #fff0f0;
         }

         .sos-toggle-btn {
            position: absolute;
            top: 8px;
            right: 36px;
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 14px;
            padding: 2px 5px;
            border-radius: 4px;
            opacity: 0.6;
            transition: opacity 0.2s;
         }

         .sos-toggle-btn:hover {
            opacity: 1;
            background: #f0f0f0;
         }

         .sos-history-item.disabled {
            opacity: 0.5;
            background: #f5f5f5;
            border-left-color: #adb5bd;
         }

         .sos-history-item.disabled .sos-toggle-btn {
            color: #28a745;
         }
      </style>
   </head>

   <body>

      <div class="chat-fab" id="chatFab">
         <button class="fab-main" id="fabToggle" title="Open quick actions" type="button">
            <i class="fas fa-comments"></i>
            <div class="chat-notification" id="chatNotificationMain" style="display: none;">0</div>
         </button>
         <div class="fab-actions" id="fabActions">
            <button class="fab-action chat-action" id="openChatButton" title="Open Chat" type="button">
               <i class="fas fa-comments"></i>
               <div class="chat-notification" id="chatNotification" style="display: none;">0</div>
            </button>
            <?php if ($user_type == 1): ?>
               <button class="fab-action sos-action" id="adminSOSButton" title="Send Announcement to ALL Users" type="button">
                  <i class="fas fa-exclamation-triangle"></i>
               </button>
            <?php endif; ?>
         </div>
      </div>

      <!-- Chat Container -->
      <div class="chat-container" id="chatContainer">
         <div class="chat-header" id="chatHeader">
            <button class="back-to-chat-list" id="backToChatList">
               <i class="fas fa-arrow-left"></i>
            </button>
            <h5 id="chatHeaderTitle">Messages</h5>
            <button class="chat-close" id="chatClose">
               <i class="fas fa-times"></i>
            </button>
         </div>

         <div class="chat-tabs">
            <button class="chat-tab active" id="recentChatsTab">Recent</button>
            <button class="chat-tab" id="allUsersTab">All Users</button>
            <?php if ($user_type == 1): ?>
               <button class="chat-tab" id="sosHistoryTab">SOS History</button>
            <?php endif; ?>
         </div>

         <div class="chat-list" id="chatList"></div>

         <div class="chat-messages" id="chatMessages"></div>

         <div class="typing-indicator" id="typingIndicator">
            <span id="typingUser"></span> is typing<span class="typing-dots"><span></span><span></span><span></span></span>
         </div>

         <div class="chat-input" id="chatInputContainer" style="display:none;">
            <button id="emojiToggle">😊</button>
            <input type="text" id="messageInput" placeholder="Type a message...">
            <button id="sendMessage"><i class="fas fa-paper-plane"></i></button>
            <div class="emoji-picker" id="emojiPicker"></div>
         </div>
      </div>

      <script>
         $(document).ready(function () {
            const state = {
               currentChatUserId: null,
               lastMessageId: 0,
               messagePollingInterval: null,
               statusUpdateInterval: null,
               heartbeatInterval: null,
               unreadCount: 0,
               activeTab: 'recent',
               isPageActive: true,
               currentUserId: <?php echo json_encode($user_id); ?>,
               typingTimeout: null,
               isTyping: false
            };

            // SOS Alert System
            function checkForSOSAlerts() {
               $.ajax({
                  url: 'chat_system.php',
                  type: 'GET',
                  data: { action: 'get_latest_sos' },
                  dataType: 'json',
                  cache: false,
                  success: function (response) {
                     if (response.status === 'success' && response.alert) {
                        const alertId = response.alert.id;
                        const lastShown = localStorage.getItem('last_sos_shown_id');
                        if (!lastShown || parseInt(lastShown) < alertId) {
                           showSOSAlertModal(response.alert);
                           localStorage.setItem('last_sos_shown_id', alertId);
                        }
                     }
                  }
               });
            }

            function showSOSAlertModal(alert) {
               $('#sosAlertModal').remove();
               const modalHtml = `
            <div id="sosAlertModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:20000;display:flex;justify-content:center;align-items:center;animation:fadeIn 0.4s;">
                <div style="background:#dc3545;color:white;padding:30px;border-radius:20px;max-width:90%;width:500px;text-align:center;box-shadow:0 15px 40px rgba(0,0,0,0.6);animation:pulse 2s infinite;">
                    <h2 style="font-size:32px;margin:0 0 20px 0;">📢 ANNOUNCEMENTS 📢</h2>
                    <p style="font-size:20px;line-height:1.6;white-space:pre-wrap;margin:20px 0;">${alert.message.replace(/\n/g, '<br>')}</p>
                    <div style="margin:20px 0;font-size:14px;opacity:0.9;">
                        Sent by: <strong>${alert.sent_by_name}</strong><br>${formatTime(alert.sent_at)}
                    </div>
                    <button onclick="$('#sosAlertModal').fadeOut(300, function(){$(this).remove();})" style="background:white;color:#dc3545;border:none;padding:12px 32px;border-radius:50px;font-size:16px;font-weight:bold;cursor:pointer;">
                        I UNDERSTAND
                    </button>
                </div>
            </div>
            <style>
                @keyframes pulse {0%{box-shadow:0 0 0 0 rgba(220,53,69,0.7);}70%{box-shadow:0 0 0 20px rgba(220,53,69,0);}100%{box-shadow:0 0 0 0 rgba(220,53,69,0);}}
                @keyframes fadeIn {from{opacity:0;}to{opacity:1;}}
            </style>
        `;
            $('body').append(modalHtml);
            const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-emergency-alert-alarm-1004.mp3');
            audio.volume = 0.6;
            audio.play().catch(() => { });
         }

         /* ----------  Admin SOS Button – SweetAlert2  ---------- */
         $('#adminSOSButton').click(function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeFabMenu();
            Swal.fire({
               title: '📢 SEND ANNOUNCEMENT TO ALL USERS 📢',
               input: 'textarea',
               inputLabel: 'Announcement message',
               inputPlaceholder: 'Type the announcement…',
               inputAttributes: { maxlength: 500 },
               showCancelButton: true,
               confirmButtonText: 'Send Alert',
               confirmButtonColor: '#dc3545',
               cancelButtonText: 'Cancel',
               reverseButtons: true,
               inputValidator: (value) => {
                  if (!value || value.trim() === '') {
                     return 'Message cannot be empty!';
                  }
               }
            }).then((result) => {
               if (!result.isConfirmed) return;          // user pressed Cancel
               const message = result.value.trim();

               Swal.fire({
                  title: '⚠️ Confirm broadcast',
                  text: `Send this SOS to every user now?\n\n"${message}"`,
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonText: 'Yes, send it!',
                  confirmButtonColor: '#dc3545',
                  cancelButtonText: 'No, cancel'
               }).then((step2) => {
                  if (!step2.isConfirmed) return;

                  $.post('chat_system.php',
                     { action: 'send_sos', message: message },
                     function (res) {
                        if (res.status === 'success') {
                           Swal.fire('Sent!', 'SOS alert has been broadcast to all users.', 'success');
                        } else {
                           Swal.fire('Error', res.message || 'Failed to send alert.', 'error');
                        }
                     },
                     'json'
                  ).fail(() =>
                     Swal.fire('Network error', 'Could not reach the server.', 'error')
                  );
               });
            });
         });

         // Start checking for SOS alerts
         checkForSOSAlerts();
         setInterval(checkForSOSAlerts, 15000);

         updateUnreadCount();
         startHeartbeat();
         initializeEventListeners();
         initializeEmojiPicker();
         /* ---------- SOS History tab ---------- */
         $('#sosHistoryTab').click(() => switchTab('sos'));

         function loadSOSHistory() {
            $.get('chat_system.php', { action: 'get_sos_history' }, function (res) {
               if (res.status !== 'success') return;
               let html = '';
               if (!res.history || res.history.length === 0) {
                  html = '<div class="text-center p-3">No SOS alerts sent yet.</div>';
               } else {
                  res.history.forEach(h => {
                     const isDisabled = h.is_active == 0;
                     const disabledClass = isDisabled ? ' disabled' : '';
                     const toggleIcon = isDisabled ? 'fa-eye' : 'fa-eye-slash';
                     const toggleTitle = isDisabled ? 'Enable' : 'Disable';
                     html += `
                   <div class="sos-history-item${disabledClass}" data-id="${h.id}" data-active="${h.is_active}">
                      <button class="sos-toggle-btn" title="${toggleTitle}"><i class="fa ${toggleIcon}"></i></button>
                      <button class="sos-delete-btn" title="Delete"><i class="fa fa-trash"></i></button>
                      <div>${h.message.replace(/\n/g, '<br>')}</div>
                      <div class="sos-meta">
                         Sent by <strong>${h.sent_by_name}</strong> • ${formatTime(h.sent_at)}
                         ${isDisabled ? ' • <span style="color:#dc3545;font-weight:600;">Disabled</span>' : ''}
                      </div>
                   </div>`;
                  });
               }
               $('#chatList').html(html);
            }, 'json');
         }

         $(document).on('click', '.sos-delete-btn', function () {
            const item = $(this).closest('.sos-history-item');
            const id = item.data('id');
            Swal.fire({
               title: 'Delete this SOS alert?',
               icon: 'warning',
               showCancelButton: true,
               confirmButtonColor: '#dc3545',
               confirmButtonText: 'Delete'
            }).then(result => {
               if (!result.isConfirmed) return;
               $.post('chat_system.php', { action: 'delete_sos', id: id }, function (res) {
                  if (res.status === 'success') {
                     item.remove();
                     if ($('.sos-history-item').length === 0)
                        $('#chatList').html('<div class="text-center p-3">No SOS alerts sent yet.</div>');
                  }
               }, 'json');
            });
         });

         $(document).on('click', '.sos-toggle-btn', function () {
            const item = $(this).closest('.sos-history-item');
            const id = item.data('id');
            const isActive = item.data('active') == 1;
            const actionLabel = isActive ? 'Disable' : 'Enable';
            const actionColor = isActive ? '#6c757d' : '#28a745';
            Swal.fire({
               title: `${actionLabel} this SOS alert?`,
               text: isActive ? 'The alert will no longer appear to users.' : 'The alert will be shown to users again.',
               icon: 'question',
               showCancelButton: true,
               confirmButtonColor: actionColor,
               confirmButtonText: actionLabel
            }).then(result => {
               if (!result.isConfirmed) return;
               $.post('chat_system.php', { action: 'toggle_sos', id: id }, function (res) {
                  if (res.status === 'success') {
                     const nowActive = res.is_active == 1;
                     item.data('active', res.is_active);
                     item.toggleClass('disabled', !nowActive);
                     item.find('.sos-toggle-btn i')
                        .removeClass('fa-eye fa-eye-slash')
                        .addClass(nowActive ? 'fa-eye-slash' : 'fa-eye');
                     item.find('.sos-toggle-btn').attr('title', nowActive ? 'Disable' : 'Enable');
                     const meta = item.find('.sos-meta');
                     meta.find('.sos-disabled-label').remove();
                     if (!nowActive) {
                        meta.append(' • <span class="sos-disabled-label" style="color:#dc3545;font-weight:600;">Disabled</span>');
                     }
                  }
               }, 'json');
            });
         });
         function switchTab(tab) {
            if (state.activeTab === tab) return;
            $('.chat-tab').removeClass('active');
            const tabId = tab === 'recent' ? 'recentChatsTab' :
               tab === 'sos' ? 'sosHistoryTab' :
                  'allUsersTab';
            $('#' + tabId).addClass('active');
            state.activeTab = tab;
            tab === 'sos' ? loadSOSHistory() : loadActiveTabContent();
         }
         // Rest of your original functions below...
         function initializeEventListeners() {
            document.addEventListener('visibilitychange', handleVisibilityChange);
            window.addEventListener('beforeunload', handlePageUnload);

            $('#fabToggle').click(function (e) {
               e.preventDefault();
               e.stopPropagation();
               toggleFabMenu();
            });
            $('#openChatButton').click(function (e) {
               e.preventDefault();
               e.stopPropagation();
               toggleChatContainer();
               closeFabMenu();
            });
            $(document).on('click', handleOutsideFabClick);
            $('#chatClose').click(closeChat);
            $('#backToChatList').click(showChatList);

            $('#recentChatsTab').click(() => switchTab('recent'));
            $('#allUsersTab').click(() => switchTab('all'));

            $('#sendMessage').click(sendMessage);
            $('#messageInput').keypress(handleMessageKeypress);
            $('#messageInput').on('input', handleTypingIndicator);
         }

         function handleVisibilityChange() {
            state.isPageActive = !document.hidden;
            if (state.isPageActive) startHeartbeat(); else stopHeartbeat();
         }

         function handlePageUnload() {
            navigator.sendBeacon('chat_system.php', 'action=update_status');
         }

         function toggleFabMenu() {
            $('#chatFab').toggleClass('open');
         }

         function closeFabMenu() {
            $('#chatFab').removeClass('open');
         }

         function handleOutsideFabClick(e) {
            if (!$(e.target).closest('#chatFab').length) closeFabMenu();
         }

         function toggleChatContainer() {
            $('#chatContainer').toggleClass('active');
            if ($('#chatContainer').hasClass('active')) {
               loadActiveTabContent();
               startMessagePolling();
               startStatusUpdates();
               state.unreadCount = 0;
               updateUnreadBadge();
            } else {
               stopMessagePolling();
               stopStatusUpdates();
            }
         }

         function closeChat() {
            $('#chatContainer').removeClass('active');
            stopMessagePolling();
            stopStatusUpdates();
         }

         function switchTab(tab) {
            if (state.activeTab === tab) return;
            $('.chat-tab').removeClass('active');
            const tabId = tab === 'recent' ? 'recentChatsTab' :
               tab === 'sos' ? 'sosHistoryTab' :
                  'allUsersTab';
            $('#' + tabId).addClass('active');
            state.activeTab = tab;
            tab === 'sos' ? loadSOSHistory() : loadActiveTabContent();
         }
         function loadActiveTabContent() {
            state.activeTab === 'recent' ? loadChatList() : loadAllUsers();
         }

         function loadChatList() {
            $.ajax({
               url: 'chat_system.php',
               type: 'GET',
               data: { action: 'get_chat_list' },
               dataType: 'json',
               success: function (response) {
                  if (response.status === 'success') {
                     displayChatList(response.chat_list);
                     state.unreadCount = response.total_unread || 0;
                     updateUnreadBadge();
                  }
               },
               error: handleAjaxError
            });
         }

         function loadAllUsers() {
            $.ajax({
               url: 'chat_system.php',
               type: 'GET',
               data: { action: 'get_chat_list' },
               dataType: 'json',
               success: function (response) {
                  if (response.status === 'success') displayAllUsers(response.all_users);
               },
               error: handleAjaxError
            });
         }

         function displayChatList(chatList) {
            let html = chatList.length === 0 ? '<div class="text-center p-3">No recent chats</div>' : '';
            chatList.forEach(user => {
               const initials = getUserInitials(user.user_name);
               const lastMessageTime = formatTime(user.last_message_time);
               const unreadBadge = user.unread_count > 0 ? `<div class="unread-badge">${user.unread_count}</div>` : '';
               const onlineClass = user.is_online ? 'online' : '';
               const onlineStatus = user.is_online ? 'online' : 'offline';
               const statusText = user.is_online ? 'Online' : formatLastSeen(user.last_seen);

               html += `
                <div class="chat-user ${onlineClass}" data-user-id="${user.chat_user_id}">
                    <div class="user-avatar">
                        ${getUserAvatar(user)}
                        <div class="online-status ${onlineStatus}"></div>
                    </div>
                    <div class="user-info">
                        <div class="user-name">${user.user_name}</div>
                        <div class="last-message">${user.last_message || 'No messages yet'}</div>
                        <div class="user-status"><span class="status-dot ${onlineStatus}"></span>${statusText}</div>
                    </div>
                    <div class="message-time">${lastMessageTime}</div>
                    ${unreadBadge}
                </div>`;
            });
            $('#chatList').html(html);
            $('.chat-user').click(handleChatUserClick);
         }

         function displayAllUsers(allUsers) {
            let html = allUsers.length === 0 ? '<div class="text-center p-3">No users found</div>' : '';
            allUsers.forEach(user => {
               const initials = getUserInitials(user.user_name);
               const onlineClass = user.is_online ? 'online' : '';
               const onlineStatus = user.is_online ? 'online' : 'offline';
               const statusText = user.is_online ? 'Online' : formatLastSeen(user.last_seen);

               html += `
                <div class="chat-user ${onlineClass}" data-user-id="${user.user_id}">
                    <div class="user-avatar">
                        ${getUserAvatar(user)}
                        <div class="online-status ${onlineStatus}"></div>
                    </div>
                    <div class="user-info">
                        <div class="user-name">${user.user_name}</div>
                        <div class="last-message">Start a conversation</div>
                        <div class="user-status"><span class="status-dot ${onlineStatus}"></span>${statusText}</div>
                    </div>
                </div>`;
            });
            $('#chatList').html(html);
            $('.chat-user').click(handleChatUserClick);
         }

         function handleChatUserClick() {
            const userId = $(this).data('user-id');
            const userName = $(this).find('.user-name').text();
            openChat(userId, userName);
         }

         function openChat(userId, userName) {
            state.currentChatUserId = userId;
            state.lastMessageId = 0;
            $('#chatHeader').addClass('with-chat');
            $('#chatHeaderTitle').text(userName);
            $('#chatList').hide();
            $('#chatMessages').addClass('active').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
            $('#chatInputContainer').show();
            loadMessages();
         }

         function showChatList() {
            state.currentChatUserId = null;
            $('#chatHeader').removeClass('with-chat');
            $('#chatHeaderTitle').text('Messages');
            $('#chatList').show();
            $('#chatMessages').removeClass('active');
            $('#chatInputContainer').hide();
            loadActiveTabContent();
         }

         function loadMessages() {
            if (!state.currentChatUserId) return;
            $.ajax({
               url: 'chat_system.php',
               type: 'GET',
               data: { action: 'get_messages', recipient_id: state.currentChatUserId, last_message_id: state.lastMessageId },
               dataType: 'json',
               success: function (response) {
                  if (response.status === 'success') {
                     displayMessages(response.messages);
                     if (response.messages.length > 0) {
                        state.lastMessageId = response.messages[response.messages.length - 1].id;
                     }
                     updateUnreadCount();
                  }
               },
               error: handleAjaxError
            });
         }

         function displayMessages(messages) {
            if (messages.length === 0) {
               if ($('#chatMessages').html().includes('Loading')) {
                  $('#chatMessages').html('<div class="text-center p-3">No messages yet. Start a conversation!</div>');
               }
               return;
            }
            let html = '';
            messages.forEach(message => {
               const isSent = message.sender_id == state.currentUserId;
               const messageClass = isSent ? 'sent' : 'received';
               const messageTime = formatTime(message.sent_at);
               html += `
                <div class="message ${messageClass}">
                    <div class="message-content">
                        ${message.message}
                        <div class="message-time">${messageTime}</div>
                    </div>
                </div>`;
            });
            if ($('#chatMessages').html().includes('Loading')) {
               $('#chatMessages').html(html);
            } else {
               $('#chatMessages').append(html);
            }
            scrollToBottom();
         }

         function sendMessage() {
            const message = $('#messageInput').val().trim();
            if (message === '' || !state.currentChatUserId) return;
            $.ajax({
               url: 'chat_system.php',
               type: 'POST',
               data: { action: 'send_message', recipient_id: state.currentChatUserId, message: message },
               dataType: 'json',
               success: function (response) {
                  if (response.status === 'success') {
                     $('#messageInput').val('');
                     loadMessages();
                     if (state.activeTab === 'recent') loadChatList();
                  }
               },
               error: handleAjaxError
            });
         }

         function handleMessageKeypress(e) {
            if (e.which == 13) sendMessage();
         }

         function handleTypingIndicator() { }

         function startMessagePolling() {
            state.messagePollingInterval = setInterval(() => {
               if (state.currentChatUserId) loadMessages();
               else if (state.activeTab === 'recent') loadChatList();
            }, 3000);
         }

         function stopMessagePolling() {
            clearInterval(state.messagePollingInterval);
            state.messagePollingInterval = null;
         }

         function startStatusUpdates() {
            state.statusUpdateInterval = setInterval(loadActiveTabContent, 10000);
         }

         function stopStatusUpdates() {
            clearInterval(state.statusUpdateInterval);
            state.statusUpdateInterval = null;
         }

         function startHeartbeat() {
            state.heartbeatInterval = setInterval(() => {
               if (!state.isPageActive) return;
               $.ajax({
                  url: 'chat_system.php',
                  type: 'POST',
                  data: { action: 'heartbeat' },
                  dataType: 'json',
                  success: function (res) {
                     if (res.logged_in === false) {
                        clearInterval(state.heartbeatInterval);
                        stopMessagePolling();
                        stopStatusUpdates();
                     }
                  }
               });
            }, 15000);
         }

         function stopHeartbeat() {
            clearInterval(state.heartbeatInterval);
            state.heartbeatInterval = null;
         }

         function updateUnreadCount() {
            $.ajax({
               url: 'chat_system.php',
               type: 'GET',
               data: { action: 'get_chat_list' },
               dataType: 'json',
               success: function (response) {
                  if (response.status === 'success') {
                     state.unreadCount = response.total_unread || 0;
                     updateUnreadBadge();
                  }
               }
            });
         }

         function updateUnreadBadge() {
            if (state.unreadCount > 0) {
               const badgeText = state.unreadCount > 99 ? '99+' : state.unreadCount;
               $('#chatNotification').text(badgeText).show();
               $('#chatNotificationMain').text(badgeText).show();
            } else {
               $('#chatNotification').hide();
               $('#chatNotificationMain').hide();
            }
         }

         function scrollToBottom() {
            $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight);
         }

         function getUserInitials(name) {
            return name.split(' ').slice(0, 2).map(p => p[0].toUpperCase()).join('');
         }

         function getUserAvatar(user) {
            const initials = getUserInitials(user.user_name);
            if (user.user_pic && user.user_pic.trim() !== '') {
               let imagePath = 'Uploads/staff_images/' + user.user_pic;
               imagePath = imagePath.replace(/\\/g, '/');
               return `<img src="${imagePath}" alt="${user.user_name}" onerror="handleAvatarError(this, '${initials}')" loading="lazy">`;
            }
            return initials;
         }

         function formatTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins} min ago`;
            if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
            if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
            return `${date.getMonth() + 1}/${date.getDate()}/${date.getFullYear().toString().substr(-2)}`;
         }

         function formatLastSeen(lastSeen) {
            if (!lastSeen) return 'Unknown';
            return 'Last seen ' + formatTime(lastSeen);
         }

         function handleAjaxError(xhr, status, error) {
            console.error('AJAX Error:', status, error);
         }

         setInterval(() => {
            if (!$('#chatContainer').hasClass('active')) updateUnreadCount();
         }, 10000);

         function initializeEmojiPicker() {
            const emojis = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '💔', '💕', '👍', '👎', '👌', '🤞', '🤟', '✌️', '🤘', '👏', '🙌', '🤝', '🙏', '🔥', '💯', '🎉', '✨', '🌟', '😴', '🤔', '🙄', '😬', '🤗', '🤭', '🤫', '🤥', '😐', '😑', '😶', '😮', '😲', '😳', '🤤', '😪', '🤒', '🤕', '🤧', '😷', '🤢', '🤮', '🥴'];
            const $picker = $('#emojiPicker');
            emojis.forEach(e => $picker.append(`<span>${e}</span>`));

            $('#emojiToggle').click(() => $picker.toggle());
            $picker.on('click', 'span', function () {
               $('#messageInput').val($('#messageInput').val() + this.textContent);
               $picker.hide();
               $('#messageInput').focus();
            }); 
            $(document).on('click', e => {
               if (!$(e.target).closest('#emojiToggle,#emojiPicker').length) $picker.hide();
            });
         }
      });

      window.handleAvatarError = function (img, initials) {
         const avatarDiv = img.parentElement;
         avatarDiv.innerHTML = initials;
         avatarDiv.style.backgroundColor = getAvatarColor(initials);
         avatarDiv.style.display = 'flex';
         avatarDiv.style.alignItems = 'center';
         avatarDiv.style.justifyContent = 'center';
      };
  
      window.getAvatarColor = function (initials) {
         const colors = ['#6FD943', '#007bff', '#dc3545', '#ffc107', '#17a2b8', '#28a745', '#6f42c1', '#fd7e14'];
         let hash = 0;
         for (let i = 0; i < initials.length; i++) hash = initials.charCodeAt(i) + ((hash << 5) - hash);
         return colors[Math.abs(hash) % colors.length];
      };
   </script>

</body>
 
</html>
<?php
      exit;
}
?>
