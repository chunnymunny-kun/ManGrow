<?php
require_once 'database.php';

echo "Fixing eventstbl schema to allow NULL values...\n";
echo str_repeat("=", 60) . "\n";

$queries = [
    "ALTER TABLE eventstbl MODIFY COLUMN venue VARCHAR(255) NULL DEFAULT NULL",
    "ALTER TABLE eventstbl MODIFY COLUMN barangay VARCHAR(100) NULL DEFAULT NULL",
    "ALTER TABLE eventstbl MODIFY COLUMN city_municipality VARCHAR(100) NULL DEFAULT NULL",
    "ALTER TABLE eventstbl MODIFY COLUMN area_no VARCHAR(20) NULL DEFAULT NULL",
    "ALTER TABLE eventstbl MODIFY COLUMN event_type VARCHAR(255) NULL DEFAULT NULL"
];

foreach ($queries as $query) {
    if ($connection->query($query)) {
        echo "✓ " . substr($query, 0, 50) . "...\n";
    } else {
        echo "✗ Error: " . $connection->error . "\n";
        exit(1);
    }
}

echo str_repeat("=", 60) . "\n";
echo "Schema updated successfully!\n\n";

// Verify changes
echo "Verifying changes:\n";
echo str_repeat("=", 60) . "\n";
$result = $connection->query("DESCRIBE eventstbl");
$fields = ['venue', 'barangay', 'city_municipality', 'area_no', 'event_type'];

while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], $fields)) {
        printf("%-25s | Null: %-3s | Default: %s\n", 
            $row['Field'], 
            $row['Null'], 
            $row['Default'] ?? 'NULL'
        );
    }
}

$connection->close();
echo str_repeat("=", 60) . "\n";
echo "Done!\n";
