<?php
/**
 * Standard Activity Logger
 * Usage: log_activity($pdo, 'Invoice', 'Created', $invoice_id, ['amount' => 500]);
 */
function log_activity($conn, $module, $action, $record_id = null, $data = []) {
    $user_id   = $_SESSION['user_id'] ?? 0;
    $user_name = $_SESSION['user_name'] ?? 'System';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $agent     = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);

    $json_data = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    try {
        $sql = "INSERT INTO activity_logs 
                (user_id, user_name, module_name, action, record_id, new_value, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $user_id,
            $user_name,
            $module,
            $action,
            $record_id,
            $json_data,
            $ip,
            $agent
        ]);
    } catch (Exception $e) {
        error_log("Log Error: " . $e->getMessage());
    }
}