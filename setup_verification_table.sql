-- Create user_verification table for temporary storage during registration
CREATE TABLE IF NOT EXISTS user_verification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    personal_email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    barangay VARCHAR(255) NOT NULL,
    city_municipality VARCHAR(255) NOT NULL,
    verification_token VARCHAR(64) NOT NULL UNIQUE,
    token_expiry DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_token (verification_token),
    INDEX idx_expiry (token_expiry)
);

-- Create a cleanup event to remove expired verification records
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS cleanup_expired_verifications
ON SCHEDULE EVERY 1 MINUTE
DO
  DELETE FROM user_verification WHERE token_expiry < NOW();
