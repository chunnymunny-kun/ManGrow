<!--
<?php
    session_start();
    require_once 'database.php';
    include 'badge_system_db.php';
    checkExpiredReports($connection);

    function checkExpiredReports($connection) {
        try {
            // Update expired mangrove reports
            $query = "UPDATE mangrovereporttbl 
                    SET follow_up_status = 'expired'
                    WHERE follow_up_status = 'pending' 
                    AND rejection_timestamp IS NOT NULL
                    AND TIMESTAMPDIFF(HOUR, rejection_timestamp, NOW()) >= 48";
            $connection->query($query);
            $mangroveExpired = $connection->affected_rows;
            
            // Update expired illegal activity reports
            $query = "UPDATE illegalreportstbl 
                    SET follow_up_status = 'expired'
                    WHERE follow_up_status = 'pending' 
                    AND rejection_timestamp IS NOT NULL
                    AND TIMESTAMPDIFF(HOUR, rejection_timestamp, NOW()) >= 48";
            $connection->query($query);
            $illegalExpired = $connection->affected_rows;
            
            // Optional logging (remove in production if not needed)
            if ($mangroveExpired > 0 || $illegalExpired > 0) {
                error_log("Marked $mangroveExpired mangrove and $illegalExpired illegal reports as expired");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error checking expired reports: " . $e->getMessage());
            return false;
        }
    }

    if(!isset($_SESSION["name"]) || !isset($_SESSION["email"]) || !isset($_SESSION["accessrole"]) || !isset($_SESSION["user_id"])){
        header("Location: login.php");
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'You must be logged in to view this page.'
        ];
        exit();
    }

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }
    if(isset($_SESSION["user_id"])){
        $user_id = $_SESSION["user_id"];
    }

    // Function to format date for display
    function formatDate($dateString) {
        return date('F j, Y', strtotime($dateString));
    }

    // Function to format species display for PHP
    function formatSpeciesDisplay($speciesData) {
        if (empty($speciesData)) return 'Not specified';
        
        $speciesMap = [
            'Rhizophora Apiculata' => 'Bakawan Lalake',
            'Rhizophora Mucronata' => 'Bakawan Babae',
            'Avicennia Marina' => 'Bungalon',
            'Sonneratia Alba' => 'Palapat'
        ];
        
        // Check if it's a JSON string (new multiple species format)
        $decoded = json_decode($speciesData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Convert scientific names to common names for display
            $displayNames = array_map(function($species) use ($speciesMap) {
                return isset($speciesMap[trim($species)]) ? $speciesMap[trim($species)] : trim($species);
            }, $decoded);
            
            return implode(', ', $displayNames);
        }
        
        // Check if it's a comma-separated string (multiple species stored as string)
        if (strpos($speciesData, ',') !== false) {
            $speciesArray = explode(',', $speciesData);
            $displayNames = array_map(function($species) use ($speciesMap) {
                $trimmed = trim($species);
                return isset($speciesMap[$trimmed]) ? $speciesMap[$trimmed] : $trimmed;
            }, $speciesArray);
            
            return implode(', ', $displayNames);
        }
        
        // Handle single species
        return isset($speciesMap[trim($speciesData)]) ? $speciesMap[trim($speciesData)] : trim($speciesData);
    }

    // Get all reports for this user from userreportstbl
    $user_reports = [];
    if(isset($user_id)) {
        $query = "SELECT report_id, report_type FROM userreportstbl WHERE account_id = ? ORDER BY report_id DESC";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            $user_reports[] = $row;
        }
        $stmt->close();
    }

    // Organize reports by type
    $mangrove_reports = [];
    $illegal_reports = [];
    
    foreach($user_reports as $report) {
        $report_id = $report['report_id'];
        $report_type = $report['report_type'];
        
        // Get the latest notification status for this report
        $status_query = "SELECT action_type FROM report_notifstbl 
                WHERE report_id = ? 
                AND report_type = ?
                ORDER BY notif_date DESC LIMIT 1";
        $stmt = $connection->prepare($status_query);
        $stmt->bind_param("is", $report_id, $report_type);
        $stmt->execute();
        $status_result = $stmt->get_result();
        $status_row = $status_result->fetch_assoc();
        $action_type = $status_row ? $status_row['action_type'] : 'Received';
        $stmt->close();
        
        if($report_type == 'Mangrove Data Report') {
            // Get details from mangrovereporttbl - ADDED ORDER BY
            $details_query = "SELECT species, created_at, area_no, area_id, city_municipality 
                            FROM mangrovereporttbl 
                            WHERE report_id = ?
                            ORDER BY created_at DESC";
            $stmt = $connection->prepare($details_query);
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $details_result = $stmt->get_result();
            $details = $details_result->fetch_assoc();
            $stmt->close();
            
            if($details) {
                $mangrove_reports[] = [
                    'report_id' => $report_id,
                    'species' => $details['species'],
                    'date' => $details['created_at'],
                    'area_no' => $details['area_no'],
                    'city_municipality' => $details['city_municipality'],
                    'status' => $action_type
                ];
            }
        } 
        elseif($report_type == 'Illegal Activity Report') {
            // Get details from illegalreportstbl - ADDED ORDER BY
            $details_query = "SELECT incident_type, created_at, area_no, city_municipality, priority, rating, points_awarded, badge_awarded 
                            FROM illegalreportstbl 
                            WHERE report_id = ?
                            ORDER BY created_at DESC";
            $stmt = $connection->prepare($details_query);
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $details_result = $stmt->get_result();
            $details = $details_result->fetch_assoc();
            $stmt->close();
            
            if($details) {
                $illegal_reports[] = [
                    'report_id' => $report_id,
                    'incident_type' => $details['incident_type'],
                    'date' => $details['created_at'],
                    'area_no' => $details['area_no'],
                    'city_municipality' => $details['city_municipality'],
                    'priority' => $details['priority'],
                    'status' => $action_type,
                    'rating' => $details['rating'],
                    'points_awarded' => $details['points_awarded'],
                    'badge_awarded' => $details['badge_awarded']
                ];
            }
        }
    }
    
    // Badge notification system
    $showBadgeNotification = false;
    $badgeToShow = null;

    if(isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        // First check for new badge from session (immediate after earning)
        if(isset($_SESSION['new_badge_awarded']) && $_SESSION['new_badge_awarded']['badge_awarded']) {
            $showBadgeNotification = true;
            $badgeToShow = $_SESSION['new_badge_awarded'];
            
            // Mark as permanently notified in database
            $badgeName = $badgeToShow['badge_name'];
            $insertNotificationQuery = "INSERT IGNORE INTO badge_notifications (user_id, badge_name) VALUES (?, ?)";
            $stmt = $connection->prepare($insertNotificationQuery);
            $stmt->bind_param("is", $userId, $badgeName);
            $stmt->execute();
            $stmt->close();
            
            // Clear session to prevent showing again
            unset($_SESSION['new_badge_awarded']);
        } 
        // If no new badge from session, check for existing unnotified badges
        else {
            // Get user's current badges from their profile
            $userQuery = "SELECT badges FROM accountstbl WHERE account_id = ?";
            $stmt = $connection->prepare($userQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $userBadges = $userData['badges'];
                
                if(!empty($userBadges)) {
                    // Parse badges (comma-separated)
                    $badgeList = array_map('trim', explode(',', $userBadges));
                    
                    // Get already notified badges
                    $notifiedQuery = "SELECT badge_name FROM badge_notifications WHERE user_id = ?";
                    $stmt2 = $connection->prepare($notifiedQuery);
                    $stmt2->bind_param("i", $userId);
                    $stmt2->execute();
                    $notifiedResult = $stmt2->get_result();
                    
                    $notifiedBadges = [];
                    while($row = $notifiedResult->fetch_assoc()) {
                        $notifiedBadges[] = $row['badge_name'];
                    }
                    $stmt2->close();
                    
                    // Find badges that haven't been notified
                    $unnotifiedBadges = array_diff($badgeList, $notifiedBadges);
                    
                    if(!empty($unnotifiedBadges)) {
                        // Show notification for the first unnotified badge
                        $badgeToNotify = reset($unnotifiedBadges);
                        $showBadgeNotification = true;
                        
                        // Fetch badge description from database
                        $badgeQuery = "SELECT description FROM badgestbl WHERE badge_name = ? AND is_active = 1";
                        $stmt3 = $connection->prepare($badgeQuery);
                        $stmt3->bind_param("s", $badgeToNotify);
                        $stmt3->execute();
                        $badgeResult = $stmt3->get_result();
                        
                        $badgeDescription = 'Congratulations on earning this badge!';
                        if($badgeResult->num_rows > 0) {
                            $badgeData = $badgeResult->fetch_assoc();
                            $badgeDescription = $badgeData['description'];
                        }
                        $stmt3->close();
                        
                        $badgeToShow = [
                            'badge_awarded' => true,
                            'badge_name' => $badgeToNotify,
                            'badge_description' => $badgeDescription
                        ];
                        
                        // Mark this badge as notified
                        $insertNotificationQuery = "INSERT IGNORE INTO badge_notifications (user_id, badge_name) VALUES (?, ?)";
                        $stmt4 = $connection->prepare($insertNotificationQuery);
                        $stmt4->bind_param("is", $userId, $badgeToNotify);
                        $stmt4->execute();
                        $stmt4->close();
                    }
                }
            }
            $stmt->close();
        }
    }

    // Helper function to get badge descriptions from database
    function getBadgeDescription($badgeName) {
        global $connection;
        
        $query = "SELECT description FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['description'];
        }
        
        $stmt->close();
        return 'Congratulations on earning this badge!';
    }

    // Helper function to get badge icons from database
    function getBadgeIcon($badgeName) {
        global $connection;
        
        $query = "SELECT icon_class FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['icon_class'];
        }
        
        $stmt->close();
        return 'fas fa-star';
    }
    
    // Sort the arrays by date (newest first) to ensure proper display order
    usort($mangrove_reports, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    usort($illegal_reports, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reportspage.css">
    <link rel="stylesheet" href="gamification_notifications.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>
    
    <!-- Badge Notification Styles -->
    <style>
        .badge-notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease-in;
        }

        .badge-notification-modal {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: white;
            animation: bounceIn 0.6s ease-out;
        }

        .badge-celebration .badge-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        .badge-celebration h2 {
            margin: 1rem 0;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .new-badge-display {
            margin: 2rem 0;
            display: flex;
            justify-content: center;
        }

        .badge-card.earned {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .badge.starting-point, .badge.event-organizer, .badge.mangrove-guardian, 
        .badge.watchful-eye, .badge.vigilant-protector, .badge.conservation-champion, 
        .badge.ecosystem-sentinel, .badge.mangrove-legend {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            animation: rotate 3s linear infinite;
        }

        .badge.starting-point { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge.event-organizer { background: linear-gradient(135deg, #e67e22, #d35400); }
        .badge.mangrove-guardian { background: linear-gradient(135deg, #27ae60, #229954); }
        .badge.watchful-eye { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge.vigilant-protector { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .badge.conservation-champion { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .badge.ecosystem-sentinel { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .badge.mangrove-legend { background: linear-gradient(135deg, #8e44ad, #71368a); }

        .badge-description {
            font-size: 1.1rem;
            margin: 1rem 0;
            opacity: 0.9;
        }

        /* Confetti Animation */
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background: #f1c40f;
            animation: confetti-fall 3s linear infinite;
        }

        .confetti:nth-child(1) { left: 10%; animation-delay: 0s; background: #e74c3c; }
        .confetti:nth-child(2) { left: 20%; animation-delay: 0.2s; background: #3498db; }
        .confetti:nth-child(3) { left: 30%; animation-delay: 0.4s; background: #2ecc71; }
        .confetti:nth-child(4) { left: 40%; animation-delay: 0.6s; background: #f39c12; }
        .confetti:nth-child(5) { left: 50%; animation-delay: 0.8s; background: #9b59b6; }
        .confetti:nth-child(6) { left: 60%; animation-delay: 1s; background: #1abc9c; }
        .confetti:nth-child(7) { left: 70%; animation-delay: 1.2s; background: #e67e22; }
        .confetti:nth-child(8) { left: 80%; animation-delay: 1.4s; background: #34495e; }
        .confetti:nth-child(9) { left: 90%; animation-delay: 1.6s; background: #e91e63; }
        .confetti:nth-child(10) { left: 15%; animation-delay: 1.8s; background: #ff5722; }
        .confetti:nth-child(11) { left: 25%; animation-delay: 2s; background: #4caf50; }
        .confetti:nth-child(12) { left: 35%; animation-delay: 2.2s; background: #ff9800; }
        .confetti:nth-child(13) { left: 45%; animation-delay: 2.4s; background: #673ab7; }
        .confetti:nth-child(14) { left: 55%; animation-delay: 2.6s; background: #03a9f4; }
        .confetti:nth-child(15) { left: 65%; animation-delay: 2.8s; background: #8bc34a; }
        .confetti:nth-child(16) { left: 75%; animation-delay: 3s; background: #ffc107; }
        .confetti:nth-child(17) { left: 85%; animation-delay: 0.3s; background: #607d8b; }
        .confetti:nth-child(18) { left: 95%; animation-delay: 0.7s; background: #795548; }

        @keyframes confetti-fall {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }

        .notification-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-view-badge, .btn-close-notification {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-view-badge {
            background: rgba(255, 255, 255, 0.9);
            color: #2c3e50;
        }

        .btn-close-notification {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-view-badge:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-close-notification:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3) translateY(-50px); opacity: 0; }
            50% { transform: scale(1.05) translateY(-20px); }
            70% { transform: scale(0.95) translateY(-10px); }
            100% { transform: scale(1) translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .badge-notification-modal {
                padding: 1.5rem;
                width: 95%;
            }
            
            .notification-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .badge-celebration h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <ul class="nav-list">
                <li>
                    <i class="bx bx-home"></i>
                    <a href="index.php">Home</a>
                </li>
                <li>
                    <i class="bx bx-bulb"></i>
                    <a href="#" class="active">Reports</a>
                </li>
                <li>
                    <i class="bx bx-calendar-event"></i>
                    <a href="events.php">Events</a>
                </li>
                <li>
                    <i class="bx bx-trophy"></i>
                    <a href="leaderboards.php">Leaderboards</a>
                </li>
                <?php if (isset($_SESSION["name"])): ?>
                <li>
                    <i class="bx bx-group"></i>
                    <a href="organizations.php">Organizations</a>
                </li>
                <?php endif; ?>
            </ul>
            <?php 
            if (isset($_SESSION["name"])) {
                // Show profile icon when logged in
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
                // Show login link when not logged in
                echo '<a href="login.php" class="login-link">Login</a>';
            }
            ?>
            </nav>
        </header>
    <aside id="sidebar" class="close">  
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button onclick= "SidebarToggle()"id="toggle-btn" class="rotate">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
                </button>
            </li>
            <hr>
            <li>
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="initiatives.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h168q13-36 43.5-58t68.5-22q38 0 68.5 22t43.5 58h168q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm80-80h280v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80Zm200-190q13 0 21.5-8.5T510-760q0-13-8.5-21.5T480-790q-13 0-21.5 8.5T450-760q0 13 8.5 21.5T480-730Z"/></svg>
                    <span>Initiatives</span>
                </a>
            </li>
            <li>
                <a href="mangrovemappage.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-690v-100q0-42 29-71t71-29h100v100q0 42-29 71t-71 29H440ZM220-450q-58 0-99-41t-41-99v-140h140q58 0 99 41t41 99v140H220ZM640-90q-39 0-74.5-12T501-135l-33 33q-11 11-28 11t-28-11q-11-11-11-28t11-28l33-33q-21-29-33-64.5T400-330q0-100 70-170.5T640-571h241v241q0 100-70.5 170T640-90Zm0-80q67 0 113-47t46-113v-160H640q-66 0-113 46.5T480-330q0 23 5.5 43.5T502-248l110-110q11-11 28-11t28 11q11 11 11 28t-11 28L558-192q18 11 38.5 16.5T640-170Zm1-161Z"/></svg>
                    <span>Explore Map</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="reportspage.php" tabindex="-1">My Reports</a></li>
                    <li><a href="myevents.php" tabindex="-1">My Events</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <a href="about.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-29t36.5-11q19 0 35 11t16 29q0 17-12 29.5T484-540q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>
                    <span>About</span>
                </a>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == "Barangay Official" || $_SESSION['accessrole'] == "Administrator" || $_SESSION['accessrole'] == "Representative")) {
                    ?>
                        <li class="admin-link">
                            <a href="adminpage.php" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M680-280q25 0 42.5-17.5T740-340q0-25-17.5-42.5T680-400q-25 0-42.5 17.5T620-340q0 25 17.5 42.5T680-280Zm0 120q31 0 57-14.5t42-38.5q-22-13-47-20t-52-7q-27 0-52 7t-47 20q16 24 42 38.5t57 14.5ZM480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v227q-19-8-39-14.5t-41-9.5v-147l-240-90-240 90v188q0 47 12.5 94t35 89.5Q310-290 342-254t71 60q11 32 29 61t41 52q-1 0-1.5.5t-1.5.5Zm200 0q-83 0-141.5-58.5T480-280q0-83 58.5-141.5T680-480q83 0 141.5 58.5T880-280q0 83-58.5 141.5T680-80ZM480-494Z"/></svg>
                                <span>Administrator Lobby</span>
                            </a>
                        </li>
                    <?php
                }
            ?>
    </aside>
    <main>
        <?php if(!empty($_SESSION['response'])): ?>
        <div class="flash-container">
            <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
                <?= $_SESSION['response']['msg'] ?>
            </div>
        </div>
        <?php 
        unset($_SESSION['response']); 
        endif; 
        ?>

        <!-- Eco Points Notification for Resolved Reports -->
        <?php
        require_once 'eco_points_notification.php';
        $ecoPointsNotification = getUnnotifiedResolvedReports($user_id);
        if ($ecoPointsNotification) {
            echo generateEcoPointsNotificationCSS();
            echo generateEcoPointsNotificationHTML($ecoPointsNotification);
        }
        ?>

        <!-- Badge Award Notification -->
        <?php if($showBadgeNotification && $badgeToShow): ?>
        <div class="badge-notification-overlay" id="badgeNotification">
            <!-- Confetti Background -->
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            <div class="confetti"></div>
            
            <div class="badge-notification-modal">
                <div class="badge-celebration">
                    <div class="badge-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h2>🎉 Congratulations! 🎉</h2>
                    <p>You've earned a new badge!</p>
                    <div class="new-badge-display">
                        <div class="badge-card earned">
                            <?php 
                            $badgeName = $badgeToShow['badge_name'];
                            $badgeClass = strtolower(str_replace(' ', '-', $badgeName));
                            $badgeIcon = getBadgeIcon($badgeName);
                            ?>
                            <div class="badge <?= $badgeClass ?>">
                                <i class="<?= $badgeIcon ?>"></i>
                            </div>
                            <p><?= htmlspecialchars($badgeName) ?></p>
                        </div>
                    </div>
                    <p class="badge-description"><?= htmlspecialchars($badgeToShow['badge_description']) ?></p>
                    <div class="notification-buttons">
                        <button onclick="viewBadgeDetails()" class="btn-view-badge">View Badge</button>
                        <button onclick="closeBadgeNotification()" class="btn-close-notification">Awesome!</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
            <!-- Profile Details Popup (positioned relative to header) -->
        <div class="profile-details close" id="profile-details">
            <div class="details-box">
                <?php
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                        echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="big-profile-icon">';
                    } else {
                        echo '<div class="big-default-profile-icon"><i class="fas fa-user"></i></div>';
                    }
                ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <?php if(isset($_SESSION["organization"])){ 
                    if(!empty($_SESSION["organization"]) || ($_SESSION["organization"] == "N/A")) {?>
                    <p><?= $_SESSION["organization"] ?></p>
                <?php 
                    }
                } ?>
                <p>Barangay <?= isset($_SESSION["barangay"]) ? $_SESSION["barangay"] : "" ?>, <?= isset($_SESSION["city_municipality"]) ? $_SESSION["city_municipality"] : "" ?></p> 
                <div class="profile-link-container">
                    <a href="profileform.php" class="profile-link">Edit Profile <i class="fa fa-angle-double-right"></i></a>
                </div>
            </div>
            <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <!-- reports page -->
        <div class="reports-container">
            <div class="reports-header">
                <h1>My Reports</h1>
            </div>
            
            <div class="reports-layout">
                <!-- Left column - Mangrove Data Reports -->
                <?php if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == 'Administrator' || $_SESSION['accessrole'] == 'Barangay Official' || $_SESSION['accessrole'] == 'LGU')){?>
                <div class="report-column">
                    <div class="report-tab">
                        <h2>Mangrove Data Reports</h2>
                        <div class="report-frequency-note">
                            <i class='bx bx-info-circle'></i>
                            <span>Mangrove Data Reports are typically submitted twice a year for monitoring purposes.</span>
                        </div>
                        <button class="create-report-btn" onclick="window.location.href='reportform_mdata.php'">
                            <i class='bx bx-plus-circle'></i> Create New Report
                        </button>
                        <div class="filter-group">
                            <select id="filter-mangrove-species">
                                <option value="">All Species</option>
                                <option value="Rhizophora Apiculata">Rhizophora Apiculata</option>
                                <option value="Rhizophora Mucronata">Rhizophora Mucronata</option>
                                <option value="Avicennia Marina">Avicennia Marina</option>
                                <option value="Sonneratia Alba">Sonneratia Alba</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="report-list">
                            <?php foreach($mangrove_reports as $report): ?>
                            <div class="report-item" data-report-id="<?= $report['report_id'] ?>" data-type="mangrove" data-species="<?= htmlspecialchars($report['species']) ?>">
                                <div class="report-summary">
                                    <h3><?= htmlspecialchars(formatSpeciesDisplay($report['species'])) ?></h3>
                                    <p class="report-meta">
                                        <span class="report-date"><?= formatDate($report['date']) ?></span>
                                        <span class="report-priority normal">Normal</span>
                                    </p>
                                    <p class="report-location">
                                        <?= !empty($report['area_no']) ? htmlspecialchars($report['area_no']) . ', ' : '' ?>
                                        <?= htmlspecialchars($report['city_municipality']) ?>
                                    </p>
                                </div>
                                <div class="report-status">
                                    <span class="status-badge <?= strtolower(str_replace('_', '-', $report['status'])) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $report['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($mangrove_reports)): ?>
                                <div class="no-reports">
                                    <i class='bx bx-info-circle'></i>
                                    <p>No mangrove reports found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
                <!-- Middle column - Illegal Activity Reports -->
                <div class="report-column">
                    <div class="report-tab">
                        <h2>Illegal Activity Reports</h2>
                        <div class="report-frequency-note" style="background: #e8f5e9; border-left-color: #4CAF50; color: #2e7d32;">
                            <i class='bx bx-gift'></i>
                            <span>Report illegal activities and earn rewards when your report is resolved!</span>
                        </div>
                        <button class="create-report-btn" onclick="window.location.href='reportform_iacts.php'">
                            <i class='bx bx-plus-circle'></i> Create New Report
                        </button>
                        <div class="filter-group">
                            <select id="filter-incident-type">
                                <option value="">All Incident Types</option>
                                <option value="Illegal Cutting">Illegal Cutting</option>
                                <option value="Waste Dumping">Waste Dumping</option>
                                <option value="Construction">Unauthorized Construction</option>
                                <option value="Harmful Fishing">Fishing with Harmful Methods</option>
                                <option value="Water Pollution">Water Pollution</option>
                                <option value="Fire">Fire in Mangrove Area</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="report-list">
                            <?php foreach($illegal_reports as $report): ?>
                            <div class="report-item" data-report-id="<?= $report['report_id'] ?>" data-type="illegal" data-incident-type="<?= htmlspecialchars($report['incident_type']) ?>">
                                <div class="report-summary">
                                    <h3><?= htmlspecialchars($report['incident_type']) ?></h3>
                                    <p class="report-meta">
                                        <span class="report-date"><?= formatDate($report['date']) ?></span>
                                        <span class="report-priority <?= strtolower($report['priority']) ?>">
                                            <?= htmlspecialchars($report['priority']) ?>
                                        </span>
                                    </p>
                                    <p class="report-location">
                                        <?= !empty($report['area_no']) ? htmlspecialchars($report['area_no']) . ', ' : '' ?>
                                        <?= htmlspecialchars($report['city_municipality']) ?>
                                    </p>
                                </div>
                                <div class="report-status">
                                    <span class="status-badge <?= strtolower(str_replace('_', '-', $report['status'])) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $report['status'])) ?>
                                    </span>
                                    <?php if ($report['status'] === 'Resolved' && !empty($report['rating'])): ?>
                                    <div class="report-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?= $i <= $report['rating'] ? 'filled' : 'empty' ?>">
                                                <?= $i <= $report['rating'] ? '★' : '☆' ?>
                                            </span>
                                        <?php endfor; ?>
                                        <span class="rating-points">+<?= $report['points_awarded'] ?> pts</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($illegal_reports)): ?>
                                <div class="no-reports">
                                    <i class='bx bx-info-circle'></i>
                                    <p>No illegal activity reports found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Right column - Report preview and notifications -->
                <div class="report-preview">
                    <div class="preview-header">
                        <h2>Report Details</h2>
                        <div class="right-part">
                            <div class="preview-actions">
                                <button id="edit-report-btn" class="edit-btn" style="display: none;">
                                    <i class='bx bx-edit'></i> Edit Report
                                </button>
                                <button id="resubmit-report-btn" class="resubmit-btn" style="display: none;">
                                    <i class='bx bx-plus'></i> Resubmit New Report
                                </button>
                            </div>
                            <div class="notification-bell">
                                <i class='bx bx-bell'></i>
                                <span class="notification-count">2</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Default state when no report is selected -->
                    <div class="empty-preview">
                        <i class='bx bx-file-blank'></i>
                        <p>Select a report to view details</p>
                    </div>
                    
                    <!-- Report details (hidden by default, shown when report is selected) -->
                    <div class="report-details" style="display: none;">
                        <div class="report-content">
                            <div class="report-title">
                                <h3 id="preview-title">No title available</h3>
                                <span class="report-status-badge" id="preview-status">No status</span>
                            </div>
                            
                            <div class="report-meta-info">
                                <div class="meta-item">
                                    <i class='bx bx-calendar'></i>
                                    <span id="preview-date">No date indicated</span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-map'></i>
                                    <span id="preview-location">No location indicated</span>
                                </div>
                                <div class="meta-item">
                                    <i class='bx bx-alarm-exclamation'></i>
                                    <span id="preview-priority">No priority indicated</span>
                                </div>
                            </div>
                            
                            <div class="report-images">
                                <div class="no-images">
                                    <i class='bx bx-image-alt'></i>
                                    <p>No images available</p>
                                </div>
                            </div>
                            
                            <div class="report-description">
                                <h4>Details</h4>
                                <p id="preview-description">
                                    No details provided for this report.
                                </p>
                                
                                <div class="additional-info">
                                    <div class="info-row">
                                        <span class="info-label">Species:</span>
                                        <span id="preview-species">Not specified</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Area Size:</span>
                                        <span id="preview-area">Not specified</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Status:</span>
                                        <span id="preview-mangrove-status">Not specified</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="report-notifications">
                            <h3>Updates</h3>
                            <div class="notification-list">
                                <div class="no-notifications">
                                    <i class='bx bx-bell-off'></i>
                                    <p>No updates available for this report</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lightbox Modal for Images -->
        <div id="imageLightbox" class="lightbox-modal">
            <span class="lightbox-close">&times;</span>
            <div class="lightbox-content">
                <img id="lightboxImage" src="" alt="Attachment">
                <div class="lightbox-controls">
                    <button id="prevImage" class="lightbox-btn"><i class='bx bx-chevron-left'></i></button>
                    <span id="imageCounter" class="image-counter"></span>
                    <button id="nextImage" class="lightbox-btn"><i class='bx bx-chevron-right'></i></button>
                </div>
                <div class="lightbox-caption" id="lightboxCaption"></div>
            </div>
        </div>
        
        <!-- Video Player Modal -->
        <div id="videoPlayerModal" class="lightbox-modal">
            <span class="video-close">&times;</span>
            <div class="lightbox-content">
                <video id="videoPlayer" controls>
                    <source src="" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                <div class="lightbox-caption" id="videoCaption"></div>
            </div>
        </div>
    </main>
    <footer>
                <div id="right-footer">
                    <h3>Follow us on</h3>
                    <div id="social-media-footer">
                        <ul>
                            <li>
                                <a href="#">
                                    <i class="fab fa-facebook"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <p>This website is developed by ManGrow. All Rights Reserved.</p>
                </div>
            </footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Report item click handler
    document.addEventListener('click', function(e) {
        const reportItem = e.target.closest('.report-item');
        if (reportItem) {
            const reportId = reportItem.getAttribute('data-report-id');
            const reportType = reportItem.getAttribute('data-type');
            
            // Show loading state while fetching
            showLoadingState();
            
            // Highlight selected report
            document.querySelectorAll('.report-item').forEach(i => i.classList.remove('active'));
            reportItem.classList.add('active');
            
            // Fetch report details
            fetchReportDetails(reportId, reportType);
        }
    });
    
    function showLoadingState() {
        document.querySelector('.empty-preview').style.display = 'none';
        document.getElementById('edit-report-btn').style.display = 'none';
        const detailsSection = document.querySelector('.report-details');
        detailsSection.style.display = 'block';
        
        // Clear previous content and show loading
        document.getElementById('preview-title').textContent = 'Loading...';
        document.getElementById('preview-status').textContent = '';
        document.getElementById('preview-date').textContent = '';
        document.getElementById('preview-location').textContent = 'Loading location...';
        document.getElementById('preview-priority').textContent = '';
        document.getElementById('preview-description').textContent = 'Loading report details...';
        
        // Clear images and show loading
        const imagesContainer = document.querySelector('.report-images');
        imagesContainer.innerHTML = '<div class="no-images"><i class="bx bx-image-alt"></i><p>Loading images...</p></div>';
        
        // Clear notifications and show loading
        const notificationsContainer = document.querySelector('.notification-list');
        notificationsContainer.innerHTML = '<div class="no-notifications"><i class="bx bx-bell-off"></i><p>Loading updates...</p></div>';
    }
    
    function fetchReportDetails(reportId, reportType) {
        fetch(`get_myreport_details.php?report_id=${reportId}&type=${reportType === 'mangrove' ? 'Mangrove Data Report' : 'Illegal Activity Report'}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                updatePreviewSection(data);
            })
            .catch(error => {
                console.error('Error fetching report details:', error);
                showErrorState();
            });
    }
    
    function showErrorState() {
        document.getElementById('edit-report-btn').style.display = 'none';
        const detailsSection = document.querySelector('.report-details');
        detailsSection.style.display = 'block';
        
        document.getElementById('preview-title').textContent = 'Error';
        document.getElementById('preview-status').textContent = 'Error';
        document.getElementById('preview-date').textContent = 'N/A';
        document.getElementById('preview-location').textContent = 'Could not load location';
        document.getElementById('preview-priority').textContent = 'N/A';
        document.getElementById('preview-description').textContent = 'Failed to load report details. Please try again.';
        
        const imagesContainer = document.querySelector('.report-images');
        imagesContainer.innerHTML = '<div class="no-images"><i class="bx bx-image-alt"></i><p>Could not load images</p></div>';
        
        const notificationsContainer = document.querySelector('.notification-list');
        notificationsContainer.innerHTML = '<div class="no-notifications"><i class="bx bx-bell-off"></i><p>Could not load updates</p></div>';
    }
    
    function updatePreviewSection(data) {
        if (data.error) {
            console.error(data.error);
            showErrorState();
            return;
        }

        const report = data.report || {};
        const notifications = data.notifications || [];
        
        // Get the edit button and hide it by default
        const editBtn = document.getElementById('edit-report-btn');
        editBtn.style.display = 'none';

        const resubmitBtn = document.getElementById('resubmit-report-btn');
        resubmitBtn.style.display = 'none';
        
        // Remove any existing countdown first
        const existingCountdown = document.querySelector('.countdown-container');
        if (existingCountdown) {
            existingCountdown.remove();
        }

        // Get the latest status
        const latestStatus = notifications[0] ? notifications[0].action_type : 'Received';
        
        if (latestStatus === 'Rejected') {
            resubmitBtn.style.display = 'inline-flex';
            
            // Set up the direct link based on report type
            if (report.report_type === 'Mangrove Data Report') {
                resubmitBtn.onclick = function() {
                    window.location.href = `reportform_mdata.php?original_report_id=${report.report_id}&report_type=${report.report_type}`;
                };
            } else {
                resubmitBtn.onclick = function() {
                    window.location.href = `reportform_iacts.php?original_report_id=${report.report_id}&report_type=${report.report_type}`;
                };
            }
            
            resubmitBtn.title = "Submit a new report based on this rejected one";
        } else {
            resubmitBtn.title = "Resubmit only available for rejected reports";
        }

        // Only show edit button and countdown for rejected reports within 48-hour window
        if (latestStatus === 'Rejected' && report.rejection_timestamp) {
            const rejectionTime = new Date(report.rejection_timestamp);
            const deadline = new Date(rejectionTime.getTime() + 48 * 60 * 60 * 1000);
            const now = new Date();
            
            if (deadline > now) {
                // Only show edit button if within time window
                editBtn.style.display = 'inline-flex';
                editBtn.onclick = function() {
                    window.location.href = report.report_type === 'Mangrove Data Report' 
                        ? `edit_reportmdata.php?report_id=${report.report_id}&report_type=${report.report_type}`
                        : `edit_reportiacts.php?report_id=${report.report_id}&report_type=${report.report_type}`;
                };
                
                // Add countdown display
                const countdownEl = document.createElement('div');
                countdownEl.className = 'countdown-container';
                countdownEl.innerHTML = `
                    <div class="countdown-header">Follow-up Deadline</div>
                    <div class="countdown-timer" data-deadline="${deadline.toISOString()}">
                        <span class="hours">48</span>:<span class="minutes">00</span>:<span class="seconds">00</span>
                    </div>
                    <div class="countdown-message">You can edit this report until the timer expires</div>
                `;
                
                // Insert after the status badge
                const statusBadge = document.getElementById('preview-status');
                statusBadge.insertAdjacentElement('afterend', countdownEl);
                
                // Start the countdown
                updateCountdown(countdownEl.querySelector('.countdown-timer'));
                
                // Update tooltip with remaining time
                const hoursRemaining = Math.floor((deadline - now) / (1000 * 60 * 60));
                editBtn.title = `You can edit this rejected report for ${hoursRemaining} more hours`;
            } else {
                editBtn.title = "Editing not allowed - time window expired";
            }
        } else {
            editBtn.title = "Editing not allowed for " + latestStatus + " reports";
        }

        // Hide empty preview and show details
        document.querySelector('.empty-preview').style.display = 'none';
        const detailsSection = document.querySelector('.report-details');
        detailsSection.style.display = 'block';
        
        // Update notification count or hide if zero
        const notificationCountEl = document.querySelector('.notification-count');
        if (data.notification_count > 0) {
            notificationCountEl.textContent = data.notification_count;
            notificationCountEl.style.display = 'inline-block';
        } else {
            notificationCountEl.style.display = 'none';
        }
        
        // Update report title or show default if empty
        const title = report.report_type === 'Mangrove Data Report' 
            ? formatSpeciesDisplay(report.species)
            : report.incident_type;
        document.getElementById('preview-title').textContent = 
            title || 'No title available';
        
        // Update status badge
        const statusBadge = document.getElementById('preview-status');
        statusBadge.textContent = formatActionType(latestStatus);
        statusBadge.className = 'report-status-badge ' + latestStatus.toLowerCase().replace('_', '-');
        
        // Update report date or show default
        const dateEl = document.getElementById('preview-date');
        if (report.created_at) {
            dateEl.textContent = new Date(report.created_at).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });
        } else {
            dateEl.textContent = 'No date indicated';
        }
        
        // Update location or show default
        const locationEl = document.getElementById('preview-location');
        if (report.city_municipality) {
            locationEl.textContent = (report.area_no ? report.area_no + ', ' : '') + 
                                report.city_municipality;
        } else {
            locationEl.textContent = 'No location indicated';
        }
        
        // Update priority or show default
        const priorityEl = document.getElementById('preview-priority');
        priorityEl.textContent = report.priority 
            ? report.priority + ' Priority' 
            : 'Normal Priority';
        
        // Update description or show default
        const descriptionEl = document.getElementById('preview-description');
        if (report.report_type === 'Mangrove Data Report') {
            descriptionEl.textContent = report.remarks || 'No additional details provided.';
            
            // Update mangrove-specific fields
            document.getElementById('preview-species').textContent = 
                formatSpeciesDisplay(report.species) || 'Not specified';
            document.getElementById('preview-area').textContent = 
                report.area_m2 ? report.area_m2 + ' sqm' : 'Not specified';
            document.getElementById('preview-mangrove-status').textContent = 
                report.mangrove_status || 'Not specified';
            
            // Clear any previously added forest metrics first
            const additionalInfo = document.querySelector('.additional-info');
            const existingForestMetrics = additionalInfo.querySelectorAll('.info-row.forest-metric');
            existingForestMetrics.forEach(row => row.remove());
            
            // Update forest metrics if available
            const forestMetricsHTML = [];
            if (report.forest_cover_percent !== null && report.forest_cover_percent !== undefined) {
                forestMetricsHTML.push(`
                    <div class="info-row forest-metric" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                        <span class="info-label"><i class="fas fa-tree"></i> Forest Cover:</span>
                        <span class="info-value" style="font-weight: 600;">${parseFloat(report.forest_cover_percent).toFixed(1)}%</span>
                    </div>
                `);
            }
            if (report.canopy_density_percent !== null && report.canopy_density_percent !== undefined) {
                forestMetricsHTML.push(`
                    <div class="info-row forest-metric">
                        <span class="info-label"><i class="fas fa-cloud-sun"></i> Canopy Density:</span>
                        <span class="info-value" style="font-weight: 600;">${parseFloat(report.canopy_density_percent).toFixed(1)}%</span>
                    </div>
                `);
            }
            if (report.tree_count !== null && report.tree_count !== undefined) {
                forestMetricsHTML.push(`
                    <div class="info-row forest-metric">
                        <span class="info-label"><i class="fas fa-calculator"></i> Trees Counted:</span>
                        <span class="info-value" style="font-weight: 600;">${report.tree_count} trees</span>
                    </div>
                `);
            }
            if (report.calculated_density !== null && report.calculated_density !== undefined) {
                forestMetricsHTML.push(`
                    <div class="info-row forest-metric">
                        <span class="info-label"><i class="fas fa-chart-line"></i> Tree Density:</span>
                        <span class="info-value" style="font-weight: 600;">${parseFloat(report.calculated_density).toLocaleString('en-US', {maximumFractionDigits: 0})} trees/ha</span>
                    </div>
                `);
            }
            
            // Insert forest metrics after existing mangrove info rows
            if (forestMetricsHTML.length > 0) {
                const lastInfoRow = additionalInfo.querySelector('.info-row:last-child');
                if (lastInfoRow) {
                    lastInfoRow.insertAdjacentHTML('afterend', forestMetricsHTML.join(''));
                }
            }
            
            // Show mangrove-specific fields
            document.querySelectorAll('.additional-info .info-row').forEach(row => {
                row.style.display = 'flex';
            });
        } else {
            descriptionEl.textContent = report.description || 'No additional details provided.';
            
            // Hide mangrove-specific fields if this is an illegal activity report
            document.querySelectorAll('.additional-info .info-row').forEach(row => {
                row.style.display = 'none';
            });
        }

        // Update images
        updateImagesSection(report);
        
        // Update notifications
        updateNotificationsSection(notifications);
    }
    
    function updateCountdown(element) {
        const deadline = new Date(element.dataset.deadline);
        
        function update() {
            const now = new Date();
            const diff = deadline - now;
            
            if (diff <= 0) {
                clearInterval(interval);
                element.innerHTML = 'Time expired';
                // Optionally disable the edit button here
                const editBtn = document.getElementById('edit-report-btn');
                if (editBtn) editBtn.style.display = 'none';
                return;
            }
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            element.querySelector('.hours').textContent = hours.toString().padStart(2, '0');
            element.querySelector('.minutes').textContent = minutes.toString().padStart(2, '0');
            element.querySelector('.seconds').textContent = seconds.toString().padStart(2, '0');
        }
        
        update(); // Run immediately
        const interval = setInterval(update, 1000); // Update every second
    }

    function updateImagesSection(report) {
        const imagesContainer = document.querySelector('.report-images');
        imagesContainer.innerHTML = ''; // Clear existing images

        // Collect all possible image fields
        const imageFields = [
            'image1', 'image2', 'image3',
            'image_video1', 'image_video2'
        ];

        // Gather available images
        const images = imageFields
            .map(field => report[field])
            .filter(src => src && src.trim() !== '');

        if (images.length > 0) {
            images.forEach((src, idx) => {
                const imgDiv = document.createElement('div');
                imgDiv.className = 'image-preview';
                imgDiv.innerHTML = `<img src="${src}" alt="Report Image ${idx + 1}">`;
                imagesContainer.appendChild(imgDiv);
            });
        } else {
            // Show no images message
            imagesContainer.innerHTML = `
                <div class="no-images">
                    <i class='bx bx-image-alt'></i>
                    <p>No images available</p>
                </div>
            `;
        }
    }
    
    function updateNotificationsSection(notifications) {
        const notificationsContainer = document.querySelector('.notification-list');
        notificationsContainer.innerHTML = '';
        
        if (notifications.length === 0) {
            notificationsContainer.innerHTML = `
                <div class="no-notifications">
                    <i class='bx bx-bell-off'></i>
                    <p>No updates available for this report</p>
                </div>
            `;
            return;
        }
        
        // Simply display all notifications in date order
        notifications.forEach(notif => {
            const notifItem = document.createElement('div');
            notifItem.className = 'notification-item ' + notif.action_type.toLowerCase().replace('_', '-');
            
            const date = new Date(notif.notif_date).toLocaleDateString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric' 
            });
            
            let attachmentsHTML = '';
            // Parse admin_attachments if it's a string, or use it directly if already an array
            let adminAttachments = notif.admin_attachments;
            if (typeof adminAttachments === 'string') {
                try {
                    adminAttachments = JSON.parse(adminAttachments);
                } catch (e) {
                    console.error('Failed to parse admin_attachments:', e);
                    adminAttachments = [];
                }
            }
            
            if (adminAttachments && Array.isArray(adminAttachments) && adminAttachments.length > 0) {
                attachmentsHTML = `
                    <div class="admin-attachments-section">
                        <div class="attachments-header">
                            <i class='bx bx-paperclip'></i>
                            <span>Admin Documentation (${notif.attachment_count} file${notif.attachment_count > 1 ? 's' : ''})</span>
                        </div>
                        <div class="admin-attachments-grid">
                            ${adminAttachments.map((file, index) => {
                                const isVideo = file.match(/\.(mp4|avi|mov|wmv)$/i);
                                const isImage = file.match(/\.(jpg|jpeg|png|gif|webp)$/i);
                                const fileName = file.split('/').pop();
                                
                                if (isVideo) {
                                    return `
                                        <div class="admin-attachment-item" onclick="openVideo('${file}', '${fileName}')">
                                            <video src="${file}"></video>
                                            <div class="attachment-type-badge">VIDEO</div>
                                            <div class="attachment-overlay">
                                                <i class='bx bx-play-circle'></i>
                                            </div>
                                        </div>
                                    `;
                                } else if (isImage) {
                                    return `
                                        <div class="admin-attachment-item" onclick="openLightbox(${JSON.stringify(adminAttachments).replace(/"/g, '&quot;')}, ${index})">
                                            <img src="${file}" alt="Admin attachment">
                                            <div class="attachment-type-badge">IMAGE</div>
                                            <div class="attachment-overlay">
                                                <i class='bx bx-search-alt'></i>
                                            </div>
                                        </div>
                                    `;
                                }
                                return '';
                            }).join('')}
                        </div>
                    </div>
                `;
            }
            
            notifItem.innerHTML = `
                <div class="notification-main-content">
                    <div class="notification-header">
                        <span class="notification-status ${notif.action_type.toLowerCase().replace('_', '-')}">
                            ${formatActionType(notif.action_type)}
                        </span>
                        <span class="notification-date">${date}</span>
                    </div>
                    <p class="notification-message">
                        ${notif.notif_description ? notif.notif_description.replace(/\\(.)/g, "$1") : 'No details provided.'}
                        ${notif.notifier_name ? `<br><small>Notified by: ${notif.notifier_name}</small>` : ''}
                    </p>
                </div>
                ${attachmentsHTML ? '<div class="notification-separator"></div>' : ''}
                ${attachmentsHTML}
            `;
            
            notificationsContainer.appendChild(notifItem);
        });
    }
    
    // Helper function to format species display
    function formatSpeciesDisplay(speciesData) {
        if (!speciesData) return 'Not specified';
        
        const speciesMap = {
            'Rhizophora Apiculata': 'Bakawan Lalake',
            'Rhizophora Mucronata': 'Bakawan Babae',
            'Avicennia Marina': 'Bungalon',
            'Sonneratia Alba': 'Palapat'
        };
        
        // Check if it's a JSON string (new multiple species format)
        try {
            const parsed = JSON.parse(speciesData);
            if (Array.isArray(parsed)) {
                const displayNames = parsed.map(species => speciesMap[species.trim()] || species.trim());
                return displayNames.join(', ');
            }
        } catch (e) {
            // Not JSON, continue to other checks
        }
        
        // Check if it's a comma-separated string (multiple species stored as string)
        if (speciesData.includes(',')) {
            const speciesArray = speciesData.split(',');
            const displayNames = speciesArray.map(species => {
                const trimmed = species.trim();
                return speciesMap[trimmed] || trimmed;
            });
            return displayNames.join(', ');
        }
        
        // Handle single species
        const trimmed = speciesData.trim();
        return speciesMap[trimmed] || trimmed;
    }
    
    // Helper function to format action types
    function formatActionType(action) {
        if (!action) return 'Update';
        return action.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    }

    // Filter functionality for reports
    const mangroveSpeciesFilter = document.getElementById('filter-mangrove-species');
    const incidentTypeFilter = document.getElementById('filter-incident-type');

    if (mangroveSpeciesFilter) {
        mangroveSpeciesFilter.addEventListener('change', function() {
            filterMangroveReports(this.value);
        });
    }

    if (incidentTypeFilter) {
        incidentTypeFilter.addEventListener('change', function() {
            filterIllegalReports(this.value);
        });
    }

    function filterMangroveReports(selectedSpecies) {
        const mangroveReports = document.querySelectorAll('.report-item[data-type="mangrove"]');
        
        mangroveReports.forEach(function(report) {
            const reportSpecies = report.getAttribute('data-species');
            let showReport = false;
            
            if (!selectedSpecies || selectedSpecies === '') {
                showReport = true;
            } else {
                if (selectedSpecies === 'Other') {
                    // Check if the species contains any of the main four species
                    const mainSpecies = ['Rhizophora Apiculata', 'Rhizophora Mucronata', 'Avicennia Marina', 'Sonneratia Alba'];
                    const hasMainSpecies = mainSpecies.some(species => reportSpecies && reportSpecies.includes(species));
                    // Show if it doesn't contain main species or contains additional species beyond the main ones
                    if (!hasMainSpecies || (reportSpecies && reportSpecies.split(',').length > 1 && 
                        !mainSpecies.every(species => reportSpecies.includes(species)))) {
                        showReport = true;
                    }
                } else {
                    // Check if the selected species is in the comma-separated list
                    showReport = reportSpecies && reportSpecies.includes(selectedSpecies);
                }
            }
            
            if (showReport) {
                report.style.display = 'block';
            } else {
                report.style.display = 'none';
            }
        });

        // Update "no reports" message
        updateNoReportsMessage('mangrove');
    }

    function filterIllegalReports(selectedIncidentType) {
        const illegalReports = document.querySelectorAll('.report-item[data-type="illegal"]');
        
        illegalReports.forEach(function(report) {
            const reportIncidentType = report.getAttribute('data-incident-type');
            let showReport = false;
            
            if (!selectedIncidentType || selectedIncidentType === '') {
                showReport = true;
            } else {
                showReport = reportIncidentType === selectedIncidentType;
            }
            
            if (showReport) {
                report.style.display = 'block';
            } else {
                report.style.display = 'none';
            }
        });

        // Update "no reports" message
        updateNoReportsMessage('illegal');
    }

    function updateNoReportsMessage(reportType) {
        const reportColumn = document.querySelector(`.report-item[data-type="${reportType}"]`)?.closest('.report-column');
        if (!reportColumn) return;

        const visibleReports = reportColumn.querySelectorAll(`.report-item[data-type="${reportType}"][style*="display: block"], .report-item[data-type="${reportType}"]:not([style*="display: none"])`);
        const noReportsElement = reportColumn.querySelector('.no-reports');
        
        if (visibleReports.length === 0) {
            if (!noReportsElement) {
                const reportList = reportColumn.querySelector('.report-list');
                const noReportsDiv = document.createElement('div');
                noReportsDiv.className = 'no-reports';
                noReportsDiv.innerHTML = `
                    <i class='bx bx-info-circle'></i>
                    <p>No reports match the current filter</p>
                `;
                reportList.appendChild(noReportsDiv);
            } else {
                noReportsElement.style.display = 'flex';
            }
        } else {
            if (noReportsElement) {
                noReportsElement.style.display = 'none';
            }
        }
    }
});

// Badge Notification Functions
function closeBadgeNotification() {
    const notification = document.getElementById('badgeNotification');
    if (notification) {
        notification.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

function viewBadgeDetails() {
    // Close notification and redirect to profile page
    closeBadgeNotification();
    setTimeout(() => {
        window.location.href = 'profile.php#badges';
    }, 400);
}

// CSS for fade out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
`;
document.head.appendChild(style);

// Lightbox functionality for images
let currentImageIndex = 0;
let currentImageArray = [];

function openLightbox(imagesArray, startIndex) {
    currentImageArray = imagesArray.filter(file => file.match(/\.(jpg|jpeg|png|gif|webp)$/i));
    currentImageIndex = startIndex;
    
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImg = document.getElementById('lightboxImage');
    const counter = document.getElementById('imageCounter');
    const prevBtn = document.getElementById('prevImage');
    const nextBtn = document.getElementById('nextImage');
    const caption = document.getElementById('lightboxCaption');
    
    if (currentImageArray.length === 0) return;
    
    lightbox.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    updateLightboxImage();
    
    // Navigation button states
    prevBtn.disabled = currentImageIndex === 0;
    nextBtn.disabled = currentImageIndex === currentImageArray.length - 1;
}

function updateLightboxImage() {
    const lightboxImg = document.getElementById('lightboxImage');
    const counter = document.getElementById('imageCounter');
    const caption = document.getElementById('lightboxCaption');
    const prevBtn = document.getElementById('prevImage');
    const nextBtn = document.getElementById('nextImage');
    
    lightboxImg.src = currentImageArray[currentImageIndex];
    counter.textContent = `${currentImageIndex + 1} / ${currentImageArray.length}`;
    
    const fileName = currentImageArray[currentImageIndex].split('/').pop();
    caption.textContent = fileName;
    
    prevBtn.disabled = currentImageIndex === 0;
    nextBtn.disabled = currentImageIndex === currentImageArray.length - 1;
}

function closeLightbox() {
    const lightbox = document.getElementById('imageLightbox');
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
}

function nextImage() {
    if (currentImageIndex < currentImageArray.length - 1) {
        currentImageIndex++;
        updateLightboxImage();
    }
}

function prevImage() {
    if (currentImageIndex > 0) {
        currentImageIndex--;
        updateLightboxImage();
    }
}

// Video player functionality
function openVideo(videoSrc, fileName) {
    const videoModal = document.getElementById('videoPlayerModal');
    const videoPlayer = document.getElementById('videoPlayer');
    const videoCaption = document.getElementById('videoCaption');
    const videoSource = videoPlayer.querySelector('source');
    
    videoSource.src = videoSrc;
    videoPlayer.load();
    videoCaption.textContent = fileName;
    
    videoModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeVideo() {
    const videoModal = document.getElementById('videoPlayerModal');
    const videoPlayer = document.getElementById('videoPlayer');
    
    videoPlayer.pause();
    videoPlayer.currentTime = 0;
    videoModal.classList.remove('active');
    document.body.style.overflow = '';
}

// Event listeners for lightbox and video player
document.addEventListener('DOMContentLoaded', function() {
    // Lightbox close button
    document.querySelector('.lightbox-close').addEventListener('click', closeLightbox);
    
    // Video modal close button
    document.querySelector('.video-close').addEventListener('click', closeVideo);
    
    // Navigation buttons
    document.getElementById('prevImage').addEventListener('click', prevImage);
    document.getElementById('nextImage').addEventListener('click', nextImage);
    
    // Close on background click
    document.getElementById('imageLightbox').addEventListener('click', function(e) {
        if (e.target === this) {
            closeLightbox();
        }
    });
    
    document.getElementById('videoPlayerModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeVideo();
        }
    });
    
    // Keyboard navigation for lightbox
    document.addEventListener('keydown', function(e) {
        const lightbox = document.getElementById('imageLightbox');
        const videoModal = document.getElementById('videoPlayerModal');
        
        if (lightbox.classList.contains('active')) {
            if (e.key === 'Escape') {
                closeLightbox();
            } else if (e.key === 'ArrowLeft') {
                prevImage();
            } else if (e.key === 'ArrowRight') {
                nextImage();
            }
        }
        
        if (videoModal.classList.contains('active') && e.key === 'Escape') {
            closeVideo();
        }
    });
});
</script>
</body>
</html>