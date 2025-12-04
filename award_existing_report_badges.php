<?php
/**
 * Script to award badges to existing users who have resolved reports
 * Run this once to grant badges to users who submitted reports before the badge system was implemented
 */

require_once 'database.php';
require_once 'badge_system.php';

echo "Starting badge award process for existing users...\n";

// Get all users who have resolved reports
$query = "SELECT 
            reporter_id,
            COUNT(*) as report_count
          FROM illegalreportstbl 
          WHERE reporter_id IS NOT NULL 
            AND action_type = 'Resolved'
          GROUP BY reporter_id
          ORDER BY report_count DESC";

$result = $connection->query($query);

$usersProcessed = 0;
$badgesAwarded = 0;

while ($row = $result->fetch_assoc()) {
    $userId = $row['reporter_id'];
    $reportCount = $row['report_count'];
    
    // Get current user badges
    $userQuery = "SELECT badges, fullname FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        continue; // Skip if user doesn't exist
    }
    
    $userData = $userResult->fetch_assoc();
    $userBadges = $userData['badges'] ?? '';
    $currentBadges = array_filter(explode(',', $userBadges));
    
    $newBadges = [];
    
    // Determine which badges to award based on report count
    if ($reportCount >= 50 && !in_array('Mangrove Legend', $currentBadges)) {
        $newBadges[] = 'Mangrove Legend';
    }
    if ($reportCount >= 25 && !in_array('Ecosystem Sentinel', $currentBadges)) {
        $newBadges[] = 'Ecosystem Sentinel';
    }
    if ($reportCount >= 15 && !in_array('Conservation Champion', $currentBadges)) {
        $newBadges[] = 'Conservation Champion';
    }
    if ($reportCount >= 5 && !in_array('Vigilant Protector', $currentBadges)) {
        $newBadges[] = 'Vigilant Protector';
    }
    if ($reportCount >= 1 && !in_array('Watchful Eye', $currentBadges)) {
        $newBadges[] = 'Watchful Eye';
    }
    
    if (!empty($newBadges)) {
        // Add new badges to existing badges
        $allBadges = array_merge($currentBadges, $newBadges);
        $allBadges = array_unique($allBadges); // Remove duplicates
        $newBadgesString = implode(',', array_filter($allBadges));
        
        // Update user badges
        $updateQuery = "UPDATE accountstbl SET badges = ? WHERE account_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("si", $newBadgesString, $userId);
        $stmt->execute();
        
        $userName = $userData['fullname'] ?? "User $userId";
        echo "User: $userName (ID: $userId) - Reports: $reportCount - Awarded: " . implode(', ', $newBadges) . "\n";
        $badgesAwarded += count($newBadges);
    }
    
    $usersProcessed++;
}

echo "\nBadge award process completed!\n";
echo "Users processed: $usersProcessed\n";
echo "Total badges awarded: $badgesAwarded\n";

$connection->close();
?>
