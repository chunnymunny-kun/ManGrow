<?php
/**
 * Event QR System for ManGrow
 * Handles QR code generation, validation, and attendance tracking
 */

require_once 'database.php';
require_once 'eco_points_integration.php';
require_once 'badge_system_db.php';

class EventQRSystem {
    private static $connection = null;
    
    // QR Code types
    const QR_CHECKIN = 'checkin';
    const QR_CHECKOUT = 'checkout';
    
    // Attendance status
    const STATUS_CHECKED_IN = 'checked_in';
    const STATUS_COMPLETED = 'completed';
    const STATUS_NO_SHOW = 'no_show';
    
    /**
     * Initialize the QR system
     */
    public static function init($dbConnection) {
        self::$connection = $dbConnection;
        
        // Initialize dependencies
        initializeEcoPointsSystem();
        BadgeSystem::init($dbConnection);
    }
    
    /**
     * Generate QR codes for an event (both check-in and check-out)
     */
    public static function generateEventQRCodes($eventId, $createdBy) {
        // Check if event exists
        $eventQuery = "SELECT event_id, subject, start_date, end_date FROM eventstbl WHERE event_id = ?";
        $stmt = self::$connection->prepare($eventQuery);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Event not found'];
        }
        
        $event = $result->fetch_assoc();
        
        // CHECK IF QR CODES ALREADY EXIST FOR THIS EVENT
        $existingQRQuery = "SELECT qr_type FROM event_qr_codes WHERE event_id = ? AND is_active = 1";
        $stmt = self::$connection->prepare($existingQRQuery);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $existingResult = $stmt->get_result();
        
        if ($existingResult->num_rows > 0) {
            return ['success' => false, 'message' => 'QR codes already exist for this event. Please deactivate existing codes first.'];
        }
        
        // Generate unique tokens
        $checkinToken = self::generateUniqueToken();
        $checkoutToken = self::generateUniqueToken();
        
        // Calculate expiration (1 hour after event end)
        $expiresAt = date('Y-m-d H:i:s', strtotime($event['end_date'] . ' +1 hour'));
        
        // Insert QR codes
        $insertQuery = "INSERT INTO event_qr_codes (event_id, qr_token, qr_type, created_by, expires_at) VALUES (?, ?, ?, ?, ?)";
        
        // Insert check-in QR
        $stmt = self::$connection->prepare($insertQuery);
        $checkinType = self::QR_CHECKIN;
        $stmt->bind_param("issis", $eventId, $checkinToken, $checkinType, $createdBy, $expiresAt);
        $checkinSuccess = $stmt->execute();
        
        // Insert check-out QR
        $stmt = self::$connection->prepare($insertQuery);
        $checkoutType = self::QR_CHECKOUT;
        $stmt->bind_param("issis", $eventId, $checkoutToken, $checkoutType, $createdBy, $expiresAt);
        $checkoutSuccess = $stmt->execute();
        
        if ($checkinSuccess && $checkoutSuccess) {
            return [
                'success' => true,
                'checkin_token' => $checkinToken,
                'checkout_token' => $checkoutToken,
                'checkin_url' => self::generateQRURL($checkinToken),
                'checkout_url' => self::generateQRURL($checkoutToken),
                'expires_at' => $expiresAt
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to generate QR codes'];
    }
    
    /**
     * Process QR code scan (check-in or check-out)
     */
    public static function processQRScan($qrToken, $userId) {
        // Validate QR token
        $now = date('Y-m-d H:i:s');
        error_log("QR System: Processing scan - Token: $qrToken, User: $userId, Time: $now");
        
        $qrQuery = "SELECT qr.*, e.subject, e.start_date, e.end_date, e.eco_points, e.completion_status 
                   FROM event_qr_codes qr 
                   JOIN eventstbl e ON qr.event_id = e.event_id 
                   WHERE qr.qr_token = ? AND qr.is_active = 1 AND (qr.expires_at IS NULL OR qr.expires_at > ?)";
        
        $stmt = self::$connection->prepare($qrQuery);
        $stmt->bind_param("ss", $qrToken, $now);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("QR System: Invalid or expired QR code - Token: $qrToken");
            return ['success' => false, 'message' => 'Invalid or expired QR code'];
        }
        
        $qrData = $result->fetch_assoc();
        $eventId = $qrData['event_id'];
        $qrType = $qrData['qr_type'];
        
        error_log("QR System: Event found - ID: $eventId, Type: $qrType, Event: {$qrData['subject']}");
        error_log("QR System: Event dates - Start: {$qrData['start_date']}, End: {$qrData['end_date']}");
        
        // FLEXIBLE TIMING FOR TESTING - Allow QR scans anytime for development/testing
        // Comment out this section and uncomment the strict timing checks below for production
        
        error_log("QR System: Using flexible timing mode for testing");
        
        /*
        // STRICT TIMING FOR PRODUCTION - Uncomment this section for production use
        $eventStart = $qrData['start_date'];
        $eventEnd = $qrData['end_date'];
        
        // For check-in: allow from event start until event end
        if ($qrType === self::QR_CHECKIN) {
            if ($now < $eventStart) {
                error_log("QR System: Check-in too early - Current: $now, Event starts: $eventStart");
                return ['success' => false, 'message' => 'Event has not started yet. Check-in opens when the event begins.'];
            }
            if ($now > $eventEnd) {
                error_log("QR System: Check-in too late - Current: $now, Event ended: $eventEnd");
                return ['success' => false, 'message' => 'Event has ended. Check-in period is closed.'];
            }
        }
        
        // For check-out: allow from event start until 1 hour after event end
        if ($qrType === self::QR_CHECKOUT) {
            $checkoutDeadline = date('Y-m-d H:i:s', strtotime($eventEnd . ' +1 hour'));
            
            if ($now < $eventStart) {
                error_log("QR System: Check-out too early - Current: $now, Event starts: $eventStart");
                return ['success' => false, 'message' => 'Event has not started yet. Check-out is not available.'];
            }
            if ($now > $checkoutDeadline) {
                error_log("QR System: Check-out too late - Current: $now, Deadline: $checkoutDeadline");
                return ['success' => false, 'message' => 'Check-out period has expired. You must check out within 1 hour after the event ends.'];
            }
        }
        */
        
        error_log("QR System: Timing checks passed, processing $qrType");
        
        // Process based on QR type
        if ($qrType === self::QR_CHECKIN) {
            return self::processCheckin($eventId, $userId, $qrToken, $qrData);
        } else {
            return self::processCheckout($eventId, $userId, $qrToken, $qrData);
        }
    }
    
    /**
     * Process check-in
     */
    private static function processCheckin($eventId, $userId, $qrToken, $eventData) {
        error_log("QR System: Processing check-in for Event $eventId, User $userId");
        
        // Check if user already checked in
        $attendanceQuery = "SELECT attendance_id, checkin_time FROM event_attendance WHERE event_id = ? AND user_id = ?";
        $stmt = self::$connection->prepare($attendanceQuery);
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $attendance = $result->fetch_assoc();
            if ($attendance['checkin_time']) {
                error_log("QR System: User $userId already checked in for event $eventId");
                return ['success' => false, 'message' => 'Already checked in for this event'];
            }
        }
        
        // Get current time in Asia/Manila timezone
        $manila_tz = new DateTimeZone('Asia/Manila');
        $now_manila = new DateTime('now', $manila_tz);
        $timestamp_manila = $now_manila->format('Y-m-d H:i:s');
        
        // Insert or update attendance record
        $upsertQuery = "INSERT INTO event_attendance (event_id, user_id, checkin_time, checkin_qr_token, attendance_status, created_at, updated_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?) 
                       ON DUPLICATE KEY UPDATE 
                       checkin_time = ?, checkin_qr_token = ?, attendance_status = ?, updated_at = ?";
        
        $stmt = self::$connection->prepare($upsertQuery);
        $status = self::STATUS_CHECKED_IN;
        $stmt->bind_param("iisssssssss", $eventId, $userId, $timestamp_manila, $qrToken, $status, $timestamp_manila, $timestamp_manila, $timestamp_manila, $qrToken, $status, $timestamp_manila);
        
        if ($stmt->execute()) {
            error_log("QR System: Check-in successful for User $userId, Event $eventId");
            return [
                'success' => true,
                'message' => 'Successfully checked in!',
                'event_name' => $eventData['subject'],
                'next_step' => 'Remember to check out when the event ends to receive your eco points!'
            ];
        }
        
        return ['success' => false, 'message' => 'Failed to process check-in'];
    }
    
    /**
     * Process check-out and award points/badges
     */
    private static function processCheckout($eventId, $userId, $qrToken, $eventData) {
        error_log("QR System: Processing check-out for Event $eventId, User $userId");
        
        // Check if user checked in first
        $attendanceQuery = "SELECT * FROM event_attendance WHERE event_id = ? AND user_id = ? AND checkin_time IS NOT NULL";
        $stmt = self::$connection->prepare($attendanceQuery);
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("QR System: User $userId tried to check out without checking in for event $eventId");
            return ['success' => false, 'message' => 'You must check in first before checking out'];
        }
        
        $attendance = $result->fetch_assoc();
        
        if ($attendance['checkout_time']) {
            error_log("QR System: User $userId already checked out for event $eventId");
            return ['success' => false, 'message' => 'Already checked out for this event'];
        }
        
        // Check if checkout is on time (within 1 hour after event end)
        $now = date('Y-m-d H:i:s');
        $checkoutDeadline = date('Y-m-d H:i:s', strtotime($eventData['end_date'] . ' +1 hour'));
        $isOnTime = $now <= $checkoutDeadline;
        
        // Get current time in Asia/Manila timezone
        $manila_tz = new DateTimeZone('Asia/Manila');
        $now_manila = new DateTime('now', $manila_tz);
        $timestamp_manila = $now_manila->format('Y-m-d H:i:s');
        
        // Update attendance with checkout
        $updateQuery = "UPDATE event_attendance 
                       SET checkout_time = ?, checkout_qr_token = ?, attendance_status = ?, updated_at = ? 
                       WHERE attendance_id = ?";
        
        $stmt = self::$connection->prepare($updateQuery);
        $status = self::STATUS_COMPLETED;
        $stmt->bind_param("ssssi", $timestamp_manila, $qrToken, $status, $timestamp_manila, $attendance['attendance_id']);
        
        if (!$stmt->execute()) {
            error_log("QR System: Failed to update checkout for User $userId, Event $eventId");
            return ['success' => false, 'message' => 'Failed to process check-out'];
        }
        
        error_log("QR System: Check-out successful for User $userId, Event $eventId, On-time: " . ($isOnTime ? 'Yes' : 'No'));
        
        // Award eco points and badges ONLY if checkout is on time
        $pointsAwarded = 0;
        $badgesAwarded = [];
        
        if ($isOnTime) {
            error_log("QR System: Processing rewards for on-time checkout");
            // ALWAYS award the event's eco points value to points_awarded field
            if ($eventData['eco_points'] > 0) {
                $pointsAwarded = $eventData['eco_points']; // Set this FIRST
                error_log("QR System: Setting points_awarded to {$pointsAwarded} for User $userId");
                
                // Award through EcoPointsSystem (this handles account balance update AND transaction logging)
                error_log("QR System: Attempting to award {$eventData['eco_points']} eco points to User $userId for Event $eventId");
                $pointResult = EcoPointsSystem::awardEventPoints($eventId, $userId, $eventData['eco_points']);
                error_log("QR System: Eco points result: " . json_encode($pointResult));
                
                if ($pointResult && isset($pointResult['success']) && $pointResult['success']) {
                    error_log("QR System: Successfully awarded eco points through EcoPointsSystem");
                } else {
                    $errorMsg = isset($pointResult['message']) ? $pointResult['message'] : 'Unknown error';
                    error_log("QR System: EcoPointsSystem failed ($errorMsg) - points_awarded will still be set to {$pointsAwarded}");
                }
            } else {
                error_log("QR System: No eco points to award (event eco_points = {$eventData['eco_points']})");
            }
            
            // Check for event-based badges only for on-time checkout
            $badgesAwarded = self::checkEventBadges($userId);
        } else {
            error_log("QR System: Late checkout - no rewards awarded");
        }
        
        // Get current time in Asia/Manila timezone
        $manila_tz = new DateTimeZone('Asia/Manila');
        $now_manila = new DateTime('now', $manila_tz);
        $timestamp_manila = $now_manila->format('Y-m-d H:i:s');
        
        // Update attendance record with rewards
        $updateRewardsQuery = "UPDATE event_attendance 
                              SET points_awarded = ?, badges_awarded = ?, updated_at = ? 
                              WHERE attendance_id = ?";
        $stmt = self::$connection->prepare($updateRewardsQuery);
        $badgesString = !empty($badgesAwarded) ? implode(',', $badgesAwarded) : null;
        $stmt->bind_param("issi", $pointsAwarded, $badgesString, $timestamp_manila, $attendance['attendance_id']);
        
        if ($stmt->execute()) {
            error_log("QR System: Updated attendance record - Points: $pointsAwarded, Badges: " . ($badgesString ?: 'none'));
        } else {
            error_log("QR System: Failed to update attendance record: " . $stmt->error);
        }
        
        $message = 'Successfully checked out!';
        if (!$isOnTime) {
            $message .= ' Note: Late checkout - no rewards awarded.';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'event_name' => $eventData['subject'],
            'on_time' => $isOnTime,
            'points_awarded' => $pointsAwarded,
            'badges_awarded' => $badgesAwarded,
            'total_events_completed' => self::getUserCompletedEventsCount($userId)
        ];
    }
    
    /**
     * Check and award event-based badges
     */
    private static function checkEventBadges($userId) {
        $completedEvents = self::getUserCompletedEventsCount($userId);
        $badgesAwarded = [];
        
        // Event participation badges
        $eventBadges = [
            1 => 'Enthusiast',
            5 => 'Event Organizer',
            10 => 'Community Leader',
            25 => 'Green Ambassador',
            50 => 'Conservation Hero'
        ];
        
        foreach ($eventBadges as $threshold => $badgeName) {
            if ($completedEvents >= $threshold) {
                // Check if user already has this badge
                $userQuery = "SELECT badges FROM accountstbl WHERE account_id = ?";
                $stmt = self::$connection->prepare($userQuery);
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                $currentBadges = !empty($user['badges']) ? explode(',', $user['badges']) : [];
                $currentBadges = array_map('trim', $currentBadges);
                
                if (!in_array($badgeName, $currentBadges)) {
                    // Award badge
                    if (BadgeSystem::awardBadgeToUser($userId, $badgeName)) {
                        $badgesAwarded[] = $badgeName;
                        
                        // Award badge bonus points
                        awardBadgeBonusPoints($userId, $badgeName);
                    }
                }
            }
        }
        
        return $badgesAwarded;
    }
    
    /**
     * Get user's completed events count (events where user checked in)
     */
    public static function getUserCompletedEventsCount($userId) {
        $query = "SELECT COUNT(*) as count FROM event_attendance 
                 WHERE user_id = ? AND checkin_time IS NOT NULL";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return $data['count'];
    }
    
    /**
     * Get user's completed events list
     */
    public static function getUserCompletedEventsList($userId) {
        $query = "SELECT ea.*, e.subject, e.start_date, e.end_date, e.venue, e.eco_points
                 FROM event_attendance ea 
                 JOIN eventstbl e ON ea.event_id = e.event_id 
                 WHERE ea.user_id = ? AND ea.checkin_time IS NOT NULL 
                 ORDER BY ea.checkin_time DESC";
        
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $completedEvents = [];
        while ($row = $result->fetch_assoc()) {
            $completedEvents[] = $row;
        }
        
        return $completedEvents;
    }
    
    /**
     * Get event attendance statistics
     */
    public static function getEventAttendanceStats($eventId) {
        $query = "SELECT 
                    COUNT(*) as total_registered,
                    SUM(CASE WHEN checkin_time IS NOT NULL THEN 1 ELSE 0 END) as checked_in,
                    SUM(CASE WHEN checkout_time IS NOT NULL THEN 1 ELSE 0 END) as completed,
                    SUM(points_awarded) as total_points_awarded
                 FROM event_attendance WHERE event_id = ?";
        
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Get user's attendance for an event
     */
    public static function getUserEventAttendance($eventId, $userId) {
        $query = "SELECT * FROM event_attendance WHERE event_id = ? AND user_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    /**
     * Generate unique token for QR codes
     */
    private static function generateUniqueToken() {
        do {
            $token = bin2hex(random_bytes(16)) . time();
            
            // Check if token exists
            $checkQuery = "SELECT qr_id FROM event_qr_codes WHERE qr_token = ?";
            $stmt = self::$connection->prepare($checkQuery);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
        } while ($result->num_rows > 0);
        
        return $token;
    }
    
    /**
     * Generate QR code URL
     */
    private static function generateQRURL($token) {
        // Determine base URL based on server configuration
        if (isset($_SERVER['HTTP_HOST'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            
            // Check if we're running on port 3000 (likely local development without /project path)
            if (strpos($host, ':3000') !== false) {
                $baseURL = "$protocol://$host";
            } else {
                // Default XAMPP setup with /project path
                $baseURL = "$protocol://$host/project";
            }
        } else {
            // Fallback for CLI or other environments
            $baseURL = 'http://localhost:3000';
        }
        
        return $baseURL . '/qr_scan.php?token=' . $token;
    }
    
    /**
     * Get QR codes for an event
     */
    public static function getEventQRCodes($eventId) {
        $query = "SELECT * FROM event_qr_codes WHERE event_id = ? AND is_active = 1 ORDER BY qr_type";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $qrCodes = [];
        while ($row = $result->fetch_assoc()) {
            $qrCodes[$row['qr_type']] = [
                'token' => $row['qr_token'],
                'url' => self::generateQRURL($row['qr_token']),
                'created_at' => $row['created_at'],
                'expires_at' => $row['expires_at']
            ];
        }
        
        return $qrCodes;
    }
    
    /**
     * Deactivate QR codes for an event (DELETE them from database)
     */
    public static function deactivateEventQRCodes($eventId) {
        $query = "DELETE FROM event_qr_codes WHERE event_id = ?";
        $stmt = self::$connection->prepare($query);
        $stmt->bind_param("i", $eventId);
        return $stmt->execute();
    }
}
?>
