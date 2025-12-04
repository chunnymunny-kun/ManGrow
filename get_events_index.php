<?php
header('Content-Type: application/json');

require_once 'database.php';

try {
    $query = "SELECT 
                event_id, 
                subject, 
                description, 
                venue, 
                area_no, 
                start_date, 
                end_date, 
                organization 
              FROM eventstbl 
              WHERE is_approved = 'Approved'
              ORDER BY start_date ASC";
    
    $result = mysqli_query($connection, $query);
    
    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }
    
    $events = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
    
    echo json_encode($events);
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
