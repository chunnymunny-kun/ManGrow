<?php
/**
 * Eco Points Notification System
 * Shows notification for unnotified resolved reports
 */

function getUnnotifiedResolvedReports($userId) {
    global $connection;
    
    // Create user_notifications table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS user_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        reference_id INT,
        shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_notification (user_id, notification_type, reference_id)
    )";
    $connection->query($createTable);
    
    // Get resolved reports that have eco points transactions but user hasn't been notified on this page load
    $query = "SELECT DISTINCT t.reference_id as report_id, t.points_awarded, t.created_at,
                     r.description, r.city_municipality, r.barangays
              FROM eco_points_transactions t
              JOIN illegalreportstbl r ON t.reference_id = r.report_id
              WHERE t.user_id = ? 
              AND t.activity_type = 'report_resolved'
              AND t.reference_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM user_notifications un 
                  WHERE un.user_id = t.user_id 
                  AND un.notification_type = 'eco_points_resolved'
                  AND un.reference_id = t.reference_id
              )
              ORDER BY t.created_at DESC
              LIMIT 1";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notification = null;
    if ($result->num_rows > 0) {
        $notification = $result->fetch_assoc();
        
        // Check for any badges awarded in the same timeframe (within 1 minute)
        // Using accountstbl badges field to see if user recently got badges
        $badgeQuery = "SELECT badges FROM accountstbl WHERE account_id = ?";
        $badgeStmt = $connection->prepare($badgeQuery);
        $badgeStmt->bind_param("i", $userId);
        $badgeStmt->execute();
        $badgeResult = $badgeStmt->get_result();
        
        $notification['badge_awarded'] = null;
        if ($badgeResult->num_rows > 0) {
            $userBadges = $badgeResult->fetch_assoc()['badges'];
            
            // Check if user has resolution badges (simplified check)
            $resolutionBadges = ['First Resolution', 'Alert Citizen', 'Community Protector', 'Super Watchdog', 'Vigilant Guardian', 'Report Veteran'];
            foreach ($resolutionBadges as $badge) {
                if (strpos($userBadges, $badge) !== false) {
                    $notification['badge_awarded'] = $badge;
                    break; // Show the first found badge
                }
            }
        }
        $badgeStmt->close();
    }
    
    $stmt->close();
    return $notification;
}

function markEcoPointsNotificationAsShown($userId, $reportId) {
    global $connection;
    
    // Create user_notifications table if it doesn't exist
    $createTable = "CREATE TABLE IF NOT EXISTS user_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        reference_id INT,
        shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_notification (user_id, notification_type, reference_id)
    )";
    $connection->query($createTable);
    
    // Mark as shown
    $query = "INSERT IGNORE INTO user_notifications (user_id, notification_type, reference_id) 
              VALUES (?, 'eco_points_resolved', ?)";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ii", $userId, $reportId);
    $stmt->execute();
    $stmt->close();
}

function generateEcoPointsNotificationHTML($notification) {
    if (!$notification) return '';
    
    $points = $notification['points_awarded'];
    $badge = $notification['badge_awarded'];
    $reportLocation = $notification['city_municipality'] . ', ' . $notification['barangays'];
    
    return '
    <!-- Eco Points Notification for Resolved Report -->
    <div class="eco-points-notification" id="ecoPointsNotification" style="display: block;">
        <div class="eco-points-content">
            <div class="eco-points-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="eco-points-text">
                <h4>' . ($badge ? 'Report Resolved + Badge Earned!' : 'Report Resolved!') . '</h4>
                <p>Your environmental report from ' . htmlspecialchars($reportLocation) . ' has been successfully resolved by authorities.</p>
                <div class="eco-points-details">
                    <div class="point-detail">
                        <i class="fas fa-coins"></i> +' . $points . ' Eco Points Earned
                    </div>
                    ' . ($badge ? '<div class="point-detail badge-detail">
                        <i class="fas fa-medal"></i> Badge: ' . htmlspecialchars($badge) . '
                    </div>' : '') . '
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const notification = document.getElementById("ecoPointsNotification");
        if (notification) {
            // Show notification
            setTimeout(() => {
                notification.classList.add("show");
            }, 500);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                notification.classList.remove("show");
                notification.classList.add("hide");
                
                setTimeout(() => {
                    notification.style.display = "none";
                    
                    // Mark as shown via AJAX
                    fetch("mark_notification_shown.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            report_id: ' . $notification['report_id'] . '
                        })
                    });
                }, 400);
            }, 5000);
            
            // Allow manual close
            notification.addEventListener("click", function() {
                notification.classList.remove("show");
                notification.classList.add("hide");
                
                setTimeout(() => {
                    notification.style.display = "none";
                    
                    // Mark as shown via AJAX
                    fetch("mark_notification_shown.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            report_id: ' . $notification['report_id'] . '
                        })
                    });
                }, 400);
            });
        }
    });
    </script>';
}

function generateEcoPointsNotificationCSS() {
    return '
    <style>
    /* Eco Points Notification Popup */
    .eco-points-notification {
        position: fixed;
        top: 20px;
        right: -400px;
        width: 350px;
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        overflow: hidden;
    }
    
    .eco-points-notification::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #f1c40f, #f39c12, #e67e22);
    }
    
    .eco-points-notification.show {
        right: 20px;
        animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .eco-points-notification.hide {
        right: -400px;
        animation: slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .eco-points-content {
        display: flex;
        align-items: center;
        padding: 20px;
        gap: 15px;
    }
    
    .eco-points-icon {
        font-size: 32px;
        color: #fff;
        background: rgba(255, 255, 255, 0.2);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .eco-points-text {
        flex: 1;
    }
    
    .eco-points-text h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: white;
    }
    
    .eco-points-text p {
        margin: 0 0 12px 0;
        font-size: 14px;
        opacity: 0.9;
        line-height: 1.4;
    }
    
    .eco-points-details {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .point-detail {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.15);
        padding: 6px 10px;
        border-radius: 6px;
    }
    
    .point-detail i {
        font-size: 14px;
        color: #f1c40f;
    }
    
    .badge-detail {
        background: rgba(255, 255, 255, 0.2) !important;
    }
    
    .badge-detail i {
        color: #ffd700 !important;
    }
    
    @keyframes slideInRight {
        from {
            right: -400px;
            opacity: 0;
            transform: translateX(50px);
        }
        to {
            right: 20px;
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            right: 20px;
            opacity: 1;
            transform: translateX(0);
        }
        to {
            right: -400px;
            opacity: 0;
            transform: translateX(50px);
        }
    }
    
    @media (max-width: 768px) {
        .eco-points-notification {
            width: calc(100% - 40px);
            right: -100%;
            top: 10px;
        }
        
        .eco-points-notification.show {
            right: 20px;
        }
        
        .eco-points-notification.hide {
            right: -100%;
        }
    }
    </style>';
}
?>
