<?php
session_start();
include 'database.php';
header('Content-Type: application/json'); // Set JSON header

// Check if user is logged in
if(!isset($_SESSION["user_id"])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Please login first.'
    ];
    
    header("Location: login.php");
    exit();
}

// Check if event ID is provided
if (!isset($_POST['event_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Event ID missing'
    ]);
    exit();
}

$event_id = $_POST['event_id'];
$account_id = $_SESSION['user_id'];
$isAttending = false;
$newCount = 0;

try {
    // Check if user already attended
    $check = "SELECT * FROM attendeestbl 
              WHERE event_id = ? AND account_id = ?";
    $stmt = $connection->prepare($check);
    $stmt->bind_param("ii", $event_id, $account_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User already attended - remove them
        $delete = "DELETE FROM attendeestbl 
                   WHERE event_id = ? AND account_id = ?";
        $stmt = $connection->prepare($delete);
        $stmt->bind_param("ii", $event_id, $account_id);
        $stmt->execute();
        
        // Decrease participant count
        $update = "UPDATE eventstbl 
                   SET participants = participants - 1 
                   WHERE event_id = ?";
        $stmt = $connection->prepare($update);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        
        $isAttending = false;
    } else {
        // User hasn't attended - add them
        $insert = "INSERT INTO attendeestbl (event_id, account_id, count) 
                   VALUES (?, ?, 1)";
        $stmt = $connection->prepare($insert);
        $stmt->bind_param("ii", $event_id, $account_id);
        $stmt->execute();
        
        // Increase participant count
        $update = "UPDATE eventstbl 
                   SET participants = participants + 1 
                   WHERE event_id = ?";
        $stmt = $connection->prepare($update);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        
        $isAttending = true;
    }

    // Get updated participant count
    $countQuery = "SELECT participants FROM eventstbl WHERE event_id = ?";
    $stmt = $connection->prepare($countQuery);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $newCount = $row['participants'];

    // Update the events.json file
    updateEventsJson($connection);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'isAttending' => $isAttending,
        'newCount' => $newCount
    ]);
    exit();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit();
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