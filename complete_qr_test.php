<?php
include 'database.php';
include 'event_qr_system.php';

// Initialize QR system
EventQRSystem::init($connection);

echo "=== COMPLETE QR SYSTEM TEST ===\n\n";

// Step 1: Generate new QR codes
echo "1. Generating fresh QR codes...\n";
EventQRSystem::deactivateEventQRCodes(14);
$result = EventQRSystem::generateEventQRCodes(14, 35);

if (!$result['success']) {
    echo "Failed to generate QR codes: " . $result['message'] . "\n";
    exit;
}

echo "✅ QR codes generated successfully!\n";
$checkinToken = $result['checkin_token'];
$checkoutToken = $result['checkout_token'];
echo "Check-in token: " . substr($checkinToken, 0, 20) . "...\n";
echo "Check-out token: " . substr($checkoutToken, 0, 20) . "...\n\n";

// Step 2: Test check-in with user 35
echo "2. Testing check-in with user 35...\n";
$checkinResult = EventQRSystem::processQRScan($checkinToken, 35);
echo "Result: " . ($checkinResult['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . $checkinResult['message'] . "\n\n";

// Step 3: Test duplicate check-in
echo "3. Testing duplicate check-in (should fail)...\n";
$duplicateCheckin = EventQRSystem::processQRScan($checkinToken, 35);
echo "Result: " . ($duplicateCheckin['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . $duplicateCheckin['message'] . "\n\n";

// Step 4: Test check-out
echo "4. Testing check-out...\n";
$checkoutResult = EventQRSystem::processQRScan($checkoutToken, 35);
echo "Result: " . ($checkoutResult['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . $checkoutResult['message'] . "\n";
if ($checkoutResult['success']) {
    echo "Points awarded: " . ($checkoutResult['points_awarded'] ?? 0) . "\n";
    echo "On time: " . ($checkoutResult['on_time'] ? 'YES' : 'NO') . "\n";
}
echo "\n";

// Step 5: Test duplicate check-out
echo "5. Testing duplicate check-out (should fail)...\n";
$duplicateCheckout = EventQRSystem::processQRScan($checkoutToken, 35);
echo "Result: " . ($duplicateCheckout['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . $duplicateCheckout['message'] . "\n\n";

// Step 6: Test checkout without checkin for different user
echo "6. Testing checkout without checkin for user 36 (should fail)...\n";
$noCheckinCheckout = EventQRSystem::processQRScan($checkoutToken, 36);
echo "Result: " . ($noCheckinCheckout['success'] ? '✅ SUCCESS' : '❌ FAILED') . "\n";
echo "Message: " . $noCheckinCheckout['message'] . "\n\n";

// Step 7: Check completed events
echo "7. Checking user 35's completed events...\n";
$completedCount = EventQRSystem::getUserCompletedEventsCount(35);
$completedList = EventQRSystem::getUserCompletedEventsList(35);
echo "Completed events count: " . $completedCount . "\n";
echo "Events in list: " . count($completedList) . "\n";
if (!empty($completedList)) {
    foreach ($completedList as $event) {
        echo "- " . $event['subject'];
        echo " | Check-in: " . ($event['checkin_time'] ? date('M j H:i', strtotime($event['checkin_time'])) : 'No');
        echo " | Check-out: " . ($event['checkout_time'] ? date('M j H:i', strtotime($event['checkout_time'])) : 'No');
        echo " | Points: " . ($event['points_awarded'] ?? 0);
        echo " | Status: " . $event['attendance_status'] . "\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
?>
