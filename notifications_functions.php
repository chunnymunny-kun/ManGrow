<?php
function createEventNotification($event_id, $notif_type, $receiver_id, $additional_data = []) {
    global $connection;
    
    // Set Philippine timezone
    date_default_timezone_set('Asia/Manila');
    $current_time = date('Y-m-d H:i:s');
    
    // Get event details
    $event_query = "SELECT subject, author FROM eventstbl WHERE event_id = ?";
    $stmt = mysqli_prepare($connection, $event_query);
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $event_result = mysqli_stmt_get_result($stmt);
    $event = mysqli_fetch_assoc($event_result);
    
    if (!$event) return false;
    
    // Current user is usually the sender (default to system if not logged in)
    $sender_id = $_SESSION['user_id'] ?? 0; // 0 could represent "system"
    
    // Initialize notification message based on type
    $message = '';
    $initiator = null;
    $action_date = null;
    
    switch ($notif_type) {
        case 'event_approval':
            $message = "Your event '{$event['subject']}' has been approved";
            $initiator = $_SESSION['user_id'] ?? null;
            $action_date = $current_time;
            break;
            
        case 'event_disapproval':
            $reason = $additional_data['reason'] ?? 'No reason provided';
            $message = "Your event '{$event['subject']}' was disapproved. Reason: $reason";
            $initiator = $_SESSION['user_id'] ?? null;
            $action_date = $current_time;
            break;
            
        case 'event_created':
            $message = "You created a new event: '{$event['subject']}'";
            break;
            
        case 'event_reminder':
            $start_time = $additional_data['start_time'] ?? '';
            $message = "Reminder: Your event '{$event['subject']}' starts soon at {$start_time}";
            break;
            
        case 'event_update':
            $message = "Your event '{$event['subject']}' has been updated";
            $initiator = $_SESSION['user_id'] ?? null;
            $action_date = $current_time;
            break;
    }
    
    // Insert notification
    $insert_query = "INSERT INTO eventsnotif_tbl 
                    (event_id, sender_id, receiver_id, notif_type, 
                     notif_message, created_at, action_initiator, action_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $insert_query);
    mysqli_stmt_bind_param($stmt, "iiisssis", 
        $event_id, 
        $sender_id, 
        $receiver_id, 
        $notif_type, 
        $message,
        $current_time,
        $initiator,
        $action_date
    );
    
    return mysqli_stmt_execute($stmt);
}

function getTimeAgo($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
?>