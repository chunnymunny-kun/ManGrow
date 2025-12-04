<?php
// eco_shop_emails.php - Handle email notifications for eco shop transactions
require_once 'database.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send approval email to user with receipt and pickup instructions
 */
function sendApprovalEmail($transactionId, $adminId) {
    global $connection;
    
    error_log("sendApprovalEmail: Starting for transaction $transactionId and admin $adminId");
    
    // Get transaction details with user and item information
    $query = "SELECT 
                t.transaction_id,
                t.reference_number,
                t.user_id,
                t.points_used,
                t.quantity,
                t.transaction_date,
                u.fullname as user_name,
                u.personal_email,
                u.email as mangrow_email,
                u.eco_points as remaining_points,
                i.item_name,
                i.item_description,
                i.category,
                i.image_path
              FROM ecoshop_transactions t
              JOIN accountstbl u ON t.user_id = u.account_id
              JOIN ecoshop_itemstbl i ON t.item_id = i.item_id
              WHERE t.transaction_id = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    
    if (!$transaction) {
        error_log("sendApprovalEmail: Transaction not found for ID $transactionId");
        return false;
    }
    
    error_log("sendApprovalEmail: Transaction found for user " . $transaction['user_name']);
    
    // Get admin details
    $adminQuery = "SELECT admin_name, email FROM adminaccountstbl WHERE admin_id = ?";
    $stmt = $connection->prepare($adminQuery);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $admin = $adminResult->fetch_assoc();
    
    if (!$admin) {
        error_log("sendApprovalEmail: Admin not found for ID $adminId, using fallback");
        // Fallback if admin not found
        $admin = ['admin_name' => 'ManGrow Administrator', 'email' => 'admin@mangrow.com'];
    } else {
        error_log("sendApprovalEmail: Admin found - " . $admin['admin_name']);
    }
    
    $to = $transaction['personal_email'];
    $subject = "Eco Shop Purchase Approved - ManGrow";
    
    error_log("sendApprovalEmail: Sending email to " . $to);
    
    // Calculate new eco points after deduction
    $newBalance = $transaction['remaining_points'];
    
    // Email content
    $message = "
    <html>
    <head>
        <title>Purchase Approved - ManGrow Eco Shop</title>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                line-height: 1.6; 
                color: #333; 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background-color: #f8f9fa;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                color: #2E8B57; 
                text-align: center; 
                border-bottom: 2px solid #2E8B57;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .success-icon {
                font-size: 48px;
                color: #28a745;
                margin-bottom: 15px;
            }
            .receipt-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #2E8B57;
            }
            .receipt-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .receipt-item:last-child {
                border-bottom: none;
                font-weight: bold;
                color: #2E8B57;
            }
            .pickup-section {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .pickup-section h3 {
                color: #856404;
                margin-top: 0;
            }
            .contact-info {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 8px;
                margin: 15px 0;
            }
            .footer { 
                margin-top: 30px; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
                border-top: 1px solid #eee;
                padding-top: 20px;
            }
            .button {
                display: inline-block;
                background: #2E8B57;
                color: white !important;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 5px;
                margin: 10px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='success-icon'>✅</div>
                <h1>Purchase Approved!</h1>
                <p>Your eco shop redemption has been successfully approved</p>
            </div>
            
            <p>Dear <strong>".htmlspecialchars($transaction['user_name'])."</strong>,</p>
            
            <p>Great news! Your redemption request has been approved by our administrator.</p>
            
            <div class='receipt-section'>
                <h3>📋 Transaction Receipt</h3>
                <div class='receipt-item'>
                    <span>Reference Number:</span>
                    <span><strong>".htmlspecialchars($transaction['reference_number'] ?? 'REF-'.date('Ymd').'-'.str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT))."</strong></span>
                </div>
                <div class='receipt-item'>
                    <span>Transaction ID:</span>
                    <span><strong>#".str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT)."</strong></span>
                </div>
                <div class='receipt-item'>
                    <span>Item:</span>
                    <span>".htmlspecialchars($transaction['item_name'])."</span>
                </div>
                <div class='receipt-item'>
                    <span>Quantity:</span>
                    <span>".htmlspecialchars($transaction['quantity'])."</span>
                </div>
                <div class='receipt-item'>
                    <span>Eco Points Deducted:</span>
                    <span>".number_format($transaction['points_used'])." points</span>
                </div>
                <div class='receipt-item'>
                    <span>Remaining Balance:</span>
                    <span>".number_format($newBalance)." eco points</span>
                </div>
                <div class='receipt-item'>
                    <span>Transaction Date:</span>
                    <span>".date('F j, Y g:i A', strtotime($transaction['transaction_date']))."</span>
                </div>
                <div class='receipt-item'>
                    <span>Approved By:</span>
                    <span>".htmlspecialchars($admin['admin_name'])."</span>
                </div>
            </div>
            
            <!-- Receipt Download Section -->
            <div style='text-align: center; margin: 25px 0;'>
                <a href='http://mangrow.42web.io/generate_receipt_pdf_v2.php?ref=".urlencode($transaction['reference_number'] ?? 'REF-'.date('Ymd').'-'.str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT))."' 
                   class='button' style='background: #dc3545; text-decoration: none; display: inline-block;'>
                    <i style='margin-right: 8px;'>📄</i> View & Download Receipt
                </a>
                <br>
                <small style='color: #666; margin-top: 10px; display: block;'>
                    <strong>Important:</strong> Click to view your receipt. You can print it or download as text file.
                </small>
            </div>
            
            <div class='pickup-section'>
                <h3>🏢 Item Pickup Instructions</h3>
                <p><strong>Please collect your item from:</strong></p>
                <div class='contact-info'>
                    <p><strong>📍 MENRO Office</strong><br>
                    Abucay Municipality Hall<br>
                    Abucay, Bataan, Philippines</p>
                    
                    <p><strong>🕒 Office Hours:</strong><br>
                    Monday - Friday: 8:00 AM - 5:00 PM<br>
                    Saturday: 8:00 AM - 12:00 PM</p>
                    
                    <p><strong>📞 Contact Information:</strong><br>
                    Phone: (047) 481-0032<br>
                    Email: menro.abucay@gmail.com</p>
                </div>
                
                <h4>📋 What to bring:</h4>
                <ul>
                    <li>Reference Number: <strong>".htmlspecialchars($transaction['reference_number'] ?? 'REF-'.date('Ymd').'-'.str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT))."</strong></li>
                    <li>Printed PDF receipt (download from link above)</li>
                </ul>
                
                <p><strong>⚠️ Important Notes:</strong></p>
                <ul>
                    <li>Items must be collected within <strong>30 days</strong> from approval date</li>
                    <li>Unclaimed items after 30 days will be forfeited</li>
                    <li>Please bring the required documents for smooth processing</li>
                </ul>
            </div>
            
            <p>Thank you for being an active member of the ManGrow community! Your environmental contributions make a real difference.</p>
            
            <p>Continue earning eco points by:</p>
            <ul>
                <li>Attending environmental events</li>
                <li>Submitting environmental reports</li>
                <li>Daily login streaks</li>
                <li>Participating in community activities</li>
            </ul>
            
            <div class='footer'>
                <p>If you have any questions about your purchase or pickup, please contact us at:</p>
                <p><strong>Email:</strong> menro.abucay@gmail.com | <strong>Phone:</strong> (047) 481-0032</p>
                <p>&copy; ".date('Y')." ManGrow - Mangrove Conservation and Eco Tracking System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    error_log("sendApprovalEmail: Email content prepared, attempting to send");
    $result = sendEmail($to, $subject, $message);
    error_log("sendApprovalEmail: Email send result: " . ($result ? 'SUCCESS' : 'FAILED'));
    return $result;
}

/**
 * Send rejection email to user with reason
 */
function sendRejectionEmail($transactionId, $adminId, $rejectionReason = '') {
    global $connection;
    
    error_log("sendRejectionEmail: Starting for transaction $transactionId and admin $adminId");
    
    // Get transaction details
    $query = "SELECT 
                t.transaction_id,
                t.reference_number,
                t.user_id,
                t.points_used,
                t.quantity,
                t.transaction_date,
                t.notes,
                u.fullname as user_name,
                u.personal_email,
                u.email as mangrow_email,
                u.eco_points as current_points,
                i.item_name,
                i.item_description,
                i.category,
                i.image_path
              FROM ecoshop_transactions t
              JOIN accountstbl u ON t.user_id = u.account_id
              JOIN ecoshop_itemstbl i ON t.item_id = i.item_id
              WHERE t.transaction_id = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    
    if (!$transaction) {
        error_log("sendRejectionEmail: Transaction not found for ID $transactionId");
        return false;
    }
    
    error_log("sendRejectionEmail: Transaction found for user " . $transaction['user_name']);
    
    // Get admin details
    $adminQuery = "SELECT admin_name, email FROM adminaccountstbl WHERE admin_id = ?";
    $stmt = $connection->prepare($adminQuery);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $admin = $adminResult->fetch_assoc();
    
    if (!$admin) {
        error_log("sendRejectionEmail: Admin not found for ID $adminId, using fallback");
        $admin = ['admin_name' => 'ManGrow Administrator', 'email' => 'admin@mangrow.com'];
    } else {
        error_log("sendRejectionEmail: Admin found - " . $admin['admin_name']);
    }
    
    $to = $transaction['personal_email'];
    $subject = "Eco Shop Purchase Declined - ManGrow";
    
    // Use custom reason if provided, otherwise use stored notes
    $finalReason = !empty($rejectionReason) ? $rejectionReason : $transaction['notes'];
    if (empty($finalReason)) {
        $finalReason = 'No specific reason provided by administrator.';
    }
    
    // Email content
    $message = "
    <html>
    <head>
        <title>Purchase Declined - ManGrow Eco Shop</title>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                line-height: 1.6; 
                color: #333; 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
                background-color: #f8f9fa;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .header { 
                color: #dc3545; 
                text-align: center; 
                border-bottom: 2px solid #dc3545;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .declined-icon {
                font-size: 48px;
                color: #dc3545;
                margin-bottom: 15px;
            }
            .transaction-details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #dc3545;
            }
            .detail-item {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .detail-item:last-child {
                border-bottom: none;
            }
            .reason-section {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .reason-section h3 {
                color: #856404;
                margin-top: 0;
            }
            .next-steps {
                background: #e3f2fd;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
                border-top: 1px solid #eee;
                padding-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='declined-icon'>❌</div>
                <h1>Purchase Declined</h1>
                <p>Your eco shop redemption request has been declined</p>
            </div>
            
            <p>Dear <strong>".htmlspecialchars($transaction['user_name'])."</strong>,</p>
            
            <p>We regret to inform you that your redemption request has been declined by our administrator.</p>
            
            <div class='transaction-details'>
                <h3>📋 Transaction Details</h3>
                <div class='detail-item'>
                    <span>Transaction ID:</span>
                    <span><strong>#".str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT)."</strong></span>
                </div>
                <div class='detail-item'>
                    <span>Item:</span>
                    <span>{$transaction['item_emoji']} ".htmlspecialchars($transaction['item_name'])."</span>
                </div>
                <div class='detail-item'>
                    <span>Quantity:</span>
                    <span>".htmlspecialchars($transaction['quantity'])."</span>
                </div>
                <div class='detail-item'>
                    <span>Points Required:</span>
                    <span>".number_format($transaction['points_used'])." eco points</span>
                </div>
                <div class='detail-item'>
                    <span>Request Date:</span>
                    <span>".date('F j, Y g:i A', strtotime($transaction['transaction_date']))."</span>
                </div>
                <div class='detail-item'>
                    <span>Reviewed By:</span>
                    <span>".htmlspecialchars($admin['admin_name'])."</span>
                </div>
            </div>
            
            <div class='reason-section'>
                <h3>📝 Reason for Decline</h3>
                <p><strong>".htmlspecialchars($finalReason)."</strong></p>
            </div>
            
            <div class='next-steps'>
                <h3>🔄 What's Next?</h3>
                <p><strong>Your eco points have NOT been deducted.</strong> You can:</p>
                <ul>
                    <li>Try redeeming the same item again later</li>
                    <li>Choose a different item from our eco shop</li>
                    <li>Earn more eco points through activities</li>
                    <li>Contact our administrator for clarification</li>
                </ul>
                
                <p><strong>Current Balance:</strong> ".number_format($transaction['current_points'])." eco points</p>
            </div>
            
            <p>Don't let this discourage you! Continue participating in environmental activities to earn more eco points:</p>
            <ul>
                <li>Attend environmental events</li>
                <li>Submit environmental reports</li>
                <li>Maintain daily login streaks</li>
                <li>Join community conservation activities</li>
            </ul>
            
            <div class='footer'>
                <p>If you have questions about this decision, please contact us at:</p>
                <p><strong>Email:</strong> menro.abucay@gmail.com | <strong>Phone:</strong> (047) 481-0032</p>
                <p>&copy; ".date('Y')." ManGrow - Mangrove Conservation and Eco Tracking System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    error_log("sendRejectionEmail: Email content prepared, attempting to send to $to");
    $result = sendEmail($to, $subject, $message);
    error_log("sendRejectionEmail: Email send result: " . ($result ? "SUCCESS" : "FAILED"));
    return $result;
}

/**
 * Core email sending function using PHPMailer
 */
function sendEmail($to, $subject, $messageBody) {
    error_log("sendEmail: Starting to send email to $to with subject: $subject");
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jetrimentimentalistically@gmail.com';
        $mail->Password   = 'uaxrlxrruwhzybnq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        error_log("sendEmail: SMTP configuration set");
        
        // Recipients
        $mail->setFrom('jetrimentimentalistically@gmail.com', 'ManGrow Eco Shop');
        $mail->addAddress($to);
        
        error_log("sendEmail: Recipients set");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $messageBody;
        
        error_log("sendEmail: Content set, attempting to send");
        
        $mail->send();
        error_log("sendEmail: Email sent successfully!");
        return true;
    } catch (Exception $e) {
        error_log("sendEmail: Email sending failed: " . $e->getMessage());
        return false;
    }
}
?>