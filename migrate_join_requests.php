<?php
require_once 'database.php';

echo "<h2>Creating join_requests table...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS join_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    org_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    responded_by INT NULL,
    INDEX (org_id),
    INDEX (user_id),
    INDEX (status),
    UNIQUE KEY unique_pending_request (org_id, user_id, status),
    FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    FOREIGN KEY (responded_by) REFERENCES accountstbl(account_id) ON DELETE SET NULL
)";

if ($connection->query($sql) === TRUE) {
    echo "✅ join_requests table created successfully!<br>";
} else {
    echo "❌ Error creating table: " . $connection->error . "<br>";
}

// Migrate existing join requests from organization_invites to join_requests
echo "<h3>Migrating existing join requests...</h3>";

$migrateSql = "INSERT INTO join_requests (org_id, user_id, status, requested_at, responded_at)
               SELECT org_id, invited_user_id, 
                      CASE 
                          WHEN status = 'accepted' THEN 'approved'
                          WHEN status = 'declined' THEN 'rejected'
                          ELSE 'pending'
                      END,
                      invited_at, responded_at
               FROM organization_invites 
               WHERE invited_by_user_id = invited_user_id
               ON DUPLICATE KEY UPDATE status = VALUES(status)";

if ($connection->query($migrateSql) === TRUE) {
    echo "✅ Existing join requests migrated successfully!<br>";
} else {
    echo "❌ Error migrating data: " . $connection->error . "<br>";
}

// Clean up join requests from organization_invites (keep only actual invitations)
echo "<h3>Cleaning up organization_invites...</h3>";

$cleanupSql = "DELETE FROM organization_invites WHERE invited_by_user_id = invited_user_id";

if ($connection->query($cleanupSql) === TRUE) {
    echo "✅ Cleaned up organization_invites table - removed join requests!<br>";
    echo "Now organization_invites only contains actual invitations from creators to users.<br>";
} else {
    echo "❌ Error cleaning up: " . $connection->error . "<br>";
}

echo "<h3>✅ Database migration completed!</h3>";
echo "<p>join_requests table now handles all user requests to join organizations.</p>";
echo "<p>organization_invites table now only handles invitations from creators to users.</p>";
?>