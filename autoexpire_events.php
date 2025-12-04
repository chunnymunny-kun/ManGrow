<?php
require_once 'database.php';

date_default_timezone_set('Asia/Manila');

function autoExpireRegularEvents() {
    global $connection;
    $now = date('Y-m-d H:i:s');
    
    // Select all events past their end date
    $result = $connection->query(
        "SELECT event_id FROM eventstbl 
         WHERE end_date IS NOT NULL 
         AND end_date <= '$now'"
    );
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $stmt = $connection->prepare(
            "UPDATE eventstbl 
             SET event_status = 'Completed'
             WHERE event_id = ? AND is_approved = 'Approved'"
        );
        $stmt->bind_param("i", $row['event_id']);
        if ($stmt->execute()) {
            $count++;
        }
    }
    
    file_put_contents('event_expiration.log', "[" . date('Y-m-d H:i:s') . "] Marked $count events as Completed\n", FILE_APPEND);
    return ['count' => $count];
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $result = autoExpireRegularEvents();
    echo json_encode(['success' => true, 'count' => $result['count']]);
    exit;
}