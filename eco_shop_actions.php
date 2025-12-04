<?php
// eco_shop_actions.php - Handle AJAX requests for eco shop transactions
session_start();

// Start output buffering to capture any unwanted output
ob_start();

// Suppress all PHP errors and warnings to ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

include 'database.php';
require_once 'send_redemption_email.php'; // Include email functions

// Clean any unwanted output
ob_clean();

header('Content-Type: application/json');

// Check if user is authorized
if (!isset($_SESSION['accessrole']) || 
    ($_SESSION['accessrole'] != 'Administrator' && 
     $_SESSION['accessrole'] != 'Barangay Official')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $admin_id = $_SESSION['user_id'];
    
    switch ($_POST['action']) {
        case 'approve_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            
            // Get transaction details
            $transactionQuery = "SELECT t.*, u.eco_points, u.fullname, u.personal_email 
                                FROM ecoshop_transactions t 
                                JOIN accountstbl u ON t.user_id = u.account_id 
                                WHERE t.transaction_id = ? AND t.status = 'pending'";
            $stmt = $connection->prepare($transactionQuery);
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Transaction not found or already processed']);
                exit();
            }
            
            $transaction = $result->fetch_assoc();
            
            // Get all items from transaction_items table
            $itemsQuery = "SELECT ti.item_id, ti.quantity, ti.points_used, i.item_name, i.stock_quantity, i.points_required 
                          FROM ecoshop_transaction_items ti
                          JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
                          WHERE ti.transaction_id = ?";
            $itemsStmt = $connection->prepare($itemsQuery);
            $itemsStmt->bind_param("i", $transaction_id);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $transactionItems = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                // Add subtotal for email compatibility
                $itemRow['subtotal'] = $itemRow['points_used'];
                $transactionItems[] = $itemRow;
            }
            $itemsStmt->close();
            
            if (empty($transactionItems)) {
                echo json_encode(['success' => false, 'message' => 'No items found for this transaction']);
                exit();
            }
            
            // Validate stock availability for all items
            foreach ($transactionItems as $item) {
                if ($item['stock_quantity'] !== null && $item['stock_quantity'] < $item['quantity']) {
                    echo json_encode(['success' => false, 'message' => "Insufficient stock for {$item['item_name']}"]);
                    exit();
                }
            }
            
            // Start transaction
            $connection->begin_transaction();
            
            try {
                // Update transaction status (removed approved_by to avoid FK constraint)
                $updateTransactionQuery = "UPDATE ecoshop_transactions 
                                          SET status = 'approved', approval_date = NOW() 
                                          WHERE transaction_id = ?";
                $stmt = $connection->prepare($updateTransactionQuery);
                $stmt->bind_param("i", $transaction_id);
                $stmt->execute();
                
                // Deduct points from user (points were NOT deducted at checkout per recent changes)
                $userPoints = $transaction['eco_points'];
                if ($userPoints < $transaction['points_used']) {
                    throw new Exception('User has insufficient points. User has ' . $userPoints . ' but needs ' . $transaction['points_used']);
                }
                
                $newBalance = $userPoints - $transaction['points_used'];
                $deductPointsQuery = "UPDATE accountstbl SET eco_points = ? WHERE account_id = ?";
                $pointsStmt = $connection->prepare($deductPointsQuery);
                $pointsStmt->bind_param("ii", $newBalance, $transaction['user_id']);
                $pointsStmt->execute();
                $pointsStmt->close();
                
                // Stock was already updated at checkout - no need to update again
                
                // Log activity with all items
                $itemNames = array_column($transactionItems, 'item_name');
                $itemsDescription = implode(', ', $itemNames);
                $logQuery = "INSERT INTO ecoshop_activity_logs (admin_id, activity_type, transaction_id, details) 
                            VALUES (?, 'transaction_approved', ?, ?)";
                $details = "Approved redemption of {$itemsDescription} (" . count($transactionItems) . " items) for user {$transaction['fullname']}";
                $stmt = $connection->prepare($logQuery);
                $stmt->bind_param("iis", $admin_id, $transaction_id, $details);
                $stmt->execute();
                
                $connection->commit();
                
                // Send approval email to user with multi-item support and balance info
                try {
                    if (function_exists('sendRedemptionEmail')) {
                        // Add balance information to transaction data
                        $balanceInfo = [
                            'previous_balance' => $userPoints,
                            'points_used' => $transaction['points_used'],
                            'new_balance' => $newBalance
                        ];
                        
                        $emailSent = sendRedemptionEmail(
                            $transaction['personal_email'],
                            $transaction['fullname'],
                            $transaction['reference_number'],
                            $transactionItems,
                            $transaction['points_used'],
                            'approved',
                            '',
                            $balanceInfo
                        );
                        
                        if ($emailSent) {
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Transaction approved successfully! Points deducted and email sent to user.',
                                'balance_info' => $balanceInfo
                            ]);
                        } else {
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Transaction approved successfully! Points deducted but email failed to send.',
                                'balance_info' => $balanceInfo
                            ]);
                        }
                    } else {
                        echo json_encode([
                            'success' => true, 
                            'message' => 'Transaction approved successfully! Points deducted. (Email function not available)',
                            'balance_info' => $balanceInfo
                        ]);
                    }
                } catch (Exception $emailError) {
                    error_log("Email error: " . $emailError->getMessage());
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Transaction approved successfully! Points deducted but email failed.',
                        'balance_info' => $balanceInfo
                    ]);
                }
                
            } catch (Exception $e) {
                $connection->rollback();
                echo json_encode(['success' => false, 'message' => 'Error processing transaction: ' . $e->getMessage()]);
            }
            break;
            
        case 'reject_transaction':
            $transaction_id = intval($_POST['transaction_id']);
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
            
            // Get transaction details
            $transactionQuery = "SELECT t.*, u.fullname, u.personal_email 
                                FROM ecoshop_transactions t 
                                JOIN accountstbl u ON t.user_id = u.account_id 
                                WHERE t.transaction_id = ? AND t.status IN ('pending', 'approved')";
            $stmt = $connection->prepare($transactionQuery);
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Transaction not found or already processed']);
                exit();
            }
            
            $transaction = $result->fetch_assoc();
            
            // Get all items from transaction_items table
            $itemsQuery = "SELECT ti.item_id, ti.quantity, ti.points_used, i.item_name, i.stock_quantity, i.points_required 
                          FROM ecoshop_transaction_items ti
                          JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
                          WHERE ti.transaction_id = ?";
            $itemsStmt = $connection->prepare($itemsQuery);
            $itemsStmt->bind_param("i", $transaction_id);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            
            $transactionItems = [];
            while ($itemRow = $itemsResult->fetch_assoc()) {
                // Add subtotal for email compatibility
                $itemRow['subtotal'] = $itemRow['points_used'];
                $transactionItems[] = $itemRow;
            }
            $itemsStmt->close();
            
            // Start transaction
            $connection->begin_transaction();
            
            try {
                // Update transaction status
                $notes = empty($reason) ? 'Rejected by administrator' : 'Rejected: ' . $reason;
                $updateTransactionQuery = "UPDATE ecoshop_transactions 
                                          SET status = 'rejected', notes = ? 
                                          WHERE transaction_id = ?";
                $stmt = $connection->prepare($updateTransactionQuery);
                $stmt->bind_param("si", $notes, $transaction_id);
                $stmt->execute();

                // DO NOT refund points - they were never deducted at checkout!
                // Points are only deducted when admin APPROVES, not at checkout
                // So if we're rejecting a PENDING transaction, there's nothing to refund
                
                // Only refund if transaction was already approved (rare edge case)
                if ($transaction['status'] == 'approved') {
                    $refundUserQuery = "UPDATE accountstbl 
                                      SET eco_points = eco_points + ? 
                                      WHERE account_id = ?";
                    $refundStmt = $connection->prepare($refundUserQuery);
                    $refundStmt->bind_param("ii", $transaction['points_used'], $transaction['user_id']);
                    $refundStmt->execute();
                    $refundStmt->close();
                }

                // Restore stock for all items
                foreach ($transactionItems as $item) {
                    if ($item['stock_quantity'] !== null) {
                        $restoreStockQuery = "UPDATE ecoshop_itemstbl 
                                            SET stock_quantity = stock_quantity + ? 
                                            WHERE item_id = ?";
                        $stockStmt = $connection->prepare($restoreStockQuery);
                        $stockStmt->bind_param("ii", $item['quantity'], $item['item_id']);
                        $stockStmt->execute();
                        $stockStmt->close();
                    }
                }
                
                // Log activity with all items
                $itemNames = array_column($transactionItems, 'item_name');
                $itemsDescription = implode(', ', $itemNames);
                $logQuery = "INSERT INTO ecoshop_activity_logs (admin_id, activity_type, transaction_id, details) 
                            VALUES (?, 'transaction_rejected', ?, ?)";
                $details = "Rejected redemption of {$itemsDescription} (" . count($transactionItems) . " items) for user {$transaction['fullname']}";
                if (!empty($reason)) {
                    $details .= " - Reason: $reason";
                }
                $stmt = $connection->prepare($logQuery);
                $stmt->bind_param("iis", $admin_id, $transaction_id, $details);
                $stmt->execute();
                
                $connection->commit();
                
                // Send rejection email to user with multi-item support
                try {
                    if (function_exists('sendRedemptionEmail')) {
                        $emailSent = sendRedemptionEmail(
                            $transaction['personal_email'],
                            $transaction['fullname'],
                            $transaction['reference_number'],
                            $transactionItems,
                            $transaction['points_used'],
                            'rejected',
                            $reason
                        );
                        
                        if ($emailSent) {
                            echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully! Stock restored and email sent to user.']);
                        } else {
                            echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully! Stock restored but email failed to send.']);
                        }
                    } else {
                        echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully! Stock restored. (Email function not available)']);
                    }
                } catch (Exception $emailError) {
                    error_log("Email error: " . $emailError->getMessage());
                    echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully! Stock restored but email failed.']);
                    error_log("Email error: " . $emailError->getMessage());
                    echo json_encode(['success' => true, 'message' => 'Transaction rejected successfully! Points refunded but email failed.']);
                }
                
            } catch (Exception $e) {
                $connection->rollback();
                echo json_encode(['success' => false, 'message' => 'Error processing transaction: ' . $e->getMessage()]);
            }
            break;
            
        case 'mark_as_claimed':
            $transaction_id = intval($_POST['transaction_id']);
            
            // Get transaction details
            $transactionQuery = "SELECT t.*, u.fullname as user_name, i.item_name 
                                FROM ecoshop_transactions t 
                                JOIN accountstbl u ON t.user_id = u.account_id 
                                JOIN ecoshop_itemstbl i ON t.item_id = i.item_id 
                                WHERE t.transaction_id = ? AND t.status = 'approved'";
            $stmt = $connection->prepare($transactionQuery);
            $stmt->bind_param("i", $transaction_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Transaction not found or not approved']);
                exit();
            }
            
            $transaction = $result->fetch_assoc();
            
            // Start transaction
            $connection->begin_transaction();
            
            try {
                // Update transaction status to completed
                $updateTransactionQuery = "UPDATE ecoshop_transactions 
                                          SET status = 'completed', 
                                              notes = CONCAT(IFNULL(notes, ''), ' - Item claimed by user on ', NOW()) 
                                          WHERE transaction_id = ?";
                $stmt = $connection->prepare($updateTransactionQuery);
                $stmt->bind_param("i", $transaction_id);
                $stmt->execute();
                
                // Log activity
                $logQuery = "INSERT INTO ecoshop_activity_logs (admin_id, activity_type, transaction_id, details) 
                            VALUES (?, 'transaction_completed', ?, ?)";
                $details = "Marked as claimed: {$transaction['item_name']} for user {$transaction['user_name']}";
                $stmt = $connection->prepare($logQuery);
                $stmt->bind_param("iis", $admin_id, $transaction_id, $details);
                $stmt->execute();
                
                $connection->commit();
                
                echo json_encode(['success' => true, 'message' => 'Transaction marked as claimed successfully']);
                
            } catch (Exception $e) {
                $connection->rollback();
                echo json_encode(['success' => false, 'message' => 'Error marking transaction as claimed: ' . $e->getMessage()]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$connection->close();
?>
