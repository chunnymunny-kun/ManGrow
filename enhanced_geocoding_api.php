<?php
/**
 * Enhanced Geocoding API
 * Provides accurate reverse geocoding with database matching
 * Uses Nominatim (free) with fallback strategies
 */

header('Content-Type: application/json');
require_once 'database.php';
require_once 'geocoding_helper.php';

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

try {
    // Get request parameters
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'reverse_geocode':
            $lat = floatval($_GET['lat'] ?? 0);
            $lng = floatval($_GET['lng'] ?? 0);
            
            if (!$lat || !$lng) {
                throw new Exception('Invalid coordinates');
            }
            
            $geocoder = new GeocodingHelper($connection, false, '');
            $result = $geocoder->reverseGeocode($lat, $lng);
            
            echo json_encode($result);
            break;
            
        case 'search_address':
            $query = $_GET['q'] ?? '';
            
            if (empty($query)) {
                throw new Exception('Search query is required');
            }
            
            $results = searchAddress($query);
            echo json_encode($results);
            break;
            
        case 'get_barangays':
            $city = $_GET['city'] ?? '';
            
            if (empty($city)) {
                throw new Exception('City parameter is required');
            }
            
            $barangays = getBarangaysForCity($city, $connection);
            echo json_encode(['success' => true, 'barangays' => $barangays]);
            break;
            
        case 'validate_location':
            $barangay = $_GET['barangay'] ?? '';
            $city = $_GET['city'] ?? '';
            
            if (empty($barangay) || empty($city)) {
                throw new Exception('Barangay and city are required');
            }
            
            $isValid = validateLocationInDatabase($barangay, $city, $connection);
            echo json_encode(['success' => true, 'valid' => $isValid]);
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
 * Search for addresses using Nominatim
 */
function searchAddress($query) {
    // Add Bataan bias to search
    $searchQuery = urlencode($query . ', Bataan, Philippines');
    $url = "https://nominatim.openstreetmap.org/search?q={$searchQuery}&format=json&addressdetails=1&limit=5&countrycodes=ph";
    
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
        
        $results = [];
        foreach ($data as $item) {
            $results[] = [
                'display_name' => $item['display_name'],
                'lat' => floatval($item['lat']),
                'lng' => floatval($item['lon']),
                'type' => $item['type'] ?? 'location',
                'importance' => floatval($item['importance'] ?? 0)
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
 * Get all barangays for a specific city
 */
function getBarangaysForCity($city, $connection) {
    $city = mysqli_real_escape_string($connection, $city);
    $query = "SELECT barangay FROM barangaytbl WHERE city_municipality = '$city' ORDER BY barangay";
    $result = mysqli_query($connection, $query);
    
    $barangays = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $barangays[] = $row['barangay'];
    }
    
    return $barangays;
}

/**
 * Validate if a barangay-city combination exists in database
 */
function validateLocationInDatabase($barangay, $city, $connection) {
    $barangay = mysqli_real_escape_string($connection, $barangay);
    $city = mysqli_real_escape_string($connection, $city);
    
    $query = "SELECT COUNT(*) as count FROM barangaytbl 
              WHERE barangay = '$barangay' AND city_municipality = '$city'";
    $result = mysqli_query($connection, $query);
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] > 0;
}
