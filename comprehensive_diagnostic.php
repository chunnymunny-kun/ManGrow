<?php
// Comprehensive test to identify ALL issues preventing form submission
require_once 'database.php';
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>🔍 Comprehensive Form Submission Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.5em; margin-bottom: 10px; }
        .content { padding: 30px; }
        .test-section { margin: 30px 0; padding: 20px; border-radius: 8px; border-left: 5px solid #ddd; }
        .test-section.pass { background: #e8f5e9; border-color: #4caf50; }
        .test-section.fail { background: #ffebee; border-color: #f44336; }
        .test-section.warn { background: #fff3e0; border-color: #ff9800; }
        .test-section.info { background: #e3f2fd; border-color: #2196f3; }
        h2 { color: #333; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .icon { font-size: 1.5em; }
        .pass .icon { color: #4caf50; }
        .fail .icon { color: #f44336; }
        .warn .icon { color: #ff9800; }
        .info .icon { color: #2196f3; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: 600; }
        tr:hover { background: #f9f9f9; }
        code { background: #f5f5f5; padding: 3px 8px; border-radius: 4px; font-family: 'Courier New', monospace; color: #d32f2f; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        pre { background: #263238; color: #aed581; padding: 15px; border-radius: 5px; overflow-x: auto; margin: 15px 0; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.85em; font-weight: 600; }
        .badge.success { background: #4caf50; color: white; }
        .badge.error { background: #f44336; color: white; }
        .badge.warning { background: #ff9800; color: white; }
        .action-box { background: #1a237e; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .action-box h3 { margin-bottom: 15px; color: #82b1ff; }
        .action-box ul { padding-left: 20px; }
        .action-box li { margin: 10px 0; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Comprehensive Form Submission Diagnostic</h1>
            <p>Complete analysis of event creation system</p>
        </div>
        
        <div class="content">
            
<?php
// TEST 1: Session Check
echo '<div class="test-section ' . (isset($_SESSION['user_id']) ? 'pass' : 'fail') . '">';
echo '<h2><span class="icon">🔐</span> Session Validation</h2>';
if (isset($_SESSION['user_id'])) {
    echo '<p class="success">✓ Session user_id is SET: ' . $_SESSION['user_id'] . '</p>';
    echo '<table>';
    echo '<tr><th>Session Variable</th><th>Value</th><th>Status</th></tr>';
    foreach (['user_id', 'name', 'email', 'organization', 'accessrole'] as $key) {
        $value = $_SESSION[$key] ?? 'NOT SET';
        $status = isset($_SESSION[$key]) ? '<span class="success">SET</span>' : '<span class="error">MISSING</span>';
        echo "<tr><td><code>\$_SESSION['$key']</code></td><td>$value</td><td>$status</td></tr>";
    }
    echo '</table>';
} else {
    echo '<p class="error">✗ CRITICAL: No user_id in session - User is NOT logged in!</p>';
    echo '<div class="action-box">';
    echo '<h3>🔧 How to Fix:</h3>';
    echo '<ul>';
    echo '<li>Log in to your account first</li>';
    echo '<li>Check your login script sets: <code>$_SESSION[\'user_id\'] = $row[\'account_id\'];</code></li>';
    echo '</ul>';
    echo '</div>';
}
echo '</div>';

// TEST 2: Database User Verification
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userCheck = $connection->prepare("SELECT account_id, fullname, email, accessrole FROM accountstbl WHERE account_id = ?");
    $userCheck->bind_param("i", $userId);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    echo '<div class="test-section ' . ($userResult->num_rows > 0 ? 'pass' : 'fail') . '">';
    echo '<h2><span class="icon">👤</span> User Database Verification</h2>';
    
    if ($userResult->num_rows > 0) {
        $userData = $userResult->fetch_assoc();
        echo '<p class="success">✓ User EXISTS in accountstbl</p>';
        echo '<table>';
        echo '<tr><th>Field</th><th>Value</th></tr>';
        foreach ($userData as $field => $value) {
            echo "<tr><td><strong>$field</strong></td><td>$value</td></tr>";
        }
        echo '</table>';
        echo '<p style="margin-top: 15px;"><span class="badge success">FOREIGN KEY OK</span> This user_id can be used as author in eventstbl</p>';
    } else {
        echo '<p class="error">✗ CRITICAL: user_id ' . $userId . ' does NOT exist in accountstbl!</p>';
        echo '<p class="error">This is why you get: "Cannot add or update a child row: foreign key constraint fails"</p>';
        echo '<div class="action-box">';
        echo '<h3>🔧 How to Fix:</h3>';
        echo '<ul>';
        echo '<li>Your login script is setting the wrong user_id</li>';
        echo '<li>Check the login script sets: <code>$_SESSION[\'user_id\'] = $row[\'account_id\'];</code></li>';
        echo '<li>NOT: <code>$_SESSION[\'user_id\'] = $row[\'user_id\'];</code> (if that column doesn\'t exist)</li>';
        echo '<li>Log out and log back in with correct credentials</li>';
        echo '</ul>';
        echo '</div>';
    }
    echo '</div>';
    $userCheck->close();
}

// TEST 3: Database Table Structure
echo '<div class="test-section info">';
echo '<h2><span class="icon">🗄️</span> Database Table Structure</h2>';

$tableCheck = $connection->query("SHOW CREATE TABLE eventstbl");
if ($tableCheck) {
    $createTable = $tableCheck->fetch_assoc();
    echo '<p><span class="badge success">TABLE EXISTS</span></p>';
    
    // Check nullable columns
    $columnsCheck = $connection->query("SHOW COLUMNS FROM eventstbl WHERE Field IN ('venue', 'barangay', 'city_municipality', 'area_no', 'event_type')");
    echo '<h3>Optional Field Configuration:</h3>';
    echo '<table>';
    echo '<tr><th>Column</th><th>Type</th><th>Null Allowed</th><th>Status</th></tr>';
    while ($col = $columnsCheck->fetch_assoc()) {
        $nullAllowed = $col['Null'] === 'YES';
        $status = $nullAllowed ? '<span class="success">✓ Correct</span>' : '<span class="error">✗ Should be NULL</span>';
        echo "<tr><td><code>{$col['Field']}</code></td><td>{$col['Type']}</td><td>" . ($nullAllowed ? 'YES' : 'NO') . "</td><td>$status</td></tr>";
    }
    echo '</table>';
}
echo '</div>';

// TEST 4: Foreign Key Constraint Check
echo '<div class="test-section info">';
echo '<h2><span class="icon">🔗</span> Foreign Key Constraints</h2>';
$fkCheck = $connection->query("
    SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'eventstbl'
    AND CONSTRAINT_NAME LIKE '%author%'
    AND TABLE_SCHEMA = DATABASE()
");

if ($fkCheck && $fkCheck->num_rows > 0) {
    echo '<table>';
    echo '<tr><th>Constraint</th><th>Column</th><th>References</th></tr>';
    while ($fk = $fkCheck->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
        echo "<td><code>eventstbl.{$fk['COLUMN_NAME']}</code></td>";
        echo "<td><code>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</code></td>";
        echo "</tr>";
    }
    echo '</table>';
    echo '<p><strong>Requirement:</strong> The value in <code>eventstbl.author</code> MUST exist in <code>accountstbl.account_id</code></p>';
} else {
    echo '<p class="warning">⚠️ No foreign key constraint found on author column</p>';
}
echo '</div>';

// TEST 5: Test INSERT Query
if (isset($_SESSION['user_id'])) {
    echo '<div class="test-section info">';
    echo '<h2><span class="icon">🧪</span> Test INSERT Query</h2>';
    echo '<p>This is what the INSERT would look like with current session:</p>';
    echo '<pre>';
    echo "INSERT INTO eventstbl (\n";
    echo "    author, organization, thumbnail, subject, program_type,\n";
    echo "    event_type, start_date, end_date, description, venue,\n";
    echo "    latitude, longitude, barangay, city_municipality, area_no,\n";
    echo "    eco_points, created_at, posted_at, is_approved,\n";
    echo "    participants, event_links\n";
    echo ") VALUES (\n";
    echo "    " . $_SESSION['user_id'] . ",  -- author (from session)\n";
    echo "    '" . ($_SESSION['organization'] ?? 'NULL') . "',  -- organization\n";
    echo "    'uploads/events/test.jpg',  -- thumbnail\n";
    echo "    'Test Event',  -- subject\n";
    echo "    'Event',  -- program_type\n";
    echo "    'Tree Planting',  -- event_type (can be NULL)\n";
    echo "    '2024-01-15',  -- start_date\n";
    echo "    '2024-01-15',  -- end_date\n";
    echo "    'Test Description',  -- description\n";
    echo "    'Test Venue',  -- venue (can be NULL)\n";
    echo "    14.5995,  -- latitude (can be NULL)\n";
    echo "    120.9842,  -- longitude (can be NULL)\n";
    echo "    'Test Barangay',  -- barangay (can be NULL)\n";
    echo "    'Test City',  -- city_municipality (can be NULL)\n";
    echo "    '1',  -- area_no (can be NULL)\n";
    echo "    10,  -- eco_points\n";
    echo "    NOW(),  -- created_at\n";
    echo "    NULL,  -- posted_at\n";
    echo "    'Pending',  -- is_approved\n";
    echo "    0,  -- participants\n";
    echo "    NULL  -- event_links\n";
    echo ");\n";
    echo '</pre>';
    
    // Try to verify this would work
    if (isset($_SESSION['user_id'])) {
        $testQuery = "SELECT COUNT(*) as can_insert FROM accountstbl WHERE account_id = ?";
        $testStmt = $connection->prepare($testQuery);
        $testStmt->bind_param("i", $_SESSION['user_id']);
        $testStmt->execute();
        $testResult = $testStmt->get_result()->fetch_assoc();
        
        if ($testResult['can_insert'] > 0) {
            echo '<p><span class="badge success">✓ This INSERT would SUCCEED</span></p>';
            echo '<p class="success">The foreign key constraint would be satisfied.</p>';
        } else {
            echo '<p><span class="badge error">✗ This INSERT would FAIL</span></p>';
            echo '<p class="error">Foreign key constraint would be violated because user_id ' . $_SESSION['user_id'] . ' doesn\'t exist in accountstbl.</p>';
        }
        $testStmt->close();
    }
    echo '</div>';
}

// TEST 6: Form Field Checklist
echo '<div class="test-section info">';
echo '<h2><span class="icon">📝</span> Required Form Fields Checklist</h2>';
echo '<p>The form in create_event.php must have these input fields with correct <code>name</code> attributes:</p>';
echo '<table>';
echo '<tr><th>Field Name</th><th>HTML Attribute</th><th>Required For</th></tr>';
$fields = [
    ['title', 'name="title"', 'Both Announcements and Events'],
    ['description', 'name="description"', 'Both Announcements and Events'],
    ['program_type', 'name="program_type"', 'Both (select: Announcement or Event)'],
    ['thumbnail', 'name="thumbnail"', 'Both (file upload)'],
    ['event_type', 'name="event_type"', 'Events only (dropdown)'],
    ['manual_event_type', 'name="manual_event_type"', 'Events with "Other" type'],
    ['start_date', 'name="start_date"', 'Events only'],
    ['end_date', 'name="end_date"', 'Events only'],
    ['venue', 'name="venue"', 'Events (optional)'],
    ['city', 'name="city"', 'Events (optional)'],
    ['barangay', 'name="barangay"', 'Events (optional)'],
    ['area_no', 'name="area_no"', 'Events (optional)'],
    ['latitude', 'name="latitude"', 'Optional (from map)'],
    ['longitude', 'name="longitude"', 'Optional (from map)'],
    ['eco_points', 'name="eco_points"', 'Optional'],
    ['link', 'name="link"', 'Optional (event links)'],
    ['special_notes', 'name="special_notes"', 'Optional']
];
foreach ($fields as $field) {
    echo "<tr><td><code>{$field[0]}</code></td><td><code>{$field[1]}</code></td><td>{$field[2]}</td></tr>";
}
echo '</table>';
echo '</div>';

// TEST 7: Browser Console Logging
echo '<div class="test-section warn">';
echo '<h2><span class="icon">🖥️</span> JavaScript Console Logging</h2>';
echo '<p>When you submit the form, you should see these console logs in your browser:</p>';
echo '<pre>';
echo "=== FORM SUBMISSION START ===\n";
echo "Program Type: Event\n";
echo "Event Type (dropdown): Tree Planting\n";
echo "Event Type (manual): \n";
echo "Title: [your title]\n";
echo "Description: [your description]\n";
echo "Venue: [your venue]\n";
echo "... (all field values)\n";
echo "=== VALIDATION PASSED - Form will submit ===\n";
echo "Form action: uploadevent.php\n";
echo "Form method: post\n";
echo '</pre>';
echo '<div class="action-box">';
echo '<h3>📋 How to Check:</h3>';
echo '<ul>';
echo '<li>Go to create_event.php</li>';
echo '<li>Press F12 to open Developer Tools</li>';
echo '<li>Go to "Console" tab</li>';
echo '<li>Fill the form and click "Create Post"</li>';
echo '<li>Look for the logs above</li>';
echo '</ul>';
echo '</div>';
echo '</div>';

// TEST 8: PHP Error Logging
echo '<div class="test-section warn">';
echo '<h2><span class="icon">📄</span> PHP Error Log Location</h2>';
echo '<p>Backend logs are written to the PHP error log file:</p>';
echo '<pre>C:\xampp\php\logs\php_error_log</pre>';
echo '<p>When form submits to uploadevent.php, you should see:</p>';
echo '<pre>';
echo "=== EVENT SUBMISSION START ===\n";
echo "Session user_id: [user_id]\n";
echo "POST data: Array([title] => ..., [program_type] => ...)\n";
echo "User verification successful\n";
echo "Event data prepared:\n";
echo "- author: [user_id] (type: integer)\n";
echo "- program_type: Event\n";
echo "...\n";
echo "Preparing SQL: INSERT INTO eventstbl ...\n";
echo "Executing INSERT statement...\n";
echo "INSERT successful! Event ID: [new_id]\n";
echo '</pre>';
echo '<div class="action-box">';
echo '<h3>📋 How to Check:</h3>';
echo '<ul>';
echo '<li>Open C:\xampp\php\logs\php_error_log in a text editor</li>';
echo '<li>Scroll to the bottom (newest logs)</li>';
echo '<li>Try submitting the form</li>';
echo '<li>Refresh/reopen the log file</li>';
echo '<li>Look for "=== EVENT SUBMISSION START ==="</li>';
echo '</ul>';
echo '</div>';
echo '</div>';

// FINAL SUMMARY
echo '<div class="test-section ' . (isset($_SESSION['user_id']) && $userResult->num_rows > 0 ? 'pass' : 'fail') . '">';
echo '<h2><span class="icon">🎯</span> Summary & Next Steps</h2>';

if (!isset($_SESSION['user_id'])) {
    echo '<h3 class="error">❌ CRITICAL ISSUE: No User Session</h3>';
    echo '<p>You must log in first before creating events.</p>';
} elseif ($userResult->num_rows == 0) {
    echo '<h3 class="error">❌ CRITICAL ISSUE: Invalid User ID</h3>';
    echo '<p>Your session user_id (' . $_SESSION['user_id'] . ') doesn\'t exist in the database.</p>';
    echo '<p>This causes the foreign key constraint error.</p>';
    echo '<div class="action-box">';
    echo '<h3>🔧 Fix Required:</h3>';
    echo '<ol>';
    echo '<li>Check your login script (adminloginprocess.php or similar)</li>';
    echo '<li>Make sure it uses: <code>$_SESSION[\'user_id\'] = $row[\'account_id\'];</code></li>';
    echo '<li>Log out and log back in</li>';
    echo '<li>Re-run this diagnostic</li>';
    echo '</ol>';
    echo '</div>';
} else {
    echo '<h3 class="success">✅ Session and Database Are Configured Correctly!</h3>';
    echo '<p>If the form still won\'t submit, the issue is likely:</p>';
    echo '<ul style="margin-left: 30px; margin-top: 15px;">';
    echo '<li><strong>JavaScript Error:</strong> Check browser console for errors</li>';
    echo '<li><strong>Missing Form Fields:</strong> Check all inputs have correct <code>name</code> attributes</li>';
    echo '<li><strong>Form Action Wrong:</strong> Check form action points to uploadevent.php</li>';
    echo '<li><strong>Browser Cache:</strong> Clear cache and try again</li>';
    echo '</ul>';
    
    echo '<div class="action-box" style="background: #1b5e20; margin-top: 20px;">';
    echo '<h3>🎯 Testing Procedure:</h3>';
    echo '<ol>';
    echo '<li>Go to <strong>create_event.php</strong></li>';
    echo '<li>Open <strong>Browser Console</strong> (F12 → Console)</li>';
    echo '<li>Fill out the form completely</li>';
    echo '<li>Click <strong>"Create Post"</strong></li>';
    echo '<li>Check console logs appear (=== FORM SUBMISSION START ===)</li>';
    echo '<li>Check PHP error log: C:\xampp\php\logs\php_error_log</li>';
    echo '<li>If no logs appear: JavaScript is preventing submission</li>';
    echo '<li>If logs appear but INSERT fails: Check the error message in PHP log</li>';
    echo '</ol>';
    echo '</div>';
}
echo '</div>';

?>

        </div>
    </div>
</body>
</html>
<?php $connection->close(); ?>
