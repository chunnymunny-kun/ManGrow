<?php
header('Content-Type: application/json');
require_once 'database.php'; // Your existing database connection

$geojsonFile = 'mangrovetrees.json';

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Required fields
    $required = ['mangrove_id', 'latitude', 'longitude', 'area_no', 'mangrove_type', 'status'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // ===== 1. UPDATE DATABASE =====
    $query = "UPDATE mangrovetbl SET latitude = ?, longitude = ?, area_no = ?, mangrove_type = ?, status = ?, date_updated = NOW() WHERE mangrove_id = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param(
        "ddssss",
        $input['latitude'],
        $input['longitude'],
        $input['area_no'],
        $input['mangrove_type'],
        $input['status'],
        $input['mangrove_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Database update failed');
    }

    // Check if any rows were actually updated
    if ($stmt->affected_rows === 0) {
        throw new Exception('No matching marker found in database');
    }

    // ===== 2. UPDATE GEOJSON FILE =====
    if (!file_exists($geojsonFile)) {
        throw new Exception('Mangrove data file not found');
    }

    $geojson = json_decode(file_get_contents($geojsonFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse mangrove data');
    }

    // Find and update the feature
    $updated = false;
    foreach ($geojson['features'] as &$feature) {
        if ($feature['properties']['mangrove_id'] == $input['mangrove_id']) {
            // Update geometry (coordinates)
            $feature['geometry']['coordinates'] = [
                (float)$input['longitude'],
                (float)$input['latitude']
            ];

            // Update properties
            $feature['properties']['area_no'] = $input['area_no'];
            $feature['properties']['mangrove_type'] = $input['mangrove_type'];
            $feature['properties']['status'] = $input['status'];
            $feature['properties']['date_updated'] = date('Y-m-d H:i:s');

            $updated = true;
            break;
        }
    }

    if (!$updated) {
        throw new Exception('Marker not found in GeoJSON file');
    }

    // Save back to file
    if (!file_put_contents($geojsonFile, json_encode($geojson, JSON_PRETTY_PRINT))) {
        throw new Exception('Failed to write to GeoJSON file');
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Marker updated in both database and GeoJSON file',
        'updated_fields' => [
            'latitude' => $input['latitude'],
            'longitude' => $input['longitude'],
            'area_no' => $input['area_no'],
            'mangrove_type' => $input['mangrove_type'],
            'status' => $input['status']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>