<?php
session_start();
require_once 'database.php';

// Set timezone to Philippine time
date_default_timezone_set('Asia/Manila');

// Initialize response
$_SESSION['response'] = [
    'status' => 'error',
    'msg' => 'An error occurred'
];

try {
    // Validate required session data
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("User not logged in");
    }

    // Validate required POST data
    // Client-side validation should handle this, but as a fallback:
    $requiredFields = ['event_id', 'title', 'program_type', 'description'];
    // Location fields (venue, city, barangay) are now optional to support automatic mode
    // if (isset($_POST['program_type']) && $_POST['program_type'] === 'Event') {
    //     $requiredFields = array_merge($requiredFields, ['venue', 'city', 'barangay']);
    // }
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Required field '$field' is missing");
        }
    }
    
    // Handle event_type from either dropdown or manual input for Events
    if (isset($_POST['program_type']) && $_POST['program_type'] === 'Event') {
        $eventType = null;
        if (!empty($_POST['event_type'])) {
            $eventType = $_POST['event_type'];
        } elseif (!empty($_POST['manual_event_type'])) {
            $eventType = $_POST['manual_event_type'];
        }
        
        if (empty($eventType)) {
            throw new Exception("Event type is required for Events");
        }
        
        // Store the event type for use in eventData
        $_POST['event_type'] = $eventType;
    }

    // Get event ID
    $event_id = intval($_POST['event_id']);

    // Start transaction
    $connection->begin_transaction();

    try {
        // First, get the current event data
        $currentEventStmt = $connection->prepare("SELECT * FROM eventstbl WHERE event_id = ?");
        $currentEventStmt->bind_param("i", $event_id);
        $currentEventStmt->execute();
        $currentEvent = $currentEventStmt->get_result()->fetch_assoc();
        $currentEventStmt->close();

        if (!$currentEvent) {
            throw new Exception("Event not found");
        }

        // Check if this is a cross-barangay event
        $isCrossBarangay = $currentEvent['is_cross_barangay'];
        $requiresSpecialApproval = $currentEvent['requires_special_approval'];
        $attachmentsMetadata = $currentEvent['attachments_metadata'];
        $removedAttachments = [];

        // Check if barangay changed
        if (isset($_POST['barangay'])) {
            $newBarangay = strtolower($_POST['barangay']);
            $userBarangay = isset($_SESSION['barangay']) ? strtolower($_SESSION['barangay']) : '';
            
            if ($newBarangay !== $userBarangay) {
                $isCrossBarangay = 1;
                $requiresSpecialApproval = 1;
            } else {
                $isCrossBarangay = 0;
                $requiresSpecialApproval = 0;
            }
        }

        // Handle removed attachments
        if (!empty($_POST['removed_attachments'])) {
            $removedAttachments = explode('|', $_POST['removed_attachments']);
            
            // Update attachments metadata
            if (!empty($attachmentsMetadata)) {
                $currentAttachments = json_decode($attachmentsMetadata, true);
                if (is_array($currentAttachments)) {
                    $updatedAttachments = array_filter($currentAttachments, function($attachment) use ($removedAttachments) {
                        return !in_array($attachment['path'], $removedAttachments);
                    });
                    $attachmentsMetadata = json_encode(array_values($updatedAttachments));
                }
            }
            
            // Delete the actual files
            foreach ($removedAttachments as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // Handle new file uploads
        if ($isCrossBarangay && !empty($_FILES['attachments']['name'][0])) {
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
            
            $newAttachments = [];
            
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
                
                $newAttachments[] = [
                    'name' => $_FILES['attachments']['name'][$key],
                    'path' => $destination,
                    'type' => $fileType,
                    'size' => $_FILES['attachments']['size'][$key],
                    'upload_date' => date('Y-m-d H:i:s')
                ];
            }
            
            // Merge with existing attachments
            $currentAttachments = [];
            if (!empty($attachmentsMetadata)) {
                $currentAttachments = json_decode($attachmentsMetadata, true);
                if (!is_array($currentAttachments)) {
                    $currentAttachments = [];
                }
            }
            
            $allAttachments = array_merge($currentAttachments, $newAttachments);
            $attachmentsMetadata = json_encode($allAttachments);
        }

        // Handle thumbnail upload
        $thumbnailPath = $currentEvent['thumbnail'];
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
            
            // Delete old thumbnail if it exists
            if (!empty($thumbnailPath) && file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            $thumbnailPath = $destination;
        }

        // Prepare data for database update
        // Helper function to convert empty strings to NULL
        $getNullableValue = function($key) {
            if (!isset($_POST[$key])) return null;
            $value = trim($_POST[$key]);
            return empty($value) ? null : $value;
        };
        
        $eventData = [
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
            'thumbnail' => $thumbnailPath,
            'event_links' => !empty($_POST['link']) ? json_encode(array_filter(array_map('trim', explode("\n", $_POST['link'])))) : null,
            'featured_enddate' => $getNullableValue('featured_enddate'),
            'is_cross_barangay' => $isCrossBarangay,
            'requires_special_approval' => $requiresSpecialApproval,
            'attachments_metadata' => $attachmentsMetadata,
            'special_notes' => $getNullableValue('special_notes'),
            'edited_at' => date('Y-m-d H:i:s')
        ];

        // Handle Announcement specific logic
        if ($_POST['program_type'] === 'Announcement') {
            $eventData['start_date'] = null;
            $eventData['end_date'] = null;
        }

        // Prepare SQL update statement
        $updates = [];
        $types = '';
        $values = [];
        
        foreach ($eventData as $column => $value) {
            $updates[] = "$column = ?";
            $types .= 's'; // All parameters treated as strings
            $values[] = $value;
        }
        
        // Add event_id to values
        $values[] = $event_id;
        $types .= 'i'; // event_id is integer
        
        $sql = "UPDATE eventstbl SET " . implode(', ', $updates) . " WHERE event_id = ?";
        $stmt = $connection->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database error: " . $connection->error);
        }
        
        // Bind parameters
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update database: " . $stmt->error);
        }

        // Update the events.json file
        updateEventsJson($connection);
        
        // Commit transaction
        $connection->commit();
        
        $_SESSION['response'] = [
            'status' => 'success',
            'msg' => htmlspecialchars($_POST['program_type']) . ' updated successfully!'
        ];

    } catch (Exception $e) {
        $connection->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => htmlspecialchars($e->getMessage())
    ];
}

// Close connection
$connection->close();

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
    if (!$result) {
        throw new Exception("Database query failed: " . $connection->error);
    }
    
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
    if (file_put_contents('events.json', json_encode($geojson, JSON_PRETTY_PRINT)) === false) {
        throw new Exception("Failed to write to events.json file");
    }
}

// Redirect back to edit page
header('Location: edit_event.php?event_id=' . intval($_POST['event_id']));
exit();
?>