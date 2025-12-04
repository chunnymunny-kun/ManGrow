<?php
/**
 * Eco Points System Configuration
 * Centralized configuration for point values, limits, and system settings
 */

class EcoPointsConfig {
    
    /**
     * Base point values for different activities
     */
    const POINT_VALUES = [
        // Event-related points
        'event_attendance_base' => 50,
        'event_organizer_bonus' => 25,
        'featured_event_bonus' => 20,
        'cross_barangay_event_bonus' => 15,
        
        // Report-related points
        'report_base_points' => 25,
        'report_priority_multipliers' => [
            'Emergency' => 2.0,
            'Normal' => 1.0
        ],
        'report_quality_bonus' => 10, // For reports with good evidence
        
        // Daily activities
        'daily_login_base' => 5,
        'daily_login_streak_bonus' => 1, // Per consecutive day, max 6
        'weekly_login_bonus' => 15, // For logging in every day of the week
        
        // Social features
        'referral_points' => 50,
        'referral_verification_requirement' => true, // Referred user must verify account
        'referral_activity_requirement' => 1, // Referred user must earn at least 1 point
        
        // Organization bonuses
        'organization_collaboration_bonus' => 5, // Bonus per member when organization achieves milestones
        'organization_ranking_bonuses' => [
            1 => 50,  // 1st place organization
            2 => 30,  // 2nd place organization  
            3 => 20,  // 3rd place organization
            'top_10_percent' => 10 // Top 10% organizations
        ],
        'organization_milestone_bonuses' => [
            1000 => 25,   // First 1K points as organization
            5000 => 50,   // 5K points milestone
            10000 => 100, // 10K points milestone
            25000 => 200, // 25K points milestone
            50000 => 500  // 50K points milestone
        ],
        
        // Badge achievements
        'badge_bonus_base' => 25,
        'badge_rarity_multipliers' => [
            'Common' => 1.0,
            'Uncommon' => 1.5,
            'Rare' => 2.0,
            'Epic' => 3.0,
            'Legendary' => 5.0
        ],
        
        // Special achievements
        'profile_completion_bonus' => 30,
        'first_time_bonuses' => [
            'first_event' => 20,
            'first_report' => 15,
            'first_badge' => 10
        ]
    ];
    
    /**
     * System limits and restrictions
     */
    const LIMITS = [
        'max_daily_points' => 500,
        'max_weekly_points' => 2000,
        'max_monthly_points' => 7500,
        'min_account_age_days' => 1,
        'leaderboard_qualification_days' => 7,
        'leaderboard_min_activities' => 3,
        'referral_cooldown_hours' => 24,
        'daily_login_grace_hours' => 6 // Grace period for daily login (for different timezones)
    ];
    
    /**
     * Eco shop pricing tiers and categories
     */
    const SHOP_CONFIG = [
        'pricing_tiers' => [
            'tier_1' => ['min' => 100, 'max' => 500, 'description' => 'Basic Rewards'],
            'tier_2' => ['min' => 500, 'max' => 1500, 'description' => 'Premium Rewards'],
            'tier_3' => ['min' => 1500, 'max' => 5000, 'description' => 'Elite Rewards'],
            'tier_4' => ['min' => 5000, 'max' => 999999, 'description' => 'Legendary Rewards']
        ],
        'categories' => [
            'conservation' => ['icon' => 'fas fa-leaf', 'color' => '#4CAF50'],
            'education' => ['icon' => 'fas fa-book', 'color' => '#2196F3'],
            'accessories' => ['icon' => 'fas fa-shopping-bag', 'color' => '#FF9800'],
            'vouchers' => ['icon' => 'fas fa-ticket-alt', 'color' => '#9C27B0'],
            'apparel' => ['icon' => 'fas fa-tshirt', 'color' => '#795548'],
            'personal_care' => ['icon' => 'fas fa-spa', 'color' => '#E91E63']
        ],
        'stock_warning_threshold' => 5,
        'low_stock_notification' => true
    ];
    
    /**
     * Leaderboard configurations
     */
    const LEADERBOARD_CONFIG = [
        'periods' => [
            'weekly' => ['days' => 7, 'reset_day' => 'Monday'],
            'monthly' => ['days' => 30, 'reset_day' => 1], // First day of month
            'quarterly' => ['days' => 90, 'reset_day' => 1],
            'yearly' => ['days' => 365, 'reset_day' => 1]
        ],
        'categories' => [
            'individual' => ['table' => 'accountstbl', 'field' => 'account_id'],
            'barangay' => ['table' => 'accountstbl', 'field' => 'barangay'],
            'municipality' => ['table' => 'accountstbl', 'field' => 'city_municipality'],
            'organization' => ['table' => 'accountstbl', 'field' => 'organization']
        ],
        'reward_thresholds' => [
            'top_1_percentage' => 1,
            'top_5_percentage' => 5,
            'top_10_percentage' => 10
        ]
    ];
    
    /**
     * Badge integration settings
     */
    const BADGE_CONFIG = [
        'point_milestones' => [
            100 => 'First Century',
            250 => 'Quarter Achiever',
            500 => 'Half Thousand',
            1000 => 'Thousand Club',
            2500 => 'Elite Member',
            5000 => 'Eco Champion',
            10000 => 'Conservation Legend',
            25000 => 'Eco Master',
            50000 => 'Green Guru'
        ],
        'activity_milestones' => [
            'events' => [1, 5, 10, 25, 50, 100],
            'reports' => [1, 3, 5, 10, 25, 50],
            'login_streak' => [7, 30, 60, 100, 365],
            'referrals' => [1, 3, 5, 10, 25]
        ],
        'seasonal_badges' => [
            'earth_day' => ['month' => 4, 'day' => 22],
            'environment_day' => ['month' => 6, 'day' => 5],
            'tree_day' => ['month' => 3, 'day' => 21]
        ]
    ];
    
    /**
     * Weekly tasks configuration
     */
    const WEEKLY_TASKS = [
        'task_types' => [
            'attend_events' => [
                'name' => 'Event Attendee',
                'description' => 'Attend {count} events this week',
                'points' => 75,
                'targets' => [1, 2, 3]
            ],
            'submit_reports' => [
                'name' => 'Environmental Guardian',
                'description' => 'Submit {count} environmental reports',
                'points' => 50,
                'targets' => [1, 2]
            ],
            'daily_logins' => [
                'name' => 'Consistent Contributor',
                'description' => 'Log in for {count} consecutive days',
                'points' => 40,
                'targets' => [3, 5, 7]
            ],
            'profile_engagement' => [
                'name' => 'Profile Perfectionist',
                'description' => 'Update your profile information',
                'points' => 25,
                'targets' => [1]
            ],
            'social_sharing' => [
                'name' => 'Eco Advocate',
                'description' => 'Share {count} environmental activities',
                'points' => 30,
                'targets' => [1, 3]
            ]
        ],
        'weekly_reset_day' => 'Monday',
        'max_active_tasks' => 5,
        'completion_bonus' => 50 // Bonus for completing all weekly tasks
    ];
    
    /**
     * Anti-fraud and security settings
     */
    const SECURITY_CONFIG = [
        'point_validation' => [
            'enable_rate_limiting' => true,
            'max_transactions_per_hour' => 20,
            'suspicious_threshold' => 100, // Points earned in short time
            'admin_notification_threshold' => 500
        ],
        'duplicate_prevention' => [
            'event_attendance_window' => 3600, // 1 hour window for duplicate prevention
            'report_submission_cooldown' => 1800, // 30 minutes between reports
            'login_bonus_timezone_grace' => 6 // Hours grace for timezone differences
        ],
        'audit_settings' => [
            'log_all_transactions' => true,
            'retain_logs_days' => 365,
            'enable_rollback' => true,
            'admin_approval_threshold' => 1000 // Points requiring admin approval
        ]
    ];
    
    /**
     * Notification settings
     */
    const NOTIFICATION_CONFIG = [
        'point_awards' => [
            'show_small_awards' => false, // Don't show notifications for awards under threshold
            'small_award_threshold' => 10,
            'batch_notifications' => true, // Batch multiple small awards
            'celebration_threshold' => 100 // Points that trigger celebration animation
        ],
        'milestones' => [
            'enable_milestone_notifications' => true,
            'milestone_celebration_duration' => 5000, // milliseconds
            'sound_notifications' => false
        ],
        'leaderboard' => [
            'rank_change_notifications' => true,
            'new_top_10_notification' => true,
            'weekly_summary' => true
        ]
    ];
    
    /**
     * Get configuration by key
     */
    public static function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = null;
        
        // Map first key to constant
        switch ($keys[0]) {
            case 'points':
                $value = self::POINT_VALUES;
                break;
            case 'limits':
                $value = self::LIMITS;
                break;
            case 'shop':
                $value = self::SHOP_CONFIG;
                break;
            case 'leaderboard':
                $value = self::LEADERBOARD_CONFIG;
                break;
            case 'badges':
                $value = self::BADGE_CONFIG;
                break;
            case 'tasks':
                $value = self::WEEKLY_TASKS;
                break;
            case 'security':
                $value = self::SECURITY_CONFIG;
                break;
            case 'notifications':
                $value = self::NOTIFICATION_CONFIG;
                break;
            default:
                return $default;
        }
        
        // Navigate through nested keys
        for ($i = 1; $i < count($keys); $i++) {
            if (is_array($value) && isset($value[$keys[$i]])) {
                $value = $value[$keys[$i]];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Get point value for specific activity
     */
    public static function getPointValue($activity, $context = []) {
        switch ($activity) {
            case 'event_attendance':
                $base = self::get('points.event_attendance_base', 50);
                $bonus = 0;
                
                if (!empty($context['is_organizer'])) {
                    $bonus += self::get('points.event_organizer_bonus', 25);
                }
                
                if (!empty($context['is_featured'])) {
                    $bonus += self::get('points.featured_event_bonus', 20);
                }
                
                if (!empty($context['cross_barangay'])) {
                    $bonus += self::get('points.cross_barangay_event_bonus', 15);
                }
                
                return $base + $bonus;
                
            case 'report_resolved':
                $base = self::get('points.report_base_points', 25);
                $priority = $context['priority'] ?? 'Normal';
                $multiplier = self::get("points.report_priority_multipliers.$priority", 1.0);
                
                $points = (int)($base * $multiplier);
                
                if (!empty($context['has_evidence'])) {
                    $points += self::get('points.report_quality_bonus', 10);
                }
                
                return $points;
                
            case 'daily_login':
                $base = self::get('points.daily_login_base', 5);
                $streak = $context['streak_day'] ?? 1;
                $streakBonus = min($streak - 1, 6) * self::get('points.daily_login_streak_bonus', 1);
                
                return $base + $streakBonus;
                
            case 'badge_bonus':
                $base = self::get('points.badge_bonus_base', 25);
                $rarity = $context['rarity'] ?? 'Common';
                $multiplier = self::get("points.badge_rarity_multipliers.$rarity", 1.0);
                
                return (int)($base * $multiplier);
                
            default:
                return self::get("points.$activity", 0);
        }
    }
    
    /**
     * Check if points exceed daily limit
     */
    public static function exceedsDailyLimit($currentDailyPoints, $additionalPoints) {
        $limit = self::get('limits.max_daily_points', 500);
        return ($currentDailyPoints + $additionalPoints) > $limit;
    }
    
    /**
     * Get next milestone for user
     */
    public static function getNextMilestone($currentPoints) {
        $milestones = self::get('badges.point_milestones', []);
        
        foreach ($milestones as $points => $name) {
            if ($currentPoints < $points) {
                return [
                    'points' => $points,
                    'name' => $name,
                    'progress' => ($currentPoints / $points) * 100,
                    'remaining' => $points - $currentPoints
                ];
            }
        }
        
        return null; // User has reached all milestones
    }
    
    /**
     * Get pricing tier for shop item
     */
    public static function getPricingTier($points) {
        $tiers = self::get('shop.pricing_tiers', []);
        
        foreach ($tiers as $tier => $config) {
            if ($points >= $config['min'] && $points <= $config['max']) {
                return [
                    'tier' => $tier,
                    'description' => $config['description'],
                    'min' => $config['min'],
                    'max' => $config['max']
                ];
            }
        }
        
        return ['tier' => 'custom', 'description' => 'Special Item'];
    }
    
    /**
     * Validate configuration
     */
    public static function validate() {
        $errors = [];
        
        // Check required configurations
        if (self::get('limits.max_daily_points') <= 0) {
            $errors[] = 'Invalid max_daily_points configuration';
        }
        
        if (self::get('points.event_attendance_base') <= 0) {
            $errors[] = 'Invalid event_attendance_base points';
        }
        
        // Add more validation rules as needed
        
        return $errors;
    }
}
?>
