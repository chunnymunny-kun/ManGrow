<?php
session_start();
include 'database.php';
include 'badge_system_db.php'; // Include badge system

// Initialize badge system
BadgeSystem::init($connection);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $event_id = $_POST['event_id'];
    $approval_status = $_POST['approval_status'];
    
    if(isset($_SESSION['user_id'])){
        $user_id = $_SESSION['user_id'];
        $admin_name = $_SESSION['name'] ?? 'Admin';
        $access_role = $_SESSION['accessrole'] ?? '';
    }
    
    $disapproval_note = null;
    $posted_at = null;
    
    // Set Philippine timezone and create date variables
    date_default_timezone_set('Asia/Manila');
    $approval_date = date('Y-m-d H:i:s');

    if ($approval_status == 'Approved') {
        $posted_at = date('Y-m-d H:i:s');
        $edited_at = null;
    }

    if ($approval_status == 'Disapproved' && !empty($_POST['disapproval_reason'])) {
        $disapproval_note = htmlspecialchars($_POST['disapproval_reason'], ENT_QUOTES);
        $disapproval_note = $connection->real_escape_string($disapproval_note);
    }

    // Start transaction
    $connection->begin_transaction();

    try {
        // Validate inputs
        if (!empty($event_id) && in_array($approval_status, ['Approved', 'Disapproved'])) {
            // Determine which column to update based on user role
            $approved_by_field = '';
            $approved_by_value = null;
            
            if ($access_role == 'Administrator') {
                $approved_by_field = 'approved_by_admin';
                $approved_by_value = $user_id;
            } elseif ($access_role == 'Barangay Official' || $access_role == 'Representative') {
                $approved_by_field = 'approved_by';
                $approved_by_value = $user_id;
            } else {
                throw new Exception("Unauthorized access role");
            }
            
            // 1. Update the event status with the appropriate approval field
            $query = "UPDATE eventstbl SET is_approved = ?, disapproval_note = ?, $approved_by_field = ?, approval_date = ?, posted_at = ?, edited_at = ? WHERE event_id = ?";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("ssisssi", $approval_status, $disapproval_note, $approved_by_value, $approval_date, $posted_at, $edited_at, $event_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating event status: " . $stmt->error);
            }
            $stmt->close();

            // 2. Get event details for notification
            $event_query = "SELECT author, subject FROM eventstbl WHERE event_id = ?";
            $stmt = $connection->prepare($event_query);
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $event_result = $stmt->get_result();
            $event = $event_result->fetch_assoc();
            $stmt->close();

            if (!$event) {
                throw new Exception("Event not found");
            }

            // 3. Create appropriate notification
            $notif_date = date('Y-m-d H:i:s');
            $notif_details = '';
            $notif_type = '';
            
            if ($approval_status == 'Approved') {
                $notif_type = 'event_approved';
                $notif_details = "Your event '{$event['subject']}' has been approved by $admin_name";
            } else {
                $notif_type = 'event_disapproved';
                $reason = $disapproval_note ? " Reason: $disapproval_note" : "";
                $notif_details = "Your event '{$event['subject']}' was disapproved by $admin_name.$reason";
            }

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
                $event['author'], // Receiver is the event creator
                $notif_date,
                $notif_type,
                $access_role,
                $approval_status,
                $notif_details
            );
            
            if (!$notifStmt->execute()) {
                throw new Exception("Failed to create notification: " . $notifStmt->error);
            }
            $notifStmt->close();

            // 4. Update events.json if approved
            if ($approval_status == 'Approved') {
                updateEventsJson($connection);
                
                // 5. Award Event Organizer badge if user doesn't have it yet but has approved events
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

                // 6. Create new notification for posted events after approval
                $notif_date = date('Y-m-d H:i:s');
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
                    $event['author'], // Using the event creator's ID instead of admin_id
                    $notif_date,
                    $notif_type,
                    $access_role,
                    $approval_status, 
                    $notif_details
                );

                if (!$postedNotifStmt->execute()) {
                    throw new Exception("Failed to create posted notification: " . $postedNotifStmt->error);
                }
                $postedNotifStmt->close();
            }

            // Commit transaction
            $connection->commit();
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => "Event has been $approval_status successfully"
            ];
        } else {
            throw new Exception("Invalid data submitted");
        }
        
    } catch (Exception $e) {
        $connection->rollback();
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
    }
    
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit();
}

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