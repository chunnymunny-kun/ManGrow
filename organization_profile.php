<?php
session_start();
include 'database.php';
include 'badge_system_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_user_organization = $_SESSION['organization'] ?? '';

// Get organization ID from URL parameter
if (!isset($_GET['org_id']) || empty($_GET['org_id'])) {
    header("Location: organizations.php");
    exit();
}

$org_id = intval($_GET['org_id']);

// Fetch organization details
$orgQuery = "SELECT o.*, COUNT(DISTINCT om.account_id) as member_count,
                    COALESCE(SUM(a.eco_points), 0) as total_points,
                    COALESCE(AVG(a.eco_points), 0) as avg_points
             FROM organizations o
             LEFT JOIN organization_members om ON o.org_id = om.org_id
             LEFT JOIN accountstbl a ON om.account_id = a.account_id
             WHERE o.org_id = ?
             GROUP BY o.org_id";
$stmt = $connection->prepare($orgQuery);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$result = $stmt->get_result();
$organization = $result->fetch_assoc();
$stmt->close();

if (!$organization) {
    header("Location: organizations.php");
    exit();
}

// Check if organization is private and user is not a member
if ($organization['privacy_setting'] === 'private' && $organization['name'] !== $current_user_organization) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => "This organization is private and you don't have access to view its details."
    ];
    header("Location: organizations.php");
    exit();
}

// Fetch organization members
$membersQuery = "SELECT a.account_id, a.fullname, a.eco_points, a.profile_thumbnail, 
                        a.barangay, a.city_municipality, om.role, om.joined_at
                 FROM organization_members om
                 JOIN accountstbl a ON om.account_id = a.account_id
                 WHERE om.org_id = ?
                 ORDER BY 
                     CASE om.role 
                         WHEN 'creator' THEN 1 
                         WHEN 'admin' THEN 2 
                         WHEN 'member' THEN 3 
                         ELSE 4 
                     END,
                     a.eco_points DESC";
$stmt = $connection->prepare($membersQuery);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$result = $stmt->get_result();
$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}
$stmt->close();

// Fetch organization badges (from members' badge CSV data)
$badgesQuery = "SELECT a.badges, a.badge_count
                FROM accountstbl a
                JOIN organization_members om ON a.account_id = om.account_id
                WHERE om.org_id = ? AND a.badges IS NOT NULL AND a.badges != ''";
$stmt = $connection->prepare($badgesQuery);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$result = $stmt->get_result();

// Collect all badges from organization members
$all_badges = [];
$badge_counts = [];
while ($row = $result->fetch_assoc()) {
    if (!empty($row['badges'])) {
        $member_badges = explode(',', $row['badges']);
        foreach ($member_badges as $badge_name) {
            $badge_name = trim($badge_name);
            if (!empty($badge_name)) {
                $badge_counts[$badge_name] = ($badge_counts[$badge_name] ?? 0) + 1;
                if (!in_array($badge_name, $all_badges)) {
                    $all_badges[] = $badge_name;
                }
            }
        }
    }
}
$stmt->close();

// Get badge details from badgestbl
$badges = [];
if (!empty($all_badges)) {
    $placeholders = str_repeat('?,', count($all_badges) - 1) . '?';
    $badgeDetailsQuery = "SELECT badge_name, description, icon_class, color, image_path
                          FROM badgestbl 
                          WHERE badge_name IN ($placeholders) AND is_active = 1
                          ORDER BY badge_name";
    $stmt = $connection->prepare($badgeDetailsQuery);
    $stmt->bind_param(str_repeat('s', count($all_badges)), ...$all_badges);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $badge_name = $row['badge_name'];
        $badges[] = [
            'badge_name' => $badge_name,
            'badge_description' => $row['description'],
            'badge_icon' => $row['icon_class'],
            'badge_color' => $row['color'],
            'image_path' => $row['image_path'],
            'earned_count' => $badge_counts[$badge_name] ?? 0
        ];
    }
    $stmt->close();
    
    // Sort badges by earned count (descending)
    usort($badges, function($a, $b) {
        return $b['earned_count'] - $a['earned_count'];
    });
    
    // Create separate array for top 3 rarest badges (ascending by count)
    $rarest_badges = $badges;
    usort($rarest_badges, function($a, $b) {
        return $a['earned_count'] - $b['earned_count'];
    });
    $top_rarest_badges = array_slice($rarest_badges, 0, 3);
}

// Fetch organization events (created by members)
$eventsQuery = "SELECT e.event_id, e.subject as event_name, e.description as event_description, 
                       e.start_date, e.end_date, e.venue as event_location, e.event_status, 
                       e.created_at, e.program_type, e.thumbnail,
                       a.fullname as created_by
                FROM eventstbl e
                JOIN accountstbl a ON e.author = a.account_id
                JOIN organization_members om ON a.account_id = om.account_id
                WHERE om.org_id = ? AND e.event_status IN ('approved', 'completed')
                ORDER BY e.start_date DESC, e.created_at DESC
                LIMIT 20";
$stmt = $connection->prepare($eventsQuery);
$stmt->bind_param("i", $org_id);
$stmt->execute();
$result = $stmt->get_result();
$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

// Check if current user is a member of this organization
$is_member = false;
$user_role = null;
if (!empty($current_user_organization) && $organization['name'] === $current_user_organization) {
    $is_member = true;
    foreach ($members as $member) {
        if ($member['account_id'] == $user_id) {
            $user_role = $member['role'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($organization['name']) ?> - Organization Profile</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script type="text/javascript" src="app.js" defer></script>
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: var(--accent-clr);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .profile-header {
            background: var(--event-clr);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .org-title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .org-title-info h1 {
            color: var(--base-clr);
            margin-bottom: 10px;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .privacy-badge {
            font-size: 0.9rem;
            padding: 6px 15px;
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        
        .privacy-badge.public {
            background: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        
        .privacy-badge.private {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        
        .org-location {
            color: var(--placeholder-text-clr);
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .org-description {
            color: var(--text-clr);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .back-button {
            background: var(--placeholder-text-clr);
            color: azure;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: var(--base-clr);
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--event-clr);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--placeholder-text-clr);
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--text-clr);
            font-size: 1rem;
            font-weight: 500;
        }
        
        .section-container {
            background: var(--event-clr);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .section-title {
            color: var(--base-clr);
            font-size: 1.8rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid rgba(62, 123, 39, 0.2);
            padding-bottom: 15px;
        }
        
        .section-header-with-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .toggle-btn {
            background: var(--base-clr);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover {
            background: var(--placeholder-text-clr);
            transform: translateY(-2px);
        }
        
        .toggle-btn i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .toggle-btn.active i {
            transform: rotate(180deg);
        }
        
        .members-section-content {
            transition: all 0.3s ease;
        }
        
        .members-table-container {
            background: var(--accent-clr);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(62, 123, 39, 0.3);
        }
        
        .members-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .members-table thead {
            background: var(--primary-clr);
            color: var(--base-clr);
        }
        
        .members-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .members-table tbody tr {
            border-bottom: 1px solid rgba(62, 123, 39, 0.1);
            transition: background-color 0.3s ease;
        }
        
        .members-table tbody tr:hover {
            background: rgba(62, 123, 39, 0.05);
        }
        
        .members-table td {
            padding: 12px;
            vertical-align: middle;
        }
        
        .member-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--secondarybase-clr);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .member-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .member-avatar-small i {
            font-size: 1.2rem;
            color: var(--placeholder-text-clr);
        }
        
        .member-name-cell {
            font-weight: 600;
            color: var(--text-clr);
        }
        
        .member-points-cell {
            color: var(--primary-clr);
            font-weight: 600;
        }
        
        .member-points-cell::after {
            content: " eco points";
            color: var(--placeholder-text-clr);
            font-weight: normal;
            font-size: 0.9rem;
        }
        
        .member-location-cell {
            color: var(--text-clr);
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .role-badge.creator {
            background: #f39c12;
            color: white;
        }
        
        .role-badge.admin {
            background: var(--placeholder-text-clr);
            color: white;
        }
        
        .role-badge.member {
            background: rgba(62, 123, 39, 0.2);
            color: var(--placeholder-text-clr);
        }
        
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .badge-card {
            background: var(--accent-clr);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(62, 123, 39, 0.3);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .badge-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }
        
        .badge-info h4 {
            color: var(--text-clr);
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .badge-description {
            color: var(--text-clr);
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }
        
        .badge-count {
            color: var(--placeholder-text-clr);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .event-card {
            background: var(--accent-clr);
            border-radius: 10px;
            border: 1px solid rgba(62, 123, 39, 0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .event-thumbnail {
            width: 100%;
            height: 120px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
        }
        
        .event-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .event-card:hover .event-thumbnail img {
            transform: scale(1.05);
        }
        
        .event-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .event-title {
            color: var(--text-clr);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            flex: 1;
        }
        
        .event-program-type {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-left: 10px;
            white-space: nowrap;
        }
        
        .event-program-type.event {
            background: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        
        .event-program-type.announcement {
            background: rgba(59, 130, 246, 0.2);
            color: #2563eb;
        }
        
        .event-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 5px;
        }
        
        .event-status.approved {
            background: rgba(34, 197, 94, 0.2);
            color: #16a34a;
        }
        
        .event-status.completed {
            background: rgba(59, 130, 246, 0.2);
            color: #2563eb;
        }
        
        .event-details {
            margin-bottom: 15px;
            flex: 1;
        }
        
        .event-detail {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: var(--text-clr);
            font-size: 0.9rem;
        }
        
        .event-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
        }
        
        .event-creator {
            color: var(--placeholder-text-clr);
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .view-event-btn {
            background: var(--placeholder-text-clr);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .view-event-btn:hover {
            background: var(--base-clr);
            transform: translateY(-1px);
        }
        
        /* Top Badges Section */
        .top-badges-section {
            background: var(--event-clr);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid rgba(62, 123, 39, 0.2);
        }
        
        .top-badges-title {
            color: var(--base-clr);
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .top-badge-card {
            background: var(--accent-clr);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(62, 123, 39, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
        }
        
        .top-badge-rank {
            background: var(--placeholder-text-clr);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .top-badge-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
        }
        
        .top-badge-info h5 {
            color: var(--text-clr);
            margin-bottom: 3px;
            font-size: 0.9rem;
        }
        
        .top-badge-rarity {
            color: var(--placeholder-text-clr);
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .no-data-message {
            text-align: center;
            padding: 40px;
            color: var(--text-clr);
            opacity: 0.7;
            font-style: italic;
        }
        
        .no-data-message i {
            font-size: 3rem;
            color: var(--placeholder-text-clr);
            opacity: 0.5;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                margin: 10px;
                padding: 15px;
            }
            
            .org-title-section {
                flex-direction: column;
                gap: 15px;
            }
            
            .org-title-info h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .badges-grid, .events-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header-with-toggle {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .members-table-container {
                overflow-x: auto;
            }
            
            .members-table {
                min-width: 600px;
            }
            
            .members-table th,
            .members-table td {
                padding: 8px 6px;
                font-size: 0.9rem;
            }
            
            .member-avatar-small {
                width: 35px;
                height: 35px;
            }
            
            .toggle-btn {
                width: 100%;
                justify-content: center;
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
        <nav class="navbar">
            <a href="initiatives.php">Initiatives</a>
            <a href="about.php">About</a>
            <a href="events.php">Events</a>
            <a href="leaderboards.php">Leaderboards</a>
            <?php if (isset($_SESSION["name"])): ?>
            <a href="organizations.php">Organizations</a>
            <?php endif; ?>
            <?php 
            if (isset($_SESSION["name"])) {
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="bx bx-user"></i></div>';
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
                    <svg width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-right"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12,5 19,12 12,19"></polyline></svg>
                </button>
            </li>
            <hr>
            <li class="active">
                <a href="index.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-240h240v240h120v-360L480-740 240-560v360Zm-80 80v-480l320-240 320 240v480H520v-240h-80v240H160Zm320-350Z"/></svg>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="mangrovemappage.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-690v-100q0-42 29-71t71-29h100v100q0-42-29-71t-71-29H440ZM220-450q-58 0-99-41t-41-99v-140h140q58 0 99 41t41 99v140H220ZM640-90q-39 0-74.5-12T501-135l-33 33q-11 11-28 11t-28-11q-11-11-11-28t11-28l33-33q-21-29-33-64.5T400-330q0-100 70-170.5T640-571h241v241q0 100-70.5 170T640-90Zm0-80q67 0 113-47t46-113v-160H640q-66 0-113 46.5T480-330q0 23 5.5 43.5T502-248l110-110q11-11 28-11t28 11q11 11 11 28t-11 28L558-192q18 11 38.5 16.5T640-170Zm1-161Z"/></svg>
                    <span>Map</span>
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
                        <li><a href="initiatives.php">Initiatives</a></li>
                        <li><a href="leaderboards.php">Leaderboards</a></li>
                        <li><a href="reports.php">Reports</a></li>
                    </div>
                </ul>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == "Barangay Official" || $_SESSION['accessrole'] == "Administrator" || $_SESSION['accessrole'] == "Representative")) {
                    echo '<li>';
                    echo '<a href="adminpage.php" class="admin-link" tabindex="-1">';
                    echo '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-120v-80h280v-560H480v-80h280q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H480Zm-80-160-55-58 102-102H120v-80h327L345-622l55-58 200 200-200 200Z"/></svg>';
                    echo '<span>Admin</span>';
                    echo '</a>';
                    echo '</li>';
                }
            ?>
        </ul>
    </aside>
    
    <main>
        <!-- Profile Details Popup -->
        <div class="profile-details close" id="profile-details">
            <div class="details-box">
                <?php if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
                    <img src="<?= $_SESSION['profile_image'] ?>" alt="Profile Image" class="big-profile-icon">
                <?php else: ?>
                    <div class="big-default-profile-icon"><i class="bx bx-user"></i></div>
                <?php endif; ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <?php if(isset($_SESSION["organization"])): ?>
                    <p><?= $_SESSION["organization"] ?></p>
                <?php endif; ?>
            </div>
            <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        
        <div class="profile-container">
            <!-- Organization Header -->
            <div class="profile-header">
                <div class="org-title-section">
                    <div class="org-title-info">
                        <h1>
                            <i class='bx bx-group'></i>
                            <?= htmlspecialchars($organization['name']) ?>
                            <span class="privacy-badge <?= $organization['privacy_setting'] ?>">
                                <i class='bx bx-<?= $organization['privacy_setting'] === 'private' ? 'lock' : 'globe' ?>'></i>
                                <?= ucfirst($organization['privacy_setting']) ?>
                            </span>
                        </h1>
                        
                        <?php if (!empty($organization['barangay']) && !empty($organization['city_municipality'])): ?>
                            <div class="org-location">
                                <i class='bx bx-map'></i>
                                <?= htmlspecialchars($organization['barangay']) ?>, <?= htmlspecialchars($organization['city_municipality']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($organization['description'])): ?>
                            <div class="org-description">
                                <?= htmlspecialchars($organization['description']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <a href="organizations.php" class="back-button">
                        <i class='bx bx-arrow-back'></i>
                        Back to Organizations
                    </a>
                </div>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $organization['member_count'] ?>/<?= $organization['capacity_limit'] ?></div>
                        <div class="stat-label">Members</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($organization['total_points']) ?></div>
                        <div class="stat-label">Total Eco Points</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= number_format($organization['avg_points']) ?></div>
                        <div class="stat-label">Average Points</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= count($badges) ?></div>
                        <div class="stat-label">Unique Badges</div>
                    </div>
                </div>
            </div>
            
            <!-- Members Section -->
            <div class="section-container">
                <div class="section-header-with-toggle">
                    <h2 class="section-title">
                        <i class='bx bx-users'></i>
                        Organization Members (<?= count($members) ?>)
                    </h2>
                    <button class="toggle-btn" onclick="toggleMembersSection()" id="membersToggleBtn">
                        <i class='bx bx-chevron-down'></i>
                        <span>Show Members</span>
                    </button>
                </div>
                
                <div class="members-section-content" id="membersContent" style="display: none;">
                    <?php if (!empty($members)): ?>
                        <div class="members-table-container">
                            <table class="members-table">
                                <thead>
                                    <tr>
                                        <th>Avatar</th>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Eco Points</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): ?>
                                        <tr>
                                            <td>
                                                <div class="member-avatar-small">
                                                    <?php if ($member['profile_thumbnail']): ?>
                                                        <img src="<?= htmlspecialchars($member['profile_thumbnail']) ?>" alt="<?= htmlspecialchars($member['fullname']) ?>">
                                                    <?php else: ?>
                                                        <i class='bx bx-user'></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="member-name-cell"><?= htmlspecialchars($member['fullname']) ?></td>
                                            <td>
                                                <div class="role-badge <?= $member['role'] ?>">
                                                    <?php if ($member['role'] === 'creator'): ?>
                                                        <i class='bx bx-crown'></i> Creator
                                                    <?php elseif ($member['role'] === 'admin'): ?>
                                                        <i class='bx bx-shield'></i> Admin
                                                    <?php else: ?>
                                                        <i class='bx bx-user'></i> Member
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="member-points-cell"><?= number_format($member['eco_points']) ?></td>
                                            <td class="member-location-cell"><?= htmlspecialchars($member['barangay']) ?>, <?= htmlspecialchars($member['city_municipality']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class='bx bx-user-x'></i>
                            <p>No members found in this organization.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Badges Section -->
            <div class="section-container">
                <h2 class="section-title">
                    <i class='bx bx-medal'></i>
                    Organization Badges
                </h2>
                
                <?php if (!empty($top_rarest_badges)): ?>
                    <!-- Top 3 Rarest Badges -->
                    <div class="top-badges-section">
                        <h3 class="top-badges-title">
                            <i class='bx bx-trophy'></i>
                            Rarest Achievements
                        </h3>
                        <div class="top-badges-grid">
                            <?php foreach ($top_rarest_badges as $index => $badge): ?>
                                <div class="top-badge-card">
                                    <div class="top-badge-rank"><?= $index + 1 ?></div>
                                    <div class="top-badge-icon" style="background-color: <?= htmlspecialchars($badge['badge_color']) ?>;">
                                        <?php if (!empty($badge['image_path']) && file_exists($badge['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($badge['image_path']) ?>" alt="<?= htmlspecialchars($badge['badge_name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                        <?php else: ?>
                                            <i class='<?= htmlspecialchars($badge['badge_icon']) ?>'></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="top-badge-info">
                                        <h5><?= htmlspecialchars($badge['badge_name']) ?></h5>
                                        <div class="top-badge-rarity"><?= $badge['earned_count'] ?> member<?= $badge['earned_count'] != 1 ? 's' : '' ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($badges)): ?>
                    <div class="badges-grid">
                        <?php foreach ($badges as $badge): ?>
                            <div class="badge-card">
                                <div class="badge-icon" style="background-color: <?= htmlspecialchars($badge['badge_color']) ?>;">
                                    <?php if (!empty($badge['image_path']) && file_exists($badge['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($badge['image_path']) ?>" alt="<?= htmlspecialchars($badge['badge_name']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                    <?php else: ?>
                                        <i class='<?= htmlspecialchars($badge['badge_icon']) ?>'></i>
                                    <?php endif; ?>
                                </div>
                                <div class="badge-info">
                                    <h4><?= htmlspecialchars($badge['badge_name']) ?></h4>
                                    <div class="badge-count">Earned by <?= $badge['earned_count'] ?> member<?= $badge['earned_count'] != 1 ? 's' : '' ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class='bx bx-medal'></i>
                        <p>No badges earned by organization members yet.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Events Section -->
            <div class="section-container">
                <h2 class="section-title">
                    <i class='bx bx-calendar-event'></i>
                    Organization Events & Announcements
                </h2>
                
                <?php if (!empty($events)): ?>
                    <div class="events-grid">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <!-- Event Thumbnail -->
                                <div class="event-thumbnail">
                                    <?php if (!empty($event['thumbnail'])): ?>
                                        <img src="<?= htmlspecialchars($event['thumbnail']) ?>" alt="<?= htmlspecialchars($event['event_name']) ?>">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, var(--placeholder-text-clr), var(--base-clr)); display: flex; align-items: center; justify-content: center;">
                                            <i class='bx bx-image' style="font-size: 2rem; color: white; opacity: 0.7;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-content">
                                    <div class="event-header">
                                        <div style="flex: 1;">
                                            <div class="event-title"><?= htmlspecialchars($event['event_name']) ?></div>
                                            <span class="event-program-type <?= strtolower($event['program_type']) ?>">
                                                <?= htmlspecialchars($event['program_type']) ?>
                                            </span>
                                        </div>
                                        <span class="event-status <?= $event['event_status'] ?>">
                                            <?= ucfirst($event['event_status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="event-details">
                                        <div class="event-detail">
                                            <i class='bx bx-calendar'></i>
                                            <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                            <?php if ($event['end_date'] && $event['end_date'] != $event['start_date']): ?>
                                                - <?= date('M j, Y', strtotime($event['end_date'])) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-detail">
                                            <i class='bx bx-map'></i>
                                            <?= htmlspecialchars($event['event_location']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="event-actions">
                                        <div class="event-creator">Created by: <?= htmlspecialchars($event['created_by']) ?></div>
                                        <a href="event_details.php?event_id=<?= $event['event_id'] ?>" class="view-event-btn">
                                            <i class='bx bx-show'></i>
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data-message">
                        <i class='bx bx-calendar-x'></i>
                        <p>No events have been organized by this organization yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <footer>
        <div id="right-footer">
            <h3>Follow us on</h3>
            <div id="social-media-footer">
                <a href="https://web.facebook.com/"><i class="fab fa-facebook"></i></a>
                <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                <a href="https://x.com/"><i class="fab fa-twitter"></i></a>
                <a href="https://www.youtube.com/"><i class="fab fa-youtube"></i></a>
                <a href="https://github.com/"><i class="fab fa-github"></i></a>
            </div>
            <p>This website is developed by ManGrow. All Rights Reserved.</p>
        </div>
    </footer>
    
    <script>
        // Profile popup toggle functionality
        function toggleProfilePopup(event) {
            event.stopPropagation();
            const profileDetails = document.getElementById('profile-details');
            if (profileDetails) {
                profileDetails.classList.toggle('close');
            }
        }

        // Members section toggle functionality
        function toggleMembersSection() {
            const membersContent = document.getElementById('membersContent');
            const toggleBtn = document.getElementById('membersToggleBtn');
            const icon = toggleBtn.querySelector('i');
            const text = toggleBtn.querySelector('span');
            
            if (membersContent.style.display === 'none') {
                membersContent.style.display = 'block';
                toggleBtn.classList.add('active');
                text.textContent = 'Hide Members';
            } else {
                membersContent.style.display = 'none';
                toggleBtn.classList.remove('active');
                text.textContent = 'Show Members';
            }
        }

        // Close profile details when clicking outside
        document.addEventListener('click', function(event) {
            const profileDetails = document.getElementById('profile-details');
            const userbox = document.querySelector('.userbox');
            
            if (profileDetails && userbox && 
                !profileDetails.contains(event.target) && 
                !userbox.contains(event.target)) {
                profileDetails.classList.add('close');
            }
        });
        
        // Event thumbnail background color script
        document.addEventListener('DOMContentLoaded', function() {
            const thumbnails = document.querySelectorAll('.event-thumbnail img');
            
            thumbnails.forEach(img => {
                // Create a temporary canvas to analyze the image
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // When the image loads
                img.onload = function() {
                    // Set canvas dimensions to a small size for performance
                    canvas.width = 16;
                    canvas.height = 16;
                    
                    // Draw the image to the canvas (scaled down)
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                    
                    // Get the dominant color
                    const color = getDominantColor(canvas, ctx);
                    
                    // Apply the color to the parent thumbnail
                    img.parentElement.style.backgroundColor = `rgb(${color.r}, ${color.g}, ${color.b})`;
                };
                
                // If image is already loaded (cached)
                if (img.complete) img.onload();
            });
            
            function getDominantColor(canvas, ctx) {
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                const colorCount = {};
                let maxCount = 0;
                let dominantColor = {r: 0, g: 0, b: 0};
                
                // Sample pixels at intervals for performance
                for (let i = 0; i < data.length; i += 16) {
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    const key = `${r},${g},${b}`;
                    
                    colorCount[key] = (colorCount[key] || 0) + 1;
                    
                    if (colorCount[key] > maxCount) {
                        maxCount = colorCount[key];
                        dominantColor = {r, g, b};
                    }
                }
                
                // Adjust brightness to ensure readability
                return adjustColor(dominantColor);
            }
            
            function adjustColor(color) {
                // Convert to HSL for easier manipulation
                let [h, s, l] = rgbToHsl(color.r, color.g, color.b);
                
                // Make slightly darker and more saturated for better contrast
                s = Math.min(s * 1.2, 100);
                l = l * 0.7;
                
                // Convert back to RGB
                return hslToRgb(h, s, l);
            }
            
            // Helper functions for color conversion
            function rgbToHsl(r, g, b) {
                r /= 255, g /= 255, b /= 255;
                const max = Math.max(r, g, b), min = Math.min(r, g, b);
                let h, s, l = (max + min) / 2;

                if (max === min) {
                    h = s = 0;
                } else {
                    const d = max - min;
                    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                    switch (max) {
                        case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                        case g: h = (b - r) / d + 2; break;
                        case b: h = (r - g) / d + 4; break;
                    }
                    h /= 6;
                }

                return [h * 360, s * 100, l * 100];
            }
            
            function hslToRgb(h, s, l) {
                h /= 360;
                s /= 100;
                l /= 100;
                let r, g, b;

                if (s === 0) {
                    r = g = b = l;
                } else {
                    const hue2rgb = (p, q, t) => {
                        if (t < 0) t += 1;
                        if (t > 1) t -= 1;
                        if (t < 1/6) return p + (q - p) * 6 * t;
                        if (t < 1/2) return q;
                        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                        return p;
                    };

                    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                    const p = 2 * l - q;
                    r = hue2rgb(p, q, h + 1/3);
                    g = hue2rgb(p, q, h);
                    b = hue2rgb(p, q, h - 1/3);
                }

                return {
                    r: Math.round(r * 255),
                    g: Math.round(g * 255),
                    b: Math.round(b * 255)
                };
            }
        });
    </script>
</body>
</html>