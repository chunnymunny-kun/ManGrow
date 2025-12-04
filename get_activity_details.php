<?php
include 'database.php';

header('Content-Type: application/json');

if(!isset($_GET['activity_id']) || !is_numeric($_GET['activity_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid activity ID']);
    exit;
}

$activityId = (int)$_GET['activity_id'];
$query = "SELECT activity_details FROM account_activitytbl WHERE activity_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $activityId);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'details' => $row['activity_details']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Activity not found']);
}
?>