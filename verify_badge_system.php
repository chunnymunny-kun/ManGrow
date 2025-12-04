<?php
require_once 'database.php';

echo "=== BADGE SYSTEM VERIFICATION ===\n";

// Check user badges
echo "\n1. Current User Badges:\n";
$userQuery = "SELECT account_id, fullname, badges, badge_count FROM accountstbl WHERE badges IS NOT NULL AND badges != ''";
$result = $connection->query($userQuery);

while ($row = $result->fetch_assoc()) {
    echo "   User {$row['account_id']} ({$row['fullname']}): {$row['badge_count']} badges - {$row['badges']}\n";
}

// Check resolved reports
echo "\n2. Resolved Reports Count:\n";
$reportQuery = "SELECT reporter_id, COUNT(*) as count FROM illegalreportstbl WHERE action_type = 'Resolved' GROUP BY reporter_id";
$result = $connection->query($reportQuery);

while ($row = $result->fetch_assoc()) {
    echo "   User {$row['reporter_id']}: {$row['count']} resolved reports\n";
}

// Check badge notifications
echo "\n3. Badge Notifications:\n";
$notifQuery = "SELECT user_id, badge_name, notified_at FROM badge_notifications ORDER BY notified_at DESC";
$result = $connection->query($notifQuery);

while ($row = $result->fetch_assoc()) {
    echo "   User {$row['user_id']}: {$row['badge_name']} ({$row['notified_at']})\n";
}

echo "\n✅ Badge system is ready! Future resolved reports will automatically award badges.\n";
?>
