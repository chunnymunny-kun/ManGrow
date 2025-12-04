<?php
function ResidentSR() {
    // Require database connection with error handling
    require 'database.php';
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new Exception("Database connection error");
    }

    // Set Philippines timezone
    date_default_timezone_set('Asia/Manila');
    $submission_date = date('Y-m-d H:i:s'); // Current datetime in PH timezone

    // Validate session variables
    if (!isset($_SESSION['name'], $_SESSION['accessrole'], $_SESSION['organization'])) {
        throw new Exception("Session data incomplete");
    }

    $name = $_SESSION['name'];
    $accessrole = $_SESSION['accessrole'];
    $organization = $_SESSION['organization'];
    
    // Validate GET parameters
    $event_title = filter_input(INPUT_GET, 'title') ?? '';
    $program_type = filter_input(INPUT_GET, 'programType') ?? '';
    $venue = filter_input(INPUT_GET, 'venue') ?? '';
    $barangay = filter_input(INPUT_GET, 'barangay') ?? '';
    $city_municipality = filter_input(INPUT_GET, 'city') ?? '';
    $area_no = filter_input(INPUT_GET, 'areaNo') ?? '';
    
    try {
        // Validate POST data
        $measurement_type = in_array($_POST['measurement_type'] ?? '', ['visual_estimate', 'height_pole']) 
            ? $_POST['measurement_type'] 
            : '';
        if (empty($measurement_type)) {
            throw new Exception("Measurement type is required");
        }

        $mangrove_species = filter_input(INPUT_POST, 'mangrove_species') ?? '';
        $soil_condition = filter_input(INPUT_POST, 'soil') ?? '';
        $water_condition = filter_input(INPUT_POST, 'water') ?? '';
        $observation = filter_input(INPUT_POST, 'notes') ?? '';
        $average_height = filter_input(INPUT_POST, 'avg_height') ?? 0;
        
        $height_values = array_fill(0, 5, null);

        // Process height values based on measurement type
        for ($i = 1; $i <= 5; $i++) {
            $field_name = $measurement_type === 'visual_estimate' 
                ? "height_estimate_{$i}" 
                : "exact_height_{$i}";
            
            if (isset($_POST[$field_name]) && $_POST[$field_name] !== '') {
                $height_values[$i-1] = filter_var($_POST[$field_name], FILTER_VALIDATE_FLOAT);
            }
        }

        // Handle file uploads
        $upload_dir = 'uploads/reports/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        $photo_full = uploadFile('photo_full', $upload_dir, true);
        $photo_detail = uploadFile('photo_detail', $upload_dir, false);
        $photo_context = uploadFile('photo_context', $upload_dir, false);
        
        // Prepare SQL query - added submission_date
        $stmt = $connection->prepare("
            INSERT INTO resi_statusreport_tbl (
                name, accessrole, organization,
                event_title, program_type, venue, barangay, city_municipality, area_no,
                measurement_type, mangrove_species,
                height1, height2, height3, height4, height5, average_height,
                soil_condition, water_condition,
                photo_full, photo_detail, photo_context,
                observation, submission_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Updated bind_param with submission_date
        $bind_result = $stmt->bind_param(
            "ssssssssssssdddddsssssss",
            $name,
            $accessrole,
            $organization,
            $event_title,
            $program_type,
            $venue,
            $barangay,
            $city_municipality,
            $area_no,
            $measurement_type,
            $mangrove_species,
            $height_values[0],
            $height_values[1],
            $height_values[2],
            $height_values[3],
            $height_values[4],
            $average_height,
            $soil_condition,
            $water_condition,
            $photo_full,
            $photo_detail,
            $photo_context,
            $observation,
            $submission_date
        );
        
        if (!$bind_result) {
            throw new Exception("Bind failed: " . $stmt->error);
        }
        
        if ($stmt->execute()) {
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Status Report submitted successfully!'
            ];
            return true;
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error submitting report: ' . $e->getMessage()
        ];
        error_log("Report submission error: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function uploadFile($field, $upload_dir, $required = true) {
    if (!isset($_FILES[$field])) {
        if ($required) {
            throw new Exception("Photo $field is required");
        }
        return null;
    }

    $file = $_FILES[$field];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($required && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("Error uploading $field: " . $file['error']);
        }
        return null;
    }

    // Validate file
    $max_size = 5 * 1024 * 1024; // 5MB
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    
    if ($file['size'] > $max_size) {
        throw new Exception("File $field is too large (max 5MB)");
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Invalid file type for $field. Only JPG, PNG, GIF allowed");
    }

    // Generate unique filename
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = uniqid() . '.' . strtolower($file_ext);
    $file_path = $upload_dir . $file_name;

    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception("Failed to upload $field photo");
    }

    return $file_path;
}

function ResidentTP() {
    // Require database connection with error handling
    require 'database.php';
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new Exception("Database connection error");
    }

    // Set Philippines timezone and create submission timestamp
    date_default_timezone_set('Asia/Manila');
    $submission_date = date('Y-m-d H:i:s');

    // Validate session variables
    if (!isset($_SESSION['name'], $_SESSION['email'])) {
        throw new Exception("Session data incomplete");
    }

    $name = $_SESSION['name'];
    $accessrole = $_SESSION['accessrole'];
    $organization = $_SESSION['organization'];
    $email = $_SESSION['email'];
    
    // Validate GET parameters
    $event_title = filter_input(INPUT_GET, 'title') ?? '';
    $program_type = filter_input(INPUT_GET, 'programType') ?? '';
    $venue = filter_input(INPUT_GET, 'venue') ?? '';
    $barangay = filter_input(INPUT_GET, 'barangay') ?? '';
    $city_municipality = filter_input(INPUT_GET, 'city') ?? '';
    $area_no = filter_input(INPUT_GET, 'areaNo') ?? '';
    
    try {
        // Validate POST data
        $participant_phone = filter_input(INPUT_POST, 'phone') ?? '';
        $planting_date = filter_input(INPUT_POST, 'planting_date') ?? '';
        $planting_time = filter_input(INPUT_POST, 'planting_time') ?? '';
        $tree_count = filter_input(INPUT_POST, 'tree_count', FILTER_VALIDATE_INT) ?? 0;
        $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT) ?? 0;
        $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT) ?? 0;
        $site_name = filter_input(INPUT_POST, 'site_name') ?? '';
        $monitoring_date = filter_input(INPUT_POST, 'monitoring_date') ?? null;
        $remarks = filter_input(INPUT_POST, 'remarks') ?? '';
        
        // Validate required fields
        if (empty($participant_phone) || strlen($participant_phone) !== 11) {
            throw new Exception("Valid 11-digit mobile number is required");
        }
        
        if (empty($planting_date) || empty($planting_time)) {
            throw new Exception("Planting date and time are required");
        }
        
        if ($tree_count < 1) {
            throw new Exception("Number of trees planted must be at least 1");
        }
        
        if (empty($latitude) || empty($longitude)) {
            throw new Exception("GPS coordinates are required");
        }
        
        if (empty($site_name)) {
            throw new Exception("Site name is required");
        }
        
        // Process mangrove species (checkbox values)
        $mangrove_species = [];
        if (isset($_POST['species'])) {
            foreach ($_POST['species'] as $species) {
                $mangrove_species[] = filter_var($species);
            }
        }
        
        if (empty($mangrove_species)) {
            throw new Exception("At least one mangrove species must be selected");
        }
        
        $other_mangrove_species = null;
        if (in_array('Other', $mangrove_species)) {
            $other_mangrove_species = filter_input(INPUT_POST, 'other_species') ?? '';
            if (empty($other_mangrove_species)) {
                throw new Exception("Please specify the other mangrove species");
            }
        }
        
        $mangrove_species_str = implode(',', $mangrove_species);
        
        // Handle file uploads
        $upload_dir = 'uploads/tree_planting/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }
        
        $planting_photo = uploadFile('planting_photo', $upload_dir, true);
        $seedling_photo = uploadFile('seedling_photo', $upload_dir, false);
        $site_photo = uploadFile('site_photo', $upload_dir, false);
        
        // Prepare SQL query - added submission_date
        $stmt = $connection->prepare("
            INSERT INTO resi_treeplanting_tbl (
                event_title, name, accessrole, organization, program_type, venue, barangay, city_municipality, area_no,
                email, participant_phone,
                planting_date, planting_time, tree_count,
                latitude, longitude, site_name,
                mangrove_species, other_mangrove_species,
                planting_photo, seedling_photo, site_photo,
                monitoring_date, remarks, submission_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        // Updated bind_param with submission_date
        $bind_result = $stmt->bind_param(
            "ssssssssssssssddsssssssss",
            $event_title,
            $name,
            $accessrole,
            $organization,
            $program_type,
            $venue,
            $barangay,
            $city_municipality,
            $area_no,
            $email,
            $participant_phone,
            $planting_date,
            $planting_time,
            $tree_count,
            $latitude,
            $longitude,
            $site_name,
            $mangrove_species_str,
            $other_mangrove_species,
            $planting_photo,
            $seedling_photo,
            $site_photo,
            $monitoring_date,
            $remarks,
            $submission_date
        );
        
        if (!$bind_result) {
            throw new Exception("Bind failed: " . $stmt->error);
        }
        
        if ($stmt->execute()) {
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Tree Planting Report submitted successfully!'
            ];
            return true;
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error submitting report: ' . $e->getMessage()
        ];
        error_log("Tree Planting submission error: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function EnvManagerSR($conn, $title, $program_type, $venue, $barangay, $city, $area_no) {
    try {
        // Get all form data directly without validation
        $manager_name = $_POST['specialist_name'];
        $organization = $_POST['specialist_org'];
        $soil_type = $_POST['soil_type'];
        $hydrological_conditions = $_POST['hydrological_conditions'];
        $water_salinity = isset($_POST['water_salinity']) ? $_POST['water_salinity'] : null;
        $pollution = isset($_POST['pollution']) ? $_POST['pollution'] : null;
        $planting_datetime = $_POST['planting_datetime'];
        $scientific_species = $_POST['scientific_species'];
        $planting_density = $_POST['planting_density'];
        $tree_quantity = $_POST['tree_quantity'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $community_participants = $_POST['community_participants'];
        
        // Handle file upload using the provided function
        $upload_dir = 'uploads/';
        $event_image = uploadFile('site_image', $upload_dir, true);
        
        // Current date for submission_date
        $submission_date = date('Y-m-d H:i:s');
        
        // Prepare and execute SQL statement
        $stmt = $conn->prepare("
            INSERT INTO reports (
                submission_date,
                manager_name,
                organization,
                event_title,
                program_type,
                venue,
                barangay,
                city_municipality,
                area_no,
                soil_type,
                hydrological_conditions,
                water_salinity,
                pollution,
                planting_datetime,
                scientific_species,
                planting_density,
                tree_quantity,
                latitude,
                longitude,
                community_participants,
                event_image
            ) VALUES (
                :submission_date,
                :manager_name,
                :organization,
                :event_title,
                :program_type,
                :venue,
                :barangay,
                :city_municipality,
                :area_no,
                :soil_type,
                :hydrological_conditions,
                :water_salinity,
                :pollution,
                :planting_datetime,
                :scientific_species,
                :planting_density,
                :tree_quantity,
                :latitude,
                :longitude,
                :community_participants,
                :event_image
            )
        ");
        
        // Bind parameters
        $stmt->bindParam(':submission_date', $submission_date);
        $stmt->bindParam(':manager_name', $manager_name);
        $stmt->bindParam(':organization', $organization);
        $stmt->bindParam(':event_title', $title);
        $stmt->bindParam(':program_type', $program_type);
        $stmt->bindParam(':venue', $venue);
        $stmt->bindParam(':barangay', $barangay);
        $stmt->bindParam(':city_municipality', $city);
        $stmt->bindParam(':area_no', $area_no);
        $stmt->bindParam(':soil_type', $soil_type);
        $stmt->bindParam(':hydrological_conditions', $hydrological_conditions);
        $stmt->bindParam(':water_salinity', $water_salinity);
        $stmt->bindParam(':pollution', $pollution);
        $stmt->bindParam(':planting_datetime', $planting_datetime);
        $stmt->bindParam(':scientific_species', $scientific_species);
        $stmt->bindParam(':planting_density', $planting_density);
        $stmt->bindParam(':tree_quantity', $tree_quantity);
        $stmt->bindParam(':latitude', $latitude);
        $stmt->bindParam(':longitude', $longitude);
        $stmt->bindParam(':community_participants', $community_participants);
        $stmt->bindParam(':event_image', $event_image);
        
        // Execute the statement
        return $stmt->execute();
        
    } catch (Exception $e) {
        // Log error or handle as needed
        error_log("EnvManagerSR Error: " . $e->getMessage());
        return false;
    }
}

function BarangayOfficialSR(){
}

function BarangayOfficialTP() {
    // Require database connection
    require 'database.php';
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new Exception("Database connection error");
    }

    // Set timezone
    date_default_timezone_set('Asia/Manila');
    $submission_date = date('Y-m-d H:i:s');

    // Validate session
    if (!isset($_SESSION['name'], $_SESSION['organization'])) {
        throw new Exception("Session data incomplete");
    }

    // Get basic info
    $official_name = $_SESSION['name'];
    $official_brgy = $_SESSION['organization'];
    
    // Get event details from URL
    $event_title = filter_input(INPUT_GET, 'title') ?? '';
    $program_type = filter_input(INPUT_GET, 'programType') ?? '';
    $venue = filter_input(INPUT_GET, 'venue') ?? '';
    $barangay = filter_input(INPUT_GET, 'barangay') ?? '';
    $city_municipality = filter_input(INPUT_GET, 'city') ?? '';
    $area_no = filter_input(INPUT_GET, 'areaNo') ?? '';
    
    // Validate POST data
    if (empty($_POST['planting_date']) || empty($_POST['tree_count'])) {
        throw new Exception("Planting date and tree count are required");
    } 
    if (empty($_POST['site_condition'])) {
        throw new Exception("Site condition is required");
    }
    if (empty($_POST['validation_notes'])) {
        throw new Exception("Validation notes are required");
    }
    if (empty($_POST['monitoring_date'])) {
        throw new Exception("Monitoring date is required");
    }

    try {
    // Validate and sanitize input
    $planting_date = filter_input(INPUT_POST, 'planting_date');
    $trees_planted = filter_input(INPUT_POST, 'tree_count', FILTER_VALIDATE_INT);
    $site_condition = filter_input(INPUT_POST, 'site_condition');
    $monitoring_date = filter_input(INPUT_POST, 'monitoring_date');
    $notes = filter_input(INPUT_POST, 'validation_notes');

        // Handle file uploads
        $upload_dir = 'uploads/tree_planting/';
        if (!file_exists($upload_dir) && !mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }

        $image1 = uploadFile('planting_photo_1', $upload_dir, true);
        $image2 = uploadFile('planting_photo_2', $upload_dir, false);
        $image3 = uploadFile('planting_photo_3', $upload_dir, false);

        // Prepare and execute query
        $stmt = $connection->prepare("
            INSERT INTO brgy_treeplanting_tbl (
                submission_date, official_name, official_brgy,
                event_title, program_type, venue, barangay, city_municipality, area_no,
                planting_date, trees_planted, site_condition,
                image1, image2, image3, monitoring_date, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connection->error);
        }
        
        $bind_result = $stmt->bind_param(
            "ssssssssssissssss",
            $submission_date,
            $official_name,
            $official_brgy,
            $event_title,
            $program_type,
            $venue,
            $barangay,
            $city_municipality,
            $area_no,
            $planting_date,
            $trees_planted,
            $site_condition,
            $image1,
            $image2,
            $image3,
            $monitoring_date,
            $notes
        );
        
        if (!$bind_result) {
            throw new Exception("Database error: " . $stmt->error);
        }
        if ($stmt->execute()) {
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Status Report submitted successfully!'
            ];
            return true;
        } else {
            throw new Exception('Database error: ' . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("Submission error: " . $e->getMessage());
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
        return false;
    } finally {
        if (isset($stmt)) {
            $stmt->close();
        }
    }
}

function EnvManagerTP() {
    require 'database.php';
    
    // Basic database check
    if (!isset($connection) || !($connection instanceof mysqli)) {
        throw new Exception("Database connection error");
    }

    date_default_timezone_set('Asia/Manila');
    $submission_date = date('Y-m-d H:i:s');

    try {
        // Get all data directly without validation
        $data = [
            'submission_date' => $submission_date,
            'manager_name' => $_SESSION['name'] ?? '',
            'organization' => $_SESSION['organization'] ?? '',
            'event_title' => $_GET['title'] ?? '',
            'program_type' => $_GET['programType'] ?? '',
            'venue' => $_GET['venue'] ?? '',
            'barangay' => $_GET['barangay'] ?? '',
            'city_municipality' => $_GET['city'] ?? '',
            'area_no' => $_GET['areaNo'] ?? '',
            'planting_date' => $_POST['planting_datetime'] ?? '',
            'trees_planted' => $_POST['trees_planted'] ?? 0,
            'number_participants' => $_POST['number_participants'] ?? 0,
            'group_name' => $_POST['group_name'] ?? '',
            'latitude' => $_POST['latitude'] ?? 0,
            'longitude' => $_POST['longitude'] ?? 0,
            'site_description' => $_POST['site_description'] ?? '',
            'soil_condition' => $_POST['soil_condition'] ?? '',
            'water_condition' => $_POST['water_condition'] ?? '',
            'environmental_observations' => $_POST['environmental_observations'] ?? '',
            'before_photo' => '',
            'before_photo_date' => $_POST['before_photo_date'] ?? null,
            'after_photo' => '',
            'additional_photos' => '',
            'survival_rate' => $_POST['survival_rate'] ?? null,
            'progress_report' => $_POST['progress_report'] ?? '',
            'challenges' => $_POST['challenges'] ?? ''
        ];

        // Handle file uploads if they exist
        $upload_dir = 'uploads/tree_planting/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        if (isset($_FILES['before_photo']) && $_FILES['before_photo']['error'] === UPLOAD_ERR_OK) {
            $data['before_photo'] = uniqid() . '_' . $_FILES['before_photo']['name'];
            move_uploaded_file($_FILES['before_photo']['tmp_name'], $upload_dir . $data['before_photo']);
        }

        if (isset($_FILES['after_photo']) && $_FILES['after_photo']['error'] === UPLOAD_ERR_OK) {
            $data['after_photo'] = uniqid() . '_' . $_FILES['after_photo']['name'];
            move_uploaded_file($_FILES['after_photo']['tmp_name'], $upload_dir . $data['after_photo']);
        }

        // Insert into database
        $stmt = $connection->prepare("
            INSERT INTO envi_treeplanting_tbl (
                submission_date, manager_name, organization,
                event_title, program_type, venue, barangay, city_municipality, area_no,
                planting_date, trees_planted, number_participants, group_name,
                latitude, longitude, site_description, soil_condition, water_condition,
                environmental_observations, before_photo, before_photo_date, after_photo,
                additional_photos, survival_rate, progress_report, challenges
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->bind_param(
            "ssssssssssiisiisssssssssss",
            $data['submission_date'],
            $data['manager_name'],
            $data['organization'],
            $data['event_title'],
            $data['program_type'],
            $data['venue'],
            $data['barangay'],
            $data['city_municipality'],
            $data['area_no'],
            $data['planting_date'],
            $data['trees_planted'],
            $data['number_participants'],
            $data['group_name'],
            $data['latitude'],
            $data['longitude'],
            $data['site_description'],
            $data['soil_condition'],
            $data['water_condition'],
            $data['environmental_observations'],
            $data['before_photo'],
            $data['before_photo_date'],
            $data['after_photo'],
            $data['additional_photos'],
            $data['survival_rate'],
            $data['progress_report'],
            $data['challenges']
        );

        $stmt->execute();
        
        $_SESSION['response'] = [
            'status' => 'success',
            'msg' => 'Report submitted successfully!'
        ];
        return true;

    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error: ' . $e->getMessage()
        ];
        error_log("EnvManagerTP Error: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) $stmt->close();
    }
}
?>