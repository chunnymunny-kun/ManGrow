-- Create join_requests table for users requesting to join organizations
CREATE TABLE IF NOT EXISTS join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    responded_by INT NULL,
    INDEX (org_id),
    INDEX (user_id),
    INDEX (status),
    UNIQUE KEY unique_pending_request (org_id, user_id, status),
    FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES accountstbl(account_id) ON DELETE SET NULL
);