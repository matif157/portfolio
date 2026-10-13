<?php
// ============================================================
// SPS CRM - Notifications AJAX endpoint (used by layout.php)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();

header('Content-Type: application/json');

$action  = $_GET['action'] ?? '';
$user_id = current_user_id();

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        db_query("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$id, $user_id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Default: return recent notifications for the bell dropdown
$rows = db_rows(
    "SELECT id, title, message, type, link, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 15",
    [$user_id]
);

$out = array_map(function ($n) {
    return [
        'id'         => (int)$n['id'],
        'title'      => $n['title'],
        'message'    => $n['message'],
        'type'       => $n['type'],
        'link'       => $n['link'],
        'is_read'    => (bool)$n['is_read'],
        'time_ago'   => time_ago($n['created_at']),
    ];
}, $rows);

echo json_encode($out);
