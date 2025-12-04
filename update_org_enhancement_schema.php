<?php
require_once 'database.php';

echo "<h1>Organization Enhancement Schema Update</h1>";

try {
    // Add privacy column to organizations table
    $addPrivacyColumn = "ALTER TABLE organizations ADD COLUMN IF NOT EXISTS privacy_setting ENUM('public', 'private') DEFAULT 'public'";
    if ($connection->query($addPrivacyColumn)) {
        echo "✅ Added privacy_setting column to organizations table<br>";
    } else {
        echo "❌ Failed to add privacy_setting column: " . $connection->error . "<br>";
    }

    // Add join_date to organization_members for cooldown tracking
    $addJoinDate = "ALTER TABLE organization_members ADD COLUMN IF NOT EXISTS joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    if ($connection->query($addJoinDate)) {
        echo "✅ Added joined_at column to organization_members table<br>";
    } else {
        echo "❌ Failed to add joined_at column: " . $connection->error . "<br>";
    }

    // Create organization invites table
    $createInvitesTable = "CREATE TABLE IF NOT EXISTS organization_invites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        org_id INT NOT NULL,
        invited_by INT NOT NULL,
        invited_user INT NOT NULL,
        invite_code VARCHAR(32) UNIQUE NOT NULL,
        status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 7 DAY),
        FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE,
        FOREIGN KEY (invited_by) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
        FOREIGN KEY (invited_user) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
        UNIQUE KEY unique_pending_invite (org_id, invited_user, status)
    )";
    if ($connection->query($createInvitesTable)) {
        echo "✅ Created organization_invites table<br>";
    } else {
        echo "❌ Failed to create organization_invites table: " . $connection->error . "<br>";
    }

    // Update existing organization_members to have joined_at timestamp
    $updateExistingMembers = "UPDATE organization_members SET joined_at = CURRENT_TIMESTAMP WHERE joined_at IS NULL";
    if ($connection->query($updateExistingMembers)) {
        echo "✅ Updated existing members with joined_at timestamp<br>";
    } else {
        echo "❌ Failed to update existing members: " . $connection->error . "<br>";
    }

    echo "<br><h2>Schema update completed successfully!</h2>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>