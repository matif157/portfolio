<?php
require_once __DIR__ . '/../includes/log_in.php';
require_permission('Client', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../clients/index.php');
    exit;
}

$member_id = (int)($_POST['member_id'] ?? 0);
$detail    = trim($_POST['comment_detail'] ?? '');

if ($member_id <= 0 || $detail === '') {
    set_flash('Note cannot be empty.', 'danger');
    header("Location: ../clients/view.php?id=$member_id");
    exit;
}

$pdo->prepare("INSERT INTO client_comments (member_id, comment_detail, user_id) VALUES (?,?,?)")
    ->execute([$member_id, $detail, $_SESSION['user_id']]);

log_activity('add_note', 'Client', "Added note on client ID $member_id");
set_flash('Note added.');
header("Location: ../clients/view.php?id=$member_id");
exit;
