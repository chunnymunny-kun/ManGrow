<?php
/**
 * Get Forest Metrics for a Barangay
 * 
 * This script calculates weighted average forest metrics
 * based on plot-based sampling from mangrove reports.
 * 
 * Formula: Weighted Average = Σ(metric × area) / Σ(area)
 */

header('Content-Type: application/json');
require_once 'database.php';

// Get parameters
$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$city = isset($_GET['city']) ? trim($_GET['city']) : '';

// Validate inputs
if (empty($barangay) || empty($city)) {
    echo json_encode([
        'success' => false,
        'error' => 'Missing barangay or city parameter'
    ]);
    exit;
}

try {
    // Query to get forest metrics using weighted averages
    // Only include reports that have forest metrics and are not rejected
    $query = "SELECT 
        COUNT(*) as total_reports,
        SUM(area_m2) as total_monitored_area,
        SUM(forest_cover_percent * area_m2) / NULLIF(SUM(area_m2), 0) as weighted_forest_cover,
        SUM(canopy_density_percent * area_m2) / NULLIF(SUM(area_m2), 0) as weighted_canopy_density,
        AVG(calculated_density) as avg_tree_density,
        SUM(tree_count) as total_trees_counted,
        COUNT(CASE WHEN tree_count IS NOT NULL THEN 1 END) as reports_with_tree_count
    FROM mangrovereporttbl 
    WHERE (barangays = ? OR barangays LIKE CONCAT(?, ', %') OR barangays LIKE CONCAT('%, ', ?, ', %') OR barangays LIKE CONCAT('%, ', ?))
        AND city_municipality = ?
        AND action_type != 'Rejected'
        AND forest_cover_percent IS NOT NULL";
    
    $stmt = $connection->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $connection->error);
    }
    
    $stmt->bind_param("sssss", $barangay, $barangay, $barangay, $barangay, $city);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $response = [
            'success' => true,
            'total_reports' => (int)$row['total_reports'],
            'total_monitored_area' => (float)($row['total_monitored_area'] ?? 0),
            'weighted_forest_cover' => $row['weighted_forest_cover'] !== null ? (float)$row['weighted_forest_cover'] : null,
            'weighted_canopy_density' => $row['weighted_canopy_density'] !== null ? (float)$row['weighted_canopy_density'] : null,
            'avg_tree_density' => $row['avg_tree_density'] !== null ? (float)$row['avg_tree_density'] : null,
            'total_trees_counted' => (int)($row['total_trees_counted'] ?? 0),
            'reports_with_tree_count' => (int)$row['reports_with_tree_count']
        ];
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No data found'
        ]);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$connection->close();
?>
