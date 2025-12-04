<?php
// update_events_json.php
require_once 'database.php';

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

try {
    updateEventsJson($connection);
    echo json_encode(['success' => true, 'message' => 'Events JSON updated']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$connection->close();
?>