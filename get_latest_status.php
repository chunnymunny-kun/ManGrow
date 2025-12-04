<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing report_id parameter']);
    exit();
}

$reportId = $connection->real_escape_string($_GET['report_id']);

// Query to get the latest status for this report
$sql = "SELECT action_type 
        FROM report_notifstbl 
        WHERE report_id = '$reportId' 
        ORDER BY notif_date DESC 
        LIMIT 1";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'latest_status' => $row['action_type']
    ]);
} else {
    // No status found, return default
    echo json_encode([
        'success' => true,
        'latest_status' => 'Received'
    ]);
}

$connection->close();
?>