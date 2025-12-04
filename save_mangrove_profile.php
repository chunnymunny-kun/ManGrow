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
$required_fields = ['barangay', 'city_municipality', 'mangrove_area', 'profile_date', 'species_present', 'latitude', 'longitude', 'profile_key'];
foreach($required_fields as $field) {
    if(!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['status' => 'error', 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Determine account table type based on session accessrole
    $account_table_type = ($_SESSION["accessrole"] == 'Administrator') ? 'adminaccountstbl' : 'accountstbl';
    $status = 'published';
    
    // Use the profile key from the form (this should match the QR code)
    $profile_key = $_POST['profile_key'];

    // Sanitize the profile key
    $profile_key = preg_replace('/[^a-z0-9\-]/', '', strtolower($profile_key));

    // Validate key length
    if (strlen($profile_key) < 10 || strlen($profile_key) > 100) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid profile key']);
        exit();
    }

    // First, check if there's already an active profile for this barangay
    $duplicate_check_sql = "SELECT profile_id, profile_date, status FROM barangayprofiletbl 
                           WHERE barangay = ? AND city_municipality = ? AND status = 'published' 
                           ORDER BY profile_date DESC LIMIT 1";
    $duplicate_stmt = $connection->prepare($duplicate_check_sql);
    $duplicate_stmt->bind_param("ss", $_POST['barangay'], $_POST['city_municipality']);
    $duplicate_stmt->execute();
    $duplicate_result = $duplicate_stmt->get_result();

    if($duplicate_result->num_rows > 0) {
        $existing_profile = $duplicate_result->fetch_assoc();
        $duplicate_stmt->close();
        
        echo json_encode([
            'status' => 'duplicate_profile', 
            'message' => 'An active mangrove profile already exists for ' . $_POST['barangay'] . ', ' . $_POST['city_municipality'] . '. Only one active profile per barangay is allowed.',
            'existing_profile_id' => $existing_profile['profile_id'],
            'existing_profile_date' => $existing_profile['profile_date'],
            'barangay' => $_POST['barangay'],
            'city' => $_POST['city_municipality']
        ]);
        exit();
    }
    $duplicate_stmt->close();

    // Then check for profile key conflicts
    $check_sql = "SELECT profile_id FROM barangayprofiletbl WHERE profile_key = ?";
    $check_stmt = $connection->prepare($check_sql);
    $check_stmt->bind_param("s", $profile_key);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if($check_result->num_rows > 0) {
        echo json_encode([
            'status' => 'key_conflict', 
            'message' => 'Profile key already exists. Regenerating QR code...',
            'barangay' => $_POST['barangay'],
            'city' => $_POST['city_municipality']
        ]);
        exit();
    }
    $check_stmt->close();
    
    // Handle file uploads
    $photo_paths = [];
    if(!empty($_FILES['photos']['name'][0])) {
        $upload_dir = 'uploads/mangrove_profiles/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
            $file_name = time() . '_' . basename($_FILES['photos']['name'][$key]);
            $target_path = $upload_dir . $file_name;
            
            if(move_uploaded_file($tmp_name, $target_path)) {
                $photo_paths[] = $target_path;
            }
        }
    }
    
    // Convert photos array to string
    $photos_string = !empty($photo_paths) ? implode(',', $photo_paths) : '';
    
    // Prepare SQL statement - Added profile_key
    $sql = "INSERT INTO barangayprofiletbl 
            (barangay, city_municipality, mangrove_area, profile_date, species_present, 
             latitude, longitude, account_id, account_table_type, qr_code, photos, status, profile_key) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $connection->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $connection->error]);
        exit();
    }
    
    // Bind parameters - Added profile_key
    $stmt->bind_param("ssdssddisssss", 
        $_POST['barangay'],
        $_POST['city_municipality'],
        $_POST['mangrove_area'],
        $_POST['profile_date'],
        $_POST['species_present'],
        $_POST['latitude'],
        $_POST['longitude'],
        $_SESSION['user_id'],
        $account_table_type,
        $_POST['qr_code'],
        $photos_string,
        $status,
        $profile_key
    );
    
    // Execute query
    if($stmt->execute()) {
        $profile_id = $stmt->insert_id;
        
        // --- NEW CODE: Create log entry for published action ---
        // Get current datetime in Philippine timezone
        date_default_timezone_set('Asia/Manila');
        $log_date = date('Y-m-d H:i:s');
        
        // Create description message
        $description = "Administrator " . htmlspecialchars($_SESSION['name']) . " published a new profile for barangay " . htmlspecialchars($_POST['barangay']) . ", " . htmlspecialchars($_POST['city_municipality']);
        
        // Prepare SQL for logging
        $log_sql = "INSERT INTO barangayprofile_logstbl 
                    (profile_id, fullname, account_id, account_table_type, barangay, city_municipality, action, log_date, description, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $log_stmt = $connection->prepare($log_sql);
        
        if ($log_stmt) {
            // Bind parameters for log entry
            $log_stmt->bind_param("isisssssss",
                $profile_id,
                $_SESSION['name'],
                $_SESSION['user_id'],
                $account_table_type,
                $_POST['barangay'],
                $_POST['city_municipality'],
                $status,
                $log_date,
                $description,
                $log_date // created_at is same as log_date
            );
            
            // Execute log insertion
            if (!$log_stmt->execute()) {
                // Log creation failed but original profile was saved
                error_log("Failed to create log entry: " . $log_stmt->error);
            }
            $log_stmt->close();
        } else {
            error_log("Prepare failed for log entry: " . $connection->error);
        }
        
        // --- END OF NEW CODE ---
        
        echo json_encode(['status' => 'success', 'profile_id' => $profile_id, 'profile_key' => $profile_key]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $stmt->error]);
    }
    
    $stmt->close();
    
} catch(Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
}

$connection->close();
?>