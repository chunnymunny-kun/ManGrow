<?php
session_start();
include 'database.php';

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['actions']) || !is_array($data['actions'])) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Invalid actions']));
}

try {
    $stmt = $connection->prepare("
        INSERT INTO activities (
            user_id,
            userrole_type,
            action_type,
            city_municipality,
            details,
            coordinates,
            timestamp
        ) VALUES (?, ?, ?, ?, ?, ST_GeomFromGeoJSON(?), ?)
    ");
    
    $successCount = 0;
    foreach ($data['actions'] as $action) {
        try {
            $stmt->bind_param(
                "issssss",
                $data['user_id'],
                $data['userrole_type'],
                $action['action_type'],
                $action['city_municipality'] ?? null,
                $action['details'] ?? null,
                $action['coordinates'],
                $action['timestamp'] ?? date('Y-m-d H:i:s')
            );
            if ($stmt->execute()) $successCount++;
        } catch (Exception $e) {
            error_log("Batch log error: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'count' => $successCount
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>