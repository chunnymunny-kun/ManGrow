<?php
/**
 * Multi-Item Checkout Handler
 * Processes shopping cart checkout with multiple items
 * Validates stock, points, creates transaction, and sends email
 */

// Suppress all output before JSON
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'database.php';

// Try to load email function, but don't fail if it doesn't exist
if (file_exists('send_redemption_email.php')) {
    require_once 'send_redemption_email.php';
}

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to redeem items'
    ]);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get cart data
$cartData = json_decode(file_get_contents('php://input'), true);

if (!$cartData || !isset($cartData['items']) || empty($cartData['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Cart is empty'
    ]);
    exit();
}

$userId = $_SESSION['user_id'];
$cartItems = $cartData['items'];

try {
    // Start transaction
    $connection->begin_transaction();
    
    // Step 1: Get user's current points
    $userQuery = "SELECT eco_points, fullname, personal_email FROM accountstbl WHERE account_id = ?";
    $stmt = $connection->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception('User not found');
    }
    
    $user = $userResult->fetch_assoc();
    $userPoints = $user['eco_points'];
    $userName = $user['fullname'];
    $userEmail = $user['personal_email'];
    $stmt->close();
    
    // Step 2: Validate all items and calculate total cost
    $totalCost = 0;
    $validatedItems = [];
    
    foreach ($cartItems as $item) {
        $itemId = intval($item['itemId']);
        $quantity = intval($item['quantity']);
        
        if ($quantity <= 0) {
            throw new Exception('Invalid quantity for item');
        }
        
        // Get item details
        $itemQuery = "SELECT item_id, item_name, points_required, stock_quantity, is_available, image_path 
                      FROM ecoshop_itemstbl 
                      WHERE item_id = ?";
        $stmt = $connection->prepare($itemQuery);
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $itemResult = $stmt->get_result();
        
        if ($itemResult->num_rows === 0) {
            throw new Exception("Item not found: {$item['itemName']}");
        }
        
        $itemData = $itemResult->fetch_assoc();
        $stmt->close();
        
        // Validate availability
        if ($itemData['is_available'] != 1) {
            throw new Exception("{$itemData['item_name']} is no longer available");
        }
        
        // Validate stock
        if ($itemData['stock_quantity'] !== null && $itemData['stock_quantity'] < $quantity) {
            throw new Exception("Insufficient stock for {$itemData['item_name']}. Available: {$itemData['stock_quantity']}");
        }
        
        // Calculate subtotal
        $subtotal = $itemData['points_required'] * $quantity;
        $totalCost += $subtotal;
        
        $validatedItems[] = [
            'item_id' => $itemData['item_id'],
            'item_name' => $itemData['item_name'],
            'points_required' => $itemData['points_required'],
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'stock_quantity' => $itemData['stock_quantity'],
            'image_path' => $itemData['image_path']
        ];
    }
    
    // Step 3: Verify user has enough points
    if ($userPoints < $totalCost) {
        throw new Exception("Insufficient points. You have {$userPoints} pts but need {$totalCost} pts");
    }
    
    // Step 4: Generate reference number
    $referenceNumber = 'REF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Step 5: Get current Manila datetime
    $manilaDateTime = date('Y-m-d H:i:s');
    
    // Step 6: Create main transaction record (use first item for backward compatibility)
    $firstItem = $validatedItems[0];
    $insertTransactionSQL = "INSERT INTO ecoshop_transactions 
        (user_id, item_id, quantity, points_used, status, reference_number, transaction_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $status = 'pending';
    $stmt = $connection->prepare($insertTransactionSQL);
    $stmt->bind_param("iiiisss", 
        $userId, 
        $firstItem['item_id'], 
        $firstItem['quantity'], 
        $totalCost,
        $status,
        $referenceNumber,
        $manilaDateTime
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create transaction: ' . $stmt->error);
    }
    
    $transactionId = $connection->insert_id;
    $stmt->close();
    
    // Step 7: Insert all items into transaction_items table
    $insertItemSQL = "INSERT INTO ecoshop_transaction_items 
        (transaction_id, item_id, quantity, points_used) 
        VALUES (?, ?, ?, ?)";
    
    $stmt = $connection->prepare($insertItemSQL);
    
    foreach ($validatedItems as $item) {
        $stmt->bind_param("iiii", 
            $transactionId, 
            $item['item_id'], 
            $item['quantity'], 
            $item['subtotal']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert transaction item: ' . $stmt->error);
        }
        
        // Update stock quantity if tracked
        if ($item['stock_quantity'] !== null) {
            $newStock = $item['stock_quantity'] - $item['quantity'];
            $updateStockSQL = "UPDATE ecoshop_itemstbl SET stock_quantity = ? WHERE item_id = ?";
            $stockStmt = $connection->prepare($updateStockSQL);
            $stockStmt->bind_param("ii", $newStock, $item['item_id']);
            $stockStmt->execute();
            $stockStmt->close();
        }
    }
    
    $stmt->close();
    
    // Step 8: DO NOT deduct points yet - only after admin approval
    // Points will be deducted in eco_shop_actions.php when admin approves
    
    // Commit transaction
    $connection->commit();
    
    // Step 9: Send confirmation email (optional)
    $emailSent = false;
    if (function_exists('sendRedemptionEmail')) {
        try {
            $emailSent = sendRedemptionEmail(
                $userEmail,
                $userName,
                $referenceNumber,
                $validatedItems,
                $totalCost,
                'confirmation'
            );
        } catch (Exception $emailError) {
            // Log error but don't fail the transaction
            error_log("Email sending failed: " . $emailError->getMessage());
        }
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Redemption request submitted! Please wait for admin approval.',
        'data' => [
            'transaction_id' => $transactionId,
            'reference_number' => $referenceNumber,
            'total_cost' => $totalCost,
            'new_balance' => $userPoints, // Current balance, not deducted yet
            'items_count' => count($validatedItems),
            'email_sent' => $emailSent,
            'status' => 'pending'
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if ($connection) {
        $connection->rollback();
    }
    
    // Log the error
    error_log("Multi-item checkout error: " . $e->getMessage());
    
    // Always return JSON
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
    exit();
}

if (isset($connection)) {
    $connection->close();
}
?>
