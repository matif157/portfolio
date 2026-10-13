<?php
// ============================================================
// SPS CRM - Secure Bank Statement Download
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_login();
require_permission('bank_statements.view');

$id = (int)($_GET['id'] ?? 0);

$stmt = db_row(
    "SELECT bs.*, ba.business_id FROM bank_statements bs
     JOIN bank_accounts ba ON ba.id = bs.bank_account_id
     WHERE bs.id = ? AND bs.status = 'received'",
    [$id]
);

if (!$stmt || !$stmt['filename']) {
    http_response_code(404);
    die('Statement not found.');
}

$path = UPLOAD_PATH . 'statements/' . $stmt['bank_account_id'] . '/' . $stmt['filename'];

if (!is_file($path)) {
    http_response_code(404);
    die('File is missing from storage.');
}

log_activity('download', 'bank_statements', $id, 'Statement downloaded: ' . $stmt['original_filename']);

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($stmt['original_filename'] ?: $stmt['filename']) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
