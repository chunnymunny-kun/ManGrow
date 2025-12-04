<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['report_id']) || !isset($_GET['report_type'])) {
    echo json_encode(['success' => false, 'message' => 'Report ID and type required']);
    exit();
}

$report_id = $connection->real_escape_string($_GET['report_id']);
$report_type = $connection->real_escape_string($_GET['report_type']);

// Get latest notification with description
$sql = "SELECT *
        FROM report_notifstbl 
        WHERE report_id = '$report_id' 
        AND report_type = '$report_type'
        ORDER BY notif_date DESC 
        LIMIT 1";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    $notification = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'notification' => [
            'action_type' => $notification['action_type'],
            'notif_description' => $notification['notif_description'],
            'notif_date' => $notification['notif_date']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No notifications found']);
}

$connection->close();
?>