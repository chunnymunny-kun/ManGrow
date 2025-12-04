<?php
include 'database.php';

echo "=== DEBUGGING JOIN REQUESTS FUNCTIONALITY ===\n\n";

// 1. Check if join_requests table exists
echo "1. Checking join_requests table...\n";
$result = $connection->query("SHOW TABLES LIKE 'join_requests'");
if ($result->num_rows == 0) {
    echo "❌ join_requests table MISSING! Creating it now...\n";
    
    $createSQL = "CREATE TABLE join_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        org_id INT NOT NULL,
        user_id INT NOT NULL,
        status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        responded_at TIMESTAMP NULL,
        responded_by INT NULL
    )";
    
    if ($connection->query($createSQL)) {
        echo "✅ join_requests table created!\n";
    } else {
        echo "❌ Failed: " . $connection->error . "\n";
    }
} else {
    echo "✅ join_requests table exists\n";
    
    // Show structure
    $result = $connection->query("DESCRIBE join_requests");
    echo "Table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\n2. Checking private organizations...\n";
$result = $connection->query("SELECT org_id, name, privacy_setting FROM organizations WHERE privacy_setting = 'private' LIMIT 3");
if ($result->num_rows > 0) {
    echo "✅ Private organizations found:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - ID: " . $row['org_id'] . ", Name: " . $row['name'] . "\n";
    }
} else {
    echo "❌ No private organizations found. Making first org private...\n";
    $connection->query("UPDATE organizations SET privacy_setting = 'private' WHERE org_id = 1");
    echo "✅ Set org ID 1 to private\n";
}

echo "\n3. Testing join request insert...\n";
try {
    $stmt = $connection->prepare("INSERT INTO join_requests (org_id, user_id, status, requested_at) VALUES (1, 1, 'pending', NOW())");
    if ($stmt->execute()) {
        $id = $connection->insert_id;
        echo "✅ Test insert successful, ID: $id\n";
        
        // Clean up
        $connection->query("DELETE FROM join_requests WHERE id = $id");
        echo "✅ Test data cleaned up\n";
    } else {
        echo "❌ Insert failed: " . $stmt->error . "\n";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== READY TO TEST ===\n";
$connection->close();
?>