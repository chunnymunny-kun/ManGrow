<?php
    session_start();
    include 'database.php';

    if(isset($_SESSION["accessrole"])){
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
        
        if(isset($_SESSION["name"])) $email = $_SESSION["name"];
        if(isset($_SESSION["accessrole"])) $accessrole = $_SESSION["accessrole"];
    } else {
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
    <title>Mangrove Map</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminmappage.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>
    <script src="bataanbarangay.js"></script>

    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
    <script src="area-manager.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    <script src="https://unpkg.com/@turf/turf@6.5.0/turf.min.js"></script>
    <script>
        // Pass PHP session data to JavaScript
        window.currentUser = {
            role: '<?php echo $_SESSION["accessrole"] ?? ""; ?>',
            city: '<?php echo $_SESSION["city_municipality"] ?? ""; ?>'
        };
    </script>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
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
    <main class="map-container">
        <div class="map-box">
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
        <!-- map for mangrove areas in Bataan -->
        <div id="map"></div>
        <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"): ?>
        <div id="areaControlPanel" class="area-controls">
                <div class="btn-group-vertical">
                    <button id="addAreaBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-draw-polygon"></i> Add New Area
                    </button>
                    <button id="editAreasBtn" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit Areas
                    </button>
                    <button id="deleteAreaBtn" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <button id="saveAreasBtn" class="btn btn-success btn-sm">
                        <i class="fas fa-save"></i> Save All Areas
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="adminmap-main">
            <h1 class="map-heading">Mangrove Monitoring</h1>
            <div class="map-card" id="admin-profile">
            <h3>User Profile</h3>
            <div class="profile-info">
                <p><strong>Name:</strong> <?= $_SESSION["name"] ?? "Guest" ?></p>
                <p><strong>Email:</strong> <?= $_SESSION["email"] ?? "Not logged in" ?></p>
                <p><strong>Role:</strong> <?= $_SESSION["accessrole"] ?? "Visitor" ?></p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="map-card" id="quick-actions">
            <h3>Quick Actions</h3>
            <button class="action-btn" onclick="window.location.href='adminreportpage.php'">
                <i class="fa fa-file-text"></i> View Submission Reports
            </button>
            <button class="action-btn" onclick="window.location.href='eco-track.php'" disabled>
                <i class="fa fa-map-marker"></i> Eco-Tracking Logs (disabled)
            </button>
        </div>

        <!-- Statistics Dashboard -->
        <div class="map-card" id="stats-dashboard">
            <h3>Mangrove Statistics</h3>
            <div class="stat-cards">
                <div class="stat-card">
                    <h4>Total Area</h4>
                    <p id="total-area">Loading...</p>
                </div>
                <div class="stat-card">
                    <h4>Protected Zones</h4>
                    <p id="protected-zones">Loading...</p>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="map-card" id="recent-activity">
            <h3>Recent Changes</h3>
            <div class="activity-controls">
                <button id="refresh-activity" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <ul id="activity-feed" class="activity-list">
                <!-- Activities will be loaded here dynamically -->
            </ul>
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
            <p class="credits">
                Data Sources: © Global Mangrove Watch, © OpenStreetMap contributors, © MapTiler
            </p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js" defer></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" defer></script>
    <script type = "text/javascript">
        function selectcitymunicipality(citymunicipality){
            console.log(Barangay);
        }
    </script>
    <script>
        //default map
        var map = L.map('map').setView([14.64852, 120.47318], 11.2);
        //attribution credits
        map.attributionControl.addAttribution('Mangrove data © <a href="https://www.globalmangrovewatch.org/" target="_blank">Global Mangrove Watch</a>');
        map.attributionControl.addAttribution('<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>');
        map.attributionControl.addAttribution('&copy; Stadia Maps, Stamen Design, OpenMapTiles & OSM contributors');
        map.attributionControl.addAttribution('DENR-PhilSA Mangrove Map V2 (DENR & PhilSA, 2024)');

        //map layering arrangement for mangrove areas
        map.createPane('cmPane').style.zIndex = 200;
        map.createPane('exmangrovePane').style.zIndex = 400;
        map.createPane('mangrovePane').style.zIndex = 500; 
        map.createPane('treePane').style.zIndex = 600;
        map.createPane('popupPane').style.zIndex = 1000;
        
        //registered city municipalities layer group - fetch but don't display
        var cmlayer = L.layerGroup({pane:'cmPane'});
        
        //toggles checkbox for mangrove areas
        var mangrovelayer = L.layerGroup({pane:'exmangrovePane'}).addTo(map);
        var extendedmangrovelayer = L.layerGroup({pane:'mangrovePane'}).addTo(map);
        // toogles checkbox for trees
        var treelayer = L.layerGroup({ pane: 'treePane' }).addTo(map);
        
        // Base maps
        var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '',
        }).addTo(map);

        //city municipality fetch data but don't display
        fetch('bataancm.geojson')
            .then(response => response.json())
            .then(data => {
                // Just store the data, don't add to map
                window.cityMunicipalityData = data;
            })
            .catch(error => {
                console.error('Error loading city/municipality data:', error);
            });

        // Initialize marker store
    const markerStore = {};

    // Define a custom icon for mangrove pins
    const customMangroveIcon = L.icon({
        iconUrl: 'images/mangrow-logo-pin.png', 
        iconSize: [20, 32],
        iconAnchor: [16, 32], 
        popupAnchor: [0, -32] 
    });

    // Define a style for the tree areas
        const treeAreaStyle = {
            color: '#FFA500', // Orange color
            weight: 2,
            opacity: 1,
            fillColor: '#FFA500',
            fillOpacity: 0.3
        };

        // Function to convert meters to degrees at a given latitude
        function metersToDegrees(lat, meters) {
            const earthRadius = 6378137; // meters
            const dLat = meters / earthRadius;
            const dLng = meters / (earthRadius * Math.cos(Math.PI * lat / 180));
            return {
                latOffset: dLat * (180 / Math.PI),
                lngOffset: dLng * (180 / Math.PI)
            };
        }

        // Function to create a 100 sqm square around a point (10m x 10m)
        function createTreeArea(latlng) {
            // 10 meters in each direction from the center
            const { latOffset, lngOffset } = metersToDegrees(latlng.lat, 5); // 5m each way = 10m total
            return L.rectangle([
                [latlng.lat - latOffset, latlng.lng - lngOffset],
                [latlng.lat + latOffset, latlng.lng + lngOffset]
            ], treeAreaStyle);
        }

        // Modified fetch script with tree areas
        fetch('mangrovetrees.json')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    pointToLayer: function(feature, latlng) {
                        // Create marker with custom icon and store reference
                        const marker = L.marker(latlng, { icon: customMangroveIcon });
                        markerStore[feature.properties.mangrove_id] = marker;
                        
                        // Create and store the tree area
                        const treeArea = createTreeArea(latlng);
                        treeAreaStore[feature.properties.mangrove_id] = treeArea;
                        treeArea.addTo(treelayer);
                        
                        return marker;
                    },
                    style: function(feature) {
                        return {
                            color: '#228B22',
                            weight: 2,
                            opacity: 1,
                            fillColor: '#32CD32',
                            fillOpacity: 0.6
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        // Store the feature data on the layer
                        layer.feature = feature;
                        
                        layer.bindPopup(`
                            <div class="marker-popup">
                                <h4>Tree Details</h4>
                                <table>
                                    <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                                        <td>${feature.properties.mangrove_id}</td></tr>
                                    <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                        <td>${feature.geometry.coordinates[1].toFixed(5)}, ${feature.geometry.coordinates[0].toFixed(5)}</td></tr>
                                    <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                                        <td>${feature.properties.area_no}</td></tr>
                                    <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                                        <td>${feature.properties.mangrove_type}</td></tr>
                                    <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                                        <td><span class="status-${feature.properties.status.toLowerCase()}">${feature.properties.status}</span></td></tr>
                                </table>
                                <div class="marker-actions" style="margin-top:0.8rem;">
                                    <button class="btn btn-warning btn-sm" onclick="editMarker('${feature.properties.mangrove_id}')">Edit</button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteMarker('${feature.properties.mangrove_id}')">Delete</button>
                                </div>
                            </div>
                        `);
                    }
                }).addTo(treelayer);
            })
            .catch(error => {
                console.error('Error fetching mangrovetrees.json:', error);
            });


        const areaStore = {};
        // Extended mangrove area fetch data - simplified version
        // Extended mangrove area fetch data with area calculation (with turf.js)
        fetch('mangroveareas.json')
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (!data.features || data.features.length === 0) {
                    console.log("No mangrove areas found");
                    return;
                }

                // Process each feature
                data.features.forEach(feature => {
                    const area = turf.area(feature);
                    feature.properties.area_m2 = Math.round(area);
                    feature.properties.area_ha = (area / 10000).toFixed(2);
                    
                    // Format dates
                    if (feature.properties.date_created) {
                        feature.properties.date_created_display = new Date(feature.properties.date_created).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila'
                        });
                    }
                    if (feature.properties.date_updated) {
                        feature.properties.date_updated_display = new Date(feature.properties.date_updated).toLocaleString('en-PH', {
                            timeZone: 'Asia/Manila'
                        });
                    }
                });

                // Create GeoJSON layer with improved popup handling
                const mangroveLayer = L.geoJSON(data, {
                    pane: 'mangrovePane',
                    style: {
                        fillColor: '#3d9970',
                        weight: 1,
                        opacity: 1,
                        color: '#2d7561',
                        fillOpacity: 0.5
                    },
                    onEachFeature: (feature, layer) => {
                        // Store the layer reference in our area store
                        if (feature.properties.id) {
                            areaStore[feature.properties.id] = layer;
                        } else {
                            // Generate an ID if one doesn't exist
                            const tempId = 'temp_' + Math.random().toString(36).substr(2, 9);
                            feature.properties.id = tempId;
                            areaStore[tempId] = layer;
                        }
                        
                        // Store the original feature data on the layer
                        layer.feature = feature;

                        // Create popup content
                        const popupContent = `
                            <div class="mangrove-popup">
                                <h4>${feature.properties.area_no || 'Mangrove Area'}</h4>
                                <table>
                                    <tr><th>Location:</th><td>${feature.properties.city_municipality || 'N/A'}</td></tr>
                                    <tr><th>Size:</th><td>${feature.properties.area_m2?.toLocaleString() || 'N/A'} m²</td></tr>
                                    <tr><th>Created:</th><td>${feature.properties.date_created_display || 'N/A'}</td></tr>
                                    <tr><th>Updated:</th><td>${feature.properties.date_updated_display || 'N/A'}</td></tr>
                                </table>
                                <div class="popup-actions">
                                    <button class="btn btn-sm btn-primary edit-area" 
                                            data-id="${feature.properties.id}">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-area" 
                                            data-id="${feature.properties.id}">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        `;

                        // Bind popup with proper options
                        layer.bindPopup(popupContent, {
                            className: 'mangrove-popup',
                            maxWidth: 400,
                            minWidth: 300
                        });

                        // Click handling
                        layer.on('click', function(e) {
                            if (!document.getElementById('addMarkerCheckbox')?.checked) {
                                this.openPopup();
                            }
                        });

                        // Hover effects
                        layer.on({
                            mouseover: function() {
                                this.setStyle({
                                    weight: 3,
                                    color: '#666',
                                    fillOpacity: 0.7
                                });
                            },
                            mouseout: function() {
                                this.setStyle({
                                    weight: 1,
                                    color: '#2d7561',
                                    fillOpacity: 0.5
                                });
                            }
                        });
                    }
                }).addTo(extendedmangrovelayer);

                console.log(`Loaded ${data.features.length} mangrove areas`);
            })
            .catch(error => {
                console.error('Error loading mangrove areas:', error);
            });

            function editArea(areaId) {
                if (!window.areaManager) {
                    console.error("AreaManager not initialized");
                    return;
                }

                const areaLayer = areaManager.areaStore[areaId];
                if (!areaLayer) {
                    alert("Area not found!");
                    return;
                }

                // Enter edit mode and select the area
                if (!areaManager.editMode) {
                    areaManager.toggleEditMode();
                }
                areaManager.selectArea(areaLayer, areaLayer.feature);
            }

            function deleteArea(areaId) {
                if (!window.areaManager) {
                    console.error("AreaManager not initialized");
                    return;
                }

                if (!confirm("Are you sure you want to delete this area?")) return;

                const areaLayer = areaManager.areaStore[areaId];
                if (!areaLayer) {
                    alert("Area not found!");
                    return;
                }

                // Remove from areas array
                areaManager.areas.features = areaManager.areas.features.filter(
                    area => area.properties.id !== areaId
                );

                // Remove from map and store
                areaLayer.remove();
                delete areaManager.areaStore[areaId];

                // If this was the currently selected area, clear selection
                if (areaManager.currentArea && areaManager.currentArea.feature.properties.id === areaId) {
                    areaManager.currentArea = null;
                    areaManager.drawnItems.clearLayers();
                }

                alert("Area deleted successfully!");
            }

        /*/mangrove area fetch data
        fetch('mangrove2020.json')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    style: function(feature){
                        return{
                            color:'#000000',
                            weight: 2,
                            opacity: 1,
                            fillColor: 'azure',
                            fillOpacity: 0.5
                        };
                    },
                    onEachFeature: (feature, layer) => {
                        layer.bindPopup(`<b>${feature.properties.Area}</b>`);
                        layer.on({
                            mouseover: function(e) {
                                this.openPopup();
                            },
                            mouseout: function(e) {
                                this.closePopup();
                            }
                        });
                    }
                }).addTo(mangrovelayer);
            });  */ 
        
                const legend = L.control({position: 'bottomright'});
                    legend.onAdd = function(map) {
                        const div = L.DomUtil.create('div', 'info legend');
                        div.innerHTML = `
                            <h4>Legend</h4>
                            <div>
                                <span style="display:inline-block;width:14px;height:14px;background:#3d9970;border-radius:3px;vertical-align:middle;margin-right:4px;"></span>
                                <span style="color:#3d9970;">Mangrove Area</span>
                            </div>
                            <div>
                                <span style="display:inline-block;width:14px;height:14px;background:#FFA500;border-radius:3px;vertical-align:middle;margin-right:4px;"></span>
                                <span style="color:#FFA500;">Tree Area (100sq m)</span>
                            </div>
                            <div>
                                <img src="images/mangrow-logo-pin.png" alt="Tree" style="width:16px;vertical-align:middle;margin-right:4px;">
                                <span style="color:#228B22;">Mangrove Trees</span>
                            </div>
                            <div>
                                <i class="fa fa-file-alt" style="color:black;font-size:16px;position:relative;top:1px;left:1px;vertical-align:middle;margin-right:4px;"></i>
                                <span style="color:#007bff;">Reports</span>
                            </div>
                            <hr>
                            <div>
                                <span style="font-size:12px;">1 m² = 0.0001 ha</span><br>
                                <span style="font-size:12px;">1 ha = 10,000 m²</span>
                            </div>
                        `;
                        return div;
                    };
                    legend.addTo(map);            

        var SatelliteStreets = L.tileLayer('https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
            attribution: '',
            maxZoom: 20
        });

        var EsriStreets = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles © Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
            maxZoom: 20
        });

        var StamenWatercolor = L.tileLayer('https://tiles.stadiamaps.com/tiles/stamen_watercolor/{z}/{x}/{y}.{ext}', {
            minZoom: 1,
            maxZoom: 16,
            attribution: '',
            ext: 'jpg'
        });

        var StamenTerrain = L.tileLayer('https://tiles.stadiamaps.com/tiles/stamen_terrain/{z}/{x}/{y}{r}.{ext}', {
            minZoom: 0,
            maxZoom: 16,
            attribution: '',
            ext: 'png'
        });

        // Layer control
        var baseMaps = {
            'Default': osm,
            'Satellite Streets': SatelliteStreets,
            'Esri Map': EsriStreets,
            'Water Color': StamenWatercolor,
            'Terrain': StamenTerrain
        };

        var overlayMaps = {
            'Mangrove Areas': extendedmangrovelayer,  // Make sure this is first
            'Mangrove Trees': treelayer,
            'City/Municipality': cmlayer
        };

        // Add layer control
        var layerControl = L.control.layers(baseMaps, overlayMaps, {}).addTo(map);

        // Add default layers
        osm.addTo(map);

        //L.control.locate().addTo(map);

    // Add a checkbox to enable/disable marker addition
    var addMarkerCheckbox = L.DomUtil.create('div', 'leaflet-control-layers-checkbox');
    addMarkerCheckbox.innerHTML = `
        <label style="padding: 5px; cursor: pointer; display: flex; align-items: center;">
            <input type="checkbox" id="addMarkerCheckbox" style="margin-right: 5px;">
            <i class="fas fa-tree" style="color: green; margin-right: 5px;"></i> Pin a Tree
        </label>`;

    // Append the checkbox to the layer control
    var layerControlContainer = layerControl.getContainer();
    layerControlContainer.appendChild(addMarkerCheckbox);

    // Prevent map interactions when interacting with the checkbox
    L.DomEvent.disableClickPropagation(addMarkerCheckbox);

    // Add event listener to the checkbox
    var markerAdditionEnabled = false;
    const markerCheckbox = document.getElementById('addMarkerCheckbox');
    if (markerCheckbox) {
        markerCheckbox.addEventListener('change', function(e) {
            markerAdditionEnabled = e.target.checked;

            // Show/hide boundary layer when checkbox is toggled
            if (window.areaManager && window.areaManager.boundaryLayer) {
                if (e.target.checked) {
                    window.areaManager.boundaryLayer.addTo(map);
                    // Optionally zoom to the boundary
                    map.fitBounds(window.areaManager.boundaryLayer.getBounds());
                } else {
                    window.areaManager.boundaryLayer.remove();
                }
            }

            if (markerAdditionEnabled) {
                map.on('click', onMapClick);
            } else {
                map.off('click', onMapClick);
            }
        });
    }

    function onMapClick(e) {
        // Check if location is allowed for this user
        if (window.areaManager && !window.areaManager.isLocationAllowed(e.latlng)) {
            return;
        }
        var formDiv = L.DomUtil.create('div', 'marker-form');
        formDiv.innerHTML = `
            <h3>Add Mangrove Marker</h3>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="areaNo" class="form-control">
            </div>
            <div class="form-group">
                <label>Mangrove Type:</label>
                <select id="mangroveType" class="form-control">
                    <option value="Rhizophora apiculata">Bakawan lalake</option>
                    <option value="Rhizophora mucronata">Bakawan babae</option>
                    <option value="Avicennia marina">Bungalon</option>
                    <option value="Sonneratia alba">Palapat</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="status" class="form-control">
                    <option value="Healthy">Alive</option>
                    <option value="Growing">Growing</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Dead">Dead</option>
                </select>
            </div>
            <div class="form-buttons">
                <button id="saveMarker" class="btn btn-primary">Save</button>
                <button id="cancelMarker" class="btn btn-secondary">Cancel</button>
            </div>
        `;
        
        var popup = L.popup()
            .setLatLng(e.latlng)
            .setContent(formDiv)
            .openOn(map);
        
        L.DomEvent.on(formDiv.querySelector('#saveMarker'), 'click', async function() {
            var areaNo = formDiv.querySelector('#areaNo').value;
            var mangroveType = formDiv.querySelector('#mangroveType').value;
            var status = formDiv.querySelector('#status').value;
            
            map.closePopup();
            
            try {
                // Save marker first
                const saveResponse = await fetch('save_marker.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        latitude: e.latlng.lat,
                        longitude: e.latlng.lng,
                        area_no: areaNo,
                        mangrove_type: mangroveType,
                        status: status
                    })
                });
                
                const saveData = await saveResponse.json();
                
                if (saveData.success) {
                    // Create marker with custom icon
                    const newMarker = L.marker(e.latlng, { icon: customMangroveIcon }).addTo(treelayer);

                    // Create and add the 100 sqm orange square area
                    const treeArea = createTreeArea(e.latlng);
                    treeArea.addTo(treelayer);

                    // Store in the marker store (flat, not nested)
                    markerStore[saveData.mangrove_id] = newMarker;

                    // Store in the tree area store
                    treeAreaStore[saveData.mangrove_id] = treeArea;

                    // Set feature data
                    newMarker.feature = {
                        type: 'Feature',
                        properties: {
                            mangrove_id: saveData.mangrove_id,
                            area_no: areaNo,
                            mangrove_type: mangroveType,
                            status: status,
                            date_added: new Date().toISOString()
                        },
                        geometry: {
                            type: 'Point',
                            coordinates: [e.latlng.lng, e.latlng.lat]
                        }
                    };

                    newMarker.bindPopup(`
                        <div class="marker-popup">
                            <h4>Tree Details</h4>
                            <table>
                                <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                                    <td>${saveData.mangrove_id}</td></tr>
                                <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                    <td>${e.latlng.lat.toFixed(5)}, ${e.latlng.lng.toFixed(5)}</td></tr>
                                <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                                    <td>${areaNo}</td></tr>
                                <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                                    <td>${mangroveType}</td></tr>
                                <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                                    <td><span class="status-${status.toLowerCase()}">${status}</span></td></tr>
                            </table>
                            <div class="marker-actions" style="margin-top:0.8rem;">
                                ${areaManager.isPointInUserBoundary(e.latlng) ? `
                                <button class="btn btn-warning btn-sm" onclick="editMarker('${saveData.mangrove_id}')">Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteMarker('${saveData.mangrove_id}')">Delete</button>
                                ` : `
                                <div class="text-muted">Editing restricted to your city/municipality</div>
                                `}
                            </div>
                        </div>
                    `);

                    try {
                        const expanded = await areaManager.expandAreaForTree(e.latlng);
                        if (expanded) {
                            console.log("Tree area created/merged successfully");
                        }
                    } catch (error) {
                        console.error("Area expansion error:", error);
                    }
                } else {
                    throw new Error(saveData.message || 'Failed to save marker');
                }
            } catch (error) {
                console.error("Error:", error);
                alert("Operation failed: " + error.message);
            }
        });
        
        L.DomEvent.on(formDiv.querySelector('#cancelMarker'), 'click', function() {
            map.closePopup();
        });
    }

    // Initialize tree area store
    const treeAreaStore = {};

    // Helper: create a 100 sqm (10m x 10m) rectangle around a point
    function getTreeAreaBounds(latlng) {
        const { latOffset, lngOffset } = metersToDegrees(latlng.lat, 5); // 5m each way = 10m total
        return [
            [latlng.lat - latOffset, latlng.lng - lngOffset],
            [latlng.lat + latOffset, latlng.lng + lngOffset]
        ];
    }

    // Modified editMarker function
    function editMarker(mangroveId) {
        const markerToEdit = markerStore[mangroveId];
        const areaToEdit = treeAreaStore[mangroveId];
        if (!markerToEdit) {
            alert("Marker not found!");
            return;
        }
        
        // Check permissions
        const latlng = markerToEdit.getLatLng();
        if (window.areaManager && !window.areaManager.isPointInUserBoundary(latlng)) {
            alert("You are not authorized to edit markers outside your city/municipality.");
            return;
        }

        // Enable dragging for the marker
        markerToEdit.dragging.enable();

        // Update the area position when marker is dragged
        markerToEdit.on('drag', function(e) {
            const newLatLng = e.target.getLatLng();
            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(newLatLng));
        });

        // Create a form div for editing
        const formDiv = L.DomUtil.create('div', 'marker-form');
        formDiv.innerHTML = `
            <h3>Edit Mangrove Marker</h3>
            <div class="form-group">
                <label>Mangrove ID:</label>
                <input type="text" class="form-control" value="${markerToEdit.feature.properties.mangrove_id}" readonly>
            </div>
            <div class="form-group">
                <label>Area Number:</label>
                <input type="text" id="editAreaNo" class="form-control" value="${markerToEdit.feature.properties.area_no}">
            </div>
            <div class="form-group">
                <label>Mangrove Type:</label>
                <select id="editMangroveType" class="form-control">
                    <option value="Rhizophora apiculata" ${markerToEdit.feature.properties.mangrove_type === "Rhizophora apiculata" ? "selected" : ""}>Bakawan lalake</option>
                    <option value="Rhizophora mucronata" ${markerToEdit.feature.properties.mangrove_type === "Rhizophora mucronata" ? "selected" : ""}>Bakawan babae</option>
                    <option value="Avicennia marina" ${markerToEdit.feature.properties.mangrove_type === "Avicennia marina" ? "selected" : ""}>Bungalon</option>
                    <option value="Sonneratia alba" ${markerToEdit.feature.properties.mangrove_type === "Sonneratia alba" ? "selected" : ""}>Palapat</option>
                </select>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select id="editStatus" class="form-control">
                    <option value="Healthy" ${markerToEdit.feature.properties.status === "Healthy" ? "selected" : ""}>Alive</option>
                    <option value="Growing" ${markerToEdit.feature.properties.status === "Growing" ? "selected" : ""}>Growing</option>
                    <option value="Damaged" ${markerToEdit.feature.properties.status === "Damaged" ? "selected" : ""}>Damaged</option>
                    <option value="Dead" ${markerToEdit.feature.properties.status === "Dead" ? "selected" : ""}>Dead</option>
                </select>
            </div>
            <div class="form-buttons">
                <button id="saveEditMarker" class="btn btn-primary">Save</button>
                <button id="cancelEditMarker" class="btn btn-secondary">Cancel</button>
            </div>
        `;

        // Create a popup with the form
        const popup = L.popup()
            .setLatLng(markerToEdit.getLatLng())
            .setContent(formDiv)
            .openOn(map);

        // Handle save button click
        L.DomEvent.on(formDiv.querySelector('#saveEditMarker'), 'click', function() {
            const updatedAreaNo = formDiv.querySelector('#editAreaNo').value;
            const updatedMangroveType = formDiv.querySelector('#editMangroveType').value;
            const updatedStatus = formDiv.querySelector('#editStatus').value;
            const newLatLng = markerToEdit.getLatLng();

            // Update marker properties
            markerToEdit.feature.properties.area_no = updatedAreaNo;
            markerToEdit.feature.properties.mangrove_type = updatedMangroveType;
            markerToEdit.feature.properties.status = updatedStatus;

            // Update marker popup
            markerToEdit.setPopupContent(`
                <div class="marker-popup">
                    <h4>Tree Details</h4>
                    <table>
                        <tr><th><i class="fas fa-hashtag"></i> Mangrove ID</th>
                            <td>${markerToEdit.feature.properties.mangrove_id}</td></tr>
                        <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                            <td>${newLatLng.lat.toFixed(5)}, ${newLatLng.lng.toFixed(5)}</td></tr>
                        <tr><th><i class="fas fa-hashtag"></i> Area No</th>
                            <td>${updatedAreaNo}</td></tr>
                        <tr><th><i class="fas fa-tree"></i> Mangrove Type</th>
                            <td>${updatedMangroveType}</td></tr>
                        <tr><th><i class="fas fa-heartbeat"></i> Status</th>
                            <td><span class="status-${updatedStatus.toLowerCase()}">${updatedStatus}</span></td></tr>
                    </table>
                    <div class="marker-actions" style="margin-top:0.8rem;">
                        <button class="btn btn-warning btn-sm" onclick="editMarker('${markerToEdit.feature.properties.mangrove_id}')">Edit</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteMarker('${markerToEdit.feature.properties.mangrove_id}')">Delete</button>
                    </div>
                </div>
            `);

            // Save updated data to the server
            fetch('update_marker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    mangrove_id: markerToEdit.feature.properties.mangrove_id,
                    latitude: newLatLng.lat,
                    longitude: newLatLng.lng,
                    area_no: updatedAreaNo,
                    mangrove_type: updatedMangroveType,
                    status: updatedStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Marker updated successfully!");
                } else {
                    alert("Failed to update marker: " + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error("Error updating marker:", error);
            });

            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(newLatLng));

            // Disable dragging and close popup
            markerToEdit.dragging.disable();
            markerToEdit.off('drag');
            map.closePopup();
        });

        // Handle cancel button click
        L.DomEvent.on(formDiv.querySelector('#cancelEditMarker'), 'click', function() {
            // Reset marker position to original
            markerToEdit.setLatLng(markerToEdit.feature._latlng || markerToEdit.getLatLng());
            // Always keep the area 100 sqm (10m x 10m)
            areaToEdit.setBounds(getTreeAreaBounds(markerToEdit.getLatLng()));
            
            markerToEdit.dragging.disable();
            markerToEdit.off('drag');
            map.closePopup();
        });
    }

    // Modified deleteMarker function
    async function deleteMarker(mangroveId) {
        if (!confirm("Are you sure you want to delete this marker and update the mangrove area?")) return;

        // Find the marker and its area in our stores
        const markerToDelete = markerStore[mangroveId];
        const treeAreaToDelete = treeAreaStore[mangroveId];
        
        if (!markerToDelete || !treeAreaToDelete) {
            alert("Marker or area not found!");
            return;
        }

        const latlng = markerToDelete.getLatLng();
        if (window.areaManager && !window.areaManager.isPointInUserBoundary(latlng)) {
            alert("You are not authorized to delete markers outside your city/municipality.");
            return;
        }

        try {
            // Get the pin's location and create a temporary area for subtraction
            const pinLatLng = markerToDelete.getLatLng();
            
            // Create a 105 sqm area around the pin (10.25m x 10.25m)
            // 105 sqm = side^2 => side = sqrt(105) ≈ 10.247m, so half-side ≈ 5.1235m
            const halfSide = Math.sqrt(105) / 2; // ≈ 5.1235 meters
            const { latOffset, lngOffset } = metersToDegrees(pinLatLng.lat, halfSide);
            const pinBounds = [
                [pinLatLng.lat - latOffset, pinLatLng.lng - lngOffset],
                [pinLatLng.lat + latOffset, pinLatLng.lng + lngOffset]
            ];
            
            // Create a GeoJSON representation of the pin area
            const pinAreaGeoJSON = {
                type: "Polygon",
                coordinates: [[
                    [pinBounds[0][1], pinBounds[0][0]], // SW
                    [pinBounds[0][1], pinBounds[1][0]], // NW
                    [pinBounds[1][1], pinBounds[1][0]], // NE
                    [pinBounds[1][1], pinBounds[0][0]], // SE
                    [pinBounds[0][1], pinBounds[0][0]]  // SW (close)
                ]]
            };

            // Find intersecting mangrove areas
            const intersectingAreas = areaManager.findIntersectingAreas(pinAreaGeoJSON);
            
            if (intersectingAreas.length > 0) {
                // Subtract the pin area from each intersecting mangrove area
                for (const area of intersectingAreas) {
                    await areaManager.subtractAreaFromMangrove(pinAreaGeoJSON, area);
                }
            }

            // Now delete the marker and its tree area
            const deleteResponse = await fetch('delete_marker.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mangrove_id: mangroveId })
            });
            
            const deleteData = await deleteResponse.json();
            
            if (deleteData.success) {
                // Remove marker and area from map and stores
                markerToDelete.remove();
                treeAreaToDelete.remove();
                delete markerStore[mangroveId];
                delete treeAreaStore[mangroveId];
                
                // Save the updated mangrove areas
                await areaManager.saveAreas();
                
                // Re-render the areas to show changes
                areaManager.renderAreas();
                
                alert("Marker deleted and mangrove areas updated successfully!");
            } else {
                throw new Error(deleteData.message || 'Failed to delete marker');
            }
        } catch (error) {
            console.error("Error deleting marker:", error);
            alert("Error deleting marker: " + error.message);
        }
    }
    <!-- update mangrove areas json script (to database) -->
    </script>
</body>
</html>