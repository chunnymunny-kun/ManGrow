<?php
session_start();
include 'database.php';
include 'badge_system_db.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Unauthorized access'
    ];
    header("Location: admin_badges.php");
    exit();
}

// Initialize badge system
BadgeSystem::init($connection);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'deactivate':
            $result = BadgeSystem::deactivateBadge($_POST['badge_id']);
            if ($result) {
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => 'Badge deactivated successfully!'
                ];
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'error',
                    'message' => 'Error deactivating badge.'
                ];
            }
            break;
            
        case 'award':
            if (isset($_POST['user_ids']) && is_array($_POST['user_ids'])) {
                // Batch awarding
                $result = BadgeSystem::awardBadgeToMultipleUsers($_POST['user_ids'], $_POST['badge_name']);
                if ($result && $result['success'] > 0) {
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => "Badge awarded successfully to {$result['success']} out of {$result['total']} users!"
                    ];
                } else {
                    $_SESSION['flash_message'] = [
                        'type' => 'error',
                        'message' => 'Error awarding badge to users.'
                    ];
                }
            } else {
                // Single user awarding
                $result = BadgeSystem::awardBadgeToUser($_POST['user_id'], $_POST['badge_name']);
                if ($result) {
                    $_SESSION['flash_message'] = [
                        'type' => 'success',
                        'message' => 'Badge awarded successfully!'
                    ];
                } else {
                    $_SESSION['flash_message'] = [
                        'type' => 'error',
                        'message' => 'Error awarding badge.'
                    ];
                }
            }
            break;
            
        default:
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Invalid action.'
            ];
            break;
    }
} else {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Invalid request.'
    ];
}

header("Location: admin_badges.php");
exit();
?>
