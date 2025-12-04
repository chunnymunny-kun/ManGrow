<?php
/**
 * Get notifications for earned eco points and badges from resolved reports
 */

function getReportGamificationNotifications($userId) {
    global $connection;
    
    // Get recent resolved reports with awarded points/badges (last 30 days)
    $query = "SELECT 
                ir.report_id,
                ir.incident_type,
                ir.priority,
                ir.rating,
                ir.points_awarded,
                ir.badge_awarded,
                ir.rated_at,
                ir.created_at as report_date
              FROM illegalreportstbl ir
              WHERE ir.reporter_id = ? 
                AND ir.action_type = 'Resolved' 
                AND ir.points_awarded > 0
                AND ir.rated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              ORDER BY ir.rated_at DESC";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'type' => 'report_resolved',
            'report_id' => $row['report_id'],
            'incident_type' => $row['incident_type'],
            'priority' => $row['priority'],
            'rating' => $row['rating'],
            'points_awarded' => $row['points_awarded'],
            'badge_awarded' => $row['badge_awarded'],
            'rated_at' => $row['rated_at'],
            'report_date' => $row['report_date']
        ];
    }
    
    return $notifications;
}

/**
 * Display gamification notifications in a styled format
 */
function displayGamificationNotifications($notifications) {
    if (empty($notifications)) {
        return '';
    }
    
    $html = '<div class="gamification-notifications">';
    $html .= '<h4><i class="fas fa-trophy"></i> Recent Achievements</h4>';
    
    foreach ($notifications as $notification) {
        $html .= '<div class="notification-item ' . $notification['type'] . '">';
        
        // Icon based on achievement type
        if (!empty($notification['badge_awarded'])) {
            $html .= '<div class="notification-icon badge-icon"><i class="fas fa-medal"></i></div>';
        } else {
            $html .= '<div class="notification-icon points-icon"><i class="fas fa-coins"></i></div>';
        }
        
        $html .= '<div class="notification-content">';
        
        // Main message
        if (!empty($notification['badge_awarded'])) {
            $html .= '<div class="notification-title">🏆 New Badge Earned!</div>';
            $html .= '<div class="notification-message">You earned the <strong>' . htmlspecialchars($notification['badge_awarded']) . '</strong> badge!</div>';
        }
        
        $html .= '<div class="notification-title">✅ Report Resolved</div>';
        $html .= '<div class="notification-message">';
        $html .= 'Your <strong>' . htmlspecialchars($notification['incident_type']) . '</strong> report has been resolved and rated ';
        
        // Star rating display
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $notification['rating']) {
                $html .= '<span class="star filled">★</span>';
            } else {
                $html .= '<span class="star empty">☆</span>';
            }
        }
        
        $html .= '</div>';
        
        // Points awarded
        $html .= '<div class="notification-points">';
        $html .= '<i class="fas fa-coins"></i> +' . $notification['points_awarded'] . ' eco points earned';
        $html .= '</div>';
        
        // Timestamp
        $html .= '<div class="notification-time">';
        $html .= '<i class="fas fa-clock"></i> ' . timeAgo($notification['rated_at']);
        $html .= '</div>';
        
        $html .= '</div>'; // notification-content
        $html .= '</div>'; // notification-item
    }
    
    $html .= '</div>'; // gamification-notifications
    
    return $html;
}

/**
 * Helper function for time formatting
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

/**
 * Get notification count for badge/point display
 */
function getUnreadNotificationCount($userId) {
    global $connection;
    
    // Count notifications from last 7 days that user might not have seen
    $query = "SELECT COUNT(*) as count 
              FROM illegalreportstbl 
              WHERE reporter_id = ? 
                AND action_type = 'Resolved' 
                AND points_awarded > 0
                AND rated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}
?>
