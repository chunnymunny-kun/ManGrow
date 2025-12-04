<?php
require_once 'database.php';
session_start();

$initiative_id = $_GET['initiatives_id'] ?? 0;

// Fetch initiative details
$stmt = $connection->prepare("SELECT * FROM mangroveinitiativestbl WHERE initiatives_id = ?");
$stmt->bind_param("i", $initiative_id);
$stmt->execute();
$result = $stmt->get_result();
$initiative = $result->fetch_assoc();
$stmt->close();

if (!$initiative) {
    header("Location: initiatives.php");
    exit();
}

// If this is a reference link, redirect immediately
if (!empty($initiative['reference_link'])) {
    header("Location: " . $initiative['reference_link']);
    exit();
}

// Fetch author details
$author = [];
if ($initiative['account_id']) {
    $stmt = $connection->prepare("SELECT fullname, profile, profile_thumbnail FROM accountstbl WHERE account_id = ?");
    $stmt->bind_param("i", $initiative['account_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $author = $result->fetch_assoc();
    $stmt->close();
}

// Fetch supporting images
$supporting_images = array_filter([
    $initiative['supporting_image1'],
    $initiative['supporting_image2'],
    $initiative['supporting_image3']
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($initiative['title']) ?> - ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
<style>
.initiative-details {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.initiative-header {
    margin-bottom: 30px;
    text-align: center;
}

.initiative-header h1 {
    margin:1.5rem 0;
    font-size: 2.5rem;
    color: var(--base-clr);
    line-height: 1.2;
}

.initiative-meta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-bottom: 15px;
    color: var(--placeholder-text-clr);
}

.initiative-type {  
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: bold;
    color: white;
}

.initiative-type.conservation { background-color: #27ae60; }
.initiative-type.research { background-color: #3498db; }
.initiative-type.news { background-color: #e74c3c; }
.initiative-type.articles { background-color: #9b59b6; }
.initiative-type.community { background-color: #f39c12; }
.initiative-type.guides { background-color: #1abc9c; }
.initiative-type.videos { background-color: #d35400; }

.initiative-date{
    font-size:20px;
}

.initiative-tags {
    display: flex;
    justify-content: center;
    gap: 10px;
}

.initiative-tags .tag {
    background-color: var(--placeholder-text-clr);
    color: azure;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
}

.initiative-content {
    background-color: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.initiative-thumbnail img {
    width: 100%;
    max-height: 500px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 20px;
}

.initiative-caption {
    font-size: 1.2rem;
    line-height: 1.6;
    color: var(--text-clr);
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.initiative-description {
    font-size: 1rem;
    line-height: 1.6;
    color: var(--text-clr);
    margin-bottom: 30px;
    white-space: pre-line;
}

.initiative-gallery {
    margin: 40px 0;
}

.initiative-gallery h3 {
    margin-bottom: 20px;
    color: var(--base-clr);
}

.gallery-images {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.gallery-images img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.initiative-document, .initiative-video {
    margin: 40px 0;
    padding: 20px;
    background-color: var(--accent-clr);
    border-radius: 8px;
}

.initiative-document h3, .initiative-video h3 {
    margin-bottom: 15px;
    color: var(--base-clr);
}

.download-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background-color: var(--placeholder-text-clr);
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: bold;
    transition: background-color 0.3s;
}

.download-btn:hover {
    background-color: var(--base-clr);
}

.initiative-video video {
    width: 100%;
    border-radius: 8px;
}

.initiative-author {
    background-color: white;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.initiative-author h3 {
    margin-bottom: 20px;
    color: var(--base-clr);
}

.author-info {
    display: flex;
    align-items: center;
    gap: 20px;
}

.author-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
}

.author-avatar.default {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #eee;
    color: #777;
    font-size: 2rem;
}

.author-details {
    flex: 1;
}

.author-name {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--text-clr);
    margin-bottom: 5px;
}

.author-org {
    color: var(--placeholder-text-clr);
    font-size: 0.9rem;
}

@media(max-width:768px){
    .initiative-header h1{
        font-size:28px;
    }
    .initiative-date{
        font-size: 16px;
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
                    <a href="initiatives.php" class="active">Initiatives</a>
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
    <main class="initiative-details">
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
        <div class="initiative-header">
            <h1><?= htmlspecialchars($initiative['title']) ?></h1>
            <div class="initiative-meta">
                <span class="initiative-type <?= $initiative['program_type'] ?>">
                    <?= ucfirst($initiative['program_type']) ?>
                </span>
                <div class="initiative-tags">
                    <?php if (!empty($initiative['tag1'])): ?>
                        <span class="tag"><?= htmlspecialchars($initiative['tag1']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($initiative['tag2'])): ?>
                        <span class="tag"><?= htmlspecialchars($initiative['tag2']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
                <span class="initiative-date">
                    <?= date('F j, Y', strtotime($initiative['date_published'])) ?>
                </span>
        </div>
        
        <div class="initiative-content">
            <div class="initiative-thumbnail">
                <img src="<?= htmlspecialchars($initiative['thumbnail']) ?>" alt="<?= htmlspecialchars($initiative['title']) ?>">
            </div>
            
            <div class="initiative-caption">
                <?= nl2br(htmlspecialchars($initiative['caption'])) ?>
            </div>
            
            <?php if (!empty($initiative['description'])): ?>
                <div class="initiative-description">
                    <?= nl2br(htmlspecialchars($initiative['description'])) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($supporting_images)): ?>
                <div class="initiative-gallery">
                    <h3>Gallery</h3>
                    <div class="gallery-images">
                        <?php foreach ($supporting_images as $image): ?>
                            <img src="<?= htmlspecialchars($image) ?>" alt="Supporting image">
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($initiative['document_path'])): ?>
                <div class="initiative-document">
                    <h3>Download Guide</h3>
                    <a href="<?= htmlspecialchars($initiative['document_path']) ?>" class="download-btn" download>
                        <i class="fas fa-file-download"></i> Download Document
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($initiative['video_path'])): ?>
                <div class="initiative-video">
                    <h3>Watch Video</h3>
                    <video controls>
                        <source src="<?= htmlspecialchars($initiative['video_path']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="initiative-author">
            <h3>Published by</h3>
            <div class="author-info">
                <?php if (!empty($author['profile'])): ?>
                    <img src="<?= htmlspecialchars($author['profile']) ?>" alt="Author profile" class="author-avatar">
                <?php else: ?>
                    <div class="author-avatar default">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
                
                <div class="author-details">
                    <div class="author-name"><?= htmlspecialchars($author['fullname'] ?? 'Unknown') ?></div>
                    <div class="author-org"><?= htmlspecialchars($initiative['organization']) ?></div>
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
    <!-- Include your footer from initiatives.php -->
</body>
</html>
<?php $connection->close(); ?>