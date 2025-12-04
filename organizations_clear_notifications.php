<?php
session_start();
header('Content-Type: application/json');

// Check if request is POST and has action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'clear_notifications') {
        // Clear the first organization join rewards notifications
        if (isset($_SESSION['first_org_join_rewards'])) {
            unset($_SESSION['first_org_join_rewards']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Notifications cleared']);
    } else {
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>