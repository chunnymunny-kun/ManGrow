-- Add reference_number column to ecoshop_transactions table
-- This will be used for transaction receipts and verification

ALTER TABLE ecoshop_transactions 
ADD COLUMN reference_number VARCHAR(20) UNIQUE AFTER transaction_id;

-- Create an index for faster lookups
CREATE INDEX idx_reference_number ON ecoshop_transactions(reference_number);

-- Update existing transactions with reference numbers
-- Format: REF-YYYYMMDD-000001, REF-YYYYMMDD-000002, etc.
UPDATE ecoshop_transactions 
SET reference_number = CONCAT(
    'REF-',
    DATE_FORMAT(transaction_date, '%Y%m%d'),
    '-',
    LPAD(transaction_id, 6, '0')
)
WHERE reference_number IS NULL;