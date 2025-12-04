<?php
require_once 'database.php';

echo "=== MANUAL BADGE AWARDING FOR EXISTING RESOLVED REPORTS ===\n";

// Create badge_notifications table if it doesn't exist
$createTable = "CREATE TABLE IF NOT EXISTS badge_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_name VARCHAR(255) NOT NULL,
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_notification (user_id, badge_name)
)";
$connection->query($createTable);

// Define resolution badge milestones
$badges = [
    1 => 'First Resolution',
    5 => 'Alert Citizen', 
    10 => 'Community Protector',
    25 => 'Super Watchdog',
    50 => 'Vigilant Guardian',
    100 => 'Report Veteran'
];

// Get all users who have resolved reports
$userQuery = "SELECT reporter_id, COUNT(*) as resolved_count 
              FROM illegalreportstbl 
              WHERE action_type = 'Resolved' 
              GROUP BY reporter_id 
              ORDER BY resolved_count DESC";

$result = $connection->query($userQuery);

echo "\nFound " . $result->num_rows . " users with resolved reports\n\n";

while ($row = $result->fetch_assoc()) {
    $reporter_id = $row['reporter_id'];
    $resolved_count = $row['resolved_count'];
    
    echo "User ID $reporter_id: $resolved_count resolved reports\n";
    
    // Get current user badges
    $userBadgeQuery = "SELECT badges, badge_count FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($userBadgeQuery);
    $stmt->bind_param("i", $reporter_id);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        echo "  - User not found in accountstbl\n";
        $stmt->close();
        continue;
    }
    
    $user = $userResult->fetch_assoc();
    $currentBadges = $user['badges'] ?? '';
    $currentBadgeCount = $user['badge_count'] ?? 0;
    $stmt->close();
    
    // Determine which badges user should have
    $badgesToAward = [];
    foreach ($badges as $milestone => $badgeName) {
        if ($resolved_count >= $milestone) {
            // Check if user already has this badge
            if (strpos($currentBadges, $badgeName) === false) {
                $badgesToAward[] = $badgeName;
            }
        }
    }
    
    if (!empty($badgesToAward)) {
        echo "  - Awarding badges: " . implode(', ', $badgesToAward) . "\n";
        
        // Add new badges to existing ones
        $newBadges = empty($currentBadges) ? implode(',', $badgesToAward) : $currentBadges . ',' . implode(',', $badgesToAward);
        $newBadgeCount = $currentBadgeCount + count($badgesToAward);
        
        // Update user's badges
        $updateQuery = "UPDATE accountstbl SET badges = ?, badge_count = ? WHERE account_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("sii", $newBadges, $newBadgeCount, $reporter_id);
        
        if ($stmt->execute()) {
            echo "  - ✅ Updated user badges successfully\n";
            
            // Log each badge notification
            foreach ($badgesToAward as $badge) {
                $notifQuery = "INSERT INTO badge_notifications (user_id, badge_name) VALUES (?, ?) ON DUPLICATE KEY UPDATE badge_name = badge_name";
                $notifStmt = $connection->prepare($notifQuery);
                $notifStmt->bind_param("is", $reporter_id, $badge);
                $notifStmt->execute();
                $notifStmt->close();
            }
        } else {
            echo "  - ❌ Failed to update badges\n";
        }
        $stmt->close();
    } else {
        echo "  - Already has all appropriate badges\n";
    }
    
    echo "\n";
}

echo "=== BADGE AWARDING COMPLETE ===\n";
?>
