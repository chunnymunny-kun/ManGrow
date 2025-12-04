<?php
    session_start();
    include 'database.php';
    require_once 'getdropdown.php';
    checkExpiredReports($connection);

    function checkExpiredReports($connection) {
    
        try {
            // Update expired mangrove reports
            $query = "UPDATE mangrovereporttbl 
                    SET follow_up_status = 'expired'
                    WHERE follow_up_status = 'pending' 
                    AND rejection_timestamp IS NOT NULL
                    AND TIMESTAMPDIFF(HOUR, rejection_timestamp, NOW()) >= 48";
            $connection->query($query);
            $mangroveExpired = $connection->affected_rows;
            
            // Update expired illegal activity reports
            $query = "UPDATE illegalreportstbl 
                    SET follow_up_status = 'expired'
                    WHERE follow_up_status = 'pending' 
                    AND rejection_timestamp IS NOT NULL
                    AND TIMESTAMPDIFF(HOUR, rejection_timestamp, NOW()) >= 48";
            $connection->query($query);
            $illegalExpired = $connection->affected_rows;
            
            // Optional logging (remove in production if not needed)
            if ($mangroveExpired > 0 || $illegalExpired > 0) {
                error_log("Marked $mangroveExpired mangrove and $illegalExpired illegal reports as expired");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error checking expired reports: " . $e->getMessage());
            return false;
        }
    }

    // Function to format species display for admin page
    function formatSpeciesDisplay($speciesData) {
        if (empty($speciesData)) return 'Not specified';
        
        $speciesMap = [
            'Rhizophora Apiculata' => 'Bakawan Lalake',
            'Rhizophora Mucronata' => 'Bakawan Babae',
            'Avicennia Marina' => 'Bungalon',
            'Sonneratia Alba' => 'Palapat'
        ];
        
        // Check if it's a JSON string (new multiple species format)
        $decoded = json_decode($speciesData, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Convert scientific names to common names for display
            $displayNames = array_map(function($species) use ($speciesMap) {
                return isset($speciesMap[trim($species)]) ? $speciesMap[trim($species)] : trim($species);
            }, $decoded);
            
            return implode(', ', $displayNames);
        }
        
        // Check if it's a comma-separated string (multiple species stored as string)
        if (strpos($speciesData, ',') !== false) {
            $speciesArray = explode(',', $speciesData);
            $displayNames = array_map(function($species) use ($speciesMap) {
                $trimmed = trim($species);
                return isset($speciesMap[$trimmed]) ? $speciesMap[$trimmed] : $trimmed;
            }, $speciesArray);
            
            return implode(', ', $displayNames);
        }
        
        // Handle single species
        return isset($speciesMap[trim($speciesData)]) ? $speciesMap[trim($speciesData)] : trim($speciesData);
    }
    
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
        
        // Add this for city/municipality filtering
        $city_municipality = isset($_SESSION["city_municipality"]) ? $_SESSION["city_municipality"] : null;
        $isAdminOrRep = ($_SESSION["accessrole"] == 'Administrator' || $_SESSION["accessrole"] == 'Representative');
    } else {
        // No accessrole set at all - redirect to login
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Please login first'
        ];
        header("Location: index.php");
        exit();
    }

    $compilerRestrictions = [];
    if ($_SESSION["accessrole"] === 'Barangay Official') {
        $compilerRestrictions['restricted'] = true;
        $compilerRestrictions['city'] = $_SESSION["city_municipality"] ?? '';
        $compilerRestrictions['barangay'] = $_SESSION["barangay"] ?? '';
    } else {
        $compilerRestrictions['restricted'] = false;
        $compilerRestrictions['city'] = '';
        $compilerRestrictions['barangay'] = '';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Reports</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminreportpage.css">
    <link rel="stylesheet" href="rating_modal.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    
    <!-- Map libraries -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script type="text/javascript" src="adminusers.js" defer></script>
    <script type="text/javascript" src="app.js" defer></script>
    
    <style>
    /* Star Rating Styles */
    .star-rating {
        font-size: 2rem;
        cursor: pointer;
        user-select: none;
    }
    
    .star-rating .star {
        color: #e9ecef;
        transition: color 0.2s;
        margin: 0 2px;
    }
    
    .star-rating .star:hover {
        color: #ffc107;
    }
    
    .rating-description {
        min-height: 24px;
        font-size: 0.9rem;
        color: #6c757d;
    }
    
    .rating-success {
        font-weight: 600;
    }
    
    .rating-success small {
        display: block;
        margin-top: 5px;
        font-weight: normal;
    }
    
    .rating-success .fas {
        margin-right: 5px;
    }
    
    /* Points Indicator Styles */
    .points-indicator {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: 2px solid #28a745;
        border-radius: 12px;
        padding: 15px;
        margin: 15px 0;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
    }
    
    .points-display {
        font-size: 1.2rem;
        font-weight: 600;
        color: #28a745;
        margin-bottom: 5px;
    }
    
    .points-calculation {
        font-size: 0.9rem;
        font-weight: normal;
        color: #6c757d;
        margin-left: 8px;
    }
    
    .points-explanation {
        font-size: 0.85rem;
        color: #6c757d;
    }
    </style>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class="navbar">
            <ul class="nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
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
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <p><?= isset($_SESSION["organization"]) ? $_SESSION["organization"] : "" ?></p>
                <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"):
                    if(isset($_SESSION["barangay"])){
                        $accessrole = $_SESSION["barangay"];
                    }
                    if(isset($_SESSION['city_municipality'])){
                        $city_municipality = $_SESSION['city_municipality'];
                    }
                    ?><p>Barangay <?php echo $accessrole ?>, <?php echo $city_municipality ?></p>
                <?php endif;
                ?>
                <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
                <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"): ?>
                    <button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button>
                <?php endif; ?>
            </div>
        </div>  
<div class="reporting-container">
        <!-- Recent Reports Notification -->
        <div class="recent-reports-notification">
            <div class="notification-icon">
                <i class="fa fa-bell"></i>
            </div>
            <div class="notification-content">
                <strong>Recent Reports:</strong>
                <?php
                // Query for recent mangrove reports
                $mangroveSql = "SELECT report_id, reporter_id, report_date, species, area_no, city_municipality, priority 
                FROM mangrovereporttbl ";
                
                // Add condition for Barangay Officials
                if (!$isAdminOrRep && isset($city_municipality)) {
                    $mangroveSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                }

                $mangroveSql .= "ORDER BY report_date DESC LIMIT 10";
                $mangroveResult = $connection->query($mangroveSql);
                
                // Query for recent illegal reports
                $illegalSql = "SELECT report_id, reporter_id, report_date, incident_type, area_no, city_municipality, priority 
                FROM illegalreportstbl ";
                
                // Add condition for Barangay Officials
                if (!$isAdminOrRep && isset($city_municipality)) {
                    $illegalSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                }

                $illegalSql .= "ORDER BY report_date DESC LIMIT 10";
                $illegalResult = $connection->query($illegalSql);

                
                if ($mangroveResult && $mangroveResult->num_rows > 0) {
                    $row = $mangroveResult->fetch_assoc();
                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : 'normal';
                    echo '<span class="report-badge '.$priorityClass.'">Mangrove: '.date('Y-m-d', strtotime($row['report_date'])).'</span>';
                }
                
                if ($illegalResult && $illegalResult->num_rows > 0) {
                    $row = $illegalResult->fetch_assoc();
                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : 'normal';
                    echo '<span class="report-badge '.$priorityClass.'">Illegal: '.date('Y-m-d', strtotime($row['report_date'])).'</span>';
                }
                ?>
            </div>
        </div>

        <!-- Dual Maps Section -->
        <div class="maps-container">
            <div class="map-wrapper">
                <div class="map-header mangrove-header">
                    <i class="fas fa-tree"></i> Mangrove Data Reports Map
                </div>
                <div id="mangroveMap"></div>
            </div>
            
            <div class="map-wrapper">
                <div class="map-header illegal-header">
                    <i class="fas fa-exclamation-triangle"></i> Illegal Activity Reports Map
                </div>
                <div id="illegalMap"></div>
            </div>
        </div>

        <div class="status-notifications-container mt-4">
            <div class="row g-3">
                <!-- Status Tracking Section (2/3 width) -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Reports Status Tracking</h5>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm w-auto" id="statusFilter">
                                        <option value="all">All Statuses</option>
                                        <option value="Received">Received</option>
                                        <option value="Investigating">Investigating</option>
                                        <option value="Action Taken">Action Taken</option>
                                        <option value="Resolved">Resolved</option>
                                        <option value="Rejected">Rejected</option>
                                    </select>
                                    <select class="form-select form-select-sm w-auto" id="reportTypeFilter">
                                        <option value="all">All Types</option>
                                        <option value="mangrove">Mangrove Data</option>
                                        <option value="illegal">Illegal Activity</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php
                                // Query to get status updates from report_notifstbl
                                $statusPage = isset($_GET['status_page']) ? max(1, intval($_GET['status_page'])) : 1;
                                $statusLimit = 6;
                                $statusOffset = ($statusPage - 1) * $statusLimit;

                                // Query to get status updates from report_notifstbl with pagination
                                $statusSql = "SELECT n.report_id, n.action_type, n.notif_date, n.account_id, n.report_type
                                    FROM report_notifstbl n
                                    INNER JOIN (
                                        SELECT report_id, report_type, MAX(notif_date) AS latest_notif_date
                                        FROM report_notifstbl
                                        GROUP BY report_id, report_type
                                    ) latest
                                    ON n.report_id = latest.report_id 
                                    AND n.report_type = latest.report_type 
                                    AND n.notif_date = latest.latest_notif_date
                                    ORDER BY n.notif_date DESC 
                                    LIMIT $statusLimit OFFSET $statusOffset";

                                $statusResult = $connection->query($statusSql);

                                // Get total count for pagination
                                $totalStatusSql = "SELECT COUNT(*) as total FROM (
                                    SELECT DISTINCT report_id, report_type 
                                    FROM report_notifstbl
                                ) as unique_reports";
                                $totalStatusResult = $connection->query($totalStatusSql);
                                $totalStatusCount = $totalStatusResult->fetch_assoc()['total'];
                                $totalStatusPages = ceil($totalStatusCount / $statusLimit);

                                if ($statusResult && $statusResult->num_rows > 0) {
                                    while ($row = $statusResult->fetch_assoc()) {
                                        $reportType = $row['report_type']; // Use the report_type directly from report_notifstbl
                                        
                                        // Fetch details based on report type
                                        $reportDetails = [];
                                        
                                        if ($reportType === 'Mangrove Data Report') {
                                            $detailsSql = "SELECT species, city_municipality, created_at 
                                                        FROM mangrovereporttbl 
                                                        WHERE report_id = '" . $connection->real_escape_string($row['report_id']) . "'";
                                            $detailsResult = $connection->query($detailsSql);
                                            
                                            if ($detailsResult && $detailsResult->num_rows > 0) {
                                                $detailsRow = $detailsResult->fetch_assoc();
                                                $reportDetails = [
                                                    'type' => 'Mangrove: ' . htmlspecialchars(formatSpeciesDisplay($detailsRow['species'])),
                                                    'location' => htmlspecialchars($detailsRow['city_municipality']),
                                                    'details' => htmlspecialchars($detailsRow['created_at'])
                                                ];
                                            }
                                        } elseif ($reportType === 'Illegal Activity Report') {
                                            $detailsSql = "SELECT incident_type, city_municipality, created_at
                                                        FROM illegalreportstbl 
                                                        WHERE report_id = '" . $connection->real_escape_string($row['report_id']) . "'";
                                            $detailsResult = $connection->query($detailsSql);
                                            
                                            if ($detailsResult && $detailsResult->num_rows > 0) {
                                                $detailsRow = $detailsResult->fetch_assoc();
                                                $reportDetails = [
                                                    'type' => 'Illegal: ' . htmlspecialchars($detailsRow['incident_type']),
                                                    'location' => htmlspecialchars($detailsRow['city_municipality']),
                                                    'details' => htmlspecialchars($detailsRow['created_at'])
                                                ];
                                            }
                                        }
                                        
                                        // If we couldn't fetch details, use defaults
                                        if (empty($reportDetails)) {
                                            $reportDetails = [
                                                'type' => $reportType,
                                                'location' => 'Location not specified',
                                                'details' => 'No details available'
                                            ];
                                        }
                                        
                                        // Get admin name who made the update
                                        $adminName = 'Anonymous';
                                        if (!empty($row['account_id'])) {
                                            $adminId = $connection->real_escape_string($row['account_id']);
                                            $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$adminId' LIMIT 1");
                                            if ($accResult && $accResult->num_rows > 0) {
                                                $accRow = $accResult->fetch_assoc();
                                                $adminName = htmlspecialchars($accRow['fullname']);
                                            }
                                        }
                                        
                                        // Determine badge color based on status
                                        $badgeClass = 'bg-secondary';
                                        switch($row['action_type']) {
                                            case 'Received': $badgeClass = 'bg-info text-dark'; break;
                                            case 'Investigating': $badgeClass = 'bg-warning text-dark'; break;
                                            case 'Action Taken': $badgeClass = 'bg-success'; break;
                                            case 'Resolved': $badgeClass = 'bg-primary'; break;
                                            case 'Rejected': $badgeClass = 'bg-danger'; break;
                                        }
                                        
                                        // Determine report type badge color and text
                                        $reportTypeBadgeClass = ($reportType === 'Mangrove Data Report') ? 
                                            'bg-success text-white' : 'bg-danger text-white';
                                        $reportTypeShort = ($reportType === 'Mangrove Data Report') ? 
                                            'Mangrove' : 'Illegal';
                                        
                                        $reportBadgeClass = ($reportType === 'Mangrove Data Report') ? 'bg-primary' : 'bg-danger';
                                        $dataReportType = ($reportType === 'Mangrove Data Report') ? 'mangrove' : 'illegal';
                                        ?>
                                        <div class="list-group-item p-3" data-status="<?= htmlspecialchars($row['action_type']) ?>" 
                                            data-type="<?= $dataReportType ?>" 
                                            data-report-type="<?= htmlspecialchars($reportType) ?>"> 
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span class="badge <?= $reportBadgeClass ?> rounded-pill me-2"><?= htmlspecialchars($row['report_id']) ?></span>
                                                    <span class="badge <?= $badgeClass ?> rounded-pill me-2 status-badge"><?= htmlspecialchars($row['action_type']) ?></span>
                                                    <span class="badge <?= $reportTypeBadgeClass ?> rounded-pill"><?= $reportTypeShort ?></span>
                                                </div>
                                                <p><?= $reportDetails['details'] ?></p>
                                            </div>
                                            <div class="mb-2">
                                                <strong><?= $reportDetails['type'] ?></strong> - <?= $reportDetails['location'] ?>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6>Updated by: <?= $adminName ?></h6>
                                                <button class="btn btn-sm btn-outline-primary update-status-btn" 
                                                        data-report-id="<?= htmlspecialchars($row['report_id']) ?>"
                                                        data-report-type="<?= htmlspecialchars($reportType) ?>">
                                                    <i class="fas fa-edit me-1"></i>Update
                                                </button>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<div class="list-group-item p-3">No status updates found</div>';
                                }
                                ?>
                            </div>
                            <div class="d-flex justify-content-center mt-3">
                                            <nav aria-label="Status tracking pagination">
                                                <ul class="pagination">
                                                    <?php if ($statusPage > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?status_page=<?= $statusPage - 1 ?>#status-tracking" aria-label="Previous">
                                                                <span aria-hidden="true">&laquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">&laquo;</span>
                                                        </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php
                                                    // Show page numbers
                                                    $startPage = max(1, $statusPage - 2);
                                                    $endPage = min($totalStatusPages, $statusPage + 2);
                                                    
                                                    for ($i = $startPage; $i <= $endPage; $i++):
                                                    ?>
                                                        <li class="page-item <?= $i == $statusPage ? 'active' : '' ?>">
                                                            <a class="page-link" href="?status_page=<?= $i ?>#status-tracking"><?= $i ?></a>
                                                        </li>
                                                    <?php endfor; ?>
                                                    
                                                    <?php if ($statusPage < $totalStatusPages): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="?status_page=<?= $statusPage + 1 ?>#status-tracking" aria-label="Next">
                                                                <span aria-hidden="true">&raquo;</span>
                                                            </a>
                                                        </li>
                                                    <?php else: ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link">&raquo;</span>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </nav>
                                        </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notifications Section (1/3 width) -->
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h5>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php
                                // Query to get recent notifications
                                $notifSql = "SELECT n.report_id, n.report_type, n.action_type, n.notif_date, n.account_id, n.admin_notif_description
                                            FROM report_notifstbl n
                                          ORDER BY n.notif_date DESC LIMIT 5";
                                
                                $notifResult = $connection->query($notifSql);

                                if ($notifResult && $notifResult->num_rows > 0) {
                                    while ($row = $notifResult->fetch_assoc()) {
                                        // Get reporter name
                                        $adminName = 'Anonymous';
                                        if (!empty($row['account_id'])) {
                                            $adminId = $connection->real_escape_string($row['account_id']);
                                            $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$adminId' LIMIT 1");
                                            if ($accResult && $accResult->num_rows > 0) {
                                                $accRow = $accResult->fetch_assoc();
                                                $adminName = htmlspecialchars($accRow['fullname']);
                                            }
                                        }
                                        
                                        // Determine badge type based on action
                                        $badgeType = 'bg-secondary';
                                        $badgeText = 'Update';
                                        if ($row['action_type'] === 'Received') {
                                            $badgeType = 'bg-info';
                                            $badgeText = 'New Report';
                                        } elseif ($row['action_type'] === 'Rejected') {
                                            $badgeType = 'bg-danger';
                                            $badgeText = 'Rejection';
                                        }
                                        
                                        // Add report type badge
                                        $reportTypeBadge = $row['report_type'] === 'Mangrove Data Report' ? 
                                            '<span class="badge bg-success rounded-pill ms-2">Mangrove</span>' : 
                                            '<span class="badge bg-danger rounded-pill ms-2">Illegal</span>';
                                        ?>
                                        <a href="#" class="list-group-item list-group-item-action p-3 border-0">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <div>
                                                    <span class="badge <?= $badgeType ?> rounded-pill"><?= $badgeText ?></span>
                                                    <?= $reportTypeBadge ?>
                                                </div>
                                                <p><?= date('Y-m-d H:i', strtotime($row['notif_date'])) ?></p>
                                            </div>
                                            <div class="mb-1">Report no. <strong><?= htmlspecialchars($row['report_id']) ?></strong> status changed to <?= htmlspecialchars($row['action_type']) ?></div>
                                            <h6>By: <?= $adminName ?></h6>
                                            <div class="notification-preview mt-1"><?= htmlspecialchars(substr($row['admin_notif_description'], 0, 100)) ?>...</div>
                                        </a>
                                        <?php
                                    }
                                } else {
                                    echo '<a href="#" class="list-group-item list-group-item-action p-3 border-0">No notifications found</a>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Reports Dashboard -->
        <div class="reports-dashboard">
            <div class="dashboard-header">
                <h2><i class="fas fa-file-alt"></i> Reports Dashboard</h2>
                <p>View and manage all mangrove data and illegal activity reports</p>
            </div>
            
            <!-- Filtering System -->
            <div class="filter-system">
                <div class="filter-group">
                    <input type="text" id="filter-keyword" placeholder="Search all reports...">
                </div>
                <div class="filter-group date" style="display:none;">
                    <input type="date" id="filter-date" placeholder="Filter by date...">
                </div>
                <div class="filter-group date date-range">
                    <input type="date" id="filter-start-date" placeholder="Start date">
                    <span>to</span>
                    <input type="date" id="filter-end-date" placeholder="End date">
                    <button class="clear-date-btn" title="Clear date range">&times;</button>
                </div>
            </div>
            <div class="filter-system">
                <div class="filter-group">
                    <select id="filter-city">
                        <option value="">All Cities/Municipalities</option>
                        <?php
                        $cities = getcitymunicipality();
                        foreach ($cities as $city) {
                            echo '<option value="'.$city['city'].'">'.$city['city'].'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="filter-barangay" disabled>
                        <option value="">All Barangays</option>
                    </select>
                </div>
            </div>
            <!-- Modify the reports-grid section to include separate filters for each report type -->
            <div class="reports-grid">
                <!-- Mangrove Data Reports Section -->
                <div class="report-section">
                    <div class="section-header mangrove-header">
                        <i class="fas fa-tree"></i> Mangrove Data Reports
                    </div>
                    <div class="section-content">
                        <!-- Mangrove-specific filters -->
                        <div class="filter-system">
                            <div class="filter-group">
                                <select id="filter-plant-type">
                                    <option value="">All Plant Types</option>
                                    <option value="Rhizophora Apiculata">Bakawan lalake</option>
                                    <option value="Rhizophora Mucronata">Bakawan babae</option>
                                    <option value="Avicennia Marina">Bungalon</option>
                                    <option value="Sonneratia Alba">Palapat</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <select id="filter-mangrove-status">
                                    <option value="">All Statuses</option>
                                    <option value="Healthy">Healthy</option>
                                    <option value="Growing">Growing</option>
                                    <option value="Needs Attention">Needs Attention</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Dead">Dead</option>
                                </select>
                            </div>
                        </div>

                        <h6><i class="fas fa-list"></i> All Reports</h6>
                        <ul class="report-list" id="mangrove-reports-list">
                            <?php
                            $mangroveSql = "SELECT * 
                            FROM mangrovereporttbl ";

                            // Add condition for Barangay Officials
                            if (!$isAdminOrRep && isset($city_municipality)) {
                                $mangroveSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                            }

                            $mangroveSql .= "ORDER BY report_date DESC LIMIT 10";
                            $mangroveResult = $connection->query($mangroveSql);

                            if ($mangroveResult && $mangroveResult->num_rows > 0) {
                                while ($row = $mangroveResult->fetch_assoc()) {
                                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
                                    // Get reporter name
                                    $reporterName = 'Anonymous';
                                    if (!empty($row['reporter_id'])) {
                                        $reporterId = $connection->real_escape_string($row['reporter_id']);
                                        $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$reporterId' LIMIT 1");
                                        if ($accResult && $accResult->num_rows > 0) {
                                            $accRow = $accResult->fetch_assoc();
                                            $reporterName = htmlspecialchars($accRow['fullname']);
                                        }
                                    }
                                    echo '
                                    <li class="report-item '.$priorityClass.'" 
                                        data-priority="'.htmlspecialchars($row['priority']).'" 
                                        data-plant-type="'.htmlspecialchars($row['species']).'" 
                                        data-mangrove-status="'.htmlspecialchars($row['mangrove_status']).'"
                                        data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" 
                                        data-barangay="'.htmlspecialchars($row['barangays']).'"
                                        data-address="'.htmlspecialchars($row['address'] ?? '').'">
                                        <div class="report-content">
                                            <strong>'.htmlspecialchars(formatSpeciesDisplay($row['species'])).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                                            <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                                            <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                                        </div>
                                        <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="mangrove">View</button>
                                    </li>';
                                }
                            } else {
                                echo '<li class="report-item">No reports found</li>';
                            }
                            ?>
                        </ul>
                        <?php
                        $mangroveCountSql = "SELECT COUNT(*) as total FROM mangrovereporttbl";
                        // Add condition for Barangay Officials
                        if (!$isAdminOrRep && isset($city_municipality)) {
                            $mangroveCountSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                        }
                        $mangroveCountResult = $connection->query($mangroveCountSql);
                        $mangroveTotal = $mangroveCountResult ? $mangroveCountResult->fetch_assoc()['total'] : 0;

                        if ($mangroveTotal > 10) {
                            echo '<div class="page-ination" data-report-type="mangrove" data-current-page="1" data-total-pages="'.ceil($mangroveTotal/10).'">';
                            echo '<button class="page-btn prev-btn" disabled><i class="fas fa-chevron-left"></i></button>';
                            echo '<span class="page-info">Page 1 of '.ceil($mangroveTotal/10).'</span>';
                            echo '<button class="page-btn next-btn"><i class="fas fa-chevron-right"></i></button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Illegal Activity Reports Section -->
                <div class="report-section">
                    <div class="section-header illegal-header">
                        <i class="fas fa-exclamation-triangle"></i> Illegal Activity Reports
                    </div>
                    <div class="section-content">
                        <!-- Illegal-specific filters -->
                        <div class="filter-system">
                            <div class="filter-group">
                                <select id="filter-priority">
                                    <option value="">All Priorities</option>
                                    <option value="Emergency">Emergency Reports</option>
                                    <option value="Normal">Normal Reports</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <select id="filter-incident-category">
                                    <option value="">All Categories</option>
                                    <option value="Illegal Activities">Illegal Activities</option>
                                    <option value="Mangrove-related Incidents">Mangrove-related Incidents</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <select id="filter-incident-type">
                                    <option value="">All Incident Types</option>
                                    <option value="Illegal Cutting">Illegal Cutting</option>
                                    <option value="Waste Dumping">Waste Dumping</option>
                                    <option value="Construction">Unauthorized Construction</option>
                                    <option value="Harmful Fishing">Fishing with Harmful Methods</option>
                                    <option value="Water Pollution">Water Pollution</option>
                                    <option value="Fire">Fire in Mangrove Area</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="priority-section">
                            <h6 class="emergency"><i class="fas fa-exclamation-circle"></i> Emergency Reports</h6>
                            <ul class="report-list" id="illegal-emergency-list">
                                <?php
                                $emergencySql = "SELECT * 
                                                FROM illegalreportstbl 
                                                WHERE priority = 'Emergency' ";
                                
                                if (!$isAdminOrRep && isset($city_municipality)) {
                                    $emergencySql .= " AND city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                                }
                                
                                $emergencySql .= "ORDER BY report_date DESC LIMIT 3";
                                $emergencyResult = $connection->query($emergencySql);
                                
                                if ($emergencyResult && $emergencyResult->num_rows > 0) {
                                    while ($row = $emergencyResult->fetch_assoc()) {
                                        // Categorize incident types
                                        $incidentCategory = in_array($row['incident_type'], ['Illegal Cutting', 'Construction', 'Harmful Fishing']) 
                                            ? 'Illegal Activities' 
                                            : 'Mangrove-related Incidents';
                                        
                                        // Get reporter name
                                        $reporterName = 'Anonymous';
                                        if (!empty($row['reporter_id'])) {
                                            $reporterId = $connection->real_escape_string($row['reporter_id']);
                                            $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$reporterId' LIMIT 1");
                                            if ($accResult && $accResult->num_rows > 0) {
                                                $accRow = $accResult->fetch_assoc();
                                                $reporterName = htmlspecialchars($accRow['fullname']);
                                            }
                                        }
                                        echo '
                                        <li class="report-item emergency" data-incident-type="'.htmlspecialchars($row['incident_type']).'" data-incident-category="'.$incidentCategory.'" data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" data-barangay="'.htmlspecialchars($row['barangays']).'" data-address="'.htmlspecialchars($row['address'] ?? '').'">
                                            <div class="report-content">
                                                <strong>'.htmlspecialchars($row['incident_type']).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                                                <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                                                <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                                            </div>
                                            <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="illegal">View</button>
                                        </li>';
                                    }
                                } else {
                                    echo '<li class="report-item">No emergency reports</li>';
                                }
                                ?>
                            </ul>
                        </div>
                        
                        <h6><i class="fas fa-list"></i> All Reports</h6>
                        <ul class="report-list" id="illegal-reports-list">
                            <?php
                            $illegalSql = "SELECT ir.*, 
                                          COALESCE(a.fullname, 'Anonymous') as reporter_name
                                        FROM illegalreportstbl ir
                                        LEFT JOIN accountstbl a ON ir.reporter_id = a.account_id ";
                                        
                            // Add condition for Barangay Officials
                            if (!$isAdminOrRep && isset($city_municipality)) {
                                $illegalSql .= " WHERE ir.city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                            }

                            $illegalSql .= "ORDER BY report_date DESC LIMIT 10";
                            $illegalResult = $connection->query($illegalSql);
                            
                            
                            if ($illegalResult && $illegalResult->num_rows > 0) {
                                while ($row = $illegalResult->fetch_assoc()) {
                                    // Categorize incident types
                                    $incidentCategory = in_array($row['incident_type'], ['Illegal Cutting', 'Construction', 'Harmful Fishing']) 
                                        ? 'Illegal Activities' 
                                        : 'Mangrove-related Incidents';
                                    
                                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
                                    // Get reporter name from joined data
                                    $reporterName = $row['reporter_name'] ?? 'Anonymous';
                                    
                                    // Build rating display if resolved
                                    $ratingDisplay = '';
                                    if ($row['action_type'] === 'Resolved' && !empty($row['rating'])) {
                                        $ratingDisplay = '<div class="report-rating">';
                                        for ($i = 1; $i <= 5; $i++) {
                                            $starClass = $i <= $row['rating'] ? 'filled' : 'empty';
                                            $starIcon = $i <= $row['rating'] ? '★' : '☆';
                                            $ratingDisplay .= '<span class="star ' . $starClass . '">' . $starIcon . '</span>';
                                        }
                                        $ratingDisplay .= '<span class="rating-points">+' . ($row['points_awarded'] ?? 0) . ' pts</span>';
                                        $ratingDisplay .= '</div>';
                                    }
                                    
                                    echo '
                                    <li class="report-item '.$priorityClass.'" data-priority="'.htmlspecialchars($row['priority']).'" data-incident-type="'.htmlspecialchars($row['incident_type']).'" data-incident-category="'.$incidentCategory.'"  data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" data-barangay="'.htmlspecialchars($row['barangays']).'" data-address="'.htmlspecialchars($row['address'] ?? '').'">
                                        <div class="report-content">
                                            <strong>'.htmlspecialchars($row['incident_type']).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                                            <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                                            <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                                            '.$ratingDisplay.'
                                        </div>
                                        <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="illegal">View</button>
                                    </li>';
                                }
                            } else {
                                echo '<li class="report-item">No reports found</li>';
                            }
                            ?>
                        </ul>
                        <?php
                        $illegalCountSql = "SELECT COUNT(*) as total FROM illegalreportstbl";
                        // Add condition for Barangay Officials
                        if (!$isAdminOrRep && isset($city_municipality)) {
                            $illegalCountSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
                        }
                        $illegalCountResult = $connection->query($illegalCountSql);
                        $illegalTotal = $illegalCountResult ? $illegalCountResult->fetch_assoc()['total'] : 0;

                        if ($illegalTotal > 10) {
                            echo '<div class="page-ination" data-report-type="illegal" data-current-page="1" data-total-pages="'.ceil($illegalTotal/10).'">';
                            echo '<button class="page-btn prev-btn" disabled><i class="fas fa-chevron-left"></i></button>';
                            echo '<span class="page-info">Page 1 of '.ceil($illegalTotal/10).'</span>';
                            echo '<button class="page-btn next-btn"><i class="fas fa-chevron-right"></i></button>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <div class="reports-compiler-container">
            <div class="compiler-header">
                <h2><i class="fas fa-file-pdf"></i> Reports Compiler</h2>
                <p>Filter, preview, and compile reports into PDF documents</p>
            </div>
            
            <!-- Compiler Controls -->
            <div class="compiler-controls">
                <div class="filter-group">
                    <label for="compiler-report-type">Report Type:</label>
                    <select id="compiler-report-type">
                        <option value="mangrove">Mangrove Data Reports</option>
                        <option value="illegal">Illegal Activity Reports</option>
                    </select>
                </div>
                
                <!-- City/Municipality filter - disabled for Barangay Officials -->
                <div class="filter-group">
                    <label for="compiler-city">City/Municipality:</label>
                    <select id="compiler-city" <?= $compilerRestrictions['restricted'] ? 'disabled' : '' ?>>
                        <option value="">All Cities/Municipalities</option>
                        <?php
                        $cities = getcitymunicipality();
                        foreach ($cities as $city) {
                            $selected = ($compilerRestrictions['restricted'] && $compilerRestrictions['city'] === $city['city']) ? 'selected' : '';
                            echo '<option value="'.$city['city'].'" '.$selected.'>'.$city['city'].'</option>';
                        }
                        ?>
                    </select>
                    <?php if ($compilerRestrictions['restricted']): ?>
                        <input type="hidden" id="compiler-city-hidden" value="<?= htmlspecialchars($compilerRestrictions['city']) ?>">
                    <?php endif; ?>
                </div>
                
                <!-- Barangay filter - disabled for Barangay Officials -->
                <div class="filter-group">
                    <label for="compiler-barangay">Barangay:</label>
                    <select id="compiler-barangay" <?= $compilerRestrictions['restricted'] ? 'disabled' : '' ?>>
                        <option value="">All Barangays</option>
                        <?php if ($compilerRestrictions['restricted'] && !empty($compilerRestrictions['barangay'])): ?>
                            <option value="<?= htmlspecialchars($compilerRestrictions['barangay']) ?>" selected>
                                <?= htmlspecialchars($compilerRestrictions['barangay']) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <?php if ($compilerRestrictions['restricted']): ?>
                        <input type="hidden" id="compiler-barangay-hidden" value="<?= htmlspecialchars($compilerRestrictions['barangay']) ?>">
                    <?php endif; ?>
                </div>
                
                <div class="filter-group">
                    <label for="compiler-date-range">Date Range:</label>
                    <select id="compiler-date-range">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>
                
                <!-- Date selectors remain the same -->
                <div class="filter-group date-selector" id="daily-selector">
                    <label for="compiler-date">Select Date:</label>
                    <input type="date" id="compiler-date">
                </div>
                
                <div class="filter-group date-selector" id="weekly-selector" style="display:none;">
                    <label for="compiler-week">Select Week:</label>
                    <input type="week" id="compiler-week">
                </div>
                
                <div class="filter-group date-selector" id="monthly-selector" style="display:none;">
                    <label for="compiler-month">Select Month:</label>
                    <input type="month" id="compiler-month">
                </div>
                
                <div class="filter-group date-selector" id="custom-range-selector" style="display:none;">
                    <label>Custom Range:</label>
                    <div class="date-range-inputs">
                        <input type="date" id="compiler-start-date" placeholder="Start Date">
                        <span>to</span>
                        <input type="date" id="compiler-end-date" placeholder="End Date">
                    </div>
                </div>
                
                <button id="preview-compilation" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Preview Compilation
                </button>
            </div>
            
            <!-- Preview Section -->
            <div class="compiler-preview-section" style="display:none;">
                <div class="preview-header">
                    <h3>Compilation Preview</h3>
                    <div class="preview-controls">
                        <button id="generate-pdf" class="btn btn-success">
                            <i class="fas fa-file-pdf"></i> Generate PDF
                        </button>
                        <button id="cancel-compilation" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
                
                <div class="preview-meta">
                    <div class="meta-item">
                        <strong>Report Type:</strong> <span id="preview-report-type">Mangrove Data Reports</span>
                    </div>
                    <div class="meta-item">
                        <strong>Date Range:</strong> <span id="preview-date-range">August 1, 2023 - August 31, 2023</span>
                    </div>
                    <div class="meta-item">
                        <strong>Total Reports:</strong> <span id="preview-total-reports">0</span>
                    </div>
                </div>
                
                <div class="preview-actions">
                    <button id="select-all-reports" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button id="deselect-all-reports" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-square"></i> Deselect All
                    </button>
                    <button id="remove-selected" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> Remove Selected
                    </button>
                </div>
                
                <div class="preview-reports-list" id="preview-reports-list">
                    <!-- Reports will be loaded here -->
                </div>
            </div>
        </div>

        <!-- PDF Generation Modal -->
        <div class="modal fade" id="pdfGenerationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Generating PDF</h5>
                    </div>
                    <div class="modal-body text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Compiling reports and generating PDF document...</p>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="statusUpdateModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0">
                        <h5 class="modal-title">Update Report Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">X</button>
                    </div>
                    <div class="modal-body">
                        <form id="statusUpdateForm">
                            <input type="hidden" id="currentReportId">
                            <input type="hidden" id="currentReportType">
                            <input type="hidden" id="currentUserId" value="<?= $_SESSION['user_id'] ?? '' ?>">
                            <input type="hidden" id="currentUserRole" value="<?= $_SESSION['accessrole'] ?? '' ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Report ID</label>
                                <input type="text" class="form-control" id="displayReportId" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Status</label>
                                <input type="text" class="form-control" id="currentStatus" readonly>
                            </div>
                            
                            <!-- Add previous notification preview -->
                            <div class="mb-3 previous-notification">
                                <label class="form-label">Previous Notification</label>
                                <div class="notification-preview" id="previousNotificationPreview">
                                    <em>No previous notification found</em>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="statusSelect" class="form-label">New Status</label>
                                <select class="form-select" id="statusSelect" required>
                                    <option value="">Select status...</option>
                                    <option value="Investigating">Investigating</option>
                                    <option value="Action Taken">Action Taken</option>
                                    <option value="Resolved">Resolved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="actionDescription" class="form-label">Notification Message for User</label>
                                <textarea class="form-control" id="actionDescription" rows="3" 
                                    placeholder="Detailed message for the reporter..." required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancelStatusUpdate">Cancel</button>
                        <button type="button" class="btn btn-primary" id="submitStatusUpdate">
                            <span id="submitStatusText">Update Status</span>
                            <span id="submitStatusSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rating Modal for Resolved Reports -->
        <div class="modal fade" id="ratingModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-0" style="background: linear-gradient(135deg, #28a745, #20c997); color: white;">
                        <h5 class="modal-title"><i class="fas fa-star me-2"></i>Rate Report Resolution</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="report-info mb-4">
                            <h6>Report Details</h6>
                            <div class="row g-2 small">
                                <div class="col-6"><strong>Report ID:</strong> <span id="modal-report-id">-</span></div>
                                <div class="col-6"><strong>Type:</strong> <span id="modal-incident-type">-</span></div>
                                <div class="col-6"><strong>Priority:</strong> <span id="modal-priority">-</span></div>
                                <div class="col-6"><strong>Reporter:</strong> <span id="modal-reporter">-</span></div>
                                <div class="col-12"><strong>Location:</strong> <span id="modal-location">-</span></div>
                            </div>
                        </div>
                        
                        <p class="text-center mb-3">Please rate the accuracy and quality of this report:</p>
                        
                        <div class="text-center mb-3">
                            <div class="star-rating" id="starRating">
                                <span class="star" data-rating="1">★</span>
                                <span class="star" data-rating="2">★</span>
                                <span class="star" data-rating="3">★</span>
                                <span class="star" data-rating="4">★</span>
                                <span class="star" data-rating="5">★</span>
                            </div>
                        </div>
                        
                        <div class="rating-description text-center mb-3" id="ratingDescription">
                            Please select a rating
                        </div>
                        
                        <!-- Points Indicator -->
                        <div class="points-indicator text-center mb-3" id="pointsIndicator" style="display: none;">
                            <div class="points-display">
                                <i class="fas fa-coins text-warning me-2"></i>
                                <strong id="pointsAmount">0</strong> eco points
                                <span class="points-calculation" id="pointsCalculation"></span>
                            </div>
                            <small class="text-muted" id="pointsExplanation">Points awarded based on rating quality</small>
                        </div>
                        
                        <div class="rating-success text-center text-success" id="ratingSuccess" style="display: none;">
                            <i class="fas fa-check-circle me-2"></i>Rating submitted successfully!
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="submitRating" disabled>Submit Rating</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="reportModal" class="report-modal">
            <div class="report-modal-content">
                <span class="report-modal-close">&times;</span>
                <div id="reportModalContent"></div>
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
            <p class="credits">
                Data Sources: © Global Mangrove Watch, © OpenStreetMap contributors, © MapTiler
            </p>
        </div>
    </footer>

<script>
// Global helper function to format species display
function formatSpeciesDisplay(speciesData) {
    if (!speciesData) return 'Not specified';
    
    const speciesMap = {
        'Rhizophora Apiculata': 'Bakawan Lalake',
        'Rhizophora Mucronata': 'Bakawan Babae',
        'Avicennia Marina': 'Bungalon',
        'Sonneratia Alba': 'Palapat'
    };
    
    // Check if it's a JSON string (new multiple species format)
    try {
        const parsed = JSON.parse(speciesData);
        if (Array.isArray(parsed)) {
            const displayNames = parsed.map(species => speciesMap[species.trim()] || species.trim());
            return displayNames.join(', ');
        }
    } catch (e) {
        // Not JSON, continue to other checks
    }
    
    // Check if it's a comma-separated string (multiple species stored as string)
    if (speciesData.includes(',')) {
        const speciesArray = speciesData.split(',');
        const displayNames = speciesArray.map(species => {
            const trimmed = species.trim();
            return speciesMap[trimmed] || trimmed;
        });
        return displayNames.join(', ');
    }
    
    // Handle single species
    const trimmed = speciesData.trim();
    return speciesMap[trimmed] || trimmed;
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        // Default coordinates (Manila area)
        const defaultCoords = [14.64852, 120.47318];
        const defaultZoom = 11.2;
        // Initialize Mangrove Map
        const mangroveMap = L.map('mangroveMap', {
            center: defaultCoords,
            zoom: defaultZoom,
            zoomControl: true,
            dragging: true,
            scrollWheelZoom: true,
            tap: true,
            touchZoom: true,
            doubleClickZoom: true
        }).setView(defaultCoords, defaultZoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(mangroveMap);
        
        // Initialize Illegal Activity Map
        const illegalMap = L.map('illegalMap', {
            center: defaultCoords,
            zoom: defaultZoom,
            zoomControl: true,
            dragging: true,
            scrollWheelZoom: true,
            tap: true,
            touchZoom: true,
            doubleClickZoom: true
        }).setView(defaultCoords, defaultZoom);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(illegalMap);
        
        // Add markers for mangrove reports
        <?php
        $mangroveMarkersSql = "SELECT latitude, longitude, species, report_date, report_id 
                      FROM mangrovereporttbl ";
                      
        if (!$isAdminOrRep && isset($city_municipality)) {
            $mangroveMarkersSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
        }

        $mangroveMarkersSql .= "ORDER BY report_date DESC LIMIT 50";
        $mangroveMarkersResult = $connection->query($mangroveMarkersSql);
        
        if ($mangroveMarkersResult && $mangroveMarkersResult->num_rows > 0) {
            while ($row = $mangroveMarkersResult->fetch_assoc()) {
                if (!empty($row['latitude']) && !empty($row['longitude'])) {
                    $formattedSpecies = formatSpeciesDisplay($row['species']);
                    echo "
                    L.marker([".$row['latitude'].", ".$row['longitude']."])
                        .addTo(mangroveMap)
                        .bindPopup('<b>".addslashes($formattedSpecies)."</b><br>".date('Y-m-d', strtotime($row['report_date']))."');";
                }
            }
        }
        ?>
        
        // Add markers for illegal reports
        <?php
        $illegalMarkersSql = "SELECT latitude, longitude, incident_type, report_date, report_id 
                     FROM illegalreportstbl ";
                     
        if (!$isAdminOrRep && isset($city_municipality)) {
            $illegalMarkersSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
        }

        $illegalMarkersSql .= "ORDER BY report_date DESC LIMIT 50";
        $illegalMarkersResult = $connection->query($illegalMarkersSql);
        
        if ($illegalMarkersResult && $illegalMarkersResult->num_rows > 0) {
            while ($row = $illegalMarkersResult->fetch_assoc()) {
                if (!empty($row['latitude']) && !empty($row['longitude'])) {
                    echo "
                    L.marker([".$row['latitude'].", ".$row['longitude']."])
                        .addTo(illegalMap)
                        .bindPopup('<b>".addslashes($row['incident_type'])."</b><br>".date('Y-m-d', strtotime($row['report_date']))."');";
                }
            }
        }
        ?>
        
        // Fix map rendering issues
        setTimeout(() => {
            mangroveMap.invalidateSize();
            illegalMap.invalidateSize();
        }, 100);
        
    } catch (e) {
        console.error("Map initialization error:", e);
    }
    
    // Filtering functionality remains the same
    const keywordInput = document.getElementById('filter-keyword');
    const typeSelect = document.getElementById('filter-type');
    const prioritySelect = document.getElementById('filter-priority');
    const dateInput = document.getElementById('filter-date');
    const cityFilter = document.getElementById('filter-city');
    const barangayFilter = document.getElementById('filter-barangay');

    // When city changes, load barangays for that city
    cityFilter.addEventListener('change', async function() {
        const selectedCity = this.value;
        
        // Reset barangay filter
        barangayFilter.innerHTML = '<option value="">All Barangays</option>';
        barangayFilter.disabled = !selectedCity;
        
        if (selectedCity) {
            try {
                // Show loading state
                barangayFilter.disabled = true;
                const defaultOption = barangayFilter.querySelector('option');
                defaultOption.textContent = 'Loading barangays...';
                
                // Fetch barangays for selected city
                const response = await fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`);
                const barangays = await response.json();
                
                // Populate barangay dropdown
                barangayFilter.innerHTML = '<option value="">All Barangays</option>';
                if (Array.isArray(barangays)) {
                    barangays.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.barangay;
                        option.textContent = barangay.barangay;
                        barangayFilter.appendChild(option);
                    });
                }
                
                barangayFilter.disabled = false;
                filterReports(); // Apply filtering after loading barangays
            } catch (error) {
                console.error('Error loading barangays:', error);
                barangayFilter.innerHTML = '<option value="">Error loading barangays</option>';
            }
        } else {
            filterReports(); // If city is cleared, apply filtering
        }
    });

    // When barangay changes, filter reports
    barangayFilter.addEventListener('change', filterReports);

    function filterReports() {
        const keyword = keywordInput.value.toLowerCase();
        const startDate = document.getElementById('filter-start-date').value;
        const endDate = document.getElementById('filter-end-date').value;
        const singleDate = document.getElementById('filter-date').value;
        const cityFilterValue = document.getElementById('filter-city').value;
        const barangayFilterValue = document.getElementById('filter-barangay').value;

        // Filter mangrove reports
        const plantTypeFilter = document.getElementById('filter-plant-type').value;
        const mangroveStatusFilter = document.getElementById('filter-mangrove-status').value; // NEW FILTER
        const mangroveList = document.getElementById('mangrove-reports-list');
        let mangroveVisible = 0;
        
        Array.from(mangroveList.children).forEach(item => {
            if (item.classList.contains('no-results')) return;
            
            const matchesKeyword = keyword === '' || item.textContent.toLowerCase().includes(keyword);
            const matchesPlantType = plantTypeFilter === '' || item.dataset.plantType === plantTypeFilter;
            const matchesMangroveStatus = mangroveStatusFilter === '' || item.dataset.mangroveStatus === mangroveStatusFilter; // NEW FILTER CHECK
            const matchesCity = cityFilterValue === '' || item.dataset.cityMunicipality === cityFilterValue;
            
            // Check barangay filter if city is selected and barangay is specified
            let matchesBarangay = true;
            if (cityFilterValue && barangayFilterValue) {
                const barangays = (item.dataset.barangay || '').toLowerCase();
                matchesBarangay = barangays.split(',')
                    .map(b => b.trim())
                    .some(b => b === barangayFilterValue.toLowerCase());
            }
            
            // Check date range if provided
            let matchesDate = true;
            const reportDate = item.querySelector('.report-meta')?.textContent.match(/\d{4}-\d{2}-\d{2}/)?.[0];
            
            if (reportDate) {
                // If using date range
                if (startDate || endDate) {
                    if (startDate && reportDate < startDate) matchesDate = false;
                    if (endDate && reportDate > endDate) matchesDate = false;
                } 
                // Fallback to single date if no range is set
                else if (singleDate) {
                    matchesDate = reportDate === singleDate;
                }
            } else {
                matchesDate = false; // No date found in report
            }

            const show = (matchesKeyword && matchesDate && matchesPlantType && matchesMangroveStatus && matchesCity && matchesBarangay); // UPDATED WITH NEW FILTER
            item.style.display = show ? 'flex' : 'none';
            if (show) mangroveVisible++;
        });

        // Handle "no results" message for mangrove
        let mangroveNoResults = mangroveList.querySelector('.no-results');
        if (!mangroveNoResults) {
            mangroveNoResults = document.createElement('li');
            mangroveNoResults.className = 'report-item no-results';
            mangroveNoResults.textContent = 'No reports found matching your filters.';
            mangroveNoResults.style.display = 'none';
            mangroveList.appendChild(mangroveNoResults);
        }
        mangroveNoResults.style.display = mangroveVisible === 0 ? 'flex' : 'none';

        // Rest of the function remains the same for illegal reports...
        // Filter illegal reports (both regular and emergency)
        const priorityFilter = document.getElementById('filter-priority').value;
        const incidentCategoryFilter = document.getElementById('filter-incident-category').value;
        const incidentTypeFilter = document.getElementById('filter-incident-type').value;

        // Apply to both illegal reports lists
        ['illegal-reports-list', 'illegal-emergency-list'].forEach(listId => {
            const list = document.getElementById(listId);
            let visibleCount = 0;
            
            Array.from(list.children).forEach(item => {
                if (item.classList.contains('no-results')) return;
                
                const matchesKeyword = keyword === '' || item.textContent.toLowerCase().includes(keyword);
                const matchesPriority = priorityFilter === '' || item.dataset.priority === priorityFilter;
                const matchesIncidentCategory = incidentCategoryFilter === '' || item.dataset.incidentCategory === incidentCategoryFilter;
                const matchesIncidentType = incidentTypeFilter === '' || item.dataset.incidentType === incidentTypeFilter;
                const matchesCity = cityFilterValue === '' || item.dataset.cityMunicipality === cityFilterValue;
                
                // Check barangay filter if city is selected and barangay is specified
                let matchesBarangay = true;
                if (cityFilterValue && barangayFilterValue) {
                    const barangays = (item.dataset.barangay || '').toLowerCase();
                    matchesBarangay = barangays.split(',')
                        .map(b => b.trim())
                        .some(b => b === barangayFilterValue.toLowerCase());
                }

                // Check date range if provided
                let matchesDate = true;
                const reportDate = item.querySelector('.report-meta')?.textContent.match(/\d{4}-\d{2}-\d{2}/)?.[0];
                
                if (reportDate) {
                    // If using date range
                    if (startDate || endDate) {
                        if (startDate && reportDate < startDate) matchesDate = false;
                        if (endDate && reportDate > endDate) matchesDate = false;
                    } 
                    // Fallback to single date if no range is set
                    else if (singleDate) {
                        matchesDate = reportDate === singleDate;
                    }
                } else {
                    matchesDate = false; // No date found in report
                }

                const isEmergencyItem = listId === 'illegal-emergency-list';
                const priorityMatches = isEmergencyItem ? true : matchesPriority;

                const show = (matchesKeyword && matchesDate && priorityMatches &&
                    matchesIncidentCategory && matchesIncidentType && matchesCity && matchesBarangay);
                item.style.display = show ? 'flex' : 'none';
                if (show) visibleCount++;
            });

            // Handle "no results" message
            let noResults = list.querySelector('.no-results');
            if (!noResults) {
                noResults = document.createElement('li');
                noResults.className = 'report-item no-results';
                noResults.textContent = 'No reports found matching your filters.';
                noResults.style.display = 'none';
                list.appendChild(noResults);
            }
            noResults.style.display = visibleCount === 0 ? 'flex' : 'none';
        });
    }

    // Add event listeners for new filters
    keywordInput.addEventListener('input', filterReports);
    dateInput.addEventListener('change', filterReports);
    document.getElementById('filter-start-date').addEventListener('change', filterReports);
    document.getElementById('filter-end-date').addEventListener('change', filterReports);
    document.getElementById('filter-plant-type').addEventListener('change', filterReports);
    document.getElementById('filter-mangrove-status').addEventListener('change', filterReports); 
    document.getElementById('filter-priority').addEventListener('change', filterReports);
    document.getElementById('filter-incident-category').addEventListener('change', filterReports);
    document.getElementById('filter-incident-type').addEventListener('change', filterReports);
    document.getElementById('filter-city').addEventListener('change', filterReports);
    
    // Clear date filter button
    document.querySelector('.date-range .clear-date-btn').addEventListener('click', function() {
        document.getElementById('filter-start-date').value = '';
        document.getElementById('filter-end-date').value = '';
        filterReports();
    });

    document.addEventListener('click', function(e) {
        if (e.target.closest('.page-btn')) {
            const btn = e.target.closest('.page-btn');
            const pagination = btn.closest('.page-ination');
            const reportType = pagination.dataset.reportType;
            let currentPage = parseInt(pagination.dataset.currentPage);
            const totalPages = parseInt(pagination.dataset.totalPages);
            
            if (btn.classList.contains('prev-btn') && currentPage > 1) {
                currentPage--;
            } else if (btn.classList.contains('next-btn') && currentPage < totalPages) {
                currentPage++;
            }
            
            // Update UI immediately
            pagination.dataset.currentPage = currentPage;
            pagination.querySelector('.page-info').textContent = `Page ${currentPage} of ${totalPages}`;
            
            // Disable/enable buttons appropriately
            pagination.querySelector('.prev-btn').disabled = currentPage <= 1;
            pagination.querySelector('.next-btn').disabled = currentPage >= totalPages;
            
            // Load reports asynchronously
            loadReports(reportType, currentPage);
        }
    });

    async function loadReports(reportType, page) {
        try {
            const offset = (page - 1) * 10;
            let url, listId;
            
            if (reportType === 'mangrove') {
                url = `load_mangrove_reports.php?offset=${offset}`;
                listId = 'mangrove-reports-list';
            } else {
                url = `load_illegal_reports.php?offset=${offset}`;
                listId = 'illegal-reports-list';
            }
            
            // Apply current filters to the URL
            const keyword = document.getElementById('filter-keyword').value;
            const date = document.getElementById('filter-date').value;
            const plantType = document.getElementById('filter-plant-type')?.value || '';
            const mangroveStatus = document.getElementById('filter-mangrove-status')?.value || ''; // NEW FILTER
            const priority = document.getElementById('filter-priority')?.value || '';
            const incidentCategory = document.getElementById('filter-incident-category')?.value || '';
            const incidentType = document.getElementById('filter-incident-type')?.value || '';
            const city = document.getElementById('filter-city')?.value || '';
            const barangay = document.getElementById('filter-barangay')?.value || '';
            
            // Build query string with filters
            let queryString = `offset=${offset}`;
            if (keyword) queryString += `&keyword=${encodeURIComponent(keyword)}`;
            if (date) queryString += `&date=${date}`;
            if (plantType) queryString += `&plant_type=${encodeURIComponent(plantType)}`;
            if (mangroveStatus) queryString += `&mangrove_status=${encodeURIComponent(mangroveStatus)}`; // NEW FILTER
            if (priority) queryString += `&priority=${encodeURIComponent(priority)}`;
            if (incidentCategory) queryString += `&incident_category=${encodeURIComponent(incidentCategory)}`;
            if (incidentType) queryString += `&incident_type=${encodeURIComponent(incidentType)}`;
            if (city) queryString += `&city=${encodeURIComponent(city)}`;
            if (barangay) queryString += `&barangay=${encodeURIComponent(barangay)}`;
            
            const response = await fetch(`${url}?${queryString}`);
            const html = await response.text();
            
            document.getElementById(listId).innerHTML = html;
            
        } catch (error) {
            console.error('Error loading reports:', error);
        }
    }

         // Filter functionality
    const statusFilter = document.getElementById('statusFilter');
    const reportTypeFilter = document.getElementById('reportTypeFilter');
    
statusFilter.addEventListener('change', filterStatusReports);
reportTypeFilter.addEventListener('change', filterStatusReports);

    function filterStatusReports() {
        const statusValue = statusFilter.value;
        const typeValue = reportTypeFilter.value;

        let anyVisible = false;
        document.querySelectorAll('.list-group-item[data-status]').forEach(item => {
            const itemStatus = item.getAttribute('data-status');
            const itemType = item.getAttribute('data-type');

            const statusMatch = statusValue === 'all' || itemStatus === statusValue;
            const typeMatch = typeValue === 'all' || itemType === typeValue;

            if (statusMatch && typeMatch) {
                item.style.display = 'block';
                anyVisible = true;
            } else {
                item.style.display = 'none';
            }
        });

        // Show/hide "no results" message
        let noResultsDiv = document.getElementById('status-no-results');
        if (!noResultsDiv) {
            noResultsDiv = document.createElement('div');
            noResultsDiv.id = 'status-no-results';
            noResultsDiv.className = 'list-group-item p-3 text-center text-muted';
            noResultsDiv.textContent = 'No reports found matching your filters.';
            const listGroup = document.querySelector('.card-body .list-group');
            if (listGroup) listGroup.appendChild(noResultsDiv);
        }
        noResultsDiv.style.display = anyVisible ? 'none' : 'block';
    }
    
    // Status update modal
    const statusUpdateModal = new bootstrap.Modal('#statusUpdateModal');
    const updateButtons = document.querySelectorAll('.update-status-btn');

    updateButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const reportId = this.getAttribute('data-report-id');
            const reportType = this.getAttribute('data-report-type');
            const reportItem = this.closest('.list-group-item');
            const statusBadge = reportItem.querySelector('.status-badge');
            const currentStatus = statusBadge ? statusBadge.textContent.trim() : 'Received';
            
            await showStatusModal(reportId, reportType, currentStatus);
        });
    });
    
    // Form submission
    document.getElementById('submitStatusUpdate').addEventListener('click', async function() {
        const form = document.getElementById('statusUpdateForm');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        const reportId = document.getElementById('currentReportId').value;
        const reportType = document.getElementById('currentReportType').value;
        const newStatus = document.getElementById('statusSelect').value;
        const actionDesc = document.getElementById('actionDescription').value;
        const userId = document.getElementById('currentUserId').value;
        const userRole = document.getElementById('currentUserRole').value;
        
        try {
            // First get the report type and account_id
            const reportInfo = await getReportInfo(reportId, reportType);
            
            if (!reportInfo.success) {
                throw new Error(reportInfo.message || 'Failed to get report information');
            }
            
            // Validate the new status
            const validStatuses = ['Investigating', 'Action Taken', 'Resolved', 'Rejected'];
            if (!validStatuses.includes(newStatus)) {
                throw new Error('Invalid status selected');
            }
            
            // Create notification data
            const notifData = {
                report_id: reportId,
                report_type: reportType, // Add report_type to the data
                account_id: reportInfo.account_id,
                action_type: newStatus,
                admin_notif_description: generateAdminDescription(newStatus, userRole),
                notif_description: actionDesc,
                notifier_type: userRole === 'Barangay Official' ? 'accountstbl' : 'adminaccountstbl'
            };
            
            // Show loading state
            const submitBtn = document.getElementById('submitStatusUpdate');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Updating...';
            
            // Send data to server
            const response = await fetch('save_report_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(notifData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Check if rating is required for resolved illegal activity reports
                if (result.requires_rating) {
                    // Close status update modal first
                    statusUpdateModal.hide();
                    
                    // Get report data from the current context and show rating modal
                    showRatingModal(reportId, reportType);
                } else {
                    // Show success message using flash container
                    await showAlert(`Status for report ${reportId} (${reportType}) updated to ${newStatus}`, 'success');
                }
            } else {
                throw new Error(result.message || 'Unknown error');
            }
        } catch (error) {
            console.error('Error updating status:', error);
            // Show error message using flash container
            await showAlert('Error updating status: ' + error.message, 'error');
            
            // Reset button state
            const submitBtn = document.getElementById('submitStatusUpdate');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Update Status';
        }
    });


    function showAlert(message, type) {
        // Set the flash message in session
        fetch('set_flash_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: type,
                msg: message
            })
        }).then(() => {
            // Reload the page to show the flash message
            window.location.reload();
        });
    }

    async function getReportInfo(reportId, reportType) {
        try {
            const response = await fetch(`get_report_info.php?report_id=${reportId}&report_type=${reportType}`);
            return await response.json();
        } catch (error) {
            console.error('Error fetching report info:', error);
            return { success: false, message: 'Failed to fetch report information' };
        }
    }


    // Helper function to generate admin description based on status
    function generateAdminDescription(status, userRole) {
        const userTitle = userRole === 'Barangay Official' ? 'Barangay Official' : 'Administrator';
        const now = new Date().toLocaleString('en-PH', { 
            timeZone: 'Asia/Manila',
            dateStyle: 'medium',
            timeStyle: 'short'
        });
        
        switch(status) {
            case 'Investigating':
                return `${userTitle} ${<?= json_encode($_SESSION['name'] ?? '') ?>} has started investigating this report on ${now}.`;
            case 'Action Taken':
                return `${userTitle} ${<?= json_encode($_SESSION['name'] ?? '') ?>} has taken action on this report on ${now}.`;
            case 'Resolved':
                return `${userTitle} ${<?= json_encode($_SESSION['name'] ?? '') ?>} has marked this report as resolved on ${now}.`;
            case 'Rejected':
                return `${userTitle} ${<?= json_encode($_SESSION['name'] ?? '') ?>} has rejected this report on ${now}.`;
            default:
                return `Status updated by ${userTitle} ${<?= json_encode($_SESSION['name'] ?? '') ?>} on ${now}.`;
        }
    }

    const reportModal = document.getElementById('reportModal');
    const reportModalContent = document.getElementById('reportModalContent');
    const modalClose = document.querySelector('.report-modal-close');

    // Close modal when clicking X or outside
    modalClose.addEventListener('click', () => {
        reportModal.style.display = 'none';
    });

    window.addEventListener('click', (event) => {
        if (event.target === reportModal) {
            reportModal.style.display = 'none';
        }
    });

                // Function to show status update modal
    async function showStatusModal(reportId, reportType = null, forceStatus = null) {
        try {
            const prevNotifPreview = document.getElementById('previousNotificationPreview');
            prevNotifPreview.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm" role="status"></div> Loading...</div>';
            
            // If reportType isn't provided, determine it
            if (!reportType) {
                const typeResponse = await fetch(`get_report_type.php?report_id=${reportId}`);
                const typeData = await typeResponse.json();
                reportType = typeData.success ? typeData.report_type : 
                            (reportId.startsWith('MR-') ? 'Mangrove Data Report' : 'Illegal Activity Report');
            }

            // Fetch the latest notification to get actual current status
            const response = await fetch(`get_latest_notification.php?report_id=${reportId}&report_type=${reportType}`);
            const result = await response.json();
            
            // Set basic modal info
            document.getElementById('currentReportId').value = reportId;
            document.getElementById('displayReportId').value = reportId;
            
            // Store report type in a hidden field
            document.getElementById('currentReportType').value = reportType;
            
            // ALWAYS show the actual current status from the database
            const actualCurrentStatus = result.success ? result.notification.action_type : 'Received';
            document.getElementById('currentStatus').value = actualCurrentStatus;
            
            // Update notification preview
            if (result.success && result.notification.notif_description) {
                prevNotifPreview.innerHTML = `
                    <div><strong>${result.notification.action_type}</strong></div>
                    <div class="notification-description">${result.notification.notif_description}</div>
                    <small>${new Date(result.notification.notif_date).toLocaleString()}</small>
                `;
            } else {
                prevNotifPreview.innerHTML = '<em>No previous notification found</em>';
            }
            
            // Initialize status select - empty by default
            const statusSelect = document.getElementById('statusSelect');
            statusSelect.value = '';
            
            // If forceStatus is provided (for Reject button), select it but don't change current status display
            if (forceStatus) {
                statusSelect.value = forceStatus;
            }
            
            // Show modal
            statusUpdateModal.show();
            
        } catch (error) {
            console.error('Error:', error);
            prevNotifPreview.innerHTML = '<em class="text-danger">Error loading notification</em>';
            document.getElementById('currentReportId').value = reportId;
            document.getElementById('displayReportId').value = reportId;
            document.getElementById('currentStatus').value = 'Error loading status';
            statusUpdateModal.show();
        }
    }

    async function loadReportDetails(reportId, isMangrove) {
        try {
            const reportType = isMangrove ? 'Mangrove Data Report' : 'Illegal Activity Report';
            
            if (!reportType) {
                reportType = isMangrove ? 'Mangrove Data Report' : 'Illegal Activity Report';
            }

            // Load report details and history simultaneously
            const [detailsResponse, historyResponse] = await Promise.all([
                fetch(`get_report_details.php?report_id=${reportId}&type=${isMangrove ? 'mangrove' : 'illegal'}`),
                fetch(`get_report_history.php?report_id=${reportId}&report_type=${reportType}`)
            ]);
            
            const detailsData = await detailsResponse.json();
            const historyData = await historyResponse.json();
            
            if (!detailsData.success) {
                throw new Error(detailsData.message || 'Failed to load report details');
            }

            const report = detailsData.report;
            const currentStatus = historyData.success && historyData.history.length > 0 
                ? historyData.history[0].status 
                : 'Received';

            // Format barangays display
            let barangaysDisplay = 'None specified';
            if (report.barangays) {
                const barangaysList = report.barangays.split(',').map(b => b.trim());
                barangaysDisplay = barangaysList.join(', ');
            }

            // Build HTML
            let html = `
                <h3>${isMangrove ? 'Mangrove Data' : 'Illegal Activity'} Report Details</h3>
                <div class="report-details-container">
            `;
            
            // Common details for both report types
            html += `
                <div class="report-details-row">
                    <div class="report-details-label">Location:</div>
                    <div class="report-details-value">${report.city_municipality || 'N/A'}</div>
                </div>
                <div class="report-details-row">
                    <div class="report-details-label">Nearby Barangays:</div>
                    <div class="report-details-value">${barangaysDisplay}</div>
                </div>
                <div class="report-details-row">
                    <div class="report-details-label">Address:</div>
                    <div class="report-details-value">${report.address || 'No address specified'}</div>
                </div>
                <div class="report-details-row">
                    <div class="report-details-label">Report Date:</div>
                    <div class="report-details-value">${new Date(report.report_date).toLocaleString() || 'N/A'}</div>
                </div>
            `;
            
            // Type-specific details
            if (isMangrove) {
                html += `
                    <div class="report-details-row">
                        <div class="report-details-label">Species:</div>
                        <div class="report-details-value">${formatSpeciesDisplay(report.species)}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Area Number:</div>
                        <div class="report-details-value">${report.area_no || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Mangrove Status:</div>
                        <div class="report-details-value">${report.mangrove_status || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Area (m²):</div>
                        <div class="report-details-value">${report.area_m2 || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Priority:</div>
                        <div class="report-details-value">${report.priority || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Remarks:</div>
                        <div class="report-details-value">${report.remarks || 'No remarks provided'}</div>
                    </div>
                `;
            } else {
                html += `
                    <div class="report-details-row">
                        <div class="report-details-label">Incident Type:</div>
                        <div class="report-details-value">${report.incident_type || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Area Number:</div>
                        <div class="report-details-value">${report.area_no || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Priority:</div>
                        <div class="report-details-value">${report.priority || 'N/A'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Description:</div>
                        <div class="report-details-value">${report.description || 'No description provided'}</div>
                    </div>
                    <div class="report-details-row">
                        <div class="report-details-label">Contact Number:</div>
                        <div class="report-details-value">${report.contact_no || 'N/A'}</div>
                    </div>
                `;
            }

            // Handle images with slider
            const images = [];
            if (isMangrove) {
                if (report.image1) images.push(report.image1);
                if (report.image2) images.push(report.image2);
                if (report.image3) images.push(report.image3);
            } else {
                if (report.image_video1) images.push(report.image_video1);
                if (report.image_video2) images.push(report.image_video2);
                if (report.image_video3) images.push(report.image_video3);
            }
            
            if (images.length > 0) {
                html += `
                <div class="report-details-row attachments">
                    <div class="report-details-label">Attachments:</div>
                    <div class="report-details-value attachments">
                        <div class="report-images-slider">
                            ${images.length > 1 ? '<div class="slider-arrow prev">&#10094;</div>' : ''}
                            ${images.length > 1 ? '<div class="slider-arrow next">&#10095;</div>' : ''}
                            <div class="report-images-track">
                                ${images.map((img, index) => `
                                    <div class="report-image-slide">
                                        ${img.match(/\.(mp4|mov|avi)$/i) ? 
                                            `<video controls><source src="${img}" type="video/mp4">Your browser does not support the video tag.</video>` :
                                            `<img src="${img}" alt="Report attachment ${index + 1}">`
                                        }
                                    </div>
                                `).join('')}
                            </div>
                            ${images.length > 1 ? `
                            <div class="slider-nav">
                                ${images.map((_, index) => `
                                    <div class="slider-dot ${index === 0 ? 'active' : ''}" data-index="${index}"></div>
                                `).join('')}
                            </div>` : ''}
                        </div>
                    </div>
                </div>`;
            }

            // Current status display
            html += `
                <div class="report-details-row">
                    <div class="report-details-label">Current Status:</div>
                    <div class="report-details-value">
                        <span class="status-badge ${getStatusBadgeClass(currentStatus)}">
                            ${currentStatus}
                        </span>
                    </div>
                </div>
            `;

            // Status history if available
            if (historyData.success && historyData.history.length > 0) {
                html += `
                <div class="status-history">
                    <h4>Status History</h4>
                    <div class="status-history-list">
                        ${historyData.history.map(item => `
                        <div class="status-item">
                            <div class="status-header">
                                <strong>${item.status}</strong>
                                <small>${new Date(item.date).toLocaleString()}</small>
                            </div>
                            <div class="status-notifier">
                                ${item.notifier_type === 'accountstbl' ? 'User' : 'Official'}: 
                                ${item.notifier}
                            </div>
                            <div class="status-description">${item.description}</div>
                        </div>
                        `).join('')}
                    </div>
                </div>`;
            } else {
                html += `
                <div class="status-history">
                    <h4>Status History</h4>
                    <div class="no-history">No status updates recorded yet</div>
                </div>`;
            }

            // Action buttons
            html += `
                </div>
                <div class="report-actions">
                    <button class="action-btn update" data-report-id="${reportId}" data-report-type="${reportType}">Update Status</button>
                    <button class="action-btn reject" data-report-id="${reportId}" data-report-type="${reportType}">Reject Report</button>
                </div>
            `;

            reportModalContent.innerHTML = html;
            reportModal.style.display = 'block';

            // Initialize the slider after content is loaded
            if (images.length > 0) {
                initImageSlider();
            }

            // Add event listeners for action buttons
            document.querySelector('.action-btn.update').addEventListener('click', async () => {
                reportModal.style.display = 'none';
                await showStatusModal(reportId, reportType); // Just show modal with current status
            });

            document.querySelector('.action-btn.reject').addEventListener('click', async () => {
                reportModal.style.display = 'none';
                await showStatusModal(reportId, reportType, 'Rejected'); // Show modal with Rejected pre-selected
            });

        } catch (error) {
            console.error('Error loading report details:', error);
            reportModalContent.innerHTML = `
                <div class="error-message">
                    <h3>Error Loading Report</h3>
                    <p>${error.message || 'Please try again later.'}</p>
                </div>
            `;
            reportModal.style.display = 'block';
        }
    }

    reportModalContent.addEventListener('click', function(e) {
        if (e.target.classList.contains('action-btn')) {
            const reportId = e.target.getAttribute('data-report-id');
            const reportType = e.target.getAttribute('data-report-type'); // Get report type
            
            if (e.target.classList.contains('update')) {
                reportModal.style.display = 'none';
                showStatusModal(reportId, reportType); // Pass report type
            } else if (e.target.classList.contains('reject')) {
                reportModal.style.display = 'none';
                showStatusModal(reportId, reportType, 'Rejected'); // Pass report type
            }
        }
    });

    function getStatusBadgeClass(status) {
        switch(status) {
            case 'Received': return 'bg-info';
            case 'Investigating': return 'bg-warning';
            case 'Action Taken': return 'bg-success';
            case 'Resolved': return 'bg-primary';
            case 'Rejected': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    async function getLatestNotification(reportId) {
        try {
            const response = await fetch(`get_latest_notification.php?report_id=${reportId}`);
            const data = await response.json();
            
            // Return the notification if valid, otherwise null
            return data?.success ? data.notification : null;
        } catch (error) {
            console.error('Error fetching notification:', error);
            return null;
        }
    }

    function initImageSlider() {
        const track = document.querySelector('.report-images-track');
        if (!track) return; // Exit if no slider exists
        
        const slides = Array.from(document.querySelectorAll('.report-image-slide'));
        const dots = Array.from(document.querySelectorAll('.slider-dot'));
        const prevBtn = document.querySelector('.slider-arrow.prev');
        const nextBtn = document.querySelector('.slider-arrow.next');
        
        let currentIndex = 0;
        const slideCount = slides.length;
        
        // Only initialize if there are slides
        if (slideCount === 0) return;
        
        const updateSlider = () => {
            // Update track position
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            
            // Update active dot
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentIndex);
            });
            
            // Disable prev/next buttons at boundaries
            if (prevBtn) prevBtn.style.visibility = currentIndex === 0 ? 'hidden' : 'visible';
            if (nextBtn) nextBtn.style.visibility = currentIndex === slideCount - 1 ? 'hidden' : 'visible';
        };
        
        // Initialize slider
        updateSlider();
        
        // Previous button click handler
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentIndex > 0) {
                    currentIndex--;
                    updateSlider();
                }
            });
        }
        
        // Next button click handler
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentIndex < slideCount - 1) {
                    currentIndex++;
                    updateSlider();
                }
            });
        }
        
        // Dot navigation
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                currentIndex = parseInt(dot.dataset.index);
                updateSlider();
            });
        });
        
        // Touch support for mobile devices
        let touchStartX = 0;
        let touchEndX = 0;
        
        track.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        track.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            const diff = touchStartX - touchEndX;
            if (diff > 50 && currentIndex < slideCount - 1) {
                // Swipe left - next
                currentIndex++;
                updateSlider();
            } else if (diff < -50 && currentIndex > 0) {
                // Swipe right - previous
                currentIndex--;
                updateSlider();
            }
        }
    }

    document.querySelectorAll('.view-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const reportId = this.getAttribute('data-report-id');
            const reportType = this.getAttribute('data-report-type');
            const isMangrove = reportType === 'mangrove';
            
            // Show loading state
            reportModalContent.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading report details...</p>
                </div>
            `;
            reportModal.style.display = 'block';
            
            loadReportDetails(reportId, isMangrove, reportType);
        });
    });
    
    // Rating Modal Functions
    let currentRatingReportId = null;
    let currentRating = 0;
    
    function showRatingModal(reportId, reportType) {
        currentRatingReportId = reportId;
        currentRating = 0;
        
        // Fetch report details and populate modal
        fetchReportForRating(reportId);
        
        // Show the modal
        const ratingModal = new bootstrap.Modal(document.getElementById('ratingModal'));
        ratingModal.show();
    }
    
    async function fetchReportForRating(reportId) {
        try {
            const response = await fetch(`get_report_details.php?report_id=${reportId}&type=illegal`);
            const result = await response.json();
            
            if (result.success && result.report) {
                const report = result.report;
                
                // Populate modal with report data
                document.getElementById('modal-report-id').textContent = report.report_id || reportId;
                document.getElementById('modal-incident-type').textContent = report.incident_type || 'Unknown';
                document.getElementById('modal-priority').textContent = report.priority || 'Normal';
                document.getElementById('modal-location').textContent = (report.barangays || 'Unknown Barangay') + ', ' + (report.city_municipality || 'Unknown City');
                
                // Handle reporter name
                if (report.reporter_id) {
                    // Try to get from accounts table
                    const reporterResponse = await fetch(`get_report_details.php?report_id=${reportId}&type=illegal`);
                    const reporterResult = await reporterResponse.json();
                    document.getElementById('modal-reporter').textContent = reporterResult.success ? (reporterResult.report.reporter_name || 'Anonymous') : 'Anonymous';
                } else {
                    document.getElementById('modal-reporter').textContent = 'Anonymous';
                }
            } else {
                // Fallback data
                document.getElementById('modal-report-id').textContent = reportId;
                document.getElementById('modal-incident-type').textContent = 'Unknown';
                document.getElementById('modal-priority').textContent = 'Normal';
                document.getElementById('modal-location').textContent = 'Unknown';
                document.getElementById('modal-reporter').textContent = 'Unknown';
            }
        } catch (error) {
            console.error('Error fetching report for rating:', error);
            // Use fallback data
            document.getElementById('modal-report-id').textContent = reportId;
            document.getElementById('modal-incident-type').textContent = 'Error loading';
            document.getElementById('modal-priority').textContent = 'Error';
            document.getElementById('modal-location').textContent = 'Error loading';
            document.getElementById('modal-reporter').textContent = 'Error';
        }
    }
    
    // Star rating functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('star')) {
            const rating = parseInt(e.target.dataset.rating);
            setRating(rating);
        }
    });
    
    function setRating(rating) {
        currentRating = rating;
        
        // Update star display
        const stars = document.querySelectorAll('#starRating .star');
        stars.forEach((star, index) => {
            if (index < rating) {
                star.style.color = '#ffc107';
            } else {
                star.style.color = '#e9ecef';
            }
        });
        
        // Update description
        const descriptions = {
            1: 'Poor: Report had significant inaccuracies',
            2: 'Fair: Report had some issues but provided useful information',
            3: 'Good: Report was mostly accurate and helpful',
            4: 'Very Good: Report was accurate and well-detailed',
            5: 'Excellent: Report was highly accurate and extremely helpful'
        };
        
        document.getElementById('ratingDescription').innerHTML = descriptions[rating] || 'Please select a rating';
        
        // Calculate and display points
        if (rating > 0) {
            const priority = document.getElementById('modal-priority').textContent;
            const maxPoints = (priority === 'Emergency') ? 50 : 25;
            const ratingMultiplier = rating / 5.0;
            let pointsAwarded = Math.round(maxPoints * ratingMultiplier);
            
            // Ensure minimum points for any valid rating (1-5 stars)
            // Even 1-star ratings should give at least some points as acknowledgment
            if (pointsAwarded < 1 && rating >= 1) {
                pointsAwarded = 1; // Minimum 1 point for any valid rating
            }
            
            // Show points indicator
            const pointsIndicator = document.getElementById('pointsIndicator');
            const pointsAmount = document.getElementById('pointsAmount');
            const pointsCalculation = document.getElementById('pointsCalculation');
            const pointsExplanation = document.getElementById('pointsExplanation');
            
            pointsAmount.textContent = pointsAwarded;
            pointsCalculation.textContent = `(${rating}/5 × ${maxPoints} pts)`;
            pointsExplanation.textContent = `Points awarded based on ${priority.toLowerCase()} priority rating quality`;
            pointsIndicator.style.display = 'block';
        } else {
            // Hide points indicator if no rating selected
            document.getElementById('pointsIndicator').style.display = 'none';
        }
        
        // Enable submit button
        document.getElementById('submitRating').disabled = rating === 0;
    }
    
    // Submit rating
    document.getElementById('submitRating').addEventListener('click', async function() {
        if (currentRating === 0 || !currentRatingReportId) return;
        
        const submitBtn = this;
        const originalText = submitBtn.textContent;
        
        try {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            const response = await fetch('submit_report_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    report_id: currentRatingReportId,
                    rating: currentRating
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message with points/badge info
                const successDiv = document.getElementById('ratingSuccess');
                let successMessage = '<i class="fas fa-check-circle me-2"></i>Rating submitted successfully!';
                
                if (result.points_awarded && result.points_awarded > 0) {
                    successMessage += `<br><small class="text-success"><i class="fas fa-coins"></i> +${result.points_awarded} eco points awarded to reporter!</small>`;
                }
                
                if (result.badge_awarded) {
                    successMessage += `<br><small class="text-warning"><i class="fas fa-medal"></i> Badge "${result.badge_awarded}" awarded!</small>`;
                }
                
                successDiv.innerHTML = successMessage;
                successDiv.style.display = 'block';
                
                // Close modal after delay
                setTimeout(() => {
                    const ratingModal = bootstrap.Modal.getInstance(document.getElementById('ratingModal'));
                    ratingModal.hide();
                    
                    // Enhanced success alert
                    let alertMessage = `Status updated to Resolved and rated ${currentRating} stars!`;
                    if (result.points_awarded > 0) {
                        alertMessage += ` Reporter earned ${result.points_awarded} eco points.`;
                    }
                    if (result.badge_awarded) {
                        alertMessage += ` Badge "${result.badge_awarded}" awarded!`;
                    }
                    
                    showAlert(alertMessage, 'success');
                    
                    // Reset modal
                    resetRatingModal();
                }, 2000); // Increased delay to show badge/points info
            } else {
                throw new Error(result.message || 'Failed to submit rating');
            }
        } catch (error) {
            console.error('Error submitting rating:', error);
            alert('Error submitting rating: ' + error.message);
            
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    function resetRatingModal() {
        currentRating = 0;
        currentRatingReportId = null;
        document.getElementById('submitRating').disabled = true;
        document.getElementById('submitRating').textContent = 'Submit Rating';
        document.getElementById('ratingSuccess').style.display = 'none';
        document.getElementById('ratingSuccess').innerHTML = '<i class="fas fa-check-circle me-2"></i>Rating submitted successfully!';
        document.getElementById('ratingDescription').textContent = 'Please select a rating';
        
        // Reset points indicator
        document.getElementById('pointsIndicator').style.display = 'none';
        
        // Reset stars
        const stars = document.querySelectorAll('#starRating .star');
        stars.forEach(star => {
            star.style.color = '#e9ecef';
        });
    }
    
    // Reset modal when it's hidden
    document.getElementById('ratingModal').addEventListener('hidden.bs.modal', function() {
        resetRatingModal();
    });
    
    // Eco Points Notification Function
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Date range selector toggle
    const dateRangeSelect = document.getElementById('compiler-date-range');
    const dateSelectors = document.querySelectorAll('.date-selector');
    
    dateRangeSelect.addEventListener('change', function() {
        dateSelectors.forEach(selector => {
            selector.style.display = 'none';
        });
        
        const selectedRange = this.value;
        if (selectedRange === 'custom') {
            document.getElementById('custom-range-selector').style.display = 'flex';
        } else {
            document.getElementById(`${selectedRange}-selector`).style.display = 'flex';
        }
    });
    
    // Initialize with daily selector visible
    document.getElementById('daily-selector').style.display = 'flex';
    
    // Preview compilation button
    document.getElementById('preview-compilation').addEventListener('click', previewCompilation);
    
    // Cancel compilation button
    document.getElementById('cancel-compilation').addEventListener('click', function() {
        document.querySelector('.compiler-preview-section').style.display = 'none';
    });
    
    // Select/Deselect all buttons
    document.getElementById('select-all-reports').addEventListener('click', function() {
        document.querySelectorAll('.report-preview-checkbox input').forEach(checkbox => {
            checkbox.checked = true;
        });
    });
    
    document.getElementById('deselect-all-reports').addEventListener('click', function() {
        document.querySelectorAll('.report-preview-checkbox input').forEach(checkbox => {
            checkbox.checked = false;
        });
    });
    
    // Remove selected button
    document.getElementById('remove-selected').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.report-preview-checkbox input:checked');
        checkboxes.forEach(checkbox => {
            checkbox.closest('.report-preview-item').remove();
        });
        
        updatePreviewCount();
        
        // If no reports left, hide preview section
        if (document.querySelectorAll('.report-preview-item').length === 0) {
            document.querySelector('.compiler-preview-section').style.display = 'none';
        }
    });
    
    // Generate PDF button
    document.getElementById('generate-pdf').addEventListener('click', generatePdf);
    
    // Barangay Official restrictions for compiler
    const isBarangayOfficial = <?= json_encode($_SESSION["accessrole"] === 'Barangay Official') ?>;
    const barangayOfficialCity = <?= json_encode($_SESSION["city_municipality"] ?? '') ?>;
    const barangayOfficialBarangay = <?= json_encode($_SESSION["barangay"] ?? '') ?>;

    // Initialize compiler with restrictions
    document.addEventListener('DOMContentLoaded', function() {
        if (isBarangayOfficial) {
            // Disable city and barangay filters and set fixed values
            const citySelect = document.getElementById('compiler-city');
            const barangaySelect = document.getElementById('compiler-barangay');
            
            // Add helper text for Barangay Officials
            const cityGroup = citySelect.closest('.filter-group');
            const barangayGroup = barangaySelect.closest('.filter-group');
            
            // Add restriction notice
            const restrictionNotice = document.createElement('div');
            restrictionNotice.className = 'restriction-notice small text-muted mt-1';
            restrictionNotice.innerHTML = '<i class="fas fa-info-circle"></i> Restricted to your assigned area';
            
            cityGroup.appendChild(restrictionNotice.cloneNode(true));
            barangayGroup.appendChild(restrictionNotice.cloneNode(true));
            
            // Ensure values are set correctly
            if (barangayOfficialCity) {
                citySelect.value = barangayOfficialCity;
            }
            if (barangayOfficialBarangay) {
                // Populate barangay dropdown with only their barangay
                barangaySelect.innerHTML = '';
                const option = document.createElement('option');
                option.value = barangayOfficialBarangay;
                option.textContent = barangayOfficialBarangay;
                option.selected = true;
                barangaySelect.appendChild(option);
            }
        }
    });

    // Function to preview compilation
    async function previewCompilation() {
        // Enforce restrictions for Barangay Officials
        let cityFilter, barangayFilter;
        
        if (isBarangayOfficial) {
            cityFilter = barangayOfficialCity;
            barangayFilter = barangayOfficialBarangay;
            
            // Override any attempts to change the values
            document.getElementById('compiler-city').value = cityFilter;
            document.getElementById('compiler-city').style.border = '1px solid indigo';
            document.getElementById('compiler-barangay').value = barangayFilter;
            document.getElementById('compiler-barangay').style.border = '1px solid indigo';
        } else {
            cityFilter = document.getElementById('compiler-city').value;
            barangayFilter = document.getElementById('compiler-barangay').value;
        }
        
        // Rest of the function remains the same, but use the enforced values
        const reportType = document.getElementById('compiler-report-type').value;
        const dateRange = document.getElementById('compiler-date-range').value;
        
        let startDate, endDate;
        
        // Determine date range based on selection
        switch(dateRange) {
            case 'daily':
                const dailyDateInput = document.getElementById('compiler-date');
                if (!dailyDateInput.value) {
                    await showAlert('Please select a date', 'error');
                    dailyDateInput.focus();
                    return;
                }
                startDate = endDate = dailyDateInput.value;
                break;
                
            case 'custom':
                startDate = document.getElementById('compiler-start-date').value;
                endDate = document.getElementById('compiler-end-date').value;
                if (!startDate || !endDate) {
                    await showAlert('Please select both start and end dates', 'error');
                    return;
                }
                if (new Date(startDate) > new Date(endDate)) {
                    await showAlert('End date must be after start date', 'error');
                    return;
                }
                break;
                
            case 'weekly':
                const weekInput = document.getElementById('compiler-week').value;
                if (!weekInput) {
                    await showAlert('Please select a week', 'error');
                    return;
                }
                const [year, week] = weekInput.split('-W').map(Number);
                startDate = getDateOfISOWeek(week, year);
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 6);
                startDate = formatDate(startDate);
                endDate = formatDate(endDate);
                break;
                
            case 'monthly':
                const monthInput = document.getElementById('compiler-month').value;
                if (!monthInput) {
                    await showAlert('Please select a month', 'error');
                    return;
                }
                startDate = monthInput + '-01';
                const [yearM, monthM] = monthInput.split('-').map(Number);
                const lastDay = new Date(yearM, monthM, 0).getDate();
                endDate = monthInput + '-' + (lastDay < 10 ? '0' + lastDay : lastDay);
                break;
        }
        
        // Show loading state
        const previewList = document.getElementById('preview-reports-list');
        previewList.innerHTML = `
            <div class="empty-preview">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading reports...</p>
            </div>
        `;
        
        // Show preview section
        const previewSection = document.querySelector('.compiler-preview-section');
        previewSection.style.display = 'block';
        previewSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Update preview metadata
        document.getElementById('preview-report-type').textContent = 
            reportType === 'mangrove' ? 'Mangrove Data Reports' : 'Illegal Activity Reports';
        
        const startDateFormatted = new Date(startDate).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'long', day: 'numeric' 
        });
        const endDateFormatted = new Date(endDate).toLocaleDateString('en-US', { 
            year: 'numeric', month: 'long', day: 'numeric' 
        });
        
        document.getElementById('preview-date-range').textContent = 
            startDate === endDate ? startDateFormatted : `${startDateFormatted} - ${endDateFormatted}`;
        
        try {
            // Fetch reports from server with filters
            const response = await fetch('get_reports_for_compilation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    reportType,
                    startDate,
                    endDate,
                    city: cityFilter,
                    barangay: barangayFilter,
                    cityMunicipality: '<?= isset($city_municipality) ? $city_municipality : "" ?>',
                    isBarangayOfficial: isBarangayOfficial // Send this flag to backend
                })
            });
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Failed to load reports');
            }
            
            // Display reports in preview
            previewList.innerHTML = '';
            
            if (result.data.length === 0) {
                previewList.innerHTML = `
                    <div class="empty-preview">
                        <i class="fas fa-inbox fa-2x mb-3"></i>
                        <p>No reports found for the selected criteria</p>
                    </div>
                `;
                updatePreviewCount(0);
                return;
            }
            
            result.data.forEach(report => {
                const reportDate = new Date(report.report_date).toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric'
                });
                
                const statusClass = getStatusClass(report.status || 'Received');
                
                let detailsHtml = '';
                if (reportType === 'mangrove') {
                    detailsHtml = `
                        <div><strong>Species:</strong> ${formatSpeciesDisplay(report.species)}</div>
                        <div><strong>Location:</strong> ${escapeHtml(report.city_municipality) || 'N/A'}</div>
                        <div><strong>Barangay:</strong> ${escapeHtml(report.barangays) || 'N/A'}</div>
                        <div><strong>Area:</strong> ${escapeHtml(report.area_no) || 'N/A'}</div>
                        <div><strong>Status:</strong> ${escapeHtml(report.mangrove_status) || 'N/A'}</div>
                    `;
                } else {
                    detailsHtml = `
                        <div><strong>Incident Type:</strong> ${escapeHtml(report.incident_type) || 'N/A'}</div>
                        <div><strong>Location:</strong> ${escapeHtml(report.city_municipality) || 'N/A'}</div>
                        <div><strong>Barangay:</strong> ${escapeHtml(report.barangays) || 'N/A'}</div>
                        <div><strong>Area:</strong> ${escapeHtml(report.area_no) || 'N/A'}</div>
                        <div><strong>Priority:</strong> ${escapeHtml(report.priority) || 'N/A'}</div>
                    `;
                }
                
                const reportItem = document.createElement('div');
                reportItem.className = 'report-preview-item';
                reportItem.dataset.city = report.city_municipality || '';
                reportItem.dataset.barangay = report.barangays || '';
                reportItem.innerHTML = `
                    <div class="report-preview-checkbox">
                        <input type="checkbox" checked>
                    </div>
                    <div class="report-preview-content">
                        <div class="report-preview-header">
                            <span class="report-preview-id">${escapeHtml(report.report_id)}</span>
                            <span class="report-preview-date">${reportDate}</span>
                        </div>
                        <div class="report-preview-details">
                            ${detailsHtml}
                        </div>
                        <span class="report-preview-status ${statusClass}">
                            ${escapeHtml(report.status) || 'Received'}
                        </span>
                    </div>
                `;
                
                previewList.appendChild(reportItem);
            });
            
            updatePreviewCount(result.data.length);
            
        } catch (error) {
            console.error('Error:', error);
            previewList.innerHTML = `
                <div class="empty-preview text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p>${escapeHtml(error.message) || 'Failed to load reports'}</p>
                </div>
            `;
            updatePreviewCount(0);
        }
    }
    
    function formatDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // Function to generate PDF
    async function generatePdf() {
        const generateBtn = document.getElementById('generate-pdf');
        const originalText = generateBtn.innerHTML;
        
        try {
            // Get selected reports
            const selectedReports = Array.from(
                document.querySelectorAll('.report-preview-item input:checked')
            ).map(item => 
                item.closest('.report-preview-item').querySelector('.report-preview-id').textContent
            );

            if (selectedReports.length === 0) {
                throw new Error('Please select at least one report');
            }

            const reportType = document.getElementById('compiler-report-type').value;
            const reportTypeName = document.getElementById('preview-report-type').textContent;
            const dateRange = document.getElementById('preview-date-range').textContent;

            // Show loading state
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Generating...';

            const response = await fetch('generate_pdf.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    reportIds: selectedReports,
                    reportType: reportType,
                    reportTypeName: reportTypeName,
                    dateRange: dateRange,
                    generatedBy: '<?= isset($_SESSION["name"]) ? $_SESSION["name"] : "System" ?>',
                    generatedRole: '<?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "User" ?>'
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'PDF generation failed');
            }

            // Trigger download
            const link = document.createElement('a');
            link.href = result.downloadUrl;
            link.download = result.filename || 'reports_compilation.pdf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show success message
            await showAlert('PDF generated successfully!', 'success');

        } catch (error) {
            console.error('PDF generation error:', error);
            await showAlert(`Error: ${error.message}`, 'error');
        } finally {
            generateBtn.disabled = false;
            generateBtn.innerHTML = originalText;
        }
    }
    
    // Helper functions
    function updatePreviewCount(count = null) {
        if (count === null) {
            count = document.querySelectorAll('.report-preview-item').length;
        }
        document.getElementById('preview-total-reports').textContent = count;
    }
    
    function getStatusClass(status) {
        const statusMap = {
            'Received': 'status-received',
            'Investigating': 'status-investigating',
            'Action Taken': 'status-action-taken',
            'Resolved': 'status-resolved',
            'Rejected': 'status-rejected'
        };
        return statusMap[status] || 'status-received';
    }
    
    function formatDate(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Helper to get date of ISO week
    function getDateOfISOWeek(week, year) {
        const simple = new Date(year, 0, 1 + (week - 1) * 7);
        const dow = simple.getDay();
        const ISOweekStart = simple;
        if (dow <= 4) {
            ISOweekStart.setDate(simple.getDate() - simple.getDay() + 1);
        } else {
            ISOweekStart.setDate(simple.getDate() + 8 - simple.getDay());
        }
        return ISOweekStart;
    }

    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    async function showAlert(message, type) {
        // Create and show the flash message immediately in the DOM
        const flashContainer = document.createElement('div');
        flashContainer.className = 'flash-container';
        flashContainer.innerHTML = `
            <div class="flash-message flash-${type}">
                ${escapeHtml(message)}
            </div>
        `;
        
        document.body.appendChild(flashContainer);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            flashContainer.style.animation = 'fadeOut 0.5s forwards';
            setTimeout(() => flashContainer.remove(), 500);
        }, 5000);
        
        // For async operations, return a promise that resolves after animation
        return new Promise(resolve => {
            setTimeout(resolve, 5500);
        });
    }

    // City/Brgy filter interaction
    const compilerCity = document.getElementById('compiler-city');
    const compilerBarangay = document.getElementById('compiler-barangay');
    
    compilerCity.addEventListener('change', async function() {
        const selectedCity = this.value;
        compilerBarangay.innerHTML = '<option value="">All Barangays</option>';
        compilerBarangay.disabled = !selectedCity;
        
        if (selectedCity) {
            try {
                // Show loading state
                compilerBarangay.disabled = true;
                const defaultOption = compilerBarangay.querySelector('option');
                defaultOption.textContent = 'Loading barangays...';
                
                // Fetch barangays for selected city
                const response = await fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`);
                const barangays = await response.json();
                
                // Populate barangay dropdown
                compilerBarangay.innerHTML = '<option value="">All Barangays</option>';
                if (Array.isArray(barangays)) {
                    barangays.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.barangay;
                        option.textContent = barangay.barangay;
                        compilerBarangay.appendChild(option);
                    });
                }
                
                compilerBarangay.disabled = false;
            } catch (error) {
                console.error('Error loading barangays:', error);
                compilerBarangay.innerHTML = '<option value="">Error loading barangays</option>';
            }
        }
    });
});
</script>
</body>
</html>