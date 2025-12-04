<?php
session_start();
require_once 'database.php';
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

// Check if user is logged in
if(!isset($_SESSION["user_id"])){
    header("Location: login.php");
    exit();
}

// Get report_id from URL
$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
$report_type = isset($_GET['report_type']) ? htmlspecialchars($_GET['report_type']) : '';

if (!$report_id || !$report_type) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'Invalid report'];
    header("Location: reportspage.php");
    exit();
}

// Determine which table to check
$table = ($report_type === 'Mangrove Data Report') ? 'mangrovereporttbl' : 'illegalreportstbl';

// Check if report exists and is in rejected state with time remaining
$query = "SELECT 
            r.report_id, 
            r.rejection_timestamp, 
            r.action_type,
            r.follow_up_status,
            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(r.rejection_timestamp, INTERVAL 48 HOUR)) AS seconds_remaining
          FROM $table r
          JOIN userreportstbl u ON r.report_id = u.report_id
          WHERE r.report_id = ? AND u.account_id = ? AND u.report_type = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("iis", $report_id, $_SESSION['user_id'], $report_type);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();

if (!$report) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'Report not found or not yours'];
    header("Location: reportspage.php");
    exit();
}

// Only allow editing if:
// 1. Report is in "Rejected" status
// 2. Within 48 hours of rejection
if ($report['action_type'] !== 'Rejected') {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'Only rejected reports can be edited'];
    header("Location: reportspage.php");
    exit();
}

if ($report['seconds_remaining'] <= 0) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'The 48-hour editing window has expired'];
    header("Location: reportspage.php");
    exit();
}

// Initialize variables for form fields
$incident_type = '';
$emergency = '';
$latitude = '';
$longitude = '';
$incident_datetime = '';
$description = '';
$city_municipality = '';
$area_no = '';
$area_id = '';
$contact_phone = '';
$anonymous = 0;
$evidence_files = [];
$address = '';
$barangays = '';

// Fetch report data if report_id is valid
if($report_id > 0) {
    try {
        // Get basic report info
        $query = "SELECT * FROM illegalreportstbl WHERE report_id = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $report = $result->fetch_assoc();
            
            // Assign values to variables
            $incident_type = $report['incident_type'] ?? '';
            $priority = $report['priority'] ?? 0;
            $latitude = $report['latitude'] ?? '';
            $longitude = $report['longitude'] ?? '';
            $incident_datetime = $report['incident_datetime'] ?? '';
            $description = $report['description'] ?? '';
            $city_municipality = $report['city_municipality'] ?? '';
            $area_no = $report['area_no'] ?? '';
            $area_id = $report['area_id'] ?? '';
            $contact_phone = $report['contact_no'] ?? '';
            $anonymous = (empty($report['reporter_id']) || intval($report['reporter_id']) === 0) ? 1 : 0;
            
            $address = $report['address'] ?? '';
            $barangays = $report['barangays'] ?? '';
            
            // Get evidence files
            $evidence_files = [];
            for($i = 1; $i <= 3; $i++) {
                if(!empty($report["image_video$i"])) {
                    $evidence_files[] = $report["image_video$i"];
                }
            }
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Report not found'
            ];
            header("Location: reportspage.php");
            exit();
        }
        $stmt->close();
        
    } catch(Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error fetching report: ' . $e->getMessage()
        ];
        header("Location: reportspage.php");
        exit();
    }
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid report ID'
    ];
    header("Location: reportspage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Form</title>
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
                <h2 class="form-title">Illegal Activity Report</h2>
                
                <form id="illegalActivityForm" action="updatereport_ia.php" method="POST" enctype="multipart/form-data" class="two-column-form <?= ($priority === 'Emergency') ? 'emergency' : '' ?>">
                    <!-- Left Column -->
                    <div class="form-column">
                        <!-- Emergency Toggle -->
                        <div class="form-group emergency-toggle">
                            <label class="section-label">
                                <i class="fas fa-exclamation-triangle"></i> Emergency Report
                            </label>
                            <div class="toggle-container" style="pointer-events: none; opacity: 0.7;">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="emergencyToggle" name="emergency" <?= ($priority === 'Emergency') ? 'checked' : '' ?> disabled>
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="toggle-label">Mark as emergency</span>
                            </div>
                            <p class="note">Use this for urgent situations requiring immediate response</p>
                        </div>

                        <!-- Incident Type -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-clipboard-list"></i> Type of Incident <em style="font-size: 0.9em; color: #666;">(Uri ng Insidente)</em>
                            </label>
                            <select id="incidentType" name="incident_type" required class="form-input" disabled style="pointer-events: none; opacity: 0.7;">
                                <option value="" disabled>Select incident type</option>
                                <option value="Illegal Cutting" <?= $incident_type == 'Illegal Cutting' ? 'selected' : '' ?>>Illegal Cutting of Mangrove Trees</option>
                                <option value="Waste Dumping" <?= $incident_type == 'Waste Dumping' ? 'selected' : '' ?>>Waste Dumping</option>
                                <option value="Construction" <?= $incident_type == 'Construction' ? 'selected' : '' ?>>Unauthorized Construction</option>
                                <option value="Harmful Fishing" <?= $incident_type == 'Harmful Fishing' ? 'selected' : '' ?>>Fishing with Harmful Methods</option>
                                <option value="Water Pollution" <?= $incident_type == 'Water Pollution' ? 'selected' : '' ?>>Water Pollution</option>
                                <option value="Fire" <?= $incident_type == 'Fire' ? 'selected' : '' ?>>Fire in Mangrove Area</option>
                                <option value="Other" <?= $incident_type == 'Other' ? 'selected' : '' ?>>Other (please specify)</option>
                            </select>
                            <input type="hidden" name="incident_type" value="<?= htmlspecialchars($incident_type) ?>">
                        </div>

                        <!-- Location Map -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marked-alt"></i> Mangrove Area
                            </label>
                            <select id="mangroveAreaSelect" name="mangroveArea" class="form-input" disabled>
                                <option value="" disabled>Select a mangrove area</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                            <input type="hidden" id="cityMunicipality" name="city_municipality" value="<?= htmlspecialchars($city_municipality) ?>">
                            <input type="hidden" id="areaId" name="area_id" value="<?= htmlspecialchars($area_id) ?>">
                            <input type="hidden" id="areaNo" name="area_no" value="<?= htmlspecialchars($area_no) ?>">
                        </div>
                        <!-- City/Municipality Dropdown -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-city"></i> City/Municipality
                            </label>
                            <select id="cityMunicipalitySelect" name="city_municipality" required class="form-input" disabled>
                                <option value="" disabled>Select City/Municipality</option>
                                <?php
                                $query = "SELECT * FROM citymunicipalitytbl";
                                $result = mysqli_query($connection, $query);
                                while($row = $result->fetch_assoc()):
                                ?>
                                <option value="<?= htmlspecialchars($row['city']) ?>" <?= $city_municipality == $row['city'] ? 'selected' : '' ?>>
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
                            <div id="barangayCheckboxContainer" class="checkbox-container" style="pointer-events: none; opacity: 0.7;">
                                <div class="loading-placeholder">Select a city/municipality first</div>
                            </div>
                            <input type="hidden" id="selectedBarangays" name="barangays" value="<?= htmlspecialchars($barangays) ?>">
                            <div id="selectedBarangaysDebug" class="debug-info" style="display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marked-alt"></i> Location of Incident <em style="font-size: 0.9em; color: #666;">(Lokasyon ng Insidente)</em>
                            </label>
                            <div id="map" style="height: 250px; border-radius: 8px; margin-bottom: 10px; pointer-events: none;"></div>
                            <div class="coord-inputs">
                                <div class="coord-input">
                                    <label><i class="fas fa-latitude"></i> Latitude</label>
                                    <input type="text" id="latitude" name="latitude" readonly class="form-input" value="<?= htmlspecialchars($latitude) ?>">
                                </div>
                                <div class="coord-input">
                                    <label><i class="fas fa-longitude"></i> Longitude</label>
                                    <input type="text" id="longitude" name="longitude" readonly class="form-input" value="<?= htmlspecialchars($longitude) ?>">
                                </div>
                            </div>
                            <button type="button" id="locateBtn" class="map-button" style="display: none;">
                                <i class="fas fa-location-arrow"></i> Get Current Location
                            </button>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="form-column">
                        <!-- Address Display -->
                        <div class="address-display">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="address" name="address" class="form-textarea" rows="2" readonly><?= htmlspecialchars($address) ?></textarea>
                        </div>
                        <!-- Date and Time -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="far fa-calendar-alt"></i> Date Updated
                            </label>
                            <input type="datetime-local" id="incidentDateTime" name="incident_datetime" required class="form-input" 
                                value="<?= $incident_datetime ? date('Y-m-d\TH:i', strtotime($incident_datetime)) : '' ?>">
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-align-left"></i> Brief Description <em style="font-size: 0.9em; color: #666;">(Maikling Paglalarawan)</em>
                            </label>
                            <textarea id="description" name="description" rows="4" class="form-textarea" readonly style="pointer-events: none; opacity: 0.7;"
                                    placeholder="What did you observe? Who was involved (if known)? Any other details..."><?= htmlspecialchars($description) ?></textarea>
                        </div>

                        <!-- Media Upload -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-camera-retro"></i> Upload Photo/Video Evidence <em style="font-size: 0.9em; color: #666;">(Mga Ebidensya)</em>
                            </label>
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
                                <i class="fas fa-address-book"></i> Contact Information <em style="font-size: 0.9em; color: #666;">(Impormasyon sa Pakikipag-ugnayan)</em> (Optional)
                            </label>
                            <input type="tel" id="contactPhone" name="contact_phone" class="form-input" readonly style="pointer-events: none; opacity: 0.7;"
                                placeholder="Your phone number" value="<?= htmlspecialchars($contact_phone) ?>">
                        </div>

                        <!-- Consent and Anonymous -->
                        <div class="form-group">
                            <label class="consent-checkbox">
                                <input type="checkbox" id="consentCheckbox" name="consent" required checked>
                                <span>I confirm that this report is made in good faith and to the best of my knowledge.</span>
                            </label>
                            <label class="anonymous-checkbox">
                                <input type="checkbox" id="anonymousReport" name="anonymous" value="1" <?= $anonymous ? 'checked' : '' ?>>
                                <span>Submit anonymously</span>
                            </label>
                        </div>

                        <!-- Hidden field for report ID -->
                        <input type="hidden" name="report_id" value="<?= $report_id ?>">

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="submit-button">
                                <i class="fas fa-paper-plane"></i> Submit Report
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

    let selectedBarangays = [];
    let selectedCityMunicipality = '<?= $city_municipality ?>';
    let lastGeocodeTime = 0;
    const GEOCODE_DELAY = 1000; // 1 second delay between geocoding requests

    // Initialize map (read-only version)
    function initMap() {
        map = L.map('map', {
            zoomControl: false, // Disable zoom controls
            dragging: false, // Disable dragging
            touchZoom: false, // Disable touch zoom
            scrollWheelZoom: false, // Disable scroll wheel zoom
            doubleClickZoom: false, // Disable double click zoom
            boxZoom: false, // Disable box zoom
            keyboard: false // Disable keyboard navigation
        }).setView([14.64852, 120.47318], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Set the initial location if we have coordinates
        const initialLat = parseFloat("<?= $latitude ?>");
        const initialLng = parseFloat("<?= $longitude ?>");
        
        if (!isNaN(initialLat) && !isNaN(initialLng)) {
            const initialLocation = L.latLng(initialLat, initialLng);
            updateLocation(initialLocation);
            map.setView(initialLocation, 16);
            
            // Set the address from the database
            document.getElementById('address').value = "<?= addslashes($address) ?>";
        }
    }

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
            // Don't show alert since this is just for display
        }
    }

    // Populate the dropdown with mangrove areas
    function populateAreaDropdown() {
        const select = document.getElementById('mangroveAreaSelect');
        const currentAreaId = "<?= $area_id ?>"; // Get the current area_id from PHP
        
        // Clear existing options except the first one
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // Add new options using id as the value
        mangroveAreaList.forEach(area => {
            const option = document.createElement('option');
            option.value = area.id; // Use id as the value
            option.textContent = `${area.area_no} - ${area.city_municipality}`;
            option.dataset.city = area.city_municipality; // Store city in data attribute
            option.dataset.areaNo = area.area_no; // Store area_no in data attribute
            
            // Select the option if it matches the current area_id
            if (currentAreaId && area.id == currentAreaId) {
                option.selected = true;
            }
            
            select.appendChild(option);
        });
    }

    // Update location inputs and marker (read-only version)
    function updateLocation(latlng) {
        document.getElementById('latitude').value = latlng.lat.toFixed(6);
        document.getElementById('longitude').value = latlng.lng.toFixed(6);
        
        if (marker) map.removeLayer(marker);
        
        marker = L.marker(latlng, {
            draggable: false // Disable dragging
        }).addTo(map)
          .bindPopup("Report location").openPopup();
    }

    // Fetch and populate barangay checkboxes (read-only version)
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
            
            // Get currently selected barangays from hidden field
            const currentBarangays = document.getElementById('selectedBarangays').value;
            const selectedBarangaysArray = currentBarangays ? currentBarangays.split(',').map(b => b.trim()) : [];
            
            data.forEach(barangay => {
                const checkboxItem = document.createElement('div');
                checkboxItem.className = 'checkbox-item';
                
                const checkboxId = `barangay-${barangay.barangay.replace(/\s+/g, '-').toLowerCase()}`;
                const isChecked = selectedBarangaysArray.includes(barangay.barangay);
                
                checkboxItem.innerHTML = `
                    <label>
                        <input type="checkbox" id="${checkboxId}" value="${barangay.barangay}" ${isChecked ? 'checked' : ''} disabled>
                        ${barangay.barangay}
                    </label>
                `;
                
                container.appendChild(checkboxItem);
                
                // Add to selected array if checked
                if (isChecked) {
                    selectedBarangays.push(barangay.barangay);
                }
            });
            
            updateSelectedBarangays();
            console.log('Barangay checkboxes populated for:', cityMunicipality);
        } catch (error) {
            console.error('Error fetching barangay data:', error);
            container.innerHTML = '<div class="loading-placeholder">Error loading barangays</div>';
        }
    }

    function updateSelectedBarangays() {
        const checkboxes = document.querySelectorAll('#barangayCheckboxContainer input[type="checkbox"]');
        selectedBarangays = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        // Update hidden field
        document.getElementById('selectedBarangays').value = selectedBarangays.join(',');
        
        // Update debug display
        const debugElement = document.getElementById('selectedBarangaysDebug');
        debugElement.textContent = `Selected Barangays: ${selectedBarangays.join(', ') || 'None'}`;
        debugElement.style.display = selectedBarangays.length > 0 ? 'block' : 'none';
        
        console.log('Selected barangays updated:', selectedBarangays);
    }

    // Initialize everything when DOM loads
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        fetchMangroveAreas();
        
        // Display existing evidence files
        const evidenceFiles = <?= json_encode($evidence_files) ?>;
        if (evidenceFiles.length > 0) {
            const previewContainer = document.getElementById('mediaPreviews');
            previewContainer.innerHTML = '';
            
            evidenceFiles.forEach((fileUrl, index) => {
                const preview = document.createElement('div');
                preview.className = 'media-preview';
                
                if (fileUrl.match(/\.(jpg|jpeg|png|gif)$/i)) {
                    preview.innerHTML = `
                        <img src="${fileUrl}" alt="Preview">
                        <input type="hidden" name="existing_evidence[]" value="${fileUrl}">
                        <button type="button" class="remove-media" data-index="${index}">&times;</button>
                    `;
                } else if (fileUrl.match(/\.(mp4|webm|ogg)$/i)) {
                    preview.innerHTML = `
                        <video controls>
                            <source src="${fileUrl}" type="video/mp4">
                        </video>
                        <input type="hidden" name="existing_evidence[]" value="${fileUrl}">
                        <button type="button" class="remove-media" data-index="${index}">&times;</button>
                    `;
                }
                
                previewContainer.appendChild(preview);
                
                // Add remove button handler for existing images
                const removeBtn = preview.querySelector('.remove-media');
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeExistingMedia(index, fileUrl);
                });
            });
        }

        // Initialize barangays when page loads
        if (selectedCityMunicipality) {
            populateBarangayCheckboxes(selectedCityMunicipality);
        }

        // Set default datetime to now
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
            // Clear any empty slots first
            const emptySlots = document.querySelectorAll('#mediaPreviews .media-preview.empty');
            emptySlots.forEach(slot => slot.remove());
            
            // Count existing non-empty previews (both existing and new)
            const existingPreviews = document.querySelectorAll('#mediaPreviews .media-preview:not(.empty)');
            const existingCount = existingPreviews.length;
            
            // Calculate available slots
            const availableSlots = MAX_FILES - existingCount;
            const filesToProcess = files.slice(0, availableSlots);
            
            // Process new files
            filesToProcess.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('div');
                    preview.className = 'media-preview';
                    
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Preview">
                            <button type="button" class="remove-media" data-index="${existingCount + index}">&times;</button>
                        `;
                    } else if (file.type.startsWith('video/')) {
                        preview.innerHTML = `
                            <video controls>
                                <source src="${e.target.result}" type="${file.type}">
                            </video>
                            <button type="button" class="remove-media" data-index="${existingCount + index}">&times;</button>
                        `;
                    }
                    
                    previewContainer.appendChild(preview);
                    
                    // Add remove button handler for new files
                    const removeBtn = preview.querySelector('.remove-media');
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeMedia(existingCount + index);
                    });
                };
                reader.readAsDataURL(file);
            });
            
            // Create empty slots for remaining available space
            const remainingSlots = MAX_FILES - (existingCount + filesToProcess.length);
            for (let i = 0; i < remainingSlots; i++) {
                const emptyPreview = document.createElement('div');
                emptyPreview.className = 'media-preview empty';
                emptyPreview.innerHTML = `<span>No file</span>`;
                previewContainer.appendChild(emptyPreview);
            }
        }

        // Also replace the removeMedia function with this corrected version
        function removeMedia(index) {
            const dt = new DataTransfer();
            const files = Array.from(mediaInput.files);
            
            // Calculate which file to remove (adjust index for existing files)
            const existingCount = document.querySelectorAll('#mediaPreviews input[type="hidden"][name="existing_evidence[]"]').length;
            const fileIndex = index - existingCount;
            
            if (fileIndex >= 0 && fileIndex < files.length) {
                files.splice(fileIndex, 1);
                files.forEach(file => dt.items.add(file));
                mediaInput.files = dt.files;
            }
            
            // Remove the preview and re-render
            const previewContainer = document.getElementById('mediaPreviews');
            previewContainer.innerHTML = '';
            
            // Re-display all existing media first
            const existingMedia = document.querySelectorAll('input[name="existing_evidence[]"]');
            existingMedia.forEach((input, i) => {
                const fileUrl = input.value;
                const preview = document.createElement('div');
                preview.className = 'media-preview';
                
                if (fileUrl.match(/\.(jpg|jpeg|png|gif)$/i)) {
                    preview.innerHTML = `
                        <img src="${fileUrl}" alt="Preview">
                        <input type="hidden" name="existing_evidence[]" value="${fileUrl}">
                        <button type="button" class="remove-media" data-index="${i}">&times;</button>
                    `;
                } else if (fileUrl.match(/\.(mp4|webm|ogg)$/i)) {
                    preview.innerHTML = `
                        <video controls>
                            <source src="${fileUrl}" type="video/mp4">
                        </video>
                        <input type="hidden" name="existing_evidence[]" value="${fileUrl}">
                        <button type="button" class="remove-media" data-index="${i}">&times;</button>
                    `;
                }
                
                previewContainer.appendChild(preview);
                
                // Add remove button handler for existing images
                const removeBtn = preview.querySelector('.remove-media');
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    removeExistingMedia(i, fileUrl);
                });
            });
            
            // Then display current files from input
            const currentFiles = Array.from(mediaInput.files);
            if (currentFiles.length > 0) {
                // Create a temporary function to display the current files
                const displayCurrentFiles = () => {
                    currentFiles.forEach((file, i) => {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const newIndex = existingMedia.length + i;
                            const preview = document.createElement('div');
                            preview.className = 'media-preview';
                            
                            if (file.type.startsWith('image/')) {
                                preview.innerHTML = `
                                    <img src="${e.target.result}" alt="Preview">
                                    <button type="button" class="remove-media" data-index="${newIndex}">&times;</button>
                                `;
                            } else if (file.type.startsWith('video/')) {
                                preview.innerHTML = `
                                    <video controls>
                                        <source src="${e.target.result}" type="${file.type}">
                                    </video>
                                    <button type="button" class="remove-media" data-index="${newIndex}">&times;</button>
                                `;
                            }
                            
                            previewContainer.appendChild(preview);
                            
                            // Add remove button handler
                            const removeBtn = preview.querySelector('.remove-media');
                            removeBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                e.stopPropagation();
                                removeMedia(newIndex);
                            });
                        };
                        reader.readAsDataURL(file);
                    });
                    
                    // Add empty slots for remaining space
                    const totalMedia = existingMedia.length + currentFiles.length;
                    const remainingSlots = MAX_FILES - totalMedia;
                    for (let i = 0; i < remainingSlots; i++) {
                        const emptyPreview = document.createElement('div');
                        emptyPreview.className = 'media-preview empty';
                        emptyPreview.innerHTML = `<span>No file</span>`;
                        previewContainer.appendChild(emptyPreview);
                    }
                };
                
                displayCurrentFiles();
            } else {
                // Just add empty slots if no current files
                const remainingSlots = MAX_FILES - existingMedia.length;
                for (let i = 0; i < remainingSlots; i++) {
                    const emptyPreview = document.createElement('div');
                    emptyPreview.className = 'media-preview empty';
                    emptyPreview.innerHTML = `<span>No file</span>`;
                    previewContainer.appendChild(emptyPreview);
                }
            }
        }

        function removeExistingMedia(index, fileUrl) {
            // Create a hidden input to track removed files
            let removedInput = document.querySelector(`input[name="removed_evidence[]"][value="${fileUrl}"]`);
            if (!removedInput) {
                removedInput = document.createElement('input');
                removedInput.type = 'hidden';
                removedInput.name = 'removed_evidence[]';
                removedInput.value = fileUrl;
                document.getElementById('illegalActivityForm').appendChild(removedInput);
            }
            
            // Remove the existing evidence input
            const existingInput = document.querySelector(`input[name="existing_evidence[]"][value="${fileUrl}"]`);
            if (existingInput) {
                existingInput.remove();
            }
            
            // Update the preview
            const preview = previewContainer.children[index];
            preview.className = 'media-preview empty';
            preview.innerHTML = `<span>No file</span>`;
        }
    });
</script>
</body>
</html>