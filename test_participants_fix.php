<?php
session_start();

// Simulate admin session
$_SESSION['accessrole'] = 'Administrator';
$_SESSION['user_id'] = 1;

require_once 'database.php';

echo "=== TESTING get_cycle_participants.php FIX ===\n\n";

// Test 1: Check if we have an active cycle
echo "1. Checking for active cycles...\n";
$cycleResult = $connection->query("SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1");
if($cycleResult->num_rows > 0) {
    $cycle = $cycleResult->fetch_assoc();
    echo "   ✅ Found active cycle: {$cycle['cycle_name']} (ID: {$cycle['cycle_id']})\n";
    echo "   Period: {$cycle['start_date']} to {$cycle['end_date']}\n\n";
    
    $cycleId = $cycle['cycle_id'];
    
    // Test 2: Check for transactions in this period
    echo "2. Checking eco_points_transactions...\n";
    $transQuery = "
        SELECT COUNT(*) as count, SUM(points_awarded) as total_points
        FROM eco_points_transactions 
        WHERE created_at >= ? 
          AND created_at <= ?
          AND points_awarded > 0
    ";
    $stmt = $connection->prepare($transQuery);
    $stmt->bind_param("ss", $cycle['start_date'], $cycle['end_date']);
    $stmt->execute();
    $transStats = $stmt->get_result()->fetch_assoc();
    echo "   Found {$transStats['count']} transactions with {$transStats['total_points']} points\n\n";
    
    // Test 3: Check for event attendance in this period
    echo "3. Checking event_attendance...\n";
    $eventQuery = "
        SELECT COUNT(*) as count, SUM(points_awarded) as total_points
        FROM event_attendance
        WHERE checkout_time >= ? 
          AND checkout_time <= ?
          AND attendance_status = 'completed'
          AND points_awarded > 0
    ";
    $stmt = $connection->prepare($eventQuery);
    $stmt->bind_param("ss", $cycle['start_date'], $cycle['end_date']);
    $stmt->execute();
    $eventStats = $stmt->get_result()->fetch_assoc();
    echo "   Found {$eventStats['count']} completed events with {$eventStats['total_points']} points\n\n";
    
    // Test 4: Simulate the actual endpoint call
    echo "4. Simulating endpoint call...\n";
    $_GET['cycle_id'] = $cycleId;
    
    // Capture output
    ob_start();
    include 'get_cycle_participants.php';
    $output = ob_get_clean();
    
    echo "   Raw output length: " . strlen($output) . " bytes\n";
    
    // Try to decode JSON
    $data = json_decode($output, true);
    if($data === null) {
        echo "   ❌ JSON DECODE ERROR!\n";
        echo "   Error: " . json_last_error_msg() . "\n";
        echo "   First 500 chars of output:\n";
        echo "   " . substr($output, 0, 500) . "\n";
    } else {
        echo "   ✅ Valid JSON response!\n";
        if($data['success']) {
            echo "   Success: true\n";
            echo "   Participants: {$data['stats']['total_participants']}\n";
            echo "   Total Points: {$data['stats']['total_points_earned']}\n";
            echo "   Total Activities: {$data['stats']['total_activities']}\n";
            echo "   Avg Points/User: {$data['stats']['avg_points_per_user']}\n";
            
            if(count($data['participants']) > 0) {
                echo "\n   Top 3 Participants:\n";
                $top3 = array_slice($data['participants'], 0, 3);
                foreach($top3 as $i => $p) {
                    echo "   " . ($i+1) . ". {$p['fullname']} - {$p['total_points']} pts ({$p['activities']} activities)\n";
                }
            } else {
                echo "\n   No participants yet (this is expected for new cycles)\n";
            }
        } else {
            echo "   ❌ Success: false\n";
            echo "   Message: {$data['message']}\n";
        }
    }
    
} else {
    echo "   ⚠️ No active cycle found!\n";
    echo "   Create a cycle first to test.\n";
}

$connection->close();
echo "\n=== TEST COMPLETE ===\n";
?>
