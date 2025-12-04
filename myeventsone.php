<!--
<?php
    session_start();
    include 'database.php';
    //check if the user is logged in
    if (!isset($_SESSION['user_id'])) {
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
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="events.css">
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
            <a href="about.php">About</a>
            <a href="events.php" class="active">Events</a>
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
                <a href="dashboard.html" tabindex="-1">
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
                    <li><a href="reportspage.php" tabindex="-1">Growth Reports</a></li>
                    <li><a href="#" tabindex="-1">Illegal Activities</a></li>
                    <li><a href="#" tabindex="-1">Conservation Programs</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>To Do Lists</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu">
                    <div>
                    <li><a href="#" tabindex="-1">Work</a></li>
                    <li><a href="#" tabindex="-1">Private</a></li>
                    <li><a href="#" tabindex="-1">Coding</a></li>
                    <li><a href="#" tabindex="-1">Gardening</a></li>
                    <li><a href="#" tabindex="-1">School</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <a href="random.html" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Random</span>
                </a>
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
                        echo '<img src="'.$_SESSION['profile_image'].'" alt='.$_SESSION['profile_image'].' class="big-profile-icon">';
                    } else {
                        echo '<div class="big-default-profile-icon"><i class="fas fa-user"></i></div>';
                    }
                ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <p><?= isset($_SESSION["organization"]) ? $_SESSION["organization"] : "" ?></p>
                <div class="profile-link-container">
                    <a href="profileform.php" class="profile-link">Edit Profile <i class="fa fa-angle-double-right"></i></a>
                </div>
                </div>
        <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <!-- events page container-->
        <div class="events-container">
            <!-- Page Title Section -->
            <div class="page-title">
                <h1>My <span class="man">Man</span><span class="grow">Grow</span> Events</h1>
            </div>

            <!-- Dual-Column Layout -->
            <div class="events-content">
                
                <!-- Left Column - Filters -->
                <div class="events-filters">
                    <div class="filter-section">
                        <h2>Filters</h2>
                        <div class="filter-group">
                            <label>Event Type:</label>
                            <select id="filter-type">
                                <option value="all">All Events</option>
                                <option value="Status Report">Status Report</option>
                                <option value="Tree Planting">Tree Planting</option>
                                <option value="Announcement">Announcement</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Date Range:</label>
                            <div class="date-inputs">
                                <input type="date" id="start-date">
                                <span>to</span>
                                <input type="date" id="end-date">
                            </div>
                        </div>
                        <button class="apply-filters">Apply Filters</button>
                    </div>

                    <div class="quick-links">
                        <h2>Quick Actions</h2>
                        <?php
                        if(isset($_SESSION['accessrole'])){
                            if($_SESSION['accessrole'] == 'Resident' && $_SESSION['organization'] == 'N/A'){
                                echo '';
                            }
                            else{
                        ?><button class="create-event" onclick="window.location.href = `create_event.php?organization=${encodeURIComponent('<?php echo $_SESSION['organization'];?>')}`;">
                            <i class="fas fa-plus"></i> Create New Event
                        </button><?php
                        }}?>
                    </div>
                    <button class="my-events" onclick="window.location.href = 'events.php';" style="width:fit-content;">
                            <i class="fas fa-user"></i> Back
                        </button>
                </div>

                <!-- Right Column - Events Feed -->
           <div class="events-feed">
                <?php   
                if(isset($_SESSION['name'])) {
                    $author = $_SESSION['name'];
                }
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Resident' && $_SESSION['organization'] == 'N/A') {
                    $query = "SELECT e.* FROM eventstbl e 
                              INNER JOIN attendeestbl a ON e.event_id = a.event_id 
                              WHERE a.account_id = ?";
                    $stmt = $connection->prepare($query);
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if(mysqli_num_rows($result) > 0){
                        while ($items = $result->fetch_assoc()) {
                            // Determine which date to display based on program_type
                            $displayDate = ($items['program_type'] === 'Announcement') 
                                ? $items['created_at'] 
                                : $items['posted_at'];
                            
                            // Format the date properly
                            $formattedDate = date("F j, Y", strtotime($displayDate));
                            $formattedTime = ($items['program_type'] === 'Announcement')
                                ? '' // No time for announcements
                                : date("g:i A", strtotime($displayDate));
                            
                            // Add disapproved class if needed
                            $disapprovedClass = ($items['is_approved'] == 'Disapproved') ? 'disapproved' : '';
                            
                            echo '
                                <div class="event-post '.$disapprovedClass.'">
                                    <div class="event-header">
                                        <span class="event-date">'.$formattedDate;
                                        
                            if (!empty($formattedTime)) {
                                echo ' <span class="event-time">'.$formattedTime.'</span>';
                                if (!empty($items['edited_at'])) {
                                    $editedDate = date("F j, Y, g:i A", strtotime($items['edited_at']));
                                    echo ' <span class="event-edited" style="color: #123524;">(Edited: '.$editedDate.')</span>';
                                }
                            }
                            
                            echo '</span>
                                        <span class="event-author">
                                            '.(($items['is_approved'] === 'Pending') 
                                                ? 'Submitted by: '.$items['organization'] 
                                                : 'Posted by: '.$items['organization']).'
                                        </span>
                                    </div>
                                    <div class="event-status" style="background-color: ' . 
                                        ($items['is_approved'] == 'Approved' ? '#d4edda' : 
                                        ($items['is_approved'] == 'Disapproved' ? '#f8d7da' : 
                                        '#fff3cd')) . ';">
                                        Approval Status: '.$items['is_approved'].'
                                    </div>
                                    <h3>'.$items['title'];

                            // Only show location if not an announcement
                            if ($items['program_type'] !== 'Announcement') {
                                echo ' at Brgy. '.$items['barangay'];
                            }
                            
                            echo '</h3>';
                            // Set timezone to Philippine Time
                            date_default_timezone_set('Asia/Manila');
                            $currentDate = date("Y-m-d");
                            if (isset($items['end_date']) && isset($items['posted_at'])) {
                                if ($items['end_date'] > $currentDate && $currentDate < date("Y-m-d", strtotime($items['posted_at']))) {
                                    $eventStatus = "Upcoming";
                                } elseif ($items['end_date'] < $currentDate) {
                                    $eventStatus = "Completed";
                                } else {
                                    $eventStatus = "Ongoing";
                                }
                                echo '<div class="event-status">Status: ' . $eventStatus . '</div>';
                            }
                            if($items['is_approved'] == 'Disapproved') {
                                echo '
                                    <div class="disapproval-note">
                                        Note from <strong>'.$items['approved_by'].'</strong> <span style="color: gray;">Commented on ('.$items['approval_date'].')</span>:<br>
                                        '.$items['disapproval_note'].'
                                    </div>';
                            }
                            
                            if (!empty($items['thumbnail'])) {
                                echo '
                                    <div class="event-image">
                                        <img src="'.$items['thumbnail'].'" alt="'.$items['thumbnail_data'].'">
                                    </div>';
                            }
                            
                            if (!empty($items['description'])) {
                                echo '<p class="event-desc">'.$items['description'].'</p>';
                            }
                                
                            echo '</div>';
                        }
                    }else{
                        echo '<div class="event-post">No events found...</div>';
                    }
                }
                else{
                $query = "SELECT * FROM eventstbl 
                        WHERE author = ?
                        ORDER BY created_at DESC"; // Added sorting by date
                $stmt = $connection->prepare($query);
                $stmt->bind_param("s", $author);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if (mysqli_num_rows($result) > 0) {
                    while($items = mysqli_fetch_assoc($result)) {
                        // Determine which date to display based on program_type
                        $displayDate = ($items['program_type'] === 'Announcement') 
                            ? $items['created_at'] 
                            : $items['posted_at'];
                        
                        // Format the date properly
                        $formattedDate = date("F j, Y", strtotime($displayDate));
                        $formattedTime = ($items['program_type'] === 'Announcement')
                            ? '' // No time for announcements
                            : date("g:i A", strtotime($displayDate));
                        
                        // Add disapproved class if needed
                        $disapprovedClass = ($items['is_approved'] == 'Disapproved') ? 'disapproved' : '';
                        
                        ?><div class="event-post '.$disapprovedClass.'">
                                <div class="event-header">
                                    <span class="event-date"> <?php echo $formattedDate. ' | ' .$formattedTime ?>
                            <?php if (!empty($items['edited_at'])) {
                                $editedDate = date("F j, Y, g:i A", strtotime($items['edited_at'])); ?>
                                | <span class="event-edited" style="color: #123524;">(Edited: <?php echo $editedDate ?>)</span></span>
                            <?php
                            } 
                        echo '</span>
                                    <span class="event-author">
                                        '.(($items['is_approved'] === 'Pending') 
                                            ? 'Submitted by: '.$items['organization'] 
                                            : 'Posted by: '.$items['organization']).'
                                    </span>
                                </div>
                                <div class="event-status" style="background-color: ' . 
                                    ($items['is_approved'] == 'Approved' ? '#d4edda' : 
                                    ($items['is_approved'] == 'Disapproved' ? '#f8d7da' : 
                                    '#fff3cd')) . ';">
                                    Approval Status: '.$items['is_approved'].'
                                </div>
                                <h3>'.$items['title'];
                        
                        // Only show location if not an announcement
                        if ($items['program_type'] !== 'Announcement') {
                            echo ' at Brgy. '.$items['barangay'];
                        }
                        
                        echo '</h3>';
                         // Set timezone to Philippine Time
                        date_default_timezone_set('Asia/Manila');
                        $currentDate = date("Y-m-d");
                        if (isset($items['end_date']) && isset($items['posted_at'])) {
                            if ($items['end_date'] > $currentDate && $currentDate < date("Y-m-d", strtotime($items['posted_at']))) {
                                $eventStatus = "Upcoming";
                            } elseif ($items['end_date'] < $currentDate) {
                                $eventStatus = "Completed";
                            } else {
                                $eventStatus = "Ongoing";
                            }
                            echo '<div class="event-status">Status: ' . $eventStatus . '</div>';
                        }

                        if($items['is_approved'] == 'Disapproved') {
                            echo '
                                <div class="disapproval-note">
                                    Note from <strong>'.$items['approved_by'].'</strong> <span style="color: gray;">Commented on ('.$items['approval_date'].')</span>:<br>
                                    '.$items['disapproval_note'].'
                                </div>';
                        }
                        
                        if (!empty($items['thumbnail'])) {
                            echo '
                                <div class="event-image">
                                    <img src="'.$items['thumbnail'].'" alt="'.$items['thumbnail_data'].'">
                                </div>';
                        }
                        
                        if (!empty($items['description'])) {
                            echo '<p class="event-desc">'.$items['description'].'</p>';
                        }

                        echo '
                            <div class="event-footer">
                                <div class="event-actions">
                                    <button class="edit-event" onclick="window.location.href=\'edit_event.php?event_id='.$items['event_id'].'\'">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="delete-event" onclick="if(confirm(\'Are you sure you want to delete this event?\')) { window.location.href=\'delete_event.php?event_id='.$items['event_id'].'\'; }">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>';
                            
                        echo '</div>';
                    }
                } else {
                    echo '<div class="event-post">No events found...</div>';
                }
            }
                ?>
            </div>

            <div id="event-modal" class="event-modal">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div id="modal-body">
                        <!-- Content will be dynamically inserted here -->
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
                
        document.querySelector('.apply-filters').addEventListener('click', function () {
            const filterType = document.getElementById('filter-type').value;
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;

            const eventPosts = document.querySelectorAll('.event-post');

            eventPosts.forEach(eventPost => {
                const eventType = eventPost.querySelector('.event-status').textContent.trim();
                const eventDate = new Date(eventPost.querySelector('.event-date').textContent.trim());

                let matchesType = filterType === 'all' || eventType.includes(filterType);
                let matchesDate = true;

                if (startDate) {
                    matchesDate = matchesDate && eventDate >= new Date(startDate);
                }
                if (endDate) {
                    matchesDate = matchesDate && eventDate <= new Date(endDate);
                }

                if (matchesType && matchesDate) {
                    eventPost.style.display = 'block';
                } else {
                    eventPost.style.display = 'none';
                }
            });
        });

    </script>
</body>
</html>