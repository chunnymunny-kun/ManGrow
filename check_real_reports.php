
<?php
require_once 'database.php';
require_once 'eco_points_integration.php';

echo "Testing eco points system with real data...
";

try {
    // First, let's see if there are any resolved reports
    $checkReports = "SELECT report_id, reporter_id, action_type, priority, points_awarded FROM illegalreportstbl WHERE action_type = 'Resolved' LIMIT 5";
    $result = $connection->query($checkReports);
    
    echo "Found " . $result->num_rows . " resolved reports.
";
    
    if ($result->num_rows > 0) {
        while ($report = $result->fetch_assoc()) {
            echo "Report ID: " . $report['report_id'] . 
                 ", Reporter: " . $report['reporter_id'] . 
                 ", Priority: " . $report['priority'] . 
                 ", Points Awarded: " . ($report['points_awarded'] ?? 'NULL') . "
";
        }
        
        // Test with the first resolved report
        $result->data_seek(0);
        $testReport = $result->fetch_assoc();
        $reportId = $testReport['report_id'];
        $adminId = 1; // Test admin ID
        $rating = 5;
        
        echo "
Testing eco points award for report ID: $reportId with rating: $rating
";
        
        // Check if eco points table exists and has the correct structure
        $checkEnum = "SHOW COLUMNS FROM eco_points_transactions WHERE Field = 'activity_type'";
        $enumResult = $connection->query($checkEnum);
        
        if ($enumResult && $enumResult->num_rows > 0) {
            $column = $enumResult->fetch_assoc();
            echo "Current ENUM values: " . $column['Type'] . "
";
            
            if (strpos($column['Type'], 'organization_join') === false) {
                echo "Missing 'organization_join' in ENUM - this is the problem!
";
                echo "Fixing ENUM...
";
                
                $fixEnum = "ALTER TABLE eco_points_transactions 
                           MODIFY COLUMN activity_type ENUM('event_attendance', 'report_resolved', 'daily_login', 'referral', 'badge_bonus', 'shop_purchase', 'admin_adjustment', 'organization_join') NOT NULL";
                
                if ($connection->query($fixEnum)) {
                    echo "ENUM fixed successfully!
";
                } else {
                    echo "Failed to fix ENUM: " . $connection->error . "
";
                }
            } else {
                echo "ENUM already contains 'organization_join'
";
            }
        }
        
        // Now test the award function
        $awardResult = awardReportResolutionPointsWithRating($reportId, $adminId, $rating);
        echo "Award result: " . json_encode($awardResult) . "
";
        
    } else {
        echo "No resolved reports found to test with.
";
        
        // Let's check if there are any reports at all
        $allReports = "SELECT COUNT(*) as total FROM illegalreportstbl";
        $totalResult = $connection->query($allReports);
        $total = $totalResult->fetch_assoc()['total'];
        echo "Total reports in database: " . $total . "
";
        
        if ($total > 0) {
            // Get a sample report to work with
            $sampleReport = "SELECT report_id, reporter_id, action_type, priority FROM illegalreportstbl LIMIT 1";
            $sampleResult = $connection->query($sampleReport);
            $sample = $sampleResult->fetch_assoc();
            
            echo "Sample report - ID: " . $sample['report_id'] . ", Status: " . $sample['action_type'] . "
";
            echo "You can test by marking this report as 'Resolved' in the admin panel.
";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "
";
}
?>
