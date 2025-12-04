<?php
session_start();
include 'database.php';
include 'badge_system_db.php'; // Include the new database-driven badge system
include 'eco_points_integration.php'; // Include eco points system
include 'eco_points_notification.php'; // Include eco points notification system

// Initialize badge system with database connection
BadgeSystem::init($connection);

// Initialize eco points system
initializeEcoPointsSystem();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Please login to view your profile'
    ];
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data from accountstbl
$user_query = "SELECT * FROM accountstbl WHERE account_id = ?";
$stmt = $connection->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'User profile not found'
    ];
    header("Location: login.php");
    exit();
}

$user_data = $user_result->fetch_assoc();

// Process daily login bonus (only if not already received today)
$showLoginBonus = false;
$loginBonusMessage = '';

// First check if there's a pending notification from session (from login process)
if (isset($_SESSION['daily_login_bonus'])) {
    $showLoginBonus = true;
    $loginBonusData = $_SESSION['daily_login_bonus'];
    $loginBonusMessage = "Daily login bonus: +{$loginBonusData['points']} eco points!";
    // Clear session after showing notification
    unset($_SESSION['daily_login_bonus']);
} else {
    // Check if we already showed the notification this session to prevent refresh spam
    $today = date('Y-m-d');
    
    // Clear session flag if it's a new day
    if (isset($_SESSION['daily_login_shown_today']) && $_SESSION['daily_login_shown_today'] !== $today) {
        unset($_SESSION['daily_login_shown_today']);
    }
    
    if (!isset($_SESSION['daily_login_shown_today'])) {
        // If no session notification, check if user should get daily bonus
        // But only process if they haven't received it today
        $checkQuery = "SELECT transaction_id FROM eco_points_transactions 
                       WHERE user_id = ? AND activity_type = 'daily_login' AND DATE(created_at) = ?";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Only process daily login if they haven't received it today
        if ($result->num_rows === 0) {
            $dailyLoginResult = processDailyLoginBonus($user_id);
            if ($dailyLoginResult['success']) {
                $showLoginBonus = true;
                $loginBonusMessage = "Daily login bonus: +{$dailyLoginResult['points_awarded']} eco points!";
                
                // Set session flag to prevent showing again today
                $_SESSION['daily_login_shown_today'] = $today;
                
                // REFRESH USER DATA to show updated points immediately
                $user_query = "SELECT * FROM accountstbl WHERE account_id = ?";
                $stmt = $connection->prepare($user_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user_result = $stmt->get_result();
                $user_data = $user_result->fetch_assoc();
            }
        }
    }
}

// Get enhanced user point information
$userPointsSummary = getUserPointsSummary($user_id);

// Get attended events from attendeestbl
$events_query = "SELECT e.subject, e.start_date, e.venue, a.id 
                FROM attendeestbl a 
                JOIN eventstbl e ON a.event_id = e.event_id 
                WHERE a.account_id = ? 
                ORDER BY e.start_date DESC";
$events_stmt = $connection->prepare($events_query);
$events_stmt->bind_param("i", $user_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Get user's badges using the database-driven badge system
$user_badges = BadgeSystem::parseUserBadges($user_data['badges']);

// Get badge statistics with percentages and rarity
$badge_statistics = BadgeSystem::calculateBadgeStatistics($connection);

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile - ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type="text/javascript" src="app.js" defer></script>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="gamification_notifications.css">
    
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

        /* Eco Points Notification Styles */
        .eco-points-notification {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10001;
            animation: fadeIn 0.3s ease-in;
        }

        .eco-points-modal {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-radius: 20px;
            padding: 2rem;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: white;
            animation: bounceIn 0.6s ease-out;
            position: relative;
        }

        .eco-points-header {
            text-align: center;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .eco-points-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            animation: spin 2s linear infinite;
            color: #ffd700;
            filter: drop-shadow(0 0 10px rgba(255, 215, 0, 0.5));
        }

        .eco-points-header h3 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .close-notification {
            position: absolute;
            top: -10px;
            right: -10px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .close-notification:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .eco-points-content {
            text-align: center;
        }

        .eco-points-content p {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .eco-points-summary {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
        }

        .point-item {
            flex: 1;
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .point-item .label {
            display: block;
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.3rem;
        }

        .point-item .value {
            display: block;
            font-size: 1.3rem;
            font-weight: bold;
            color: #ffd700;
        }

        /* Animation for points counter */
        @keyframes pointsGlow {
            0%, 100% { 
                text-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
            }
            50% { 
                text-shadow: 0 0 20px rgba(255, 215, 0, 0.8), 0 0 30px rgba(255, 215, 0, 0.6);
            }
        }

        .point-item .value {
            animation: pointsGlow 2s ease-in-out infinite;
        }

        /* Responsive design for eco points notification */
        @media (max-width: 768px) {
            .eco-points-modal {
                padding: 1.5rem;
            }
            
            .eco-points-summary {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .eco-points-header h3 {
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
                    <a href="initiatives.php">Initiatives</a>
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
            <li class="active">
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
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

    <!-- Daily Login Bonus Notification -->
    <?php if($showLoginBonus): ?>
    <div class="eco-points-notification" id="ecoPointsNotification">
        <div class="eco-points-modal">
            <div class="eco-points-header">
                <i class="fas fa-coins eco-points-icon"></i>
                <h3>Daily Login Bonus!</h3>
                <button class="close-notification" onclick="closeEcoPointsNotification()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="eco-points-content">
                <p><?php echo $loginBonusMessage; ?></p>
                <div class="eco-points-summary">
                    <div class="point-item">
                        <span class="label">Current Points:</span>
                        <span class="value"><?php echo number_format($userPointsSummary['current_points']); ?></span>
                    </div>
                    <div class="point-item">
                        <span class="label">Weekly Progress:</span>
                        <span class="value"><?php echo number_format($userPointsSummary['weekly_earned']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                
                <p class="badge-description" id="badgeDescriptionDisplay"><?= htmlspecialchars($badgeToShow['badge_description']) ?></p>
                
                <div class="notification-buttons">
                    <button onclick="viewBadgeDetails()" class="btn-view-badge">View All Badges</button>
                    <button onclick="closeBadgeNotification()" class="btn-close-notification">Awesome!</button>
                </div>
            </div>
            
            <!-- Hidden badge data for JavaScript -->
            <script type="application/json" id="badgeData">
                <?= isset($badgesToShow) ? json_encode($badgesToShow) : json_encode([$badgeToShow]) ?>
            </script>
        </div>
    </div>
    <?php endif; ?>

    <!-- Eco Points Notification for Resolved Reports -->
    <?php
    $ecoPointsNotification = getUnnotifiedResolvedReports($_SESSION['user_id']);
    if ($ecoPointsNotification) {
        echo generateEcoPointsNotificationCSS();
        echo generateEcoPointsNotificationHTML($ecoPointsNotification);
    }
    ?>

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

    <div class="profile-container">
        <div class="profile-header">
            <?php if (!empty($user_data['profile_thumbnail'])): ?>
                <img src="<?php echo htmlspecialchars($user_data['profile_thumbnail']); ?>" 
                     alt="<?php echo htmlspecialchars($user_data['profile']); ?>" 
                     class="profile-picture">
            <?php else: ?>
                <div class="default-profile-picture">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user_data['fullname']); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user_data['barangay'] . ', ' . $user_data['city_municipality']); ?></p>
                <?php if (!empty($user_data['bio'])): ?>
                    <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($user_data['bio']); ?></p>
                <?php endif; ?>
            </div>
            
            <div class="profile-stats">
                <div class="stat current-points">
                    <h2><?php echo number_format($userPointsSummary['current_points']); ?></h2>
                    <p>Current Eco Points</p>
                </div>
                <div class="stat total-points">
                    <h2><?php echo number_format($userPointsSummary['total_points']); ?></h2>
                    <p>Total Eco Points</p>
                </div>
                <div class="stat weekly-points">
                    <h2><?php echo number_format($userPointsSummary['weekly_earned']); ?></h2>
                    <p>Earned this week</p>
                </div>
                <div class="stat rank">
                    <h2>#<?php echo $userPointsSummary['user_rank']; ?></h2>
                    <p>Weekly Rank</p>
                </div>
            </div>
            
            <!-- Gamification Tutorial Button -->
            <div class="tutorial-button">
                <button onclick="showGamificationTutorial()" class="tutorial-btn" title="Learn about Eco Points and Badges">
                    <i class="fas fa-question-circle"></i>
                </button>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-section">
                <h2><i class="fas fa-medal"></i> My Badges (<?php echo $user_data['badge_count']; ?>)</h2>
                <?php if (!empty($user_badges)): ?>
                    <div class="badges-grid">
                        <?php foreach ($user_badges as $badge): ?>
                            <?php echo BadgeSystem::generateBadgeHTML($badge, true, true); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-badges">
                        <i class="fas fa-trophy"></i>
                        <p>No badges earned yet. Participate in events to earn your first badge!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- All Badges Section -->
            <div class="profile-section all-badges-section">
                <div class="all-badges-header">
                    <h3><i class="fas fa-star"></i> All Obtainable Badges</h3>
                    <button class="toggle-btn" onclick="toggleAllBadges()" id="toggleBadgesBtn">
                        Hide <i class="fas fa-chevron-up"></i>
                    </button>
                </div>
                <?php 
                    $allBadgesWithStatus = BadgeSystem::getAllBadgesWithStatus($user_badges);
                    if (!empty($allBadgesWithStatus)): 
                ?>
                    <div class="all-badges-content" id="allBadgesContent">
                        <!-- Category Filter -->
                        <div class="badge-category-filter">
                            <button class="filter-btn active" onclick="filterBadges('all')">All</button>
                            <?php 
                                $categories = BadgeSystem::getCategories();
                                foreach ($categories as $category): 
                            ?>
                                <button class="filter-btn" onclick="filterBadges('<?php echo htmlspecialchars($category); ?>')"><?php echo htmlspecialchars($category); ?></button>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="badges-grid" id="badgesContainer">
                            <?php foreach ($allBadgesWithStatus as $badgeStatus): ?>
                                <div class="badge-item" data-category="<?php echo htmlspecialchars($badgeStatus['badge']['category']); ?>">
                                    <?php echo BadgeSystem::generateBadgeHTML($badgeStatus['badge'], true, $badgeStatus['obtained']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <h2><i class="fas fa-calendar-check"></i> Past Events</h2>
                <?php if ($events_result->num_rows > 0): ?>
                    <ul class="events-list">
                        <?php while ($event = $events_result->fetch_assoc()): ?>
                            <li>
                                <div class="event-info">
                                    <strong><?php echo htmlspecialchars($event['subject']); ?></strong>
                                    <span class="event-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('F j, Y', strtotime($event['start_date'])); ?>
                                    </span>
                                    <span class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($event['venue']); ?>
                                    </span>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <div class="no-events">
                        <i class="fas fa-calendar-plus"></i>
                        <p>No events attended yet. Join events to start making a difference!</p>
                        <a href="events.php" class="cta-button">Browse Events</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    </main>
    <footer>
        <div id="right-footer">
            <h3>Follow us on</h3>
            <div id="social-media-footer">
                <ul>
                    <li><a href="#"><i class="fab fa-facebook"></i></a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                    <li><a href="#"><i class="fab fa-twitter"></i></a></li>
                </ul>
            </div>
            <p>This website is developed by ManGrow. All Rights Reserved.</p>
        </div>
    </footer>
    
    <!-- Badge Modal -->
    <?php echo generateBadgeModal(); ?>
    
    <!-- Gamification Tutorial Modal -->
    <div id="gamificationTutorial" class="tutorial-modal">
        <div class="tutorial-content">
            <div class="tutorial-header">
                <h2><i class="fas fa-gamepad"></i> Welcome to ManGrow Gamification!</h2>
                <button class="close-tutorial" onclick="closeGamificationTutorial()">&times;</button>
            </div>
            <div class="tutorial-body">
                <div class="tutorial-section">
                    <div class="tutorial-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="tutorial-text">
                        <h3>Eco Points System</h3>
                        <p>Earn Eco Points by participating in environmental activities:</p>
                        <ul>
                            <li><strong>Attend Events:</strong> Participate in mangrove planting and conservation activities</li>
                            <li><strong>Complete Tasks:</strong> Upload reports, join initiatives, and contribute to the community</li>
                            <li><strong>Redeem Rewards:</strong> Use your points in the Eco Shop for sustainable products and tools</li>
                        </ul>
                    </div>
                </div>
                
                <div class="tutorial-section">
                    <div class="tutorial-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <div class="tutorial-text">
                        <h3>Badge System</h3>
                        <p>Unlock badges by achieving specific milestones:</p>
                        <ul>
                            <li><strong>Starting Point:</strong> Complete your account registration</li>
                            <li><strong>Tree Planter:</strong> Participate in your first planting event</li>
                            <li><strong>Eco Points Collector:</strong> Earn significant eco points through activities</li>
                            <li><strong>And many more!</strong> Keep exploring to unlock all badges</li>
                        </ul>
                    </div>
                </div>
                
                <div class="tutorial-section">
                    <div class="tutorial-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="tutorial-text">
                        <h3>Progress & Achievements</h3>
                        <p>Track your environmental impact:</p>
                        <ul>
                            <li><strong>Leaderboards:</strong> See how you rank among other conservation heroes</li>
                            <li><strong>Profile Progress:</strong> View your earned badges and accumulated points</li>
                            <li><strong>Community Impact:</strong> Your efforts contribute to real environmental change</li>
                        </ul>
                    </div>
                </div>
                
                <div class="tutorial-footer">
                    <div class="encouragement">
                        <i class="fas fa-leaf"></i>
                        <p><strong>Start your journey today!</strong> Every action counts towards a greener future.</p>
                    </div>
                    <button onclick="closeGamificationTutorial()" class="tutorial-close-btn">Get Started!</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Badge System CSS -->
    <?php echo generateBadgeModalCSS(); ?>
    
    <!-- Badge System JavaScript -->
    <?php echo generateBadgeModalJS($connection); ?>
    
    <!-- Toggle All Badges Script -->
    <script>
        function toggleAllBadges() {
            const content = document.getElementById('allBadgesContent');
            const btn = document.getElementById('toggleBadgesBtn');
            const icon = btn.querySelector('i');
            
            if (content.classList.contains('hidden')) {
                // Show badges
                content.classList.remove('hidden');
                btn.innerHTML = 'Hide <i class="fas fa-chevron-up"></i>';
            } else {
                // Hide badges
                content.classList.add('hidden');
                btn.innerHTML = 'Show <i class="fas fa-chevron-down"></i>';
            }
        }

        function filterBadges(category) {
            const badgeItems = document.querySelectorAll('.badge-item');
            const filterBtns = document.querySelectorAll('.filter-btn');
            const badgesContainer = document.getElementById('badgesContainer');
            
            // Update active filter button
            filterBtns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide badges based on category
            let visibleCount = 0;
            badgeItems.forEach(item => {
                if (category === 'all' || item.dataset.category === category) {
                    item.classList.remove('hidden');
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.classList.add('hidden');
                    item.style.display = 'none';
                }
            });
            
            // Adjust container height based on visible badges
            if (visibleCount === 0) {
                badgesContainer.style.minHeight = '200px';
            } else {
                badgesContainer.style.minHeight = 'auto';
            }
        }

        // Gamification Tutorial Functions
        function showGamificationTutorial() {
            document.getElementById('gamificationTutorial').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeGamificationTutorial() {
            const modal = document.getElementById('gamificationTutorial');
            modal.style.animation = 'fadeOut 0.3s ease';
            setTimeout(() => {
                modal.style.display = 'none';
                modal.style.animation = '';
                document.body.style.overflow = 'auto';
            }, 300);
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('gamificationTutorial');
            if (event.target === modal) {
                closeGamificationTutorial();
            }
        }

        // Add fade out animation
        const tutorialStyle = document.createElement('style');
        tutorialStyle.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(tutorialStyle);
    </script>
    
    <!-- Badge Category Filter Styles -->
    <style>
        .tutorial-content::-webkit-scrollbar {
            width: 12px;
        }

        .tutorial-content::-webkit-scrollbar-button {
            display: none;
            height: 0;
            width: 0;
        }

        .tutorial-content::-webkit-scrollbar-track {
            background: rgba(18, 53, 36, 0.3);
            border-radius: 0 10px 10px 0;
            margin: 10px 0;
        }

        .tutorial-content::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, #4CAF50, #2E7D32); 
            border-radius: 10px;
            border: 2px solid rgba(239, 227, 194, 0.5);
        }

        .tutorial-content::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, #3D9140, #1B5E20);
            box-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
        }

        .tutorial-content {
            border-top-right-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            scrollbar-width: thin;
            scrollbar-color: #4CAF50 rgba(18, 53, 36, 0.3);
        }

        .badge-category-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            background: white;
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #dee2e6;
        }

        .filter-btn:hover {
            background: #e9ecef;
            color: #495057;
            border-color: #adb5bd;
        }

        .filter-btn.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .badge-item {
            transition: all 0.3s ease;
            width: 160px;
            height: 180px;
            min-width: 160px;
            min-height: 180px;
            max-width: 160px;
            max-height: 180px;
        }

        .badge-item .badge {
            width: 100%;
            height: 100%;
            min-width: 100%;
            min-height: 100%;
            max-width: 100%;
            max-height: 100%;
        }

        .badge-item .badge p {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 140px;
        }

        .badge-item[style*="display: none"] {
            display: none !important;
        }

        @media (max-width: 768px) {
            .badge-category-filter {
                justify-content: center;
            }
            
            .filter-btn {
                font-size: 12px;
                padding: 6px 12px;
            }

            .badge-item {
                width: 140px;
                height: 160px;
                min-width: 140px;
                min-height: 160px;
                max-width: 140px;
                max-height: 160px;
            }

            .badge-item .badge {
                width: 100%;
                height: 100%;
            }

            .badge-item .badge p {
                font-size: 0.85rem;
                max-width: 120px;
            }
        }

        @media (max-width: 480px) {
            .badge-item {
                width: 120px;
                height: 140px;
                min-width: 120px;
                min-height: 140px;
                max-width: 120px;
                max-height: 140px;
            }

            .badge-item .badge p {
                font-size: 0.8rem;
                max-width: 100px;
            }
        }
        
        /* Tutorial Button Styles */
        .profile-header {
            position: relative;
        }
        
        .tutorial-button {
            position: absolute;
            z-index:3;
            top: 1rem;
            right: 1rem;
        }

        .tutorial-btn {
            background: var(--base-clr);
            color: var(--accent-clr);
            border: 2px solid var(--accent-clr);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(18, 53, 36, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tutorial-btn:hover {
            background: var(--accent-clr);
            color: var(--base-clr);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(239, 227, 194, 0.5);
        }

        /* Tutorial Modal Styles */
        .tutorial-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(18, 53, 36, 0.9); /* Using base-clr with transparency */
            animation: fadeIn 0.3s ease;
        }

        .tutorial-content {
            background: linear-gradient(135deg, var(--base-clr) 0%, var(--secondary-text-clr) 100%);
            margin: 2% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: var(--secondarybase-clr);
            border: 2px solid azure;
        }

        .tutorial-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid azure;
        }

        .tutorial-header h2 {
            margin: 0;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            color: azure;
        }

        .close-tutorial {
            background: none;
            border: none;
            color: azure;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-tutorial:hover {
            background: azure;
            color: var(--base-clr);
            transform: rotate(90deg);
        }

        .tutorial-body {
            padding: 2rem;
        }

        .tutorial-section {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            background: rgba(239, 227, 194, 0.1); /* Using accent-clr with transparency */
            padding: 1.5rem;
            border-radius: 15px;
            border: 1px solid rgba(239, 227, 194, 0.3);
            backdrop-filter: blur(10px);
        }

        .tutorial-icon {
            font-size: 3rem;
            margin-right: 1.5rem;
            color: azure;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            flex-shrink: 0;
        }

        .tutorial-text h3 {
            margin: 0 0 1rem 0;
            font-size: 1.5rem;
            color: azure;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }

        .tutorial-text p {
            margin-bottom: 1rem;
            line-height: 1.6;
            color: var(--secondarybase-clr);
        }

        .tutorial-text ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .tutorial-text li {
            margin-bottom: 0.5rem;
            line-height: 1.5;
            color: var(--secondarybase-clr);
        }

        .tutorial-text strong {
            color: azure;
        }

        .tutorial-footer {
            text-align: center;
            padding: 2rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 0 0 20px 20px;
            border-top: 2px solid azure;
        }

        .encouragement {
            margin-bottom: 2rem;
        }

        .encouragement i {
            font-size: 2rem;
            color: azure;
            margin-bottom: 1rem;
            display: block;
        }

        .encouragement p {
            color: var(--secondarybase-clr);
        }

        .encouragement strong {
            color: azure;
        }

        .tutorial-close-btn {
            background: linear-gradient(135deg, var(--base-clr), var(--secondary-text-clr));
            color: azure;
            border: 2px solid azure;
            padding: 1rem 3rem;
            border-radius: 30px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(18, 53, 36, 0.3);
        }

        .tutorial-close-btn:hover {
            background: azure;
            color: var(--base-clr);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 227, 194, 0.4);
        }

        @media (max-width: 768px) {
            .tutorial-content {
                margin: 5% auto;
                width: 95%;
            }
            
            .tutorial-header {
                padding: 1.5rem;
            }
            
            .tutorial-header h2 {
                font-size: 1.5rem;
            }
            
            .tutorial-body {
                padding: 1.5rem;
            }
            
            .tutorial-section {
                flex-direction: column;
                text-align: center;
            }
            
            .tutorial-icon {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .tutorial-btn {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
            }
        }
    </style>
    
    <!-- Badge Notification JavaScript -->
    <script>
        // Multiple Badge Navigation System
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
                window.location.hash = '#badges';
                // Scroll to badges section if it exists
                const badgesSection = document.querySelector('#badges');
                if (badgesSection) {
                    badgesSection.scrollIntoView({ behavior: 'smooth' });
                }
            }, 400);
        }

        // Eco Points Notification Functions
        function closeEcoPointsNotification() {
            const notification = document.getElementById('ecoPointsNotification');
            if (notification) {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        // Auto-close eco points notification after 5 seconds
        setTimeout(() => {
            const ecoNotification = document.getElementById('ecoPointsNotification');
            if (ecoNotification) {
                closeEcoPointsNotification();
            }
        }, 5000);

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
    
    <script src="..."></script>
</body>
</html>
