<?php
session_start();
require_once 'database.php';
require_once 'eco_points_notification.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing report ID']);
    exit();
}

$userId = $_SESSION['user_id'];
$reportId = intval($input['report_id']);

markEcoPointsNotificationAsShown($userId, $reportId);

echo json_encode(['success' => true]);
?>
