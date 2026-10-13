<?php
include 'includes/db.php';
include "includes/auth.php";

if (isset($_POST['update_secured_access'])) {
    $user_id = $_POST['user_id'];
    $current_status = $_POST['current_status'];
    
    
    // Toggle the status (if 1, set to 0; if 0, set to 1)
    $new_status = ($current_status == 1) ? 0 : 1;
    
    // Update the database
    $query = "UPDATE users SET has_secured_access = $new_status WHERE user_id = $user_id";
    $result = mysqli_query($con, $query);
    
    if ($result) {
        header("Location: users.php?data=updated");
    } else {
        header("Location: users.php?error=access_update_failed");
    }
}
?>