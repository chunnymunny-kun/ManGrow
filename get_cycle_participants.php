<?php
// Prevent any output before JSON
ob_start();

session_start();
require_once 'database.php';

// Set JSON header
header('Content-Type: application/json');

// Admin access check
if(!isset($_SESSION['accessrole']) || $_SESSION['accessrole'] !== 'Administrator') {
    ob_clean(); // Clear any buffered output
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$cycleId = $_GET['cycle_id'] ?? null;

if(!$cycleId) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Cycle ID required']);
    exit;
}

try {
    // Get cycle details
    $cycleStmt = $connection->prepare("SELECT * FROM reward_cycles WHERE cycle_id = ?");
    $cycleStmt->bind_param("i", $cycleId);
    $cycleStmt->execute();
    $cycle = $cycleStmt->get_result()->fetch_assoc();

    if(!$cycle) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Cycle not found']);
        exit;
    }

    // Convert cycle dates for different timezone tables
    // reward_cycles stores in Asia/Manila (UTC+8)
    // eco_points_transactions: InfinityFree DB is 15 hours behind PHT (actual observation)
    // event_attendance: Uses Asia/Manila (UTC+8) - correct
    $startPHT = new DateTime($cycle['start_date'], new DateTimeZone('Asia/Manila'));
    $endPHT = new DateTime($cycle['end_date'], new DateTimeZone('Asia/Manila'));
    
    // Subtract 15 hours to match InfinityFree database timezone
    $startDB = clone $startPHT;
    $startDB->modify('-15 hours');
    
    $endDB = clone $endPHT;
    $endDB->modify('-15 hours');
    
    $startESTStr = $startDB->format('Y-m-d H:i:s');
    $endESTStr = $endDB->format('Y-m-d H:i:s');
    $startPHTStr = $cycle['start_date'];
    $endPHTStr = $cycle['end_date'];

    // Get all users who earned points during this cycle
    // Combines eco_points_transactions + event attendance
    $participants = [];

// Query for eco_points_transactions (using EST boundaries)
$transactionQuery = "
    SELECT 
        a.account_id,
        a.fullname,
        a.profile_thumbnail,
        a.barangay,
        a.city_municipality,
        a.organization,
        SUM(ept.points_awarded) as total_points,
        COUNT(ept.transaction_id) as transaction_count,
        MAX(ept.created_at) as last_activity
    FROM eco_points_transactions ept
    JOIN accountstbl a ON ept.user_id = a.account_id
    WHERE ept.created_at >= ? 
      AND ept.created_at <= ?
      AND ept.points_awarded > 0
      AND ept.activity_type != 'event_attendance'
    GROUP BY a.account_id, a.fullname, a.profile_thumbnail, a.barangay, a.city_municipality, a.organization
";

$stmt = $connection->prepare($transactionQuery);
$stmt->bind_param("ss", $startESTStr, $endESTStr);  // Use EST boundaries
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $userId = $row['account_id'];
    if(!isset($participants[$userId])) {
        $participants[$userId] = [
            'user_id' => $userId,
            'fullname' => $row['fullname'],
            'profile_thumbnail' => $row['profile_thumbnail'],
            'barangay' => $row['barangay'],
            'city_municipality' => $row['city_municipality'],
            'organization' => $row['organization'],
            'total_points' => 0,
            'activities' => 0,
            'last_activity' => null
        ];
    }
    $participants[$userId]['total_points'] += (int)$row['total_points'];
    $participants[$userId]['activities'] += (int)$row['transaction_count'];
    
    // Convert DB timezone (15h behind) to PHT for display
    if($row['last_activity']) {
        $activityDB = new DateTime($row['last_activity']);
        $activityDB->modify('+15 hours'); // Add 15 hours to convert back to PHT
        $activityPHT = $activityDB->format('Y-m-d H:i:s');
        
        error_log("Transaction time conversion - DB: {$row['last_activity']} → PHT: {$activityPHT}");
        
        if(!$participants[$userId]['last_activity'] || $activityPHT > $participants[$userId]['last_activity']) {
            $participants[$userId]['last_activity'] = $activityPHT;
        }
    }
}

// Query for event attendance (using PHT boundaries - already correct timezone)
$eventQuery = "
    SELECT 
        a.account_id,
        a.fullname,
        a.profile_thumbnail,
        a.barangay,
        a.city_municipality,
        a.organization,
        SUM(ea.points_awarded) as total_points,
        COUNT(ea.attendance_id) as event_count,
        MAX(ea.checkout_time) as last_activity
    FROM event_attendance ea
    JOIN accountstbl a ON ea.user_id = a.account_id
    WHERE ea.checkout_time >= ? 
      AND ea.checkout_time <= ?
      AND ea.attendance_status = 'completed'
      AND ea.points_awarded > 0
    GROUP BY a.account_id, a.fullname, a.profile_thumbnail, a.barangay, a.city_municipality, a.organization
";

$stmt = $connection->prepare($eventQuery);
$stmt->bind_param("ss", $startPHTStr, $endPHTStr);  // Use PHT boundaries (already correct)
$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()) {
    $userId = $row['account_id'];
    if(!isset($participants[$userId])) {
        $participants[$userId] = [
            'user_id' => $userId,
            'fullname' => $row['fullname'],
            'profile_thumbnail' => $row['profile_thumbnail'],
            'barangay' => $row['barangay'],
            'city_municipality' => $row['city_municipality'],
            'organization' => $row['organization'],
            'total_points' => 0,
            'activities' => 0,
            'last_activity' => null
        ];
    }
    $participants[$userId]['total_points'] += (int)$row['total_points'];
    $participants[$userId]['activities'] += (int)$row['event_count'];
    
    // Event attendance times are already in PHT
    if($row['last_activity']) {
        error_log("Event time (already PHT): {$row['last_activity']}");
        
        if(!$participants[$userId]['last_activity'] || $row['last_activity'] > $participants[$userId]['last_activity']) {
            $participants[$userId]['last_activity'] = $row['last_activity'];
        }
    }
}

// Convert to array and sort by points
$participants = array_values($participants);
usort($participants, function($a, $b) {
    return $b['total_points'] - $a['total_points'];
});

    // Calculate statistics
    $stats = [
        'total_participants' => count($participants),
        'total_points_earned' => array_sum(array_column($participants, 'total_points')),
        'total_activities' => array_sum(array_column($participants, 'activities')),
        'avg_points_per_user' => count($participants) > 0 ? round(array_sum(array_column($participants, 'total_points')) / count($participants), 2) : 0
    ];

    ob_clean(); // Clear any buffered output before sending JSON
    echo json_encode([
        'success' => true,
        'cycle' => $cycle,
        'participants' => $participants,
        'stats' => $stats
    ]);

} catch(Exception $e) {
    ob_clean(); // Clear any buffered output
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$connection->close();
?>
