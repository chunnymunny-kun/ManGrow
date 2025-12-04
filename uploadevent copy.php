<?php
session_start();
require_once 'database.php';

date_default_timezone_set('Asia/Manila');

$_SESSION['response'] = [
    'status' => 'error',
    'msg' => 'An error occurred'
];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    // Validation for non-announcement events
    if (!isset($_POST['program_type']) || $_POST['program_type'] !== 'Announcement') {
        if (!isset($_POST['event_type'])) {
            throw new Exception("Event type is required for non-announcement events");
        }
        $requiredFields = ['title', 'program_type', 'description', 'venue', 'city', 'barangay'];
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
    $eventData = [
        'author' => $_SESSION['user_id'],
        'organization' => $_SESSION['organization'] ?? null,
        'thumbnail' => $thumbnailPath,
        'subject' => $_POST['title'],
        'program_type' => $_POST['program_type'],
        'event_type' => $_POST['event_type'] ?? null,
        'start_date' => $_POST['start_date'] ?? null,
        'end_date' => $_POST['end_date'] ?? null,
        'description' => $_POST['description'],
        'venue' => $_POST['venue'] ?? null,
        'latitude' => isset($_POST['latitude']) ? floatval($_POST['latitude']) : null,
        'longitude' => isset($_POST['longitude']) ? floatval($_POST['longitude']) : null,
        'barangay' => $_POST['barangay'] ?? null,
        'city_municipality' => $_POST['city'] ?? null,
        'area_no' => $_POST['area_no'] ?? null,
        'eco_points' => $_POST['eco_points'] ?? 0,
        'created_at' => date('Y-m-d H:i:s'),
        'posted_at' => null,
        'is_approved' => 'Pending',
        'participants' => 0,
        'event_links' => $eventLinks // This can now be NULL
    ];

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

    // Start transaction
    $connection->begin_transaction();

    try {
        // Insert event
        $columns = implode(', ', array_keys($eventData));
        $placeholders = implode(', ', array_fill(0, count($eventData), '?'));
        $sql = "INSERT INTO eventstbl ($columns) VALUES ($placeholders)";
        
        $stmt = $connection->prepare($sql);
        if (!$stmt) {
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
        
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save event: " . $stmt->error);
        }
        
        $newEventId = $connection->insert_id;

        // Create notification
        $notif_date = date('Y-m-d H:i:s');
        $notif_type = 'event_created';
        $approvalStatus = 'Pending';
        $notif_details = ($_SESSION['name'] ?? 'User') . " created an event: " . $_POST['title'];
        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official'){
            $notif_details = ($_SESSION['name'] ?? 'User') . " posted an event: " . $_POST['title'];
            $approvalStatus = 'Approved';
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

// Function to update events.json
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