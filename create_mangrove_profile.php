<?php
    session_start();
    include 'database.php';
    require_once 'getdropdown.php'; // Assuming this file provides data for dropdowns dynamically

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
            $email = $_SESSION["name"]; // Assuming 'name' stores a unique identifier or email
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
    <title>Create New Mangrove Profile</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="create_mangrove_profile.css">
    <!-- Boxicons and Font Awesome for icons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Inter Font (for consistency with the new page content) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- QRious library for QR code generation -->
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
      crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>
</head>
<body class="min-h-screen flex flex-col">
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a-php"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d-php"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d-php"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80-php"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d-php)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>           
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
    <main class="flex-grow container mx-auto p-6 md:p-10">
        <?php
        if (isset($_GET['view_event'])) {
            $eventId = (int)$_GET['view_event'];
            echo <<<HTML
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Wait a brief moment to ensure all elements are loaded
                setTimeout(function() {
                    handleViewButtonClick($eventId); // Assuming this JS function exists in your app.js or adminusers.js
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
        
        <div class="form-container">
            <a href="#" onclick="history.back()" class="back-link">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon-back" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Profiles
            </a>
            <h2 class="page-title">Create New Mangrove Profile</h2>

            <div class="main-grid">
            <!-- Left Column: Data Input Form -->
            <section class="section-left">
                <!-- Location Details -->
                <div class="section">
                <h3 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Location Details
                </h3>
                <div class="form-grid">
                    <div>
                    <label for="cityMunicipality" class="form-label"><span class="required-field">City/Municipality</span></label>
                    <select id="cityMunicipality" class="form-select" required>
                        <option value="" disabled selected>Select City/Municipality</option>
                        <?php
                        $cities = getcitymunicipality();
                        foreach ($cities as $city) {
                        echo '<option value="' . htmlspecialchars($city['city']) . '">' . htmlspecialchars($city['city']) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <!-- Add toggle for other city -->
                    <div class="toggle-other-container">
                        <input type="checkbox" id="toggle-other-city" class="toggle-checkbox">
                        <label for="toggle-other-city" class="toggle-label">Not in the list? Specify other city/municipality</label>
                    </div>
                    
                    <!-- Other city input field -->
                    <div class="other-city-container" id="other-city-container" style="display: none;">
                        <label for="otherCity" class="form-label">Specify City/Municipality</label>
                        <input type="text" id="otherCity" class="form-input" placeholder="Enter your city/municipality">
                    </div>
                    </div>
                    <div>
                    <label for="barangay" class="form-label"><span class="required-field">Barangay</span></label>
                    <select id="barangay" class="form-select" required>
                        <option value="" disabled selected>Select Barangay</option>
                        <!-- Options will be dynamically populated -->
                    </select>
                    
                    <!-- Add toggle for other barangay -->
                    <div class="toggle-other-container">
                        <input type="checkbox" id="toggle-other-barangay" class="toggle-checkbox">
                        <label for="toggle-other-barangay" class="toggle-label">Not in the list? Specify other barangay</label>
                    </div>
                    
                    <!-- Other barangay input field -->
                    <div class="other-barangay-container" id="other-barangay-container" style="display: none;">
                        <label for="otherBarangay" class="form-label">Specify Barangay</label>
                        <input type="text" id="otherBarangay" class="form-input" placeholder="Enter your barangay">
                    </div>
                    </div>
                </div>
                <div class="map-section">
                    <label class="form-label">Approximate Location on Map</label>
                    <!-- Add search bar for map -->
                    <div class="map-search-container">
                        <div class="map-search-input-wrapper">
                            <svg xmlns="http://www.w3.org/2000/svg" class="map-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text" id="map-search" class="map-search-input" placeholder="Search for a location (e.g., Barangay Wawa, Abucay)" />
                            <div id="search-suggestions" class="search-suggestions"></div>
                        </div>
                    </div>
                    <div class="map" id="locationMap" style="height: 300px;"></div>
                    <div class="form-grid map-coords">
                        <div>
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="text" id="latitude" class="form-input" value="14.6850" required readonly>
                        </div>
                        <div>
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="text" id="longitude" class="form-input" value="120.5350" required readonly>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Mangrove Profile Information -->
                <div class="section">
                <h3 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.127l-3.328 3.328m0 0l-3.328-3.328m3.328 3.328v9.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Mangrove Profile Information
                </h3>
                <div class="form-grid">
                    <div>
                    <label for="mangroveArea" class="form-label"><span class="required-field">Total Mangrove Area (ha)</span></label>
                    <input type="number" id="mangroveArea" class="form-input" placeholder="Auto-calculated from JSON data" step="0.01" min="0" required readonly style="background-color: #f3f4f6;">
                    
                    <!-- Area calculation breakdown -->
                    <div id="area-breakdown" style="margin-top: 8px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; border: 1px solid #e9ecef; display: none;">
                        <div style="font-size: 0.875rem; color: #666; margin-bottom: 6px;">
                            <strong>Area Calculation Breakdown:</strong>
                        </div>
                        <div style="font-size: 0.8rem; color: #495057;">
                            <div id="raw-area-display" style="margin-bottom: 3px;">
                                <span style="color: #6c757d;">Raw Total:</span> <span id="raw-area-value">--</span> ha
                                <span style="color: #6c757d; font-style: italic;">(sum of all mapped areas)</span>
                            </div>
                            <div id="distributed-area-display">
                                <span style="color: #28a745; font-weight: 500;">Distributed Total:</span> <span id="distributed-area-value">--</span> ha
                                <span style="color: #6c757d; font-style: italic;">(divided by shared barangays)</span>
                            </div>
                        </div>
                        <div style="font-size: 0.75rem; color: #6c757d; margin-top: 6px; font-style: italic;">
                            The distributed total accounts for areas shared with other barangays.
                        </div>
                    </div>
                    
                    <small class="form-help-text">This value is automatically calculated from mapped mangrove areas for the selected barangay. <a href="#" id="show-area-breakdown" style="color: #007bff; text-decoration: none;">Show calculation details</a></small>
                    </div>
                    <div>
                    <label for="profileDate" class="form-label"><span class="required-field">Date of Profile</span></label>
                    <input type="date" id="profileDate" class="form-input" required>
                    </div>
                </div>

                <div class="species-section">
                    <label class="form-label">Mangrove Species Present</label>
                    <div class="checkbox-container">
                    <div class="checkbox-item">
                        <input id="species1" name="species" type="checkbox" value="Rhizophora apiculata" class="checkbox-input">
                        <label for="species1" class="checkbox-label">Rhizophora apiculata</label>
                    </div>
                    <div class="checkbox-item">
                        <input id="species2" name="species" type="checkbox" value="Rhizophora mucronata" class="checkbox-input">
                        <label for="species2" class="checkbox-label">Rhizophora mucronata</label>
                    </div>
                    <div class="checkbox-item">
                        <input id="species3" name="species" type="checkbox" value="Avicennia marina" class="checkbox-input">
                        <label for="species3" class="checkbox-label">Avicennia marina</label>
                    </div>
                    <div class="checkbox-item">
                        <input id="species4" name="species" type="checkbox" value="Sonneratia alba" class="checkbox-input">
                        <label for="species4" class="checkbox-label">Sonneratia alba</label>
                    </div>
                    <div class="checkbox-item">
                        <input id="species-other-checkbox" type="checkbox" class="checkbox-input">
                        <label for="species-other-checkbox" class="checkbox-label">Other (please specify)</label>
                    </div>
                    <div class="other-species-container" id="other-species-input-container" style="display: none;">
                        <label for="otherSpecies" class="sr-only">Other Species</label>
                        <input type="text" id="otherSpecies" class="form-input" placeholder="e.g., Bruguiera gymnorrhiza, Ceriops tagal">
                    </div>
                    </div>
                    <p id="species-error" class="error-message hidden">Please select at least one species.</p>
                </div>
                </div>

                <!-- Photos/Documentation Upload -->
                <div class="section">
                <h3 class="section-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Photos/Documentation
                </h3>
                <div class="file-upload">
                    <div class="file-upload-content">
                    <svg class="file-upload-icon" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L36 32" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <div class="file-upload-text">
                        <label for="file-upload" class="file-upload-label">
                        <span>Upload a file</span>
                        <input id="file-upload" name="file-upload" type="file" class="file-upload-input" multiple accept="image/*">
                        </label>
                        <p class="file-upload-or">or multiple files</p>
                    </div>
                    <p class="file-upload-hint">PNG, JPG, GIF up to 10MB</p>
                    </div>
                </div>
                <ul id="file-list" class="file-list">
                    <!-- Uploaded files will be listed here -->
                </ul>
                </div>
            </section>

            <!-- Right Column: Summary and Finalization -->
            <aside class="section-right">
                <!-- Profile Summary -->
                <div class="summary-section">
                <h3 class="summary-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                    Profile Summary
                </h3>
                <p class="summary-item"><strong>Barangay:</strong> <span id="summary-barangay">N/A</span></p>
                <p class="summary-item"><strong>City/Municipality:</strong> <span id="summary-city">N/A</span></p>
                <p class="summary-item"><strong>Total Area:</strong> <span id="summary-area">N/A</span> ha</p>
                <p class="summary-item"><strong>Profile Date:</strong> <span id="summary-date">N/A</span></p>
                <p class="summary-item"><strong>Species Present:</strong> <span id="summary-species">N/A</span></p>
                </div>

                <!-- QR Code Section -->
                <div class="qr-section">
                <h3 class="qr-title">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-section" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 5V2m12 3V2m-6 3V2M3 8v.01M21 8v.01M3 12v.01M21 12v.01M3 16v.01M21 16v.01M3 20v.01M21 20v.01M8 20h8a2 2 0 002-2V6a2 2 0 00-2-2H8a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    QR Code Status
                </h3>
                <div class="qr-container">
                    <canvas id="qrcode" class="qr-canvas"></canvas>
                    <p class="qr-hint">
                    QR code will link to a public-facing profile page once finalized.
                    </p>
                    <a id="downloadQr" href="#" download="" class="download-qr" style="display: none;">
                    <script>
                        // Dynamically set the PNG filename based on barangay and city
                        function updateQrDownloadFilename() {
                            const cityValue = document.getElementById('toggle-other-city').checked 
                                ? document.getElementById('otherCity').value.trim() 
                                : document.getElementById('cityMunicipality').value.trim();
                            const barangayValue = document.getElementById('toggle-other-barangay').checked 
                                ? document.getElementById('otherBarangay').value.trim() 
                                : document.getElementById('barangay').value.trim();
                            let cleanCity = cityValue.replace(/[^\w\s-]/g, '').replace(/\s+/g, '_');
                            let cleanBarangay = barangayValue.replace(/[^\w\s-]/g, '').replace(/\s+/g, '_');
                            let filename = 'qr_' + cleanBarangay + '_' + cleanCity + '.png';
                            document.getElementById('downloadQr').setAttribute('download', filename);
                        }
                        // Call this whenever summary or QR is updated
                        document.addEventListener('DOMContentLoaded', function() {
                            updateQrDownloadFilename();
                            document.getElementById('cityMunicipality').addEventListener('change', updateQrDownloadFilename);
                            document.getElementById('barangay').addEventListener('change', updateQrDownloadFilename);
                            document.getElementById('toggle-other-city').addEventListener('change', updateQrDownloadFilename);
                            document.getElementById('toggle-other-barangay').addEventListener('change', updateQrDownloadFilename);
                            document.getElementById('otherCity').addEventListener('input', updateQrDownloadFilename);
                            document.getElementById('otherBarangay').addEventListener('input', updateQrDownloadFilename);
                        });
                    </script>
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon-download" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    Download QR
                    </a>
                </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                <button id="finalizeButton" class="btn btn-primary" disabled>
                    Publish Profile & Generate QR
                </button>
                <button id="cancelButton" class="btn btn-danger">
                    Cancel
                </button>
                </div>
                </aside>
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
        let map, marker;
        let searchTimeout;

        function initMap() {
            // Default coordinates (Manila area)
            const defaultLat = 14.64852;
            const defaultLng = 120.47318;
            
            // Initialize map
            map = L.map('locationMap').setView([defaultLat, defaultLng], 11.5);
            
            // Add tile layer (OpenStreetMap)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Set z-index of zoom controls to 1 (or lower)
            setTimeout(function() {
            const zoomControl = document.querySelector('.leaflet-control-zoom');
                if (zoomControl) {
                    zoomControl.style.zIndex = '1';
                }
            }, 100);

            // Initialize marker with default position
            marker = L.marker([defaultLat, defaultLng], {
                draggable: true
            }).addTo(map)
              .bindPopup('Drag me to the approximate location or click on the map to move me.');

            // Set initial coordinates in form fields
            document.getElementById('latitude').value = defaultLat.toFixed(6);
            document.getElementById('longitude').value = defaultLng.toFixed(6);
            
            // Event listener for marker drag end
            marker.on('dragend', function(event) {
            const position = marker.getLatLng();
                updateCoordinates(position.lat, position.lng);
            });
            
            // Event listener for map click (to add/move marker)
            map.on('click', function(event) {
            marker.setLatLng(event.latlng);
                updateCoordinates(event.latlng.lat, event.latlng.lng);
            });
            
            // Initialize search functionality
            initSearch();
        }

        function initSearch() {
            const searchInput = document.getElementById('map-search');
            const suggestionsContainer = document.getElementById('search-suggestions');
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Hide suggestions if query is empty
                if (query.length < 3) {
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.classList.remove('active');
                    return;
                }
                
                // Set timeout to avoid too many API calls
                searchTimeout = setTimeout(() => {
                    searchLocation(query, suggestionsContainer);
                }, 300);
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.classList.remove('active');
                }
            });
        }

        function searchLocation(query, suggestionsContainer) {
            // Use Nominatim (OpenStreetMap's geocoding service)
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=PH`;
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    suggestionsContainer.innerHTML = '';
                    
                    if (data.length === 0) {
                        suggestionsContainer.innerHTML = '<div class="suggestion-item">No results found</div>';
                        suggestionsContainer.classList.add('active');
                        return;
                    }
                    
                    data.forEach(place => {
                        const suggestionItem = document.createElement('div');
                        suggestionItem.className = 'suggestion-item';
                        suggestionItem.innerHTML = `
                            <strong>${place.display_name}</strong>
                            <small>${place.type}</small>
                        `;
                        
                        suggestionItem.addEventListener('click', () => {
                            // Move map to the selected location
                            const lat = parseFloat(place.lat);
                            const lon = parseFloat(place.lon);
                            
                            map.setView([lat, lon], 15);
                            marker.setLatLng([lat, lon]);
                            updateCoordinates(lat, lon);
                            
                            // Update search input with selected location
                            document.getElementById('map-search').value = place.display_name;
                            
                            // Hide suggestions
                            suggestionsContainer.classList.remove('active');
                        });
                        
                        suggestionsContainer.appendChild(suggestionItem);
                    });
                    
                    suggestionsContainer.classList.add('active');
                })
                .catch(error => {
                    console.error('Error searching location:', error);
                    suggestionsContainer.innerHTML = '<div class="suggestion-item">Error searching location</div>';
                    suggestionsContainer.classList.add('active');
                });
        }

        function updateCoordinates(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(6);
            document.getElementById('longitude').value = lng.toFixed(6);
            validateForm(); // Update form validation if needed
        }

        // Initialize map when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Add event listeners to coordinate inputs to update marker position
            document.getElementById('latitude').addEventListener('change', updateMarkerFromInputs);
            document.getElementById('longitude').addEventListener('change', updateMarkerFromInputs);
        });

        function updateMarkerFromInputs() {
            const lat = parseFloat(document.getElementById('latitude').value);
            const lng = parseFloat(document.getElementById('longitude').value);
            
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            }
        }

        // Get DOM elements
        const cityMunicipalitySelect = document.getElementById('cityMunicipality');
        const barangaySelect = document.getElementById('barangay');
        const toggleOtherCity = document.getElementById('toggle-other-city');
        const otherCityContainer = document.getElementById('other-city-container');
        const otherCityInput = document.getElementById('otherCity');
        const toggleOtherBarangay = document.getElementById('toggle-other-barangay');
        const otherBarangayContainer = document.getElementById('other-barangay-container');
        const otherBarangayInput = document.getElementById('otherBarangay');
        const mangroveAreaInput = document.getElementById('mangroveArea');
        const profileDateInput = document.getElementById('profileDate');
        const speciesCheckboxes = document.querySelectorAll('input[name="species"]');
        const otherSpeciesCheckbox = document.getElementById('species-other-checkbox');
        const otherSpeciesInputContainer = document.getElementById('other-species-input-container');
        const otherSpeciesInput = document.getElementById('otherSpecies');
        const fileUploadInput = document.getElementById('file-upload');
        const fileList = document.getElementById('file-list');
        const speciesError = document.getElementById('species-error');

        const summaryBarangay = document.getElementById('summary-barangay');
        const summaryCity = document.getElementById('summary-city');
        const summaryArea = document.getElementById('summary-area');
        const summaryDate = document.getElementById('summary-date');
        const summarySpecies = document.getElementById('summary-species');

        const qrcodeCanvas = document.getElementById('qrcode');
        const downloadQrButton = document.getElementById('downloadQr');
        const finalizeButton = document.getElementById('finalizeButton');
        const cancelButton = document.getElementById('cancelButton');
        let qr = new QRious({
            element: qrcodeCanvas,
            size: 250,
            value: 'Mangrove Profile Data Will Appear Here', // Initial placeholder value
            level: 'H'
        });

        // Function to update the barangay dropdown based on selected city
        function updateBarangayOptions() {
            const selectedCity = cityMunicipalitySelect.value;
            barangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>'; // Clear existing options

            if (selectedCity) {
                // Fetch barangays for the selected city from the server
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
            updateSummary(); // Update summary when city changes
        }

        // Function to update the profile summary
        function updateSummary() {
            const selectedCity = toggleOtherCity.checked 
                ? otherCityInput.value 
                : cityMunicipalitySelect.value;
            
            const selectedBarangay = toggleOtherBarangay.checked 
                ? otherBarangayInput.value 
                : barangaySelect.value;
            const area = mangroveAreaInput.value;
            const profileDate = profileDateInput.value;

            summaryBarangay.textContent = selectedBarangay || 'N/A';
            summaryCity.textContent = selectedCity || 'N/A';
            summaryArea.textContent = area ? `${parseFloat(area).toFixed(2)}` : 'N/A';
            summaryDate.textContent = profileDate || 'N/A';

            const selectedSpecies = Array.from(speciesCheckboxes)
                .filter(checkbox => checkbox.checked && checkbox.value !== '')
                .map(checkbox => checkbox.value);

            if (otherSpeciesCheckbox.checked && otherSpeciesInput.value.trim() !== '') {
                selectedSpecies.push(otherSpeciesInput.value.trim());
            }

            summarySpecies.textContent = selectedSpecies.length > 0 ? selectedSpecies.join(', ') : 'N/A';

            validateForm(); // Re-validate form on summary update
        }

        // Function to check for existing profiles
        async function checkForExistingProfile() {
            const cityValue = toggleOtherCity.checked 
                ? otherCityInput.value.trim()
                : cityMunicipalitySelect.value;
            
            const barangayValue = toggleOtherBarangay.checked 
                ? otherBarangayInput.value.trim()
                : barangaySelect.value;
            
            if (!cityValue || !barangayValue) {
                hideExistingProfileWarning();
                return;
            }
            
            try {
                const response = await fetch('check_existing_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        city: cityValue,
                        barangay: barangayValue
                    })
                });
                
                if (!response.ok) {
                    console.error('Failed to check existing profile');
                    return;
                }
                
                const result = await response.json();
                
                if (result.exists) {
                    showExistingProfileWarning(result);
                } else {
                    hideExistingProfileWarning();
                }
                
            } catch (error) {
                console.error('Error checking existing profile:', error);
            }
        }

        // Function to show existing profile warning
        function showExistingProfileWarning(profileData) {
            hideExistingProfileWarning(); // Remove any existing warning
            
            const formContainer = document.querySelector('.form-container');
            const warning = document.createElement('div');
            warning.id = 'existing-profile-warning';
            warning.className = 'alert alert-warning';
            warning.style.cssText = 'margin-bottom: 20px; padding: 15px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;';
            
            const adminButtons = profileData.is_admin ? `
                <button type="button" class="edit-profile-btn" onclick="window.open('edit_mangrove_profile.php?profile_id=${profileData.profile_id}', '_blank')" style="margin-right: 8px;">
                    <i class="fas fa-edit"></i> Edit Existing Profile
                </button>
            ` : '';
            
            warning.innerHTML = `
                <div style="display: flex; align-items: start; gap: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #f39c12; margin-top: 2px; font-size: 18px;"></i>
                    <div style="flex: 1;">
                        <strong>Existing Profile Found</strong>
                        <p style="margin: 5px 0 10px 0;">
                            An active mangrove profile already exists for <strong>${profileData.barangay}, ${profileData.city_municipality}</strong> 
                            (created on ${new Date(profileData.profile_date).toLocaleDateString()}).
                        </p>
                        <p style="margin: 0 0 10px 0; font-size: 0.9em;">
                            Only one active profile per barangay is allowed. You cannot create another profile until the existing one is archived.
                        </p>
                        <div style="margin-top: 10px; display: flex; flex-direction: row; ">
                            <button type="button" class="view-existing-profile-btn" onclick="window.open('view_barangay_profile.php?profile_key=${profileData.profile_key}', '_blank')" style="margin-right: 8px;">
                                <i class="fas fa-eye"></i> View Existing Profile
                            </button>
                            ${adminButtons}
                        </div>
                    </div>
                </div>
            `;
            
            formContainer.insertBefore(warning, formContainer.firstChild);
            
            // Disable the finalize button
            if (finalizeButton) {
                finalizeButton.disabled = true;
                finalizeButton.innerHTML = 'Cannot Create - Profile Exists';
            }
        }

        // Function to hide existing profile warning
        function hideExistingProfileWarning() {
            const existingWarning = document.getElementById('existing-profile-warning');
            if (existingWarning) {
                existingWarning.remove();
            }
            
            // Re-enable the finalize button and validate form
            if (finalizeButton) {
                validateForm(); // This will re-enable if other conditions are met
            }
        }

        // Function to calculate total mangrove area for selected barangay and city
        async function calculateMangroveAreaForBarangay() {
            try {
                const cityValue = toggleOtherCity.checked 
                    ? otherCityInput.value.trim()
                    : cityMunicipalitySelect.value;
                
                const barangayValue = toggleOtherBarangay.checked 
                    ? otherBarangayInput.value.trim()
                    : barangaySelect.value;
                
                if (!cityValue || !barangayValue) {
                    mangroveAreaInput.value = '';
                    hideAreaBreakdown();
                    return;
                }
                
                // Use the dedicated endpoint for calculation
                const response = await fetch('calculate_barangay_area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        city: cityValue,
                        barangay: barangayValue
                    })
                });
                
                if (!response.ok) {
                    console.error('Failed to calculate area');
                    mangroveAreaInput.value = '0.00';
                    hideAreaBreakdown();
                    return;
                }
                
                const result = await response.json();
                
                if (result.success) {
                    // Use distributed area as the main value (for consistency with view page)
                    mangroveAreaInput.value = result.distributed_total_hectares.toFixed(2);
                    
                    // Update breakdown display
                    updateAreaBreakdown(result);
                    
                    console.log(`Calculated area for ${barangayValue}, ${cityValue}: Raw=${result.raw_total_hectares} ha, Distributed=${result.distributed_total_hectares} ha from ${result.areas_found} areas`);
                } else {
                    console.error('Calculation error:', result.error);
                    mangroveAreaInput.value = '0.00';
                    hideAreaBreakdown();
                }
                
                updateSummary();
                
            } catch (error) {
                console.error('Error calculating mangrove area:', error);
                mangroveAreaInput.value = '0.00';
                hideAreaBreakdown();
            }
        }

        // Function to update area breakdown display
        function updateAreaBreakdown(result) {
            document.getElementById('raw-area-value').textContent = result.raw_total_hectares.toFixed(2);
            document.getElementById('distributed-area-value').textContent = result.distributed_total_hectares.toFixed(2);
            
            // Show additional information if there are shared areas
            const hasSharedAreas = result.areas.some(area => area.shared_with_count > 1);
            if (hasSharedAreas) {
                const breakdown = document.getElementById('area-breakdown');
                let sharedInfo = breakdown.querySelector('.shared-areas-info');
                if (!sharedInfo) {
                    sharedInfo = document.createElement('div');
                    sharedInfo.className = 'shared-areas-info';
                    sharedInfo.style.cssText = 'font-size: 0.75rem; color: #dc3545; margin-top: 4px; padding: 4px; background-color: #fff3cd; border-radius: 3px;';
                    breakdown.appendChild(sharedInfo);
                }
                
                const sharedAreas = result.areas.filter(area => area.shared_with_count > 1);
                sharedInfo.innerHTML = `⚠️ ${sharedAreas.length} area(s) shared with other barangays`;
            }
        }

        // Function to hide area breakdown
        function hideAreaBreakdown() {
            document.getElementById('area-breakdown').style.display = 'none';
            document.getElementById('raw-area-value').textContent = '--';
            document.getElementById('distributed-area-value').textContent = '--';
        }

        // Function to validate the form and enable/disable the finalize button
        function validateForm() {
            // Get the correct city value (either from dropdown or other input)
            const isCitySelected = toggleOtherCity.checked 
                ? otherCityInput.value.trim() !== ''
                : cityMunicipalitySelect.value !== '';
            
            // Get the correct barangay value (either from dropdown or other input)
            const isBarangaySelected = toggleOtherBarangay.checked 
                ? otherBarangayInput.value.trim() !== ''
                : barangaySelect.value !== '';
            
            const isAreaValid = mangroveAreaInput.value !== '' && parseFloat(mangroveAreaInput.value) > 0;
            const isDateSelected = profileDateInput.value !== '';
            
            const selectedSpeciesCount = Array.from(speciesCheckboxes).filter(cb => cb.checked).length;
            const isOtherSpeciesProvided = otherSpeciesCheckbox.checked && otherSpeciesInput.value.trim() !== '';
            const isSpeciesSelected = selectedSpeciesCount > 0 || isOtherSpeciesProvided;

            const isLatValid = !isNaN(parseFloat(document.getElementById('latitude').value));
            const isLngValid = !isNaN(parseFloat(document.getElementById('longitude').value));
            
            // Check if there's an existing profile warning
            const hasExistingProfileWarning = document.getElementById('existing-profile-warning') !== null;
            
            if (isSpeciesSelected) {
                speciesError.classList.add('hidden');
            } else {
                speciesError.classList.remove('hidden');
            }

            if (isCitySelected && isBarangaySelected && isAreaValid && isDateSelected && isSpeciesSelected && isLatValid && isLngValid && !hasExistingProfileWarning) {
                finalizeButton.disabled = false;
                finalizeButton.classList.remove('bg-emerald-600/50', 'cursor-not-allowed');
                finalizeButton.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
                finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
                // Generate QR code preview if valid
                generateQrCode();
                downloadQrButton.style.display = 'inline-flex';
            } else {
                finalizeButton.disabled = true;
                finalizeButton.classList.add('bg-emerald-600/50', 'cursor-not-allowed');
                finalizeButton.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                
                if (hasExistingProfileWarning) {
                    finalizeButton.innerHTML = 'Cannot Create - Profile Exists';
                } else {
                    finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
                }
                
                // Reset QR code
                qr.value = 'Fill all required fields to generate QR';
                qr.update();
                downloadQrButton.style.display = 'none';
            }
        }

        // Function to generate the QR code
        function generateQrCode() {
            // Get the correct city value (either from dropdown or other input)
            const cityValue = toggleOtherCity.checked 
                ? otherCityInput.value 
                : cityMunicipalitySelect.value;
            
            // Get the correct barangay value (either from dropdown or other input)
            const barangayValue = toggleOtherBarangay.checked 
                ? otherBarangayInput.value 
                : barangaySelect.value;
            
            const data = {
                barangay: barangayValue,
                city: cityValue,
                area: mangroveAreaInput.value,
                date: profileDateInput.value,
                species: Array.from(speciesCheckboxes).filter(cb => cb.checked).map(cb => cb.value)
            };
            
            if (otherSpeciesCheckbox.checked && otherSpeciesInput.value.trim() !== '') {
                data.species.push(otherSpeciesInput.value.trim());
            }

            // Generate a profile key for the QR code (this will be recreated on the server)
            const profileKey = generateProfileKey(barangayValue, cityValue);
            
            // Generate a URL for the public profile page using profile key
            const profileUrl = `http://mangrow.42web.io/view_barangay_profile.php?profile_key=${encodeURIComponent(profileKey)}`;

            qr.value = profileUrl;
            qr.update();
            downloadQrButton.href = qrcodeCanvas.toDataURL('image/png');
        }

        let currentProfileKey = null;

        function generateProfileKey(barangay, city) {
            if (!currentProfileKey) {
                // Create a clean key from barangay and city
                const cleanBarangay = barangay.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                
                const cleanCity = city.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
                
                // Add timestamp and random string for uniqueness
                const timestamp = Date.now();
                const random = Math.random().toString(36).substring(2, 8);
                
                currentProfileKey = `${cleanBarangay}-${cleanCity}-${timestamp}-${random}`.substring(0, 100);
            }
            return currentProfileKey;
        }

        function submitForm(formData, attempt = 1) {
            if (attempt > 3) {
                alert('Failed to save after multiple attempts. Please try again.');
                finalizeButton.disabled = false;
                finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
                return;
            }

            fetch('save_mangrove_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = `view_barangay_profile.php?profile_key=${data.profile_key}`;
                } else if (data.status === 'duplicate_profile') {
                    // Handle duplicate profile error with a more informative message
                    const message = `${data.message}\n\nWould you like to:\n- View the existing profile\n- Contact an administrator to archive the existing profile first`;
                    
                    if (confirm(message + '\n\nClick OK to view the existing profile, or Cancel to return to the form.')) {
                        // Redirect to view the existing profile (we'll need to get the profile key)
                        // For now, redirect to the profiles list or main page
                        window.location.href = 'adminpage.php';
                    } else {
                        // Reset the form button
                        finalizeButton.disabled = false;
                        finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
                    }
                } else if (data.status === 'key_conflict') {
                    // Clear existing key to force regeneration
                    currentProfileKey = null;
                    
                    // Get current values
                    const cityValue = toggleOtherCity.checked 
                        ? otherCityInput.value 
                        : cityMunicipalitySelect.value;
                    const barangayValue = toggleOtherBarangay.checked 
                        ? otherBarangayInput.value 
                        : barangaySelect.value;
                    
                    // Generate new key and update QR
                    currentProfileKey = generateProfileKey(barangayValue, cityValue);
                    generateQrCode();
                    
                    // Update form data with new key
                    formData.set('profile_key', currentProfileKey);
                    formData.set('qr_code', qrcodeCanvas.toDataURL('image/png'));
                    
                    // Resubmit with new key
                    submitForm(formData, attempt + 1);
                } else {
                    alert('Error saving profile: ' + data.message);
                    finalizeButton.disabled = false;
                    finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the profile.');
                finalizeButton.disabled = false;
                finalizeButton.innerHTML = 'Finalize Profile & Generate QR';
            });
        }

        // Event Listeners
        cityMunicipalitySelect.addEventListener('change', () => {
            updateBarangayOptions();
            updateSummary();
            calculateMangroveAreaForBarangay();
            checkForExistingProfile();
        });
        
        otherCityInput.addEventListener('input', () => {
            updateSummary();
            calculateMangroveAreaForBarangay();
            checkForExistingProfile();
        });

        barangaySelect.addEventListener('change', () => {
            updateSummary();
            calculateMangroveAreaForBarangay();
            checkForExistingProfile();
        });
        
        otherBarangayInput.addEventListener('input', () => {
            updateSummary();
            calculateMangroveAreaForBarangay();
            checkForExistingProfile();
        });
        mangroveAreaInput.addEventListener('input', updateSummary);
        profileDateInput.addEventListener('change', updateSummary);

        speciesCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSummary);
        });

        otherSpeciesCheckbox.addEventListener('change', () => {
            otherSpeciesInputContainer.style.display = otherSpeciesCheckbox.checked ? 'block' : 'none';
            if (!otherSpeciesCheckbox.checked) {
                otherSpeciesInput.value = ''; // Clear other species input if unchecked
            }
            updateSummary();
        });
        otherSpeciesInput.addEventListener('input', updateSummary);


        fileUploadInput.addEventListener('change', (event) => {
            fileList.innerHTML = ''; // Clear previous list
            Array.from(event.target.files).forEach(file => {
                const listItem = document.createElement('li');
                listItem.textContent = file.name;
                fileList.appendChild(listItem);
            });
        });

        finalizeButton.addEventListener('click', () => {
            if (!finalizeButton.disabled) {
                // Get the correct values for city and barangay
                const cityValue = toggleOtherCity.checked 
                    ? otherCityInput.value 
                    : cityMunicipalitySelect.value;
                
                const barangayValue = toggleOtherBarangay.checked 
                    ? otherBarangayInput.value 
                    : barangaySelect.value;
                
                // Generate profile key if not already exists
                currentProfileKey = generateProfileKey(barangayValue, cityValue);
                
                // Get selected species
                const selectedSpecies = Array.from(speciesCheckboxes)
                    .filter(cb => cb.checked && cb.value !== '')
                    .map(cb => cb.value);
                
                if (otherSpeciesCheckbox.checked && otherSpeciesInput.value.trim() !== '') {
                    selectedSpecies.push(otherSpeciesInput.value.trim());
                }
                
                // Get coordinates
                const latitude = document.getElementById('latitude').value;
                const longitude = document.getElementById('longitude').value;
                
                // Create FormData object to send via AJAX
                const formData = new FormData();
                formData.append('barangay', barangayValue);
                formData.append('city_municipality', cityValue);
                formData.append('mangrove_area', mangroveAreaInput.value);
                formData.append('profile_date', profileDateInput.value);
                formData.append('species_present', selectedSpecies.join(','));
                formData.append('latitude', latitude);
                formData.append('longitude', longitude);
                formData.append('profile_key', currentProfileKey);
                formData.append('qr_code', qrcodeCanvas.toDataURL('image/png'));
                
                // Add files if any
                Array.from(fileUploadInput.files).forEach((file, index) => {
                    formData.append(`photos[]`, file);
                });
                
                // Show loading state
                finalizeButton.disabled = true;
                finalizeButton.innerHTML = 'Saving...';
                
                // Submit the form
                submitForm(formData);
            }
        });

        // Event listener for cancel button click
        cancelButton.addEventListener('click', () => {
            if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
                // Clear the form
                document.getElementById('cityMunicipality').value = '';
                document.getElementById('barangay').innerHTML = '<option value="" disabled selected>Select Barangay</option>';
                document.getElementById('mangroveArea').value = '';
                document.getElementById('profileDate').value = '';
                speciesCheckboxes.forEach(cb => cb.checked = false);
                otherSpeciesCheckbox.checked = false;
                otherSpeciesInput.value = '';
                otherSpeciesInputContainer.style.display = 'none';
                fileUploadInput.value = '';
                fileList.innerHTML = '';
                updateSummary(); // Reset summary
                alert('Form cleared.'); // Use custom modal in production
                // history.back(); // Or redirect to the main profiles page
            }
        });

        // Initial setup
        document.addEventListener('DOMContentLoaded', () => {
            updateSummary();
            validateForm(); // Ensure button state is correct on load
        });

        // Polyfill for confirm 
        window.confirm = (message) => {
            const response = window.prompt(`${message}\nType 'yes' to confirm, or anything else to cancel.`);
            return response && response.toLowerCase() === 'yes';
        };

        // Custom alert 
        window.alert = (message) => {
            const alertBox = document.createElement('div');
            alertBox.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: #fff;
                padding: 24px 36px;
                border-radius: 8px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.18);
                z-index: 1000;
                font-family: 'Lexend', sans-serif;
                color: #222;
                text-align: center;
                max-width: 400px;
            `;
            alertBox.innerHTML = `
                <p style="font-size: 1.15rem; font-weight: 600; margin-bottom: 18px;">${message}</p>
                <button style="
                    padding: 10px 28px;
                    background: #059669;
                    color: #fff;
                    border: none;
                    border-radius: 5px;
                    font-size: 1rem;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">OK</button>
            `;
            document.body.appendChild(alertBox);

            alertBox.querySelector('button').onclick = () => {
                document.body.removeChild(alertBox);
            };
        };

        // Event listener for city dropdown change
        cityMunicipalitySelect.addEventListener('change', () => {
            const isOtherSelected = cityMunicipalitySelect.value === 'other';
            otherCityContainer.style.display = isOtherSelected ? 'block' : 'none';
            if (!isOtherSelected) {
                otherCityInput.value = ''; // Clear other city input if not selected
            }
            updateBarangayOptions();
            updateSummary();
        });

        // Event listener for barangay dropdown change
        barangaySelect.addEventListener('change', () => {
            const isOtherSelected = barangaySelect.value === 'other';
            otherBarangayContainer.style.display = isOtherSelected ? 'block' : 'none';
            if (!isOtherSelected) {
                otherBarangayInput.value = ''; // Clear other barangay input if not selected
            }
            updateSummary();
        });

        // Event listeners for other input fields
        otherCityInput.addEventListener('input', updateSummary);
        otherBarangayInput.addEventListener('input', updateSummary);
        
        // Event listener for city toggle
        toggleOtherCity.addEventListener('change', () => {
            otherCityContainer.style.display = toggleOtherCity.checked ? 'block' : 'none';
            cityMunicipalitySelect.disabled = toggleOtherCity.checked;
            if (toggleOtherCity.checked) {
                cityMunicipalitySelect.value = '';
            }
            updateSummary();
        });

        // Event listener for barangay toggle
        toggleOtherBarangay.addEventListener('change', () => {
            otherBarangayContainer.style.display = toggleOtherBarangay.checked ? 'block' : 'none';
            barangaySelect.disabled = toggleOtherBarangay.checked;
            if (toggleOtherBarangay.checked) {
                barangaySelect.value = '';
            }
            updateSummary();
        });

        // Event listener for area breakdown toggle
        document.getElementById('show-area-breakdown').addEventListener('click', (e) => {
            e.preventDefault();
            const breakdown = document.getElementById('area-breakdown');
            const link = e.target;
            
            if (breakdown.style.display === 'none') {
                breakdown.style.display = 'block';
                link.textContent = 'Hide calculation details';
            } else {
                breakdown.style.display = 'none';
                link.textContent = 'Show calculation details';
            }
        });
        
    </script>
</body>
</html>
