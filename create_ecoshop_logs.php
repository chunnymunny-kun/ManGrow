<?php
// create_ecoshop_logs.php - Run this once to create the eco shop logging tables
include 'database.php';

// Create redeem transactions table
$createRedeemTable = "CREATE TABLE IF NOT EXISTS ecoshop_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    points_used INT NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approval_date TIMESTAMP NULL,
    notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id),
    FOREIGN KEY (item_id) REFERENCES ecoshop_itemstbl(item_id),
    FOREIGN KEY (approved_by) REFERENCES accountstbl(account_id)
)";

// Create eco shop activity logs table
$createLogsTable = "CREATE TABLE IF NOT EXISTS ecoshop_activity_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    activity_type ENUM('item_added', 'item_edited', 'item_deleted', 'stock_updated', 'transaction_approved', 'transaction_rejected') NOT NULL,
    item_id INT NULL,
    transaction_id INT NULL,
    details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES accountstbl(account_id),
    FOREIGN KEY (item_id) REFERENCES ecoshop_itemstbl(item_id),
    FOREIGN KEY (transaction_id) REFERENCES ecoshop_transactions(transaction_id)
)";

// Execute table creation
try {
    if ($connection->query($createRedeemTable)) {
        echo "✅ ecoshop_transactions table created successfully\n";
    } else {
        echo "❌ Error creating ecoshop_transactions table: " . $connection->error . "\n";
    }
    
    if ($connection->query($createLogsTable)) {
        echo "✅ ecoshop_activity_logs table created successfully\n";
    } else {
        echo "❌ Error creating ecoshop_activity_logs table: " . $connection->error . "\n";
    }
    
    echo "\n🎉 Eco shop logging system setup complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

$connection->close();
?>
