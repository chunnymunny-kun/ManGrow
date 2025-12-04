<?php
session_start();

if(isset($_SESSION["name"])){
    $loggeduser = $_SESSION["name"];
}else{
    if(isset($_SESSION['qr_url'])){
        $_SESSION['redirect_url'] = $_SESSION['qr_url'];
    }
    if(!isset($_SESSION['redirect_url'])){
        $_SESSION['redirect_url'] = 'reportform_iacts.php';
    }
    header("Location: reportlogin.php");
    exit();
}

// Get the event_id parameter from the URL
$event_id = isset($_GET['event_id']) ? htmlspecialchars($_GET['event_id']) : '';
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '';
$program_type = isset($_GET['programType']) ? htmlspecialchars($_GET['programType']) : '';
$venue = isset($_GET['venue']) ? htmlspecialchars($_GET['venue']) : '';
$area_no = isset($_GET['areaNo']) ? htmlspecialchars($_GET['areaNo']) : '';
$barangay = isset($_GET['barangay']) ? htmlspecialchars($_GET['barangay']) : '';
$city = isset($_GET['city']) ? htmlspecialchars($_GET['city']) : '';
$start_date = isset($_GET['startDate']) ? htmlspecialchars($_GET['startDate']) : '';

// NEW CODE: Handle resubmission of existing report
$original_report_id = isset($_GET['original_report_id']) ? intval($_GET['original_report_id']) : 0;
$report_type = isset($_GET['report_type']) ? htmlspecialchars($_GET['report_type']) : '';

// Initialize variables for pre-filling the form
$original_report_data = null;
$is_resubmission = false;

// If we have an original report ID, fetch the data
if ($original_report_id > 0 && !empty($report_type)) {
    $is_resubmission = true;
    
    // Connect to database and fetch the original report
    include 'database.php';
    
    $query = "SELECT * FROM illegalreportstbl WHERE report_id = ? AND report_type = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "is", $original_report_id, $report_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $original_report_data = mysqli_fetch_assoc($result);
    }
    
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_resubmission ? 'Resubmit' : 'Create'; ?> Illegal Activity Report</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reportform_2.0_.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- map links/scripts -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>
    <script type="text/javascript" src="app.js" defer></script>
    <style>
        .resubmission-banner {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .resubmission-banner h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .resubmission-banner p {
            margin-bottom: 5px;
            color: #856404;
        }
        
        .original-data-note {
            font-size: 0.9em;
            color: #6c757d;
            font-style: italic;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <header>
    <div class="header-logo"><i class='bx bxs-leaf'></i><span class="logo">ManGrow</span></div>
        <nav class = "navbar">
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
        
        <?php if ($is_resubmission): ?>
        <div class="resubmission-banner">
            <h3><i class="fas fa-redo-alt"></i> Resubmitting Report #<?php echo $original_report_id; ?></h3>
            <p>You are resubmitting a previous report. Please review and update the information as needed.</p>
            <p class="original-data-note">Original submission date: <?php echo isset($original_report_data['date_reported']) ? date('M j, Y g:i A', strtotime($original_report_data['date_reported'])) : 'Unknown'; ?></p>
        </div>
        <?php endif; ?>
        
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
                <p><?= isset($_SESSION["organization"]) ? $_SESSION["organization"] : "" ?></p>
                </div>
        <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <!-- reports page -->
        <div class="form-div">
            <div class="form-content">
                <h2 class="form-title"><?php echo $is_resubmission ? 'Resubmit' : 'Create'; ?> Illegal Activity Report</h2>
                
                <form id="illegalActivityForm" action="uploadreport_ia.php" method="POST" enctype="multipart/form-data" class="two-column-form">
                    <!-- Hidden fields for resubmission tracking -->
                    <?php if ($is_resubmission): ?>
                    <input type="hidden" name="original_report_id" value="<?php echo $original_report_id; ?>">
                    <input type="hidden" name="is_resubmission" value="1">
                    <?php endif; ?>
                    
                    <!-- Left Column -->
                    <div class="form-column">
                        <!-- Emergency Toggle -->
                        <div class="form-group emergency-toggle">
                            <label class="section-label">
                                <i class="fas fa-exclamation-triangle"></i> Emergency Report
                            </label>
                            <div class="toggle-container">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="emergencyToggle" name="emergency" 
                                        <?php if ($is_resubmission && isset($original_report_data['emergency']) && $original_report_data['emergency'] == 1) echo 'checked'; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Mark as emergency</span>
                            </div>
                            <p class="note">Use this for urgent situations requiring immediate response</p>
                        </div>

                        <!-- Incident Type -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-clipboard-list"></i> Type of Incident
                            </label>
                            <select id="incidentType" name="incident_type" required class="form-input">
                                <option value="" disabled <?php echo !$is_resubmission ? 'selected' : ''; ?>>Select incident type</option>
                                <option value="Illegal Cutting" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Illegal Cutting') echo 'selected'; ?>>Illegal Cutting of Mangrove Trees</option>
                                <option value="Waste Dumping" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Waste Dumping') echo 'selected'; ?>>Waste Dumping</option>
                                <option value="Construction" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Construction') echo 'selected'; ?>>Unauthorized Construction</option>
                                <option value="Harmful Fishing" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Harmful Fishing') echo 'selected'; ?>>Fishing with Harmful Methods</option>
                                <option value="Water Pollution" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Water Pollution') echo 'selected'; ?>>Water Pollution</option>
                                <option value="Fire" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Fire') echo 'selected'; ?>>Fire in Mangrove Area</option>
                                <option value="Other" <?php if ($is_resubmission && isset($original_report_data['incident_type']) && $original_report_data['incident_type'] == 'Other') echo 'selected'; ?>>Other (please specify)</option>
                            </select>
                        </div>

                        <!-- Location Map -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marked-alt"></i> Mangrove Area
                            </label>
                            <select id="mangroveAreaSelect" name="mangroveArea" class="form-input">
                                <option value="" disabled selected>Select a mangrove area</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                            <input type="hidden" id="cityMunicipality" name="city_municipality" 
                                value="<?php if ($is_resubmission && isset($original_report_data['city_municipality'])) echo htmlspecialchars($original_report_data['city_municipality']); ?>">
                            <input type="hidden" id="areaId" name="area_id" 
                                value="<?php if ($is_resubmission && isset($original_report_data['area_id'])) echo htmlspecialchars($original_report_data['area_id']); ?>">
                            <input type="hidden" id="areaNo" name="area_no" 
                                value="<?php if ($is_resubmission && isset($original_report_data['area_no'])) echo htmlspecialchars($original_report_data['area_no']); ?>">
                        </div>

                        <!-- City/Municipality Dropdown -->
                            <div class="form-group">
                                <label class="section-label">
                                    <i class="fas fa-city"></i> City/Municipality
                                </label>
                                <select id="cityMunicipalitySelect" name="city_municipality" required class="form-input">
                                    <option value="" disabled <?php echo !$is_resubmission ? 'selected' : ''; ?>>Select City/Municipality</option>
                                    <?php
                                    include 'database.php';
                                    $query = "SELECT * FROM citymunicipalitytbl";
                                    $result = mysqli_query($connection, $query);
                                    while($row = $result->fetch_assoc()):
                                    ?>
                                    <option value="<?= htmlspecialchars($row['city']) ?>" 
                                        <?php if ($is_resubmission && isset($original_report_data['city_municipality']) && $original_report_data['city_municipality'] == $row['city']) echo 'selected'; ?>>
                                        <?= htmlspecialchars($row['city']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Barangay Checkbox List -->
                            <div class="form-group">
                                <label class="section-label">
                                    <i class="fas fa-map-pin"></i> Nearby Barangays (Select all that apply)
                                </label>
                                <div id="barangayCheckboxContainer" class="checkbox-container">
                                    <!-- Checkboxes will be populated by JavaScript -->
                                    <div class="loading-placeholder">Select a city/municipality first</div>
                                </div>
                                <div id="selectedBarangaysDebug" class="debug-info" style="display: none;"></div>
                            </div>    

                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marked-alt"></i> Location of Incident
                            </label>
                            <div id="map" style="height: 250px; border-radius: 8px; margin-bottom: 10px;"></div>
                            <div class="coord-inputs">
                                <div class="coord-input">
                                    <label><i class="fas fa-latitude"></i> Latitude</label>
                                    <input type="text" id="latitude" name="latitude" readonly class="form-input" 
                                        value="<?php if ($is_resubmission && isset($original_report_data['latitude'])) echo htmlspecialchars($original_report_data['latitude']); ?>">
                                </div>
                                <div class="coord-input">
                                    <label><i class="fas fa-longitude"></i> Longitude</label>
                                    <input type="text" id="longitude" name="longitude" readonly class="form-input" 
                                        value="<?php if ($is_resubmission && isset($original_report_data['longitude'])) echo htmlspecialchars($original_report_data['longitude']); ?>">
                                </div>
                            </div>
                            <button type="button" id="locateBtn" class="map-button">
                                <i class="fas fa-location-arrow"></i> Get Current Location
                            </button>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="form-column">
                        <!-- Address Display -->
                        <div class="address-display">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="address" name="address" readonly class="form-textarea" rows="2" placeholder="Address will appear here..."><?php if ($is_resubmission && isset($original_report_data['address'])) echo htmlspecialchars($original_report_data['address']); ?></textarea>
                        </div>
                        <!-- Date and Time -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="far fa-calendar-alt"></i> Date and Time of Incident
                            </label>
                            <input type="datetime-local" id="incidentDateTime" name="incident_datetime" required class="form-input" 
                                value="<?php if ($is_resubmission && isset($original_report_data['incident_datetime'])) echo date('Y-m-d\TH:i', strtotime($original_report_data['incident_datetime'])); ?>">
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-align-left"></i> Brief Description
                            </label>
                            <textarea id="description" name="description" rows="4" class="form-textarea" placeholder="What did you observe? Who was involved (if known)? Any other details..."><?php if ($is_resubmission && isset($original_report_data['description'])) echo htmlspecialchars($original_report_data['description']); ?></textarea>
                        </div>

                        <!-- Media Upload -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-camera-retro"></i> Upload Photo/Video Evidence
                            </label>
                            <?php if ($is_resubmission && isset($original_report_data['evidence_paths'])): ?>
                            <div class="original-media-note">
                                <p class="original-data-note">Original media files cannot be pre-loaded for security reasons. Please re-upload any evidence.</p>
                            </div>
                            <?php endif; ?>
                            <div class="image-upload-container">
                                <input type="file" id="evidenceMedia" name="evidence[]" accept="image/*,video/*" multiple class="image-upload">
                                <label for="evidenceMedia" class="upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> 
                                    <span>Select files (max 5MB each)</span>
                                </label>
                                <div id="mediaPreviews" class="media-previews"></div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-address-book"></i> Contact Information (Optional)
                            </label>
                            <input type="tel" id="contactPhone" name="contact_phone" class="form-input" placeholder="Your phone number" style="margin-top: 0.5rem;" 
                                value="<?php if ($is_resubmission && isset($original_report_data['contact_phone'])) echo htmlspecialchars($original_report_data['contact_phone']); ?>">
                        </div>

                        <!-- Consent and Anonymous -->
                        <div class="form-group">
                            <label class="consent-checkbox">
                                <input type="checkbox" id="consentCheckbox" name="consent" required>
                                <span>I confirm that this report is made in good faith and to the best of my knowledge.</span>
                            </label>
                            <label class="anonymous-checkbox">
                                <input type="checkbox" id="anonymousReport" name="anonymous" value="1"
                                    <?php if ($is_resubmission && isset($original_report_data['anonymous']) && $original_report_data['anonymous'] == 1) echo 'checked'; ?>>
                                <span>Submit anonymously</span>
                            </label>
                        </div>

                        <input type="hidden" id="reportedBy" name="reported_by" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="submit-button">
                                <i class="fas fa-paper-plane"></i> <?php echo $is_resubmission ? 'Resubmit Report' : 'Submit Report'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <footer>
        <div id="right-footer">
            <p>This website is developed by ManGrow. All Rights Reserved.</p>
        </div>
    </footer>            
<script>
    // Global variables
    let map;
    let marker = null;
    let userLocation = null;
    let circle = null;
    let mangroveAreaList = []; // Stores area data with id, area_no, and city_municipality
    let selectedAreaLayer = null;
    let lastGeocodeTime = 0;
    const GEOCODE_DELAY = 1000; // 1 second delay between geocoding requests

    let selectedCityMunicipality = '';
    let selectedBarangays = [];
    
    // Check if we have original coordinates to pre-set the map
    const originalLat = <?php echo $is_resubmission && isset($original_report_data['latitude']) ? floatval($original_report_data['latitude']) : 'null'; ?>;
    const originalLng = <?php echo $is_resubmission && isset($original_report_data['longitude']) ? floatval($original_report_data['longitude']) : 'null'; ?>;
    
    // Initialize map
    function initMap() {
        // Set initial view based on whether we have original coordinates
        if (originalLat && originalLng) {
            map = L.map('map').setView([originalLat, originalLng], 15);
            
            // Add marker for original location
            marker = L.marker([originalLat, originalLng], {
                draggable: true
            }).addTo(map)
              .bindPopup("Original incident location").openPopup();
              
            marker.on('dragend', function(e) {
                const newLatLng = marker.getLatLng();
                updateLocation(newLatLng);
                reverseGeocode(newLatLng.lat, newLatLng.lng);
            });
        } else {
            map = L.map('map').setView([14.64852, 120.47318], 13);
            
            // Try to locate user immediately
            map.locate({setView: false, maxZoom: 16, enableHighAccuracy: true});
        }

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        map.on('locationfound', function(e) {
            userLocation = e.latlng;
            updateLocation(userLocation);
            reverseGeocode(userLocation.lat, userLocation.lng);

            if (circle) map.removeLayer(circle);
            circle = L.circle(userLocation, {
                color: 'blue',
                fillColor: '#30f',
                fillOpacity: 0.2,
                radius: e.accuracy / 2
            }).addTo(map);
        });

        map.on('locationerror', function(e) {
            alert("Location access denied. Please enable location services or click on the map to set location manually.");
        });

        map.on('click', function(e) {
            updateLocation(e.latlng);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
            if (circle) {
                map.removeLayer(circle);
                circle = null;
            }
        });
    }

    async function populateCityMunicipalityDropdown() {
        try {
            const response = await fetch('getdropdown.php');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            const select = document.getElementById('cityMunicipalitySelect');
            
            // Clear existing options except the first one
            while (select.options.length > 1) {
                select.remove(1);
            }
            
            // Add new options
            data.forEach(city => {
                const option = document.createElement('option');
                option.value = city.city;
                option.textContent = city.city;
                select.appendChild(option);
            });
            
            console.log('City/Municipality dropdown populated');
        } catch (error) {
            console.error('Error fetching city/municipality data:', error);
            alert('Could not load city/municipality data. Please try again later.');
        }
    }

    // Fetch and populate barangay checkboxes
    async function populateBarangayCheckboxes(cityMunicipality) {
        try {
            const container = document.getElementById('barangayCheckboxContainer');
            container.innerHTML = '<div class="loading-placeholder">Loading barangays...</div>';
            
            const response = await fetch(`getdropdown.php?city=${encodeURIComponent(cityMunicipality)}`);
            if (!response.ok) throw new Error('Network response was not ok');
            
            const data = await response.json();
            
            if (data.error) {
                throw new Error(data.error);
            }
            
            container.innerHTML = '';
            
            if (data.length === 0) {
                container.innerHTML = '<div class="loading-placeholder">No barangays found for this city/municipality</div>';
                return;
            }
            
            data.forEach(barangay => {
                const checkboxItem = document.createElement('div');
                checkboxItem.className = 'checkbox-item';
                
                const checkboxId = `barangay-${barangay.barangay.replace(/\s+/g, '-').toLowerCase()}`;
                
                checkboxItem.innerHTML = `
                    <label>
                        <input type="checkbox" id="${checkboxId}" value="${barangay.barangay}">
                        ${barangay.barangay}
                    </label>
                `;
                
                container.appendChild(checkboxItem);
                
                // Add event listener to the checkbox
                const checkbox = checkboxItem.querySelector('input');
                checkbox.addEventListener('change', function() {
                    updateSelectedBarangays();
                });
            });
            
            console.log('Barangay checkboxes populated for:', cityMunicipality);
        } catch (error) {
            console.error('Error fetching barangay data:', error);
            container.innerHTML = '<div class="loading-placeholder">Error loading barangays</div>';
        }
    }

    function updateSelectedBarangays() {
        const checkboxes = document.querySelectorAll('#barangayCheckboxContainer input[type="checkbox"]:checked');
        selectedBarangays = Array.from(checkboxes).map(cb => cb.value);
        
        // Update debug display
        const debugElement = document.getElementById('selectedBarangaysDebug');
        debugElement.textContent = `Selected Barangays: ${selectedBarangays.join(', ') || 'None'}`;
        debugElement.style.display = selectedBarangays.length > 0 ? 'block' : 'none';
        
        console.log('Selected barangays updated:', selectedBarangays);
    }

    // Validate that selected city matches address city (silent validation)
    function validateCityMatch() {
        const addressCity = document.getElementById('address').value.toLowerCase();
        const selectedCity = document.getElementById('cityMunicipalitySelect').value.toLowerCase();
        
        if (addressCity.includes(selectedCity)) {
            return true;
        }
        
        // If we have a selected mangrove area, check its city too
        const mangroveAreaSelect = document.getElementById('mangroveAreaSelect');
        if (mangroveAreaSelect.value) {
            const selectedOption = mangroveAreaSelect.options[mangroveAreaSelect.selectedIndex];
            const mangroveCity = selectedOption.dataset.city.toLowerCase();
            if (addressCity.includes(mangroveCity)) {
                return true;
            }
        }
        
        return false;
    }

    // City/Municipality dropdown change handler
    document.getElementById('cityMunicipalitySelect').addEventListener('change', function() {
        selectedCityMunicipality = this.value;
        document.getElementById('cityMunicipality').value = this.value;
        populateBarangayCheckboxes(this.value);
        
        // Silent validation - no alert
        validateCityMatch();
    });

    // Fetch mangrove areas data
    async function fetchMangroveAreas() {
        try {
            const response = await fetch('mangroveareas.json?t=' + Date.now());
            if (!response.ok) throw new Error('Network response was not ok');
            
            const fullData = await response.json();
            console.log('Fetched mangrove areas:', fullData);
            
            mangroveAreaList = []; // Clear previous data
            
            if (fullData.features && Array.isArray(fullData.features)) {
                mangroveAreaList = fullData.features.map(feature => {
                    if (!feature.properties.id) {
                        console.warn('Feature missing ID:', feature);
                    }
                    return {
                        id: feature.properties.id,
                        area_no: feature.properties.area_no,
                        city_municipality: feature.properties.city_municipality
                    };
                });
            } else if (Array.isArray(fullData)) {
                mangroveAreaList = fullData.map(area => {
                    if (!area.id) {
                        console.warn('Area missing ID:', area);
                    }
                    return {
                        id: area.id,
                        area_no: area.area_no,
                        city_municipality: area.city_municipality
                    };
                });
            } else {
                throw new Error('Invalid data format in mangroveareas.json');
            }
            
            console.log('Processed mangrove areas:', mangroveAreaList);
            populateAreaDropdown();
        } catch (error) {
            console.error('Error fetching mangrove areas:', error);
            alert('Could not load mangrove areas. Please try again later.');
        }
    }

    // Populate the dropdown with mangrove areas
    function populateAreaDropdown() {
        const select = document.getElementById('mangroveAreaSelect');
        
        // Clear existing options except the first one
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // Add new options using id as value
        mangroveAreaList.forEach(area => {
            const option = document.createElement('option');
            option.value = area.id; // Use id as the value
            option.textContent = `${area.area_no} - ${area.city_municipality}`;
            option.dataset.city = area.city_municipality; // Store city in data attribute
            option.dataset.areaNo = area.area_no; // Store area_no in data attribute
            select.appendChild(option);
        });
    }

    // Display selected area on map
    async function displaySelectedArea(areaId) {
        try {
            const response = await fetch('mangroveareas.json');
            if (!response.ok) throw new Error('Network response was not ok');
            
            const fullData = await response.json();
            let selectedArea = null;
            
            // Handle different JSON formats
            if (fullData.features) {
                // GeoJSON format
                const feature = fullData.features.find(f => f.properties.id == areaId);
                if (feature) {
                    selectedArea = {
                        type: "Feature",
                        geometry: feature.geometry,
                        properties: feature.properties
                    };
                }
            } else if (Array.isArray(fullData)) {
                // Regular array format
                const area = fullData.find(a => a.id == areaId);
                if (area) {
                    selectedArea = {
                        type: "Feature",
                        geometry: area.geometry,
                        properties: {
                            id: area.id,
                            area_no: area.area_no,
                            city_municipality: area.city_municipality
                        }
                    };
                }
            } else {
                throw new Error('Invalid data format in mangroveareas.json');
            }
            
            if (!selectedArea) {
                console.error('Area not found with ID:', areaId);
                return;
            }
            
            // Remove previous area layer if exists
            if (selectedAreaLayer) {
                map.removeLayer(selectedAreaLayer);
            }
            
            // Convert to layer and add to map
            selectedAreaLayer = L.geoJSON(selectedArea, {
                style: {
                    color: '#3E7B27',
                    weight: 2,
                    opacity: 1,
                    fillColor: '#3E7B27',
                    fillOpacity: 0.2
                }
            }).addTo(map);
            
            // Zoom to the area bounds
            map.fitBounds(selectedAreaLayer.getBounds());
            
            // Update location with area's center
            const center = selectedAreaLayer.getBounds().getCenter();
            updateLocation(center);
            reverseGeocode(center.lat, center.lng);
            
        } catch (error) {
            console.error('Error loading area details:', error);
            alert('Could not load area details. Please try again.');
        }
    }

    // Update location inputs and marker
    function updateLocation(latlng) {
        document.getElementById('latitude').value = latlng.lat.toFixed(6);
        document.getElementById('longitude').value = latlng.lng.toFixed(6);
        
        if (marker) map.removeLayer(marker);
        
        marker = L.marker(latlng, {
            draggable: true
        }).addTo(map)
          .bindPopup("You are currently right here.").openPopup();
        
        marker.on('dragend', function(e) {
            const newLatLng = marker.getLatLng();
            updateLocation(newLatLng);
            reverseGeocode(newLatLng.lat, newLatLng.lng);
        });
    }

    // Reverse geocode coordinates to get address
    function reverseGeocode(lat, lng) {
        const now = Date.now();
        if (now - lastGeocodeTime < GEOCODE_DELAY) {
            return;
        }
        lastGeocodeTime = now;
        
        const addressTextarea = document.getElementById('address');
        addressTextarea.value = "Loading address...";
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                let address = '';
                if (data.address) {
                    const addr = data.address;
                    if (addr.road) address += addr.road;
                    if (addr.neighbourhood) address += (address ? ', ' : '') + addr.neighbourhood;
                    if (addr.suburb) address += (address ? ', ' : '') + addr.suburb;
                    if (addr.city_district) address += (address ? ', ' : '') + addr.city_district;
                    if (addr.city || addr.town || addr.village) {
                        address += (address ? ', ' : '') + (addr.city || addr.town || addr.village);
                    }
                    if (addr.state) address += (address ? ', ' : '') + addr.state;
                    if (addr.postcode) address += (address ? ' ' : '') + addr.postcode;
                    if (addr.country) address += (address ? ', ' : '') + addr.country;
                    
                    if (!address && data.display_name) {
                        address = data.display_name;
                    }
                    
                    // Try to auto-select city if found in address
                    const city = addr.city || addr.town || addr.village || addr.municipality;
                    if (city) {
                        const citySelect = document.getElementById('cityMunicipalitySelect');
                        for (let i = 0; i < citySelect.options.length; i++) {
                            if (citySelect.options[i].text.toLowerCase().includes(city.toLowerCase())) {
                                citySelect.value = citySelect.options[i].value;
                                citySelect.dispatchEvent(new Event('change'));
                                break;
                            }
                        }
                    }
                } else if (data.display_name) {
                    address = data.display_name;
                }
                
                addressTextarea.value = address || `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            })
            .catch(error => {
                console.error('Error reverse geocoding:', error);
                addressTextarea.value = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            });
    }

    // Modify the form submission to include barangays
    document.getElementById('illegalActivityForm').addEventListener('submit', function(e) {
        // Validate city match before submission
        if (!validateCityMatch()) {
            e.preventDefault();
            alert('The selected city/municipality must match the address location. Please correct your selection before submitting.');
            return;
        }
        
        // Create a hidden input for barangays if not exists
        if (!document.getElementById('barangaysInput')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.id = 'barangaysInput';
            input.name = 'barangays';
            this.appendChild(input);
        }
        
        // Set the barangays value as JSON string
        document.getElementById('barangaysInput').value = JSON.stringify(selectedBarangays);
        
        console.log('Form submitted with barangays:', selectedBarangays);
    });

    // Initialize everything when DOM loads
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        fetchMangroveAreas();
        
        // If we have an original area ID, select it in the dropdown
        <?php if ($is_resubmission && isset($original_report_data['area_id'])): ?>
        setTimeout(function() {
            const areaSelect = document.getElementById('mangroveAreaSelect');
            if (areaSelect) {
                areaSelect.value = '<?php echo $original_report_data['area_id']; ?>';
                areaSelect.dispatchEvent(new Event('change'));
            }
        }, 1000);
        <?php endif; ?>
        
        // Get the city select element
        const citySelect = document.getElementById('cityMunicipalitySelect');
        
        // Only trigger change event if we have a valid selection
        if (citySelect.options.length > 1) {
            // Set the first non-disabled option as selected
            for (let i = 1; i < citySelect.options.length; i++) {
                if (!citySelect.options[i].disabled) {
                    citySelect.selectedIndex = i;
                    break;
                }
            }
            // Trigger the change event to load barangays
            citySelect.dispatchEvent(new Event('change'));
        }
        
        // Event listeners
        document.getElementById('locateBtn').addEventListener('click', function() {
            map.locate({setView: true, maxZoom: 16, enableHighAccuracy: true});
        });

        document.getElementById('mangroveAreaSelect').addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('cityMunicipality').value = selectedOption.dataset.city || '';
                document.getElementById('areaId').value = this.value; // Set the area_id
                document.getElementById('areaNo').value = selectedOption.dataset.areaNo; // Set area_no
                displaySelectedArea(this.value);
            }
        });

        // Set default datetime to now if not resubmitting
        <?php if (!$is_resubmission): ?>
        const dateInput = document.getElementById('incidentDateTime');
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        function toDatetimeLocal(date) {
            const pad = n => n.toString().padStart(2, '0');
            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + 'T' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes());
        }
        
        dateInput.min = toDatetimeLocal(yesterday);
        dateInput.value = toDatetimeLocal(today);
        <?php endif; ?>

        // Emergency toggle styling
        const emergencyToggle = document.getElementById('emergencyToggle');
        emergencyToggle.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('illegalActivityForm').classList.add('emergency');
            } else {
                document.getElementById('illegalActivityForm').classList.remove('emergency');
            }
        });
        
        // Media upload handling with compression
        const mediaInput = document.getElementById('evidenceMedia');
        const previewContainer = document.getElementById('mediaPreviews');

        // Maximum allowed files and size
        const MAX_FILES = 3;
        const MAX_SIZE_MB = 25;
        const MAX_SIZE_BYTES = MAX_SIZE_MB * 1024 * 1024;
        const TARGET_VIDEO_SIZE_MB = 25;
        const TARGET_VIDEO_SIZE_BYTES = TARGET_VIDEO_SIZE_MB * 1024 * 1024;

        // Function to compress video
        async function compressVideo(file) {
            return new Promise((resolve) => {
                if (!file.type.startsWith('video/') || file.size <= TARGET_VIDEO_SIZE_BYTES) {
                    resolve(file);
                    return;
                }

                const video = document.createElement('video');
                video.src = URL.createObjectURL(file);
                video.muted = true;
                video.playsInline = true;
                
                video.onloadedmetadata = () => {
                    // Calculate target bitrate (rough estimation)
                    const duration = video.duration;
                    const targetBitrate = (TARGET_VIDEO_SIZE_BYTES * 8) / duration; // in bits
                    
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    
                    // Capture first frame for dimensions
                    video.currentTime = 0.1;
                    
                    video.onseeked = () => {
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        
                        // Create media recorder with reduced quality
                        const stream = canvas.captureStream();
                        const mediaRecorder = new MediaRecorder(stream, {
                            mimeType: 'video/webm',
                            videoBitsPerSecond: targetBitrate
                        });
                        
                        const chunks = [];
                        mediaRecorder.ondataavailable = (e) => chunks.push(e.data);
                        mediaRecorder.onstop = () => {
                            const compressedBlob = new Blob(chunks, { type: 'video/webm' });
                            const compressedFile = new File([compressedBlob], file.name, {
                                type: 'video/webm',
                                lastModified: Date.now()
                            });
                            resolve(compressedFile);
                        };
                        
                        mediaRecorder.start();
                        setTimeout(() => mediaRecorder.stop(), duration * 1000);
                    };
                };
            });
        }

        mediaInput.addEventListener('change', async function() {
            const files = Array.from(this.files);
            
            // Clear previous error messages
            const existingErrors = document.querySelectorAll('.upload-error');
            existingErrors.forEach(error => error.remove());
            
            // Validate file count
            if (files.length > MAX_FILES) {
                showError(`Maximum ${MAX_FILES} files allowed. Only the first ${MAX_FILES} will be processed.`);
                const validFiles = files.slice(0, MAX_FILES);
                updateFileInput(validFiles);
                return;
            }
            
            // Process files with compression
            try {
                const processedFiles = [];
                
                for (const file of files) {
                    // Basic validation
                    if (!file.type.match(/(image|video)\/.*/)) {
                        showError(`"${file.name}" is not a valid image/video file`);
                        continue;
                    }
                    
                    // Compress if video and over size limit
                    let processedFile = file;
                    if (file.type.startsWith('video/') && file.size > TARGET_VIDEO_SIZE_BYTES) {
                        showMessage(`Compressing "${file.name}"...`);
                        processedFile = await compressVideo(file);
                        showMessage(`"${file.name}" compressed from ${formatFileSize(file.size)} to ${formatFileSize(processedFile.size)}`);
                    }
                    
                    processedFiles.push(processedFile);
                }
                
                // Update input with processed files
                updateFileInput(processedFiles);
                displayPreviews(processedFiles);
                
            } catch (error) {
                console.error('Error processing files:', error);
                showError('Error processing files. Please try again.');
            }
        });

        // Helper functions
        function showError(message) {
            const errorMsg = document.createElement('div');
            errorMsg.className = 'upload-error';
            errorMsg.textContent = message;
            mediaInput.parentNode.appendChild(errorMsg);
        }

        function showMessage(message) {
            console.log(message); // Or implement visual feedback if desired
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' bytes';
            else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            else return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function updateFileInput(files) {
            const dt = new DataTransfer();
            files.forEach(file => dt.items.add(file));
            mediaInput.files = dt.files;
        }

        function displayPreviews(files) {
            previewContainer.innerHTML = '';
            
            // Create empty slots for remaining files
            for (let i = 0; i < MAX_FILES; i++) {
                const preview = document.createElement('div');
                preview.className = 'media-preview empty';
                preview.innerHTML = `<span>No file</span>`;
                previewContainer.appendChild(preview);
            }
            
            // Process valid files
            files.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = previewContainer.children[index];
                    preview.className = 'media-preview';
                    
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button class="remove-media" data-index="${index}">&times;</button>
                        `;
                    } else if (file.type.startsWith('video/')) {
                        preview.innerHTML = `
                            <video controls>
                                <source src="${e.target.result}" type="${file.type}">
                            </video>
                            <button class="remove-media" data-index="${index}">&times;</button>
                        `;
                    }
                    
                    // Add remove button handler
                    preview.querySelector('.remove-media').addEventListener('click', function() {
                        removeMedia(index);
                    });
                };
                reader.readAsDataURL(file);
            });
        }

        function removeMedia(index) {
            const dt = new DataTransfer();
            const files = Array.from(mediaInput.files);
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            mediaInput.files = dt.files;
            mediaInput.dispatchEvent(new Event('change'));
        }
    });
</script>
</body>
</html>