<?php
include 'includes/db.php';
include 'includes/auth.php';

// Make sure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "error: Unauthorized - No user logged in";
    exit;
}

$userid = intval($_SESSION['user_id']);

if (isset($_POST['toggle_buttons'])) {
    $client_id     = intval($_POST['client_id']);
    $sms_enabled   = intval($_POST['sms_toggle']);
    $email_enabled = intval($_POST['email_toggle']);
 
    // Check if record exists
    $check_query = "SELECT id FROM client_button_settings WHERE member_id = '$client_id'";
    $check_result = mysqli_query($con, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        // Update existing
        $query = "UPDATE client_button_settings 
                  SET sms_enabled = '$sms_enabled', 
                      email_enabled = '$email_enabled', 
                      user_id = '$userid', 
                      updated_at = NOW() 
                  WHERE member_id = '$client_id'";
    } else {
        // Insert new
        $query = "INSERT INTO client_button_settings (member_id, sms_enabled, email_enabled, user_id, updated_at) 
                  VALUES ('$client_id', '$sms_enabled', '$email_enabled', '$userid', NOW())";
    }

    $result = mysqli_query($con, $query);

    echo $result ? "success" : "error: " . mysqli_error($con);
}
