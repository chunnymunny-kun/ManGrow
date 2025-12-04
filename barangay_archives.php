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

    // Fetch archived profiles from database
    $sql = "SELECT * FROM profile_archivestbl WHERE status = 'archived' ORDER BY date_created DESC";
    $result = $connection->query($sql);
    $archived_profiles = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $archived_profiles[] = $row;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Profile Archives</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminprofile.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="adminusers.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>

    <style>
    .archive-container {
        margin:0 auto;
        padding: 20px;
        background-color: #f8f9fa;
        min-height: calc(100vh - 160px);
    }

    .archive-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .archive-title {
        font-size: 28px;
        color: var(--base-clr);
        margin: 0;
    }

    .back-btn {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .back-btn:hover {
        background-color: #5a6268;
        color: white;
        text-decoration: none;
    }

    .archive-content {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 20px;
        margin-bottom: 20px;
    }

    .archive-list {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .archive-detail-panel {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: fit-content;
        position: sticky;
        top: 20px;
    }

    .detail-panel-empty {
        text-align: center;
        color: #6c757d;
        font-style: italic;
        padding: 40px 20px;
    }

    .filter-section {
        background: white;
        padding: 15px 20px;
        border-bottom: 1px solid #e9ecef;
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-weight: 500;
        margin-bottom: 5px;
        color: #495057;
        font-size: 14px;
    }

    .filter-dropdown, .date-input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
        background-color: white;
    }

    .filter-actions {
        display: flex;
        flex-direction: row;
        gap: 10px;
        align-items: end;
    }

    .apply-filter-btn, .reset-filter-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .apply-filter-btn {
        background-color: var(--base-clr);
        color: white;
    }

    .apply-filter-btn:hover {
        background-color: var(--placeholder-text-clr);
    }

    .reset-filter-btn {
        background-color: #6c757d;
        color: white;
    }

    .reset-filter-btn:hover {
        background-color: #5a6268;
    }

    .archive-table {
        width: 100%;
        border-collapse: collapse;
    }

    .archive-table th,
    .archive-table td {
        padding: 12px 15px;
        text-align: left;
        border-bottom: 1px solid #e9ecef;
    }

    .archive-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .archive-table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .archive-table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .archive-table tbody tr.selected {
        background-color: #e3f2fd;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .status-archived {
        background-color: #ffc107;
        color: #212529;
    }

    .qr-status-inactive {
        background-color: #dc3545;
        color: white;
    }

    .detail-header {
        border-bottom: 1px solid #e9ecef;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .detail-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--base-clr);
        margin: 0;
    }

    .detail-info {
        display: grid;
        gap: 10px;
        margin-bottom: 20px;
    }

    .info-row {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 10px;
    }

    .info-label {
        font-weight: 500;
        color: #6c757d;
    }

    .info-value {
        color: #495057;
    }

    .restore-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .restore-btn:hover {
        background-color: #218838;
    }

    .restore-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }

    @media (max-width: 1024px) {
        .archive-content {
            grid-template-columns: 1fr;
        }
        
        .archive-detail-panel {
            position: static;
        }
    }
    </style>
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
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>            
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

        <!-- Archive Container Section -->
        <div class="archive-container">
            <div class="archive-header">
                <h1 class="archive-title">Archived Barangay Profiles</h1>
                <a href="adminprofile.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Profiles
                </a>
            </div>
            
            <div class="archive-content">
                <!-- Archive List -->
                <div class="archive-list">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="archive-city-filter">City/Municipality:</label>
                                <select id="archive-city-filter" class="filter-dropdown" onchange="updateArchiveBarangayDropdown()">
                                    <option value="">All Cities/Municipalities</option>
                                    <?php
                                    $cities = getcitymunicipality();
                                    foreach ($cities as $city) {
                                        echo '<option value="' . htmlspecialchars($city['city_municipality']) . '">' . htmlspecialchars($city['city_municipality']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="archive-barangay-filter">Barangay:</label>
                                <select id="archive-barangay-filter" class="filter-dropdown">
                                    <option value="">All Barangays</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="archive-date-from">From Date:</label>
                                <input type="date" id="archive-date-from" class="date-input">
                            </div>
                            
                            <div class="filter-group">
                                <label for="archive-date-to">To Date:</label>
                                <input type="date" id="archive-date-to" class="date-input">
                            </div>
                            
                            <div class="filter-group filter-actions">
                                
                            </div>
                            <div class="filter-group filter-actions">
                                <button class="apply-filter-btn" onclick="applyArchiveFilters()">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <button class="reset-filter-btn" onclick="resetArchiveFilters()">
                                    <i class="fas fa-times"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <table class="archive-table" id="archive-table">
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>City / Municipality</th>
                                <th>Area (ha)</th>
                                <th>Archived Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($archived_profiles)): ?>
                                <?php foreach ($archived_profiles as $profile): ?>
                                <tr onclick="selectArchiveProfile(<?php echo $profile['draft_id']; ?>)" data-profile-id="<?php echo $profile['draft_id']; ?>">
                                    <td><?php echo htmlspecialchars($profile['barangay']); ?></td>
                                    <td><?php echo htmlspecialchars($profile['city_municipality']); ?></td>
                                    <td><?php echo number_format($profile['mangrove_area'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($profile['date_created'])); ?></td>
                                    <td>
                                        <span class="status-badge status-archived">Archived</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #6c757d; padding: 40px;">
                                        No archived profiles found
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Detail Panel -->
                <div class="archive-detail-panel" id="detail-panel">
                    <div class="detail-panel-empty">
                        <i class="fas fa-archive" style="font-size: 48px; color: #dee2e6; margin-bottom: 15px;"></i>
                        <p>Select an archived profile to view details</p>
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
    let selectedArchiveId = null;
    let archiveProfiles = <?php echo json_encode($archived_profiles); ?>;

    // Function to update barangay dropdown based on selected city
    function updateArchiveBarangayDropdown() {
        const citySelect = document.getElementById('archive-city-filter');
        const barangaySelect = document.getElementById('archive-barangay-filter');
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

    // Function to apply filters
    function applyArchiveFilters() {
        const cityFilter = document.getElementById('archive-city-filter').value.toLowerCase();
        const barangayFilter = document.getElementById('archive-barangay-filter').value.toLowerCase();
        const dateFromFilter = document.getElementById('archive-date-from').value;
        const dateToFilter = document.getElementById('archive-date-to').value;
        
        const table = document.getElementById('archive-table');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        // Loop through all table rows
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            
            // Skip if row has no data (empty state)
            if (cells.length < 5) continue;
            
            // Get values from each relevant cell
            const barangay = cells[0].textContent.trim().toLowerCase();
            const city = cells[1].textContent.trim().toLowerCase();
            const archivedDate = cells[3].textContent.trim();
            
            // Convert archived date to comparable format
            const rowDate = new Date(archivedDate);
            const fromDate = dateFromFilter ? new Date(dateFromFilter) : null;
            const toDate = dateToFilter ? new Date(dateToFilter) : null;
            
            // Check if row matches all filters
            const cityMatch = !cityFilter || city === cityFilter;
            const barangayMatch = !barangayFilter || barangay === barangayFilter;
            const dateMatch = (!fromDate || rowDate >= fromDate) && (!toDate || rowDate <= toDate);
            
            // Show or hide the row based on filter matches
            if (cityMatch && barangayMatch && dateMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }

    // Function to reset filters
    function resetArchiveFilters() {
        document.getElementById('archive-city-filter').value = '';
        document.getElementById('archive-barangay-filter').value = '';
        document.getElementById('archive-date-from').value = '';
        document.getElementById('archive-date-to').value = '';
        
        // Clear barangay dropdown except the first option
        const barangaySelect = document.getElementById('archive-barangay-filter');
        while (barangaySelect.options.length > 1) {
            barangaySelect.remove(1);
        }
        
        // Show all rows
        const table = document.getElementById('archive-table');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            rows[i].style.display = '';
        }
        
        // Clear selection
        clearSelection();
    }

    // Function to select an archived profile
    function selectArchiveProfile(profileId) {
        selectedArchiveId = profileId;
        
        // Remove previous selection
        document.querySelectorAll('.archive-table tbody tr').forEach(row => {
            row.classList.remove('selected');
        });
        
        // Add selection to clicked row
        const selectedRow = document.querySelector(`tr[data-profile-id="${profileId}"]`);
        if (selectedRow) {
            selectedRow.classList.add('selected');
        }
        
        // Find the profile data
        const profile = archiveProfiles.find(p => p.draft_id == profileId);
        if (profile) {
            displayProfileDetails(profile);
        }
    }

    // Function to display profile details in the panel
    function displayProfileDetails(profile) {
        const detailPanel = document.getElementById('detail-panel');
        
        const speciesArray = profile.species_present ? profile.species_present.split(',') : [];
        const speciesHtml = speciesArray.length > 0 
            ? speciesArray.map(species => `<span class="species-tag">${species.trim()}</span>`).join(' ')
            : 'No species data';

        const photosArray = profile.photos ? profile.photos.split(',') : [];
        const photosHtml = photosArray.length > 0
            ? photosArray.map(photo => `<img src="${photo}" alt="Profile Photo" style="width: 100%; margin-bottom: 10px; border-radius: 4px;">`).join('')
            : '<p style="color: #6c757d; font-style: italic;">No photos available</p>';
        
        detailPanel.innerHTML = `
            <div class="detail-header">
                <h3 class="detail-title">${profile.barangay}, ${profile.city_municipality}</h3>
            </div>
            
            <div class="detail-info">
                <div class="info-row">
                    <span class="info-label">Profile Key:</span>
                    <span class="info-value">${profile.profile_key || 'N/A'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Mangrove Area:</span>
                    <span class="info-value">${parseFloat(profile.mangrove_area).toFixed(2)} ha</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Profile Date:</span>
                    <span class="info-value">${new Date(profile.profile_date).toLocaleDateString()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Coordinates:</span>
                    <span class="info-value">${parseFloat(profile.latitude).toFixed(6)}, ${parseFloat(profile.longitude).toFixed(6)}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Species Present:</span>
                    <span class="info-value">${speciesHtml}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">QR Status:</span>
                    <span class="info-value">
                        <span class="status-badge qr-status-inactive">${profile.qr_status || 'Inactive'}</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Archived Date:</span>
                    <span class="info-value">${new Date(profile.date_created).toLocaleDateString()}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Last Edited:</span>
                    <span class="info-value">${profile.date_edited ? new Date(profile.date_edited).toLocaleDateString() : 'Never'}</span>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <h4 style="color: #495057; margin-bottom: 10px;">Photos:</h4>
                ${photosHtml}
            </div>
            
            <button class="restore-btn" onclick="restoreProfile(${profile.draft_id})">
                <i class="fas fa-undo"></i> Restore Profile
            </button>
        `;
    }

    // Function to clear selection
    function clearSelection() {
        selectedArchiveId = null;
        document.querySelectorAll('.archive-table tbody tr').forEach(row => {
            row.classList.remove('selected');
        });
        
        const detailPanel = document.getElementById('detail-panel');
        detailPanel.innerHTML = `
            <div class="detail-panel-empty">
                <i class="fas fa-archive" style="font-size: 48px; color: #dee2e6; margin-bottom: 15px;"></i>
                <p>Select an archived profile to view details</p>
            </div>
        `;
    }

    // Function to restore a profile
    function restoreProfile(profileId) {
        if (!confirm('Are you sure you want to restore this profile? It will be moved back to active profiles with published status.')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('profile_id', profileId);
        
        fetch('restore_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Set success flash message
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'success',
                        msg: 'Profile restored successfully!'
                    })
                })
                .then(() => {
                    location.reload(); // Refresh the page to update the list and show flash message
                });
            } else {
                // Set error flash message
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'error',
                        msg: 'Error restoring profile: ' + data.message
                    })
                })
                .then(() => {
                    location.reload(); // Refresh the page to show flash message
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Set error flash message for network/unexpected errors
            fetch('set_flash_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'error',
                    msg: 'An error occurred while restoring the profile.'
                })
            })
            .then(() => {
                location.reload(); // Refresh the page to show flash message
            });
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Any initialization code can go here
    });
    </script>
</body>
</html>
