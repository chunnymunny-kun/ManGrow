<?php
session_start();
include 'database.php';

// Check if user is logged in and has proper role
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Barangay Official' && 
    $_SESSION["accessrole"] != 'Administrator')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if user_id is set in session
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User ID not found in session']);
    exit();
}

// Check if required fields are set
if(!isset($_POST['profile_id']) || empty($_POST['profile_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Profile ID is required']);
    exit();
}

try {
    $profile_id = $_POST['profile_id'];
    
    // First, get the archived profile data
    $select_sql = "SELECT * FROM profile_archivestbl WHERE draft_id = ?";
    $select_stmt = $connection->prepare($select_sql);
    $select_stmt->bind_param("i", $profile_id);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Archived profile not found']);
        $select_stmt->close();
        exit();
    }
    
    $archived_profile = $result->fetch_assoc();
    $select_stmt->close();
    
    // Check if there's already an existing profile with the same barangay and city_municipality
    $check_existing_sql = "SELECT profile_id FROM barangayprofiletbl WHERE barangay = ? AND city_municipality = ?";
    $check_existing_stmt = $connection->prepare($check_existing_sql);
    $check_existing_stmt->bind_param("ss", $archived_profile['barangay'], $archived_profile['city_municipality']);
    $check_existing_stmt->execute();
    $existing_result = $check_existing_stmt->get_result();
    
    if($existing_result->num_rows > 0) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Cannot restore profile. A profile for ' . htmlspecialchars($archived_profile['barangay']) . ', ' . htmlspecialchars($archived_profile['city_municipality']) . ' already exists in the active profiles.'
        ]);
        $check_existing_stmt->close();
        exit();
    }
    $check_existing_stmt->close();
    
    // Insert the profile back into barangayprofiletbl with published status and active QR
    $restore_sql = "INSERT INTO barangayprofiletbl 
                    (profile_key, barangay, city_municipality, mangrove_area, profile_date, species_present, 
                     latitude, longitude, account_id, account_table_type, qr_code, photos, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $restore_stmt = $connection->prepare($restore_sql);
    
    if (!$restore_stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare restore statement: ' . $connection->error]);
        exit();
    }
    
    // Set restore-specific values
    $status = 'published';
    
    $restore_stmt->bind_param("sssdssddissss",
        $archived_profile['profile_key'],
        $archived_profile['barangay'],
        $archived_profile['city_municipality'],
        $archived_profile['mangrove_area'],
        $archived_profile['profile_date'],
        $archived_profile['species_present'],
        $archived_profile['latitude'],
        $archived_profile['longitude'],
        $archived_profile['account_id'], // This maps back to account_id
        $archived_profile['account_table_type'],
        $archived_profile['qr_code'],
        $archived_profile['photos'],
        $status
    );
    
    if (!$restore_stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to restore profile: ' . $restore_stmt->error]);
        $restore_stmt->close();
        exit();
    }
    
    $new_profile_id = $restore_stmt->insert_id;
    $restore_stmt->close();
    
    // Delete the profile from archive table
    $delete_archive_sql = "DELETE FROM profile_archivestbl WHERE draft_id = ?";
    $delete_archive_stmt = $connection->prepare($delete_archive_sql);
    $delete_archive_stmt->bind_param("i", $profile_id);
    
    if($delete_archive_stmt->execute()) {
        // --- Create log entry for restored action ---
        // Determine account table type based on session accessrole
        $account_table_type = ($_SESSION["accessrole"] == 'Administrator') ? 'adminaccountstbl' : 'accountstbl';
        
        // Get current datetime in Philippine timezone
        date_default_timezone_set('Asia/Manila');
        $log_date = date('Y-m-d H:i:s');
        $action = 'restored';
        
        // Create description message
        $description = "The " . strtolower($_SESSION["accessrole"]) . " " . htmlspecialchars($_SESSION['name']) . 
                      " restored the profile for barangay " . htmlspecialchars($archived_profile['barangay']) . 
                      ", " . htmlspecialchars($archived_profile['city_municipality']) . " from archives";
        
        // Prepare SQL for logging
        $log_sql = "INSERT INTO barangayprofile_logstbl 
                    (profile_id, fullname, account_id, account_table_type, barangay, city_municipality, 
                     action, log_date, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $log_stmt = $connection->prepare($log_sql);
        
        if ($log_stmt) {
            // Bind parameters for log entry
            $log_stmt->bind_param("isisssssss",
                $new_profile_id, // Use the new profile ID from barangayprofiletbl
                $_SESSION['name'],
                $_SESSION['user_id'],
                $account_table_type,
                $archived_profile['barangay'],
                $archived_profile['city_municipality'],
                $action,
                $log_date,
                $description,
                $log_date // created_at is same as log_date
            );
            
            // Execute log insertion
            if (!$log_stmt->execute()) {
                error_log("Failed to create log entry for profile restoration: " . $log_stmt->error);
            }
            $log_stmt->close();
        } else {
            error_log("Failed to prepare log statement for profile restoration: " . $connection->error);
        }
        
        // Set flash message for successful restoration
        $_SESSION['response'] = [
            'status' => 'success',
            'msg' => 'Profile for ' . htmlspecialchars($archived_profile['barangay']) . ', ' . htmlspecialchars($archived_profile['city_municipality']) . ' has been successfully restored.'
        ];
        
        echo json_encode(['status' => 'success', 'message' => 'Profile restored successfully', 'new_profile_id' => $new_profile_id]);
    } else {
        // If we can't delete from archive, we should also remove from main table to maintain consistency
        $cleanup_sql = "DELETE FROM barangayprofiletbl WHERE profile_id = ?";
        $cleanup_stmt = $connection->prepare($cleanup_sql);
        $cleanup_stmt->bind_param("i", $new_profile_id);
        $cleanup_stmt->execute();
        $cleanup_stmt->close();
        
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove profile from archive: ' . $delete_archive_stmt->error]);
    }
    
    $delete_archive_stmt->close();
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}

$connection->close();
?>
