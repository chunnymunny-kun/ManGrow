<?php
session_start();
require_once 'database.php';
require_once 'event_qr_system.php';

// Check if user is logged in (use the test_login.php first if not)
if (!isset($_SESSION['user_id'])) {
    echo "<h1>⚠️ Login Required</h1>";
    echo "<p>Please <a href='test_login.php'>click here to login</a> first, then return to this page.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];

echo "<h1>🎯 Create Test Event with QR Codes</h1>";
echo "<p><strong>Logged in as:</strong> {$_SESSION['name']} (ID: {$user_id})</p>";

try {
    // Create a test event that lasts 10 minutes from now
    $start_time = new DateTime();
    $end_time = new DateTime();
    $end_time->add(new DateInterval('PT10M')); // Add 10 minutes
    
    // Fix variable assignments to avoid notice
    $start_date_str = $start_time->format('Y-m-d H:i:s');
    $end_date_str = $end_time->format('Y-m-d H:i:s');
    
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
    $description = "This is a 10-minute test event to demonstrate the QR Code check-in/check-out system. Participants can earn eco points and badges by attending!";
    $venue = "Test Venue";
    $barangay = "Test Barangay";
    $city = "Test City";
    $program_type = "Community Engagement";
    $event_type = "Environmental";
    $participants = 50;
    $eco_points = 25;
    $is_approved = "Approved";
    $organization = "ManGrow Platform";
    
    $stmt->bind_param("sssssssssisiis", 
        $subject, 
        $description, 
        $venue, 
        $barangay, 
        $city, 
        $start_date_str, 
        $end_date_str, 
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
            echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
            echo "<h3>📋 Event Details:</h3>";
            echo "<p><strong>Event ID:</strong> {$event_id}</p>";
            echo "<p><strong>Subject:</strong> {$subject}</p>";
            echo "<p><strong>Duration:</strong> 10 minutes (ends at " . $end_time->format('Y-m-d H:i:s') . ")</p>";
            echo "<p><strong>Eco Points Reward:</strong> {$eco_points} points</p>";
            echo "</div>";
            
            echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
            echo "<h3>🔗 Generated QR Codes:</h3>";
            echo "<p><strong>Check-in Token:</strong> <code style='background: #fff; padding: 5px; border-radius: 3px;'>" . $result['checkin_token'] . "</code></p>";
            echo "<p><strong>Check-out Token:</strong> <code style='background: #fff; padding: 5px; border-radius: 3px;'>" . $result['checkout_token'] . "</code></p>";
            echo "</div>";
            
            echo "<div style='background: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
            echo "<h3>🎯 Testing Steps:</h3>";
            echo "<ol style='line-height: 1.8;'>";
            echo "<li><strong>View QR Codes:</strong> Click the QR Management button below to see the visual QR codes</li>";
            echo "<li><strong>Test Check-in:</strong> Use the QR Scanner to scan or manually enter the check-in token</li>";
            echo "<li><strong>Test Check-out:</strong> After check-in, scan or enter the check-out token</li>";
            echo "<li><strong>Verify Rewards:</strong> Check your profile for newly earned eco points and badges</li>";
            echo "</ol>";
            echo "</div>";
            
            echo "<div style='text-align: center; margin: 30px 0;'>";
            echo "<h3>📱 Quick Access Links:</h3>";
            echo "<a href='events.php' style='color: white; background: #2c5e3f; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin: 5px; display: inline-block;'>📋 Events Page</a>";
            echo "<a href='event_qr_management.php?event_id={$event_id}' style='color: white; background: #4CAF50; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin: 5px; display: inline-block;'>⚙️ QR Management Dashboard</a>";
            echo "<a href='qr_scan.php' style='color: white; background: #FF9800; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin: 5px; display: inline-block;'>📷 QR Scanner</a>";
            echo "</div>";
            
            echo "<div style='background: #d1ecf1; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
            echo "<h3>💡 Manual Testing Tokens:</h3>";
            echo "<p>If camera scanning doesn't work, you can manually enter these tokens in the QR scanner:</p>";
            echo "<p><strong>1. Check-in:</strong> <input type='text' value='" . $result['checkin_token'] . "' style='width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px;' readonly onclick='this.select()'></p>";
            echo "<p><strong>2. Check-out:</strong> <input type='text' value='" . $result['checkout_token'] . "' style='width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px;' readonly onclick='this.select()'></p>";
            echo "<p><small>Click the input fields above to select and copy the tokens</small></p>";
            echo "</div>";
            
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
    font-family: 'Segoe UI', Arial, sans-serif;
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
    line-height: 1.6;
    background: #f8f9fa;
}

h1, h2, h3 {
    color: #2c5e3f;
}

code {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

a:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

ol li {
    margin: 8px 0;
}
</style>
