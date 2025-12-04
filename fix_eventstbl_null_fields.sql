-- Fix eventstbl to allow NULL for optional fields
-- These fields should be NULL for Announcements

ALTER TABLE eventstbl 
MODIFY COLUMN venue VARCHAR(255) NULL DEFAULT NULL,
MODIFY COLUMN barangay VARCHAR(100) NULL DEFAULT NULL,
MODIFY COLUMN city_municipality VARCHAR(100) NULL DEFAULT NULL,
MODIFY COLUMN area_no VARCHAR(20) NULL DEFAULT NULL,
MODIFY COLUMN event_type VARCHAR(255) NULL DEFAULT NULL;

-- Show updated structure
DESCRIBE eventstbl;
