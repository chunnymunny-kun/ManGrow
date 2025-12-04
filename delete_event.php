<?php
session_start();
include 'database.php';

// Check if user is logged in (optional security measure)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Please login first.'
    ];
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);

    try {
        // Check if the event exists
        $sql = "SELECT thumbnail, program_type FROM eventstbl WHERE event_id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();

        if ($event) {
            // Delete the thumbnail file if it exists
            if (!empty($event['thumbnail']) && file_exists($event['thumbnail'])) {
                unlink($event['thumbnail']);
            }

            // Delete the event from the database
            $sql = "DELETE FROM eventstbl WHERE event_id = ?";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("i", $event_id);

            if ($stmt->execute()) {
                // Update the events.json file
                updateEventsJson($connection);
                
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => htmlspecialchars($event['program_type']) . ' deleted successfully!'
                ];
            } else {
                throw new Exception('Error deleting event: ' . $connection->error);
            }
        } else {
            throw new Exception('Event not found');
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
    }

    // Close statements and connection
    if (isset($stmt)) $stmt->close();
    $connection->close();
    
    header("Location: events.php");
    exit();
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid request'
    ];
    header("Location: events.php");
    exit();
}

// Function to update events.json (same as in other scripts)
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
?>