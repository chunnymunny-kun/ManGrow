-- Organizations table schema update
-- This script creates a proper organizations table and migrates existing data

-- Create organizations table
CREATE TABLE IF NOT EXISTS organizations (
    org_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    barangay VARCHAR(255) NOT NULL,
    city_municipality VARCHAR(255) NOT NULL,
    capacity_limit INT NOT NULL DEFAULT 25 CHECK (capacity_limit >= 10 AND capacity_limit <= 50),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (created_by) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    INDEX idx_location (barangay, city_municipality),
    INDEX idx_active (is_active)
);

-- Create organization members table for better relationship management
CREATE TABLE IF NOT EXISTS organization_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    org_id INT NOT NULL,
    account_id INT NOT NULL,
    role ENUM('creator', 'member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (org_id, account_id)
);

-- Migrate existing organization data from accountstbl
INSERT INTO organizations (name, description, barangay, city_municipality, capacity_limit, created_by)
SELECT DISTINCT 
    a.organization as name,
    CONCAT('Organization for ', a.organization) as description,
    COALESCE(a.barangay, 'Not Specified') as barangay,
    COALESCE(a.city_municipality, 'Not Specified') as city_municipality,
    25 as capacity_limit,
    MIN(a.account_id) as created_by
FROM accountstbl a 
WHERE a.organization IS NOT NULL 
    AND a.organization != '' 
    AND a.organization != 'N/A'
GROUP BY a.organization
ON DUPLICATE KEY UPDATE name = name; -- Ignore if already exists

-- Populate organization_members table
INSERT INTO organization_members (org_id, account_id, role)
SELECT 
    o.org_id,
    a.account_id,
    CASE 
        WHEN a.account_id = o.created_by THEN 'creator'
        ELSE 'member'
    END as role
FROM accountstbl a
JOIN organizations o ON a.organization = o.name
WHERE a.organization IS NOT NULL 
    AND a.organization != '' 
    AND a.organization != 'N/A'
ON DUPLICATE KEY UPDATE role = role; -- Ignore if already exists