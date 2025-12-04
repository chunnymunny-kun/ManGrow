<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Only POST requests allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$city = $input['city'] ?? '';
$barangay = $input['barangay'] ?? '';

if (empty($city) || empty($barangay)) {
    echo json_encode(['error' => 'City and barangay are required']);
    exit;
}

try {
    // Load the mangrove areas JSON
    $jsonFile = 'mangroveareas.json';
    if (!file_exists($jsonFile)) {
        throw new Exception('Mangrove areas JSON file not found');
    }
    
    $jsonData = file_get_contents($jsonFile);
    $data = json_decode($jsonData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format');
    }
    
    $totalRawArea = 0;
    $totalDistributedArea = 0;
    $areasFound = [];
    
    // Calculate both raw total and distributed total for the specified barangay and city
    foreach ($data['features'] as $feature) {
        $featureCity = $feature['properties']['city_municipality'] ?? '';
        $featureBarangays = $feature['properties']['barangays'] ?? [];
        
        // Check if this area belongs to the selected city and includes the selected barangay
        if ($featureCity === $city && is_array($featureBarangays) && in_array($barangay, $featureBarangays)) {
            $areaHa = floatval($feature['properties']['area_ha'] ?? 0);
            $numBarangays = count($featureBarangays);
            
            // Raw total (sum of all areas where this barangay is included)
            $totalRawArea += $areaHa;
            
            // Distributed total (area divided by number of barangays it's shared with)
            $distributedArea = $areaHa / $numBarangays;
            $totalDistributedArea += $distributedArea;
            
            $areasFound[] = [
                'area_no' => $feature['properties']['area_no'] ?? 'Unknown',
                'area_ha' => $areaHa,
                'distributed_area_ha' => round($distributedArea, 2),
                'barangays' => $featureBarangays,
                'shared_with_count' => $numBarangays
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'city' => $city,
        'barangay' => $barangay,
        'total_area_hectares' => round($totalRawArea, 2), // Keep backward compatibility
        'raw_total_hectares' => round($totalRawArea, 2),
        'distributed_total_hectares' => round($totalDistributedArea, 2),
        'areas_found' => count($areasFound),
        'areas' => $areasFound
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
