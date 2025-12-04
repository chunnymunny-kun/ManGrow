<?php
// redeem_item.php - Handle user redemption requests
session_start();
include 'database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to redeem items']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $item_id = intval($_POST['item_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Validate inputs
    if ($item_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item or quantity']);
        exit();
    }
    
    // Get user's current points and item details
    $userQuery = "SELECT eco_points FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($userQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $userResult->fetch_assoc();
    
    // Get item details
    $itemQuery = "SELECT * FROM ecoshop_itemstbl WHERE item_id = ? AND is_available = 1";
    $stmt = $connection->prepare($itemQuery);
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $itemResult = $stmt->get_result();
    
    if ($itemResult->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Item not found or not available']);
        exit();
    }
    
    $item = $itemResult->fetch_assoc();
    
    // Calculate total points needed
    $totalPointsNeeded = $item['points_required'] * $quantity;
    
    // Check if user has enough points
    if ($user['eco_points'] < $totalPointsNeeded) {
        echo json_encode([
            'success' => false, 
            'message' => "Insufficient eco points. You need {$totalPointsNeeded} points but only have {$user['eco_points']} points."
        ]);
        exit();
    }
    
    // Check stock availability
    if ($item['stock_quantity'] !== null && $item['stock_quantity'] < $quantity) {
        $available = $item['stock_quantity'];
        echo json_encode([
            'success' => false, 
            'message' => "Insufficient stock. Only {$available} items available."
        ]);
        exit();
    }
    
    // Start transaction
    $connection->begin_transaction();
    try {
        // Generate reference number
        $referenceNumber = 'REF-' . date('Ymd') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        
        // Create redemption transaction record (pending approval - no points deducted yet)
        $insertTransactionQuery = "INSERT INTO ecoshop_transactions 
                                  (user_id, item_id, points_used, quantity, status, transaction_date, reference_number) 
                                  VALUES (?, ?, ?, ?, 'approved', NOW(), ?)";
        $stmt = $connection->prepare($insertTransactionQuery);
        $stmt->bind_param("iiiis", $user_id, $item_id, $totalPointsNeeded, $quantity, $referenceNumber);
        $stmt->execute();
        $transaction_id = $connection->insert_id;

        // NEW: Also insert into transaction_items table for consistency
        $insertItemSQL = "INSERT INTO ecoshop_transaction_items 
                          (transaction_id, item_id, quantity, points_used) 
                          VALUES (?, ?, ?, ?)";
        $itemsStmt = $connection->prepare($insertItemSQL);
        $itemsStmt->bind_param("iiii", $transaction_id, $item_id, $quantity, $totalPointsNeeded);
        $itemsStmt->execute();
        $itemsStmt->close();

        // Deduct points immediately (status is 'approved')
        $updatePointsQuery = "UPDATE accountstbl SET eco_points = eco_points - ? WHERE account_id = ?";
        $pointsStmt = $connection->prepare($updatePointsQuery);
        $pointsStmt->bind_param("ii", $totalPointsNeeded, $user_id);
        $pointsStmt->execute();
        $pointsStmt->close();
        
        // Update stock immediately
        if ($item['stock_quantity'] !== null) {
            $decrementStockQuery = "UPDATE ecoshop_itemstbl 
                                   SET stock_quantity = stock_quantity - ? 
                                   WHERE item_id = ?";
            $stmt = $connection->prepare($decrementStockQuery);
            $stmt->bind_param("ii", $quantity, $item_id);
            $stmt->execute();
        }

        $connection->commit();
        
        // Get updated stock quantity
        $newStockQuery = "SELECT stock_quantity FROM ecoshop_itemstbl WHERE item_id = ?";
        $stmt = $connection->prepare($newStockQuery);
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $stockResult = $stmt->get_result();
        $newStock = $stockResult->fetch_assoc()['stock_quantity'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Redemption successful! Your item is ready for claiming.',
            'transaction_id' => $transaction_id,
            'reference_number' => $referenceNumber,
            'new_stock' => $newStock
        ]);
    } catch (Exception $e) {
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => 'Error submitting redemption request: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$connection->close();
?>
