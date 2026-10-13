<?php
// =============================================
// FILE: functions.php
// PURPOSE: Reusable helper functions for the whole CRM
// WHY: Instead of writing the same code again and again,
//      we keep common logic here and call it from any page.
// =============================================

/**
 * log_activity()
 * -------------------------------------------------
 * Purpose: Save every important action into activity_logs table
 * Why: So we can later see who did what and when (audit trail)
 *
 * Parameters:
 *   $conn        - Database connection (PDO)
 *   $module      - Which module (Invoice, Client, System...)
 *   $action      - What happened (Created, Updated, Viewed, Payment Added...)
 *   $record_id   - ID of the record that was affected (optional)
 *   $data        - Extra details as array (will be saved as JSON)
 */
function log_activity($conn, $module, $action, $record_id = null, $data = []) {

    // Get current logged-in user from session
    // ?? means: if not set, use the value after ??
    $user_id   = $_SESSION['user_id'] ?? 0;
    $user_name = $_SESSION['user_name'] ?? 'System';

    // Get visitor information
    $ip         = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
    $url        = $_SERVER['REQUEST_URI'] ?? '';

    // Build a clean structured JSON payload
    $payload = [
        'module'     => $module,
        'action'     => $action,
        'record_id'  => $record_id,
        'user_id'    => $user_id,
        'user_name'  => $user_name,
        'ip'         => $ip,
        'url'        => $url,
        'data'       => $data,
        'timestamp'  => date('c')   // ISO 8601 format
    ];

    $json_data = json_encode($payload, JSON_UNESCAPED_UNICODE);

    try {
        $sql = "INSERT INTO activity_logs 
                (user_id, user_name, module_name, action, record_id, old_value, new_value, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $user_name,
            $module,
            $action,
            $record_id,
            null,               // old_value (we keep simple for now)
            $json_data,         // structured JSON goes here
            $ip,
            $user_agent
        ]);
    } catch (Exception $e) {
        // Never crash the whole page if logging fails
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * show()
 * -------------------------------------------------
 * Purpose: Safely display any value with fallback
 * Why: Prevents "Undefined array key" warnings
 * Example: echo show($client['email'], '—');
 */
function show($value, $fallback = '—') {
    if ($value === null || $value === '') {
        return htmlspecialchars($fallback);
    }
    return htmlspecialchars((string)$value);
}

/**
 * redirect()
 * -------------------------------------------------
 * Purpose: Send user to another page and stop further code
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * is_logged_in()
 * -------------------------------------------------
 * Purpose: Check if user is currently logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * require_login()
 * -------------------------------------------------
 * Purpose: Force login if user is not logged in
 * Use this at the top of every protected page
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}
