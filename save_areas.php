<?php
header('Content-Type: application/json');

// Create backup directory if needed
if (!file_exists('backups')) {
    mkdir('backups', 0755, true);
}

// Get and validate input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
}

// Create backup
$backupFile = 'backups/mangroveareas_' . date('Ymd_His') . '.json';
if (file_exists('mangroveareas.json')) {
    copy('mangroveareas.json', $backupFile);
}

// Save new data
if (file_put_contents('mangroveareas.json', json_encode($data, JSON_PRETTY_PRINT))) {
    // Automatically update barangay profiles after saving areas
    try {
        $updateResponse = file_get_contents('http://localhost/project/update_barangay_profiles.php', false, 
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => json_encode([])
                ]
            ])
        );
        
        if ($updateResponse) {
            $updateResult = json_decode($updateResponse, true);
            if (!$updateResult['success']) {
                error_log('Failed to update barangay profiles: ' . $updateResult['message']);
            }
        }
    } catch (Exception $e) {
        error_log('Error updating barangay profiles: ' . $e->getMessage());
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}
?>