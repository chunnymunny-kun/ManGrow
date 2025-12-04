<!--
<?php
    session_start();
    include 'database.php';
    include 'badge_system_db.php';

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }

    // Badge notification system
    $showBadgeNotification = false;
    $badgeToShow = null;

    if(isset($_SESSION['new_badge_awarded']) && $_SESSION['new_badge_awarded']['badge_awarded'] && isset($_SESSION['user_id'])) {
        $showBadgeNotification = true;
        $badgeToShow = $_SESSION['new_badge_awarded'];
        
        // Mark as permanently notified in database
        $userId = $_SESSION['user_id'];
        $badgeName = $badgeToShow['badge_name'];
        
        $insertNotificationQuery = "INSERT IGNORE INTO badge_notifications (user_id, badge_name) VALUES (?, ?)";
        $stmt = $connection->prepare($insertNotificationQuery);
        $stmt->bind_param("is", $userId, $badgeName);
        $stmt->execute();
        $stmt->close();
        
        // Clear session to prevent showing again
        unset($_SESSION['new_badge_awarded']);
    }

    // Helper function to get badge descriptions from database
    function getBadgeDescription($badgeName) {
        global $connection;
        
        $query = "SELECT description FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['description'];
        }
        
        $stmt->close();
        return 'Congratulations on earning this badge!';
    }

    // Helper function to get badge icons from database
    function getBadgeIcon($badgeName) {
        global $connection;
        
        $query = "SELECT icon_class FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['icon_class'];
        }
        
        $stmt->close();
        return 'fas fa-star';
    }
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ManGrow Map</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mangrovemappage.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>

    <script src="https://api.tiles.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.js"></script>
    <link href="https://api.tiles.mapbox.com/mapbox-gl-js/v3.3.0/mapbox-gl.css" rel="stylesheet"/>
<!-- for mangrove area calculations -->
    <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

    <script type ="text/javascript" src ="app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
    
    <!-- Badge Notification Styles -->
    <style>
        .badge-notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease-in;
        }

        .badge-notification-modal {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: white;
            animation: bounceIn 0.6s ease-out;
        }

        .badge-celebration .badge-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        .badge-celebration h2 {
            margin: 1rem 0;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .new-badge-display {
            margin: 2rem 0;
            display: flex;
            justify-content: center;
        }

        .badge-card.earned {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
            animation: rotate 3s linear infinite;
        }

        .badge.event-organizer { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge.mangrove-guardian { background: linear-gradient(135deg, #27ae60, #229954); }
        .badge.watchful-eye { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .badge.vigilant-protector { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .badge.conservation-champion { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .badge.ecosystem-sentinel { background: linear-gradient(135deg, #34495e, #2c3e50); }
        .badge.mangrove-legend { background: linear-gradient(135deg, #f1c40f, #f39c12); }

        .badge-description {
            font-size: 1.1rem;
            margin: 1.5rem 0;
            line-height: 1.5;
            opacity: 0.9;
        }

        .notification-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-view-badge, .btn-close-notification {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-close-notification {
            background: white;
            color: #4CAF50;
        }

        .btn-view-badge:hover {
            background: white;
            color: #4CAF50;
        }

        .btn-close-notification:hover {
            background: rgba(255, 255, 255, 0.9);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .badge-notification-modal {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .notification-buttons {
                flex-direction: column;
            }
        }
    </style>
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
                    <a href="events.php">Events</a>
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
            <li class="active">
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

        <!-- Badge Award Notification -->
        <?php if($showBadgeNotification && $badgeToShow): ?>
        <div class="badge-notification-overlay" id="badgeNotification">
            <div class="badge-notification-modal">
                <div class="badge-celebration">
                    <div class="badge-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h2>🎉 Congratulations! 🎉</h2>
                    <p>You've earned a new badge!</p>
                    <div class="new-badge-display">
                        <div class="badge-card earned">
                            <?php 
                            $badgeName = $badgeToShow['badge_name'];
                            $badgeClass = strtolower(str_replace(' ', '-', $badgeName));
                            $badgeIcon = getBadgeIcon($badgeName);
                            $badgeDescription = getBadgeDescription($badgeName);
                            ?>
                            <div class="badge <?= $badgeClass ?>">
                                <i class="<?= $badgeIcon ?>"></i>
                            </div>
                            <p><?= htmlspecialchars($badgeName) ?></p>
                        </div>
                    </div>
                    <p class="badge-description"><?= htmlspecialchars($badgeDescription) ?></p>
                    <div class="notification-buttons">
                        <button onclick="viewBadgeDetails()" class="btn-view-badge">View Badge</button>
                        <button onclick="closeBadgeNotification()" class="btn-close-notification">Awesome!</button>
                    </div>
                </div>
            </div>
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

        <div class="map-container">
            <div id="map" class="map" style="height:100%; width:100%;"></div>
            <div class="map-controls">
                <button id="toggle-controls-btn" class="map-control-btn hidden"><i class='bx bx-slider-alt'></i></button>

                <button id="locate-btn" class="map-control-btn"><i class='bx bx-current-location'></i> Locate Me</button>
                <!--<button id="qr-btn" class="map-control-btn"><i class='bx bx-qr'></i> Generate QR Code</button>-->
            </div>
        </div>
    </main>
    <!-- page toggles script -->
    <script>
        //show toggle controls button when screen width is less than 768px
        window.addEventListener('resize', function() {
            const controlsContainer = document.querySelector('.map-controls');
            const toggleControlsBtn = document.getElementById('toggle-controls-btn');
            const locateBtn = document.getElementById('locate-btn');
            const qrBtn = document.getElementById('qr-btn');
            
            if (window.innerWidth < 768) {
                controlsContainer.classList.add('collapsed');
                toggleControlsBtn.classList.remove('hidden');
                locateBtn.classList.add('hidden');
                qrBtn.classList.add('hidden');
            } else {
                controlsContainer.classList.remove('collapsed');
                toggleControlsBtn.classList.add('hidden');
                locateBtn.classList.remove('hidden');
                qrBtn.classList.remove('hidden');
            }
        });

        // adjust toggle controls button visibility on initial load
        window.addEventListener('DOMContentLoaded', function() {
            const controlsContainer = document.querySelector('.map-controls');
            const toggleControlsBtn = document.getElementById('toggle-controls-btn');
            const locateBtn = document.getElementById('locate-btn');
            const qrBtn = document.getElementById('qr-btn');

            if (window.innerWidth < 768) {
                controlsContainer.classList.add('collapsed');
                toggleControlsBtn.classList.remove('hidden');
                locateBtn.classList.add('hidden');
                qrBtn.classList.add('hidden');
            } else {
                controlsContainer.classList.remove('collapsed');
                toggleControlsBtn.classList.add('hidden');
                locateBtn.classList.remove('hidden');
                qrBtn.classList.remove('hidden');
            }
        });

        // Toggle map controls visibility
        document.getElementById('toggle-controls-btn').addEventListener('click', function() {
            const controlsContainer = document.querySelector('.map-controls');
            const locateBtn = document.getElementById('locate-btn');
            const qrBtn = document.getElementById('qr-btn');

            if (locateBtn.classList.contains('hidden')) {
                controlsContainer.classList.remove('collapsed');
                locateBtn.classList.remove('hidden');
                qrBtn.classList.remove('hidden');
            } else {
                controlsContainer.classList.add('collapsed');
                locateBtn.classList.add('hidden');
                qrBtn.classList.add('hidden');
            }
        });
    </script>
    <!-- Leaflet Map Initialization -->
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize the map
            map = L.map('map').setView([14.64852, 120.47318], 11.2);
                
            // Make sure the map is properly sized
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
        
        // Create a custom icon for the location marker
            var locationIcon = L.icon({
                iconUrl: 'https://cdn0.iconfinder.com/data/icons/small-n-flat/24/678111-map-marker-512.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34]
            });

            // Create the locate control but don't add it to the map yet
            var locateControl = L.control.locate({
                drawCircle: false,
                follow: false,
                setView: 'once',
                keepCurrentZoomLevel: false,
                strings: {
                    popup: "Your Location"
                },
                locateOptions: {
                    maxZoom: 16,
                    enableHighAccuracy: true
                },
                createMarker: function(latlng) {
                    var marker = L.marker(latlng, {
                        icon: locationIcon,
                        title: "Your Location"
                    }).bindPopup("Your Location");
                    return marker;
                }
            });

            // Add the control to the map (hidden)
            locateControl.addTo(map);
            
            // Get the locate button element
            var locateBtn = document.getElementById('locate-btn');
            
            // Add click event to your custom button
            locateBtn.addEventListener('click', function() {
                // Check if geolocation is available
                if (!navigator.geolocation) {
                    alert("Geolocation is not supported by your browser");
                    return;
                }
                
                // Start the locate control
                locateControl.start();
                
                // Optional: Add loading state
                locateBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Locating...';
                
                // Set timeout to reset button text
                setTimeout(function() {
                    locateBtn.innerHTML = '<i class="fa fa-location-arrow"></i> Locate Me';
                }, 3000);
            });

        // Attribution credits
        map.attributionControl.addAttribution('Mangrove data © <a href="https://www.globalmangrovewatch.org/" target="_blank">Global Mangrove Watch</a>');
        map.attributionControl.addAttribution('<a href="https://www.maptiler.com/copyright/" target="_blank">&copy; MapTiler</a> <a href="https://www.openstreetmap.org/copyright" target="_blank">&copy; OpenStreetMap contributors</a>');
        map.attributionControl.addAttribution('&copy; Stadia Maps, Stamen Design, OpenMapTiles & OSM contributors');
        map.attributionControl.addAttribution('DENR-PhilSA Mangrove Map V2 (DENR & PhilSA, 2024)');

        // Map layering arrangement for mangrove areas
        map.createPane('cmPane').style.zIndex = 200;
        map.createPane('exmangrovePane').style.zIndex = 400;
        map.createPane('eventPane').style.zIndex = 500; 
        map.createPane('treePane').style.zIndex = 600;
        
        // Layer groups
        var cmlayer = L.layerGroup({pane:'cmPane'}).addTo(map);
        var extendedmangrovelayer = L.layerGroup({pane:'exmangrovePane'}).addTo(map);
        var eventlayer = L.layerGroup({pane:'eventPane'}).addTo(map);
        var treelayer = L.layerGroup({ pane: 'treePane' }).addTo(map);
        
        // Base map
        var osm = L.tileLayer('https://api.maptiler.com/maps/openstreetmap/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
            attribution: '',
        }).addTo(map);

        // Define a custom icon for mangrove pins
        const customMangroveIcon = L.icon({
            iconUrl: 'images/mangrow-logo-pin.png', 
            iconSize: [20, 32],
            iconAnchor: [16, 32], 
            popupAnchor: [0, -32] 
        });

        // Load existing markers from GeoJSON - simplified version
        fetch('mangrovetrees.json')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    pointToLayer: function(feature, latlng) {
                        return L.marker(latlng, { icon: customMangroveIcon });
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
                        </div>
                    `);
                }
                }).addTo(treelayer);
            })
            .catch(error => {
                console.error('Error fetching mangrovetrees.json:', error);
            });

            // Load events from GeoJSON - simplified version
            // Custom icon for events
            const eventIcon = L.icon({
                iconUrl: 'images/event-icon.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34]
            });

            // Function to get current week range (Monday to Sunday)
            function getCurrentWeekRange() {
                const now = new Date();
                const phTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                const day = phTime.getDay(); // 0 (Sunday) to 6 (Saturday)
                
                // Calculate Monday of current week
                const monday = new Date(phTime);
                monday.setDate(phTime.getDate() - (day === 0 ? 6 : day - 1));
                monday.setHours(0, 0, 0, 0);
                
                // Calculate Sunday of current week
                const sunday = new Date(monday);
                sunday.setDate(monday.getDate() + 6);
                sunday.setHours(23, 59, 59, 999);
                
                return { start: monday, end: sunday };
            }

            // Function to determine event status based on dates
            function getEventStatus(event) {
                const now = new Date();
                const phTime = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
                const startDate = new Date(event.properties.start_date);
                const endDate = new Date(event.properties.end_date);
                
                if (phTime < startDate) return 'upcoming';
                if (phTime > endDate) return 'completed';
                return 'ongoing';
            }

            // Function to check if event falls within current week
            function isInCurrentWeek(event, weekRange) {
                const startDate = new Date(event.properties.start_date);
                const endDate = new Date(event.properties.end_date);
                
                // Event ended during current week (even if started earlier)
                if (endDate >= weekRange.start && endDate <= weekRange.end) {
                    return true;
                }
                
                // Event starts during current week (even if ends later)
                if (startDate >= weekRange.start && startDate <= weekRange.end) {
                    return true;
                }
                
                // Event spans the entire current week
                if (startDate <= weekRange.start && endDate >= weekRange.end) {
                    return true;
                }
                
                return false;
            }

            // Load and display events
            function loadAndDisplayEvents() {
                const weekRange = getCurrentWeekRange();
                
                fetch('events.json?' + new Date().getTime()) // Cache busting
                    .then(response => response.json())
                    .then(data => {
                        eventlayer.clearLayers();
                        
                        L.geoJSON(data, {
                            pointToLayer: function(feature, latlng) {
                                const status = getEventStatus(feature);
                                return L.marker(latlng, { 
                                    icon: eventIcon,
                                    status: status
                                });
                            },
                            style: function(feature) {
                                const status = getEventStatus(feature);
                                return {
                                    color: status === 'ongoing' ? '#FF5733' : 
                                        (status === 'upcoming' ? '#3388FF' : '#888888'), // Gray for completed
                                    weight: 2,
                                    opacity: 1,
                                    fillColor: status === 'ongoing' ? '#FFC300' : 
                                            (status === 'upcoming' ? '#33FF57' : '#DDDDDD'),
                                    fillOpacity: 0.6
                                };
                            },
                            filter: function(feature) {
                                const status = getEventStatus(feature);
                                const inCurrentWeek = isInCurrentWeek(feature, weekRange);
                                return status === 'ongoing' || 
                                    (status === 'upcoming' && inCurrentWeek) || 
                                    (status === 'completed' && inCurrentWeek);
                            },
                            onEachFeature: function(feature, layer) {
                                 // Format dates with time component
                                const startDate = new Date(feature.properties.start_date);
                                const endDate = new Date(feature.properties.end_date);
                                
                                const options = { 
                                    year: 'numeric', 
                                    month: 'long', 
                                    day: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    timeZone: 'Asia/Manila'
                                };
                                
                                const popupContent = `
                                    <div class="event-popup">
                                        <h4>${feature.properties.subject}</h4>
                                        <div class="event-status ${getEventStatus(feature)}">
                                            ${getEventStatus(feature).toUpperCase()}
                                        </div>
                                        <table>
                                            <tr><th><i class="fas fa-calendar-day"></i> Start</th>
                                                <td>${startDate.toLocaleString('en-PH', options)}</td></tr>
                                            <tr><th><i class="fas fa-calendar-day"></i> End</th>
                                                <td>${endDate.toLocaleString('en-PH', options)}</td></tr>
                                            <tr><th><i class="fas fa-map-marker-alt"></i> Location</th>
                                                <td>${feature.properties.venue}, ${feature.properties.barangay}</td></tr>
                                            <tr><th><i class="fas fa-map-marker-alt"></i> Coordinates</th>
                                                <td>${feature.geometry.coordinates[1].toFixed(5)}, ${feature.geometry.coordinates[0].toFixed(5)}</td></tr>
                                            <tr><th><i class="fas fa-tag"></i> Type</th>
                                                <td>${feature.properties.event_type}</td></tr>
                                            <tr><th><i class="fas fa-users"></i> Participants</th>
                                                <td>${feature.properties.participants || 'Not specified'}</td></tr>
                                        </table>
                                        <div class="event-actions">
                                            <button class="btn-view-details" onclick="viewEventDetails(${feature.properties.event_id})">
                                                View Details
                                            </button>
                                        </div>
                                    </div>
                                `;
                                
                                layer.bindPopup(popupContent);
                            }
                        }).addTo(eventlayer);
                    })
                    .catch(error => console.error('Error loading events:', error));
            }
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

                    // Process each feature: calculate area and format dates
                    data.features.forEach(feature => {
                        const area = turf.area(feature);
                        feature.properties.area_m2 = Math.round(area);
                        feature.properties.area_ha = (area / 10000).toFixed(2);

                        // Format dates if present
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

                    // Create GeoJSON layer with improved popups
                    L.geoJSON(data, {
                        style: {
                            fillColor: '#3d9970',
                            weight: 1,
                            opacity: 1,
                            color: '#2d7561',
                            fillOpacity: 0.5
                        },
                        onEachFeature: (feature, layer) => {
                            // Create popup content
                            const popupContent = `
                                <div class="mangrove-popup">
                                    <h4>${feature.properties.area_no || 'Mangrove Area'}</h4>
                                    <table>
                                        <tr><th>Location:</th><td>${feature.properties.city_municipality || 'N/A'}</td></tr>
                                        <tr><th>Size:</th><td>${feature.properties.area_m2?.toLocaleString() || 'N/A'} m² (${feature.properties.area_ha} ha)</td></tr>
                                        <tr><th>Created:</th><td>${feature.properties.date_created_display || 'N/A'}</td></tr>
                                        <tr><th>Updated:</th><td>${feature.properties.date_updated_display || 'N/A'}</td></tr>
                                    </table>
                                </div>
                            `;

                            layer.bindPopup(popupContent, {
                                className: 'mangrove-popup',
                                maxWidth: 400,
                                minWidth: 300
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

                    const totalArea = data.features.reduce((sum, feature) => sum + feature.properties.area_m2, 0);
                    console.log(`Total mangrove area: ${totalArea.toLocaleString()} m²`);
                })
                .catch(error => {
                    console.error('Error loading mangrove areas:', error);
                    L.popup()
                        .setLatLng(map.getCenter())
                        .setContent('<b>Error</b><br>Could not load mangrove areas data')
                        .openOn(map);
                });

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
                                <img src="images/mangrow-logo-pin.png" alt="Tree" style="width:16px;vertical-align:middle;margin-right:4px;">
                                <span style="color:#228B22;">Mangrove Trees</span>
                            </div>
                            <div>
                                <img src="images/event-icon.png" alt="Event" style="width:16px;vertical-align:middle;margin-right:4px;">
                                <span style="color:#FF5733;">Events</span>
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

                    // Define base map layers
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

                    // Layer control configuration
                    var baseMaps = {
                        'Default': osm,
                        'Satellite': SatelliteStreets,
                        'Esri Imagery': EsriStreets,
                        'Watercolor': StamenWatercolor,
                        'Terrain': StamenTerrain
                    };

                    var overlayMaps = {
                        'Mangrove Trees': treelayer,
                        'Municipalities': cmlayer,
                        'Mangrove Events': eventlayer,
                        'Mangrove Areas': extendedmangrovelayer
                    };

                    // Add layer control to map
                    L.control.layers(baseMaps, overlayMaps, {
                        collapsed: true, 
                        position: 'topright' 
                    }).addTo(map);

                    // Load events after map is ready
                    setTimeout(loadAndDisplayEvents, 500);
                
                    // Optional: Refresh events every hour
                    setInterval(loadAndDisplayEvents, 3600000);
                });

            function viewEventDetails(eventId) {
                console.log('Viewing details for event:', eventId);
                window.location.href = `event_details.php?event_id=${eventId}`;
            }
    </script>
            <!-- update events json script -->
    <script>
            // Function to update events and refresh the map
            async function updateEventsJson() {
                try {
                    // 1. Update the JSON file
                    await fetch('update_events_json.php');
                    
                    // 2. Let your existing interval handle the refresh
                    console.log('Events JSON updated');
                } catch (error) {
                    console.error('Update failed:', error);
                }
            }

            // Update on load and every minute
            document.addEventListener('DOMContentLoaded', function() {
                updateEventsJson();
                setInterval(updateEventsJson, 60000);
            });
    </script>
    <!-- update mangrove areas json script (to database) -->
    <script>
        async function updateMangroveAreasJson() {
            try {
                // 1. Update the JSON file
                await fetch('export_mangrovearea.php');
                
                // 2. Let your existing interval handle the refresh
                console.log('Mangrove areas JSON updated');
            } catch (error) {
                console.error('Update failed:', error);
            }
        }

        // Update on load and every minute
        document.addEventListener('DOMContentLoaded', function() {
            updateMangroveAreasJson();
            setInterval(updateMangroveAreasJson, 60000);
        });
    </script>
    
    <!-- Badge Notification JavaScript -->
    <script>
        // Badge Notification Functions
        function closeBadgeNotification() {
            const notification = document.getElementById('badgeNotification');
            if (notification) {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }

        function viewBadgeDetails() {
            // Close notification and redirect to profile page
            closeBadgeNotification();
            setTimeout(() => {
                window.location.href = 'profile.php#badges';
            }, 400);
        }

        // CSS for fade out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: scale(1); }
                to { opacity: 0; transform: scale(0.8); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>