<?php
include 'database.php';

$result = $connection->query("UPDATE eventstbl SET completion_status = 'approved' WHERE event_id = 1");
if ($result) {
    echo "Event 1 approved for testing\n";
} else {
    echo "Failed to approve event 1\n";
}
?>
