-- Add forest cover and canopy density columns to mangrovereporttbl
-- Run this SQL script in your database

ALTER TABLE mangrovereporttbl
ADD COLUMN forest_cover_percent DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage of plot covered by mangroves (0-100)',
ADD COLUMN canopy_density_percent DECIMAL(5,2) DEFAULT NULL COMMENT 'Percentage of canopy coverage (0-100)',
ADD COLUMN tree_count INT DEFAULT NULL COMMENT 'Number of trees counted in plot (optional)',
ADD COLUMN calculated_density DECIMAL(10,2) DEFAULT NULL COMMENT 'Trees per hectare (auto-calculated from tree_count)';

-- Add index for better query performance
CREATE INDEX idx_forest_metrics ON mangrovereporttbl(forest_cover_percent, canopy_density_percent);

-- Verify the changes
DESCRIBE mangrovereporttbl;

SELECT 'Database columns added successfully!' as Status;
