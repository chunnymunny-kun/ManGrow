<?php
session_start();
include 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if event ID is provided
if (!isset($_POST['event_id'])) {
    header("Location: events.php");
    exit();
}

$event_id = $_POST['event_id'];
$account_id = $_SESSION['user_id'];

// Check if user already attended
$check = "SELECT * FROM attendeestbl 
          WHERE event_id = $event_id AND account_id = $account_id";
$result = mysqli_query($connection, $check);

if (mysqli_num_rows($result) > 0) {
    // User already attended - remove them
    $delete = "DELETE FROM attendeestbl 
               WHERE event_id = $event_id AND account_id = $account_id";
    mysqli_query($connection, $delete);
    
    // Decrease participant count
    $update = "UPDATE eventstbl 
               SET participants = participants - 1 
               WHERE event_id = $event_id";
    mysqli_query($connection, $update);
} else {
    // User hasn't attended - add them
    $insert = "INSERT INTO attendeestbl (event_id, account_id, count) 
               VALUES ($event_id, $account_id, 1)";
    mysqli_query($connection, $insert);
    
    // Increase participant count
    $update = "UPDATE eventstbl 
               SET participants = participants + 1 
               WHERE event_id = $event_id";
    mysqli_query($connection, $update);
}

// Redirect back to events page
header("Location: events.php");
exit();
?>