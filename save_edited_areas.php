<?php
// save_edited_areas.php

header('Content-Type: application/json');

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit;
}

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate the data
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($data['feature']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Path to your mangroveareas.json file
$file_path = 'mangroveareas.json';

// Load existing areas
$areas = [];
if (file_exists($file_path)) {
    $areas = json_decode(file_get_contents($file_path), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $areas = ['type' => 'FeatureCollection', 'features' => []];
    }
} else {
    $areas = ['type' => 'FeatureCollection', 'features' => []];
}

// Handle different actions
switch ($data['action']) {
    case 'update':
        // Find and update the feature
        $updated = false;
        foreach ($areas['features'] as &$feature) {
            if ($feature['properties']['id'] === $data['feature']['properties']['id']) {
                $feature = $data['feature'];
                $updated = true;
                break;
            }
        }
        
        if (!$updated) {
            echo json_encode(['success' => false, 'message' => 'Area not found']);
            exit;
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

// Save the updated data
try {
    file_put_contents($file_path, json_encode($areas, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'message' => 'Area updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error saving: ' . $e->getMessage()]);
}
?>