<!--
<?php
    session_start();
    include 'database.php';

    if(!isset($_GET['event_id'])){
        header('Location: events.php');
        exit;
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

    $query = "SELECT author, barangay, city_municipality FROM eventstbl WHERE event_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header('Location: events.php');
        exit;
    }

    $event = $result->fetch_assoc();
    
    // 3. Check authorization
    $isAuthor = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['author']);

    $isBarangayOfficial = false;
    if (!$isAuthor && isset($_SESSION['accessrole']) && $_SESSION['accessrole'] === 'Barangay Official') {
        // Check if barangay and city match between official and author
        $isBarangayOfficial = (
            isset($_SESSION['barangay']) && 
            isset($_SESSION['city_municipality']) &&
            strtolower($_SESSION['barangay']) === strtolower($event['barangay']) &&
            strtolower($_SESSION['city_municipality']) === strtolower($event['city_municipality'])
        );
    }

    if (!$isAuthor && !$isBarangayOfficial) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'You are not authorized to edit this event'
        ];
        header('Location: events.php');
        exit;
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
        default:
            return 'fa-file';
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return '1 byte';
    } else {
        return '0 bytes';
    }
}
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event/Activity</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="eventform.css">
    <link rel="stylesheet" href="event_location_manager.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    
    <script type="text/javascript" src="app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    <script src="event_location_manager.js" defer></script>
    <script src="mangrove_area_selector.js" defer></script>
    
    <script>
        // Pass PHP session values to JavaScript
        window.eventFormUserBarangay = '<?= isset($_SESSION["barangay"]) ? addslashes($_SESSION["barangay"]) : "" ?>';
        window.eventFormUserCity = '<?= isset($_SESSION["city_municipality"]) ? addslashes($_SESSION["city_municipality"]) : "" ?>';
    </script>
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
                    <a href="events.php" class="active">Events</a>
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
        <?php
            if (!empty($event_id)) {
                $query = "SELECT * FROM eventstbl WHERE event_id = ?";
                $stmt = mysqli_prepare($connection, $query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $event_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $event_data = mysqli_fetch_assoc($result);
                    } else {
                        echo "No event found with ID: " . htmlspecialchars($event_id);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    echo "Database query preparation failed.";
                }
            } else {
                echo "No event ID specified.";
            }

            $disapproval_note = isset($event_data['disapproval_note']) ? $event_data['disapproval_note'] : ''; //to get disapproval note
        ?>
        <!-- events form page container-->
        <div class="another-event-form-container">
            <h2>Edit Event/Activity</h2>
            <form id="event-creation-form" enctype="multipart/form-data" action="updateevent.php" method="post">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars($event_id); ?>">
                <!-- Event Thumbnail -->
                <div class="form-group thumbnail">
                    <div class="thumbnail-preview-container" onclick="document.getElementById('thumbnail').click()">
                        <!-- Default placeholder -->
                        <div class="thumbnail-placeholder" style="<?= isset($event_data['thumbnail']) ? 'display: none;' : 'display: block;' ?>">
                            <span>Click to upload thumbnail</span>
                        </div>
                        <img id="thumbnail-preview" class="thumbnail-preview" src="<?= isset($event_data['thumbnail']) ? htmlspecialchars($event_data['thumbnail']) : '' ?>" alt="<?= isset($event_data['thumbnail_data']) ? htmlspecialchars($event_data['thumbnail_data']) : '' ?>" style="<?= isset($event_data['thumbnail']) ? 'display: block;' : 'display: none;' ?>">
                        <div class="thumbnail-actions" style="<?= isset($event_data['thumbnail']) ? 'display: flex;' : 'display: none;' ?>">
                            <button type="button" class="change-btn" onclick="event.stopPropagation(); document.getElementById('thumbnail').click()">
                                Change
                            </button>
                            <button type="button" class="remove-btn" onclick="event.stopPropagation(); removeThumbnail()">
                                Remove
                            </button>
                        </div>
                        <!-- Error message container (when user uploads other files other than image) -->
                        <div id="thumbnail-error" class="thumbnail-error" style="display: none;"></div>
                    </div>
                    <!-- Hidden file input -->
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp,image/svg+xml" style="display: none;" onchange="validateImage(this)">
                </div>
                <!-- Event Title -->
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" placeholder="Write the title of the post" value="<?= isset($event_data['subject']) ? htmlspecialchars($event_data['subject']) : '' ?>" required>
                </div>
                <!-- Event Program Type -->
                <div class="form-group">
                    <label for="program-type">Program Type</label>
                    <select id="program-type" name="program_type" required>
                        <option value="" disabled selected>Select program type</option>
                        <option value="Event" <?= ($event_data['program_type'] == 'Event') ? 'selected' : ''; ?>>Event</option>
                        <option value="Announcement" <?= ($event_data['program_type'] == 'Announcement') ? 'selected' : ''; ?>>Announcement</option>
                    </select>
                    <?php if(isset($event_data['program_type']) && $event_data['program_type'] == 'Event'): ?>
                        <input type="hidden" id="program-type" name="program_type" value="<?= isset($event_data['program_type']) ? htmlspecialchars($event_data['program_type']) : '' ?>">
                    <?php endif; ?>
                    <!-- when the selected program-type is event, ask the user the type of the event -->
                    <div class="event-type-container" style="display: none;">
                        <?php 
                            $current_event_type = isset($event_data['event_type']) ? htmlspecialchars($event_data['event_type']) : '';
                            $predefined_types = ['Tree Planting', 'Reforestation Drive', 'Coastal Clean-up', 'Mangrove Monitoring', 'Wildlife Observation', 'Nursery Establishment'];
                            $is_manual_mode = !in_array($current_event_type, $predefined_types) && !empty($current_event_type);
                        ?>
                        
                        <div class="event-type-mode-header">
                            <label for="event-type">Event Type</label>
                            <div class="event-type-toggle-container">
                                <label class="event-type-mode-switch">
                                    <input type="checkbox" id="event-type-mode-toggle" <?= $is_manual_mode ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                                <span id="event-type-mode-label"><?= $is_manual_mode ? 'Switch to List Selection' : 'Switch to Manual Input' ?></span>
                            </div>
                        </div>
                        
                        <!-- Dropdown for mangrove area events -->
                        <div id="select-event-type-container" style="display: <?= $is_manual_mode ? 'none' : 'block' ?>;">
                            <div class="event-type-indicator mangrove-area">
                                <i class="fas fa-seedling"></i>
                                <span>Mangrove Area Events (Select from list)</span>
                            </div>
                            <select id="event-type" name="event_type" required>
                                <option value="" disabled <?= empty($current_event_type) || $is_manual_mode ? 'selected' : '' ?>>Select event type</option>
                                <option value="Tree Planting" <?= ($current_event_type == 'Tree Planting') ? 'selected' : '' ?>>Tree Planting</option>
                                <option value="Reforestation Drive" <?= ($current_event_type == 'Reforestation Drive') ? 'selected' : '' ?>>Reforestation Drive</option>
                                <option value="Coastal Clean-up" <?= ($current_event_type == 'Coastal Clean-up') ? 'selected' : '' ?>>Coastal Clean-up</option>
                                <option value="Mangrove Monitoring" <?= ($current_event_type == 'Mangrove Monitoring') ? 'selected' : '' ?>>Mangrove Monitoring</option>
                                <option value="Wildlife Observation" <?= ($current_event_type == 'Wildlife Observation') ? 'selected' : '' ?>>Wildlife Observation</option>
                                <option value="Nursery Establishment" <?= ($current_event_type == 'Nursery Establishment') ? 'selected' : '' ?>>Nursery Establishment</option>
                            </select>
                        </div>
                        
                        <!-- Manual input for non-mangrove area events -->
                        <div id="manual-event-type-container" style="display: <?= $is_manual_mode ? 'block' : 'none' ?>;">
                            <div class="event-type-indicator non-mangrove-area">
                                <i class="fas fa-keyboard"></i>
                                <span>Non-Mangrove Area Events (Manual input)</span>
                            </div>
                            <input type="text" id="manual-event-type" name="manual_event_type" placeholder="e.g. Seminar, Workshop, Awareness Campaign, Training, Community Meeting, etc." value="<?= $is_manual_mode ? $current_event_type : '' ?>">
                        </div>
                    </div>
                </div>
                <!-- Event Date -->
                <div class="form-group-dates">
                    <div class="date-input-group">
                        <label for="start-date">Start Date*</label>
                        <?php
                            // Set timezone to Philippine time
                            date_default_timezone_set('Asia/Manila');
                            // Set minimum to tomorrow at 12:00 AM
                            $tomorrow = date('Y-m-d\T00:00', strtotime('tomorrow'));
                        ?>
                        <?php
                        // determine if the start date is already passed to enable/disable the input
                            $startDateValue = isset($event_data['start_date']) ? htmlspecialchars($event_data['start_date']) : '';
                            $isPast = false;
                            if ($startDateValue) {
                                $startDateTimestamp = strtotime($startDateValue);
                                $nowTimestamp = time();
                                $isPast = $startDateTimestamp < $nowTimestamp;
                            }
                            $minValue = !$isPast ? date('Y-m-d\TH:i') : $tomorrow;
                        ?>
                        <?php if ($isPast): ?>
                            <div style="color: #b00; font-size: 0.65em; margin-top:-10px;">
                                This event's start date has already passed and cannot be changed.
                            </div>
                        <?php endif; ?>
                        <input type="datetime-local" id="start-date" name="start_date" value="<?= $startDateValue ?>" <?= $isPast ? 'disabled' : 'required' ?> min="<?= $minValue ?>">
                        <?php if ($isPast): ?>
                            <input type="hidden" name="start_date" value="<?= $startDateValue ?>">
                        <?php endif; ?>
                    </div>
                    <div class="date-input-group">
                        <label for="end-date">End Date*</label>
                         <?php
                        // Check if event is featured and get featured_enddate
                        $isFeatured = (isset($event_data['featured_status']) && $event_data['featured_status'] === 'Featured');
                        $featuredEndDate = $isFeatured && !empty($event_data['featured_enddate']) ? htmlspecialchars($event_data['featured_enddate']) : (isset($event_data['end_date']) ? htmlspecialchars($event_data['end_date']) : '');
                        ?>
                        <?php if ($isFeatured): ?>
                            <input type="hidden" id="featured-enddate" name="featured_enddate" value="<?= $featuredEndDate ?>">
                            <div id="featured-enddate-info" style="color: #b00; font-size: 0.65em; margin-top:-10px;">
                                Featured status ends on: <span id="featured-enddate-span"><?= date('F j, Y, g:i a', strtotime($featuredEndDate)) ?></span>
                            </div>
                        <?php endif; ?>
                        <input type="datetime-local" id="end-date" name="end_date" value="<?= isset($event_data['end_date']) ? htmlspecialchars($event_data['end_date']) : '' ?>" required min="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                <!-- Event Description -->
                <div class="form-group">
                    <label for="description">Description*</label>
                    <textarea id="description" name="description" rows="4" placeholder="Write your description/instructions about the post here" required><?= isset($event_data['description']) ? htmlspecialchars(stripslashes($event_data['description'])) : '' ?></textarea>
                </div>
                <!-- Event Link -->
                <div class="form-group">
                    <label for="link">Attached Event Link/s</label>
                    <textarea id="link" name="link" rows="3" placeholder="Add links related to the post (one per line)&#10;Example:&#10;https://example.com/event-info&#10;https://example.com/registration"><?php 
                    if (isset($event_data['event_links'])) {
                        $links = json_decode(stripslashes($event_data['event_links']), true);
                        if (is_array($links)) {
                            echo htmlspecialchars(implode("\n", $links));
                        }
                    }
                    ?></textarea>
                </div>
                <!-- Event Location -->
                <div class="form-row">
                    <div class="form-group">
                       <div class="venue-label-container">
                           <label for="venue">Venue*</label>
                           <label class="venue-mode-switch">
                               <input type="checkbox" id="venue-mode-toggle">
                               <span class="slider"></span>
                           </label>
                           <span id="venue-mode-label">(Automatic - from map)</span>
                       </div>
                        <div class="venue-input-container">
                            <input type="text" id="venue" name="venue" placeholder="Click map button to select venue" value="<?= isset($event_data['venue']) ? htmlspecialchars($event_data['venue']) : '' ?>">
                            <button type="button" id="map-button" class="map-button" title="Select location from map">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                        </div>
                        <small id="venue-instruction" class="instruction-text" style="display: none;">
                            <i class="fas fa-info-circle"></i> Manual mode: Type venue name, then enter city/municipality and barangay below. Click map to pin location (optional).
                        </small>
                        <!-- Hidden fields for coordinates -->
                        <input type="hidden" id="latitude" name="latitude" value="<?= isset($event_data['latitude']) ? htmlspecialchars($event_data['latitude']) : '' ?>">
                        <input type="hidden" id="longitude" name="longitude" value="<?= isset($event_data['longitude']) ? htmlspecialchars($event_data['longitude']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="area-no">Area No</label>
                        <div class="area-input-container">
                            <input type="text" id="area-no" name="area_no" placeholder="Select mangrove area" readonly value="<?= isset($event_data['area_no']) ? htmlspecialchars($event_data['area_no']) : '' ?>">
                            <button type="button" id="area-map-button" class="map-button" title="Select mangrove area from map" disabled>
                                <i class="fas fa-map-marked-alt"></i>
                            </button>
                        </div>
                        <small id="area-instruction" class="instruction-text" style="display: none;">
                            <i class="fas fa-info-circle"></i> Please set venue location first to enable area selection.
                        </small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City/Municipality* <span id="city-hint">(Auto-filled from map)</span></label>
                        <input type="text" id="city" name="city" class="location-input" value="<?= isset($event_data['city_municipality']) ? htmlspecialchars($event_data['city_municipality']) : '' ?>" title="Auto-filled from map">
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay* <span id="barangay-hint">(Auto-filled from map)</span></label>
                        <input type="text" id="barangay" name="barangay" class="location-input" value="<?= isset($event_data['barangay']) ? htmlspecialchars($event_data['barangay']) : '' ?>" title="Auto-filled from map">
                    </div>
                </div>
                
                <!-- Cross-Barangay Toggle -->
                <div class="form-group">
                    <div class="toggle-container">
                        <div class="toggle-label">
                            <i class="fas fa-exchange-alt"></i> This is a cross-barangay event
                            <span class="toggle-hint">Enable if event requires special permissions</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="cross-barangay-toggle" <?= ($event_data['is_cross_barangay'] == 1) ? 'checked' : '' ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <!-- Eco Points -->
                <div class="form-group">
                    <label for="eco-points">Eco Points</label>
                    <input type="number" id="eco-points" name="eco_points" placeholder="Enter eco points (optional)" min="0" step="1" value="<?= isset($event_data['eco_points']) ? htmlspecialchars($event_data['eco_points']) : '' ?>">
                </div>
                <!-- Cross-barangay Event Section -->
                <div id="cross-barangay-section" style="<?= ($event_data['is_cross_barangay'] == 1) ? 'display: block;' : 'display: none;' ?>">
                    <div class="form-group">
                        <h3>Additional Requirements for Cross-Barangay Events</h3>
                        <p>This event requires special approval as it's conducted outside your registered barangay.</p>
                        
                        <div class="file-upload-container">
                            <label for="event-attachments">Upload Supporting Documents (Max 50MB total)</label>
                            <input type="file" id="event-attachments" name="attachments[]" multiple 
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" 
                                style="display: none;">
                            
                            <div class="file-upload-area" id="drop-zone">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <p>Click to upload files or drag and drop</p>
                                <p class="file-types">(PDF, DOC, XLS, PPT, JPG, PNG)</p>
                            </div>
                            
                            <!-- Display existing attachments -->
                            <?php if ($event_data['is_cross_barangay'] == 1 && !empty($event_data['attachments_metadata'])): 
                                $attachments = json_decode($event_data['attachments_metadata'], true);
                                if (is_array($attachments) && count($attachments) > 0): ?>
                                    <div id="existing-attachments" class="attachments-section">
                                        <h4><i class="fas fa-paperclip"></i> Current Supporting Documents</h4>
                                        <div class="attachments-list">
                                            <?php foreach ($attachments as $index => $attachment): 
                                                $fileExt = pathinfo($attachment['name'], PATHINFO_EXTENSION);
                                                $iconClass = getFileIconClass($fileExt);
                                            ?>
                                                <div class="attachment-card" data-file-path="<?= htmlspecialchars($attachment['path']) ?>">
                                                    <div class="attachment-header">
                                                        <i class="fas <?= $iconClass ?> attachment-icon"></i>
                                                        <div class="attachment-info">
                                                            <span class="attachment-name"><?= htmlspecialchars($attachment['name']) ?></span>
                                                            <div class="attachment-meta">
                                                                <span><?= formatFileSize($attachment['size']) ?></span>
                                                                <span>•</span>
                                                                <span><?= date('M j, Y', strtotime($attachment['upload_date'])) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="attachment-actions">
                                                        <a href="<?= htmlspecialchars($attachment['path']) ?>" download class="download-btn">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                        <button type="button" class="remove-attachment-btn" data-file-path="<?= htmlspecialchars($attachment['path']) ?>">
                                                            <i class="fas fa-trash"></i> Remove
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <div id="file-preview-container" class="file-preview-container"></div>
                            <div id="size-warning" class="size-warning" style="display: none;"></div>
                            <div id="file-counter" class="file-counter" style="display: none;"></div>
                            <input type="hidden" id="removed-attachments" name="removed_attachments" value="">
                        </div>
                        
                        <div class="form-group">
                            <label for="special-notes">Additional Notes for Approvers</label>
                            <textarea id="special-notes" name="special_notes" rows="3" 
                                    placeholder="Explain why you're conducting this event outside your barangay..."><?= isset($event_data['special_notes']) ? htmlspecialchars($event_data['special_notes']) : '' ?></textarea>
                        </div>
                    </div>
                </div>
                <?php if($event_data['is_approved'] == 'Disapproved' && !empty($disapproval_note)): ?>
                    <div class="disapproval-note">
                        <span class="disapproval-text"><i class="fas fa-exclamation-circle"></i> This event has been disapproved by moderators</span>
                        <div class="disapproval-reason">
                            <strong>Reason:</strong> <?= htmlspecialchars($disapproval_note) ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" name="submit" class="submit-btn">Edit Event/Activity</button>
                </div>
            </form>
            
            <!-- Enhanced Map Modal -->
            <div id="map-modal" class="map-modal">
                <div class="map-modal-content">
                    <div class="map-modal-header">
                        <h3><i class="fas fa-map-marked-alt"></i> Select Event Location</h3>
                        <span class="close-modal">&times;</span>
                    </div>
                    
                    <div class="map-modal-body">
                        <!-- Search Section -->
                        <div class="map-search-section">
                            <div class="search-container">
                                <input type="text" id="venue-search" placeholder="Search for a place in Bataan... (e.g., BPSU Balanga, Abucay Church)">
                                <button id="search-venue-btn" type="button">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                            <div id="search-results"></div>
                        </div>
                        
                        <!-- Map and Info Section -->
                        <div class="map-display-section">
                            <div id="map-container"></div>
                            <div id="location-info-section" class="location-info-panel">
                                <h4>Selected Location</h4>
                                <div id="location-info">
                                    <p style="color: #718096; font-style: italic; text-align: center; padding: 20px;">
                                        Click on the map or search for a location to begin
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map-modal-footer">
                        <button id="cancel-location" class="modal-btn" type="button">Cancel</button>
                        <button id="confirm-location" class="modal-btn" type="button">Confirm Location</button>
                    </div>
                </div>
            </div>
            
            <!-- Area Selection Modal -->
            <div id="area-modal" class="map-modal">
                <div class="map-modal-content">
                    <div class="map-modal-header">
                        <h3><i class="fas fa-leaf"></i> Select Mangrove Area</h3>
                        <span class="close-area-modal">&times;</span>
                    </div>
                    
                    <div class="map-modal-body">
                        <!-- Search Section for Areas -->
                        <div class="map-search-section">
                            <div class="search-container">
                                <input type="text" id="area-search" placeholder="Search by barangay or city/municipality... (e.g., Wawa, Balanga)">
                                <button id="search-area-btn" type="button">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button id="clear-area-search-btn" type="button" style="display: none;">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                            <div id="area-search-results"></div>
                        </div>
                        
                        <!-- Map Display -->
                        <div class="map-display-section">
                            <div id="area-map-container"></div>
                            <div class="area-info-panel">
                                <h4>Selected Mangrove Area</h4>
                                <div id="area-info">
                                    <p style="color: #718096; font-style: italic; text-align: center; padding: 20px;">
                                        Click on a mangrove area or search to select
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="map-modal-footer">
                        <button id="cancel-area" class="modal-btn" type="button">Cancel</button>
                        <button id="confirm-area" class="modal-btn" type="button" disabled>Confirm Area</button>
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
        document.getElementById('event-creation-form').addEventListener('submit', function(e) {
            const programType = document.getElementById('program-type').value;

            if (programType !== 'Announcement') {
                const canvas = document.querySelector('#qr-preview canvas');
                if (canvas) {
                    const qrImageData = canvas.toDataURL('image/png');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'qr_image_data';
                    hiddenInput.value = qrImageData;
                    this.appendChild(hiddenInput);
                }
            }

            // Form validation
            const endDate = new Date(document.getElementById('end-date').value);
            const startDate = new Date(document.getElementById('start-date').value);

            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
            }
        });

        document.getElementById('qrtext').addEventListener('input', function (e) {
            e.preventDefault();
            
            // Get the base URL value (what's shown in the input)
            const baseUrl = document.getElementById('qrtext').value;
            
            // Construct the full URL for the QR code
            const qrCodeUrl = `http://localhost:3000/save_eventurl.php?redirect_url=${encodeURIComponent(baseUrl)}`;
            
            const qrCodeContainer = document.getElementById('qr-preview');
            qrCodeContainer.innerHTML = '';

            const canvas = document.createElement('canvas');
            qrCodeContainer.appendChild(canvas);

            // Generate QR code with the modified URL
            QRCode.toCanvas(canvas, qrCodeUrl, {
                width: 300,
                margin: 1,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function (error) {
                if (error) {
                    console.error(error);
                    return;
                }

                const ctx = canvas.getContext('2d');
                const logo = new Image();
                logo.src = 'images/mangrow-logo.png';
                logo.onload = function () {
                    const logoSize = 60;
                    const x = (canvas.width - logoSize) / 2;
                    const y = (canvas.height - logoSize) / 2;
                    ctx.drawImage(logo, x, y, logoSize, logoSize);
                    
                    // Store the QR code URL in a data attribute (optional)
                    qrCodeContainer.dataset.qrUrl = qrCodeUrl;
                };
            });
        });

        document.getElementById('event-creation-form').addEventListener('input', function () {
            const programType = document.getElementById('program-type').value;
            if (programType === 'Announcement') return;

            const title = document.getElementById('event-title').value;
            const organization = document.getElementById('organization').value;
            const venue = document.getElementById('venue').value;
            const barangay = document.getElementById('barangay').value;
            const city = document.getElementById('city').value;
            const areaNo = document.getElementById('area-no').value;
            const startDate = document.getElementById('start-date').value;

            const qrTextInput = document.getElementById('qrtext');
            const baseUrl = "http://localhost:3000/reportform.php";
            
            if (title && organization && venue && barangay && city && areaNo) {
                // Update the input field with just the base URL and parameters
                qrTextInput.value = `${baseUrl}?programType=${encodeURIComponent(programType)}&title=${encodeURIComponent(title)}&organization=${encodeURIComponent(organization)}&venue=${encodeURIComponent(venue)}&barangay=${encodeURIComponent(barangay)}&city=${encodeURIComponent(city)}&areaNo=${encodeURIComponent(areaNo)}&startDate=${encodeURIComponent(startDate)}`;
                
                // Trigger QR code generation (which will use the base URL to create the modified QR code URL)
                qrTextInput.dispatchEvent(new Event('input'));
            }
        });

        function resetQRCode() {
            const qrTextInput = document.getElementById('qrtext');
            const qrPreview = document.getElementById('qr-preview');
            
            // Reset to default URL (just the base URL)
            qrTextInput.value = "http://localhost:3000/reportform.php";
            
            // Clear the preview
            qrPreview.innerHTML = '';
            delete qrPreview.dataset.qrUrl;
        }

        function toggleQRCodeVisibility() {
            const programType = document.getElementById('program-type').value;
            const qrCodeSection = document.getElementById('qr-code-section');
            
            if (programType === 'Announcement') {
                qrCodeSection.style.display = 'none';
                document.getElementById('qrtext').removeAttribute('required');
                resetQRCode(); // Reset when hiding
            } else {
                qrCodeSection.style.display = 'block';
                document.getElementById('qrtext').setAttribute('required', '');
                // Trigger QR code generation if there's already content
                if (document.getElementById('event-title').value) {
                    document.getElementById('qrtext').dispatchEvent(new Event('input'));
                }
            }
        }

        document.getElementById('program-type').addEventListener('change', function() {
            toggleQRCodeVisibility();
            
            if (this.value !== 'Announcement') {
                document.getElementById('event-creation-form').dispatchEvent(new Event('input'));
            }
        });

        toggleQRCodeVisibility();

        document.getElementById('thumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameElement = document.getElementById('file-name');
            const previewElement = document.getElementById('thumbnail-preview');
            
            if (file) {
                fileNameElement.textContent = file.name;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.innerHTML = `<img src="${e.target.result}" alt="Thumbnail Preview">`;
                };
                reader.readAsDataURL(file);
            } else {
                fileNameElement.textContent = 'No file chosen';
                previewElement.innerHTML = '';
            }
        });

    </script>
    <!-- event form scripts -->
    <script>
        function validateImage(input) {
            const errorElement = document.getElementById('thumbnail-error');
            const validImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            const form = input.closest('form');
            
            if (input.files && input.files[0]) {
                // Check if file is an image
                if (!validImageTypes.includes(input.files[0].type)) {
                    errorElement.textContent = 'Please select a valid image file (JPEG, PNG, WEBP, SVG)';
                    errorElement.style.display = 'block';
                    input.value = '';
                    
                    setTimeout(() => {
                        errorElement.style.display = 'none';
                    }, 3000);
                    
                    return false;
                }
                errorElement.style.display = 'none';
                previewThumbnail(input);
                input.classList.add('validated');
                return true;
            }
            return false;
        }

        function validateForm(event) {
            const thumbnailInput = document.getElementById('thumbnail');
            const errorElement = document.getElementById('thumbnail-error');
            
            // Check if thumbnail is required but not provided
            if (document.querySelector('input[name="thumbnail_required"]') && 
                (!thumbnailInput.files || thumbnailInput.files.length === 0)) {
                
                errorElement.textContent = 'Thumbnail is required';
                errorElement.style.display = 'block';
                errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Prevent form submission
                event.preventDefault();
                return false;
            }
            
            return true;
        }

        // Add event listener to your form
        document.querySelector('form').addEventListener('submit', validateForm);
    
        function previewThumbnail(input) {
            const reader = new FileReader();
            const preview = document.getElementById('thumbnail-preview');
            const placeholder = document.querySelector('.thumbnail-placeholder');
            const actions = document.querySelector('.thumbnail-actions');
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
                actions.style.display = 'flex';
            }
            
            reader.readAsDataURL(input.files[0]);
        }
        
        function removeThumbnail() {
            const thumbnailInput = document.getElementById('thumbnail');
            const preview = document.getElementById('thumbnail-preview');
            const placeholder = document.querySelector('.thumbnail-placeholder');
            const actions = document.querySelector('.thumbnail-actions');
            const errorElement = document.getElementById('thumbnail-error');
            
            // Reset everything
            thumbnailInput.value = '';
            thumbnailInput.classList.remove('validated');
            preview.style.display = 'none';
            preview.src = '';
            placeholder.style.display = 'flex';
            actions.style.display = 'none';
            errorElement.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const programTypeSelect = document.getElementById('program-type');
            const eventTypeContainer = document.querySelector('.event-type-container');
            const eventTypeInput = document.getElementById('event-type');
            const manualEventTypeInput = document.getElementById('manual-event-type');
            const dateContainer = document.querySelector('.form-group-dates');
            const locationContainer = document.querySelectorAll('.form-row');
            const ecoPointsContainer = document.getElementById('eco-points').closest('.form-group');
            const form = document.getElementById('event-creation-form');
            const areaNoInput = document.getElementById('area-no'); // Get the area-no input

            // Function to toggle fields based on program type
            function toggleFields() {
                if (programTypeSelect.value === 'Event') {
                    // Show event-specific fields
                    eventTypeContainer.style.display = 'block';
                    eventTypeInput.setAttribute('required', 'required');
                    dateContainer.style.display = 'flex';
                    locationContainer.forEach(container => container.style.display = 'flex');
                    ecoPointsContainer.style.display = 'block';
                    
                    // Re-add required attributes to event-specific fields (excluding area-no and location fields)
                    document.getElementById('start-date').setAttribute('required', 'required');
                    document.getElementById('end-date').setAttribute('required', 'required');
                    // NOTE: venue, city, barangay required attributes are managed by event_location_manager.js based on venue mode
                    // Do NOT set them here to avoid conflicts with automatic/manual venue toggle
                    
                    // CRITICAL: Remove required attribute from location fields when they become visible
                    console.log('🧹 TOGGLE FIELDS: Removing required from venue/city/barangay (fields now visible)');
                    
                     document.getElementById('venue-mode-toggle')?.removeAttribute('required');
                    document.getElementById('venue')?.removeAttribute('required');
                    document.getElementById('city')?.removeAttribute('required');
                    document.getElementById('barangay')?.removeAttribute('required');
                    console.log('  ✅ Required attributes removed from location fields');
                    
                    // Ensure area-no is not required
                    areaNoInput.removeAttribute('required');

                    //make the program type input disabled when it is event
                    programTypeSelect.setAttribute('disabled', 'disabled');
                } else {
                    // Hide event-specific fields (Announcement mode)
                    eventTypeContainer.style.display = 'none';
                    eventTypeInput.removeAttribute('required');
                    eventTypeInput.value = '';
                    manualEventTypeInput.removeAttribute('required');
                    manualEventTypeInput.value = '';
                    dateContainer.style.display = 'none';
                    locationContainer.forEach(container => container.style.display = 'none');
                    ecoPointsContainer.style.display = 'none';
                    
                    // Clear values and remove required attributes from hidden fields
                    document.querySelectorAll('.form-group-dates input').forEach(input => {
                        input.value = '';
                        input.removeAttribute('required');
                    });
                    
                    // Remove required from location fields and clear values
                    document.getElementById('venue').removeAttribute('required');
                    document.getElementById('venue').value = '';
                    document.getElementById('city').removeAttribute('required');
                    document.getElementById('city').value = '';
                    document.getElementById('barangay').removeAttribute('required');
                    document.getElementById('barangay').value = '';
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                    
                    document.getElementById('eco-points').value = '';
                    
                    // Ensure area-no is not required
                    areaNoInput.removeAttribute('required');
                    areaNoInput.value = '';
                }
            }

            // Initial toggle on page load
            toggleFields();
            
            // Toggle fields when program type changes
            programTypeSelect.addEventListener('change', toggleFields);

            // Handle event type mode toggle (Select from list vs Manual input)
            const eventTypeModeToggle = document.getElementById('event-type-mode-toggle');
            const eventTypeModeLabel = document.getElementById('event-type-mode-label');
            const selectEventTypeContainer = document.getElementById('select-event-type-container');
            const eventTypeSelect = document.getElementById('event-type');
            const manualEventTypeContainer = document.getElementById('manual-event-type-container');
            // manualEventTypeInput is already declared at the top of this function

            eventTypeModeToggle.addEventListener('change', function() {
                const venueSet = document.getElementById('latitude').value && document.getElementById('longitude').value;
                
                if (this.checked) {
                    // Manual input mode (for non-mangrove area events)
                    eventTypeModeLabel.textContent = 'Switch to List Selection';
                    selectEventTypeContainer.style.display = 'none';
                    eventTypeSelect.removeAttribute('required');
                    eventTypeSelect.value = '';
                    manualEventTypeContainer.style.display = 'block';
                    manualEventTypeInput.setAttribute('required', 'required');
                    
                    // Disable area selection for non-mangrove events
                    if (window.mangroveAreaSelector) {
                        window.mangroveAreaSelector.setEnabled(false, venueSet);
                    }
                } else {
                    // Select from list mode (for mangrove area events)
                    eventTypeModeLabel.textContent = 'Switch to Manual Input';
                    selectEventTypeContainer.style.display = 'block';
                    eventTypeSelect.setAttribute('required', 'required');
                    manualEventTypeContainer.style.display = 'none';
                    manualEventTypeInput.removeAttribute('required');
                    manualEventTypeInput.value = '';
                    
                    // Enable area selection for mangrove area events
                    if (window.mangroveAreaSelector) {
                        window.mangroveAreaSelector.setEnabled(true, venueSet);
                    }
                }
            });
            
            // Initialize area selector state on page load
            // Check if coordinates and area_no already exist (editing existing event)
            setTimeout(() => {
                if (window.mangroveAreaSelector) {
                    const latitudeValue = document.getElementById('latitude').value;
                    const longitudeValue = document.getElementById('longitude').value;
                    const areaNoValue = document.getElementById('area-no').value;
                    const venueSet = latitudeValue && longitudeValue;
                    const isMangroveEvent = !eventTypeModeToggle.checked;
                    
                    // Enable area selector if venue coordinates are set
                    window.mangroveAreaSelector.setEnabled(isMangroveEvent, venueSet);
                    
                    // If coordinates and area_no exist, enable the area button directly
                    if (venueSet && areaNoValue && isMangroveEvent) {
                        const areaMapButton = document.getElementById('area-map-button');
                        if (areaMapButton) {
                            areaMapButton.disabled = false;
                            areaMapButton.classList.add('enabled');
                        }
                    }
                }
            }, 500);

            // Form submission handler
            form.addEventListener('submit', function(e) {
                console.log('Form submitting...');
                console.log('Program Type:', programTypeSelect.value);
                
                // CRITICAL: Remove required attribute from location fields to prevent browser validation issues
                // JavaScript validation will handle this properly
                const venueInput = document.getElementById('venue');
                const cityInput = document.getElementById('city');
                const barangayInput = document.getElementById('barangay');
                venueInput?.removeAttribute('required');
                cityInput?.removeAttribute('required');
                barangayInput?.removeAttribute('required');
                console.log('🧹 Removed required attributes from venue/city/barangay (if any existed)');
                
                // Always ensure area-no is never required on submission
                areaNoInput.removeAttribute('required');
                
                // For Announcements, ensure all event-specific fields are not required
                if (programTypeSelect.value === 'Announcement') {
                    document.getElementById('start-date').removeAttribute('required');
                    document.getElementById('end-date').removeAttribute('required');
                    document.getElementById('venue').removeAttribute('required');
                    document.getElementById('city').removeAttribute('required');
                    document.getElementById('barangay').removeAttribute('required');
                    eventTypeInput.removeAttribute('required');
                    manualEventTypeInput.removeAttribute('required');
                    
                    // Clear hidden field values to prevent sending empty strings
                    document.getElementById('start-date').value = '';
                    document.getElementById('end-date').value = '';
                    document.getElementById('venue').value = '';
                    document.getElementById('city').value = '';
                    document.getElementById('barangay').value = '';
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                    eventTypeInput.value = '';
                    manualEventTypeInput.value = '';
                    areaNoInput.value = '';
                } else {
                    // For Events, validate venue/city/barangay in automatic mode
                    const venueModeToggle = document.getElementById('venue-mode-toggle');
                    const isManualMode = venueModeToggle && venueModeToggle.checked;
                    
                    if (!isManualMode) {
                        // Automatic mode - validate that fields are filled from map
                        const venueInput = document.getElementById('venue');
                        const cityInput = document.getElementById('city');
                        const barangayInput = document.getElementById('barangay');
                        
                        if (!venueInput.value || !venueInput.value.trim()) {
                            alert('Please select a venue from the map by clicking the map button.');
                            e.preventDefault();
                            return;
                        }
                        
                        if (!cityInput.value || !cityInput.value.trim()) {
                            alert('Please select a location from the map to auto-fill the city/municipality.');
                            e.preventDefault();
                            return;
                        }
                        
                        if (!barangayInput.value || !barangayInput.value.trim()) {
                            alert('Please select a location from the map to auto-fill the barangay.');
                            e.preventDefault();
                            return;
                        }
                    }
                }
                
                // Validate links if they exist
                const linksTextarea = document.getElementById('link');
                if (linksTextarea && linksTextarea.value.trim()) {
                    const links = linksTextarea.value.split('\n').filter(link => link.trim() !== '');
                    for (const link of links) {
                        if (!link.match(/^https?:\/\/.+/)) {
                            alert(`Invalid URL: ${link}\nAll links must start with http:// or https://`);
                            e.preventDefault();
                            return;
                        }
                    }
                }
                
                console.log('Form validation passed, submitting...');
            });
        });

    document.addEventListener('DOMContentLoaded', function() {
        const endDateInput = document.getElementById('end-date');
        const featuredEndDateSpan = document.getElementById('featured-enddate-span');
        const featuredEndDateInput = document.getElementById('featured-enddate');
        
        if (endDateInput && featuredEndDateSpan && featuredEndDateInput) {
            endDateInput.addEventListener('change', function() {
                // Get the new end date value
                const newEndDate = new Date(this.value);
                
                // Format for display (e.g., "January 1, 2023, 12:00 PM")
                const formattedDate = newEndDate.toLocaleString('en-US', {
                    timeZone: 'Asia/Manila', // Set timezone to Philippine time
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
                
                // Update the display
                featuredEndDateSpan.textContent = formattedDate;
                
                // Update the hidden input value (ISO format)
                featuredEndDateInput.value = endDateInput.value;
            });
        }
    });
    </script>
    <!-- Map and location handling is now managed by event_location_manager.js -->
    <!-- cross barangay additional data script -->
<script>
// File upload handling for cross-barangay events (now managed by toggle)
document.addEventListener('DOMContentLoaded', function() {
    const attachmentsInput = document.getElementById('event-attachments');
    const filePreviewContainer = document.getElementById('file-preview-container');
    const sizeWarning = document.getElementById('size-warning');
    const fileCounter = document.getElementById('file-counter');
    let totalSize = 0;
    const MAX_SIZE = 50 * 1024 * 1024; // 50MB in bytes

    // File upload handling
    const dropZone = document.getElementById('drop-zone');

    // Click handler for file upload area
    dropZone.addEventListener('click', function() {
        attachmentsInput.click();
    });

    // Handle file selection
    attachmentsInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag and drop handlers
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        dropZone.classList.add('highlight');
    }

    function unhighlight() {
        dropZone.classList.remove('highlight');
    }

    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    });

    function handleFiles(files) {
        if (!files || files.length === 0) return;
        
        // Reset if empty selection
        if (files.length === 0) {
            filePreviewContainer.innerHTML = '';
            totalSize = 0;
            updateFileCounter();
            return;
        }
        
        // Process each file
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check file type
            const validTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'image/jpeg',
                'image/png'
            ];
            
            if (!validTypes.includes(file.type)) {
                alert(`File type not supported: ${file.name}`);
                continue;
            }
            
            totalSize += file.size;
            
            if (totalSize > MAX_SIZE) {
                sizeWarning.textContent = 'Total size exceeds 50MB limit! Please remove some files.';
                sizeWarning.style.display = 'block';
                totalSize -= file.size; // Revert the size addition
                continue;
            } else {
                sizeWarning.style.display = 'none';
            }
            
            addFilePreview(file);
        }
        
        updateFileCounter();
        updateFileInput();
    }
    
    function addFilePreview(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-preview-item';
        fileItem.dataset.fileName = file.name;
        
        const fileInfo = document.createElement('div');
        fileInfo.className = 'file-info';
        
        const fileIcon = document.createElement('i');
        fileIcon.className = 'file-icon fas ' + getFileIcon(file.type);
        
        const fileName = document.createElement('span');
        fileName.className = 'file-name';
        fileName.textContent = file.name;
        
        const fileSize = document.createElement('span');
        fileSize.className = 'file-size';
        fileSize.textContent = formatFileSize(file.size);
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-file';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = function() {
            totalSize -= file.size;
            fileItem.remove();
            updateFileCounter();
            updateFileInput();
        };
        
        fileInfo.appendChild(fileIcon);
        fileInfo.appendChild(fileName);
        fileInfo.appendChild(fileSize);
        fileItem.appendChild(fileInfo);
        fileItem.appendChild(removeBtn);
        filePreviewContainer.appendChild(fileItem);
    }
    
    function updateFileCounter() {
        const fileCount = filePreviewContainer.children.length;
        if (fileCount > 0) {
            fileCounter.textContent = `${fileCount} file(s) selected (${formatFileSize(totalSize)})`;
            fileCounter.style.display = 'block';
        } else {
            fileCounter.style.display = 'none';
        }
    }
    
    function updateFileInput() {
        sizeWarning.style.display = totalSize > MAX_SIZE ? 'block' : 'none';
    }
    
    function getFileIcon(type) {
        if (type.match('image.*')) return 'fa-file-image';
        if (type.match('application/pdf')) return 'fa-file-pdf';
        if (type.match('application/msword') || type.match('application/vnd.openxmlformats-officedocument.wordprocessingml.document')) 
            return 'fa-file-word';
        if (type.match('application/vnd.ms-excel') || type.match('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) 
            return 'fa-file-excel';
        if (type.match('application/vnd.ms-powerpoint') || type.match('application/vnd.openxmlformats-officedocument.presentationml.presentation')) 
            return 'fa-file-powerpoint';
        return 'fa-file';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const removedAttachmentsInput = document.getElementById('removed-attachments');
    let removedAttachments = new Set();
    
    function handleAttachmentRemoval(btn) {
        const filePath = btn.dataset.filePath;
        const attachmentCard = btn.closest('.attachment-card');
        
        if (removedAttachments.has(filePath)) {
            // Undo removal
            removedAttachments.delete(filePath);
            attachmentCard.classList.remove('removed');
            btn.innerHTML = '<i class="fas fa-trash"></i> Remove';
            btn.classList.remove('undo-btn');
            btn.classList.add('remove-btn');
        } else {
            // Mark for removal
            removedAttachments.add(filePath);
            attachmentCard.classList.add('removed');
            btn.innerHTML = '<i class="fas fa-undo"></i> Undo';
            btn.classList.remove('remove-btn');
            btn.classList.add('undo-btn');
        }
        
        // Update hidden input
        removedAttachmentsInput.value = Array.from(removedAttachments).join('|');
    }
    
    // Set up event listeners
    document.querySelectorAll('.remove-attachment-btn').forEach(btn => {
        btn.classList.add('remove-btn'); // Initial state
        btn.addEventListener('click', function() {
            handleAttachmentRemoval(this);
        });
    });
});
</script>
</body>
</html>
