<?php
include 'database.php';

// Create a test event that lasts 5 minutes for testing
$testEventData = [
    'subject' => 'QR System Test Event',
    'description' => 'This is a 5-minute test event to demonstrate the QR check-in/check-out system with eco points and badge rewards.',
    'start_date' => date('Y-m-d H:i:s', strtotime('+1 minute')), // Starts in 1 minute
    'end_date' => date('Y-m-d H:i:s', strtotime('+6 minutes')), // Ends in 6 minutes (5 min duration)
    'venue' => 'Test Venue - Your Computer',
    'barangay' => 'Test Barangay',
    'city_municipality' => 'Test City',
    'author' => 1, // Change this to your user ID
    'eco_points' => 100, // Award 100 eco points for completion
    'completion_status' => 'pending',
    'qr_checkin_enabled' => 1,
    'qr_checkout_enabled' => 1
];

try {
    // Insert the test event
    $insertQuery = "INSERT INTO eventstbl (subject, description, start_date, end_date, venue, barangay, city_municipality, author, eco_points, completion_status, qr_checkin_enabled, qr_checkout_enabled, date_posted) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("ssssssssisss", 
        $testEventData['subject'],
        $testEventData['description'],
        $testEventData['start_date'],
        $testEventData['end_date'],
        $testEventData['venue'],
        $testEventData['barangay'],
        $testEventData['city_municipality'],
        $testEventData['author'],
        $testEventData['eco_points'],
        $testEventData['completion_status'],
        $testEventData['qr_checkin_enabled'],
        $testEventData['qr_checkout_enabled']
    );
    
    if ($stmt->execute()) {
        $eventId = $connection->insert_id;
        
        echo "<h2>Test Event Created Successfully!</h2>";
        echo "<p><strong>Event ID:</strong> $eventId</p>";
        echo "<p><strong>Title:</strong> " . htmlspecialchars($testEventData['subject']) . "</p>";
        echo "<p><strong>Start Time:</strong> " . $testEventData['start_date'] . "</p>";
        echo "<p><strong>End Time:</strong> " . $testEventData['end_date'] . "</p>";
        echo "<p><strong>Eco Points:</strong> " . $testEventData['eco_points'] . "</p>";
        
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li><a href='event_qr_management.php?event_id=$eventId' target='_blank'>Generate QR Codes for this event</a></li>";
        echo "<li><a href='qr_scan.php' target='_blank'>Open QR Scanner (for testing)</a></li>";
        echo "<li><a href='events.php' target='_blank'>View Events Page</a></li>";
        echo "</ol>";
        
        echo "<h3>Testing Instructions:</h3>";
        echo "<ol>";
        echo "<li>Click the QR Management link above</li>";
        echo "<li>Generate QR codes for the event</li>";
        echo "<li>Open the QR Scanner in another tab/window</li>";
        echo "<li>Use the manual token input to test (copy tokens from QR management page)</li>";
        echo "<li>First check-in with the check-in token</li>";
        echo "<li>Then check-out with the check-out token to receive eco points and badges</li>";
        echo "</ol>";
        
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
        echo "<strong>Note:</strong> This event will be active for 5 minutes starting from " . $testEventData['start_date'] . ". ";
        echo "You can test the QR system during this time window.";
        echo "</div>";
        
    } else {
        echo "<h2>Error Creating Test Event</h2>";
        echo "<p>Error: " . $stmt->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>Exception: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
}

h2 {
    color: #2c5e3f;
}

h3 {
    color: #4CAF50;
}

a {
    color: #4CAF50;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

ol {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
}

p {
    margin: 10px 0;
}
</style>
