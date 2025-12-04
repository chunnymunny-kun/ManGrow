<?php
// generate_receipt_pdf_v2.php - Generate proper PDF using FPDF or simple text
session_start();
require_once 'database.php';

// Check if reference number is provided
if (!isset($_GET['ref']) || empty($_GET['ref'])) {
    die('Invalid reference number');
}

$referenceNumber = $_GET['ref'];
        

// Get transaction details with current balance
$query = "SELECT 
            t.transaction_id,
            t.reference_number,
            t.user_id,
            t.points_used,
            t.quantity,
            t.transaction_date,
            t.approval_date,
            t.status,
            u.fullname as user_name,
            u.personal_email,
            u.eco_points as current_balance,
            a.admin_name as approved_by
          FROM ecoshop_transactions t
          JOIN accountstbl u ON t.user_id = u.account_id
          LEFT JOIN adminaccountstbl a ON t.approved_by = a.admin_id
          WHERE t.reference_number = ? AND t.status = 'approved'";

$stmt = $connection->prepare($query);
$stmt->bind_param("s", $referenceNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Transaction not found or not approved');
}

$transaction = $result->fetch_assoc();

// Get all items for this transaction from transaction_items table
$itemsQuery = "SELECT 
                ti.quantity,
                ti.points_used as subtotal,
                i.item_name,
                i.item_description,
                i.category,
                i.points_required
              FROM ecoshop_transaction_items ti
              JOIN ecoshop_itemstbl i ON ti.item_id = i.item_id
              WHERE ti.transaction_id = ?
              ORDER BY i.item_name";

$itemsStmt = $connection->prepare($itemsQuery);
$itemsStmt->bind_param("i", $transaction['transaction_id']);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$transactionItems = [];
while ($itemRow = $itemsResult->fetch_assoc()) {
    $transactionItems[] = $itemRow;
}
$itemsStmt->close();

// Display the HTML receipt page with print functionality
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redemption Ticket - <?php echo htmlspecialchars($transaction['reference_number']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .receipt {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #2E8B57 0%, #1e5f3d 100%);
            color: white;
            padding: 15px;
            text-align: center;
        }
        
        .header .brand {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .header .leaf-icon {
            font-size: 24px;
        }
        
        .header h1 {
            font-size: 22px;
            margin: 5px 0;
        }
        
        .status-badge {
            background: #4caf50;
            color: white;
            padding: 8px 20px;
            display: inline-block;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 15px 0 5px 0;
        }
        
        .content {
            padding: 20px;
        }
        
        .ref-box {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .ref-label {
            font-size: 9px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        
        .ref-number {
            font-size: 18px;
            color: #2E8B57;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 12px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #2E8B57;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        
        .items-table thead {
            background: #fafafa;
        }
        
        .items-table th {
            padding: 6px;
            text-align: left;
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
            border: 1px solid #e0e0e0;
        }
        
        .items-table td {
            padding: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .items-table tfoot td {
            background: #fafafa;
            font-weight: bold;
            font-size: 15px;
        }
        
        .items-table .text-right { text-align: right; }
        .items-table .text-center { text-align: center; }
        .items-table .total-label { text-align: right; }
        .items-table .total-value { color: #2E8B57; font-size: 16px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin: 12px 0;
        }
        
        .info-item {
            font-size: 10px;
        }
        
        .info-label {
            color: #999;
            margin-bottom: 2px;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.3px;
        }
        
        .info-value {
            color: #333;
            font-weight: 500;
            font-size: 11px;
        }
        
        .pickup-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 12px;
            border-radius: 6px;
            margin: 12px 0;
            font-size: 10px;
        }
        
        .pickup-section h3 {
            color: #856404;
            font-size: 12px;
            margin-bottom: 6px;
        }
        
        .pickup-section ul {
            margin: 5px 0;
            padding-left: 15px;
        }
        
        .pickup-section li {
            margin: 2px 0;
        }
        
        .footer {
            background: #fafafa;
            padding: 10px;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #e0e0e0;
        }
        
        .no-print {
            text-align: center;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .no-print button {
            background: #2E8B57;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
        }
        
        .no-print button:hover { background: #1e5f3d; }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt {
                box-shadow: none;
                max-width: 100%;
            }
            .no-print { display: none; }
            .header { padding: 10px; }
            .content { padding: 12px; }
            .items-table { font-size: 9px; }
            .items-table th, .items-table td { padding: 4px; }
            .info-grid { gap: 5px; margin: 8px 0; }
            .pickup-section { padding: 8px; margin: 8px 0; font-size: 9px; }
            .footer { padding: 8px; font-size: 8px; }
        }
        
        @page {
            size: A5;
            margin: 0.2in;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <!-- Header -->
        <div class="header">
            <div class="icon"><i class="fas fa-leaf"></i> <strong>ManGrow</strong></div>
            <h1>Redemption Ticket</h1>
            <div class="status-badge">✅ Approved</div>
        </div>
        
        <!-- Content -->
        <div class="content">
            <!-- Reference Number Box -->
            <div class="ref-box">
                <div class="ref-label">Reference Number</div>
                <div class="ref-number"><?php echo htmlspecialchars($transaction['reference_number']); ?></div>
            </div>
            
            <!-- Customer Info -->
            <div class="section-title">📋 Transaction Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Customer Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($transaction['user_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Transaction ID</div>
                    <div class="info-value">#<?php echo str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($transaction['personal_email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Transaction Date</div>
                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($transaction['transaction_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Approval Date</div>
                    <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($transaction['approval_date'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">Approved</div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="section-title">🛍️ Items Summary</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactionItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right"><?php echo number_format($item['points_required']); ?> pts</td>
                        <td class="text-right"><?php echo number_format($item['subtotal']); ?> pts</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="total-label">Total Eco Points Used:</td>
                        <td class="text-right total-value"><?php echo number_format($transaction['points_used']); ?> pts</td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- Balance Computation -->
            <?php 
            // Calculate previous balance (current + points used since points were already deducted)
            $previous_balance = $transaction['current_balance'] + $transaction['points_used'];
            $new_balance = $transaction['current_balance'];
            ?>
            <div class="section-title">💰 Eco Points Balance</div>
            <table class="items-table">
                <tbody>
                    <tr>
                        <td colspan="3">Previous Balance:</td>
                        <td class="text-right"><?php echo number_format($previous_balance); ?> pts</td>
                    </tr>
                    <tr>
                        <td colspan="3">Points Used:</td>
                        <td class="text-right" style="color: #f44336;">- <?php echo number_format($transaction['points_used']); ?> pts</td>
                    </tr>
                    <tr style="background: #e8f5e9; font-weight: bold;">
                        <td colspan="3" style="color: #2e7d32;">Remaining Balance:</td>
                        <td class="text-right" style="color: #2e7d32; font-size: 16px;"><?php echo number_format($new_balance); ?> pts</td>
                    </tr>
                </tbody>
            </table>
            
            <!-- Pickup Instructions -->
            <div class="pickup-section">
                <h3>📍 Item Pickup Instructions</h3>
                <p style="margin: 3px 0;"><strong>Location:</strong> MENRO Office, Abucay Municipality Hall, Abucay, Bataan</p>
                <p style="margin: 3px 0;"><strong>Office Hours:</strong> Monday to Friday, 8:00 AM - 5:00 PM</p>
                <p style="margin: 3px 0;"><strong>Contact:</strong> (047) 481-0032 | menro.abucay@gmail.com</p>
                
                <h3 style="margin: 8px 0 4px 0;">What to Bring:</h3>
                <ul>
                    <li>This receipt (printed or digital)</li>
                    <li>Valid ID for verification</li>
                    <li>Reference Number: <strong><?php echo htmlspecialchars($transaction['reference_number']); ?></strong></li>
                </ul>
                
                <p style="margin: 8px 0 0 0;"><strong>⚠️ Important:</strong> Items must be claimed within 30 days of approval.</p>
            </div>
            
            <!-- Print Button (hidden on print) -->
            <div class="no-print">
                <button onclick="window.print()">🖨️ Print Receipt</button>
                <button onclick="window.location.href='verify_receipt.php'">🔍 Verify Receipt</button>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Generated on <?php echo date('F j, Y g:i A'); ?></p>
            <p>© <?php echo date('Y'); ?> ManGrow - Mangrove Conservation & Eco Tracking System</p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
        </div>
    </div>
</body>
</html>