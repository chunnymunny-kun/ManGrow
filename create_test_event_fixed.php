<?php
require_once 'database.php';
require_once 'event_qr_system.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['id'])) {
    die('<h2>Error</h2><p>Please log in to create a test event.</p>');
}

$user_id = $_SESSION['id'];

try {
    // Create a test event that lasts 5 minutes from now
    $start_time = new DateTime();
    $end_time = new DateTime();
    $end_time->add(new DateInterval('PT5M')); // Add 5 minutes
    
    $sql = "INSERT INTO eventstbl (
        subject, 
        description, 
        venue, 
        barangay, 
        city_municipality, 
        start_date, 
        end_date, 
        program_type, 
        event_type, 
        participants, 
        eco_points, 
        created_at, 
        posted_at, 
        is_approved, 
        author, 
        organization,
        qr_checkin_enabled,
        qr_checkout_enabled,
        completion_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, 1, 1, 'ongoing')";
    
    $stmt = $connection->prepare($sql);
    $subject = "QR Test Event - " . date('Y-m-d H:i:s');
    $description = "This is a 5-minute test event to demonstrate the QR Code check-in/check-out system. Participants can earn eco points and badges by attending!";
    $venue = "Test Venue";
    $barangay = "Test Barangay";
    $city = "Test City";
    $program_type = "Community Engagement";
    $event_type = "Environmental";
    $participants = 50;
    $eco_points = 10;
    $is_approved = "Approved";
    $organization = "ManGrow Platform";
    
    $stmt->bind_param("sssssssssisiis", 
        $subject, 
        $description, 
        $venue, 
        $barangay, 
        $city, 
        $start_time->format('Y-m-d H:i:s'), 
        $end_time->format('Y-m-d H:i:s'), 
        $program_type, 
        $event_type, 
        $participants, 
        $eco_points, 
        $is_approved, 
        $user_id, 
        $organization
    );
    
    if ($stmt->execute()) {
        $event_id = $connection->insert_id;
        
        // Initialize and generate QR codes for the event
        EventQRSystem::init($connection);
        $result = EventQRSystem::generateEventQRCodes($event_id, $user_id);
        
        if ($result['success']) {
            echo "<h2>✅ Test Event Created Successfully!</h2>";
            echo "<h3>Event Details:</h3>";
            echo "<p><strong>Event ID:</strong> {$event_id}</p>";
            echo "<p><strong>Subject:</strong> {$subject}</p>";
            echo "<p><strong>Duration:</strong> 5 minutes (ends at " . $end_time->format('Y-m-d H:i:s') . ")</p>";
            echo "<p><strong>Eco Points Reward:</strong> {$eco_points} points</p>";
            
            echo "<h3>🎯 How to Test the QR System:</h3>";
            echo "<ol>";
            echo "<li><strong>Access QR Management:</strong> Go to <a href='events.php'>Events Page</a> and click 'Manage QR' button for your test event</li>";
            echo "<li><strong>Get QR Codes:</strong> In the management dashboard, click 'Generate QR Codes' to create check-in and check-out QR codes</li>";
            echo "<li><strong>Test Check-in:</strong> Use the <a href='qr_scan.php'>QR Scanner</a> (available in sidebar navigation) to scan the check-in QR code</li>";
            echo "<li><strong>Test Check-out:</strong> After check-in, scan the check-out QR code to complete attendance and earn rewards</li>";
            echo "<li><strong>Verify Rewards:</strong> Check your profile for newly earned eco points and badges</li>";
            echo "</ol>";
            
            echo "<h3>📱 Quick Access Links:</h3>";
            echo "<p><a href='events.php'>📋 Events Page</a> | <a href='qr_scan.php'>📷 QR Scanner</a> | <a href='event_qr_management.php?event_id={$event_id}'>⚙️ QR Management Dashboard</a></p>";
            
            echo "<h3>💡 Testing Tips:</h3>";
            echo "<p>• The event will expire automatically in 5 minutes</p>";
            echo "<p>• You can check-in and check-out multiple times for testing</p>";
            echo "<p>• Eco points and badges are awarded upon successful check-out</p>";
            echo "<p>• Use different browser tabs or devices to simulate multiple attendees</p>";
            
        } else {
            echo "<h2>⚠️ Event Created but QR Generation Failed</h2>";
            echo "<p>Event ID: {$event_id}</p>";
            echo "<p>Error: " . $result['message'] . "</p>";
        }
    } else {
        echo "<h2>❌ Failed to Create Test Event</h2>";
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
