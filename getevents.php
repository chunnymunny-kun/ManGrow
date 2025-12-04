<?php
header('Content-Type: application/json');
require_once 'database.php'; // Include your database connection

if (isset($_GET['event_id'])) {
    $eventId = $_GET['event_id'];
    
    $query = "SELECT * FROM eventstbl WHERE event_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $eventId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($event = mysqli_fetch_assoc($result)) {
        echo json_encode($event);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Event ID not provided']);
}
?>