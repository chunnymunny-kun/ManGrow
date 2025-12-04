<?php
session_start();
require_once 'database.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if(!isset($_GET['report_id']) || !isset($_GET['type'])) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$report_id = (int)$_GET['report_id'];
$report_type = $_GET['type'];
$response = [];

try {
    // First verify this report belongs to the user
    $verify_query = "SELECT 1 FROM userreportstbl 
                    WHERE report_id = ? AND account_id = ?";
    $stmt = $connection->prepare($verify_query);
    $stmt->bind_param("ii", $report_id, $_SESSION['user_id']);
    $stmt->execute();
    $verify_result = $stmt->get_result();
    
    if($verify_result->num_rows === 0) {
        echo json_encode(['error' => 'Report not found or unauthorized']);
        exit;
    }
    $stmt->close();
    
    // Get report details based on type
    if($report_type === 'Mangrove Data Report') {
        $query = "SELECT 
                    m.*,
                    'Mangrove Data Report' as report_type
                  FROM mangrovereporttbl m
                  WHERE m.report_id = ?";
    } else {
        $query = "SELECT 
                    i.*,
                    'Illegal Activity Report' as report_type
                  FROM illegalreportstbl i
                  WHERE i.report_id = ?";
    }
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['report'] = $result->fetch_assoc();
    $stmt->close();
    
    // Get unique notifications for this report (only one per action_type)
    $notif_query = "SELECT 
                    n.*, 
                    CASE 
                        WHEN n.notifier_type = 'adminaccountstbl' THEN a.admin_name
                        WHEN n.notifier_type = 'accountstbl' THEN u.fullname
                        ELSE 'Anonymous'
                    END as notifier_name
                FROM report_notifstbl n
                LEFT JOIN adminaccountstbl a ON n.notified_by = a.admin_id AND n.notifier_type = 'adminaccountstbl'
                LEFT JOIN accountstbl u ON n.notified_by = u.account_id AND n.notifier_type = 'accountstbl'
                WHERE n.report_id = ? 
                AND n.report_type = ?
                ORDER BY n.notif_date DESC";
    
    $stmt = $connection->prepare($notif_query);
    $stmt->bind_param("is", $report_id, $report_type);
    $stmt->execute();
    $notif_result = $stmt->get_result();
    $response['notifications'] = $notif_result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Count all notifications for the bell icon
    $count_query = "SELECT COUNT(*) as count FROM report_notifstbl WHERE report_id = ?";
    $stmt = $connection->prepare($count_query);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $response['notification_count'] = $count_result->fetch_assoc()['count'];
    $stmt->close();
    
    echo json_encode($response);
    
} catch(Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}