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

// Get full history from report_notifstbl
$sql = "SELECT *
        FROM report_notifstbl
        WHERE report_id = '$report_id' 
        AND report_type = '$report_type'
        ORDER BY notif_date DESC";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $historyItem = [
            'status' => $row['action_type'],
            'description' => $row['notif_description'],
            'date' => $row['notif_date'],
            'notifier' => $row['notifier_name'] ?? 'System',
            'notifier_type' => $row['notifier_type']
        ];
        
        // Include admin attachments if they exist
        if (!empty($row['admin_attachments'])) {
            $historyItem['admin_attachments'] = json_decode($row['admin_attachments'], true);
            $historyItem['attachment_count'] = intval($row['attachment_count'] ?? 0);
        }
        
        $history[] = $historyItem;
    }
    
    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'No status history found for this report'
    ]);
}

$connection->close();
?>