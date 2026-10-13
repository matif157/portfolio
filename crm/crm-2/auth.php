<?php
session_start();
include "includes/db.php";

$timeout = 1300;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];   // <-- ADD THIS LINE HERE
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION["username"])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

$usertype = $_SESSION['user_type'];

// New function to refresh permissions
function refresh_user_permissions($user_id) {
    global $con;
    
    $_SESSION['permissions'] = [];
    $perm_query = mysqli_query($con, "SELECT * FROM user_permissions WHERE user_id='$user_id'");
    
    if (mysqli_num_rows($perm_query) > 0) {
        $permissions = mysqli_fetch_assoc($perm_query);
        unset($permissions['user_id']);
        $_SESSION['permissions'] = $permissions;
    } else {
        // Load default permissions based on role
        $user = mysqli_fetch_assoc(mysqli_query($con, "SELECT user_type FROM users WHERE user_id='$user_id'"));
        $default_query = mysqli_query($con, "SELECT * FROM dashboard_access WHERE user_type='{$user['user_type']}'");
        
        if (mysqli_num_rows($default_query) > 0) {
            $defaults = mysqli_fetch_assoc($default_query);
            unset($defaults['user_type']);
            $_SESSION['permissions'] = $defaults;
        }
    }
    
    // Set individual permission flags in session
    foreach ($_SESSION['permissions'] as $perm => $value) {
        $_SESSION['acc_' . $perm] = $value;
    }
}

// Refresh permissions on every page load
refresh_user_permissions($_SESSION['user_id']);

// Set permission variables from session
$acc_add_client = $_SESSION['acc_add_new_client'] ?? "No";
$acc_view_client = $_SESSION['acc_view_clients_list'] ?? "No";
$acc_detail_client = $_SESSION['acc_client_detail_area'] ?? "No";
$acc_billing_client = $_SESSION['acc_client_billing_area'] ?? "No";
$acc_delete_client = $_SESSION['acc_delete_client'] ?? "No";
$acc_view_products = $_SESSION['acc_view_products'] ?? "No";
$acc_add_edit_products = $_SESSION['acc_add_edit_products'] ?? "No";
$acc_delete_products = $_SESSION['acc_delete_products'] ?? "No";
$acc_view_staff = $_SESSION['acc_view_staff'] ?? "No";
$acc_add_edit_staff = $_SESSION['acc_add_edit_staff'] ?? "No";
$acc_delete_staff = $_SESSION['acc_delete_staff'] ?? "No";
$acc_view_entity = $_SESSION['acc_view_entity_form'] ?? "No";
$acc_add_edit_entity = $_SESSION['acc_add_edit_form'] ?? "No";
$acc_delete_entity = $_SESSION['acc_delete_form'] ?? "No";
$acc_view_product = $_SESSION['acc_view_product_cycle'] ?? "No";
$acc_add_edit_product = $_SESSION['acc_add_edit_cycle'] ?? "No";
$acc_delete_product = $_SESSION['acc_delete_cycle'] ?? "No";
$acc_invoice_and_billing = $_SESSION['acc_invoice_and_billing'] ?? "No";
$acc_estimates_and_qutations = $_SESSION['acc_estimates_and_qutations'] ?? "No";
$acc_bank_card_area = $_SESSION['acc_bank_card_area'] ?? "No";
$client_active = $_SESSION['acc_client_active'] ?? "No";
?>

<script>
    let timeoutSeconds = 1300;
    let logoutTimer;

    function startLogoutTimer() {
        logoutTimer = setTimeout(() => {
            window.location.href = "login.php?timeout=1";
        }, timeoutSeconds * 1000);
    }

    function resetLogoutTimer() {
        clearTimeout(logoutTimer);
        startLogoutTimer();
    }

    window.addEventListener('load', () => {
        startLogoutTimer();

        ['click', 'mousemove', 'keypress', 'scroll'].forEach(evt => {
            document.addEventListener(evt, resetLogoutTimer);
        });
    });
</script>