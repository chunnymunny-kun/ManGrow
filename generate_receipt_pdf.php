<?php
// generate_receipt_pdf.php - Generate HTML receipt with print functionality
session_start();
require_once 'database.php';

// Check if reference number is provided
if (!isset($_GET['ref']) || empty($_GET['ref'])) {
    die('Invalid reference number');
}

$referenceNumber = $_GET['ref'];

// Get transaction details
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
            i.item_name,
            i.item_description,
            i.category,
            i.points_required,
            a.admin_name as approved_by
          FROM ecoshop_transactions t
          JOIN accountstbl u ON t.user_id = u.account_id
          JOIN ecoshop_itemstbl i ON t.item_id = i.item_id
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

// Set headers for HTML download (not PDF)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ManGrow Eco Shop Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2E8B57;
        }
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .receipt-table th,
        .receipt-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .receipt-table th {
            background-color: #2E8B57;
            color: white;
        }
        .total-row {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .pickup-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .header { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🌱 ManGrow Eco Shop</div>
        <h2>Official Receipt</h2>
        <p>Mangrove Conservation and Eco Tracking System</p>
    </div>

    <table class="receipt-table">
        <tr>
            <th>Field</th>
            <th>Details</th>
        </tr>
        <tr>
            <td><strong>Reference Number</strong></td>
            <td><?php echo htmlspecialchars($transaction['reference_number']); ?></td>
        </tr>
        <tr>
            <td><strong>Transaction ID</strong></td>
            <td>#<?php echo str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT); ?></td>
        </tr>
        <tr>
            <td><strong>Customer Name</strong></td>
            <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td><?php echo htmlspecialchars($transaction['personal_email']); ?></td>
        </tr>
        <tr>
            <td><strong>Item</strong></td>
            <td><?php echo htmlspecialchars($transaction['item_name']); ?></td>
        </tr>
        <tr>
            <td><strong>Category</strong></td>
            <td><?php echo ucfirst(htmlspecialchars($transaction['category'])); ?></td>
        </tr>
        <tr>
            <td><strong>Quantity</strong></td>
            <td><?php echo htmlspecialchars($transaction['quantity']); ?></td>
        </tr>
        <tr class="total-row">
            <td><strong>Eco Points Used</strong></td>
            <td><?php echo number_format($transaction['points_used']); ?> points</td>
        </tr>
        <tr>
            <td><strong>Transaction Date</strong></td>
            <td><?php echo date('F j, Y g:i A', strtotime($transaction['transaction_date'])); ?></td>
        </tr>
        <tr>
            <td><strong>Approval Date</strong></td>
            <td><?php echo date('F j, Y g:i A', strtotime($transaction['approval_date'])); ?></td>
        </tr>
        <tr>
            <td><strong>Approved By</strong></td>
            <td><?php echo htmlspecialchars($transaction['approved_by'] ?? 'ManGrow Administrator'); ?></td>
        </tr>
    </table>

    <div class="pickup-info">
        <h3>📍 Item Pickup Information</h3>
        <p><strong>Location:</strong> MENRO Office, Abucay Municipality Hall, Abucay, Bataan</p>
        <p><strong>Office Hours:</strong> Monday to Friday, 8:00 AM - 5:00 PM</p>
        <p><strong>Contact:</strong> (047) 481-0032 | menro.abucay@gmail.com</p>
        
        <h4>What to bring:</h4>
        <ul>
            <li>This printed receipt</li>
            <li>Valid government-issued ID</li>
            <li>Reference Number: <strong><?php echo htmlspecialchars($transaction['reference_number']); ?></strong></li>
        </ul>
        
        <p><strong>Note:</strong> Items must be claimed within 30 days of approval.</p>
    </div>

    <div class="qr-section">
        <p><strong>Reference Number for Verification:</strong></p>
        <div style="font-size: 18px; font-weight: bold; background: #f0f0f0; padding: 10px; display: inline-block;">
            <?php echo htmlspecialchars($transaction['reference_number']); ?>
        </div>
    </div>

    <div class="footer">
        <p>This is an official receipt generated by the ManGrow Eco Tracking System</p>
        <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
        <p>&copy; <?php echo date('Y'); ?> ManGrow - Mangrove Conservation and Eco Tracking System</p>
        <p>For verification, visit: http://localhost/project/verify_receipt.php</p>
    </div>

    <!-- Print Controls -->
    <div class="no-print" style="text-align: center; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <button onclick="window.print()" style="background: #2E8B57; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            🖨️ Print Receipt
        </button>
        <button onclick="downloadAsPDF()" style="background: #dc3545; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            📄 Save as PDF
        </button>
        <button onclick="window.close()" style="background: #6c757d; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
            ✖️ Close
        </button>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // };
        
        function downloadAsPDF() {
            // Hide the print controls
            document.querySelector('.no-print').style.display = 'none';
            
            // Print the page (user can save as PDF)
            window.print();
            
            // Show the controls again after a delay
            setTimeout(() => {
                document.querySelector('.no-print').style.display = 'block';
            }, 1000);
        }
    </script>
</body>
</html>