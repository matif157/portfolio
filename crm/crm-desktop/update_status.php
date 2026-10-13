<?php

include 'includes/db.php';
include "includes/auth.php";
include "includes/activity_log.php";

$userid    = $_SESSION["user_id"] ?? 0;
$memberId  = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
$status    = isset($_POST['status'])    ? intval($_POST['status'])    : 0;

$stmt = $con->prepare("UPDATE members  SET status = ? WHERE member_id = ?");
$stmt->bind_param("ii", $status, $memberId);
 
if ($stmt->execute()) {
    // LOG: Activated / Deactivated client
    $desc = ($status == 1 ? 'Activated' : 'Deactivated') . " member ID: {$memberId}.";
    logActivity($con, $userid, 'UPDATE', 'members', $memberId, $desc);

    echo "Record updated successfully";
} else {
    echo "Error updating record: " . $con->error;
}

$stmt->close();
$con->close();
?>
