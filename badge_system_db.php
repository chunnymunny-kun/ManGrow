<?php
/**
 * Database-Driven Badge System for ManGrow
 * Manages badge definitions, images, and descriptions from database
 */

class BadgeSystem {
    private static $connection = null;
    private static $badgeCache = [];
    private static $cacheLoaded = false;

    /**
     * Initialize the badge system with database connection
     */
    public static function init($dbConnection) {
        self::$connection = $dbConnection;
        self::loadBadgesFromDatabase();
    }

    /**
     * Load all badges from database into cache
     */
    private static function loadBadgesFromDatabase() {
        if (self::$cacheLoaded || !self::$connection) {
            return;
        }

        $query = "SELECT badge_id, badge_name, description, instructions, image_path, icon_class, color, category 
                  FROM badgestbl 
                  WHERE is_active = 1 
                  ORDER BY badge_name";
        
        $result = self::$connection->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                self::$badgeCache[$row['badge_name']] = [
                    'id' => $row['badge_id'],
                    'name' => $row['badge_name'],
                    'description' => $row['description'],
                    'instructions' => $row['instructions'],
                    'image' => $row['image_path'],
                    'icon' => $row['icon_class'],
                    'color' => $row['color'],
                    'category' => $row['category']
                ];
            }
            self::$cacheLoaded = true;
        }
    }

    /**
     * Get all available badges
     */
    public static function getAllBadges() {
        self::loadBadgesFromDatabase();
        return self::$badgeCache;
    }

    /**
     * Get badge information by name
     */
    public static function getBadge($badgeName) {
        self::loadBadgesFromDatabase();
        $badgeName = trim($badgeName);
        return isset(self::$badgeCache[$badgeName]) ? self::$badgeCache[$badgeName] : null;
    }

    /**
     * Get multiple badges by their names
     */
    public static function getBadges($badgeNames) {
        $badges = [];
        foreach ($badgeNames as $badgeName) {
            $badge = self::getBadge($badgeName);
            if ($badge) {
                $badges[] = $badge;
            }
        }
        return $badges;
    }

    /**
     * Parse user's badges from comma-separated string
     */
    public static function parseUserBadges($badgesString) {
        if (empty($badgesString)) {
            return [];
        }
        
        $badgeNames = explode(',', $badgesString);
        $badgeNames = array_map('trim', $badgeNames);
        $badgeNames = array_filter($badgeNames); // Remove empty strings
        
        return self::getBadges($badgeNames);
    }

    /**
     * Get badge categories
     */
    public static function getCategories() {
        self::loadBadgesFromDatabase();
        $categories = [];
        foreach (self::$badgeCache as $badge) {
            if (!in_array($badge['category'], $categories)) {
                $categories[] = $badge['category'];
            }
        }
        return $categories;
    }

    /**
     * Get badges by category
     */
    public static function getBadgesByCategory($category) {
        self::loadBadgesFromDatabase();
        $badges = [];
        foreach (self::$badgeCache as $badge) {
            if ($badge['category'] === $category) {
                $badges[] = $badge;
            }
        }
        return $badges;
    }

    /**
     * Check if a badge exists
     */
    public static function badgeExists($badgeName) {
        self::loadBadgesFromDatabase();
        return isset(self::$badgeCache[trim($badgeName)]);
    }

    /**
     * Get badge count
     */
    public static function getTotalBadgeCount() {
        self::loadBadgesFromDatabase();
        return count(self::$badgeCache);
    }

    /**
     * Search badges by name or description
     */
    public static function searchBadges($searchTerm) {
        self::loadBadgesFromDatabase();
        $results = [];
        $searchTerm = strtolower($searchTerm);
        
        foreach (self::$badgeCache as $badge) {
            if (strpos(strtolower($badge['name']), $searchTerm) !== false ||
                strpos(strtolower($badge['description']), $searchTerm) !== false) {
                $results[] = $badge;
            }
        }
        
        return $results;
    }

    /**
     * Generate badge HTML
     */
    public static function generateBadgeHTML($badge, $clickable = false, $isObtained = true, $showStats = false, $badgeStats = null) {
        $onclick = $clickable ? 'onclick="openBadgeModal(\'' . htmlspecialchars($badge['name']) . '\')"' : '';
        $cursor = $clickable ? 'cursor: pointer;' : '';
        
        if ($isObtained) {
            $badgeColor = 'background: linear-gradient(135deg, ' . $badge['color'] . ', ' . self::darkenColor($badge['color']) . ');';
            $badgeClass = 'badge';
        } else {
            $badgeColor = 'background: linear-gradient(135deg, #cccccc, #999999);';
            $badgeClass = 'badge badge-disabled';
        }
        
        // Determine content based on whether badge has image
        $contentHTML = '';
        if (!empty($badge['image']) && file_exists($badge['image'])) {
            // Badge has image - use circular design like icon
            $contentHTML = '
            <div class="badge-image" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; background: rgba(255, 255, 255, 0.2); flex-shrink: 0;">
                <img src="' . htmlspecialchars($badge['image']) . '" alt="' . htmlspecialchars($badge['name']) . '" style="width: 100%; height: 100%; object-fit: cover; object-position: center; border-radius: 50%; min-width: 60px; min-height: 60px; max-width: 60px; max-height: 60px;">
            </div>
            <p>' . htmlspecialchars($badge['name']) . '</p>';
        } else {
            // Badge uses icon
            $contentHTML = '
            <div class="badge-icon">
                <i class="' . $badge['icon'] . '"></i>
            </div>
            <p>' . htmlspecialchars($badge['name']) . '</p>';
        }
        
        // Only show stats overlay in specific contexts (like admin panel), not in user profile
        $statsHTML = '';
        
        return '
        <div class="' . $badgeClass . '" ' . $onclick . ' style="' . $cursor . $badgeColor . '">
            ' . $contentHTML . '
            ' . $statsHTML . '
        </div>';
    }

    /**
     * Get all badges with their obtained status for a user
     */
    public static function getAllBadgesWithStatus($userBadges) {
        $allBadges = self::getAllBadges();
        $userBadgeNames = array_column($userBadges, 'name');
        $badgesWithStatus = [];
        
        foreach ($allBadges as $badgeName => $badge) {
            $badgesWithStatus[] = [
                'badge' => $badge,
                'obtained' => in_array($badgeName, $userBadgeNames)
            ];
        }
        
        return $badgesWithStatus;
    }

    /**
     * Helper function to darken a color
     */
    private static function darkenColor($color, $percent = 20) {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Convert to RGB
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        
        // Darken
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));
        
        // Convert back to hex
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }

    /**
     * Calculate badge statistics including percentage of users who have each badge
     */
    public static function calculateBadgeStatistics($connection = null) {
        $conn = $connection ?: self::$connection;
        if (!$conn) {
            return [];
        }

        $badges = self::getAllBadges();
        $statistics = [];
        
        // Get total number of users with badges
        $totalUsersQuery = "SELECT COUNT(*) as total_users FROM accountstbl WHERE badges IS NOT NULL AND badges != ''";
        $result = $conn->query($totalUsersQuery);
        $totalUsers = $result ? $result->fetch_assoc()['total_users'] : 0;
        
        // Prevent division by zero
        if ($totalUsers == 0) {
            foreach ($badges as $badgeName => $badge) {
                $statistics[$badgeName] = [
                    'badge_info' => $badge,
                    'users_with_badge' => 0,
                    'percentage' => 0.0,
                    'rarity' => 'Common',
                    'rarity_class' => 'common'
                ];
            }
            return $statistics;
        }
        
        // Calculate statistics for each badge
        foreach ($badges as $badgeName => $badge) {
            // Count users who have this specific badge (more precise search)
            $escapedBadgeName = $conn->real_escape_string($badgeName);
            $badgeQuery = "SELECT COUNT(*) as badge_count FROM accountstbl 
                          WHERE badges IS NOT NULL 
                          AND badges != ''
                          AND (
                              badges LIKE '%$escapedBadgeName,%' OR 
                              badges LIKE '%, $escapedBadgeName,%' OR 
                              badges LIKE '% $escapedBadgeName,%' OR
                              badges LIKE '%,$escapedBadgeName%' OR
                              badges LIKE '%, $escapedBadgeName' OR
                              badges LIKE '% $escapedBadgeName' OR
                              badges = '$escapedBadgeName'
                          )";
            $result = $conn->query($badgeQuery);
            $usersWithBadge = $result ? $result->fetch_assoc()['badge_count'] : 0;
            
            $percentage = ($usersWithBadge / $totalUsers) * 100;
            
            // Calculate rarity based on percentage
            $rarity = self::calculateRarity($percentage);
            
            $statistics[$badgeName] = [
                'badge_info' => $badge,
                'users_with_badge' => $usersWithBadge,
                'total_users' => $totalUsers,
                'percentage' => round($percentage, 1),
                'rarity' => $rarity['name'],
                'rarity_class' => $rarity['class'],
                'rarity_color' => $rarity['color']
            ];
        }
        
        return $statistics;
    }

    /**
     * Calculate badge rarity based on percentage of users who have it
     */
    private static function calculateRarity($percentage) {
        if ($percentage >= 80) {
            return [
                'name' => 'Common',
                'class' => 'common',
                'color' => '#9E9E9E'
            ];
        } else if ($percentage >= 50) {
            return [
                'name' => 'Uncommon',
                'class' => 'uncommon',
                'color' => '#4CAF50'
            ];
        } else if ($percentage >= 20) {
            return [
                'name' => 'Rare',
                'class' => 'rare',
                'color' => '#2196F3'
            ];
        } else if ($percentage >= 5) {
            return [
                'name' => 'Epic',
                'class' => 'epic',
                'color' => '#9C27B0'
            ];
        } else {
            return [
                'name' => 'Legendary',
                'class' => 'legendary',
                'color' => '#FF9800'
            ];
        }
    }

    /**
     * Add a new badge to the database
     */
    public static function addBadge($name, $description, $instructions, $imagePath, $iconClass, $color, $category) {
        if (!self::$connection) {
            return false;
        }

        // Set Philippine timezone
        date_default_timezone_set('Asia/Manila');
        $philippineTime = date('Y-m-d H:i:s');

        $query = "INSERT INTO badgestbl (badge_name, description, instructions, image_path, icon_class, color, category, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = self::$connection->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sssssssss", $name, $description, $instructions, $imagePath, $iconClass, $color, $category, $philippineTime, $philippineTime);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Clear cache to reload badges
            self::$cacheLoaded = false;
            self::$badgeCache = [];
        }

        return $result;
    }

    /**
     * Update an existing badge
     */
    public static function updateBadge($badgeId, $name, $description, $instructions, $imagePath, $iconClass, $color, $category) {
        if (!self::$connection) {
            return false;
        }

        // Set Philippine timezone
        date_default_timezone_set('Asia/Manila');
        $philippineTime = date('Y-m-d H:i:s');

        $query = "UPDATE badgestbl SET 
                  badge_name = ?, description = ?, instructions = ?, image_path = ?, 
                  icon_class = ?, color = ?, category = ?, 
                  updated_at = ? 
                  WHERE badge_id = ?";
        
        $stmt = self::$connection->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ssssssssi", $name, $description, $instructions, $imagePath, $iconClass, $color, $category, $philippineTime, $badgeId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Clear cache to reload badges
            self::$cacheLoaded = false;
            self::$badgeCache = [];
        }

        return $result;
    }

    /**
     * Deactivate a badge (soft delete)
     */
    public static function deactivateBadge($badgeId) {
        if (!self::$connection) {
            return false;
        }

        $query = "UPDATE badgestbl SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE badge_id = ?";
        $stmt = self::$connection->prepare($query);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $badgeId);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Clear cache to reload badges
            self::$cacheLoaded = false;
            self::$badgeCache = [];
        }

        return $result;
    }

    /**
     * Get badge by ID
     */
    public static function getBadgeById($badgeId) {
        if (!self::$connection) {
            return null;
        }

        $query = "SELECT * FROM badgestbl WHERE badge_id = ? AND is_active = 1";
        $stmt = self::$connection->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $badgeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $badge = $result->fetch_assoc();
        $stmt->close();

        if ($badge) {
            return [
                'id' => $badge['badge_id'],
                'name' => $badge['badge_name'],
                'description' => $badge['description'],
                'instructions' => $badge['instructions'],
                'image' => $badge['image_path'],
                'icon' => $badge['icon_class'],
                'color' => $badge['color'],
                'category' => $badge['category']
            ];
        }

        return null;
    }

    /**
     * Award a badge to a user
     */
    public static function awardBadgeToUser($userId, $badgeName) {
        if (!self::$connection) {
            return false;
        }

        // Check if badge exists
        if (!self::badgeExists($badgeName)) {
            return false;
        }

        // Get current user badges
        $getUserQuery = "SELECT badges, badge_count FROM accountstbl WHERE account_id = ?";
        $stmt = self::$connection->prepare($getUserQuery);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            return false;
        }

        // Parse current badges
        $currentBadges = empty($user['badges']) ? [] : explode(',', $user['badges']);
        $currentBadges = array_map('trim', $currentBadges);

        // Check if user already has this badge
        if (in_array($badgeName, $currentBadges)) {
            return true; // Already has badge
        }

        // Add new badge
        $currentBadges[] = $badgeName;
        $newBadgesString = implode(', ', $currentBadges);
        $newBadgeCount = count($currentBadges);

        // Update user's badges
        $updateQuery = "UPDATE accountstbl SET badges = ?, badge_count = ? WHERE account_id = ?";
        $stmt = self::$connection->prepare($updateQuery);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("sii", $newBadgesString, $newBadgeCount, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Award a badge to multiple users
     */
    public static function awardBadgeToMultipleUsers($userIds, $badgeName) {
        if (!self::$connection || empty($userIds) || !is_array($userIds)) {
            return false;
        }

        // Check if badge exists
        if (!self::badgeExists($badgeName)) {
            return false;
        }

        $successCount = 0;
        $totalCount = count($userIds);

        foreach ($userIds as $userId) {
            if (self::awardBadgeToUser($userId, $badgeName)) {
                $successCount++;
            }
        }

        return [
            'success' => $successCount,
            'total' => $totalCount,
            'success_rate' => $totalCount > 0 ? ($successCount / $totalCount) * 100 : 0
        ];
    }

    /**
     * Get users who don't have a specific badge
     */
    public static function getUsersWithoutBadge($badgeName) {
        if (!self::$connection) {
            return [];
        }

        $query = "SELECT account_id, fullname, email, badges FROM accountstbl 
                 WHERE badges IS NULL OR badges NOT LIKE ? 
                 ORDER BY fullname";
        
        $stmt = self::$connection->prepare($query);
        if (!$stmt) {
            return [];
        }

        $searchPattern = "%{$badgeName}%";
        $stmt->bind_param("s", $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            // Double-check if user really doesn't have the badge (for exact matching)
            $userBadges = empty($row['badges']) ? [] : explode(',', $row['badges']);
            $userBadges = array_map('trim', $userBadges);
            
            if (!in_array($badgeName, $userBadges)) {
                $users[] = [
                    'account_id' => $row['account_id'],
                    'fullname' => $row['fullname'],
                    'email' => $row['email']
                ];
            }
        }
        
        $stmt->close();
        return $users;
    }

    /**
     * Get all users for badge awarding with their badge status
     */
    public static function getAllUsersWithBadgeStatus($badgeName = null) {
        if (!self::$connection) {
            return [];
        }

        $query = "SELECT account_id, fullname, email, badges FROM accountstbl ORDER BY fullname";
        $result = self::$connection->query($query);
        
        if (!$result) {
            return [];
        }

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $userBadges = empty($row['badges']) ? [] : explode(',', $row['badges']);
            $userBadges = array_map('trim', $userBadges);
            
            $hasBadge = $badgeName ? in_array($badgeName, $userBadges) : false;
            
            $users[] = [
                'account_id' => $row['account_id'],
                'fullname' => $row['fullname'],
                'email' => $row['email'],
                'has_badge' => $hasBadge,
                'badge_count' => count($userBadges)
            ];
        }
        
        return $users;
    }

    /**
     * Clear cache (useful for testing or after manual database changes)
     */
    public static function clearCache() {
        self::$cacheLoaded = false;
        self::$badgeCache = [];
    }
}

/**
 * Helper function to get user badges (for backward compatibility)
 */
function getUserBadges($badgesString) {
    return BadgeSystem::parseUserBadges($badgesString);
}

/**
 * Generate badge modal HTML (for backward compatibility)
 */
function generateBadgeModal() {
    return '
    <div id="badgeModal" class="badge-modal" onclick="closeBadgeModal(event)">
        <div class="badge-modal-content" onclick="event.stopPropagation()">
            <span class="badge-modal-close" onclick="closeBadgeModal()">&times;</span>
            <div class="badge-modal-header">
                <div class="badge-modal-icon" id="badgeModalIconContainer">
                    <i id="badgeModalIcon" style="display: none;"></i>
                    <img id="badgeModalImage" style="display: none;" alt="Badge Image">
                </div>
                <h2 id="badgeModalTitle"></h2>
                <div class="badge-rarity" id="badgeModalRarity"></div>
            </div>
            <div class="badge-modal-body">
                <p id="badgeModalDescription"></p>
                <div class="badge-modal-instructions">
                    <h4><i class="fas fa-tasks"></i> How to Earn This Badge:</h4>
                    <p id="badgeModalInstructions"></p>
                </div>
                <div class="badge-modal-stats">
                    <div class="badge-stat">
                        <span class="stat-label">Category:</span>
                        <span id="badgeModalCategory" class="stat-value"></span>
                    </div>
                    <div class="badge-stat">
                        <span class="stat-label">Rarity:</span>
                        <span id="badgeModalRarityText" class="stat-value stat-rarity"></span>
                    </div>
                    <div class="badge-stat">
                        <span class="stat-label">Owned by:</span>
                        <span id="badgeModalPercentage" class="stat-value"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

/**
 * Generate badge modal CSS (for backward compatibility)
 */
function generateBadgeModalCSS() {
    return '
    <style>
    .badge-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }

    .badge-modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 0;
        border: none;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        overflow: hidden;
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .badge-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        padding: 10px 15px;
        transition: color 0.3s ease;
    }

    .badge-modal-close:hover {
        color: #000;
    }

    .badge-modal-header {
        background: linear-gradient(135deg, #4CAF50, #2E7D32);
        color: white;
        padding: 20px;
        text-align: center;
        position: relative;
    }

    .badge-modal-icon {
        font-size: 48px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60px;
    }

    .badge-modal-icon i {
        color: white;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .badge-modal-icon img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,0.3);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    .badge-modal-header h2 {
        margin: 0;
        font-size: 24px;
    }

    .badge-modal-body {
        padding: 20px;
    }

    .badge-modal-body p {
        font-size: 16px;
        line-height: 1.6;
        color: #333;
        margin-bottom: 20px;
    }

    .badge-modal-instructions {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin: 20px 0;
        border-left: 4px solid #4CAF50;
    }

    .badge-modal-instructions h4 {
        color: #2E7D32;
        margin: 0 0 10px 0;
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-modal-instructions p {
        margin: 0;
        font-size: 14px;
        color: #555;
        line-height: 1.5;
    }

    .badge-modal-stats {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        gap: 15px;
    }

    .badge-stat {
        text-align: center;
        flex: 1;
        min-width: 120px;
    }

    .stat-label {
        display: block;
        font-weight: bold;
        color: #666;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .stat-value {
        display: block;
        font-size: 16px;
        color: #333;
        background: #f0f0f0;
        padding: 8px 12px;
        border-radius: 20px;
    }

    .badge-rarity {
        font-size: 12px;
        font-weight: bold;
        padding: 4px 12px;
        border-radius: 15px;
        margin-top: 8px;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .stat-rarity {
        font-weight: bold;
    }

    /* Rarity Colors */
    .rarity-common { background-color: #9E9E9E; color: white; }
    .rarity-uncommon { background-color: #4CAF50; color: white; }
    .rarity-rare { background-color: #2196F3; color: white; }
    .rarity-epic { background-color: #9C27B0; color: white; }
    .rarity-legendary { background-color: #FF9800; color: white; }

    /* Badge stats overlay */
    .badge-stats-overlay {
        position: absolute;
        top: 5px;
        right: 5px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .badge-rarity-small {
        font-size: 8px;
        padding: 2px 6px;
        border-radius: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-percentage {
        font-size: 9px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 1px 4px;
        border-radius: 6px;
        text-align: center;
    }

    /* Make badge container relative for positioning */
    .badge {
        position: relative;
    }

    @media (max-width: 600px) {
        .badge-modal-content {
            width: 95%;
            margin: 5% auto;
        }
        
        .badge-modal-header {
            padding: 15px;
        }
        
        .badge-modal-icon {
            font-size: 36px;
        }
        
        .badge-modal-header h2 {
            font-size: 20px;
        }
    }
    </style>';
}

/**
 * Generate badge modal JavaScript (for backward compatibility)
 */
function generateBadgeModalJS($connection) {
    // Initialize the badge system with the connection
    BadgeSystem::init($connection);
    
    $badgeStats = BadgeSystem::calculateBadgeStatistics($connection);
    
    return '
    <script>
    // Badge statistics data
    const badgeStatistics = ' . json_encode($badgeStats) . ';
    
    function openBadgeModal(badgeName) {
        // Get badge data via AJAX or use the data already loaded
        const badges = ' . json_encode(BadgeSystem::getAllBadges()) . ';
        const badge = badges[badgeName];
        const stats = badgeStatistics[badgeName];
        
        if (!badge) {
            console.error("Badge not found:", badgeName);
            return;
        }
        
        // Handle badge icon/image display
        const iconElement = document.getElementById("badgeModalIcon");
        const imageElement = document.getElementById("badgeModalImage");
        
        // Hide both initially
        iconElement.style.display = "none";
        imageElement.style.display = "none";
        
        // Check if badge has an image and if the file exists
        if (badge.image && badge.image.trim() !== "") {
            // Show image
            imageElement.src = badge.image;
            imageElement.alt = badge.name;
            imageElement.style.display = "block";
            
            // Handle image load error - fallback to icon
            imageElement.onerror = function() {
                this.style.display = "none";
                if (badge.icon && badge.icon.trim() !== "") {
                    iconElement.className = badge.icon;
                    iconElement.style.display = "block";
                } else {
                    // Default fallback icon
                    iconElement.className = "fas fa-star";
                    iconElement.style.display = "block";
                }
            };
        } else if (badge.icon && badge.icon.trim() !== "") {
            // Show icon
            iconElement.className = badge.icon;
            iconElement.style.display = "block";
        } else {
            // Default fallback icon
            iconElement.className = "fas fa-star";
            iconElement.style.display = "block";
        }
        
        document.getElementById("badgeModalTitle").textContent = badge.name;
        document.getElementById("badgeModalDescription").textContent = badge.description;
        document.getElementById("badgeModalInstructions").textContent = badge.instructions || "Instructions not available.";
        document.getElementById("badgeModalCategory").textContent = badge.category;
        
        // Update rarity information if stats are available
        if (stats) {
            const rarityElement = document.getElementById("badgeModalRarity");
            const rarityTextElement = document.getElementById("badgeModalRarityText");
            const percentageElement = document.getElementById("badgeModalPercentage");
            
            rarityElement.textContent = stats.rarity;
            rarityElement.className = "badge-rarity rarity-" + stats.rarity_class;
            
            rarityTextElement.textContent = stats.rarity;
            rarityTextElement.style.backgroundColor = stats.rarity_color;
            rarityTextElement.style.color = "white";
            
            percentageElement.textContent = stats.percentage + "% of users";
        } else {
            // Hide stats if not available
            document.getElementById("badgeModalRarity").textContent = "";
            document.getElementById("badgeModalRarityText").textContent = "Unknown";
            document.getElementById("badgeModalPercentage").textContent = "N/A";
        }
        
        // Update modal header color
        const modalHeader = document.querySelector(".badge-modal-header");
        modalHeader.style.background = `linear-gradient(135deg, ${badge.color}, ${darkenColor(badge.color, 20)})`;
        
        document.getElementById("badgeModal").style.display = "block";
    }

    function closeBadgeModal(event) {
        if (!event || event.target.id === "badgeModal" || event.target.classList.contains("badge-modal-close")) {
            document.getElementById("badgeModal").style.display = "none";
        }
    }

    function darkenColor(color, percent = 20) {
        const num = parseInt(color.replace("#", ""), 16);
        const amt = Math.round(2.55 * percent);
        const R = (num >> 16) - amt;
        const G = (num >> 8 & 0x00FF) - amt;
        const B = (num & 0x0000FF) - amt;
        return "#" + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
            (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
            (B < 255 ? B < 1 ? 0 : B : 255)).toString(16).slice(1);
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById("badgeModal");
        if (event.target === modal) {
            closeBadgeModal();
        }
    }
    </script>';
}
?>
