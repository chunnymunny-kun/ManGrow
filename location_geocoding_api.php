<?php
/**
 * Location Geocoding API for Event Creation/Editing
 * Uses Nominatim with Bataan database matching
 * Handles address normalization and cross-barangay detection
 */

header('Content-Type: application/json');
require_once 'database.php';

// Enable error logging but hide from output
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'reverse_geocode':
            // Get coordinates and reverse geocode to address
            $lat = floatval($_GET['lat'] ?? 0);
            $lng = floatval($_GET['lng'] ?? 0);
            
            if (!$lat || !$lng) {
                throw new Exception('Invalid coordinates');
            }
            
            $result = reverseGeocodeLocation($lat, $lng, $connection);
            echo json_encode($result);
            break;
            
        case 'search_venue':
            // Search for venues in Bataan
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            
            $results = searchVenue($query);
            echo json_encode($results);
            break;
            
        case 'validate_address':
            // Validate and normalize barangay/city from external source
            $barangay = $_GET['barangay'] ?? '';
            $city = $_GET['city'] ?? '';
            
            $result = validateAndNormalizeAddress($barangay, $city, $connection);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Reverse geocode coordinates to address with database matching
 */
function reverseGeocodeLocation($lat, $lng, $connection) {
    // Call Nominatim for reverse geocoding
    $url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lng}&format=json&addressdetails=1";
    
    $options = [
        'http' => [
            'header' => "User-Agent: ManGrow-BataanApp/1.0\r\n",
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($options);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to geocoding service'];
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['address'])) {
            return ['success' => false, 'error' => 'No address found for these coordinates'];
        }
        
        $address = $data['address'];
        
        // Extract location components with priority order
        // Priority: village > suburb > neighbourhood (village is more specific in Philippines)
        $rawBarangay = $address['village'] ?? $address['suburb'] ?? $address['neighbourhood'] ?? $address['hamlet'] ?? '';
        
        // For city, prioritize municipality over city (more accurate in Philippines)
        $rawCity = $address['municipality'] ?? $address['city'] ?? $address['town'] ?? $address['county'] ?? '';
        
        // Clean up display name - remove redundant barangay mentions
        $displayName = $data['display_name'] ?? '';
        
        // If we have multiple possible barangays in the address, try to find the best match
        $allPossibleBarangays = array_filter([
            $address['village'] ?? '',
            $address['suburb'] ?? '',
            $address['neighbourhood'] ?? '',
            $address['hamlet'] ?? ''
        ]);
        
        // Try each barangay candidate against database to find the best match
        $bestMatch = null;
        $bestConfidence = 0;
        
        foreach ($allPossibleBarangays as $candidateBarangay) {
            if (empty($candidateBarangay)) continue;
            
            $testNormalized = validateAndNormalizeAddress($candidateBarangay, $rawCity, $connection);
            if ($testNormalized['success'] && $testNormalized['confidence'] > $bestConfidence) {
                $bestMatch = $testNormalized;
                $bestConfidence = $testNormalized['confidence'];
            }
        }
        
        // Use best match if found, otherwise try the first candidate
        if ($bestMatch) {
            $normalized = $bestMatch;
            $rawBarangay = $normalized['barangay'];
        } else {
            $normalized = validateAndNormalizeAddress($rawBarangay, $rawCity, $connection);
        }
        
        if ($normalized['success']) {
            return [
                'success' => true,
                'venue' => $displayName,
                'barangay' => $normalized['barangay'],
                'city_municipality' => $normalized['city_municipality'],
                'latitude' => $lat,
                'longitude' => $lng,
                'raw_address' => $address,
                'matched' => $normalized['matched'],
                'confidence' => $normalized['confidence']
            ];
        } else {
            // Return raw data even if not matched
            return [
                'success' => true,
                'venue' => $displayName,
                'barangay' => $rawBarangay,
                'city_municipality' => $rawCity,
                'latitude' => $lat,
                'longitude' => $lng,
                'raw_address' => $address,
                'matched' => false,
                'confidence' => 0,
                'warning' => 'Location may be outside Bataan or not in database'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Geocoding error: ' . $e->getMessage()
        ];
    }
}

/**
 * Search for venues in Bataan area
 */
function searchVenue($query) {
    // Add Bataan context to search
    $searchQuery = urlencode($query . ', Bataan, Philippines');
    $url = "https://nominatim.openstreetmap.org/search?q={$searchQuery}&format=json&addressdetails=1&limit=10&countrycodes=ph";
    
    $options = [
        'http' => [
            'header' => "User-Agent: ManGrow-BataanApp/1.0\r\n",
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($options);
    
    try {
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['success' => false, 'error' => 'Failed to connect to search service'];
        }
        
        $data = json_decode($response, true);
        
        $results = [];
        foreach ($data as $item) {
            $results[] = [
                'display_name' => $item['display_name'],
                'lat' => floatval($item['lat']),
                'lng' => floatval($item['lon']),
                'type' => $item['type'] ?? 'location',
                'importance' => floatval($item['importance'] ?? 0),
                'address' => $item['address'] ?? []
            ];
        }
        
        return [
            'success' => true,
            'results' => $results
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Search error: ' . $e->getMessage()
        ];
    }
}

/**
 * Validate and normalize address against database
 */
function validateAndNormalizeAddress($rawBarangay, $rawCity, $connection) {
    // Clean inputs
    $rawBarangay = trim($rawBarangay);
    $rawCity = trim($rawCity);
    
    if (empty($rawBarangay) || empty($rawCity)) {
        return [
            'success' => false,
            'error' => 'Barangay and city are required'
        ];
    }
    
    // City normalization map (external name => database name)
    $cityNormalizationMap = [
        'Balanga City' => 'Balanga',
        'City of Balanga' => 'Balanga',
        'Abucay' => 'Abucay',
        'Bagac' => 'Bagac',
        'Dinalupihan' => 'Dinalupihan',
        'Hermosa' => 'Hermosa',
        'Limay' => 'Limay',
        'Mariveles' => 'Mariveles',
        'Morong' => 'Morong',
        'Orani' => 'Orani',
        'Orion' => 'Orion',
        'Pilar' => 'Pilar',
        'Samal' => 'Samal'
    ];
    
    // Normalize city name
    $normalizedCity = $rawCity;
    foreach ($cityNormalizationMap as $external => $internal) {
        if (stripos($rawCity, $external) !== false || 
            levenshtein(strtolower($rawCity), strtolower($external)) <= 2) {
            $normalizedCity = $internal;
            break;
        }
    }
    
    // Try exact match first
    $stmt = $connection->prepare(
        "SELECT barangay, city_municipality FROM barangaytbl 
         WHERE LOWER(barangay) = LOWER(?) AND LOWER(city_municipality) = LOWER(?)"
    );
    $stmt->bind_param("ss", $rawBarangay, $normalizedCity);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return [
            'success' => true,
            'barangay' => $row['barangay'],
            'city_municipality' => $row['city_municipality'],
            'matched' => true,
            'confidence' => 1.0
        ];
    }
    $stmt->close();
    
    // Try fuzzy match for barangay (within same city)
    $stmt = $connection->prepare(
        "SELECT barangay, city_municipality FROM barangaytbl 
         WHERE LOWER(city_municipality) = LOWER(?)"
    );
    $stmt->bind_param("s", $normalizedCity);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bestMatch = null;
    $bestScore = PHP_INT_MAX;
    
    while ($row = $result->fetch_assoc()) {
        $distance = levenshtein(
            strtolower($rawBarangay), 
            strtolower($row['barangay'])
        );
        
        if ($distance < $bestScore && $distance <= 3) {
            $bestScore = $distance;
            $bestMatch = $row;
        }
    }
    $stmt->close();
    
    if ($bestMatch) {
        return [
            'success' => true,
            'barangay' => $bestMatch['barangay'],
            'city_municipality' => $bestMatch['city_municipality'],
            'matched' => true,
            'confidence' => 1 - ($bestScore / 10), // Confidence based on edit distance
            'fuzzy' => true
        ];
    }
    
    // No match found - check if city exists at all
    $stmt = $connection->prepare(
        "SELECT city FROM citymunicipalitytbl WHERE LOWER(city) = LOWER(?)"
    );
    $stmt->bind_param("s", $normalizedCity);
    $stmt->execute();
    $cityExists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    
    if ($cityExists) {
        return [
            'success' => false,
            'error' => "Barangay '{$rawBarangay}' not found in {$normalizedCity}",
            'city_valid' => true,
            'suggestions' => getSuggestedBarangays($normalizedCity, $connection)
        ];
    }
    
    return [
        'success' => false,
        'error' => "Location not found in Bataan database",
        'city_valid' => false
    ];
}

/**
 * Get suggested barangays for a city
 */
function getSuggestedBarangays($city, $connection) {
    $stmt = $connection->prepare(
        "SELECT barangay FROM barangaytbl 
         WHERE LOWER(city_municipality) = LOWER(?) 
         ORDER BY barangay LIMIT 10"
    );
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['barangay'];
    }
    $stmt->close();
    
    return $suggestions;
}
