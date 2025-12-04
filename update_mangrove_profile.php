<?php
session_start();
include 'database.php';

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $profile_id = $_POST['profile_id'];
    $barangay = $_POST['barangay'];
    $city_municipality = $_POST['city_municipality'];
    $mangrove_area = $_POST['mangrove_area'];
    $profile_date = $_POST['profile_date'];
    $species_present = $_POST['species_present'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $profile_key = $_POST['profile_key'];
    
    // Get current date/time in Philippine timezone
    $date_edited = date('Y-m-d H:i:s');
    
    // Handle photo uploads and removals
    $existing_photos = [];
    
    // First, get the current photos from the database
    $sql = "SELECT photos FROM barangayprofiletbl WHERE profile_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $profile_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
    $stmt->close();
    
    if (!empty($profile['photos'])) {
        $existing_photos = explode(',', $profile['photos']);
    }
    
    // Remove photos that were marked for deletion
    if (!empty($_POST['photos_to_remove'])) {
        $photos_to_remove = $_POST['photos_to_remove'];
        foreach ($photos_to_remove as $photo_path) {
            if (($key = array_search($photo_path, $existing_photos)) !== false) {
                unset($existing_photos[$key]);
                // Optionally delete the file from server
                if (file_exists($photo_path)) {
                    unlink($photo_path);
                }
            }
        }
        $existing_photos = array_values($existing_photos); // Reindex array
    }
    
    // Handle new photo uploads
    $uploaded_photos = [];
    if (!empty($_FILES['new_photos'])) {
        $upload_dir = 'uploads/mangrove_profiles/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['new_photos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['new_photos']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['new_photos']['name'][$key]);
                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_file_name = $profile_key . '_' . uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($tmp_name, $file_path)) {
                    $uploaded_photos[] = $file_path;
                }
            }
        }
    }
    
    // Combine existing and new photos
    $all_photos = array_merge($existing_photos, $uploaded_photos);
    $photos_string = !empty($all_photos) ? implode(',', $all_photos) : '';
    
    // Update the database - include date_edited field
    $sql = "UPDATE barangayprofiletbl SET 
            barangay = ?, 
            city_municipality = ?, 
            mangrove_area = ?, 
            profile_date = ?, 
            species_present = ?, 
            latitude = ?, 
            longitude = ?, 
            photos = ?,
            date_edited = ?
            WHERE profile_id = ?";
    
    $stmt = $connection->prepare($sql);
    $stmt->bind_param(
        "ssdssddssi", 
        $barangay, 
        $city_municipality, 
        $mangrove_area, 
        $profile_date, 
        $species_present, 
        $latitude, 
        $longitude, 
        $photos_string,
        $date_edited,
        $profile_id
    );
    
    if ($stmt->execute()) {
        // Determine account table type based on session accessrole
        $account_table_type = ($_SESSION["accessrole"] == 'Administrator') ? 'adminaccountstbl' : 'accountstbl';
        
        // Create description message for the log
        $description = $_SESSION["accessrole"] . " " . htmlspecialchars($_SESSION['name']) . 
                  " updated the profile for barangay " . htmlspecialchars($barangay) . 
                  ", " . htmlspecialchars($city_municipality);
        
        // Get current datetime in Philippine timezone
        $log_date = date('Y-m-d H:i:s');
        
        // Prepare SQL for logging
        $log_sql = "INSERT INTO barangayprofile_logstbl 
                    (profile_id, fullname, account_id, account_table_type, barangay, city_municipality, 
                     action, log_date, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $log_stmt = $connection->prepare($log_sql);
        
        if ($log_stmt) {
            $action = "updated";
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
                error_log("Failed to create log entry for profile update: " . $log_stmt->error);
            }
            $log_stmt->close();
        } else {
            error_log("Failed to prepare log statement for profile update: " . $connection->error);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error updating profile: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
    $connection->close();
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>