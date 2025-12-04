<?php
/**
 * Organization Weekly Bonus Cron Job
 * This script should be run weekly (e.g., every Monday) to award organization ranking bonuses
 * 
 * Set up a cron job to run this script:
 * 0 0 * * 1 /usr/bin/php /path/to/your/project/organization_weekly_bonuses.php
 */

// Include necessary files
require_once 'database.php';
require_once 'eco_points_integration.php';

// Initialize eco points system
initializeEcoPointsSystem();

echo "Starting organization weekly bonus calculation...\n";

try {
    // Award organization ranking bonuses
    awardOrganizationRankingBonuses();
    
    echo "Organization weekly bonuses awarded successfully!\n";
    
    // Log the execution
    $logFile = 'logs/organization_bonuses.log';
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - Organization weekly bonuses processed successfully\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    echo "Error processing organization bonuses: " . $e->getMessage() . "\n";
    
    // Log the error
    $logFile = 'logs/organization_bonuses_error.log';
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Close database connection
if (isset($connection)) {
    $connection->close();
}

echo "Organization bonus processing completed.\n";
?>