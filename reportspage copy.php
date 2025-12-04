<!--
<?php
    session_start();
    require_once 'database.php'; // Make sure you have your database connection file

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

    // Get all reports for this user from userreportstbl
    $user_reports = [];
    if(isset($user_id)) {
        $query = "SELECT report_id, report_type FROM userreportstbl WHERE account_id = ?";
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
            // Get details from mangrovereporttbl
            $details_query = "SELECT species, created_at, area_no, area_id, city_municipality 
                            FROM mangrovereporttbl 
                            WHERE report_id = ?";
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
            // Get details from illegalreportstbl
            $details_query = "SELECT incident_type, created_at, area_no, city_municipality, priority 
                            FROM illegalreportstbl 
                            WHERE report_id = ?";
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
                    'status' => $action_type
                ];
            }
        }
    }
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
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <a href="about.php">About</a>
            <a href="events.php">Events</a>
            <a href="leaderboards.php">Leaderboards</a>
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
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li class="active"><a href="reportspage.php" tabindex="-1">My Reports</a></li>
                    <li><a href="#" tabindex="-1">My Events</a></li>
                    </div>
                </ul>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == "Barangay Official"){
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
                        <div class="report-list">
                            <?php foreach($mangrove_reports as $report): ?>
                            <div class="report-item" data-report-id="<?= $report['report_id'] ?>" data-type="mangrove">
                                <div class="report-summary">
                                    <h3><?= htmlspecialchars($report['species']) ?></h3>
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
                        <div class="report-list">
                            <?php foreach($illegal_reports as $report): ?>
                            <div class="report-item" data-report-id="<?= $report['report_id'] ?>" data-type="illegal">
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
                        <div class="notification-bell">
                            <i class='bx bx-bell'></i>
                            <span class="notification-count">2</span>
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
    
function getCurrentStatus(notifications) {
    if (!notifications || notifications.length === 0) {
        return 'Received';
    }
    
    // Sort notifications by date (newest first)
    const sortedNotifications = [...notifications].sort((a, b) => 
        new Date(b.notif_date) - new Date(a.notif_date)
    );
    
    // Return the action_type of the most recent notification
    return sortedNotifications[0].action_type;
}

function updatePreviewSection(data) {
    if (data.error) {
        console.error(data.error);
        showErrorState();
        return;
    }

    const report = data.report || {};
    const notifications = data.notifications || [];
    
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
        ? report.species 
        : report.incident_type;
    document.getElementById('preview-title').textContent = 
        title || 'No title available';
    
    // Update status badge
    const latestStatus = getCurrentStatus(notifications);

    console.log(latestStatus);
    const statusBadge = document.getElementById('preview-status');
    statusBadge.textContent = formatActionType(latestStatus);
    statusBadge.className = 'report-status-badge ' + latestStatus.toLowerCase().replace('_', '-');
    
    // Check if report is rejected
    const isRejected = latestStatus === 'Rejected';
    
    // Add or remove edit button container
    let editButtonContainer = document.querySelector('.edit-button-container');
    if (!editButtonContainer) {
        editButtonContainer = document.createElement('div');
        editButtonContainer.className = 'edit-button-container';
        document.querySelector('.report-content').appendChild(editButtonContainer);
    } else {
        editButtonContainer.innerHTML = ''; // Clear existing content
    }
    
    if (isRejected) {
        // Find the rejection notification to get the date
        const rejectionNotif = notifications.find(n => n.action_type === 'Rejected');
        const rejectDate = rejectionNotif ? new Date(rejectionNotif.notif_date) : new Date();
        
        // Calculate time remaining (48 hours from rejection)
        const timeRemaining = calculateTimeRemaining(rejectDate);
        
        // Create countdown display
        const countdownEl = document.createElement('div');
        countdownEl.className = 'rejection-countdown';
        
        if (timeRemaining.total <= 0) {
            // Time has expired
            countdownEl.innerHTML = `
                <div class="countdown-expired">
                    <i class='bx bx-time-five'></i>
                    <span>The deadline for editing this report has passed</span>
                </div>
            `;
        } else {
            // Time still remaining
            countdownEl.innerHTML = `
                <div class="countdown-active">
                    <i class='bx bx-time-five'></i>
                    <span>Time remaining to edit: 
                        <span class="countdown-timer">${formatTimeRemaining(timeRemaining)}</span>
                    </span>
                </div>
            `;
            
            // Add edit button
            const editButton = document.createElement('button');
            editButton.className = 'edit-report-btn';
            editButton.innerHTML = `<i class='bx bx-edit'></i> Edit Report`;
            editButton.onclick = function() {
                window.location.href = `edit_reportform.php?report_id=${report.report_id}&type=${
                    report.report_type === 'Mangrove Data Report' ? 'mangrove' : 'illegal'
                }`;
            };
            
            editButtonContainer.appendChild(editButton);
            
            // Start the countdown timer
            startCountdown(rejectDate, countdownEl.querySelector('.countdown-timer'));
        }
        
        editButtonContainer.prepend(countdownEl);
    }
    
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
    if (report.report_type === 'Illegal Activity Report') {
        priorityEl.textContent = report.priority 
            ? report.priority + ' Priority' 
            : 'Normal Priority';
        priorityEl.className = 'report-priority ' + (report.priority ? report.priority.toLowerCase() : 'normal');
    } else {
        priorityEl.textContent = 'Normal Priority';
        priorityEl.className = 'report-priority normal';
    }
    
    // Update description or show default
    const descriptionEl = document.getElementById('preview-description');
    if (report.report_type === 'Mangrove Data Report') {
        descriptionEl.textContent = report.remarks || 'No additional details provided.';
        
        // Update mangrove-specific fields
        document.getElementById('preview-species').textContent = 
            report.species || 'Not specified';
        document.getElementById('preview-area').textContent = 
            report.area_m2 ? report.area_m2 + ' sqm' : 'Not specified';
        document.getElementById('preview-mangrove-status').textContent = 
            report.mangrove_status || 'Not specified';
        
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

// Helper functions for countdown
function calculateTimeRemaining(rejectDate) {
    const now = new Date();
    const deadline = new Date(rejectDate.getTime() + 48 * 60 * 60 * 1000); // 48 hours after rejection
    const total = deadline - now;
    
    const seconds = Math.floor((total / 1000) % 60);
    const minutes = Math.floor((total / 1000 / 60) % 60);
    const hours = Math.floor((total / (1000 * 60 * 60)) % 24);
    const days = Math.floor(total / (1000 * 60 * 60 * 24));
    
    return {
        total,
        days,
        hours,
        minutes,
        seconds
    };
}

function formatTimeRemaining(time) {
    if (time.days > 0) {
        return `${time.days}d ${time.hours}h ${time.minutes}m`;
    }
    if (time.hours > 0) {
        return `${time.hours}h ${time.minutes}m ${time.seconds}s`;
    }
    return `${time.minutes}m ${time.seconds}s`;
}

function startCountdown(rejectDate, displayElement) {
    const timer = setInterval(() => {
        const timeRemaining = calculateTimeRemaining(rejectDate);
        
        if (timeRemaining.total <= 0) {
            clearInterval(timer);
            displayElement.textContent = '0m 0s';
            
            // Update UI when countdown expires
            const container = displayElement.closest('.countdown-active');
            if (container) {
                container.innerHTML = `
                    <i class='bx bx-time-five'></i>
                    <span>The deadline for editing this report has passed</span>
                `;
                container.className = 'countdown-expired';
                
                // Remove edit button
                const editButton = document.querySelector('.edit-report-btn');
                if (editButton) {
                    editButton.remove();
                }
            }
        } else {
            displayElement.textContent = formatTimeRemaining(timeRemaining);
        }
    }, 1000);
}

// Helper function to format action types
function formatActionType(action) {
    if (!action) return 'Update';
    return action.split('_').map(word => 
        word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
    ).join(' ');
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
        } else {
            // Group notifications by action type, keeping only the latest of each type
            const groupedNotifications = {};
            notifications.forEach(notif => {
                if (!groupedNotifications[notif.action_type] || 
                    new Date(notif.notif_date) > new Date(groupedNotifications[notif.action_type].notif_date)) {
                    groupedNotifications[notif.action_type] = notif;
                }
            });
            
            // Display them in order
            const displayOrder = ['Received', 'Investigating', 'Action Taken', 'Resolved', 'Rejected'];
            displayOrder.forEach(actionType => {
                if (groupedNotifications[actionType]) {
                    const notif = groupedNotifications[actionType];
                    const notifItem = document.createElement('div');
                    notifItem.className = 'notification-item ' + actionType.toLowerCase().replace('_', '-');
                    
                    const date = new Date(notif.notif_date).toLocaleDateString('en-US', { 
                        year: 'numeric', month: 'long', day: 'numeric' 
                    });
                    
                    notifItem.innerHTML = `
                        <div class="notification-header">
                            <span class="notification-date">${date}</span>
                            <span class="notification-status ${actionType.toLowerCase().replace('_', '-')}">
                                ${formatActionType(actionType)}
                            </span>
                        </div>
                        <p class="notification-message">
                            ${notif.notif_description ? notif.notif_description.replace(/\\(.)/g, "$1") : 'No details provided.'}
                            ${notif.notifier_name ? `<br><small>Notified by: ${notif.notifier_name}</small>` : ''}
                        </p>
                    `;
                    
                    notificationsContainer.appendChild(notifItem);
                }
            });
        }
    }
    
    // Helper function to format action types
    function formatActionType(action) {
        if (!action) return 'Update';
        return action.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()
        ).join(' ');
    }
});
</script>
</body>
</html>