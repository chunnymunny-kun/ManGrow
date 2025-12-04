<?php
// regenerate_qr.php
session_start();
include 'database.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || $_SESSION['accessrole'] !== 'Administrator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_id = intval($_POST['profile_id']);
    
    // Simply update the status to active
    $update_sql = "UPDATE barangayprofiletbl SET qr_status = 'active', date_edited = NOW() WHERE profile_id = ?";
    $update_stmt = $connection->prepare($update_sql);
    $update_stmt->bind_param("i", $profile_id);
    
    if ($update_stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'QR code status updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update QR status: ' . $connection->error]);
    }
    
    $update_stmt->close();
    $connection->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>