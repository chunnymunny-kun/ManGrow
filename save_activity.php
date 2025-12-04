<?php
session_start();
include 'database.php';

// Check if user is logged in and has appropriate role
if(!isset($_SESSION["accessrole"])) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// Get input data
$data = json_decode(file_get_contents('php://input'), true);

if(!$data) {
    die(json_encode(['success' => false, 'message' => 'Invalid data']));
}

try {
    $user_id = $_SESSION['user_id'];
    $action = mysqli_real_escape_string($connection, $data['action']);
    $location = mysqli_real_escape_string($connection, $data['location']);
    $details = mysqli_real_escape_string($connection, $data['details']);
    $coordinates = mysqli_real_escape_string($connection, json_encode($data['coordinates']));
    $timestamp = mysqli_real_escape_string($connection, $data['timestamp']);
    
    $query = "INSERT INTO activities 
        (user_id, action_type, location, details, coordinates, timestamp)
        VALUES ('$user_id', '$action', '$location', '$details', '$coordinates', '$timestamp')";
    
    if(mysqli_query($connection, $query)) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception(mysqli_error($connection));
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}