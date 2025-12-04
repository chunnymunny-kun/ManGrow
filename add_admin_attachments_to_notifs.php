<?php
/**
 * Database Migration: Add Admin Attachments Support to report_notifstbl
 * 
 * This adds the ability for admins to attach documentation (images/videos)
 * when updating report statuses for transparency
 */

require_once 'database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - Admin Attachments</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; padding: 10px; background: #d5f4e6; border-left: 4px solid #27ae60; margin: 10px 0; }
        .error { color: #e74c3c; padding: 10px; background: #fadbd8; border-left: 4px solid #e74c3c; margin: 10px 0; }
        .info { color: #3498db; padding: 10px; background: #d6eaf8; border-left: 4px solid #3498db; margin: 10px 0; }
        h1 { color: #2c3e50; }
        code { background: #ecf0f1; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Database Migration: Admin Documentation Attachments</h1>
        <p>Adding support for admin attachments in report status updates...</p>
";

try {
    // Check if columns already exist
    $checkQuery = "SHOW COLUMNS FROM report_notifstbl LIKE 'admin_attachments'";
    $result = mysqli_query($connection, $checkQuery);
    
    if (mysqli_num_rows($result) > 0) {
        echo "<div class='info'>ℹ️ Column <code>admin_attachments</code> already exists. Skipping...</div>";
    } else {
        // Add admin_attachments column (JSON array of file paths)
        $sql1 = "ALTER TABLE report_notifstbl 
                 ADD COLUMN admin_attachments TEXT NULL 
                 COMMENT 'JSON array of admin attachment file paths (images/videos for documentation)'";
        
        if (mysqli_query($connection, $sql1)) {
            echo "<div class='success'>✅ Added <code>admin_attachments</code> column to report_notifstbl</div>";
        } else {
            throw new Exception("Failed to add admin_attachments column: " . mysqli_error($connection));
        }
    }
    
    // Check if attachment_count column exists
    $checkQuery2 = "SHOW COLUMNS FROM report_notifstbl LIKE 'attachment_count'";
    $result2 = mysqli_query($connection, $checkQuery2);
    
    if (mysqli_num_rows($result2) > 0) {
        echo "<div class='info'>ℹ️ Column <code>attachment_count</code> already exists. Skipping...</div>";
    } else {
        // Add attachment_count column for quick reference
        $sql2 = "ALTER TABLE report_notifstbl 
                 ADD COLUMN attachment_count INT DEFAULT 0 
                 COMMENT 'Number of admin attachments for this notification'";
        
        if (mysqli_query($connection, $sql2)) {
            echo "<div class='success'>✅ Added <code>attachment_count</code> column to report_notifstbl</div>";
        } else {
            throw new Exception("Failed to add attachment_count column: " . mysqli_error($connection));
        }
    }
    
    // Create uploads directory structure if it doesn't exist
    $uploadDirs = [
        'uploads/report_admin_attachments',
        'uploads/report_admin_attachments/investigating',
        'uploads/report_admin_attachments/action_taken',
        'uploads/report_admin_attachments/resolved'
    ];
    
    foreach ($uploadDirs as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0777, true)) {
                echo "<div class='success'>✅ Created directory: <code>$dir</code></div>";
            } else {
                echo "<div class='error'>❌ Failed to create directory: <code>$dir</code></div>";
            }
        } else {
            echo "<div class='info'>ℹ️ Directory already exists: <code>$dir</code></div>";
        }
    }
    
    // Create .htaccess for security
    $htaccess = "uploads/report_admin_attachments/.htaccess";
    if (!file_exists($htaccess)) {
        $htaccessContent = "# Allow only specific file types\n<FilesMatch \"\\.(jpg|jpeg|png|gif|webp|mp4|avi|mov|wmv)$\">\n    Order Allow,Deny\n    Allow from all\n</FilesMatch>\n\n# Deny everything else\nOrder Deny,Allow\nDeny from all\n";
        file_put_contents($htaccess, $htaccessContent);
        echo "<div class='success'>✅ Created security .htaccess file</div>";
    }
    
    echo "<div class='success'><h2>✅ Migration Completed Successfully!</h2></div>";
    echo "<div class='info'><h3>📊 Updated Table Structure:</h3>
    <ul>
        <li><strong>admin_attachments</strong> (TEXT): JSON array of file paths</li>
        <li><strong>attachment_count</strong> (INT): Count of attachments</li>
    </ul>
    </div>";
    
    echo "<div class='info'><h3>💾 Storage Structure Created:</h3>
    <ul>
        <li>uploads/report_admin_attachments/investigating/</li>
        <li>uploads/report_admin_attachments/action_taken/</li>
        <li>uploads/report_admin_attachments/resolved/</li>
    </ul>
    </div>";
    
    echo "<p><strong>Next Steps:</strong></p>
    <ol>
        <li>Update adminreportpage.php status modal to include file upload</li>
        <li>Update save_report_notification.php to handle file uploads</li>
        <li>Update admin view modal to display attachments</li>
        <li>Update reportspage.php to show admin attachments to users</li>
    </ol>";
    
} catch (Exception $e) {
    echo "<div class='error'><h2>❌ Migration Failed</h2><p>" . $e->getMessage() . "</p></div>";
}

echo "</div></body></html>";

mysqli_close($connection);
?>
