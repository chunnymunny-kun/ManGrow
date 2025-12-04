<?php
session_start();
require_once 'database.php';
header('Content-Type: application/json');

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$eventId = $data['event_id'] ?? null;
$status = $data['status'] ?? null;

if (!$eventId || !in_array($status, ['Normal', 'Featured'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Verify the user owns this event and get its end date
$stmt = $connection->prepare("SELECT author, end_date FROM eventstbl WHERE event_id = ?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

$event = $result->fetch_assoc();
if ($event['author'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Update status and dates
if ($status === 'Featured') {
    $featuredStart = date('Y-m-d H:i:s');
    
    // Calculate featured end date - use event's end_date if it's sooner than 7 days
    $sevenDaysLater = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    if (!empty($event['end_date'])) {
        // Use whichever is earlier - the event's end date or 7 days from now
        $featuredEnd = (strtotime($event['end_date']) < strtotime($sevenDaysLater)) 
            ? $event['end_date'] 
            : $sevenDaysLater;
    } else {
        // No end date specified, default to 7 days
        $featuredEnd = $sevenDaysLater;
    }
    
    $updateStmt = $connection->prepare("UPDATE eventstbl 
                                      SET featured_status = ?, 
                                          featured_startdate = ?, 
                                          featured_enddate = ?
                                      WHERE event_id = ?");
    $updateStmt->bind_param("sssi", $status, $featuredStart, $featuredEnd, $eventId);
} else {
    $updateStmt = $connection->prepare("UPDATE eventstbl 
                                      SET featured_status = ?, 
                                          featured_startdate = NULL, 
                                          featured_enddate = NULL
                                      WHERE event_id = ?");
    $updateStmt->bind_param("si", $status, $eventId);
}

if ($updateStmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$updateStmt->close();
$connection->close();
?>