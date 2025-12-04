<?php
/**
 * AJAX Endpoint for Real-Time Cycle Rankings
 * Returns current cycle rankings in JSON format
 * Works like "Earned this week" - calculates in real-time based on active cycle dates
 */

session_start();
require_once 'database.php';
require_once 'cycle_rankings.php';

header('Content-Type: application/json');

// Get current active cycle
$cycleQuery = "SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
$cycleResult = $connection->query($cycleQuery);

if(!$cycleResult || $cycleResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No active cycle',
        'hasActiveCycle' => false
    ]);
    exit;
}

$currentCycle = $cycleResult->fetch_assoc();

// Get cycle rankings in real-time (just like weekly points)
$cycleTopIndividuals = getCycleIndividualRankings($currentCycle['cycle_id'], 10);
$cycleTopBarangays = getCycleBarangayRankings($currentCycle['cycle_id'], 5);
$cycleTopMunicipalities = getCycleMunicipalityRankings($currentCycle['cycle_id'], 5);
$cycleTopOrganizations = getCycleOrganizationRankings($currentCycle['cycle_id'], 5);

// Get cycle statistics
$cycleStats = getCycleStatistics($currentCycle['cycle_id']);

// Return data in JSON format
echo json_encode([
    'success' => true,
    'hasActiveCycle' => true,
    'currentCycle' => $currentCycle,
    'cycleTopIndividuals' => $cycleTopIndividuals,
    'cycleTopBarangays' => $cycleTopBarangays,
    'cycleTopMunicipalities' => $cycleTopMunicipalities,
    'cycleTopOrganizations' => $cycleTopOrganizations,
    'cycleStats' => $cycleStats
]);

$connection->close();
?>
