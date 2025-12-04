<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location System Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { color: #2c5f2d; }
        h2 { color: #3e7b27; border-bottom: 2px solid #3e7b27; padding-bottom: 10px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #007bff; }
        button {
            background: #3e7b27;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover { background: #2c5f2d; }
        input {
            padding: 8px;
            margin: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 200px;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #3e7b27;
            color: white;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>🧪 Enhanced Location System - Test Suite</h1>
    
    <!-- Test 1: Database Connection -->
    <div class="test-section">
        <h2>Test 1: Database Tables</h2>
        <?php
        require_once 'database.php';
        
        echo "<h3>Checking citymunicipalitytbl...</h3>";
        $cityQuery = "SELECT * FROM citymunicipalitytbl LIMIT 5";
        $cityResult = mysqli_query($connection, $cityQuery);
        
        if ($cityResult) {
            echo "<p class='success'>✅ citymunicipalitytbl exists and is accessible</p>";
            echo "<table><tr><th>City/Municipality</th></tr>";
            while ($row = mysqli_fetch_assoc($cityResult)) {
                echo "<tr><td>" . htmlspecialchars($row['city']) . "</td></tr>";
            }
            echo "</table>";
            
            $countResult = mysqli_query($connection, "SELECT COUNT(*) as count FROM citymunicipalitytbl");
            $count = mysqli_fetch_assoc($countResult)['count'];
            echo "<p class='info'>Total cities: {$count}</p>";
        } else {
            echo "<p class='error'>❌ Error: " . mysqli_error($connection) . "</p>";
        }
        
        echo "<h3>Checking barangaytbl...</h3>";
        $barangayQuery = "SELECT * FROM barangaytbl LIMIT 10";
        $barangayResult = mysqli_query($connection, $barangayQuery);
        
        if ($barangayResult) {
            echo "<p class='success'>✅ barangaytbl exists and is accessible</p>";
            echo "<table><tr><th>Barangay</th><th>City/Municipality</th></tr>";
            while ($row = mysqli_fetch_assoc($barangayResult)) {
                echo "<tr><td>" . htmlspecialchars($row['barangay']) . "</td>";
                echo "<td>" . htmlspecialchars($row['city_municipality']) . "</td></tr>";
            }
            echo "</table>";
            
            $countResult = mysqli_query($connection, "SELECT COUNT(*) as count FROM barangaytbl");
            $count = mysqli_fetch_assoc($countResult)['count'];
            echo "<p class='info'>Total barangays: {$count}</p>";
        } else {
            echo "<p class='error'>❌ Error: " . mysqli_error($connection) . "</p>";
        }
        ?>
    </div>

    <!-- Test 2: Geocoding Helper -->
    <div class="test-section">
        <h2>Test 2: Geocoding Helper Class</h2>
        <?php
        if (file_exists('geocoding_helper.php')) {
            echo "<p class='success'>✅ geocoding_helper.php exists</p>";
            require_once 'geocoding_helper.php';
            
            try {
                $geocoder = new GeocodingHelper($connection, false, '');
                echo "<p class='success'>✅ GeocodingHelper class instantiated successfully</p>";
                
                // Test reverse geocoding
                echo "<h3>Testing reverse geocoding (BPSU Main Campus area)</h3>";
                $testLat = 14.6796;
                $testLng = 120.5415;
                
                echo "<p class='info'>Testing coordinates: {$testLat}, {$testLng}</p>";
                $result = $geocoder->reverseGeocode($testLat, $testLng);
                
                if ($result['success']) {
                    echo "<p class='success'>✅ Geocoding successful</p>";
                    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";
                    
                    // Show confidence badges
                    $barangayConf = round($result['barangay_confidence'] * 100);
                    $cityConf = round($result['city_confidence'] * 100);
                    
                    $barangayBadge = $barangayConf >= 80 ? 'success' : ($barangayConf >= 60 ? 'warning' : 'danger');
                    $cityBadge = $cityConf >= 80 ? 'success' : ($cityConf >= 60 ? 'warning' : 'danger');
                    
                    echo "<p>Barangay Match: <span class='badge badge-{$barangayBadge}'>{$barangayConf}%</span></p>";
                    echo "<p>City Match: <span class='badge badge-{$cityBadge}'>{$cityConf}%</span></p>";
                } else {
                    echo "<p class='error'>❌ Geocoding failed: " . ($result['error'] ?? 'Unknown error') . "</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>❌ geocoding_helper.php not found</p>";
        }
        ?>
    </div>

    <!-- Test 3: Enhanced Geocoding API -->
    <div class="test-section">
        <h2>Test 3: Enhanced Geocoding API</h2>
        <?php
        if (file_exists('enhanced_geocoding_api.php')) {
            echo "<p class='success'>✅ enhanced_geocoding_api.php exists</p>";
        } else {
            echo "<p class='error'>❌ enhanced_geocoding_api.php not found</p>";
        }
        ?>
        
        <h3>Interactive API Test</h3>
        <div>
            <h4>Test Reverse Geocoding</h4>
            <input type="number" id="test-lat" placeholder="Latitude" value="14.6796" step="0.0001">
            <input type="number" id="test-lng" placeholder="Longitude" value="120.5415" step="0.0001">
            <button onclick="testReverseGeocode()">Test Reverse Geocode</button>
            <div id="reverse-result"></div>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>Test Address Search</h4>
            <input type="text" id="test-search" placeholder="Search query" value="BPSU Main Campus" style="width: 300px;">
            <button onclick="testAddressSearch()">Test Search</button>
            <div id="search-result"></div>
        </div>

        <div style="margin-top: 20px;">
            <h4>Common Test Locations</h4>
            <button onclick="testLocation(14.6796, 120.5415, 'BPSU Main Campus')">BPSU Main Campus</button>
            <button onclick="testLocation(14.6420, 120.5034, 'Balanga City Hall')">Balanga City Hall</button>
            <button onclick="testLocation(14.7422, 120.4667, 'Wawa, Abucay')">Wawa, Abucay</button>
            <button onclick="testLocation(14.8167, 120.5000, 'Samal Town')">Samal</button>
        </div>
    </div>

    <!-- Test 4: File Check -->
    <div class="test-section">
        <h2>Test 4: Required Files</h2>
        <?php
        $requiredFiles = [
            'enhanced_geocoding_api.php' => 'Geocoding API endpoint',
            'event_location_styles.css' => 'Location system styles',
            'event_location_system.js' => 'Location system JavaScript',
            'geocoding_helper.php' => 'Geocoding helper class',
            'create_event.php' => 'Event creation page',
            'edit_event.php' => 'Event editing page'
        ];
        
        echo "<table><tr><th>File</th><th>Description</th><th>Status</th></tr>";
        foreach ($requiredFiles as $file => $desc) {
            $exists = file_exists($file);
            $status = $exists ? "<span class='success'>✅ Exists</span>" : "<span class='error'>❌ Missing</span>";
            echo "<tr><td>{$file}</td><td>{$desc}</td><td>{$status}</td></tr>";
        }
        echo "</table>";
        ?>
    </div>

    <!-- Test 5: Session Variables -->
    <div class="test-section">
        <h2>Test 5: Session Variables</h2>
        <?php
        session_start();
        
        if (isset($_SESSION['barangay']) && isset($_SESSION['city_municipality'])) {
            echo "<p class='success'>✅ Session variables are set</p>";
            echo "<p>User Barangay: <strong>" . htmlspecialchars($_SESSION['barangay']) . "</strong></p>";
            echo "<p>User City: <strong>" . htmlspecialchars($_SESSION['city_municipality']) . "</strong></p>";
            echo "<p class='info'>Cross-barangay detection will work correctly</p>";
        } else {
            echo "<p class='warning' style='color: #856404;'>⚠️ Session variables not set (user not logged in)</p>";
            echo "<p class='info'>This is normal if not logged in. Cross-barangay detection requires login.</p>";
        }
        ?>
    </div>

    <script>
        function testReverseGeocode() {
            const lat = document.getElementById('test-lat').value;
            const lng = document.getElementById('test-lng').value;
            const resultDiv = document.getElementById('reverse-result');
            
            resultDiv.innerHTML = '<p style="color: #007bff;">Loading...</p>';
            
            fetch(`enhanced_geocoding_api.php?action=reverse_geocode&lat=${lat}&lng=${lng}`)
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    resultDiv.innerHTML = '<p style="color: #dc3545;">Error: ' + error.message + '</p>';
                });
        }

        function testAddressSearch() {
            const query = document.getElementById('test-search').value;
            const resultDiv = document.getElementById('search-result');
            
            resultDiv.innerHTML = '<p style="color: #007bff;">Searching...</p>';
            
            fetch(`enhanced_geocoding_api.php?action=search_address&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    resultDiv.innerHTML = '<p style="color: #dc3545;">Error: ' + error.message + '</p>';
                });
        }

        function testLocation(lat, lng, name) {
            document.getElementById('test-lat').value = lat;
            document.getElementById('test-lng').value = lng;
            testReverseGeocode();
        }
    </script>
</body>
</html>
