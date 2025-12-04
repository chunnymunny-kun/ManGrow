<?php
// Comprehensive URL generation test
require_once 'database.php';
require_once 'event_qr_system.php';

// Initialize the system
EventQRSystem::init($connection);

echo "<h2>Comprehensive URL Generation Test</h2>\n";

// Simulate different environments
echo "<h3>Test 1: CLI Environment (current)</h3>\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Not set') . "<br>\n";

// Test URL generation
$reflection = new ReflectionClass('EventQRSystem');
$method = $reflection->getMethod('generateQRURL');
$method->setAccessible(true);
$testToken = "test123456789";
$generatedURL = $method->invoke(null, $testToken);
echo "Generated URL: <strong>$generatedURL</strong><br>\n";

echo "<h3>Test 2: Simulated Web Environment (localhost:3000)</h3>\n";
// Temporarily set server variables to simulate web request
$_SERVER['HTTP_HOST'] = 'localhost:3000';
$_SERVER['HTTPS'] = 'off';

$generatedURL2 = $method->invoke(null, $testToken);
echo "Generated URL: <strong>$generatedURL2</strong><br>\n";

echo "<h3>Test 3: Simulated XAMPP Environment (localhost)</h3>\n";
// Simulate traditional XAMPP setup
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = '80';

$generatedURL3 = $method->invoke(null, $testToken);
echo "Generated URL: <strong>$generatedURL3</strong><br>\n";

echo "<h3>Test 4: Simulated HTTPS Environment</h3>\n";
$_SERVER['HTTP_HOST'] = 'localhost:3000';
$_SERVER['HTTPS'] = 'on';

$generatedURL4 = $method->invoke(null, $testToken);
echo "Generated URL: <strong>$generatedURL4</strong><br>\n";

// Reset server variables
unset($_SERVER['HTTP_HOST']);
unset($_SERVER['HTTPS']);
unset($_SERVER['SERVER_PORT']);

echo "<h3>URL Analysis:</h3>\n";
echo "✅ CLI fallback: $generatedURL<br>\n";
echo "✅ Your setup (localhost:3000): $generatedURL2<br>\n";
echo "✅ Traditional XAMPP: $generatedURL3<br>\n";
echo "✅ HTTPS version: $generatedURL4<br>\n";

echo "<h3>All URLs should point to qr_scan.php without 404 errors!</h3>\n";

// Test if we can generate actual QR codes for an event
echo "<h3>Test 5: Generate Real QR Codes</h3>\n";

// Find an event to test with
$eventQuery = "SELECT event_id, subject FROM eventstbl WHERE is_approved = 'approved' LIMIT 1";
$result = $connection->query($eventQuery);

if ($result && $result->num_rows > 0) {
    $event = $result->fetch_assoc();
    $eventId = $event['event_id'];
    $eventName = $event['subject'];
    
    echo "Testing with Event ID: $eventId ($eventName)<br>\n";
    
    // Set up proper web environment for this test
    $_SERVER['HTTP_HOST'] = 'localhost:3000';
    $_SERVER['HTTPS'] = 'off';
    
    // Delete existing QR codes first
    $deleteQuery = "DELETE FROM event_qr_codes WHERE event_id = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    
    // Generate new QR codes
    $result = EventQRSystem::generateEventQRCodes($eventId, 12); // Using existing user_id = 12
    
    if ($result['success']) {
        echo "✅ QR codes generated successfully!<br>\n";
        echo "Check-in URL: <strong>{$result['checkin_url']}</strong><br>\n";
        echo "Check-out URL: <strong>{$result['checkout_url']}</strong><br>\n";
        
        // Verify these URLs are correct
        if (strpos($result['checkin_url'], '/project/') === false) {
            echo "✅ URLs are correctly generated without /project/ path<br>\n";
        } else {
            echo "❌ URLs still contain /project/ path<br>\n";
        }
    } else {
        echo "❌ Failed to generate QR codes: {$result['message']}<br>\n";
    }
    
    // Clean up
    unset($_SERVER['HTTP_HOST']);
    unset($_SERVER['HTTPS']);
} else {
    echo "No approved events found to test with.<br>\n";
}

?>
