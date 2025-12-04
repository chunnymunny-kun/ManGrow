<!--
<?php
    session_start();
    include 'database.php';
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
    <link rel="stylesheet" href="eventform.css">
    <link rel="stylesheet" href="event_location_manager.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>

    <script type ="text/javascript" src ="app.js" defer></script>
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
        <!-- events form page container-->
        <div class="another-event-form-container">
            <h2>Create New Event/Activity</h2>
            <form id="event-creation-form" enctype="multipart/form-data" action="uploadevent.php" method="post">
                <!-- Event Thumbnail -->
                <div class="form-group thumbnail">
                    <div class="thumbnail-preview-container" onclick="document.getElementById('thumbnail').click()">
                        <!-- Default placeholder -->
                        <div class="thumbnail-placeholder">
                            <span>Click to upload thumbnail</span>
                        </div>
                        <img id="thumbnail-preview" class="thumbnail-preview" style="display: none;">
                        <div class="thumbnail-actions" style="display: none;">
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
                    <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp,image/svg+xml" required style="display: none;" onchange="validateImage(this)">
                </div>
                <!-- Event Title -->
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" placeholder="Write the title of the post" required>
                </div>
                <!-- Event Program Type -->
                <div class="form-group">
                    <label for="program-type">Program Type</label>
                    <select id="program-type" name="program_type" required>
                        <option value="" disabled selected>Select program type</option>
                        <option value="Event">Event</option>
                        <option value="Announcement">Announcement</option>
                    </select>
                    <!-- when the selected program-type is event, ask the user the type of the event -->
                    <div class="event-type-container" style="display: none;">
                        <div class="event-type-mode-header">
                            <label for="event-type">Event Type</label>
                            <div class="event-type-toggle-container">
                                <label class="event-type-mode-switch">
                                    <input type="checkbox" id="event-type-mode-toggle">
                                    <span class="slider"></span>
                                </label>
                                <span id="event-type-mode-label">Switch to Manual Input</span>
                            </div>
                        </div>
                        
                        <!-- Dropdown for mangrove area events -->
                        <div id="select-event-type-container">
                            <div class="event-type-indicator mangrove-area">
                                <i class="fas fa-seedling"></i>
                                <span>Mangrove Area Events (Select from list)</span>
                            </div>
                            <select id="event-type" name="event_type" required>
                                <option value="" disabled selected>Select event type</option>
                                <option value="Tree Planting">Tree Planting</option>
                                <option value="Reforestation Drive">Reforestation Drive</option>
                                <option value="Coastal Clean-up">Coastal Clean-up</option>
                                <option value="Mangrove Monitoring">Mangrove Monitoring</option>
                                <option value="Wildlife Observation">Wildlife Observation</option>
                                <option value="Nursery Establishment">Nursery Establishment</option>
                            </select>
                        </div>
                        
                        <!-- Manual input for non-mangrove area events -->
                        <div id="manual-event-type-container" style="display: none;">
                            <div class="event-type-indicator non-mangrove-area">
                                <i class="fas fa-keyboard"></i>
                                <span>Non-Mangrove Area Events (Manual input)</span>
                            </div>
                            <input type="text" id="manual-event-type" name="manual_event_type" placeholder="e.g. Seminar, Workshop, Awareness Campaign, Training, Community Meeting, etc.">
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
                        <input type="datetime-local" id="start-date" name="start_date" required min="<?= $tomorrow ?>">
                    </div>
                    <div class="date-input-group">
                        <label for="end-date">End Date*</label>
                        <input type="datetime-local" id="end-date" name="end_date" required min="<?= $tomorrow ?>">
                    </div>
                </div>
                <!-- Event Description -->
                <div class="form-group">
                    <label for="description">Description*</label>
                    <textarea id="description" name="description" rows="4" placeholder="Write your description/instructions about the post here" required></textarea>
                </div>
                <!-- Event Link -->
                <div class="form-group">
                    <label for="link">Attached Event Link/s</label>
                    <textarea id="link" name="link" rows="3" placeholder="Add links related to the post (one per line)&#10;Example:&#10;https://example.com/event-info&#10;https://example.com/registration"></textarea>
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
                            <input type="text" id="venue" name="venue" placeholder="Click map button to select venue">
                            <button type="button" id="map-button" class="map-button" title="Select location from map">
                                <i class="fas fa-map-marker-alt"></i>
                            </button>
                        </div>
                        <small id="venue-instruction" class="instruction-text" style="display: none;">
                            <i class="fas fa-info-circle"></i> Manual mode: Type venue name, then enter city/municipality and barangay below. Click map to pin location (optional).
                        </small>
                        <!-- Hidden fields for coordinates -->
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                    <div class="form-group">
                        <label for="area-no">Area No</label>
                        <div class="area-input-container">
                            <input type="text" id="area-no" name="area_no" placeholder="Select mangrove area" readonly>
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
                        <input type="text" id="city" name="city" class="location-input" title="Auto-filled from map">
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay* <span id="barangay-hint">(Auto-filled from map)</span></label>
                        <input type="text" id="barangay" name="barangay" class="location-input" title="Auto-filled from map">
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
                            <input type="checkbox" id="cross-barangay-toggle">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <!-- Eco Points -->
                <div class="form-group">
                    <label for="eco-points">Eco Points</label>
                    <input type="number" id="eco-points" name="eco_points" placeholder="Enter eco points (optional)" min="0" step="1">
                </div>
                <!-- Cross-barangay Event Section -->
                <div id="cross-barangay-section" style="display: none;">
                    <div class="form-group">
                        <h3>Additional Requirements for Cross-Barangay Events</h3>
                        <p>Since this event is outside your registered barangay, please provide supporting documents:</p>
                        
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
                            
                            <div id="file-preview-container" class="file-preview-container"></div>
                            <div id="size-warning" class="size-warning" style="display: none;"></div>
                            <div id="file-counter" class="file-counter" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="special-notes">Additional Notes for Approvers</label>
                            <textarea id="special-notes" name="special_notes" rows="3" 
                                    placeholder="Explain why you're conducting this event outside your barangay..."></textarea>
                        </div>
                    </div>
                </div>
                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" name="submit" class="submit-btn">Create Event/Activity</button>
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
    <script type="text/javascript" src="events.js"></script>
    <!-- event form scripts -->
    <script>
        function validateImage(input) {
            const errorElement = document.getElementById('thumbnail-error');
            const validImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            
            if (input.files && input.files[0]) {
                // Check if file is an image
                if (!validImageTypes.includes(input.files[0].type)) {
                    // Show error message
                    errorElement.textContent = 'Please select a valid image file (JPEG, PNG, WEBP, SVG)';
                    errorElement.style.display = 'block';
                    
                    // Reset input
                    input.value = '';
                    
                    // Hide error after 3 seconds
                    setTimeout(() => {
                        errorElement.style.display = 'none';
                    }, 3000);
                    
                    return false;
                }
                
                // If valid image, proceed with preview
                errorElement.style.display = 'none';
                previewThumbnail(input);
                return true;
            }
        }
    
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
            const preview = document.getElementById('thumbnail-preview');
            const placeholder = document.querySelector('.thumbnail-placeholder');
            const fileInput = document.getElementById('thumbnail');
            const actions = document.querySelector('.thumbnail-actions');
            const errorElement = document.getElementById('thumbnail-error');
            
            // Reset everything
            fileInput.value = '';
            preview.style.display = 'none';
            preview.src = '';
            placeholder.style.display = 'flex';
            actions.style.display = 'none';
            errorElement.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 ===== PAGE LOADED - CREATE EVENT =====');
            
            const programTypeSelect = document.getElementById('program-type');
            const eventTypeContainer = document.querySelector('.event-type-container');
            const eventTypeInput = document.getElementById('event-type');
            const manualEventTypeInput = document.getElementById('manual-event-type');
            const dateContainer = document.querySelector('.form-group-dates');
            const locationContainer = document.querySelectorAll('.form-row');
            const ecoPointsContainer = document.getElementById('eco-points').closest('.form-group');
            const form = document.getElementById('event-creation-form');
            const areaNoInput = document.getElementById('area-no'); // Get the area-no input
            
            console.log('📋 Form element found:', !!form);
            console.log('📋 Form ID:', form?.id);
            console.log('📋 Form action:', form?.action);
            console.log('📋 Form method:', form?.method);
            
            // Get submit button
            const submitButton = document.querySelector('.submit-btn');
            console.log('🔘 Submit button found:', !!submitButton);
            console.log('🔘 Submit button type:', submitButton?.type);
            console.log('🔘 Submit button text:', submitButton?.textContent);
            
            // Add direct click listener to submit button for debugging
            if (submitButton) {
                submitButton.addEventListener('click', function(e) {
                    console.log('🖱️ ===== SUBMIT BUTTON CLICKED =====');
                    console.log('Button type:', this.type);
                    console.log('Button disabled:', this.disabled);
                    console.log('Form validity:', form?.checkValidity());
                    
                    // Check for invalid fields
                    if (form && !form.checkValidity()) {
                        console.log('❌ Form has invalid fields:');
                        const invalidFields = form.querySelectorAll(':invalid');
                        invalidFields.forEach(field => {
                            console.log('  - Invalid field:', field.name || field.id, 
                                       '| Value:', field.value,
                                       '| Type:', field.type,
                                       '| Required:', field.required,
                                       '| Readonly:', field.readOnly);
                        });
                    } else {
                        console.log('✅ Form appears valid');
                    }
                    console.log('===== SUBMIT BUTTON CLICK END =====\n');
                });
            }

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

            // Form submission handler
            if (form) {
                console.log('✅ Adding submit event listener to form...');
            } else {
                console.error('❌ CRITICAL: Form element not found! Cannot add submit listener!');
            }
            
            form.addEventListener('submit', function(e) {
                console.log('🎯 ===== FORM SUBMIT EVENT FIRED =====');
                console.log('📝 ===== FORM SUBMISSION START =====');
                console.log('Event object:', e);
                console.log('Event type:', e.type);
                console.log('Event target:', e.target);
                console.log('Program Type:', programTypeSelect.value);
                console.log('Event Type (dropdown):', eventTypeSelect.value);
                console.log('Event Type (manual):', manualEventTypeInput.value);
                console.log('Title:', document.getElementById('title')?.value);
                console.log('Description:', document.getElementById('description')?.value);
                
                const venueInput = document.getElementById('venue');
                const cityInput = document.getElementById('city');
                const barangayInput = document.getElementById('barangay');
                
                // CRITICAL: Remove required attribute from location fields to prevent browser validation issues
                // JavaScript validation below will handle this properly
                venueInput?.removeAttribute('required');
                cityInput?.removeAttribute('required');
                barangayInput?.removeAttribute('required');
                console.log('🧹 Removed required attributes from venue/city/barangay (if any existed)');
                
                console.log('📍 Location fields:');
                console.log('  - Venue:', venueInput?.value);
                console.log('    • readonly:', venueInput?.hasAttribute('readonly'));
                console.log('    • required:', venueInput?.hasAttribute('required'));
                console.log('  - City:', cityInput?.value);
                console.log('    • readonly:', cityInput?.hasAttribute('readonly'));
                console.log('    • required:', cityInput?.hasAttribute('required'));
                console.log('  - Barangay:', barangayInput?.value);
                console.log('    • readonly:', barangayInput?.hasAttribute('readonly'));
                console.log('    • required:', barangayInput?.hasAttribute('required'));
                
                console.log('Area No:', areaNoInput.value);
                console.log('Start Date:', document.getElementById('start-date')?.value);
                console.log('End Date:', document.getElementById('end-date')?.value);
                console.log('Thumbnail file:', document.getElementById('thumbnail-upload')?.files[0]?.name);
                
                // Always ensure area-no is never required on submission
                areaNoInput.removeAttribute('required');
                
                // For Announcements, ensure all event-specific fields are not required
                if (programTypeSelect.value === 'Announcement') {
                    document.getElementById('start-date').removeAttribute('required');
                    document.getElementById('end-date').removeAttribute('required');
                    document.getElementById('venue').removeAttribute('required');
                    document.getElementById('city').removeAttribute('required');
                    document.getElementById('barangay').removeAttribute('required');
                    eventTypeSelect.removeAttribute('required');
                    manualEventTypeInput.removeAttribute('required');
                    
                    // Clear hidden field values to prevent sending empty strings
                    document.getElementById('start-date').value = '';
                    document.getElementById('end-date').value = '';
                    document.getElementById('venue').value = '';
                    document.getElementById('city').value = '';
                    document.getElementById('barangay').value = '';
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                    eventTypeSelect.value = '';
                    manualEventTypeInput.value = '';
                    areaNoInput.value = '';
                } else {
                    // For Events, validate venue/city/barangay in automatic mode
                    const venueModeToggle = document.getElementById('venue-mode-toggle');
                    const isManualMode = venueModeToggle && venueModeToggle.checked;
                    
                    console.log('🔍 Venue mode check:');
                    console.log('  - Toggle element exists:', !!venueModeToggle);
                    console.log('  - Toggle checked:', venueModeToggle?.checked);
                    console.log('  - Is manual mode:', isManualMode);
                    
                    if (!isManualMode) {
                        console.log('🗺️ AUTOMATIC MODE - Validating map-selected fields...');
                        
                        // Automatic mode - validate that fields are filled from map
                        const venueInput = document.getElementById('venue');
                        const cityInput = document.getElementById('city');
                        const barangayInput = document.getElementById('barangay');
                        
                        if (!venueInput.value || !venueInput.value.trim()) {
                            console.log('❌ VALIDATION FAILED: Venue is empty');
                            alert('Please select a venue from the map by clicking the map button.');
                            e.preventDefault();
                            return;
                        }
                        console.log('✅ Venue validation passed');
                        
                        if (!cityInput.value || !cityInput.value.trim()) {
                            console.log('❌ VALIDATION FAILED: City is empty');
                            alert('Please select a location from the map to auto-fill the city/municipality.');
                            e.preventDefault();
                            return;
                        }
                        console.log('✅ City validation passed');
                        
                        if (!barangayInput.value || !barangayInput.value.trim()) {
                            console.log('❌ VALIDATION FAILED: Barangay is empty');
                            alert('Please select a location from the map to auto-fill the barangay.');
                            e.preventDefault();
                            return;
                        }
                        console.log('✅ Barangay validation passed');
                        console.log('✅ All automatic mode validations passed!');
                    } else {
                        console.log('✋ MANUAL MODE - Using browser validation');
                    }
                }
                
                // Validate links if they exist
                console.log('🔗 Validating event links...');
                const linksTextarea = document.getElementById('link');
                if (linksTextarea && linksTextarea.value.trim()) {
                    const links = linksTextarea.value.split('\n').filter(link => link.trim() !== '');
                    console.log('  - Found', links.length, 'link(s)');
                    for (const link of links) {
                        if (!link.match(/^https?:\/\/.+/)) {
                            console.log('❌ VALIDATION FAILED: Invalid link:', link);
                            alert(`Invalid URL: ${link}\nAll links must start with http:// or https://`);
                            e.preventDefault();
                            return;
                        }
                    }
                    console.log('✅ All links valid');
                } else {
                    console.log('  - No links to validate');
                }
                
                console.log('✅✅✅ ALL VALIDATIONS PASSED! Form will now submit...');
                console.log('===== FORM SUBMISSION END =====\n');
            });
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

    // Listen for clear event from venue toggle
    window.addEventListener('clearFileUploads', function() {
        totalSize = 0;
        filePreviewContainer.innerHTML = '';
        attachmentsInput.value = '';
        fileCounter.style.display = 'none';
        sizeWarning.style.display = 'none';
    });

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
</script>
</body>
</html>