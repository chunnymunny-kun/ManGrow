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
if(!isset($_POST['profile_key']) || empty($_POST['profile_key'])) {
    echo json_encode(['status' => 'error', 'message' => 'Profile key is required']);
    exit();
}

try {
    $profile_key = $_POST['profile_key'];
    
    // First, get the profile data to verify it exists and for logging
    $select_sql = "SELECT * FROM barangayprofiletbl WHERE profile_key = ?";
    $select_stmt = $connection->prepare($select_sql);
    $select_stmt->bind_param("s", $profile_key);
    $select_stmt->execute();
    $result = $select_stmt->get_result();
    
    if($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Profile not found or invalid profile key']);
        $select_stmt->close();
        exit();
    }
    
    $profile = $result->fetch_assoc();
    $profile_id = $profile['profile_id'];
    $barangay = $profile['barangay'];
    $city_municipality = $profile['city_municipality'];
    $photos = $profile['photos'];
    $select_stmt->close();
    
    // Delete associated photos from server before archiving
    if(!empty($photos)) {
        $photo_paths = explode(',', $photos);
        foreach($photo_paths as $photo_path) {
            $photo_path = trim($photo_path);
            if(file_exists($photo_path)) {
                // Get file extension
                $file_extension = strtolower(pathinfo($photo_path, PATHINFO_EXTENSION));
                
                // List of image file extensions to delete
                $image_extensions = [
                    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif',
                    'webp', 'avif', 'heic', 'heif', 'svg', 'ico', 'raw',
                    'cr2', 'nef', 'arw', 'dng', 'psd', 'ai', 'eps'
                ];
                
                // Delete if it's an image file
                if(in_array($file_extension, $image_extensions)) {
                    unlink($photo_path);
                    
                    // Also try to delete any associated thumbnail if it exists
                    $thumbnail_path = str_replace('/uploads/', '/uploads/thumbnails/', $photo_path);
                    if(file_exists($thumbnail_path)) {
                        unlink($thumbnail_path);
                    }
                }
            }
        }
    }
    
    // Insert the profile data into profile_archivestbl with empty photos field
    $archive_sql = "INSERT INTO profile_archivestbl 
                    (profile_key, barangay, city_municipality, mangrove_area, profile_date, species_present, 
                     latitude, longitude, account_id, account_table_type, qr_code, qr_status, photos, 
                     date_created, date_edited, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $archive_stmt = $connection->prepare($archive_sql);
    
    if (!$archive_stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare archive statement: ' . $connection->error]);
        exit();
    }
    
    // Set archive-specific values - set photos to empty string
    $empty_photos = ''; // Empty photos field for archive
    $qr_status = 'inactive'; // Set QR code as inactive in archive
    $archive_status = 'archived';
    
    $archive_stmt->bind_param("sssdssddisssssss",
        $profile['profile_key'],
        $profile['barangay'],
        $profile['city_municipality'],
        $profile['mangrove_area'],
        $profile['profile_date'],
        $profile['species_present'],
        $profile['latitude'],
        $profile['longitude'],
        $profile['account_id'], // This maps to 'account_id' field in archive table
        $profile['account_table_type'],
        $profile['qr_code'],
        $qr_status,
        $empty_photos, // Use empty string instead of original photos
        $profile['date_created'],
        $profile['date_edited'],
        $archive_status
    );
    
    if (!$archive_stmt->execute()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to archive profile: ' . $archive_stmt->error]);
        $archive_stmt->close();
        exit();
    }
    $archive_stmt->close();
    
    // Now delete the profile from database
    $delete_sql = "DELETE FROM barangayprofiletbl WHERE profile_key = ?";
    $delete_stmt = $connection->prepare($delete_sql);
    $delete_stmt->bind_param("s", $profile_key);
    
    if($delete_stmt->execute()) {
        // --- Create log entry for archived action ---
        // Determine account table type based on session accessrole
        $account_table_type = ($_SESSION["accessrole"] == 'Administrator') ? 'adminaccountstbl' : 'accountstbl';
        
        // Get current datetime in Philippine timezone
        date_default_timezone_set('Asia/Manila');
        $log_date = date('Y-m-d H:i:s');
        $action = 'archived';
        
        // Create description message
        $description = "The " . strtolower($_SESSION["accessrole"]) . " " . htmlspecialchars($_SESSION['name']) . 
                      " archived the profile for barangay " . htmlspecialchars($barangay) . 
                      ", " . htmlspecialchars($city_municipality);
        
        // Prepare SQL for logging
        $log_sql = "INSERT INTO barangayprofile_logstbl 
                    (profile_id, fullname, account_id, account_table_type, barangay, city_municipality, 
                     action, log_date, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $log_stmt = $connection->prepare($log_sql);
        
        if ($log_stmt) {
            // Bind parameters for log entry
            $log_stmt->bind_param("isisssssss",
                $profile_id,
                $_SESSION['name'],
                $_SESSION['user_id'],
                $account_table_type,
                $barangay,
                $city_municipality,
                $action,
                $log_date,
                $description,
                $log_date // created_at is same as log_date
            );
            
            // Execute log insertion
            if (!$log_stmt->execute()) {
                error_log("Failed to create log entry for profile deletion: " . $log_stmt->error);
            }
            $log_stmt->close();
        } else {
            error_log("Failed to prepare log statement for profile deletion: " . $connection->error);
        }
        
        // Set flash message for successful archiving
        $_SESSION['response'] = [
            'status' => 'success',
            'msg' => 'Profile for ' . htmlspecialchars($barangay) . ', ' . htmlspecialchars($city_municipality) . ' has been successfully archived.'
        ];
        
        echo json_encode(['status' => 'success', 'message' => 'Profile archived successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete profile: ' . $delete_stmt->error]);
    }
    
    $delete_stmt->close();
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}

$connection->close();
?>