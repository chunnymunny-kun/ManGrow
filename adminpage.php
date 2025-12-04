<?php
    session_start();
    include 'database.php';


    if(isset($_SESSION["accessrole"])){
        // Check if role is NOT in allowed list
        if($_SESSION["accessrole"] != 'Barangay Official' && 
           $_SESSION["accessrole"] != 'Administrator' && 
           $_SESSION["accessrole"] != 'Representative') {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'This account is not authorized'
            ];
            header("Location: index.php");
            exit();
        }
        
        // Set variables if logged in with proper role
        if(isset($_SESSION["name"])){
            $email = $_SESSION["name"];
        }
        if(isset($_SESSION["accessrole"])){
            $accessrole = $_SESSION["accessrole"];
        }
    } else {
        // No accessrole set at all - redirect to login
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Please login first'
        ];
        header("Location: index.php");
        exit();
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Lobby</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminpagecontent.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="adminusers.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"){ ?>
                <li><a href="adminprofile.php"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>
                <li><a href="adminleaderboards.php"><i class="far fa-chart-bar" style="margin-bottom:-5px"></i></a></li>
                <?php } ?>
            </ul>
        </nav>
        
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
    </header>
    <main>
    <?php
    if (isset($_GET['view_event'])) {
        $eventId = (int)$_GET['view_event'];
        echo <<<HTML
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a brief moment to ensure all elements are loaded
            setTimeout(function() {
                handleViewButtonClick($eventId);
            }, 300);
        });
        </script>
        HTML;
    }
    ?>
<?php 
    $statusType = '';
    $statusMsg = '';
    //<!-- Flash Message Display -->
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
    <!-- Display status message -->
    <?php if(!empty($statusMsg)){ ?>
    <div class="col-xs-12">
        <div class="alert alert-<?php echo $status; ?>"><?php echo $statusMsg; ?></div>
    </div>
    <?php } ?>
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
    
            <!-- adminpage content -->
        <div class="adminpage-strip">
            <div class="adminheader">
                <h1>Welcome to Admin Page</h1>
            </div>
        <div class="stats-container">
            <div class="stats-card">
                <h3>Total Mangrove Data Reports</h3>
                <h2>
                    20        
                </h2>
            </div>
            <div class="stats-card">
                <h3>Total Illegal Activity Reports</h3>
                <h2>
                    40        
                </h2>
            </div>
            <div class="stats-card">
                <h3>Ongoing Events
                <?php
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                    echo ' in Barangay ' . htmlspecialchars($_SESSION['barangay']) . ', ' . htmlspecialchars($_SESSION['city_municipality']);
                }
                ?>
                </h3>
                <h2>
                    <?php
                    // Set timezone to Philippine Standard Time
                    date_default_timezone_set('Asia/Manila');
                    $now = date('Y-m-d H:i:s');

                    // Build query for ongoing events
                    $ongoingQuery = "SELECT COUNT(*) AS ongoing_count FROM eventstbl WHERE program_type != 'Announcement' AND start_date <= '$now' AND end_date >= '$now'";
                    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                        $ongoingQuery = "SELECT COUNT(*) AS ongoing_count FROM eventstbl WHERE program_type != 'Announcement' AND start_date <= '$now' AND end_date >= '$now' AND barangay = '{$_SESSION['barangay']}' AND city_municipality = '{$_SESSION['city_municipality']}'";
                    }
                    $ongoingResult = mysqli_query($connection, $ongoingQuery);
                    $ongoingCount = 0;
                    if($ongoingResult && $row = mysqli_fetch_assoc($ongoingResult)) {
                        $ongoingCount = (int)$row['ongoing_count'];
                    }
                    echo $ongoingCount;
                    ?>
                </h2>
            </div>
            <div class="stats-card">
                <h3>Completed Events
                <?php
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                    echo ' in Barangay ' . htmlspecialchars($_SESSION['barangay']) . ', ' . htmlspecialchars($_SESSION['city_municipality']);
                }
                ?>
                </h3>
                <h2>
                    <?php
                    // Set timezone to Philippine Standard Time
                    date_default_timezone_set('Asia/Manila');
                    $now = date('Y-m-d');

                    // Build query for completed events (event_status = 'Completed')
                    $completedQuery = "SELECT COUNT(*) AS completed_count FROM eventstbl WHERE program_type != 'Announcement' AND event_status = 'Completed'";
                    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                        $completedQuery = "SELECT COUNT(*) AS completed_count FROM eventstbl WHERE program_type != 'Announcement' AND event_status = 'Completed' AND barangay = '{$_SESSION['barangay']}' AND city_municipality = '{$_SESSION['city_municipality']}'";
                    }
                    $completedResult = mysqli_query($connection, $completedQuery);
                    $completedCount = 0;
                    if($completedResult && $row = mysqli_fetch_assoc($completedResult)) {
                        $completedCount = (int)$row['completed_count'];
                    }
                    echo $completedCount;
                    ?>
                </h2>
            </div>
        </div>
    <div class="events-card-strip">

    <div class="admin-filters">
    <h2>Filter Events</h2>
    <div class="filter-row">
        <div class="filter-group">
            <label for="filter-type">Event Type</label>
            <select id="filter-type">
                <option value="all">All Types</option>
                <?php
                $typesQuery = "SELECT DISTINCT event_type FROM eventstbl WHERE program_type != 'Announcement' ORDER BY event_type";
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                    $typesQuery = "SELECT DISTINCT event_type FROM eventstbl WHERE program_type != 'Announcement' AND barangay = '{$_SESSION['barangay']}' AND city_municipality = '{$_SESSION['city_municipality']}' ORDER BY event_type";
                }
                $typesResult = mysqli_query($connection, $typesQuery);
                while($type = mysqli_fetch_assoc($typesResult)) {
                    echo '<option value="'.htmlspecialchars($type['event_type']).'">'.htmlspecialchars($type['event_type']).'</option>';
                }
                ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-status">Approval Status</label>
                    <select id="filter-status">
                        <option value="all">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Disapproved">Disapproved</option>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-date-from">From Date</label>
                    <input type="date" id="filter-date-from">
                </div>
                <div class="filter-group">
                    <label for="filter-date-to">Date Posted</label>
                    <input type="date" id="filter-date-to">
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-organization">Organization</label>
                    <select id="filter-organization">
                        <option value="all">All Organizations</option>
                        <?php
                        $orgQuery = "SELECT DISTINCT organization FROM eventstbl WHERE program_type != 'Announcement' ORDER BY organization";
                        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                            $orgQuery = "SELECT DISTINCT organization FROM eventstbl WHERE program_type != 'Announcement' AND barangay = '{$_SESSION['barangay']}' AND city_municipality = '{$_SESSION['city_municipality']}' ORDER BY organization";
                        }
                        $orgResult = mysqli_query($connection, $orgQuery);
                        while($org = mysqli_fetch_assoc($orgResult)) {
                            
                            echo '<option value="'.htmlspecialchars($org['organization']).'">'.htmlspecialchars($org['organization']).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-location">Location</label>
                    <select id="filter-location">
                        <option value="all" <?= (!isset($_SESSION['barangay']) || empty($_SESSION['barangay'])) ? 'selected' : '' ?>>All Locations</option>
                        <?php
                        $locQuery = "SELECT DISTINCT barangay FROM eventstbl WHERE program_type != 'Announcement' ORDER BY barangay";
                        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                            $locQuery = "SELECT DISTINCT barangay FROM eventstbl WHERE program_type != 'Announcement' AND barangay = '{$_SESSION['barangay']}' AND city_municipality = '{$_SESSION['city_municipality']}' ORDER BY barangay";
                        }
                        
                        $locResult = mysqli_query($connection, $locQuery);
                        while($loc = mysqli_fetch_assoc($locResult)) {
                            $isSelected = (isset($_SESSION['barangay']) && $_SESSION['barangay'] == $loc['barangay']) ? 'selected' : '';
                            echo '<option value="'.htmlspecialchars($loc['barangay']).'" '.$isSelected.'>Brgy. '.htmlspecialchars($loc['barangay']).'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="filter-btn reset-btn" id="reset-filters">Reset</button>
                <button class="filter-btn apply-btn" id="apply-filters">Apply Filters</button>
            </div>
        </div>
        <div class="events-head" style="text-align:center; padding-top:20px; box-sizing:border-box; border-top:2px solid #123524;"><h1>Mangrow Events</h1></div>
        <div class="bulk-actions-container">
            <div class="bulk-checkbox">
                <input type="checkbox" id="select-all-events">
                <label for="select-all-events">Select All</label>
            </div>
            <div class="bulk-actions">
                <form method="post" action="bulk_approve_events.php" id="bulk-approve-form">
                    <input type="hidden" name="selected_events" id="selected-events-input">
                    <button type="submit" class="bulk-approve-btn" id="bulk-approve-btn" disabled>
                        <i class="fa fa-check"></i> Approve Selected
                    </button>
                </form>
            </div>
        </div>
    <?php 
    // Set timezone to Philippine Standard Time
    date_default_timezone_set('Asia/Manila');
    $now = date('Y-m-d H:i:s');

    // Build the query for Mangrow Events section (excludes completed events)
    $query = "SELECT * FROM eventstbl WHERE program_type != 'Announcement' 
            AND (
                /* Show all pending/disapproved events regardless of date */
                is_approved IN ('Pending', 'Disapproved') 
                OR 
                /* Show approved events that haven't ended yet */
                (is_approved = 'Approved' AND end_date >= '$now')
            )";

    // Add location restriction for Barangay Officials
    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
        $query .= " AND barangay = '{$_SESSION['barangay']}' 
                    AND city_municipality = '{$_SESSION['city_municipality']}'";
    }

    // Order by creation date (newest first)
    $query .= " ORDER BY created_at DESC";

    $result = mysqli_query($connection, $query);

    if (mysqli_num_rows($result) > 0) {
        while($items = mysqli_fetch_assoc($result)) {
            $created_date = date("F j, Y, g:i A", strtotime($items['created_at']));
            $edited_date = !empty($items['edited_at']) ? date("F j, Y, g:i A", strtotime($items['edited_at'])) : 'Not Edited';
            $iso_date = date("Y-m-d", strtotime($items['created_at'])); // For filtering
            ?>
            <div class="events-card" 
                data-type="<?php echo htmlspecialchars($items['event_type']); ?>"
                data-status="<?php echo htmlspecialchars($items['is_approved']); ?>"
                data-date="<?php echo $iso_date; ?>"
                data-organization="<?php echo htmlspecialchars($items['organization']); ?>"
                data-location="<?php echo htmlspecialchars($items['barangay']); ?>">
                    
                    <!-- Add checkbox for bulk selection -->
                    <?php if($items['is_approved'] == 'Pending'): ?>
                    <div class="event-checkbox">
                        <input type="checkbox" class="event-checkbox-input" name="event_ids[]" value="<?php echo $items['event_id']; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <button type="button" class="view-btn" data-event-id="<?php echo $items['event_id']; ?>">
                        <i class="fa fa-eye"></i> View
                    </button>

                    <div class="event-thumbnail">
                        <?php echo '<img src="' . $items['thumbnail'] . '" alt="' . htmlspecialchars($items['thumbnail_data']) . '">'; ?>
                    </div>
                    
                    <div class="event-description">
                        <h3><?php echo htmlspecialchars($items['subject']); ?> (<?php echo htmlspecialchars($items['is_approved']); ?>)</h3>
                        <div class="event-meta">
                            <p><strong>Location: </strong> 
                                <?php 
                                $locationParts = [];
                                if (!empty($items['venue'])) {
                                    $locationParts[] = $items['venue'];
                                }
                                if (!empty($items['barangay'])) {
                                    $locationParts[] = htmlspecialchars($items['barangay']);
                                }
                                if (!empty($items['city_municipality'])) {
                                    $locationParts[] = htmlspecialchars($items['city_municipality']);
                                }
                                if (!empty($items['area_no'])) {
                                    $locationParts[] = htmlspecialchars($items['area_no']);
                                }
                                echo htmlspecialchars(implode(', ', $locationParts));
                                ?>
                            </p>
                            <p><strong>Organized by: </strong> <?php echo htmlspecialchars($items['organization']); ?></p>
                            <p><strong>Event Type: </strong> <?php echo htmlspecialchars($items['program_type']); ?></p>
                            <p><strong>
                                <?php
                                    if ($items['is_approved'] == 'Pending') {
                                        echo 'Submitted on: ';
                                    } elseif ($items['is_approved'] == 'Disapproved') {
                                        echo 'Submitted on: ';
                                    } else {
                                        echo 'Posted on: ';
                                    }
                                ?>
                            </strong> <?php echo $created_date; ?>
                            <?php if (!empty($items['edited_at'])) { ?>
                                <strong style="color: gray; margin-left:10px;">Edited on: <?php echo $edited_date; ?></strong>
                            <?php } ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if($items['is_approved'] != 'Approved'){ ?>
                        <form method="post" action="eventapproval.php" class="approval-form">
                            <input type="hidden" name="event_id" value="<?php echo $items['event_id']; ?>">
                            <input type="hidden" name="approval_status" value="Approved"> <!-- Add this -->
                            <div class="event-side-footer">
                                <i class="fa fa-angle-double-left"></i>
                                <div class="action-buttons">
                                    <button type="submit" class="approve-btn">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="disapprove-btn" onclick="openDisapprovalModal('<?php echo $items['event_id']; ?>')">
                                        <i class="fa fa-times"></i> Disapprove
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                </div>
                <?php
            }
        } else {
            echo '<div class="events-card">No events available</div>';
        }
    ?>

    <!-- All Events Section -->
    <div class="events-head" style="text-align:center; padding-top:20px; box-sizing:border-box; border-top:2px solid #123524;">
        <h1>All Events (Including Completed)</h1>
    </div>

    <div class="all-events-container">
        <!-- Add filters for All Events section -->
        <div class="all-events-filters">
            <div class="filter-row">
                <div class="filter-group">
                    <input type="text" id="all-events-search" placeholder="Search events..." class="search-input">
                </div>
                <div class="filter-group">
                    <select id="all-events-barangay" class="filter-select">
                        <option value="all">All Barangays</option>
                        <?php
                        $barangayQuery = "SELECT DISTINCT barangay FROM eventstbl WHERE program_type != 'Announcement' ORDER BY barangay";
                        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                            $barangayQuery = "SELECT DISTINCT barangay FROM eventstbl WHERE program_type != 'Announcement' 
                                            AND barangay = '{$_SESSION['barangay']}' 
                                            AND city_municipality = '{$_SESSION['city_municipality']}' 
                                            ORDER BY barangay";
                        }
                        $barangayResult = mysqli_query($connection, $barangayQuery);
                        while($barangay = mysqli_fetch_assoc($barangayResult)) {
                            echo '<option value="'.htmlspecialchars($barangay['barangay']).'">Brgy. '.htmlspecialchars($barangay['barangay']).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="all-events-city" class="filter-select">
                        <option value="all">All Cities/Municipalities</option>
                        <?php
                        $cityQuery = "SELECT DISTINCT city_municipality FROM eventstbl WHERE program_type != 'Announcement' ORDER BY city_municipality";
                        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                            $cityQuery = "SELECT DISTINCT city_municipality FROM eventstbl WHERE program_type != 'Announcement' 
                                        AND barangay = '{$_SESSION['barangay']}' 
                                        AND city_municipality = '{$_SESSION['city_municipality']}' 
                                        ORDER BY city_municipality";
                        }
                        $cityResult = mysqli_query($connection, $cityQuery);
                        while($city = mysqli_fetch_assoc($cityResult)) {
                            echo '<option value="'.htmlspecialchars($city['city_municipality']).'">'.htmlspecialchars($city['city_municipality']).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <input type="date" id="all-events-date-from" class="date-input" placeholder="From Date">
                </div>
                <div class="filter-group">
                    <input type="date" id="all-events-date-to" class="date-input" placeholder="To Date">
                </div>
                <div class="filter-group">
                    <button id="all-events-reset" class="filter-btn reset-btn">Reset</button>
                </div>
            </div>
        </div>

        <div class="all-events-table">
            <div class="table-header">
                <div class="header-cell">Event</div>
                <div class="header-cell">Location</div>
                <div class="header-cell">Organization</div>
                <div class="header-cell">Type</div>
                <div class="header-cell">Status</div>
                <div class="header-cell">Date Posted</div>
                <div class="header-cell">Actions</div>
            </div>
            <div id="events-table-body">
                <!-- Events will be loaded here via JavaScript -->
            </div>
            <div class="pagination-controls">
                <button id="prev-page" disabled>Previous</button>
                <span id="page-info">Page 1 of 1</span>
                <button id="next-page" disabled>Next</button>
                <select id="items-per-page">
                    <option value="5">5 per page</option>
                    <option value="10" selected>10 per page</option>
                    <option value="20">20 per page</option>
                    <option value="50">50 per page</option>
                </select>
            </div>
        </div>
    </div>
    <div id="disapprovalModal" class="modal">
        <div class="modal-content">
            <div class="disapprove-header">
                <span class="close-modal" onclick="closeModal()">×</span>
                <h3>Reason for Disapproval</h3>
            </div>
            <form id="disapprovalForm" method="post" action="eventapproval.php">
                <input type="hidden" name="event_id" id="modal_event_id">
                <input type="hidden" name="approval_status" value="Disapproved">
                
                <div class="form-group">
                    <label for="disapproval_reason">Please specify the reason:</label>
                    <textarea id="disapproval_reason" name="disapproval_reason" rows="4" required></textarea>
                    
                    <div id="existing_note_container" style="margin-top: 15px; display: none;">
                        <h4 style="color:#123524;">Previous Note:</h4>
                        <div id="existing_disapproval_note" style="background:rgba(214, 133, 133,0.6); padding: 10px; border:1px solid rgb(214,133,133); text-align:justify; border-radius: 4px;"></div>
                    </div>
                </div>
                <button type="submit" class="modal-submit-btn">Submit Disapproval</button>
            </form>
        </div>
    </div>
</div>
<div id="approveModal" class="approve-modal">
        <div class="approve-modal-content">
            <div class="approve-header">
            <span class="approve-close-modal" onclick="closeApproveModal()">×</span>
            <h3>Confirm Approval</h3>
            <p>Are you sure you want to approve this event?</p>
            </div>
            <div class="approve-modal-actions">
                <button type="button" class="modal-cancel-btn" onclick="closeApproveModal()">Cancel</button>
                <button type="button" class="modal-confirm-btn" id="confirmApproveBtn">Yes, Approve</button>
            </div>
        </div>
    </div>
<div id="event-modal" class="modal">
    <div class="modal-content">
        <span class="close-event-modal">&times;</span>
        <div id="modal-body">
        </div>
    </div>
</div>
<!-- Attachment Preview Modal -->
<div id="attachmentPreviewModal" class="attachment-preview-modal">
    <div class="preview-modal-content">
        <span class="close-preview-modal" onclick="closeAttachmentPreview()">&times;</span>
        <div id="attachmentPreviewContent"></div>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js" defer></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" defer></script>
    <script type = "text/javascript" src="adminevents.js"></script>
    <script type = "text/javascript">
        function selectcitymunicipality(citymunicipality){
            console.log(Barangay);
        }

        function openDisapprovalModal(eventId) {
            document.getElementById('modal_event_id').value = eventId;
            document.getElementById('disapprovalModal').style.display = 'block';
            
            // Fetch existing disapproval note via AJAX
            fetch(`get_disapproval_note.php?event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.note) {
                        document.getElementById('existing_note_container').style.display = 'block';
                        document.getElementById('existing_disapproval_note').textContent = data.note;
                    } else {
                        document.getElementById('existing_note_container').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error fetching note:', error);
                    document.getElementById('existing_note_container').style.display = 'none';
                });
        }

        function closeModal() {
            document.getElementById('disapprovalModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('disapprovalModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const filterType = document.getElementById('filter-type');
            const filterStatus = document.getElementById('filter-status');
            const filterDateFrom = document.getElementById('filter-date-from');
            const filterDateTo = document.getElementById('filter-date-to');
            const filterOrganization = document.getElementById('filter-organization');
            const filterLocation = document.getElementById('filter-location');
            const applyBtn = document.getElementById('apply-filters');
            const resetBtn = document.getElementById('reset-filters');
            const eventCards = document.querySelectorAll('.events-card');

            // Apply filters
            applyBtn.addEventListener('click', function() {
                const typeValue = filterType.value;
                const statusValue = filterStatus.value;
                const dateFromValue = filterDateFrom.value;
                const dateToValue = filterDateTo.value;
                const orgValue = filterOrganization.value;
                const locValue = filterLocation.value;

                eventCards.forEach(card => {
                    const cardType = card.dataset.type;
                    const cardStatus = card.dataset.status;
                    const cardDate = card.dataset.date;
                    const cardOrg = card.dataset.organization;
                    const cardLoc = card.dataset.location;

                    let matches = true;

                    // Check type filter
                    if (typeValue !== 'all' && cardType !== typeValue) {
                        matches = false;
                    }

                    // Check status filter
                    if (statusValue !== 'all' && cardStatus !== statusValue) {
                        matches = false;
                    }

                    // Check date range
                    if (dateFromValue && cardDate < dateFromValue) {
                        matches = false;
                    }
                    if (dateToValue && cardDate > dateToValue) {
                        matches = false;
                    }

                    // Check organization
                    if (orgValue !== 'all' && cardOrg !== orgValue) {
                        matches = false;
                    }

                    // Check location
                    if (locValue !== 'all' && cardLoc !== locValue) {
                        matches = false;
                    }

                    // Show/hide card based on matches
                    if (matches) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });

            // Reset filters
            resetBtn.addEventListener('click', function() {
                filterType.value = 'all';
                filterStatus.value = 'all';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                filterOrganization.value = 'all';
                filterLocation.value = 'all';

                eventCards.forEach(card => {
                    card.classList.remove('hidden');
                });
            });
        });
    </script>
    <!-- approval modal script -->
    <script>
        let currentApprovalForm = null;
        function openApproveModal(form) {
            currentApprovalForm = form;
            document.getElementById('approveModal').style.display = 'block';
        }

        function closeApproveModal() {
            document.getElementById('approveModal').style.display = 'none';
            currentApprovalForm = null;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const approveForms = document.querySelectorAll('.approval-form');
            
            approveForms.forEach(form => {
                // Change to listen for button clicks instead of form submission
                const approveBtn = form.querySelector('.approve-btn');
                approveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    openApproveModal(form);
                });
            });
            
            // Handle confirm button click
            document.getElementById('confirmApproveBtn').addEventListener('click', function() {
                if (currentApprovalForm) {
                    // Create a hidden input for approval_status if not already present
                    if (!currentApprovalForm.querySelector('input[name="approval_status"]')) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'approval_status';
                        input.value = 'Approved';
                        currentApprovalForm.appendChild(input);
                    }
                    currentApprovalForm.submit();
                }
                closeApproveModal();
            });
        });
    </script>
    <!-- all events script -->
    <script>
// Define all functions in the global scope or make them accessible where needed
function handleViewButtonClick(eventId) {
    // First try to find the view button for this event and scroll to it
    const viewBtn = document.querySelector(`.view-btn[data-event-id="${eventId}"]`);
    if (viewBtn) {
        viewBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Highlight the card temporarily
        const card = viewBtn.closest('.events-card');
        if (card) {
            card.classList.add('highlighted');
            setTimeout(() => card.classList.remove('highlighted'), 2000);
        }
    }
    
    // Then open the modal
    fetchEventDetails(eventId);
}

async function fetchEventDetails(eventId) {
    try {
        const modal = document.getElementById('event-modal');
        const modalBody = document.getElementById('modal-body');
        
        // Show loading state
        modalBody.innerHTML = '<div class="loading">Loading event details...</div>';
        modal.style.display = 'block';
        
        // Fetch event data
        const response = await fetch(`getevents.php?event_id=${eventId}`);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const event = await response.json();
        
        // Format dates
        const startDate = new Date(event.start_date);
        const endDate = new Date(event.end_date);
        const formattedStart = startDate.toLocaleDateString('en-US', { 
            month: 'long', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
        const formattedEnd = endDate.toLocaleDateString('en-US', { 
            month: 'long', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
        
        // Generate modal content with admin actions
        let modalContent = `
            <div class="modal-header">
                <h3>${event.subject} <span class="status-badge status-${event.is_approved.toLowerCase()}">${event.is_approved}</span></h3>
                <p>Posted by: ${event.organization}</p>
            </div>
            <div class="event-image">
                <img src="${event.thumbnail}" alt="Event thumbnail">
            </div>
            <div class="event-details">
                <p><strong>Description:</strong> ${event.description}</p>
                ${event.venue ? `<p><strong>Venue:</strong> ${event.venue}</p>` : ''}
                ${event.barangay ? `<p><strong>Barangay:</strong> ${event.barangay}</p>` : ''}
                ${event.city_municipality ? `<p><strong>City/Municipality:</strong> ${event.city_municipality}</p>` : ''}
                <p><strong>Start:</strong> ${formattedStart}</p>
                <p><strong>End:</strong> ${formattedEnd}</p>
                ${event.participants ? `<p><strong>Participants:</strong> ${event.participants}</p>` : ''}
            </div>`;

        // Add special approval reason if requires_special_approval is 1
        if (event.requires_special_approval == 1) {
            modalContent += `
                <div class="special-approval-note">
                    <strong>Special Approval Reason:</strong> ${event.special_notes === null ? 'N/A' : event.special_notes}
                </div>`;
        }

        // Add attachments section if this is a cross-barangay event with attachments
        if (event.is_cross_barangay && event.attachments_metadata) {
            try {
                const attachments = JSON.parse(event.attachments_metadata);
                if (attachments.length > 0) {
                    modalContent += `
                        <div class="attachments-container">
                            <h3 class="attachments-title"><i class="fas fa-paperclip"></i> Supporting Documents</h3>
                            <p>This event required special approval as it was conducted outside the organizer's barangay.</p>
                            <div class="attachments-list">`;
                    
                    attachments.forEach(attachment => {
                        const fileExt = attachment.name.split('.').pop().toLowerCase();
                        const iconClass = getFileIconClass(fileExt);
                        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt);
                        const isPdf = fileExt === 'pdf';
                        const isViewable = isImage || isPdf;
                        
                        modalContent += `
                            <div class="attachment-item">
                                <div class="attachment-header">
                                    <i class="fas ${iconClass} attachment-icon"></i>
                                    <span class="attachment-name">${attachment.name}</span>
                                </div>
                                <div class="attachment-meta">
                                    <span>${formatFileSize(attachment.size)}</span>
                                    <span>Uploaded: ${new Date(attachment.upload_date).toLocaleDateString()}</span>
                                </div>
                                <div class="attachment-actions">
                                    <a href="${attachment.path}" download class="download-btn">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    ${isViewable ? `
                                    <button class="preview-btn" onclick="previewAttachment('${attachment.path}', '${fileExt}')">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    ` : ''}
                                </div>
                            </div>`;
                    });
                    
                    modalContent += `</div></div>`;
                }
            } catch (e) {
                console.error('Error parsing attachments:', e);
            }
        }

        // For the admin actions section
        if (event.is_approved === 'Pending' || event.is_approved === 'Disapproved') {
            modalContent += `
                <div class="admin-actions">
                    ${event.is_approved === 'Disapproved' && event.disapproval_note ? 
                        `<div class="disapproval-note">
                            <h4>Previous Disapproval Note:</h4>
                            <p>${event.disapproval_note}</p>
                        </div>` : ''}
                    <form method="post" action="eventapproval.php" class="approval-form">
                        <input type="hidden" name="event_id" value="${event.event_id}">
                        <input type="hidden" name="approval_status" value="Approved">
                        <button type="submit" class="approve-btn">
                            <i class="fa fa-check"></i> Approve Event
                        </button>
                        <button type="button" class="disapprove-btn" onclick="openDisapprovalModal('${event.event_id}')">
                            <i class="fa fa-times"></i> Disapprove Event
                        </button>
                    </form>
                </div>`;
        }
        
        modalBody.innerHTML = modalContent;
        
    } catch (error) {
        modalBody.innerHTML = `
            <div class="error">
                <p>Error loading event details.</p>
                <p>${error.message}</p>
            </div>
        `;
        console.error('Fetch error:', error);
    }
}

// Helper functions for attachments
function getFileIconClass(extension) {
    switch (extension.toLowerCase()) {
        case 'pdf': return 'fa-file-pdf';
        case 'doc':
        case 'docx': return 'fa-file-word';
        case 'xls':
        case 'xlsx': return 'fa-file-excel';
        case 'ppt':
        case 'pptx': return 'fa-file-powerpoint';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp': return 'fa-file-image';
        case 'zip':
        case 'rar':
        case '7z': return 'fa-file-archive';
        default: return 'fa-file';
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function previewAttachment(filePath, fileExt) {
    const modal = document.getElementById('attachmentPreviewModal');
    const content = document.getElementById('attachmentPreviewContent');
    
    // Clear previous content
    content.innerHTML = '';
    
    const ext = fileExt.toLowerCase();
    
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        // Image preview
        const img = document.createElement('img');
        img.src = filePath;
        img.className = 'attachment-preview-content';
        img.alt = 'Attachment Preview';
        content.appendChild(img);
    } else if (ext === 'pdf') {
        // PDF preview - using PDF.js would be better for full PDF support
        const iframe = document.createElement('iframe');
        iframe.src = filePath;
        iframe.className = 'pdf-viewer';
        content.appendChild(iframe);
    } else {
        // Unsupported file type
        content.innerHTML = `
            <div class="unsupported-preview">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Preview Not Available</h3>
                <p>This file type cannot be previewed in the browser.</p>
                <p>Please download the file to view it.</p>
                <a href="${filePath}" download class="download-btn">
                    <i class="fas fa-download"></i> Download File
                </a>
            </div>`;
    }
    
    // Show modal
    modal.style.display = 'block';
    
    // Close when clicking outside content
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeAttachmentPreview();
        }
    });
}

function closeAttachmentPreview() {
    document.getElementById('attachmentPreviewModal').style.display = 'none';
}

// Close with ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAttachmentPreview();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal close functionality
    const modal = document.getElementById('event-modal');
    const closeBtn = document.querySelector('.close-event-modal');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Set up event listeners for view buttons in Mangrow Events section
    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            handleViewButtonClick(eventId);
        });
    });

    // Pagination and filtering code
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalEvents = 0;
    let totalPages = 1;
    let currentFilters = {
        search: '',
        barangay: 'all',
        city: 'all',
        dateFrom: '',
        dateTo: ''
    };

    const eventsTableBody = document.getElementById('events-table-body');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageInfoSpan = document.getElementById('page-info');
    const itemsPerPageSelect = document.getElementById('items-per-page');
    const searchInput = document.getElementById('all-events-search');
    const barangayFilter = document.getElementById('all-events-barangay');
    const cityFilter = document.getElementById('all-events-city');
    const dateFromFilter = document.getElementById('all-events-date-from');
    const dateToFilter = document.getElementById('all-events-date-to');
    const resetBtn = document.getElementById('all-events-reset');

    // Initialize pagination
    initPagination();

    // Event listeners
    prevPageBtn.addEventListener('click', goToPreviousPage);
    nextPageBtn.addEventListener('click', goToNextPage);
    itemsPerPageSelect.addEventListener('change', changeItemsPerPage);
    searchInput.addEventListener('input', applyFilters);
    barangayFilter.addEventListener('change', applyFilters);
    cityFilter.addEventListener('change', applyFilters);
    dateFromFilter.addEventListener('change', applyFilters);
    dateToFilter.addEventListener('change', applyFilters);
    resetBtn.addEventListener('click', resetFilters);

    function initPagination() {
        loadEvents();
    }

    async function loadEvents() {
        try {
            // Show loading state
            eventsTableBody.innerHTML = '<div class="loading">Loading events...</div>';
            
            // Build query string with filters and pagination
            let queryString = `page=${currentPage}&per_page=${itemsPerPage}`;
            
            if (currentFilters.search) {
                queryString += `&search=${encodeURIComponent(currentFilters.search)}`;
            }
            if (currentFilters.barangay !== 'all') {
                queryString += `&barangay=${encodeURIComponent(currentFilters.barangay)}`;
            }
            if (currentFilters.city !== 'all') {
                queryString += `&city=${encodeURIComponent(currentFilters.city)}`;
            }
            if (currentFilters.dateFrom) {
                queryString += `&date_from=${currentFilters.dateFrom}`;
            }
            if (currentFilters.dateTo) {
                queryString += `&date_to=${currentFilters.dateTo}`;
            }
            
            // Add role-based restrictions if needed
            <?php if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official'): ?>
                queryString += `&barangay_restricted=1&barangay=<?= $_SESSION['barangay'] ?>&city=<?= $_SESSION['city_municipality'] ?>`;
            <?php endif; ?>
            
            const response = await fetch(`get_events_paginated.php?${queryString}`);
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            totalEvents = data.total;
            totalPages = Math.ceil(totalEvents / itemsPerPage);
            
            renderEvents(data.events);
            updatePaginationControls();
            
        } catch (error) {
            eventsTableBody.innerHTML = `
                <div class="error">
                    <p>Error loading events.</p>
                    <p>${error.message}</p>
                </div>
            `;
            console.error('Fetch error:', error);
        }
    }

    function renderEvents(events) {
        if (events.length === 0) {
            eventsTableBody.innerHTML = '<div class="no-events">No events found matching your criteria</div>';
            return;
        }
        
        let html = '';
        
        events.forEach(event => {
            const eventDate = new Date(event.start_date);
            const endDate = new Date(event.end_date);
            const postedDate = new Date(event.created_at);
            
            const formattedDate = postedDate.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
            
            let eventStatus = '';
            const now = new Date();
            
            if (event.is_approved === 'Pending') {
                eventStatus = 'Pending Approval';
            } else if (event.is_approved === 'Disapproved') {
                eventStatus = 'Disapproved';
            } else if (new Date(event.end_date) < now) {
                eventStatus = 'Completed';
            } else {
                eventStatus = 'Active';
            }
            
            // Format location
            let location = '';
            if (event.venue) {
                location = event.venue;
            }
            if (event.barangay) {
                location += location ? ', Brgy. ' + event.barangay : 'Brgy. ' + event.barangay;
            }
            if (event.city_municipality) {
                location += location ? ', ' + event.city_municipality : event.city_municipality;
            }
            
            html += `
                <div class="table-row">
                    <div class="table-cell event-title-cell">
                        <div class="event-thumbnail-small">
                            <img src="${event.thumbnail}" alt="Event thumbnail">
                        </div>
                        ${event.subject}
                    </div>
                    <div class="table-cell">${location}</div>
                    <div class="table-cell">${event.organization}</div>
                    <div class="table-cell">${event.event_type}</div>
                    <div class="table-cell status-cell ${eventStatus.toLowerCase().replace(' ', '-')}">
                        ${eventStatus}
                    </div>
                    <div class="table-cell">${formattedDate}</div>
                    <div class="table-cell action-cell">
                        <button type="button" class="view-btn-small" data-event-id="${event.event_id}">
                            <i class="fa fa-eye"></i> View
                        </button>
                    </div>
                </div>
            `;
        });
        
        eventsTableBody.innerHTML = html;
        
        // Reattach event listeners to view buttons
        document.querySelectorAll('.view-btn-small').forEach(btn => {
            btn.addEventListener('click', function() {
                const eventId = this.getAttribute('data-event-id');
                handleViewButtonClick(eventId);
            });
        });
    }

    function updatePaginationControls() {
        // Update page info
        pageInfoSpan.textContent = `Page ${currentPage} of ${totalPages}`;
        
        // Enable/disable previous button
        prevPageBtn.disabled = currentPage <= 1;
        
        // Enable/disable next button
        nextPageBtn.disabled = currentPage >= totalPages;
    }

    function goToPreviousPage() {
        if (currentPage > 1) {
            currentPage--;
            loadEvents();
        }
    }

    function goToNextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            loadEvents();
        }
    }

    function changeItemsPerPage() {
        itemsPerPage = parseInt(itemsPerPageSelect.value);
        currentPage = 1; // Reset to first page when changing items per page
        loadEvents();
    }

    function applyFilters() {
        currentFilters = {
            search: searchInput.value.toLowerCase(),
            barangay: barangayFilter.value,
            city: cityFilter.value,
            dateFrom: dateFromFilter.value,
            dateTo: dateToFilter.value
        };
        
        currentPage = 1; // Reset to first page when applying new filters
        loadEvents();
    }

    function resetFilters() {
        searchInput.value = '';
        barangayFilter.value = 'all';
        cityFilter.value = 'all';
        dateFromFilter.value = '';
        dateToFilter.value = '';
        
        applyFilters();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-events');
    const eventCheckboxes = document.querySelectorAll('.event-checkbox-input');
    const bulkApproveBtn = document.getElementById('bulk-approve-btn');
    const selectedEventsInput = document.getElementById('selected-events-input');
    const bulkApproveForm = document.getElementById('bulk-approve-form');

    // Select/Deselect all functionality
    selectAllCheckbox.addEventListener('change', function() {
        eventCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateBulkApproveButton();
    });

    // Individual checkbox change handler
    eventCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // If any checkbox is unchecked, uncheck the "Select All" checkbox
            if (!this.checked && selectAllCheckbox.checked) {
                selectAllCheckbox.checked = false;
            }
            updateBulkApproveButton();
        });
    });

    // Update bulk approve button state based on selections
    function updateBulkApproveButton() {
        const selectedCount = document.querySelectorAll('.event-checkbox-input:checked').length;
        bulkApproveBtn.disabled = selectedCount === 0;
    }

    // Handle form submission
    bulkApproveForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const selectedEvents = Array.from(document.querySelectorAll('.event-checkbox-input:checked'))
            .map(checkbox => checkbox.value)
            .join(',');
        
        selectedEventsInput.value = selectedEvents;
        
        // Show confirmation dialog
        if (confirm(`Are you sure you want to approve ${selectedEvents.split(',').length} selected event(s)?`)) {
            this.submit();
        }
    });
});
</script>
</body>
</html>