<?php
session_start();
include 'database.php';
include 'badge_system_db.php'; // Include badge system

// Initialize badge system
BadgeSystem::init($connection);

// Check if user is authorized
if(!isset($_SESSION["accessrole"])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Please login first'
    ];
    header("Location: index.php");
    exit();
}

// Check if user has permission to approve events
if($_SESSION["accessrole"] != 'Barangay Official' && 
   $_SESSION["accessrole"] != 'Administrator' && 
   $_SESSION["accessrole"] != 'Representative') {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'This account is not authorized'
    ];
    header("Location: index.php");
    exit();
}

// Check if events were selected
if(empty($_POST['selected_events'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'No events selected for approval'
    ];
    header("Location: adminpage.php");
    exit();
}

// Get selected event IDs
$eventIds = explode(',', $_POST['selected_events']);
$eventIds = array_filter($eventIds, 'is_numeric'); // Sanitize

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Get admin details
$user_id = $_SESSION['user_id'] ?? null;
$admin_name = $_SESSION['name'] ?? 'Admin';
$access_role = $_SESSION['accessrole'] ?? '';

// Determine which column to update based on user role
$approved_by_field = '';
if ($access_role == 'Administrator') {
    $approved_by_field = 'approved_by_admin';
} elseif ($access_role == 'Barangay Official' || $access_role == 'Representative') {
    $approved_by_field = 'approved_by';
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Unauthorized access role'
    ];
    header("Location: adminpage.php");
    exit();
}

// Start transaction
$connection->begin_transaction();

try {
    // First update all events to approved status
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $approval_date = date('Y-m-d H:i:s');
    $posted_at = $approval_date; // Same as approval date
    
    $updateQuery = "UPDATE eventstbl 
                  SET is_approved = 'Approved', 
                      $approved_by_field = ?, 
                      approval_date = ?,
                      posted_at = ?
                  WHERE event_id IN ($placeholders) 
                  AND is_approved = 'Pending'";
    
    $stmt = $connection->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception("Database error: " . $connection->error);
    }
    
    $types = 'iss' . str_repeat('i', count($eventIds)); // i for integers (user_id + dates as strings)
    $params = array_merge([$user_id, $approval_date, $posted_at], $eventIds);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating events: " . $stmt->error);
    }
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    if ($affectedRows === 0) {
        throw new Exception("No pending events were found to approve");
    }
    
    // Now process notifications and JSON update for each approved event
    foreach ($eventIds as $event_id) {
        // 1. Get event details for notification
        $event_query = "SELECT author, subject FROM eventstbl WHERE event_id = ?";
        $stmt = $connection->prepare($event_query);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $event_result = $stmt->get_result();
        $event = $event_result->fetch_assoc();
        $stmt->close();
        
        if (!$event) continue; // Skip if event not found
        
        $notif_date = date('Y-m-d H:i:s');
        
        // 2. Create approval notification
        $is_approved = 'Approved';
        $notif_type = 'event_approved';
        $notif_details = "Your event '{$event['subject']}' has been approved by $admin_name";
        
        $notifStmt = $connection->prepare("
            INSERT INTO eventsnotif_tbl (
                event_id, 
                author, 
                notif_date, 
                notif_type, 
                accessrole,
                is_approved,
                notif_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $notifStmt->bind_param(
            "iisssss", 
            $event_id,
            $event['author'],
            $notif_date,
            $notif_type,
            $access_role,
            $is_approved,
            $notif_details
        );
        
        if (!$notifStmt->execute()) {
            throw new Exception("Failed to create approval notification for event $event_id: " . $notifStmt->error);
        }
        $notifStmt->close();
        
        // 3. Create posted notification
        $notif_type = 'event_posted';
        
        // Get author's name from accountstbl
        $authorQuery = $connection->prepare("SELECT fullname FROM accountstbl WHERE account_id = ?");
        $authorQuery->bind_param("i", $event['author']);
        $authorQuery->execute();
        $authorResult = $authorQuery->get_result();
        $authorName = $authorResult->fetch_assoc()['fullname'] ?? 'Organizer';
        $authorQuery->close();
        
        $notif_details = "New event posted: '{$event['subject']}' by $authorName";
        
        $postedNotifStmt = $connection->prepare("
            INSERT INTO eventsnotif_tbl (
                event_id, 
                author, 
                notif_date, 
                notif_type, 
                accessrole,
                is_approved,
                notif_details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $postedNotifStmt->bind_param(
            "iisssss", 
            $event_id,
            $event['author'],
            $notif_date,
            $notif_type,
            $access_role,
            $is_approved,
            $notif_details
        );
        
        if (!$postedNotifStmt->execute()) {
            throw new Exception("Failed to create posted notification for event $event_id: " . $postedNotifStmt->error);
        }
        $postedNotifStmt->close();
    }
    
    // 4. Update events.json after all events are processed
    updateEventsJson($connection);
    
    // 5. Award Event Organizer badge to users who got their first event approved
    foreach ($eventIds as $event_id) {
        // Get event author
        $authorQuery = "SELECT author FROM eventstbl WHERE event_id = ?";
        $stmt = $connection->prepare($authorQuery);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();
        
        if ($event) {
            // Check if user has approved events and doesn't have Event Organizer badge
            $checkApprovedEventsQuery = "SELECT COUNT(*) as count FROM eventstbl WHERE author = ? AND is_approved = 'Approved'";
            $stmt = $connection->prepare($checkApprovedEventsQuery);
            $stmt->bind_param("i", $event['author']);
            $stmt->execute();
            $result = $stmt->get_result();
            $approvedEventCount = $result->fetch_assoc()['count'];
            $stmt->close();
            
            // Check if user already has Event Organizer badge
            $checkBadgeQuery = "SELECT badges FROM accountstbl WHERE account_id = ?";
            $stmt = $connection->prepare($checkBadgeQuery);
            $stmt->bind_param("i", $event['author']);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();
            
            $currentBadges = empty($userData['badges']) ? [] : explode(',', $userData['badges']);
            $currentBadges = array_map('trim', $currentBadges);
            $hasEventOrganizerBadge = in_array('Event Organizer', $currentBadges);
            
            // Award Event Organizer badge if user has approved events but doesn't have the badge
            if ($approvedEventCount >= 1 && !$hasEventOrganizerBadge) {
                BadgeSystem::awardBadgeToUser($event['author'], 'Event Organizer');
                
                // Create badge notifications table if it doesn't exist
                $createTableQuery = "CREATE TABLE IF NOT EXISTS badge_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    badge_name VARCHAR(100) NOT NULL,
                    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_badge (user_id, badge_name)
                )";
                $connection->query($createTableQuery);
                
                // Set session flag for immediate badge notification if this user is currently logged in
                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['author']) {
                    $_SESSION['new_badge_awarded'] = [
                        'badge_awarded' => true,
                        'badge_name' => 'Event Organizer',
                        'badge_description' => 'Awarded for organizing your first community event!'
                    ];
                }
            }
        }
    }
    
    // Commit transaction
    $connection->commit();
    
    $_SESSION['response'] = [
        'status' => 'success',
        'msg' => "Successfully approved $affectedRows event(s) and sent notifications"
    ];
    
} catch (Exception $e) {
    $connection->rollback();
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => $e->getMessage()
    ];
}

header("Location: adminpage.php");
exit();

function updateEventsJson($connection) {
    $query = "SELECT 
                event_id,
                subject,
                venue,
                barangay,
                city_municipality,
                area_no,
                latitude,
                longitude,
                start_date,
                end_date,
                event_type,
                participants,
                event_status
              FROM eventstbl
              WHERE latitude IS NOT NULL 
              AND longitude IS NOT NULL
              AND program_type = 'Event'
              AND is_approved = 'Approved'";
    
    $result = $connection->query($query);
    $events = $result->fetch_all(MYSQLI_ASSOC);

    // Convert to GeoJSON format
    $features = [];
    foreach ($events as $event) {
        $features[] = [
            'type' => 'Feature',
            'properties' => [
                'event_id' => $event['event_id'],
                'subject' => $event['subject'],
                'venue' => $event['venue'],
                'barangay' => $event['barangay'],
                'city_municipality' => $event['city_municipality'],
                'area_no' => $event['area_no'],
                'start_date' => $event['start_date'],
                'end_date' => $event['end_date'],
                'event_type' => $event['event_type'],
                'participants' => $event['participants'],
                'event_status' => $event['event_status']
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    (float)$event['longitude'],
                    (float)$event['latitude']
                ]
            ]
        ];
    }

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $features
    ];

    // Save to file with pretty print for readability
    file_put_contents('events.json', json_encode($geojson, JSON_PRETTY_PRINT));
}
?>