<?php
require_once 'database.php';

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

$input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;

// Handle AJAX requests
if (isset($input['event_id'])) {
    $eventId = $input['event_id'];
    $status = $input['status'] ?? '';
    
    if (!in_array($status, ['Featured', 'Normal'])) {
        echo json_encode(['success' => false]);
        exit;
    }
    
    $success = updateFeaturedStatus($eventId, $status);
    echo json_encode(['success' => $success]);
    exit;
}

// Handle cron execution
if (php_sapi_name() === 'cli') {
    autoExpireFeaturedEvents();
    exit;
}

function updateFeaturedStatus($eventId, $status) {
    global $connection;
    
    $stmt = $connection->prepare(
        "UPDATE eventstbl 
         SET featured_status = ?,
             featured_startdate = CASE WHEN ? = 'Featured' THEN COALESCE(featured_startdate, NOW()) ELSE NULL END,
             featured_enddate = CASE WHEN ? = 'Featured' THEN COALESCE(featured_enddate, DATE_ADD(NOW(), INTERVAL 7 DAY)) ELSE NULL END
         WHERE event_id = ?"
    );
    $stmt->bind_param("sssi", $status, $status, $status, $eventId);
    return $stmt->execute();
}

function autoExpireFeaturedEvents() {
    global $connection;
    $now = date('Y-m-d H:i:s');
    
    $result = $connection->query(
        "SELECT event_id FROM eventstbl 
         WHERE featured_status = 'Featured' 
         AND featured_enddate <= '$now'"
    );
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $stmt = $connection->prepare(
            "UPDATE eventstbl 
             SET featured_status = 'Normal',
                 featured_startdate = NULL,
                 featured_enddate = NULL
             WHERE event_id = ?"
        );
        $stmt->bind_param("i", $row['event_id']);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    file_put_contents('featured_events.log', "[" . date('Y-m-d H:i:s') . "] Expired $count events\n", FILE_APPEND);
    echo "Expired $count events\n";
}