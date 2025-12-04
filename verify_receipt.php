<?php
// verify_receipt.php - Verify receipt authenticity by reference number
session_start();
require_once 'database.php';

$verification_result = null;
$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_number'])) {
    $referenceNumber = trim($_POST['reference_number']);
    
    if (!empty($referenceNumber)) {
        // Get transaction details with multi-item support
        $query = "SELECT 
                    t.transaction_id,
                    t.reference_number,
                    t.points_used,
                    t.transaction_date,
                    t.approval_date,
                    t.status,
                    t.notes,
                    u.fullname as user_name,
                    u.personal_email,
                    COUNT(ti.transaction_item_id) as items_count
                  FROM ecoshop_transactions t
                  JOIN accountstbl u ON t.user_id = u.account_id
                  LEFT JOIN ecoshop_transaction_items ti ON t.transaction_id = ti.transaction_id
                  WHERE t.reference_number = ?
                  GROUP BY t.transaction_id";

        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $referenceNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $verification_result = $result->fetch_assoc();
        } else {
            $error_message = "No transaction found with reference number: " . htmlspecialchars($referenceNumber);
        }
    } else {
        $error_message = "Please enter a reference number.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt Verification - ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .verification-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .verification-form {
            text-align: center;
            margin-bottom: 30px;
        }
        .verification-form input {
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            width: 300px;
            margin: 10px;
        }
        .verification-form button {
            padding: 12px 25px;
            background: #2E8B57;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px;
        }
        .verification-form button:hover {
            background: #245f3f;
        }
        .result-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #cce5ff; color: #004085; }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
        }
        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2E8B57;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
        }
        .detail-value {
            font-size: 16px;
            color: #333;
            margin-top: 5px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .success-icon {
            color: #28a745;
            font-size: 48px;
            margin-bottom: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2E8B57;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <main>
        <div class="verification-container">
            <div class="header">
                <div class="logo">🌱 ManGrow Eco Shop</div>
                <h1>Receipt Verification</h1>
                <p>Verify the authenticity of your eco shop transaction receipt</p>
            </div>

            <form method="POST" class="verification-form">
                <div>
                    <input type="text" 
                           name="reference_number" 
                           placeholder="Enter Reference Number (e.g., REF-20240919-000001)"
                           value="<?php echo isset($_POST['reference_number']) ? htmlspecialchars($_POST['reference_number']) : ''; ?>"
                           required>
                </div>
                <div>
                    <button type="submit">
                        <i class="fas fa-search"></i> Verify Receipt
                    </button>
                </div>
            </form>

            <?php if ($error_message): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($verification_result): ?>
                <div class="result-card">
                    <div style="text-align: center;">
                        <?php
                        $status = $verification_result['status'];
                        $statusIcons = [
                            'pending' => ['icon' => 'fa-clock', 'color' => '#ff9800', 'text' => '⏳ Receipt Verified - Pending Approval'],
                            'approved' => ['icon' => 'fa-check-circle', 'color' => '#4caf50', 'text' => '✅ Receipt Verified - Approved'],
                            'rejected' => ['icon' => 'fa-times-circle', 'color' => '#f44336', 'text' => '❌ Receipt Verified - Rejected']
                        ];
                        $statusInfo = $statusIcons[$status] ?? $statusIcons['pending'];
                        ?>
                        <i class="fas <?php echo $statusInfo['icon']; ?>" style="font-size: 60px; color: <?php echo $statusInfo['color']; ?>; margin-bottom: 15px;"></i>
                        <h2><?php echo $statusInfo['text']; ?></h2>
                        <span class="status-badge status-<?php echo $status; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Reference Number</div>
                            <div class="detail-value"><?php echo htmlspecialchars($verification_result['reference_number']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Transaction ID</div>
                            <div class="detail-value">#<?php echo str_pad($verification_result['transaction_id'], 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Customer Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($verification_result['user_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value"><?php echo htmlspecialchars($verification_result['personal_email']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Items</div>
                            <div class="detail-value"><?php echo $verification_result['items_count']; ?> item(s)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Points Used</div>
                            <div class="detail-value"><?php echo number_format($verification_result['points_used']); ?> eco points</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Transaction Date</div>
                            <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($verification_result['transaction_date'])); ?></div>
                        </div>
                        <?php if ($verification_result['approval_date']): ?>
                        <div class="detail-item">
                            <div class="detail-label">Approval Date</div>
                            <div class="detail-value"><?php echo date('F j, Y g:i A', strtotime($verification_result['approval_date'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status-specific messages -->
                    <?php if ($status === 'approved'): ?>
                    <div style="text-align: center; margin-top: 25px; padding: 20px; background: #e8f5e9; border-radius: 8px; border: 2px solid #4caf50;">
                        <h4 style="color: #2e7d32; margin-bottom: 10px;">📍 Ready for Pickup!</h4>
                        <p style="margin: 5px 0;"><strong>MENRO Office</strong><br>
                        Abucay Municipality Hall, Abucay, Bataan</p>
                        <p style="margin: 5px 0;"><strong>Hours:</strong> Monday-Friday, 8:00 AM - 5:00 PM</p>
                        <p style="margin: 5px 0;"><strong>Contact:</strong> (047) 481-0032 | menro.abucay@gmail.com</p>
                        <p style="margin-top: 12px; font-size: 13px; color: #666;">
                            <i class="fas fa-info-circle"></i> 
                            Please bring this reference number and a valid ID when claiming your items.
                        </p>
                        <a href="generate_receipt_pdf_v2.php?ref=<?php echo urlencode($verification_result['reference_number']); ?>" 
                           style="display: inline-block; margin-top: 15px; padding: 12px 25px; background: #2E8B57; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">
                            📄 Download Receipt PDF
                        </a>
                    </div>
                    <?php elseif ($status === 'pending'): ?>
                    <div style="text-align: center; margin-top: 25px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 2px solid #ff9800;">
                        <h4 style="color: #e65100; margin-bottom: 10px;">⏳ Awaiting Admin Approval</h4>
                        <p style="color: #666;">Your transaction is currently being reviewed by our administrators.</p>
                        <p style="color: #666; margin-top: 10px; font-size: 13px;">
                            <i class="fas fa-clock"></i> You will receive an email notification once your transaction is approved or if any action is required.
                        </p>
                    </div>
                    <?php elseif ($status === 'rejected'): ?>
                    <div style="text-align: center; margin-top: 25px; padding: 20px; background: #ffebee; border-radius: 8px; border: 2px solid #f44336;">
                        <h4 style="color: #c62828; margin-bottom: 10px;">❌ Transaction Rejected</h4>
                        <?php if (!empty($verification_result['notes'])): ?>
                        <p style="margin: 10px 0; padding: 12px; background: white; border-radius: 5px;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($verification_result['notes']); ?>
                        </p>
                        <?php endif; ?>
                        <p style="color: #666; margin-top: 10px; font-size: 13px;">
                            <i class="fas fa-info-circle"></i> 
                            If you have questions about this rejection, please contact the MENRO office.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 30px; color: #666;">
                <p><small>
                    <i class="fas fa-shield-alt"></i>
                    This verification system ensures the authenticity of ManGrow eco shop receipts.
                </small></p>
            </div>
        </div>
    </main>
</body>
</html>