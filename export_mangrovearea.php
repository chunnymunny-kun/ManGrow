<?php
error_reporting(E_ALL); 
ini_set('display_errors', 1); 

require 'database.php';

header('Content-Type: application/json');

// 1. Query the database for mangrove areas
$sql = "SELECT 
            area_id, 
            area_no, 
            ST_AsGeoJSON(geometry) AS geometry_json,
            city_municipality,
            area_m2,
            area_ha,
            date_created,
            date_updated
        FROM mangroveareatbl";

$result = $connection->query($sql);
if (!$result) {
    // If query fails, output JSON error and exit
    // error_log("SQL Query Failed: " . $connection->error); // Log the actual error
    die(json_encode(['error' => 'Database query failed: ' . $connection->error]));
}

// 2. Build GeoJSON structure
$geojson = [
    'type' => 'FeatureCollection',
    'features' => []
];

// 3. Process each database row
while ($row = $result->fetch_assoc()) {
    $feature = [
        'type' => 'Feature',
        'geometry' => json_decode($row['geometry_json']),
        'properties' => [
            'area_id' => $row['area_id'],
            'area_no' => $row['area_no'],
            'city_municipality' => $row['city_municipality'],
            'area_m2' => $row['area_m2'],
            'area_ha' => $row['area_ha'],
            'date_created' => $row['date_created'],
            'date_updated' => $row['date_updated']
        ]
    ];
    $geojson['features'][] = $feature;
}

// 4. Output the GeoJSON and ensure nothing else is outputted
echo json_encode($geojson, JSON_PRETTY_PRINT);

// Close connection
$connection->close();

exit(); // IMPORTANT: Ensure script stops execution after outputting JSON
?>