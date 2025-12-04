<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Report ID required']);
    exit();
}

$report_id = $connection->real_escape_string($_GET['report_id']);

// First get the report type
$sql = "SELECT report_type FROM userreportstbl WHERE report_id = '$report_id' LIMIT 1";
$result = $connection->query($sql);

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit();
}

$row = $result->fetch_assoc();
$report_type = $row['report_type'];

// Now get the account_id based on report type
$account_id = null;
if ($report_type === 'Mangrove Data Report') {
    $sql = "SELECT reporter_id FROM mangrovereporttbl WHERE report_id = '$report_id' LIMIT 1";
} elseif ($report_type === 'Illegal Activity Report') {
    $sql = "SELECT reporter_id FROM illegalreportstbl WHERE report_id = '$report_id' LIMIT 1";
}

$result = $connection->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $account_id = $row['reporter_id'];
}

echo json_encode([
    'success' => true,
    'report_type' => $report_type,
    'account_id' => $account_id
]);

$connection->close();
?>