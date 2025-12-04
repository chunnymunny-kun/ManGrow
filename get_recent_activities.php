<?php
include 'database.php';

header('Content-Type: application/json');

$limit = 10;
$result = $db->query("
    SELECT action_type, areano_or_id, city_municipality, initiated_by, created_at 
    FROM activity_log 
    ORDER BY created_at DESC 
    LIMIT $limit
");

if ($result) {
    echo json_encode($result->fetch_all(MYSQLI_ASSOC));
} else {
    echo json_encode([]);
}
?>