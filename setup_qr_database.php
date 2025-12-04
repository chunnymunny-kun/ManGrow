<?php
include 'database.php';

echo "Setting up Event QR System database tables...\n\n";

// Read and execute the SQL file
$sqlContent = file_get_contents('create_event_qr_system.sql');
$queries = explode(';', $sqlContent);

$successCount = 0;
$errorCount = 0;

foreach ($queries as $query) {
    $query = trim($query);
    if (empty($query)) continue;
    
    echo "Executing: " . substr($query, 0, 50) . "...\n";
    
    if ($connection->query($query)) {
        echo "✓ Success\n";
        $successCount++;
    } else {
        echo "✗ Error: " . $connection->error . "\n";
        $errorCount++;
    }
    echo "\n";
}

echo "Setup completed!\n";
echo "Successful queries: $successCount\n";
echo "Failed queries: $errorCount\n";

if ($errorCount === 0) {
    echo "\n🎉 All database tables created successfully!\n";
    echo "You can now test the QR system.\n";
} else {
    echo "\n⚠️ Some queries failed. Please check the errors above.\n";
}
?>
