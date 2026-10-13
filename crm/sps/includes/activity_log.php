<?php

/**
 * Activity Logging System
 * 
 * This file contains the SQL query to create the activity_logs table and the
 * PHP function to log user activities.
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy the SQL query below and run it in your database (e.g., HeidiSQL, phpMyAdmin).
 * 2. Include this file at the top of any PHP page where you want to log activity:
 *    include "includes/activity_log.php";
 * 3. Call the logActivity() function after an action is performed.
 */

// ===================================================================================
// 1. SQL QUERY TO CREATE THE `activity_logs` TABLE
// ===================================================================================
// Copy the entire query below and execute it in your database to create the table.
// ===================================================================================

/*
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NULL,
  `action` ENUM('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'VIEW', 'EXPORT', 'UPLOAD', 'DOWNLOAD', 'ARCHIVE', 'RESTORE', 'FAILED_LOGIN') NOT NULL,
  `object_type` VARCHAR(100) NOT NULL COMMENT 'The type of object affected, e.g., member, invoice, user, project',
  `object_id` INT(11) UNSIGNED NULL COMMENT 'The primary key of the affected record',
  `description` TEXT NULL COMMENT 'A human-readable summary of the activity',
  `ip_address` VARCHAR(45) NULL COMMENT 'IP address of the user who performed the action',
  `user_agent` VARCHAR(512) NULL COMMENT 'Browser/device information of the user',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  INDEX `idx_user_id` (`user_id` ASC),
  INDEX `idx_created_at` (`created_at` ASC),
  INDEX `idx_object` (`object_type` ASC, `object_id` ASC),
  CONSTRAINT `fk_activity_logs_user`
    FOREIGN KEY (`user_id`)
    REFERENCES `users` (`user_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8mb4
COLLATE = utf8mb4_unicode_ci
COMMENT = 'Centralized log for tracking all user activities within the system.';
*/


// ===================================================================================
// 2. PHP FUNCTION TO LOG ACTIVITY
// ===================================================================================
// No need to edit below this line. Just include the file and call the function.
// ===================================================================================

/**
 * Logs a user activity to the activity_logs table.
 *
 * @param mysqli $con The active database connection object.
 * @param int $user_id The ID of the user performing the action.
 * @param string $action The action performed (e.g., 'CREATE', 'UPDATE', 'DELETE').
 * @param string $object_type The type of object affected (e.g., 'member', 'invoice', 'project').
 * @param int|null $object_id The primary key of the object that was affected.
 * @param string $description A human-readable description of the activity.
 * @return bool True on success, false on failure.
 */
function logActivity($con, $user_id, $action, $object_type, $object_id = null, $description = '') {
    // Get IP address and user agent from the server superglobal
    $ip_address = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];

    // Prepare the SQL statement to prevent SQL injection
    $query = "INSERT INTO activity_logs (user_id, action, object_type, object_id, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($con, $query);

    if ($stmt) {
        // Bind parameters: i = integer, s = string
        // The 'object_id' can be null, so we bind it as an integer 'i' which works fine.
        mysqli_stmt_bind_param($stmt, "ississs", $user_id, $action, $object_type, $object_id, $description, $ip_address, $user_agent);
        
        // Execute the statement
        $result = mysqli_stmt_execute($stmt);
        
        // Close the statement
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    // Return false if the statement could not be prepared
    return false;
}

?>