<?php
session_start();
require_once 'database.php';

date_default_timezone_set('Asia/Manila');

$_SESSION['response'] = [
    'status' => 'error',
    'msg' => 'An error occurred'
];

try {
    // DEBUG LOG
    error_log("=== EVENT SUBMISSION START ===");
    error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r(array_keys($_FILES), true));
    
    if (!isset($_SESSION['user_id'])) {
        error_log("ERROR: No user_id in session");
        throw new Exception("User not logged in");
    }

    // VERIFY USER EXISTS IN accountstbl
    $userCheck = $connection->prepare("SELECT account_id FROM accountstbl WHERE account_id = ?");
    $userCheck->bind_param("i", $_SESSION['user_id']);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows === 0) {
        error_log("CRITICAL ERROR: user_id " . $_SESSION['user_id'] . " not found in accountstbl");
        throw new Exception("Invalid user account. Please log in again.");
    }
    $userCheck->close();
    error_log("User verification successful");

    // Check if this is a cross-barangay event
    $isCrossBarangay = 0;
    $requiresSpecialApproval = 0;
    $attachmentsMetadata = null;
    
    if (isset($_POST['barangay']) && isset($_SESSION['barangay'])) {
        if (strtolower($_POST['barangay']) !== strtolower($_SESSION['barangay'])) {
            $isCrossBarangay = 1;
            $requiresSpecialApproval = 1;
            
            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $uploadDir = 'uploads/event_attachments/' . $_SESSION['user_id'] . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $totalSize = 0;
                $maxSize = 50 * 1024 * 1024; // 50MB
                $allowedTypes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'image/jpeg',
                    'image/png'
                ];
                
                $attachments = [];
                
                foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
                    $totalSize += $_FILES['attachments']['size'][$key];
                    
                    if ($totalSize > $maxSize) {
                        throw new Exception("Total attachment size exceeds 50MB limit");
                    }
                    
                    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                    $fileType = finfo_file($fileInfo, $tmpName);
                    finfo_close($fileInfo);
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        throw new Exception("Invalid file type for: " . $_FILES['attachments']['name'][$key]);
                    }
                    
                    $safeName = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $_FILES['attachments']['name'][$key]);
                    $fileName = time() . '_' . $safeName;
                    $destination = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($tmpName, $destination)) {
                        throw new Exception("Failed to upload attachment: " . $_FILES['attachments']['name'][$key]);
                    }
                    
                    $attachments[] = [
                        'name' => $_FILES['attachments']['name'][$key],
                        'path' => $destination,
                        'type' => $fileType,
                        'size' => $_FILES['attachments']['size'][$key],
                        'upload_date' => date('Y-m-d H:i:s')
                    ];
                }
                
                $attachmentsMetadata = json_encode($attachments);
            }
        }
    }

    // Validation for non-announcement events
    if (!isset($_POST['program_type']) || $_POST['program_type'] !== 'Announcement') {
        // Check event_type from either dropdown or manual input
        $eventType = null;
        if (!empty($_POST['event_type'])) {
            $eventType = $_POST['event_type'];
        } elseif (!empty($_POST['manual_event_type'])) {
            $eventType = $_POST['manual_event_type'];
        }
        
        if (empty($eventType)) {
            throw new Exception("Event type is required for non-announcement events");
        }
        
        // Store the event type for use in eventData
        $_POST['event_type'] = $eventType;
        
        // Client-side validation should handle this, but as a fallback:
        $requiredFields = ['title', 'program_type', 'description'];
        // $requiredFields = ['title', 'program_type', 'description', 'venue', 'city', 'barangay'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Required field '$field' is missing");
            }
        }
    }

    // File upload handling
    $thumbnailPath = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($fileInfo, $_FILES['thumbnail']['tmp_name']);
        finfo_close($fileInfo);
        
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception("Invalid file type. Only JPG, PNG, WEBP, and SVG are allowed.");
        }

        $extension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadDir = 'uploads/events/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $destination = $uploadDir . $safeFilename;
        
        if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destination)) {
            throw new Exception("Failed to upload thumbnail");
        }
        
        $thumbnailPath = $destination;
    } else {
        throw new Exception("Thumbnail is required");
    }

    // Prepare event links - accept NULL values
    $eventLinks = null;
    if (!empty($_POST['link'])) {
        $linksArray = array_filter(array_map('trim', explode("\n", $_POST['link'])));
        if (!empty($linksArray)) {
            $eventLinks = json_encode($linksArray);
        }
    }

    // Event data preparation
    // Helper function to convert empty strings to NULL
    $getNullableValue = function($key) {
        if (!isset($_POST[$key])) return null;
        $value = trim($_POST[$key]);
        return empty($value) ? null : $value;
    };
    
    $eventData = [
        'author' => $_SESSION['user_id'],
        'organization' => $_SESSION['organization'] ?? null,
        'thumbnail' => $thumbnailPath,
        'subject' => $_POST['title'],
        'program_type' => $_POST['program_type'],
        'event_type' => $getNullableValue('event_type'),
        'start_date' => $getNullableValue('start_date'),
        'end_date' => $getNullableValue('end_date'),
        'description' => $_POST['description'],
        'venue' => $getNullableValue('venue'),
        'latitude' => !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null,
        'longitude' => !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null,
        'barangay' => $getNullableValue('barangay'),
        'city_municipality' => $getNullableValue('city'),
        'area_no' => $getNullableValue('area_no'),
        'eco_points' => !empty($_POST['eco_points']) ? intval($_POST['eco_points']) : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'posted_at' => null,
        'is_approved' => 'Pending',
        'participants' => 0,
        'event_links' => $eventLinks,
        'is_cross_barangay' => $isCrossBarangay,
        'requires_special_approval' => $requiresSpecialApproval,
        'attachments_metadata' => $attachmentsMetadata,
        'special_notes' => $getNullableValue('special_notes'),
        'disapproval_note' => null
    ];

    // DEBUG LOG: Show event data before insertion
    error_log("Event data prepared:");
    error_log("- author: " . $eventData['author'] . " (type: " . gettype($eventData['author']) . ")");
    error_log("- program_type: " . $eventData['program_type']);
    error_log("- event_type: " . ($eventData['event_type'] ?? 'NULL'));
    error_log("- venue: " . ($eventData['venue'] ?? 'NULL'));
    error_log("- barangay: " . ($eventData['barangay'] ?? 'NULL'));
    error_log("- city: " . ($eventData['city_municipality'] ?? 'NULL'));
    error_log("- area_no: " . ($eventData['area_no'] ?? 'NULL'));
    error_log("Full event data: " . print_r($eventData, true));

    // Handle special cases
    if ($_POST['program_type'] === 'Announcement') {
        $eventData['posted_at'] = date('Y-m-d H:i:s');
        $eventData['is_approved'] = 'Approved';
        $eventData['start_date'] = null;
        $eventData['end_date'] = null;
    } elseif ($_POST['program_type'] === 'Event' && ($_SESSION['accessrole'] ?? '') === 'Barangay Official') {
        $eventData['posted_at'] = date('Y-m-d H:i:s');
        $eventData['is_approved'] = 'Approved';
    }

    // For cross-barangay events by regular users, ensure approval is pending
    if ($isCrossBarangay && ($_SESSION['accessrole'] ?? '') !== 'Barangay Official') {
        $eventData['is_approved'] = 'Pending';
        $eventData['requires_special_approval'] = 1;
    }

    // Start transaction
    $connection->begin_transaction();

    try {
        // Insert event
        $columns = implode(', ', array_keys($eventData));
        $placeholders = implode(', ', array_fill(0, count($eventData), '?'));
        $sql = "INSERT INTO eventstbl ($columns) VALUES ($placeholders)";
        
        error_log("Preparing SQL: " . $sql);
        error_log("Value count: " . count($eventData));
        
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
            error_log("PREPARE FAILED: " . $connection->error);
            throw new Exception("Database error: " . $connection->error);
        }
        
        // Bind parameters
        $types = '';
        $values = [];
        foreach ($eventData as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
            $values[] = $value;
        }
        
        error_log("Bind types: " . $types);
        error_log("Values: " . print_r($values, true));
        
        $stmt->bind_param($types, ...$values);
        
        error_log("Executing INSERT statement...");
        if (!$stmt->execute()) {
            error_log("EXECUTE FAILED: " . $stmt->error);
            error_log("MySQL errno: " . $stmt->errno);
            throw new Exception("Failed to save event: " . $stmt->error);
        }
        
        error_log("INSERT successful! Event ID: " . $connection->insert_id);
        
        $newEventId = $connection->insert_id;

        // Create notification
        $notif_date = date('Y-m-d H:i:s');
        $notif_type = 'event_created';
        $approvalStatus = $eventData['is_approved'];
        
        $notif_details = ($_SESSION['name'] ?? 'User') . " created an event: " . $_POST['title'];
        if ($isCrossBarangay) {
            $notif_details .= " (Cross-barangay event requiring approval)";
        }
        
        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official'){
            $notif_details = ($_SESSION['name'] ?? 'User') . " posted an event: " . $_POST['title'];
        }
        
        $notifStmt = $connection->prepare("
            INSERT INTO eventsnotif_tbl (
                event_id, 
                author, 
                notif_date, 
                notif_type, 
                accessrole,
                is_approved,
                notif_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$notifStmt) {
            throw new Exception("Notification preparation failed: " . $connection->error);
        }
        
        $notifStmt->bind_param(
            "iisssss", 
            $newEventId,
            $_SESSION['user_id'],
            $notif_date,
            $notif_type,
            $_SESSION['accessrole'],
            $approvalStatus,
            $notif_details
        );
        
        if (!$notifStmt->execute()) {
            throw new Exception("Failed to create notification: " . $notifStmt->error);
        }
        
        // Update events.json
        updateEventsJson($connection);
        
        // Commit transaction
        $connection->commit();
        
        $_SESSION['response'] = [
            'status' => 'success',
            'msg' => 'Event created successfully!',
            'event_id' => $newEventId
        ];
        
    } catch (Exception $e) {
        $connection->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => $e->getMessage()
    ];
}

$connection->close();

function getPhilippineTime() {
    $date = new DateTime("now", new DateTimeZone('Asia/Manila'));
    return $date->format('Y-m-d H:i:s');
}

function updateEventsJson($connection) {
    $query = "SELECT 
                event_id,
                subject,
                venue,
                barangay,
                city_municipality,
                area_no,
                latitude,
                longitude,
                start_date,
                end_date,
                event_type,
                participants,
                event_status
              FROM eventstbl
              WHERE latitude IS NOT NULL 
              AND longitude IS NOT NULL
              AND program_type = 'Event'
              AND is_approved = 'Approved'";
    
    $result = $connection->query($query);
    $events = $result->fetch_all(MYSQLI_ASSOC);

    // Convert to GeoJSON format
    $features = [];
    foreach ($events as $event) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'event_id' => $event['event_id'],
                'subject' => $event['subject'],
                'venue' => $event['venue'],
                'barangay' => $event['barangay'],
                'city_municipality' => $event['city_municipality'],
                'area_no' => $event['area_no'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'event_type' => $event['event_type'],
                'participants' => $event['participants'],
                'event_status' => $event['event_status']
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    (float)$event['longitude'],
                    (float)$event['latitude']
                ]
            ]
        ];
    }

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];

    // Save to file with pretty print for readability
    file_put_contents('events.json', json_encode($geojson, JSON_PRETTY_PRINT));
}

// Redirect back to form page
header('Location: events.php');
exit();
?>