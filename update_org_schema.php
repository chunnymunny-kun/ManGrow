<?php
/**
 * Database schema update for organizations
 */

require_once 'database.php';

echo "Updating database schema for organizations...\n\n";

try {
    // Create organizations table
    $orgTableSQL = "CREATE TABLE IF NOT EXISTS organizations (
        org_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        barangay VARCHAR(255) NOT NULL,
        city_municipality VARCHAR(255) NOT NULL,
        capacity_limit INT NOT NULL DEFAULT 25,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        is_active BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (created_by) REFERENCES accountstbl(account_id) ON DELETE CASCADE
    )";
    
    if ($connection->query($orgTableSQL)) {
        echo "✅ Organizations table created successfully\n";
    } else {
        echo "❌ Error creating organizations table: " . $connection->error . "\n";
    }
    
    // Create organization members table
    $membersTableSQL = "CREATE TABLE IF NOT EXISTS organization_members (
        id INT PRIMARY KEY AUTO_INCREMENT,
        org_id INT NOT NULL,
        account_id INT NOT NULL,
        role ENUM('creator', 'member') DEFAULT 'member',
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (org_id) REFERENCES organizations(org_id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
        UNIQUE KEY unique_membership (org_id, account_id)
    )";
    
    if ($connection->query($membersTableSQL)) {
        echo "✅ Organization members table created successfully\n";
    } else {
        echo "❌ Error creating organization members table: " . $connection->error . "\n";
    }
    
    // Check if we need to migrate data
    $checkDataSQL = "SELECT COUNT(*) as count FROM organizations";
    $result = $connection->query($checkDataSQL);
    $count = $result->fetch_assoc()['count'];
    
    if ($count == 0) {
        echo "\n📋 Migrating existing organization data...\n";
        
        // Migrate existing organization data
        $migrateOrgSQL = "INSERT INTO organizations (name, description, barangay, city_municipality, capacity_limit, created_by)
        SELECT DISTINCT 
            a.organization as name,
            CONCAT('Organization for ', a.organization) as description,
            COALESCE(a.barangay, 'Not Specified') as barangay,
            COALESCE(a.city_municipality, 'Not Specified') as city_municipality,
            25 as capacity_limit,
            MIN(a.account_id) as created_by
        FROM accountstbl a 
        WHERE a.organization IS NOT NULL 
            AND a.organization != '' 
            AND a.organization != 'N/A'
        GROUP BY a.organization";
        
        if ($connection->query($migrateOrgSQL)) {
            echo "✅ Organization data migrated successfully\n";
            
            // Migrate members
            $migrateMembersSQL = "INSERT INTO organization_members (org_id, account_id, role)
            SELECT 
                o.org_id,
                a.account_id,
                CASE 
                    WHEN a.account_id = o.created_by THEN 'creator'
                    ELSE 'member'
                END as role
            FROM accountstbl a
            JOIN organizations o ON a.organization = o.name
            WHERE a.organization IS NOT NULL 
                AND a.organization != '' 
                AND a.organization != 'N/A'";
                
            if ($connection->query($migrateMembersSQL)) {
                echo "✅ Organization members migrated successfully\n";
            } else {
                echo "❌ Error migrating members: " . $connection->error . "\n";
            }
        } else {
            echo "❌ Error migrating organizations: " . $connection->error . "\n";
        }
    } else {
        echo "📋 Organizations table already has data, skipping migration\n";
    }
    
    echo "\n🎉 Database schema update completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$connection->close();
?>