<?php
    session_start();
    include 'database.php';
    require_once 'getdropdown.php';

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

    // Pagination settings
    $profiles_per_page = 20;
    $logs_per_page = 20;
    
    // Get current page for profiles (default to 1)
    $profiles_page = isset($_GET['profiles_page']) ? max(1, intval($_GET['profiles_page'])) : 1;
    $profiles_offset = ($profiles_page - 1) * $profiles_per_page;
    
    // Get current page for logs (default to 1)
    $logs_page = isset($_GET['logs_page']) ? max(1, intval($_GET['logs_page'])) : 1;
    $logs_offset = ($logs_page - 1) * $logs_per_page;

    // Count total profiles for pagination
    $count_profiles_sql = "SELECT COUNT(*) as total FROM barangayprofiletbl WHERE status = 'published'";
    $count_profiles_result = $connection->query($count_profiles_sql);
    $total_profiles = $count_profiles_result->fetch_assoc()['total'];
    $total_profiles_pages = ceil($total_profiles / $profiles_per_page);

    // Fetch mangrove profiles from database with pagination
    $sql = "SELECT * FROM barangayprofiletbl WHERE status = 'published' ORDER BY date_created DESC LIMIT $profiles_per_page OFFSET $profiles_offset";
    $result = $connection->query($sql);
    $profiles = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $profiles[] = $row;
        }
    }

    // Count total logs for pagination
    $count_logs_sql = "SELECT COUNT(*) as total FROM barangayprofile_logstbl";
    $count_logs_result = $connection->query($count_logs_sql);
    $total_logs = $count_logs_result->fetch_assoc()['total'];
    $total_logs_pages = ceil($total_logs / $logs_per_page);

    // Build base query for logs with pagination
    $logSql = "SELECT * FROM barangayprofile_logstbl ORDER BY log_date DESC LIMIT $logs_per_page OFFSET $logs_offset";
    $logResult = $connection->query($logSql);
    $logs = [];

    if ($logResult->num_rows > 0) {
        while($row = $logResult->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Lobby</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminprofile.css">
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
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"){ ?>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>            
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
        <!-- Profile Container Section -->
        <div class="profile-container">
            <div class="profile-header">
                <h1 class="profile-title">Barangay Mangrove Profiles</h1>
                <div class="header-buttons">
                    <button class="new-profile-btn" onclick="addNewProfile()">
                        <i class="fas fa-plus"></i> New Profile
                    </button>
                    <button class="archive-btn" onclick="viewArchives()">
                        <i class="fas fa-archive"></i> View Archives
                    </button>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="city-filter">City/Municipality:</label>
                        <select id="city-filter" class="filter-dropdown" onchange="updateBarangayDropdown()">
                            <option value="">All Cities/Municipalities</option>
                            <?php
                            $cities = getcitymunicipality();
                            foreach ($cities as $city) {
                                echo '<option value="' . htmlspecialchars($city['city']) . '">' . htmlspecialchars($city['city']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="barangay-filter">Barangay:</label>
                        <select id="barangay-filter" class="filter-dropdown">
                            <option value="">All Barangays</option>
                            <!-- Barangay options will be populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status-filter">QR Code Status:</label>
                        <select id="status-filter" class="filter-dropdown">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-actions">
                        <button class="apply-filter-btn" onclick="applyFilters()">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button class="reset-filter-btn" onclick="resetFilters()">
                            <i class="fas fa-times"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <table class="profile-table" id="mangrove-table">
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>City / Municipality</th>
                        <th>Area (ha)</th>
                        <th>Species</th>
                        <th>QR Code Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($profiles)): ?>
                        <?php foreach ($profiles as $profile): ?>
                            <?php
                            // Count number of species
                            $species_count = !empty($profile['species_present']) ? count(explode(',', $profile['species_present'])) : 0;

                            // Determine QR status from the database field
                            $qr_status = !empty($profile['qr_status']) ? $profile['qr_status'] : 'inactive';
                            $status_class = $qr_status === 'active' ? 'status-governed' : 'status-unavailable';
                            $display_status = ucfirst($qr_status); // Display as "Active" or "Inactive"
                            ?>
                            <tr data-profile-id="<?php echo $profile['profile_id']; ?>" data-profile-key="<?php echo $profile['profile_key']; ?>">
                                <td><?php echo htmlspecialchars($profile['barangay']); ?></td>
                                <td><?php echo htmlspecialchars($profile['city_municipality']); ?></td>
                                <td><?php echo htmlspecialchars($profile['mangrove_area']); ?></td>
                                <td><?php echo $species_count; ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $display_status; ?></span></td>
                                <td>
                                    <div>
                                    <form method="post" action="view_barangay_profile.php?profile_key=<?php echo $profile['profile_key']; ?>" style="display:inline;">
                                        <input type="hidden" name="profile_key" value="<?php echo $profile['profile_key']; ?>">
                                        <input type="hidden" name="admin_access_key" value="adminkeynadialamnghindicoderist">
                                        <button type="submit" class="view-btn">View</button>
                                    </form>
                                    <button class="status-toggle-btn <?php echo $qr_status === 'active' ? 'deactivate' : 'activate'; ?>" 
                                            data-profile-id="<?php echo $profile['profile_id']; ?>"
                                            data-current-status="<?php echo $qr_status; ?>">
                                        <?php echo $qr_status === 'active' ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                No mangrove profiles found. <a href="create_mangrove_profile.php">Create a new profile</a> to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Profiles Pagination -->
            <div id="profiles-pagination">
            <?php if ($total_profiles_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo ($profiles_offset + 1); ?> to <?php echo min($profiles_offset + $profiles_per_page, $total_profiles); ?> of <?php echo $total_profiles; ?> profiles
                </div>
                <div class="pagination">
                    <?php if ($profiles_page > 1): ?>
                        <a href="#" class="pagination-btn" onclick="loadPage('profiles', <?php echo ($profiles_page - 1); ?>); return false;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $profiles_page - 2);
                    $end_page = min($total_profiles_pages, $profiles_page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="#" class="pagination-btn" onclick="loadPage('profiles', 1); return false;">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="#" class="pagination-btn <?php echo ($i == $profiles_page) ? 'active' : ''; ?>" onclick="loadPage('profiles', <?php echo $i; ?>); return false;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_profiles_pages): ?>
                        <?php if ($end_page < $total_profiles_pages - 1): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="#" class="pagination-btn" onclick="loadPage('profiles', <?php echo $total_profiles_pages; ?>); return false;"><?php echo $total_profiles_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($profiles_page < $total_profiles_pages): ?>
                        <a href="#" class="pagination-btn" onclick="loadPage('profiles', <?php echo ($profiles_page + 1); ?>); return false;">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            </div>
            
            <!--<div class="mangrove-types">
                <h3>Mangrove Types</h3>
                <div class="type-list">
                    <div class="type-item">Rhizophora Apiculata</div>
                    <div class="type-item">Rhizophora Mucronata</div>
                    <div class="type-item">Avicennia Marina</div>
                    <div class="type-item">Sonneratia Alba</div>
                </div>
            </div>-->

            <div class="log-table-container">
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="log-city-filter">City/Municipality:</label>
                            <select id="log-city-filter" class="filter-dropdown" onchange="updateLogBarangayDropdown()">
                                <option value="">All Cities/Municipalities</option>
                                <?php
                                $cities = getcitymunicipality();
                                foreach ($cities as $city) {
                                    echo '<option value="' . htmlspecialchars($city['city']) . '">' . htmlspecialchars($city['city']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="log-barangay-filter">Barangay:</label>
                            <select id="log-barangay-filter" class="filter-dropdown">
                                <option value="">All Barangays</option>
                                <!-- Barangay options will be populated dynamically -->
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="log-action-filter">Action:</label>
                            <select id="log-action-filter" class="filter-dropdown">
                                <option value="">All Actions</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                                <option value="updated">Updated</option>
                            </select>
                        </div>
                        
                        <div class="filter-group filter-actions">
                            <button class="apply-filter-btn" onclick="applyLogFilters()">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                            <button class="reset-filter-btn" onclick="resetLogFilters()">
                                <i class="fas fa-times"></i> Reset
                            </button>
                        </div>
                    </div>
                </div>
                
                <h3>Profile Activity Log</h3>
                <table class="profile-table log-table" id="logs-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Action By</th>
                            <th>Barangay</th>
                            <th>City / Municipality</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Account ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['log_date']); ?></td>
                                    <td><?php echo htmlspecialchars($log['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($log['barangay']); ?></td>
                                    <td><?php echo htmlspecialchars($log['city_municipality']); ?></td>
                                    <td><span class="action-badge <?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                    <td><?php echo nl2br(htmlspecialchars($log['description'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['account_id']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">
                                    No activity logs found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- Logs Pagination -->
                <div id="logs-pagination">
                <?php if ($total_logs_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?php echo ($logs_offset + 1); ?> to <?php echo min($logs_offset + $logs_per_page, $total_logs); ?> of <?php echo $total_logs; ?> logs
                    </div>
                    <div class="pagination">
                        <?php if ($logs_page > 1): ?>
                            <a href="#" class="pagination-btn" onclick="loadPage('logs', <?php echo ($logs_page - 1); ?>); return false;">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $logs_page - 2);
                        $end_page = min($total_logs_pages, $logs_page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="#" class="pagination-btn" onclick="loadPage('logs', 1); return false;">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="#" class="pagination-btn <?php echo ($i == $logs_page) ? 'active' : ''; ?>" onclick="loadPage('logs', <?php echo $i; ?>); return false;">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_logs_pages): ?>
                            <?php if ($end_page < $total_logs_pages - 1): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                            <a href="#" class="pagination-btn" onclick="loadPage('logs', <?php echo $total_logs_pages; ?>); return false;"><?php echo $total_logs_pages; ?></a>
                        <?php endif; ?>
                        
                        <?php if ($logs_page < $total_logs_pages): ?>
                            <a href="#" class="pagination-btn" onclick="loadPage('logs', <?php echo ($logs_page + 1); ?>); return false;">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
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
// Function to get URL parameters
function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        profiles_page: params.get('profiles_page') || 1,
        logs_page: params.get('logs_page') || 1
    };
}

// Function to update URL with pagination parameters
function updateUrlWithPagination(profilesPage = null, logsPage = null) {
    const params = getUrlParams();
    const newParams = new URLSearchParams();
    
    newParams.set('profiles_page', profilesPage || params.profiles_page);
    newParams.set('logs_page', logsPage || params.logs_page);
    
    window.history.replaceState({}, '', '?' + newParams.toString());
}

// Function to load page data asynchronously
function loadPage(type, page) {
    const tableId = type === 'profiles' ? 'mangrove-table' : 'logs-table';
    const paginationId = type === 'profiles' ? 'profiles-pagination' : 'logs-pagination';
    
    // Show loading state
    const tbody = document.querySelector(`#${tableId} tbody`);
    const paginationContainer = document.getElementById(paginationId);
    
    tbody.innerHTML = '<tr><td colspan="' + (type === 'profiles' ? '6' : '7') + '" style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
    
    // Get current filter values
    let filterParams = '';
    if (type === 'profiles') {
        const city = document.getElementById('city-filter').value;
        const barangay = document.getElementById('barangay-filter').value;
        const status = document.getElementById('status-filter').value;
        
        if (city) filterParams += `&city=${encodeURIComponent(city)}`;
        if (barangay) filterParams += `&barangay=${encodeURIComponent(barangay)}`;
        if (status) filterParams += `&status=${encodeURIComponent(status)}`;
    } else {
        const city = document.getElementById('log-city-filter').value;
        const barangay = document.getElementById('log-barangay-filter').value;
        const action = document.getElementById('log-action-filter').value;
        
        if (city) filterParams += `&city=${encodeURIComponent(city)}`;
        if (barangay) filterParams += `&barangay=${encodeURIComponent(barangay)}`;
        if (action) filterParams += `&action=${encodeURIComponent(action)}`;
    }
    
    // Make AJAX request
    fetch(`get_paginated_data.php?type=${type}&page=${page}${filterParams}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update table content
                tbody.innerHTML = data.html;
                
                // Update pagination
                if (paginationContainer) {
                    paginationContainer.innerHTML = data.pagination_html;
                }
                
                // Update URL parameters
                if (type === 'profiles') {
                    updateUrlWithPagination(page, null);
                } else {
                    updateUrlWithPagination(null, page);
                }
            } else {
                tbody.innerHTML = '<tr><td colspan="' + (type === 'profiles' ? '6' : '7') + '" style="text-align: center; padding: 20px; color: red;">Error loading data: ' + data.message + '</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = '<tr><td colspan="' + (type === 'profiles' ? '6' : '7') + '" style="text-align: center; padding: 20px; color: red;">Error loading data. Please try again.</td></tr>';
        });
}

// Store the original table data for filtering
let originalTableData = [];

// Function to update barangay dropdown based on selected city
function updateBarangayDropdown() {
    const citySelect = document.getElementById('city-filter');
    const barangaySelect = document.getElementById('barangay-filter');
    const selectedCity = citySelect.value;
    
    // Clear current barangay options except the first one
    while (barangaySelect.options.length > 1) {
        barangaySelect.remove(1);
    }
    
    // If no city is selected, keep the barangay dropdown with only "All Barangays"
    if (!selectedCity) {
        return;
    }
    
    // Fetch barangays for the selected city
    fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error fetching barangays:', data.error);
                return;
            }
            
            // Add barangay options to the dropdown
            data.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.barangay;
                option.textContent = barangay.barangay;
                barangaySelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Function to extract text content from table cells (handles spans with status badges)
function getCellText(cell) {
    // If the cell contains a span with class 'status-badge', get its text content
    const statusBadge = cell.querySelector('.status-badge');
    if (statusBadge) {
        return statusBadge.textContent.trim();
    }
    // Otherwise, return the cell's text content
    return cell.textContent.trim();
}

// Function to apply filters
function applyFilters() {
    const cityFilter = document.getElementById('city-filter').value.toLowerCase();
    const barangayFilter = document.getElementById('barangay-filter').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value.toLowerCase();
    
    // Reset to first page when applying filters and reload data
    updateUrlWithPagination(1, null);
    loadPage('profiles', 1);
}

// Function to reset filters
function resetFilters() {
    document.getElementById('city-filter').value = '';
    document.getElementById('barangay-filter').value = '';
    document.getElementById('status-filter').value = '';
    
    // Clear barangay dropdown except the first option
    const barangaySelect = document.getElementById('barangay-filter');
    while (barangaySelect.options.length > 1) {
        barangaySelect.remove(1);
    }
    
    // Reset to first page and reload data
    updateUrlWithPagination(1, null);
    loadPage('profiles', 1);
}

// Function to update barangay dropdown based on selected city (for Logs)
function updateLogBarangayDropdown() {
    const citySelect = document.getElementById('log-city-filter');
    const barangaySelect = document.getElementById('log-barangay-filter');
    const selectedCity = citySelect.value;
    
    // Clear current barangay options except the first one
    while (barangaySelect.options.length > 1) {
        barangaySelect.remove(1);
    }
    
    // If no city is selected, keep the barangay dropdown with only "All Barangays"
    if (!selectedCity) {
        return;
    }
    
    // Fetch barangays for the selected city
    fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Error fetching barangays:', data.error);
                return;
            }
            
            // Add barangay options to the dropdown
            data.forEach(barangay => {
                const option = document.createElement('option');
                option.value = barangay.barangay;
                option.textContent = barangay.barangay;
                barangaySelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Function to apply filters (for Logs)
function applyLogFilters() {
    const cityFilter = document.getElementById('log-city-filter').value.toLowerCase();
    const barangayFilter = document.getElementById('log-barangay-filter').value.toLowerCase();
    const actionFilter = document.getElementById('log-action-filter').value.toLowerCase();
    
    // Reset to first page when applying filters and reload data
    updateUrlWithPagination(null, 1);
    loadPage('logs', 1);
}

// Function to reset filters (for Logs)
function resetLogFilters() {
    document.getElementById('log-city-filter').value = '';
    document.getElementById('log-barangay-filter').value = '';
    document.getElementById('log-action-filter').value = '';
    
    // Clear barangay dropdown except the first option
    const barangaySelect = document.getElementById('log-barangay-filter');
    while (barangaySelect.options.length > 1) {
        barangaySelect.remove(1);
    }
    
    // Reset to first page and reload data
    updateUrlWithPagination(null, 1);
    loadPage('logs', 1);
}

function addNewProfile() {
    //redirect user to the create mangrove profile page
    window.location.href = 'create_mangrove_profile.php';
}

function viewArchives() {
    //redirect user to the archives page
    window.location.href = 'barangay_archives.php';
}

function viewProfile(profileKey) {
    //redirect user to the view profile page
    window.location.href = `view_barangay_profile.php?profile_key=${profileKey}`;
}

function toggleQRStatus(profileId, currentStatus, buttonElement) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = currentStatus === 'active' ? 'deactivate' : 'activate';
    
    if (confirm(`Are you sure you want to ${action} the QR code for this profile?`)) {
        // Send AJAX request to toggle QR status
        fetch('toggle_qr_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `profile_id=${profileId}&new_status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Get the row containing the button
                const row = buttonElement.closest('tr');
                
                // Update status badge
                const statusBadge = row.querySelector('.status-badge');
                statusBadge.textContent = newStatus === 'active' ? 'Active' : 'Inactive';
                statusBadge.className = `status-badge ${newStatus === 'active' ? 'status-governed' : 'status-unavailable'}`;
                
                // Update toggle button
                buttonElement.textContent = newStatus === 'active' ? 'Deactivate' : 'Activate';
                buttonElement.className = `status-toggle-btn ${newStatus === 'active' ? 'deactivate' : 'activate'}`;
                buttonElement.setAttribute('data-current-status', newStatus);
                
                alert(`QR code ${action}d successfully!`);
            } else {
                alert('Error updating QR status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the QR status.');
        });
    }
}

// Initialize the table data when the page loads
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('mangrove-table').addEventListener('click', function(e) {
        if (e.target.classList.contains('status-toggle-btn')) {
            const profileId = e.target.getAttribute('data-profile-id');
            const currentStatus = e.target.getAttribute('data-current-status');
            toggleQRStatus(profileId, currentStatus, e.target);
        }
    });

    // Store original table data for potential future use
    const table = document.getElementById('mangrove-table');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const cells = row.getElementsByTagName('td');
        
        // Skip if row has no data (empty state)
        if (cells.length < 6) continue;
        
        originalTableData.push({
            barangay: cells[0].textContent.trim(),
            city: cells[1].textContent.trim(),
            area: cells[2].textContent.trim(),
            species: cells[3].textContent.trim(),
            status: getCellText(cells[4])
        });
    }
});
</script>
</body>
</html>