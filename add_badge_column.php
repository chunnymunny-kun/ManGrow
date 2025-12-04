<?php
// Add badge_awarded column to illegalreportstbl table
require_once 'database.php';

$query = "ALTER TABLE illegalreportstbl ADD COLUMN badge_awarded VARCHAR(255) DEFAULT NULL";
$result = $connection->query($query);

if ($result) {
    echo "Column badge_awarded added successfully to illegalreportstbl\n";
} else {
    echo "Error adding column: " . $connection->error . "\n";
}

$connection->close();
?>
