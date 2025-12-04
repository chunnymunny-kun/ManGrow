<?php
include 'database.php';

if (isset($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
    
    $query = "SELECT disapproval_note FROM eventstbl WHERE event_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode([
            'note' => $row['disapproval_note'] ? htmlspecialchars_decode($row['disapproval_note']) : null
        ]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['note' => null]);
?>