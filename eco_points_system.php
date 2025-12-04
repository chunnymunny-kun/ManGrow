<?php
/**
 * ManGrow Eco Points System
 * Comprehensive gamification system for environmental activities
 * 
 * Features:
 * - Point awarding for various activities
 * - Transaction logging and audit trail
 * - Integration with badge system
 * - Anti-fraud measures and validation
 * - Economy balance monitoring
 */

class EcoPointsSystem {
    private static $connection = null;
    private static $config = [];
    
    // Point activity types
    const ACTIVITY_EVENT_ATTENDANCE = 'event_attendance';
    const ACTIVITY_REPORT_RESOLVED = 'report_resolved';
    const ACTIVITY_DAILY_LOGIN = 'daily_login';
    const ACTIVITY_REFERRAL = 'referral';
    const ACTIVITY_BADGE_BONUS = 'badge_bonus';
    const ACTIVITY_SHOP_PURCHASE = 'shop_purchase';
    const ACTIVITY_ADMIN_ADJUSTMENT = 'admin_adjustment';
    const ACTIVITY_ORGANIZATION_JOIN = 'organization_join';
    
    /**
     * Initialize the eco points system
     */
    public static function init($dbConnection, $config = []) {
        self::$connection = $dbConnection;
        self::$config = array_merge([
            'event_base_points' => 50,
            'report_base_points' => 25,
            'daily_login_points' => 5,
            'referral_points' => 50,
            'badge_bonus_percentage' => 15,
            'max_daily_points' => 500,
            'min_account_age_days' => 1
        ], $config);
        
        // Create necessary tables if they don't exist
        self::createTables();
    }
    
    /**
     * Create necessary database tables
     */
    private static function createTables() {
        $tables = [
            'eco_points_transactions' => "
                CREATE TABLE IF NOT EXISTS eco_points_transactions (
                    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    points_awarded INT NOT NULL DEFAULT 0,
                    points_spent INT NOT NULL DEFAULT 0,
                    activity_type ENUM('event_attendance', 'report_resolved', 'daily_login', 'referral', 'badge_bonus', 'shop_purchase', 'admin_adjustment') NOT NULL,
                    reference_id INT NULL,
                    description TEXT,
                    metadata JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE
                )",
            'login_streaks' => "
                CREATE TABLE IF NOT EXISTS login_streaks (
                    streak_id INT PRIMARY KEY AUTO_INCREMENT,
                    user_id INT NOT NULL,
                    current_streak INT DEFAULT 0,
                    longest_streak INT DEFAULT 0,
                    last_login_date DATE,
                    streak_start_date DATE,
                    total_logins INT DEFAULT 0,
                    UNIQUE KEY unique_user (user_id),
                    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE
                )",
            'referrals' => "
                CREATE TABLE IF NOT EXISTS referrals (
                    referral_id INT PRIMARY KEY AUTO_INCREMENT,
                    referrer_id INT NOT NULL,
                    referred_id INT NOT NULL,
                    referral_code VARCHAR(50),
                    points_awarded INT DEFAULT 0,
                    status ENUM('pending', 'completed', 'expired') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP NULL,
                    UNIQUE KEY unique_referral (referrer_id, referred_id),
                    FOREIGN KEY (referrer_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
                    FOREIGN KEY (referred_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE
                )"
        ];
        
        foreach ($tables as $tableName => $sql) {
            if (!self::tableExists($tableName)) {
                if (self::$connection->query($sql)) {
                    error_log("EcoPointsSystem: Created table $tableName");
                } else {
                    error_log("EcoPointsSystem: Failed to create table $tableName: " . self::$connection->error);
                }
            }
        }
    }
    
    /**
     * Check if table exists
     */
    private static function tableExists($tableName) {
        $result = self::$connection->query("SHOW TABLES LIKE '$tableName'");
        return $result && $result->num_rows > 0;
    }
    
    /**
     * Award points for event attendance
     */
    public static function awardEventPoints($eventId, $userId, $customPoints = null) {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Check if user already received points for this event
        if (self::hasUserReceivedEventPoints($userId, $eventId)) {
            return ['success' => false, 'message' => 'Points already awarded for this event'];
        }
        
        // Get event details
        $eventQuery = "SELECT subject, eco_points, start_date FROM eventstbl WHERE event_id = ?";
        $stmt = self::$connection->prepare($eventQuery);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $eventResult = $stmt->get_result();
        
        if ($eventResult->num_rows === 0) {
            return ['success' => false, 'message' => 'Event not found'];
        }
        
        $event = $eventResult->fetch_assoc();
        $points = $customPoints ?? ($event['eco_points'] ?: self::$config['event_base_points']);
        
        // Award points
        $result = self::addPoints(
            $userId, 
            $points, 
            self::ACTIVITY_EVENT_ATTENDANCE, 
            $eventId,
            "Attended event: " . $event['subject'],
            ['event_name' => $event['subject'], 'event_date' => $event['start_date']]
        );
        
        if ($result['success']) {
            // Check for badge achievements
            self::checkEventBadges($userId);
        }
        
        return $result;
    }
    
    /**
     * Award points for resolved reports
     */
    public static function awardReportPoints($reportId, $userId, $priority = 'Normal') {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Check if points already awarded for this report
        if (self::hasUserReceivedReportPoints($userId, $reportId)) {
            return ['success' => false, 'message' => 'Points already awarded for this report'];
        }
        
        // Calculate points based on priority
        $pointMultiplier = [
            'Emergency' => 2.0,
            'Normal' => 1.0
        ];
        
        $basePoints = self::$config['report_base_points'];
        $points = (int)($basePoints * ($pointMultiplier[$priority] ?? 1.0));
        
        // Award points
        $result = self::addPoints(
            $userId, 
            $points, 
            self::ACTIVITY_REPORT_RESOLVED, 
            $reportId,
            "Report resolved (Priority: $priority)",
            ['priority' => $priority, 'base_points' => $basePoints, 'multiplier' => $pointMultiplier[$priority] ?? 1.0]
        );
        
        if ($result['success']) {
            // Update report with points awarded
            $updateQuery = "UPDATE illegalreportstbl SET points_awarded = ? WHERE report_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("ii", $points, $reportId);
            $stmt->execute();
            
            // Check for badge achievements
            $badgeAwarded = self::checkReportBadges($userId);
            
            // Add points_awarded to result
            $result['points_awarded'] = $points;
            
            // Add badge information if awarded and update database
            if ($badgeAwarded) {
                $result['badge_awarded'] = $badgeAwarded;
                
                // Update the report with badge information
                $updateBadgeQuery = "UPDATE illegalreportstbl SET badge_awarded = ? WHERE report_id = ?";
                $stmt = self::$connection->prepare($updateBadgeQuery);
                $stmt->bind_param("si", $badgeAwarded, $reportId);
                $stmt->execute();
            }
        }
        
        return $result;
    }
    
    /**
     * Award report points with custom calculation (e.g., based on admin rating)
     */
    public static function awardReportPointsWithRating($reportId, $userId, $priority = 'Normal', $rating = 5) {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Check if points already awarded for this report
        if (self::hasUserReceivedReportPoints($userId, $reportId)) {
            return ['success' => false, 'message' => 'Points already awarded for this report'];
        }
        
        // Validate rating
        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5 stars'];
        }
        
        // Calculate points based on rating and priority
        // Emergency: 50 points max (5 stars), Normal: 25 points max (5 stars)
        $maxPoints = ($priority === 'Emergency') ? 50 : 25;
        
        // Calculate rating multiplier (rating/5 = percentage of max points)
        $ratingMultiplier = $rating / 5.0;
        $points = (int)round($maxPoints * $ratingMultiplier);
        
        // Ensure minimum points for any valid rating (1-5 stars)
        // Even 1-star ratings should give at least some points as acknowledgment
        if ($points < 1 && $rating >= 1) {
            $points = 1; // Minimum 1 point for any valid rating
        }
        
        // Award points
        $result = self::addPoints(
            $userId, 
            $points, 
            self::ACTIVITY_REPORT_RESOLVED, 
            $reportId,
            "Report resolved with {$rating}-star rating (Priority: $priority)",
            [
                'priority' => $priority, 
                'rating' => $rating,
                'max_points' => $maxPoints,
                'rating_multiplier' => $ratingMultiplier,
                'final_points' => $points
            ]
        );
        
        if ($result['success']) {
            // Update report with points awarded
            $updateQuery = "UPDATE illegalreportstbl SET points_awarded = ? WHERE report_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("ii", $points, $reportId);
            $stmt->execute();
            
            // Check for badge achievements
            $badgeAwarded = self::checkReportBadges($userId);
            
            // Add points_awarded to result
            $result['points_awarded'] = $points;
            $result['rating'] = $rating;
            $result['max_possible_points'] = $maxPoints;
            
            // Add badge information if awarded and update database
            if ($badgeAwarded) {
                $result['badge_awarded'] = $badgeAwarded;
                
                // Update the report with badge information
                $updateBadgeQuery = "UPDATE illegalreportstbl SET badge_awarded = ? WHERE report_id = ?";
                $stmt = self::$connection->prepare($updateBadgeQuery);
                $stmt->bind_param("si", $badgeAwarded, $reportId);
                $stmt->execute();
            }
        }
        
        return $result;
    }
    
    /**
     * Award daily login points
     */
    public static function awardDailyLogin($userId) {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        $today = date('Y-m-d');
        
        // Check if user already logged in today
        if (self::hasUserLoggedInToday($userId, $today)) {
            return ['success' => false, 'message' => 'Daily login already recorded'];
        }
        
        // Get or create login streak record
        $streak = self::updateLoginStreak($userId, $today);
        
        // Calculate points based on streak
        $basePoints = self::$config['daily_login_points'];
        $streakBonus = min($streak['current_streak'] - 1, 6); // Max 6 days bonus
        $points = $basePoints + $streakBonus;
        
        $result = self::addPoints(
            $userId, 
            $points, 
            self::ACTIVITY_DAILY_LOGIN, 
            null,
            "Daily login (Day $streak[current_streak] of streak)",
            ['streak_day' => $streak['current_streak'], 'base_points' => $basePoints, 'streak_bonus' => $streakBonus]
        );
        
        return $result;
    }
    
    /**
     * Award referral points
     */
    public static function awardReferralPoints($referrerId, $referredId) {
        if (!self::validateUser($referrerId) || !self::validateUser($referredId)) {
            return ['success' => false, 'message' => 'Invalid user(s)'];
        }
        
        // Check if referral already exists and completed
        $checkQuery = "SELECT status FROM referrals WHERE referrer_id = ? AND referred_id = ?";
        $stmt = self::$connection->prepare($checkQuery);
        $stmt->bind_param("ii", $referrerId, $referredId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $referral = $result->fetch_assoc();
            if ($referral['status'] === 'completed') {
                return ['success' => false, 'message' => 'Referral points already awarded'];
            }
        }
        
        $points = self::$config['referral_points'];
        
        // Award points to referrer
        $result = self::addPoints(
            $referrerId, 
            $points, 
            self::ACTIVITY_REFERRAL, 
            $referredId,
            "Successful referral",
            ['referred_user_id' => $referredId]
        );
        
        if ($result['success']) {
            // Update or create referral record
            $updateQuery = "INSERT INTO referrals (referrer_id, referred_id, points_awarded, status, completed_at) 
                           VALUES (?, ?, ?, 'completed', CURRENT_TIMESTAMP)
                           ON DUPLICATE KEY UPDATE 
                           points_awarded = VALUES(points_awarded), 
                           status = VALUES(status), 
                           completed_at = VALUES(completed_at)";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("iii", $referrerId, $referredId, $points);
            $stmt->execute();
        }
        
        return $result;
    }
    
    /**
     * Award bonus points for badge achievement
     */
    public static function awardBadgeBonus($userId, $badgeName, $badgeRarity = 'Common') {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Calculate bonus based on badge rarity
        $rarityMultiplier = [
            'Common' => 1.0,
            'Uncommon' => 1.5,
            'Rare' => 2.0,
            'Epic' => 3.0,
            'Legendary' => 5.0
        ];
        
        $baseBonus = 25; // Base badge bonus points
        $points = (int)($baseBonus * ($rarityMultiplier[$badgeRarity] ?? 1.0));
        
        $result = self::addPoints(
            $userId, 
            $points, 
            self::ACTIVITY_BADGE_BONUS, 
            null,
            "Badge achievement bonus: $badgeName",
            ['badge_name' => $badgeName, 'rarity' => $badgeRarity, 'base_bonus' => $baseBonus]
        );
        
        return $result;
    }
    
    /**
     * Spend points for shop purchases
     */
    public static function spendPoints($userId, $points, $itemId, $itemName) {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Check if user has enough points
        $userPoints = self::getUserPoints($userId);
        if ($userPoints['current_points'] < $points) {
            return ['success' => false, 'message' => 'Insufficient points'];
        }
        
        // Deduct points
        $result = self::deductPoints(
            $userId, 
            $points, 
            self::ACTIVITY_SHOP_PURCHASE, 
            $itemId,
            "Purchased: $itemName",
            ['item_name' => $itemName, 'item_id' => $itemId]
        );
        
        return $result;
    }
    
    /**
     * Core function to add points
     */
    private static function addPoints($userId, $points, $activityType, $referenceId = null, $description = '', $metadata = []) {
        if ($points <= 0) {
            return ['success' => false, 'message' => 'Invalid point amount'];
        }
        
        // Check daily limit
        if (!self::checkDailyLimit($userId, $points)) {
            return ['success' => false, 'message' => 'Daily point limit exceeded'];
        }
        
        try {
            self::$connection->begin_transaction();
            
            // Strong duplicate guard for daily login within the same day (prevents race across pages)
            if ($activityType === self::ACTIVITY_DAILY_LOGIN) {
                $dupCheck = self::$connection->prepare(
                    "SELECT transaction_id FROM eco_points_transactions 
                     WHERE user_id = ? AND activity_type = ? AND DATE(created_at) = CURDATE() 
                     LIMIT 1 FOR UPDATE"
                );
                $dupCheck->bind_param("is", $userId, $activityType);
                $dupCheck->execute();
                $dupRes = $dupCheck->get_result();
                if ($dupRes && $dupRes->num_rows > 0) {
                    // Already awarded today – abort gracefully
                    self::$connection->rollback();
                    return ['success' => false, 'message' => 'Daily login already recorded'];
                }
            }
            
            // Insert transaction record
            $transactionQuery = "INSERT INTO eco_points_transactions 
                               (user_id, points_awarded, activity_type, reference_id, description, metadata) 
                               VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = self::$connection->prepare($transactionQuery);
            $metadataJson = json_encode($metadata);
            $stmt->bind_param("iisiss", $userId, $points, $activityType, $referenceId, $description, $metadataJson);
            $stmt->execute();
            
            // Update user's total points
            $updateQuery = "UPDATE accountstbl SET 
                           eco_points = eco_points + ?, 
                           total_eco_points = total_eco_points + ? 
                           WHERE account_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("iii", $points, $points, $userId);
            $stmt->execute();
            
            self::$connection->commit();
            
            return [
                'success' => true, 
                'points_awarded' => $points,
                'new_total' => self::getUserPoints($userId)['current_points']
            ];
            
        } catch (Exception $e) {
            self::$connection->rollback();
            error_log("EcoPointsSystem: Error adding points - " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Special function to add points bypassing daily limits (for milestone rewards like first organization join)
     */
    private static function addPointsBypassDailyLimit($userId, $points, $activityType, $referenceId = null, $description = '', $metadata = []) {
        if ($points <= 0) {
            return ['success' => false, 'message' => 'Invalid point amount'];
        }
        
        try {
            self::$connection->begin_transaction();
            
            // Insert transaction record
            $transactionQuery = "INSERT INTO eco_points_transactions 
                               (user_id, points_awarded, activity_type, reference_id, description, metadata) 
                               VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = self::$connection->prepare($transactionQuery);
            $metadataJson = json_encode($metadata);
            $stmt->bind_param("iisiss", $userId, $points, $activityType, $referenceId, $description, $metadataJson);
            $stmt->execute();
            
            // Update user's total points
            $updateQuery = "UPDATE accountstbl SET 
                           eco_points = eco_points + ?, 
                           total_eco_points = total_eco_points + ? 
                           WHERE account_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("iii", $points, $points, $userId);
            $stmt->execute();
            
            self::$connection->commit();
            
            return [
                'success' => true, 
                'points_awarded' => $points,
                'new_total' => self::getUserPoints($userId)['current_points']
            ];
            
        } catch (Exception $e) {
            self::$connection->rollback();
            error_log("EcoPointsSystem: Error adding points (bypass daily limit) - " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Core function to deduct points
     */
    private static function deductPoints($userId, $points, $activityType, $referenceId = null, $description = '', $metadata = []) {
        if ($points <= 0) {
            return ['success' => false, 'message' => 'Invalid point amount'];
        }
        
        try {
            self::$connection->begin_transaction();
            
            // Insert transaction record
            $transactionQuery = "INSERT INTO eco_points_transactions 
                               (user_id, points_spent, activity_type, reference_id, description, metadata) 
                               VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = self::$connection->prepare($transactionQuery);
            $metadataJson = json_encode($metadata);
            $stmt->bind_param("iisiss", $userId, $points, $activityType, $referenceId, $description, $metadataJson);
            $stmt->execute();
            
            // Update user's current points (but not total_eco_points which tracks lifetime earnings)
            $updateQuery = "UPDATE accountstbl SET eco_points = eco_points - ? WHERE account_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("ii", $points, $userId);
            $stmt->execute();
            
            self::$connection->commit();
            
            return [
                'success' => true, 
                'points_spent' => $points,
                'new_total' => self::getUserPoints($userId)['current_points']
            ];
            
        } catch (Exception $e) {
            self::$connection->rollback();
            error_log("EcoPointsSystem: Error deducting points - " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Get user's point information
     */
    public static function getUserPoints($userId) {
        $query = "SELECT eco_points as current_points, total_eco_points, account_id 
                  FROM accountstbl WHERE account_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['current_points' => 0, 'total_eco_points' => 0];
        }
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get user's point transaction history
     */
    public static function getUserPointHistory($userId, $limit = 20, $offset = 0) {
        $query = "SELECT * FROM eco_points_transactions 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $row['metadata'] = json_decode($row['metadata'], true);
            $transactions[] = $row;
        }
        
        return $transactions;
    }
    
    /**
     * Validation functions
     */
    private static function validateUser($userId) {
        $query = "SELECT account_id, date_registered FROM accountstbl WHERE account_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $user = $result->fetch_assoc();
        $accountAge = (strtotime('now') - strtotime($user['date_registered'])) / (60 * 60 * 24);
        
        return $accountAge >= self::$config['min_account_age_days'];
    }
    
    private static function checkDailyLimit($userId, $additionalPoints) {
        $today = date('Y-m-d');
        $query = "SELECT SUM(points_awarded) as daily_total 
                  FROM eco_points_transactions 
                  WHERE user_id = ? AND DATE(created_at) = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dailyTotal = $result->fetch_assoc()['daily_total'] ?? 0;
        
        return ($dailyTotal + $additionalPoints) <= self::$config['max_daily_points'];
    }
    
    /**
     * Check functions for duplicate awards
     */
    private static function hasUserReceivedEventPoints($userId, $eventId) {
        $query = "SELECT transaction_id FROM eco_points_transactions 
                  WHERE user_id = ? AND activity_type = ? AND reference_id = ?";
        $stmt = self::$connection->prepare($query);
        $activity = self::ACTIVITY_EVENT_ATTENDANCE;
        $stmt->bind_param("isi", $userId, $activity, $eventId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private static function hasUserReceivedReportPoints($userId, $reportId) {
        $query = "SELECT transaction_id FROM eco_points_transactions 
                  WHERE user_id = ? AND activity_type = ? AND reference_id = ?";
        $stmt = self::$connection->prepare($query);
        $activity = self::ACTIVITY_REPORT_RESOLVED;
        $stmt->bind_param("isi", $userId, $activity, $reportId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    private static function hasUserLoggedInToday($userId, $date) {
        $query = "SELECT transaction_id FROM eco_points_transactions 
                  WHERE user_id = ? AND activity_type = ? AND DATE(created_at) = ?";
        $stmt = self::$connection->prepare($query);
        $activity = self::ACTIVITY_DAILY_LOGIN;
        $stmt->bind_param("iss", $userId, $activity, $date);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Login streak management
     */
    private static function updateLoginStreak($userId, $date) {
        $query = "SELECT * FROM login_streaks WHERE user_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // First login
            $insertQuery = "INSERT INTO login_streaks (user_id, current_streak, longest_streak, last_login_date, streak_start_date, total_logins) 
                           VALUES (?, 1, 1, ?, ?, 1)";
            $stmt = self::$connection->prepare($insertQuery);
            $stmt->bind_param("iss", $userId, $date, $date);
            $stmt->execute();
            
            return ['current_streak' => 1, 'longest_streak' => 1, 'total_logins' => 1];
        }
        
        $streak = $result->fetch_assoc();
        $lastLogin = $streak['last_login_date'];
        $yesterday = date('Y-m-d', strtotime($date . ' -1 day'));
        
        if ($lastLogin === $yesterday) {
            // Consecutive login
            $newStreak = $streak['current_streak'] + 1;
            $newLongest = max($newStreak, $streak['longest_streak']);
        } else {
            // Streak broken, start new
            $newStreak = 1;
            $newLongest = $streak['longest_streak'];
        }
        
        $updateQuery = "UPDATE login_streaks SET 
                       current_streak = ?, 
                       longest_streak = ?, 
                       last_login_date = ?, 
                       total_logins = total_logins + 1,
                       streak_start_date = IF(? = 1, ?, streak_start_date)
                       WHERE user_id = ?";
        $stmt = self::$connection->prepare($updateQuery);
        $stmt->bind_param("iisisi", $newStreak, $newLongest, $date, $newStreak, $date, $userId);
        $stmt->execute();
        
        return ['current_streak' => $newStreak, 'longest_streak' => $newLongest, 'total_logins' => $streak['total_logins'] + 1];
    }
    
    /**
     * Badge integration functions (placeholder - implement based on your badge system)
     */
    private static function checkEventBadges($userId) {
        // This would integrate with your existing badge system
        // Check for badges like "Event Organizer", "Frequent Attendee", etc.
        
        if (class_exists('BadgeSystem')) {
            // Get user's event attendance count
            $query = "SELECT COUNT(*) as event_count FROM eco_points_transactions 
                      WHERE user_id = ? AND activity_type = ?";
            $stmt = self::$connection->prepare($query);
            $activity = self::ACTIVITY_EVENT_ATTENDANCE;
            $stmt->bind_param("is", $userId, $activity);
            $stmt->execute();
            $result = $stmt->get_result();
            $eventCount = $result->fetch_assoc()['event_count'];
            
            // Example badge checks (implement based on your badge criteria)
            if ($eventCount === 1) {
                // Award "First Event" badge
            } elseif ($eventCount === 5) {
                // Award "Event Enthusiast" badge
            } elseif ($eventCount === 10) {
                // Award "Event Regular" badge
            }
        }
    }
    
    public static function checkReportBadges($userId) {
        // Check if BadgeSystem is available
        if (!class_exists('BadgeSystem')) {
            return false;
        }
        
        // Count resolved reports for this user (reports they submitted that were resolved)
        $resolvedQuery = "SELECT COUNT(*) as report_count FROM illegalreportstbl 
                         WHERE reporter_id = ? AND action_type = 'Resolved' AND points_awarded > 0";
        $stmt = self::$connection->prepare($resolvedQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $resolvedCount = $result->fetch_assoc()['report_count'];
        
        // Count total submitted reports for this user
        $submittedQuery = "SELECT COUNT(*) as report_count FROM illegalreportstbl 
                          WHERE reporter_id = ?";
        $stmt = self::$connection->prepare($submittedQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $submittedCount = $result->fetch_assoc()['report_count'];
        
        // Get current user badges
        $userQuery = "SELECT badges FROM accountstbl WHERE account_id = ?";
        $stmt = self::$connection->prepare($userQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $userResult = $stmt->get_result();
        $userBadges = $userResult->fetch_assoc()['badges'] ?? '';
        $currentBadges = array_filter(explode(',', $userBadges));
        
        $newBadge = null;
        
        // Badge milestones for resolved reports (reports they submitted that got resolved)
        if ($resolvedCount >= 100 && !in_array('Report Veteran', $currentBadges)) {
            $newBadge = 'Report Veteran';
        } elseif ($resolvedCount >= 50 && !in_array('Vigilant Guardian', $currentBadges)) {
            $newBadge = 'Vigilant Guardian';
        } elseif ($resolvedCount >= 25 && !in_array('Super Watchdog', $currentBadges)) {
            $newBadge = 'Super Watchdog';
        } elseif ($resolvedCount >= 10 && !in_array('Community Protector', $currentBadges)) {
            $newBadge = 'Community Protector';
        } elseif ($resolvedCount >= 5 && !in_array('Alert Citizen', $currentBadges)) {
            $newBadge = 'Alert Citizen';
        } elseif ($resolvedCount >= 1 && !in_array('First Resolution', $currentBadges)) {
            $newBadge = 'First Resolution';
        }
        
        // If no resolved report badge, check for submission badges
        if (!$newBadge) {
            if ($submittedCount >= 100 && !in_array('Report Master', $currentBadges)) {
                $newBadge = 'Report Master';
            } elseif ($submittedCount >= 50 && !in_array('Environmental Crusader', $currentBadges)) {
                $newBadge = 'Environmental Crusader';
            } elseif ($submittedCount >= 20 && !in_array('Dedicated Reporter', $currentBadges)) {
                $newBadge = 'Dedicated Reporter';
            } elseif ($submittedCount >= 10 && !in_array('Active Citizen', $currentBadges)) {
                $newBadge = 'Active Citizen';
            } elseif ($submittedCount >= 1 && !in_array('Mangrove Guardian', $currentBadges)) {
                $newBadge = 'Mangrove Guardian';
            }
        }
        
        // Award the badge if a new one is earned
        if ($newBadge) {
            $currentBadges[] = $newBadge;
            $newBadgesString = implode(',', array_filter($currentBadges));
            
            $updateQuery = "UPDATE accountstbl SET badges = ? WHERE account_id = ?";
            $stmt = self::$connection->prepare($updateQuery);
            $stmt->bind_param("si", $newBadgesString, $userId);
            $stmt->execute();
            
            // Store badge notification in session
            $_SESSION['new_badge_awarded'] = [
                'badge_awarded' => true,
                'badge_name' => $newBadge,
                'badge_type' => 'reporting',
                'timestamp' => time()
            ];
            
            return $newBadge;
        }
        
        return false;
    }
    
    /**
     * Award points for joining an organization for the first time
     */
    public static function awardOrganizationJoinPoints($userId, $organizationId, $customPoints = 100) {
        if (!self::validateUser($userId)) {
            return ['success' => false, 'message' => 'Invalid user'];
        }
        
        // Check if user has already received organization join points
        $checkQuery = "SELECT transaction_id FROM eco_points_transactions 
                      WHERE user_id = ? AND activity_type = ? 
                      LIMIT 1";
        $stmt = self::$connection->prepare($checkQuery);
        $activityType = self::ACTIVITY_ORGANIZATION_JOIN;
        $stmt->bind_param("is", $userId, $activityType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return ['success' => false, 'message' => 'User has already received organization join points'];
        }
        
        $points = $customPoints;
        $description = "First-time organization join bonus";
        $metadata = [
            'organization_id' => $organizationId,
            'join_type' => 'first_time',
            'points_awarded' => $points
        ];
        
        $result = self::addPointsBypassDailyLimit($userId, $points, self::ACTIVITY_ORGANIZATION_JOIN, $organizationId, $description, $metadata);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Add transaction_id to the response for compatibility
            $result['transaction_id'] = self::$connection->insert_id ?? 'unknown';
        }
        
        return $result;
    }
    
    /**
     * Administrative functions
     */
    public static function adjustPoints($userId, $points, $reason, $adminId) {
        $description = "Admin adjustment: $reason (by admin $adminId)";
        
        if ($points > 0) {
            return self::addPoints($userId, $points, self::ACTIVITY_ADMIN_ADJUSTMENT, $adminId, $description, ['admin_id' => $adminId, 'reason' => $reason]);
        } else {
            return self::deductPoints($userId, abs($points), self::ACTIVITY_ADMIN_ADJUSTMENT, $adminId, $description, ['admin_id' => $adminId, 'reason' => $reason]);
        }
    }
    
    /**
     * Get system statistics
     */
    public static function getSystemStats() {
        $stats = [];
        
        // Total points in circulation
        $query = "SELECT SUM(eco_points) as total_current, SUM(total_eco_points) as total_lifetime FROM accountstbl";
        $result = self::$connection->query($query);
        $pointStats = $result->fetch_assoc();
        
        // Transaction counts by type
        $query = "SELECT activity_type, COUNT(*) as count, SUM(points_awarded) as total_points 
                  FROM eco_points_transactions 
                  GROUP BY activity_type";
        $result = self::$connection->query($query);
        $activityStats = [];
        while ($row = $result->fetch_assoc()) {
            $activityStats[$row['activity_type']] = $row;
        }
        
        return [
            'total_current_points' => $pointStats['total_current'] ?? 0,
            'total_lifetime_points' => $pointStats['total_lifetime'] ?? 0,
            'activity_breakdown' => $activityStats
        ];
    }
}
?>
