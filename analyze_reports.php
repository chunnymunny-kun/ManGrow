<?php
/**
 * Find and analyze reports for testing the anonymous rating system
 */

require_once 'database.php';

echo "=== Report Analysis for Anonymous Rating Testing ===\n\n";

// Check illegalreportstbl structure
echo "📊 Analyzing report data...\n\n";

// Count total reports
$totalQuery = "SELECT COUNT(*) as total FROM illegalreportstbl";
$totalResult = $connection->query($totalQuery);
$total = $totalResult->fetch_assoc()['total'];

// Count by reporter_id status
$anonymousQuery = "SELECT COUNT(*) as count FROM illegalreportstbl WHERE reporter_id = 0 OR reporter_id IS NULL";
$anonymousResult = $connection->query($anonymousQuery);
$anonymousCount = $anonymousResult->fetch_assoc()['count'];

$namedQuery = "SELECT COUNT(*) as count FROM illegalreportstbl WHERE reporter_id > 0";
$namedResult = $connection->query($namedQuery);
$namedCount = $namedResult->fetch_assoc()['count'];

echo "📈 Report Statistics:\n";
echo "   - Total reports: $total\n";
echo "   - Anonymous reports (reporter_id = 0/NULL): $anonymousCount\n";
echo "   - Named reports (reporter_id > 0): $namedCount\n\n";

// Count by status
$statusQuery = "SELECT action_type, COUNT(*) as count FROM illegalreportstbl GROUP BY action_type";
$statusResult = $connection->query($statusQuery);

echo "📋 Reports by Status:\n";
while ($row = $statusResult->fetch_assoc()) {
    echo "   - " . $row['action_type'] . ": " . $row['count'] . "\n";
}

// Count unrated resolved reports
$unreatedQuery = "SELECT COUNT(*) as count FROM illegalreportstbl WHERE action_type = 'Resolved' AND (rating IS NULL OR rating = 0)";
$unreatedResult = $connection->query($unreatedQuery);
$unreatedCount = $unreatedResult->fetch_assoc()['count'];

echo "\n⭐ Unrated resolved reports: $unreatedCount\n\n";

// Find some specific examples
echo "🔍 Sample Reports:\n\n";

// Anonymous unrated resolved reports
$sampleAnonymousQuery = "SELECT report_id, priority, action_type, rating FROM illegalreportstbl 
                        WHERE (reporter_id = 0 OR reporter_id IS NULL) 
                        AND action_type = 'Resolved' 
                        AND (rating IS NULL OR rating = 0) 
                        LIMIT 3";
$sampleAnonymousResult = $connection->query($sampleAnonymousQuery);

echo "📝 Anonymous Unrated Resolved Reports:\n";
if ($sampleAnonymousResult && $sampleAnonymousResult->num_rows > 0) {
    while ($row = $sampleAnonymousResult->fetch_assoc()) {
        echo "   - Report ID: {$row['report_id']}, Priority: {$row['priority']}, Status: {$row['action_type']}, Rating: " . ($row['rating'] ?? 'NULL') . "\n";
        
        // Check if this report exists in userreportstbl
        $userCheckQuery = "SELECT account_id FROM userreportstbl WHERE report_id = ? AND report_type = 'Illegal Activity Report'";
        $userStmt = $connection->prepare($userCheckQuery);
        $userStmt->bind_param("i", $row['report_id']);
        $userStmt->execute();
        $userCheckResult = $userStmt->get_result();
        
        if ($userCheckResult->num_rows > 0) {
            $userRow = $userCheckResult->fetch_assoc();
            echo "     → User ID in userreportstbl: {$userRow['account_id']}\n";
        } else {
            echo "     → ❌ No corresponding entry in userreportstbl\n";
        }
    }
} else {
    echo "   - No anonymous unrated resolved reports found\n";
}

// Named unrated resolved reports
$sampleNamedQuery = "SELECT report_id, reporter_id, priority, action_type, rating FROM illegalreportstbl 
                    WHERE reporter_id > 0 
                    AND action_type = 'Resolved' 
                    AND (rating IS NULL OR rating = 0) 
                    LIMIT 3";
$sampleNamedResult = $connection->query($sampleNamedQuery);

echo "\n📝 Named Unrated Resolved Reports:\n";
if ($sampleNamedResult && $sampleNamedResult->num_rows > 0) {
    while ($row = $sampleNamedResult->fetch_assoc()) {
        echo "   - Report ID: {$row['report_id']}, Reporter ID: {$row['reporter_id']}, Priority: {$row['priority']}, Status: {$row['action_type']}, Rating: " . ($row['rating'] ?? 'NULL') . "\n";
    }
} else {
    echo "   - No named unrated resolved reports found\n";
}

// Check userreportstbl for Illegal Activity Reports
echo "\n🔗 Checking userreportstbl for Illegal Activity Reports:\n";
$userReportsQuery = "SELECT COUNT(*) as count FROM userreportstbl WHERE report_type = 'Illegal Activity Report'";
$userReportsResult = $connection->query($userReportsQuery);
$userReportsCount = $userReportsResult->fetch_assoc()['count'];
echo "   - Total user reports with type 'Illegal Activity Report': $userReportsCount\n";

// Sample user reports
$sampleUserQuery = "SELECT report_id, account_id FROM userreportstbl WHERE report_type = 'Illegal Activity Report' LIMIT 5";
$sampleUserResult = $connection->query($sampleUserQuery);

echo "   - Sample entries:\n";
while ($row = $sampleUserResult->fetch_assoc()) {
    echo "     → Report ID: {$row['report_id']}, Account ID: {$row['account_id']}\n";
}

echo "\n=== Recommendations ===\n";
echo "To test the anonymous rating system:\n";
echo "1. Create a test anonymous report (reporter_id = 0) with action_type = 'Resolved'\n";
echo "2. Ensure there's a corresponding entry in userreportstbl with report_type = 'Illegal Activity Report'\n";
echo "3. Then test the rating system on that report\n";

$connection->close();
?>