<!--
<?php
    session_start();

    $focusEventId = isset($_GET['focus_event']) ? intval($_GET['focus_event']) : 0;

    if ($focusEventId > 0) {
        $query = "SELECT * FROM eventstbl WHERE event_id = $focusEventId";
    }

    include 'database.php';
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

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
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
    <title>My Events</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="events_2.0_.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
                    <li class="active"><a href="myevents.php" tabindex="-1">My Events</a></li>
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
        <!-- events page container-->
        <div class="events-container">
            <div class="events-filter">
                <div class="quick-actions-section">
                    <h2 class="filter-title">Quick Actions</h2>
                    <div class="quick-actions">
                        <?php if(isset($_SESSION['accessrole'])): ?>
                            <?php if(!($_SESSION['accessrole'] == 'Resident' && ($_SESSION['organization'] == 'N/A' || $_SESSION['organization'] == ''))): ?>
                                <button class="action-button create-button" onclick="window.location.href = `create_event.php?organization=${encodeURIComponent('<?php echo $_SESSION['organization']; ?>')}`;">
                                    <i class="fas fa-plus"></i> Create New Event/Activity
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <button class="action-button eventsback-button" onclick="window.location.href='events.php'">
                            <i class="fas fa-user"></i> Back
                        </button>
                    </div>
                </div>
                <div class="filter-section">
                    <h2 class="filter-title">Filters</h2>
                    <div class="filter-group">
                        <label for="filter-program" class="filter-label">Program Type</label>
                        <select id="filter-program" class="filter-select">
                            <option value="all">All Types</option>
                            <?php
                            $typesQuery = "SELECT DISTINCT program_type FROM eventstbl ORDER BY program_type";
                            $typesResult = mysqli_query($connection, $typesQuery);
                            while($type = mysqli_fetch_assoc($typesResult)) {
                                echo '<option value="'.htmlspecialchars($type['program_type']).'">'.htmlspecialchars($type['program_type']).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-type" class="filter-label">Event Type</label>
                        <select id="filter-type" class="filter-select">
                            <option value="all">All Events</option>
                            <?php
                            $typesQuery = "SELECT DISTINCT event_type FROM eventstbl WHERE program_type != 'Announcement' ORDER BY event_type";
                            $typesResult = mysqli_query($connection, $typesQuery);
                            while($type = mysqli_fetch_assoc($typesResult)) {
                                echo '<option value="'.htmlspecialchars($type['event_type']).'">'.htmlspecialchars($type['event_type']).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter-status" class="filter-label">Event Status</label>
                        <select id="filter-status" class="filter-select">
                            <option value="all">All Statuses</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="upcoming">Upcoming</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <div class="date-range-container">
                            <div class="date-input-wrapper">
                                <input type="date" id="start-date" class="date-input">
                                <span class="date-separator">to</span>
                                <input type="date" id="end-date" class="date-input">
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="filter-city" class="filter-label">City/Municipality</label>
                        <select id="filter-city" class="filter-select" onchange="updateBarangayDropdown(this.value)">
                            <option value="all">All Cities/Municipalities</option>
                            <?php
                            $citiesQuery = "SELECT DISTINCT city_municipality FROM barangaytbl ORDER BY city_municipality";
                            $citiesResult = mysqli_query($connection, $citiesQuery);
                            while($city = mysqli_fetch_assoc($citiesResult)) {
                                echo '<option value="'.htmlspecialchars($city['city_municipality']).'">'.htmlspecialchars($city['city_municipality']).'</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="filter-barangay" class="filter-label">Barangay</label>
                        <select id="filter-barangay" class="filter-select" disabled>
                            <option value="all">All Barangays</option>
                            <!-- Will be populated via JavaScript -->
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button id="apply-filters" class="filter-button apply-button">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button id="reset-filters" class="filter-button reset-button">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            <div class="events-content">
                <?php 
                $currentDate = date("Y-m-d");
                $query = "SELECT * FROM eventstbl 
                        WHERE author = ?
                        ORDER BY posted_at DESC";
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Resident' && ($_SESSION['organization'] == 'N/A' || $_SESSION['organization'] == '')) {
                    // If the resident w/o organization is logged in, show the events they are interested going to
                    $query = "SELECT e.* FROM eventstbl e 
                              JOIN attendeestbl a ON e.event_id = a.event_id 
                              WHERE a.account_id = ".$_SESSION['user_id']."
                              ORDER BY posted_at DESC;";
                } elseif(isset($_SESSION['user_id'])) {
                    // If the user is a Barangay Official, show all events they made as well as the events made by other users from the same barangay as Barangay Official
                    $barangay = $_SESSION['barangay'] ?? '';
                    $city_municipality = $_SESSION['city_municipality'] ?? '';
                    $query = "SELECT * FROM eventstbl 
                              WHERE (author = ".$_SESSION['user_id']." OR (barangay = '$barangay' AND city_municipality = '$city_municipality'))
                              ORDER BY posted_at DESC;";
                }
                $result = mysqli_query($connection,$query);
                if (mysqli_num_rows($result) > 0) {
                    while($items = mysqli_fetch_assoc($result)) {
                        //display the event dates with the format "F j, Y" (ex. "May 15, 2025")
                        $startDate = date("F j, Y", strtotime($items['start_date']));
                        $endDate = date("F j, Y", strtotime($items['end_date']));
                        $postedAt = date("F j, Y", strtotime($items['posted_at']));

                        //get the account_id of the user who posted the event
                        $accountId = $items['author'];
                        //then create a query that will get the name and profile of the user who posted the event
                        $userQuery = "SELECT fullname, profile, profile_thumbnail FROM accountstbl WHERE account_id = '$accountId'";
                        $userResult = mysqli_query($connection, $userQuery);
                        $user = mysqli_fetch_assoc($userResult);
                        ?>
                        <div id="no-results-message" class="no-events-message" style="display: <?php echo mysqli_num_rows($result) > 0 ? 'none' : 'block'; ?>;">
                            <i class="fas fa-calendar-times"></i>
                            <h3>No events available at the moment</h3>
                            <p>There are currently no events matching your criteria.</p>
                        </div>
                        <div class="event-details <?= htmlspecialchars($items['featured_status']) ?>" data-event-id="<?= $items['event_id'] ?>" data-type="<?= htmlspecialchars($items['program_type']) ?>" data-event-type="<?= htmlspecialchars($items['event_type']) ?>" data-date="<?= htmlspecialchars($items['start_date']) ?>" data-timestamp="<?= htmlspecialchars($items['posted_at']) ?>"data-city="<?= htmlspecialchars($items['city_municipality']) ?>"data-barangay="<?= htmlspecialchars($items['barangay']) ?>">             
                            <div class="event-header" data-event-id="<?= $items['event_id'] ?>" data-event-end="<?= $items['end_date']?>" data-featured-status="<?= $items['featured_status'] ?>" data-featured-end="<?= $items['featured_enddate'] ? date('Y-m-d H:i:s', strtotime($items['featured_enddate'])) : '' ?>">
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
                                    <div class="event-profile">
                                        <?php if(!empty($user['profile_thumbnail'])): ?>
                                            <img src="<?= htmlspecialchars($user['profile_thumbnail']) ?>" alt="User Profile" class="event-profile-icon">
                                        <?php else: ?>
                                            <div class="default-profile-icon"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                <div class="event-user">
                                    <span class="event-user-name"><?= htmlspecialchars($user['fullname']) ?></span>
                                    <div class="event-timestamps">
                                        <span class="event-time-ago" 
                                            data-timestamp="<?= ($items['is_approved'] === 'Approved') ? htmlspecialchars($items['posted_at']) : htmlspecialchars($items['created_at']) ?>" 
                                            data-status="<?= htmlspecialchars($items['is_approved'] ?? '') ?>">
                                        </span> |
                                        <span class="event-time-posted">
                                            <?= ($items['is_approved'] === 'Approved') ? htmlspecialchars($postedAt) : 'Submitted on '.date("F j, Y", strtotime($items['created_at'])) ?>
                                        </span>
                                        <?php if(!empty($items['edited_at'])): ?>
                                            | <span class="event-time-edited">
                                                Edited on <?= date("F j, Y", strtotime($items['edited_at'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                            <div class="event-body">
                                <div class="event-meta">
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
                                    if($items['is_approved'] === 'Disapproved'){
                                            $eventStatus = "Disapproved";
                                            $statusClass = "disapproved";
                                            echo '<span class="event-status ' . $statusClass . '">Status: ' . $eventStatus . '</span>';
                                        }
                                    elseif($items['is_approved'] === 'Pending'){
                                            $eventStatus = "Pending";
                                            $statusClass = "pending";
                                            echo '<span class="event-status ' . $statusClass . '">Status: ' . $eventStatus . '</span>';
                                    }
                                    else{
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
                                    }
                                    ?>
                                </div>
                                <?php if(isset($items['is_approved']) && $items['is_approved'] === 'Disapproved'): ?>
                                    <div class="disapproval-note">
                                        <span class="disapproval-text"><i class="fas fa-exclamation-circle"></i> This event has been disapproved by moderators</span>
                                        <?php if(!empty($items['disapproval_note'])): ?>
                                            <div class="disapproval-reason">
                                                Reason: <?= $items['disapproval_note'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <h4><?= htmlspecialchars($items['subject'])?></h4>
                                <div class="description-container">
                                    <div class="event-description collapsed" id="desc-<?= $items['event_id']?>">
                                        <p><?= htmlspecialchars(stripslashes($items['description']))?></p>
                                    </div>
                                    <button class="see-more-btn" onclick="toggleDescription('desc-<?= $items['event_id']?>', this)">See More</button>
                                </div>
                                <?php if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $items['author'] && $items['is_cross_barangay'] && !empty($items['attachments_metadata'])): ?>
                                    <div class="attachments-section">
                                        <div class="attachments-header" onclick="toggleAttachments(this)">
                                            <h3><i class="fas fa-paperclip"></i> Supporting Documents</h3>
                                            <span class="toggle-icon">▼</span>
                                        </div>
                                        <div class="attachments-content" style="display: none;">
                                            <p>This event required special approval as it was conducted outside your barangay. Below are the supporting documents you provided:</p>
                                            
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
                                    </div>
                                <?php endif; ?>
                                        <!-- lets re add the thumbnail from earlier with the same concept as profile -->
                                <?php if(!empty($items['thumbnail'])): ?>
                                    <div class="event-thumbnail" onclick="openThumbnailModal('<?= $items['event_id'] ?>')">
                                        <img src="<?= htmlspecialchars($items['thumbnail']) ?>" alt="Event Thumbnail" class="event-thumbnail-img">
                                    </div>                              
                                <?php else: ?>
                                <?php endif; ?>
                            </div>
                            <div class="event-actions">
                                <!-- Attend Button -->
                                <?php if (
                                    isset($_SESSION['user_id']) && 
                                    $_SESSION['user_id'] == $items['author'] && 
                                    !(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Resident' && $_SESSION['organization'] == 'N/A')
                                ): ?>
                                <div class="attend-div">
                                    <button class="edit-event" onclick="window.location.href='edit_event.php?event_id=<?= $items['event_id'] ?>'">
                                        <i class="fas fa-edit"></i>Edit
                                    </button>
                                    <button class="delete-event" onclick="if(confirm('Are you sure you want to delete this event?')) { window.location.href='delete_event.php?event_id=<?= $items['event_id'] ?>'; }">
                                        <i class="fas fa-trash"></i>Delete
                                    </button>
                                </div>
                                <?php endif; ?>
                                <div class="actions-div">
                                    <!-- Comment Button -->
                                    <button class="comment-btn" onclick="toggleComments('<?= $items['event_id'] ?>')">
                                        <i class="fas fa-comment"></i> 
                                        <span class="btn-text">Comment</span>
                                    </button>

                                    <!-- View Details Button -->
                                    <a href="./event_details.php?event_id=<?= $items['event_id'] ?>" class="view-details-btn">
                                        <i class="fas fa-info-circle"></i> 
                                        <span class="btn-text">View Details</span>
                                    </a>

                                    <!-- Share Button -->
                                    <button class="share-btn" onclick="shareEvent('<?= $items['event_id'] ?>')">
                                        <i class="fas fa-share-alt"></i> 
                                        <span class="btn-text">Share</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else{
                    ?>
                    <div class="event-details">
                        <h4>No events found.</h4>
                        <p>It seems there are no events available at the moment. Please check back later or create a new event if you have the permissions.</p>
                        <?php if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] != 'Resident'): ?>
                            <button class="create-event-btn" onclick="window.location.href='create_event.php'">Create New Event</button>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                ?>
            
                <!--  Events Modal (for read more) -->
                <div id="thumbnailModal" class="thumbnail-modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button class="modal-close" onclick="closeThumbnailModal()">&times;</button>
                            <h3 class="modal-title">Event Details</h3>
                        </div>
                        <div class="modal-body">
                            <div class="modal-image-container">
                                <img id="modalThumbnail" src="" alt="Event Thumbnail" class="modal-thumbnail">
                            </div>
                            <div class="modal-event-details">
                                <!-- Event details will be loaded here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a id="fullEventLink" href="" class="view-full-event">View Full Event Details</a>
                        </div>
                    </div>
                </div>

                <!-- Attachment Modal -->
                <div id="attachmentModal" class="attachment-modal">
                    <div class="modal-content">
                        <span class="close-modal" onclick="closeAttachmentModal()">&times;</span>
                        <div id="modalContent"></div>
                    </div>
                </div>
            </div>
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
    <!-- carousel script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const track = document.querySelector('.carousel-track');
            const slides = Array.from(document.querySelectorAll('.carousel-slide'));
            const nextBtn = document.querySelector('.carousel-next');
            const prevBtn = document.querySelector('.carousel-prev');
            const dotsContainer = document.querySelector('.carousel-dots');
            
            // Clone first and last slides for seamless looping
            const firstClone = slides[0].cloneNode(true);
            const lastClone = slides[slides.length - 1].cloneNode(true);
            
            firstClone.classList.add('clone');
            lastClone.classList.add('clone');
            
            track.appendChild(firstClone);
            track.insertBefore(lastClone, slides[0]);
            
            const allSlides = Array.from(document.querySelectorAll('.carousel-slide'));
            let currentSlide = 1; // Start at first real slide
            
            // Create dots only for original slides
            slides.forEach((_, index) => {
            const dot = document.createElement('button');
            dot.classList.add('carousel-dot');
            if (index === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(index + 1)); // +1 because of clone
            dotsContainer.appendChild(dot);
            });
            
            const dots = Array.from(document.querySelectorAll('.carousel-dot'));
            
            // Initialize slide positions
            const initSlides = () => {
            const containerWidth = track.parentElement.clientWidth;
            allSlides.forEach(slide => {
                slide.style.width = `${containerWidth}px`;
            });
            track.style.width = `${containerWidth * allSlides.length}px`;
            track.style.transform = `translateX(-${currentSlide * containerWidth}px)`;
            };
            
            const goToSlide = (slideIndex, instant = false) => {
            const containerWidth = track.parentElement.clientWidth;
            if (instant) {
                track.style.transition = 'none';
            }
            track.style.transform = `translateX(-${slideIndex * containerWidth}px)`;
            currentSlide = slideIndex;
            
            // Update dots (only for original slides)
            const realSlideIndex = (slideIndex - 1 + slides.length) % slides.length;
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === realSlideIndex);
            });
            
            if (instant) {
                setTimeout(() => {
                track.style.transition = 'transform 0.5s ease';
                }, 10);
            }
            };
            
            const nextSlide = () => {
            currentSlide++;
            goToSlide(currentSlide);
            
            // If we've reached the clone at end, instantly reset to beginning
            if (currentSlide === allSlides.length - 1) {
                setTimeout(() => {
                currentSlide = 1;
                goToSlide(currentSlide, true);
                }, 500);
            }
            };
            
            const prevSlide = () => {
            currentSlide--;
            goToSlide(currentSlide);
            
            // If we've reached the clone at start, instantly reset to end
            if (currentSlide === 0) {
                setTimeout(() => {
                currentSlide = allSlides.length - 2;
                goToSlide(currentSlide, true);
                }, 500);
            }
            };
            
            // Initialize
            initSlides();
            window.addEventListener('resize', initSlides);
            
            // Event listeners
            nextBtn.addEventListener('click', nextSlide);
            prevBtn.addEventListener('click', prevSlide);
            
            // Auto-advance (interval set to 10 seconds)
            let slideInterval = setInterval(nextSlide, 10000);
            
            track.addEventListener('mouseenter', () => clearInterval(slideInterval));
            track.addEventListener('mouseleave', () => {
            slideInterval = setInterval(nextSlide, 10000);
            });
            
            // Handle transition end for infinite loop
            track.addEventListener('transitionend', () => {
            if (currentSlide === 0) {
                currentSlide = allSlides.length - 2;
                goToSlide(currentSlide, true);
            } else if (currentSlide === allSlides.length - 1) {
                currentSlide = 1;
                goToSlide(currentSlide, true);
            }
            });
        });
    </script>
    <!-- img bg script -->
    <script>
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
    <!--filter script-->
    <script>
        //filter events
    function updateBarangayDropdown(city) {
        const barangaySelect = document.getElementById('filter-barangay');
        
        // Reset barangay dropdown
        barangaySelect.innerHTML = '<option value="all">All Barangays</option>';
        
        if (city === 'all') {
            barangaySelect.disabled = true;
            return;
        }
        
        // Show loading state
        barangaySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="">Loading barangays...</option>';
        
        // Fetch barangays for selected city
        fetch(`getdropdown.php?city=${encodeURIComponent(city)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    barangaySelect.innerHTML = '<option value="all">All Barangays</option>';
                    return;
                }
                
                // Populate barangay dropdown
                let options = '<option value="all">All Barangays</option>';
                data.forEach(barangay => {
                    options += `<option value="${barangay.barangay}">${barangay.barangay}</option>`;
                });
                
                barangaySelect.innerHTML = options;
                barangaySelect.disabled = false;
            })
            .catch(error => {
                console.error('Error fetching barangays:', error);
                barangaySelect.innerHTML = '<option value="all">All Barangays</option>';
                barangaySelect.disabled = false;
            });
    }

    // Initialize the barangay dropdown when city changes
    document.addEventListener('DOMContentLoaded', function() {
        const citySelect = document.getElementById('filter-city');
        
        // Set up event listener for city dropdown
        citySelect.addEventListener('change', function() {
            updateBarangayDropdown(this.value);
        });
        
        // If a city is already selected on page load (from URL params, etc.)
        if (citySelect.value !== 'all') {
            updateBarangayDropdown(citySelect.value);
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Filter elements
        const filterType = document.getElementById('filter-type');
        const filterProgram = document.getElementById('filter-program');
        const filterCity = document.getElementById('filter-city');
        const filterBarangay = document.getElementById('filter-barangay');
        const startDate = document.getElementById('start-date');
        const endDate = document.getElementById('end-date');
        const applyBtn = document.getElementById('apply-filters');
        const resetBtn = document.getElementById('reset-filters');
        const eventCards = document.querySelectorAll('.event-details');
        const filterStatus = document.getElementById('filter-status');
        
        // Apply filters
        applyBtn.addEventListener('click', function() {
            const typeValue = filterType.value;
            const programValue = filterProgram.value;
            const cityValue = filterCity.value;
            const barangayValue = filterBarangay.value;
            const startDateValue = startDate.value;
            const endDateValue = endDate.value;
            const statusValue = filterStatus.value;
            
            let hasVisibleCards = false;
            
            eventCards.forEach(card => {
                const cardEventType = card.dataset.eventType;
                const cardProgramType = card.dataset.type;
                const cardCity = card.dataset.city || '';
                const cardBarangay = card.dataset.barangay || '';
                const cardDate = card.dataset.date;
                
                let matches = true;
                
                // Check event type filter
                if (typeValue !== 'all' && cardEventType !== typeValue) {
                    matches = false;
                }
                
                // Check program type filter
                if (programValue !== 'all' && cardProgramType !== programValue) {
                    matches = false;
                }
                
                // Check city filter
                if (cityValue !== 'all' && cardCity !== cityValue) {
                    matches = false;
                }
                
                // Check barangay filter (only if city is not 'all')
                if (cityValue !== 'all' && barangayValue !== 'all' && cardBarangay !== barangayValue) {
                    matches = false;
                }
                
                // Check date range
                if (startDateValue && cardDate < startDateValue) {
                    matches = false;
                }
                if (endDateValue && cardDate > endDateValue) {
                    matches = false;
                }
                
                // Check status filter
                if (statusValue !== 'all') {
                    const statusElement = card.querySelector('.event-status');
                    if (statusElement) {
                        const cardStatus = statusElement.textContent.toLowerCase().replace('status: ', '');
                        if (statusValue !== cardStatus) {
                            matches = false;
                        }
                    } else {
                        // If no status element found and we're filtering by status, hide the card
                        matches = false;
                    }
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
            filterProgram.value = 'all';
            filterCity.value = 'all';
            filterBarangay.innerHTML = '<option value="all">All Barangays</option>';
            filterBarangay.disabled = true;
            startDate.value = '';
            endDate.value = '';
            filterStatus.value = 'all';
        
            eventCards.forEach(card => {
                card.style.display = '';
            });
            
            // Hide no results message
            const noResultsMsg = document.getElementById('no-results-message');
            if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        });

        filterCity.addEventListener('change', function() {
            const selectedCity = this.value;
            if (selectedCity === 'all') {
                // Reset barangay filter to all
                filterBarangay.value = 'all';
                return;
            }
        });
    });
    </script>
    <!--timestamp script-->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Time ago calculation function
        function timeAgo(timestamp, status) {
            const now = new Date();
            const past = new Date(timestamp);
            const seconds = Math.floor((now - past) / 1000);
            
            const prefix = (status === 'Approved') ? 'Posted ' : 'Submitted ';
            
            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) return prefix + interval + " year" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) return prefix + interval + " month" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 86400);
            if (interval >= 1) return prefix + interval + " day" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 3600);
            if (interval >= 1) return prefix + interval + " hour" + (interval === 1 ? "" : "s") + " ago";
            
            interval = Math.floor(seconds / 60);
            if (interval >= 1) return prefix + interval + " minute" + (interval === 1 ? "" : "s") + " ago";
            
            return prefix + "just now";
        }

        // Apply to all time-ago elements
        const timeAgoElements = document.querySelectorAll('.event-time-ago');
        timeAgoElements.forEach(el => {
            const timestamp = el.getAttribute('data-timestamp');
            const status = el.getAttribute('data-status');
            if (timestamp) {
                el.textContent = timeAgo(timestamp, status);
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
    <!-- mobile responsive filter script-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterPanel = document.querySelector('.events-filter');
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
            const filterPanel = document.querySelector('.events-filter');
            
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
    <!-- events expiration script -->
    <script>
        function checkAndExpireEvents() {
            // First update the UI for any expired events
            updateExpiredEventsUI();
            
            // Then call the server to update the database
            fetch('autoexpire_events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    console.log(`Marked ${data.count} events as completed`);
                }
            })
            .catch(error => {
                console.error('Error expiring events:', error);
            });
        }

        // Update UI for expired events
        function updateExpiredEventsUI() {
            const now = new Date();

            document.querySelectorAll('.event-header[data-event-end]').forEach(eventElement => {
            const endDate = new Date(eventElement.dataset.eventEnd);
            if (endDate <= now) {
                const eventDetails = eventElement.closest('.event-details') ||
                eventElement.querySelector('.event-details');

                if (eventDetails && !eventDetails.classList.contains('Completed')) {
                // Check if the event is already disapproved
                const statusElement = eventDetails.querySelector('.event-status');
                if (statusElement && statusElement.textContent.includes('Disapproved')) {
                    // Skip updating this event
                    const featuredIconContainer = eventElement.querySelector('.featured-icon-container');
                    if (featuredIconContainer) {
                        featuredIconContainer.style.display = 'none';
                    }
                    return;
                }
                eventDetails.classList.add('Completed');

                if (statusElement) {
                    statusElement.textContent = 'Status: Completed';
                    statusElement.classList.add('completed');
                }
                // Hide featured icon container
                const featuredIconContainer = eventElement.querySelector('.featured-icon-container');
                if (featuredIconContainer) {
                    featuredIconContainer.style.display = 'none';
                }
                }
            }
            });
        }

        checkAndExpireEvents();
        setInterval(checkAndExpireEvents, 60 * 1000); // 1 minute
    </script>
    <!-- autoupdate event completion and featured_status script -->
    <script>
        // auto update featured status
        function autoCheckFeaturedStatus() {
            document.querySelectorAll('.event-header[data-featured-end]').forEach(header => {
                const featuredEnd = header.dataset.featuredEnd;
                const featuredStatus = header.dataset.featuredStatus;
                
                if (featuredStatus === 'Featured' && featuredEnd) {
                    const endDate = new Date(featuredEnd);
                    const now = new Date();
                    
                    if (now > endDate) {
                        updateEventUI(header, 'Normal');
                        autoUpdateFeaturedStatus(header.dataset.eventId, 'Normal');
                    }
                }
            });
        }

        function updateEventUI(headerElement, newStatus) {
            // Update visual elements
            const featuredIcon = headerElement.querySelector('.featured-icon');
            if (featuredIcon) {
                featuredIcon.classList.toggle('featured-active', newStatus === 'Featured');
            }
            
            const featuredPeriod = headerElement.querySelector('.featured-period');
            if (featuredPeriod) {
                if (newStatus === 'Normal') {
                    featuredPeriod.remove();
                } else {
                    // Optional: Update period display if needed
                }
            }
            
            // Update data attributes
            headerElement.dataset.featuredStatus = newStatus;
            if (newStatus === 'Normal') {
                headerElement.removeAttribute('data-featured-end');
            }
        }

        function autoUpdateFeaturedStatus(eventId, status) {
            fetch('autoupdate_fs.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({event_id: eventId, status: status})
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Update failed');
                    // Optionally revert UI here if needed
                }
            })
            .catch(console.error);
        }

        function autoToggleFeaturedStatus(element) {
            const eventId = element.dataset.eventId;
            const isFeatured = element.classList.contains('featured-active');
            const newStatus = isFeatured ? 'Normal' : 'Featured';
            
            // Find the parent event header
            const header = element.closest('.event-header');
            
            // Immediate UI update
            updateEventUI(header, newStatus);
            
            // Server update
            autoUpdateFeaturedStatus(eventId, newStatus);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            autoCheckFeaturedStatus();
            setInterval(autoCheckFeaturedStatus, 60000);
        });
    </script>
    <!-- event actions script -->
    <script>
        // Attend Form Submission (updated)
        document.querySelectorAll('.attend-form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const btn = this.querySelector('.attend-btn');
                const originalText = btn.innerHTML;
                
                // Show loading state
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
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
                            btn.innerHTML = `<i class="fas fa-calendar-check"></i> ${data.isAttending ? 'Not Interested' : 'Interested'}`;
                            countElement.innerHTML = `<i class="fas fa-users"></i> ${data.newCount} interested`;
                        }
                    } else {
                        btn.innerHTML = originalText;
                        alert('Error updating attendance');
                    }
                } catch (error) {
                    btn.innerHTML = originalText;
                    alert('Network error');
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
                        const commentsContainer = document.querySelector('.comments-list');
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
            const commentSection = document.getElementById(`comments-${eventId}`);
            const eventContainer = document.querySelector(`[data-event-id="${eventId}"]`).closest('.event-details');
            
            if (commentSection) {
                commentSection.classList.toggle('show');
            } else {
                // Create loading indicator
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'comments-loading';
                loadingDiv.innerHTML = '<div class="spinner"></div> Loading comments...';
                eventContainer.appendChild(loadingDiv);
                
                // Load comments via AJAX
                fetch(`load_comments.php?event_id=${eventId}`)
                    .then(response => response.text())
                    .then(html => {
                        loadingDiv.remove();
                        
                        const container = document.createElement('div');
                        container.id = `comments-${eventId}`;
                        container.className = 'comments-section show';
                        container.innerHTML = html;
                        eventContainer.appendChild(container);
                        
                        // Initialize comment form submission
                        initCommentForm(eventId);
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
    <!-- img click view script -->
    <script>
    // Open modal with thumbnail and event details
        function openThumbnailModal(eventId) {
            const modal = document.getElementById('thumbnailModal');
            const modalImg = document.getElementById('modalThumbnail');
            const detailsContainer = document.querySelector('.modal-event-details');
            const eventElement = document.querySelector(`[data-event-id="${eventId}"]`).closest('.event-details');
            
            // Set thumbnail
            const thumbnailImg = eventElement.querySelector('.event-thumbnail-img');
            if (!thumbnailImg) return;
            modalImg.src = thumbnailImg.src;
            
            // Clone event details (excluding actions and thumbnail)
            const eventHeader = eventElement.querySelector('.event-header').cloneNode(true);
            const eventBody = eventElement.querySelector('.event-body').cloneNode(true);
            
            // Clean up cloned elements
            const thumbnailInBody = eventBody.querySelector('.event-thumbnail');
            if (thumbnailInBody) thumbnailInBody.remove();
            
            const featuredIconContainer = eventHeader.querySelector('.featured-icon-container');
            if (featuredIconContainer) featuredIconContainer.remove();
            
            // Update the see-more button to target the modal's description
            const descriptionContainer = eventBody.querySelector('.description-container');
            if (descriptionContainer) {
                const descId = `modal-desc-${eventId}`;
                const description = descriptionContainer.querySelector('.event-description');
                const seeMoreBtn = descriptionContainer.querySelector('.see-more-btn');
                
                if (description && seeMoreBtn) {
                    description.id = descId;
                    seeMoreBtn.setAttribute('onclick', `toggleDescription('${descId}', this)`);
                }
            }
            
            // Populate details container
            detailsContainer.innerHTML = '';
            detailsContainer.appendChild(eventHeader);
            detailsContainer.appendChild(eventBody);
            
            // Set full event link
            document.getElementById('fullEventLink').href = `event_details.php?event_id=${eventId}`;
            
            // Update URL
            history.pushState({ modal: 'thumbnail', eventId: eventId }, '', `?thumbnail=${eventId}`);
            
            // Show modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Add keyboard escape listener
            document.addEventListener('keydown', handleModalKeyDown);
        }

        function handleModalKeyDown(e) {
            if (e.key === 'Escape') {
                closeThumbnailModal();
            }
        }

        function closeThumbnailModal() {
            const modal = document.getElementById('thumbnailModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            document.removeEventListener('keydown', handleModalKeyDown);
            
            // Reset URL if modal was opened via direct link
            if (window.location.search.includes('thumbnail=')) {
                history.pushState(null, '', window.location.pathname);
            }
        }
    </script>
    <!-- navigation event focus script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a focus_event parameter in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const focusEventId = urlParams.get('focus_event');
            
            if (focusEventId) {
                // Find the event element with matching data-event-id
                const eventElement = document.querySelector(`.event-details[data-event-id="${focusEventId}"]`);
                
                if (eventElement) {
                    // Scroll to the event
                    eventElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Highlight the event temporarily
                    eventElement.classList.add('highlight-event');
                    setTimeout(() => {
                        eventElement.classList.remove('highlight-event');
                    }, 3000);
                    
                    // Remove the parameter from URL without reloading
                    history.replaceState(null, '', window.location.pathname);
                }
            }
        });
    </script>
    <!-- attachment script -->
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

        function toggleAttachments(header) {
            const section = header.closest('.attachments-section');
            const content = section.querySelector('.attachments-content');
            const isExpanded = section.classList.contains('expanded');
            
            if (isExpanded) {
                // Collapse
                section.classList.remove('expanded');
                content.style.display = 'none';
                header.querySelector('.toggle-icon').textContent = '▼';
            } else {
                // Expand
                section.classList.add('expanded');
                content.style.display = 'block';
                header.querySelector('.toggle-icon').textContent = '▲';
            }
        }
    </script>
</body>
</html>