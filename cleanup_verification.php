<?php
/**
 * Cleanup Verification Tokens
 * Removes expired verification tokens from user_verification table
 * Compatible with shared hosting environments (InfinityFree, etc.)
 * 
 * Usage:
 * 1. Include in other scripts: include 'cleanup_verification.php';
 * 2. Call directly via browser: cleanup_verification.php
 * 3. Set up as cron job if supported by hosting
 */

// Prevent direct access from browser for security (optional)
$direct_access = basename(__FILE__) == basename($_SERVER['SCRIPT_NAME']);

// Include database connection
if (!isset($connection)) {
    include_once 'database.php';
}

/**
 * Clean up expired verification tokens
 * @param mysqli $connection Database connection
 * @return array Result with success status and message
 */
function cleanupExpiredVerifications($connection) {
    try {
        // Check if the table exists first
        $table_check = "SHOW TABLES LIKE 'user_verification'";
        $table_result = mysqli_query($connection, $table_check);
        
        if (mysqli_num_rows($table_result) == 0) {
            return [
                'success' => false,
                'message' => "Table 'user_verification' does not exist.",
                'deleted_count' => 0
            ];
        }
        
        // Delete expired verification records
        $cleanup_query = "DELETE FROM user_verification WHERE token_expiry < NOW()";
        $result = mysqli_query($connection, $cleanup_query);
        
        if ($result) {
            $deleted_count = mysqli_affected_rows($connection);
            
            // Log the cleanup activity
            $log_message = date('Y-m-d H:i:s') . " - Verification cleanup: Deleted {$deleted_count} expired token(s)\n";
            
            // Create logs directory if it doesn't exist
            if (!file_exists('logs')) {
                mkdir('logs', 0755, true);
            }
            
            // Log to file (optional - comment out if not needed)
            file_put_contents('logs/verification_cleanup.log', $log_message, FILE_APPEND | LOCK_EX);
            
            return [
                'success' => true,
                'message' => "Cleanup completed successfully. Deleted {$deleted_count} expired verification token(s).",
                'deleted_count' => $deleted_count,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } else {
            $error_message = mysqli_error($connection);
            error_log("Verification cleanup failed: " . $error_message);
            
            return [
                'success' => false,
                'message' => "Cleanup failed: " . $error_message,
                'deleted_count' => 0
            ];
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Verification cleanup exception: " . $error_message);
        
        return [
            'success' => false,
            'message' => "Cleanup error: " . $error_message,
            'deleted_count' => 0
        ];
    }
}

/**
 * Get statistics about verification tokens
 * @param mysqli $connection Database connection
 * @return array Statistics about active and expired tokens
 */
function getVerificationStats($connection) {
    try {
        // Check if table exists
        $table_check = "SHOW TABLES LIKE 'user_verification'";
        $table_result = mysqli_query($connection, $table_check);
        
        if (mysqli_num_rows($table_result) == 0) {
            return [
                'total_tokens' => 0,
                'active_tokens' => 0,
                'expired_tokens' => 0
            ];
        }
        
        // Get total tokens
        $total_query = "SELECT COUNT(*) as total FROM user_verification";
        $total_result = mysqli_query($connection, $total_query);
        $total_count = mysqli_fetch_assoc($total_result)['total'];
        
        // Get expired tokens
        $expired_query = "SELECT COUNT(*) as expired FROM user_verification WHERE token_expiry < NOW()";
        $expired_result = mysqli_query($connection, $expired_query);
        $expired_count = mysqli_fetch_assoc($expired_result)['expired'];
        
        // Calculate active tokens
        $active_count = $total_count - $expired_count;
        
        return [
            'total_tokens' => $total_count,
            'active_tokens' => $active_count,
            'expired_tokens' => $expired_count
        ];
    } catch (Exception $e) {
        return [
            'total_tokens' => 0,
            'active_tokens' => 0,
            'expired_tokens' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Clean up old tokens (older than 24 hours regardless of expiry)
 * @param mysqli $connection Database connection
 * @return array Result with success status and message
 */
function cleanupOldVerifications($connection) {
    try {
        // Delete tokens older than 24 hours
        $cleanup_query = "DELETE FROM user_verification WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = mysqli_query($connection, $cleanup_query);
        
        if ($result) {
            $deleted_count = mysqli_affected_rows($connection);
            
            return [
                'success' => true,
                'message' => "Old verification cleanup completed. Deleted {$deleted_count} old token(s).",
                'deleted_count' => $deleted_count
            ];
        } else {
            return [
                'success' => false,
                'message' => "Old verification cleanup failed: " . mysqli_error($connection),
                'deleted_count' => 0
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Old verification cleanup error: " . $e->getMessage(),
            'deleted_count' => 0
        ];
    }
}

// If this file is accessed directly via browser or cron job
if ($direct_access) {
    // Set content type to JSON for API-like response
    header('Content-Type: application/json');
    
    // Get action parameter (default to 'cleanup')
    $action = isset($_GET['action']) ? $_GET['action'] : 'cleanup';
    
    // Perform requested action
    switch ($action) {
        case 'stats':
            $result = getVerificationStats($connection);
            echo json_encode([
                'action' => 'stats',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'cleanup_old':
            $cleanup_result = cleanupExpiredVerifications($connection);
            $old_cleanup_result = cleanupOldVerifications($connection);
            
            echo json_encode([
                'action' => 'cleanup_old',
                'expired_cleanup' => $cleanup_result,
                'old_cleanup' => $old_cleanup_result,
                'total_deleted' => $cleanup_result['deleted_count'] + $old_cleanup_result['deleted_count'],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;
            
        case 'cleanup':
        default:
            $result = cleanupExpiredVerifications($connection);
            echo json_encode([
                'action' => 'cleanup',
                'result' => $result,
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;
    }
} else {
    // If included in another script, automatically run cleanup
    // This ensures expired tokens are cleaned up when verification scripts run
    cleanupExpiredVerifications($connection);
}

// Close database connection if we opened it
if (isset($connection) && $connection) {
    // Don't close if connection was passed from another script
    // Only close if we opened it ourselves
    if ($direct_access) {
        mysqli_close($connection);
    }
}
?>
