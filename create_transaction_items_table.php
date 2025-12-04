<?php
/**
 * Database Migration: Create ecoshop_transaction_items table
 * This creates a junction table to support multiple items per transaction
 * Run this file ONCE to set up the multi-item shopping cart system
 */

require_once 'database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Transaction Items</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #e8f5e9; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #ffebee; border-radius: 4px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #e3f2fd; border-radius: 4px; margin: 10px 0; }
        h1 { color: #2e7d32; }
        .step { margin: 15px 0; padding: 10px; background: #f5f5f5; border-left: 4px solid #4caf50; }
    </style>
</head>
<body>
    <h1>Multi-Item Shopping Cart - Database Migration</h1>
";

try {
    // Step 1: Check if table already exists
    echo "<div class='step'><strong>Step 1:</strong> Checking if table exists...</div>";
    $checkTable = "SHOW TABLES LIKE 'ecoshop_transaction_items'";
    $result = $connection->query($checkTable);
    
    if ($result->num_rows > 0) {
        echo "<div class='info'>⚠️ Table 'ecoshop_transaction_items' already exists. Skipping creation.</div>";
    } else {
        // Step 2: Create the junction table
        echo "<div class='step'><strong>Step 2:</strong> Creating ecoshop_transaction_items table...</div>";
        
        $createTableSQL = "
        CREATE TABLE ecoshop_transaction_items (
            transaction_item_id INT PRIMARY KEY AUTO_INCREMENT,
            transaction_id INT NOT NULL,
            item_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            points_used INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (transaction_id) REFERENCES ecoshop_transactions(transaction_id) ON DELETE CASCADE,
            FOREIGN KEY (item_id) REFERENCES ecoshop_itemstbl(item_id) ON DELETE RESTRICT,
            
            INDEX idx_transaction (transaction_id),
            INDEX idx_item (item_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        if ($connection->query($createTableSQL) === TRUE) {
            echo "<div class='success'>✅ Table 'ecoshop_transaction_items' created successfully!</div>";
        } else {
            throw new Exception("Error creating table: " . $connection->error);
        }
    }
    
    // Step 3: Migrate existing transaction data
    echo "<div class='step'><strong>Step 3:</strong> Migrating existing transaction data...</div>";
    
    // Check if migration has already been done
    $countCheck = "SELECT COUNT(*) as count FROM ecoshop_transaction_items";
    $countResult = $connection->query($countCheck);
    $countRow = $countResult->fetch_assoc();
    
    if ($countRow['count'] > 0) {
        echo "<div class='info'>⚠️ Transaction items table already contains {$countRow['count']} records. Skipping migration.</div>";
    } else {
        // Migrate existing transactions to the new table
        $migrateSQL = "
        INSERT INTO ecoshop_transaction_items (transaction_id, item_id, quantity, points_used, created_at)
        SELECT 
            transaction_id,
            item_id,
            quantity,
            points_used,
            transaction_date as created_at
        FROM ecoshop_transactions
        WHERE item_id IS NOT NULL
        ";
        
        if ($connection->query($migrateSQL) === TRUE) {
            $migratedCount = $connection->affected_rows;
            echo "<div class='success'>✅ Successfully migrated {$migratedCount} existing transactions to the new table!</div>";
        } else {
            echo "<div class='error'>⚠️ Migration warning: " . $connection->error . "</div>";
            echo "<div class='info'>This is OK if you have no existing transactions.</div>";
        }
    }
    
    // Step 4: Verify the setup
    echo "<div class='step'><strong>Step 4:</strong> Verifying setup...</div>";
    
    // Check table structure
    $describeSQL = "DESCRIBE ecoshop_transaction_items";
    $descResult = $connection->query($describeSQL);
    
    echo "<div class='success'>✅ Table structure verified:</div>";
    echo "<ul>";
    while ($row = $descResult->fetch_assoc()) {
        echo "<li><strong>{$row['Field']}</strong> - {$row['Type']}</li>";
    }
    echo "</ul>";
    
    // Count records
    $countSQL = "SELECT COUNT(*) as total FROM ecoshop_transaction_items";
    $countResult = $connection->query($countSQL);
    $countRow = $countResult->fetch_assoc();
    
    echo "<div class='success'>✅ Total records in transaction_items table: {$countRow['total']}</div>";
    
    // Final success message
    echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>
        <strong>🎉 Migration completed successfully!</strong><br><br>
        Your database is now ready for the multi-item shopping cart system.<br>
        You can now proceed to use the new features.
    </div>";
    
    echo "<div class='info' style='margin-top: 20px;'>
        <strong>Next Steps:</strong><br>
        1. Test the shopping cart on the leaderboards page<br>
        2. Add multiple items and checkout<br>
        3. Verify that receipts show all items<br>
        4. Check that admin approval handles multiple items
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Migration failed:</strong><br>" . $e->getMessage() . "</div>";
    echo "<div class='info'>Please check your database connection and try again.</div>";
}

$connection->close();

echo "
    <div style='margin-top: 30px; padding: 15px; background: #fff3e0; border-radius: 4px;'>
        <strong>⚠️ Important:</strong> This migration script should only be run ONCE. 
        If you need to run it again, make sure to backup your database first.
    </div>
</body>
</html>";
?>
