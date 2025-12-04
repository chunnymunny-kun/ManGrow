<?php
/**
 * Geocoding Helper - Location Services with Database Matching
 * Supports both OpenStreetMap (free) and Google Maps API
 * Includes fuzzy matching for Bataan cities and barangays
 */

require_once 'database.php';

class GeocodingHelper {
    private $connection;
    private $useGoogleMaps;
    private $googleMapsApiKey;
    
    // Cache for database values
    private $citiesCache = null;
    private $barangaysCache = null;
    
    public function __construct($dbConnection, $useGoogleMaps = false, $apiKey = '') {
        $this->connection = $dbConnection;
        $this->useGoogleMaps = $useGoogleMaps;
        $this->googleMapsApiKey = $apiKey;
        
        // Load database caches
        $this->loadCaches();
    }
    
    /**
     * Load cities and barangays from database into memory
     */
    private function loadCaches() {
        // Load cities
        $cityQuery = "SELECT DISTINCT city FROM citymunicipalitytbl ORDER BY city";
        $cityResult = mysqli_query($this->connection, $cityQuery);
        $this->citiesCache = [];
        while ($row = mysqli_fetch_assoc($cityResult)) {
            $this->citiesCache[] = $row['city'];
        }
        
        // Load all barangays with their cities
        $barangayQuery = "SELECT barangay, city_municipality FROM barangaytbl ORDER BY barangay";
        $barangayResult = mysqli_query($this->connection, $barangayQuery);
        $this->barangaysCache = [];
        while ($row = mysqli_fetch_assoc($barangayResult)) {
            if (!isset($this->barangaysCache[$row['city_municipality']])) {
                $this->barangaysCache[$row['city_municipality']] = [];
            }
            $this->barangaysCache[$row['city_municipality']][] = $row['barangay'];
        }
    }
    
    /**
     * Reverse geocode coordinates to address
     */
    public function reverseGeocode($lat, $lng) {
        if ($this->useGoogleMaps && !empty($this->googleMapsApiKey)) {
            return $this->reverseGeocodeGoogle($lat, $lng);
        } else {
            return $this->reverseGeocodeOSM($lat, $lng);
        }
    }
    
    /**
     * Reverse geocode using OpenStreetMap Nominatim (Free)
     */
    private function reverseGeocodeOSM($lat, $lng) {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
        
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
                return $this->errorResponse('Failed to connect to geocoding service');
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['error'])) {
                return $this->errorResponse($data['error']);
            }
            
            return $this->parseOSMAddress($data, $lat, $lng);
            
        } catch (Exception $e) {
            return $this->errorResponse('Geocoding error: ' . $e->getMessage());
        }
    }
    
    /**
     * Reverse geocode using Google Maps API
     */
    private function reverseGeocodeGoogle($lat, $lng) {
        $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$this->googleMapsApiKey}&language=en";
        
        try {
            $response = @file_get_contents($url);
            if ($response === false) {
                // Fallback to OSM if Google fails
                return $this->reverseGeocodeOSM($lat, $lng);
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return $this->reverseGeocodeOSM($lat, $lng);
            }
            
            return $this->parseGoogleAddress($data['results'][0], $lat, $lng);
            
        } catch (Exception $e) {
            return $this->reverseGeocodeOSM($lat, $lng);
        }
    }
    
    /**
     * Parse OpenStreetMap address response
     */
    private function parseOSMAddress($data, $lat, $lng) {
        $address = $data['address'] ?? [];
        $displayName = $data['display_name'] ?? "Location at {$lat}, {$lng}";
        
        // Extract components
        $barangayRaw = $address['suburb'] ?? $address['village'] ?? $address['neighbourhood'] ?? null;
        $cityRaw = $address['city'] ?? $address['municipality'] ?? $address['town'] ?? null;
        $province = $address['state'] ?? null;
        
        // Match with database
        $matchedCity = $this->matchCity($cityRaw);
        $matchedBarangay = $this->matchBarangay($barangayRaw, $matchedCity['matched']);
        
        // Check if in Bataan
        $isBataan = $this->checkIfBataan($province, $cityRaw);
        
        return [
            'success' => true,
            'formatted_address' => $displayName,
            'lat' => $lat,
            'lng' => $lng,
            'barangay_raw' => $barangayRaw,
            'city_raw' => $cityRaw,
            'province' => $province,
            'barangay' => $matchedBarangay['matched'],
            'city_municipality' => $matchedCity['matched'],
            'barangay_confidence' => $matchedBarangay['confidence'],
            'city_confidence' => $matchedCity['confidence'],
            'is_bataan' => $isBataan,
            'source' => 'OpenStreetMap'
        ];
    }
    
    /**
     * Parse Google Maps address response
     */
    private function parseGoogleAddress($result, $lat, $lng) {
        $components = $result['address_components'] ?? [];
        $formattedAddress = $result['formatted_address'] ?? "Location at {$lat}, {$lng}";
        
        $barangayRaw = null;
        $cityRaw = null;
        $province = null;
        
        // Extract components
        foreach ($components as $component) {
            $types = $component['types'];
            
            // Barangay
            if (in_array('sublocality_level_1', $types) || in_array('neighborhood', $types)) {
                $barangayRaw = $component['long_name'];
            }
            
            // City/Municipality
            if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                $cityRaw = $component['long_name'];
            }
            
            // Province
            if (in_array('administrative_area_level_1', $types)) {
                $province = $component['long_name'];
            }
        }
        
        // Match with database
        $matchedCity = $this->matchCity($cityRaw);
        $matchedBarangay = $this->matchBarangay($barangayRaw, $matchedCity['matched']);
        
        // Check if in Bataan
        $isBataan = $this->checkIfBataan($province, $cityRaw);
        
        return [
            'success' => true,
            'formatted_address' => $formattedAddress,
            'lat' => $lat,
            'lng' => $lng,
            'barangay_raw' => $barangayRaw,
            'city_raw' => $cityRaw,
            'province' => $province,
            'barangay' => $matchedBarangay['matched'],
            'city_municipality' => $matchedCity['matched'],
            'barangay_confidence' => $matchedBarangay['confidence'],
            'city_confidence' => $matchedCity['confidence'],
            'is_bataan' => $isBataan,
            'source' => 'Google Maps'
        ];
    }
    
    /**
     * Match city name with database using fuzzy matching
     */
    private function matchCity($searchCity) {
        if (empty($searchCity)) {
            return ['matched' => null, 'confidence' => 0];
        }
        
        // Special hardcoded cases for common variations
        $hardcodedMatches = [
            'balanga city' => 'Balanga',
            'balanga' => 'Balanga',
            'mariveles' => 'Mariveles',
            'abucay' => 'Abucay',
            'bagac' => 'Bagac',
            'dinalupihan' => 'Dinalupihan',
            'hermosa' => 'Hermosa',
            'limay' => 'Limay',
            'morong' => 'Morong',
            'orani' => 'Orani',
            'orion' => 'Orion',
            'pilar' => 'Pilar',
            'samal' => 'Samal'
        ];
        
        $searchLower = strtolower(trim($searchCity));
        
        // Check hardcoded matches first
        if (isset($hardcodedMatches[$searchLower])) {
            return ['matched' => $hardcodedMatches[$searchLower], 'confidence' => 1.0];
        }
        
        // Fuzzy match against database
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($this->citiesCache as $dbCity) {
            $score = $this->calculateSimilarity($searchCity, $dbCity);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $dbCity;
            }
        }
        
        // Only accept if confidence is reasonable
        if ($bestScore >= 0.6) {
            return ['matched' => $bestMatch, 'confidence' => $bestScore];
        }
        
        return ['matched' => $searchCity, 'confidence' => 0];
    }
    
    /**
     * Match barangay name with database using fuzzy matching
     */
    private function matchBarangay($searchBarangay, $matchedCity) {
        if (empty($searchBarangay)) {
            return ['matched' => null, 'confidence' => 0];
        }
        
        if (empty($matchedCity)) {
            return ['matched' => $searchBarangay, 'confidence' => 0];
        }
        
        // Get barangays for the matched city
        $cityBarangays = $this->barangaysCache[$matchedCity] ?? [];
        
        if (empty($cityBarangays)) {
            return ['matched' => $searchBarangay, 'confidence' => 0];
        }
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($cityBarangays as $dbBarangay) {
            $score = $this->calculateSimilarity($searchBarangay, $dbBarangay);
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $dbBarangay;
            }
        }
        
        // Only accept if confidence is reasonable
        if ($bestScore >= 0.6) {
            return ['matched' => $bestMatch, 'confidence' => $bestScore];
        }
        
        return ['matched' => $searchBarangay, 'confidence' => 0];
    }
    
    /**
     * Calculate similarity between two strings using multiple methods
     */
    private function calculateSimilarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        // Exact match
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Remove common words
        $commonWords = ['barangay', 'city', 'municipality', 'poblacion'];
        foreach ($commonWords as $word) {
            $str1 = str_replace($word, '', $str1);
            $str2 = str_replace($word, '', $str2);
        }
        $str1 = trim($str1);
        $str2 = trim($str2);
        
        // Check if one contains the other
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return 0.9;
        }
        
        // Levenshtein distance
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen == 0) {
            return 1.0;
        }
        
        $distance = levenshtein($str1, $str2);
        $levenshteinSimilarity = 1 - ($distance / $maxLen);
        
        // Similar text percentage
        similar_text($str1, $str2, $percentage);
        $similarTextScore = $percentage / 100;
        
        // Average of both methods
        return ($levenshteinSimilarity + $similarTextScore) / 2;
    }
    
    /**
     * Check if location is in Bataan province
     */
    private function checkIfBataan($province, $city) {
        if (!empty($province)) {
            return (stripos($province, 'bataan') !== false);
        }
        
        // Check if city is in our database (all are Bataan)
        if (!empty($city)) {
            foreach ($this->citiesCache as $dbCity) {
                if (strcasecmp($city, $dbCity) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Search for location by address (forward geocoding)
     */
    public function searchAddress($address) {
        // Add Bataan context to improve results
        $searchQuery = $address . ', Bataan, Philippines';
        
        if ($this->useGoogleMaps && !empty($this->googleMapsApiKey)) {
            return $this->searchAddressGoogle($searchQuery);
        } else {
            return $this->searchAddressOSM($searchQuery);
        }
    }
    
    /**
     * Forward geocode using OpenStreetMap
     */
    private function searchAddressOSM($address) {
        $encodedAddress = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&limit=1&addressdetails=1";
        
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
                return $this->errorResponse('Failed to connect to geocoding service');
            }
            
            $data = json_decode($response, true);
            
            if (empty($data)) {
                return $this->errorResponse('Location not found');
            }
            
            $result = $data[0];
            $lat = floatval($result['lat']);
            $lng = floatval($result['lon']);
            
            // Get full details via reverse geocode
            return $this->reverseGeocodeOSM($lat, $lng);
            
        } catch (Exception $e) {
            return $this->errorResponse('Search error: ' . $e->getMessage());
        }
    }
    
    /**
     * Forward geocode using Google Maps
     */
    private function searchAddressGoogle($address) {
        $encodedAddress = urlencode($address);
        $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encodedAddress}&key={$this->googleMapsApiKey}";
        
        try {
            $response = @file_get_contents($url);
            if ($response === false) {
                return $this->searchAddressOSM($address);
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] !== 'OK' || empty($data['results'])) {
                return $this->searchAddressOSM($address);
            }
            
            $result = $data['results'][0];
            $lat = $result['geometry']['location']['lat'];
            $lng = $result['geometry']['location']['lng'];
            
            return $this->parseGoogleAddress($result, $lat, $lng);
            
        } catch (Exception $e) {
            return $this->searchAddressOSM($address);
        }
    }
    
    /**
     * Error response helper
     */
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}

// ============================================
// AJAX API ENDPOINTS
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    require_once 'database.php';
    
    // Configuration: Set to true and add API key to use Google Maps
    $useGoogleMaps = false;
    $googleApiKey = ''; // Add your Google Maps API key here if using Google Maps
    
    $geocoder = new GeocodingHelper($connection, $useGoogleMaps, $googleApiKey);
    
    try {
        if ($_POST['action'] === 'reverse_geocode') {
            if (!isset($_POST['lat']) || !isset($_POST['lng'])) {
                echo json_encode(['success' => false, 'error' => 'Missing coordinates']);
                exit;
            }
            
            $lat = floatval($_POST['lat']);
            $lng = floatval($_POST['lng']);
            $result = $geocoder->reverseGeocode($lat, $lng);
            echo json_encode($result);
        }
        
        elseif ($_POST['action'] === 'search_address') {
            if (!isset($_POST['address']) || empty(trim($_POST['address']))) {
                echo json_encode(['success' => false, 'error' => 'Missing search query']);
                exit;
            }
            
            $address = trim($_POST['address']);
            $result = $geocoder->searchAddress($address);
            echo json_encode($result);
        }
        
        else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit;
}
?>
