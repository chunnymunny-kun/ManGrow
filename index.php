<!--
<?php
    session_start();
    include 'database.php';
    include 'badge_system_db.php';

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
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
                $user = $result->fetch_assoc();
                $userBadges = $user['badges'];
                
                if(!empty($userBadges)) {
                    // Parse user badges (assuming comma-separated format)
                    $badgeList = array_filter(array_map('trim', explode(',', $userBadges)));
                    
                    if(!empty($badgeList)) {
                        // Check which badges haven't been notified yet
                        $badgePlaceholders = str_repeat('?,', count($badgeList) - 1) . '?';
                        $checkNotificationQuery = "SELECT badge_name FROM badge_notifications WHERE user_id = ? AND badge_name IN ($badgePlaceholders)";
                        $stmt2 = $connection->prepare($checkNotificationQuery);
                        
                        $types = 'i' . str_repeat('s', count($badgeList));
                        $params = array_merge([$userId], $badgeList);
                        $stmt2->bind_param($types, ...$params);
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
                            // Show notification for ALL unnotified badges (multiple badge support)
                            $showBadgeNotification = true;
                            $badgesToShow = [];
                            
                            foreach($unnotifiedBadges as $badgeToNotify) {
                                // Fetch badge details from database
                                $badgeQuery = "SELECT * FROM badgestbl WHERE badge_name = ? AND is_active = 1";
                                $stmt3 = $connection->prepare($badgeQuery);
                                $stmt3->bind_param("s", $badgeToNotify);
                                $stmt3->execute();
                                $badgeResult = $stmt3->get_result();
                                
                                if($badgeResult->num_rows > 0) {
                                    $badgeData = $badgeResult->fetch_assoc();
                                    
                                    // Check if image exists and is accessible
                                    $hasValidImage = false;
                                    if (!empty($badgeData['image_path']) && file_exists($badgeData['image_path'])) {
                                        $hasValidImage = true;
                                    }
                                    
                                    $badgesToShow[] = [
                                        'badge_awarded' => true,
                                        'badge_name' => $badgeToNotify,
                                        'badge_description' => $badgeData['description'],
                                        'badge_icon' => $badgeData['icon_class'],
                                        'badge_image' => $hasValidImage ? $badgeData['image_path'] : null,
                                        'badge_color' => $badgeData['color'],
                                        'badge_category' => $badgeData['category']
                                    ];
                                }
                                $stmt3->close();
                                
                                // Mark this badge as notified
                                $insertNotificationQuery = "INSERT IGNORE INTO badge_notifications (user_id, badge_name) VALUES (?, ?)";
                                $stmt4 = $connection->prepare($insertNotificationQuery);
                                $stmt4->bind_param("is", $userId, $badgeToNotify);
                                $stmt4->execute();
                                $stmt4->close();
                            }
                            
                            // For backward compatibility, set the first badge as primary
                            $badgeToShow = !empty($badgesToShow) ? $badgesToShow[0] : null;
                        }
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
?>

-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Welcome to ManGrow</title>
    <link rel="stylesheet" href="style.css">
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
            animation: spin 3s linear infinite;
        }

        .badge-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            margin:0 auto;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .badge-icon i {
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .badge-celebration .badge-image {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.3);
            animation: spin 3s linear infinite;
        }

        .badge-celebration .badge-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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

        .badge.starting-point {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2980b9);
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

        .badge.event-organizer {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e67e22, #d35400);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            animation: spin 3s linear infinite;
        }

        /* Badge Navigation Controls */
        .badge-navigation {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10001;
        }

        .badge-nav-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .badge-nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .badge-nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .badge-counter {
            position: absolute;
            top: 10px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Multiple Badges Indicator */
        .multiple-badges-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .badge-count-badge {
            position: relative;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #333;
            box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            animation: pulse-glow 2s ease-in-out infinite;
        }

        .badge-count-badge .count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            font-size: 14px;
            font-weight: bold;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.6);
            animation: bounce-count 1.5s ease-in-out infinite;
        }

        .indicator-text {
            text-align: left;
            color: white;
        }

        .indicator-text strong {
            font-size: 18px;
            display: block;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .indicator-text p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        @keyframes pulse-glow {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 8px 20px rgba(255, 215, 0, 0.4);
            }
            50% { 
                transform: scale(1.05);
                box-shadow: 0 12px 30px rgba(255, 215, 0, 0.7);
            }
        }

        @keyframes bounce-count {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .badge.mangrove-guardian {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #27ae60, #229954);
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

        .badge.watchful-eye {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2980b9);
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

        .badge.vigilant-protector {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
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

        .badge.conservation-champion {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f39c12, #e67e22);
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

        .badge.ecosystem-sentinel {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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

        .badge.mangrove-legend {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #8e44ad, #71368a);
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
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
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

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .badge-notification-modal {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .notification-buttons {
                flex-direction: column;
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
                    <a href="index.php" class="active">Home</a>
                </li>
                <li>
                    <i class="bx bx-bulb"></i>
                    <a href="reportspage.php">Reports</a>
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
        if (isset($_SESSION['user_id'])) {
            require_once 'eco_points_notification.php';
            $ecoPointsNotification = getUnnotifiedResolvedReports($_SESSION['user_id']);
            if ($ecoPointsNotification) {
                echo generateEcoPointsNotificationCSS();
                echo generateEcoPointsNotificationHTML($ecoPointsNotification);
            }
        }
        ?>

        <!-- Badge Award Notification -->
        <?php if($showBadgeNotification && $badgeToShow): ?>
        <div class="badge-notification-overlay" id="badgeNotification">
            <!-- Badge Navigation (for multiple badges) -->
            <?php if(isset($badgesToShow) && count($badgesToShow) > 1): ?>
            <div class="badge-counter">
                Badge <span id="currentBadgeIndex">1</span> of <span id="totalBadges"><?= count($badgesToShow) ?></span>
            </div>
            <div class="badge-navigation">
                <button class="badge-nav-btn" id="prevBadgeBtn" onclick="showPreviousBadge()" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="badge-nav-btn" id="nextBadgeBtn" onclick="showNextBadge()">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <?php endif; ?>
            
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
            
            <div class="badge-notification-modal" id="badgeModal">
                <!-- Badge count indicator for multiple badges -->
                <?php if(isset($badgesToShow) && count($badgesToShow) > 1): ?>
                <div class="multiple-badges-indicator">
                    <div class="badge-count-badge">
                        <i class="fas fa-trophy"></i>
                        <span class="count"><?= count($badgesToShow) ?></span>
                    </div>
                    <div class="indicator-text">
                        <strong><?= count($badgesToShow) ?> New Badges Earned!</strong>
                        <p>You've achieved multiple milestones</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="badge-celebration">
                    <!-- Dynamic badge display (image or icon with spinning animation) -->
                    <div id="badgeDisplayContainer">
                        <?php if(!empty($badgeToShow['badge_image'])): ?>
                            <div class="badge-image">
                                <img src="<?= htmlspecialchars($badgeToShow['badge_image']) ?>" alt="<?= htmlspecialchars($badgeToShow['badge_name']) ?>" />
                            </div>
                        <?php else: ?>
                            <div class="badge-icon">
                                <i class="<?= getBadgeIcon($badgeToShow['badge_name']) ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <h2 id="congratsMessage">🎉 Congratulations! 🎉</h2>
                    <p id="badgeMessage">You've earned a new badge!</p>
                    
                    <div class="new-badge-display">
                        <div class="badge-card earned">
                            <?php 
                            $badgeName = $badgeToShow['badge_name'];
                            $badgeClass = strtolower(str_replace(' ', '-', $badgeName));
                            $badgeIcon = getBadgeIcon($badgeName);
                            $badgeDescription = getBadgeDescription($badgeName);
                            ?>
                            <div class="badge <?= $badgeClass ?>" id="modalBadgeIcon">
                                <?php if(!empty($badgeToShow['badge_image'])): ?>
                                    <img src="<?= htmlspecialchars($badgeToShow['badge_image']) ?>" alt="<?= htmlspecialchars($badgeToShow['badge_name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" />
                                <?php else: ?>
                                    <i class="<?= $badgeIcon ?>"></i>
                                <?php endif; ?>
                            </div>
                            <p id="badgeNameDisplay"><?= htmlspecialchars($badgeName) ?></p>
                        </div>
                    </div>
                    
                    <p class="badge-description" id="badgeDescriptionDisplay"><?= htmlspecialchars($badgeDescription) ?></p>
                    
                    <div class="notification-buttons">
                        <button onclick="viewBadgeDetails()" class="btn-view-badge">View All Badges</button>
                        <button onclick="closeBadgeNotification()" class="btn-close-notification">Awesome!</button>
                    </div>
                </div>
            </div>
            
            <!-- Hidden badge data for JavaScript -->
            <script type="application/json" id="badgeData">
                <?= isset($badgesToShow) ? json_encode($badgesToShow) : json_encode([$badgeToShow]) ?>
            </script>
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
        <div class= "home-container">
        <section class="s1">
            <div class ="background-img"><img src="images/mangrove.webp" alt ="Mangrove">
            <h1>ManGrow: Mangrove Conservation and Eco-Tracking System with 2D Mapping</h1>
            <button type="button" class="abt-project" onclick="window.location.href='about.php';"><span class="abt-project-span"></span>About Project</button>
            </div>
        </section>
        <section class="s2">
            <div class="one">
                <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. 
                    We use technology such as GIS-powered mapping with eco-tracking features and promote community engagement to establish sustainable mangrove conservation. 
                    The technology enables mangrove ecosystem protection and strengthens local populations so they actively engage in conservation work.</p>
                </div>
        </section>
        <section class="s3">
            <div class="two">
            <div class="community-hub">
                <div class="hub-flex">
                    <!-- About -->
                    <div class="flex-item" data-modal="modal-one">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Community News</h3>
                            <p>Latest updates and announcements</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="flex-item" data-modal="modal-two">
                        <div class="item-image" style="background-image: url(images/event\ banner.jpg);"></div>
                        <div class="item-content">
                            <h3>Events Timeline</h3>
                            <p>Join our next gathering</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="flex-item" data-modal="modal-three">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Success Stories</h3>
                            <p>Inspiring member journeys</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="flex-item" data-modal="modal-four">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Helpful Resources</h3>
                            <p>Tools and guides</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for second section -->
            <div class="modal" id="modal-template">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div class="modal-body">
                        <!-- Content will be added here via JavaScript -->
                    </div>
                </div>
            </div>
        </section>
        <section class="s4">
            <div class="three">
                <div class="three-header"><h1>Expect the latest from our community hub</h1></div>
                <div class="programs-box" id="programs-container">
                    <!-- Content will be added here dynamically -->
                </div>
                <div class="view-more">
                    <button type="button" class="view-more-btn" onclick="window.location.href='initiatives.php';">View More <span><i class="fas fa-angle-double-right"></i></span></button>
                </div>
            </div>
        </section>
        <section class="s5">
            <div class="four">
                <div class="three-header">
                    <h1>Your awareness can save the environment!</h1>
                </div>
                <p>We at ManGrow encourage you to report unusual activities that might negatively affect the development of mangroves in your area. Our team actively monitors reports to ensure they reach the authorities promptly.</p>
                
                <div class="report-cta">
                    <div class="cta-content">
                        <div class="cta-icon">
                            <i class="fas fa-binoculars"></i>
                        </div>
                        <div class="cta-text">
                            <h3>Spot something suspicious?</h3>
                            <p>Help protect mangrove ecosystems by reporting illegal activities like logging, dumping, or unauthorized construction.</p>
                        </div>
                    </div>
                    <a href="reportform_iacts.php" class="report-btn" onclick="storeRedirectUrl(this.href); return false;">
                        <i class="fas fa-flag"></i> Report Illegal Activity
                    </a>
                </div>
                <?php if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == 'Administrator' || $_SESSION['accessrole'] == 'Barangay Official' || $_SESSION['accessrole'] == 'LGU')){?>
                <div class="report-cta">
                    <div class="cta-content">
                        <div class="cta-icon">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <div class="cta-text">
                            <h3>Need to update mangrove data?</h3>
                            <p>Submit a status report or feedback for tree planting activities. Your input helps us track progress and improve future initiatives.</p>
                        </div>
                    </div>
                    <a href="reportform_mdata.php" class="report-btn" onclick="storeRedirectUrl(this.href); return false;">
                        <i class="fas fa-leaf"></i> Submit Mangrove Data Report
                    </a>
                </div>
                <?php } ?>
                <div class="safety-note">
                    <i class="fas fa-shield-alt"></i>
                    <span>All reports are confidential. Your identity will be protected.</span>
                </div>
            </div>
        </section>
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
    <!-- section four script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sample data - in a real app, you would fetch this from a server
        const hubItems = [
            {
                id: 1,
                thumbnail: 'images/mangrove.webp',
                title: 'Mangrove Conservation Workshop',
                tags: ['Workshop', 'Education'],
                description: 'Learn about mangrove ecosystems and conservation techniques in this hands-on workshop.',
                link: 'initiatives.php?id=1'
            },
            {
                id: 2,
                thumbnail: 'images/mangrove-conserver.jpg',
                title: 'Coastal Cleanup Drive',
                tags: ['Volunteer', 'Environment'],
                description: 'Join our community effort to clean up the coastal areas and protect marine life.',
                link: 'initiatives.php?id=2'
            },
            {
                id: 3,
                thumbnail: 'images/mangrove-conserver.jpg',
                title: 'Mangrove Planting Day',
                tags: ['Volunteer', 'Conservation'],
                description: 'Help us plant new mangroves and contribute to coastal ecosystem restoration.',
                link: 'initiatives.php?id=3'
            },
            {
                id: 4,
                thumbnail: 'images/mangrove-young.jpg',
                title: 'Marine Biology Talk',
                tags: ['Education', 'Science'],
                description: 'Expert discussion on the latest marine biology research and conservation methods.',
                link: 'initiatives.php?id=4'
            },
            {
                id: 5,
                thumbnail: 'images/event\ banner.jpg',
                title: 'Community Meeting',
                tags: ['News', 'Update'],
                description: 'Monthly community meeting to discuss ongoing projects and future plans.',
                link: 'initiatives.php?id=5'
            },
            {
                id: 6,
                thumbnail: 'images/mangrove-login.jpg',
                title: 'Eco-Tourism Initiative',
                tags: ['Tourism', 'Conservation'],
                description: 'Learn about our sustainable eco-tourism programs that support conservation efforts.',
                link: 'initiatives.php?id=6'
            }
        ];

        // Function to create a program item
        function createProgramItem(item) {
            const programDiv = document.createElement('div');
            programDiv.className = 'programs-details';
            programDiv.innerHTML = `
                <div class="programs-img">
                    <img src="${item.thumbnail}" alt="${item.title}">
                </div>
                <div class="programs-desc">
                    <h4>${item.title}</h4>
                    <div class="programs-tags">
                        ${item.tags.map(tag => `<h5>${tag}</h5>`).join('')}
                    </div>
                    <p>${item.description}</p>
                    <a href="${item.link}" class="learn-more-link">Learn More <i class="fas fa-angle-double-right"></i></a>
                </div>
            `;
            return programDiv;
        }

        // Function to populate the programs container
        function populatePrograms() {
            const container = document.getElementById('programs-container');
            
            // Clear existing content (if any)
            container.innerHTML = '';
            
            // Add each program item
            hubItems.forEach(item => {
                container.appendChild(createProgramItem(item));
            });
        }

        // Initialize the programs
        populatePrograms();
    });

    function storeRedirectUrl(url) {
        // Make an AJAX request to store the URL in session
        fetch('store_redirect.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'redirect_url=' + encodeURIComponent(url)
        })
        .then(response => {
            window.location.href = url;
        })
        .catch(error => console.error('Error:', error));
    }

    // Badge Notification Functions with Multiple Badge Support
    let currentBadgeIndex = 0;
    let allBadges = [];
    
    // Initialize badge system on page load
    document.addEventListener('DOMContentLoaded', function() {
        const badgeNotification = document.getElementById('badgeNotification');
        if (badgeNotification) {
            // Load badge data from hidden script tag
            const badgeDataElement = document.getElementById('badgeData');
            if (badgeDataElement) {
                try {
                    allBadges = JSON.parse(badgeDataElement.textContent);
                    console.log('Loaded badges:', allBadges);
                    
                    // Initialize first badge display
                    updateBadgeDisplay();
                    
                    // Update navigation visibility
                    updateNavigationButtons();
                } catch (e) {
                    console.error('Error parsing badge data:', e);
                }
            }
        }
    });

    // Update badge display with current badge data
    function updateBadgeDisplay() {
        if (allBadges.length === 0) return;
        
        const currentBadge = allBadges[currentBadgeIndex];
        
        // Update multiple badges indicator if exists
        const indicator = document.querySelector('.multiple-badges-indicator');
        if (indicator && allBadges.length > 1) {
            const countElement = indicator.querySelector('.count');
            const textElement = indicator.querySelector('.indicator-text strong');
            if (countElement) countElement.textContent = allBadges.length;
            if (textElement) textElement.textContent = `${allBadges.length} New Badges Earned!`;
        }
        
        // Update badge icon/image in celebration area
        const displayContainer = document.getElementById('badgeDisplayContainer');
        if (displayContainer) {
            if (currentBadge.badge_image && currentBadge.badge_image.trim() !== '') {
                displayContainer.innerHTML = `
                    <div class="badge-image">
                        <img src="${escapeHtml(currentBadge.badge_image)}" 
                             alt="${escapeHtml(currentBadge.badge_name)}" 
                             onerror="this.parentElement.innerHTML='<div class=&quot;badge-icon&quot;><i class=&quot;${currentBadge.badge_icon || 'fas fa-star'}&quot;></i></div>'" />
                    </div>`;
            } else {
                displayContainer.innerHTML = `
                    <div class="badge-icon">
                        <i class="${currentBadge.badge_icon || 'fas fa-star'}"></i>
                    </div>`;
            }
        }
        
        // Update modal badge icon/image
        const modalBadgeIcon = document.getElementById('modalBadgeIcon');
        if (modalBadgeIcon) {
            const badgeClass = currentBadge.badge_name.toLowerCase().replace(/\s+/g, '-');
            modalBadgeIcon.className = `badge ${badgeClass}`;
            
            if (currentBadge.badge_image && currentBadge.badge_image.trim() !== '') {
                modalBadgeIcon.innerHTML = `<img src="${escapeHtml(currentBadge.badge_image)}" 
                                                 alt="${escapeHtml(currentBadge.badge_name)}" 
                                                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                                                 onerror="this.outerHTML='<i class=&quot;${currentBadge.badge_icon || 'fas fa-star'}&quot;></i>'" />`;
            } else {
                modalBadgeIcon.innerHTML = `<i class="${currentBadge.badge_icon || 'fas fa-star'}"></i>`;
            }
        }
        
        // Update text content
        const badgeNameDisplay = document.getElementById('badgeNameDisplay');
        if (badgeNameDisplay) {
            badgeNameDisplay.textContent = currentBadge.badge_name;
        }
        
        const badgeDescriptionDisplay = document.getElementById('badgeDescriptionDisplay');
        if (badgeDescriptionDisplay) {
            badgeDescriptionDisplay.textContent = currentBadge.badge_description;
        }
        
        // Update counter
        const currentIndexDisplay = document.getElementById('currentBadgeIndex');
        if (currentIndexDisplay) {
            currentIndexDisplay.textContent = currentBadgeIndex + 1;
        }
        
        // Update congratulations message for multiple badges
        const congratsMessage = document.getElementById('congratsMessage');
        const badgeMessage = document.getElementById('badgeMessage');
        if (allBadges.length > 1) {
            if (congratsMessage) congratsMessage.textContent = '🎉 Amazing Achievement! 🎉';
            if (badgeMessage) badgeMessage.textContent = `Viewing badge ${currentBadgeIndex + 1} of ${allBadges.length}`;
        } else {
            if (congratsMessage) congratsMessage.textContent = '🎉 Congratulations! 🎉';
            if (badgeMessage) badgeMessage.textContent = "You've earned a new badge!";
        }
    }

    // Navigate to next badge
    function showNextBadge() {
        if (currentBadgeIndex < allBadges.length - 1) {
            currentBadgeIndex++;
            updateBadgeDisplay();
            updateNavigationButtons();
        }
    }

    // Navigate to previous badge
    function showPreviousBadge() {
        if (currentBadgeIndex > 0) {
            currentBadgeIndex--;
            updateBadgeDisplay();
            updateNavigationButtons();
        }
    }

    // Update navigation button states
    function updateNavigationButtons() {
        const prevBtn = document.getElementById('prevBadgeBtn');
        const nextBtn = document.getElementById('nextBadgeBtn');
        
        if (prevBtn) {
            prevBtn.disabled = currentBadgeIndex === 0;
        }
        
        if (nextBtn) {
            nextBtn.disabled = currentBadgeIndex === allBadges.length - 1;
        }
    }

    function closeBadgeNotification() {
        const notification = document.getElementById('badgeNotification');
        if (notification) {
            notification.style.animation = 'fadeOut 0.3s ease-out';
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

    // Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
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
</script>
<!-- header navbar toggle script -->
<script>
    //set the script here

</script>
</body>
</html>