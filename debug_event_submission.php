<?php
session_start();
require_once 'database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Event Submission Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        .section { margin: 20px 0; padding: 15px; background: #2d2d2d; border-left: 4px solid #007acc; }
        .error { color: #f48771; }
        .success { color: #4ec9b0; }
        .warning { color: #dcdcaa; }
        h2 { color: #4ec9b0; }
        h3 { color: #569cd6; }
        pre { background: #1e1e1e; padding: 10px; overflow-x: auto; }
        .highlight { background: #264f78; padding: 2px 4px; }
    </style>
</head>
<body>

<h1>🔍 EVENT SUBMISSION DEBUG REPORT</h1>

<?php
// 1. CHECK SESSION
echo '<div class="section">';
echo '<h2>1. SESSION CHECK</h2>';
echo '<pre>';
echo "Session ID: " . session_id() . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "name: " . ($_SESSION['name'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "email: " . ($_SESSION['email'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "accessrole: " . ($_SESSION['accessrole'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "barangay: " . ($_SESSION['barangay'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "city_municipality: " . ($_SESSION['city_municipality'] ?? '<span class="error">NOT SET</span>') . "\n";
echo "organization: " . ($_SESSION['organization'] ?? '<span class="error">NOT SET</span>') . "\n";
echo '</pre>';
echo '</div>';

// 2. CHECK IF USER EXISTS IN accountstbl
echo '<div class="section">';
echo '<h2>2. USER ACCOUNT VERIFICATION</h2>';
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $connection->prepare("SELECT account_id, name, email FROM accountstbl WHERE account_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo '<pre class="success">';
        echo "✅ USER EXISTS in accountstbl\n";
        echo "account_id: " . $user['account_id'] . "\n";
        echo "name: " . $user['name'] . "\n";
        echo "email: " . $user['email'] . "\n";
        echo '</pre>';
    } else {
        echo '<pre class="error">';
        echo "❌ USER NOT FOUND in accountstbl with account_id = " . $userId . "\n\n";
        echo "CRITICAL ERROR: This is why the foreign key constraint fails!\n";
        echo "The session user_id (" . $userId . ") does not exist in accountstbl.account_id\n";
        echo '</pre>';
        
        // Try to find by email
        if (isset($_SESSION['email'])) {
            $emailStmt = $connection->prepare("SELECT account_id, name, email FROM accountstbl WHERE email = ?");
            $emailStmt->bind_param("s", $_SESSION['email']);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            
            if ($emailResult->num_rows > 0) {
                $emailUser = $emailResult->fetch_assoc();
                echo '<pre class="warning">';
                echo "⚠️ FOUND USER BY EMAIL:\n";
                echo "account_id: " . $emailUser['account_id'] . "\n";
                echo "name: " . $emailUser['name'] . "\n";
                echo "email: " . $emailUser['email'] . "\n\n";
                echo "🔧 FIX: Update session to use account_id " . $emailUser['account_id'] . "\n";
                echo '</pre>';
            }
        }
    }
} else {
    echo '<pre class="error">❌ No user_id in session</pre>';
}
echo '</div>';

// 3. CHECK eventstbl STRUCTURE
echo '<div class="section">';
echo '<h2>3. EVENTSTBL STRUCTURE</h2>';
$structureQuery = "SHOW CREATE TABLE eventstbl";
$structureResult = $connection->query($structureQuery);
if ($structureResult) {
    $structure = $structureResult->fetch_assoc();
    echo '<pre>';
    echo htmlspecialchars($structure['Create Table']);
    echo '</pre>';
}
echo '</div>';

// 4. CHECK FOREIGN KEY CONSTRAINTS
echo '<div class="section">';
echo '<h2>4. FOREIGN KEY CONSTRAINTS</h2>';
$fkQuery = "SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'mangrowdb'
AND TABLE_NAME = 'eventstbl'
AND REFERENCED_TABLE_NAME IS NOT NULL";

$fkResult = $connection->query($fkQuery);
echo '<pre>';
while ($fk = $fkResult->fetch_assoc()) {
    echo "Constraint: " . $fk['CONSTRAINT_NAME'] . "\n";
    echo "  Column: " . $fk['COLUMN_NAME'] . " -> " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "\n\n";
}
echo '</pre>';
echo '</div>';

// 5. TEST INSERT WITH DUMMY DATA
echo '<div class="section">';
echo '<h2>5. TEST INSERT (DRY RUN)</h2>';
echo '<h3>Testing if we can insert an event with current session user_id...</h3>';

if (isset($_SESSION['user_id'])) {
    echo '<pre>';
    echo "Attempting to insert with author = " . $_SESSION['user_id'] . "\n\n";
    
    $testData = [
        'author' => $_SESSION['user_id'],
        'organization' => $_SESSION['organization'] ?? null,
        'thumbnail' => 'uploads/events/test.jpg',
        'subject' => 'TEST EVENT',
        'program_type' => 'Announcement',
        'event_type' => null,
        'start_date' => null,
        'end_date' => null,
        'description' => 'Test description',
        'venue' => null,
        'latitude' => null,
        'longitude' => null,
        'barangay' => null,
        'city_municipality' => null,
        'area_no' => null,
        'eco_points' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'posted_at' => date('Y-m-d H:i:s'),
        'is_approved' => 'Approved',
        'participants' => 0,
        'event_links' => null,
        'is_cross_barangay' => 0,
        'requires_special_approval' => 0,
        'attachments_metadata' => null,
        'special_notes' => null,
        'disapproval_note' => null
    ];
    
    $columns = implode(', ', array_keys($testData));
    $placeholders = implode(', ', array_fill(0, count($testData), '?'));
    $sql = "INSERT INTO eventstbl ($columns) VALUES ($placeholders)";
    
    echo "SQL: " . $sql . "\n\n";
    echo "Values:\n";
    foreach ($testData as $key => $value) {
        echo "  " . $key . ": " . ($value === null ? 'NULL' : $value) . "\n";
    }
    
    $testStmt = $connection->prepare($sql);
    if (!$testStmt) {
        echo '<span class="error">❌ PREPARE FAILED: ' . $connection->error . '</span>' . "\n";
    } else {
        // Bind parameters
        $types = '';
        $values = [];
        foreach ($testData as $value) {
            if (is_int($value)) $types .= 'i';
            elseif (is_float($value)) $types .= 'd';
            else $types .= 's';
            $values[] = $value;
        }
        
        $testStmt->bind_param($types, ...$values);
        
        // DON'T ACTUALLY EXECUTE - JUST CHECK IF IT WOULD WORK
        echo "\n<span class=\"warning\">⚠️ Skipping actual execution (dry run)</span>\n";
        echo "\nIf you want to test the actual insert, uncomment the execute line in the script.\n";
        
        // Uncomment to actually test:
        // if ($testStmt->execute()) {
        //     echo '<span class="success">✅ TEST INSERT SUCCESSFUL!</span>' . "\n";
        //     $testId = $connection->insert_id;
        //     echo "Inserted event_id: " . $testId . "\n";
        //     // Clean up
        //     $connection->query("DELETE FROM eventstbl WHERE event_id = " . $testId);
        // } else {
        //     echo '<span class="error">❌ TEST INSERT FAILED: ' . $testStmt->error . '</span>' . "\n";
        // }
    }
    echo '</pre>';
} else {
    echo '<pre class="error">❌ Cannot test - no user_id in session</pre>';
}
echo '</div>';

// 6. CHECK FORM POST DATA SIMULATION
echo '<div class="section">';
echo '<h2>6. FORM DATA CHECK</h2>';
echo '<h3>What data would be sent from the form:</h3>';
echo '<pre>';
echo "Required for Event:\n";
echo "  - title (from input#title)\n";
echo "  - program_type (from select#program-type)\n";
echo "  - event_type OR manual_event_type\n";
echo "  - description (from textarea#description)\n";
echo "  - venue (from input#venue)\n";
echo "  - city (from input#city)\n";
echo "  - barangay (from input#barangay)\n";
echo "  - thumbnail (FILE upload)\n";
echo "\nOptional:\n";
echo "  - start_date, end_date\n";
echo "  - latitude, longitude\n";
echo "  - area_no\n";
echo "  - eco_points\n";
echo "  - link\n";
echo "  - special_notes\n";
echo "  - attachments[] (for cross-barangay)\n";
echo '</pre>';
echo '</div>';

// 7. JAVASCRIPT CONSOLE LOG GENERATOR
echo '<div class="section">';
echo '<h2>7. CLIENT-SIDE DEBUG CODE</h2>';
echo '<h3>Add this to create_event.php form submission handler:</h3>';
echo '<pre>';
echo htmlspecialchars("
form.addEventListener('submit', function(e) {
    e.preventDefault(); // TEMPORARILY PREVENT SUBMISSION
    
    console.log('=== FORM SUBMISSION DEBUG ===');
    console.log('Program Type:', document.getElementById('program-type').value);
    console.log('Title:', document.getElementById('title').value);
    console.log('Description:', document.getElementById('description').value);
    console.log('Event Type (dropdown):', document.getElementById('event-type').value);
    console.log('Event Type (manual):', document.getElementById('manual-event-type').value);
    console.log('Venue:', document.getElementById('venue').value);
    console.log('City:', document.getElementById('city').value);
    console.log('Barangay:', document.getElementById('barangay').value);
    console.log('Latitude:', document.getElementById('latitude').value);
    console.log('Longitude:', document.getElementById('longitude').value);
    console.log('Area No:', document.getElementById('area-no').value);
    console.log('Thumbnail:', document.getElementById('thumbnail').files[0]?.name);
    console.log('Start Date:', document.getElementById('start-date').value);
    console.log('End Date:', document.getElementById('end-date').value);
    
    // Check which fields are visible/hidden
    console.log('Event Type Container visible:', 
        document.querySelector('.event-type-container').style.display !== 'none');
    console.log('Date Container visible:', 
        document.querySelector('.form-group-dates').style.display !== 'none');
    
    // UNCOMMENT TO ACTUALLY SUBMIT:
    // this.submit();
});
");
echo '</pre>';
echo '</div>';

// 8. SUMMARY
echo '<div class="section">';
echo '<h2>8. SUMMARY & ACTION ITEMS</h2>';
echo '<pre>';
$issues = [];
$fixes = [];

if (!isset($_SESSION['user_id'])) {
    $issues[] = "❌ No user_id in session";
    $fixes[] = "Log in again or check login script";
} else {
    $userCheck = $connection->prepare("SELECT account_id FROM accountstbl WHERE account_id = ?");
    $userCheck->bind_param("i", $_SESSION['user_id']);
    $userCheck->execute();
    if ($userCheck->get_result()->num_rows === 0) {
        $issues[] = "❌ CRITICAL: user_id (" . $_SESSION['user_id'] . ") NOT IN accountstbl";
        $fixes[] = "Fix login script to set correct account_id in session";
        $fixes[] = "OR update session: \$_SESSION['user_id'] = <correct_account_id>";
    }
}

if (empty($issues)) {
    echo '<span class="success">✅ NO CRITICAL ISSUES FOUND!</span>' . "\n\n";
    echo "The form should be able to submit. If it's not submitting:\n";
    echo "1. Check browser console for JavaScript errors\n";
    echo "2. Add the debug console.log code from section 7\n";
    echo "3. Check if form is actually reaching uploadevent.php\n";
} else {
    echo '<span class="error">ISSUES FOUND:</span>' . "\n";
    foreach ($issues as $issue) {
        echo "  " . $issue . "\n";
    }
    echo "\n<span class=\"warning\">FIXES NEEDED:</span>\n";
    foreach ($fixes as $fix) {
        echo "  🔧 " . $fix . "\n";
    }
}
echo '</pre>';
echo '</div>';

$connection->close();
?>

</body>
</html>
