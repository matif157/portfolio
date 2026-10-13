<?php
// =============================================
// FILE: error_handler.php
// PURPOSE: Global Error & Exception Handling for the entire CRM
// WHY: Default PHP errors show white screens or ugly messages.
//      This file makes errors clean, logged, and beginner-friendly.
// =============================================

/**
 * custom_error_handler()
 * Purpose: Catch normal PHP errors (warnings, notices, fatal)
 * When: Automatically called by PHP when any error happens
 */
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    
    // Only handle errors that are currently being reported
    if (!(error_reporting() & $errno)) {
        return false;
    }

    // Map error numbers to readable names
    $types = [
        E_ERROR             => 'Fatal Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
    ];

    $error_type = $types[$errno] ?? 'Unknown Error';

    // Save error into PHP error log (C:\xampp\apache\logs\error.log)
    error_log("[$error_type] $errstr | File: $errfile | Line: $errline");

    // Developer Mode: true = show full details (good for learning)
    //                false = show simple message (for live website)
    $developer_mode = true;

    if ($developer_mode) {
        echo '
        <div style="
            background: #0f172a;
            color: #e2e8f0;
            padding: 25px 30px;
            margin: 30px auto;
            max-width: 900px;
            border-radius: 14px;
            font-family: Consolas, monospace;
            border-left: 6px solid #f87171;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        ">
            <h2 style="color:#f87171; margin:0 0 15px 0; font-size:20px;">System Error Detected</h2>
            <p style="margin:8px 0;"><strong style="color:#94a3b8;">Type:</strong> ' . htmlspecialchars($error_type) . '</p>
            <p style="margin:8px 0;"><strong style="color:#94a3b8;">Message:</strong> ' . htmlspecialchars($errstr) . '</p>
            <p style="margin:8px 0;"><strong style="color:#94a3b8;">File:</strong> ' . htmlspecialchars($errfile) . '</p>
            <p style="margin:8px 0;"><strong style="color:#94a3b8;">Line:</strong> ' . $errline . '</p>
            <p style="margin:15px 0 0 0; font-size:13px; color:#64748b;">This error has been logged automatically.</p>
        </div>';
    } else {
        echo '
        <div style="
            background: #fee2e2;
            color: #991b1b;
            padding: 20px;
            margin: 30px auto;
            max-width: 600px;
            border-radius: 10px;
            text-align: center;
            font-family: Arial, sans-serif;
        ">
            <strong>Something went wrong.</strong><br>
            Please contact the administrator.
        </div>';
    }

    // Stop execution on fatal errors
    if (in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        exit(1);
    }

    return true;
}

/**
 * custom_exception_handler()
 * Purpose: Catch uncaught exceptions (when try-catch is missing)
 */
function custom_exception_handler($exception) {
    $message = $exception->getMessage();
    $file    = $exception->getFile();
    $line    = $exception->getLine();

    error_log("Uncaught Exception: $message in $file on line $line");

    echo '
    <div style="
        background: #0f172a;
        color: #e2e8f0;
        padding: 25px 30px;
        margin: 30px auto;
        max-width: 900px;
        border-radius: 14px;
        font-family: Consolas, monospace;
        border-left: 6px solid #f87171;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    ">
        <h2 style="color:#f87171; margin:0 0 15px 0; font-size:20px;">Uncaught Exception</h2>
        <p style="margin:8px 0;"><strong style="color:#94a3b8;">Message:</strong> ' . htmlspecialchars($message) . '</p>
        <p style="margin:8px 0;"><strong style="color:#94a3b8;">File:</strong> ' . htmlspecialchars($file) . '</p>
        <p style="margin:8px 0;"><strong style="color:#94a3b8;">Line:</strong> ' . $line . '</p>
    </div>';
}

// Register the handlers so PHP uses them
set_error_handler("custom_error_handler");
set_exception_handler("custom_exception_handler");

// Also catch fatal errors that happen at the very end
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        custom_error_handler($error['type'], $error['message'], $error['file'], $error['line']);
    }
});
