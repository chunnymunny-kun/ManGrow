<?php
include 'database.php';

echo "Creating join_requests table...\n";

$sql = "CREATE TABLE IF NOT EXISTS join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    responded_by INT NULL,
    INDEX (org_id),
    INDEX (user_id),
    INDEX (status)
)";

if ($connection->query($sql)) {
    echo "✅ join_requests table created/verified successfully!\n";
    
    // Test insert
    echo "Testing insert...\n";
    $testSQL = "INSERT INTO join_requests (org_id, user_id, status) VALUES (1, 1, 'pending')";
    if ($connection->query($testSQL)) {
        $id = $connection->insert_id;
        echo "✅ Test insert successful with ID: $id\n";
        
        // Clean up
        $connection->query("DELETE FROM join_requests WHERE id = $id");
        echo "✅ Test data cleaned up\n";
    } else {
        echo "❌ Test insert failed: " . $connection->error . "\n";
    }
} else {
    echo "❌ Failed to create table: " . $connection->error . "\n";
}

$connection->close();
?>