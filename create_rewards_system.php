<?php
// create_rewards_system.php - Run this ONCE to create reward cycle tables
require_once 'database.php';

echo "<h2>Creating Leaderboard Rewards System Tables...</h2>";

// Table 1: Reward Cycles
$createCyclesTable = "CREATE TABLE IF NOT EXISTS reward_cycles (
    cycle_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(100) NOT NULL COMMENT 'e.g., October 2025, Q4 2025',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('upcoming', 'active', 'ended', 'finalized') DEFAULT 'active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    finalized_at TIMESTAMP NULL COMMENT 'When rewards were calculated',
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks monthly/quarterly reward cycles'";

// Table 2: User Rewards (Claimable rewards)
$createRewardsTable = "CREATE TABLE IF NOT EXISTS user_rewards (
    reward_id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    user_id INT NOT NULL,
    category ENUM('individual', 'barangay', 'municipality', 'organization') NOT NULL,
    rank_achieved INT NOT NULL,
    entity_name VARCHAR(255) NOT NULL COMMENT 'User/Barangay/Municipality/Organization name',
    points_awarded INT NOT NULL DEFAULT 0,
    status ENUM('pending', 'claimed') DEFAULT 'pending',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP NULL,
    FOREIGN KEY (cycle_id) REFERENCES reward_cycles(cycle_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES accountstbl(account_id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_cycle_status (cycle_id, status),
    UNIQUE KEY unique_user_cycle_category (cycle_id, user_id, category, entity_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='User claimable rewards per cycle'";

try {
    // Create tables
    if ($connection->query($createCyclesTable)) {
        echo "✅ <strong>reward_cycles</strong> table created successfully!<br>";
    } else {
        echo "❌ Error creating reward_cycles table: " . $connection->error . "<br>";
    }

    if ($connection->query($createRewardsTable)) {
        echo "✅ <strong>user_rewards</strong> table created successfully!<br>";
    } else {
        echo "❌ Error creating user_rewards table: " . $connection->error . "<br>";
    }

    // Insert initial active cycle for October 2025
    $insertInitialCycle = "INSERT INTO reward_cycles (cycle_name, start_date, end_date, status) 
                          VALUES ('October 2025', '2025-10-01', '2025-10-31', 'active')
                          ON DUPLICATE KEY UPDATE cycle_id=cycle_id";
    
    if ($connection->query($insertInitialCycle)) {
        echo "✅ Initial cycle <strong>'October 2025'</strong> created successfully!<br>";
    } else {
        echo "❌ Error creating initial cycle: " . $connection->error . "<br>";
    }

    echo "<br><h3>✅ Setup Complete!</h3>";
    echo "<p>Tables created successfully. You can now:</p>";
    echo "<ul>";
    echo "<li>Go to <a href='admin_rewards_manager.php'>Admin Rewards Manager</a> to manage cycles</li>";
    echo "<li>View <a href='leaderboards.php'>Leaderboards</a> with the new claim system</li>";
    echo "</ul>";
    echo "<p><strong>Note:</strong> You can delete this file after running it once.</p>";

} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}

$connection->close();
?>
