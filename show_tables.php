<?php
require_once 'database.php';

echo "All tables in the database:\n";
$result = mysqli_query($connection, 'SHOW TABLES');
while($row = mysqli_fetch_array($result)) {
    echo $row[0] . "\n";
}
?>
