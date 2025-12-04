<?php
require_once 'database.php';

$result = $connection->query('DESCRIBE eventstbl');

echo "EVENTSTBL STRUCTURE:\n";
echo str_repeat("=", 100) . "\n";
printf("%-30s | %-20s | %-5s | %-10s | %-10s\n", "Field", "Type", "Null", "Key", "Default");
echo str_repeat("=", 100) . "\n";

while($row = $result->fetch_assoc()) {
    printf("%-30s | %-20s | %-5s | %-10s | %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Key'], 
        $row['Default'] ?? 'NULL'
    );
}

echo str_repeat("=", 100) . "\n";
$connection->close();
