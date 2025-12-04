<?php
header('Content-Type: application/json');
require_once 'database.php';

/**
 * Calculate and update barangay profile mangrove areas based on JSON data
 */

try {
    // Load the current mangrove areas JSON
    $jsonFile = 'mangroveareas.json';
    if (!file_exists($jsonFile)) {
        throw new Exception('Mangrove areas JSON file not found');
    }
    
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format');
    }
    
    // Array to store barangay totals: [barangay][city] = total_hectares
    $barangayTotals = [];
    
    // Calculate totals for each barangay based on mangrove areas
    foreach ($data['features'] as $feature) {
        $city = $feature['properties']['city_municipality'] ?? 'Unknown';
        $area_ha = floatval($feature['properties']['area_ha'] ?? 0);
        
        // Get barangays for this area (if available)
        $barangays = [];
        if (isset($feature['properties']['barangays'])) {
            if (is_array($feature['properties']['barangays'])) {
                $barangays = $feature['properties']['barangays'];
            } else {
                // Handle comma-separated string format
                $barangays = array_map('trim', explode(',', $feature['properties']['barangays']));
            }
        }
        
        // If no barangays specified, skip this area for now
        if (empty($barangays)) {
            continue;
        }
        
        // Distribute area among all barangays for this feature
        $areaPerBarangay = $area_ha / count($barangays);
        
        foreach ($barangays as $barangay) {
            if (empty($barangay)) continue;
            
            $key = $barangay . '|' . $city;
            if (!isset($barangayTotals[$key])) {
                $barangayTotals[$key] = 0;
            }
            $barangayTotals[$key] += $areaPerBarangay;
        }
    }
    
    // Update barangay profiles in database
    $updated = 0;
    $errors = [];
    
    foreach ($barangayTotals as $key => $totalHectares) {
        list($barangay, $city) = explode('|', $key, 2);
        
        // Check if profile exists
        $checkSql = "SELECT profile_id, mangrove_area FROM barangayprofiletbl 
                     WHERE barangay = ? AND city_municipality = ? AND status = 'published'";
        $checkStmt = $connection->prepare($checkSql);
        $checkStmt->bind_param("ss", $barangay, $city);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $profile = $result->fetch_assoc();
            $currentArea = floatval($profile['mangrove_area']);
            $newArea = round($totalHectares, 2);
            
            // Only update if there's a significant change (to avoid unnecessary updates)
            if (abs($currentArea - $newArea) > 0.01) {
                $updateSql = "UPDATE barangayprofiletbl 
                             SET mangrove_area = ?, date_edited = NOW() 
                             WHERE profile_id = ?";
                $updateStmt = $connection->prepare($updateSql);
                $updateStmt->bind_param("di", $newArea, $profile['profile_id']);
                
                if ($updateStmt->execute()) {
                    $updated++;
                } else {
                    $errors[] = "Failed to update {$barangay}, {$city}: " . $updateStmt->error;
                }
                $updateStmt->close();
            }
        }
        $checkStmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Updated {$updated} barangay profiles",
        'barangay_totals' => count($barangayTotals),
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$connection->close();
?>
