<?php
session_start();
include 'database.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || $_SESSION['accessrole'] !== 'Administrator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_id = intval($_POST['profile_id']);
    $new_status = $_POST['new_status'];
    
    // Validate status (lowercase only)
    if (!in_array($new_status, ['active', 'inactive'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
        exit();
    }
    
    // Update the QR status in the database
    $sql = "UPDATE barangayprofiletbl SET qr_status = ? WHERE profile_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("si", $new_status, $profile_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'QR status updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update QR status']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}

$connection->close();
?>