<!DOCTYPE html>
<html>
<head>
    <title>Cycle System Quick Check</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 10px 0; border-radius: 5px; }
        h1 { color: #123524; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; background: white; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #123524; color: white; }
    </style>
</head>
<body>
    <h1>🎯 Cycle System Quick Check</h1>
    
    <?php
    require_once 'database.php';
    require_once 'cycle_rankings.php';
    
    // Check 1: Active Cycle
    echo "<h2>✅ Step 1: Active Cycle Check</h2>";
    $cycleQuery = "SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
    $cycleResult = $connection->query($cycleQuery);
    
    if($cycleResult && $cycleResult->num_rows > 0) {
        $currentCycle = $cycleResult->fetch_assoc();
        echo "<div class='success'>";
        echo "<strong>Active Cycle Found:</strong> " . htmlspecialchars($currentCycle['cycle_name']) . "<br>";
        echo "<strong>Period:</strong> " . $currentCycle['start_date'] . " to " . $currentCycle['end_date'] . "<br>";
        echo "<strong>Cycle ID:</strong> " . $currentCycle['cycle_id'];
        echo "</div>";
        $cycleId = $currentCycle['cycle_id'];
    } else {
        echo "<div class='error'><strong>No active cycle!</strong> Create one in admin_rewards_manager.php</div>";
        exit;
    }
    
    // Check 2: User Activities
    echo "<h2>✅ Step 2: Recent User Activities</h2>";
    
    // Check eco_points_transactions
    $transQuery = "SELECT COUNT(*) as count, SUM(points_awarded) as total FROM eco_points_transactions WHERE activity_type != 'event_attendance'";
    $transResult = $connection->query($transQuery);
    $transData = $transResult->fetch_assoc();
    
    // Check event_attendance
    $eventQuery = "SELECT COUNT(*) as count, SUM(points_awarded) as total FROM event_attendance WHERE status = 'checked_out'";
    $eventResult = $connection->query($eventQuery);
    $eventData = $eventResult->fetch_assoc();
    
    echo "<div class='info'>";
    echo "<strong>eco_points_transactions:</strong> " . $transData['count'] . " transactions, " . number_format($transData['total']) . " points<br>";
    echo "<strong>event_attendance:</strong> " . $eventData['count'] . " checkouts, " . number_format($eventData['total']) . " points<br>";
    echo "<strong>TOTAL ACTIVITIES:</strong> " . ($transData['count'] + $eventData['count']) . " activities tracked";
    echo "</div>";
    
    // Check 3: Cycle Rankings
    echo "<h2>✅ Step 3: Current Cycle Leaderboard</h2>";
    $rankings = getCycleIndividualRankings($cycleId, 10);
    
    if(!empty($rankings)) {
        echo "<div class='success'><strong>Leaderboard is working!</strong> Showing top " . count($rankings) . " users</div>";
        echo "<table>";
        echo "<tr><th>Rank</th><th>Name</th><th>Cycle Points</th><th>Events</th><th>Reports</th><th>Active Days</th></tr>";
        $rank = 1;
        foreach($rankings as $user) {
            echo "<tr>";
            echo "<td><strong>#$rank</strong></td>";
            echo "<td>" . htmlspecialchars($user['fullname']) . "</td>";
            echo "<td><strong>" . number_format($user['cycle_points']) . " pts</strong></td>";
            echo "<td>" . $user['events_attended'] . "</td>";
            echo "<td>" . $user['reports_resolved'] . "</td>";
            echo "<td>" . $user['active_days'] . "</td>";
            echo "</tr>";
            $rank++;
        }
        echo "</table>";
    } else {
        echo "<div class='error'><strong>No cycle activity yet!</strong> Users need to attend events, submit reports, or login during the cycle period.</div>";
    }
    
    // Check 4: How it works
    echo "<h2>📖 How The System Works</h2>";
    echo "<div class='info'>";
    echo "<strong>User Activities Tracked:</strong><br>";
    echo "1. <strong>Event Attendance</strong> - When users check out from events (event_attendance table)<br>";
    echo "2. <strong>Report Submission</strong> - When reports are resolved (eco_points_transactions table)<br>";
    echo "3. <strong>Daily Login</strong> - When users login daily (eco_points_transactions table)<br>";
    echo "4. <strong>Organization Join</strong> - When users join organizations (eco_points_transactions table)<br><br>";
    
    echo "<strong>Cycle Leaderboards Update:</strong><br>";
    echo "✅ Automatically when page refreshes (leaderboards.php)<br>";
    echo "✅ Combines ALL activities from both tables<br>";
    echo "✅ Filters by cycle date range<br>";
    echo "✅ Excludes Barangay Officials<br><br>";
    
    echo "<strong>Reward Calculation:</strong><br>";
    echo "When admin clicks 'Calculate & Distribute Rewards':<br>";
    echo "1. System queries cycle rankings (from both tables)<br>";
    echo "2. Top 10 individuals get rewards (500, 300, 200, 100, 50 pts)<br>";
    echo "3. Top 5 groups (barangays, municipalities, organizations) get rewards<br>";
    echo "4. Only ACTIVE members of winning groups receive rewards<br>";
    echo "5. Rewards saved to user_rewards table with status='pending'<br>";
    echo "6. Users can claim rewards in leaderboards page<br>";
    echo "</div>";
    
    // Test button
    echo "<h2>🧪 Next Steps</h2>";
    echo "<div class='success'>";
    echo "<ol>";
    echo "<li>✅ <strong>System is ready!</strong> Cycle rankings combine both tables</li>";
    echo "<li>📱 <strong>Test it:</strong> Attend an event or submit a report</li>";
    echo "<li>🔄 <strong>Refresh:</strong> Go to <a href='leaderboards.php' target='_blank'>leaderboards.php</a> - you should see updated cycle rankings</li>";
    echo "<li>🎁 <strong>Reward:</strong> Go to <a href='admin_rewards_manager.php' target='_blank'>admin_rewards_manager.php</a> - calculate rewards based on cycle rankings</li>";
    echo "</ol>";
    echo "</div>";
    
    $connection->close();
    ?>
</body>
</html>
