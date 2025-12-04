<!--
<?php
session_start();
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

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize variables
    $errors = [];
    $thumbnail_path = '';
    $supporting_images = [];
    $document_path = '';
    $video_path = '';
    $description = null;
    $reference_link = null;
    
    // Validate required fields
    if (empty($_POST['title'])) {
        $errors[] = "Title is required";
    }
    
    if (empty($_POST['caption'])) {
        $errors[] = "Caption is required";
    }
    
    // Validate tags
    $tags = array_filter($_POST['tags'] ?? [], function($tag) {
        return !empty(trim($tag));
    });
    
    if (count($tags) === 0) {
        $errors[] = "At least one tag is required";
    }
    
    // Handle file uploads
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $thumbnail_path = uploadFile($_FILES['thumbnail'], 'thumbnails');
        if (!$thumbnail_path) {
            $errors[] = "Failed to upload thumbnail";
        }
    } else {
        $errors[] = "Thumbnail image is required";
    }
    
    // Handle program type specific fields
    $program_type = $_POST['program_type'] ?? '';
    
    switch ($program_type) {
        case 'conservation':
        case 'research':
        case 'news':
        case 'articles':
        case 'community':
            if (($_POST['publish_option'] ?? '') === 'create') {
                if (empty($_POST['description'])) {
                    $errors[] = "Description is required";
                } else {
                    $description = trim($_POST['description']);
                }
                
                // Handle supporting images
                if (isset($_FILES['supporting_images'])) {
                    foreach ($_FILES['supporting_images']['name'] as $key => $name) {
                        if ($_FILES['supporting_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $name,
                                'type' => $_FILES['supporting_images']['type'][$key],
                                'tmp_name' => $_FILES['supporting_images']['tmp_name'][$key],
                                'error' => $_FILES['supporting_images']['error'][$key],
                                'size' => $_FILES['supporting_images']['size'][$key]
                            ];
                            $path = uploadFile($file, 'supporting_images');
                            if ($path) {
                                $supporting_images[] = $path;
                            }
                        }
                    }
                }
            } else {
                if (empty($_POST['reference_link'])) {
                    $errors[] = "Reference link is required";
                } else {
                    $reference_link = filter_var(trim($_POST['reference_link']), FILTER_VALIDATE_URL);
                    if (!$reference_link) {
                        $errors[] = "Valid reference link is required";
                    }
                }
            }
            break;
            
        case 'guides':
            if (($_POST['publish_option'] ?? '') === 'upload') {
                if (isset($_FILES['guide_file']) && $_FILES['guide_file']['error'] === UPLOAD_ERR_OK) {
                    $document_path = uploadFile($_FILES['guide_file'], 'documents');
                    if (!$document_path) {
                        $errors[] = "Failed to upload guide document";
                    }
                } else {
                    $errors[] = "Guide document is required";
                }
            } else {
                if (empty($_POST['reference_link'])) {
                    $errors[] = "Reference link is required";
                } else {
                    $reference_link = filter_var(trim($_POST['reference_link']), FILTER_VALIDATE_URL);
                    if (!$reference_link) {
                        $errors[] = "Valid reference link is required";
                    }
                }
            }
            break;
            
        case 'videos':
            if (($_POST['publish_option'] ?? '') === 'upload') {
                if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                    $video_path = uploadFile($_FILES['video_file'], 'videos');
                    if (!$video_path) {
                        $errors[] = "Failed to upload video file";
                    }
                } else {
                    $errors[] = "Video file is required";
                }
            } else {
                if (empty($_POST['reference_link'])) {
                    $errors[] = "Reference link is required";
                } else {
                    $reference_link = filter_var(trim($_POST['reference_link']), FILTER_VALIDATE_URL);
                    if (!$reference_link) {
                        $errors[] = "Valid reference link is required";
                    }
                }
            }
            break;
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            // Prepare the statement
            $stmt = $connection->prepare("INSERT INTO mangroveinitiativestbl (
                program_type, title, caption, thumbnail, tag1, tag2, description, 
                reference_link, supporting_image1, supporting_image2, supporting_image3, 
                document_path, video_path, account_id, organization, date_published
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $connection->error);
            }
            
            $account_id = $_SESSION['user_id'] ?? 0;
            $organization = $_SESSION['organization'] ?? 'N/A';
            
            // Set default values for null fields
            $tag1 = $tags[0] ?? '';
            $tag2 = $tags[1] ?? '';
            $supporting_image1 = $supporting_images[0] ?? '';
            $supporting_image2 = $supporting_images[1] ?? '';
            $supporting_image3 = $supporting_images[2] ?? '';
            
            // Bind parameters
            $stmt->bind_param("sssssssssssssis", 
                $program_type,
                $_POST['title'],
                $_POST['caption'],
                $thumbnail_path,
                $tag1,
                $tag2,
                $description,
                $reference_link,
                $supporting_image1,
                $supporting_image2,
                $supporting_image3,
                $document_path,
                $video_path,
                $account_id,
                $organization
            );
            
            if ($stmt->execute()) {
                $initiative_id = $connection->insert_id;
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Initiative published successfully!'
                ];
                
                // Redirect to view the new initiative
                header("Location: initiatives-details.php?initiatives_id=$initiative_id");
                exit();
            } else {
                throw new Exception("Database error: " . $connection->error);
            }
        } catch (Exception $e) {
            $errors[] = "Failed to save initiative: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
    
    // If we got here, there were errors
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => implode("<br>", $errors)
    ];
    
    // Redirect back to form with errors
    header("Location: initiatives.php");
    exit();
}

function uploadFile($file, $subfolder) {
    $upload_dir = __DIR__ . "/uploads/$subfolder/";
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            return false;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        return false;
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $target_path = $upload_dir . $filename;
    
    // Validate file type based on subfolder
    $allowed_types = [];
    switch ($subfolder) {
        case 'thumbnails':
        case 'supporting_images':
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            break;
        case 'documents':
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            break;
        case 'videos':
            $allowed_types = ['video/mp4', 'video/webm', 'video/quicktime'];
            break;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return "uploads/$subfolder/$filename";
    }
    
    return false;
}
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ManGrow Initiatives</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>

<style>
    :root{
    --base-clr: #123524;
    --line-clr: indigo;
    --secondarybase-clr: lavenderblush;
    --text-clr: #222533;
    --accent-clr: #EFE3C2;
    --secondary-text-clr: #123524;
    --placeholder-text-clr:#3E7B27;
    --event-clr:#FFFDF6;
}
    .initiatives-container {
        padding: 20px;
        background-color: var(--accent-clr);
        min-height: 100vh;
        overflow-y: auto;
    }

    .initiatives-header {
        padding-top:1rem;
        text-align: center;
        margin-bottom: 30px;
    }

    .initiatives-header h1 {
        color: var(--base-clr);
        font-size: 2.5rem;
        margin-bottom: 10px;
    }

    .initiatives-header p {
        color:var(--placeholder-text-clr);
        font-size: 1.1rem;
    }

    .initiatives-filter {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 8px 15px;
        border: 1px solid var(--base-clr);
        background: rgba(62, 123, 39, 0.1);
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 1.1rem;
    }

    .filter-btn:hover{
        background: var(--placeholder-text-clr);
        color:azure;
    }

    .filter-btn.active {
        background-color: var(--base-clr);
        color:azure;
        border-color:var(--base-clr);
    }

    .initiatives-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 25px;
    }

    .initiative-card {
        background: var(--event-clr);
        border-radius: 10px;
        border-top: 5px solid var(--base-clr);
        border-bottom: 3px solid var(--base-clr);
        border-left: 1px solid var(--base-clr);
        border-right: 1px solid var(--base-clr);
        overflow: hidden;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }

    .initiative-card:hover {
        transform: translateY(-5px);
    }

    .card-image {
        position: relative;
        height: 180px;
        overflow: hidden;
    }

    .card-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .content-type {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
        color: white;
    }

    .content-type.news {
        background-color: #e74c3c;
    }

    .content-type.article {
        background-color: #3498db;
    }

    .content-type.video {
        background-color: #9b59b6;
    }

    .content-type.guide {
        background-color: #27ae60;
    }

    .content-type.research {
        background-color: #f39c12;
    }

    .card-content {
        display:flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 20px;
        height:calc(100% - 180px);
        box-sizing: border-box;
    }

    @media(max-width:768px){
        .card-content{
            min-width:280px;
            max-height:400px;
        }
    }

    .tags {
        display: flex;
        gap: 5px;
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .tag {
        height: fit-content;
        background-color: #ecf0f1;
        color: #7f8c8d;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
    }

    .card-content h3 {
        color: #2c3e50;
        margin-bottom: 10px;
        font-size: 1.2rem;
    }

    .date {
        margin:0;
        color: #7f8c8d;
        font-size: 0.8rem;
        margin-bottom: 10px;
    }

    .description {
        color: #34495e;
        margin:0;
        margin-bottom: 15px;
        line-height: 1.5;
        font-size: 0.9rem;
    }

    .card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        align-self: flex-end;
        gap: 10px;
    }

    .view-btn {
        background-color: #2c3e50;
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
        text-decoration: none;
        font-size: 0.9rem;
        flex:1 1 100%;
    }

    .view-btn:hover {
        background-color: #1a252f;
    }

    .author {
        color: #7f8c8d;
        font-size: 0.8rem;
        flex:1 1 100%;
    }

    @media (max-width: 768px) {
        .initiatives-grid {
            grid-template-columns: 1fr;
        }
        
        .initiatives-filter {
            justify-content: flex-start;
        }

        .initiatives-header h1{
            line-height: 1.2;
        }
    }

    .initiatives-highlight {
        background-color: var(--secondarybase-clr);
        padding: 20px;
        margin-bottom: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .initiatives-highlight h2 {
        color: var(--base-clr);
        text-align: center;
        margin-bottom: 25px;
        line-height: 1.2;
        font-size: 1.8rem;
    }

    .initiatives-highlight h3 {
        color: var(--placeholder-text-clr);
        margin: 20px 0 15px;
        font-size: 1.4rem;
    }

    .highlight-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .core-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .core-card {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }

    .core-card:hover {
        transform: translateY(-5px);
    }

    .core-image {
        height: 180px;
        overflow: hidden;
    }

    .core-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .core-content {
        padding: 15px;
    }

    .core-content h4 {
        color: var(--base-clr);
        margin-bottom: 10px;
        font-size: 1.2rem;
    }

    .core-content p {
        color: var(--text-clr);
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 10px;
    }

    .core-tag {
        display: inline-block;
        background-color: var(--accent-clr);
        color: var(--base-clr);
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .small-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .small-card {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .small-card h4 {
        color: var(--placeholder-text-clr);
        font-size: 1.1rem;
        margin-bottom: 8px;
    }

    .small-card p {
        color: var(--text-clr);
        font-size: 0.9rem;
        line-height: 1.4;
    }

    /* Create Button and Actions */
    .initiatives-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .create-container {
        display:flex;
        margin-left: auto;
        margin-bottom: 30px;
    }

    .create-btn {
        background-color: var(--placeholder-text-clr);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.3s;
        font-size: 1.1rem;
    }

    .create-btn:hover {
        background-color: var(--base-clr);
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 25px;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        position: relative;
    }

    .close-modal {
        position: absolute;
        top: 15px;
        right: 20px;
        font-size: 1.5rem;
        cursor: pointer;
        color: #aaa;
    }

    .close-modal:hover {
        color: #333;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--base-clr);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        box-sizing: border-box;
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }

    .form-group textarea {
        min-height: 120px;
        resize: vertical;
    }

    .submit-btn {
        background-color: var(--placeholder-text-clr);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 1rem;
        width: 100%;
        transition: background-color 0.3s;
    }

    .submit-btn:hover {
        background-color: var(--base-clr);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .initiatives-actions {
            flex-direction: column;
        }
        
        .create-container {
            margin-left: 0;
            width: 100%;
        }
        
        .filter-btn{
            font-size: 1rem;
        }

        .create-btn {
            width: 100%;
            font-size:1rem;
            justify-content: center;
        }
        
        .core-grid {
            grid-template-columns: 1fr;
        }
        
        .small-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 480px) {
        .small-grid {
            grid-template-columns: 1fr;
        }
    }

    .radio-group {
        display: flex;
        gap: 15px;
        margin: 10px 0;
    }

    .radio-group label {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
    }

    .note {
        font-size: 0.8rem;
        color: #666;
        margin-top: 5px;
        font-style: italic;
    }

    .create-fields {
        margin-top: 15px;
    }

    #tagsContainer {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 10px;
    }

    .tag-input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        width: 100%;
    }

    .add-tag-btn {
        background-color: #f0f0f0;
        color: var(--base-clr);
        border: 1px solid #ddd;
        padding: 5px 10px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .add-tag-btn:hover {
        background-color: #e0e0e0;
    }

    .add-tag-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .char-count {
        font-size: 0.8rem;
        color: #666;
        text-align: right;
        margin-top: -15px;
        margin-bottom: 10px;
    }

    .char-count.warning {
        color: #e74c3c;
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
        <div class="initiatives-container">
            <div class="initiatives-header">
                <h1>Mangrove Information Hub</h1>
                <p>Educational resources, news, and media about mangrove conservation</p>
            </div>
            <div class="initiatives-highlight">
                <h2>Featured Mangrove Initiatives in the Philippines</h2>
                <div class="highlight-container">
                    <!-- Core Initiatives -->
                    <div class="core-initiatives">
                        <h3>Major Conservation Programs</h3>
                        <div class="core-grid">
                            <div class="core-card">
                                <div class="core-image">
                                    <img src="images/mangrove-login.jpg" alt="Mangrove Rehabilitation">
                                </div>
                                <div class="core-content">
                                    <h4>National Greening Program</h4>
                                    <p>Government-led initiative that has planted over 300,000 mangrove seedlings across coastal communities since 2011.</p>
                                    <span class="core-tag">DENR-led</span>
                                </div>
                            </div>
                            
                            <div class="core-card">
                                <div class="core-image">
                                    <img src="images/mangrove trail.jpg" alt="Bataan Mangrove Park">
                                </div>
                                <div class="core-content">
                                    <h4>Bataan National Park Mangrove Rehabilitation</h4>
                                    <p>Large-scale reforestation of mangrove areas in Bataan National Park, restoring habitats and supporting local fisheries.</p>
                                    <span class="core-tag">DENR & LGU Bataan</span>
                                </div>
                            </div>
                            
                            <div class="core-card">
                                <div class="core-image">
                                    <img src="images/mangrove w meetings.jpg" alt="Philippines Mangrove Conservation">
                                </div>
                                <div class="core-content">
                                    <h4>Philippines Mangrove Conservation Initiatives</h4>
                                    <p>Nationwide efforts involving government, NGOs, and local communities to restore, protect, and sustainably manage mangrove forests across the archipelago.</p>
                                    <span class="core-tag">Multi-sectoral Collaboration</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Small Initiatives -->
                    <div class="small-initiatives">
                        <h3>Local Community Efforts</h3>
                        <div class="small-grid">
                            <div class="small-card">
                                <h4>MENRO Bataan Mangrove Restoration</h4>
                                <p>MENRO Bataan organizes mangrove planting and clean-ups with local communities along the Bataan coast.</p>
                            </div>
                            <div class="small-card">
                                <h4>Mangrove Ecotourism</h4>
                                <p>Guided kayak tours through mangrove forests in Palawan</p>
                            </div>
                            <div class="small-card">
                                <h4>Adopt-a-Mangrove</h4>
                                <p>Schools adopting mangrove patches for monitoring</p>
                            </div>
                            <div class="small-card">
                                <h4>Fishpond Conversion</h4>
                                <p>Abandoned fishponds being restored to mangroves</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<?php 
$showCreateButton = false;
if (isset($_SESSION['accessrole'])) {
    if ($_SESSION['accessrole'] == 'Resident' && isset($_SESSION['organization'])) {
        $showCreateButton = ($_SESSION['organization'] !== null && $_SESSION['organization'] !== 'N/A');
    } else {
        $showCreateButton = true;
    }
}
?>
            <div class="initiatives-actions">
                <div class="initiatives-filter">
                    <button class="filter-btn active" data-filter="all">All Content</button>
                    <button class="filter-btn" data-filter="news">News</button>
                    <button class="filter-btn" data-filter="article">Articles</button>
                    <button class="filter-btn" data-filter="video">Videos</button>
                    <button class="filter-btn" data-filter="guide">Guides</button>
                    <button class="filter-btn" data-filter="research">Research</button>
                    <button class="filter-btn" data-filter="conservation">Conservation</button>
                    <button class="filter-btn" data-filter="community">Community</button>
                </div>
                
                <?php if ($showCreateButton): ?>
                <div class="create-container">
                    <button class="create-btn" id="createInitiativeBtn">
                        <i class="fas fa-plus"></i> Publish Initiative
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <div class="initiatives-grid">
                <?php
                // Fetch initiatives from database sorted by date_published (newest first)
                $query = "SELECT * FROM mangroveinitiativestbl ORDER BY date_published DESC";
                $result = $connection->query($query);
                
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        // Determine the content type class and text based on program_type
                        $contentTypeClass = '';
                        $contentTypeText = '';
                        
                        switch ($row['program_type']) {
                            case 'news':
                                $contentTypeClass = 'news';
                                $contentTypeText = 'News';
                                break;
                            case 'articles':
                                $contentTypeClass = 'article';
                                $contentTypeText = 'Article';
                                break;
                            case 'videos':
                                $contentTypeClass = 'video';
                                $contentTypeText = 'Video';
                                break;
                            case 'guides':
                                $contentTypeClass = 'guide';
                                $contentTypeText = 'Guide';
                                break;
                            case 'research':
                                $contentTypeClass = 'research';
                                $contentTypeText = 'Research';
                                break;
                            case 'conservation':
                                $contentTypeClass = 'conservation';
                                $contentTypeText = 'Conservation';
                                break;
                            case 'community':
                                $contentTypeClass = 'community';
                                $contentTypeText = 'Community';
                                break;
                            default:
                                $contentTypeClass = 'news';
                                $contentTypeText = 'News';
                        }
                        
                        // Format the date
                        $datePublished = new DateTime($row['date_published']);
                        $formattedDate = $datePublished->format('F j, Y');
                        
                        // Prepare tags
                        $tags = [];
                        if (!empty($row['tag1'])) $tags[] = $row['tag1'];
                        if (!empty($row['tag2'])) $tags[] = $row['tag2'];
                        
                        // Determine the view button text based on content type
                        $viewButtonText = 'View';
                        if ($row['program_type'] === 'videos') {
                            $viewButtonText = 'Watch Video';
                        } elseif ($row['program_type'] === 'guides') {
                            $viewButtonText = 'Download';
                        } elseif ($row['program_type'] === 'articles' || $row['program_type'] === 'news') {
                            $viewButtonText = 'Read More';
                        } elseif ($row['program_type'] === 'research') {
                            $viewButtonText = 'View Study';
                        }
                        ?>
                        
                        <div class="initiative-card" data-type="<?= $row['program_type'] ?>" data-tags="<?= implode(',', $tags) ?>">
                            <div class="card-image">
                                <img src="<?= !empty($row['thumbnail']) ? $row['thumbnail'] : 'images/default-thumbnail.jpg' ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                                <span class="content-type <?= $contentTypeClass ?>"><?= $contentTypeText ?></span>
                            </div>
                            <div class="card-content">
                                <?php if (!empty($tags)): ?>
                                <div class="tags">
                                    <?php foreach ($tags as $tag): ?>
                                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <h3><?= htmlspecialchars($row['title']) ?></h3>
                                <p class="date"><i class="far fa-calendar-alt"></i> <?= $formattedDate ?></p>
                                <p class="description"><?= htmlspecialchars($row['caption']) ?></p>
                                <div class="card-footer">
                                    <a href="initiatives-details.php?initiatives_id=<?= $row['initiatives_id'] ?>" class="view-btn"><?= $viewButtonText ?></a>
                                    <div class="author">
                                        <i class="fas fa-user-edit"></i> <?= htmlspecialchars($row['organization']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    // Display a message if no initiatives are found
                    echo '<p class="no-initiatives">No initiatives found. Be the first to publish one!</p>';
                }
                ?>
            </div>
            <br>
            
        <div id="initiativeModal" class="modal">
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <h2>Publish New Initiative</h2>
                <form id="initiativeForm" method="POST" action="initiatives.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="program_type">Initiative Type</label>
                        <select id="program_type" name="program_type" required>
                            <option value="">Select type...</option>
                            <option value="conservation">Conservation Program</option>
                            <option value="research">Research Project</option>
                            <option value="news">News Update</option>
                            <option value="articles">Blog Article</option>
                            <option value="community">Community Activity</option>
                            <option value="guides">Educational Guide</option>
                            <option value="videos">Video Content</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="initiativeTitle">Title</label>
                        <input type="text" id="initiativeTitle" name="title" required placeholder="Enter initiative title">
                    </div>
                    
                    <div class="form-group">
                        <label for="initiativeCaption">Caption</label>
                        <textarea id="initiativeCaption" name="caption" required placeholder="Brief description of your initiative"></textarea>
                    </div>

                    <!-- Thumbnail input -->
                    <div class="form-group">
                        <label for="initiativeThumbnail">Thumbnail Image</label>
                        <input type="file" id="initiativeThumbnail" name="thumbnail" accept="image/*" required>
                        <p class="note">This will be displayed as the cover image for your initiative</p>
                    </div>
                    
                    <!-- Tags input -->
                    <div class="form-group">
                        <label for="initiativeTags">Tags (max 2)</label>
                        <div id="tagsContainer">
                            <input type="text" class="tag-input" name="tags[]" placeholder="Enter a tag (e.g. conservation)" maxlength="20">
                        </div>
                        <button type="button" id="addTagBtn" class="add-tag-btn" disabled>+ Add Another Tag</button>
                        <p class="note">Tags help categorize your initiative (20 characters max per tag)</p>
                    </div>
                    <!-- Dynamic fields will appear here -->
                    <div id="dynamicFields"></div>
                    
                    <button type="submit" class="submit-btn">Publish Initiative</button>
                </form>
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
<script>
        document.addEventListener('DOMContentLoaded', function() {
        // Filter functionality
        const filterBtns = document.querySelectorAll('.filter-btn');
        const initiativeCards = document.querySelectorAll('.initiative-card');

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all buttons
                filterBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked button
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                
                // Filter cards
                initiativeCards.forEach(card => {
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else {
                        const cardType = card.dataset.type;
                        if (cardType === filter) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });

        const tags = document.querySelectorAll('.tag');
        tags.forEach(tag => {
            tag.addEventListener('click', function() {
                const tagText = this.textContent.toLowerCase();
                alert(`In a complete implementation, this would filter by "${tagText}" tag`);
                // You would implement actual filtering logic here
            });
        });
    });

    const modal = document.getElementById('initiativeModal');
    const createBtn = document.getElementById('createInitiativeBtn');
    const closeModal = document.querySelector('.close-modal');
    const dynamicFieldsContainer = document.getElementById('dynamicFields'); // Define this globally

    if (createBtn) {
        createBtn.addEventListener('click', () => {
            modal.style.display = 'block';
        });
    }

    closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    document.getElementById('program_type').addEventListener('change', function() {
        const selectedType = this.value;
        dynamicFieldsContainer.innerHTML = ''; // Clear previous fields
        
        if (['conservation', 'research', 'news', 'articles', 'community'].includes(selectedType)) {
            addPublishChoiceFields(selectedType);
        } else if (selectedType === 'guides') {
            addGuideFields();
        } else if (selectedType === 'videos') {
            addVideoFields();
        }
    });


    document.addEventListener('DOMContentLoaded', function() {
        // Tags functionality
        const tagsContainer = document.getElementById('tagsContainer');
        const addTagBtn = document.getElementById('addTagBtn');
        
        if (tagsContainer && addTagBtn) {
            // Limit caption to 120 characters
            const captionInput = document.getElementById('initiativeCaption');
            if (captionInput) {
                const charCount = document.createElement('div');
                charCount.className = 'char-count';
                charCount.textContent = '0/120';
                captionInput.parentNode.insertBefore(charCount, captionInput.nextSibling);
                
                captionInput.addEventListener('input', function() {
                    if (this.value.length > 120) {
                        this.value = this.value.substring(0, 120);
                    }
                    const remaining = 120 - this.value.length;
                    charCount.textContent = `${this.value.length}/120`;
                    
                    if (remaining < 20) {
                        charCount.classList.add('warning');
                    } else {
                        charCount.classList.remove('warning');
                    }
                });
            }
            
            // Tags functionality
            let tagCount = 1;

             const firstTagInput = tagsContainer.querySelector('.tag-input');
        if (firstTagInput) {
            firstTagInput.addEventListener('input', function() {
                addTagBtn.disabled = this.value.trim() === '';
            });
        }
        
        addTagBtn.addEventListener('click', function() {
            if (tagCount < 2) {
                const newTagInput = document.createElement('input');
                newTagInput.type = 'text';
                newTagInput.className = 'tag-input';
                newTagInput.name = 'tags[]';
                newTagInput.placeholder = 'Enter another tag';
                newTagInput.maxLength = 20;
                
                tagsContainer.appendChild(newTagInput);
                tagCount++;
                
                if (tagCount >= 2) {
                    addTagBtn.disabled = true;
                }
            }
        });
    }
        
    // Form submission
    const initiativeForm = document.getElementById('initiativeForm');
        if (initiativeForm) {
            document.getElementById('initiativeForm').addEventListener('submit', function(e) {
            // Simple client-side validation
            const title = document.getElementById('initiativeTitle').value.trim();
            const caption = document.getElementById('initiativeCaption').value.trim();
            const thumbnail = document.getElementById('initiativeThumbnail').files.length;
            
            if (!title || !caption || !thumbnail) {
                e.preventDefault();
                alert('Please fill out all required fields');
                return false;
            }
            
            // Let the form submit normally if validation passes
            return true;
        });
    }
});
</script>
    <!-- modal options script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const programTypeSelect = document.getElementById('program_type');
    const dynamicFieldsContainer = document.getElementById('dynamicFields');
    
    programTypeSelect.addEventListener('change', function() {
        const selectedType = this.value;
        dynamicFieldsContainer.innerHTML = ''; // Clear previous fields
        
        if (['conservation', 'research', 'news', 'articles', 'community'].includes(selectedType)) {
            addPublishChoiceFields(selectedType);
        } else if (selectedType === 'guides') {
            addGuideFields();
        } else if (selectedType === 'videos') {
            addVideoFields();
        }
    });
    
    function addPublishChoiceFields(type) {
        const choiceDiv = document.createElement('div');
        choiceDiv.className = 'form-group';
        choiceDiv.innerHTML = `
            <label>Publish Option</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="publish_option" value="create" checked>
                    Create Content
                </label>
                <label>
                    <input type="radio" name="publish_option" value="link">
                    Reference Link
                </label>
            </div>
            
            <div id="contentFields">
                <div class="form-group create-fields">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Detailed description of your initiative"></textarea>
                </div>
                <div class="form-group create-fields">
                    <label for="supporting_images">Supporting Images (max 3)</label>
                    <input type="file" id="supporting_images" name="supporting_images[]" accept="image/*" multiple>
                    <p class="note">You can upload up to 3 supporting images</p>
                </div>
            </div>
            
            <div id="linkFields" style="display:none;">
                <div class="form-group">
                    <label for="reference_link">Reference Link</label>
                    <input type="url" id="reference_link" name="reference_link" placeholder="https://example.com">
                </div>
            </div>
        `;
        
        dynamicFieldsContainer.appendChild(choiceDiv);
        
        // Add event listeners for radio buttons
        const createRadio = choiceDiv.querySelector('input[value="create"]');
        const linkRadio = choiceDiv.querySelector('input[value="link"]');
        
        createRadio.addEventListener('change', function() {
            document.getElementById('contentFields').style.display = 'block';
            document.getElementById('linkFields').style.display = 'none';
        });
        
        linkRadio.addEventListener('change', function() {
            document.getElementById('contentFields').style.display = 'none';
            document.getElementById('linkFields').style.display = 'block';
        });
    }
    
    function addGuideFields() {
        const choiceDiv = document.createElement('div');
        choiceDiv.className = 'form-group';
        choiceDiv.innerHTML = `
            <label>Publish Option</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="publish_option" value="upload" checked>
                    Upload Guide
                </label>
                <label>
                    <input type="radio" name="publish_option" value="link">
                    Reference Link
                </label>
            </div>
            
            <div id="uploadFields">
                <div class="form-group">
                    <label for="guide_file">Guide Document</label>
                    <input type="file" id="guide_file" name="guide_file" accept=".pdf,.doc,.docx">
                </div>
            </div>
            
            <div id="linkFields" style="display:none;">
                <div class="form-group">
                    <label for="guide_link">Guide Link</label>
                    <input type="url" id="guide_link" name="reference_link" placeholder="https://example.com/guide">
                </div>
            </div>
        `;
        
        dynamicFieldsContainer.appendChild(choiceDiv);
        
        // Add event listeners for radio buttons
        const uploadRadio = choiceDiv.querySelector('input[value="upload"]');
        const linkRadio = choiceDiv.querySelector('input[value="link"]');
        
        uploadRadio.addEventListener('change', function() {
            document.getElementById('uploadFields').style.display = 'block';
            document.getElementById('linkFields').style.display = 'none';
        });
        
        linkRadio.addEventListener('change', function() {
            document.getElementById('uploadFields').style.display = 'none';
            document.getElementById('linkFields').style.display = 'block';
        });
    }
    
    function addVideoFields() {
        const choiceDiv = document.createElement('div');
        choiceDiv.className = 'form-group';
        choiceDiv.innerHTML = `
            <label>Publish Option</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="publish_option" value="upload" checked>
                    Upload Video
                </label>
                <label>
                    <input type="radio" name="publish_option" value="link">
                    Reference Link
                </label>
            </div>
            
            <div id="uploadFields">
                <div class="form-group">
                    <label for="video_file">Video File</label>
                    <input type="file" id="video_file" name="video_file" accept="video/*">
                    <p class="note">Note: For large videos, consider using Google Drive or YouTube and sharing the link instead.</p>
                </div>
            </div>
            
            <div id="linkFields" style="display:none;">
                <div class="form-group">
                    <label for="video_link">Video Link</label>
                    <input type="url" id="video_link" name="reference_link" placeholder="https://youtube.com/watch?v=...">
                </div>
            </div>
        `;
        
        dynamicFieldsContainer.appendChild(choiceDiv);
        
        // Add event listeners for radio buttons
        const uploadRadio = choiceDiv.querySelector('input[value="upload"]');
        const linkRadio = choiceDiv.querySelector('input[value="link"]');
        
        uploadRadio.addEventListener('change', function() {
            document.getElementById('uploadFields').style.display = 'block';
            document.getElementById('linkFields').style.display = 'none';
        });
        
        linkRadio.addEventListener('change', function() {
            document.getElementById('uploadFields').style.display = 'none';
            document.getElementById('linkFields').style.display = 'block';
        });
    }
});

// Document click script for core-card: redirect on National Greening Program title
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.core-card').forEach(function(card) {
        card.addEventListener('click', function(e) {
            const title = card.querySelector('.core-content h4');
            if (title && title.textContent.trim() === 'National Greening Program') {
                window.open('https://fmb.denr.gov.ph/ngp/what-is-ngp/', '_blank');
            }
        });
    });
});

// Document click script for core-card: redirect on Bataan National Park Mangrove Rehabilitation title
document.querySelectorAll('.core-card').forEach(function(card) {
    card.addEventListener('click', function(e) {
        const title = card.querySelector('.core-content h4');
        if (title && title.textContent.trim() === 'Bataan National Park Mangrove Rehabilitation') {
            window.open('https://denr.gov.ph/news-events/denr-partners-to-implement-project-transform-in-2-bataan-areas/', '_blank');
        }
    });
});
</script>
</body>
</html>