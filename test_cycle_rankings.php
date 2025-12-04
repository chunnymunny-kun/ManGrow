<?php
/**
 * Test script to verify cycle-based rankings are working correctly
 * This script will:
 * 1. Check if cycle_rankings.php functions are accessible
 * 2. Create a test cycle if none exists
 * 3. Display cycle rankings vs lifetime rankings
 * 4. Show the difference between the two ranking systems
 */

require_once 'database.php';
require_once 'cycle_rankings.php';

echo "<h1>Cycle Rankings Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    tr:nth-child(even) { background-color: #f2f2f2; }
    .section { margin: 30px 0; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
</style>";

// 1. Check for active cycle
echo "<div class='section'>";
echo "<h2>1. Active Cycle Check</h2>";

$cycleQuery = "SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
$cycleResult = $connection->query($cycleQuery);

if($cycleResult && $cycleResult->num_rows > 0) {
    $currentCycle = $cycleResult->fetch_assoc();
    echo "<p class='success'>✓ Active cycle found: " . htmlspecialchars($currentCycle['cycle_name']) . "</p>";
    echo "<p><strong>Cycle ID:</strong> " . $currentCycle['cycle_id'] . "</p>";
    echo "<p><strong>Start Date:</strong> " . $currentCycle['start_date'] . "</p>";
    echo "<p><strong>End Date:</strong> " . $currentCycle['end_date'] . "</p>";
    $cycleId = $currentCycle['cycle_id'];
} else {
    echo "<p class='error'>✗ No active cycle found. Creating a test cycle...</p>";
    
    // Create a test cycle for current month
    $startDate = date('Y-m-01'); // First day of current month
    $endDate = date('Y-m-t'); // Last day of current month
    $cycleName = date('F Y') . " Cycle";
    
    $insertQuery = "INSERT INTO reward_cycles (cycle_name, start_date, end_date, status, created_at) 
                    VALUES (?, ?, ?, 'active', NOW())";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("sss", $cycleName, $startDate, $endDate);
    
    if($stmt->execute()) {
        $cycleId = $stmt->insert_id;
        echo "<p class='success'>✓ Test cycle created successfully! Cycle ID: $cycleId</p>";
    } else {
        echo "<p class='error'>✗ Failed to create test cycle: " . $stmt->error . "</p>";
        exit;
    }
}
echo "</div>";

// 2. Display Lifetime Rankings (OLD METHOD)
echo "<div class='section'>";
echo "<h2>2. Lifetime Rankings (Old Method)</h2>";
echo "<p class='info'>These rankings are based on total eco_points accumulated over all time</p>";

$lifetimeQuery = "SELECT account_id, fullname, eco_points 
                  FROM accountstbl 
                  WHERE (accessrole IS NULL OR accessrole != 'Barangay Official')
                  ORDER BY eco_points DESC 
                  LIMIT 10";
$lifetimeResult = $connection->query($lifetimeQuery);

if($lifetimeResult && $lifetimeResult->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Rank</th><th>Name</th><th>Lifetime Points</th></tr>";
    $rank = 1;
    while($user = $lifetimeResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>#$rank</td>";
        echo "<td>" . htmlspecialchars($user['fullname']) . "</td>";
        echo "<td>" . number_format($user['eco_points']) . "</td>";
        echo "</tr>";
        $rank++;
    }
    echo "</table>";
} else {
    echo "<p>No users found</p>";
}
echo "</div>";

// 3. Display Cycle Rankings (NEW METHOD)
echo "<div class='section'>";
echo "<h2>3. Cycle Rankings (New Method)</h2>";
echo "<p class='info'>These rankings combine BOTH eco_points_transactions AND event_attendance tables</p>";

$cycleRankings = getCycleIndividualRankings($cycleId, 10);

if(!empty($cycleRankings)) {
    echo "<table>";
    echo "<tr>
            <th>Rank</th>
            <th>Name</th>
            <th>Cycle Points</th>
            <th>Events</th>
            <th>Reports</th>
            <th>Logins</th>
            <th>Active Days</th>
          </tr>";
    $rank = 1;
    foreach($cycleRankings as $user) {
        echo "<tr>";
        echo "<td>#$rank</td>";
        echo "<td>" . htmlspecialchars($user['fullname']) . "</td>";
        echo "<td>" . number_format($user['cycle_points']) . "</td>";
        echo "<td>" . ($user['events_attended'] ?? 0) . "</td>";
        echo "<td>" . ($user['reports_resolved'] ?? 0) . "</td>";
        echo "<td>" . number_format($user['login_points'] ?? 0) . "</td>";
        echo "<td>" . ($user['active_days'] ?? 0) . "</td>";
        echo "</tr>";
        $rank++;
    }
    echo "</table>";
} else {
    echo "<p class='error'>No cycle activity found. This could mean:</p>";
    echo "<ul>";
    echo "<li>No users have earned points during this cycle period</li>";
    echo "<li>The eco_points_transactions table is empty</li>";
    echo "<li>Transaction dates don't fall within the cycle dates</li>";
    echo "</ul>";
}
echo "</div>";

// 4. Display Cycle Statistics
echo "<div class='section'>";
echo "<h2>4. Cycle Statistics</h2>";

$cycleStats = getCycleStatistics($cycleId);

if($cycleStats) {
    echo "<table>";
    echo "<tr><th>Metric</th><th>Value</th></tr>";
    echo "<tr><td>Total Active Users</td><td>" . $cycleStats['total_active_users'] . "</td></tr>";
    echo "<tr><td>Total Points Awarded</td><td>" . number_format($cycleStats['total_points_awarded']) . "</td></tr>";
    echo "<tr><td>Total Transactions</td><td>" . number_format($cycleStats['total_transactions']) . "</td></tr>";
    echo "<tr><td>Active Days</td><td>" . $cycleStats['active_days'] . "</td></tr>";
    echo "</table>";
} else {
    echo "<p>No statistics available</p>";
}
echo "</div>";

// 5. Check eco_points_transactions table
echo "<div class='section'>";
echo "<h2>5. Sample Transactions Check</h2>";
echo "<p class='info'>Checking if transactions exist in eco_points_transactions table</p>";

$transactionQuery = "SELECT COUNT(*) as total FROM eco_points_transactions";
$transactionResult = $connection->query($transactionQuery);
$transactionCount = $transactionResult->fetch_assoc()['total'];

echo "<p>Total transactions in database: <strong>$transactionCount</strong></p>";

if($transactionCount > 0) {
    echo "<p class='success'>✓ Transactions table has data</p>";
    
    // Show sample transactions
    $sampleQuery = "SELECT t.*, a.fullname 
                    FROM eco_points_transactions t
                    JOIN accountstbl a ON t.user_id = a.account_id
                    ORDER BY t.created_at DESC 
                    LIMIT 5";
    $sampleResult = $connection->query($sampleQuery);
    
    if($sampleResult && $sampleResult->num_rows > 0) {
        echo "<h3>Recent Transactions (Sample)</h3>";
        echo "<table>";
        echo "<tr><th>User</th><th>Activity Type</th><th>Points</th><th>Date</th></tr>";
        while($tx = $sampleResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($tx['fullname']) . "</td>";
            echo "<td>" . htmlspecialchars($tx['activity_type']) . "</td>";
            echo "<td>" . $tx['points_awarded'] . "</td>";
            echo "<td>" . $tx['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p class='error'>✗ No transactions found. The cycle rankings will be empty until activities are logged.</p>";
    echo "<p>Activities that should log to this table:</p>";
    echo "<ul>";
    echo "<li>Event attendance (QR check-out)</li>";
    echo "<li>Illegal reports resolution</li>";
    echo "<li>Login streaks</li>";
    echo "<li>Badge awards</li>";
    echo "<li>Referrals</li>";
    echo "</ul>";
}
echo "</div>";

// 6. Display Group Rankings
echo "<div class='section'>";
echo "<h2>6. Group Cycle Rankings</h2>";

echo "<h3>Top Barangays (Cycle)</h3>";
$cycleBarangays = getCycleBarangayRankings($cycleId, 5);
if(!empty($cycleBarangays)) {
    echo "<table>";
    echo "<tr><th>Rank</th><th>Barangay</th><th>Cycle Points</th><th>Active Members</th></tr>";
    $rank = 1;
    foreach($cycleBarangays as $barangay) {
        echo "<tr>";
        echo "<td>#$rank</td>";
        echo "<td>" . htmlspecialchars($barangay['barangay']) . "</td>";
        echo "<td>" . number_format($barangay['cycle_points']) . "</td>";
        echo "<td>" . $barangay['active_members'] . "</td>";
        echo "</tr>";
        $rank++;
    }
    echo "</table>";
} else {
    echo "<p>No barangay activity during this cycle</p>";
}

echo "<h3>Top Organizations (Cycle)</h3>";
$cycleOrgs = getCycleOrganizationRankings($cycleId, 5);
if(!empty($cycleOrgs)) {
    echo "<table>";
    echo "<tr><th>Rank</th><th>Organization</th><th>Cycle Points</th><th>Active Members</th></tr>";
    $rank = 1;
    foreach($cycleOrgs as $org) {
        echo "<tr>";
        echo "<td>#$rank</td>";
        echo "<td>" . htmlspecialchars($org['organization']) . "</td>";
        echo "<td>" . number_format($org['cycle_points']) . "</td>";
        echo "<td>" . $org['active_members'] . "</td>";
        echo "</tr>";
        $rank++;
    }
    echo "</table>";
} else {
    echo "<p>No organization activity during this cycle</p>";
}
echo "</div>";

echo "<hr>";
echo "<p><strong>Test completed!</strong> Navigate back to <a href='leaderboards.php'>Leaderboards</a> or <a href='admin_rewards_manager.php'>Admin Rewards Manager</a> to see the changes in action.</p>";

$connection->close();
?>
