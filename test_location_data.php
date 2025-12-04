<?php
// Quick test to see what's in the location tables
require_once 'database.php';

echo "<h2>Cities/Municipalities in Database:</h2>";
$cityQuery = "SELECT * FROM citymunicipalitytbl LIMIT 20";
$cityResult = mysqli_query($connection, $cityQuery);

echo "<table border='1' style='border-collapse: collapse; margin: 20px;'>";
echo "<tr><th>ID</th><th>City/Municipality Name</th></tr>";
while ($row = mysqli_fetch_assoc($cityResult)) {
    echo "<tr>";
    foreach ($row as $key => $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<h2>Sample Barangays (Abucay & Balanga):</h2>";
$barangayQuery = "SELECT * FROM barangaytbl 
                  WHERE city_municipality LIKE '%Abucay%' 
                  OR city_municipality LIKE '%Balanga%' 
                  ORDER BY city_municipality, barangay 
                  LIMIT 30";
$barangayResult = mysqli_query($connection, $barangayQuery);

echo "<table border='1' style='border-collapse: collapse; margin: 20px;'>";
echo "<tr><th>Barangay</th><th>City/Municipality</th></tr>";
while ($row = mysqli_fetch_assoc($barangayResult)) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['barangay'] ?? '') . "</td>";
    echo "<td>" . htmlspecialchars($row['city_municipality'] ?? '') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Total Counts:</h2>";
$cityCount = mysqli_query($connection, "SELECT COUNT(*) as count FROM citymunicipalitytbl");
$cityCountRow = mysqli_fetch_assoc($cityCount);
echo "<p>Total cities: " . $cityCountRow['count'] . "</p>";

$barangayCount = mysqli_query($connection, "SELECT COUNT(*) as count FROM barangaytbl");
$barangayCountRow = mysqli_fetch_assoc($barangayCount);
echo "<p>Total barangays: " . $barangayCountRow['count'] . "</p>";
?>
