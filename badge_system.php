<?php
/**
 * Badge System for ManGrow
 * Manages badge definitions, images, and descriptions
 */

class BadgeSystem {
    
    /**
     * All available badges in the system with their details
     */
    private static $badges = [
        'Starting Point' => [
            'name' => 'Starting Point',
            'description' => 'Awarded for successfully registering in the system. Welcome to ManGrow!',
            'image' => 'images/badges/starting_point.png',
            'icon' => 'fas fa-flag-checkered',
            'color' => '#2196F3',
            'category' => 'Milestone'
        ],
        'Enthusiast' => [
            'name' => 'Enthusiast',
            'description' => 'Congratulations on attending your first ManGrow event! This is the beginning of your eco-warrior journey.',
            'image' => 'images/badges/first_event.png',
            'icon' => 'fas fa-seedling',
            'color' => '#4CAF50',
            'category' => 'Milestone'
        ],
        'Eco Warrior' => [
            'name' => 'Eco Warrior',
            'description' => 'You\'ve attended 5 environmental events. Your dedication to protecting nature is remarkable!',
            'image' => 'images/badges/eco_warrior.png',
            'icon' => 'fas fa-shield-alt',
            'color' => '#2E7D32',
            'category' => 'Achievement'
        ],
        'Community Leader' => [
            'name' => 'Community Leader',
            'description' => 'You\'ve organized or led community environmental initiatives. Leadership in action!',
            'image' => 'images/badges/community_leader.png',
            'icon' => 'fas fa-users',
            'color' => '#1976D2',
            'category' => 'Leadership'
        ],
        'Tree Planter' => [
            'name' => 'Tree Planter',
            'description' => 'You\'ve participated in tree planting activities. Every tree makes a difference!',
            'image' => 'images/badges/tree_planter.png',
            'icon' => 'fas fa-tree',
            'color' => '#388E3C',
            'category' => 'Environmental'
        ],
        'Beach Cleaner' => [
            'name' => 'Beach Cleaner',
            'description' => 'You\'ve joined coastal cleanup drives. Thank you for keeping our shores pristine!',
            'image' => 'images/badges/beach_cleaner.png',
            'icon' => 'fas fa-water',
            'color' => '#0288D1',
            'category' => 'Environmental'
        ],
        'Mangrove Guardian' => [
            'name' => 'Mangrove Guardian',
            'description' => 'You\'ve actively participated in mangrove conservation activities. A true guardian of coastal ecosystems!',
            'image' => 'images/badges/mangrove_guardian.png',
            'icon' => 'fas fa-leaf',
            'color' => '#2E7D32',
            'category' => 'Conservation'
        ],
        'Educator' => [
            'name' => 'Educator',
            'description' => 'You\'ve shared knowledge and educated others about environmental conservation. Knowledge is power!',
            'image' => 'images/badges/educator.png',
            'icon' => 'fas fa-graduation-cap',
            'color' => '#7B1FA2',
            'category' => 'Education'
        ],
        'Volunteer Champion' => [
            'name' => 'Volunteer Champion',
            'description' => 'You\'ve volunteered for 10+ environmental events. Your commitment is inspiring!',
            'image' => 'images/badges/volunteer_champion.png',
            'icon' => 'fas fa-hands-helping',
            'color' => '#F57C00',
            'category' => 'Achievement'
        ],
        'Eco Points Collector' => [
            'name' => 'Eco Points Collector',
            'description' => 'You\'ve earned 1000+ eco points! Your environmental contributions are adding up!',
            'image' => 'images/badges/eco_points_collector.png',
            'icon' => 'fas fa-coins',
            'color' => '#FF9800',
            'category' => 'Points'
        ],
        'Green Ambassador' => [
            'name' => 'Green Ambassador',
            'description' => 'You\'ve recruited friends to join environmental causes. Spreading the green movement!',
            'image' => 'images/badges/green_ambassador.png',
            'icon' => 'fas fa-globe-americas',
            'color' => '#4CAF50',
            'category' => 'Social'
        ],
        'Sustainability Star' => [
            'name' => 'Sustainability Star',
            'description' => 'You\'ve consistently participated in sustainable living practices. A shining example!',
            'image' => 'images/badges/sustainability_star.png',
            'icon' => 'fas fa-star',
            'color' => '#FFD700',
            'category' => 'Lifestyle'
        ],
        'Conservation Hero' => [
            'name' => 'Conservation Hero',
            'description' => 'You\'ve made significant contributions to conservation efforts. A true environmental hero!',
            'image' => 'images/badges/conservation_hero.png',
            'icon' => 'fas fa-award',
            'color' => '#D32F2F',
            'category' => 'Hero'
        ],
        'Nature Photographer' => [
            'name' => 'Nature Photographer',
            'description' => 'You\'ve documented nature through photography for conservation awareness. Picture perfect!',
            'image' => 'images/badges/nature_photographer.png',
            'icon' => 'fas fa-camera',
            'color' => '#795548',
            'category' => 'Creative'
        ],
        'Research Contributor' => [
            'name' => 'Research Contributor',
            'description' => 'You\'ve contributed to environmental research and data collection. Science in action!',
            'image' => 'images/badges/research_contributor.png',
            'icon' => 'fas fa-microscope',
            'color' => '#3F51B5',
            'category' => 'Research'
        ],
        'Event Organizer' => [
            'name' => 'Event Organizer',
            'description' => 'You\'ve organized environmental events for the community. Making things happen!',
            'image' => 'images/badges/event_organizer.png',
            'icon' => 'fas fa-calendar-plus',
            'color' => '#E91E63',
            'category' => 'Leadership'
        ],
        'Watchful Eye' => [
            'name' => 'Watchful Eye',
            'description' => 'You always watch behind the scenes for any incidents',
            'image' => 'images/badges/watchful_eye.png',
            'icon' => 'fas fa-eye',
            'color' => '#FF9800',
            'category' => 'Reporting'
        ],
        'Vigilant Protector' => [
            'name' => 'Vigilant Protector',
            'description' => "You've shown enough love for the environment and we thank you for that",
            'image' => 'images/badges/vigilant_protector.png',
            'icon' => 'fas fa-shield-alt',
            'color' => '#2196F3',
            'category' => 'Reporting'
        ],
        'Conservation Champion' => [
            'name' => 'Conservation Champion',
            'description' => 'This is the proof of your love and care for our dear mangroves.',
            'image' => 'images/badges/conservation_champion.png',
            'icon' => 'fas fa-medal',
            'color' => '#4CAF50',
            'category' => 'Reporting'
        ],
        'Ecosystem Sentinel' => [
            'name' => 'Ecosystem Sentinel',
            'description' => 'The mangroves will be forever thankful to you.',
            'image' => 'images/badges/ecosystem_sentinel.png',
            'icon' => 'fas fa-binoculars',
            'color' => '#9C27B0',
            'category' => 'Reporting'
        ],
        'Mangrove Legend' => [
            'name' => "You're the GOAT!",
            'description' => 'Submitted 50 environmental incident reports. A legendary guardian of nature!',
            'image' => 'images/badges/mangrove_legend.png',
            'icon' => 'fas fa-crown',
            'color' => '#FF5722',
            'category' => 'Reporting'
        ],
        'First Resolution' => [
            'name' => 'First Resolution',
            'description' => 'Successfully resolved your first environmental incident report. Thank you for taking action!',
            'image' => 'images/badges/first_resolution.png',
            'icon' => 'fas fa-check-circle',
            'color' => '#28a745',
            'category' => 'Resolution'
        ],
        'Alert Citizen' => [
            'name' => 'Alert Citizen',
            'description' => 'Successfully resolved 5 environmental incident reports. Your vigilance makes a difference!',
            'image' => 'images/badges/alert_citizen.png',
            'icon' => 'fas fa-shield-alt',
            'color' => '#17a2b8',
            'category' => 'Resolution'
        ],
        'Community Protector' => [
            'name' => 'Community Protector',
            'description' => 'Successfully resolved 10 environmental incident reports. A true protector of the environment!',
            'image' => 'images/badges/community_protector.png',
            'icon' => 'fas fa-user-shield',
            'color' => '#6f42c1',
            'category' => 'Resolution'
        ],
        'Super Watchdog' => [
            'name' => 'Super Watchdog',
            'description' => 'Successfully resolved 25 environmental incident reports. Your dedication is exceptional!',
            'image' => 'images/badges/super_watchdog.png',
            'icon' => 'fas fa-medal',
            'color' => '#fd7e14',
            'category' => 'Resolution'
        ],
        'Vigilant Guardian' => [
            'name' => 'Vigilant Guardian',
            'description' => 'Successfully resolved 50 environmental incident reports. A guardian of nature and community!',
            'image' => 'images/badges/vigilant_guardian.png',
            'icon' => 'fas fa-trophy',
            'color' => '#ffc107',
            'category' => 'Resolution'
        ],
        'Report Veteran' => [
            'name' => 'Report Veteran',
            'description' => 'Successfully resolved 100 environmental incident reports. A veteran in environmental protection!',
            'image' => 'images/badges/report_veteran.png',
            'icon' => 'fas fa-star',
            'color' => '#dc3545',
            'category' => 'Resolution'
        ],
        'Active Citizen' => [
            'name' => 'Active Citizen',
            'description' => 'Submitted 10 environmental incident reports. Your active participation protects our environment!',
            'image' => 'images/badges/active_citizen.png',
            'icon' => 'fas fa-bullhorn',
            'color' => '#20c997',
            'category' => 'Submission'
        ],
        'Dedicated Reporter' => [
            'name' => 'Dedicated Reporter',
            'description' => 'Submitted 20 environmental incident reports. Your dedication to reporting is commendable!',
            'image' => 'images/badges/dedicated_reporter.png',
            'icon' => 'fas fa-clipboard-list',
            'color' => '#6610f2',
            'category' => 'Submission'
        ],
        'Environmental Crusader' => [
            'name' => 'Environmental Crusader',
            'description' => 'Submitted 50 environmental incident reports. A true crusader for environmental protection!',
            'image' => 'images/badges/environmental_crusader.png',
            'icon' => 'fas fa-fist-raised',
            'color' => '#e83e8c',
            'category' => 'Submission'
        ],
        'Report Master' => [
            'name' => 'Report Master',
            'description' => 'Submitted 100 environmental incident reports. A master of environmental vigilance!',
            'image' => 'images/badges/report_master.png',
            'icon' => 'fas fa-crown',
            'color' => '#6f42c1',
            'category' => 'Submission'
        ]
        ,
            'Badge Collector I' => [
                'name' => 'Badge Collector I',
                'description' => 'Collected 5 different badges. Keep going!',
                'image' => 'images/badges/badge_collector_5.png',
                'icon' => 'fas fa-layer-group',
                'color' => '#00BCD4',
                'category' => 'Collector'
            ],
            'Badge Collector II' => [
                'name' => 'Badge Collector II',
                'description' => 'Collected 10 different badges. Impressive achievement!',
                'image' => 'images/badges/badge_collector_10.png',
                'icon' => 'fas fa-th-large',
                'color' => '#8BC34A',
                'category' => 'Collector'
            ],
            'Badge Collector III' => [
                'name' => 'Badge Collector III',
                'description' => 'Collected 20 different badges. You are a true badge hunter!',
                'image' => 'images/badges/badge_collector_20.png',
                'icon' => 'fas fa-cubes',
                'color' => '#FF5722',
                'category' => 'Collector'
            ],
            'Badge Collector IV' => [
                'name' => 'Badge Collector IV',
                'description' => 'Collected 25 different badges. Outstanding dedication!',
                'image' => 'images/badges/badge_collector_25.png',
                'icon' => 'fas fa-gem',
                'color' => '#9C27B0',
                'category' => 'Collector'
            ],
            'Badge Collector V' => [
                'name' => 'Badge Collector V',
                'description' => 'Collected 50 different badges. Legendary collector!',
                'image' => 'images/badges/badge_collector_50.png',
                'icon' => 'fas fa-crown',
                'color' => '#FFC107',
                'category' => 'Collector'
            ],
            'Badge Master' => [
                'name' => 'Badge Master',
                'description' => 'Collected 100 different badges. The ultimate badge master!',
                'image' => 'images/badges/badge_collector_100.png',
                'icon' => 'fas fa-trophy',
                'color' => '#E91E63',
                'category' => 'Collector'
            ]
    ];

    /**
     * Get all available badges
     */
    public static function getAllBadges() {
        return self::$badges;
    }

    /**
     * Get badge information by name
     */
    public static function getBadge($badgeName) {
        $badgeName = trim($badgeName);
        return isset(self::$badges[$badgeName]) ? self::$badges[$badgeName] : null;
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
        $categories = [];
        foreach (self::$badges as $badge) {
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
        $badges = [];
        foreach (self::$badges as $badge) {
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
        return isset(self::$badges[trim($badgeName)]);
    }

    /**
     * Get badge count
     */
    public static function getTotalBadgeCount() {
        return count(self::$badges);
    }

    /**
     * Search badges by name or description
     */
    public static function searchBadges($searchTerm) {
        $results = [];
        $searchTerm = strtolower($searchTerm);
        
        foreach (self::$badges as $badge) {
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
    public static function generateBadgeHTML($badge, $clickable = false, $isObtained = true) {
        $onclick = $clickable ? 'onclick="openBadgeModal(\'' . htmlspecialchars($badge['name']) . '\')"' : '';
        $cursor = $clickable ? 'cursor: pointer;' : '';
        
        if ($isObtained) {
            $badgeColor = 'background: linear-gradient(135deg, ' . $badge['color'] . ', ' . self::darkenColor($badge['color']) . ');';
            $badgeClass = 'badge';
        } else {
            $badgeColor = 'background: linear-gradient(135deg, #cccccc, #999999);';
            $badgeClass = 'badge badge-disabled';
        }
        
        return '
        <div class="' . $badgeClass . '" ' . $onclick . ' style="' . $cursor . $badgeColor . '">
            <div class="badge-icon">
                <i class="' . $badge['icon'] . '"></i>
            </div>
            <p>' . htmlspecialchars($badge['name']) . '</p>
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
    public static function calculateBadgeStatistics($connection) {
        $badges = self::getAllBadges();
        $statistics = [];
        
        // Get total number of users
        $totalUsersQuery = "SELECT COUNT(*) as total_users FROM accountstbl WHERE badges IS NOT NULL";
        $result = $connection->query($totalUsersQuery);
        $totalUsers = $result ? $result->fetch_assoc()['total_users'] : 0;
        
        // Prevent division by zero
        if ($totalUsers == 0) {
            foreach ($badges as $badgeName => $badge) {
                $statistics[$badgeName] = [
                    'badge_info' => $badge,
                    'users_with_badge' => 0,
                    'percentage' => 0.0
                ];
            }
            return $statistics;
        }
        
        // Calculate statistics for each badge
        foreach ($badges as $badgeName => $badge) {
            // Count users who have this specific badge
            $badgeQuery = "SELECT COUNT(*) as badge_count FROM accountstbl 
                          WHERE badges IS NOT NULL 
                          AND (badges LIKE '%\"$badgeName\"%' OR badges LIKE '%$badgeName%')";
            $result = $connection->query($badgeQuery);
            $usersWithBadge = $result ? $result->fetch_assoc()['badge_count'] : 0;
            
            $percentage = ($usersWithBadge / $totalUsers) * 100;
            
            $statistics[$badgeName] = [
                'badge_info' => $badge,
                'users_with_badge' => $usersWithBadge,
                'total_users' => $totalUsers,
                'percentage' => round($percentage, 1)
            ];
        }
        
        return $statistics;
    }

    /**
     * Get badge statistics as JSON for JavaScript
     */
    public static function getBadgeStatisticsJSON($connection) {
        $statistics = self::calculateBadgeStatistics($connection);
        return json_encode($statistics);
    }
}

/**
 * Helper functions for easy access
 */

/**
 * Get user's badges from database badges string
 */
function getUserBadges($badgesString) {
    return BadgeSystem::parseUserBadges($badgesString);
}

/**
 * Get badge information
 */
function getBadgeInfo($badgeName) {
    return BadgeSystem::getBadge($badgeName);
}

/**
 * Generate badge modal HTML
 */
function generateBadgeModal() {
    return '
    <div id="badgeModal" class="badge-modal" style="display: none;">
        <div class="badge-modal-content">
            <span class="badge-modal-close" onclick="closeBadgeModal()">&times;</span>
            <div class="badge-modal-body">
                <div class="badge-modal-icon">
                    <i id="modalBadgeIcon" class="fas fa-award"></i>
                </div>
                <h2 id="modalBadgeName">Badge Name</h2>
                <p class="badge-category">Category: <span id="modalBadgeCategory">Category</span></p>
                <div class="badge-stats">
                    <div class="badge-percentage">
                        <i class="fas fa-users"></i>
                        <span id="modalBadgePercentage">0%</span> of users have this badge
                    </div>
                    <div class="badge-rarity" id="modalBadgeRarity">Common</div>
                </div>
                <p id="modalBadgeDescription">Badge description goes here...</p>
            </div>
        </div>
    </div>';
}

/**
 * Generate badge modal JavaScript with statistics
 */
function generateBadgeModalJS($connection = null) {
    $badges = BadgeSystem::getAllBadges();
    $badgesJSON = json_encode($badges);
    
    // Get badge statistics if connection is provided
    $statisticsJSON = '{}';
    if ($connection) {
        $statisticsJSON = BadgeSystem::getBadgeStatisticsJSON($connection);
    }
    
    return "
    <script>
    const allBadges = $badgesJSON;
    const badgeStatistics = $statisticsJSON;
    
    function getRarityLevel(percentage) {
        if (percentage >= 50) return { text: 'Common', color: '#4CAF50' };
        if (percentage >= 25) return { text: 'Uncommon', color: '#FF9800' };
        if (percentage >= 10) return { text: 'Rare', color: '#9C27B0' };
        if (percentage >= 5) return { text: 'Epic', color: '#2196F3' };
        return { text: 'Legendary', color: '#F44336' };
    }
    
    function openBadgeModal(badgeName) {
        const badge = allBadges[badgeName];
        if (!badge) return;
        
        document.getElementById('modalBadgeName').textContent = badge.name;
        document.getElementById('modalBadgeDescription').textContent = badge.description;
        document.getElementById('modalBadgeCategory').textContent = badge.category;
        document.getElementById('modalBadgeIcon').className = badge.icon;
        
        // Update badge statistics
        const stats = badgeStatistics[badgeName];
        if (stats) {
            const percentage = stats.percentage;
            const rarity = getRarityLevel(percentage);
            
            document.getElementById('modalBadgePercentage').textContent = percentage + '%';
            const rarityElement = document.getElementById('modalBadgeRarity');
            rarityElement.textContent = rarity.text;
            rarityElement.style.color = rarity.color;
            rarityElement.style.fontWeight = 'bold';
        } else {
            document.getElementById('modalBadgePercentage').textContent = '0%';
            document.getElementById('modalBadgeRarity').textContent = 'Unknown';
        }
        
        // Update modal colors
        const modalContent = document.querySelector('.badge-modal-content');
        modalContent.style.borderTop = '5px solid ' + badge.color;
        
        const modalIcon = document.querySelector('.badge-modal-icon');
        modalIcon.style.background = 'linear-gradient(135deg, ' + badge.color + ', ' + darkenColor(badge.color) + ')';
        
        document.getElementById('badgeModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeBadgeModal() {
        document.getElementById('badgeModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('badgeModal');
        if (event.target === modal) {
            closeBadgeModal();
        }
    }
    
    // Helper function to darken color
    function darkenColor(color, percent = 20) {
        // Simple color darkening
        const hex = color.replace('#', '');
        const r = Math.max(0, parseInt(hex.substr(0, 2), 16) - (parseInt(hex.substr(0, 2), 16) * percent / 100));
        const g = Math.max(0, parseInt(hex.substr(2, 2), 16) - (parseInt(hex.substr(2, 2), 16) * percent / 100));
        const b = Math.max(0, parseInt(hex.substr(4, 2), 16) - (parseInt(hex.substr(4, 2), 16) * percent / 100));
        
        return '#' + Math.round(r).toString(16).padStart(2, '0') + 
                     Math.round(g).toString(16).padStart(2, '0') + 
                     Math.round(b).toString(16).padStart(2, '0');
    }
    </script>";
}

/**
 * Generate badge modal CSS
 */
function generateBadgeModalCSS() {
    return '
    <style>
    /* Badge sliding glass effect - override inline styles */
    .badge {
        transition: all 0.3s ease !important;
        position: relative !important;
        overflow: hidden !important;
    }
    
    .badge::before {
        content: "" !important;
        position: absolute !important;
        top: 0 !important;
        left: -100% !important;
        width: 100% !important;
        height: 100% !important;
        background: linear-gradient(90deg, 
            transparent, 
            rgba(255, 255, 255, 0.4), 
            transparent
        ) !important;
        transition: left 0.6s ease !important;
        pointer-events: none !important;
    }
    
    .badge:hover::before {
        left: 100% !important;
    }
    
    .badge:hover {
        transform: scale(1.05) translateY(-5px) !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2) !important;
    }
    
    .badge-modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    
    .badge-modal-content {
        background-color: white;
        margin: auto;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        position: relative;
        animation: slideIn 0.3s ease;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        border-top: 5px solid #4CAF50;
    }
    
    .badge-modal-close {
        position: absolute;
        top: 15px;
        right: 20px;
        color: #999;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        transition: color 0.3s ease;
    }
    
    .badge-modal-close:hover {
        color: #333;
    }
    
    .badge-modal-body {
        padding: 40px 30px 30px;
        text-align: center;
    }
    
    .badge-modal-icon {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4CAF50, #2E7D32);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    
    .badge-modal-icon i {
        font-size: 3rem;
        color: white;
    }
    
    .badge-modal h2 {
        margin: 0 0 10px;
        color: #333;
        font-size: 2rem;
        font-weight: 700;
    }
    
    .badge-category {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 20px;
        font-weight: 600;
    }
    
    .badge-category span {
        color: #4CAF50;
        font-weight: 700;
    }
    
    .badge-stats {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 10px;
        padding: 15px;
        margin: 15px 0;
        border-left: 4px solid #4CAF50;
    }
    
    .badge-percentage {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-size: 1rem;
        color: #555;
        margin-bottom: 8px;
    }
    
    .badge-percentage i {
        color: #4CAF50;
        font-size: 1.1rem;
    }
    
    .badge-percentage span {
        font-weight: bold;
        color: #2E7D32;
        font-size: 1.1rem;
    }
    
    .badge-rarity {
        text-align: center;
        font-size: 0.9rem;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 5px 10px;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .badge-modal p {
        color: #555;
        line-height: 1.6;
        font-size: 1.1rem;
        margin: 0;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { 
            opacity: 0;
            transform: scale(0.8) translateY(-50px);
        }
        to { 
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    @media (max-width: 600px) {
        .badge-modal-content {
            width: 95%;
            margin: 20px;
        }
        
        .badge-modal-body {
            padding: 30px 20px 20px;
        }
        
        .badge-modal h2 {
            font-size: 1.5rem;
        }
        
        .badge-modal-icon {
            width: 80px;
            height: 80px;
        }
        
        .badge-modal-icon i {
            font-size: 2.5rem;
        }
        
        .badge-stats {
            padding: 12px;
            margin: 12px 0;
        }
        
        .badge-percentage {
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        
        .badge-percentage span {
            font-size: 1rem;
        }
        
        .badge-rarity {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        
        .badge-modal p {
            font-size: 1rem;
        }
    }
    </style>';
}
?>
