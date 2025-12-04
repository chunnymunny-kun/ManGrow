<?php
header('Content-Type: application/json');

// Include your database connection file
require_once 'database.php';

// Initialize response array
$response = [];

try {
    // Check if connection is established
    if (!$connection) {
        throw new Exception("Database connection failed");
    }

    // Query to get all activity logs with proper ordering:
    // 1. Latest activities first (by timestamp DESC)
    // 2. Within same timestamp, area actions before save_changes
    $query = "SELECT * FROM activity_logtbl 
                ORDER BY created_at DESC, 
                        CASE 
                            WHEN action_type = 'save_changes' THEN 0 
                            ELSE 1 
                        END ASC;";
    $result = mysqli_query($connection, $query);

    if (!$result) {
        throw new Exception(mysqli_error($connection));
    }

    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }

    $response = [
        'status' => 'success',
        'data' => $logs
    ];

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Failed to fetch activity logs',
        'error' => $e->getMessage()
    ];
} finally {
    // Close connection if it exists
    if (isset($connection)) {
        mysqli_close($connection);
    }
}

echo json_encode($response);
?>