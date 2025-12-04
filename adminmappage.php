<?php
    session_start();
    include 'database.php';

    if(isset($_SESSION["accessrole"])){
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
        
        if(isset($_SESSION["name"])) $email = $_SESSION["name"];
        if(isset($_SESSION["accessrole"])) $accessrole = $_SESSION["accessrole"];
    } else {
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
    <title>Mangrove Map</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminmappage.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>

    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
    <script src="area-manager.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
    <script>
        // Pass PHP session data to JavaScript
        window.currentUser = {
            user_id: '<?php echo $_SESSION["user_id"] ?? ""; ?>',
            name: '<?php echo $_SESSION["name"] ?? ""; ?>',
            role: '<?php echo $_SESSION["accessrole"] ?? ""; ?>',
            city: '<?php echo $_SESSION["city_municipality"] ?? ""; ?>'
        };
    </script>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
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
    <main class="map-container">
        <div class="map-box">
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
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
        </div>
        <!-- map for mangrove areas in Bataan -->
        <div id="map"></div>
        <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"): ?>
        <div id="areaControlPanel" class="area-controls">
                <div class="btn-group-vertical">
                    <button id="addAreaBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-draw-polygon"></i> Add New Area
                    </button>
                    <button id="editAreasBtn" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit Areas
                    </button>
                    <button id="deleteAreaBtn" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <button id="saveAreasBtn" class="btn btn-success btn-sm">
                        <i class="fas fa-save"></i> Save All Areas
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
<div class="adminmap-main">
    <div class="map-header">
        <h1 class="map-heading">Mangrove Monitoring Dashboard</h1>
        <div class="map-subtitle">Real-time tracking and management of Bataan's mangrove ecosystems</div>
    </div>

<!-- Total Coverage Card -->
    <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-map-marked-alt"></i>
            </div>
            <div class="stat-content">
                <h3>Total Mangrove Coverage</h3>
                <div class="stat-value" id="total-area">Loading...</div>
                <div class="stat-description">Across all protected zones</div>
                <div id="user-coverage" class="user-stat"></div>
            </div>
        </div>
    <!-- Statistics Dashboard -->
    <div class="stats-container">
        <!-- Species Distribution Card -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-tree"></i>
            </div>
            <div class="stat-content">
                <h3>Species Distribution</h3>
                <div class="chart-container">
                    <canvas id="speciesChart"></canvas>
                </div>
                <div id="speciesLegend" class="chart-legend"></div>
                <div class="stat-description">Mangrove species count</div>
            </div>
        </div>

        <!-- Municipality Coverage Card -->
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-city"></i>
            </div>
            <div class="stat-content">
                <h3>Municipality Coverage</h3>
                <div class="chart-container">
                    <canvas id="municipalityChart"></canvas>
                </div>
                <div id="remainingMunicipalities" class="remaining-municipalities"></div>
                <div class="stat-description">Hectares by location (Top 5 shown)</div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics Section -->
    <div class="detailed-stats">
        <div class="stats-section">
            <h2><i class="fas fa-chart-pie"></i> Mangrove Analytics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Total Trees Mapped</div>
                    <div class="stat-figure" id="total-trees">0</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Municipalities</div>
                    <div class="stat-figure" id="total-municipalities">0</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-section">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="action-buttons">
                <button class="action-btn" onclick="window.location.href='adminreportpage.php'">
                    <i class="fa fa-file-text"></i> View Reports
                </button>
                <button class="action-btn" onclick="loadMangroveStatistics()">
                    <i class="fa fa-sync-alt"></i> Refresh Stats
                </button>
            </div>
        </div>
    </div>

    <div class="activity-log-section">
            <h2><i class="fas fa-history"></i> Activity Log</h2>
            
            <!-- Filter Controls -->
            <div class="log-filters">
                <div class="filter-group">
                    <label for="actionTypeFilter">Action Type:</label>
                    <select id="actionTypeFilter" class="form-control">
                        <option value="all">All Actions</option>
                        <option value="add_area">Area Added</option>
                        <option value="edit_area_details">Area Edited</option>
                        <option value="delete_area">Area Deleted</option>
                        <option value="merge_area">Areas Merged</option>
                        <option value="expand_area">Area Expanded</option>
                        <option value="save_changes">Changes Saved</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dateFilter">Date Range:</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                <button id="refreshLogs" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Log Table -->
            <div class="log-table-container">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Area No</th>
                            <th>AreaNo/Municipality</th>
                            <th>Details</th>
                            <th>Action By</th>
                        </tr>
                    </thead>
                    <tbody id="logEntries">
                        <!-- Logs will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="log-pagination">
                <button id="prevPage" class="btn btn-secondary" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span id="pageInfo">Page 1 of 1</span>
                <button id="nextPage" class="btn btn-secondary" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
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
            <p class="credits">
                Data Sources: © Global Mangrove Watch, © OpenStreetMap contributors, © MapTiler
            </p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js" defer></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" defer></script>
    <script type = "text/javascript">
        function selectcitymunicipality(citymunicipality){
            console.log(Barangay);
        }
    </script>
    <script>
        //default map
        var map = L.map('map').setView([14.64852, 120.47318], 11.2);
        //attribution credits
        map.attributionControl.addAttribution('Mangrove data © <a href="https://www.globalmangrovewatch.org/" target="_blank">Global Mangrove Watch</a>');
        map.attributionControl.addAttribution('<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>');
        map.attributionControl.addAttribution('&copy; Stadia Maps, Stamen Design, OpenMapTiles & OSM contributors');
        map.attributionControl.addAttribution('DENR-PhilSA Mangrove Map V2 (DENR & PhilSA, 2024)');

        //map layering arrangement for mangrove areas
        map.createPane('cmPane').style.zIndex = 200;
        map.createPane('exmangrovePane').style.zIndex = 400;
        map.createPane('mangrovePane').style.zIndex = 500; 
        map.createPane('treePane').style.zIndex = 600;
        map.createPane('popupPane').style.zIndex = 1000;
        
        //registered city municipalities layer group - fetch but don't display
        var cmlayer = L.layerGroup({pane:'cmPane'});
        
        //toggles checkbox for mangrove areas
        var mangrovelayer = L.layerGroup({pane:'exmangrovePane'}).addTo(map);
        var extendedmangrovelayer = L.layerGroup({pane:'mangrovePane'}).addTo(map);
        // toogles checkbox for trees
        var treelayer = L.layerGroup({ pane: 'treePane' }).addTo(map);
        
        // Base maps
        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
        }).addTo(map);

        //city municipality fetch data but don't display
        fetch('bataancm1.geojson')
            .then(response => response.json())
            .then(data => {
                // Just store the data, don't add to map
                window.cityMunicipalityData = data;
            })
            .catch(error => {
                console.error('Error loading city/municipality data:', error);
            });

        // Initialize marker store
    const markerStore = {};

    // Define a custom icon for mangrove pins
    const customMangroveIcon = L.icon({
        iconUrl: 'images/mangrow-logo-pin.png', 
        iconSize: [20, 32],
        iconAnchor: [16, 32], 
        popupAnchor: [0, -32] 
    });

    // Define a style for the tree areas
        const treeAreaStyle = {
            color: '#FFA500', // Orange color
            weight: 2,
            opacity: 1,
            fillColor: '#FFA500',
            fillOpacity: 0.3
        };

        // Function to convert meters to degrees at a given latitude
        function metersToDegrees(lat, meters) {
            const earthRadius = 6378137; // meters
            const dLat = meters / earthRadius;
            const dLng = meters / (earthRadius * Math.cos(Math.PI * lat / 180));
            return {
                latOffset: dLat * (180 / Math.PI),
                lngOffset: dLng * (180 / Math.PI)
            };
        }

        // Function to create a 100 sqm square around a point (10m x 10m)
        function createTreeArea(latlng) {
            // 10 meters in each direction from the center
            const { latOffset, lngOffset } = metersToDegrees(latlng.lat, 5); // 5m each way = 10m total
            return L.rectangle([
                [latlng.lat - latOffset, latlng.lng - lngOffset],
                [latlng.lat + latOffset, latlng.lng + lngOffset]
            ], treeAreaStyle);
        }

        // Modified fetch script with tree areas
        fetch('mangrovetrees.json')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    pointToLayer: function(feature, latlng) {
                        // Create marker with custom icon and store reference
                        const marker = L.marker(latlng, { icon: customMangroveIcon });
                        markerStore[feature.properties.mangrove_id] = marker;
                        
                        // Create and store the tree area
                        const treeArea = createTreeArea(latlng);
                        treeAreaStore[feature.properties.mangrove_id] = treeArea;
                        treeArea.addTo(treelayer);
                        
                        return marker;
                    },
                    style: function(feature) {
                        return {
                            color: '#228B22',
                            weight: 2,
                            opacity: 1,
                            fillColor: '#32CD32',
                            fillOpacity: 0.6
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        // Store the feature data on the layer
                        layer.feature = feature;
                        
                        layer.bindPopup(`
                            <div class="marker-popup">
                                <h4>Tree Details</h4>
                                <table>
                                    <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                                        <td>${feature.properties.mangrove_id}</td></tr>
                                    <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                        <td>${feature.geometry.coordinates[1].toFixed(5)}, ${feature.geometry.coordinates[0].toFixed(5)}</td></tr>
                                    <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                                        <td>${feature.properties.area_no}</td></tr>
                                    <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                                        <td>${feature.properties.mangrove_type}</td></tr>
                                    <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                                        <td><span class="status-${feature.properties.status.toLowerCase()}">${feature.properties.status}</span></td></tr>
                                </table>
                                <div class="marker-actions" style="margin-top:0.8rem;">
                                    <button class="btn btn-warning btn-sm" onclick="editMarker('${feature.properties.mangrove_id}')">Edit</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteMarker('${feature.properties.mangrove_id}')">Delete</button>
                                </div>
                            </div>
                        `);
                    }
                }).addTo(treelayer);
            })
            .catch(error => {
                console.error('Error fetching mangrovetrees.json:', error);
            });


        const areaStore = {};
        // Extended mangrove area fetch data - simplified version
        // Extended mangrove area fetch data with area calculation (with turf.js)
        fetch('mangroveareas.json')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (!data.features || data.features.length === 0) {
                    console.log("No mangrove areas found");
                    return;
                }

                // Process each feature
                data.features.forEach(feature => {
                    const area = turf.area(feature);
                    feature.properties.area_m2 = Math.round(area);
                    feature.properties.area_ha = (area / 10000).toFixed(2);
                    
                    // Format dates
                    if (feature.properties.date_created) {
                        feature.properties.date_created_display = new Date(feature.properties.date_created).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila'
                        });
                    }
                    if (feature.properties.date_updated) {
                        feature.properties.date_updated_display = new Date(feature.properties.date_updated).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila'
                        });
                    }
                });

                // Create GeoJSON layer with improved popup handling
                const mangroveLayer = L.geoJSON(data, {
                    pane: 'mangrovePane',
                    style: {
                        fillColor: '#3d9970',
                        weight: 1,
                        opacity: 1,
                        color: '#2d7561',
                        fillOpacity: 0.5
                    },
                    onEachFeature: (feature, layer) => {
                        // Store the layer reference in our area store
                        if (feature.properties.id) {
                            areaStore[feature.properties.id] = layer;
                        } else {
                            // Generate an ID if one doesn't exist
                            const tempId = 'temp_' + Math.random().toString(36).substr(2, 9);
                            feature.properties.id = tempId;
                            areaStore[tempId] = layer;
                        }
                        
                        // Store the original feature data on the layer
                        layer.feature = feature;

                        // Create popup content
                        const popupContent = `
                            <div class="mangrove-popup">
                                <h4>${feature.properties.area_no || 'Mangrove Area'}</h4>
                                <table>
                                    <tr><th>Location:</th><td>${feature.properties.city_municipality || 'N/A'}</td></tr>
                                    <tr><th>Size:</th><td>${feature.properties.area_m2?.toLocaleString() || 'N/A'} m²</td></tr>
                                    <tr><th>Created:</th><td>${feature.properties.date_created_display || 'N/A'}</td></tr>
                                    <tr><th>Updated:</th><td>${feature.properties.date_updated_display || 'N/A'}</td></tr>
                                </table>
                                <div class="popup-actions">
                                    <button class="btn btn-sm btn-primary edit-area" 
                                            data-id="${feature.properties.id}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-area" 
                                            data-id="${feature.properties.id}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        `;

                        // Bind popup with proper options
                        layer.bindPopup(popupContent, {
                            className: 'mangrove-popup',
                            maxWidth: 400,
                            minWidth: 300
                        });

                        // Click handling
                        layer.on('click', function(e) {
                            if (!document.getElementById('addMarkerCheckbox')?.checked) {
                                this.openPopup();
                            }
                        });

                        // Hover effects
                        layer.on({
                            mouseover: function() {
                                this.setStyle({
                                    weight: 3,
                                    color: '#666',
                                    fillOpacity: 0.7
                                });
                            },
                            mouseout: function() {
                                this.setStyle({
                                    weight: 1,
                                    color: '#2d7561',
                                    fillOpacity: 0.5
                                });
                            }
                        });
                    }
                }).addTo(extendedmangrovelayer);

                console.log(`Loaded ${data.features.length} mangrove areas`);
            })
            .catch(error => {
                console.error('Error loading mangrove areas:', error);
            });

            function editArea(areaId) {
                if (!window.areaManager) {
                    console.error("AreaManager not initialized");
                    return;
                }

                const areaLayer = areaManager.areaStore[areaId];
                if (!areaLayer) {
                    alert("Area not found!");
                    return;
                }

                // Enter edit mode and select the area
                if (!areaManager.editMode) {
                    areaManager.toggleEditMode();
                }
                areaManager.selectArea(areaLayer, areaLayer.feature);
            }

            function deleteArea(areaId) {
                if (!window.areaManager) {
                    console.error("AreaManager not initialized");
                    return;
                }

                if (!confirm("Are you sure you want to delete this area?")) return;

                const areaLayer = areaManager.areaStore[areaId];
                if (!areaLayer) {
                    alert("Area not found!");
                    return;
                }

                // Remove from areas array
                areaManager.areas.features = areaManager.areas.features.filter(
                    area => area.properties.id !== areaId
                );

                // Remove from map and store
                areaLayer.remove();
                delete areaManager.areaStore[areaId];

                // If this was the currently selected area, clear selection
                if (areaManager.currentArea && areaManager.currentArea.feature.properties.id === areaId) {
                    areaManager.currentArea = null;
                    areaManager.drawnItems.clearLayers();
                }

                //alert("Area deleted successfully!");
            }

        /*/mangrove area fetch data
        fetch('mangrove2020.json')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    style: function(feature){
                        return{
                            color:'#000000',
                            weight: 2,
                            opacity: 1,
                            fillColor: 'azure',
                            fillOpacity: 0.5
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        layer.bindPopup(`<b>${feature.properties.Area}</b>`);
                        layer.on({
                            mouseover: function(e) {
                                this.openPopup();
                            },
                            mouseout: function(e) {
                                this.closePopup();
                            }
                        });
                    }
                }).addTo(mangrovelayer);
            });  */ 
        
                const legend = L.control({position: 'bottomright'});
                    legend.onAdd = function(map) {
                        const div = L.DomUtil.create('div', 'info legend');
                        div.innerHTML = `
                            <h4>Legend</h4>
                            <div>
                                <span style="display:inline-block;width:14px;height:14px;background:#3d9970;border-radius:3px;vertical-align:middle;margin-right:4px;"></span>
                                <span style="color:#3d9970;">Mangrove Area</span>
                            </div>
                            <div>
                                <span style="display:inline-block;width:14px;height:14px;background:#FFA500;border-radius:3px;vertical-align:middle;margin-right:4px;"></span>
                                <span style="color:#FFA500;">Tree Area (100sq m)</span>
                            </div>
                            <div>
                                <img src="images/mangrow-logo-pin.png" alt="Tree" style="width:16px;vertical-align:middle;margin-right:4px;">
                                <span style="color:#228B22;">Mangrove Trees</span>
                            </div>
                            <hr>
                            <div>
                                <span style="font-size:12px;">1 m² = 0.0001 ha</span><br>
                                <span style="font-size:12px;">1 ha = 10,000 m²</span>
                            </div>
                        `;
                        return div;
                    };
                    legend.addTo(map);            

        var SatelliteStreets = L.tileLayer('https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
            attribution: '',
            maxZoom: 20
        });

        var EsriStreets = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            maxZoom: 20
        });

        var StamenWatercolor = L.tileLayer('https://tiles.stadiamaps.com/tiles/stamen_watercolor/{z}/{x}/{y}.{ext}', {
            minZoom: 1,
            maxZoom: 16,
            attribution: '',
            ext: 'jpg'
        });

        var StamenTerrain = L.tileLayer('https://tiles.stadiamaps.com/tiles/stamen_terrain/{z}/{x}/{y}{r}.{ext}', {
            minZoom: 0,
            maxZoom: 16,
            attribution: '',
            ext: 'png'
        });

        // Layer control
        var baseMaps = {
            'Default': osm,
            'Satellite Streets': SatelliteStreets,
            'Esri Map': EsriStreets,
            'Water Color': StamenWatercolor,
            'Terrain': StamenTerrain
        };

        var overlayMaps = {
            'Mangrove Areas': extendedmangrovelayer,  // Make sure this is first
            'Mangrove Trees': treelayer
        };

        // Add layer control
        var layerControl = L.control.layers(baseMaps, overlayMaps, {}).addTo(map);

        // Add default layers
        osm.addTo(map);

        //L.control.locate().addTo(map);

    // Add a checkbox to enable/disable marker addition
    var addMarkerCheckbox = L.DomUtil.create('div', 'leaflet-control-layers-checkbox');
    addMarkerCheckbox.innerHTML = `
        <label style="padding: 5px; cursor: pointer; display: flex; align-items: center;">
            <input type="checkbox" id="addMarkerCheckbox" style="margin-right: 5px;">
            <i class="fas fa-tree" style="color: green; margin-right: 5px;"></i> Pin a Tree
        </label>`;

    // Append the checkbox to the layer control
    var layerControlContainer = layerControl.getContainer();
    layerControlContainer.appendChild(addMarkerCheckbox);

    // Prevent map interactions when interacting with the checkbox
    L.DomEvent.disableClickPropagation(addMarkerCheckbox);

    // Add event listener to the checkbox
    var markerAdditionEnabled = false;
    const markerCheckbox = document.getElementById('addMarkerCheckbox');
    if (markerCheckbox) {
        markerCheckbox.addEventListener('change', function(e) {
            markerAdditionEnabled = e.target.checked;

            // Show/hide boundary layer when checkbox is toggled
            if (window.areaManager && window.areaManager.boundaryLayer) {
                if (e.target.checked) {
                    window.areaManager.boundaryLayer.addTo(map);
                    // Optionally zoom to the boundary
                    map.fitBounds(window.areaManager.boundaryLayer.getBounds());
                } else {
                    window.areaManager.boundaryLayer.remove();
                }
            }

            if (markerAdditionEnabled) {
                map.on('click', onMapClick);
            } else {
                map.off('click', onMapClick);
            }
        });
    }

    function onMapClick(e) {
        // Check if location is allowed for this user
        if (window.areaManager && !window.areaManager.isLocationAllowed(e.latlng)) {
            return;
        }
        var formDiv = L.DomUtil.create('div', 'marker-form');
        formDiv.innerHTML = `
            <h3>Add Mangrove Marker</h3>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="areaNo" class="form-control">
            </div>
            <div class="form-group">
                <label>Mangrove Type:</label>
                <select id="mangroveType" class="form-control">
                    <option value="Rhizophora apiculata">Bakawan lalake</option>
                    <option value="Rhizophora mucronata">Bakawan babae</option>
                    <option value="Avicennia marina">Bungalon</option>
                    <option value="Sonneratia alba">Palapat</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="status" class="form-control">
                    <option value="Healthy">Alive</option>
                    <option value="Growing">Growing</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Dead">Dead</option>
                </select>
            </div>
            <div class="form-buttons">
                <button id="saveMarker" class="btn btn-primary">Save</button>
                <button id="cancelMarker" class="btn btn-secondary">Cancel</button>
            </div>
        `;
        
        var popup = L.popup()
            .setLatLng(e.latlng)
            .setContent(formDiv)
            .openOn(map);
        
        L.DomEvent.on(formDiv.querySelector('#saveMarker'), 'click', async function() {
            var areaNo = formDiv.querySelector('#areaNo').value;
            var mangroveType = formDiv.querySelector('#mangroveType').value;
            var status = formDiv.querySelector('#status').value;
            
            map.closePopup();
            
            try {
                // Save marker first
                const saveResponse = await fetch('save_marker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        latitude: e.latlng.lat,
                        longitude: e.latlng.lng,
                        area_no: areaNo,
                        mangrove_type: mangroveType,
                        status: status
                    })
                });
                
                const saveData = await saveResponse.json();
                
                if (saveData.success) {
                    // Create marker with custom icon
                    const newMarker = L.marker(e.latlng, { icon: customMangroveIcon }).addTo(treelayer);

                    // Create and add the 100 sqm orange square area
                    const treeArea = createTreeArea(e.latlng);
                    treeArea.addTo(treelayer);

                    // Store in the marker store (flat, not nested)
                    markerStore[saveData.mangrove_id] = newMarker;

                    // Store in the tree area store
                    treeAreaStore[saveData.mangrove_id] = treeArea;

                    // Set feature data
                    newMarker.feature = {
                        type: 'Feature',
                        properties: {
                            mangrove_id: saveData.mangrove_id,
                            area_no: areaNo,
                            mangrove_type: mangroveType,
                            status: status,
                            date_added: new Date().toISOString()
                        },
                        geometry: {
                            type: 'Point',
                            coordinates: [e.latlng.lng, e.latlng.lat]
                        }
                    };

                    newMarker.bindPopup(`
                        <div class="marker-popup">
                            <h4>Tree Details</h4>
                            <table>
                                <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                                    <td>${saveData.mangrove_id}</td></tr>
                                <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                    <td>${e.latlng.lat.toFixed(5)}, ${e.latlng.lng.toFixed(5)}</td></tr>
                                <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                                    <td>${areaNo}</td></tr>
                                <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                                    <td>${mangroveType}</td></tr>
                                <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                                    <td><span class="status-${status.toLowerCase()}">${status}</span></td></tr>
                            </table>
                            <div class="marker-actions" style="margin-top:0.8rem;">
                                ${areaManager.isPointInUserBoundary(e.latlng) ? `
                                <button class="btn btn-warning btn-sm" onclick="editMarker('${saveData.mangrove_id}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteMarker('${saveData.mangrove_id}')">Delete</button>
                                ` : `
                                <div class="text-muted">Editing restricted to your city/municipality</div>
                                `}
                            </div>
                        </div>
                    `);

                    // Log the activity after successful marker creation
                    try {
                        const formData = new FormData();
                        formData.append('area_no', areaNo);
                        formData.append('id', saveData.mangrove_id);
                        formData.append('action_type', 'add_pin');
                        formData.append('city_municipality', areaNo);
                        formData.append('details', `Added new mangrove pin (ID: ${saveData.mangrove_id}) in area ${areaNo}`);

                        const activityResponse = await fetch('log_activity.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await activityResponse.json();

                        if (result.status === 'success') {
                            console.log('Activity logged successfully');
                        } else {
                            console.error('Failed to log activity:', result.message);
                            if (result.debug_info) {
                                console.error('Debug info:', result.debug_info);
                            }
                            // Optionally show to user
                            alert(`Failed to log activity: ${result.message}`);
                        }
                        try {
                            const expanded = await areaManager.expandAreaForTree(e.latlng);
                            if (expanded) {
                                console.log("Tree area created/merged successfully:", {
                                    coordinates: e.latlng,
                                    area_no: areaNo,
                                    mangrove_id: saveData.mangrove_id,
                                    timestamp: new Date().toISOString()
                                });
                            }
                        } catch (error) {
                            console.error("Area expansion error:", {
                                error: error.message,
                                coordinates: e.latlng,
                                area_no: areaNo,
                                mangrove_id: saveData.mangrove_id,
                                stack: error.stack,
                                timestamp: new Date().toISOString()
                            });
                        }
                    } catch (error) {
                        console.error('Network error while logging activity:', error);
                        alert('Network error occurred while logging activity');
                    }
                } else {
                    throw new Error(saveData.message || 'Failed to save marker');
                }
            } catch (error) {
                console.error("Error:", error);
                alert("Operation failed: " + error.message);
            }
        });
        
        L.DomEvent.on(formDiv.querySelector('#cancelMarker'), 'click', function() {
            map.closePopup();
        });
    }

    // Initialize tree area store
    const treeAreaStore = {};

    // Helper: create a 100 sqm (10m x 10m) rectangle around a point
    function getTreeAreaBounds(latlng) {
        const { latOffset, lngOffset } = metersToDegrees(latlng.lat, 5); // 5m each way = 10m total
        return [
            [latlng.lat - latOffset, latlng.lng - lngOffset],
            [latlng.lat + latOffset, latlng.lng + lngOffset]
        ];
    }

    // Modified editMarker function
    function editMarker(mangroveId) {
        const markerToEdit = markerStore[mangroveId];
        const areaToEdit = treeAreaStore[mangroveId];
        if (!markerToEdit) {
            alert("Marker not found!");
            return;
        }
        
        // Check permissions
        const latlng = markerToEdit.getLatLng();
        if (window.areaManager && !window.areaManager.isPointInUserBoundary(latlng)) {
            alert("You are not authorized to edit markers outside your city/municipality.");
            return;
        }

        // Enable dragging for the marker
        markerToEdit.dragging.enable();

        // Update the area position when marker is dragged
        markerToEdit.on('drag', function(e) {
            const newLatLng = e.target.getLatLng();
            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(newLatLng));
        });

        // Create a form div for editing
        const formDiv = L.DomUtil.create('div', 'marker-form');
        formDiv.innerHTML = `
            <h3>Edit Mangrove Marker</h3>
            <div class="form-group">
                <label>Mangrove ID:</label>
                <input type="text" class="form-control" value="${markerToEdit.feature.properties.mangrove_id}" readonly>
            </div>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="editAreaNo" class="form-control" value="${markerToEdit.feature.properties.area_no}">
            </div>
            <div class="form-group">
                <label>Mangrove Type:</label>
                <select id="editMangroveType" class="form-control">
                    <option value="Rhizophora apiculata" ${markerToEdit.feature.properties.mangrove_type === "Rhizophora apiculata" ? "selected" : ""}>Bakawan lalake</option>
                    <option value="Rhizophora mucronata" ${markerToEdit.feature.properties.mangrove_type === "Rhizophora mucronata" ? "selected" : ""}>Bakawan babae</option>
                    <option value="Avicennia marina" ${markerToEdit.feature.properties.mangrove_type === "Avicennia marina" ? "selected" : ""}>Bungalon</option>
                    <option value="Sonneratia alba" ${markerToEdit.feature.properties.mangrove_type === "Sonneratia alba" ? "selected" : ""}>Palapat</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="editStatus" class="form-control">
                    <option value="Healthy" ${markerToEdit.feature.properties.status === "Healthy" ? "selected" : ""}>Alive</option>
                    <option value="Growing" ${markerToEdit.feature.properties.status === "Growing" ? "selected" : ""}>Growing</option>
                    <option value="Damaged" ${markerToEdit.feature.properties.status === "Damaged" ? "selected" : ""}>Damaged</option>
                    <option value="Dead" ${markerToEdit.feature.properties.status === "Dead" ? "selected" : ""}>Dead</option>
                </select>
            </div>
            <div class="form-buttons">
                <button id="saveEditMarker" class="btn btn-primary">Save</button>
                <button id="cancelEditMarker" class="btn btn-secondary">Cancel</button>
            </div>
        `;

        // Create a popup with the form
        const popup = L.popup()
            .setLatLng(markerToEdit.getLatLng())
            .setContent(formDiv)
            .openOn(map);

        // Handle save button click
        L.DomEvent.on(formDiv.querySelector('#saveEditMarker'), 'click', function() {
            const updatedAreaNo = formDiv.querySelector('#editAreaNo').value;
            const updatedMangroveType = formDiv.querySelector('#editMangroveType').value;
            const updatedStatus = formDiv.querySelector('#editStatus').value;
            const newLatLng = markerToEdit.getLatLng();

            // Update marker properties
            markerToEdit.feature.properties.area_no = updatedAreaNo;
            markerToEdit.feature.properties.mangrove_type = updatedMangroveType;
            markerToEdit.feature.properties.status = updatedStatus;

            // Update marker popup
            markerToEdit.setPopupContent(`
                <div class="marker-popup">
                    <h4>Tree Details</h4>
                    <table>
                        <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                            <td>${markerToEdit.feature.properties.mangrove_id}</td></tr>
                        <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                            <td>${newLatLng.lat.toFixed(5)}, ${newLatLng.lng.toFixed(5)}</td></tr>
                        <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                            <td>${updatedAreaNo}</td></tr>
                        <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                            <td>${updatedMangroveType}</td></tr>
                        <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                            <td><span class="status-${updatedStatus.toLowerCase()}">${updatedStatus}</span></td></tr>
                    </table>
                    <div class="marker-actions" style="margin-top:0.8rem;">
                        <button class="btn btn-warning btn-sm" onclick="editMarker('${markerToEdit.feature.properties.mangrove_id}')">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteMarker('${markerToEdit.feature.properties.mangrove_id}')">Delete</button>
                    </div>
                </div>
            `);

            // Save updated data to the server
            fetch('update_marker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    mangrove_id: markerToEdit.feature.properties.mangrove_id,
                    latitude: newLatLng.lat,
                    longitude: newLatLng.lng,
                    area_no: updatedAreaNo,
                    mangrove_type: updatedMangroveType,
                    status: updatedStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Log the edit activity
                    const formData = new FormData();
                    formData.append('area_no', updatedAreaNo);
                    formData.append('id', markerToEdit.feature.properties.mangrove_id);
                    formData.append('action_type', 'edit_pin');
                    formData.append('city_municipality', updatedAreaNo);
                    formData.append('details', `Edited mangrove pin (ID: ${markerToEdit.feature.properties.mangrove_id}) in area ${updatedAreaNo}`);

                    fetch('log_activity.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status !== 'success') {
                            console.error('Failed to log edit activity:', result.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error logging edit activity:', error);
                    });

                    alert("Marker updated successfully!");
                } else {
                    alert("Failed to update marker: " + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error("Error updating marker:", error);
            });

            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(newLatLng));

            // Disable dragging and close popup
            markerToEdit.dragging.disable();
            markerToEdit.off('drag');
            map.closePopup();
        });

        // Handle cancel button click
        L.DomEvent.on(formDiv.querySelector('#cancelEditMarker'), 'click', function() {
            // Reset marker position to original
            markerToEdit.setLatLng(markerToEdit.feature._latlng || markerToEdit.getLatLng());
            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(markerToEdit.getLatLng()));
            
            markerToEdit.dragging.disable();
            markerToEdit.off('drag');
            map.closePopup();
        });
    }

    // Modified deleteMarker function
    async function deleteMarker(mangroveId) {
        if (!confirm("Are you sure you want to delete this marker and update the mangrove area?")) return;

        // Find the marker and its area in our stores
        const markerToDelete = markerStore[mangroveId];
        const treeAreaToDelete = treeAreaStore[mangroveId];
        
        if (!markerToDelete || !treeAreaToDelete) {
            alert("Marker or area not found!");
            return;
        }

        const latlng = markerToDelete.getLatLng();
        if (window.areaManager && !window.areaManager.isPointInUserBoundary(latlng)) {
            alert("You are not authorized to delete markers outside your city/municipality.");
            return;
        }

        try {
            // Get the pin's location and create a temporary area for subtraction
            const pinLatLng = markerToDelete.getLatLng();
            
            // Create a 105 sqm area around the pin (10.25m x 10.25m)
            // 105 sqm = side^2 => side = sqrt(105) ≈ 10.247m, so half-side ≈ 5.1235m
            const halfSide = Math.sqrt(105) / 2; // ≈ 5.1235 meters
            const { latOffset, lngOffset } = metersToDegrees(pinLatLng.lat, halfSide);
            const pinBounds = [
                [pinLatLng.lat - latOffset, pinLatLng.lng - lngOffset],
                [pinLatLng.lat + latOffset, pinLatLng.lng + lngOffset]
            ];
            
            // Create a GeoJSON representation of the pin area
            const pinAreaGeoJSON = {
                type: "Polygon",
                coordinates: [[
                    [pinBounds[0][1], pinBounds[0][0]], // SW
                    [pinBounds[0][1], pinBounds[1][0]], // NW
                    [pinBounds[1][1], pinBounds[1][0]], // NE
                    [pinBounds[1][1], pinBounds[0][0]], // SE
                    [pinBounds[0][1], pinBounds[0][0]]  // SW (close)
                ]]
            };

            // Find intersecting mangrove areas
            const intersectingAreas = areaManager.findIntersectingAreas(pinAreaGeoJSON);
            
            if (intersectingAreas.length > 0) {
                // Subtract the pin area from each intersecting mangrove area
                for (const area of intersectingAreas) {
                    await areaManager.subtractAreaFromMangrove(pinAreaGeoJSON, area);
                }
            }

            // Now delete the marker and its tree area
            const deleteResponse = await fetch('delete_marker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mangrove_id: mangroveId })
            });
            
            const deleteData = await deleteResponse.json();
            
            if (deleteData.success) {
                // Log the delete activity
                const formData = new FormData();
                formData.append('area_no', markerToDelete.feature.properties.area_no);
                formData.append('id', mangroveId);
                formData.append('action_type', 'delete_pin');
                formData.append('city_municipality', markerToDelete.feature.properties.area_no);
                formData.append('details', `Deleted mangrove pin (ID: ${mangroveId}) from area ${markerToDelete.feature.properties.area_no}`);

                fetch('log_activity.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status !== 'success') {
                        console.error('Failed to log delete activity:', result.message);
                    }
                })
                .catch(error => {
                    console.error('Error logging delete activity:', error);
                });

                // Remove marker and area from map and stores
                markerToDelete.remove();
                treeAreaToDelete.remove();
                delete markerStore[mangroveId];
                delete treeAreaStore[mangroveId];
                
                // Save the updated mangrove areas
                await areaManager.saveAreas();
                
                // Re-render the areas to show changes
                areaManager.renderAreas();
                
                alert("Marker deleted and mangrove areas updated successfully!");
            } else {
                throw new Error(deleteData.message || 'Failed to delete marker');
            }
        } catch (error) {
            console.error("Error deleting marker:", error);
            alert("Error deleting marker: " + error.message);
        }
    }
    <!-- update mangrove areas json script (to database) -->
function loadChartJS() {
    return new Promise((resolve, reject) => {
        if (typeof Chart !== 'undefined') {
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// Fetch and calculate statistics
async function loadMangroveStatistics() {
    try {
        // Load required libraries
        await loadChartJS();
        
        // Fetch all data
        const [treesData, areasData, cmData] = await Promise.all([
            fetch('mangrovetrees.json').then(r => r.json()),
            fetch('mangroveareas.json').then(r => r.json()),
            fetch('bataancm1.geojson').then(r => r.json())
        ]);

        // Calculate statistics
        const stats = {
            totalTrees: treesData.features.length,
            totalAreas: areasData.features.length,
            totalMunicipalities: cmData.features.length,
            totalCoverage: 0,
            speciesDistribution: {},
            municipalityDistribution: {}
        };

        // Calculate species distribution
        treesData.features.forEach(tree => {
            const species = tree.properties.mangrove_type || 'Unknown';
            stats.speciesDistribution[species] = (stats.speciesDistribution[species] || 0) + 1;
        });

        // Calculate municipality distribution
        areasData.features.forEach(area => {
            const municipality = area.properties.city_municipality || 'Unknown';
            const areaSize = turf.area(area) / 10000; // in hectares
            stats.municipalityDistribution[municipality] = (stats.municipalityDistribution[municipality] || 0) + areaSize;
        });

        // Update UI
        document.getElementById('total-trees').textContent = stats.totalTrees.toLocaleString();
        document.getElementById('total-municipalities').textContent = stats.totalMunicipalities.toLocaleString();

        // Create charts
        createSpeciesChart(stats.speciesDistribution);
        createMunicipalityChart(stats.municipalityDistribution);

        // Update user coverage if available
        if (window.currentUser && window.currentUser.city) {
            const userCoverage = stats.municipalityDistribution[window.currentUser.city] || 0;
            document.getElementById('user-coverage').textContent = `${userCoverage.toFixed(2)} ha in ${window.currentUser.city}`;
        }

    } catch (error) {
        console.error('Error loading statistics:', error);
        document.getElementById('total-trees').textContent = 'Error';
        document.getElementById('total-municipalities').textContent = 'Error';
    }
}

// Create species distribution pie chart
function createSpeciesChart(distribution) {
    const ctx = document.getElementById('speciesChart').getContext('2d');
    const labels = Object.keys(distribution);
    const data = Object.values(distribution);
    const backgroundColors = [
        '#3d9970', '#2d7561', '#5a7d6a', '#8fbc8f', 
        '#20b2aa', '#3cb371', '#2e8b57', '#228b22'
    ];

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Create custom legend
    const legendContainer = document.getElementById('speciesLegend');
    legendContainer.innerHTML = '';
    
    labels.forEach((label, i) => {
        const legendItem = document.createElement('div');
        legendItem.className = 'legend-item';
        legendItem.innerHTML = `
            <span class="legend-color" style="background: ${backgroundColors[i]}"></span>
            ${label}: ${data[i]} (${((data[i] / data.reduce((a,b) => a + b, 0)) * 100).toFixed(1)}%)
        `;
        legendContainer.appendChild(legendItem);
    });
}

// Create municipality distribution bar chart
function createMunicipalityChart(distribution) {
    const ctx = document.getElementById('municipalityChart').getContext('2d');
    const remainingContainer = document.getElementById('remainingMunicipalities');
    
    // Convert distribution to array and sort by value (descending)
    const sorted = Object.entries(distribution)
        .sort((a, b) => b[1] - a[1]);
    
    // Separate top 5 and remaining
    const top5 = sorted.slice(0, 5);
    const remaining = sorted.slice(5);
    
    // Create horizontal bar chart for top 5
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: top5.map(item => item[0]),
            datasets: [{
                label: 'Area (ha)',
                data: top5.map(item => item[1]),
                backgroundColor: '#3d9970',
                borderColor: '#2d7561',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y', // This makes the chart horizontal
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hectares'
                    }
                },
                y: {
                    title: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Display remaining municipalities as a list
    if (remaining.length > 0) {
        remainingContainer.innerHTML = `
            <div class="remaining-title">Other Municipalities:</div>
            <ul class="remaining-list">
                ${remaining.map(item => `
                    <li>
                        <span class="municipality-name">${item[0]}</span>
                        <span class="municipality-value">${item[1].toFixed(2)} ha</span>
                    </li>
                `).join('')}
            </ul>
        `;
    } else {
        remainingContainer.innerHTML = '';
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    loadMangroveStatistics();
});

let currentPage = 1;
const logsPerPage = 10;
let totalLogs = 0;
let allLogs = []; // Initialize as empty array

// Function to fetch logs from server
async function fetchActivityLogs() {
    try {
        const response = await fetch('get_activity_logs.php');
        if (!response.ok) {
            throw new Error('Failed to fetch logs');
        }
        const data = await response.json();
        
        // Ensure we always return an array, even if empty or if data structure is different
        if (data.status === 'success' && Array.isArray(data.data)) {
            return data.data;
        } else if (data.status === 'error') {
            console.error('Server error:', data.message);
            return [];
        }
        return []; // Fallback for unexpected response format
    } catch (error) {
        console.error('Error fetching activity logs:', error);
        return []; // Return empty array on error
    }
}

function renderLogs(logs) {
    const logEntries = document.getElementById('logEntries');
    logEntries.innerHTML = '';
    
    // Ensure logs is an array
    logs = Array.isArray(logs) ? logs : [];
    
    if (logs.length === 0) {
        logEntries.innerHTML = '<tr><td colspan="5" class="text-center">No activity logs found</td></tr>';
        return;
    }
    
    logs.forEach(log => {
        const row = document.createElement('tr');
        
        // Format date
        const logDate = new Date(log.created_at);
        const formattedDate = logDate.toLocaleString();
        
        // Determine badge class based on action type
        let badgeClass = '';
        let actionText = '';
        
        switch(log.action_type) {
            // Area actions
            case 'add_area':
                badgeClass = 'badge-add';
                actionText = 'Area Added';
                break;
            case 'edit_area_details':
                badgeClass = 'badge-edit';
                actionText = 'Area Edited';
                break;
            case 'delete_area':
                badgeClass = 'badge-delete';
                actionText = 'Area Deleted';
                break;
            case 'merge_area':
                badgeClass = 'badge-merge';
                actionText = 'Areas Merged';
                break;
            case 'expand_area':
                badgeClass = 'badge-expand';
                actionText = 'Area Expanded';
                break;
            case 'save_changes':
                badgeClass = 'badge-save';
                actionText = 'Changes Saved';
                break;
                
            // Pin actions
            case 'add_pin':
                badgeClass = 'badge-pin-add';
                actionText = 'Pin Added';
                break;
            case 'edit_pin':
                badgeClass = 'badge-pin-edit';
                actionText = 'Pin Edited';
                break;
            case 'delete_pin':
                badgeClass = 'badge-pin-delete';
                actionText = 'Pin Deleted';
                break;
                
            // Default case
            default:
                badgeClass = '';
                actionText = log.action_type || 'Unknown Action';
        }
        
        row.innerHTML = `
            <td>${formattedDate}</td>
            <td><span class="action-badge ${badgeClass}">${actionText}</span></td>
            <td>${log.area_no || 'N/A'}</td>
            <td>${log.city_municipality || 'N/A'}</td>
            <td>${log.details || ''}</td>
            <td>${log.initiated_by || ''}</td>
        `;
        
        logEntries.appendChild(row);
    });
}

// Function to apply filters
function applyFilters() {
    const actionTypeFilter = document.getElementById('actionTypeFilter').value;
    const dateFilter = document.getElementById('dateFilter').value;
    
    // Ensure allLogs is an array before filtering
    let filteredLogs = Array.isArray(allLogs) ? [...allLogs] : [];
    
    // Filter by action type
    if (actionTypeFilter !== 'all') {
        filteredLogs = filteredLogs.filter(log => log.action_type === actionTypeFilter);
    }
    
    // Filter by date
    if (dateFilter) {
        const filterDate = new Date(dateFilter);
        filteredLogs = filteredLogs.filter(log => {
            const logDate = new Date(log.timestamp);
            return logDate.toDateString() === filterDate.toDateString();
        });
    }
    
    // Sort by date (newest first)
    filteredLogs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
    
    totalLogs = filteredLogs.length;
    updatePagination();
    renderPaginatedLogs(filteredLogs);
}

// Function to render paginated logs
function renderPaginatedLogs(logs) {
    const startIndex = (currentPage - 1) * logsPerPage;
    const endIndex = startIndex + logsPerPage;
    const paginatedLogs = logs.slice(startIndex, endIndex);
    renderLogs(paginatedLogs);
}

// Function to update pagination controls
function updatePagination() {
    const totalPages = Math.ceil(totalLogs / logsPerPage);
    document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
    
    document.getElementById('prevPage').disabled = currentPage <= 1;
    document.getElementById('nextPage').disabled = currentPage >= totalPages;
}

// Initialize the activity log
async function initActivityLog() {
    try {
        // Show loading state
        document.getElementById('logEntries').innerHTML = '<tr><td colspan="5" class="text-center">Loading logs...</td></tr>';
        
        // Fetch logs
        const logs = await fetchActivityLogs();
        
        // Ensure allLogs is set to an array
        allLogs = Array.isArray(logs) ? logs : [];
        
        // Apply filters and render
        applyFilters();
    } catch (error) {
        console.error('Error initializing activity log:', error);
        document.getElementById('logEntries').innerHTML = '<tr><td colspan="5" class="text-center">Error loading activity logs</td></tr>';
    }
}

// Event listeners
document.getElementById('actionTypeFilter').addEventListener('change', applyFilters);
document.getElementById('dateFilter').addEventListener('change', applyFilters);
document.getElementById('refreshLogs').addEventListener('click', initActivityLog);
document.getElementById('prevPage').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        applyFilters();
    }
});
document.getElementById('nextPage').addEventListener('click', () => {
    const totalPages = Math.ceil(totalLogs / logsPerPage);
    if (currentPage < totalPages) {
        currentPage++;
        applyFilters();
    }
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', initActivityLog);
    </script>
</body>
</html>