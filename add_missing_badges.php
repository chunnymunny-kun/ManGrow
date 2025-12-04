<?php
require_once 'database.php';
require_once 'badge_system.php';

// Get all badges that should exist
$badges = BadgeSystem::getAllBadges();

// Get existing badges from database
$existingQuery = "SELECT badge_name FROM badgestbl";
$result = $connection->query($existingQuery);
$existingBadges = [];
while($row = $result->fetch_assoc()) {
    $existingBadges[] = $row['badge_name'];
}

echo "Adding missing badges to database...\n";

foreach($badges as $badgeName => $badge) {
    if (!in_array($badgeName, $existingBadges)) {
        echo "Adding: $badgeName\n";
        
        // Default values for missing badges
        $category = $badge['category'] ?? 'Achievement';
        $description = $badge['description'] ?? "Congratulations on earning the $badgeName badge!";
        $icon = $badge['icon'] ?? 'fas fa-star';
        $color = $badge['color'] ?? '#3498db';
        
        $insertQuery = "INSERT INTO badgestbl (badge_name, category, description, icon_class, color, is_active, created_at) 
                       VALUES (?, ?, ?, ?, ?, 1, NOW())";
        $stmt = $connection->prepare($insertQuery);
        $stmt->bind_param("sssss", $badgeName, $category, $description, $icon, $color);
        
        if($stmt->execute()) {
            echo "✅ Added: $badgeName\n";
        } else {
            echo "❌ Failed to add: $badgeName\n";
        }
        $stmt->close();
    }
}

echo "Done!\n";
?>
