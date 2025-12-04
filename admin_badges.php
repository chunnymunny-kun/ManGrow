<?php
session_start();
include 'database.php';
include 'badge_system_db.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    header("Location: index.php");
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'This account is not authorized to enter this page'
    ];
    header("Location: index.php");
    exit();
}

// Initialize badge system
BadgeSystem::init($connection);

// Get flash message if exists
$flashMessage = '';
$flashType = '';
if (isset($_SESSION['flash_message'])) {
    $flashMessage = $_SESSION['flash_message']['message'];
    $flashType = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']); // Clear the flash message after displaying
}

// Get all badges
$allBadges = BadgeSystem::getAllBadges();
$categories = BadgeSystem::getCategories();

// Get badge details if editing
$editBadge = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editBadge = BadgeSystem::getBadgeById($_GET['edit']);
}

// Get badge statistics for view badges section
$badgeStats = BadgeSystem::calculateBadgeStatistics($connection);

// Get users for badge awarding
$usersQuery = "SELECT account_id, fullname, email FROM accountstbl ORDER BY fullname";
$usersResult = $connection->query($usersQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge Management - ManGrow Admin</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
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
            --mangrove-clr: #27ae60;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--accent-clr);
            color: var(--text-clr);
        }

        .container {
            background: var(--accent-clr);
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--base-clr);
            color: azure;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .tabs {
            display: flex;
            background: var(--event-clr);
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .tab {
            flex: 1;
            padding: 15px 20px;
            background: var(--accent-clr);
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            color: var(--secondary-text-clr);
        }

        .tab.active {
            background: var(--base-clr);
            color: azure;
        }

        .tab:hover {
            background: var(--event-clr);
            color: var(--base-clr);
        }

        .tab.active:hover {
            background: var(--placeholder-text-clr);
        }

        .tab-content {
            display: none;
            background: var(--event-clr);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            margin-bottom: 20px;
        }

        .tab-content.active {
            display: block;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            position: relative;
            animation: slideIn 0.5s ease-out;
        }

        .message.success {
            background: var(--event-clr);
            color: var(--mangrove-clr);
            border: 1px solid var(--placeholder-text-clr);
        }

        .message.error {
            background: var(--accent-clr);
            color: var(--text-clr);
            border: 1px solid var(--placeholder-text-clr);
        }

        .message .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
        }

        .message .close-btn:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--secondary-text-clr);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--accent-clr);
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background: var(--event-clr);
            color: var(--text-clr);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--base-clr);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn {
            background: var(--base-clr);
            color: azure;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: var(--placeholder-text-clr);
        }

        .btn-danger {
            background: var(--placeholder-text-clr);
        }

        .btn-danger:hover {
            background: var(--base-clr);
        }

        .badge-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
            width: 100%;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .badge-card {
            background: var(--event-clr);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
            width: 100%;
            height: auto;
            min-height: 350px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            overflow: hidden;
        }

        .badge-card:hover {
            transform: translateY(-5px);
        }

        .badge-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-clr);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
            border: 2px solid var(--placeholder-text-clr);
            font-size: 24px;
        }

        .badge-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
            color: var(--text-clr);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }

        .badge-description {
            font-size: 14px;
            color: var(--text-clr);
            margin-bottom: 15px;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            max-height: 60px;
        }

        .badge-instructions {
            box-sizing:border-box;
            font-size: 13px;
            color: #1976D2;
            margin-bottom: 15px;
            line-height: 1.4;
            padding: 10px;
            background: azure;
            border-radius: 5px;
            border-left: 3px solid var(--line-clr);
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            max-height: 80px;
        }

        .badge-instructions strong {
            color: var(--base-clr);
        }

        .badge-category {
            background: var(--placeholder-text-clr);
            color: azure;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
            display: inline-block;
        }

        .badge-stats {
            font-size: 12px;
            color: var(--placeholder-text-clr);
            margin-bottom: 12px;
            margin-top: auto;
            padding-top: 10px;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--base-clr);
            text-decoration: none;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .back-btn:hover {
            color: var(--placeholder-text-clr);
        }

        /* Navigation Buttons */
        .nav-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .back-button {
            background: var(--event-clr);
            color: var(--base-clr);
            border-color: var(--placeholder-text-clr);
        }

        .back-button:hover {
            background: var(--base-clr);
            color: azure;
            border-color: var(--base-clr);
            transform: translateX(-2px);
        }

        .leaderboards-button {
            background: var(--placeholder-text-clr);
            color: azure;
            border-color: var(--placeholder-text-clr);
        }

        .leaderboards-button:hover {
            background: var(--base-clr);
            color: azure;
            border-color: var(--base-clr);
            transform: translateX(2px);
        }

        .nav-button i {
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--event-clr);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: var(--base-clr);
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-clr);
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .badge-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .badge-card {
                width: 100%;
                height: auto;
                min-height: 280px;
                padding: 15px;
            }

            .badge-name {
                font-size: 16px;
            }

            .nav-button {
                padding: 10px 16px;
                font-size: 13px;
            }

            .nav-button .button-text {
                display: none;
            }

            .back-button::after {
                content: ' Dashboard';
            }

            .leaderboards-button::after {
                content: ' Leaderboards';
            }
        }

        @media (max-width: 480px) {
            .badge-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .badge-card {
                width: 100%;
                height: auto;
                min-height: 260px;
                padding: 10px;
                flex-shrink: 0;
            }

            .badge-name {
                font-size: 13px;
                max-width: 150px;
                padding: 12px;
            }

            .badge-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .nav-button {
                padding: 8px 12px;
                font-size: 12px;
            }

            .back-button::after {
                content: '';
            }

            .leaderboards-button::after {
                content: '';
            }

            div[style*="display: flex"] {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
        }

        /* Form Container and Preview Styles */
        .form-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
            align-items: start;
        }

        .form-section {
            background: var(--event-clr);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
        }

        .preview-section {
            background: var(--event-clr);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            position: sticky;
            top: 20px;
        }

        .preview-section h3 {
            margin-bottom: 15px;
            color: var(--text-clr);
            font-size: 1.1em;
        }

        .badge-preview-container {
            display:flex;
            flex-direction: column;
            text-align: center;
        }

        .badge-preview {
            display: inline-flex;
            align-self:center;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 160px;
            height: 180px;
            border-radius: 15px;
            background: var(--base-clr);
            color: azure;
            text-align: center;
            margin-bottom: 10px;
            box-shadow: 0 4px 15px rgba(18, 53, 36, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .badge-preview .badge-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .badge-preview .badge-image {
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }

        .badge-preview .badge-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            border-radius: 50%;
            min-width: 100px;
            min-height: 100px;
            max-width: 100px;
            max-height: 100px;
            display: block;
            transform: scale(1);
            transition: transform 0.3s ease;
        }

        /* Ensure image container is properly constrained */
        .badge-preview .badge-image {
            position: relative;
        }

        .badge-preview .badge-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: transparent;
            z-index: 1;
            pointer-events: none;
        }

        .badge-preview.has-image .badge-icon {
            display: none;
        }

        .badge-preview.has-image p {
            position: static;
            margin: 0;
            padding: 0 10px;
            background: none;
            color: azure;
            font-size: 0.9rem;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 140px;
        }

        .badge-preview p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
            padding: 0 10px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 140px;
        }

        .badge-preview-container small {
            color: var(--placeholder-text-clr);
            font-style: italic;
        }

        .form-group input[type="file"] {
            padding: 8px;
            border: 2px dashed var(--placeholder-text-clr);
            border-radius: 5px;
            background: var(--accent-clr);
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-clr);
        }

        .form-group input[type="file"]:hover {
            border-color: var(--base-clr);
            background: var(--event-clr);
        }

        .form-group input[type="file"]:focus {
            outline: none;
            border-color: var(--base-clr);
            box-shadow: 0 0 5px rgba(18, 53, 36, 0.3);
        }

        #current_image_display {
            padding: 10px;
            background: var(--event-clr);
            border: 1px solid var(--placeholder-text-clr);
            border-radius: 5px;
            font-size: 14px;
            color: var(--text-clr);
        }

        @media (max-width: 1024px) {
            .form-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .preview-section {
                position: static;
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .badge-preview {
                width: 140px;
                height: 160px;
            }

            .badge-preview .badge-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }

            .badge-preview .badge-image {
                width: 60px;
                height: 60px;
            }

            .badge-preview .badge-image img {
                min-width: 60px;
                min-height: 60px;
                max-width: 60px;
                max-height: 60px;
            }

            .badge-preview p {
                font-size: 0.85rem;
                max-width: 120px;
            }
        }

        /* Award Badge Section Styles */
        .award-section {
            background: var(--event-clr);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(18, 53, 36, 0.1);
            border-left: 4px solid var(--placeholder-text-clr);
        }

        .award-section h3 {
            color: var(--text-clr);
            margin-bottom: 20px;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .award-section h3 i {
            color: var(--placeholder-text-clr);
        }

        .user-selection-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .selection-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-secondary {
            background: var(--accent-clr);
            color: var(--secondary-text-clr);
            border: 2px solid var(--placeholder-text-clr);
        }

        .btn-secondary:hover {
            background: var(--placeholder-text-clr);
            color: azure;
        }

        .btn-primary {
            background: var(--placeholder-text-clr);
            color: azure;
            font-size: 18px;
            padding: 15px 30px;
        }

        .btn-primary:hover {
            background: var(--base-clr);
        }

        .selection-counter {
            background: var(--base-clr);
            color: azure;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .user-search {
            position: relative;
            max-width: 300px;
        }

        .user-search input {
            width: 100%;
            padding: 10px 40px 10px 15px;
            border: 2px solid var(--accent-clr);
            border-radius: 25px;
            font-size: 14px;
            background: var(--event-clr);
        }

        .user-search i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--placeholder-text-clr);
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 2px solid var(--accent-clr);
            border-radius: 10px;
            background: var(--secondarybase-clr);
        }

        .user-card {
            background: var(--event-clr);
            border: 2px solid var(--accent-clr);
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-card:hover {
            border-color: var(--placeholder-text-clr);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(18, 53, 36, 0.15);
        }

        .user-card.selected {
            border-color: var(--base-clr);
            background: var(--accent-clr);
            box-shadow: 0 4px 12px rgba(18, 53, 36, 0.2);
        }

        .user-card input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--base-clr);
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: bold;
            color: var(--text-clr);
            margin-bottom: 5px;
        }

        .user-email {
            color: var(--placeholder-text-clr);
            font-size: 13px;
        }

        .award-summary {
            background: var(--accent-clr);
            border: 2px solid var(--placeholder-text-clr);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }

        .award-summary h4 {
            color: var(--text-clr);
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .summary-item {
            background: var(--event-clr);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--accent-clr);
            border-radius: 50%;
            border-top-color: var(--base-clr);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .no-users-message {
            text-align: center;
            padding: 40px;
            color: var(--placeholder-text-clr);
            font-style: italic;
        }

        @media (max-width: 768px) {
            .user-selection-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .selection-actions {
                justify-content: center;
                flex-wrap: wrap;
            }

            .users-grid {
                grid-template-columns: 1fr;
                max-height: 300px;
            }

            .user-card {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="adminpage.php" class="nav-button back-button">
                <i class="fas fa-arrow-left"></i> Back to Admin Dashboard
            </a>
            
            <a href="adminleaderboards.php" class="nav-button leaderboards-button">
                <i class="fas fa-trophy"></i> Leaderboards & Eco Shop <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="header">
            <h1><i class="fas fa-medal"></i> Badge Management System</h1>
            <p>Manage badges and award achievements</p>
        </div>

        <?php if ($flashMessage): ?>
            <div class="message <?php echo $flashType; ?>" id="flashMessage">
                <?php echo htmlspecialchars($flashMessage); ?>
                <button type="button" class="close-btn" onclick="closeFlashMessage()">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($allBadges); ?></div>
                <div class="stat-label">Total Badges</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($categories); ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $totalUsersQuery = "SELECT COUNT(*) as total FROM accountstbl WHERE badges IS NOT NULL";
                    $result = $connection->query($totalUsersQuery);
                    echo $result ? $result->fetch_assoc()['total'] : 0;
                    ?>
                </div>
                <div class="stat-label">Users with Badges</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="openTab(event, 'viewBadges')">View Badges</button>
            <button class="tab" onclick="openTab(event, 'addBadge')">Add Badge</button>
            <button class="tab" onclick="openTab(event, 'editBadge')">Edit Badge</button>
            <button class="tab" onclick="openTab(event, 'awardBadge')">Award Badge</button>
        </div>

        <!-- View Badges Tab -->
        <div id="viewBadges" class="tab-content active">
            <h2><i class="fas fa-list"></i> All Badges</h2>
            <div class="badge-grid">
                <?php foreach ($allBadges as $badgeName => $badge): ?>
                    <div class="badge-card">
                        <?php 
                        $showImage = false;
                        if (!empty($badge['image']) && file_exists($badge['image'])) {
                            $showImage = true;
                        }
                        ?>
                        <?php if ($showImage): ?>
                            <div class="badge-image" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; margin: 0 auto 15px auto; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center;">
                                <img src="<?php echo htmlspecialchars($badge['image']); ?>" alt="Badge Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            </div>
                        <?php else: ?>
                            <div class="badge-icon" style="background: <?php echo $badge['color']; ?>; color: white;">
                                <i class="<?php echo !empty($badge['icon']) ? $badge['icon'] : 'fas fa-medal'; ?>"></i>
                            </div>
                        <?php endif; ?>
                        <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                        <div class="badge-category"><?php echo htmlspecialchars($badge['category']); ?></div>
                        <div class="badge-description"><?php echo htmlspecialchars($badge['description']); ?></div>
                        <?php if (!empty($badge['instructions'])): ?>
                            <div class="badge-instructions">
                                <strong>How to earn:</strong> <?php echo htmlspecialchars($badge['instructions']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($badgeStats[$badgeName])): ?>
                            <div class="badge-stats">
                                <?php echo $badgeStats[$badgeName]['users_with_badge']; ?> users (<?php echo $badgeStats[$badgeName]['percentage']; ?>%)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add Badge Tab -->
        <div id="addBadge" class="tab-content">
            <h2><i class="fas fa-plus"></i> Add New Badge</h2>
            <div class="form-container">
                <div class="form-section">
                    <form method="POST" action="add_badge_process.php" enctype="multipart/form-data">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="badge_name">Badge Name *</label>
                                <input type="text" id="badge_name" name="badge_name" required onInput="updateBadgePreview()">
                            </div>
                            <div class="form-group">
                                <label for="category">Category *</label>
                                <select id="category" name="category" required onchange="updateBadgePreview()">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                    <option value="new">+ Add New Category</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description *</label>
                            <textarea id="description" name="description" required placeholder="Describe what this badge represents..." onInput="updateBadgePreview()"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="instructions">Instructions *</label>
                            <textarea id="instructions" name="instructions" required placeholder="Explain how users can earn this badge..." onInput="updateBadgePreview()"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="icon_class">Icon Class *</label>
                                <input type="text" id="icon_class" name="icon_class" required placeholder="e.g., fas fa-medal" onInput="updateBadgePreview()">
                                <small>Use FontAwesome icon classes</small>
                            </div>
                            <div class="form-group">
                                <label for="color">Color *</label>
                                <input type="color" id="color" name="color" required value="#4CAF50" onchange="updateBadgePreview()">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="image_upload">Badge Image</label>
                            <input type="file" id="image_upload" name="image_upload" accept="image/*" onchange="previewImage(this, 'badgePreview')">
                            <small>Upload an image file (PNG, JPG, GIF). Leave empty to use icon only.</small>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Add Badge
                        </button>
                    </form>
                </div>
                
                <div class="preview-section">
                    <h3><i class="fas fa-eye"></i> Badge Preview</h3>
                    <div class="badge-preview-container">
                        <div class="badge-preview" id="badgePreview">
                            <div class="badge-image" id="badgeImage" style="display: none;">
                                <img src="" alt="Badge Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 15px;">
                            </div>
                            <div class="badge-icon" id="badgeIcon">
                                <i class="fas fa-medal"></i>
                            </div>
                            <p>Badge Name</p>
                        </div>
                        <small>This is how your badge will appear to users</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Badge Tab -->
        <div id="editBadge" class="tab-content">
            <h2><i class="fas fa-edit"></i> Edit Badge</h2>
            <div class="form-container">
                <div class="form-section">
                    <form method="POST" action="update_badge_process.php" id="editBadgeForm" enctype="multipart/form-data">
                        <input type="hidden" name="badge_id" id="edit_badge_id">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_badge_name">Badge Name *</label>
                                <input type="text" id="edit_badge_name" name="badge_name" required onInput="updateEditBadgePreview()">
                            </div>
                            <div class="form-group">
                                <label for="edit_category">Category *</label>
                                <select id="edit_category" name="category" required onchange="updateEditBadgePreview()">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_description">Description *</label>
                            <textarea id="edit_description" name="description" required placeholder="Describe what this badge represents..." onInput="updateEditBadgePreview()"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="edit_instructions">Instructions *</label>
                            <textarea id="edit_instructions" name="instructions" required placeholder="Explain how users can earn this badge..." onInput="updateEditBadgePreview()"></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_icon_class">Icon Class *</label>
                                <input type="text" id="edit_icon_class" name="icon_class" required placeholder="e.g., fas fa-medal" onInput="updateEditBadgePreview()">
                                <small>Use FontAwesome icon classes</small>
                            </div>
                            <div class="form-group">
                                <label for="edit_color">Color *</label>
                                <input type="color" id="edit_color" name="color" required value="#4CAF50" onchange="updateEditBadgePreview()">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_image_upload">Badge Image</label>
                            <input type="file" id="edit_image_upload" name="image_upload" accept="image/*" onchange="previewImage(this, 'editBadgePreview')">
                            <input type="hidden" id="edit_image_path" name="current_image_path">
                            <small>Upload a new image file (PNG, JPG, GIF) or leave empty to keep current image.</small>
                            <div id="current_image_display" style="margin-top: 10px; display: none;">
                                <small>Current image: <span id="current_image_name"></span></small>
                            </div>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update Badge
                        </button>
                    </form>
                </div>
                
                <div class="preview-section">
                    <h3><i class="fas fa-eye"></i> Badge Preview</h3>
                    <div class="badge-preview-container">
                        <div class="badge-preview" id="editBadgePreview">
                            <div class="badge-image" id="editBadgeImage" style="display: none;">
                                <img src="" alt="Badge Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 15px;">
                            </div>
                            <div class="badge-icon" id="editBadgeIcon">
                                <i class="fas fa-medal"></i>
                            </div>
                            <p>Badge Name</p>
                        </div>
                        <small>This is how your badge will appear to users</small>
                    </div>
                </div>
            </div>

            <!-- Badge Selection for Editing -->
            <div style="margin-top: 30px;">
                <h3>Select Badge to Edit:</h3>
                <div class="badge-grid">
                    <?php foreach ($allBadges as $badgeName => $badge): ?>
                        <div class="badge-card" style="cursor: pointer;" onclick="loadBadgeForEdit('<?php echo htmlspecialchars($badgeName); ?>')">
                            <?php 
                            $showImage = false;
                            if (!empty($badge['image']) && file_exists($badge['image'])) {
                                $showImage = true;
                            }
                            ?>
                            <?php if ($showImage): ?>
                                <div class="badge-image" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; margin: 0 auto 15px auto; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center;">
                                    <img src="<?php echo htmlspecialchars($badge['image']); ?>" alt="Badge Image" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                </div>
                            <?php else: ?>
                                <div class="badge-icon" style="background: <?php echo $badge['color']; ?>; color: white;">
                                    <i class="<?php echo !empty($badge['icon']) ? $badge['icon'] : 'fas fa-medal'; ?>"></i>
                                </div>
                            <?php endif; ?>
                            <div class="badge-name"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="badge-category"><?php echo htmlspecialchars($badge['category']); ?></div>
                            <small>Click to edit</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Award Badge Tab -->
        <div id="awardBadge" class="tab-content">
            <h2><i class="fas fa-award"></i> Award Badge to Users</h2>
            
            <!-- Badge Selection Section -->
            <div class="award-section">
                <h3><i class="fas fa-medal"></i> Step 1: Select Badge</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category_filter">Filter by Category</label>
                        <select id="category_filter" onchange="filterBadgesByCategory()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="selected_badge">Select Badge *</label>
                        <select id="selected_badge" onchange="loadEligibleUsers()" required>
                            <option value="">Choose a badge...</option>
                            <?php foreach ($allBadges as $badgeName => $badge): ?>
                                <option value="<?php echo htmlspecialchars($badgeName); ?>" data-category="<?php echo htmlspecialchars($badge['category']); ?>">
                                    <?php echo htmlspecialchars($badgeName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- User Selection Section -->
            <div class="award-section" id="user_selection_section" style="display: none;">
                <h3><i class="fas fa-users"></i> Step 2: Select Users</h3>
                <div class="user-selection-controls">
                    <div class="selection-actions">
                        <button type="button" class="btn btn-secondary" onclick="selectAllUsers()">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="deselectAllUsers()">
                            <i class="fas fa-square"></i> Deselect All
                        </button>
                        <span id="selected_count" class="selection-counter">0 users selected</span>
                    </div>
                    <div class="user-search">
                        <input type="text" id="user_search" placeholder="Search users..." onkeyup="filterUsers()">
                        <i class="fas fa-search"></i>
                    </div>
                </div>
                
                <div id="eligible_users_container" class="users-grid">
                    <!-- Users will be loaded here -->
                </div>
            </div>

            <!-- Award Form -->
            <form method="POST" action="badge_actions_process.php" id="award_form" style="display: none;">
                <input type="hidden" name="action" value="award">
                <input type="hidden" name="badge_name" id="form_badge_name">
                <div id="selected_users_inputs">
                    <!-- Selected user IDs will be added here as hidden inputs -->
                </div>
                
                <div class="award-summary" id="award_summary">
                    <!-- Summary will be shown here -->
                </div>
                
                <button type="submit" class="btn btn-primary" id="award_submit_btn">
                    <i class="fas fa-award"></i> Award Badge to Selected Users
                </button>
            </form>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tabs;
            
            // Hide all tab contents
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            
            // Remove active class from all tabs
            tabs = document.getElementsByClassName("tab");
            for (i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            // Show the selected tab content and mark the button as active
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }

        // Handle new category input
        document.getElementById('category').addEventListener('change', function() {
            if (this.value === 'new') {
                var newCategory = prompt('Enter new category name:');
                if (newCategory) {
                    var option = new Option(newCategory, newCategory, true, true);
                    this.appendChild(option);
                }
            }
        });

        // Badge data for editing
        const badgeData = <?php echo json_encode($allBadges); ?>;
        console.log('All badge data loaded:', badgeData);

        // Load badge data for editing
        function loadBadgeForEdit(badgeName) {
            console.log('Loading badge for edit:', badgeName);
            const badge = badgeData[badgeName];
            console.log('Badge data:', badge);
            if (badge) {
                document.getElementById('edit_badge_id').value = badge.id;
                console.log('Setting badge_id to:', badge.id);
                document.getElementById('edit_badge_name').value = badge.name;
                document.getElementById('edit_description').value = badge.description;
                document.getElementById('edit_instructions').value = badge.instructions || '';
                document.getElementById('edit_icon_class').value = badge.icon;
                document.getElementById('edit_color').value = badge.color;
                document.getElementById('edit_category').value = badge.category;
                document.getElementById('edit_image_path').value = badge.image || '';
                
                // Handle existing image display
                const preview = document.getElementById('editBadgePreview');
                const imageContainer = preview.querySelector('.badge-image');
                const iconContainer = preview.querySelector('.badge-icon');
                const img = imageContainer.querySelector('img');
                const currentImageDisplay = document.getElementById('current_image_display');
                const currentImageName = document.getElementById('current_image_name');
                
                if (badge.image && badge.image.trim() !== '') {
                    // Check if image exists by trying to load it
                    const testImg = new Image();
                    testImg.onload = function() {
                        // Image exists, show it
                        img.src = badge.image;
                        imageContainer.style.display = 'block';
                        iconContainer.style.display = 'none';
                        preview.classList.add('has-image');
                        
                        // Show current image info
                        currentImageDisplay.style.display = 'block';
                        currentImageName.textContent = badge.image.split('/').pop();
                    };
                    testImg.onerror = function() {
                        // Image doesn't exist, show icon instead
                        imageContainer.style.display = 'none';
                        iconContainer.style.display = 'flex';
                        preview.classList.remove('has-image');
                        currentImageDisplay.style.display = 'none';
                        
                        // Set default icon if none specified
                        const iconElement = iconContainer.querySelector('i');
                        if (!badge.icon || badge.icon.trim() === '') {
                            iconElement.className = 'fas fa-medal';
                        }
                    };
                    testImg.src = badge.image;
                } else {
                    // No image, show icon
                    imageContainer.style.display = 'none';
                    iconContainer.style.display = 'flex';
                    preview.classList.remove('has-image');
                    currentImageDisplay.style.display = 'none';
                    
                    // Set default icon if none specified
                    const iconElement = iconContainer.querySelector('i');
                    if (!badge.icon || badge.icon.trim() === '') {
                        document.getElementById('edit_icon_class').value = 'fas fa-medal';
                        iconElement.className = 'fas fa-medal';
                    }
                }
                
                // Update edit preview
                updateEditBadgePreview();
                
                // Switch to edit tab
                openTab(event, 'editBadge');
                document.querySelector('.tab[onclick*="editBadge"]').classList.add('active');
                
                // Scroll to top of form
                document.getElementById('editBadgeForm').scrollIntoView({ behavior: 'smooth' });
            }
        }

        // Function to preview uploaded image
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const imageContainer = preview.querySelector('.badge-image');
            const iconContainer = preview.querySelector('.badge-icon');
            const img = imageContainer.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    imageContainer.style.display = 'block';
                    iconContainer.style.display = 'none';
                    preview.classList.add('has-image');
                };
                reader.readAsDataURL(input.files[0]);
            } else {
                imageContainer.style.display = 'none';
                iconContainer.style.display = 'flex';
                preview.classList.remove('has-image');
            }
        }

        // Function to update badge preview for Add Badge form
        function updateBadgePreview() {
            const name = document.getElementById('badge_name').value || 'Badge Name';
            const icon = document.getElementById('icon_class').value || 'fas fa-medal';
            const color = document.getElementById('color').value || '#4CAF50';
            
            const preview = document.getElementById('badgePreview');
            const iconElement = preview.querySelector('.badge-icon i');
            const nameElement = preview.querySelector('p');
            
            // Update icon - use default if empty
            iconElement.className = icon.trim() === '' ? 'fas fa-medal' : icon;
            
            // Update name
            nameElement.textContent = name;
            
            // Always update background color with gradient
            const darkerColor = darkenColor(color, 20);
            preview.style.background = `linear-gradient(135deg, ${color}, ${darkerColor})`;
            preview.style.boxShadow = `0 4px 15px ${color}4D`; // 30% opacity
        }

        // Function to update badge preview for Edit Badge form
        function updateEditBadgePreview() {
            const name = document.getElementById('edit_badge_name').value || 'Badge Name';
            const icon = document.getElementById('edit_icon_class').value || 'fas fa-medal';
            const color = document.getElementById('edit_color').value || '#4CAF50';
            
            const preview = document.getElementById('editBadgePreview');
            const iconElement = preview.querySelector('.badge-icon i');
            const nameElement = preview.querySelector('p');
            
            // Update icon - use default if empty
            iconElement.className = icon.trim() === '' ? 'fas fa-medal' : icon;
            
            // Update name
            nameElement.textContent = name;
            
            // Always update background color with gradient
            const darkerColor = darkenColor(color, 20);
            preview.style.background = `linear-gradient(135deg, ${color}, ${darkerColor})`;
            preview.style.boxShadow = `0 4px 15px ${color}4D`; // 30% opacity
        }

        // Helper function to darken a color
        function darkenColor(color, percent) {
            // Remove # if present
            color = color.replace('#', '');
            
            // Convert to RGB
            const r = parseInt(color.substr(0, 2), 16);
            const g = parseInt(color.substr(2, 2), 16);
            const b = parseInt(color.substr(4, 2), 16);
            
            // Darken
            const newR = Math.max(0, Math.floor(r * (100 - percent) / 100));
            const newG = Math.max(0, Math.floor(g * (100 - percent) / 100));
            const newB = Math.max(0, Math.floor(b * (100 - percent) / 100));
            
            // Convert back to hex
            return `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
        }

        // Initialize previews when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateBadgePreview();
            
            // Auto-hide flash message after 5 seconds
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(function() {
                    closeFlashMessage();
                }, 5000);
            }
        });

        // Function to close flash message
        function closeFlashMessage() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(function() {
                    flashMessage.style.display = 'none';
                }, 300);
            }
        }

        // Add slideOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    transform: translateY(0);
                    opacity: 1;
                }
                to {
                    transform: translateY(-20px);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Award Badge Functions
        let originalBadgeOptions = [];
        
        function filterBadgesByCategory() {
            const categorySelect = document.getElementById('category_filter');
            const badgeSelect = document.getElementById('selected_badge');
            const selectedCategory = categorySelect.value;
            
            // Clear badge selection
            badgeSelect.innerHTML = '<option value="">Choose a badge...</option>';
            
            // Reset user selection
            document.getElementById('user_selection_section').style.display = 'none';
            document.getElementById('award_form').style.display = 'none';
            
            // Filter badges by category
            originalBadgeOptions.forEach(option => {
                if (selectedCategory === '' || option.dataset.category === selectedCategory) {
                    const newOption = option.cloneNode(true);
                    badgeSelect.appendChild(newOption);
                }
            });
        }

        function loadEligibleUsers() {
            const badgeSelect = document.getElementById('selected_badge');
            const selectedBadge = badgeSelect.value;
            const userSection = document.getElementById('user_selection_section');
            const usersContainer = document.getElementById('eligible_users_container');
            
            if (!selectedBadge) {
                userSection.style.display = 'none';
                document.getElementById('award_form').style.display = 'none';
                return;
            }
            
            // Show loading
            usersContainer.innerHTML = '<div class="loading-spinner"></div><p>Loading eligible users...</p>';
            userSection.style.display = 'block';
            
            // Fetch users without this badge
            fetch('badge_ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_users_without_badge&badge_name=${encodeURIComponent(selectedBadge)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    usersContainer.innerHTML = `<div class="no-users-message">${data.error}</div>`;
                    return;
                }
                
                displayEligibleUsers(data.users, selectedBadge);
            })
            .catch(error => {
                console.error('Error:', error);
                usersContainer.innerHTML = '<div class="no-users-message">Error loading users</div>';
            });
        }

        function displayEligibleUsers(users, badgeName) {
            const usersContainer = document.getElementById('eligible_users_container');
            
            if (users.length === 0) {
                usersContainer.innerHTML = '<div class="no-users-message">All users already have this badge!</div>';
                return;
            }
            
            let html = '';
            users.forEach(user => {
                html += `
                    <div class="user-card" onclick="toggleUserSelection(this, ${user.account_id})">
                        <input type="checkbox" value="${user.account_id}" onclick="event.stopPropagation()" onchange="updateSelectedUsers()">
                        <div class="user-info">
                            <div class="user-name">${escapeHtml(user.fullname)}</div>
                            <div class="user-email">${escapeHtml(user.email)}</div>
                        </div>
                    </div>
                `;
            });
            
            usersContainer.innerHTML = html;
            
            // Reset search filter and button text
            document.getElementById('user_search').value = '';
            const selectAllBtn = document.querySelector('button[onclick="selectAllUsers()"]');
            const deselectAllBtn = document.querySelector('button[onclick="deselectAllUsers()"]');
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i> Select All';
            deselectAllBtn.innerHTML = '<i class="fas fa-square"></i> Deselect All';
            
            updateSelectedUsers();
        }

        function toggleUserSelection(card, userId) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
            
            updateSelectedUsers();
        }

        function selectAllUsers() {
            // Only select visible users (those not hidden by search filter)
            const visibleUserCards = document.querySelectorAll('#eligible_users_container .user-card:not([style*="display: none"])');
            visibleUserCards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
                card.classList.add('selected');
            });
            updateSelectedUsers();
        }

        function deselectAllUsers() {
            // Only deselect visible users (those not hidden by search filter)
            const visibleUserCards = document.querySelectorAll('#eligible_users_container .user-card:not([style*="display: none"])');
            visibleUserCards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
                card.classList.remove('selected');
            });
            updateSelectedUsers();
        }

        function clearHiddenSelections() {
            // Clear selections from hidden users
            const hiddenUserCards = document.querySelectorAll('#eligible_users_container .user-card[style*="display: none"]');
            hiddenUserCards.forEach(card => {
                const checkbox = card.querySelector('input[type="checkbox"]');
                if (checkbox.checked) {
                    checkbox.checked = false;
                    card.classList.remove('selected');
                }
            });
            updateSelectedUsers();
        }

        function filterUsers() {
            const searchTerm = document.getElementById('user_search').value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userName = card.querySelector('.user-name').textContent.toLowerCase();
                const userEmail = card.querySelector('.user-email').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Update the counter
            updateSelectedUsers();
        }

        function updateSelectedUsers() {
            // Count all checked checkboxes
            const allCheckedCheckboxes = document.querySelectorAll('#eligible_users_container input[type="checkbox"]:checked');
            // Only count and process checkboxes that are both checked AND visible
            const visibleCheckedCheckboxes = document.querySelectorAll('#eligible_users_container .user-card:not([style*="display: none"]) input[type="checkbox"]:checked');
            
            const totalSelectedCount = allCheckedCheckboxes.length;
            const visibleSelectedCount = visibleCheckedCheckboxes.length;
            const badgeName = document.getElementById('selected_badge').value;
            
            // Update counter with more informative text
            let counterText = `${visibleSelectedCount} selected users`;
            if (totalSelectedCount > visibleSelectedCount) {
                counterText += ` (${totalSelectedCount - visibleSelectedCount} more selected but hidden)`;
            }
            
            const counterElement = document.getElementById('selected_count');
            counterElement.textContent = counterText;
            
            // Add clear hidden button if there are hidden selections
            const existingClearBtn = document.getElementById('clear_hidden_btn');
            if (existingClearBtn) {
                existingClearBtn.remove();
            }
            
            if (totalSelectedCount > visibleSelectedCount) {
                const clearBtn = document.createElement('button');
                clearBtn.id = 'clear_hidden_btn';
                clearBtn.type = 'button';
                clearBtn.className = 'btn btn-secondary';
                clearBtn.style.marginLeft = '10px';
                clearBtn.style.fontSize = '12px';
                clearBtn.style.padding = '4px 8px';
                clearBtn.innerHTML = '<i class="fas fa-times"></i> Clear Hidden';
                clearBtn.onclick = clearHiddenSelections;
                counterElement.parentNode.appendChild(clearBtn);
            }
            
            // Update form - only process visible selections
            const form = document.getElementById('award_form');
            const selectedUsersInputs = document.getElementById('selected_users_inputs');
            const summaryDiv = document.getElementById('award_summary');
            const formBadgeName = document.getElementById('form_badge_name');
            
            if (visibleSelectedCount > 0 && badgeName) {
                // Clear previous inputs
                selectedUsersInputs.innerHTML = '';
                
                // Add only visible selected user IDs as hidden inputs
                visibleCheckedCheckboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'user_ids[]';
                    input.value = checkbox.value;
                    selectedUsersInputs.appendChild(input);
                });
                
                // Set badge name
                formBadgeName.value = badgeName;
                
                // Create summary
                let summaryHtml = `
                    <h4><i class="fas fa-info-circle"></i> Award Summary</h4>
                    <div class="summary-item">
                        <span><strong>Badge:</strong> ${escapeHtml(badgeName)}</span>
                    </div>
                    <div class="summary-item">
                        <span><strong>Users Selected:</strong> ${visibleSelectedCount}</span>
                    </div>
                `;
                
                summaryDiv.innerHTML = summaryHtml;
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // Initialize category filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Store original badge options for filtering
            const badgeSelect = document.getElementById('selected_badge');
            originalBadgeOptions = Array.from(badgeSelect.querySelectorAll('option[data-category]'));
        });
    </script>
</body>
</html>
