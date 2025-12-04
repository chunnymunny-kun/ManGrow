<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

// Check if user is logged in and is an administrator
if(!isset($_SESSION["accessrole"]) || $_SESSION["accessrole"] != 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin privileges required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$profile_id = $input['profile_id'] ?? 0;
$reason = $input['reason'] ?? 'Archived by administrator';

if (empty($profile_id)) {
    echo json_encode(['success' => false, 'message' => 'Profile ID is required']);
    exit;
}

try {
    // Start transaction
    $connection->autocommit(false);
    
    // First, get the profile details for logging
    $profile_sql = "SELECT profile_id, barangay, city_municipality, status FROM barangayprofiletbl WHERE profile_id = ?";
    $profile_stmt = $connection->prepare($profile_sql);
    $profile_stmt->bind_param("i", $profile_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    
    if ($profile_result->num_rows === 0) {
        $profile_stmt->close();
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
        exit;
    }
    
    $profile = $profile_result->fetch_assoc();
    $profile_stmt->close();
    
    // Check if profile is already archived
    if ($profile['status'] !== 'published') {
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Profile is not currently active']);
        exit;
    }
    
    // Update profile status to archived
    $update_sql = "UPDATE barangayprofiletbl SET status = 'archived', date_edited = NOW() WHERE profile_id = ?";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("i", $profile_id);
    
    if (!$update_stmt->execute()) {
        $update_stmt->close();
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to archive profile: ' . $update_stmt->error]);
        exit;
    }
    $update_stmt->close();
    
    // Create log entry for the archive action
    date_default_timezone_set('Asia/Manila');
    $log_date = date('Y-m-d H:i:s');
    $description = "Administrator " . htmlspecialchars($_SESSION['name']) . " archived profile for barangay " . htmlspecialchars($profile['barangay']) . ", " . htmlspecialchars($profile['city_municipality']) . ". Reason: " . htmlspecialchars($reason);
    
    $log_sql = "INSERT INTO barangayprofile_logstbl 
                (profile_id, fullname, account_id, account_table_type, barangay, city_municipality, action, log_date, description, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $log_stmt = $connection->prepare($log_sql);
    $log_stmt->bind_param("isisssssss",
        $profile_id,
        $_SESSION['name'],
        $_SESSION['user_id'],
        'adminaccountstbl',
        $profile['barangay'],
        $profile['city_municipality'],
        'archived',
        $log_date,
        $description,
        $log_date
    );
    
    if (!$log_stmt->execute()) {
        $log_stmt->close();
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to create log entry: ' . $log_stmt->error]);
        exit;
    }
    $log_stmt->close();
    
    // Commit transaction
    $connection->commit();
    $connection->autocommit(true);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile archived successfully',
        'profile_id' => $profile_id,
        'barangay' => $profile['barangay'],
        'city_municipality' => $profile['city_municipality']
    ]);
    
} catch (Exception $e) {
    $connection->rollback();
    echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
}

$connection->close();
?>
