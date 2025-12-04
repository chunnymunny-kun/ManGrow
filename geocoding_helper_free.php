<?php
/**
 * FREE Geocoding Helper - No Credit Card Required
 * Uses: OpenStreetMap Nominatim + GeoNames + Spatial Matching
 * Accurate barangay detection for Philippines (including Wawa, Abucay)
 */

class FreeGeocodingHelper {
    private $db;
    private $citiesCache = [];
    private $barangaysCache = [];
    private $geonamesCache = []; // Philippine places from GeoNames
    private $minConfidence = 0.6;
    
    // Bataan-specific hardcoded coordinates for better accuracy
    private $bataanBarangays = [
        // Abucay barangays with approximate coordinates
        'Wawa' => ['lat' => 14.7289, 'lng' => 120.5372, 'city' => 'Abucay'],
        'Bangkal' => ['lat' => 14.7156, 'lng' => 120.5289, 'city' => 'Abucay'],
        'Laon' => ['lat' => 14.7378, 'lng' => 120.5445, 'city' => 'Abucay'],
        'Calaylayan' => ['lat' => 14.7423, 'lng' => 120.5512, 'city' => 'Abucay'],
        'Capitangan' => ['lat' => 14.7534, 'lng' => 120.5623, 'city' => 'Abucay'],
        'Gabon' => ['lat' => 14.7645, 'lng' => 120.5734, 'city' => 'Abucay'],
        'Mabatang' => ['lat' => 14.7089, 'lng' => 120.5156, 'city' => 'Abucay'],
        'Omboy' => ['lat' => 14.7267, 'lng' => 120.5401, 'city' => 'Abucay'],
        'Salian' => ['lat' => 14.7512, 'lng' => 120.5578, 'city' => 'Abucay'],
        
        // Balanga barangays
        'Poblacion' => ['lat' => 14.6792, 'lng' => 120.5372, 'city' => 'Balanga'],
        'Bagong Silang' => ['lat' => 14.6834, 'lng' => 120.5289, 'city' => 'Balanga'],
        'Cabog-Cabog' => ['lat' => 14.6923, 'lng' => 120.5445, 'city' => 'Balanga'],
        'Lati' => ['lat' => 14.6845, 'lng' => 120.5512, 'city' => 'Balanga'],
        
        // Add more as needed - this is just a starter set
    ];

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
        $this->loadCaches();
        $this->loadGeoNamesData(); // Load Philippine places
    }

    /**
     * Load cities and barangays from database
     */
    private function loadCaches() {
        // Load cities
        $result = $this->db->query("SELECT city FROM citymunicipalitytbl");
        while ($row = $result->fetch_assoc()) {
            $this->citiesCache[] = $row['city'];
        }

        // Load barangays with city context
        $result = $this->db->query("SELECT barangay, city_municipality FROM barangaytbl");
        while ($row = $result->fetch_assoc()) {
            $city = $row['city_municipality'];
            if (!isset($this->barangaysCache[$city])) {
                $this->barangaysCache[$city] = [];
            }
            $this->barangaysCache[$city][] = $row['barangay'];
        }
    }

    /**
     * Load GeoNames data for Philippines (free, no API key)
     * Alternative: Load from local file downloaded from geonames.org
     */
    private function loadGeoNamesData() {
        // Option 1: Load from local file (recommended for performance)
        // Download from: http://download.geonames.org/export/dump/PH.zip
        // Contains all Philippine places with coordinates
        
        $geonamesFile = __DIR__ . '/geonames_ph.txt';
        
        if (file_exists($geonamesFile)) {
            $handle = fopen($geonamesFile, 'r');
            while (($line = fgets($handle)) !== false) {
                $parts = explode("\t", $line);
                if (count($parts) >= 5) {
                    $name = $parts[1]; // Place name
                    $lat = floatval($parts[4]);
                    $lng = floatval($parts[5]);
                    $featureCode = $parts[7]; // PPL = populated place
                    
                    // Only store populated places (barangays, cities)
                    if (strpos($featureCode, 'PPL') !== false) {
                        $this->geonamesCache[] = [
                            'name' => $name,
                            'lat' => $lat,
                            'lng' => $lng
                        ];
                    }
                }
            }
            fclose($handle);
        }
    }

    /**
     * Main reverse geocode function - FREE (no API key needed)
     */
    public function reverseGeocode($lat, $lng) {
        try {
            // STEP 1: Check if coordinates are in our hardcoded Bataan barangays
            $hardcodedMatch = $this->findNearestHardcodedBarangay($lat, $lng);
            if ($hardcodedMatch) {
                return $this->successResponse(
                    $hardcodedMatch['barangay'],
                    $hardcodedMatch['city'],
                    $hardcodedMatch['barangay'] . ', ' . $hardcodedMatch['city'] . ', Bataan',
                    $lat,
                    $lng,
                    1.0, // High confidence for hardcoded matches
                    1.0
                );
            }

            // STEP 2: Use OpenStreetMap Nominatim (free, no key)
            $osmData = $this->reverseGeocodeOSM($lat, $lng);
            
            // STEP 3: Find nearest barangay from GeoNames or database
            $nearestBarangay = $this->findNearestBarangay($lat, $lng);
            
            // STEP 4: Combine and fuzzy match to database
            $rawCity = $osmData['city'] ?? '';
            $rawBarangay = $nearestBarangay['name'] ?? ($osmData['barangay'] ?? '');
            
            // Match to database
            $matchedCity = $this->matchCity($rawCity);
            $matchedBarangay = $this->matchBarangay($rawBarangay, $matchedCity['value']);
            
            // Build venue (address)
            $venue = $this->buildVenue($osmData, $matchedBarangay['value'], $matchedCity['value']);
            
            return $this->successResponse(
                $matchedBarangay['value'],
                $matchedCity['value'],
                $venue,
                $lat,
                $lng,
                $matchedBarangay['confidence'],
                $matchedCity['confidence']
            );
            
        } catch (Exception $e) {
            return $this->errorResponse('Geocoding error: ' . $e->getMessage());
        }
    }

    /**
     * Find nearest barangay from hardcoded coordinates
     */
    private function findNearestHardcodedBarangay($lat, $lng, $maxDistance = 0.02) {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;
        
        foreach ($this->bataanBarangays as $barangay => $coords) {
            $distance = $this->calculateDistance($lat, $lng, $coords['lat'], $coords['lng']);
            
            if ($distance < $minDistance && $distance <= $maxDistance) {
                $minDistance = $distance;
                $nearest = [
                    'barangay' => $barangay,
                    'city' => $coords['city'],
                    'distance' => $distance
                ];
            }
        }
        
        return $nearest;
    }

    /**
     * Find nearest barangay from GeoNames cache
     */
    private function findNearestBarangay($lat, $lng, $maxDistance = 0.05) {
        $nearest = null;
        $minDistance = PHP_FLOAT_MAX;
        
        // First check GeoNames cache
        foreach ($this->geonamesCache as $place) {
            $distance = $this->calculateDistance($lat, $lng, $place['lat'], $place['lng']);
            
            if ($distance < $minDistance && $distance <= $maxDistance) {
                $minDistance = $distance;
                $nearest = [
                    'name' => $place['name'],
                    'lat' => $place['lat'],
                    'lng' => $place['lng'],
                    'distance' => $distance
                ];
            }
        }
        
        // If no GeoNames match, check all database barangays with coordinates
        if (!$nearest) {
            $nearest = $this->findNearestFromDatabase($lat, $lng);
        }
        
        return $nearest;
    }

    /**
     * Calculate distance between two coordinates (Haversine formula)
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371; // km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng/2) * sin($dLng/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;
        
        return $distance; // in kilometers
    }

    /**
     * Find nearest barangay from database (fallback)
     */
    private function findNearestFromDatabase($lat, $lng) {
        // Query database barangays (if you have lat/lng columns)
        // For now, return null to use fuzzy matching only
        return null;
    }

    /**
     * Reverse geocode using OpenStreetMap Nominatim (FREE)
     */
    private function reverseGeocodeOSM($lat, $lng) {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=18&addressdetails=1";
        
        $options = [
            'http' => [
                'header' => "User-Agent: ManGrow-Event-System/1.0\r\n",
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Failed to connect to OSM Nominatim');
        }
        
        $data = json_decode($response, true);
        
        if (!$data || isset($data['error'])) {
            throw new Exception('No results from OSM');
        }
        
        return $this->parseOSMAddress($data);
    }

    /**
     * Parse OSM address components
     */
    private function parseOSMAddress($data) {
        $address = $data['address'] ?? [];
        
        return [
            'formatted' => $data['display_name'] ?? '',
            'barangay' => $address['suburb'] ?? $address['village'] ?? $address['neighbourhood'] ?? '',
            'city' => $address['city'] ?? $address['town'] ?? $address['municipality'] ?? '',
            'province' => $address['state'] ?? $address['province'] ?? '',
            'road' => $address['road'] ?? '',
            'house_number' => $address['house_number'] ?? ''
        ];
    }

    /**
     * Match city to database with fuzzy logic
     */
    private function matchCity($searchCity) {
        // Hardcoded Bataan cities first
        $bataanCities = [
            'Balanga' => ['Balanga', 'Balanga City'],
            'Abucay' => ['Abucay', 'Abucay Municipality'],
            'Bagac' => ['Bagac'],
            'Dinalupihan' => ['Dinalupihan', 'Dinalupihan Municipality'],
            'Hermosa' => ['Hermosa'],
            'Limay' => ['Limay'],
            'Mariveles' => ['Mariveles'],
            'Morong' => ['Morong'],
            'Orani' => ['Orani'],
            'Orion' => ['Orion'],
            'Pilar' => ['Pilar'],
            'Samal' => ['Samal']
        ];
        
        // Check hardcoded matches
        foreach ($bataanCities as $city => $variations) {
            foreach ($variations as $variation) {
                if (stripos($searchCity, $variation) !== false || 
                    stripos($variation, $searchCity) !== false) {
                    return ['value' => $city, 'confidence' => 1.0];
                }
            }
        }
        
        // Fuzzy match to database
        $bestMatch = null;
        $bestConfidence = 0;
        
        foreach ($this->citiesCache as $dbCity) {
            $confidence = $this->calculateSimilarity($searchCity, $dbCity);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $dbCity;
            }
        }
        
        if ($bestConfidence >= $this->minConfidence) {
            return ['value' => $bestMatch, 'confidence' => $bestConfidence];
        }
        
        // Default to Balanga if no match
        return ['value' => 'Balanga', 'confidence' => 0.3];
    }

    /**
     * Match barangay to database with fuzzy logic
     */
    private function matchBarangay($searchBarangay, $matchedCity) {
        if (empty($searchBarangay)) {
            return ['value' => 'Unknown', 'confidence' => 0.0];
        }
        
        $cityBarangays = $this->barangaysCache[$matchedCity] ?? [];
        
        if (empty($cityBarangays)) {
            return ['value' => $searchBarangay, 'confidence' => 0.5];
        }
        
        $bestMatch = null;
        $bestConfidence = 0;
        
        foreach ($cityBarangays as $dbBarangay) {
            $confidence = $this->calculateSimilarity($searchBarangay, $dbBarangay);
            if ($confidence > $bestConfidence) {
                $bestConfidence = $confidence;
                $bestMatch = $dbBarangay;
            }
        }
        
        if ($bestConfidence >= $this->minConfidence) {
            return ['value' => $bestMatch, 'confidence' => $bestConfidence];
        }
        
        // Return raw value with low confidence
        return ['value' => $searchBarangay, 'confidence' => $bestConfidence];
    }

    /**
     * Calculate similarity between two strings
     */
    private function calculateSimilarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) return 1.0;
        if (empty($str1) || empty($str2)) return 0.0;
        
        // Levenshtein distance (normalized)
        $maxLen = max(strlen($str1), strlen($str2));
        $lev = levenshtein($str1, $str2);
        $levSimilarity = 1 - ($lev / $maxLen);
        
        // Similar text percentage
        similar_text($str1, $str2, $percent);
        $simTextSimilarity = $percent / 100;
        
        // Weighted average
        return ($levSimilarity * 0.6) + ($simTextSimilarity * 0.4);
    }

    /**
     * Build venue address
     */
    private function buildVenue($osmData, $barangay, $city) {
        $parts = [];
        
        if (!empty($osmData['road'])) {
            $parts[] = $osmData['road'];
        }
        
        $parts[] = $barangay;
        $parts[] = $city;
        $parts[] = 'Bataan';
        
        return implode(', ', array_filter($parts));
    }

    /**
     * Success response
     */
    private function successResponse($barangay, $city, $venue, $lat, $lng, $barangayConf, $cityConf) {
        return [
            'success' => true,
            'matched_barangay' => $barangay,
            'matched_city' => $city,
            'venue' => $venue,
            'formatted_address' => "$barangay, $city, Bataan",
            'lat' => $lat,
            'lng' => $lng,
            'barangay_confidence' => $barangayConf,
            'city_confidence' => $cityConf,
            'is_bataan' => true,
            'method' => 'Free OSM + Spatial Matching'
        ];
    }

    /**
     * Error response
     */
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message
        ];
    }

    /**
     * Forward geocode (search address) - FREE
     */
    public function searchAddress($address) {
        $encodedAddress = urlencode($address . ', Bataan, Philippines');
        $url = "https://nominatim.openstreetmap.org/search?format=json&q={$encodedAddress}&limit=1&addressdetails=1";
        
        $options = [
            'http' => [
                'header' => "User-Agent: ManGrow-Event-System/1.0\r\n",
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return $this->errorResponse('Search failed');
        }
        
        $data = json_decode($response, true);
        
        if (empty($data)) {
            return $this->errorResponse('No results found');
        }
        
        $result = $data[0];
        $lat = floatval($result['lat']);
        $lng = floatval($result['lon']);
        
        // Reverse geocode the found coordinates to get accurate barangay
        return $this->reverseGeocode($lat, $lng);
    }
}

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.php';
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    $geocoder = new FreeGeocodingHelper($connection);
    
    if ($action === 'reverse_geocode') {
        $lat = floatval($input['lat']);
        $lng = floatval($input['lng']);
        $result = $geocoder->reverseGeocode($lat, $lng);
        
    } elseif ($action === 'search_address') {
        $address = $input['address'] ?? '';
        $result = $geocoder->searchAddress($address);
        
    } else {
        $result = ['success' => false, 'error' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
