<?php
require 'database.php';

// Disable any HTML output
header('Content-Type: text/plain');

// 1. Get the JSON data
$json = file_get_contents('extendedmangroveareas.json');
if ($json === false) {
    die("ERROR: Could not read JSON file");
}

// 2. Parse the JSON
$data = json_decode($json, true);
if ($data === null) {
    die("ERROR: Invalid JSON format");
}

// 3. Process each feature
$imported = 0;
foreach ($data['features'] as $feature) {
    // Skip if missing required data
    if (!isset($feature['geometry']) || !isset($feature['properties']['ClassID'])) {
        continue;
    }
    
    // Convert geometry to JSON string
    $geometry = json_encode($feature['geometry']);
    $class_id = $feature['properties']['ClassID'];
    
    // 4. Insert into database
    $sql = "INSERT INTO mangroveareatbl (geometry, class_id) 
            VALUES (ST_GeomFromGeoJSON('$geometry'), $class_id)";
    
    if (!$connection->query($sql)) {
        echo "WARNING: Failed to insert record: " . $connection->error . "\n";
        continue;
    }
    
    $imported++;
}

// 5. Show simple result
echo "SUCCESS: Imported $imported mangrove areas\n";

// Close connection
$connection->close();
?>