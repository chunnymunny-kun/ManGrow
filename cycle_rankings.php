<?php
/**
 * Cycle Rankings System - TIMEZONE CORRECTED VERSION
 * Combines eco_points_transactions + event_attendance for accurate cycle rankings
 * 
 * TIMEZONE HANDLING:
 * - reward_cycles: Stores dates in Asia/Manila (UTC+8)
 * - eco_points_transactions: InfinityFree DB uses EST (UTC-5) - 13 hours behind PHT
 * - event_attendance: Uses Asia/Manila (UTC+8) - correct timezone
 * 
 * SOLUTION: Convert cycle boundaries to EST when querying eco_points_transactions
 */

require_once 'database.php';

/**
 * Convert cycle dates from Asia/Manila to InfinityFree DB timezone
 * InfinityFree database is 15 hours behind PHT based on actual observation
 * Example: 2025-10-14 06:39 AM PHT → stored as 2025-10-13 15:37:35 in DB
 */
function convertCycleDatesToEST($startDatePHT, $endDatePHT) {
    // Cycle dates are stored in Asia/Manila timezone
    $startPHT = new DateTime($startDatePHT, new DateTimeZone('Asia/Manila'));
    $endPHT = new DateTime($endDatePHT, new DateTimeZone('Asia/Manila'));
    
    // Subtract 15 hours to match InfinityFree database timezone
    // Based on user's observation: PHT 6:39 AM = DB 3:37 PM previous day (15 hours behind)
    $startDB = clone $startPHT;
    $startDB->modify('-15 hours');
    
    $endDB = clone $endPHT;
    $endDB->modify('-15 hours');
    
    return [
        'start_est' => $startDB->format('Y-m-d H:i:s'),
        'end_est' => $endDB->format('Y-m-d H:i:s'),
        'start_pht' => $startDatePHT,
        'end_pht' => $endDatePHT
    ];
}

/**
 * Get individual rankings for a specific cycle
 * Combines points from BOTH eco_points_transactions AND event_attendance tables
 */
function getCycleIndividualRankings($cycleId, $limit = 10) {
    global $connection;
    
    // Get cycle dates
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return [];
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    // Combine both tables with LEFT JOINs
    // NOTE: eco_points_transactions uses EST boundaries, event_attendance uses PHT boundaries
    $query = "
        SELECT 
            a.account_id as user_id,
            a.fullname,
            a.profile,
            a.profile_thumbnail,
            COALESCE(trans_points.points, 0) + COALESCE(event_points.points, 0) as cycle_points,
            COALESCE(event_points.events_attended, 0) as events_attended,
            COALESCE(trans_points.reports_resolved, 0) as reports_resolved,
            COALESCE(trans_points.active_days, 0) as active_days
        FROM accountstbl a
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(points_awarded) as points,
                SUM(CASE WHEN activity_type = 'report_resolved' THEN 1 ELSE 0 END) as reports_resolved,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM eco_points_transactions
            WHERE created_at >= ? 
              AND created_at <= ?
              AND points_awarded > 0
              AND activity_type != 'event_attendance'
            GROUP BY user_id
        ) trans_points ON a.account_id = trans_points.user_id
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(points_awarded) as points,
                COUNT(DISTINCT event_id) as events_attended
            FROM event_attendance
            WHERE checkout_time >= ? 
              AND checkout_time <= ?
              AND points_awarded > 0
              AND attendance_status = 'completed'
            GROUP BY user_id
        ) event_points ON a.account_id = event_points.user_id
        WHERE (a.accessrole IS NULL OR a.accessrole != 'Barangay Official')
          AND (trans_points.points > 0 OR event_points.points > 0)
        ORDER BY cycle_points DESC
        LIMIT ?
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssi", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht'],  // PHT for event_attendance
        $limit
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get barangay rankings for a specific cycle
 */
function getCycleBarangayRankings($cycleId, $limit = 5) {
    global $connection;
    
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return [];
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    $query = "
        SELECT 
            a.barangay,
            SUM(COALESCE(trans_points.points, 0) + COALESCE(event_points.points, 0)) as cycle_points,
            COUNT(DISTINCT a.account_id) as active_members
        FROM accountstbl a
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM eco_points_transactions
            WHERE created_at >= ? AND created_at <= ?
              AND points_awarded > 0
              AND activity_type != 'event_attendance'
            GROUP BY user_id
        ) trans_points ON a.account_id = trans_points.user_id
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM event_attendance
            WHERE checkout_time >= ? AND checkout_time <= ?
              AND points_awarded > 0
              AND attendance_status = 'completed'
            GROUP BY user_id
        ) event_points ON a.account_id = event_points.user_id
        WHERE a.barangay IS NOT NULL 
          AND a.barangay != ''
          AND (a.accessrole IS NULL OR a.accessrole != 'Barangay Official')
          AND (trans_points.points > 0 OR event_points.points > 0)
        GROUP BY a.barangay
        ORDER BY cycle_points DESC
        LIMIT ?
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssi", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht'],  // PHT for event_attendance
        $limit
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get municipality rankings for a specific cycle
 */
function getCycleMunicipalityRankings($cycleId, $limit = 5) {
    global $connection;
    
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return [];
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    $query = "
        SELECT 
            a.city_municipality,
            SUM(COALESCE(trans_points.points, 0) + COALESCE(event_points.points, 0)) as cycle_points,
            COUNT(DISTINCT a.account_id) as active_members
        FROM accountstbl a
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM eco_points_transactions
            WHERE created_at >= ? AND created_at <= ?
              AND points_awarded > 0
              AND activity_type != 'event_attendance'
            GROUP BY user_id
        ) trans_points ON a.account_id = trans_points.user_id
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM event_attendance
            WHERE checkout_time >= ? AND checkout_time <= ?
              AND points_awarded > 0
              AND attendance_status = 'completed'
            GROUP BY user_id
        ) event_points ON a.account_id = event_points.user_id
        WHERE a.city_municipality IS NOT NULL 
          AND a.city_municipality != ''
          AND (a.accessrole IS NULL OR a.accessrole != 'Barangay Official')
          AND (trans_points.points > 0 OR event_points.points > 0)
        GROUP BY a.city_municipality
        ORDER BY cycle_points DESC
        LIMIT ?
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssi", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht'],  // PHT for event_attendance
        $limit
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get organization rankings for a specific cycle
 */
function getCycleOrganizationRankings($cycleId, $limit = 5) {
    global $connection;
    
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return [];
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    $query = "
        SELECT 
            a.organization,
            SUM(COALESCE(trans_points.points, 0) + COALESCE(event_points.points, 0)) as cycle_points,
            COUNT(DISTINCT a.account_id) as active_members
        FROM accountstbl a
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM eco_points_transactions
            WHERE created_at >= ? AND created_at <= ?
              AND points_awarded > 0
              AND activity_type != 'event_attendance'
            GROUP BY user_id
        ) trans_points ON a.account_id = trans_points.user_id
        LEFT JOIN (
            SELECT user_id, SUM(points_awarded) as points
            FROM event_attendance
            WHERE checkout_time >= ? AND checkout_time <= ?
              AND points_awarded > 0
              AND attendance_status = 'completed'
            GROUP BY user_id
        ) event_points ON a.account_id = event_points.user_id
        WHERE a.organization IS NOT NULL 
          AND a.organization != ''
          AND a.organization != 'N/A'
          AND (a.accessrole IS NULL OR a.accessrole != 'Barangay Official')
          AND (trans_points.points > 0 OR event_points.points > 0)
        GROUP BY a.organization
        ORDER BY cycle_points DESC
        LIMIT ?
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssi", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht'],  // PHT for event_attendance
        $limit
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get active members of a group who participated during the cycle
 * Returns array of user IDs who actually earned points
 */
function getCycleGroupActiveMembers($cycleId, $groupField, $groupName) {
    global $connection;
    
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return [];
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    // Get users who earned points during cycle from BOTH tables
    $query = "
        SELECT DISTINCT a.account_id
        FROM accountstbl a
        LEFT JOIN eco_points_transactions t ON a.account_id = t.user_id
            AND t.created_at >= ? 
            AND t.created_at <= ?
            AND t.points_awarded > 0
            AND t.activity_type != 'event_attendance'
        LEFT JOIN event_attendance ea ON a.account_id = ea.user_id
            AND ea.checkout_time >= ? 
            AND ea.checkout_time <= ?
            AND ea.points_awarded > 0
            AND ea.attendance_status = 'completed'
        WHERE a.$groupField = ?
          AND (a.accessrole IS NULL OR a.accessrole != 'Barangay Official')
          AND (t.transaction_id IS NOT NULL OR ea.attendance_id IS NOT NULL)
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("sssss", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht'],  // PHT for event_attendance
        $groupName
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while($row = $result->fetch_assoc()) {
        $members[] = $row['account_id'];
    }
    
    return $members;
}

/**
 * Get cycle statistics
 */
function getCycleStatistics($cycleId) {
    global $connection;
    
    $cycleQuery = "SELECT start_date, end_date FROM reward_cycles WHERE cycle_id = ?";
    $stmt = $connection->prepare($cycleQuery);
    $stmt->bind_param("i", $cycleId);
    $stmt->execute();
    $cycle = $stmt->get_result()->fetch_assoc();
    
    if(!$cycle) return null;
    
    // Convert cycle dates for different timezone tables
    $dates = convertCycleDatesToEST($cycle['start_date'], $cycle['end_date']);
    
    // Count unique participants from BOTH tables
    $participantsQuery = "
        SELECT COUNT(DISTINCT user_id) as total_participants
        FROM (
            SELECT user_id FROM eco_points_transactions 
            WHERE created_at >= ? AND created_at <= ? 
              AND points_awarded > 0
              AND activity_type != 'event_attendance'
            UNION
            SELECT user_id FROM event_attendance 
            WHERE checkout_time >= ? AND checkout_time <= ? 
              AND points_awarded > 0
              AND attendance_status = 'completed'
        ) as participants
    ";
    
    $stmt = $connection->prepare($participantsQuery);
    $stmt->bind_param("ssss", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht']   // PHT for event_attendance
    );
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    // Calculate total points from BOTH tables
    $pointsQuery = "
        SELECT 
            COALESCE(
                (SELECT SUM(points_awarded) 
                 FROM eco_points_transactions 
                 WHERE created_at >= ? AND created_at <= ? 
                   AND points_awarded > 0
                   AND activity_type != 'event_attendance'
                ), 0
            ) 
            + 
            COALESCE(
                (SELECT SUM(points_awarded) 
                 FROM event_attendance 
                 WHERE checkout_time >= ? AND checkout_time <= ? 
                   AND points_awarded > 0
                   AND attendance_status = 'completed'
                ), 0
            ) as total_points
    ";
    
    $stmt = $connection->prepare($pointsQuery);
    $stmt->bind_param("ssss", 
        $dates['start_est'], $dates['end_est'],  // EST for eco_points_transactions
        $dates['start_pht'], $dates['end_pht']   // PHT for event_attendance
    );
    $stmt->execute();
    $pointsStats = $stmt->get_result()->fetch_assoc();
    
    return array_merge($stats, $pointsStats);
}
?>
