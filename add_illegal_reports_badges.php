<?php
/**
 * Script to add new badges for illegal activity reports
 * Run this once to add the new report-related badges
 */

include 'database.php';
include 'badge_system_db.php';

// Initialize badge system
BadgeSystem::init($connection);

// New badges for resolved reports
$resolvedReportsBadges = [
    [
        'name' => 'First Resolution',
        'description' => 'Your first report has been resolved successfully!',
        'instructions' => 'Submit a report that gets resolved by authorities',
        'icon' => 'fas fa-check-circle',
        'color' => '#28a745',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Watchful Eye',
        'description' => 'You have 5 reports that were successfully resolved',
        'instructions' => 'Submit 5 reports that get resolved by authorities',
        'icon' => 'fas fa-eye',
        'color' => '#17a2b8',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Alert Citizen',
        'description' => 'You have 10 reports that were successfully resolved',
        'instructions' => 'Submit 10 reports that get resolved by authorities',
        'icon' => 'fas fa-shield-alt',
        'color' => '#fd7e14',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Community Protector',
        'description' => 'You have 20 reports that were successfully resolved',
        'instructions' => 'Submit 20 reports that get resolved by authorities',
        'icon' => 'fas fa-user-shield',
        'color' => '#6f42c1',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Super Watchdog',
        'description' => 'You have 50 reports that were successfully resolved',
        'instructions' => 'Submit 50 reports that get resolved by authorities',
        'icon' => 'fas fa-medal',
        'color' => '#e83e8c',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Vigilant Guardian',
        'description' => 'You have 100 reports that were successfully resolved',
        'instructions' => 'Submit 100 reports that get resolved by authorities',
        'icon' => 'fas fa-crown',
        'color' => '#ffc107',
        'category' => 'Reporting'
    ]
];

// New badges for submission count
$submissionBadges = [
    [
        'name' => 'Report Veteran',
        'description' => 'You have submitted 5 illegal activity reports',
        'instructions' => 'Submit 5 illegal activity reports',
        'icon' => 'fas fa-flag',
        'color' => '#20c997',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Active Citizen',
        'description' => 'You have submitted 10 illegal activity reports',
        'instructions' => 'Submit 10 illegal activity reports',
        'icon' => 'fas fa-user-check',
        'color' => '#17a2b8',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Dedicated Reporter',
        'description' => 'You have submitted 20 illegal activity reports',
        'instructions' => 'Submit 20 illegal activity reports',
        'icon' => 'fas fa-clipboard-check',
        'color' => '#6610f2',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Environmental Crusader',
        'description' => 'You have submitted 50 illegal activity reports',
        'instructions' => 'Submit 50 illegal activity reports',
        'icon' => 'fas fa-fist-raised',
        'color' => '#e83e8c',
        'category' => 'Reporting'
    ],
    [
        'name' => 'Report Master',
        'description' => 'You have submitted 100 illegal activity reports',
        'instructions' => 'Submit 100 illegal activity reports',
        'icon' => 'fas fa-trophy',
        'color' => '#ffc107',
        'category' => 'Reporting'
    ]
];

$allBadges = array_merge($resolvedReportsBadges, $submissionBadges);

echo "<h2>Adding New Illegal Activity Report Badges</h2>";

foreach ($allBadges as $badge) {
    try {
        // Check if badge already exists
        $checkQuery = "SELECT badge_id FROM badgestbl WHERE badge_name = ?";
        $checkStmt = $connection->prepare($checkQuery);
        $checkStmt->bind_param("s", $badge['name']);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            echo "ℹ️ Badge '{$badge['name']}' already exists, skipping...<br>";
            continue;
        }
        
        // Insert new badge
        $insertQuery = "INSERT INTO badgestbl (badge_name, description, instructions, icon_class, color, category, is_active, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";
        $insertStmt = $connection->prepare($insertQuery);
        $insertStmt->bind_param("ssssss", 
            $badge['name'], 
            $badge['description'], 
            $badge['instructions'], 
            $badge['icon'], 
            $badge['color'], 
            $badge['category']
        );
        
        if ($insertStmt->execute()) {
            echo "✅ Added badge: {$badge['name']}<br>";
        } else {
            echo "❌ Failed to add badge: {$badge['name']} - " . $connection->error . "<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error adding badge '{$badge['name']}': " . $e->getMessage() . "<br>";
    }
}

echo "<br>🎉 Badge addition completed!<br>";
echo "<br>📋 Summary:<br>";
echo "- Added " . count($resolvedReportsBadges) . " badges for resolved reports<br>";
echo "- Added " . count($submissionBadges) . " badges for submission count<br>";
echo "- Total: " . count($allBadges) . " new badges<br>";

$connection->close();
?>
