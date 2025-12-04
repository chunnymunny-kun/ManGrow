<?php
session_start();
include 'database.php';
include 'badge_system_db.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Initialize badge system
BadgeSystem::init($connection);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_users_without_badge':
            $badgeName = $_POST['badge_name'] ?? '';
            if (empty($badgeName)) {
                echo json_encode(['error' => 'Badge name is required']);
                exit();
            }
            
            $users = BadgeSystem::getUsersWithoutBadge($badgeName);
            echo json_encode(['users' => $users]);
            break;
            
        case 'get_badges_by_category':
            $category = $_POST['category'] ?? '';
            if (empty($category)) {
                echo json_encode(['error' => 'Category is required']);
                exit();
            }
            
            $badges = BadgeSystem::getBadgesByCategory($category);
            echo json_encode(['badges' => $badges]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
