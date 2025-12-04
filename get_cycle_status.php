<?php
/**
 * Get Cycle Status - AJAX Endpoint
 * Returns current status of a cycle for auto-monitoring
 */

session_start();
require_once 'database.php';

// Check admin access
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Administrator' && $_SESSION["accessrole"] != 'Barangay Official')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$cycleId = isset($_GET['cycle_id']) ? intval($_GET['cycle_id']) : 0;

if (!$cycleId) {
    echo json_encode(['error' => 'Invalid cycle ID']);
    exit();
}

$stmt = $connection->prepare("SELECT cycle_id, cycle_name, start_date, end_date, status, created_at, ended_at, finalized_at FROM reward_cycles WHERE cycle_id = ?");
$stmt->bind_param("i", $cycleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Cycle not found']);
    exit();
}

$cycle = $result->fetch_assoc();

// Check if cycle should be automatically ended based on datetime
$now = new DateTime();
$endDate = new DateTime($cycle['end_date']);

$autoEnded = false;
if ($cycle['status'] === 'active' && $now > $endDate) {
    // Automatically end cycle if past end datetime
    $updateStmt = $connection->prepare("UPDATE reward_cycles SET status = 'ended', ended_at = NOW() WHERE cycle_id = ?");
    $updateStmt->bind_param("i", $cycleId);
    $updateStmt->execute();
    
    $cycle['status'] = 'ended';
    $cycle['ended_at'] = date('Y-m-d H:i:s');
    $autoEnded = true;
}

echo json_encode([
    'success' => true,
    'cycle' => $cycle,
    'auto_ended' => $autoEnded,
    'time_remaining' => $endDate->getTimestamp() - $now->getTimestamp()
]);

$connection->close();
?>
