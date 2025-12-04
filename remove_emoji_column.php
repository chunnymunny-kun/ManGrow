<?php
require_once 'database.php';

// Remove emoji column from ecoshop_itemstbl table
$alterTableSQL = "ALTER TABLE ecoshop_itemstbl DROP COLUMN item_emoji";

if ($connection->query($alterTableSQL) === TRUE) {
    echo "Emoji column removed successfully\n";
} else {
    echo "Error removing emoji column: " . $connection->error . "\n";
}

$connection->close();
echo "Database update completed successfully!";
?>
