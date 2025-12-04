<?php
session_start();

if(isset($_SESSION["name"])){
    $loggeduser = $_SESSION["name"];
}else{
    if(isset($_SESSION['qr_url'])){
        $_SESSION['redirect_url'] = $_SESSION['qr_url'];
    }
    if(!isset($_SESSION['redirect_url'])){
        $_SESSION['redirect_url'] = 'reportform_iacts_2.php';
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

// Handle resubmission of existing report
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
    <title><?php echo $is_resubmission ? 'Resubmit' : 'Create'; ?> Illegal Activity Report (Offline)</title>
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
        
        /* Resubmission Banner */
        .resubmission-banner {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .resubmission-banner h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.2rem;
        }
        
        .resubmission-banner p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Saved Reports Section */
        .saved-reports-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #dc2626;
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
            background: #dc2626;
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
        
        /* Priority indicator */
        .priority-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .priority-normal {
            background: #e5f3ff;
            color: #0066cc;
        }
        
        .priority-emergency {
            background: #ffe5e5;
            color: #cc0000;
            animation: emergencyPulse 2s infinite;
        }
        
        @keyframes emergencyPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
        <i class="fas fa-save"></i> Report saved locally
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
        
        <?php if ($is_resubmission): ?>
        <div class="resubmission-banner">
            <h3><i class="fas fa-redo"></i> Resubmitting Report</h3>
            <p>You are resubmitting a report that was previously rejected. Please review and update the information below.</p>
        </div>
        <?php endif; ?>
        
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
                <h3><i class="fas fa-exclamation-triangle"></i> Saved Illegal Activity Reports (Offline)</h3>
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
                <h2 class="form-title">Illegal Activity Report (Offline Mode)</h2>
                
                <form id="illegalActivityForm" class="two-column-form">
                    <!-- Left Column -->
                    <div class="form-column">
                        <!-- Priority Level -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-exclamation-circle"></i> Priority Level
                            </label>
                            <select id="priority" name="priority" required class="form-input">
                                <option value="" disabled selected>Select priority level</option>
                                <option value="Normal">Normal</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                            <div id="priorityIndicator" class="priority-indicator" style="display: none; margin-top: 0.5rem;">
                                <i class="fas fa-info-circle"></i>
                                <span id="priorityText">Select priority level</span>
                            </div>
                        </div>
                        
                        <!-- Incident Type -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-list"></i> Type of Incident
                            </label>
                            <select id="incidentType" name="incident_type" required class="form-input">
                                <option value="" disabled selected>Select incident type</option>
                                <option value="Illegal Cutting">Illegal Cutting of Mangroves</option>
                                <option value="Illegal Dumping">Illegal Dumping of Waste</option>
                                <option value="Unauthorized Construction">Unauthorized Construction</option>
                                <option value="Pollution">Water/Soil Pollution</option>
                                <option value="Poaching">Wildlife Poaching</option>
                                <option value="Other">Other Environmental Violation</option>
                            </select>
                        </div>
                        
                        <!-- Mangrove Area -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marked-alt"></i> Mangrove Area (if applicable)
                            </label>
                            <select id="mangroveAreaSelect" name="mangroveArea" class="form-input">
                                <option value="" selected>Select a mangrove area (loads when online)</option>
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
                            <input type="text" id="cityMunicipalityInput" name="city_municipality_input" class="form-input" placeholder="Enter city/municipality manually" required>
                        </div>

                        <!-- Barangay Input -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-pin"></i> Barangays
                            </label>
                            <textarea id="barangaysInput" name="barangays" class="form-textarea" rows="2" placeholder="Enter nearby barangays (comma-separated)"></textarea>
                        </div>

                        <!-- Location Map -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-map-marker-alt"></i> Incident Location
                            </label>
                            <div id="map" style="height: 250px; border-radius: 8px; margin-bottom: 10px;"></div>
                            <div class="coord-inputs">
                                <div class="coord-input">
                                    <label><i class="fas fa-latitude"></i> Latitude</label>
                                    <input type="text" id="latitude" name="latitude" class="form-input" required>
                                </div>
                                <div class="coord-input">
                                    <label><i class="fas fa-longitude"></i> Longitude</label>
                                    <input type="text" id="longitude" name="longitude" class="form-input" required>
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
                            <textarea id="address" name="address" class="form-textarea" rows="2" placeholder="Enter address manually or wait for GPS" required></textarea>
                        </div>
                        
                        <!-- Date Reported -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="far fa-calendar-alt"></i> Date & Time of Incident
                            </label>
                            <input type="datetime-local" id="dateReported" name="report_date" required class="form-input">
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-file-alt"></i> Detailed Description
                            </label>
                            <textarea id="description" name="description" rows="5" class="form-textarea" placeholder="Describe the incident in detail..." required></textarea>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-phone"></i> Contact Number (Optional)
                            </label>
                            <input type="tel" id="contactNo" name="contact_no" class="form-input" placeholder="Your contact number">
                        </div>

                        <!-- Image Upload -->
                        <div class="form-group">
                            <label class="section-label">
                                <i class="fas fa-camera"></i> Evidence Photos/Videos (Max 3)
                            </label>
                            <div class="image-upload-container">
                                <input type="file" id="evidenceFiles" name="images[]" accept="image/*,video/*" multiple class="image-upload">
                                <label for="evidenceFiles" class="upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i> 
                                    <span>Select up to 3 files (stored locally)</span>
                                </label>
                                <div id="evidencePreviews" class="image-previews"></div>
                            </div>
                        </div>

                        <!-- Hidden Fields -->
                        <input type="hidden" name="report_type" value="Illegal Activity Report">
                        <input type="hidden" id="reporterId" name="reporter_id" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">
                        <input type="hidden" id="reporterName" name="reporter_name" value="<?php echo $_SESSION['name'] ?? ''; ?>">
                        
                        <?php if ($is_resubmission): ?>
                        <input type="hidden" name="original_report_id" value="<?php echo $original_report_id; ?>">
                        <input type="hidden" name="resubmission" value="1">
                        <?php endif; ?>

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
    // Offline Illegal Activity Report Manager
    class OfflineIllegalActivityManager {
        constructor() {
            this.dbName = 'IllegalActivityReportsDB';
            this.version = 1;
            this.db = null;
            this.isOnline = navigator.onLine;
            this.map = null;
            this.marker = null;
            this.userLocation = null;
            this.currentGpsWatch = null;
            
            // Check if this is a resubmission
            this.isResubmission = <?php echo $is_resubmission ? 'true' : 'false'; ?>;
            this.originalData = <?php echo $original_report_data ? json_encode($original_report_data) : 'null'; ?>;
            
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
            
            // Pre-fill form if resubmission
            if (this.isResubmission && this.originalData) {
                this.populateFormWithOriginalData();
            }
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
                        store.createIndex('priority', 'priority', { unique: false });
                        store.createIndex('type', 'type', { unique: false });
                    }
                    
                    // Create evidence store
                    if (!db.objectStoreNames.contains('evidence')) {
                        const evidenceStore = db.createObjectStore('evidence', { keyPath: 'id', autoIncrement: true });
                        evidenceStore.createIndex('reportId', 'reportId', { unique: false });
                    }
                };
            });
        }
        
        // Pre-fill form with original data for resubmission
        populateFormWithOriginalData() {
            if (!this.originalData) return;
            
            const data = this.originalData;
            
            // Fill form fields
            document.getElementById('priority').value = data.priority || '';
            document.getElementById('incidentType').value = data.incident_type || '';
            document.getElementById('cityMunicipalityInput').value = data.city_municipality || '';
            document.getElementById('barangaysInput').value = data.barangays || '';
            document.getElementById('latitude').value = data.latitude || '';
            document.getElementById('longitude').value = data.longitude || '';
            document.getElementById('address').value = data.address || '';
            document.getElementById('dateReported').value = data.report_date || '';
            document.getElementById('description').value = data.description || '';
            document.getElementById('contactNo').value = data.contact_no || '';
            
            // Update map if coordinates exist
            if (data.latitude && data.longitude) {
                const latlng = { lat: parseFloat(data.latitude), lng: parseFloat(data.longitude) };
                this.updateLocation(latlng);
                this.map.setView([latlng.lat, latlng.lng], 15);
            }
            
            // Trigger priority indicator update
            this.updatePriorityIndicator();
        }
        
        // Save report locally
        async saveReportLocally(reportData, evidence = []) {
            const transaction = this.db.transaction(['reports', 'evidence'], 'readwrite');
            const reportStore = transaction.objectStore('reports');
            const evidenceStore = transaction.objectStore('evidence');
            
            // Add timestamp and offline flag
            reportData.timestamp = new Date().toISOString();
            reportData.offline = true;
            reportData.synced = false;
            reportData.type = 'illegal_activity';
            
            const reportRequest = reportStore.add(reportData);
            
            return new Promise((resolve, reject) => {
                reportRequest.onsuccess = async () => {
                    const reportId = reportRequest.result;
                    
                    // Save evidence files
                    if (evidence.length > 0) {
                        for (let i = 0; i < evidence.length; i++) {
                            const evidenceData = {
                                reportId: reportId,
                                data: evidence[i],
                                index: i
                            };
                            await evidenceStore.add(evidenceData);
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
                
                const priorityBadge = report.priority === 'Emergency' ? 
                    '<span class="priority-indicator priority-emergency"><i class="fas fa-exclamation-triangle"></i> Emergency</span>' :
                    '<span class="priority-indicator priority-normal"><i class="fas fa-info-circle"></i> Normal</span>';
                
                item.innerHTML = `
                    <div class="report-info">
                        <strong>${statusIcon} ${report.incident_type || 'Unknown Incident'}</strong> ${priorityBadge}<br>
                        <small>${new Date(report.timestamp).toLocaleString()}</small><br>
                        <small>Location: ${report.city_municipality || 'N/A'}</small>
                    </div>
                    <div class="report-actions">
                        <button class="btn-small btn-load" onclick="offlineIllegalManager.loadReport(${report.id})">
                            <i class="fas fa-edit"></i> Load
                        </button>
                        ${!report.synced && this.isOnline ? `
                            <button class="btn-small btn-sync" onclick="offlineIllegalManager.syncReport(${report.id})">
                                <i class="fas fa-sync"></i> Sync
                            </button>
                        ` : ''}
                        <button class="btn-small btn-delete" onclick="offlineIllegalManager.deleteReport(${report.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                `;
                
                container.appendChild(item);
            });
        }
        
        // Load report into form
        async loadReport(reportId) {
            const transaction = this.db.transaction(['reports', 'evidence'], 'readonly');
            const reportStore = transaction.objectStore('reports');
            const evidenceStore = transaction.objectStore('evidence');
            
            const reportRequest = reportStore.get(reportId);
            
            reportRequest.onsuccess = () => {
                const report = reportRequest.result;
                if (!report) return;
                
                // Populate form fields
                document.getElementById('priority').value = report.priority || '';
                document.getElementById('incidentType').value = report.incident_type || '';
                document.getElementById('cityMunicipalityInput').value = report.city_municipality || '';
                document.getElementById('barangaysInput').value = report.barangays || '';
                document.getElementById('latitude').value = report.latitude || '';
                document.getElementById('longitude').value = report.longitude || '';
                document.getElementById('address').value = report.address || '';
                document.getElementById('dateReported').value = report.report_date || '';
                document.getElementById('description').value = report.description || '';
                document.getElementById('contactNo').value = report.contact_no || '';
                document.getElementById('anonymousReport').checked = report.anonymous === '1';
                
                // Update priority indicator
                this.updatePriorityIndicator();
                
                // Update map if coordinates exist
                if (report.latitude && report.longitude) {
                    const latlng = { lat: parseFloat(report.latitude), lng: parseFloat(report.longitude) };
                    this.updateLocation(latlng);
                    this.map.setView([latlng.lat, latlng.lng], 15);
                }
                
                alert('Report loaded into form!');
            };
        }
        
        // Sync individual report
        async syncReport(reportId) {
            if (!this.isOnline) {
                alert('Cannot sync while offline');
                return;
            }
            
            const transaction = this.db.transaction(['reports', 'evidence'], 'readonly');
            const reportStore = transaction.objectStore('reports');
            const evidenceStore = transaction.objectStore('evidence');
            
            const reportRequest = reportStore.get(reportId);
            
            reportRequest.onsuccess = async () => {
                const report = reportRequest.result;
                if (!report || report.synced) return;
                
                try {
                    // Get evidence files
                    const evidenceRequest = evidenceStore.index('reportId').getAll(reportId);
                    evidenceRequest.onsuccess = async () => {
                        const evidence = evidenceRequest.result;
                        
                        // Create FormData for submission
                        const formData = new FormData();
                        
                        // Add all report fields
                        Object.keys(report).forEach(key => {
                            if (key !== 'id' && key !== 'timestamp' && key !== 'offline' && key !== 'synced' && key !== 'type') {
                                formData.append(key, report[key]);
                            }
                        });
                        
                        // Add evidence files as blobs
                        evidence.forEach((file, index) => {
                            const blob = this.base64ToBlob(file.data);
                            const extension = file.data.includes('data:video/') ? 'mp4' : 'jpg';
                            formData.append('images[]', blob, `evidence_${index}.${extension}`);
                        });
                        
                        // Submit to server
                        const response = await fetch('uploadreport_ia.php', {
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
            
            const transaction = this.db.transaction(['reports', 'evidence'], 'readwrite');
            const reportStore = transaction.objectStore('reports');
            const evidenceStore = transaction.objectStore('evidence');
            
            // Delete evidence first
            const evidenceRequest = evidenceStore.index('reportId').getAll(reportId);
            evidenceRequest.onsuccess = () => {
                const evidence = evidenceRequest.result;
                evidence.forEach(file => {
                    evidenceStore.delete(file.id);
                });
                
                // Delete report
                reportStore.delete(reportId);
                this.loadSavedReports();
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
                navigator.geolocation.getCurrentPosition(
                    (position) => this.handleGpsSuccess(position),
                    (error) => this.handleGpsError(error),
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                );
                
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
            
            if (type === 'success') {
                setTimeout(() => {
                    status.classList.remove('active');
                }, 5000);
            }
        }
        
        // Map setup
        setupMap() {
            // Check if we have original coordinates for resubmission
            const originalLat = <?php echo $is_resubmission && isset($original_report_data['latitude']) ? floatval($original_report_data['latitude']) : 'null'; ?>;
            const originalLng = <?php echo $is_resubmission && isset($original_report_data['longitude']) ? floatval($original_report_data['longitude']) : 'null'; ?>;
            
            if (originalLat && originalLng) {
                this.map = L.map('map').setView([originalLat, originalLng], 15);
            } else {
                this.map = L.map('map').setView([14.64852, 120.47318], 13);
            }
            
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
                .bindPopup("Incident location").openPopup();
            
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
            // Priority change handler
            document.getElementById('priority').addEventListener('change', () => {
                this.updatePriorityIndicator();
            });
            
            // Date setup
            const dateInput = document.getElementById('dateReported');
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
            document.getElementById('illegalActivityForm').addEventListener('submit', (e) => {
                e.preventDefault();
                
                if (this.isOnline) {
                    this.submitForm();
                } else {
                    alert('Cannot submit while offline. Data will be saved locally.');
                    this.saveCurrentForm();
                }
            });
            
            // Evidence file handling
            document.getElementById('evidenceFiles').addEventListener('change', (e) => {
                this.handleEvidenceUpload(e);
            });
        }
        
        updatePriorityIndicator() {
            const priority = document.getElementById('priority').value;
            const indicator = document.getElementById('priorityIndicator');
            const text = document.getElementById('priorityText');
            
            if (priority) {
                indicator.style.display = 'block';
                if (priority === 'Emergency') {
                    indicator.className = 'priority-indicator priority-emergency';
                    text.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Emergency - High Priority';
                } else {
                    indicator.className = 'priority-indicator priority-normal';
                    text.innerHTML = '<i class="fas fa-info-circle"></i> Normal Priority';
                }
            } else {
                indicator.style.display = 'none';
            }
        }
        
        // Handle evidence upload
        handleEvidenceUpload(event) {
            const files = Array.from(event.target.files).slice(0, 3);
            const previewContainer = document.getElementById('evidencePreviews');
            
            this.createEmptyPreviews();
            
            files.forEach((file, index) => {
                if (file.size > 10 * 1024 * 1024) { // 10MB limit for videos
                    alert(`File "${file.name}" exceeds 10MB limit`);
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = previewContainer.children[index];
                    preview.className = 'image-preview';
                    
                    if (file.type.startsWith('video/')) {
                        preview.innerHTML = `
                            <video src="${e.target.result}" controls style="width: 100%; height: 80px;">
                            <div style="font-size: 0.8rem; margin-top: 5px;">${file.name}</div>
                            <button type="button" class="remove-image" data-index="${index}">&times;</button>
                        `;
                    } else {
                        preview.innerHTML = `
                            <img src="${e.target.result}" alt="Evidence ${index + 1}">
                            <button type="button" class="remove-image" data-index="${index}">&times;</button>
                        `;
                    }
                    
                    preview.querySelector('.remove-image').addEventListener('click', () => {
                        this.removeEvidence(index);
                    });
                };
                reader.readAsDataURL(file);
            });
        }
        
        createEmptyPreviews() {
            const container = document.getElementById('evidencePreviews');
            container.innerHTML = '';
            for (let i = 0; i < 3; i++) {
                const preview = document.createElement('div');
                preview.className = 'image-preview empty';
                preview.innerHTML = `<span>No evidence</span>`;
                container.appendChild(preview);
            }
        }
        
        removeEvidence(index) {
            const input = document.getElementById('evidenceFiles');
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
            
            // Get evidence files as base64
            const evidence = await this.getEvidenceAsBase64();
            
            try {
                await this.saveReportLocally(formData, evidence);
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
                priority: document.getElementById('priority').value,
                incident_type: document.getElementById('incidentType').value,
                city_municipality: document.getElementById('cityMunicipalityInput').value,
                barangays: document.getElementById('barangaysInput').value,
                area_id: document.getElementById('areaId').value,
                area_no: document.getElementById('areaNo').value,
                latitude: document.getElementById('latitude').value,
                longitude: document.getElementById('longitude').value,
                address: document.getElementById('address').value,
                report_date: document.getElementById('dateReported').value,
                description: document.getElementById('description').value,
                contact_no: document.getElementById('contactNo').value,
                reporter_id: document.getElementById('reporterId').value,
                reporter_name: document.getElementById('reporterName').value,
                anonymous: document.getElementById('anonymousReport').checked ? '1' : '0',
                report_type: 'Illegal Activity Report'
            };
        }
        
        // Validate form
        validateForm(data) {
            if (!data.priority) {
                alert('Please select a priority level');
                return false;
            }
            
            if (!data.incident_type) {
                alert('Please select an incident type');
                return false;
            }
            
            if (!data.latitude || !data.longitude) {
                alert('Please set a location');
                return false;
            }
            
            if (!data.address) {
                alert('Please provide an address');
                return false;
            }
            
            if (!data.report_date) {
                alert('Please set a date and time');
                return false;
            }
            
            if (!data.description.trim()) {
                alert('Please provide a description of the incident');
                return false;
            }
            
            return true;
        }
        
        // Get evidence as base64
        async getEvidenceAsBase64() {
            const evidence = [];
            const fileInput = document.getElementById('evidenceFiles');
            
            for (let file of fileInput.files) {
                const base64 = await this.fileToBase64(file);
                evidence.push(base64);
            }
            
            return evidence;
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
            
            const mimeType = parts[0].match(/:(.*?);/)[1];
            return new Blob(byteArrays, { type: mimeType });
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
            
            // Add evidence files
            const fileInput = document.getElementById('evidenceFiles');
            for (let file of fileInput.files) {
                formData.append('images[]', file);
            }
            
            try {
                const response = await fetch('uploadreport_ia.php', {
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
            document.getElementById('illegalActivityForm').reset();
            this.createEmptyPreviews();
            
            if (this.marker) {
                this.map.removeLayer(this.marker);
                this.marker = null;
            }
            
            document.getElementById('priorityIndicator').style.display = 'none';
            
            const dateInput = document.getElementById('dateReported');
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
            const form = document.getElementById('illegalActivityForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.autoSave();
                });
            });
        }
        
        autoSave() {
            const formData = this.getFormData();
            localStorage.setItem('illegal_activity_form_autosave', JSON.stringify(formData));
            this.showAutoSaveIndicator();
        }
        
        showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            indicator.classList.add('show');
            
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
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
    }
    
    // Global functions for buttons
    window.offlineIllegalManager = null;
    
    // Sync all reports function for button
    function syncAllReports() {
        if (window.offlineIllegalManager) {
            window.offlineIllegalManager.syncAllReports();
        }
    }
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        window.offlineIllegalManager = new OfflineIllegalActivityManager();
    });
</script>
</body>
</html>
