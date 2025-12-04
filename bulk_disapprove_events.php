<?php
session_start();
include 'database.php';

// Check if user is authorized
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Barangay Official' && 
    $_SESSION["accessrole"] != 'Administrator' && 
    $_SESSION["accessrole"] != 'Representative')) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Get the event IDs and reason from POST data
$eventIds = json_decode($_POST['event_ids'] ?? '[]', true);
$disapprovalReason = $_POST['disapproval_reason'] ?? '';

if(empty($eventIds)) {
    die(json_encode(['success' => false, 'message' => 'No events selected']));
}

if(empty($disapprovalReason)) {
    die(json_encode(['success' => false, 'message' => 'Disapproval reason is required']));
}

// Convert all IDs to integers for safety
$eventIds = array_map('intval', $eventIds);
$placeholders = implode(',', array_fill(0, count($eventIds), '?'));

// Prepare the query with role-based restrictions
$query = "UPDATE eventstbl 
          SET is_approved = 'Disapproved', 
              disapproval_reason = ?,
              approved_by = ?, 
              approved_at = NOW() 
          WHERE event_id IN ($placeholders) AND is_approved = 'Pending'";

// Add location restriction for Barangay Officials
if($_SESSION['accessrole'] == 'Barangay Official') {
    $query .= " AND barangay = ? AND city_municipality = ?";
}

$stmt = mysqli_prepare($connection, $query);

if(!$stmt) {
    die(json_encode(['success' => false, 'message' => 'Database error']));
}

// Bind parameters
$approvedBy = $_SESSION['name'] ?? 'System';
$params = [$disapprovalReason, $approvedBy];
$types = 'ss' . str_repeat('i', count($eventIds));

foreach($eventIds as $id) {
    $params[] = $id;
    $types .= 'i';
}

// Add location parameters for Barangay Officials
if($_SESSION['accessrole'] == 'Barangay Official') {
    $params[] = $_SESSION['barangay'];
    $params[] = $_SESSION['city_municipality'];
    $types .= 'ss';
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
$result = mysqli_stmt_execute($stmt);
$affectedRows = mysqli_stmt_affected_rows($stmt);

if($result) {
    $_SESSION['response'] = [
        'status' => 'success',
        'msg' => "$affectedRows events disapproved successfully"
    ];
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Error disapproving events: ' . mysqli_error($connection)
    ];
}

mysqli_stmt_close($stmt);
mysqli_close($connection);

header('Location: adminpage.php');
exit();
?>