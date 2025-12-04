<?php
header('Content-Type: application/json');
require_once 'database.php';

$geojsonFile = 'mangrovetrees.json';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests are allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    if (empty($input['mangrove_id'])) {
        throw new Exception('Missing mangrove_id');
    }
    $mangrove_id = $input['mangrove_id'];

    // 1. Delete from database
    $stmt = $connection->prepare("DELETE FROM mangrovetbl WHERE mangrove_id = ?");
    $stmt->bind_param("s", $mangrove_id);
    if (!$stmt->execute()) {
        throw new Exception('Database delete failed');
    }
    if ($stmt->affected_rows === 0) {
        throw new Exception('No matching marker found in database');
    }

    // 2. Delete from GeoJSON
    if (!file_exists($geojsonFile)) {
        throw new Exception('Mangrove data file not found');
    }
    $geojson = json_decode(file_get_contents($geojsonFile), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to parse mangrove data');
    }

    $found = false;
    foreach ($geojson['features'] as $i => $feature) {
        if ($feature['properties']['mangrove_id'] == $mangrove_id) {
            array_splice($geojson['features'], $i, 1);
            $found = true;
            break;
        }
    }
    if (!$found) {
        throw new Exception('Marker not found in GeoJSON file');
    }

    if (!file_put_contents($geojsonFile, json_encode($geojson, JSON_PRETTY_PRINT))) {
        throw new Exception('Failed to write to GeoJSON file');
    }

    echo json_encode(['success' => true, 'message' => 'Marker deleted from database and GeoJSON']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>