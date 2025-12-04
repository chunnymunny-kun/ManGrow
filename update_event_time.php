<?php
include 'database.php';

$currentTime = date('Y-m-d H:i:s');
$endTime = date('Y-m-d H:i:s', strtotime('+2 hours'));

$query = 'UPDATE eventstbl SET start_date = ?, end_date = ? WHERE event_id = 14';
$stmt = $connection->prepare($query);
$stmt->bind_param('ss', $currentTime, $endTime);
$stmt->execute();

echo 'Event 14 updated to current time:' . PHP_EOL;
echo 'Start: ' . $currentTime . PHP_EOL;
echo 'End: ' . $endTime . PHP_EOL;
?>
