<?php
/**
 * Get Transaction Details API
 * Returns all items for a given transaction
 * Used by admin modal to display multi-item transactions
 */

session_start();
require_once 'database.php';

header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['accessrole']) || 
    ($_SESSION['accessrole'] != 'Administrator' && 
     $_SESSION['accessrole'] != 'Barangay Official' &&
     $_SESSION['accessrole'] != 'Representative')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit();
}

$transactionId = intval($_GET['id']);

try {
    // Get transaction basic info
    $transactionQuery = "SELECT 
                            t.transaction_id,
                            t.reference_number,
                            t.points_used as total_points,
                            t.status,
                            t.transaction_date,
                            u.fullname as user_name,
                            u.personal_email as user_email
                         FROM ecoshop_transactions t
                         JOIN accountstbl u ON t.user_id = u.account_id
                         WHERE t.transaction_id = ?";
    
    $stmt = $connection->prepare($transactionQuery);
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $transactionResult = $stmt->get_result();
    
    if ($transactionResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit();
    }
    
    $transaction = $transactionResult->fetch_assoc();
    $stmt->close();
    
    // Get all items for this transaction
    $itemsQuery = "SELECT 
                    ti.quantity,
                    ti.points_used as subtotal,
                    i.item_name,
                    i.item_description,
                    i.points_required as unit_price,
                    i.image_path
                  FROM ecoshop_transaction_items ti
                  JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
                  WHERE ti.transaction_id = ?
                  ORDER BY i.item_name";
    
    $stmt = $connection->prepare($itemsQuery);
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $itemsResult = $stmt->get_result();
    
    $items = [];
    while ($row = $itemsResult->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    
    // Combine transaction and items
    $transaction['items'] = $items;
    $transaction['items_count'] = count($items);
    
    echo json_encode([
        'success' => true,
        'transaction' => $transaction
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching transaction details: ' . $e->getMessage()
    ]);
    error_log("get_transaction_details error: " . $e->getMessage());
}

$connection->close();
?>
