<?php
// Run this file once to set up the user_verification table
include 'database.php';

$sql = file_get_contents('setup_verification_table.sql');

// Split the SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($connection->query($statement)) {
            echo "✅ Executed: " . substr($statement, 0, 50) . "...\n<br>";
        } else {
            echo "❌ Error: " . $connection->error . "\n<br>";
            echo "Statement: " . $statement . "\n<br><br>";
        }
    }
}

echo "<br>✅ Database setup complete! You can now use the registration system.";
?>
