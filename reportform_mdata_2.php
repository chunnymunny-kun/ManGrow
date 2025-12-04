<?php
session_start();

if(isset($_SESSION["name"])){
    $loggeduser = $_SESSION["name"];
}else{
    if(isset($_SESSION['qr_url'])){
        $_SESSION['redirect_url'] = $_SESSION['qr_url'];
    }
    if(!isset($_SESSION['redirect_url'])){
        $_SESSION['redirect_url'] = 'reportform_mdata_2.php';
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
    <title>Offline Mangrove Data Report</title>
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#123524">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ManGrow Reports">
    
    <!-- Offline-First CSS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="reportform_2.0_.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Offline Map Resources -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script type="text/javascript" src="app.js" defer></script>
    
    <style>
        /* Offline Status Indicator */
        .offline-indicator {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1000;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .offline-indicator.online {
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }
        
        .offline-indicator.offline {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        /* Saved Reports Section */
        .saved-reports-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #3E7B27;
        }
        
        .saved-reports-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .saved-reports-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .saved-report-item {
            background: white;
            border-radius: 6px;
            padding: 0.8rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        
        .report-info {
            flex: 1;
        }
        
        .report-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.3rem 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }
        
        .btn-sync {
            background: #059669;
            color: white;
        }
        
        .btn-sync:hover {
            background: #047857;
        }
        
        .btn-delete {
            background: #dc2626;
            color: white;
        }
        
        .btn-delete:hover {
            background: #b91c1c;
        }
        
        .btn-load {
            background: #2563eb;
            color: white;
        }
        
        .btn-load:hover {
            background: #1d4ed8;
        }
        
        /* GPS Status */
        .gps-status {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 0.8rem;
            margin-bottom: 1rem;
            display: none;
        }
        
        .gps-status.active {
            display: block;
        }
        
        .gps-status.success {
            background: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
        
        .gps-status.error {
            background: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        
        /* Auto-save indicator */
        .auto-save-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3E7B27;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <!-- Offline Status Indicator -->
    <div id="offlineIndicator" class="offline-indicator online">
        <i class="fas fa-wifi"></i> Online
    </div>
    
    <!-- Auto-save Indicator -->
    <div id="autoSaveIndicator" class="auto-save-indicator">
        <i class="fas fa-save"></i> Data saved locally
    </div>
    
    <header>
        <div class="header-logo"><i class='bx bxs-leaf'></i><span class="logo">ManGrow</span></div>
        <nav class="navbar">
            <?php 
            if (isset($_SESSION["name"])) {
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
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
        
        <!-- Profile Details Popup -->
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
        
        <!-- GPS Status -->
        <div id="gpsStatus" class="gps-status">
            <i class="fas fa-satellite-dish"></i> <span id="gpsStatusText">Searching for GPS location...</span>
        </div>
        
        <!-- Saved Reports Section -->
        <div class="saved-reports-section">
            <div class="saved-reports-header">
                <h3><i class="fas fa-database"></i> Saved Reports (Offline)</h3>
                <button id="syncAllBtn" class="btn-small btn-sync" onclick="syncAllReports()">
                    <i class="fas fa-sync"></i> Sync All
                </button>
            </div>
            <div id="savedReportsList" class="saved-reports-list">
                <!-- Saved reports will be populated here -->
            </div>
        </div>
        
        <!-- Report Form -->
        <div class="form-div">
            <div class="form-content">
                <h2 class="form-title">Mangrove Data Report (Offline Mode)</h2>
                
                <form id="mangroveReportForm" class="two-column-form">
                    <!-- Left Column -->
                    <div class="form-column">
                        <!-- Mangrove Species Visual Selector -->
                        <div class="form-group visual-selector">
                            <label class="section-label">
                                <i class="fas fa-tree"></i> Mangrove Species
                            </label>
                            <p class="note" style="color: #3E7B27; font-size: 0.95em; margin-bottom: 8px;">
                                <i class="fas fa-info-circle"></i>
                                Select one or more mangrove species observed in the area.
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
                                <option value="" disabled selected>Select a mangrove area (loads when online)</option>
                            </select>
                            <input type="hidden" id="cityMunicipality" name="city_municipality">
                            <input type="hidden" id="areaId" name="area_id">
                            <input type="hidden" id="areaNo" name="area_no">
                        </div>
                        
                        <!-- City/Municipality Input -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-city"></i> City/Municipality
                            </label>
                            <input type="text" id="cityMunicipalityInput" name="city_municipality_input" class="form-input" placeholder="Enter city/municipality manually">
                        </div>

                        <!-- Barangay Input -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-pin"></i> Barangays
                            </label>
                            <textarea id="barangaysInput" name="barangays" class="form-textarea" rows="2" placeholder="Enter nearby barangays (comma-separated)"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marker-alt"></i> Location
                            </label>
                            <div id="map" style="height: 250px; border-radius: 8px; margin-bottom: 10px;"></div>
                            <div class="coord-inputs">
                                <div class="coord-input">
                                    <label><i class="fas fa-latitude"></i> Latitude</label>
                                    <input type="text" id="latitude" name="latitude" class="form-input">
                                </div>
                                <div class="coord-input">
                                    <label><i class="fas fa-longitude"></i> Longitude</label>
                                    <input type="text" id="longitude" name="longitude" class="form-input">
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
                            <textarea id="address" name="address" class="form-textarea" rows="2" placeholder="Enter address manually or wait for GPS"></textarea>
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
                                <option value="Healthy">Healthy</option>
                                <option value="Growing">Growing</option>
                                <option value="Needs Attention">Needs Attention</option>
                                <option value="Damaged">Damaged</option>
                                <option value="Dead">Dead</option>
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
                                    <span>Select up to 3 images (stored locally)</span>
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
                        <input type="hidden" id="reporterName" name="reporter_name" value="<?php echo $_SESSION['name'] ?? ''; ?>">

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label>
                                    <input type="checkbox" id="anonymousReport" name="anonymous" value="1">
                                    Submit as Anonymous
                                </label>
                            </div>
                            <button type="button" id="saveLocalBtn" class="submit-button" style="background: #2563eb; margin-bottom: 10px;">
                                <i class="fas fa-save"></i> Save Locally
                            </button>
                            <button type="submit" class="submit-button">
                                <i class="fas fa-paper-plane"></i> Submit Report (Online)
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
    // Offline Management System
    class OfflineReportManager {
        constructor() {
            this.dbName = 'MangroveReportsDB';
            this.version = 1;
            this.db = null;
            this.isOnline = navigator.onLine;
            this.map = null;
            this.marker = null;
            this.userLocation = null;
            this.selectedSpecies = [];
            this.currentGpsWatch = null;
            
            this.init();
        }
        
        async init() {
            await this.initDB();
            this.setupEventListeners();
            this.setupMap();
            this.setupForm();
            this.loadSavedReports();
            this.updateConnectionStatus();
            this.startGpsTracking();
            this.setupAutoSave();
        }
        
        // IndexedDB Setup
        async initDB() {
            return new Promise((resolve, reject) => {
                const request = indexedDB.open(this.dbName, this.version);
                
                request.onerror = () => reject(request.error);
                request.onsuccess = () => {
                    this.db = request.result;
                    resolve();
                };
                
                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    
                    // Create reports store
                    if (!db.objectStoreNames.contains('reports')) {
                        const store = db.createObjectStore('reports', { keyPath: 'id', autoIncrement: true });
                        store.createIndex('timestamp', 'timestamp', { unique: false });
                        store.createIndex('type', 'type', { unique: false });
                    }
                    
                    // Create images store
                    if (!db.objectStoreNames.contains('images')) {
                        const imageStore = db.createObjectStore('images', { keyPath: 'id', autoIncrement: true });
                        imageStore.createIndex('reportId', 'reportId', { unique: false });
                    }
                };
            });
        }
        
        // Save report locally
        async saveReportLocally(reportData, images = []) {
            const transaction = this.db.transaction(['reports', 'images'], 'readwrite');
            const reportStore = transaction.objectStore('reports');
            const imageStore = transaction.objectStore('images');
            
            // Add timestamp and offline flag
            reportData.timestamp = new Date().toISOString();
            reportData.offline = true;
            reportData.synced = false;
            reportData.type = 'mangrove_data';
            
            const reportRequest = reportStore.add(reportData);
            
            return new Promise((resolve, reject) => {
                reportRequest.onsuccess = async () => {
                    const reportId = reportRequest.result;
                    
                    // Save images
                    if (images.length > 0) {
                        for (let i = 0; i < images.length; i++) {
                            const imageData = {
                                reportId: reportId,
                                data: images[i],
                                index: i
                            };
                            await imageStore.add(imageData);
                        }
                    }
                    
                    this.showAutoSaveIndicator();
                    this.loadSavedReports();
                    resolve(reportId);
                };
                
                reportRequest.onerror = () => reject(reportRequest.error);
            });
        }
        
        // Load saved reports
        async loadSavedReports() {
            const transaction = this.db.transaction(['reports'], 'readonly');
            const store = transaction.objectStore('reports');
            const request = store.getAll();
            
            request.onsuccess = () => {
                const reports = request.result;
                this.displaySavedReports(reports);
            };
        }
        
        // Display saved reports in UI
        displaySavedReports(reports) {
            const container = document.getElementById('savedReportsList');
            
            if (reports.length === 0) {
                container.innerHTML = '<p style="color: #666; font-style: italic;">No saved reports</p>';
                return;
            }
            
            container.innerHTML = '';
            
            reports.forEach(report => {
                const item = document.createElement('div');
                item.className = 'saved-report-item';
                
                const statusIcon = report.synced ? 
                    '<i class="fas fa-check-circle" style="color: #059669;"></i>' : 
                    '<i class="fas fa-clock" style="color: #f59e0b;"></i>';
                
                item.innerHTML = `
                    <div class="report-info">
                        <strong>${statusIcon} ${report.species ? JSON.parse(report.species)[0] : 'Unknown Species'}</strong><br>
                        <small>${new Date(report.timestamp).toLocaleString()}</small><br>
                        <small>Status: ${report.mangrove_status || 'N/A'} | Area: ${report.area_planted || 'N/A'}m²</small>
                    </div>
                    <div class="report-actions">
                        <button class="btn-small btn-load" onclick="offlineManager.loadReport(${report.id})">
                            <i class="fas fa-edit"></i> Load
                        </button>
                        ${!report.synced && this.isOnline ? `
                            <button class="btn-small btn-sync" onclick="offlineManager.syncReport(${report.id})">
                                <i class="fas fa-sync"></i> Sync
                            </button>
                        ` : ''}
                        <button class="btn-small btn-delete" onclick="offlineManager.deleteReport(${report.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                container.appendChild(item);
            });
        }
        
        // Load report into form
        async loadReport(reportId) {
            const transaction = this.db.transaction(['reports', 'images'], 'readonly');
            const reportStore = transaction.objectStore('reports');
            const imageStore = transaction.objectStore('images');
            
            const reportRequest = reportStore.get(reportId);
            
            reportRequest.onsuccess = () => {
                const report = reportRequest.result;
                if (!report) return;
                
                // Populate form fields
                if (report.species) {
                    this.selectedSpecies = JSON.parse(report.species);
                    this.updateSpeciesDisplay();
                }
                
                document.getElementById('cityMunicipalityInput').value = report.city_municipality || '';
                document.getElementById('barangaysInput').value = report.barangays || '';
                document.getElementById('latitude').value = report.latitude || '';
                document.getElementById('longitude').value = report.longitude || '';
                document.getElementById('address').value = report.address || '';
                document.getElementById('dateRecorded').value = report.date_recorded || '';
                document.getElementById('status').value = report.mangrove_status || '';
                document.getElementById('areaPlanted').value = report.area_planted || '100';
                document.getElementById('remarks').value = report.remarks || '';
                document.getElementById('anonymousReport').checked = report.anonymous === '1';
                
                // Update map if coordinates exist
                if (report.latitude && report.longitude) {
                    const latlng = { lat: parseFloat(report.latitude), lng: parseFloat(report.longitude) };
                    this.updateLocation(latlng);
                    this.map.setView([latlng.lat, latlng.lng], 15);
                }
                
                // Load images
                const imageRequest = imageStore.index('reportId').getAll(reportId);
                imageRequest.onsuccess = () => {
                    const images = imageRequest.result;
                    // Handle image loading if needed
                };
                
                alert('Report loaded into form!');
            };
        }
        
        // Sync individual report
        async syncReport(reportId) {
            if (!this.isOnline) {
                alert('Cannot sync while offline');
                return;
            }
            
            const transaction = this.db.transaction(['reports', 'images'], 'readonly');
            const reportStore = transaction.objectStore('reports');
            const imageStore = transaction.objectStore('images');
            
            const reportRequest = reportStore.get(reportId);
            
            reportRequest.onsuccess = async () => {
                const report = reportRequest.result;
                if (!report || report.synced) return;
                
                try {
                    // Get images
                    const imageRequest = imageStore.index('reportId').getAll(reportId);
                    imageRequest.onsuccess = async () => {
                        const images = imageRequest.result;
                        
                        // Create FormData for submission
                        const formData = new FormData();
                        
                        // Add all report fields
                        Object.keys(report).forEach(key => {
                            if (key !== 'id' && key !== 'timestamp' && key !== 'offline' && key !== 'synced' && key !== 'type') {
                                formData.append(key, report[key]);
                            }
                        });
                        
                        // Add images as blobs
                        images.forEach((image, index) => {
                            const blob = this.base64ToBlob(image.data);
                            formData.append('images[]', blob, `image_${index}.jpg`);
                        });
                        
                        // Submit to server
                        const response = await fetch('uploadreport_m.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (response.ok) {
                            // Mark as synced
                            await this.markReportSynced(reportId);
                            this.loadSavedReports();
                            alert('Report synced successfully!');
                        } else {
                            throw new Error('Server error');
                        }
                    };
                } catch (error) {
                    console.error('Sync error:', error);
                    alert('Failed to sync report. Please try again.');
                }
            };
        }
        
        // Mark report as synced
        async markReportSynced(reportId) {
            const transaction = this.db.transaction(['reports'], 'readwrite');
            const store = transaction.objectStore('reports');
            
            const getRequest = store.get(reportId);
            getRequest.onsuccess = () => {
                const report = getRequest.result;
                report.synced = true;
                store.put(report);
            };
        }
        
        // Delete report
        async deleteReport(reportId) {
            if (!confirm('Are you sure you want to delete this report?')) return;
            
            const transaction = this.db.transaction(['reports', 'images'], 'readwrite');
            const reportStore = transaction.objectStore('reports');
            const imageStore = transaction.objectStore('images');
            
            // Delete images first
            const imageRequest = imageStore.index('reportId').getAll(reportId);
            imageRequest.onsuccess = () => {
                const images = imageRequest.result;
                images.forEach(image => {
                    imageStore.delete(image.id);
                });
                
                // Delete report
                reportStore.delete(reportId);
                this.loadSavedReports();
            };
        }
        
        // Sync all reports
        async syncAllReports() {
            if (!this.isOnline) {
                alert('Cannot sync while offline');
                return;
            }
            
            const transaction = this.db.transaction(['reports'], 'readonly');
            const store = transaction.objectStore('reports');
            const request = store.getAll();
            
            request.onsuccess = () => {
                const reports = request.result.filter(r => !r.synced);
                
                if (reports.length === 0) {
                    alert('No reports to sync');
                    return;
                }
                
                let syncCount = 0;
                reports.forEach(async report => {
                    try {
                        await this.syncReport(report.id);
                        syncCount++;
                        
                        if (syncCount === reports.length) {
                            alert(`All ${syncCount} reports synced successfully!`);
                        }
                    } catch (error) {
                        console.error('Error syncing report:', report.id, error);
                    }
                });
            };
        }
        
        // Connection status management
        setupEventListeners() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.updateConnectionStatus();
            });
            
            window.addEventListener('offline', () => {
                this.isOnline = false;
                this.updateConnectionStatus();
            });
        }
        
        updateConnectionStatus() {
            const indicator = document.getElementById('offlineIndicator');
            if (this.isOnline) {
                indicator.className = 'offline-indicator online';
                indicator.innerHTML = '<i class="fas fa-wifi"></i> Online';
            } else {
                indicator.className = 'offline-indicator offline';
                indicator.innerHTML = '<i class="fas fa-wifi-slash"></i> Offline';
            }
        }
        
        // GPS Tracking
        startGpsTracking() {
            if (navigator.geolocation) {
                // Get initial position
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGpsSuccess(position),
                    (error) => this.handleGpsError(error),
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                );
                
                // Watch position changes
                this.currentGpsWatch = navigator.geolocation.watchPosition(
                    (position) => this.handleGpsSuccess(position),
                    (error) => this.handleGpsError(error),
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
                );
            } else {
                this.showGpsStatus('GPS not supported by this device', 'error');
            }
        }
        
        handleGpsSuccess(position) {
            const latlng = {
                lat: position.coords.latitude,
                lng: position.coords.longitude
            };
            
            this.userLocation = latlng;
            this.updateLocation(latlng);
            
            const accuracy = position.coords.accuracy;
            this.showGpsStatus(`GPS location found (±${Math.round(accuracy)}m accuracy)`, 'success');
            
            // Auto-fill address if online
            if (this.isOnline) {
                this.reverseGeocode(latlng.lat, latlng.lng);
            }
        }
        
        handleGpsError(error) {
            let message = 'GPS error: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Location access denied';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Location unavailable';
                    break;
                case error.TIMEOUT:
                    message += 'Location timeout';
                    break;
                default:
                    message += 'Unknown error';
                    break;
            }
            this.showGpsStatus(message, 'error');
        }
        
        showGpsStatus(message, type = 'info') {
            const status = document.getElementById('gpsStatus');
            const text = document.getElementById('gpsStatusText');
            
            text.textContent = message;
            status.className = `gps-status active ${type}`;
            
            // Hide after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    status.classList.remove('active');
                }, 5000);
            }
        }
        
        // Map setup
        setupMap() {
            this.map = L.map('map').setView([14.64852, 120.47318], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(this.map);
            
            this.map.on('click', (e) => {
                this.updateLocation(e.latlng);
                if (this.isOnline) {
                    this.reverseGeocode(e.latlng.lat, e.latlng.lng);
                }
            });
        }
        
        updateLocation(latlng) {
            document.getElementById('latitude').value = latlng.lat.toFixed(6);
            document.getElementById('longitude').value = latlng.lng.toFixed(6);
            
            if (this.marker) this.map.removeLayer(this.marker);
            
            this.marker = L.marker(latlng, { draggable: true })
                .addTo(this.map)
                .bindPopup("Report location").openPopup();
            
            this.marker.on('dragend', (e) => {
                const newLatLng = this.marker.getLatLng();
                this.updateLocation(newLatLng);
                if (this.isOnline) {
                    this.reverseGeocode(newLatLng.lat, newLatLng.lng);
                }
            });
        }
        
        // Reverse geocoding (online only)
        async reverseGeocode(lat, lng) {
            if (!this.isOnline) return;
            
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                const data = await response.json();
                
                if (data.display_name) {
                    document.getElementById('address').value = data.display_name;
                }
            } catch (error) {
                console.error('Geocoding error:', error);
            }
        }
        
        // Form setup
        setupForm() {
            // Species selection
            const speciesCards = document.querySelectorAll('.species-card');
            speciesCards.forEach(card => {
                card.addEventListener('click', () => {
                    const species = card.dataset.species;
                    
                    if (card.classList.contains('active')) {
                        card.classList.remove('active');
                        this.selectedSpecies = this.selectedSpecies.filter(s => s !== species);
                    } else {
                        card.classList.add('active');
                        this.selectedSpecies.push(species);
                    }
                    
                    this.updateSpeciesDisplay();
                });
            });
            
            // Area controls
            document.getElementById('increaseArea').addEventListener('click', () => {
                const input = document.getElementById('areaPlanted');
                input.stepUp();
            });
            
            document.getElementById('decreaseArea').addEventListener('click', () => {
                const input = document.getElementById('areaPlanted');
                if (input.value > 100) {
                    input.stepDown();
                }
            });
            
            // Date setup
            const dateInput = document.getElementById('dateRecorded');
            const now = new Date();
            dateInput.value = this.toDatetimeLocal(now);
            
            // Locate button
            document.getElementById('locateBtn').addEventListener('click', () => {
                this.startGpsTracking();
            });
            
            // Save locally button
            document.getElementById('saveLocalBtn').addEventListener('click', () => {
                this.saveCurrentForm();
            });
            
            // Form submission
            document.getElementById('mangroveReportForm').addEventListener('submit', (e) => {
                e.preventDefault();
                
                if (this.isOnline) {
                    this.submitForm();
                } else {
                    alert('Cannot submit while offline. Data will be saved locally.');
                    this.saveCurrentForm();
                }
            });
            
            // Image handling
            document.getElementById('mangroveImages').addEventListener('change', (e) => {
                this.handleImageUpload(e);
            });
        }
        
        updateSpeciesDisplay() {
            const display = document.getElementById('selectedSpeciesDisplay');
            const hiddenInput = document.getElementById('mangroveSpecies');
            
            if (this.selectedSpecies.length === 0) {
                display.innerHTML = '<span style="color: #666; font-style: italic;">No species selected</span>';
                hiddenInput.value = '';
            } else {
                const speciesNames = this.selectedSpecies.map(species => {
                    const card = document.querySelector(`[data-species="${species}"]`);
                    const commonName = card.querySelector('.species-name').textContent;
                    const scientificName = card.querySelector('.scientific-name').textContent;
                    return `${commonName} (${scientificName})`;
                });
                
                display.innerHTML = '<strong>Selected:</strong> ' + speciesNames.join(', ');
                hiddenInput.value = JSON.stringify(this.selectedSpecies);
            }
        }
        
        // Handle image upload and convert to base64
        handleImageUpload(event) {
            const files = Array.from(event.target.files).slice(0, 3);
            const previewContainer = document.getElementById('imagePreviews');
            
            this.createEmptyPreviews();
            
            files.forEach((file, index) => {
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" exceeds 5MB limit`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = previewContainer.children[index];
                    preview.className = 'image-preview';
                    preview.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="remove-image" data-index="${index}">&times;</button>
                    `;
                    
                    preview.querySelector('.remove-image').addEventListener('click', () => {
                        this.removeImage(index);
                    });
                };
                reader.readAsDataURL(file);
            });
        }
        
        createEmptyPreviews() {
            const container = document.getElementById('imagePreviews');
            container.innerHTML = '';
            for (let i = 0; i < 3; i++) {
                const preview = document.createElement('div');
                preview.className = 'image-preview empty';
                preview.innerHTML = `<span>No image</span>`;
                container.appendChild(preview);
            }
        }
        
        removeImage(index) {
            const input = document.getElementById('mangroveImages');
            const dt = new DataTransfer();
            const files = Array.from(input.files);
            
            files.splice(index, 1);
            files.forEach(file => dt.items.add(file));
            
            input.files = dt.files;
            input.dispatchEvent(new Event('change'));
        }
        
        // Save current form data
        async saveCurrentForm() {
            const formData = this.getFormData();
            
            if (!this.validateForm(formData)) {
                return;
            }
            
            // Get images as base64
            const images = await this.getImagesAsBase64();
            
            try {
                await this.saveReportLocally(formData, images);
                alert('Report saved locally! You can submit it when you\'re back online.');
                this.clearForm();
            } catch (error) {
                console.error('Error saving locally:', error);
                alert('Failed to save report locally');
            }
        }
        
        // Get form data
        getFormData() {
            return {
                species: JSON.stringify(this.selectedSpecies),
                city_municipality: document.getElementById('cityMunicipalityInput').value,
                barangays: document.getElementById('barangaysInput').value,
                latitude: document.getElementById('latitude').value,
                longitude: document.getElementById('longitude').value,
                address: document.getElementById('address').value,
                date_recorded: document.getElementById('dateRecorded').value,
                mangrove_status: document.getElementById('status').value,
                area_planted: document.getElementById('areaPlanted').value,
                remarks: document.getElementById('remarks').value,
                planted_by: document.getElementById('plantedBy').value,
                reporter_name: document.getElementById('reporterName').value,
                anonymous: document.getElementById('anonymousReport').checked ? '1' : '0',
                report_type: 'Mangrove Data Report'
            };
        }
        
        // Validate form
        validateForm(data) {
            if (this.selectedSpecies.length === 0) {
                alert('Please select at least one mangrove species');
                return false;
            }
            
            if (!data.latitude || !data.longitude) {
                alert('Please set a location');
                return false;
            }
            
            if (!data.date_recorded) {
                alert('Please set a date');
                return false;
            }
            
            if (!data.mangrove_status) {
                alert('Please select a status');
                return false;
            }
            
            return true;
        }
        
        // Get images as base64
        async getImagesAsBase64() {
            const images = [];
            const fileInput = document.getElementById('mangroveImages');
            
            for (let file of fileInput.files) {
                const base64 = await this.fileToBase64(file);
                images.push(base64);
            }
            
            return images;
        }
        
        fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = () => resolve(reader.result);
                reader.onerror = error => reject(error);
            });
        }
        
        base64ToBlob(base64) {
            const parts = base64.split(',');
            const byteCharacters = atob(parts[1]);
            const byteArrays = [];
            
            for (let offset = 0; offset < byteCharacters.length; offset += 512) {
                const slice = byteCharacters.slice(offset, offset + 512);
                const byteNumbers = new Array(slice.length);
                
                for (let i = 0; i < slice.length; i++) {
                    byteNumbers[i] = slice.charCodeAt(i);
                }
                
                const byteArray = new Uint8Array(byteNumbers);
                byteArrays.push(byteArray);
            }
            
            return new Blob(byteArrays, { type: 'image/jpeg' });
        }
        
        // Submit form online
        async submitForm() {
            const formData = new FormData();
            const data = this.getFormData();
            
            if (!this.validateForm(data)) {
                return;
            }
            
            // Add all form data
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
            
            // Add images
            const fileInput = document.getElementById('mangroveImages');
            for (let file of fileInput.files) {
                formData.append('images[]', file);
            }
            
            try {
                const response = await fetch('uploadreport_m.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    alert('Report submitted successfully!');
                    this.clearForm();
                } else {
                    throw new Error('Server error');
                }
            } catch (error) {
                console.error('Submit error:', error);
                alert('Failed to submit report. Saving locally instead.');
                this.saveCurrentForm();
            }
        }
        
        // Clear form
        clearForm() {
            document.getElementById('mangroveReportForm').reset();
            this.selectedSpecies = [];
            this.updateSpeciesDisplay();
            this.createEmptyPreviews();
            
            // Clear map marker
            if (this.marker) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }
            
            // Reset date
            const dateInput = document.getElementById('dateRecorded');
            dateInput.value = this.toDatetimeLocal(new Date());
        }
        
        toDatetimeLocal(date) {
            const pad = n => n.toString().padStart(2, '0');
            return date.getFullYear() + '-' +
                pad(date.getMonth() + 1) + '-' +
                pad(date.getDate()) + 'T' +
                pad(date.getHours()) + ':' +
                pad(date.getMinutes());
        }
        
        // Auto-save functionality
        setupAutoSave() {
            const form = document.getElementById('mangroveReportForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.autoSave();
                });
            });
        }
        
        autoSave() {
            const formData = this.getFormData();
            localStorage.setItem('mangrove_form_autosave', JSON.stringify(formData));
            this.showAutoSaveIndicator();
        }
        
        loadAutoSave() {
            const saved = localStorage.getItem('mangrove_form_autosave');
            if (saved) {
                const data = JSON.parse(saved);
                
                // Populate form with saved data
                Object.keys(data).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        element.value = data[key];
                    }
                });
                
                // Handle species selection
                if (data.species) {
                    this.selectedSpecies = JSON.parse(data.species);
                    this.updateSpeciesDisplay();
                }
            }
        }
        
        showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }
    }
    
    // Global functions for buttons
    window.offlineManager = null;
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineManager = new OfflineReportManager();
    });
</script>
</body>
</html>
