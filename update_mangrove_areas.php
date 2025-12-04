<?php
header('Content-Type: application/json');
require_once 'database.php';

$geojsonFile = 'extendedmangroveareas.json';

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed');
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate GeoJSON structure
    if (!isset($input['type']) || $input['type'] !== 'FeatureCollection' || !isset($input['features'])) {
        throw new Exception('Invalid GeoJSON format');
    }

    // Ensure all features have required properties
    foreach ($input['features'] as &$feature) {
        if (!isset($feature['properties']['area_no'])) {
            $feature['properties']['area_no'] = "UNASSIGNED";
        }
        if (!isset($feature['properties']['city_municipality'])) {
            $feature['properties']['city_municipality'] = "UNASSIGNED";
        }
        if (!isset($feature['properties']['date_created'])) {
            $feature['properties']['date_created'] = date('Y-m-d\TH:i:s\Z');
        }
        if (!isset($feature['properties']['date_updated'])) {
            $feature['properties']['date_updated'] = date('Y-m-d\TH:i:s\Z');
        }
        
        // Calculate area if not set
        if (!isset($feature['properties']['area_m2'])) {
            require_once 'vendor/autoload.php'; // Include Turf PHP if needed
            $polygon = \GeoJson\Geometry\Polygon::jsonUnserialize($feature['geometry']);
            $area = \Location\Polygon::getArea($polygon); // Example, use actual Turf PHP method
            $feature['properties']['area_m2'] = round($area);
            $feature['properties']['area_ha'] = round($area / 10000, 2);
        }
    }

    // Save to file
    if (!file_put_contents($geojsonFile, json_encode($input, JSON_PRETTY_PRINT))) {
        throw new Exception('Failed to save GeoJSON file');
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Mangrove areas updated successfully',
        'updatedData' => $input
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}