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
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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
            <a href="initiatives.php">Initiatives</a>
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
        <!-- events form page container-->
        <div class="another-event-form-container">
            <h2>Create New Post</h2>
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
                        <label for="event-type">Event Type <span>(Please specify your event type below)</span></label>
                        <input type="text" id="event-type" name="event_type" placeholder="e.g. Tree Planting, Coastal Clean-up, Mangrove Nursery Establishment, Mangrove Monitoring, etc." required>
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
                       <label for="venue">Venue*</label>
                        <div class="venue-input-container">
                            <input type="text" id="venue" name="venue" required>
                            <button type="button" id="map-button" class="map-button">
                                <i class="fas fa-map-marker-alt"></i> <!-- Font Awesome icon -->
                            </button>
                        </div>
                        <!-- Hidden fields for coordinates -->
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                    </div>
                    <div class="form-group">
                        <label for="area-no">Area No</label>
                        <input type="text" id="area-no" name="area_no">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City/Municipality*</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay*</label>
                        <input type="text" id="barangay" name="barangay" required>
                    </div>
                </div>
                <!-- Eco Points -->
                <div class="form-group">
                    <label for="eco-points">Eco Points</label>
                    <input type="number" id="eco-points" name="eco_points" placeholder="Enter eco points (optional)" min="0" step="1">
                </div>
                <!-- Submit Button -->
                <div class="form-group">
                    <button type="submit" name="submit" class="submit-btn">Create Post</button>
                </div>
            </form>
            <div id="map-modal" class="map-modal">
                <div class="map-modal-content">
                    <span class="close-modal">&times;</span>
                    <div id="map" style="height: 400px; width: 100%;"></div>
                    <button id="confirm-location" class="confirm-btn">Confirm Location</button>
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
            const programTypeSelect = document.getElementById('program-type');
            const eventTypeContainer = document.querySelector('.event-type-container');
            const eventTypeInput = document.getElementById('event-type');
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
                    
                    // Re-add required attributes to event-specific fields (excluding area-no)
                    document.getElementById('start-date').setAttribute('required', 'required');
                    document.getElementById('venue').setAttribute('required', 'required');
                    document.getElementById('city').setAttribute('required', 'required');
                    document.getElementById('barangay').setAttribute('required', 'required');
                    
                    // Ensure area-no is not required
                    areaNoInput.removeAttribute('required');
                } else {
                    // Hide event-specific fields
                    eventTypeContainer.style.display = 'none';
                    eventTypeInput.removeAttribute('required');
                    eventTypeInput.value = '';
                    dateContainer.style.display = 'none';
                    locationContainer.forEach(container => container.style.display = 'none');
                    ecoPointsContainer.style.display = 'none';
                    
                    // Clear values and remove required attributes from hidden fields
                    document.querySelectorAll('.form-group-dates input').forEach(input => {
                        input.value = '';
                        input.removeAttribute('required');
                    });
                    
                    document.querySelectorAll('.form-row input').forEach(input => {
                        input.value = '';
                        if (input.id === 'venue' || input.id === 'city' || input.id === 'barangay') {
                            input.removeAttribute('required');
                        }
                    });
                    
                    document.getElementById('eco-points').value = '';
                    
                    // Ensure area-no is not required
                    areaNoInput.removeAttribute('required');
                }
            }

            // Initial toggle on page load
            toggleFields();
            
            // Toggle fields when program type changes
            programTypeSelect.addEventListener('change', toggleFields);

            // Form submission handler
            form.addEventListener('submit', function(e) {
                // For Announcements, manually clear validation for hidden fields
                if (programTypeSelect.value === 'Announcement') {
                    document.getElementById('start-date').removeAttribute('required');
                    document.getElementById('venue').removeAttribute('required');
                    document.getElementById('city').removeAttribute('required');
                    document.getElementById('barangay').removeAttribute('required');
                }
                
                // Ensure area-no is never required on submission
                areaNoInput.removeAttribute('required');
                
                // Validate links if they exist
                const linksTextarea = document.getElementById('link');
                if (linksTextarea) {
                    const links = linksTextarea.value.split('\n').filter(link => link.trim() !== '');
                    for (const link of links) {
                        if (!link.match(/^https?:\/\/.+/)) {
                            alert(`Invalid URL: ${link}\nAll links must start with http:// or https://`);
                            e.preventDefault();
                            return;
                        }
                    }
                }
            });
        });
    </script>
    <!-- map script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mapButton = document.getElementById('map-button');
            const mapModal = document.getElementById('map-modal');
            const closeModal = document.querySelector('.close-modal');
            const confirmBtn = document.getElementById('confirm-location');
            const venueInput = document.getElementById('venue');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            
            let map;
            let marker;
            
            // Open modal when map button is clicked
            mapButton.addEventListener('click', function() {
                mapModal.style.display = 'block';
                // Initialize map after modal is displayed to ensure proper dimensions
                setTimeout(initMap, 10);
            });
            
            // Close modal when X is clicked
            closeModal.addEventListener('click', function() {
                mapModal.style.display = 'none';
                if (map) map.invalidateSize(); // Reset map size when hidden
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target == mapModal) {
                    mapModal.style.display = 'none';
                    if (map) map.invalidateSize(); // Reset map size when hidden
                }
            });
            
            function initMap() {
                if (map) {
                    map.remove();
                }
                
                // Create map centered on a default location
                map = L.map('map').setView([14.64852, 120.47318], 12); // Default to Dangcol, Balanga
                
                // Add tile layer (OpenStreetMap)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);
                
                // Check if we have existing coordinates
                if (latitudeInput.value && longitudeInput.value) {
                    const existingLat = parseFloat(latitudeInput.value);
                    const existingLng = parseFloat(longitudeInput.value);
                    marker = L.marker([existingLat, existingLng], {
                        draggable: true
                    }).addTo(map);
                    map.setView([existingLat, existingLng], 15);
                }
                
                // Add click event to place marker
                map.on('click', function(e) {
                    placeMarker(e.latlng);
                });
            }
            
            function placeMarker(latlng) {
                if (marker) {
                    map.removeLayer(marker);
                }
                
                marker = L.marker(latlng, {
                    draggable: true
                }).addTo(map);
                
                // Update coordinates when marker is dragged
                marker.on('dragend', function(e) {
                    const newLatLng = e.target.getLatLng();
                    updateCoordinates(newLatLng.lat, newLatLng.lng);
                });
            }
            
            function updateCoordinates(lat, lng) {
                // Round to 6 decimal places (about 10cm precision)
                const roundedLat = parseFloat(lat.toFixed(6));
                const roundedLng = parseFloat(lng.toFixed(6));
                
                latitudeInput.value = roundedLat;
                longitudeInput.value = roundedLng;
            }
            
            // Confirm location button
            confirmBtn.addEventListener('click', function() {
                if (marker) {
                    const latlng = marker.getLatLng();
                    updateCoordinates(latlng.lat, latlng.lng);
                    
                    // Reverse geocode to get address
                    reverseGeocode(latlng.lat, latlng.lng);
                    
                    mapModal.style.display = 'none';
                    if (map) map.invalidateSize(); // Reset map size when hidden
                } else {
                    alert('Please select a location on the map first');
                }
            });
            
            function reverseGeocode(lat, lng) {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.display_name) {
                            venueInput.value = data.display_name;
                        }
                    })
                    .catch(error => {
                        console.error('Error reverse geocoding:', error);
                        venueInput.value = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    });
            }
        });
    </script>
</body>
</html>