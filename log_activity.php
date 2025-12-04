<?php
session_start();
include 'database.php';

// Set timezone to Asia/Manila
date_default_timezone_set('Asia/Manila');

// Initialize response
$response = ['status' => 'error', 'message' => ''];

try {
    // Verify required session data
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User ID not found in session');
    }
    if (!isset($_SESSION['name'])) {
        throw new Exception('User name not found in session');
    }

    // Verify required POST data
    $required = ['action_type', 'area_no', 'id', 'city_municipality', 'details'];
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Prepare statement
    $stmt = $connection->prepare("INSERT INTO activity_logtbl (
        user_id, area_no, id, action_type, 
        city_municipality, details, created_at, initiated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $connection->error);
    }

    // Bind parameters
    $created_at = date('Y-m-d H:i:s');
    $bound = $stmt->bind_param(
        "isssssss",
        $_SESSION['user_id'],
        $_POST['area_no'],
        $_POST['id'],
        $_POST['action_type'],
        $_POST['city_municipality'],
        $_POST['details'],
        $created_at,
        $_SESSION['name']
    );

    if (!$bound) {
        throw new Exception('Parameter binding failed');
    }

    // Execute
    if ($stmt->execute()) {
        $response = ['status' => 'success', 'message' => 'Activity logged'];
    } else {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug_info' => [
            'session_data' => [
                'user_id' => $_SESSION['user_id'] ?? null,
                'name' => $_SESSION['name'] ?? null
            ],
            'post_data' => $_POST,
            'timestamp' => $created_at ?? null
        ]
    ];
} finally {
    // Close connections
    if (isset($stmt)) $stmt->close();
    if (isset($connection)) $connection->close();
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}