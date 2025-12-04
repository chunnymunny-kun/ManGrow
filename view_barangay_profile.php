<?php
session_start();
include 'database.php';
include 'badge_system_db.php'; // Include badge system for leaderboard features
require_once 'getdropdown.php';

// Clear admin access on logout
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    unset($_SESSION['admin_access_key']);
    unset($_POST['admin_access_key']);
    // Clear any cached data
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
}

// Check if user is actually logged in as admin (more secure approach)
$is_admin = false;
if (
    (isset($_SESSION['admin_access_key']) && $_SESSION['admin_access_key'] === 'adminkeynadialamnghindicoderist') &&
    (isset($_SESSION['accessrole']) && $_SESSION['accessrole'] === 'Administrator')
) {
    $is_admin = true;
} elseif (isset($_POST['admin_access_key']) && $_POST['admin_access_key'] === 'adminkeynadialamnghindicoderist' && (isset($_SESSION['accessrole']) && $_SESSION['accessrole'] === 'Administrator')) {
    // Set session variable for future requests
    $_SESSION['admin_access_key'] = $_POST['admin_access_key'];
    $is_admin = true;
}

// Get profile key from URL
$profile_key = isset($_GET['profile_key']) ? $_GET['profile_key'] : '';

// Prevent caching of sensitive pages
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if(empty($profile_key)) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid profile key'
    ];
    header("Location: create_mangrove_profile.php");
    exit();
}

if(empty($profile_key)) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid profile key'
    ];
    header("Location: create_mangrove_profile.php");
    exit();
}

// Fetch profile data from database using profile_key
$sql = "SELECT * FROM barangayprofiletbl WHERE profile_key = ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("s", $profile_key);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Profile not found'
    ];
    header("Location: create_mangrove_profile.php");
    exit();
}

$profile = $result->fetch_assoc();
$stmt->close();

// Extract data for easier use in HTML
$profile_id = htmlspecialchars($profile['profile_id']);
$barangay = htmlspecialchars($profile['barangay']);
$city = htmlspecialchars($profile['city_municipality']);
$area = htmlspecialchars($profile['mangrove_area']);
$date = htmlspecialchars($profile['profile_date']);
$date_edited = htmlspecialchars($profile['date_edited']);
$species = explode(',', $profile['species_present']);
$lat = htmlspecialchars($profile['latitude']);
$lng = htmlspecialchars($profile['longitude']);
$qr_code = $profile['qr_code'];
$qr_status = $profile['qr_status'];
$photos = !empty($profile['photos']) ? explode(',', $profile['photos']) : [];

// if qr code is inactive
if (!$is_admin && $profile['qr_status'] === 'inactive') {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'This barangay profile is currently unavailable'
    ];
    header("Location: index.php");
    exit();
}

// Get user who created the profile
$account_table = $profile['account_table_type'];
if ($account_table == 'adminaccountstbl') {
    $user_sql = "SELECT admin_name AS name FROM $account_table WHERE admin_id = ?";
} else {
    $user_sql = "SELECT fullname AS name FROM $account_table WHERE account_id = ?";
}
$user_stmt = $connection->prepare($user_sql);
$user_stmt->bind_param("i", $profile['account_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$created_by = $user_result->num_rows > 0 ? $user_result->fetch_assoc()['name'] : 'Unknown';
$user_stmt->close();

// Database queries for statistics
// Fetch all non-rejected reports from both tables
$reports_sql = "SELECT 
    report_id, 'illegal' as report_type, barangays, city_municipality, action_type, NULL as mangrove_status, NULL as area_m2
    FROM illegalreportstbl 
    WHERE action_type != 'Rejected'
    UNION ALL
    SELECT 
    report_id, 'mangrove' as report_type, barangays, city_municipality, action_type, mangrove_status, area_m2
    FROM mangrovereporttbl 
    WHERE action_type != 'Rejected'";

$stmt_reports = $connection->prepare($reports_sql);
$stmt_reports->execute();
$all_reports_result = $stmt_reports->get_result();
$all_reports = $all_reports_result->fetch_all(MYSQLI_ASSOC);
$stmt_reports->close();

// Initialize badge system for leaderboard features
if (class_exists('BadgeSystem')) {
    BadgeSystem::init($connection);
}

// BARANGAY LEADERBOARD FEATURES
// Get badge statistics for users in this barangay
$barangay_badges = [];
$badge_totals = [];

// Get all users in this barangay with their badges and eco points
$users_query = "SELECT account_id, fullname, badges, badge_count, eco_points, total_eco_points 
                FROM accountstbl 
                WHERE barangay = ? AND city_municipality = ? 
                AND badges IS NOT NULL AND badges != ''";
$stmt = $connection->prepare($users_query);
$stmt->bind_param("ss", $barangay, $city);
$stmt->execute();
$users_result = $stmt->get_result();

while ($user = $users_result->fetch_assoc()) {
    if (!empty($user['badges'])) {
        $user_badges = explode(',', $user['badges']);
        foreach ($user_badges as $badge_name) {
            $badge_name = trim($badge_name);
            if (!empty($badge_name)) {
                $badge_totals[$badge_name] = ($badge_totals[$badge_name] ?? 0) + 1;
            }
        }
    }
}
$stmt->close();

// Get detailed badge information from badgestbl
$detailed_badges = [];
if (!empty($badge_totals)) {
    // Check if badgestbl table exists
    $table_check = $connection->query("SHOW TABLES LIKE 'badgestbl'");
    if ($table_check && $table_check->num_rows > 0) {
        $placeholders = str_repeat('?,', count($badge_totals) - 1) . '?';
        $badge_details_query = "SELECT badge_name, description, icon_class, color, category 
                               FROM badgestbl 
                               WHERE badge_name IN ($placeholders) AND is_active = 1 
                               ORDER BY badge_name";
        $stmt = $connection->prepare($badge_details_query);
        $stmt->bind_param(str_repeat('s', count($badge_totals)), ...array_keys($badge_totals));
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($badge = $result->fetch_assoc()) {
            $detailed_badges[] = [
                'name' => $badge['badge_name'],
                'description' => $badge['description'],
                'icon' => $badge['icon_class'] ?? 'fas fa-medal',
                'color' => $badge['color'] ?? '#3B82F6',
                'category' => $badge['category'] ?? 'General',
                'count' => $badge_totals[$badge['badge_name']]
            ];
        }
        $stmt->close();
        
        // Sort badges by count (descending)
        usort($detailed_badges, function($a, $b) {
            return $b['count'] - $a['count'];
        });
    } else {
        // Fallback: create basic badge info from badge names only
        foreach ($badge_totals as $badge_name => $count) {
            $detailed_badges[] = [
                'name' => $badge_name,
                'description' => $badge_name,
                'icon' => 'fas fa-medal',
                'color' => '#3B82F6',
                'category' => 'General',
                'count' => $count
            ];
        }
    }
}

// Get total eco points for this barangay
$eco_points_query = "SELECT 
                        SUM(eco_points) as total_current_points,
                        SUM(total_eco_points) as total_all_time_points,
                        COUNT(*) as total_users,
                        AVG(eco_points) as avg_current_points
                     FROM accountstbl 
                     WHERE barangay = ? AND city_municipality = ?";
$stmt = $connection->prepare($eco_points_query);
$stmt->bind_param("ss", $barangay, $city);
$stmt->execute();
$eco_points_result = $stmt->get_result();
$barangay_eco_data = $eco_points_result->fetch_assoc();
$stmt->close();

// Get organizations in this barangay with their stats
$barangay_organizations = [];
$organizations_table_check = $connection->query("SHOW TABLES LIKE 'organizations'");
if ($organizations_table_check && $organizations_table_check->num_rows > 0) {
    $organizations_query = "SELECT 
                               o.org_id,
                               o.name as org_name,
                               o.description as org_description,
                               o.capacity_limit,
                               o.privacy_setting,
                               COUNT(DISTINCT om.account_id) as member_count,
                               COALESCE(SUM(a.eco_points), 0) as total_current_points,
                               COALESCE(SUM(a.total_eco_points), 0) as total_all_time_points,
                               COALESCE(AVG(a.eco_points), 0) as avg_points_per_member
                            FROM organizations o
                            LEFT JOIN organization_members om ON o.org_id = om.org_id
                            LEFT JOIN accountstbl a ON om.account_id = a.account_id
                            WHERE o.barangay = ? AND o.city_municipality = ? AND o.is_active = 1
                            GROUP BY o.org_id, o.name, o.description, o.capacity_limit, o.privacy_setting
                            ORDER BY total_current_points DESC, member_count DESC";
    $stmt = $connection->prepare($organizations_query);
    $stmt->bind_param("ss", $barangay, $city);
    $stmt->execute();
    $organizations_result = $stmt->get_result();
    while ($org = $organizations_result->fetch_assoc()) {
        $barangay_organizations[] = $org;
    }
    $stmt->close();
}

// Get top users in this barangay by eco points
$top_users_query = "SELECT account_id, fullname, eco_points, total_eco_points, badge_count, profile_thumbnail
                    FROM accountstbl 
                    WHERE barangay = ? AND city_municipality = ?
                    ORDER BY eco_points DESC, total_eco_points DESC
                    LIMIT 5";
$stmt = $connection->prepare($top_users_query);
$stmt->bind_param("ss", $barangay, $city);
$stmt->execute();
$top_users_result = $stmt->get_result();
$barangay_top_users = [];
while ($user = $top_users_result->fetch_assoc()) {
    $barangay_top_users[] = $user;
}
$stmt->close();

// Get events happening in this barangay
$barangay_events = [];
$events_table_check = $connection->query("SHOW TABLES LIKE 'eventstbl'");
if ($events_table_check && $events_table_check->num_rows > 0) {
    // Set timezone and get current date
    date_default_timezone_set('Asia/Manila');
    $currentDate = date("Y-m-d H:i:s");
    
    $events_query = "SELECT 
                        e.event_id, 
                        e.subject as event_name, 
                        e.description as event_description,
                        e.start_date, 
                        e.end_date, 
                        e.venue as event_location, 
                        e.event_status, 
                        e.created_at, 
                        e.program_type, 
                        e.thumbnail,
                        e.organization,
                        a.fullname as created_by
                     FROM eventstbl e
                     JOIN accountstbl a ON e.author = a.account_id
                     WHERE e.barangay = ? AND e.city_municipality = ? 
                     AND e.is_approved = 'Approved'
                     AND ((e.program_type = 'Announcement') OR (e.end_date >= ?))
                     ORDER BY e.start_date ASC, e.created_at DESC
                     LIMIT 12";
    $stmt = $connection->prepare($events_query);
    $stmt->bind_param("sss", $barangay, $city, $currentDate);
    $stmt->execute();
    $events_result = $stmt->get_result();
    while ($event = $events_result->fetch_assoc()) {
        // Determine event status
        $startDateTime = $event['start_date'];
        $endDateTime = $event['end_date'];
        if ($currentDate < $startDateTime) {
            $event['status'] = "upcoming";
            $event['status_label'] = "Upcoming";
        } elseif ($currentDate > $endDateTime) {
            $event['status'] = "completed";
            $event['status_label'] = "Completed";
        } else {
            $event['status'] = "ongoing";
            $event['status_label'] = "Ongoing";
        }
        $barangay_events[] = $event;
    }
    $stmt->close();
}

$connection->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Mangrove Profile</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="view_barangay_profile.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>
</head>
<body>
    <header class="mangrove-header">
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
    <aside id="sidebar" class="mangrove-sidebar close">  
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button id="toggle-btn" class="rotate">
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
                <button class="dropdown-btn" tabindex="-1">
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
        <?php 
            $statusType = '';
            $statusMsg = '';
            if(!empty($_SESSION['response'])): ?>
            <div class="flash-container">
                <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
                    <?= $_SESSION['response']['msg'] ?>
                </div>
            </div>
            <?php 
            unset($_SESSION['response']); 
            endif; 
            ?>
        <div class="profile-details close" id="profile-details">
            <div class ="details-box">
            <h2><?php 
            if(isset($_SESSION["name"])){
                $loggeduser = $_SESSION["name"];
                echo $loggeduser; 
            }else{
                echo "";
            }
            ?></h2>
            <p><?php
            if(isset($_SESSION["email"])){
                $email = $_SESSION["email"];
                echo $email;
            }else{
                echo "";
            }
            ?></p>
            <p><?php
            if(isset($_SESSION["accessrole"])){
                $accessrole = $_SESSION["accessrole"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            <p><?php
            if(isset($_SESSION["organization"])){
                $accessrole = $_SESSION["organization"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            <?php 
            if ($is_admin) {
            ?>
                <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php 
            } else {
            ?>
                <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php 
            }
            ?>
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
        </div>
        
        <div class="form-container">
            <?php 
                if ($is_admin) {
            ?>
            <a href="adminprofile.php" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon-back" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Admin Profile
            </a>
            <h2 class="page-title">Mangrove Profile: <?php echo htmlspecialchars($barangay); ?></h2>
            <?php } ?>
            <div class="main-grid">
                <!-- Left Column: Profile Details -->
                <section class="section-left">
                    <!-- Location Details -->
                    <div class="section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Location Details
                        </h3>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">City/Municipality</label>
                                <p class="profile-data"><?php echo htmlspecialchars($city); ?></p>
                            </div>
                            <div>
                                <label class="form-label">Barangay</label>
                                <p class="profile-data"><?php echo htmlspecialchars($barangay); ?></p>
                            </div>
                        </div>
                        <div class="map-section">
                            <label class="form-label">Location on Map</label>
                            <div class="map" id="locationMap" style="height: 300px;"></div>
                            <div class="form-grid map-coords">
                                <div>
                                    <label class="form-label">Latitude</label>
                                    <p class="profile-data"><?php echo htmlspecialchars($lat); ?></p>
                                </div>
                                <div>
                                    <label class="form-label">Longitude</label>
                                    <p class="profile-data"><?php echo htmlspecialchars($lng); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mangrove Profile Information -->
                    <div class="section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.127l-3.328 3.328m0 0l-3.328-3.328m3.328 3.328v9.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Mangrove Profile Information
                        </h3>
                        <div class="form-grid">
                            <div>
                                <label class="form-label">Total Mangrove Area (ha) <small style="color: gray;">as of <?php echo !empty($date_edited) ? date('F j, Y', strtotime($date_edited)) : 'N/A'; ?></small></label>
                                <p class="profile-data"><?php echo htmlspecialchars($area); ?> ha</p>
                            </div>
                            <div>
                                <label class="form-label">Date Created</label>
                                <p class="profile-data"><?php echo htmlspecialchars($date); ?></p>
                            </div>
                        </div>

                        <div class="species-section">
                            <label class="form-label">Mangrove Species Present</label>
                            <div class="species-list">
                                <?php foreach ($species as $specie): ?>
                                    <span class="species-tag"><?php echo htmlspecialchars($specie); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistical Summary Section -->
                    <div class="section">
                        <h3 class="section-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Statistical Summary
                        </h3>
                        
                        <!-- Data Quality Indicator -->
                        <div class="data-quality-indicator" id="data-quality-indicator">
                            <div class="data-quality-header">
                                <i class="fas fa-info-circle"></i>
                                <h4>Data Confidence Level</h4>
                                <span class="confidence-badge" id="confidence-badge">Low</span>
                            </div>
                            <div class="progress-container">
                                <div class="progress-label">
                                    <span>Monitoring Coverage</span>
                                    <span id="coverage-percent">0%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="coverage-bar" style="width: 0%"></div>
                                </div>
                                <div class="progress-description">
                                    <p id="coverage-description">Only <span id="monitored-area">0</span> m² of <span id="total-area"><?php echo $area * 10000; ?></span> m² total area has been monitored</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a2 2 0 012 2v6a2 2 0 01-2 2h-2a2 2 0 01-2-2v-6a2 2 0 012-2h2z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 01-2 2h-2a2 2 0 01-2-2V5z" />
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <h4 class="stat-value" id="total-reports">0</h4>
                                    <p class="stat-label">Total Reports</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <h4 class="stat-value" id="healthy-percentage">0%</h4>
                                    <p class="stat-label">Healthy Mangroves</p>
                                    <small class="stat-note" id="health-note">Based on monitored areas</small>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <h4 class="stat-value" id="illegal-reports">0</h4>
                                    <p class="stat-label">Issues Reported</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <div class="stat-content">
                                    <h4 class="stat-value" id="resolved-issues">0</h4>
                                    <p class="stat-label">Resolved Issues</p>
                                </div>
                            </div>
                        </div>
                        <!-- Calculation Transparency Section -->
                        <div class="extended-stats">
                            <button class="dropdown-toggle transparency-toggle" onclick="toggleTransparencySection()">
                                <h4>Calculation Transparency</h4>
                                <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                </svg>
                            </button>
                            <div id="calculation-transparency" class="transparency-content collapsed">
                                <p>Loading calculation details...</p>
                            </div>
                        </div>
                        <!-- Extended Statistics Section -->
                        <div class="extended-stats">
                            <h4>Detailed Metrics</h4>
                            <div class="stats-details">
                                <div class="stat-detail">
                                    <span class="detail-label">Total Monitored Area:</span>
                                    <span class="detail-value" id="total-monitored-area">0 m²</span>
                                </div>
                                <div class="stat-detail">
                                    <span class="detail-label">Average Report Area:</span>
                                    <span class="detail-value" id="avg-report-area">0 m²</span>
                                </div>
                                <div class="stat-detail">
                                    <span class="detail-label">Estimated Total Mangroves:</span>
                                    <span class="detail-value" id="estimated-mangroves">0</span>
                                    <small class="detail-note">Based on average density</small>
                                </div>
                            </div>
                        </div>

                        <!-- Weight Distribution Visualization -->
                        <div class="extended-stats">
                            <h4>Data Weight Distribution</h4>
                            <div class="stats-details">
                                <div class="stat-detail">
                                    <span class="detail-label">Report Influence:</span>
                                    <div class="weight-visualization">
                                        <div class="weight-bar">
                                            <div class="weight-distribution" id="weight-distribution"></div>
                                        </div>
                                        <div class="weight-label" id="weight-label">No data available</div>
                                    </div>
                                    <small class="detail-note">Larger areas have greater influence on statistics</small>
                                </div>
                            </div>
                        </div>

                        <!-- Forest Metrics Section -->
                        <div class="extended-stats forest-metrics-section" id="forest-metrics-section" style="display: none;">
                            <h4>
                                <span><i class="fas fa-tree"></i> Forest Metrics</span>
                                <span class="metrics-badge" id="metrics-confidence-badge">Based on field measurements</span>
                            </h4>
                            
                            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
                                <!-- Monitoring Coverage -->
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, #e3f2fd 0%, #90caf9 100%);">
                                        <i class="fas fa-search" style="color: #1976d2;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h5 class="metric-value" id="forest-monitoring-coverage">0%</h5>
                                        <p class="metric-label">Monitoring Coverage</p>
                                        <small class="metric-note" id="coverage-area-note">0 m² surveyed</small>
                                    </div>
                                </div>
                                
                                <!-- Average Forest Cover -->
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, #c8e6c9 0%, #66bb6a 100%);">
                                        <i class="fas fa-tree" style="color: #2e7d32;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h5 class="metric-value" id="avg-forest-cover">--</h5>
                                        <p class="metric-label">Avg Forest Cover</p>
                                        <div class="progress-bar" style="height: 6px; background: #e0e0e0; border-radius: 3px; margin-top: 8px; overflow: hidden;">
                                            <div class="progress-fill" id="forest-cover-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #4CAF50, #66BB6A);"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Average Canopy Density -->
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, #b3e5fc 0%, #4fc3f7 100%);">
                                        <i class="fas fa-cloud-sun" style="color: #0277bd;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h5 class="metric-value" id="avg-canopy-density">--</h5>
                                        <p class="metric-label">Avg Canopy Density</p>
                                        <div class="progress-bar" style="height: 6px; background: #e0e0e0; border-radius: 3px; margin-top: 8px; overflow: hidden;">
                                            <div class="progress-fill" id="canopy-density-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2196F3, #42A5F5);"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Average Tree Density -->
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, #fff8e1 0%, #ffcc80 100%);">
                                        <i class="fas fa-chart-line" style="color: #e65100;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h5 class="metric-value" id="avg-tree-density">--</h5>
                                        <p class="metric-label">Avg Tree Density</p>
                                        <small class="metric-note">trees per hectare</small>
                                    </div>
                                </div>
                                
                                <!-- Estimated Total Trees -->
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, #f3e5f5 0%, #ba68c8 100%);">
                                        <i class="fas fa-calculator" style="color: #7b1fa2;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <h5 class="metric-value" id="estimated-total-trees">--</h5>
                                        <p class="metric-label">Est. Total Trees</p>
                                        <small class="metric-note">extrapolated to <?php echo $area; ?> ha</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="forest-metrics-note" style="margin-top: 16px; padding: 12px; background: #f5f5f5; border-left: 4px solid #4CAF50; border-radius: 4px;">
                                <p style="margin: 0; font-size: 0.9em; color: #555;">
                                    <i class="fas fa-info-circle"></i> <strong>How these metrics work:</strong> 
                                    Reporters measure forest cover and canopy density in small sample plots. 
                                    We use weighted averages (larger plots have more influence) to extrapolate to the entire barangay area. 
                                    More reports = higher accuracy!
                                </p>
                            </div>
                            
                            <!-- Forest Metrics Calculation Transparency -->
                            <div style="margin-top: 16px;">
                                <button class="dropdown-toggle transparency-toggle forest-transparency-toggle" onclick="toggleForestTransparencySection()">
                                    <h4 style="margin: 0; font-size: 1em;">Calculation Transparency</h4>
                                    <svg class="dropdown-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                    </svg>
                                </button>
                                <div id="forest-calculation-transparency" class="transparency-content collapsed">
                                    <p>Loading calculation details...</p>
                                </div>
                            </div>
                        </div>
                </section>
                <?php 
                    if ($is_admin) {
                ?>
                <!-- Right Column: QR Code and Actions -->
                <aside class="section-right" display="">
                    <!-- Profile Status -->
                    <div class="status-section">
                        <h3 class="status-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Profile Status
                        </h3>
                        <div class="status-badge status-published">
                            Published
                        </div>
                        <p class="status-info">This profile is publicly accessible via the QR code.</p>
                    </div>

                    <!-- QR Code Section -->
                    <div class="qr-section">
                        <h3 class="qr-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 5V2m12 3V2m-6 3V2M3 8v.01M21 8v.01M3 12v.01M21 12v.01M3 16v.01M21 16v.01M3 20v.01M21 20v.01M8 20h8a2 2 0 002-2V6a2 2 0 00-2-2H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            QR Code
                        </h3>
                        <div class="qr-container">
                            <canvas id="qrcode" class="qr-canvas"></canvas>
                            <a id="downloadQr" href="#" download="mangrove_profile_<?php echo htmlspecialchars($barangay); ?>_<?php echo htmlspecialchars($city); ?>_qr.png" class="download-qr">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon-download" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                                Download QR
                            </a>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button id="editButton" class="btn btn-primary" onclick="window.location.href='edit_mangrove_profile.php?profile_id=<?php echo $profile_id; ?>'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-edit" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit Profile
                        </button>
                        <?php
                            $isAdmin = isset($_SESSION['accessrole']) && $_SESSION['accessrole'] === 'Administrator';
                            $isBarangayOfficial = isset($_SESSION['accessrole']) && $_SESSION['accessrole'] === 'Barangay Official';
                            $userBarangay = isset($_SESSION['barangay']) ? $_SESSION['barangay'] : '';
                            $userCity = isset($_SESSION['city_municipality']) ? $_SESSION['city_municipality'] : '';
                            $disableBtn = $isBarangayOfficial ? 'disabled' : '';
                        ?>
                        <?php if ($isBarangayOfficial): ?>
                            <small class="text-muted d-block mt-1" style="font-size: 0.95em;">
                                Only Administrators can regenerate the QR code.
                            </small>
                        <?php endif; ?>
                        <button id="deleteButton" class="btn btn-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-delete" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete Profile
                        </button>
                    </div>
                </aside>
                <?php } else{ ?>
                    <!-- Profile Summary -->
                    <div class="summary-section">
                    <h3 class="summary-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Profile Summary
                    </h3>
                    <p class="summary-item"><strong>Barangay:</strong> <span id="summary-barangay"><?php echo $barangay; ?></span></p>
                    <p class="summary-item"><strong>City/Municipality:</strong> <span id="summary-city"><?php echo $city; ?></span></p>
                    <p class="summary-item"><strong>Total Area:</strong> <span id="summary-area"><?php echo $area; ?></span> ha</p>
                    <p class="summary-item"><strong>Date Created:</strong> <span id="summary-date"><?php echo $date; ?></span>
                    <?php if (!empty($date_edited)): ?>
                        <br><small>Last updated: <?php echo $date_edited; ?></small>
                    <?php endif; ?>
                    </p>
                    <p class="summary-item"><strong>Species Present:</strong> <span id="summary-species"><?php echo implode(', ', $species); ?></span></p>
                    </div>
                <?php }?>
            </div>
            <div class="bottom-grid">
                <!-- Photos/Documentation -->
                <div class="section gallery-section">
                    <h3 class="section-title">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        Photos/Documentation
                    </h3>
                    <?php if(!empty($photos)): ?>
                        <div class="photo-gallery">
                            <?php foreach($photos as $photo_path): ?>
                                <div class="photo-item">
                                    <img src="<?php echo $photo_path; ?>" alt="Mangrove Photo">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="photo-placeholder">
                            <svg class="photo-icon" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L36 32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p>No photos uploaded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Barangay Leaderboard Section -->
            <div class="leaderboard-section">
                <h2 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <?php echo htmlspecialchars($barangay); ?>, <?php echo htmlspecialchars($city); ?> - Community Leaderboard
                </h2>
                
                <div class="leaderboard-grid">
                    <!-- First Row: 3 columns -->
                    <!-- Eco Points Summary -->
                    <div class="leaderboard-card">
                        <h3 class="card-title">
                            <i class="fas fa-leaf"></i>
                            Eco Points Summary
                        </h3>
                        <div class="eco-stats">
                            <div class="stat-item">
                                <span class="stat-label">Total Community Points</span>
                                <span class="stat-value"><?php echo number_format($barangay_eco_data['total_current_points'] ?? 0); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">All-Time Points</span>
                                <span class="stat-value"><?php echo number_format($barangay_eco_data['total_all_time_points'] ?? 0); ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Active Users</span>
                                <span class="stat-value"><?php echo $barangay_eco_data['total_users'] ?? 0; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Average Points per User</span>
                                <span class="stat-value"><?php echo number_format($barangay_eco_data['avg_current_points'] ?? 0, 1); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Badge Statistics -->
                    <div class="leaderboard-card">
                        <h3 class="card-title">
                            <i class="fas fa-medal"></i>
                            Community Badges (<?php echo count($detailed_badges); ?> types)
                        </h3>
                        <div class="badges-list">
                            <?php if (!empty($detailed_badges)): ?>
                                <?php foreach (array_slice($detailed_badges, 0, 8) as $badge): ?>
                                    <div class="badge-item" style="border-left: 4px solid <?php echo htmlspecialchars($badge['color']); ?>">
                                        <div class="badge-info">
                                            <i class="<?php echo htmlspecialchars($badge['icon']); ?>" style="color: <?php echo htmlspecialchars($badge['color']); ?>"></i>
                                            <span class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></span>
                                        </div>
                                        <span class="badge-count"><?php echo $badge['count']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($detailed_badges) > 8): ?>
                                    <p class="more-badges">... and <?php echo count($detailed_badges) - 8; ?> more badge types</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="no-data">No badges earned yet in this community</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Users -->
                    <div class="leaderboard-card">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            Top 5 Contributors
                        </h3>
                        <div class="users-list">
                            <?php if (!empty($barangay_top_users)): ?>
                                <?php foreach (array_slice($barangay_top_users, 0, 5) as $index => $user): ?>
                                    <div class="user-item">
                                        <div class="user-rank">#<?php echo $index + 1; ?></div>
                                        <div class="user-info">
                                            <?php if (!empty($user['profile_thumbnail'])): ?>
                                                <img src="<?php echo htmlspecialchars($user['profile_thumbnail']); ?>" alt="Profile" class="user-avatar">
                                            <?php else: ?>
                                                <div class="user-avatar-placeholder">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="user-details">
                                                <span class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                                <span class="user-stats"><?php echo $user['eco_points']; ?> pts • <?php echo $user['badge_count']; ?> badges</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data">No active users in this community yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Second Row: Events (Full Width) -->
                    <div class="leaderboard-card full-width">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i>
                            Events in <?php echo htmlspecialchars($barangay); ?> (<?php echo count($barangay_events); ?>)
                        </h3>
                        <div class="events-grid">
                            <?php if (!empty($barangay_events)): ?>
                                <?php foreach (array_slice($barangay_events, 0, 8) as $event): ?>
                                    <div class="event-item">
                                        <div class="event-header">
                                            <h4 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                            <div class="event-badges">
                                                <span class="event-type-badge <?php echo strtolower($event['program_type']); ?>">
                                                    <?php echo htmlspecialchars($event['program_type']); ?>
                                                </span>
                                                <span class="event-status-badge <?php echo $event['status']; ?>">
                                                    <?php echo $event['status_label']; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="event-details">
                                            <div class="event-detail">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($event['event_location']); ?></span>
                                            </div>
                                            <div class="event-detail">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('M j, Y', strtotime($event['start_date'])); ?></span>
                                            </div>
                                            <?php if (!empty($event['organization'])): ?>
                                                <div class="event-detail">
                                                    <i class="fas fa-users"></i>
                                                    <span><?php echo htmlspecialchars($event['organization']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="event-detail">
                                                <i class="fas fa-user"></i>
                                                <span>by <?php echo htmlspecialchars($event['created_by']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($barangay_events) > 8): ?>
                                    <div class="more-events">
                                        <span>... and <?php echo count($barangay_events) - 8; ?> more events</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="no-data">No ongoing or upcoming events found in this barangay</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Third Row: Organizations (Full Width) -->
                    <div class="leaderboard-card full-width">
                        <h3 class="card-title">
                            <i class="fas fa-building"></i>
                            Organizations in <?php echo htmlspecialchars($barangay); ?> (<?php echo count($barangay_organizations); ?>)
                        </h3>
                        <div class="organizations-list">
                            <?php if (!empty($barangay_organizations)): ?>
                                <?php foreach ($barangay_organizations as $org): ?>
                                    <div class="organization-item">
                                        <div class="org-header">
                                            <h4 class="org-name">
                                                <a href="organization_profile.php?org_id=<?php echo $org['org_id']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($org['org_name']); ?>
                                                </a>
                                                <span class="privacy-badge <?php echo $org['privacy_setting']; ?>">
                                                    <?php echo ucfirst($org['privacy_setting']); ?>
                                                </span>
                                            </h4>
                                            <div class="org-stats">
                                                <span class="org-stat">
                                                    <i class="fas fa-users"></i>
                                                    <?php echo $org['member_count']; ?> members
                                                </span>
                                                <span class="org-stat">
                                                    <i class="fas fa-leaf"></i>
                                                    <?php echo number_format($org['total_current_points']); ?> eco pts
                                                </span>
                                                <span class="org-stat">
                                                    <i class="fas fa-chart-line"></i>
                                                    <?php echo number_format($org['avg_points_per_member'], 1); ?> avg/member
                                                </span>
                                            </div>
                                        </div>
                                        <?php if (!empty($org['org_description'])): ?>
                                            <p class="org-description"><?php echo htmlspecialchars($org['org_description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-data">No organizations found in this barangay</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
        let map, marker;
        
        function initMap() {
            // Use coordinates from static data
            const lat = <?php echo !empty($lat) ? $lat : '14.7321'; ?>;
            const lng = <?php echo !empty($lng) ? $lng : '120.5350'; ?>;
            
            // Initialize map
            map = L.map('locationMap').setView([lat, lng], 13);
            
            // Add tile layer (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Set z-index of zoom controls
            setTimeout(function() {
                const zoomControl = document.querySelector('.leaflet-control-zoom');
                if (zoomControl) {
                    zoomControl.style.zIndex = '1';
                }
            }, 100);

            // Add marker at the specified location
            marker = L.marker([lat, lng], {
                draggable: false
            }).addTo(map)
            .bindPopup('<?php echo htmlspecialchars($barangay); ?>');
        }

        // Function to save QR code to database
        function saveQrCodeToDatabase(qrDataURL, profileId = null) {
            const profileIdToUse = profileId || <?php echo $profile_id; ?>;
            
            fetch('save_qr_code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `profile_id=${profileIdToUse}&qr_code_data=${encodeURIComponent(qrDataURL)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('QR code saved to database successfully');
                } else {
                    console.error('Failed to save QR code to database:', data.message);
                }
            })
            .catch(error => {
                console.error('Error saving QR code to database:', error);
            });
        }

        // Generate QR code function
        function generateQrCode() {
            const qrCanvas = document.getElementById('qrcode');
            const downloadLink = document.getElementById('downloadQr');
            
            // Clear the canvas first
            const ctx = qrCanvas.getContext('2d');
            ctx.clearRect(0, 0, qrCanvas.width, qrCanvas.height);
            
            // Generate QR code URL
            const profileUrl = `http://<?php echo $_SERVER['HTTP_HOST']; ?>/view_barangay_profile.php?profile_key=<?php echo $profile['profile_key']; ?>`;
            
            // Generate QR code using QRious
            const qr = new QRious({
                element: qrCanvas,
                value: profileUrl,
                size: 250,
                level: 'H'
            });
            
            // Set download link
            const qrDataURL = qrCanvas.toDataURL('image/png');
            downloadLink.href = qrDataURL;
            
            // Save to database
            saveQrCodeToDatabase(qrDataURL);
            
            console.log('QR code generated successfully');
        }

        // Load QR code from database or generate new one
        function loadQrCode() {
            const qrCanvas = document.getElementById('qrcode');
            const downloadLink = document.getElementById('downloadQr');
            
            // Check if QR elements exist (only for admin users)
            if (!qrCanvas || !downloadLink) {
                console.log('QR code elements not found - user is not admin');
                return;
            }
            
            // Always generate QR code from the profile URL for consistency
            const profileUrl = `http://<?php echo $_SERVER['HTTP_HOST']; ?>/view_barangay_profile.php?profile_key=<?php echo $profile['profile_key']; ?>`;
            const qr = new QRious({
                element: qrCanvas,
                value: profileUrl,
                size: 250,
                level: 'H'
            });
            downloadLink.href = qrCanvas.toDataURL('image/png');
            
            console.log('QR code generated for:', profileUrl);
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', async function() {
            try {
                initMap();
            } catch (error) {
                console.error('Error initializing map:', error);
            }
            
            try {
                initLightbox();
            } catch (error) {
                console.error('Error initializing lightbox:', error);
            }
            
            try {
                loadQrCode(); // Load the QR code (admin only)
            } catch (error) {
                console.error('Error loading QR code:', error);
            }
            
            try {
                await calculateStatistics();
            } catch (error) {
                console.error('Error calculating statistics:', error);
            }
            
            const deleteButton = document.getElementById('deleteButton');
            if (deleteButton) {
                deleteButton.addEventListener('click', function() {
                    openDeleteModal();
                });
            }

            // Delete modal functions
            function openDeleteModal() {
                document.getElementById('deleteModal').style.display = 'block';
                document.getElementById('confirmProfileKey').value = '';
                document.getElementById('keyMismatchError').style.display = 'none';
            }

            function closeDeleteModal() {
                document.getElementById('deleteModal').style.display = 'none';
            }

            // Confirm delete button event
            document.getElementById('confirmDeleteButton').addEventListener('click', function() {
                const enteredKey = document.getElementById('confirmProfileKey').value;
                const actualKey = '<?php echo $profile_key; ?>';
                
                if (enteredKey === actualKey) {
                    // Keys match, proceed with deletion
                    const formData = new FormData();
                    formData.append('profile_key', actualKey);
                    
                    fetch('delete_barangay_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.href = 'adminprofile.php';
                        } else {
                            alert('Error: ' + data.message);
                            closeDeleteModal();
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while archiving the profile.');
                        closeDeleteModal();
                    });
                } else {
                    // Keys don't match, show error
                    document.getElementById('keyMismatchError').style.display = 'block';
                }
            });

            // Close modal when clicking outside of it
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('deleteModal');
                if (event.target === modal) {
                    closeDeleteModal();
                }
            });
        });

        function initLightbox() {
            // Create lightbox element
            const lightbox = document.createElement('div');
            lightbox.className = 'lightbox';
            lightbox.innerHTML = `
                <button class="lightbox-close">&times;</button>
                <img src="" alt="">
            `;
            document.body.appendChild(lightbox);
            
            const lightboxImg = lightbox.querySelector('img');
            const lightboxClose = lightbox.querySelector('.lightbox-close');
            
            // Add click event to all photos
            document.querySelectorAll('.photo-item img').forEach(img => {
                img.addEventListener('click', () => {
                    lightbox.style.display = 'flex';
                    lightboxImg.src = img.src;
                    lightboxImg.alt = img.alt;
                });
            });
            
            // Close lightbox
            lightboxClose.addEventListener('click', () => {
                lightbox.style.display = 'none';
            });
            
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) {
                    lightbox.style.display = 'none';
                }
            });
            
            // Close with ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && lightbox.style.display === 'flex') {
                    lightbox.style.display = 'none';
                }
            });
        }

        // Pass PHP data to JavaScript
        const profileData = {
            barangay: "<?php echo addslashes($barangay); ?>",
            city: "<?php echo addslashes($city); ?>"
        };

        const allReports = <?php echo json_encode($all_reports); ?>;

        // Function to check if a report matches the profile conditions
        function matchesProfile(report) {
            // Check if city matches exactly
            if (report.city_municipality !== profileData.city) {
                return false;
            }
            
            // Check if barangay is in the barangays list (comma-separated)
            const barangaysList = report.barangays.split(',').map(b => b.trim());
            return barangaysList.includes(profileData.barangay);
        }

        // Toggle transparency section visibility
        function toggleTransparencySection() {
            const transparencySection = document.getElementById('calculation-transparency');
            const dropdownIcon = document.querySelector('.transparency-toggle .dropdown-icon');
            
            if (transparencySection.classList.contains('collapsed')) {
                transparencySection.classList.remove('collapsed');
                transparencySection.classList.add('expanded');
                dropdownIcon.style.transform = 'rotate(180deg)';
            } else {
                transparencySection.classList.remove('expanded');
                transparencySection.classList.add('collapsed');
                dropdownIcon.style.transform = 'rotate(0deg)';
            }
        }

        // Calculate statistics
        async function calculateStatistics() {
            console.log('Starting calculateStatistics...');
            console.log('Total reports in allReports array:', allReports.length);
            
            let totalReports = 0;
            let illegalReports = 0;
            let resolvedIssues = 0;
            
            let totalMangroveArea = 0;
            let mangroveReportsCount = 0;
            let mangroveDensitySum = 0;
            let mangroveDensityCount = 0;
            
            // Arrays to store weighted data
            let healthStatusData = [];
            let mangroveDensityData = [];

            allReports.forEach(report => {
                if (matchesProfile(report)) {
                    totalReports++;
                    
                    // Count illegal reports
                    if (report.report_type === 'illegal') {
                        illegalReports++;
                    }
                    
                    // Count resolved issues
                    if (report.action_type === 'Resolved') {
                        resolvedIssues++;
                    }
                    
                    // Calculate mangrove areas (only for mangrove reports)
                    if (report.report_type === 'mangrove' && report.area_m2) {
                        const area = parseFloat(report.area_m2);
                        totalMangroveArea += area;
                        mangroveReportsCount++;
                        
                        // Store health status with weight (area)
                        if (report.mangrove_status) {
                            let healthValue = 0;
                            
                            // Assign numerical values to health status for weighted calculation
                            switch(report.mangrove_status) {
                                case 'Healthy':
                                    healthValue = 1.0;
                                    break;
                                case 'Growing':
                                    healthValue = 0.8;
                                    break;
                                case 'Needs Attention':
                                    healthValue = 0.4;
                                    break;
                                case 'Damaged':
                                    healthValue = 0.2;
                                    break;
                                case 'Dead':
                                    healthValue = 0.0;
                                    break;
                                default:
                                    healthValue = 0.5; // Default for unknown status
                            }
                            
                            healthStatusData.push({
                                value: healthValue,
                                weight: area
                            });
                        }
                        
                        // For density calculation (if available in future reports)
                        if (report.mangrove_density) {
                            const density = parseFloat(report.mangrove_density);
                            mangroveDensityData.push({
                                value: density,
                                weight: area
                            });
                            mangroveDensitySum += density * area; // Weighted sum
                            mangroveDensityCount += area; // Total weight
                        }
                    }
                }
            });

            // Calculate weighted health percentage
            let weightedHealthPercentage = 0;
            if (healthStatusData.length > 0) {
                let totalWeight = 0;
                let weightedSum = 0;
                
                healthStatusData.forEach(item => {
                    weightedSum += item.value * item.weight;
                    totalWeight += item.weight;
                });
                
                weightedHealthPercentage = (weightedSum / totalWeight) * 100;
            }
            
            // Get live area calculation from JSON data
            let totalAreaM2 = <?php echo $area * 10000; ?>; // Fallback to database value
            try {
                const areaResponse = await fetch('calculate_barangay_area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        city: '<?php echo addslashes($city); ?>',
                        barangay: '<?php echo addslashes($barangay); ?>'
                    })
                });
                
                if (areaResponse.ok) {
                    const areaResult = await areaResponse.json();
                    if (areaResult.success) {
                        // Use distributed area for consistency with create/edit pages
                        const calculatedAreaHa = areaResult.distributed_total_hectares;
                        totalAreaM2 = calculatedAreaHa * 10000; // Convert ha to m²
                        console.log(`Using live calculated area: Raw=${areaResult.raw_total_hectares} ha, Distributed=${calculatedAreaHa} ha (${areaResult.areas_found} areas found)`);
                        
                        // Check if there's a discrepancy with database value
                        const dbAreaHa = <?php echo $area; ?>;
                        if (Math.abs(calculatedAreaHa - dbAreaHa) > 0.01) {
                            console.warn(`Area discrepancy detected: DB=${dbAreaHa} ha, Calculated=${calculatedAreaHa} ha`);
                            
                            // Show a subtle notification to user about the discrepancy
                            const areaInfo = document.querySelector('.area-discrepancy-info');
                            if (areaInfo) {
                                areaInfo.remove();
                            }
                            
                            const notification = document.createElement('div');
                            notification.className = 'alert alert-info area-discrepancy-info';
                            notification.style.fontSize = '0.9em';
                            notification.style.marginTop = '10px';
                            notification.innerHTML = `
                                <i class="fas fa-info-circle"></i> 
                                <strong>Area Update Available:</strong> 
                                Live calculation shows ${calculatedAreaHa} ha 
                                (Database: ${dbAreaHa} ha). 
                                <small>The profile may need to be updated to reflect current mapped areas. Values shown are distributed areas (accounting for shared barangays).</small>
                            `;
                            
                            // Insert after the area display
                            const areaContainer = document.querySelector('.profile-header');
                            if (areaContainer) {
                                areaContainer.appendChild(notification);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Failed to fetch live area calculation:', error);
                // Continue with database value
            }
            
            // Calculate monitoring coverage
            let coveragePercent = 0;
            if (totalAreaM2 > 0) {
                coveragePercent = (totalMangroveArea / totalAreaM2) * 100;
            }
            
            // Calculate average report area
            const avgReportArea = mangroveReportsCount > 0 ? totalMangroveArea / mangroveReportsCount : 0;
            
            // Calculate weighted average density
            let weightedAvgDensity = 0;
            if (mangroveDensityCount > 0) {
                weightedAvgDensity = mangroveDensitySum / mangroveDensityCount;
            }
            
            // Calculate estimated total mangroves using weighted density
            let estimatedMangroves = 0;
            if (weightedAvgDensity > 0) {
                estimatedMangroves = weightedAvgDensity * totalAreaM2 / 10000; // Density per 100m² to total
            }
            
            // Update data quality indicator
            try {
                updateDataQualityIndicator(
                    coveragePercent.toFixed(1), 
                    totalMangroveArea, 
                    totalAreaM2
                );
            } catch (error) {
                console.error('Error updating data quality indicator:', error);
            }

            // Update the DOM with safe element access
            const updateElement = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                } else {
                    console.warn(`Element with id '${id}' not found`);
                }
            };

            updateElement('total-reports', totalReports);
            updateElement('healthy-percentage', weightedHealthPercentage.toFixed(1) + '%');
            updateElement('illegal-reports', illegalReports);
            updateElement('resolved-issues', resolvedIssues);
            
            // Update extended statistics
            updateElement('total-monitored-area', totalMangroveArea.toLocaleString(undefined, {maximumFractionDigits: 1}) + ' m²');
            updateElement('avg-report-area', avgReportArea.toLocaleString(undefined, {maximumFractionDigits: 1}) + ' m²');
            
            // Note: estimated-mangroves will be updated by loadForestMetrics() to sync with Est. Total Trees
            if (estimatedMangroves > 0) {
                updateElement('estimated-mangroves', estimatedMangroves.toLocaleString(undefined, {maximumFractionDigits: 0}));
            } else {
                updateElement('estimated-mangroves', 'N/A');
            }
            
            // Add information about weighting
            try {
                updateWeightingInformation(healthStatusData);
            } catch (error) {
                console.error('Error updating weighting information:', error);
            }

            //display calculation transparency
            try {
                showCalculationTransparency(healthStatusData, weightedHealthPercentage, coveragePercent);
            } catch (error) {
                console.error('Error showing calculation transparency:', error);
            }
            
            console.log('calculateStatistics completed successfully');
            console.log('Statistics:', { totalReports, illegalReports, resolvedIssues, weightedHealthPercentage, coveragePercent });
        }

        function showCalculationTransparency(healthData, healthPercentage, coveragePercent) {
            const transparencySection = document.getElementById('calculation-transparency');
            if (!transparencySection) return;
            
            let html = `
                <div class="transparency-explanation">
                    <p>Our calculation uses an <strong>area-weighted approach</strong> to ensure larger monitoring areas have greater influence on the overall health assessment.</p>
                    
                    <div class="calculation-steps">
                        <h5>Calculation Method:</h5>
                        <ol>
                            <li>Each mangrove report is assigned a health score based on its status:
                                <ul>
                                    <li>Healthy: 100%</li>
                                    <li>Growing: 80%</li>
                                    <li>Needs Attention: 40%</li>
                                    <li>Damaged: 20%</li>
                                    <li>Dead: 0%</li>
                                </ul>
                            </li>
                            <li>Each score is multiplied by the area (m²) of that report</li>
                            <li>All weighted scores are summed together</li>
                            <li>This sum is divided by the total monitored area</li>
                            <li>The result is multiplied by 100 to get a percentage</li>
                        </ol>
                    </div>
            `;
            
            if (healthData.length > 0) {
                // Calculate the components for display
                let totalWeight = healthData.reduce((sum, item) => sum + item.weight, 0);
                let weightedSum = healthData.reduce((sum, item) => sum + (item.value * item.weight), 0);
                
                html += `
                    <div class="calculation-details">
                        <h5>Current Calculation Details:</h5>
                        <table>
                            <tr>
                                <td>Total monitored area:</td>
                                <td><strong>${totalWeight.toLocaleString()} m²</strong></td>
                            </tr>
                            <tr>
                                <td>Weighted health sum:</td>
                                <td><strong>${weightedSum.toLocaleString(undefined, {maximumFractionDigits: 1})}</strong></td>
                            </tr>
                            <tr>
                                <td>Calculation:</td>
                                <td><strong>(${weightedSum.toLocaleString(undefined, {maximumFractionDigits: 1})} / ${totalWeight.toLocaleString()}) × 100</strong></td>
                            </tr>
                            <tr>
                                <td>Result:</td>
                                <td><strong>${healthPercentage.toFixed(1)}%</strong></td>
                            </tr>
                        </table>
                    </div>
                `;
            }
            
            html += `
                    <div class="coverage-note">
                        <p><strong>Note:</strong> This calculation is based on ${coveragePercent.toFixed(1)}% of the total mangrove area being monitored. 
                        Areas without recent monitoring data are not included in this assessment.</p>
                    </div>
                </div>
            `;
            
            transparencySection.innerHTML = html;
            
            // If the section is expanded, keep it expanded after updating content
            if (transparencySection.classList.contains('expanded')) {
                // Recalculate height to ensure smooth animation
                transparencySection.style.maxHeight = transparencySection.scrollHeight + 'px';
            }
        }

        function updateDataQualityIndicator(coveragePercent, monitoredArea, totalArea) {
            const coverageBar = document.getElementById('coverage-bar');
            const coveragePercentElement = document.getElementById('coverage-percent');
            const coverageDescription = document.getElementById('coverage-description');
            const confidenceBadge = document.getElementById('confidence-badge');
            const monitoredAreaElement = document.getElementById('monitored-area');
            const totalAreaElement = document.getElementById('total-area');
            
            // Check if elements exist
            if (!coverageBar || !coveragePercentElement || !confidenceBadge) {
                console.warn('Data quality indicator elements not found');
                return;
            }
            
            // Update coverage bar and percentage
            coverageBar.style.width = coveragePercent + '%';
            coveragePercentElement.textContent = coveragePercent + '%';
            
            // Update area values
            if (monitoredAreaElement) monitoredAreaElement.textContent = monitoredArea.toLocaleString();
            if (totalAreaElement) totalAreaElement.textContent = totalArea.toLocaleString();
            
            // Update confidence level
            let confidenceLevel = 'low';
            let confidenceText = 'Low';
            let confidenceColor = '#ffc107';
            
            if (coveragePercent >= 50) {
                confidenceLevel = 'high';
                confidenceText = 'High';
                confidenceColor = '#28a745';
            } else if (coveragePercent >= 20) {
                confidenceLevel = 'medium';
                confidenceText = 'Medium';
                confidenceColor = '#17a2b8';
            }
            
            // Update confidence badge
            confidenceBadge.textContent = confidenceText;
            confidenceBadge.className = 'confidence-badge ' + confidenceLevel;
            
            // Update border color of the quality indicator
            const qualityIndicator = document.getElementById('data-quality-indicator');
            if (qualityIndicator) {
                qualityIndicator.style.borderLeftColor = confidenceColor;
            }
            
            // Update health note based on coverage
            const healthNote = document.getElementById('health-note');
            if (healthNote) {
                if (coveragePercent < 20) {
                    healthNote.textContent = 'Limited coverage - results may not represent entire area';
                    healthNote.style.color = '#dc3545';
                } else if (coveragePercent < 50) {
                    healthNote.textContent = 'Moderate coverage - results are indicative';
                    healthNote.style.color = '#fd7e14';
                } else {
                    healthNote.textContent = 'Good coverage - results are representative';
                    healthNote.style.color = '#28a745';
                }
            }
        }

        function updateWeightingInformation(healthData) {
            const healthNote = document.getElementById('health-note');
            const weightDistribution = document.getElementById('weight-distribution');
            const weightLabel = document.getElementById('weight-label');
            
            // Check if elements exist
            if (!weightDistribution || !weightLabel) {
                console.warn('Weighting information elements not found');
                return;
            }
            
            // Clear previous distribution
            weightDistribution.innerHTML = '';
            
            if (healthData.length > 0) {
                // Calculate total weight
                const totalWeight = healthData.reduce((sum, item) => sum + item.weight, 0);
                
                // Sort by weight descending
                const sortedData = [...healthData].sort((a, b) => b.weight - a.weight);
                
                // Create segments for the top 5 reports by weight
                const topReports = Math.min(5, sortedData.length);
                let otherWeight = 0;
                
                for (let i = 0; i < topReports; i++) {
                    const weightPercent = (sortedData[i].weight / totalWeight) * 100;
                    
                    const segment = document.createElement('div');
                    segment.className = 'weight-segment';
                    segment.style.width = weightPercent + '%';
                    segment.style.backgroundColor = getColorForIndex(i);
                    segment.title = `Report ${i+1}: ${sortedData[i].weight.toLocaleString()} m² (${weightPercent.toFixed(1)}%)`;
                    
                    weightDistribution.appendChild(segment);
                }
                
                // Calculate remaining weight for "other" segment
                if (sortedData.length > topReports) {
                    for (let i = topReports; i < sortedData.length; i++) {
                        otherWeight += sortedData[i].weight;
                    }
                    
                    const otherPercent = (otherWeight / totalWeight) * 100;
                    
                    const otherSegment = document.createElement('div');
                    otherSegment.className = 'weight-segment';
                    otherSegment.style.width = otherPercent + '%';
                    otherSegment.style.backgroundColor = '#6c757d';
                    otherSegment.title = `${sortedData.length - topReports} other reports: ${otherWeight.toLocaleString()} m² (${otherPercent.toFixed(1)}%)`;
                    
                    weightDistribution.appendChild(otherSegment);
                }
                
                // Update label
                weightLabel.textContent = `${sortedData.length} reports contributing to statistics`;
                
                // Update health note
                if (sortedData[0].weight / totalWeight > 0.5) {
                    healthNote.innerHTML = 'Statistics heavily influenced by one large monitoring area';
                    healthNote.style.color = '#dc3545';
                } else if (sortedData[0].weight / totalWeight > 0.3) {
                    healthNote.innerHTML = 'Statistics weighted by area - Larger reports have significant influence';
                    healthNote.style.color = '#fd7e14';
                } else {
                    healthNote.innerHTML = 'Statistics weighted by area - Good distribution across multiple reports';
                    healthNote.style.color = '#28a745';
                }
            } else {
                // No data case
                weightLabel.textContent = 'No monitoring data available';
                healthNote.innerHTML = 'Statistics based on area-weighted monitoring data';
                healthNote.style.color = '#6c757d';
            }
        }

        // Helper function to generate colors for segments
        function getColorForIndex(index) {
            const colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'];
            return colors[index % colors.length];
        }

        // Fetch and display forest metrics
        async function loadForestMetrics() {
            const barangay = '<?php echo addslashes($barangay); ?>';
            const city = '<?php echo addslashes($city); ?>';
            const totalAreaHa = <?php echo $area; ?>;
            const totalAreaM2 = totalAreaHa * 10000;
            
            try {
                const response = await fetch(`get_forest_metrics.php?barangay=${encodeURIComponent(barangay)}&city=${encodeURIComponent(city)}`);
                const data = await response.json();
                
                // Store globally for transparency calculation
                window.forestMetricsData = data;
                
                if (data.success && data.total_reports > 0) {
                    // Show the forest metrics section
                    document.getElementById('forest-metrics-section').style.display = 'block';
                    
                    // Calculate monitoring coverage
                    const coveragePercent = (data.total_monitored_area / totalAreaM2) * 100;
                    
                    // Update monitoring coverage
                    document.getElementById('forest-monitoring-coverage').textContent = coveragePercent.toFixed(1) + '%';
                    document.getElementById('coverage-area-note').textContent = 
                        data.total_monitored_area.toLocaleString('en-US', {maximumFractionDigits: 0}) + ' m² surveyed';
                    
                    // Update forest cover
                    if (data.weighted_forest_cover !== null) {
                        document.getElementById('avg-forest-cover').textContent = 
                            parseFloat(data.weighted_forest_cover).toFixed(1) + '%';
                        document.getElementById('forest-cover-bar').style.width = 
                            parseFloat(data.weighted_forest_cover) + '%';
                    } else {
                        document.getElementById('avg-forest-cover').textContent = 'No data';
                    }
                    
                    // Update canopy density
                    if (data.weighted_canopy_density !== null) {
                        document.getElementById('avg-canopy-density').textContent = 
                            parseFloat(data.weighted_canopy_density).toFixed(1) + '%';
                        document.getElementById('canopy-density-bar').style.width = 
                            parseFloat(data.weighted_canopy_density) + '%';
                    } else {
                        document.getElementById('avg-canopy-density').textContent = 'No data';
                    }
                    
                    // Update tree density
                    if (data.avg_tree_density !== null && data.avg_tree_density > 0) {
                        document.getElementById('avg-tree-density').textContent = 
                            parseFloat(data.avg_tree_density).toLocaleString('en-US', {maximumFractionDigits: 0});
                        
                        // Calculate and display estimated total trees
                        const estimatedTrees = data.avg_tree_density * totalAreaHa;
                        const estimatedTreesRounded = Math.round(estimatedTrees);
                        document.getElementById('estimated-total-trees').textContent = 
                            estimatedTreesRounded.toLocaleString('en-US');
                        
                        // Sync with "Estimated Total Mangroves" in Detailed Metrics section
                        document.getElementById('estimated-mangroves').textContent = 
                            estimatedTreesRounded.toLocaleString('en-US');
                    } else {
                        document.getElementById('avg-tree-density').textContent = 'No density data';
                        document.getElementById('estimated-total-trees').textContent = 'N/A';
                        // Keep estimated-mangroves as is (might be calculated from other data)
                    }
                    
                    // Update confidence badge based on coverage
                    const confidenceBadge = document.getElementById('metrics-confidence-badge');
                    if (coveragePercent >= 20) {
                        confidenceBadge.textContent = 'High confidence - ' + data.total_reports + ' field measurements';
                        confidenceBadge.style.background = '#4CAF50';
                        confidenceBadge.style.color = 'white';
                    } else if (coveragePercent >= 5) {
                        confidenceBadge.textContent = 'Medium confidence - ' + data.total_reports + ' field measurements';
                        confidenceBadge.style.background = '#FF9800';
                        confidenceBadge.style.color = 'white';
                    } else {
                        confidenceBadge.textContent = 'Low confidence - Only ' + data.total_reports + ' field measurements';
                        confidenceBadge.style.background = '#f44336';
                        confidenceBadge.style.color = 'white';
                    }
                    
                    // Show forest metrics calculation transparency
                    showForestMetricsTransparency(data, totalAreaHa, coveragePercent);
                } else {
                    // Hide the section if no data
                    document.getElementById('forest-metrics-section').style.display = 'none';
                }
            } catch (error) {
                console.error('Error loading forest metrics:', error);
                document.getElementById('forest-metrics-section').style.display = 'none';
            }
        }
        
        // Show forest metrics calculation transparency
        function showForestMetricsTransparency(data, totalAreaHa, coveragePercent) {
            const transparencySection = document.getElementById('forest-calculation-transparency');
            if (!transparencySection) return;
            
            let html = `
                <div class="transparency-explanation">
                    <div class="calculation-details">
                        <h5>Current Calculation Details:</h5>
                        <table>
                            <tr>
                                <td>Total monitored area:</td>
                                <td><strong>${data.total_monitored_area.toLocaleString()} m²</strong></td>
                            </tr>
            `;
            
            // Forest Cover calculation
            if (data.weighted_forest_cover !== null) {
                html += `
                            <tr style="border-top: 2px solid #e0e0e0; padding-top: 8px;">
                                <td colspan="2" style="padding-top: 12px;"><strong>Forest Cover Calculation:</strong></td>
                            </tr>
                            <tr>
                                <td>Weighted forest cover sum:</td>
                                <td><strong>${(data.weighted_forest_cover * data.total_monitored_area / 100).toLocaleString(undefined, {maximumFractionDigits: 1})} m²</strong></td>
                            </tr>
                            <tr>
                                <td>Calculation:</td>
                                <td><strong>(${(data.weighted_forest_cover * data.total_monitored_area / 100).toLocaleString(undefined, {maximumFractionDigits: 1})} / ${data.total_monitored_area.toLocaleString()}) × 100</strong></td>
                            </tr>
                            <tr>
                                <td>Result:</td>
                                <td><strong>${parseFloat(data.weighted_forest_cover).toFixed(1)}%</strong></td>
                            </tr>
                `;
            }
            
            // Canopy Density calculation
            if (data.weighted_canopy_density !== null) {
                html += `
                            <tr style="border-top: 2px solid #e0e0e0; padding-top: 8px;">
                                <td colspan="2" style="padding-top: 12px;"><strong>Canopy Density Calculation:</strong></td>
                            </tr>
                            <tr>
                                <td>Weighted canopy density sum:</td>
                                <td><strong>${(data.weighted_canopy_density * data.total_monitored_area / 100).toLocaleString(undefined, {maximumFractionDigits: 1})} m²</strong></td>
                            </tr>
                            <tr>
                                <td>Calculation:</td>
                                <td><strong>(${(data.weighted_canopy_density * data.total_monitored_area / 100).toLocaleString(undefined, {maximumFractionDigits: 1})} / ${data.total_monitored_area.toLocaleString()}) × 100</strong></td>
                            </tr>
                            <tr>
                                <td>Result:</td>
                                <td><strong>${parseFloat(data.weighted_canopy_density).toFixed(1)}%</strong></td>
                            </tr>
                `;
            }
            
            // Tree Density calculation
            if (data.avg_tree_density !== null && data.avg_tree_density > 0) {
                const estimatedTrees = data.avg_tree_density * totalAreaHa;
                html += `
                            <tr style="border-top: 2px solid #e0e0e0; padding-top: 8px;">
                                <td colspan="2" style="padding-top: 12px;"><strong>Tree Density Extrapolation:</strong></td>
                            </tr>
                            <tr>
                                <td>Average tree density:</td>
                                <td><strong>${parseFloat(data.avg_tree_density).toLocaleString('en-US', {maximumFractionDigits: 0})} trees/ha</strong></td>
                            </tr>
                            <tr>
                                <td>Total barangay area:</td>
                                <td><strong>${totalAreaHa.toFixed(2)} ha</strong></td>
                            </tr>
                            <tr>
                                <td>Calculation:</td>
                                <td><strong>${parseFloat(data.avg_tree_density).toLocaleString('en-US', {maximumFractionDigits: 0})} × ${totalAreaHa.toFixed(2)}</strong></td>
                            </tr>
                            <tr>
                                <td>Estimated total trees:</td>
                                <td><strong>${Math.round(estimatedTrees).toLocaleString('en-US')}</strong></td>
                            </tr>
                `;
            }
            
            html += `
                        </table>
                    </div>
                    
                    <div class="coverage-note">
                        <p><strong>Note:</strong> This calculation is based on ${data.total_reports} field measurement${data.total_reports > 1 ? 's' : ''} covering ${coveragePercent.toFixed(1)}% of the total mangrove area. 
                        More reports with forest metrics = higher accuracy!</p>
                    </div>
                </div>
            `;
            
            transparencySection.innerHTML = html;
        }
        
        // Toggle forest metrics transparency section visibility
        function toggleForestTransparencySection() {
            const transparencySection = document.getElementById('forest-calculation-transparency');
            const dropdownIcon = document.querySelector('.forest-transparency-toggle .dropdown-icon');
            
            if (transparencySection.classList.contains('collapsed')) {
                transparencySection.classList.remove('collapsed');
                transparencySection.classList.add('expanded');
                dropdownIcon.style.transform = 'rotate(180deg)';
            } else {
                transparencySection.classList.remove('expanded');
                transparencySection.classList.add('collapsed');
                dropdownIcon.style.transform = 'rotate(0deg)';
            }
        }
        
        // Load forest metrics when page loads
        window.addEventListener('DOMContentLoaded', () => {
            loadForestMetrics();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements using the correct selectors
            const toggleButton = document.getElementById('toggle-btn');
            const sidebar = document.querySelector('.mangrove-sidebar');
            const profileDetails = document.getElementById('profile-details');
            const userbox = document.querySelector('.userbox');
            
            // === SIDEBAR TOGGLE FUNCTION ===
            function SidebarToggle() {
                if (sidebar && toggleButton) {
                    sidebar.classList.toggle('close');
                    toggleButton.classList.toggle('rotate');
                    CloseAllSubMenus();
                }
            }
            
            // === DROPDOWN TOGGLE FUNCTION ===
            function DropDownToggle(button) {
                if (!button.nextElementSibling.classList.contains('show')) {
                    CloseAllSubMenus();
                }
                
                button.nextElementSibling.classList.toggle('show');
                button.classList.toggle('rotate');
                
                if (sidebar && sidebar.classList.contains('close')) {
                    SidebarToggle();
                }
            }
            
            // === CLOSE ALL SUBMENUS FUNCTION ===
            function CloseAllSubMenus() {
                if (sidebar) {
                    Array.from(sidebar.getElementsByClassName('show')).forEach(ul => {
                        ul.classList.remove('show');
                        if (ul.previousElementSibling) {
                            ul.previousElementSibling.classList.remove('rotate');
                        }
                    });
                }
            }
            
            // === PROFILE POPUP TOGGLE FUNCTION ===
            function toggleProfilePopup(e) {
                if (e) e.stopPropagation();
                if (profileDetails) {
                    profileDetails.classList.toggle('close');
                    
                    if (!profileDetails.classList.contains('close')) {
                        document.addEventListener('click', function closePopup(evt) {
                            if (!profileDetails.contains(evt.target) && evt.target !== userbox) {
                                profileDetails.classList.add('close');
                                document.removeEventListener('click', closePopup);
                            }
                        });
                    }
                }
            }
            
            // === RESPONSIVE HANDLER ===
            function handleResize() {
                if (window.innerWidth <= 800 && sidebar) {
                    if (sidebar.classList.contains('close')) {
                        SidebarToggle();
                    }
                }
            }
            
            // === SET UP EVENT LISTENERS ===
            if (toggleButton) {
                toggleButton.addEventListener('click', SidebarToggle);
            }
            
            // Add event listeners to dropdown buttons
            document.querySelectorAll('.dropdown-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    DropDownToggle(this);
                });
            });
            
            // Add event listener to user profile icon
            if (userbox) {
                userbox.addEventListener('click', toggleProfilePopup);
            }
            
            // Remove the old onclick attributes and replace with event listeners
            document.querySelectorAll('[onclick*="SidebarToggle"]').forEach(el => {
                el.removeAttribute('onclick');
                if (el.id === 'toggle-btn') {
                    el.addEventListener('click', SidebarToggle);
                }
            });
            
            document.querySelectorAll('[onclick*="DropDownToggle"]').forEach(el => {
                el.removeAttribute('onclick');
                if (el.classList.contains('dropdown-btn')) {
                    el.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        DropDownToggle(this);
                    });
                }
            });
            
            document.querySelectorAll('[onclick*="toggleProfilePopup"]').forEach(el => {
                el.removeAttribute('onclick');
                if (el.classList.contains('userbox')) {
                    el.addEventListener('click', toggleProfilePopup);
                }
            });
            
            // Initialize on load
            handleResize();
            
            // Run on window resize
            window.addEventListener('resize', handleResize);
            
            // Make functions globally available if needed by other scripts
            window.SidebarToggle = SidebarToggle;
            window.DropDownToggle = DropDownToggle;
            window.toggleProfilePopup = toggleProfilePopup;
        });
    </script>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Profile Archive</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Notice:</strong> This action will delete the profile page of the barangay. The profile will be moved to the archive and will no longer be publicly accessible, but the data will be preserved for records. </p> <br><small style="text-align:center; color:red;"><strong style="color:red;">( Warning: </strong>Images will be removed for clearing space )</small>
                <p>To confirm archiving, please type the profile key exactly as shown:</p>
                <div class="profile-key-display">
                    <strong><?php echo htmlspecialchars($profile_key); ?></strong>
                </div>
                <input type="text" id="confirmProfileKey" placeholder="Enter profile key to confirm" class="form-control" style="margin-top: 10px;">
                <div id="keyMismatchError" class="error-message" style="display: none; color: red; margin-top: 5px;">
                    Profile key does not match. Please try again.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" id="confirmDeleteButton" class="btn btn-danger">Delete Profile</button>
            </div>
        </div>
    </div>

    <style>
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 0;
        border: none;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        padding: 20px 25px 15px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        color: #dc3545;
    }

    .modal-body {
        padding: 20px 25px;
    }

    .modal-footer {
        padding: 15px 25px 20px;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }

    .close:hover,
    .close:focus {
        color: #000;
        text-decoration: none;
    }

    .profile-key-display {
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 10px;
        font-family: monospace;
        text-align: center;
        margin: 10px 0;
    }

    .form-control {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }

    .error-message {
        font-size: 14px;
        font-weight: 500;
    }
    </style>
</body>
</html>