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

// Check if user is logged in (for viewing permissions)
$viewer_logged_in = isset($_SESSION['user_id']);
$viewer_id = $viewer_logged_in ? $_SESSION['user_id'] : null;

// Get target user ID from URL parameter
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (!$target_user_id) {
    header("Location: organizations.php");
    exit();
}

// Check if this is the user's own profile
$is_own_profile = $viewer_logged_in && $viewer_id == $target_user_id;

// Redirect to regular profile if viewing own profile
if ($is_own_profile) {
    header("Location: profile.php");
    exit();
}

// Get target user data from accountstbl
$user_query = "SELECT * FROM accountstbl WHERE account_id = ?";
$stmt = $connection->prepare($user_query);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    $_SESSION['response'] = [
        'status' => 'error',
        'message' => 'User not found!'
    ];
    header("Location: organizations.php");
    exit();
}

$user_data = $user_result->fetch_assoc();

// Get enhanced user point information
$userPointsSummary = getUserPointsSummary($target_user_id);

// Get attended events from attendeestbl
$events_query = "SELECT e.subject, e.start_date, e.venue, a.id 
                FROM attendeestbl a 
                JOIN eventstbl e ON a.event_id = e.event_id 
                WHERE a.account_id = ? 
                ORDER BY e.start_date DESC";
$events_stmt = $connection->prepare($events_query);
$events_stmt->bind_param("i", $target_user_id);
$events_stmt->execute();
$events_result = $events_stmt->get_result();

// Get user's badges using the database-driven badge system
$user_badges = BadgeSystem::parseUserBadges($user_data['badges']);

// Get badge statistics with percentages and rarity
$badge_statistics = BadgeSystem::calculateBadgeStatistics($connection);

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

// Get user's recent activities (limited public info)
$recentActivities = [];
if ($viewer_logged_in) {
    $activitiesQuery = "SELECT activity_type, points_awarded, created_at, reference_id
                       FROM eco_points_transactions 
                       WHERE user_id = ? AND points_awarded > 0
                       ORDER BY created_at DESC 
                       LIMIT 10";
    $stmt = $connection->prepare($activitiesQuery);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentActivities[] = $row;
    }
    $stmt->close();
}

// Get user's attended events (public information)
$attendedEvents = [];
$eventsQuery = "SELECT e.subject, e.start_date, e.venue, e.eco_points
                FROM attendeestbl a 
                JOIN eventstbl e ON a.event_id = e.event_id 
                WHERE a.account_id = ? 
                ORDER BY e.start_date DESC 
                LIMIT 5";
$stmt = $connection->prepare($eventsQuery);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendedEvents[] = $row;
}
$stmt->close();

// Get organization rank if user has organization
$orgRank = null;
$orgTotal = null;
if (!empty($user_data['organization'])) {
    $orgRankQuery = "SELECT 
                        organization,
                        SUM(eco_points) as total_points,
                        COUNT(*) as member_count,
                        FIND_IN_SET(SUM(eco_points), (
                            SELECT GROUP_CONCAT(org_points ORDER BY org_points DESC)
                            FROM (
                                SELECT SUM(eco_points) as org_points
                                FROM accountstbl 
                                WHERE organization IS NOT NULL AND organization != '' AND organization != 'N/A'
                                GROUP BY organization
                            ) as org_totals
                        )) as rank_position
                     FROM accountstbl 
                     WHERE organization = ?";
    $stmt = $connection->prepare($orgRankQuery);
    $stmt->bind_param("s", $user_data['organization']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $orgData = $result->fetch_assoc();
        $orgRank = $orgData['rank_position'];
        $orgTotal = $orgData['total_points'];
    }
    $stmt->close();
}

// Get user's ranking
$userRankQuery = "SELECT 
                    FIND_IN_SET(eco_points, (
                        SELECT GROUP_CONCAT(eco_points ORDER BY eco_points DESC)
                        FROM accountstbl
                    )) as user_rank,
                    (SELECT COUNT(*) FROM accountstbl WHERE eco_points > 0) as total_users
                  FROM accountstbl 
                  WHERE account_id = ?";
$stmt = $connection->prepare($userRankQuery);
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$result = $stmt->get_result();
$rankData = $result->fetch_assoc();
$userRank = $rankData['user_rank'];
$totalUsers = $rankData['total_users'];
$stmt->close();

// Calculate member since duration
$memberSince = new DateTime($user_data['date_registered']);
$now = new DateTime();
$memberDuration = $now->diff($memberSince);

// Function to format activity type for display
function formatActivityTypeForDisplay($activityType) {
    switch ($activityType) {
        case 'event_attendance':
            return 'Event Participation';
        case 'report_resolved':
            return 'Report Resolution';
        case 'daily_login':
            return 'Daily Login';
        case 'badge_bonus':
            return 'Badge Achievement';
        default:
            return ucwords(str_replace('_', ' ', $activityType));
    }
}

// Get badge rarity color
function getBadgeRarityColor($badgeName) {
    global $connection;
    $query = "SELECT rarity FROM badgestbl WHERE badge_name = ? AND is_active = 1";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $badgeName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $rarity = $result->fetch_assoc()['rarity'];
        switch (strtolower($rarity)) {
            case 'common': return '#95a5a6';
            case 'uncommon': return '#3498db';
            case 'rare': return '#9b59b6';
            case 'epic': return '#e67e22';
            case 'legendary': return '#f1c40f';
            default: return '#95a5a6';
        }
    }
    $stmt->close();
    return '#95a5a6';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($user_data['fullname']) ?> - Profile | ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type="text/javascript" src="app.js" defer></script>
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="gamification_notifications.css">
    
    <!-- Badge Notification Styles -->
    <style>
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--placeholder-text-clr);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.3s ease;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .back-button:hover {
            color: var(--base-clr);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: var(--accent-clr);
            min-height: 100vh;
        }
        
        .viewing-profile-indicator {
            background: var(--placeholder-text-clr);
            color: azure;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .user-info-header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .profile-info-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }
        
        .left-profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }
        
        .profile-pic-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--placeholder-text-clr);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .default-pic {
            width: 100%;
            height: 100%;
            background: var(--placeholder-text-clr);
            display: flex;
            align-items: center;
            justify-content: center;
            color: azure;
            font-size: 48px;
        }
        
        .left-profile-text h1 {
            margin: 0 0 10px 0;
            color: var(--base-clr);
            font-size: 2.5em;
            font-weight: 700;
        }
        
        .profile-location, .profile-organization {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-clr);
            margin: 8px 0;
            font-size: 1.1em;
        }
        
        .right-profile-info {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .eco-points-display, .total-points-display {
            text-align: center;
            background: var(--placeholder-text-clr);
            color: azure;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(62, 123, 39, 0.3);
        }
        
        .eco-points-number, .total-points-number {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .eco-points-label, .total-points-label {
            font-size: 0.9em;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .profile-stats-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--accent-clr);
            border-radius: 12px;
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: var(--placeholder-text-clr);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: azure;
            font-size: 1.5em;
        }
        
        .stat-number {
            font-size: 1.8em;
            font-weight: 700;
            color: var(--base-clr);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-clr);
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .profile-content-tabs {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .tab-navigation {
            display: flex;
            background: var(--accent-clr);
            border-bottom: 1px solid #dee2e6;
        }
        
        .tab-btn {
            flex: 1;
            padding: 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-clr);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .tab-btn.active {
            background: var(--placeholder-text-clr);
            color: azure;
        }
        
        .tab-btn:hover {
            background: var(--secondarybase-clr);
            color: var(--base-clr);
        }
        
        .tab-btn.active:hover {
            background: var(--base-clr);
        }
        
        .tab-content {
            display: none;
            padding: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .tab-content-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .tab-content-header h3 {
            color: var(--base-clr);
            font-size: 1.8em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .badge-display {
            background: var(--accent-clr);
            border-radius: 12px;
            padding: 20px;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .badge-display.earned {
            border-color: var(--placeholder-text-clr);
            box-shadow: 0 4px 15px rgba(62, 123, 39, 0.2);
        }
        
        .badge-display:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        .badge-icon-container {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .badge-icon {
            font-size: 3em;
            color: var(--placeholder-text-clr);
        }
        
        .badge-name {
            color: var(--base-clr);
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .badge-description {
            color: var(--text-clr);
            text-align: center;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .badge-earned-date {
            text-align: center;
            color: var(--placeholder-text-clr);
            font-weight: 500;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .no-content-message {
            text-align: center;
            color: var(--text-clr);
            padding: 40px;
        }
        
        .no-content-message i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .no-content-message h4 {
            color: var(--base-clr);
            margin-bottom: 10px;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: var(--accent-clr);
            border-radius: 12px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .event-item:hover {
            background: var(--secondarybase-clr);
            transform: translateX(5px);
        }
        
        .event-icon {
            width: 50px;
            height: 50px;
            background: var(--placeholder-text-clr);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: azure;
            font-size: 1.3em;
        }
        
        .event-details {
            flex: 1;
        }
        
        .event-title {
            color: var(--base-clr);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .event-meta {
            display: flex;
            gap: 20px;
            color: var(--text-clr);
            font-size: 0.9em;
        }
        
        .event-date, .event-venue {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .points-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .points-summary-card {
            background: var(--placeholder-text-clr);
            color: azure;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(62, 123, 39, 0.3);
        }
        
        .summary-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        
        .summary-number {
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .activity-breakdown {
            background: var(--accent-clr);
            padding: 25px;
            border-radius: 12px;
        }
        
        .activity-breakdown h4 {
            color: var(--base-clr);
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(62, 123, 39, 0.2);
        }
        
        .breakdown-item:last-child {
            border-bottom: none;
        }
        
        .activity-name {
            color: var(--base-clr);
            font-weight: 500;
        }
        
        .activity-points {
            color: var(--placeholder-text-clr);
            font-weight: 600;
        }
        
        .no-activity-data {
            text-align: center;
            color: var(--placeholder-text-clr);
            padding: 20px;
        }
        
        @media (max-width: 768px) {
            .profile-info-container {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .right-profile-info {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tab-navigation {
                flex-direction: column;
            }
            
            .badges-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if ($viewer_logged_in): ?>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button>
        </form>
        <nav class="navbar">
            <a href="initiatives.php">Initiatives</a>
            <a href="about.php">About</a>
            <a href="events.php">Events</a>
            <a href="leaderboards.php">Leaderboards</a>
            <?php if (isset($_SESSION["name"])): ?>
            <a href="organizations.php">Organizations</a>
            <?php endif; ?>
            <?php if(isset($_SESSION["name"])) {
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
                echo '<a href="login.php" class="login-link">Login</a>';
            }
            ?>
        </nav>
    </header>
    
    <aside id="sidebar" class="close">
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button onclick="SidebarToggle()" id="toggle-btn" class="rotate">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
                </button>
            </li>
            <hr>
            <li class="active">
                <a href="index.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg>
                    <span>Home</span>
                </a>
            </li>
            <li>
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
                <button onclick="DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>
                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="reportspage.php" tabindex="-1">My Reports</a></li>
                    <li><a href="myevents.php" tabindex="-1">My Events</a></li>
                    </div>
                </ul>
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
        </ul>
    </aside>
    <?php endif; ?>

    <main>
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
            <a href="organizations.php" class="back-button">
                <i class='bx bx-arrow-back'></i> Back to Organizations
            </a>
            
            <div class="viewing-profile-indicator">
                <i class="fas fa-eye"></i>
                You are viewing <?= htmlspecialchars($user_data['fullname']) ?>'s profile
            </div>

            <div class="user-info-header">
                <div class="profile-info-container">
                    <div class="left-profile-info">
                        <div class="userprofile">
                            <div class="profile-pic-container">
                                <?php if($user_data['profile_thumbnail']): ?>
                                    <img src="<?= htmlspecialchars($user_data['profile_thumbnail']) ?>" alt="Profile Picture" class="profile-pic">
                                <?php else: ?>
                                    <div class="default-pic">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="left-profile-text">
                            <h1><?= htmlspecialchars($user_data['fullname']) ?></h1>
                            <p class="profile-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($user_data['barangay']) ?>, <?= htmlspecialchars($user_data['city_municipality']) ?>
                            </p>
                            <?php if($user_data['organization']): ?>
                            <p class="profile-organization">
                                <i class="fas fa-users"></i>
                                <?= htmlspecialchars($user_data['organization']) ?>
                            </p>
                            <?php endif; ?>
                            <?php if($user_data['bio']): ?>
                            <p class="profile-bio"><?= htmlspecialchars($user_data['bio']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="right-profile-info">
                        <div class="eco-points-display">
                            <div class="eco-points-number">
                                <i class="fas fa-leaf"></i>
                                <?= number_format($user_data['eco_points']) ?>
                            </div>
                            <div class="eco-points-label">Eco Points</div>
                        </div>
                        <div class="total-points-display">
                            <div class="total-points-number">
                                <i class="fas fa-leaf"></i>
                                <?= number_format($user_data['total_eco_points']) ?>
                            </div>
                            <div class="total-points-label">Total Earned</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Stats Section -->
            <div class="profile-stats-container">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?= count($user_badges) ?></div>
                            <div class="stat-label">Badges Earned</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?= $events_result->num_rows ?></div>
                            <div class="stat-label">Events Attended</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-number"><?= htmlspecialchars($user_data['accessrole']) ?></div>
                            <div class="stat-label">Role</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-info">
                            <?php 
                            $joinDate = new DateTime($user_data['date_registered']);
                            $now = new DateTime();
                            $interval = $joinDate->diff($now);
                            ?>
                            <div class="stat-number"><?= $interval->days ?></div>
                            <div class="stat-label">Days as Member</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content Tabs -->
            <div class="profile-content-tabs">
                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="switchProfileTab('badges-tab', this)">
                        <i class="fas fa-medal"></i> Badges
                    </button>
                    <button class="tab-btn" onclick="switchProfileTab('events-tab', this)">
                        <i class="fas fa-calendar"></i> Events
                    </button>
                    <button class="tab-btn" onclick="switchProfileTab('activities-tab', this)" style="display: none;">
                        <i class="fas fa-chart-line"></i> Activities
                    </button>
                </div>

                <!-- Badges Tab -->
                <div id="badges-tab" class="tab-content active">
                    <div class="tab-content-header">
                        <h3><i class="fas fa-medal"></i> Achievement Badges</h3>
                        <p>Badges earned through environmental activities and milestones</p>
                    </div>
                    
                    <?php if (!empty($user_badges)): ?>
                        <div class="badges-grid">
                            <?php foreach ($user_badges as $badge): ?>
                                <div class="badge-display earned">
                                    <div class="badge-icon-container">
                                        <i class="<?= getBadgeIcon($badge['name']) ?> badge-icon"></i>
                                    </div>
                                    <div class="badge-info">
                                        <h4 class="badge-name"><?= htmlspecialchars($badge['name']) ?></h4>
                                        <p class="badge-description"><?= getBadgeDescription($badge['name']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content-message">
                            <i class="fas fa-medal"></i>
                            <h4>No Badges Yet</h4>
                            <p>This user hasn't earned any badges yet. Badges are awarded for various environmental activities and achievements.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Events Tab -->
                <div id="events-tab" class="tab-content">
                    <div class="tab-content-header">
                        <h3><i class="fas fa-calendar"></i> Attended Events</h3>
                        <p>Environmental events and activities this user has participated in</p>
                    </div>
                    
                    <?php if ($events_result->num_rows > 0): ?>
                        <div class="events-list">
                            <?php while($event = $events_result->fetch_assoc()): ?>
                                <div class="event-item">
                                    <div class="event-icon">
                                        <i class="fas fa-calendar-check"></i>
                                    </div>
                                    <div class="event-details">
                                        <h4 class="event-title"><?= htmlspecialchars($event['subject']) ?></h4>
                                        <div class="event-meta">
                                            <span class="event-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                            </span>
                                            <span class="event-venue">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($event['venue']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-content-message">
                            <i class="fas fa-calendar"></i>
                            <h4>No Events Attended</h4>
                            <p>This user hasn't attended any events yet. Events are a great way to earn eco points and contribute to environmental conservation.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Activities Tab -->
                <div id="activities-tab" class="tab-content">
                    <div class="tab-content-header">
                        <h3><i class="fas fa-chart-line"></i> Points Summary</h3>
                        <p>Overview of eco points earned through various activities</p>
                    </div>
                    
                    <div class="points-summary-grid">
                        <div class="points-summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-leaf"></i>
                            </div>
                            <div class="summary-info">
                                <div class="summary-number"><?= number_format($userPointsSummary['current_points']) ?></div>
                                <div class="summary-label">Current Points</div>
                            </div>
                        </div>
                        <div class="points-summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="summary-info">
                                <div class="summary-number"><?= number_format($userPointsSummary['total_earned']) ?></div>
                                <div class="summary-label">Total Earned</div>
                            </div>
                        </div>
                        <div class="points-summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-calendar-week"></i>
                            </div>
                            <div class="summary-info">
                                <div class="summary-number"><?= number_format($userPointsSummary['this_week']) ?></div>
                                <div class="summary-label">This Week</div>
                            </div>
                        </div>
                        <div class="points-summary-card">
                            <div class="summary-icon">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div class="summary-info">
                                <div class="summary-number"><?= number_format($userPointsSummary['this_month']) ?></div>
                                <div class="summary-label">This Month</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-breakdown">
                        <h4>Activity Breakdown</h4>
                        <div class="breakdown-list">
                            <?php if (!empty($userPointsSummary['breakdown'])): ?>
                                <?php foreach ($userPointsSummary['breakdown'] as $activity => $points): ?>
                                    <div class="breakdown-item">
                                        <span class="activity-name"><?= formatActivityType($activity) ?></span>
                                        <span class="activity-points"><?= number_format($points) ?> points</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-activity-data">
                                    <i class="fas fa-chart-line"></i>
                                    <p>No detailed activity data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php if ($viewer_logged_in): ?>
    <footer>
        <div id="right-footer">
            <p>&copy; 2024 ManGrow. All rights reserved.</p>
        </div>
    </footer>
    <?php endif; ?>

    <script>
        // Profile details toggle
        function toggleProfileDetails() {
            const profileDetails = document.getElementById('profile-details');
            profileDetails.classList.toggle('close');
        }

        // Tab switching functionality
        function switchProfileTab(tabId, button) {
            // Remove active class from all tabs and buttons
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to selected tab and button
            document.getElementById(tabId).classList.add('active');
            button.classList.add('active');
        }

        // Legacy function for backward compatibility
        function switchTab(tabName) {
            switchProfileTab(tabName + '-tab', event.target);
        }

        // Badge helper functions
        function getBadgeIcon(badgeName) {
            const iconMap = {
                'First Steps': 'fas fa-seedling',
                'Event Enthusiast': 'fas fa-calendar-check',
                'Community Guardian': 'fas fa-shield-alt',
                'Eco Warrior': 'fas fa-leaf',
                'Team Player': 'fas fa-users',
                'Milestone Master': 'fas fa-mountain',
                'Daily Defender': 'fas fa-sun',
                'Report Hero': 'fas fa-flag',
                'Streak Master': 'fas fa-fire',
                'Eco Champion': 'fas fa-trophy'
            };
            return iconMap[badgeName] || 'fas fa-star';
        }

        function getBadgeDescription(badgeName) {
            const descriptions = {
                'First Steps': 'Welcome to the community! Earned your first eco points.',
                'Event Enthusiast': 'Active participant in environmental events.',
                'Community Guardian': 'Dedicated to protecting the environment.',
                'Eco Warrior': 'Champion of environmental conservation.',
                'Team Player': 'Great collaborator in community activities.',
                'Milestone Master': 'Achieved significant environmental milestones.',
                'Daily Defender': 'Consistent daily environmental actions.',
                'Report Hero': 'Actively reports environmental issues.',
                'Streak Master': 'Maintained impressive activity streaks.',
                'Eco Champion': 'Top performer in environmental activities.'
            };
            return descriptions[badgeName] || 'Special achievement badge.';
        }

        function formatActivityType(activity) {
            const typeMap = {
                'event_attendance': 'Event Participation',
                'report_resolved': 'Environmental Reports',
                'daily_login': 'Daily Engagement',
                'badge_bonus': 'Achievement Bonuses',
                'milestone_bonus': 'Milestone Rewards',
                'collaboration_bonus': 'Team Collaboration'
            };
            return typeMap[activity] || activity.charAt(0).toUpperCase() + activity.slice(1);
        }

        // Close profile details when clicking outside
        document.addEventListener('click', function(event) {
            const profileDetails = document.getElementById('profile-details');
            const profileIcon = document.querySelector('.profile-icon');
            
            if (profileDetails && profileIcon && 
                !profileDetails.contains(event.target) && 
                !profileIcon.contains(event.target)) {
                profileDetails.classList.add('close');
            }
        });
    </script>
</body>
</html>