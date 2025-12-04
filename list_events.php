<?php
include 'database.php';

echo "Events in database:\n";
$result = $connection->query('SELECT event_id, subject, completion_status FROM eventstbl LIMIT 5');

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Event {$row['event_id']}: {$row['subject']} (Status: {$row['completion_status']})\n";
    }
} else {
    echo "No events found or error querying database.\n";
}
?>
