<?php
session_start();
include 'database.php';
require_once 'getdropdown.php';

// Check authorization (same as before)

// Get profile key from URL
$profile_key = isset($_GET['profile_key']) ? $_GET['profile_key'] : '';

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
$species = explode(',', $profile['species_present']);
$lat = htmlspecialchars($profile['latitude']);
$lng = htmlspecialchars($profile['longitude']);
$qr_code = $profile['qr_code'];
$photos = !empty($profile['photos']) ? explode(',', $profile['photos']) : [];

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
    <script type ="text/javascript" src ="app.js" defer></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>
</head>
<body class="min-h-screen flex flex-col">
    <?php 
        if(isset($_POST['admin_access_key']) && $_POST['admin_access_key'] === 'adminkeynadialamnghindicoderist') {
    ?>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a-php"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d-php"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d-php"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80-php"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>           
            </ul>
        </nav>
        <?php 
            if (isset($_SESSION["name"])) {
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
    </header>
    <?php }?>
    
    <main class="flex-grow container mx-auto p-6 md:p-10">
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
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
        </div>
        
        <div class="form-container">
            <?php
            if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] != "Administrator"){
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
                                <label class="form-label">Total Mangrove Area (ha)</label>
                                <p class="profile-data"><?php echo htmlspecialchars($area); ?> ha</p>
                            </div>
                            <div>
                                <label class="form-label">Date of Profile</label>
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
                </section>

                <!-- Right Column: QR Code and Actions -->
                <aside class="section-right">
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
                            <a id="downloadQr" href="#" download="mangrove_profile_<?php echo htmlspecialchars($barangay); ?>_qr.png" class="download-qr">
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
                        <button id="regenerateQrButton" class="btn btn-secondary" <?php echo $disableBtn; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-regenerate" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Regenerate QR
                        </button>
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

        // Generate QR code
        function generateQrCode() {
            const qrCanvas = document.getElementById('qrcode');
            const downloadLink = document.getElementById('downloadQr');
            
            <?php if(!empty($qr_code)): ?>
                // If we already have a QR code from database, use it
                const img = new Image();
                img.onload = function() {
                    const ctx = qrCanvas.getContext('2d');
                    qrCanvas.width = img.width;
                    qrCanvas.height = img.height;
                    ctx.drawImage(img, 0, 0);
                    downloadLink.href = qrCanvas.toDataURL('image/png');
                };
                img.src = "<?php echo $qr_code; ?>";
            <?php else: ?>
                // Generate QR code with profile key URL
                const profileUrl = `http://localhost:3000/view_barangay_profile.php?profile_key=<?php echo $profile['profile_key']; ?>`;
                
                // Generate QR code
                const qr = new QRious({
                    element: qrCanvas,
                    value: profileUrl,
                    size: 250,
                    level: 'H'
                });
                
                // Set download link
                downloadLink.href = qrCanvas.toDataURL('image/png');
            <?php endif; ?>
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            initLightbox();
            generateQrCode();
            calculateStatistics();
            
            document.getElementById('regenerateQrButton').addEventListener('click', function() {
                if(confirm('Are you sure you want to regenerate the QR code? The old code will no longer work.')) {
                    generateQrCode();
                    alert('QR code regenerated successfully!');
                }
            });
            
            document.getElementById('deleteButton').addEventListener('click', function() {
                if(confirm('Are you sure you want to delete this profile? This action cannot be undone.')) {
                    alert('Profile deletion functionality will be implemented here.');
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

        // Calculate statistics
        function calculateStatistics() {
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
            
            // Calculate monitoring coverage
            const totalAreaM2 = <?php echo $area * 10000; ?>; // Convert ha to m²
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
            updateDataQualityIndicator(
                coveragePercent.toFixed(1), 
                totalMangroveArea, 
                totalAreaM2
            );

            // Update the DOM
            document.getElementById('total-reports').textContent = totalReports;
            document.getElementById('healthy-percentage').textContent = weightedHealthPercentage.toFixed(1) + '%';
            document.getElementById('illegal-reports').textContent = illegalReports;
            document.getElementById('resolved-issues').textContent = resolvedIssues;
            
            // Update extended statistics
            document.getElementById('total-monitored-area').textContent = totalMangroveArea.toLocaleString(undefined, {maximumFractionDigits: 1}) + ' m²';
            document.getElementById('avg-report-area').textContent = avgReportArea.toLocaleString(undefined, {maximumFractionDigits: 1}) + ' m²';
            
            if (estimatedMangroves > 0) {
                document.getElementById('estimated-mangroves').textContent = estimatedMangroves.toLocaleString(undefined, {maximumFractionDigits: 0});
            } else {
                document.getElementById('estimated-mangroves').textContent = 'N/A';
            }
            
            // Add information about weighting
            updateWeightingInformation(healthStatusData);
        }

        function updateDataQualityIndicator(coveragePercent, monitoredArea, totalArea) {
            const coverageBar = document.getElementById('coverage-bar');
            const coveragePercentElement = document.getElementById('coverage-percent');
            const coverageDescription = document.getElementById('coverage-description');
            const confidenceBadge = document.getElementById('confidence-badge');
            const monitoredAreaElement = document.getElementById('monitored-area');
            const totalAreaElement = document.getElementById('total-area');
            
            // Update coverage bar and percentage
            coverageBar.style.width = coveragePercent + '%';
            coveragePercentElement.textContent = coveragePercent + '%';
            
            // Update area values
            monitoredAreaElement.textContent = monitoredArea.toLocaleString();
            totalAreaElement.textContent = totalArea.toLocaleString();
            
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
            document.getElementById('data-quality-indicator').style.borderLeftColor = confidenceColor;
            
            // Update health note based on coverage
            const healthNote = document.getElementById('health-note');
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

        function updateWeightingInformation(healthData) {
            const healthNote = document.getElementById('health-note');
            const weightDistribution = document.getElementById('weight-distribution');
            const weightLabel = document.getElementById('weight-label');
            
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
    </script>
</body>
</html>