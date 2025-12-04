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
    <title>Welcome to ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="about.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>
    
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
                    <a href="index.php" class="active">Home</a>
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
        <div class="about-main-container">
            <!-- Hero Section -->
            <section class="about-hero">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>About <span class="mangrow-highlight">ManGrow</span></h1>
                        <p class="hero-subtitle">Empowering communities through technology to preserve and protect our precious mangrove ecosystems for future generations.</p>
                        <div class="hero-stats">
                            <div class="stat">
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Active Users</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">50+</span>
                                <span class="stat-label">Events Organized</span>
                            </div>
                            <div class="stat">
                                <span class="stat-number">1000+</span>
                                <span class="stat-label">Trees Planted</span>
                            </div>
                        </div>
                    </div>
                    <div class="hero-image">
                        <img src="images/mangrove-login.jpg" alt="Mangrove Ecosystem">
                    </div>
                </div>
            </section>

            <!-- Mission & Vision Section -->
            <section class="mission-vision">
                <div class="container">
                    <div class="mission-vision-grid">
                        <div class="mission-card">
                            <div class="card-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h3>Our Mission</h3>
                            <p>To empower citizens and local officials in protecting, monitoring, and growing mangrove forests by providing a digital ecosystem for reporting, participation, and education.</p>
                        </div>
                        <div class="vision-card">
                            <div class="card-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h3>Our Vision</h3>
                            <p>A future where every coastal community actively participates in mangrove conservation, creating resilient ecosystems that protect both nature and people.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="features-section">
                <div class="container">
                    <div class="section-header">
                        <h2>Platform Features</h2>
                        <p>Discover the tools and capabilities that make ManGrow an effective conservation platform</p>
                    </div>
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4>Community Registration</h4>
                            <p>Locals can join to participate in programs and earn eco-points for their contributions.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h4>Gamification & Rewards</h4>
                            <p>Encourages participation through gamification and eco-leaderboards.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-tree"></i>
                            </div>
                            <h4>Tree Monitoring</h4>
                            <p>Track mangrove area growth with assigned QR codes and detailed reports.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h4>Environmental Protection</h4>
                            <p>Report illegal mangrove cutting or dumping to protect our ecosystems.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h4>Event Management</h4>
                            <p>Officials can manage planting events, workshops, and volunteer activities.</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-map-marked-alt"></i>
                            </div>
                            <h4>Area Visualization</h4>
                            <p>Visual display of planted mangrove areas and conservation progress.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Impact Section -->
            <section class="impact-section">
                <div class="container">
                    <div class="impact-content">
                        <h2>Community Impact</h2>
                        <p>ManGrow strengthens the connection between people and nature. Through data collection, engagement, and collaboration with environmental groups and LGUs, we've seen increased awareness and participation in mangrove rehabilitation efforts.</p>
                        <div class="impact-stats">
                            <div class="impact-stat">
                                <i class="fas fa-shield-alt"></i>
                                <span>Enhanced Coastal Protection</span>
                            </div>
                            <div class="impact-stat">
                                <i class="fas fa-leaf"></i>
                                <span>Biodiversity Conservation</span>
                            </div>
                            <div class="impact-stat">
                                <i class="fas fa-hands-helping"></i>
                                <span>Community Engagement</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Team Section -->
            <section class="team-section">
                <div class="container">
                    <div class="section-header">
                        <h2>Meet Our Team</h2>
                        <p>The passionate individuals behind ManGrow's mission</p>
                    </div>
                    <div class="team-grid">
                        <div class="team-member" data-name="Christian John C. Sabino" data-role="Backend Developer" data-description="He specializes in using Java, C#, HTML, and CSS. He always want to make sure the team will always accomplish any task needed to be done." data-image="images/obinas.png" data-skills="Environmental Science, Project Management">
                            <div class="member-image">
                                <img src="images/obinas.png" alt="Christian John C. Sabino">
                            </div>
                            <div class="member-info">
                                <h4>Christian John C. Sabino</h4>
                                <p class="member-role">Backend Developer</p>
                                <p class="member-description">He specializes in using Java, C#, HTML, and CSS. He always want to make sure the team will always accomplish any task needed to be done.</p>
                                <div class="member-skills">
                                    <span class="skill-tag">Environmental Science</span>
                                    <span class="skill-tag">Project Management</span>
                                </div>
                            </div>
                        </div>
                        <div class="team-member" data-name="Ralph Ryan V. Marcelo" data-role="Data Analyst" data-description="Ralph Ryan V. Marcelo is our creative thinking, graphic designer extraordinaire. He is the one who usually ends up thinking ideas that are suitable to work projects on." data-image="images/hplar.png" data-skills="Data Analysis, Research">
                            <div class="member-image">
                                <img src="images/hplar.png" alt="Ralph Ryan V. Marcelo">
                            </div>
                            <div class="member-info">
                                <h4>Ralph Ryan V. Marcelo</h4>
                                <p class="member-role">Data Analyst</p>
                                <p class="member-description">Ralph Ryan V. Marcelo is our creative thinking, graphic designer extraordinaire. He is the one who usually ends up thinking ideas that are suitable to work projects on.</p>
                                <div class="member-skills">
                                    <span class="skill-tag">Data Analysis</span>
                                    <span class="skill-tag">Research</span>
                                </div>
                            </div>
                        </div>
                        <div class="team-member" data-name="Sam Brix R. Perello" data-role="Front End Developer" data-description="Sam Brix R. Perello is our ultimate ace, the UI/UX designer that holds the group together. Our great teamwork will not become the same without him." data-image="images/mas.png" data-skills="Environmental Education, UI/UX Design">
                            <div class="member-image">
                                <img src="images/mas.png" alt="Sam Brix R. Perello">
                            </div>
                            <div class="member-info">
                                <h4>Sam Brix R. Perello</h4>
                                <p class="member-role">Front End Developer</p>
                                <p class="member-description">Sam Brix R. Perello is our ultimate ace, the UI/UX designer that holds the group together. Our great teamwork will not become the same without him.</p>
                                <div class="member-skills">
                                    <span class="skill-tag">Environmental Education</span>
                                    <span class="skill-tag">UI/UX Design</span>
                                </div>
                            </div>
                        </div>
                        <div class="team-member" data-name="Paulo B. Herrera" data-role="Backend Developer" data-description="In programming, he is our back-end developer that has skills on implementing advance features. Paulo is also a fitness enthusiast." data-image="images/oluap.png" data-skills="Software Development, Community Outreach">
                            <div class="member-image">
                                <img src="images/oluap.png" alt="Paulo B. Herrera">
                            </div>
                            <div class="member-info">
                                <h4>Paulo B. Herrera</h4>
                                <p class="member-role">Backend Developer</p>
                                <p class="member-description">In programming, he is our back-end developer that has skills on implementing advance features. Paulo is also a fitness enthusiast.</p>
                                <div class="member-skills">
                                    <span class="skill-tag">Software Development</span>
                                    <span class="skill-tag">Community Outreach</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="cta-section">
                <div class="container">
                    <div class="cta-content">
                        <div class="cta-text">
                            <h2>Join Our Conservation Efforts</h2>
                            <p>Be part of the movement to protect and preserve our mangrove ecosystems. Together, we can make a lasting impact on our coastal communities and environment.</p>
                        </div>
                        <div class="cta-buttons">
                            <a href="events.php" class="cta-btn primary">Join Events</a>
                            <a href="initiatives.php" class="cta-btn secondary">Learn More</a>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Team Modal -->
    <div id="teamModal" class="team-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalName"></h3>
                <button class="modal-close" onclick="closeTeamModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-image">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="modal-text">
                    <p class="modal-role" id="modalRole"></p>
                    <p id="modalDescription"></p>
                    <p><strong>Skills:</strong> <span id="modalSkills"></span></p>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Team Modal JavaScript -->
    <script>
        // Team Modal Functions
        function openTeamModal(member) {
            const modal = document.getElementById('teamModal');
            const name = document.getElementById('modalName');
            const role = document.getElementById('modalRole');
            const description = document.getElementById('modalDescription');
            const image = document.getElementById('modalImage');
            const skills = document.getElementById('modalSkills');
            
            // Set modal content from data attributes
            name.textContent = member.dataset.name;
            role.textContent = member.dataset.role;
            description.textContent = member.dataset.description;
            image.src = member.dataset.image;
            image.alt = member.dataset.name;
            skills.textContent = member.dataset.skills;
            
            // Show modal
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Add animation
            modal.style.animation = 'modalFadeIn 0.3s ease';
        }

        function closeTeamModal() {
            const modal = document.getElementById('teamModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Add click event listeners to team members
        document.addEventListener('DOMContentLoaded', function() {
            const teamMembers = document.querySelectorAll('.team-member');
            
            teamMembers.forEach(member => {
                member.addEventListener('click', () => openTeamModal(member));
            });
            
            // Close modal when clicking outside or on close button
            const modal = document.getElementById('teamModal');
            const closeBtn = document.querySelector('.modal-close');
            
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeTeamModal();
                    }
                });
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', closeTeamModal);
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    closeTeamModal();
                }
            });
        });
    </script>
</body>
</html>