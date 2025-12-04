<?php
// Test to verify readonly inputs submit values
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Specific Field Tests:</h3>";
    echo "venue: " . (isset($_POST['venue']) ? "'{$_POST['venue']}'" : "NOT SET") . "<br>";
    echo "city: " . (isset($_POST['city']) ? "'{$_POST['city']}'" : "NOT SET") . "<br>";
    echo "barangay: " . (isset($_POST['barangay']) ? "'{$_POST['barangay']}'" : "NOT SET") . "<br>";
    
    echo "<br><a href='test_readonly_submission.php'>Test Again</a>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Readonly Input Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .test-container { max-width: 600px; margin: 0 auto; }
        input { width: 100%; padding: 8px; margin: 5px 0; }
        .readonly { background: #f0f0f0; }
        button { padding: 10px 20px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="test-container">
        <h2>Test: Readonly vs Disabled Input Submission</h2>
        
        <form method="POST">
            <h3>Readonly Inputs (SHOULD SUBMIT):</h3>
            <label>Venue (readonly with value):</label>
            <input type="text" name="venue" value="Test Venue" readonly class="readonly">
            
            <label>City (readonly with value):</label>
            <input type="text" name="city" value="Test City" readonly class="readonly">
            
            <label>Barangay (readonly with value):</label>
            <input type="text" name="barangay" value="Test Barangay" readonly class="readonly">
            
            <h3>Regular Input (for comparison):</h3>
            <label>Regular Input:</label>
            <input type="text" name="regular" value="Regular Value">
            
            <h3>Disabled Input (WILL NOT SUBMIT):</h3>
            <label>Disabled Input:</label>
            <input type="text" name="disabled_test" value="This won't submit" disabled class="readonly">
            
            <button type="submit">Submit Test</button>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196F3;">
            <strong>Expected Results:</strong><br>
            ✅ venue, city, barangay should all submit (readonly allows submission)<br>
            ✅ regular should submit<br>
            ❌ disabled_test should NOT submit (disabled blocks submission)
        </div>
    </div>
</body>
</html>
