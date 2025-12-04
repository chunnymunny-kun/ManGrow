<?php
/**
 * Script to update existing resolved reports with points awarded
 * This updates reports that were resolved before the points system was implemented
 */

require_once 'database.php';
require_once 'eco_points_system.php';

echo "Starting points update for existing resolved reports...\n";

// Initialize the eco points system
EcoPointsSystem::init($connection, [
    'event_base_points' => 50,
    'report_base_points' => 25,
    'daily_login_points' => 5,
    'referral_points' => 50,
    'badge_bonus_percentage' => 15,
    'max_daily_points' => 500,
    'min_account_age_days' => 1
]);

// Get all resolved reports that don't have points awarded yet
$query = "SELECT report_id, reporter_id, priority, action_type 
          FROM illegalreportstbl 
          WHERE action_type = 'Resolved' 
            AND (points_awarded IS NULL OR points_awarded = 0)
            AND reporter_id IS NOT NULL
          ORDER BY report_id";

$result = $connection->query($query);

$reportsProcessed = 0;
$pointsAwarded = 0;

while ($row = $result->fetch_assoc()) {
    $reportId = $row['report_id'];
    $reporterId = $row['reporter_id'];
    $priority = $row['priority'] ?? 'Normal';
    
    // Calculate points based on priority
    $pointMultiplier = [
        'Emergency' => 2.0,
        'Normal' => 1.0
    ];
    
    $basePoints = 25; // report_base_points
    $points = (int)($basePoints * ($pointMultiplier[$priority] ?? 1.0));
    
    // Update the report with points
    $updateQuery = "UPDATE illegalreportstbl SET points_awarded = ? WHERE report_id = ?";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("ii", $points, $reportId);
    $stmt->execute();
    
    echo "Report ID: $reportId - Reporter: $reporterId - Priority: $priority - Points: $points\n";
    $reportsProcessed++;
    $pointsAwarded += $points;
}

echo "\nPoints update completed!\n";
echo "Reports processed: $reportsProcessed\n";
echo "Total points assigned: $pointsAwarded\n";

$connection->close();
?>
