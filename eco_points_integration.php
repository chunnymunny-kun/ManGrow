<?php
/**
 * Eco Points Integration Functions
 * These functions provide easy integration between the EcoPointsSystem and existing ManGrow components
 */

require_once 'eco_points_system.php';
require_once 'database.php';

/**
 * Initialize eco points system with database connection
 */
function initializeEcoPointsSystem() {
    global $connection;
    EcoPointsSystem::init($connection, [
        'event_base_points' => 50,
        'report_base_points' => 25,
        'daily_login_points' => 5,
        'referral_points' => 50,
        'badge_bonus_percentage' => 15,
        'max_daily_points' => 500,
        'min_account_age_days' => 0
    ]);
}

/**
 * EVENT INTEGRATION FUNCTIONS
 */

/**
 * Award points to all attendees when an event is completed
 * Called from admin panel when marking event as complete
 */
function awardEventCompletionPoints($eventId, $adminId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Get all attendees for this event
    $attendeeQuery = "SELECT DISTINCT account_id FROM attendeestbl WHERE event_id = ?";
    $stmt = $connection->prepare($attendeeQuery);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $results = [];
    $successCount = 0;
    $errorCount = 0;
    
    while ($attendee = $result->fetch_assoc()) {
        $userId = $attendee['account_id'];
        $result = EcoPointsSystem::awardEventPoints($eventId, $userId);
        
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
        $results[] = ['user_id' => $userId, 'result' => $result];
    }
    
    // Update event status
    $updateEventQuery = "UPDATE eventstbl SET completion_status = 'completed', completed_at = CURRENT_TIMESTAMP, completed_by = ? WHERE event_id = ?";
    $stmt = $connection->prepare($updateEventQuery);
    $stmt->bind_param("ii", $adminId, $eventId);
    $stmt->execute();
    
    return [
        'success' => true,
        'total_processed' => $successCount + $errorCount,
        'successful_awards' => $successCount,
        'errors' => $errorCount,
        'details' => $results
    ];
}

/**
 * Check if user can earn points for attending an event
 */
function canUserEarnEventPoints($userId, $eventId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Check if user is registered for event
    $attendeeQuery = "SELECT id FROM attendeestbl WHERE account_id = ? AND event_id = ?";
    $stmt = $connection->prepare($attendeeQuery);
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['can_earn' => false, 'reason' => 'Not registered for event'];
    }
    
    // Check if event is completed
    $eventQuery = "SELECT completion_status FROM eventstbl WHERE event_id = ?";
    $stmt = $connection->prepare($eventQuery);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if ($event['completion_status'] !== 'completed') {
        return ['can_earn' => false, 'reason' => 'Event not yet completed'];
    }
    
    // Check if already received points
    $transactionQuery = "SELECT transaction_id FROM eco_points_transactions 
                        WHERE user_id = ? AND activity_type = 'event_attendance' AND reference_id = ?";
    $stmt = $connection->prepare($transactionQuery);
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['can_earn' => false, 'reason' => 'Points already awarded'];
    }
    
    return ['can_earn' => true, 'reason' => 'Eligible for points'];
}

/**
 * REPORT INTEGRATION FUNCTIONS
 */

/**
 * Award points when a report is marked as resolved
 * Called from admin report page when updating action_type to 'Resolved'
 */
function awardReportResolutionPoints($reportId, $adminId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Get report details
    $reportQuery = "SELECT reporter_id, priority, report_type, action_type FROM illegalreportstbl WHERE report_id = ?";
    $stmt = $connection->prepare($reportQuery);
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Report not found'];
    }
    
    $report = $result->fetch_assoc();
    
    // Only award points if action_type is 'Resolved' or 'Action Taken'
    if (!in_array($report['action_type'], ['Resolved', 'Action Taken'])) {
        return ['success' => false, 'message' => 'Report not in resolved status'];
    }
    
    // Award points to the reporter
    $result = EcoPointsSystem::awardReportPoints($reportId, $report['reporter_id'], $report['priority']);
    
    return $result;
}

/**
 * Award points based on admin rating (1-5 stars)
 * Called when an admin rates a resolved report
 */
function awardReportResolutionPointsWithRating($reportId, $adminId, $rating) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'Rating must be between 1 and 5 stars'];
    }
    
    // Get report details
    $reportQuery = "SELECT reporter_id, priority, report_type, action_type FROM illegalreportstbl WHERE report_id = ?";
    $stmt = $connection->prepare($reportQuery);
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Report not found'];
    }
    
    $report = $result->fetch_assoc();
    
    // Only award points if action_type is 'Resolved'
    if ($report['action_type'] !== 'Resolved') {
        return ['success' => false, 'message' => 'Report not in resolved status'];
    }
    
    // Handle anonymous reports: if reporter_id is 0 or null, get account_id from userreportstbl
    $actualReporterId = $report['reporter_id'];
    if ($actualReporterId == 0 || $actualReporterId === null) {
        $userReportQuery = "SELECT account_id FROM userreportstbl WHERE report_id = ? AND report_type = 'Illegal Activity Report'";
        $userStmt = $connection->prepare($userReportQuery);
        $userStmt->bind_param("i", $reportId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows > 0) {
            $userReport = $userResult->fetch_assoc();
            $actualReporterId = $userReport['account_id'];
        } else {
            return ['success' => false, 'message' => 'Unable to identify reporter for anonymous report'];
        }
    }
    
    // Verify the reporter exists in accountstbl
    if ($actualReporterId > 0) {
        $accountQuery = "SELECT account_id FROM accountstbl WHERE account_id = ?";
        $accountStmt = $connection->prepare($accountQuery);
        $accountStmt->bind_param("i", $actualReporterId);
        $accountStmt->execute();
        $accountResult = $accountStmt->get_result();
        
        if ($accountResult->num_rows === 0) {
            return ['success' => false, 'message' => 'Reporter account not found'];
        }
    } else {
        return ['success' => false, 'message' => 'Invalid reporter ID'];
    }
    
    // Calculate points based on rating and priority
    // Emergency: 50 points max (5 stars), Normal: 25 points max (5 stars)
    $maxPoints = ($report['priority'] === 'Emergency') ? 50 : 25;
    
    // Calculate rating multiplier (rating/5 = percentage of max points)
    $ratingMultiplier = $rating / 5.0;
    $finalPoints = (int)round($maxPoints * $ratingMultiplier);
    
    // Ensure minimum points for any valid rating (1-5 stars)
    // Even 1-star ratings should give at least some points as acknowledgment
    if ($finalPoints < 1 && $rating >= 1) {
        $finalPoints = 1; // Minimum 1 point for any valid rating
    }
    
    // Award points using the new rating-based method
    $result = EcoPointsSystem::awardReportPointsWithRating(
        $reportId, 
        $actualReporterId, // Use the actual reporter ID (either from illegalreportstbl or userreportstbl)
        $report['priority'], 
        $rating
    );
    
    return $result;
}

/**
 * Check for submission badges when a user submits a new report
 * Called when a new report is submitted to illegalreportstbl
 */
function checkSubmissionBadges($reporterId) {
    initializeEcoPointsSystem();
    
    // Use the same badge checking logic from eco points system but make it public
    return EcoPointsSystem::checkReportBadges($reporterId);
}

/**
 * Check if a report can earn points
 */
function canReportEarnPoints($reportId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    $reportQuery = "SELECT reporter_id, action_type, points_awarded FROM illegalreportstbl WHERE report_id = ?";
    $stmt = $connection->prepare($reportQuery);
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['can_earn' => false, 'reason' => 'Report not found'];
    }
    
    $report = $result->fetch_assoc();
    
    if ($report['points_awarded'] > 0) {
        return ['can_earn' => false, 'reason' => 'Points already awarded'];
    }
    
    if (!in_array($report['action_type'], ['Resolved', 'Action Taken'])) {
        return ['can_earn' => false, 'reason' => 'Report not resolved'];
    }
    
    return ['can_earn' => true, 'reason' => 'Eligible for points'];
}

/**
 * LOGIN INTEGRATION FUNCTIONS
 */

/**
 * Process daily login bonus
 * Call this in your login process or index.php
 */
function processDailyLoginBonus($userId) {
    initializeEcoPointsSystem();
    
    // Award daily login points
    $result = EcoPointsSystem::awardDailyLogin($userId);
    
    // Store in session for notification
    if ($result['success']) {
        $_SESSION['daily_login_bonus'] = [
            'points' => $result['points_awarded'],
            'message' => $result['message'] ?? 'Daily login bonus earned!'
        ];
    }
    
    return $result;
}

/**
 * Get user's login streak information
 */
function getUserLoginStreak($userId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    $query = "SELECT * FROM login_streaks WHERE user_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'total_logins' => 0];
    }
    
    return $result->fetch_assoc();
}

/**
 * PROFILE INTEGRATION FUNCTIONS
 */

/**
 * Get comprehensive user point information for profile display
 */
function getUserPointsSummary($userId) {
    initializeEcoPointsSystem();
    
    $points = EcoPointsSystem::getUserPoints($userId);
    $streak = getUserLoginStreak($userId);
    $history = EcoPointsSystem::getUserPointHistory($userId, 10);
    
    // Calculate this week's earnings - Use eco_points_transactions ONLY
    // Since QR system now uses EcoPointsSystem exclusively, all points are recorded there
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    global $connection;
    
    // Get weekly points from eco_points_transactions ONLY (includes all activities)
    $weekQuery = "SELECT SUM(points_awarded) as week_points FROM eco_points_transactions 
                  WHERE user_id = ? AND created_at >= ? AND points_awarded > 0";
    $stmt = $connection->prepare($weekQuery);
    $stmt->bind_param("is", $userId, $weekStart);
    $stmt->execute();
    $weekResult = $stmt->get_result();
    $weekPoints = $weekResult->fetch_assoc()['week_points'] ?? 0;
    
    // Get activity breakdown - Use eco_points_transactions ONLY
    // Since QR system now uses EcoPointsSystem exclusively, all activities are recorded there
    $activityQuery = "SELECT activity_type, COUNT(*) as count, SUM(points_awarded) as total_points 
                     FROM eco_points_transactions 
                     WHERE user_id = ? AND points_awarded > 0
                     GROUP BY activity_type";
    $stmt = $connection->prepare($activityQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activityBreakdown = [];
    while ($row = $result->fetch_assoc()) {
        $activityBreakdown[$row['activity_type']] = $row;
    }
    
    // Calculate weekly rank - Use eco_points_transactions ONLY
    $weeklyRankQuery = "SELECT COUNT(*) + 1 as user_rank FROM (
                          SELECT user_id, SUM(points_awarded) as week_total
                          FROM eco_points_transactions 
                          WHERE created_at >= ? AND points_awarded > 0
                          GROUP BY user_id
                          HAVING week_total > ?
                        ) as weekly_leaders";
    $stmt = $connection->prepare($weeklyRankQuery);
    $stmt->bind_param("si", $weekStart, $weekPoints);
    $stmt->execute();
    $rankResult = $stmt->get_result();
    $userRank = $rankResult->fetch_assoc()['user_rank'] ?? 1;
    
    return [
        'current_points' => $points['current_points'] ?? 0,
        'total_points' => $points['total_eco_points'] ?? 0,
        'weekly_earned' => $weekPoints,
        'user_rank' => $userRank,
        'login_streak' => $streak,
        'recent_transactions' => $history,
        'activity_breakdown' => $activityBreakdown
    ];
}

/**
 * Get next milestone information
 */
function getNextMilestones($userId) {
    $userPoints = EcoPointsSystem::getUserPoints($userId);
    $currentTotal = $userPoints['total_eco_points'];
    
    $milestones = [
        100 => 'First Century',
        250 => 'Quarter Thousand',
        500 => 'Half Thousand', 
        1000 => 'One Thousand Club',
        2500 => 'Elite Contributor',
        5000 => 'Eco Champion',
        10000 => 'Conservation Legend'
    ];
    
    $nextMilestones = [];
    foreach ($milestones as $points => $title) {
        if ($currentTotal < $points) {
            $nextMilestones[] = [
                'points_needed' => $points - $currentTotal,
                'target_points' => $points,
                'title' => $title,
                'progress_percentage' => ($currentTotal / $points) * 100
            ];
            
            if (count($nextMilestones) >= 3) break; // Show next 3 milestones
        }
    }
    
    return $nextMilestones;
}

/**
 * LEADERBOARD INTEGRATION FUNCTIONS
 */

/**
 * Get top users by points with qualification criteria
 */
function getQualifiedTopUsers($limit = 10, $period = 'all_time') {
    initializeEcoPointsSystem();
    
    global $connection;
    
    $whereClause = '';
    $params = [];
    $types = '';
    
    if ($period === 'week') {
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $whereClause = 'AND t.created_at >= ?';
        $params[] = $weekStart;
        $types .= 's';
    } elseif ($period === 'month') {
        $monthStart = date('Y-m-01');
        $whereClause = 'AND t.created_at >= ?';
        $params[] = $monthStart;
        $types .= 's';
    }
    
    // Get users with minimum activity requirement
    $query = "SELECT a.account_id, a.fullname, a.eco_points, a.total_eco_points, 
                     a.profile_thumbnail, a.barangay, a.city_municipality,
                     COUNT(DISTINCT t.transaction_id) as activity_count,
                     SUM(CASE WHEN t.created_at >= ? THEN t.points_awarded ELSE 0 END) as period_points
              FROM accountstbl a
              LEFT JOIN eco_points_transactions t ON a.account_id = t.user_id AND t.points_awarded > 0 $whereClause
              WHERE a.date_registered <= DATE_SUB(NOW(), INTERVAL 7 DAY)
              GROUP BY a.account_id
              HAVING activity_count >= 3
              ORDER BY " . ($period === 'all_time' ? 'a.total_eco_points' : 'period_points') . " DESC
              LIMIT ?";
    
    $stmt = $connection->prepare($query);
    $periodStart = ($period === 'week') ? date('Y-m-d', strtotime('monday this week')) : 
                   (($period === 'month') ? date('Y-m-01') : '2020-01-01');
    
    $allParams = array_merge([$periodStart], $params, [$limit]);
    $allTypes = 's' . $types . 'i';
    
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank++;
        $users[] = $row;
    }
    
    return $users;
}

/**
 * BADGE INTEGRATION FUNCTIONS
 */

/**
 * Award badge bonus points when a badge is earned
 */
function awardBadgeBonusPoints($userId, $badgeName) {
    initializeEcoPointsSystem();
    
    // Get badge rarity (integrate with your existing badge system)
    if (class_exists('BadgeSystem')) {
        $badge = BadgeSystem::getBadge($badgeName);
        $rarity = $badge['rarity'] ?? 'Common';
    } else {
        $rarity = 'Common'; // Default rarity
    }
    
    $result = EcoPointsSystem::awardBadgeBonus($userId, $badgeName, $rarity);
    
    return $result;
}

/**
 * SHOP INTEGRATION FUNCTIONS
 */

/**
 * Process shop item purchase
 */
function processShopPurchase($userId, $itemId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Get item details
    $itemQuery = "SELECT item_name, points_required, stock_quantity FROM ecoshop_itemstbl WHERE item_id = ? AND is_available = 1";
    $stmt = $connection->prepare($itemQuery);
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Item not available'];
    }
    
    $item = $result->fetch_assoc();
    
    // Check stock
    if ($item['stock_quantity'] !== null && $item['stock_quantity'] <= 0) {
        return ['success' => false, 'message' => 'Item out of stock'];
    }
    
    // Process purchase
    $result = EcoPointsSystem::spendPoints($userId, $item['points_required'], $itemId, $item['item_name']);
    
    if ($result['success']) {
        // Update stock if applicable
        if ($item['stock_quantity'] !== null) {
            $updateStockQuery = "UPDATE ecoshop_itemstbl SET stock_quantity = stock_quantity - 1 WHERE item_id = ?";
            $stmt = $connection->prepare($updateStockQuery);
            $stmt->bind_param("i", $itemId);
            $stmt->execute();
        }
        
        // Log purchase (you might want to create a purchases table)
        // For now, the transaction is logged in eco_points_transactions
    }
    
    return $result;
}

/**
 * UTILITY FUNCTIONS
 */

/**
 * Format activity type for display
 */
function formatActivityType($activityType) {
    $displayNames = [
        'event_attendance' => 'Event Attendance',
        'report_resolved' => 'Report Resolution',
        'daily_login' => 'Daily Login',
        'referral' => 'Referral Bonus',
        'badge_bonus' => 'Badge Achievement',
        'shop_purchase' => 'Shop Purchase',
        'admin_adjustment' => 'Admin Adjustment'
    ];
    
    return $displayNames[$activityType] ?? ucfirst(str_replace('_', ' ', $activityType));
}

/**
 * Format points with proper number formatting
 */
function formatPoints($points) {
    return number_format($points);
}

/**
 * Get activity icon for display
 */
function getActivityIcon($activityType) {
    $icons = [
        'event_attendance' => 'fas fa-calendar-check',
        'report_resolved' => 'fas fa-flag',
        'daily_login' => 'fas fa-sign-in-alt',
        'referral' => 'fas fa-user-plus',
        'badge_bonus' => 'fas fa-medal',
        'shop_purchase' => 'fas fa-shopping-cart',
        'admin_adjustment' => 'fas fa-tools'
    ];
    
    return $icons[$activityType] ?? 'fas fa-coins';
}

/**
 * Check if user qualifies for leaderboards
 */
function userQualifiesForLeaderboards($userId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Check account age
    $query = "SELECT date_registered FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) return false;
    
    $user = $result->fetch_assoc();
    $accountAge = (strtotime('now') - strtotime($user['date_registered'])) / (60 * 60 * 24);
    
    if ($accountAge < 7) return false; // Account must be at least 7 days old
    
    // Check minimum activity
    $activityQuery = "SELECT COUNT(*) as activity_count FROM eco_points_transactions 
                     WHERE user_id = ? AND points_awarded > 0";
    $stmt = $connection->prepare($activityQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $activityCount = $result->fetch_assoc()['activity_count'];
    
    return $activityCount >= 3; // Must have at least 3 point-earning activities
}

/**
 * ORGANIZATION INTEGRATION FUNCTIONS
 */

/**
 * Award organization milestone bonuses when organization reaches certain point thresholds
 */
function checkOrganizationMilestones($organization) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    if (empty($organization)) return;
    
    // Get organization's total points
    $orgQuery = "SELECT SUM(eco_points) as total_points FROM accountstbl WHERE organization = ?";
    $stmt = $connection->prepare($orgQuery);
    $stmt->bind_param("s", $organization);
    $stmt->execute();
    $result = $stmt->get_result();
    $orgData = $result->fetch_assoc();
    $totalPoints = $orgData['total_points'] ?? 0;
    $stmt->close();
    
    // Check which milestones have been reached
    $milestones = [1000 => 25, 5000 => 50, 10000 => 100, 25000 => 200, 50000 => 500];
    
    foreach ($milestones as $threshold => $bonus) {
        if ($totalPoints >= $threshold) {
            // Check if this milestone bonus has already been awarded
            $checkQuery = "SELECT COUNT(*) as count FROM eco_points_transactions 
                          WHERE activity_type = 'organization_milestone' 
                          AND reference_id = ? 
                          AND JSON_EXTRACT(metadata, '$.milestone') = ?";
            $stmt = $connection->prepare($checkQuery);
            $milestone_ref = crc32($organization . '_' . $threshold); // Create unique reference
            $stmt->bind_param("ii", $milestone_ref, $threshold);
            $stmt->execute();
            $result = $stmt->get_result();
            $alreadyAwarded = $result->fetch_assoc()['count'] > 0;
            $stmt->close();
            
            if (!$alreadyAwarded) {
                // Award milestone bonus to all organization members
                $membersQuery = "SELECT account_id FROM accountstbl WHERE organization = ?";
                $stmt = $connection->prepare($membersQuery);
                $stmt->bind_param("s", $organization);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($member = $result->fetch_assoc()) {
                    $description = "Organization milestone bonus: {$organization} reached {$threshold} total points";
                    $metadata = json_encode(['milestone' => $threshold, 'organization' => $organization]);
                    
                    // Award points using eco points system
                    EcoPointsSystem::adjustPoints($member['account_id'], $bonus, $description, 'system');
                    
                    // Log the milestone achievement
                    $logQuery = "INSERT INTO eco_points_transactions 
                                (user_id, activity_type, points_awarded, reference_id, description, metadata, created_at) 
                                VALUES (?, 'organization_milestone', ?, ?, ?, ?, NOW())";
                    $logStmt = $connection->prepare($logQuery);
                    $logStmt->bind_param("iiiss", $member['account_id'], $bonus, $milestone_ref, $description, $metadata);
                    $logStmt->execute();
                    $logStmt->close();
                }
                $stmt->close();
            }
        }
    }
}

/**
 * Award collaboration bonus when organization members participate together in events
 */
function awardOrganizationCollaborationBonus($eventId) {
    initializeEcoPointsSystem();
    
    global $connection;
    
    // Get organizations with multiple members attending this event
    $collabQuery = "SELECT a.organization, COUNT(*) as member_count
                    FROM attendeestbl att
                    JOIN accountstbl a ON att.account_id = a.account_id
                    WHERE att.event_id = ? 
                    AND a.organization IS NOT NULL 
                    AND a.organization != '' 
                    AND a.organization != 'N/A'
                    GROUP BY a.organization
                    HAVING member_count >= 2";
    $stmt = $connection->prepare($collabQuery);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($org = $result->fetch_assoc()) {
        $bonus = 5 * $org['member_count']; // 5 points per participating member
        
        // Award collaboration bonus to all participating members from this organization
        $participantsQuery = "SELECT att.account_id
                             FROM attendeestbl att
                             JOIN accountstbl a ON att.account_id = a.account_id
                             WHERE att.event_id = ? AND a.organization = ?";
        $pStmt = $connection->prepare($participantsQuery);
        $pStmt->bind_param("is", $eventId, $org['organization']);
        $pStmt->execute();
        $pResult = $pStmt->get_result();
        
        while ($participant = $pResult->fetch_assoc()) {
            $description = "Organization collaboration bonus: {$org['member_count']} members from {$org['organization']} attended together";
            $metadata = json_encode([
                'event_id' => $eventId,
                'organization' => $org['organization'],
                'participating_members' => $org['member_count']
            ]);
            
            // Award points
            EcoPointsSystem::adjustPoints($participant['account_id'], $bonus, $description, 'system');
        }
        $pStmt->close();
    }
    $stmt->close();
}

/**
 * Check and award organization milestones when a user earns points
 */
function checkUserOrganizationBonuses($userId) {
    global $connection;
    
    // Get user's organization
    $orgQuery = "SELECT organization FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($orgQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orgData = $result->fetch_assoc();
    $stmt->close();
    
    if ($orgData && !empty($orgData['organization'])) {
        // Check for milestone bonuses
        checkOrganizationMilestones($orgData['organization']);
    }
}

/**
 * Award weekly ranking bonuses to top organizations
 * This function should be called weekly via cron job
 */
function awardOrganizationRankingBonuses() {
    global $connection, $ecoPointsConfig;
    
    if (!$connection) {
        throw new Exception("Database connection not available");
    }
    
    // Get organization rankings based on total eco points
    $rankingQuery = "
        SELECT 
            organization,
            SUM(eco_points) as total_points,
            COUNT(*) as member_count
        FROM accountstbl 
        WHERE organization IS NOT NULL AND organization != '' 
        GROUP BY organization 
        ORDER BY total_points DESC
    ";
    
    $result = $connection->query($rankingQuery);
    
    if (!$result) {
        throw new Exception("Failed to fetch organization rankings: " . $connection->error);
    }
    
    $organizations = [];
    while ($row = $result->fetch_assoc()) {
        $organizations[] = $row;
    }
    
    $totalOrganizations = count($organizations);
    if ($totalOrganizations == 0) {
        return; // No organizations to process
    }
    
    $rankingBonuses = $ecoPointsConfig['organization_ranking_bonuses'];
    $bonusesAwarded = 0;
    
    // Award bonuses based on ranking
    for ($i = 0; $i < $totalOrganizations; $i++) {
        $organization = $organizations[$i];
        $rank = $i + 1;
        $bonusPerMember = 0;
        
        // Determine bonus amount
        if ($rank <= 3 && isset($rankingBonuses[$rank])) {
            $bonusPerMember = $rankingBonuses[$rank];
        } elseif ($rank <= ceil($totalOrganizations * 0.1)) {
            // Top 10%
            $bonusPerMember = $rankingBonuses['top_10_percent'] ?? 0;
        }
        
        if ($bonusPerMember > 0) {
            // Award bonus to all members of this organization
            $updateQuery = "
                UPDATE accountstbl 
                SET eco_points = eco_points + ? 
                WHERE organization = ?
            ";
            
            $stmt = $connection->prepare($updateQuery);
            if (!$stmt) {
                throw new Exception("Failed to prepare update query: " . $connection->error);
            }
            
            $stmt->bind_param("is", $bonusPerMember, $organization['organization']);
            
            if ($stmt->execute()) {
                $membersUpdated = $stmt->affected_rows;
                
                // Log the ranking bonus activity for each member
                $logQuery = "
                    INSERT INTO user_activity_log (user_id, activity_type, activity_details, points_awarded, created_at)
                    SELECT account_id, 'organization_ranking_bonus', ?, ?, NOW()
                    FROM accountstbl 
                    WHERE organization = ?
                ";
                
                $logStmt = $connection->prepare($logQuery);
                if ($logStmt) {
                    $activityDetails = "Weekly organization ranking bonus - Rank #" . $rank . " (" . $organization['organization'] . ")";
                    $logStmt->bind_param("sis", $activityDetails, $bonusPerMember, $organization['organization']);
                    $logStmt->execute();
                    $logStmt->close();
                }
                
                echo "Awarded $bonusPerMember points to $membersUpdated members of '{$organization['organization']}' (Rank #$rank)\n";
                $bonusesAwarded++;
                
            } else {
                throw new Exception("Failed to award bonus to organization '{$organization['organization']}': " . $stmt->error);
            }
            
            $stmt->close();
        }
    }
    
    echo "Total ranking bonuses awarded to $bonusesAwarded organizations\n";
}
?>
