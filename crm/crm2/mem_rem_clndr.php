<?php
header('Content-Type: application/json');

include "includes/db.php";
include "includes/auth.php";

$client_id = $_POST['client_id'] ?? 0;
$userid = $_SESSION['user_id'] ?? 0;
$action = $_POST['action'] ?? '';

if ($action === 'get') {
    $stmt = $con->prepare(
        "SELECT reminder_id, reminder_title, reminder_description, reminder_date
         FROM client_calender_reminder
         WHERE client_id=? AND status=1
         ORDER BY reminder_date DESC
         LIMIT 1"
    );
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => $res->fetch_assoc()]);
    exit;
}

if ($action === 'add') {
    $stmt = $con->prepare(
        "DELETE FROM client_calender_reminder WHERE client_id=?"
    );
    $stmt->bind_param("i", $client_id);
    $stmt->execute();

    $stmt = $con->prepare(
        "INSERT INTO client_calender_reminder
         (client_id, reminder_title, reminder_description, reminder_date, created_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "isssi",
        $client_id,
        $_POST['reminder_title'],
        $_POST['reminder_description'],
        $_POST['reminder_date'],
        $userid
    );
    $stmt->execute();

    echo json_encode(['status' => 'ok', 'message' => 'Reminder added']);
    exit;
}


if ($action === 'edit') {
    $stmt = $con->prepare(
        "UPDATE client_calender_reminder
         SET reminder_title=?, reminder_description=?, reminder_date=?
         WHERE reminder_id=? AND client_id=?"
    );
    $stmt->bind_param(
        "sssii",
        $_POST['reminder_title'],
        $_POST['reminder_description'],
        $_POST['reminder_date'],
        $_POST['reminder_id'],
        $client_id
    );
    $stmt->execute();
    echo json_encode(['status' => 'ok', 'message' => 'Reminder updated']);
    exit;
}

if ($action === 'delete') {
    $stmt = $con->prepare(
        "DELETE FROM client_calender_reminder
         WHERE reminder_id=? AND client_id=?"
    );
    $stmt->bind_param("ii", $_POST['reminder_id'], $client_id);
    $stmt->execute();
    echo json_encode(['status' => 'ok', 'message' => 'Reminder deleted']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
