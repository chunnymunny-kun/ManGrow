<?php
/**
 * AJAX Transaction Monitor
 * Returns new transactions since last check
 * Supports both admin and user monitoring
 */

session_start();
require_once 'database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['accessrole'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$lastTransactionId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
$isAdmin = isset($_SESSION['accessrole']) && 
           ($_SESSION['accessrole'] === 'Administrator' || 
            $_SESSION['accessrole'] === 'Barangay Official' || 
            $_SESSION['accessrole'] === 'Representative');

try {
    if ($isAdmin) {
        // Admin: Get all new pending transactions
        $query = "SELECT 
                    t.transaction_id,
                    t.reference_number,
                    t.points_used as total_points,
                    t.transaction_date,
                    t.status,
                    u.fullname as user_name,
                    u.personal_email as user_email,
                    COUNT(ti.transaction_item_id) as items_count
                  FROM ecoshop_transactions t
                  JOIN accountstbl u ON t.user_id = u.account_id
                  LEFT JOIN ecoshop_transaction_items ti ON t.transaction_id = ti.transaction_id
                  WHERE t.transaction_id > ? AND t.status = 'pending'
                  GROUP BY t.transaction_id
                  ORDER BY t.transaction_date DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param("i", $lastTransactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $newTransactions = [];
        while ($row = $result->fetch_assoc()) {
            // Get all items for this transaction
            $itemsQuery = "SELECT 
                            ti.quantity,
                            ti.points_used,
                            i.item_name
                          FROM ecoshop_transaction_items ti
                          JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
                          WHERE ti.transaction_id = ?";
            $itemsStmt = $connection->prepare($itemsQuery);
            $itemsStmt->bind_param("i", $row['transaction_id']);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $items = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $items[] = $itemRow;
            }
            $itemsStmt->close();
            
            $row['items'] = $items;
            $newTransactions[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'new_count' => count($newTransactions),
            'transactions' => $newTransactions,
            'is_admin' => true
        ]);
        
    } else {
        // User: Get status updates for their own transactions
        $userId = $_SESSION['user_id'];
        
        $query = "SELECT 
                    t.transaction_id,
                    t.reference_number,
                    t.points_used as total_points,
                    t.status,
                    t.transaction_date,
                    t.approval_date,
                    COUNT(ti.transaction_item_id) as items_count
                  FROM ecoshop_transactions t
                  LEFT JOIN ecoshop_transaction_items ti ON t.transaction_id = ti.transaction_id
                  WHERE t.user_id = ? AND t.transaction_id > ?
                  GROUP BY t.transaction_id
                  ORDER BY t.transaction_date DESC";
        
        $stmt = $connection->prepare($query);
        $stmt->bind_param("ii", $userId, $lastTransactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $updates = [];
        while ($row = $result->fetch_assoc()) {
            // Get items for this transaction
            $itemsQuery = "SELECT 
                            ti.quantity,
                            ti.points_used,
                            i.item_name
                          FROM ecoshop_transaction_items ti
                          JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
                          WHERE ti.transaction_id = ?";
            $itemsStmt = $connection->prepare($itemsQuery);
            $itemsStmt->bind_param("i", $row['transaction_id']);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $items = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                $items[] = $itemRow;
            }
            $itemsStmt->close();
            
            $row['items'] = $items;
            $updates[] = $row;
        }
        
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'updates_count' => count($updates),
            'updates' => $updates,
            'is_admin' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking transactions: ' . $e->getMessage()
    ]);
    error_log("Transaction monitor error: " . $e->getMessage());
}

$connection->close();
?>
