<?php
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['status']) && isset($data['msg'])) {
    $_SESSION['response'] = [
        'status' => $data['status'],
        'msg' => $data['msg']
    ];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
}
?>