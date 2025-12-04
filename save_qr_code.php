<?php
// save_qr_code.php
session_start();
include 'database.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || $_SESSION['accessrole'] !== 'Administrator') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_id = intval($_POST['profile_id']);
    $qr_code_data = $_POST['qr_code_data'];
    
    // Validate the data URL
    if (strpos($qr_code_data, 'data:image/png;base64,') !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid QR code data format']);
        exit();
    }
    
    // Update the QR code in the database and set status to active
    $sql = "UPDATE barangayprofiletbl SET qr_code = ?, qr_status = 'active', date_edited = NOW() WHERE profile_id = ?";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param("si", $qr_code_data, $profile_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'QR code saved successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save QR code: ' . $connection->error]);
    }
    
    $stmt->close();
    $connection->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>