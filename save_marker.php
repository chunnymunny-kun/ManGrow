<?php
header('Content-Type: application/json');
require_once 'database.php';

$geojsonFile = 'mangrovetrees.json';

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

    // Validate required fields
    $required = ['latitude', 'longitude', 'area_no', 'mangrove_type', 'status'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Generate unique ID
    $mangroveId = $input['mangrove_id'] ?? time() . '-' . bin2hex(random_bytes(2));
    $dateAdded = date('Y-m-d H:i:s');

    // ===== DATABASE STORAGE =====
    $query = "INSERT INTO mangrovetbl 
              (mangrove_id, latitude, longitude, area_no, mangrove_type, status, date_added) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param(
        "sddssss", 
        $mangroveId,
        $input['latitude'],
        $input['longitude'],
        $input['area_no'],
        $input['mangrove_type'],
        $input['status'],
        $dateAdded
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Database save failed');
    }

    // ===== GEOJSON STORAGE =====
    $newFeature = [
        'type' => 'Feature',
        'geometry' => [
            'type' => 'Point',
            'coordinates' => [
                (float)$input['longitude'],
                (float)$input['latitude']
            ]
        ],
        'properties' => [
            'mangrove_id' => $mangroveId,
            'area_no' => $input['area_no'],
            'mangrove_type' => $input['mangrove_type'],
            'status' => $input['status'],
            'date_added' => $dateAdded
        ]
    ];
    // if geojson file doesn't exist, create it
    $geojson = file_exists($geojsonFile) 
        ? json_decode(file_get_contents($geojsonFile), true) 
        : ['type' => 'FeatureCollection', 'features' => []];

    $geojson['features'][] = $newFeature;

    if (!file_put_contents($geojsonFile, json_encode($geojson, JSON_PRETTY_PRINT))) {
        throw new Exception('GeoJSON file update failed');
    }

    // After successful marker save:
    if (isset($input['latitude']) && isset($input['longitude'])) {
        // Create tree area in GeoJSON
        $radius = sqrt(100) / 2; // ~5 meters (for 100 sq m area)
        $treeArea = [
            'type' => 'Feature',
            'properties' => [
                'area_no' => 'TREE-' . $mangroveId,
                'city_municipality' => '',
                'id' => 'treearea_' . $mangroveId,
                'area_m2' => 100,
                'area_ha' => "0.01",
                'date_created' => $dateAdded,
                'date_updated' => $dateAdded,
                'is_tree_area' => true,
                'tree_id' => $mangroveId
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [$input['longitude'] - $radius/111320, $input['latitude'] - $radius/110574],
                        [$input['longitude'] + $radius/111320, $input['latitude'] - $radius/110574],
                        [$input['longitude'] + $radius/111320, $input['latitude'] + $radius/110574],
                        [$input['longitude'] - $radius/111320, $input['latitude'] + $radius/110574],
                        [$input['longitude'] - $radius/111320, $input['latitude'] - $radius/110574]
                    ]
                ]
            ]
        ];

        // Add to mangrove areas GeoJSON
        $mangroveAreasFile = 'mangroveareas.json';
        $mangroveAreas = file_exists($mangroveAreasFile) 
            ? json_decode(file_get_contents($mangroveAreasFile), true)
            : ['type' => 'FeatureCollection', 'features' => []];

        // Find intersecting areas
        $intersectingAreas = [];
        foreach ($mangroveAreas['features'] as $area) {
            // Simple bounding box check first for performance
            $minLng = min(array_column($area['geometry']['coordinates'][0], 0));
            $maxLng = max(array_column($area['geometry']['coordinates'][0], 0));
            $minLat = min(array_column($area['geometry']['coordinates'][0], 1));
            $maxLat = max(array_column($area['geometry']['coordinates'][0], 1));

            if ($input['longitude'] >= $minLng && $input['longitude'] <= $maxLng &&
                $input['latitude'] >= $minLat && $input['latitude'] <= $maxLat) {
                $intersectingAreas[] = $area;
            }
        }

        if (!empty($intersectingAreas)) {
            // Merge with oldest area
            usort($intersectingAreas, function($a, $b) {
                return strtotime($a['properties']['date_created']) - strtotime($b['properties']['date_created']);
            });

            $oldestArea = &$intersectingAreas[0];
            $oldestArea['geometry']['coordinates'][0] = array_merge(
                $oldestArea['geometry']['coordinates'][0],
                $treeArea['geometry']['coordinates'][0]
            );

            // Update area size 
            $oldestArea['properties']['area_m2'] += 100;
            $oldestArea['properties']['area_ha'] = number_format($oldestArea['properties']['area_m2'] / 10000, 2);
            $oldestArea['properties']['date_updated'] = $dateAdded;

            // Remove other areas that were merged
            $otherIds = array_column(array_slice($intersectingAreas, 1), 'id');
            $mangroveAreas['features'] = array_filter($mangroveAreas['features'], function($area) use ($otherIds) {
                return !in_array($area['properties']['id'], $otherIds);
            });

            // Update the mangrove areas file
            file_put_contents($mangroveAreasFile, json_encode($mangroveAreas, JSON_PRETTY_PRINT));
        } else {
            // Add as new area
            $mangroveAreas['features'][] = $treeArea;
            file_put_contents($mangroveAreasFile, json_encode($mangroveAreas, JSON_PRETTY_PRINT));
        }
    }

    echo json_encode([
        'success' => true,
        'mangrove_id' => $mangroveId,
        'message' => 'Marker saved to both database and GeoJSON file'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>