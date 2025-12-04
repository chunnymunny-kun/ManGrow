<?php
// Script to extract and verify all form field names from create_event.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Form Field Verification</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .match { color: green; font-weight: bold; }
        .missing { color: red; font-weight: bold; }
        .section { margin: 30px 0; padding: 15px; background: #e8f5e9; border-left: 4px solid #4CAF50; }
        h2 { color: #2e7d32; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Form Field Verification Report</h1>
        
        <div class="section">
            <h2>Form Fields Expected by uploadevent.php</h2>
            <p>These are the fields that uploadevent.php expects to receive via POST:</p>
        </div>
        
        <table>
            <tr>
                <th>Field Name (POST)</th>
                <th>Usage in uploadevent.php</th>
                <th>Required?</th>
                <th>Database Column</th>
            </tr>
            <tr>
                <td><code>title</code></td>
                <td>$_POST['title'] → subject</td>
                <td>Yes</td>
                <td>subject</td>
            </tr>
            <tr>
                <td><code>description</code></td>
                <td>$_POST['description']</td>
                <td>Yes</td>
                <td>description</td>
            </tr>
            <tr>
                <td><code>program_type</code></td>
                <td>$_POST['program_type']</td>
                <td>Yes</td>
                <td>program_type</td>
            </tr>
            <tr>
                <td><code>event_type</code></td>
                <td>$getNullableValue('event_type')</td>
                <td>No (NULL allowed)</td>
                <td>event_type</td>
            </tr>
            <tr>
                <td><code>manual_event_type</code></td>
                <td>Fallback if event_type empty</td>
                <td>No</td>
                <td>event_type</td>
            </tr>
            <tr>
                <td><code>start_date</code></td>
                <td>$getNullableValue('start_date')</td>
                <td>No (NULL for Announcements)</td>
                <td>start_date</td>
            </tr>
            <tr>
                <td><code>end_date</code></td>
                <td>$getNullableValue('end_date')</td>
                <td>No (NULL for Announcements)</td>
                <td>end_date</td>
            </tr>
            <tr>
                <td><code>venue</code></td>
                <td>$getNullableValue('venue')</td>
                <td>No (NULL allowed)</td>
                <td>venue</td>
            </tr>
            <tr>
                <td><code>city</code></td>
                <td>$getNullableValue('city') → city_municipality</td>
                <td>No (NULL allowed)</td>
                <td>city_municipality</td>
            </tr>
            <tr>
                <td><code>barangay</code></td>
                <td>$getNullableValue('barangay')</td>
                <td>No (NULL allowed)</td>
                <td>barangay</td>
            </tr>
            <tr>
                <td><code>area_no</code></td>
                <td>$getNullableValue('area_no')</td>
                <td>No (NULL allowed)</td>
                <td>area_no</td>
            </tr>
            <tr>
                <td><code>latitude</code></td>
                <td>floatval($_POST['latitude'])</td>
                <td>No (0 if empty)</td>
                <td>latitude</td>
            </tr>
            <tr>
                <td><code>longitude</code></td>
                <td>floatval($_POST['longitude'])</td>
                <td>No (0 if empty)</td>
                <td>longitude</td>
            </tr>
            <tr>
                <td><code>eco_points</code></td>
                <td>intval($_POST['eco_points'])</td>
                <td>No (0 if empty)</td>
                <td>eco_points</td>
            </tr>
            <tr>
                <td><code>link</code></td>
                <td>Converted to JSON array</td>
                <td>No</td>
                <td>event_links</td>
            </tr>
            <tr>
                <td><code>special_notes</code></td>
                <td>$getNullableValue('special_notes')</td>
                <td>No</td>
                <td>special_notes</td>
            </tr>
            <tr>
                <td><code>thumbnail</code> (FILE)</td>
                <td>$_FILES['thumbnail']</td>
                <td>Yes</td>
                <td>thumbnail</td>
            </tr>
        </table>

        <div class="section">
            <h2>Session Variables Required</h2>
            <p>These session variables MUST be set for the form to work:</p>
        </div>

        <table>
            <tr>
                <th>Session Variable</th>
                <th>Usage</th>
                <th>Critical?</th>
            </tr>
            <tr>
                <td><code>$_SESSION['user_id']</code></td>
                <td>Used as 'author' - MUST exist in accountstbl.account_id</td>
                <td class="missing">CRITICAL - Foreign Key</td>
            </tr>
            <tr>
                <td><code>$_SESSION['organization']</code></td>
                <td>Stored in 'organization' field</td>
                <td>Optional</td>
            </tr>
            <tr>
                <td><code>$_SESSION['name']</code></td>
                <td>Used for notification messages</td>
                <td>Optional</td>
            </tr>
            <tr>
                <td><code>$_SESSION['accessrole']</code></td>
                <td>Determines auto-approval for Barangay Officials</td>
                <td>Optional</td>
            </tr>
        </table>

        <div class="section">
            <h2>🔍 How to Verify Form Fields in create_event.php</h2>
            <p>Open create_event.php and search for these patterns:</p>
            <ul>
                <li><code>name="title"</code> - Should be in the title input</li>
                <li><code>name="description"</code> - Should be in the description textarea</li>
                <li><code>name="program_type"</code> - Should be in the program type select</li>
                <li><code>name="event_type"</code> - Should be in the event type select</li>
                <li><code>name="manual_event_type"</code> - Should be in the manual event type input</li>
                <li><code>name="venue"</code> - Should be in the venue input</li>
                <li><code>name="city"</code> - Should be in the city input</li>
                <li><code>name="barangay"</code> - Should be in the barangay input</li>
                <li><code>name="area_no"</code> - Should be in the area number input</li>
            </ul>
        </div>

        <div class="section">
            <h2>⚠️ Most Common Issue: Foreign Key Constraint</h2>
            <p><strong>Error:</strong> Cannot add or update a child row: foreign key constraint fails</p>
            <p><strong>Root Cause:</strong> <code>$_SESSION['user_id']</code> does not exist in accountstbl.account_id</p>
            <p><strong>How to Fix:</strong></p>
            <ol>
                <li>Check your login script (adminloginprocess.php or similar)</li>
                <li>Make sure it sets: <code>$_SESSION['user_id'] = $row['account_id'];</code></li>
                <li>NOT: <code>$_SESSION['user_id'] = $row['user_id'];</code> (if that column doesn't exist)</li>
                <li>The session value MUST match the primary key in accountstbl</li>
            </ol>
        </div>

        <div class="section">
            <h2>🐛 Debugging Steps</h2>
            <ol>
                <li><strong>Check Browser Console (F12):</strong> Look for console.log output when submitting form</li>
                <li><strong>Check PHP Error Log:</strong> C:\xampp\php\logs\php_error_log - Look for "=== EVENT SUBMISSION START ==="</li>
                <li><strong>Run debug_event_submission.php:</strong> Visit http://localhost:3000/debug_event_submission.php</li>
                <li><strong>Check Network Tab:</strong> F12 → Network → Look for POST to uploadevent.php</li>
            </ol>
        </div>

        <div class="section">
            <h2>✅ Next Steps</h2>
            <ol>
                <li>Try to submit the form in create_event.php</li>
                <li>Open Browser Console (F12) and check for logs</li>
                <li>Check PHP error log for detailed backend logs</li>
                <li>If you see "Foreign key constraint" error, check your login script</li>
                <li>If form doesn't submit at all, check JavaScript console for errors</li>
            </ol>
        </div>
    </div>
</body>
</html>
