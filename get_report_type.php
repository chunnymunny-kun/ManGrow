<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Report ID required']);
    exit();
}

$report_id = $connection->real_escape_string($_GET['report_id']);

// First check userreportstbl
$sql = "SELECT report_type FROM userreportstbl WHERE report_id = '$report_id' LIMIT 1";
$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'report_type' => $row['report_type']
    ]);
} else {
    // Fallback to checking report prefixes
    if (strpos($report_id, 'MR-') === 0) {
        echo json_encode([
            'success' => true,
            'report_type' => 'Mangrove Data Report'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'report_type' => 'Illegal Activity Report'
        ]);
    }
}

$connection->close();
?>