<?php
/**
 * One-Time Script: Generate Barangay Coordinates
 * 
 * This script queries your database and uses free OSM geocoding
 * to generate coordinates for all barangays, which you can then
 * copy into geocoding_helper_free.php for instant matching.
 * 
 * Usage: Run once, copy output to geocoding_helper_free.php
 */

require_once 'database.php';

// Configuration
$delaySeconds = 1; // OSM rate limit: 1 request per second
$maxResults = 50; // Limit for testing (remove for full run)

echo "<!DOCTYPE html>
<html>
<head>
    <title>Barangay Coordinates Generator</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .header { background: #123524; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .progress { background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .code-output { background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 5px; overflow-x: auto; max-height: 600px; overflow-y: auto; }
        .success { color: #4caf50; }
        .error { color: #f44336; }
        .warning { color: #ff9800; }
        .copy-btn { background: #4caf50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; }
        .copy-btn:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🗺️ Barangay Coordinates Generator</h1>
        <p>Generating coordinates for all barangays in your database...</p>
    </div>
    <div class='progress'>";

// Query all barangays
$query = "SELECT DISTINCT barangay, city_municipality 
          FROM barangaytbl 
          ORDER BY city_municipality, barangay";

$result = $connection->query($query);

if (!$result) {
    echo "<span class='error'>❌ Database error: " . $connection->error . "</span>";
    exit;
}

$totalBarangays = $result->num_rows;
echo "<p>📊 Found <strong>{$totalBarangays}</strong> barangays in database</p>";
echo "<p>⏱️ Estimated time: " . ceil($totalBarangays * $delaySeconds / 60) . " minutes</p>";
echo "<p class='warning'>⚠️ Please keep this page open until completion...</p>";
echo "</div>";

// Start generating
$output = "// Auto-generated barangay coordinates\n";
$output .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "// Total barangays: {$totalBarangays}\n\n";
$output .= "private \$bataanBarangays = [\n";

$successful = 0;
$failed = 0;
$count = 0;

echo "<div class='progress'>";
echo "<h3>🔄 Processing...</h3>";

ob_flush();
flush();

while ($row = $result->fetch_assoc()) {
    $count++;
    
    // Limit for testing
    if (isset($maxResults) && $count > $maxResults) {
        echo "<p class='warning'>⚠️ Stopped at {$maxResults} results (testing mode)</p>";
        break;
    }
    
    $barangay = $row['barangay'];
    $city = $row['city_municipality'];
    
    echo "<p>[{$count}/{$totalBarangays}] Processing: <strong>{$barangay}, {$city}</strong>... ";
    ob_flush();
    flush();
    
    // Build search query
    $searchQuery = urlencode("{$barangay}, {$city}, Bataan, Philippines");
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$searchQuery}&limit=1";
    
    // Call OSM Nominatim
    $options = [
        'http' => [
            'header' => "User-Agent: ManGrow-Coordinates-Generator/1.0\r\n",
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        
        if (!empty($data)) {
            $lat = round(floatval($data[0]['lat']), 6);
            $lng = round(floatval($data[0]['lon']), 6);
            
            // Add to output
            $output .= "    '{$barangay}' => ['lat' => {$lat}, 'lng' => {$lng}, 'city' => '{$city}'],\n";
            
            echo "<span class='success'>✅ Success ({$lat}, {$lng})</span></p>";
            $successful++;
        } else {
            echo "<span class='error'>❌ No results</span></p>";
            $failed++;
        }
    } else {
        echo "<span class='error'>❌ Connection failed</span></p>";
        $failed++;
    }
    
    ob_flush();
    flush();
    
    // Rate limit delay (OSM requires 1 request per second)
    if ($count < $totalBarangays) {
        sleep($delaySeconds);
    }
}

$output .= "];\n";

echo "</div>";

// Display results
echo "<div class='progress'>";
echo "<h3>✅ Generation Complete!</h3>";
echo "<p><span class='success'>✅ Successful: {$successful}</span></p>";
echo "<p><span class='error'>❌ Failed: {$failed}</span></p>";
echo "<p>📋 Total processed: {$count}</p>";
echo "</div>";

// Display code output
echo "<div class='code-output' id='codeOutput'>";
echo "<h3 style='color: #4caf50;'>📝 Copy this code to geocoding_helper_free.php (around line 15):</h3>";
echo "<button class='copy-btn' onclick='copyToClipboard()'>📋 Copy to Clipboard</button>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";
echo "</div>";

echo "<script>
function copyToClipboard() {
    const code = `" . addslashes($output) . "`;
    navigator.clipboard.writeText(code).then(() => {
        alert('✅ Copied to clipboard!\\n\\nNow paste this into geocoding_helper_free.php to replace the \$bataanBarangays array.');
    }).catch(err => {
        alert('❌ Failed to copy. Please select and copy manually.');
    });
}
</script>";

echo "</body></html>";

// Save to file as well
$filename = 'generated_barangay_coordinates_' . date('Y-m-d_His') . '.txt';
file_put_contents($filename, $output);

echo "<div class='progress'>";
echo "<p>💾 Output also saved to: <strong>{$filename}</strong></p>";
echo "<p>📝 Next steps:</p>";
echo "<ol>
    <li>Copy the code above</li>
    <li>Open <code>geocoding_helper_free.php</code></li>
    <li>Find the <code>\$bataanBarangays</code> array (around line 15)</li>
    <li>Replace the array with the generated code</li>
    <li>Save and test!</li>
</ol>";
echo "</div>";
?>
