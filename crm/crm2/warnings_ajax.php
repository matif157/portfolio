<?php
// Buffer from the very first line so any stray bytes emitted by the includes
// or by PHP itself (notices, warnings, whitespace) on the production server
// are captured instead of being sent to the client. The browser compares the
// body strictly against 'OK', so a single leaked byte turns a successful save
// into a false "Error" popup even though the row was written. We discard the
// buffer at the end and emit only the intended response.
ob_start();

require_once 'includes/db.php';
require_once 'includes/auth.php';
include "includes/activity_log.php";

$allowed = in_array($_SESSION['user_type'] ?? '', ['1', '2']);
$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$text = trim($_POST['warning'] ?? '');
$uid = $_SESSION['user_id'];

$status = 200;
$response = '';

try {
    switch ($action) {

        case 'save':
            if (!$text)
                throw new Exception('Text empty');
            $mid = (int) ($_POST['member_id'] ?? 0);
            if (!$mid)
                throw new Exception('member_id missing');

            $stmt = $con->prepare("SELECT id, warning FROM client_warnings WHERE member_id=? AND is_active=1 LIMIT 1");
            $stmt->bind_param('i', $mid);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();

            if ($existing) {
                $oldText = $existing['warning'];
                $id = $existing['id'];

                $stmt = $con->prepare("UPDATE client_warnings SET warning=?, created_at=NOW(), created_by=? WHERE id=?");
                $stmt->bind_param('sii', $text, $uid, $id);
                $stmt->execute();

                logActivity($con, $uid, 'UPDATE', 'client_warnings', $id, "Alert changes {$oldText} To {$text} for member #{$mid}");
            } else {
                $stmt = $con->prepare("INSERT INTO client_warnings (member_id, warning, created_by, is_active) VALUES (?,?,?,1)");
                $stmt->bind_param('isi', $mid, $text, $uid);
                $stmt->execute();
                $id = $stmt->insert_id;

                logActivity($con, $uid, 'CREATE', 'client_warnings', $id, "Alert added for member #{$mid}");
            }

            $response = 'OK';
            break;

        case 'edit':
            if (!$text)
                throw new Exception('Text empty');

            $stmt = $con->prepare("SELECT warning, member_id FROM client_warnings WHERE id=? AND is_active=1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();

            if (!$old)
                throw new Exception('Not found');

            $oldText = $old['warning'];

            $stmt = $con->prepare("UPDATE client_warnings SET warning=?, created_at=NOW(), created_by=? WHERE id=?");
            $stmt->bind_param('sii', $text, $uid, $id);
            $stmt->execute();

            logActivity($con, $uid, 'UPDATE', 'client_warnings', $id, "Alert changes {$oldText} To {$text} for member #{$old['member_id']}");

            $response = 'OK';
            break;

        case 'delete':
            $stmt = $con->prepare("SELECT warning, member_id FROM client_warnings WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $old = $stmt->get_result()->fetch_assoc();

            if (!$old)
                throw new Exception('Not found');

            $oldText = $old['warning'];

            $stmt = $con->prepare("UPDATE client_warnings SET is_active=0 WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();

            logActivity($con, $uid, 'DELETE', 'client_warnings', $id, "Deleted alert for member #{$old['member_id']} – Text was {$oldText}");

            $response = 'OK';
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $status = 400;
    $response = $e->getMessage();
}

// Throw away anything that leaked into the output buffer (stray notices,
// warnings, whitespace from includes) and send ONLY the intended body so the
// client's `res === 'OK'` check is never tripped by environment noise.
while (ob_get_level() > 0) {
    ob_end_clean();
}
http_response_code($status);
header('Content-Type: text/plain; charset=utf-8');
echo $response;
