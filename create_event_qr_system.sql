-- Event Attendance QR System Tables
-- This creates the necessary tables for QR-based event attendance

-- Table to store QR codes for events
CREATE TABLE IF NOT EXISTS event_qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    qr_type ENUM('checkin', 'checkout') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES eventstbl(event_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    INDEX idx_event_qr (event_id, qr_type),
    INDEX idx_token (qr_token),
    INDEX idx_active (is_active)
);

-- Table to track actual event attendance with timestamps
CREATE TABLE IF NOT EXISTS event_attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    checkin_time TIMESTAMP NULL,
    checkout_time TIMESTAMP NULL,
    checkin_qr_token VARCHAR(255),
    checkout_qr_token VARCHAR(255),
    attendance_status ENUM('checked_in', 'completed', 'no_show') DEFAULT 'checked_in',
    points_awarded INT DEFAULT 0,
    badges_awarded TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES eventstbl(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (event_id, user_id),
    INDEX idx_event_attendance (event_id),
    INDEX idx_user_attendance (user_id),
    INDEX idx_status (attendance_status)
);

-- Add eco_points column to eventstbl if it doesn't exist
ALTER TABLE eventstbl 
ADD COLUMN IF NOT EXISTS eco_points INT DEFAULT 50 COMMENT 'Eco points awarded for completing this event';

-- Add completion tracking to eventstbl if it doesn't exist
ALTER TABLE eventstbl 
ADD COLUMN IF NOT EXISTS completion_status ENUM('pending', 'ongoing', 'completed', 'cancelled') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS completed_by INT NULL,
ADD COLUMN IF NOT EXISTS qr_checkin_enabled BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS qr_checkout_enabled BOOLEAN DEFAULT TRUE;

-- Add foreign key for completed_by if it doesn't exist
-- ALTER TABLE eventstbl ADD FOREIGN KEY (completed_by) REFERENCES accountstbl(account_id) ON DELETE SET NULL;
