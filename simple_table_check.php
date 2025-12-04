<?php
include 'database.php';
echo "Checking join_requests table structure...\n";
$result = $connection->query("DESCRIBE join_requests");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ": " . $row['Type'] . "\n";
}
?>