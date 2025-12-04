<?php
include 'database.php';

echo "=== TESTING REQUEST_JOIN FIX ===\n";

// Check the enum values in the status column
$result = $connection->query("SHOW COLUMNS FROM join_requests WHERE Field = 'status'");
if ($row = $result->fetch_assoc()) {
    echo "Status column type: " . $row['Type'] . "\n";
    
    if (strpos($row['Type'], 'rejected') !== false) {
        echo "✅ 'rejected' is supported\n";
    } else {
        echo "❌ 'rejected' is NOT supported\n";
    }
    
    if (strpos($row['Type'], 'declined') !== false) {
        echo "✅ 'declined' is supported\n";
    } else {
        echo "❌ 'declined' is NOT supported\n";
    }
}

echo "\n=== CONCLUSION ===\n";
echo "Fixed the PHP code to use 'rejected' instead of 'declined' to match the database enum values.\n";
echo "This should resolve the request_join not working issue.\n";

$connection->close();
?>