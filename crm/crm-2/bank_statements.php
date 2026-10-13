<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'dompdf/autoload.inc.php';
include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";

// Support both 'id' and 'client_id' parameters
 $client_id = $_GET['id'] ?? $_GET['client_id'] ?? 0;

// Security check - redirect with correct parameter
if (!isset($_SESSION['bank_auth']) || $_SESSION['bank_auth'] !== 1) {
    header("Location: client_details.php?id=" . ($client_id ?: 0));
    exit;
} 

 $status = $_GET['status'] ?? 0;
 $userid = $_SESSION["user_id"] ?? 0;
 $user_type = $_SESSION['user_type'] ?? '';
 $is_admin = ($user_type == '1');

// Verify client exists
 $client_query = mysqli_query($con, "SELECT * FROM members WHERE member_id='$client_id'");
if (mysqli_num_rows($client_query) === 0) {
    header("Location: view_clients.php");
    exit;
}
 $client_data = mysqli_fetch_assoc($client_query);

// Get client name
 $client_name = trim($client_data['name'] . ' ' . $client_data['mname'] . ' ' . $client_data['lname']);
 $business_name = $client_data['bus_name'] ?: 'N/A';
 $page_endpoint = basename(__FILE__);
 $msg = '';

if (isset($_SESSION['msg'])) {
    $msg = $_SESSION['msg'];
    unset($_SESSION['msg']);
}

function getStatementFilesByYear($client_id, $bank_id, $year)
{
    $files_by_month = [];
    $base_dir = __DIR__ . "/Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}/{$year}";
    if (!is_dir($base_dir)) {
        return $files_by_month;
    }

    $entries = scandir($base_dir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!preg_match('/^m(\d{1,2})_/', $entry, $matches)) {
            continue;
        }
        $month = (int) $matches[1];
        if ($month < 0 || $month > 11) {
            continue;
        }

        $absolute = $base_dir . '/' . $entry;
        if (!is_file($absolute)) {
            continue;
        }

        $relative_path = "Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}/{$year}/{$entry}";
        if (!isset($files_by_month[$month]) || filemtime($absolute) > $files_by_month[$month]['mtime']) {
            $files_by_month[$month] = [
                'url' => $relative_path,
                'name' => $entry,
                'mtime' => filemtime($absolute)
            ];
        }
    }

    foreach ($files_by_month as $month => $meta) {
        unset($files_by_month[$month]['mtime']);
    }

    return $files_by_month;
}

function getReceivedMonthsByYear($con, $client_id, $bank_id, $year)
{
    $received = [];
    $query = mysqli_query(
        $con,
        "SELECT statement_month FROM bank_statements 
         WHERE member_id='$client_id' AND bank_id='$bank_id' AND statement_year='$year' AND received=1"
    );

    while ($row = mysqli_fetch_assoc($query)) {
        $month = (int) $row['statement_month'];
        if ($month >= 0 && $month <= 11) {
            $received[] = $month;
        }
    }

    return array_values(array_unique($received));
}

function getMissingMonthsByYear($con, $client_id, $bank_id, $year)
{
    $received = getReceivedMonthsByYear($con, $client_id, $bank_id, $year);
    $missing = [];
    for ($m = 0; $m <= 11; $m++) {
        if (!in_array($m, $received, true)) {
            $missing[] = $m;
        }
    }
    return $missing;
}

/**
 * Find every bank in the CRM whose account number ends with the given 4 digits.
 * Used both for the live "as you type" check and the submit-time confirmation.
 */
function findBankMatchesByLast4($con, $last4, $current_client_id)
{
    $matches = [];
    if (strlen($last4) !== 4) {
        return $matches;
    }
    $last4_esc = mysqli_real_escape_string($con, $last4);
    $current_client_id = (int) $current_client_id;
    $sql = "SELECT cb.member_id, cb.bank_name, cb.account_number, cb.account_type,
                   m.name, m.lname, m.bus_name
            FROM client_banks cb
            LEFT JOIN members m ON m.member_id = cb.member_id
            WHERE RIGHT(REPLACE(REPLACE(cb.account_number, ' ', ''), '-', ''), 4) = '$last4_esc'
            ORDER BY (cb.member_id = '$current_client_id') DESC, cb.bank_id DESC";
    $res = mysqli_query($con, $sql);
    while ($res && $d = mysqli_fetch_assoc($res)) {
        $cname = trim($d['bus_name'] ?: trim($d['name'] . ' ' . $d['lname']));
        $matches[] = [
            'member_id'    => (int) $d['member_id'],
            'client_name'  => $cname !== '' ? $cname : ('Client #' . (int) $d['member_id']),
            'is_same'      => ((int) $d['member_id'] === $current_client_id),
            'bank_name'    => $d['bank_name'],
            'account_type' => $d['account_type'],
            'last4'        => substr(preg_replace('/\D/', '', $d['account_number']), -4),
        ];
    }
    return $matches;
}

/**
 * Recursively remove a directory and all of its contents.
 * Used when a bank account is deleted so its stored statement files are cleaned up.
 */
function deleteDirRecursive($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirRecursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

/**
 * Render the Edit + Delete icon buttons for a bank.
 * Admins get live buttons; everyone else sees greyed-out ("dead") icons that
 * pop up an "admin only" notice when clicked.
 */
function renderBankActionButtons($bank_id, $is_admin)
{
    $bank_id = (int) $bank_id;
    if ($is_admin) {
        return '<button onclick="editBank(' . $bank_id . ')" class="p-2.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit Bank">'
            . '<i class="fas fa-pencil-alt"></i></button>'
            . '<button onclick="deleteBank(' . $bank_id . ')" class="p-2.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Bank">'
            . '<i class="fas fa-trash-alt"></i></button>';
    }

    return '<button onclick="adminOnlyAction(\'edit\')" class="p-2.5 text-gray-300 cursor-not-allowed rounded-lg" title="Edit Bank (Admin only)">'
        . '<i class="fas fa-pencil-alt"></i></button>'
        . '<button onclick="adminOnlyAction(\'delete\')" class="p-2.5 text-gray-300 cursor-not-allowed rounded-lg" title="Delete Bank (Admin only)">'
        . '<i class="fas fa-trash-alt"></i></button>';
}

if (isset($_POST['addBankStatementComment'])) {
    $comment_details = $_POST['comment_details'];
    $current_time = date("Y-m-d H:i:s");

    $associate_query = "INSERT INTO comments(comment_detail, member_id, user_id, comment_time, comment_status, comment_updated_by) VALUES ('$comment_details','$client_id','$userid','$current_time','bank_statement','$userid')";
    $insert_associate = mysqli_query($con, $associate_query);

    if ($insert_associate) {
        $new_comment_id = mysqli_insert_id($con);
        logActivity($con, $userid, 'CREATE', 'bank_statement_comment', $new_comment_id, "New bank statement comment added for Member ID: {$client_id}.");

        $_SESSION['msg'] = "<div class='alert alert-success' role='alert'>
                    <b>Bank Statement Comment Has Been Posted</b>
                  </div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger' role='alert'>
                    <b>Something wrong!</b>
                  </div>" . $con->error;
    }

    header("Location: bank_statements.php?id=" . $client_id . "&status=" . $status);
    exit;
}

if (isset($_POST['edit_comment'])) {
    $comment_id = (int) $_POST['edit_comment_id'];
    $comment_details = $_POST['comment_detail'];
    $current_time = date("Y-m-d H:i:s");

    $update_query = "UPDATE comments 
                     SET comment_detail = '$comment_details',
                         comment_updated_by = '$userid',
                         comment_time = '$current_time'
                     WHERE comment_id = '$comment_id' AND member_id = '$client_id'";

    $update_result = mysqli_query($con, $update_query);

    if ($update_result) {
        logActivity($con, $userid, 'UPDATE', 'bank_statement_comment', $comment_id, "Updated bank statement comment ID: {$comment_id} for Member ID: {$client_id}.");

        $_SESSION['msg'] = "<div class='alert alert-success' role='alert'>
                    <b>Comment Updated Successfully</b>
                  </div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger' role='alert'>
                    <b>Update failed: " . $con->error . "</b>
                  </div>";
    }

    header("Location: bank_statements.php?id=" . $client_id . "&status=" . $status);
    exit;
}

if (isset($_POST['delete_comment'])) {
    $comment_id = (int) $_POST['delete_comment_id'];
    $delete_query = "DELETE FROM comments WHERE comment_id = '$comment_id' AND member_id = '$client_id'";
    $delete_result = mysqli_query($con, $delete_query);

    if ($delete_result) {
        logActivity($con, $userid, 'DELETE', 'bank_statement_comment', $comment_id, "Deleted bank statement comment ID: {$comment_id} for Member ID: {$client_id}.");
        $_SESSION['msg'] = "<div class='alert alert-success'>Comment Deleted Successfully.</div>";
    } else {
        $_SESSION['msg'] = "<div class='alert alert-danger'>Something Went Wrong.</div>" . $con->error;
    }

    header("Location: bank_statements.php?id=" . $client_id . "&status=" . $status);
    exit;
}

// Handle AJAX requests for bank statements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    switch ($_POST['ajax_action']) {
        case 'toggle_statement':
            $bank_id = (int) $_POST['bank_id'];
            $month = (int) $_POST['month'];
            $year = (int) $_POST['year'];
            $is_received = $_POST['received'] === 'true' ? 1 : 0;

            if ($is_received) {
                $received_date = date('Y-m-d');
                $query = "INSERT INTO bank_statements (member_id, bank_id, statement_month, statement_year, received, received_date, received_by) 
                         VALUES ('$client_id', '$bank_id', '$month', '$year', 1, '$received_date', '$userid')
                         ON DUPLICATE KEY UPDATE received=1, received_date='$received_date', received_by='$userid'";
            } else {
                $query = "UPDATE bank_statements SET received=0 WHERE member_id='$client_id' AND bank_id='$bank_id' AND statement_month='$month' AND statement_year='$year'";
            }

            if (mysqli_query($con, $query)) {
                logActivity($con, $userid, 'UPDATE', 'bank_statements', $bank_id, "Updated statement status for bank ID: $bank_id, month: $month, year: $year");
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
            exit;

        case 'add_bank':
            $bank_name = mysqli_real_escape_string($con, trim($_POST['bank_name'] ?? ''));
            $account_number = mysqli_real_escape_string($con, trim($_POST['account_number'] ?? ''));
            $account_type = mysqli_real_escape_string($con, $_POST['account_type'] ?? '');
            $confirm_duplicate = ($_POST['confirm_duplicate'] ?? '') === '1';

            if ($bank_name === '' || $account_number === '') {
                echo json_encode(['status' => 'error', 'message' => 'Bank name and account number are required']);
                exit;
            }

            // Check if the last 4 digits already exist on any client's bank in the CRM.
            $last4 = substr(preg_replace('/\D/', '', $_POST['account_number'] ?? ''), -4);
            if (!$confirm_duplicate && strlen($last4) === 4) {
                $matches = findBankMatchesByLast4($con, $last4, $client_id);
                if (!empty($matches)) {
                    echo json_encode(['status' => 'duplicate', 'last4' => $last4, 'matches' => $matches]);
                    exit;
                }
            }

            $query = "INSERT INTO client_banks (member_id, bank_name, account_number, account_type, added_by, added_date)
                     VALUES ('$client_id', '$bank_name', '$account_number', '$account_type', '$userid', NOW())";

            if (mysqli_query($con, $query)) {
                $new_bank_id = mysqli_insert_id($con);
                logActivity($con, $userid, 'CREATE', 'client_banks', $new_bank_id, "Added new bank: $bank_name for member ID: $client_id");
                echo json_encode(['status' => 'success', 'bank_id' => $new_bank_id]);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
            exit;

        case 'check_last4':
            // Live lookup while the user types the account number.
            $last4 = substr(preg_replace('/\D/', '', $_POST['account_number'] ?? ''), -4);
            $matches = strlen($last4) === 4 ? findBankMatchesByLast4($con, $last4, $client_id) : [];
            echo json_encode(['status' => 'success', 'last4' => $last4, 'matches' => $matches]);
            exit;

        case 'toggle_bank_status':
            $bank_id = (int) $_POST['bank_id'];
            $is_active = (int) $_POST['is_active'] === 1 ? 1 : 0;
            $query = "UPDATE client_banks SET is_active='$is_active' WHERE bank_id='$bank_id' AND member_id='$client_id'";

            if (mysqli_query($con, $query)) {
                $action = $is_active ? 'ACTIVATE' : 'DEACTIVATE';
                $desc = $is_active ? "Reactivated bank ID: $bank_id" : "Deactivated bank ID: $bank_id";
                logActivity($con, $userid, $action, 'client_banks', $bank_id, $desc);
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
            exit;

        case 'edit_bank':
            // Editing a bank account is restricted to admins (user_type 1).
            if ($user_type != '1') {
                echo json_encode(['status' => 'error', 'message' => 'Only an admin can edit a bank account.']);
                exit;
            }

            $bank_id = (int) $_POST['bank_id'];
            $bank_name = mysqli_real_escape_string($con, trim($_POST['bank_name'] ?? ''));
            $account_number = mysqli_real_escape_string($con, trim($_POST['account_number'] ?? ''));
            $account_type = mysqli_real_escape_string($con, $_POST['account_type'] ?? '');

            if ($bank_name === '' || $account_number === '') {
                echo json_encode(['status' => 'error', 'message' => 'Bank name and account number are required']);
                exit;
            }

            $bank_chk = mysqli_query($con, "SELECT bank_id FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id' LIMIT 1");
            if (mysqli_num_rows($bank_chk) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank account']);
                exit;
            }

            $query = "UPDATE client_banks SET bank_name='$bank_name', account_number='$account_number', account_type='$account_type'
                      WHERE bank_id='$bank_id' AND member_id='$client_id'";

            if (mysqli_query($con, $query)) {
                logActivity($con, $userid, 'UPDATE', 'client_banks', $bank_id, "Edited bank: $bank_name for member ID: $client_id");
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
            exit;

        case 'delete_bank':
            // Deleting a bank account is restricted to admins (user_type 1).
            if ($user_type != '1') {
                echo json_encode(['status' => 'error', 'message' => 'Only an admin can delete a bank account.']);
                exit;
            }

            $bank_id = (int) $_POST['bank_id'];
            $bank_chk = mysqli_query($con, "SELECT * FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id' LIMIT 1");
            $bank_row = mysqli_fetch_assoc($bank_chk);
            if (!$bank_row) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank account']);
                exit;
            }

            // Remove statement tracking rows for this bank, then the bank itself.
            mysqli_query($con, "DELETE FROM bank_statements WHERE bank_id='$bank_id' AND member_id='$client_id'");

            if (mysqli_query($con, "DELETE FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id'")) {
                // Clean up any uploaded statement files stored for this bank.
                deleteDirRecursive(__DIR__ . "/Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}");
                logActivity($con, $userid, 'DELETE', 'client_banks', $bank_id, "Deleted bank: {$bank_row['bank_name']} for member ID: $client_id");
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => mysqli_error($con)]);
            }
            exit;

        case 'upload_statement_file':
            $bank_id = (int) $_POST['bank_id'];
            $month = (int) $_POST['month'];
            $year = (int) $_POST['year'];

            if ($month < 0 || $month > 11) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid month']);
                exit;
            }

            $bank_check = mysqli_query($con, "SELECT bank_id FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id' LIMIT 1");
            if (mysqli_num_rows($bank_check) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank account']);
                exit;
            }

            if (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['status' => 'error', 'message' => 'Please select a valid file']);
                exit;
            }

            $original_name = $_FILES['statement_file']['name'];
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'csv', 'xls', 'xlsx'];
            if (!in_array($ext, $allowed, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Unsupported file type']);
                exit;
            }

            $safe_original = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original_name));
            $target_dir = __DIR__ . "/Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}/{$year}";
            if (!is_dir($target_dir) && !mkdir($target_dir, 0775, true)) {
                echo json_encode(['status' => 'error', 'message' => 'Unable to create storage folder']);
                exit;
            }

            $new_name = "m{$month}_" . time() . "_{$safe_original}";
            $target_file = $target_dir . '/' . $new_name;

            if (!move_uploaded_file($_FILES['statement_file']['tmp_name'], $target_file)) {
                echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
                exit;
            }

            $relative_path = "Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}/{$year}/{$new_name}";
            logActivity($con, $userid, 'UPLOAD', 'bank_statements', $bank_id, "Uploaded statement file for bank ID: $bank_id, month: $month, year: $year");
            echo json_encode(['status' => 'success', 'file_url' => $relative_path, 'file_name' => $new_name]);
            exit;

        case 'send_reminder':
            $bank_id = (int) $_POST['bank_id'];
            $year = (int) $_POST['year'];
            $requested_missing_months = json_decode($_POST['missing_months'] ?? '[]', true);

            $bank_query = mysqli_query($con, "SELECT * FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id' AND is_active=1");
            $bank_data = mysqli_fetch_assoc($bank_query);
            if (!$bank_data) {
                echo json_encode(['status' => 'error', 'message' => 'Bank is inactive or not found']);
                exit;
            }

            // Always derive the final set from current DB state so no month is accidentally omitted.
            $auto_missing_months = getMissingMonthsByYear($con, $client_id, $bank_id, $year);
            $missing_months = $auto_missing_months;
            if (is_array($requested_missing_months) && !empty($requested_missing_months)) {
                $safe_requested = [];
                foreach ($requested_missing_months as $month) {
                    $month = (int) $month;
                    if ($month >= 0 && $month <= 11) {
                        $safe_requested[] = $month;
                    }
                }
                $safe_requested = array_values(array_unique($safe_requested));
                $intersection = array_values(array_intersect($auto_missing_months, $safe_requested));
                if (!empty($intersection)) {
                    $missing_months = $intersection;
                }
            }

            if (empty($missing_months)) {
                echo json_encode(['status' => 'error', 'message' => 'No missing months found for this active bank account.']);
                exit;
            }

            $email_sent = sendStatementReminderEmail($client_data, $bank_data, $missing_months, $year);

            if ($email_sent) {
                logActivity($con, $userid, 'CREATE', 'email_notifications', $bank_id, "Sent reminder email for missing statements");
                echo json_encode(['status' => 'success', 'months_used' => $missing_months]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to send email']);
            }
            exit;

        case 'get_year_data':
            $bank_id = (int) $_POST['bank_id'];
            $year = (int) $_POST['year'];

            $received_months = getReceivedMonthsByYear($con, $client_id, $bank_id, $year);
            $missing_months = getMissingMonthsByYear($con, $client_id, $bank_id, $year);

            $statement_files = getStatementFilesByYear($client_id, $bank_id, $year);
            echo json_encode([
                'received_months' => $received_months,
                'missing_months' => $missing_months,
                'statement_files' => $statement_files
            ]);
            exit;

        case 'delete_statement_file':
            $bank_id = (int) $_POST['bank_id'];
            $month   = (int) $_POST['month'];
            $year    = (int) $_POST['year'];
            $file_name = isset($_POST['file_name']) ? basename($_POST['file_name']) : '';

            if (empty($file_name) || $month < 0 || $month > 11) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
                exit;
            }

            $bank_chk = mysqli_query($con, "SELECT bank_id FROM client_banks WHERE bank_id='$bank_id' AND member_id='$client_id' LIMIT 1");
            if (mysqli_num_rows($bank_chk) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid bank account']);
                exit;
            }

            if (!preg_match('/^m\d{1,2}_/', $file_name)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid file name format']);
                exit;
            }

            $del_path = __DIR__ . "/Uploads/bank_statements/client_{$client_id}/bank_{$bank_id}/{$year}/{$file_name}";
            if (!file_exists($del_path)) {
                echo json_encode(['status' => 'error', 'message' => 'File not found']);
                exit;
            }

            if (unlink($del_path)) {
                logActivity($con, $userid, 'DELETE', 'bank_statements', $bank_id, "Deleted statement file for bank ID: $bank_id, month: $month, year: $year");
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to delete file']);
            }
            exit;
    }
}

function sendStatementReminderEmail($client, $bank, $missing_months, $year)
{
    return true;
}

 $banks_query = mysqli_query($con, "SELECT * FROM client_banks WHERE member_id='$client_id' ORDER BY is_active DESC, bank_id DESC");
 $banks = [];
 $active_banks = [];
 $inactive_banks = [];
while ($bank = mysqli_fetch_assoc($banks_query)) {
    $banks[] = $bank;
    if ((int) $bank['is_active'] === 1) {
        $active_banks[] = $bank;
    } else {
        $inactive_banks[] = $bank;
    }
}

 $statement_status = [];
 $current_year = date('Y');
for ($year = $current_year; $year >= $current_year - 2; $year--) {
    $stmt_query = mysqli_query($con, "SELECT * FROM bank_statements WHERE member_id='$client_id' AND statement_year='$year'");
    while ($stmt = mysqli_fetch_assoc($stmt_query)) {
        $key = $stmt['bank_id'] . '_' . $stmt['statement_year'] . '_' . $stmt['statement_month'];
        $statement_status[$key] = $stmt;
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Statements - <?= htmlspecialchars($client_name) ?> | Syed Professional Services</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php include "includes/links.php"; ?>
    
    <style>
        body { font-family: 'Inter', sans-serif; }
        .statement-checkbox {
            appearance: none;
            width: 1.5rem;
            height: 1.5rem;
            border: 2px solid #d1d5db;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .statement-checkbox:checked {
            background-color: #10b981;
            border-color: #10b981;
        }
        .statement-checkbox:checked::after {
            content: '\2713';
            position: absolute;
            color: white;
            font-size: 1rem;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%); 
        }
        .statement-checkbox:hover { border-color: #6FD943; }
        .bank-card { transition: all 0.3s ease; }
        .bank-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .flow-step { position: relative; }
        .flow-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 50%;
            right: -1.5rem;
            width: 1.5rem;
            height: 2px;
            background: linear-gradient(90deg, #6FD943 0%, #3b82f6 100%);
            z-index: 0;
        }
        @media (max-width: 768px) {
            .flow-step:not(:last-child)::after { display: none; }
        }
        .email-preview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-family: 'Georgia', serif;
        }
        .month-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
        }
        @media (min-width: 640px) { .month-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (min-width: 1024px) { .month-grid { grid-template-columns: repeat(6, 1fr); } }
        .month-item {
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 2px solid #e5e7eb;
            transition: all 0.2s;
            cursor: pointer;
            text-align: center;
        }
        .month-item:hover { border-color: #6FD943; transform: translateY(-2px); }
        .month-item.received { background-color: #ecfdf5; border-color: #10b981; }
        .month-item.missing { background-color: #fef2f2; border-color: #ef4444; }
        .nav-tab {
            position: relative;
            padding-bottom: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-tab:hover { color: #374151; }
        .nav-tab.active { color: #6FD943; }
        .nav-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #6FD943;
        }
        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        }
        .update-success { animation: pulse-green 0.5s ease; }
        .sticky-email { position: sticky; top: 100px; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 3px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        /* Keep SweetAlert popups above Bootstrap modals (e.g. the "Add Anyway?" prompt over Add Bank). */
        .swal2-container { z-index: 20000 !important; }
    </style>
</head>
<body class="theme-3 bg-gray-50">
    <div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>
    
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <?php if (!empty($msg)): ?>
                <div class="mb-4">
                    <?= $msg ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($business_name) ?></h1>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        </div>
                        <p class="text-gray-500 flex items-center gap-2 text-sm">
                            <i class="fas fa-user text-gray-400"></i>
                            <span class="font-medium text-gray-700"><?= htmlspecialchars($client_name) ?></span>
                            <span class="mx-2 text-gray-300">|</span>
                            <i class="fas fa-hashtag text-gray-400"></i>
                            <span>Client #<?= $client_id ?></span>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="edit_client.php?id=<?= $client_id ?>" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            <i class="fas fa-pencil-alt mr-2"></i>Edit Profile
                        </a>
                        <button onclick="openAddBankModal()" class="px-4 py-2 bg-[#6FD943] text-white rounded-lg text-sm font-medium hover:bg-[#5bc73a] transition shadow-sm">
                            <i class="fas fa-plus mr-2"></i>Add Bank Account
                        </button>
                    </div>
                </div>

                <div class="mt-6 border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 overflow-x-auto">
                        <a href="client_details.php?id=<?= $client_id ?>&status=<?= $status ?>" class="nav-tab whitespace-nowrap">Profile</a>
                        <a href="bank_statements.php?id=<?= $client_id ?>&status=<?= $status ?>" class="nav-tab active whitespace-nowrap">
                            <i class="fas fa-university mr-1"></i>Bank Statements
                        </a>
                    </nav>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="xl:col-span-2 space-y-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                        <h3 class="font-semibold text-blue-900 mb-1"><i class="fas fa-folder-open mr-1"></i> Digital Manila Folder</h3>
                        <p class="text-sm text-blue-800">
                            Monthly statement uploads are stored in
                            <code class="bg-blue-100 px-1 rounded">Uploads/bank_statements/client_<?= (int) $client_id ?></code>
                            with month-based files (`m0` to `m11`) so each month can be linked and reviewed before emailing.
                        </p>
                    </div>
                    
                    <?php if (empty($active_banks)): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-university text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Active Bank Accounts</h3>
                            <p class="text-gray-500 mb-6 max-w-md mx-auto">Add a bank account or reactivate an inactive one to track statements and include it in email communications.</p>
                            <button onclick="openAddBankModal()" class="px-6 py-3 bg-[#6FD943] text-white rounded-lg font-medium hover:bg-[#5bc73a] transition">
                                <i class="fas fa-plus mr-2"></i>Add First Bank Account
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($active_banks as $bank):
                        $bank_id = $bank['bank_id'];
                        $account_last4 = substr($bank['account_number'], -4);
                        $statement_files = getStatementFilesByYear($client_id, $bank_id, $current_year);
                        ?>
                        <div class="bank-card bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" id="bank-<?= $bank_id ?>" data-active="1">
                            <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white flex items-center justify-center text-xl shadow-lg">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($bank['bank_name']) ?></h3>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($bank['account_type']) ?> **** <?= $account_last4 ?></p>
                                            <p class="text-xs text-green-700 mt-1"><i class="fas fa-check-circle mr-1"></i>Active in reminder emails</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 w-full sm:w-auto justify-between sm:justify-end">
                                        <select class="year-select bg-white border border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-[#6FD943] focus:border-[#6FD943] block p-2.5" 
                                                data-bank-id="<?= $bank_id ?>" onchange="changeYear(<?= $bank_id ?>, this.value)">
                                            <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                                    <option value="<?= $y ?>" <?= $y == $current_year ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>

                                            <button onclick="viewAllStatements(<?= $bank_id ?>, '<?= htmlspecialchars($bank['bank_name']) ?>')"
                                                    class="p-2.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View All Statement Files">
                                                <i class="fas fa-folder-open"></i>
                                            </button>
                                            <button onclick="setBankStatus(<?= $bank_id ?>, 0)" class="p-2.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Deactivate Bank">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <?= renderBankActionButtons($bank_id, $is_admin) ?>

                                    </div>
                                </div>
                            
                                <div class="mt-4 flex items-center gap-4">
                                    <div class="flex-1">
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-gray-600">Statements Received</span>
                                            <span class="font-semibold text-gray-900" id="progress-text-<?= $bank_id ?>">0/12</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-[#6FD943] h-2.5 rounded-full transition-all duration-500" id="progress-bar-<?= $bank_id ?>" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button onclick="sendReminder(<?= $bank_id ?>)" class="text-sm text-[#6FD943] hover:text-[#5bc73a] font-medium flex items-center gap-1 transition">
                                            <i class="fas fa-paper-plane"></i> Send Reminder
                                        </button>
                                        <button onclick="markAllAsReceived(<?= $bank_id ?>)" class="text-sm text-[#6FD943] hover:text-[#5bc73a] font-medium flex items-center gap-1 transition ml-2">
                                            <i class="fas fa-check-double"></i> Mark All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        
                            <div class="p-5">
                                <div class="mb-3 text-xs text-gray-500">
                                    Each month supports its own statement file upload and link for email follow-up.
                                </div>
                                <div class="month-grid" id="month-grid-<?= $bank_id ?>">
                                    <?php
                                    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                    foreach ($months as $index => $month_name):
                                        $key = $bank_id . '_' . $current_year . '_' . $index;
                                        $is_received = isset($statement_status[$key]) && $statement_status[$key]['received'] == 1;
                                        ?>
                                        <?php $month_file = $statement_files[$index] ?? null; ?>
                                        <div class="month-item <?= $is_received ? 'received' : 'missing' ?>" 
                                             data-month="<?= $index ?>" data-received="<?= $is_received ? '1' : '0' ?>" data-year="<?= $current_year ?>"
                                             onclick="toggleStatement(<?= $bank_id ?>, <?= $index ?>, this)">
                                            <div class="flex flex-col items-center gap-2">
                                                <input type="checkbox" class="statement-checkbox" <?= $is_received ? 'checked' : '' ?> 
                                                       onclick="event.stopPropagation()">
                                                <span class="font-semibold text-sm text-gray-700"><?= $month_name ?></span>
                                                <span class="text-xs font-medium status-text <?= $is_received ? 'text-green-600' : 'text-red-600' ?>">
                                                    <?= $is_received ? 'Received' : 'Missing' ?>
                                                </span>
                                            </div>
                                            <?php if ($is_received && isset($statement_status[$key]['received_date'])): ?>
                                                <div class="text-xs text-gray-400 mt-2 text-center received-date"><?= date('M d', strtotime($statement_status[$key]['received_date'])) ?></div>
                                            <?php endif; ?>
                                            <div class="mt-2 flex items-center justify-center gap-2" onclick="event.stopPropagation()">
                                                <input type="file" id="file-input-<?= $bank_id ?>-<?= $index ?>" class="hidden" accept=".pdf,.jpg,.jpeg,.png,.csv,.xls,.xlsx"
                                                       onchange="uploadStatementFile(<?= $bank_id ?>, <?= $index ?>, this)">
                                                <button type="button" class="text-[11px] px-2 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100"
                                                        onclick="openFilePicker(event, <?= $bank_id ?>, <?= $index ?>)">
                                                    Upload
                                                </button>
                                                <a href="<?= $month_file ? htmlspecialchars($month_file['url']) : '#' ?>" target="_blank"
                                                   id="file-link-<?= $bank_id ?>-<?= $index ?>"
                                                   data-file-name="<?= $month_file ? htmlspecialchars($month_file['name']) : '' ?>"
                                                   class="text-[11px] px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 <?= $month_file ? '' : 'hidden' ?>">
                                                    View
                                                </a>
                                                <button type="button" id="file-del-<?= $bank_id ?>-<?= $index ?>"
                                                        onclick="deleteStatementFile(<?= $bank_id ?>, <?= $index ?>)"
                                                        class="text-[11px] px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100 <?= $month_file ? '' : 'hidden' ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            <div id="file-name-<?= $bank_id ?>-<?= $index ?>" class="text-[10px] text-gray-500 mt-1 truncate <?= $month_file ? '' : 'hidden' ?>">
                                                <?= $month_file ? htmlspecialchars($month_file['name']) : '' ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?> 
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($inactive_banks)): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-archive text-gray-400"></i> Inactive Bank Accounts
                            </h3>
                            <p class="text-sm text-gray-500 mb-4">Inactive accounts are excluded from reminder emails until reactivated.</p>
                            <div class="space-y-3">
                                <?php foreach ($inactive_banks as $inactive_bank): ?>
                                    <div class="flex items-center justify-between bg-gray-50 border border-gray-200 rounded-lg p-3">
                                        <div>
                                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($inactive_bank['bank_name']) ?></div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($inactive_bank['account_type']) ?> **** <?= substr($inactive_bank['account_number'], -4) ?></div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button onclick="setBankStatus(<?= (int) $inactive_bank['bank_id'] ?>, 1)" class="px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200">
                                                <i class="fas fa-power-off mr-1"></i>Reactivate
                                            </button>
                                            <?= renderBankActionButtons((int) $inactive_bank['bank_id'], $is_admin) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <h5 style="margin:0; font-size:1.1rem; font-weight:700;"><i class="fas fa-comment-dots text-gray-400 mr-2"></i>Bank Statement Comments</h5>
                            <div>
                                <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBankStatementComment">Add Comment</button>
                                <button class="btn btn-secondary btn-sm" onclick="printBankStatementComments()">Print</button>
                            </div>
                        </div>
                        <hr style="margin-top:0;">
                        <div class="table-responsive">
                            <table class="table datatable1">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>By</th>
                                        <th>Detail</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $bankStatementCommentsSql = "SELECT comments.*,
                                                creator.user_login AS comment_by,
                                                updater.user_login AS updated_by_name
                                            FROM comments
                                            LEFT JOIN users AS creator ON comments.user_id = creator.user_id
                                            LEFT JOIN users AS updater ON comments.comment_updated_by = updater.user_id
                                            WHERE member_id='$client_id'
                                                AND comment_status='bank_statement'
                                            ORDER BY comment_id DESC";

                                    $bankStatementCommentsQuery = mysqli_query($con, $bankStatementCommentsSql);
                                    $bankStatementCommentsCount = mysqli_num_rows($bankStatementCommentsQuery);
                                    if ($bankStatementCommentsCount === 0) {
                                        echo '<tr><td colspan="4" class="text-center text-muted">No bank statement comments yet.</td></tr>';
                                    }
                                    while ($bankCommentRow = mysqli_fetch_array($bankStatementCommentsQuery)) {
                                        $commentdate = $bankCommentRow['comment_time'];
                                        $comment_by = $bankCommentRow['comment_by'] ?? 'Unknown';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php
                                                $timestamp = strtotime($commentdate);
                                                echo date("m/d/Y", $timestamp) . "<br>" . date("H:i:s", $timestamp);
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($comment_by); ?></td>
                                            <td style="max-width: 300px; white-space: normal; word-wrap: break-word;">
                                                <div style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.5em;">
                                                    <?php echo strip_tags($bankCommentRow["comment_detail"], '<p><br><b><i><strong><em>'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="openViewModal(
                                                    '<?php echo htmlspecialchars(json_encode($comment_by), ENT_QUOTES, 'UTF-8'); ?>',
                                                    '<?php echo htmlspecialchars(json_encode($bankCommentRow['updated_by_name']), ENT_QUOTES, 'UTF-8'); ?>',
                                                    '<?php echo htmlspecialchars(json_encode($commentdate), ENT_QUOTES, 'UTF-8'); ?>',
                                                    '<?php echo htmlspecialchars(json_encode(strip_tags($bankCommentRow['comment_detail'], '<p><br><b><i><strong><em>')), ENT_QUOTES, 'UTF-8'); ?>'
                                                )">View</button>
                                                <button class="btn btn-sm btn-warning" onclick="openEditModal(
                                                    <?php echo (int) $bankCommentRow['comment_id']; ?>,
                                                    `<?php echo addslashes(strip_tags($bankCommentRow['comment_detail'])); ?>`
                                                )">Edit</button>
                                                <button class="btn btn-sm btn-danger" onclick="openDeleteModal(
                                                    <?php echo (int) $bankCommentRow['comment_id']; ?>
                                                )">Delete</button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="printableBankStatementComments" style="display: none;">
                            <h2>All Bank Statement Comments</h2>
                            <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>By</th>
                                        <th>Detail</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (isset($bankStatementCommentsQuery) && $bankStatementCommentsCount > 0) {
                                        mysqli_data_seek($bankStatementCommentsQuery, 0);
                                        $count = 1;
                                        while ($bankCommentRow = mysqli_fetch_array($bankStatementCommentsQuery)) {
                                            $commentdate = $bankCommentRow['comment_time'];
                                            $comment_by = $bankCommentRow['comment_by'] ?? 'Unknown';
                                            ?>
                                            <tr>
                                                <td><?php echo $count++; ?></td>
                                                <td><?php echo date('m/d/Y H:i:s', strtotime($commentdate)); ?></td>
                                                <td><?php echo htmlspecialchars($comment_by); ?></td>
                                                <td><?php echo strip_tags($bankCommentRow["comment_detail"], '<p><br><b><i><strong><em>'); ?></td>
                                            </tr>
                                        <?php }
                                    } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-robot text-[#6FD943]"></i>
                            Email Sending Workflow
                        </h3>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 relative">
                            <div class="flow-step bg-gradient-to-br from-indigo-50 to-white border border-indigo-100 rounded-xl p-4 text-center hover:shadow-md transition group">
                                <div class="w-12 h-12 mx-auto bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-indigo-600 group-hover:text-white transition shadow-sm">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h4 class="font-semibold text-gray-900 text-sm">1. Scan</h4>
                                <p class="text-xs text-gray-500 mt-1">Identify missing statements</p>
                            </div>
                            <div class="flow-step bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-xl p-4 text-center hover:shadow-md transition group">
                                <div class="w-12 h-12 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-blue-600 group-hover:text-white transition shadow-sm">
                                    <i class="fas fa-database"></i>
                                </div>
                                <h4 class="font-semibold text-gray-900 text-sm">2. Fetch Info</h4>
                                <p class="text-xs text-gray-500 mt-1">Client & account details</p>
                            </div>
                            <div class="flow-step bg-gradient-to-br from-purple-50 to-white border border-purple-100 rounded-xl p-4 text-center hover:shadow-md transition group">
                                <div class="w-12 h-12 mx-auto bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-purple-600 group-hover:text-white transition shadow-sm">
                                    <i class="fas fa-envelope-open-text"></i>
                                </div>
                                <h4 class="font-semibold text-gray-900 text-sm">3. Generate</h4>
                                <p class="text-xs text-gray-500 mt-1">Draft personalized email</p>
                            </div>
                            <div class="flow-step bg-gradient-to-br from-green-50 to-white border border-green-100 rounded-xl p-4 text-center hover:shadow-md transition group">
                                <div class="w-12 h-12 mx-auto bg-green-100 text-green-600 rounded-full flex items-center justify-center mb-3 group-hover:bg-green-600 group-hover:text-white transition shadow-sm">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <h4 class="font-semibold text-gray-900 text-sm">4. Send</h4>
                                <p class="text-xs text-gray-500 mt-1">Email client & log activity</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-center gap-3">
                            <button onclick="runAutomationNow()" class="bg-gray-900 hover:bg-gray-800 text-white px-6 py-2.5 rounded-lg text-sm font-medium shadow-lg shadow-gray-200 transition flex items-center gap-2">
                                <i class="fas fa-paper-plane"></i> Send Reminder Email
                            </button>
                            
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-1">
                    <div class="sticky-email space-y-6">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-envelope text-gray-400"></i> Live Email Preview
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">Updates automatically as you check/uncheck boxes</p>
                            </div>
                            <div class="email-preview m-4 p-4 text-sm">
                                <div class="border-b border-gray-200 pb-3 mb-3 space-y-1 text-xs text-gray-500">
                                    <div><strong>To:</strong> <?= htmlspecialchars($client_data['prm_email'] ?: 'client@example.com') ?></div>
                                    <div><strong>From:</strong> accounting@syedpro.com</div>
                                    <div><strong>Subject:</strong> <span class="text-[#6FD943]">Action Required: Missing Bank Statements</span></div>
                                </div>
                                <div class="space-y-3 text-gray-700 leading-relaxed" id="email-body-content">
                                    <p>Dear <strong><?= htmlspecialchars($client_name) ?></strong>,</p>
                                    <p>We are preparing your financial records for <strong>Tax Year <span id="email-year"><?= $current_year ?></span></strong>. Please provide the missing bank statements listed below:</p>
                                    <div id="missing-statements-list" class="space-y-2 my-4"></div>
                                    <p>Please upload these documents through your client portal or reply to this email.</p>
                                    <p>Best regards,<br><strong>Syed Professional Services</strong></p>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-100 bg-gray-50 flex justify-between items-center">
                                <span class="text-xs text-gray-500" id="email-status">Ready to send</span>
                                <button onclick="copyEmailToClipboard()" class="text-[#6FD943] hover:text-[#5bc73a] text-sm font-semibold flex items-center gap-1 transition">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-chart-pie text-gray-400"></i> Statement Summary
                            </h4>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm text-gray-600">Active Banks</span>
                                    <span class="font-bold text-gray-900 text-lg"><?= count($active_banks) ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
                                    <span class="text-sm text-yellow-700">Inactive Banks</span>
                                    <span class="font-bold text-yellow-700 text-lg"><?= count($inactive_banks) ?></span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                                    <span class="text-sm text-red-600">Total Missing</span>
                                    <span class="font-bold text-red-600 text-lg" id="total-missing">0</span>
                                </div>
                                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                                    <span class="text-sm text-green-600">Received This Year</span>
                                    <span class="font-bold text-green-600 text-lg" id="total-received">0</span>
                                </div>
                                <div class="pt-3 border-t border-gray-100">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm text-gray-600">Completion Rate</span>
                                        <span class="font-bold text-[#6FD943]" id="completion-rate">0%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-gradient-to-r from-[#6FD943] to-green-400 h-3 rounded-full transition-all duration-500 shadow-sm" id="completion-bar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                            <h4 class="font-semibold text-gray-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-history text-gray-400"></i> Recent Activity
                            </h4>
                            <div class="space-y-3 max-h-64 overflow-y-auto custom-scroll pr-2" id="activity-log">
                                <?php
                                $activity_query = mysqli_query($con, "SELECT al.*, u.user_login 
                                    FROM activity_logs al 
                                    LEFT JOIN users u ON al.user_id = u.user_id 
                                    WHERE al.object_type IN ('bank_statements', 'client_banks') 
                                    AND (
                                        (al.object_type = 'client_banks' AND al.object_id IN (SELECT bank_id FROM client_banks WHERE member_id='$client_id'))
                                        OR 
                                        (al.object_type = 'bank_statements' AND al.description LIKE '%member ID: $client_id%')
                                    )
                                    ORDER BY al.created_at DESC LIMIT 10");

                                if (mysqli_num_rows($activity_query) > 0):
                                    while ($activity = mysqli_fetch_assoc($activity_query)):
                                        $icon = 'fa-history';
                                        $icon_color = 'text-gray-400';
                                        $bg_color = 'bg-gray-100';
                                        switch ($activity['action']) {
                                            case 'CREATE':
                                                $icon = 'fa-plus';
                                                $icon_color = 'text-green-500';
                                                $bg_color = 'bg-green-100';
                                                break;
                                            case 'UPDATE':
                                                $icon = 'fa-edit';
                                                $icon_color = 'text-blue-500';
                                                $bg_color = 'bg-blue-100';
                                                break;
                                            case 'DELETE':
                                                $icon = 'fa-trash';
                                                $icon_color = 'text-red-500';
                                                $bg_color = 'bg-red-100';
                                                break;
                                            case 'EMAIL':
                                                $icon = 'fa-envelope';
                                                $icon_color = 'text-purple-500';
                                                $bg_color = 'bg-purple-100';
                                                break;
                                        }
                                        ?>
                                        <div class="flex gap-3 text-sm p-2 hover:bg-gray-50 rounded-lg transition">
                                            <div class="w-8 h-8 rounded-full <?= $bg_color ?> flex items-center justify-center flex-shrink-0">
                                                <i class="fas <?= $icon ?> <?= $icon_color ?> text-xs"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-gray-800 font-medium truncate"><?= htmlspecialchars($activity['action']) ?>         <?= htmlspecialchars($activity['object_type']) ?></p>
                                                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($activity['description']) ?></p>
                                                <p class="text-xs text-gray-400 mt-1">by <?= htmlspecialchars($activity['user_login'] ?: 'System') ?> • <?= date('M d, g:i A', strtotime($activity['created_at'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; else: ?>
                                    <p class="text-sm text-gray-500 text-center py-4">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addBankStatementComment">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Add Bank Statement Comment</h4>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-2">
                            <div class="col-md-12">
                                <label class="form-label">Quick phrases</label>
                                <div class="mb-2">
                                    <?php
                                    $uid = (int) $_SESSION['user_id'];
                                    $res = mysqli_query(
                                        $con,
                                        "SELECT phrase FROM user_quick_phrases WHERE user_id=$uid ORDER BY id"
                                    );
                                    while ($row = mysqli_fetch_assoc($res)) {
                                        echo '<button type="button" class="btn btn-sm btn-outline-primary me-1 mb-1" onclick="addPhraseBankStatement(\'' . htmlspecialchars($row['phrase']) . '\')">' . htmlspecialchars($row['phrase']) . '</button>';
                                    }
                                    ?>
                                    <a href="quickphrases.php" class="btn btn-primary btn-sm">Edit Phrases</a>
                                </div>

                                <label for="comment_details_bank_statement" class="form-label">Comment (edit or type)</label>
                                <textarea class="ckeditor" name="comment_details" id="comment_details_bank_statement"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-info" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="addBankStatementComment">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="viewCommentModal">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow rounded-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Comment Details</h5>
                </div>
                <div class="modal-body px-4 pt-3 pb-0" id="viewCommentBody"></div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCommentModal">
        <div class="modal-dialog modal-lg">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #6FD943; color: white;">
                        <h5 class="modal-title">Edit Comment</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_comment_id" id="edit_comment_id">
                        <div class="form-group">
                            <label>Comment:</label>
                            <textarea name="comment_detail" id="edit_comment_detail" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_comment" class="btn" style="background-color: #6FD943; color: white;">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="deleteCommentModal">
        <div class="modal-dialog modal-md">
            <form method="POST">
                <div class="modal-content">
                    <div class="modal-body">
                        <input type="hidden" name="delete_comment_id" id="delete_comment_id">
                        <div class="swal2-icon swal2-warning swal2-icon-show" style="display: flex;">
                            <div class="swal2-icon-content">!</div>
                        </div>
                        <h2 class="swal2-title">Are you sure?</h2>
                        <div class="swal2-html-container">This action cannot be undone. Do you want to continue?</div>
                    </div>
                    <div class="modal-footer" style="justify-content: center;">
                        <button type="button" class="btn btn-info" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" name="delete_comment">Yes Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addBankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-b border-gray-200">
                    <h5 class="modal-title font-bold text-gray-900">Add Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addBankForm" onsubmit="submitAddBank(event)">
                    <div class="modal-body p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                                <select name="bank_name" id="bank_name_select" required onchange="handleBankNameChange(this)" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]">
                                    <option value="">Select Bank</option>
                                    <option value="BANK OF AMERICA">BANK OF AMERICA</option>
                                    <option value="CHASE BANK">CHASE BANK</option>
                                    <option value="TD BANK">TD BANK</option>
                                    <option value="CITI">CITI</option>
                                    <option value="CITIZEN">CITIZEN</option>
                                    <option value="CAPITAL ONE">CAPITAL ONE</option>
                                    <option value="NORTH FORK">NORTH FORK</option>
                                    <option value="HUNTINGTON">HUNTINGTON</option>
                                    <option value="M&T BANK">M&T BANK</option>
                                    <option value="COMERCIA BANK">COMERCIA BANK</option>
                                    <option value="OTHER">OTHER</option>
                                </select>
                                <input type="text" name="bank_name_other" id="bank_name_other" maxlength="100"
                                       class="hidden w-full border border-gray-300 rounded-lg px-3 py-2 mt-2 focus:ring-[#6FD943] focus:border-[#6FD943]"
                                       placeholder="Enter bank name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                                <input type="text" name="account_number" id="account_number_input" required oninput="onAccountNumberInput()" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]" placeholder="•••• •••• •••• 4521">
                                <div id="bank_match_hint" class="mt-2 text-sm hidden"></div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type <span class="text-red-500">*</span></label>
                            <select name="account_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]">
                                <option value="">Select Type</option>
                                <option value="Checking">Checking</option>
                                <option value="Savings">Savings</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Line of Credit">Line of Credit</option>
                                <option value="Investment">Investment</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-200 p-4 flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-[#6FD943] text-white rounded-lg hover:bg-[#5bc73a] transition">
                            <i class="fas fa-plus mr-1"></i> Add Bank
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBankModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-b border-gray-200">
                    <h5 class="modal-title font-bold text-gray-900">Edit Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editBankForm" onsubmit="submitEditBank(event)">
                    <input type="hidden" name="bank_id" id="edit_bank_id">
                    <div class="modal-body p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name <span class="text-red-500">*</span></label>
                                <select name="bank_name" id="edit_bank_name_select" required onchange="handleEditBankNameChange(this)" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]">
                                    <option value="">Select Bank</option>
                                    <option value="BANK OF AMERICA">BANK OF AMERICA</option>
                                    <option value="CHASE BANK">CHASE BANK</option>
                                    <option value="TD BANK">TD BANK</option>
                                    <option value="CITI">CITI</option>
                                    <option value="CITIZEN">CITIZEN</option>
                                    <option value="CAPITAL ONE">CAPITAL ONE</option>
                                    <option value="NORTH FORK">NORTH FORK</option>
                                    <option value="HUNTINGTON">HUNTINGTON</option>
                                    <option value="M&T BANK">M&T BANK</option>
                                    <option value="COMERCIA BANK">COMERCIA BANK</option>
                                    <option value="OTHER">OTHER</option>
                                </select>
                                <input type="text" name="bank_name_other" id="edit_bank_name_other" maxlength="100"
                                       class="hidden w-full border border-gray-300 rounded-lg px-3 py-2 mt-2 focus:ring-[#6FD943] focus:border-[#6FD943]"
                                       placeholder="Enter bank name">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Account Number <span class="text-red-500">*</span></label>
                                <input type="text" name="account_number" id="edit_account_number_input" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]" placeholder="•••• •••• •••• 4521">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Account Type <span class="text-red-500">*</span></label>
                            <select name="account_type" id="edit_account_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#6FD943] focus:border-[#6FD943]">
                                <option value="">Select Type</option>
                                <option value="Checking">Checking</option>
                                <option value="Savings">Savings</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Line of Credit">Line of Credit</option>
                                <option value="Investment">Investment</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-t border-gray-200 p-4 flex justify-end gap-2">
                        <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-[#6FD943] text-white rounded-lg hover:bg-[#5bc73a] transition">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View All Statements Modal -->
    <div class="modal fade" id="viewAllStatementsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#6FD943,#3b82f6);color:white;">
                    <h5 class="modal-title font-bold" id="viewAllStatementsTitle">
                        <i class="fas fa-folder-open mr-2"></i>All Statement Files
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4" id="viewAllStatementsBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
    <script>
        const clientId = <?= $client_id ?>;
        const apiEndpoint = '<?= $page_endpoint ?>?id=' + clientId;
        const currentYear = <?= $current_year ?>;
        const isAdmin = <?= $is_admin ? 'true' : 'false' ?>;
        let bankData = {};

        // Full details (incl. account number) for every bank, used to prefill the edit modal.
        const allBanks = {
            <?php foreach ($banks as $b): ?>
            <?= (int) $b['bank_id'] ?>: {
                bank_name: <?= json_encode($b['bank_name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                account_number: <?= json_encode($b['account_number'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                account_type: <?= json_encode($b['account_type'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            },
            <?php endforeach; ?>
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeBanks();
            updateEmailPreview();
            updateSummaryStats();
        });

        function initializeBanks() {
            <?php foreach ($active_banks as $bank): ?>
                bankData[<?= $bank['bank_id'] ?>] = {
                    name: "<?= htmlspecialchars($bank['bank_name']) ?>",
                    last4: "<?= substr($bank['account_number'], -4) ?>",
                    type: "<?= htmlspecialchars($bank['account_type']) ?>",
                    year: currentYear,
                    isActive: true
                };
                updateBankProgress(<?= $bank['bank_id'] ?>);
            <?php endforeach; ?>
        }

        function toggleStatement(bankId, month, element) {
            const year = parseInt(element.dataset.year || currentYear, 10);
            const checkbox = element.querySelector('input[type="checkbox"]');
            const isReceived = !checkbox.checked; // Toggle state
            checkbox.checked = isReceived;
            
            // Visual update
            const statusText = element.querySelector('.status-text');
            if (isReceived) {
                element.classList.remove('missing');
                element.classList.add('received');
                statusText.textContent = 'Received';
                statusText.classList.remove('text-red-600');
                statusText.classList.add('text-green-600');
                element.dataset.received = '1';
                
                // Add received date
                if (!element.querySelector('.received-date')) {
                    const dateDiv = document.createElement('div');
                    dateDiv.className = 'text-xs text-gray-400 mt-2 text-center received-date';
                    dateDiv.textContent = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    element.appendChild(dateDiv);
                }
            } else {
                element.classList.remove('received');
                element.classList.add('missing');
                statusText.textContent = 'Missing';
                statusText.classList.remove('text-green-600');
                statusText.classList.add('text-red-600');
                element.dataset.received = '0';
                const dateDiv = element.querySelector('.received-date');
                if (dateDiv) dateDiv.remove();
            }
            
            element.classList.add('update-success');
            setTimeout(() => element.classList.remove('update-success'), 500);
            
            updateBankProgress(bankId);
            updateEmailPreview();
            updateSummaryStats();
            saveStatementStatus(bankId, month, year, isReceived);
        }

        function saveStatementStatus(bankId, month, year, received) {
            // Show loading state
            Swal.fire({
                title: 'Updating...',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1000,
                didOpen: () => Swal.showLoading()
            });
            
            fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax_action=toggle_statement&bank_id=${bankId}&month=${month}&year=${year}&received=${received}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    console.error('Save failed:', data.message);
                    Swal.fire({ 
                        toast: true, 
                        position: 'top-end', 
                        icon: 'error', 
                        title: 'Failed to save', 
                        showConfirmButton: false, 
                        timer: 3000 
                    });
                } else {
                    Swal.fire({ 
                        toast: true, 
                        position: 'top-end', 
                        icon: 'success', 
                        title: 'Updated', 
                        showConfirmButton: false, 
                        timer: 1000 
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({ 
                    toast: true, 
                    position: 'top-end', 
                    icon: 'error', 
                    title: 'Connection error', 
                    showConfirmButton: false, 
                    timer: 3000 
                });
            });
        }

        function updateBankProgress(bankId) {
            const grid = document.getElementById(`month-grid-${bankId}`);
            const items = grid.querySelectorAll('.month-item');
            const received = grid.querySelectorAll('.month-item.received').length;
            const total = items.length;
            const percentage = (received / total) * 100;
            document.getElementById(`progress-text-${bankId}`).textContent = `${received}/${total}`;
            document.getElementById(`progress-bar-${bankId}`).style.width = `${percentage}%`;
        }

        function changeYear(bankId, year) {
            bankData[bankId].year = parseInt(year);
            document.getElementById('email-year').textContent = year;
            const grid = document.getElementById(`month-grid-${bankId}`);
            grid.style.opacity = '0.5';
            const formData = new FormData();
            formData.append('ajax_action', 'get_year_data');
            formData.append('bank_id', bankId);
            formData.append('year', year);
            
            fetch(apiEndpoint, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                const items = grid.querySelectorAll('.month-item');
                items.forEach((item, index) => {
                    item.dataset.year = year;
                    const isReceived = data.received_months && data.received_months.includes(index);
                    const checkbox = item.querySelector('input[type="checkbox"]');
                    const statusText = item.querySelector('.status-text');
                    checkbox.checked = isReceived;
                    if (isReceived) {
                        item.classList.remove('missing');
                        item.classList.add('received');
                        statusText.textContent = 'Received';
                        statusText.classList.remove('text-red-600');
                        statusText.classList.add('text-green-600');
                        item.dataset.received = '1';
                    } else {
                        item.classList.remove('received');
                        item.classList.add('missing');
                        statusText.textContent = 'Missing';
                        statusText.classList.remove('text-green-600');
                        statusText.classList.add('text-red-600');
                        item.dataset.received = '0';
                        const dateDiv = item.querySelector('.received-date');
                        if (dateDiv) dateDiv.remove();
                    }

                    const fileLink = document.getElementById(`file-link-${bankId}-${index}`);
                    const fileName = document.getElementById(`file-name-${bankId}-${index}`);
                    const fileDelBtn = document.getElementById(`file-del-${bankId}-${index}`);
                    const fileMeta = data.statement_files && data.statement_files[index] ? data.statement_files[index] : null;
                    if (fileMeta) {
                        fileLink.href = fileMeta.url;
                        fileLink.dataset.fileName = fileMeta.name;
                        fileLink.classList.remove('hidden');
                        fileName.textContent = fileMeta.name;
                        fileName.classList.remove('hidden');
                        if (fileDelBtn) fileDelBtn.classList.remove('hidden');
                    } else {
                        fileLink.href = '#';
                        fileLink.dataset.fileName = '';
                        fileLink.classList.add('hidden');
                        fileName.textContent = '';
                        fileName.classList.add('hidden');
                        if (fileDelBtn) fileDelBtn.classList.add('hidden');
                    }
                });
                grid.style.opacity = '1';
                updateBankProgress(bankId);
                updateEmailPreview();
                updateSummaryStats();
            })
            .catch(error => {
                console.error('Error:', error);
                grid.style.opacity = '1';
            });
        }

        function updateEmailPreview() {
            const listContainer = document.getElementById('missing-statements-list');
            listContainer.innerHTML = '';
            let hasMissing = false;
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            
            Object.keys(bankData).forEach(bankId => {
                const bank = bankData[bankId];
                const grid = document.getElementById(`month-grid-${bankId}`);
                const missingItems = grid.querySelectorAll('.month-item.missing');
                if (missingItems.length > 0) {
                    hasMissing = true;
                    const missingMonths = Array.from(missingItems).map(item => {
                        const monthIndex = parseInt(item.dataset.month);
                        return months[monthIndex];
                    });
                    const div = document.createElement('div');
                    div.className = 'bg-red-50 border-l-4 border-red-400 p-3 rounded-r';
                    div.innerHTML = `
                        <div class="font-bold text-gray-800 text-xs uppercase mb-1">${bank.name} (**** ${bank.last4})</div>
                        <div class="text-red-700 text-sm font-medium">Missing: ${missingMonths.join(', ')}</div>
                    `;
                    listContainer.appendChild(div);
                }
            });
            
            if (!hasMissing) {
                listContainer.innerHTML = `
                    <div class="bg-green-50 border border-green-200 p-4 rounded-lg text-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                        <p class="text-green-800 font-medium">All statements received!</p>
                        <p class="text-green-600 text-xs">No action required at this time.</p>
                    </div>
                `;
                document.getElementById('email-status').innerHTML = '<span class="text-green-600"><i class="fas fa-check"></i> Complete</span>';
            } else {
                document.getElementById('email-status').textContent = 'Ready to send';
            }
        }

        function updateSummaryStats() {
            let totalMissing = 0;
            let totalReceived = 0;
            let totalItems = 0;
            document.querySelectorAll('.month-grid').forEach(grid => {
                totalMissing += grid.querySelectorAll('.month-item.missing').length;
                totalReceived += grid.querySelectorAll('.month-item.received').length;
                totalItems += grid.querySelectorAll('.month-item').length;
            });
            document.getElementById('total-missing').textContent = totalMissing;
            document.getElementById('total-received').textContent = totalReceived;
            const rate = totalItems > 0 ? Math.round((totalReceived / totalItems) * 100) : 0;
            document.getElementById('completion-rate').textContent = rate + '%';
            document.getElementById('completion-bar').style.width = rate + '%';
        }

        function openAddBankModal() {
            // Reset any leftover state from a previous open.
            const hint = document.getElementById('bank_match_hint');
            hint.className = 'mt-2 text-sm hidden';
            hint.innerHTML = '';
            const other = document.getElementById('bank_name_other');
            other.classList.add('hidden');
            other.value = '';
            const modal = new bootstrap.Modal(document.getElementById('addBankModal'));
            modal.show();
        }

        let last4CheckTimer = null;
        function onAccountNumberInput() {
            clearTimeout(last4CheckTimer);
            last4CheckTimer = setTimeout(runLast4Check, 350);
        }

        function runLast4Check() {
            const input = document.getElementById('account_number_input');
            const hint = document.getElementById('bank_match_hint');
            const digits = (input.value || '').replace(/\D/g, '');
            if (digits.length < 4) {
                hint.className = 'mt-2 text-sm hidden';
                hint.innerHTML = '';
                return;
            }
            const formData = new FormData();
            formData.set('ajax_action', 'check_last4');
            formData.set('account_number', input.value);
            fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams(formData) })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') renderLast4Hint(data);
                })
                .catch(() => {});
        }

        function renderLast4Hint(data) {
            const hint = document.getElementById('bank_match_hint');
            const matches = data.matches || [];
            if (matches.length === 0) {
                hint.className = 'mt-2 text-sm text-green-600';
                hint.innerHTML = `<i class="fas fa-check-circle mr-1"></i>No bank ending in <strong>${escapeHtml(data.last4)}</strong> found in the CRM.`;
                return;
            }
            const rows = matches.map(m => {
                const where = m.is_same ? ' <span class="text-xs text-red-600">(this client)</span>' : '';
                return `<li><strong>${escapeHtml(m.client_name)}</strong>${where} — ${escapeHtml(m.bank_name || 'Bank')} ${escapeHtml(m.account_type || '')} <span class="text-gray-500">**** ${escapeHtml(m.last4 || '')}</span></li>`;
            }).join('');
            hint.className = 'mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2';
            hint.innerHTML = `<div class="font-semibold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i>Found similar bank ending in ${escapeHtml(data.last4)}:</div>` +
                             `<ul class="list-disc ml-5" style="max-height:140px;overflow:auto;">${rows}</ul>`;
        }

        function handleBankNameChange(select) {
            const otherInput = document.getElementById('bank_name_other');
            if (select.value === 'OTHER') {
                otherInput.classList.remove('hidden');
                otherInput.focus();
            } else {
                otherInput.classList.add('hidden');
                otherInput.value = '';
            }
        }

        function submitAddBank(event) {
            event.preventDefault();
            const form = event.target;

            // Resolve the bank name (custom value when "OTHER" is selected).
            const select = form.querySelector('#bank_name_select');
            let bankName = select.value;
            if (bankName === 'OTHER') {
                bankName = (document.getElementById('bank_name_other').value || '').trim();
                if (!bankName) {
                    Swal.fire('Bank name required', 'Please enter the bank name.', 'warning');
                    return;
                }
            }

            const formData = new FormData(form);
            formData.set('bank_name', bankName);
            formData.set('ajax_action', 'add_bank');
            sendAddBank(formData);
        }

        function sendAddBank(formData) {
            fetch(apiEndpoint, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Bank Added', text: 'The bank account has been added successfully.', showConfirmButton: false, timer: 1500 })
                    .then(() => location.reload());
                } else if (data.status === 'duplicate') {
                    const rows = (data.matches || []).map(m => {
                        const where = m.is_same ? ' <span class="text-xs text-red-600">(this client)</span>' : '';
                        return `<li class="mb-1"><strong>${escapeHtml(m.client_name)}</strong>${where} — ${escapeHtml(m.bank_name || 'Bank')} ${escapeHtml(m.account_type || '')} <span class="text-gray-500">**** ${escapeHtml(m.last4 || '')}</span></li>`;
                    }).join('');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Similar Bank Found',
                        html: `An account ending in <strong>${escapeHtml(data.last4)}</strong> already exists in the CRM:` +
                              `<ul class="text-left mt-3 mb-1" style="max-height:200px;overflow:auto;">${rows}</ul>` +
                              `<div class="text-sm text-gray-600 mt-2">Do you want to add this bank anyway?</div>`,
                        showCancelButton: true,
                        confirmButtonColor: '#6FD943',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: '<i class="fas fa-plus mr-1"></i> Add Anyway',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            formData.set('confirm_duplicate', '1');
                            sendAddBank(formData);
                        }
                    });
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Failed to add bank', 'error');
            });
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function setBankStatus(bankId, isActive) {
            const actionLabel = isActive === 1 ? 'reactivate' : 'deactivate';
            Swal.fire({
                title: `${actionLabel.charAt(0).toUpperCase() + actionLabel.slice(1)} bank account?`,
                text: isActive === 1
                    ? "This bank will be included in statement reminder emails again."
                    : "This bank will be excluded from all reminder emails.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: isActive === 1 ? '#10b981' : '#ef4444',
                confirmButtonText: `Yes, ${actionLabel} it`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(apiEndpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `ajax_action=toggle_bank_status&bank_id=${bankId}&is_active=${isActive}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: isActive === 1 ? 'Reactivated' : 'Deactivated',
                                text: 'Bank account status updated.',
                                timer: 1200,
                                showConfirmButton: false
                            }).then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Shown when a non-admin clicks the greyed-out Edit/Delete icons.
        function adminOnlyAction(action) {
            Swal.fire({
                icon: 'info',
                title: 'Admin Access Required',
                html: `Only an administrator can ${action === 'delete' ? 'delete' : 'edit'} a bank account.<br><span class="text-sm text-gray-500">Please contact an admin to make this change.</span>`,
                confirmButtonColor: '#6FD943',
                confirmButtonText: 'OK'
            });
        }

        function handleEditBankNameChange(select) {
            const otherInput = document.getElementById('edit_bank_name_other');
            if (select.value === 'OTHER') {
                otherInput.classList.remove('hidden');
                otherInput.focus();
            } else {
                otherInput.classList.add('hidden');
                otherInput.value = '';
            }
        }

        function editBank(bankId) {
            if (!isAdmin) { adminOnlyAction('edit'); return; }
            const bank = allBanks[bankId];
            if (!bank) {
                Swal.fire('Error', 'Bank details not found.', 'error');
                return;
            }

            document.getElementById('edit_bank_id').value = bankId;
            document.getElementById('edit_account_number_input').value = bank.account_number || '';

            // Match the bank name to a known option, otherwise fall back to "OTHER".
            const select = document.getElementById('edit_bank_name_select');
            const other = document.getElementById('edit_bank_name_other');
            const knownOption = Array.from(select.options).some(opt => opt.value === bank.bank_name);
            if (knownOption) {
                select.value = bank.bank_name;
                other.classList.add('hidden');
                other.value = '';
            } else {
                select.value = 'OTHER';
                other.classList.remove('hidden');
                other.value = bank.bank_name || '';
            }

            // Match the account type if it exists in the list, otherwise leave blank.
            const typeSelect = document.getElementById('edit_account_type');
            const knownType = Array.from(typeSelect.options).some(opt => opt.value === bank.account_type);
            typeSelect.value = knownType ? bank.account_type : '';

            const modal = new bootstrap.Modal(document.getElementById('editBankModal'));
            modal.show();
        }

        function submitEditBank(event) {
            event.preventDefault();
            const form = event.target;

            const select = form.querySelector('#edit_bank_name_select');
            let bankName = select.value;
            if (bankName === 'OTHER') {
                bankName = (document.getElementById('edit_bank_name_other').value || '').trim();
                if (!bankName) {
                    Swal.fire('Bank name required', 'Please enter the bank name.', 'warning');
                    return;
                }
            }

            const formData = new FormData(form);
            formData.set('bank_name', bankName);
            formData.set('ajax_action', 'edit_bank');

            fetch(apiEndpoint, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Bank Updated', text: 'The bank account has been updated.', showConfirmButton: false, timer: 1500 })
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Failed to update bank', 'error'));
        }

        function deleteBank(bankId) {
            if (!isAdmin) { adminOnlyAction('delete'); return; }
            const bank = allBanks[bankId] || {};
            const last4 = (bank.account_number || '').replace(/\D/g, '').slice(-4);
            Swal.fire({
                title: 'Delete Bank Account?',
                html: `This will permanently delete <strong>${escapeHtml(bank.bank_name || 'this bank')}</strong>${last4 ? ` (**** ${escapeHtml(last4)})` : ''} along with all of its statement records and uploaded files.<br><br><span class="text-sm text-red-600">This action cannot be undone.</span>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (!result.isConfirmed) return;
                Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax_action=delete_bank&bank_id=${bankId}`
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Bank Deleted', text: 'The bank account has been removed.', showConfirmButton: false, timer: 1500 })
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(() => {
                    Swal.close();
                    Swal.fire('Error', 'Failed to delete bank', 'error');
                });
            });
        }

        function sendReminder(bankId) {
            const bank = bankData[bankId];
            const grid = document.getElementById(`month-grid-${bankId}`);
            const missingItems = grid.querySelectorAll('.month-item.missing');
            if (missingItems.length === 0) {
                Swal.fire('All Caught Up!', 'No missing statements for this account.', 'info');
                return;
            }
            const missingMonths = Array.from(missingItems).map(item => parseInt(item.dataset.month));
            Swal.fire({
                title: 'Send Reminder Email?',
                html: `This will send an email to <strong><?= htmlspecialchars($client_data['prm_email'] ?: 'the client') ?></strong> requesting <strong>${missingItems.length}</strong> missing statement(s) for <strong>${bank.name}</strong>.<br><br><span class="text-xs text-gray-500">Missing months are auto-verified from live data before sending.</span>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6FD943',
                confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Send Email'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Sending...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    fetch(apiEndpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `ajax_action=send_reminder&bank_id=${bankId}&year=${bank.year}&missing_months=${JSON.stringify(missingMonths)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        if (data.status === 'success') {
                            const monthsUsedCount = Array.isArray(data.months_used) ? data.months_used.length : missingItems.length;
                            Swal.fire({ icon: 'success', title: 'Email Sent!', text: `Reminder sent with ${monthsUsedCount} auto-included missing month(s).`, timer: 2200, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        Swal.fire('Error', 'Failed to send email', 'error');
                    });
                }
            });
        }

        function markAllAsReceived(bankId) {
            const grid = document.getElementById(`month-grid-${bankId}`);
            const items = grid.querySelectorAll('.month-item.missing');
            
            if (items.length === 0) {
                Swal.fire('All caught up!', 'All statements are already marked as received.', 'info');
                return;
            }
            
            Swal.fire({
                title: 'Mark all as received?',
                text: `This will mark ${items.length} missing statements as received.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6FD943',
                confirmButtonText: 'Yes, mark all as received'
            }).then((result) => {
                if (result.isConfirmed) {
                    items.forEach(item => {
                        const checkbox = item.querySelector('input[type="checkbox"]');
                        checkbox.checked = true;
                        const month = parseInt(item.dataset.month);
                        toggleStatement(bankId, month, item);
                    });
                }
            });
        }

        function openFilePicker(event, bankId, month) {
            event.stopPropagation();
            const input = document.getElementById(`file-input-${bankId}-${month}`);
            if (input) input.click();
        }

        function uploadStatementFile(bankId, month, input) {
            if (!input.files || input.files.length === 0) return;
            const yearSelect = document.querySelector(`.year-select[data-bank-id="${bankId}"]`);
            const year = yearSelect ? parseInt(yearSelect.value, 10) : currentYear;
            const formData = new FormData();
            formData.append('ajax_action', 'upload_statement_file');
            formData.append('bank_id', bankId);
            formData.append('month', month);
            formData.append('year', year);
            formData.append('statement_file', input.files[0]);

            Swal.fire({ title: 'Uploading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            fetch(apiEndpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.status !== 'success') {
                    Swal.fire('Error', data.message || 'Upload failed', 'error');
                    return;
                }
                const fileLink = document.getElementById(`file-link-${bankId}-${month}`);
                const fileName = document.getElementById(`file-name-${bankId}-${month}`);
                const fileDelBtn = document.getElementById(`file-del-${bankId}-${month}`);
                fileLink.href = data.file_url;
                fileLink.dataset.fileName = data.file_name;
                fileLink.classList.remove('hidden');
                fileName.textContent = data.file_name;
                fileName.classList.remove('hidden');
                if (fileDelBtn) fileDelBtn.classList.remove('hidden');
                Swal.fire({ icon: 'success', title: 'Uploaded', timer: 1200, showConfirmButton: false });
            })
            .catch(() => {
                Swal.close();
                Swal.fire('Error', 'Failed to upload file', 'error');
            })
            .finally(() => {
                input.value = '';
            });
        }

        function runAutomationNow() {
            const totalMissing = parseInt(document.getElementById('total-missing').textContent);
            if (totalMissing === 0) {
                Swal.fire({
                    icon: 'success',
                    title: 'All Caught Up!',
                    text: 'This client has no missing bank statements. No email needed.',
                    confirmButtonColor: '#6FD943',
                    timer: 3000,
                    showConfirmButton: false
                });
                return;
            }

            const missingByBank = Object.keys(bankData).map(bankId => {
                const grid = document.getElementById(`month-grid-${bankId}`);
                if (!grid) return null;
                const missingMonths = Array.from(grid.querySelectorAll('.month-item.missing')).map(item => parseInt(item.dataset.month, 10));
                if (missingMonths.length === 0) return null;
                return {
                    bankId: parseInt(bankId, 10),
                    year: bankData[bankId].year,
                    missingMonths: missingMonths
                };
            }).filter(Boolean);

            if (missingByBank.length === 0) {
                Swal.fire('No Missing Items', 'No active bank has missing months to include in email.', 'info');
                return;
            }

            Swal.fire({
                title: '<i class="fas fa-paper-plane text-[#6FD943] mb-2"></i><br>Send Reminder Email?',
                html: `
                    <div class="text-left text-sm text-gray-600 mt-4">
                        <p class="mb-3">This will send an email to:</p>
                        <div class="bg-gray-50 p-3 rounded-lg mb-4">
                            <div class="font-semibold text-gray-800"><?= htmlspecialchars($client_name) ?></div>
                            <div class="text-xs text-gray-500"><?= htmlspecialchars($client_data['prm_email'] ?: 'No email on file') ?></div>
                        </div>
                        <p class="mb-2">The email will include:</p>
                        <ul class="list-disc pl-5 space-y-1 text-xs text-gray-500">
                            <li>All missing bank statements for this client</li>
                            <li>Only active bank accounts with missing months</li>
                            <li>Server-side auto verification of missing months (no manual month misses)</li>
                            <li>Instructions to upload via client portal</li>
                        </ul>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#6FD943',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-paper-plane mr-2"></i>Send Email',
                cancelButtonText: 'Cancel',
                showLoaderOnConfirm: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                preConfirm: () => {
                    const requests = missingByBank.map(item => {
                        const params = new URLSearchParams();
                        params.append('ajax_action', 'send_reminder');
                        params.append('bank_id', item.bankId);
                        params.append('year', item.year);
                        params.append('missing_months', JSON.stringify(item.missingMonths));
                        return fetch(apiEndpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params.toString()
                        }).then(r => r.json());
                    });

                    return Promise.all(requests).then(results => {
                        const failed = results.filter(r => r.status !== 'success');
                        if (failed.length > 0) {
                            throw new Error(failed[0].message || 'One or more emails failed');
                        }
                        return { sent: results.length };
                    }).catch(error => {
                        Swal.showValidationMessage(`Error: ${error.message}`);
                        throw error;
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    const data = result.value;
                    if (data.sent > 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Email Sent!',
                            html: `
                                <div class="text-center">
                                    <p class="mb-4">Reminder email sent successfully to:</p>
                                    <div class="bg-green-50 p-4 rounded-lg mb-4">
                                        <div class="font-bold text-green-800 text-lg"><?= htmlspecialchars($client_name) ?></div>
                                        <div class="text-green-600 text-sm">${data.sent} active bank account(s) processed</div>
                                    </div>
                                    <p class="text-xs text-gray-500">Activity has been logged.</p>
                                </div>
                            `,
                            confirmButtonColor: '#6FD943',
                            confirmButtonText: 'Great!',
                            timer: 5000,
                            showConfirmButton: true
                        });
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Send',
                            text: data.message || 'There was an error sending the email.',
                            confirmButtonColor: '#6FD943'
                        });
                    }
                }
            });
        }

        function openViewModal(by, updatedBy, date, detail) {
            const body = `
                <div class="mb-3">
                    <strong>Originally Created By:</strong>
                    <div class="text-muted">${by}</div>
                </div>
                <div class="mb-3">
                    <strong>Last Updated By:</strong>
                    <div class="text-muted">${updatedBy}</div>
                </div>
                <div class="mb-3">
                    <strong>Last Updated:</strong>
                    <div class="text-muted">${date}</div>
                </div>
                <div class="border rounded p-3 bg-light" style="white-space: normal;">
                    ${detail}
                </div>`;
            document.getElementById('viewCommentBody').innerHTML = body;
            $('#viewCommentModal').modal('show');
        }

        function openEditModal(id, detail) {
            document.getElementById('edit_comment_id').value = id;
            document.getElementById('edit_comment_detail').value = detail;
            $('#editCommentModal').modal('show');
        }

        function openDeleteModal(id) {
            document.getElementById('delete_comment_id').value = id;
            $('#deleteCommentModal').modal('show');
        }

        function printBankStatementComments() {
            const printable = document.getElementById('printableBankStatementComments');
            if (!printable) {
                Swal.fire('No comments to print', '', 'info');
                return;
            }
            const printContents = printable.innerHTML;
            const printWindow = window.open('', '', 'height=600,width=800');
            printWindow.document.write('<html><head><title>All Bank Statement Comments</title></head><body>');
            printWindow.document.write('<button onclick="window.print()" style="position: fixed; top: 20px; right: 20px; padding: 10px 20px; background-color: #007BFF; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.2);">Print</button>');
            printWindow.document.write(printContents);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
        }

        function createCommentEditor(textarea) {
            if (!textarea || textarea.ckeditorInstance || textarea.dataset.editorLoading === '1') {
                return;
            }

            textarea.dataset.editorLoading = '1';

            ClassicEditor
                .create(textarea, {
                    toolbar: [
                        'undo', 'redo', '|',
                        'heading', '|',
                        'bold', 'italic',
                        'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|',
                        'blockQuote', '|'
                    ],
                    language: 'en',
                    licenseKey: ''
                })
                .then(editor => {
                    textarea.ckeditorInstance = editor;
                    delete textarea.dataset.editorLoading;
                })
                .catch(error => {
                    delete textarea.dataset.editorLoading;
                    console.error(error);
                });
        }

        $(document).on('shown.bs.modal', '#addBankStatementComment', function () {
            createCommentEditor(this.querySelector('textarea.ckeditor'));
        });

        $(document).on('click', '[data-toggle="modal"][data-target="#addBankStatementComment"], [data-bs-toggle="modal"][data-bs-target="#addBankStatementComment"]', function () {
            const targetSelector = $(this).attr('data-target') || $(this).attr('data-bs-target');
            if (!targetSelector) {
                return;
            }

            const modal = document.querySelector(targetSelector);
            if (modal) {
                createCommentEditor(modal.querySelector('textarea.ckeditor'));
            }
        });

        function addPhraseBankStatement(text) {
            const inst = document.querySelector('#comment_details_bank_statement')?.ckeditorInstance;
            if (inst) {
                inst.model.change(writer => {
                    const pos = inst.model.document.selection.getFirstPosition();
                    writer.insertText(text + '. ', pos);
                });
                inst.editing.view.focus();
            }
        }

        function copyEmailToClipboard() {
            const emailContent = document.getElementById('email-body-content').innerText;
            navigator.clipboard.writeText(emailContent).then(() => {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied to clipboard', showConfirmButton: false, timer: 2000 });
            });
        }

        function viewAllStatements(bankId, bankName) {
            const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            const yearSelect = document.querySelector(`.year-select[data-bank-id="${bankId}"]`);
            const year = yearSelect ? parseInt(yearSelect.value, 10) : currentYear;
            let hasAny = false;
            let rows = '';
            const fileUrls = [];
            for (let m = 0; m < 12; m++) {
                const link = document.getElementById(`file-link-${bankId}-${m}`);
                const nameEl = document.getElementById(`file-name-${bankId}-${m}`);
                const hasFile = link && !link.classList.contains('hidden') && link.href !== '#' && link.getAttribute('href') !== '#';
                const fileName = link ? (link.dataset.fileName || nameEl?.textContent?.trim() || '') : '';
                if (hasFile) {
                    hasAny = true;
                    fileUrls.push(link.href);
                    rows += `
                    <tr>
                        <td class="py-2 px-3 font-medium text-gray-700">${monthNames[m]} ${year}</td>
                        <td class="py-2 px-3 text-xs text-gray-500 truncate" style="max-width:200px" title="${fileName}">${fileName || '-'}</td>
                        <td class="py-2 px-3">
                            <div class="flex gap-2">
                                <a href="${link.href}" target="_blank"
                                   class="px-3 py-1 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs font-medium">
                                    <i class="fas fa-eye mr-1"></i>View
                                </a>
                                <button type="button" onclick="deleteStatementFile(${bankId}, ${m})"
                                        class="px-3 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100 text-xs font-medium">
                                    <i class="fas fa-trash-alt mr-1"></i>Delete
                                </button>
                            </div>
                        </td>
                    </tr>`;
                } else {
                    rows += `
                    <tr class="text-gray-400">
                        <td class="py-2 px-3">${monthNames[m]} ${year}</td>
                        <td class="py-2 px-3 text-xs italic">No file uploaded</td>
                        <td class="py-2 px-3"></td>
                    </tr>`;
                }
            }

            window._bankStatementOpenAllUrls = fileUrls;
            const openAllBtn = hasAny ? `
                    <button type="button" onclick="openAllStatementFiles()"
                            class="ml-auto px-3 py-1.5 rounded bg-[#6FD943] hover:bg-[#5bc73a] text-white text-xs font-semibold inline-flex items-center gap-1"
                            title="Open all uploaded statement files in new tabs">
                        <i class="fas fa-external-link-alt"></i> Open All in Tabs (${fileUrls.length})
                    </button>` : '';
            const body = `
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${year}
                    </span>
                    <span class="text-sm text-gray-500">Use the year dropdown on the bank card to switch years.</span>
                    ${openAllBtn}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" style="font-size:0.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th>File Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                ${!hasAny ? '<p class="text-center text-gray-400 py-4"><i class="fas fa-inbox text-2xl mb-2 block"></i>No statement files uploaded for this year.</p>' : ''}`;

            document.getElementById('viewAllStatementsTitle').innerHTML = `<i class="fas fa-folder-open mr-2"></i>${bankName} — All Statement Files`;
            document.getElementById('viewAllStatementsBody').innerHTML = body;
            const modal = new bootstrap.Modal(document.getElementById('viewAllStatementsModal'));
            modal.show();
        }

        function openAllStatementFiles() {
            const urls = window._bankStatementOpenAllUrls || [];
            if (!urls.length) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'No files to open', showConfirmButton: false, timer: 1800 });
                return;
            }
            let blocked = 0;
            urls.forEach(url => {
                const w = window.open(url, '_blank', 'noopener,noreferrer');
                if (!w) blocked++;
            });
            if (blocked > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Popups Blocked',
                    html: `Your browser blocked <strong>${blocked}</strong> of ${urls.length} tabs.<br><small>Please allow popups for this site and try again.</small><br><br>
                        <a href="chrome://settings/content/popups?search=pop" target="_blank" rel="noopener"
                           onclick="navigator.clipboard && navigator.clipboard.writeText(this.href).catch(()=>{});"
                           style="color:#2563eb;text-decoration:underline;">
                            <i class="fas fa-cog mr-1"></i>Open Chrome Popup Settings
                        </a><br>
                        <small class="text-gray-400">If it doesn't open automatically, the link is copied — paste it into a new browser tab.</small>`,
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Opened ${urls.length} file(s)`, showConfirmButton: false, timer: 1800 });
            }
        }

        function deleteStatementFile(bankId, month) {
            const link = document.getElementById(`file-link-${bankId}-${month}`);
            const nameEl = document.getElementById(`file-name-${bankId}-${month}`);
            const delBtn = document.getElementById(`file-del-${bankId}-${month}`);
            const yearSelect = document.querySelector(`.year-select[data-bank-id="${bankId}"]`);
            const year = yearSelect ? parseInt(yearSelect.value, 10) : currentYear;
            const fileName = link ? (link.dataset.fileName || nameEl?.textContent?.trim() || '') : '';

            if (!fileName) {
                Swal.fire('No file', 'No statement file found for this month.', 'info');
                return;
            }

            Swal.fire({
                title: 'Delete Statement File?',
                html: `This will permanently delete <strong>${fileName}</strong>. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i>Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (!result.isConfirmed) return;
                Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                fetch(apiEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax_action=delete_statement_file&bank_id=${bankId}&month=${month}&year=${year}&file_name=${encodeURIComponent(fileName)}`
                })
                .then(r => r.json())
                .then(data => {
                    Swal.close();
                    if (data.status !== 'success') {
                        Swal.fire('Error', data.message || 'Delete failed', 'error');
                        return;
                    }
                    // Hide view/delete buttons and clear filename in month item
                    if (link) { link.href = '#'; link.dataset.fileName = ''; link.classList.add('hidden'); }
                    if (delBtn) delBtn.classList.add('hidden');
                    if (nameEl) { nameEl.textContent = ''; nameEl.classList.add('hidden'); }
                    // Refresh the View All modal if it's open
                    const modal = document.getElementById('viewAllStatementsModal');
                    if (modal && modal.classList.contains('show')) {
                        const bankName = bankData[bankId]?.name || '';
                        viewAllStatements(bankId, bankName);
                    }
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'File deleted', showConfirmButton: false, timer: 1800 });
                })
                .catch(() => {
                    Swal.close();
                    Swal.fire('Error', 'Connection error. Please try again.', 'error');
                });
            });
        }

    </script>
</body>
</html>
