
<?php
require_once 'database.php';
require_once 'eco_points_integration.php';

echo "Testing eco points system for report resolution...
";

// Test if we can award points for a test report
$test_report_id = 999999; // Test report ID
$test_user_id = 1; // Assuming user ID 1 exists
$test_admin_id = 1; 
$test_rating = 5;

try {
    // First check if table exists
    $checkTable = $connection->query("SHOW TABLES LIKE 'eco_points_transactions'");
    if ($checkTable->num_rows == 0) {
        echo "Creating eco_points_transactions table...
";
        // Initialize eco points system to create tables
        initializeEcoPointsSystem();
    }
    
    // Test the function
    $result = awardReportResolutionPointsWithRating($test_report_id, $test_admin_id, $test_rating);
    echo "Award function result: " . json_encode($result) . "
";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "
";
}
?>
