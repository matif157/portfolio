<?php
/**
 * Error Handler — include once from log_in.php or a bootstrap file
 * Logs PHP errors into error_logs table
 */

function mera_log_error($level, $message, $file = null, $line = null, $trace = null)
{
    global $pdo;
    if (!isset($pdo)) return;

    try {
        $stmt = $pdo->prepare("INSERT INTO error_logs 
            (error_level, error_message, error_file, error_line, stack_trace, request_uri, request_method, user_id, ip_address, user_agent) 
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $level,
            mb_substr((string)$message, 0, 2000),
            $file ? mb_substr($file, 0, 250) : null,
            $line,
            $trace ? mb_substr($trace, 0, 5000) : null,
            $_SERVER['REQUEST_URI'] ?? null,
            $_SERVER['REQUEST_METHOD'] ?? null,
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 250) : null
        ]);
    } catch (Exception $e) {
        // Never break the app because of logging
    }
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $map = [
        E_ERROR => 'error', E_WARNING => 'warning', E_NOTICE => 'notice',
        E_USER_ERROR => 'error', E_USER_WARNING => 'warning', E_USER_NOTICE => 'notice',
        E_DEPRECATED => 'notice', E_USER_DEPRECATED => 'notice',
    ];
    $level = $map[$errno] ?? 'error';
    mera_log_error($level, $errstr, $errfile, $errline);
    return false; // let PHP handle as normal too
});

set_exception_handler(function ($ex) {
    mera_log_error('error', $ex->getMessage(), $ex->getFile(), $ex->getLine(), $ex->getTraceAsString());
    http_response_code(500);
    echo "<div style='font-family:Arial;padding:40px;'><h2>Something went wrong</h2>
          <p>The error has been logged. Please contact the administrator.</p></div>";
});
