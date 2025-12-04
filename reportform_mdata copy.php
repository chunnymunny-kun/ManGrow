<?php
session_start();

if(isset($_SESSION["name"])){
    $loggeduser = $_SESSION["name"];
}else{
    if(isset($_SESSION['qr_url'])){
        $_SESSION['redirect_url'] = $_SESSION['qr_url'];
    }
    if(!isset($_SESSION['redirect_url'])){
        $_SESSION['redirect_url'] = 'reportform_mdata.php';
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
                <h2 class="form-title">Mangrove Data Report</h2>
                
                <form id="mangroveReportForm" action="uploadreport_m.php" method="POST" enctype="multipart/form-data" class="two-column-form">
                    <!-- Left Column -->
                    <div class="form-column">
                        <!-- Mangrove Species Visual Selector -->
                        <div class="form-group visual-selector">
                            <label class="section-label">
                                <i class="fas fa-tree"></i> Mangrove Species
                            </label>
                            <p class="note" style="color: #3E7B27; font-size: 0.95em; margin-bottom: 8px;">
                                <i class="fas fa-info-circle"></i>
                                Select one or more mangrove species observed in the area. You can click multiple species cards to select them.
                            </p>
                            <input type="hidden" name="report_type" value="Mangrove Data Report">
                            <div class="species-gallery">
                                <div class="species-card" data-species="Rhizophora Apiculata">
                                    <div class="species-image" style="background-image: url('images/bakawan\ ekalal.webp')"></div>
                                    <div class="name-div">
                                    <div class="species-name">Bakawan Lalake</div>
                                    <div class="scientific-name">Rhizophora apiculata</div>
                                    </div>
                                </div>
                                <div class="species-card" data-species="Rhizophora Mucronata">
                                    <div class="species-image" style="background-image: url('images/bakawan\ eabab.webp')"></div>
                                    <div class="name-div">
                                    <div class="species-name">Bakawan Babae</div>
                                    <div class="scientific-name">Rhizophora mucronata</div>
                                    </div>
                                </div>
                                <div class="species-card" data-species="Avicennia Marina">
                                    <div class="species-image" style="background-image: url('images/bungalonskie.webp')"></div>
                                    <div class="name-div">
                                    <div class="species-name">Bungalon</div>
                                    <div class="scientific-name">Avicennia marina</div>
                                    </div>
                                </div>
                                <div class="species-card" data-species="Sonneratia Alba">
                                    <div class="species-image" style="background-image: url('images/palapatcakes.jpg')"></div>
                                    <div class="name-div">
                                    <div class="species-name">Palapat</div>
                                    <div class="scientific-name">Sonneratia alba</div>
                                    </div>
                                </div>
                            </div>
                            <div class="selected-species-display" id="selectedSpeciesDisplay" style="margin-top: 10px; padding: 8px; background: #f9f9f9; border-radius: 4px; min-height: 20px;">
                                <span style="color: #666; font-style: italic;">No species selected</span>
                            </div>
                            <input type="hidden" id="mangroveSpecies" name="species" required>
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
                            <input type="hidden" id="cityMunicipality" name="city_municipality">
                            <input type="hidden" id="areaId" name="area_id">
                            <input type="hidden" id="areaNo" name="area_no">
                        </div>
                        <!-- City/Municipality Dropdown -->
                            <div class="form-group">
                                <label class="section-label">
                                    <i class="fas fa-city"></i> City/Municipality
                                </label>
                                <select id="cityMunicipalitySelect" name="city_municipality" required class="form-input">
                                    <option value="" disabled selected>Select City/Municipality</option>
                                    <?php
                                    include 'database.php';
                                    $query = "SELECT * FROM citymunicipalitytbl";
                                    $result = mysqli_query($connection, $query);
                                    while($row = $result->fetch_assoc()):
                                    ?>
                                    <option value="<?= htmlspecialchars($row['city']) ?>">
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
                                <i class="fas fa-map-marker-alt"></i> Location
                            </label>
                            <div id="map" style="height: 250px; border-radius: 8px; margin-bottom: 10px;"></div>
                            <div class="coord-inputs">
                                <div class="coord-input">
                                    <label><i class="fas fa-latitude"></i> Latitude</label>
                                    <input type="text" id="latitude" name="latitude" readonly class="form-input">
                                </div>
                                <div class="coord-input">
                                    <label><i class="fas fa-longitude"></i> Longitude</label>
                                    <input type="text" id="longitude" name="longitude" readonly class="form-input">
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
                            <textarea id="address" name="address" readonly class="form-textarea" rows="2" placeholder="Address will appear here..."></textarea>
                        </div>
                        <!-- Date Recorded -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="far fa-calendar-alt"></i> Date Recorded
                            </label>
                            <input type="datetime-local" id="dateRecorded" name="date_recorded" required class="form-input">
                        </div>

                        <!-- Status -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-heartbeat"></i> Status/Condition
                            </label>
                            <select id="status" name="mangrove_status" required class="form-input">
                                <option value="" disabled selected>Select status</option>
                                <option value="Healthy">Healthy <i class="fas fa-smile"></i></option>
                                <option value="Growing">Growing <i class="fas fa-seedling"></i></option>
                                <option value="Needs Attention">Needs Attention <i class="fas fa-exclamation-triangle"></i></option>
                                <option value="Damaged">Damaged <i class="fas fa-bandaid"></i></option>
                                <option value="Dead">Dead <i class="fas fa-skull-crossbones"></i></option>
                            </select>
                        </div>

                        <!-- Area Planted -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-ruler-combined"></i> Area Affected (m²)
                            </label>
                            <div class="area-control">
                                <button type="button" class="area-btn" id="decreaseArea">-100</button>
                                <input type="number" id="areaPlanted" name="area_planted" value="100" min="100" step="100" class="form-input area-input">
                                <button type="button" class="area-btn" id="increaseArea">+100</button>
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-camera-retro"></i> Upload Images (Max 3)
                            </label>
                            <div class="image-upload-container">
                                <input type="file" id="mangroveImages" name="images[]" accept="image/*" multiple class="image-upload">
                                <label for="mangroveImages" class="upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> 
                                    <span>Select up to 3 images (max 5MB each)</span>
                                </label>
                                <div id="imagePreviews" class="image-previews"></div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-comment-dots"></i> Remarks
                            </label>
                            <textarea id="remarks" name="remarks" rows="4" class="form-textarea" placeholder="Additional observations..."></textarea>
                        </div>

                        <!-- Hidden Fields -->
                        <input type="hidden" id="plantedBy" name="planted_by" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label>
                                    <input type="checkbox" id="anonymousReport" name="anonymous" value="1">
                                    Submit as Anonymous
                                </label>
                            </div>
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
    let mangroveAreaList = []; // Stores only area_no and city_municipality
    let selectedAreaLayer = null;
    let lastGeocodeTime = 0;
    const GEOCODE_DELAY = 1000; // 1 second delay between geocoding requests
    let selectedCityMunicipality = '';
    let selectedBarangays = [];

    // Initialize map
    function initMap() {
        map = L.map('map').setView([14.64852, 120.47318], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Try to locate user immediately
        map.locate({setView: false, maxZoom: 16, enableHighAccuracy: true});

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

    // Initialize everything when DOM loads
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        fetchMangroveAreas();
        
        // City/Municipality dropdown change handler
        document.getElementById('cityMunicipalitySelect').addEventListener('change', function() {
            selectedCityMunicipality = this.value;
            document.getElementById('cityMunicipality').value = this.value;
            populateBarangayCheckboxes(this.value);
            
            // Silent validation - no alert
            validateCityMatch();
        });

        // Event listeners
        document.getElementById('locateBtn').addEventListener('click', function() {
            map.locate({setView: true, maxZoom: 16, enableHighAccuracy: true});
        });

        document.getElementById('mangroveAreaSelect').addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                document.getElementById('cityMunicipality').value = selectedOption.dataset.city || '';
                document.getElementById('areaId').value = this.value; // Set the area_id from option value
                document.getElementById('areaNo').value = selectedOption.dataset.areaNo; // Set area_no from dataset
                displaySelectedArea(this.value);
            }
        });
            
        // Species selection - modified for multiple selection
        const speciesCards = document.querySelectorAll('.species-card');
        let selectedSpecies = [];
        
        function updateSpeciesDisplay() {
            const displayElement = document.getElementById('selectedSpeciesDisplay');
            const hiddenInput = document.getElementById('mangroveSpecies');
            
            if (selectedSpecies.length === 0) {
                displayElement.innerHTML = '<span style="color: #666; font-style: italic;">No species selected</span>';
                hiddenInput.value = '';
            } else {
                const speciesNames = selectedSpecies.map(species => {
                    // Get the display name from the species card
                    const card = document.querySelector(`[data-species="${species}"]`);
                    const commonName = card.querySelector('.species-name').textContent;
                    const scientificName = card.querySelector('.scientific-name').textContent;
                    return `${commonName} (${scientificName})`;
                });
                
                displayElement.innerHTML = '<strong>Selected:</strong> ' + speciesNames.join(', ');
                hiddenInput.value = JSON.stringify(selectedSpecies);
            }
        }
        
        speciesCards.forEach(card => {
            card.addEventListener('click', function() {
                const species = this.dataset.species;
                
                if (this.classList.contains('active')) {
                    // Remove from selection
                    this.classList.remove('active');
                    selectedSpecies = selectedSpecies.filter(s => s !== species);
                } else {
                    // Add to selection
                    this.classList.add('active');
                    selectedSpecies.push(species);
                }
                
                updateSpeciesDisplay();
            });
        });

        // Date picker - set min to yesterday
        const dateInput = document.getElementById('dateRecorded');
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);

        function toDatetimeLocal(date) {
            // Returns YYYY-MM-DDTHH:MM
            const pad = n => n.toString().padStart(2, '0');
            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + 'T' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes());
        }

        dateInput.min = toDatetimeLocal(yesterday);
        dateInput.value = toDatetimeLocal(today);

        // Area control buttons
        const areaInput = document.getElementById('areaPlanted');
        document.getElementById('increaseArea').addEventListener('click', function() {
            areaInput.stepUp();
        });
        document.getElementById('decreaseArea').addEventListener('click', function() {
            if (areaInput.value > 100) {
                areaInput.stepDown();
            }
        });

        // Form submission
        document.getElementById('mangroveReportForm').addEventListener('submit', function(e) {
            // Validate species selection
            if (selectedSpecies.length === 0) {
                alert('Please select at least one mangrove species');
                e.preventDefault();
                return;
            }
            
            // Validate location
            if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
                alert('Please get your location first');
                e.preventDefault();
                return;
            }
            
            // Validate city match
            if (!validateCityMatch()) {
                alert('The selected city/municipality must match the address location. Please correct your selection before submitting.');
                e.preventDefault();
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
            
            // Show loading indicator
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        });

        // Image upload handling
        const imageInput = document.getElementById('mangroveImages');
        const previewContainer = document.getElementById('imagePreviews');

        // Create empty preview slots initially
        function createEmptyPreviews() {
            previewContainer.innerHTML = '';
            for (let i = 0; i < 3; i++) {
                const preview = document.createElement('div');
                preview.className = 'image-preview empty';
                preview.innerHTML = `<span>No image</span>`;
                previewContainer.appendChild(preview);
            }
        }

        // Initialize empty previews
        createEmptyPreviews();

        imageInput.addEventListener('change', function() {
            const files = Array.from(this.files).slice(0, 3); // Limit to 3 files
            
            // Clear all previews first
            createEmptyPreviews();
            
            if (files.length > 3) {
                alert('Maximum 3 images allowed');
                this.value = '';
                return;
            }
            
            files.forEach((file, index) => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" exceeds 5MB limit`);
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    alert(`File "${file.name}" is not an image`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = previewContainer.children[index];
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button class="remove-image" data-index="${index}">&times;</button>
                    `;
                    
                    // Add remove button handler
                    preview.querySelector('.remove-image').addEventListener('click', function() {
                        removeImage(index);
                    });
                };
                reader.readAsDataURL(file);
            });
        });

        function removeImage(index) {
            const dt = new DataTransfer();
            const files = Array.from(imageInput.files);
            
            files.splice(index, 1);
            
            files.forEach(file => {
                dt.items.add(file);
            });
            
            imageInput.files = dt.files;
            imageInput.dispatchEvent(new Event('change'));
        }

        // Get the city select element and trigger change if we have a valid selection
        const citySelect = document.getElementById('cityMunicipalitySelect');
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
    });
</script>
</body>
</html>