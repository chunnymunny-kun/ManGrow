<?php
// save_report_notification.php
session_start();
include 'database.php';

// Helper function to get badge descriptions
function getBadgeDescription($badgeName) {
    $descriptions = [
        'First Resolution' => 'Congratulations! You helped resolve your first illegal activity report.',
        'Alert Citizen' => 'Excellent work! You\'ve helped resolve 5 illegal activity reports.',
        'Community Protector' => 'Outstanding! You\'ve contributed to resolving 10 illegal activity reports.',
        'Super Watchdog' => 'Amazing dedication! You\'ve helped resolve 25 illegal activity reports.',
        'Vigilant Guardian' => 'Incredible commitment! You\'ve contributed to resolving 50 illegal activity reports.',
        'Report Veteran' => 'Legendary achievement! You\'ve helped resolve 100 illegal activity reports.'
    ];
    
    return isset($descriptions[$badgeName]) ? $descriptions[$badgeName] : 'Congratulations on earning this badge!';
}

// Check if user is logged in and has proper permissions
if (!isset($_SESSION["accessrole"]) || 
    ($_SESSION["accessrole"] != 'Barangay Official' && 
     $_SESSION["accessrole"] != 'Administrator' && 
     $_SESSION["accessrole"] != 'Representative')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if this is a file upload request (multipart/form-data) or JSON
$isFileUpload = isset($_FILES['admin_attachments']);

if ($isFileUpload) {
    // Get data from POST parameters instead of JSON
    $report_id = $connection->real_escape_string($_POST['report_id']);
    $report_type = $connection->real_escape_string($_POST['report_type']);
    $action_type = $connection->real_escape_string($_POST['action_type']);
    $notif_description = $connection->real_escape_string($_POST['notif_description']);
    $admin_notif_description = isset($_POST['admin_notif_description']) ? 
        $connection->real_escape_string($_POST['admin_notif_description']) : '';
    $account_id = isset($_POST['account_id']) ? $connection->real_escape_string($_POST['account_id']) : null;
    $notifier_type = isset($_POST['notifier_type']) ? $connection->real_escape_string($_POST['notifier_type']) : 'adminaccountstbl';
} else {
    // Get the JSON data from the request (legacy support)
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['report_id']) || !isset($input['report_type']) || 
        !isset($input['action_type']) || !isset($input['notif_description'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }
    
    $report_id = $connection->real_escape_string($input['report_id']);
    $report_type = $connection->real_escape_string($input['report_type']);
    $action_type = $connection->real_escape_string($input['action_type']);
    $notif_description = $connection->real_escape_string($input['notif_description']);
    $admin_notif_description = isset($input['admin_notif_description']) ? 
        $connection->real_escape_string($input['admin_notif_description']) : '';
    $account_id = isset($input['account_id']) ? $connection->real_escape_string($input['account_id']) : null;
    $notifier_type = isset($input['notifier_type']) ? $connection->real_escape_string($input['notifier_type']) : 'adminaccountstbl';
}

// Get the user ID from session
$notified_by = isset($_SESSION['user_id']) ? $connection->real_escape_string($_SESSION['user_id']) : null;

// Handle file uploads if present
$uploadedFiles = [];
$attachmentCount = 0;

if ($isFileUpload && isset($_FILES['admin_attachments'])) {
    $files = $_FILES['admin_attachments'];
    $fileCount = count($files['name']);
    
    // Determine subdirectory based on action_type
    $statusFolder = strtolower(str_replace(' ', '_', $action_type));
    $uploadDir = "uploads/report_admin_attachments/{$statusFolder}/";
    
    // Create directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Process each file
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileName = $files['name'][$i];
            $fileTmpName = $files['tmp_name'][$i];
            $fileSize = $files['size'][$i];
            $fileType = $files['type'][$i];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 
                           'video/mp4', 'video/avi', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'];
            
            if (!in_array($fileType, $allowedTypes)) {
                continue; // Skip invalid files
            }
            
            // Validate file size (10MB max)
            if ($fileSize > 10 * 1024 * 1024) {
                continue; // Skip files over 10MB
            }
            
            // Generate unique filename
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $uniqueFileName = 'admin_' . $report_id . '_' . time() . '_' . $i . '.' . $fileExtension;
            $filePath = $uploadDir . $uniqueFileName;
            
            // Move uploaded file
            if (move_uploaded_file($fileTmpName, $filePath)) {
                $uploadedFiles[] = $filePath;
                $attachmentCount++;
            }
        }
    }
}

try {
    // Set timezone to Philippines
    date_default_timezone_set('Asia/Manila');
    $notif_date = date('Y-m-d H:i:s');
    
    // Prepare admin attachments JSON
    $adminAttachmentsJson = !empty($uploadedFiles) ? json_encode($uploadedFiles) : NULL;
    
    // Insert notification with notif_date, notified_by, and admin_attachments
    $query = "INSERT INTO report_notifstbl 
              (report_id, report_type, account_id, action_type, notif_description, 
               admin_notif_description, notifier_type, notif_date, notified_by, 
               admin_attachments, attachment_count) 
              VALUES ('$report_id', '$report_type', " . 
              ($account_id ? "'$account_id'" : "NULL") . ", 
              '$action_type', '$notif_description', '$admin_notif_description', 
              '$notifier_type', '$notif_date', " . 
              ($notified_by ? "'$notified_by'" : "NULL") . ", " .
              ($adminAttachmentsJson ? "'" . $connection->real_escape_string($adminAttachmentsJson) . "'" : "NULL") . ", 
              $attachmentCount)";
    
    if ($connection->query($query)) {
        // Update the corresponding report table based on report type
        // Always update the 'status' field, not 'follow_up_status'
        if ($report_type === 'Mangrove Data Report') {
            $update_query = "UPDATE mangrovereporttbl 
                            SET action_type = '$action_type', 
                                rejection_timestamp = " . ($action_type === 'Rejected' ? "NOW()" : "NULL") . "
                            WHERE report_id = '$report_id'";
        } else if ($report_type === 'Illegal Activity Report') {
            $update_query = "UPDATE illegalreportstbl 
                            SET action_type = '$action_type', 
                                rejection_timestamp = " . ($action_type === 'Rejected' ? "NOW()" : "NULL") . "
                            WHERE report_id = '$report_id'";
        } else {
            throw new Exception("Invalid report type");
        }
        
        if ($connection->query($update_query)) {
            // Award badges for resolved illegal activity reports
            if ($action_type === 'Resolved' && $report_type === 'Illegal Activity Report') {
                awardResolutionBadges($connection, $report_id);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Status updated successfully',
                    'requires_rating' => true,
                    'report_id' => $report_id
                ]);
            } else {
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            }
        } else {
            throw new Exception("Failed to update report status: " . $connection->error);
        }
    } else {
        throw new Exception("Failed to save notification: " . $connection->error);
    }
} catch (Exception $e) {
    error_log("Error saving notification: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Award resolution badges based on resolved report count
 */
function awardResolutionBadges($connection, $report_id) {
    // Get reporter ID from the resolved report
    $reportQuery = "SELECT reporter_id FROM illegalreportstbl WHERE report_id = ?";
    $stmt = $connection->prepare($reportQuery);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return;
    }
    
    $reporter_id = $result->fetch_assoc()['reporter_id'];
    $stmt->close();
    
    // Count total resolved reports for this user
    $countQuery = "SELECT COUNT(*) as resolved_count FROM illegalreportstbl WHERE reporter_id = ? AND action_type = 'Resolved'";
    $stmt = $connection->prepare($countQuery);
    $stmt->bind_param("i", $reporter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $resolved_count = $result->fetch_assoc()['resolved_count'];
    $stmt->close();
    
    // Define resolution badge milestones
    $badges = [
        1 => 'First Resolution',
        5 => 'Alert Citizen', 
        10 => 'Community Protector',
        25 => 'Super Watchdog',
        50 => 'Vigilant Guardian',
        100 => 'Report Veteran'
    ];
    
    // Check which badge to award
    $badgeToAward = null;
    foreach ($badges as $milestone => $badgeName) {
        if ($resolved_count == $milestone) {
            $badgeToAward = $badgeName;
            break;
        }
    }
    
    if ($badgeToAward) {
        // Check if user already has this badge
        $userQuery = "SELECT badges, badge_count FROM accountstbl WHERE account_id = ?";
        $stmt = $connection->prepare($userQuery);
        $stmt->bind_param("i", $reporter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $currentBadges = $user['badges'] ?? '';
        $currentBadgeCount = $user['badge_count'] ?? 0;
        
        // Check if badge already awarded
        if (strpos($currentBadges, $badgeToAward) === false) {
            // Add badge to user's collection
            $newBadges = empty($currentBadges) ? $badgeToAward : $currentBadges . ',' . $badgeToAward;
            $newBadgeCount = $currentBadgeCount + 1;
            
            // Update user's badges
            $updateQuery = "UPDATE accountstbl SET badges = ?, badge_count = ? WHERE account_id = ?";
            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param("sii", $newBadges, $newBadgeCount, $reporter_id);
            $stmt->execute();
            $stmt->close();
            
            // Log badge notification
            $notifQuery = "INSERT INTO badge_notifications (user_id, badge_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE badge_name = badge_name";
            $stmt = $connection->prepare($notifQuery);
            $stmt->bind_param("is", $reporter_id, $badgeToAward);
            $stmt->execute();
            $stmt->close();
            
            // Set session flag for immediate badge notification if this is the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $reporter_id) {
                $_SESSION['new_badge_awarded'] = [
                    'badge_awarded' => true,
                    'badge_name' => $badgeToAward,
                    'badge_description' => getBadgeDescription($badgeToAward)
                ];
            }
        }
    }
}
?>