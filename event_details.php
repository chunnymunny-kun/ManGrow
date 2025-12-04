<!--
<?php
    session_start();
    include 'database.php';
    include 'badge_system_db.php';
    
    // users must be logged in to access this page's full event details
    if(!isset($_SESSION["name"])){
        //get the current URL and store it in session variable
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        $_SESSION['response'] = [
                    'status' => 'error',
                    'msg' => 'Please login first.'
                ];
        header("Location: login.php");
        exit();
    }

    if(!isset($_GET['event_id'])){
        header("Location: events.php");
        exit();
    }
    // if the event_id is set but it is not Approved, go back to events page
    $event_id = htmlspecialchars($_GET['event_id']);
    $query = "SELECT * FROM eventstbl WHERE event_id = '$event_id' AND is_approved = 'Approved'";
    $result = mysqli_query($connection, $query);
    if(mysqli_num_rows($result) == 0){
        header("Location: events.php");
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

    $event_id = isset($_GET['event_id']) ? htmlspecialchars($_GET['event_id']) : '';

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
?>

<?php
function getFileIconClass($extension) {
    $extension = strtolower($extension);
    switch ($extension) {
        case 'pdf':
            return 'fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fa-file-word';
        case 'xls':
        case 'xlsx':
            return 'fa-file-excel';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
            return 'fa-file-image';
        case 'zip':
        case 'rar':
        case '7z':
            return 'fa-file-archive';
        default:
            return 'fa-file';
    }
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManGrow Events</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="event_details.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script src="https://api.tiles.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    <link href="https://api.tiles.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet"/>

    <script type ="text/javascript" src ="app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
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
        <div class="events-container">
            <div class="events-nav">
                <div class="my-events-section">
                    <h2 class="filter-title">Other Events</h4>
                    <div class="myevent-strip">
                    <?php 
                    $mycurrentDate = date("Y-m-d");
                    $myquery = "SELECT * FROM eventstbl
                            WHERE author = ?
                            ORDER BY posted_at DESC";
                    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Resident' && ($_SESSION['organization'] == '' || $_SESSION['organization'] == 'N/A')) {
                        // If the resident w/o organization is logged in, show the events they are interested going to
                        $myquery = "SELECT e.* FROM eventstbl e 
                                JOIN attendeestbl a ON e.event_id = a.event_id 
                                WHERE a.account_id = ".$_SESSION['user_id']."
                                ORDER BY posted_at DESC;";
                    } elseif(isset($_SESSION['user_id'])) {
                        // If the user is a Barangay Official, show all events they made as well as the events made by other users from the same barangay as Barangay Official
                        $mybarangay = $_SESSION['barangay'] ?? '';
                        $mycity_municipality = $_SESSION['city_municipality'] ?? '';
                        $myquery = "SELECT * FROM eventstbl
                                WHERE (author = ".$_SESSION['user_id']." OR (barangay = '$mybarangay' AND city_municipality = '$mycity_municipality')) AND program_type != 'Announcement' AND is_approved = 'Approved'
                                ORDER BY posted_at DESC;";
                    }
                    $myresult = mysqli_query($connection,$myquery);
                    if (mysqli_num_rows($myresult) > 0) {
                        while($myitems = mysqli_fetch_assoc($myresult)) {
                            //display the event dates with the format "F j, Y" (ex. "May 15, 2025")
                            $mystartDate = date("F j, Y", strtotime($myitems['start_date']));
                            $myendDate = date("F j, Y", strtotime($myitems['end_date']));
                            $mypostedAt = date("F j, Y", strtotime($myitems['posted_at']));
                            ?>
                    <div class="myevent-item" onclick="window.location.href='event_details.php?event_id=<?= $myitems['event_id'] ?>'">
                        <div class="myevent-thumbnail">
                            <?php if(!empty($myitems['thumbnail'])): ?>
                                <img src="<?= htmlspecialchars($myitems['thumbnail']) ?>" alt="Event Thumbnail" class="myevent-thumbnail-img">
                            <?php else: ?>
                                <img src="default_thumbnail.png" alt="Default Thumbnail" class="myevent-thumbnail-img">
                            <?php endif; ?>
                        </div>
                        <div class="myevent-info">
                            <h5><?= htmlspecialchars($myitems['subject']) ?></h5>
                            <p><?= htmlspecialchars($myitems['venue']) ?></p>
                            <p class="myevent-date">
                                <span class="countdown-timer" data-start-date="<?= $myitems['start_date']?>" data-end-date="<?= htmlspecialchars($myitems['end_date']) ?>">
                                    <i class="fas fa-clock"></i> 
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <?php
                    }
                } else {
                    echo "<p class='no-events'>No events found.</p>";
                }
                ?>
                </div>
                </div>
                <div class="quick-actions-section">
                    <h2 class="filter-title">Quick Actions</h2>
                    <div class="quick-actions">
                        <?php if(isset($_SESSION['accessrole'])): ?>
                            <?php if(!($_SESSION['accessrole'] == 'Resident' && ($_SESSION['organization'] == '' || $_SESSION['organization'] == 'N/A'))): ?>
                                <button class="action-button create-button" onclick="window.location.href = `create_event.php?organization=${encodeURIComponent('<?php echo $_SESSION['organization']; ?>')}`;">
                                    <i class="fas fa-plus"></i> Create New Event
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="action-button eventsback-button" onclick="window.location.href='events.php'">
                            <i class="fas fa-user"></i> Back to Events
                        </button>
                        <button class="action-button myevents-button" onclick="window.location.href='myevents.php'">
                            <i class="fas fa-user"></i> My Events
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="events-content">
                <?php 
                $currentDate = date("Y-m-d");
                $query = "SELECT * FROM eventstbl 
                          WHERE event_id = '$event_id'";
                $result = mysqli_query($connection,$query);
                if (mysqli_num_rows($result) > 0) {
                    while($items = mysqli_fetch_assoc($result)) {
                        //display the event dates with the format "F j, Y" (ex. "May 15, 2025")
                        $startDate = date("F j, Y", strtotime($items['start_date']));
                        $endDate = date("F j, Y", strtotime($items['end_date']));
                        $postedAt = date("F j, Y", strtotime($items['posted_at']));

                        //variables for dates with time included
                        $startDateTime = date("F j, Y g:i A", strtotime($items['start_date']));
                        $endDateTime = date("F j, Y g:i A", strtotime($items['end_date']));
                        $postedAtTime = date("F j, Y g:i A", strtotime($items['posted_at']));

                        //get the account_id of the user who posted the event
                        $accountId = $items['author'];
                        //then create a query that will get the name and profile of the user who posted the event
                        $userQuery = "SELECT fullname, profile, profile_thumbnail, organization FROM accountstbl WHERE account_id = '$accountId'";
                        $userResult = mysqli_query($connection, $userQuery);
                        $user = mysqli_fetch_assoc($userResult);
                        ?>
                        <div class="event-details" data-event-id="<?= $items['event_id'] ?>" data-type="<?= htmlspecialchars($items['program_type']) ?>" data-date="<?= htmlspecialchars($items['start_date']) ?>" data-timestamp="<?= htmlspecialchars($items['posted_at']) ?>">
                            <div class="event-header">
                                <?php if(!empty($items['thumbnail'])): ?>
                                    <div class="event-thumbnail" onclick="openThumbnailModal('<?= $items['event_id'] ?>')">
                                        <img src="<?= htmlspecialchars($items['thumbnail']) ?>" alt="Event Thumbnail" class="event-thumbnail-img">
                                    </div>                              
                                <?php else: ?>
                                <?php endif; ?>
                                <div class="event-title">
                                <?php if(isset($items['program_type']) && $items['program_type'] !== 'Announcement'): ?>
                                    <div class="event-dates">
                                        <span class="event-start-date"><?= htmlspecialchars($startDateTime) ?></span>
                                        <?php if ($items['start_date'] !== $items['end_date']): ?>
                                            <span class="event-end-date"> - <?= htmlspecialchars($endDateTime) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                <?php endif; ?>
                                    <h3><?= htmlspecialchars($items['subject']) ?></h3>
                                    <p><?= htmlspecialchars($items['venue']) ?></p>
                                </div>
                                <div class="event-actions">
                                    <!-- Attend Button -->
                                    <div class="attend-div">
                                        <?php
                                        $isAttending = false;
                                        if (isset($_SESSION['user_id'])) {
                                            $checkAttendance = "SELECT * FROM attendeestbl 
                                                            WHERE event_id = ".$items['event_id']." 
                                                            AND account_id = ".$_SESSION['user_id'];
                                            $attendanceResult = mysqli_query($connection, $checkAttendance);
                                            $isAttending = mysqli_num_rows($attendanceResult) > 0;
                                        }
                                        if ($items['program_type'] !== 'Announcement') {
                                        ?>
                                        <form method="post" action="attend_event.php" class="attend-form">
                                            <input type="hidden" name="event_id" value="<?= $items['event_id'] ?>">
                                            <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $items['author']): ?>
                                                <button type="submit" class="attend-btn" name="attend-btn">
                                                    <i class="fas fa-calendar-check"></i> 
                                                    <span class="btn-text"><?= $isAttending ? 'Not Interested' : 'Interested' ?></span>
                                                </button>
                                            <?php endif; ?>
                                            <span class="attendee-count"><i class="fas fa-users"></i> <?= $items['participants'] ?> interested</span>
                                        </form>
                                        <?php } ?>
                                        </div>
                                    <div class="actions-div">
                                        <!-- Comment Button -->
                                        <button class="comment-btn" onclick="toggleComments('<?= $items['event_id'] ?>')">
                                            <i class="fas fa-comment"></i> 
                                            <span class="btn-text">Comment</span>
                                        </button>
                                        <!-- Share Button -->
                                        <button class="share-btn" onclick="shareEvent('<?= $items['event_id'] ?>')">
                                            <i class="fas fa-share-alt"></i> 
                                            <span class="btn-text">Share</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="event-body">
                                <div class="event-body-main-container">
                                    <div class="event-meta">
                                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $items['author']): ?>
                                        <?php if($items['featured_status'] === 'Featured'): ?>
                                            <div class="featured-period">
                                                Featured until: <?= date('M j, Y', strtotime($items['featured_enddate'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($items['program_type'] !== 'Announcement' && ($items['is_approved'] !== 'Pending' && $items['is_approved'] !== 'Disapproved')) { ?>
                                        <div class="featured-icon-container">
                                            <i class="fas fa-star featured-icon <?= $items['featured_status'] == 'Featured' ? 'featured-active' : '' ?>" 
                                            data-event-id="<?= $items['event_id'] ?>"
                                            data-end-date="<?= !empty($items['end_date']) ? htmlspecialchars($items['end_date']) : '' ?>"
                                            onclick="toggleFeaturedStatus(this)"></i>
                                        </div>
                                        <?php } ?>
                                        <?php endif; ?>
                                        <span class="program-type <?= htmlspecialchars($items['program_type']) ?>"><?= htmlspecialchars($items['program_type']) ?></span>
                                        <?php if(isset($items['program_type']) && $items['program_type'] !== 'Announcement') { ?>
                                        <span class="event-type <?= htmlspecialchars($items['event_type']) ?>"><?= htmlspecialchars($items['event_type']) ?></span>
                                        <?php } ?>
                                            <?php 
                                            if ($items['program_type'] !== 'Announcement') {
                                            if($items['featured_status'] === 'Featured'): ?>
                                                <?php
                                                    // Set timezone to Philippine time and get current datetime
                                                    date_default_timezone_set('Asia/Manila');
                                                    $currentDate = date("Y-m-d H:i:s");
                                                    if($items['featured_startdate'] <= $currentDate && $currentDate <= $items['featured_enddate']){?>
                                                <span class="event-featured">Featured</span>
                                                <?php } else { ?>
                                                <?php } ?>
                                            <?php else: ?>
                                            <?php endif; 
                                            }?>
                                        <?php
                                        date_default_timezone_set('Asia/Manila');
                                        if (isset($items['start_date']) && isset($items['end_date'])) {
                                            $currentDateTime = date("Y-m-d H:i:s");
                                            $startDateTime = $items['start_date'];
                                            $endDateTime = $items['end_date'];
                                            
                                            // Convert all to DateTime objects for proper comparison
                                            $current = new DateTime($currentDateTime);
                                            $start = new DateTime($startDateTime);
                                            $end = new DateTime($endDateTime);
                                            
                                            if ($current < $start) {
                                                $eventStatus = "Upcoming";
                                                $statusClass = "upcoming";
                                            } elseif ($current > $end) {
                                                $eventStatus = "Completed";
                                                $statusClass = "completed";
                                            } else {
                                                $eventStatus = "Ongoing";
                                                $statusClass = "ongoing";
                                            }
                                            echo '<span class="event-status ' . $statusClass . '">Status: ' . $eventStatus . '</span>';
                                        }
                                        ?>
                                    </div>
                                    <div class="description-container">
                                        <div class="event-description collapsed" id="desc-<?= $items['event_id']?>">
                                            <p><?= htmlspecialchars(stripslashes($items['description']))?></p>
                                        </div>
                                        <button class="see-more-btn" onclick="toggleDescription('desc-<?= $items['event_id']?>', this)">See More</button>
                                        <div class="event-info-list">
                                            <ul>
                                                <?php if (isset($items['venue']) && $items['venue'] !== ''): ?>
                                                    <li>
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <strong>Venue:</strong> <?= htmlspecialchars($items['venue']) ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (isset($items['barangay']) && $items['barangay'] !== ''): ?>
                                                    <li>
                                                        <i class="fas fa-home"></i>
                                                        <strong>Barangay:</strong> <?= htmlspecialchars($items['barangay']) ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (isset($items['city_municipality']) && $items['city_municipality'] !== ''): ?>
                                                    <li>
                                                        <i class="fas fa-city"></i>
                                                        <strong>City/Municipality:</strong> <?= htmlspecialchars($items['city_municipality']) ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (isset($items['area_no']) && $items['area_no'] !== ''): ?>
                                                    <li>
                                                        <i class="fas fa-hashtag"></i>
                                                        <strong>Area No:</strong> <?= htmlspecialchars($items['area_no']) ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if (isset($user['organization']) && $user['organization'] !== ''): ?>
                                                    <li>
                                                        <i class="fas fa-users"></i>
                                                        <strong>Organization:</strong> <?= htmlspecialchars($user['organization']) ?>
                                                    </li>
                                                <?php endif; ?>
                                                <?php
                                                if (isset($items['event_links'])) {
                                                    $links = json_decode(stripslashes($items['event_links']), true);
                                                    if (is_array($links) && count($links) > 0) {
                                                        echo '<li><div class="event-links"><strong><i class="fas fa-link"></i> External Links:</strong><ul>';
                                                        foreach ($links as $link) {
                                                            echo '<li><a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($link) . '</a></li>';
                                                        }
                                                        echo '</ul></div></li>';
                                                    }
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $items['author'] && $items['is_cross_barangay'] && !empty($items['attachments_metadata'])): ?>
                                            <div class="attachments-section">
                                                <h3><i class="fas fa-paperclip"></i> Supporting Documents</h3>
                                                <p>This event required special approval as it was conducted outside the organizer's barangay. Below are the supporting documents provided:</p>
                                                
                                                <div class="attachments-list">
                                                    <?php
                                                    $attachments = json_decode($items['attachments_metadata'], true);
                                                    foreach ($attachments as $attachment): 
                                                        $fileExt = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                                                        $iconClass = getFileIconClass($fileExt);
                                                        $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                                        $isPdf = strtolower($fileExt) === 'pdf';
                                                        $isViewable = $isImage || $isPdf;
                                                    ?>
                                                        <div class="attachment-card">
                                                            <div class="attachment-header">
                                                                <i class="fas <?= $iconClass ?> attachment-icon"></i>
                                                                <span class="attachment-name"><?= htmlspecialchars($attachment['name']) ?></span>
                                                            </div>
                                                            <div class="attachment-meta">
                                                                <span><?= formatFileSize($attachment['size']) ?></span>
                                                                <span><?= date('M j, Y', strtotime($attachment['upload_date'])) ?></span>
                                                            </div>
                                                            <div class="attachment-actions">
                                                                <a href="<?= htmlspecialchars($attachment['path']) ?>" download class="download-btn">
                                                                    <i class="fas fa-download"></i> Download
                                                                </a>
                                                                <?php if ($isViewable): ?>
                                                                    <button class="view-btn" onclick="openAttachmentModal('<?= htmlspecialchars($attachment['path']) ?>', '<?= $fileExt ?>')">
                                                                        <i class="fas fa-eye"></i> View
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Attachment Modal -->
                                        <div id="attachmentModal" class="attachment-modal">
                                            <div class="modal-content">
                                                <span class="close-modal" onclick="closeAttachmentModal()">&times;</span>
                                                <div id="modalContent"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="map-comment-container">
                                    <?php if ($items['program_type'] !== 'Announcement') { ?>
                                    <div class="map-box">
                                        <div class="map" id="map"></div>
                                        <div class="map-details">
                                            <h3><?= htmlspecialchars($items['venue']) ?></h3>
                                            <p><strong>Posted by:</strong> <?= htmlspecialchars($user['fullname']) ?></p>
                                            <p><strong>Posted at:</strong> <?= htmlspecialchars($postedAtTime) ?></p>
                                        </div>
                                    </div>
                                    <!-- map script -->
                                    <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        <?php
                                        // Set default coordinates
                                        $eventLat = 14.64852;
                                        $eventLng = 120.47318;
                                        $venue = 'Event Location';

                                        // Override with database values if available
                                        if (isset($items['latitude']) && isset($items['longitude'])) {
                                            $eventLat = floatval($items['latitude']);
                                            $eventLng = floatval($items['longitude']);
                                        }
                                        $venue = !empty($items['venue']) ? $items['venue'] : 'Event Location';
                                        ?>

                                        if (document.getElementById('map')) {
                                            var map = L.map('map').setView([<?= $eventLat ?>, <?= $eventLng ?>], 15);
                                            
                                            L.tileLayer('https://api.maptiler.com/maps/openstreetmap/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
                                                attribution: ''
                                            }).addTo(map);

                                            // Event location marker
                                            var eventMarker = L.marker([<?= $eventLat ?>, <?= $eventLng ?>]).addTo(map)
                                                .bindPopup(`<p><?= htmlspecialchars($venue, ENT_QUOTES) ?></p>`).openPopup();

                                            // Mapbox Directions configuration
                                             var router = L.Routing.mapbox('pk.eyJ1IjoiY2pzYWJpbm9za2llIiwiYSI6ImNtYm96ZXg2cjF6MjMybXB5cTVzYm5hM2YifQ.QZX2EQtOCxy5H75jdm_afA', {
                                                profile: 'mapbox/driving',
                                                alternatives: true
                                            });

                                            var routingControl = L.Routing.control({
                                                router: router,
                                                waypoints: [
                                                    L.latLng(<?= $eventLat ?>, <?= $eventLng ?>) 
                                                ],
                                                routeWhileDragging: true,
                                                showAlternatives: true,
                                                addWaypoints: false,
                                                draggableWaypoints: false,
                                                fitSelectedRoutes: true,
                                                show: false,
                                                collapsible: true,
                                                position: 'topright',
                                                createMarker: function() { return null; },
                                                lineOptions: {
                                                    styles: [{color: '#3a7bd5', opacity: 0.7, weight: 5}]
                                                }
                                            }).addTo(map);

                                            // Custom control for getting directions
                                            var routeControl = L.Control.extend({
                                                options: {
                                                    position: 'topright'
                                                },
                                                onAdd: function(map) {
                                                    var container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
                                                    var link = L.DomUtil.create('a', 'leaflet-control-get-route', container);
                                                    link.href = '#';
                                                    link.title = 'Get Directions';
                                                    link.innerHTML = '<span>🚗</span>';
                                                    
                                                    L.DomEvent.on(link, 'click', function(e) {
                                                        L.DomEvent.stop(e);
                                                        
                                                        map.locate({setView: false, maxZoom: 16}).on('locationfound', function(e) {
                                                            routingControl.setWaypoints([
                                                                L.latLng(e.latitude, e.longitude),
                                                                L.latLng(<?= $eventLat ?>, <?= $eventLng ?>)
                                                            ]);
                                                            routingControl.show();

                                                            L.marker([e.latitude, e.longitude])
                                                                .addTo(map)
                                                                .bindPopup("Your Location")
                                                                .openPopup();

                                                        }).on('locationerror', function(e) {
                                                            alert("Could not get your location. Please enable location services.");
                                                        });
                                                    });
                                                    
                                                    return container;
                                                }
                                            });
                                            
                                            map.addControl(new routeControl());

                                            if (typeof L.control.locate === 'function') {
                                                L.control.locate({
                                                    position: 'topright',
                                                    flyTo: true,
                                                    showPopup: false,
                                                    strings: {
                                                        title: "Show me where I am"
                                                    }
                                                }).addTo(map);
                                            }
                                        }
                                    });
                                    </script>
                                    <?php } ?>
                                    <!-- Comment section -->
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
        </div>
    </main>
    <script type="text/javascript" src="events.js"></script>
    <script>
        document.getElementById('qrForm').addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent form submission

            const text = document.getElementById('text').value;
            const qrCodeContainer = document.getElementById('qrcode');

            // Clear any existing QR code
            qrCodeContainer.innerHTML = '';

            // Create a canvas element
            const canvas = document.createElement('canvas');
            qrCodeContainer.appendChild(canvas);

            // Generate the QR code on the canvas
            QRCode.toCanvas(canvas, text, {
                width: 300,
                margin: 1,
                color: {
                    dark: '#000000', // Black
                    light: '#ffffff' // White
                }
            }, function (error) {
                if (error) {
                    console.error(error);
                    return;
                }

                // Add the logo to the center of the QR code
                const ctx = canvas.getContext('2d');
                const logo = new Image();
                logo.src = 'images/mangrow-logo.png'; // Replace with the path to your logo image
                logo.onload = function () {
                    const logoSize = 60; // Size of the logo
                    const x = (canvas.width - logoSize) / 2;
                    const y = (canvas.height - logoSize) / 2;
                    ctx.drawImage(logo, x, y, logoSize, logoSize);
                };
            });
        });

        document.querySelectorAll('.attend-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const response = await fetch('attend_event.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    location.reload(); // Refresh to see updated count
                } else {
                    alert('Error updating attendance');
                }
            });
        });
    </script>
    <!-- img bg script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        const thumbnails = document.querySelectorAll('.event-thumbnail img');
        
        thumbnails.forEach(img => {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const eventHeader = img.closest('.event-header');
            
            img.onload = function() {
                canvas.width = 16;
                canvas.height = 16;
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                const color = getDominantColor(canvas, ctx);
                applyGradientBackground(eventHeader, color);
            };
            
            if (img.complete) img.onload();
        });
        
        function getDominantColor(canvas, ctx) {
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;
            const colorCount = {};
            let maxCount = 0;
            let dominantColor = {r: 0, g: 0, b: 0};
            
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
            
            return adjustColor(dominantColor);
        }
        
        function adjustColor(color) {
            let [h, s, l] = rgbToHsl(color.r, color.g, color.b);
            
            // Make colors slightly more vibrant but maintain brightness
            s = Math.min(s * 1.2, 100);
            l = Math.min(l * 1.1, 85);
            
            return hslToRgb(h, s, l);
        }
        
        function applyGradientBackground(element, color) {
            const baseColor = 'rgba(0,0,0, 0.7)';
            const creamColor = 'rgb(255, 253, 246)';
            const dominantColor = `rgb(${color.r}, ${color.g}, ${color.b})`;
            
            // Blended gradient: dominant color fades smoothly into cream
            element.style.background = `linear-gradient(to bottom, 
                ${baseColor} 0%, 
                ${dominantColor} 20%, 
                ${creamColor} 70%)`;

            // Optional: add smooth transition when colors change
            element.style.transition = 'background 0.5s ease';
        }
        
        // Color conversion functions remain the same
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
    <!--filter script-->
    <script>
        //filter events
        document.addEventListener('DOMContentLoaded', function() {
        // Filter elements
        const filterType = document.getElementById('filter-type');
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');
        const applyBtn = document.getElementById('apply-filters');
        const resetBtn = document.getElementById('reset-filters');
        const eventCards = document.querySelectorAll('.event-details');
        
        // Apply filters
        applyBtn.addEventListener('click', function() {
            const typeValue = filterType.value;
            const startDateValue = startDate.value;
            const endDateValue = endDate.value;
            
            let hasVisibleCards = false;
            
            eventCards.forEach(card => {
                const cardType = card.dataset.type;
                const cardDate = card.dataset.date;
                
                let matches = true;
                
                // Check type filter
                if (typeValue !== 'all' && cardType !== typeValue) {
                    matches = false;
                }
                
                // Check date range
                if (startDateValue && cardDate < startDateValue) {
                    matches = false;
                }
                if (endDateValue && cardDate > endDateValue) {
                    matches = false;
                }
                
                // Show/hide card
                card.style.display = matches ? '' : 'none';
                if (matches) hasVisibleCards = true;
            });
            
            // Show no results message if needed
            const noResultsMsg = document.getElementById('no-results-message');
            if (noResultsMsg) {
                noResultsMsg.style.display = hasVisibleCards ? 'none' : 'block';
            }
        });
        
        // Reset filters
        resetBtn.addEventListener('click', function() {
            filterType.value = 'all';
            startDate.value = '';
            endDate.value = '';
            
            eventCards.forEach(card => {
                card.style.display = '';
            });
            
            // Hide no results message
            const noResultsMsg = document.getElementById('no-results-message');
            if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        });
    });
    </script>
    <!-- mobile responsive nav script-->
    <script>
        //change events-nav to events-nav.hidden when media(max-width: 800px) is active
        document.addEventListener('DOMContentLoaded', function() {
            const filterPanel = document.querySelector('.events-nav');
            const mediaQuery = window.matchMedia('(max-width: 800px)');
            
            function handleMediaChange(e) {
                if (e.matches) {
                    filterPanel.classList.add('hidden');
                } else {
                    filterPanel.classList.remove('hidden');
                }
            }
            
            // Initial check
            handleMediaChange(mediaQuery);
            
            // Listen for changes
            mediaQuery.addEventListener('change', handleMediaChange);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector('.filter-toggle-btn') || createToggleButton();
            const overlay = document.querySelector('.filter-overlay') || createOverlay();
            const filterPanel = document.querySelector('.events-nav');
            
            let isDragging = false;
            let startX, startY, moveX, moveY;
            const dragThreshold = 5;
            const btnRadius = 25; // Half of button width/height (50px/2)

            updateButtonState();

            // Touch/Mouse Down - Start interaction
            btn.addEventListener('mousedown', startInteraction);
            btn.addEventListener('touchstart', startInteraction, { passive: false });

            function startInteraction(e) {
                e.preventDefault();
                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;
                
                startX = clientX;
                startY = clientY;
                moveX = clientX;
                moveY = clientY;
                
                document.addEventListener('mousemove', trackMovement);
                document.addEventListener('touchmove', trackMovement, { passive: false });
                document.addEventListener('mouseup', endInteraction);
                document.addEventListener('touchend', endInteraction);
            }

            function trackMovement(e) {
                const clientX = e.clientX || e.touches[0].clientX;
                const clientY = e.clientY || e.touches[0].clientY;
                
                // Calculate distance moved
                const dx = Math.abs(clientX - startX);
                const dy = Math.abs(clientY - startY);
                
                if (dx > dragThreshold || dy > dragThreshold) {
                    isDragging = true;
                    btn.style.cursor = 'grabbing';
                    
                    // Calculate new position with boundary checks
                    const deltaX = clientX - moveX;
                    const deltaY = clientY - moveY;
                    
                    let newLeft = (parseInt(window.getComputedStyle(btn).left) || 0) + deltaX;
                    let newTop = (parseInt(window.getComputedStyle(btn).top) || 0) + deltaY;
                    
                    // Boundary checks - keep at least half the button visible
                    newLeft = Math.max(-btnRadius, Math.min(newLeft, window.innerWidth - btnRadius));
                    newTop = Math.max(-btnRadius, Math.min(newTop, window.innerHeight - btnRadius));
                    
                    btn.style.left = `${newLeft}px`;
                    btn.style.top = `${newTop}px`;
                    
                    moveX = clientX;
                    moveY = clientY;
                }
            }

            function endInteraction(e) {
                document.removeEventListener('mousemove', trackMovement);
                document.removeEventListener('touchmove', trackMovement);
                document.removeEventListener('mouseup', endInteraction);
                document.removeEventListener('touchend', endInteraction);
                
                if (!isDragging) {
                    filterPanel.classList.toggle('visible');
                    overlay.style.display = filterPanel.classList.contains('visible') ? 'block' : 'none';
                    updateButtonState();
                }
                
                isDragging = false;
                btn.style.cursor = 'grab';
                
                // Snap to nearest edge if partially off-screen
                snapToEdge();
            }

            function snapToEdge() {
                const btnRect = btn.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                let newLeft = parseInt(window.getComputedStyle(btn).left) || 0;
                let newTop = parseInt(window.getComputedStyle(btn).top) || 0;
                
                // Check left/right edges
                if (btnRect.left < 0) {
                    newLeft = -btnRadius + 5; // Slightly more visible when docked
                } else if (btnRect.right > viewportWidth) {
                    newLeft = viewportWidth - btnRadius - 5;
                }
                
                // Check top/bottom edges
                if (btnRect.top < 0) {
                    newTop = -btnRadius + 5;
                } else if (btnRect.bottom > viewportHeight) {
                    newTop = viewportHeight - btnRadius - 5;
                }
                
                // Apply new position with smooth transition
                btn.style.transition = 'left 0.2s ease, top 0.2s ease';
                btn.style.left = `${newLeft}px`;
                btn.style.top = `${newTop}px`;
                
                // Remove transition after animation completes
                setTimeout(() => {
                    btn.style.transition = '';
                }, 200);
            }

            // Close panel when clicking overlay
            overlay.addEventListener('click', function() {
                filterPanel.classList.remove('visible');
                overlay.style.display = 'none';
                updateButtonState();
            });

            // Update button style based on filter state
            function updateButtonState() {
                if (filterPanel.classList.contains('visible')) {
                    // When filter is visible
                    btn.innerHTML = '<i class="fas fa-times"></i>';
                    btn.style.backgroundColor = 'var(--accent-clr)';
                    btn.style.color = 'var(--base-clr)';
                    btn.style.boxShadow = '0 0 0 2px var(--base-clr)';
                } else {
                    // When filter is hidden
                    btn.innerHTML = '<i class="fas fa-bars"></i>';
                    btn.style.backgroundColor = 'var(--base-clr)';
                    btn.style.color = 'var(--accent-clr)';
                    btn.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
                }
            }

            // Helper functions to create elements if they don't exist
            function createToggleButton() {
                const button = document.createElement('button');
                button.className = 'filter-toggle-btn';
                button.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.appendChild(button);
                return button;
            }

            function createOverlay() {
                const overlay = document.createElement('div');
                overlay.className = 'filter-overlay';
                document.body.appendChild(overlay);
                return overlay;
            }
        });
    </script>
    <!-- featured event countdown script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function updateAllCountdowns() {
            const timers = document.querySelectorAll('.countdown-timer[data-start-date][data-end-date]');
            
            timers.forEach(timer => {
                const startDate = new Date(timer.dataset.startDate);
                const endDate = new Date(timer.dataset.endDate);
                const now = new Date();
                
                // Event hasn't started yet
                if (now < startDate) {
                    const timeUntilStart = startDate - now;
                    
                    // Calculate time components until start
                    const days = Math.floor(timeUntilStart / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeUntilStart % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeUntilStart % (1000 * 60 * 60)) / (1000 * 60));
                    
                    // Format start countdown
                    let displayText;
                    if (days > 1) {
                        displayText = `Starts in ${days} days`;
                    } else if (days === 1) {
                        displayText = 'Starts tomorrow';
                    } else if (hours > 0) {
                        displayText = `Starts in ${hours}h ${minutes}m`;
                    } else {
                        displayText = `Starts in ${minutes}m`;
                    }
                    
                    timer.textContent = displayText;
                    timer.classList.remove('event-ended', 'event-started');
                    timer.classList.add('event-upcoming');
                }
                // Event has started but not ended
                else if (now < endDate) {
                    const timeLeft = endDate - now;
                    
                    // Calculate time components until end
                    const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    
                    // Format end countdown
                    let displayText;
                    if (days > 1) {
                        displayText = `Ends in ${days} days`;
                    } else if (days === 1) {
                        displayText = `Ends in 1 day ${hours}h`;
                    } else if (hours > 0) {
                        displayText = `Ends in ${hours}h ${minutes}m`;
                    } else {
                        displayText = `Ends in ${minutes}m`;
                    }
                    
                    timer.textContent = displayText;
                    timer.classList.remove('event-ended', 'event-upcoming');
                    timer.classList.add('event-started');
                }
                // Event has ended
                else {
                    timer.textContent = 'Event ended';
                    timer.classList.remove('event-upcoming', 'event-started');
                    timer.classList.add('event-ended');
                }
            });
        }

        // Initial update
        updateAllCountdowns();
        
        // Update every minute
        setInterval(updateAllCountdowns, 60000);
    });
    </script>
    <!--timestamp script-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Time ago calculation function
            function timeAgo(timestamp) {
                const now = new Date();
                const past = new Date(timestamp);
                const seconds = Math.floor((now - past) / 1000);
                
                let interval = Math.floor(seconds / 31536000);
                if (interval >= 1) return "Posted " + interval + " year" + (interval === 1 ? "" : "s") + " ago";
                
                interval = Math.floor(seconds / 2592000);
                if (interval >= 1) return "Posted " + interval + " month" + (interval === 1 ? "" : "s") + " ago";
                
                interval = Math.floor(seconds / 86400);
                if (interval >= 1) return "Posted " + interval + " day" + (interval === 1 ? "" : "s") + " ago";
                
                interval = Math.floor(seconds / 3600);
                if (interval >= 1) return "Posted " + interval + " hour" + (interval === 1 ? "" : "s") + " ago";
                
                interval = Math.floor(seconds / 60);
                if (interval >= 1) return "Posted " + interval + " minute" + (interval === 1 ? "" : "s") + " ago";
                
                return "Posted just now";
            }

            // Apply to all time-ago elements
            const timeAgoElements = document.querySelectorAll('.event-time-ago');
            timeAgoElements.forEach(el => {
                const timestamp = el.getAttribute('data-timestamp');
                if (timestamp) {
                    el.textContent = timeAgo(timestamp);
                }
            });
        });
    </script>
    <!--see more script-->
    <script>
        function toggleDescription(id, button) {
            const desc = document.getElementById(id);
            const isCollapsed = desc.classList.contains('collapsed');
            
            if (isCollapsed) {
                desc.classList.remove('collapsed');
                button.textContent = 'See Less';
                button.classList.add('expanded');
            } else {
                desc.classList.add('collapsed');
                button.textContent = 'See More';
                button.classList.remove('expanded');
            }
        }

        // Auto-hide "See More" button if content is short
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.event-description').forEach(desc => {
                const button = desc.nextElementSibling;
                if (desc.scrollHeight <= desc.clientHeight) {
                    button.style.display = 'none';
                }
            });
        });
    </script>
    <!-- featured script -->
    <script>
        function toggleFeaturedStatus(icon) {
            const eventId = icon.getAttribute('data-event-id');
            const isFeatured = icon.classList.contains('featured-active');
            const endDate = icon.getAttribute('data-end-date');
    
            let featuredUntil = '7 days from now';
            if (endDate) {
                const dateObj = new Date(endDate);
                featuredUntil = dateObj.toLocaleDateString('en-US', { 
                    month: 'long', 
                    day: 'numeric', 
                    year: 'numeric' 
                });
                
                // If end date is more than 7 days away, show "7 days from now" instead
                const sevenDaysLater = new Date();
                sevenDaysLater.setDate(sevenDaysLater.getDate() + 7);
                if (dateObj > sevenDaysLater) {
                    featuredUntil = '7 days from now';
                }
            }
            
            const confirmMessage = isFeatured 
                ? "Are you sure you want to unfeature this event? It will no longer appear in featured sections."
                : `Are you sure you want to feature this event? It will be prominently displayed until ${featuredUntil}.`;

            const action = isFeatured ? "Unfeature" : "Feature";
            
            // Show confirmation dialog
            if (!confirm(`${action} Event?\n\n${confirmMessage}`)) {
                return; // User canceled
            }
            
            const newStatus = isFeatured ? 'Normal' : 'Featured';
            
            // Show loading state
            icon.classList.add('fa-spinner', 'fa-spin');
            icon.classList.remove('fa-star');
            
            // Send AJAX request
            fetch('update_featured_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_id: eventId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    icon.classList.toggle('featured-active');
                    // Show flash message
                    showFlashMessage(
                        `Event ${newStatus === 'Featured' ? 'featured for 7 days' : 'unfeatured'}`,
                        newStatus === 'Featured' ? 'success' : 'info'
                    );
                    // Reload featured section if needed
                    loadFeaturedEvents();
                    window.location.reload();
                } else {
                    showFlashMessage(data.message || 'Failed to update status', 'error');
                    window.location.reload();
                }
            })
            .catch(error => {
                showFlashMessage('Network error', 'error');
                window.location.reload();
            })
            .finally(() => {
                // Restore icon
                if (icon.isConnected) {
                    icon.classList.remove('fa-spinner', 'fa-spin');
                    icon.classList.add('fa-star');
                }
            });
        }

        // Rest of your existing functions remain the same...
        function loadFeaturedEvents() {
            // Implement your featured events reload logic here
        }

        function showFlashMessage(message, type) {
            const flashContainer = document.querySelector('.flash-container');
            if (!flashContainer) return;
            
            const flashMessage = document.createElement('div');
            flashMessage.className = `flash-message flash-${type}`;
            flashMessage.textContent = message;
            
            flashContainer.appendChild(flashMessage);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                flashMessage.remove();
            }, 5000);
        }
    </script>
    <!-- event actions script -->
    <script>
        // Attend Form Submission (updated)
        document.querySelectorAll('.attend-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Check if user is logged in (via PHP session)
                const isLoggedIn = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;

                if (!isLoggedIn) {
                    // Encode all session data as URL parameters
                    const redirectParams = new URLSearchParams({
                        redirect: window.location.pathname + window.location.search,
                        flash_status: 'error',
                        flash_msg: 'Please login first'
                    });
                    
                    window.location.href = `login.php?${redirectParams.toString()}`;
                    return;
                }

                
                const formData = new FormData(this);
                const btn = this.querySelector('.attend-btn');
                const originalText = btn.innerHTML;
                
                // Show loading state
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;
                
                try {
                    const response = await fetch('attend_event.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            // Update button text and count without reload
                            const countElement = this.querySelector('.attendee-count');
                            btn.innerHTML = `<i class="fas fa-calendar-check"></i> <span class="btn-text">${data.isAttending ? 'Not Interested' : 'Interested'}</span>`;
                            countElement.innerHTML = `<i class="fas fa-users"></i> ${data.newCount} interested`;
                            
                            // If there was a message (like for limited capacity events)
                            if (data.message) {
                                alert(data.message);
                            }
                        } else {
                            btn.innerHTML = originalText;
                            alert(data.message || 'Error updating attendance');
                        }
                    } else {
                        btn.innerHTML = originalText;
                        alert('Error updating attendance');
                    }
                } catch (error) {
                    btn.innerHTML = originalText;
                    alert('Network error');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        // Handle pin comment buttons
        document.addEventListener('click', function(e) {
            if (e.target.closest('.pin-comment-btn')) {
                const button = e.target.closest('.pin-comment-btn');
                const commentId = button.dataset.commentId;
                const isPinned = button.dataset.pinned === '1';
                const newPinStatus = isPinned ? 0 : 1;
                const eventContainer = document.querySelector('.event-details');
                const eventId = eventContainer.dataset.eventId;
                
                // Show loading state
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isPinned ? 'Unpinning...' : 'Pinning...');
                button.disabled = true;
                
                // Add overlay loading to comments section
                const commentsSection = document.getElementById(`comments-${eventId}`);
                if (commentsSection) {
                    commentsSection.style.position = 'relative';
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.className = 'comments-loading-overlay';
                    loadingOverlay.innerHTML = '<div class="spinner"></div>';
                    commentsSection.appendChild(loadingOverlay);
                }
                
                fetch('pin_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `comment_id=${commentId}&pin_status=${newPinStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.needs_confirmation) {
                        // Remove loading overlay
                        if (commentsSection) {
                            const overlay = commentsSection.querySelector('.comments-loading-overlay');
                            if (overlay) overlay.remove();
                        }
                        
                        // Ask user to confirm
                        button.innerHTML = originalText;
                        button.disabled = false;
                        
                        if (confirm(data.message + '\n\nClick OK to replace the pinned comment.')) {
                            // User confirmed - try again with confirmation flag
                            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isPinned ? 'Unpinning...' : 'Pinning...');
                            button.disabled = true;
                            
                            // Re-add loading overlay
                            if (commentsSection) {
                                const loadingOverlay = document.createElement('div');
                                loadingOverlay.className = 'comments-loading-overlay';
                                loadingOverlay.innerHTML = '<div class="spinner"></div>';
                                commentsSection.appendChild(loadingOverlay);
                            }
                            
                            return fetch('pin_comment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `comment_id=${commentId}&pin_status=${newPinStatus}&confirmed=true`
                            }).then(response => response.json());
                        } else {
                            // User cancelled
                            return Promise.reject('Cancelled by user');
                        }
                    }
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        // Update the entire comments section
                        const commentsContainer = eventContainer.querySelector('.comments-list');
                        if (commentsContainer && data.comments_html) {
                            commentsContainer.innerHTML = data.comments_html;
                        }
                        
                        // Reinitialize any necessary event listeners
                        initPinButtons();
                    } else {
                        alert(data.message || 'Failed to update pin status');
                    }
                })
                .catch(error => {
                    if (error !== 'Cancelled by user') {
                        alert('Network error');
                    }
                })
                .finally(() => {
                    // Remove loading overlay
                    if (commentsSection) {
                        const overlay = commentsSection.querySelector('.comments-loading-overlay');
                        if (overlay) overlay.remove();
                    }
                    
                    button.disabled = false;
                });
            }
        });

        // Function to initialize pin buttons
        function initPinButtons() {
            // This will be called after comments are refreshed
            document.querySelectorAll('.pin-comment-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // The event listener above will handle it
                });
            });
        }

        // Toggle Comments
        function toggleComments(eventId) {
            const eventContainer = document.querySelector(`[data-event-id="${eventId}"]`);
            const mapCommentContainer = eventContainer.querySelector('.map-comment-container');
            
            // Check if comment section already exists
            let commentSection = mapCommentContainer.querySelector('.comments-section');
            
            if (commentSection) {
                // Toggle visibility if comments already loaded
                const isShowing = commentSection.classList.toggle('show');
                
                if (isShowing) {
                    // Focus on textarea after a brief delay
                    setTimeout(() => {
                        const textarea = commentSection.querySelector('textarea');
                        if (textarea) {
                            textarea.focus({ preventScroll: true }); // Focus without page jump
                            // Smooth scroll to textarea if needed
                            textarea.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center',
                                inline: 'nearest'
                            });
                        }
                    }, 50);
                }
            } else {
                // Create loading indicator
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'comments-loading';
                loadingDiv.innerHTML = '<div class="spinner"></div> Loading comments...';
                mapCommentContainer.appendChild(loadingDiv);
                
                // Load comments via AJAX
                fetch(`load_comments.php?event_id=${eventId}`)
                    .then(response => response.text())
                    .then(html => {
                        loadingDiv.remove();
                        
                        // Create container for comments
                        const container = document.createElement('div');
                        container.id = `comments-${eventId}`;
                        container.className = 'comments-section show';
                        container.innerHTML = html;
                        
                        // Add it to the map-comment-container after the map
                        mapCommentContainer.appendChild(container);
                        
                        // Initialize comment form submission
                        initCommentForm(eventId);
                        
                        // Focus on textarea after a brief delay
                        setTimeout(() => {
                            const textarea = container.querySelector('textarea');
                            if (textarea) {
                                textarea.focus({ preventScroll: true });
                                textarea.scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'end', // so it focus on showing the pinned comment
                                    inline: 'nearest'
                                });
                            }
                        }, 50);
                    })
                    .catch(error => {
                        loadingDiv.remove();
                        alert('Error loading comments');
                    });
            }
        }

        function initCommentForm(eventId) {
            const form = document.getElementById(`comment-form-${eventId}`);
            if (!form) return;

            // File input handling
            const fileInput = form.querySelector('.comment-image-input');
            const fileNameDisplay = form.querySelector('.file-name');
            const fileClearBtn = form.querySelector('.file-clear');
            const previewContainer = form.querySelector('.image-preview-container');
            const imagePreview = form.querySelector(`#image-preview-${eventId}`);
            const removePreviewBtn = form.querySelector('.remove-preview');
            const commentTextarea = form.querySelector('textarea[name="comment_text"]');
            
            // Restrict file types
            fileInput.setAttribute('accept', 'image/jpeg, image/png, image/gif, image/webp');
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    
                    if (!validTypes.includes(file.type)) {
                        alert('Only JPG, PNG, GIF, and WEBP images are allowed');
                        this.value = '';
                        fileNameDisplay.textContent = '';
                        if (fileClearBtn) fileClearBtn.style.display = 'none';
                        if (previewContainer) previewContainer.style.display = 'none';
                        return;
                    }
                    
                    fileNameDisplay.textContent = file.name;
                    if (fileClearBtn) fileClearBtn.style.display = 'inline-block';
                    
                    // Show image preview
                    if (previewContainer && imagePreview) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.style.display = 'block';
                            previewContainer.style.display = 'block';
                        }
                        reader.readAsDataURL(file);
                    }
                } else {
                    fileNameDisplay.textContent = '';
                    if (fileClearBtn) fileClearBtn.style.display = 'none';
                    if (previewContainer) previewContainer.style.display = 'none';
                }
            });

            // Clear button functionality
            if (fileClearBtn) {
                fileClearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.value = '';
                    fileNameDisplay.textContent = '';
                    this.style.display = 'none';
                    if (previewContainer) previewContainer.style.display = 'none';
                });
            }

            // Remove preview button functionality
            if (removePreviewBtn) {
                removePreviewBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.value = '';
                    fileNameDisplay.textContent = '';
                    if (fileClearBtn) fileClearBtn.style.display = 'none';
                    if (previewContainer) previewContainer.style.display = 'none';
                });
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Client-side validation
                const commentText = commentTextarea.value.trim();
                const hasFile = fileInput.files.length > 0;
                
                if (!commentText && !hasFile) {
                    alert('Please write a comment or attach an image');
                    commentTextarea.focus();
                    return;
                }

                const formData = new FormData(this);
                
                // Add loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner"></span> Posting...';
                submitBtn.disabled = true;
                
                // Hide preview when submitting
                if (previewContainer) previewContainer.style.display = 'none';
                
                fetch('post_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear everything
                        form.reset();
                        fileNameDisplay.textContent = '';
                        if (fileClearBtn) fileClearBtn.style.display = 'none';
                        fileInput.value = '';
                        if (previewContainer) previewContainer.style.display = 'none';
                        
                        // Add comment to list
                        const commentsList = document.querySelector(`#comments-${eventId} .comments-list`);
                        const noCommentsMsg = document.querySelector(`#comments-${eventId} .no-comments`);
                        
                        if (noCommentsMsg) noCommentsMsg.remove();
                        
                        if (commentsList) {
                            const commentElement = createCommentElement(data.comment);
                            commentsList.insertBefore(commentElement, commentsList.firstChild);
                        }
                    } else {
                        alert(data.message || 'Error posting comment');
                    }
                })
                .catch(error => {
                    alert('Network error');
                })
                .finally(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });
        }


        function createCommentElement(comment) {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment';
            
            let imageHtml = '';
            if (comment.attachment_photo) {
                imageHtml = `
                <div class="comment-image">
                    <img src="${comment.attachment_photo}" alt="Comment image">
                </div>`;
            }
            
            commentDiv.innerHTML = `
            <div class="comment-author" data-comment-id="${comment.comment_id}">
                <img src="${comment.profile_thumbnail}" alt="Profile" class="comment-avatar">
                <span class="comment-author-name">${comment.fullname}</span>
                <span class="comment-date">${formatCommentDate(comment.created_at)}</span>
                ${(comment.commenter_id == <?php echo json_encode($_SESSION['user_id'] ?? null); ?>) ? `
                <button class="pin-comment-btn" data-comment-id="${comment.comment_id}" data-pinned="${comment.pinned_status}">
                    <i class="fas fa-thumbtack"></i> ${comment.pinned_status ? 'Unpin' : 'Pin'}
                </button>` : ''}
            </div>
            <div class="comment-text">${escapeHtml(comment.comment).replace(/\n/g, '<br>')}</div>
            ${imageHtml}`;
            
            return commentDiv;
        }

        function formatCommentDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Share Event
        function shareEvent(eventId) {
            const eventUrl = `${window.location.origin}/event_details.php?event_id=${eventId}`;
            
            if (navigator.share) {
                // Use Web Share API if available
                navigator.share({
                    title: 'Check out this event',
                    text: 'I found this interesting event you might like',
                    url: eventUrl
                }).catch(err => {
                    copyToClipboard(eventUrl);
                });
            } else {
                // Fallback to copy to clipboard
                copyToClipboard(eventUrl);
            }
        }

        function copyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            // Show feedback
            const originalTitle = document.title;
            document.title = 'Link copied!';
            setTimeout(() => document.title = originalTitle, 2000);
        }
    </script>
    <script>
        // Attachment Modal Functions
        function openAttachmentModal(filePath, fileExt) {
            const modal = document.getElementById('attachmentModal');
            const modalContent = document.getElementById('modalContent');
            const ext = fileExt.toLowerCase();
            
            // Clear previous content
            modalContent.innerHTML = '';
            
            // Create appropriate viewer based on file type
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                // Image viewer
                const img = document.createElement('img');
                img.src = filePath;
                img.className = 'attachment-preview';
                img.alt = 'Attachment Preview';
                modalContent.appendChild(img);
            } else if (ext === 'pdf') {
                // PDF viewer
                const iframe = document.createElement('iframe');
                iframe.src = filePath;
                iframe.className = 'pdf-viewer';
                modalContent.appendChild(iframe);
            } else {
                // Unsupported file type
                const message = document.createElement('div');
                message.className = 'unsupported-file';
                message.innerHTML = `
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #ff9800; margin-bottom: 15px;"></i>
                    <h3>Preview Not Available</h3>
                    <p>This file type cannot be previewed in the browser.</p>
                    <p>Please download the file to view it.</p>
                    <a href="${filePath}" download class="download-btn" style="margin-top: 15px; display: inline-block;">
                        <i class="fas fa-download"></i> Download File
                    </a>
                `;
                modalContent.appendChild(message);
            }
            
            // Show modal
            modal.style.display = 'block';
            
            // Close modal when clicking outside content
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeAttachmentModal();
                }
            });
        }

        function closeAttachmentModal() {
            document.getElementById('attachmentModal').style.display = 'none';
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAttachmentModal();
            }
        });

        // Only show attachments section for cross-barangay events with attachments
        document.addEventListener('DOMContentLoaded', function() {
            const attachmentsSection = document.querySelector('.attachments-section');
            if (attachmentsSection) {
                // If there are no attachments, hide the section
                const attachmentsList = attachmentsSection.querySelector('.attachments-list');
                if (!attachmentsList || attachmentsList.children.length === 0) {
                    attachmentsSection.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>