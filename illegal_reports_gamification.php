<?php
/**
 * Illegal Activity Reports Gamification System
 * Handles eco points and badges for illegal activity reports
 */

class IllegalReportsGamification {
    private static $connection = null;
    
    // Point values configuration
    private static $pointsConfig = [
        'base_points' => [
            'Normal' => 25,    // Base points for normal priority
            'Emergency' => 50  // Base points for emergency priority
        ],
        'incident_multipliers' => [
            'Illegal Cutting' => 2.0,      // Most serious - 50/100 points
            'Fire' => 1.8,                 // Very serious - 45/90 points
            'Water Pollution' => 1.6,      // Serious - 40/80 points
            'Waste Dumping' => 1.4,        // Moderate - 35/70 points
            'Construction' => 1.2,         // Moderate - 30/60 points
            'Harmful Fishing' => 1.0,      // Base - 25/50 points
            'Other' => 0.8                 // Lower - 20/40 points
        ]
    ];
    
    public static function init($dbConnection) {
        self::$connection = $dbConnection;
    }
    
    /**
     * Award eco points and badges when a report is marked as Resolved
     */
    public static function processResolvedReport($reportId, $rating, $adminId) {
        if (!self::$connection) {
            throw new Exception("Database connection not initialized");
        }
        
        // Get report details
        $reportQuery = "SELECT * FROM illegalreportstbl WHERE report_id = ?";
        $stmt = self::$connection->prepare($reportQuery);
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Report not found");
        }
        
        $report = $result->fetch_assoc();
        
        // Only process if report is resolved and has a reporter
        if ($report['action_type'] !== 'Resolved' || empty($report['reporter_id'])) {
            return false;
        }
        
        // Calculate eco points based on priority, incident type, and rating
        $points = self::calculatePoints($report['priority'], $report['incident_type'], $rating);
        
        // Award eco points using the existing EcoPointsSystem
        require_once 'eco_points_integration.php';
        initializeEcoPointsSystem();
        
        $pointsResult = EcoPointsSystem::awardReportPoints($reportId, $report['reporter_id'], $report['priority']);
        
        // Update the report with rating and points info
        $updateQuery = "UPDATE illegalreportstbl 
                       SET rating = ?, rated_by = ?, rated_at = NOW(), points_awarded = ? 
                       WHERE report_id = ?";
        $updateStmt = self::$connection->prepare($updateQuery);
        $updateStmt->bind_param("iiii", $rating, $adminId, $points, $reportId);
        $updateStmt->execute();
        
        // Check and award badges
        $badgeAwarded = self::checkAndAwardBadges($report['reporter_id']);
        
        // Update badge info if any badge was awarded
        if ($badgeAwarded) {
            $badgeUpdateQuery = "UPDATE illegalreportstbl SET badge_awarded = ? WHERE report_id = ?";
            $badgeStmt = self::$connection->prepare($badgeUpdateQuery);
            $badgeStmt->bind_param("si", $badgeAwarded, $reportId);
            $badgeStmt->execute();
        }
        
        return [
            'points_awarded' => $points,
            'badge_awarded' => $badgeAwarded,
            'success' => true
        ];
    }
    
    /**
     * Calculate points based on priority, incident type, and rating
     */
    private static function calculatePoints($priority, $incidentType, $rating) {
        $basePoints = self::$pointsConfig['base_points'][$priority] ?? self::$pointsConfig['base_points']['Normal'];
        $multiplier = self::$pointsConfig['incident_multipliers'][$incidentType] ?? 1.0;
        
        // Apply incident type multiplier
        $points = $basePoints * $multiplier;
        
        // Apply rating multiplier (rating affects final points)
        $ratingMultiplier = $rating / 5.0; // 1-5 stars becomes 0.2-1.0 multiplier
        $points = $points * $ratingMultiplier;
        
        return round($points);
    }
    
    /**
     * Check and award badges based on user's report statistics
     */
    private static function checkAndAwardBadges($userId) {
        require_once 'badge_system_db.php';
        BadgeSystem::init(self::$connection);
        
        $badgeAwarded = null;
        
        // Check for resolved reports badges
        $resolvedCount = self::getResolvedReportsCount($userId);
        $resolvedBadge = self::checkResolvedReportsBadges($resolvedCount);
        if ($resolvedBadge && !self::userHasBadge($userId, $resolvedBadge)) {
            BadgeSystem::awardBadgeToUser($userId, $resolvedBadge);
            $badgeAwarded = $resolvedBadge;
        }
        
        // Check for submission count badges
        $submissionCount = self::getSubmissionCount($userId);
        $submissionBadge = self::checkSubmissionBadges($submissionCount);
        if ($submissionBadge && !self::userHasBadge($userId, $submissionBadge)) {
            BadgeSystem::awardBadgeToUser($userId, $submissionBadge);
            $badgeAwarded = $submissionBadge;
        }
        
        return $badgeAwarded;
    }
    
    /**
     * Check if user has a specific badge
     */
    private static function userHasBadge($userId, $badgeName) {
        $query = "SELECT badges FROM accountstbl WHERE account_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $row = $result->fetch_assoc();
        $userBadges = $row['badges'];
        
        if (empty($userBadges)) {
            return false;
        }
        
        $badgeArray = explode(',', $userBadges);
        $badgeArray = array_map('trim', $badgeArray);
        
        return in_array($badgeName, $badgeArray);
    }
    
    /**
     * Get count of resolved reports for a user
     */
    private static function getResolvedReportsCount($userId) {
        $query = "SELECT COUNT(*) as count FROM illegalreportstbl 
                 WHERE reporter_id = ? AND action_type = 'Resolved'";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    /**
     * Get total submission count for a user
     */
    private static function getSubmissionCount($userId) {
        $query = "SELECT COUNT(*) as count FROM illegalreportstbl WHERE reporter_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    /**
     * Check which resolved reports badge to award
     */
    private static function checkResolvedReportsBadges($count) {
        $badges = [
            100 => 'Vigilant Guardian',
            50 => 'Super Watchdog',
            20 => 'Community Protector',
            10 => 'Alert Citizen',
            5 => 'Watchful Eye',
            1 => 'First Resolution'
        ];
        
        foreach ($badges as $threshold => $badge) {
            if ($count >= $threshold) {
                return $badge;
            }
        }
        
        return null;
    }
    
    /**
     * Check which submission badge to award
     */
    private static function checkSubmissionBadges($count) {
        $badges = [
            100 => 'Report Master',
            50 => 'Environmental Crusader',
            20 => 'Dedicated Reporter',
            10 => 'Active Citizen',
            5 => 'Report Veteran'
            // Note: 'Mangrove Guardian' for 1 report already exists
        ];
        
        foreach ($badges as $threshold => $badge) {
            if ($count >= $threshold) {
                return $badge;
            }
        }
        
        return null;
    }
    
    /**
     * Get user's notification about earned points/badges
     */
    public static function getUserReportNotifications($userId) {
        $query = "SELECT ir.report_id, ir.points_awarded, ir.badge_awarded, ir.rated_at, ir.incident_type, ir.priority, ir.rating
                 FROM illegalreportstbl ir
                 WHERE ir.reporter_id = ? AND ir.action_type = 'Resolved' AND ir.rated_at >= DATE_SUB(NOW(), INTERVAL 7 DAYS)
                 ORDER BY ir.rated_at DESC";
        
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = [
                'report_id' => $row['report_id'],
                'points_awarded' => $row['points_awarded'],
                'badge_awarded' => $row['badge_awarded'],
                'rated_at' => $row['rated_at'],
                'incident_type' => $row['incident_type'],
                'priority' => $row['priority'],
                'rating' => $row['rating']
            ];
        }
        
        return $notifications;
    }
}
?>
