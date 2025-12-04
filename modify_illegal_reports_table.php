<?php
/**
 * Script to modify illegalreportstbl table to support rating system
 * Run this once to add rating fields to the existing table
 */

include 'database.php';

try {
    // Add rating fields to illegalreportstbl
    $alterQueries = [
        "ALTER TABLE illegalreportstbl ADD COLUMN rating INT DEFAULT NULL COMMENT 'Rating 1-5 stars for resolved reports'",
        "ALTER TABLE illegalreportstbl ADD COLUMN rated_by INT DEFAULT NULL COMMENT 'Admin ID who rated the report'",
        "ALTER TABLE illegalreportstbl ADD COLUMN rated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'When the report was rated'",
        "ALTER TABLE illegalreportstbl ADD COLUMN points_awarded INT DEFAULT 0 COMMENT 'Eco points awarded for this report'",
        "ALTER TABLE illegalreportstbl ADD COLUMN badge_awarded VARCHAR(255) DEFAULT NULL COMMENT 'Badge name if any badge was awarded'"
    ];

    foreach ($alterQueries as $query) {
        try {
            if ($connection->query($query) === TRUE) {
                echo "✅ Successfully executed: " . substr($query, 0, 50) . "...<br>";
            }
        } catch (Exception $e) {
            // Column might already exist, check error message
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "ℹ️ Column already exists: " . substr($query, 0, 50) . "...<br>";
            } else {
                echo "❌ Error executing: " . $e->getMessage() . "<br>";
            }
        }
    }

    // Set default ratings for existing resolved reports (5 stars as requested)
    $updateExistingQuery = "UPDATE illegalreportstbl 
                           SET rating = 5, rated_at = NOW() 
                           WHERE action_type = 'Resolved' AND rating IS NULL";
    
    if ($connection->query($updateExistingQuery) === TRUE) {
        $affectedRows = $connection->affected_rows;
        echo "✅ Set default 5-star rating for $affectedRows existing resolved reports<br>";
    } else {
        echo "❌ Error setting default ratings: " . $connection->error . "<br>";
    }

    echo "<br>🎉 Table modification completed successfully!<br>";
    echo "<br>📋 Summary of changes:<br>";
    echo "- Added 'rating' column (1-5 stars)<br>";
    echo "- Added 'rated_by' column (admin who rated)<br>";
    echo "- Added 'rated_at' timestamp<br>";
    echo "- Added 'points_awarded' tracking<br>";
    echo "- Added 'badge_awarded' tracking<br>";
    echo "- Set existing resolved reports to 5 stars<br>";

} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "<br>";
}

$connection->close();
?>
