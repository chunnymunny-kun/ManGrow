<?php
/**
 * Redemption Email System
 * Sends HTML emails for multi-item transactions
 * Supports: confirmation, approval, rejection notifications
 */

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendRedemptionEmail($recipientEmail, $recipientName, $referenceNumber, $items, $totalPoints, $emailType = 'confirmation', $rejectionReason = '', $balanceInfo = null) {
    // Email configuration
    $fromEmail = "noreply@mangrow.com";
    $fromName = "ManGrow Eco Shop";
    
    // Determine email subject and content based on type
    switch ($emailType) {
        case 'confirmation':
            $subject = "Redemption Confirmation - Ref: {$referenceNumber}";
            $statusBadge = '<span style="background: #ff9800; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold;">PENDING APPROVAL</span>';
            $headerColor = "#ff9800";
            $message = "Your redemption request has been received and is pending approval.";
            break;
            
        case 'approved':
            $subject = "Redemption Approved - Ref: {$referenceNumber}";
            $statusBadge = '<span style="background: #4caf50; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold;">APPROVED</span>';
            $headerColor = "#4caf50";
            $message = "Great news! Your redemption has been approved. Please claim your items at the office.";
            break;
            
        case 'rejected':
            $subject = "Redemption Rejected - Ref: {$referenceNumber}";
            $statusBadge = '<span style="background: #f44336; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold;">REJECTED</span>';
            $headerColor = "#f44336";
            $message = "We regret to inform you that your redemption request has been rejected.";
            if ($rejectionReason) {
                $message .= "<br><strong>Reason:</strong> " . htmlspecialchars($rejectionReason);
            }
            break;
            
        default:
            return false;
    }
    
    // Build items table HTML
    $itemsTableHTML = '';
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['item_name']);
        $quantity = intval($item['quantity']);
        $unitPrice = intval($item['points_required']);
        $subtotal = intval($item['subtotal']);
        
        $itemsTableHTML .= "
        <tr>
            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0;'>{$itemName}</td>
            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: center;'>{$quantity}</td>
            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: right;'>{$unitPrice} pts</td>
            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: right; font-weight: bold;'>{$subtotal} pts</td>
        </tr>
        ";
    }
    
    // Build email HTML
    $emailHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$subject}</title>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;'>
        <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f5f5f5; padding: 20px;'>
            <tr>
                <td align='center'>
                    <table width='600' cellpadding='0' cellspacing='0' style='background-color: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, {$headerColor} 0%, " . adjustColor($headerColor, -20) . " 100%); padding: 30px; text-align: center;'>
                                <div style='font-size: 24px; font-weight: bold; margin-bottom: 10px; color: white;'>
                                    <div class='icon'><i class='fas fa-leaf'></i> <strong>ManGrow</strong></div>
                                </div>
                                <h1 style='margin: 0; color: white; font-size: 26px;'>Redemption Ticket</h1>
                            </td>
                        </tr>
                        
                        <!-- Status Badge -->
                        <tr>
                            <td style='padding: 20px; text-align: center; background-color: #fafafa;'>
                                {$statusBadge}
                            </td>
                        </tr>
                        
                        <!-- Message -->
                        <tr>
                            <td style='padding: 30px;'>
                                <p style='margin: 0 0 10px 0; font-size: 16px; color: #333;'>Dear {$recipientName},</p>
                                <p style='margin: 0 0 20px 0; font-size: 14px; color: #666; line-height: 1.6;'>{$message}</p>
                            </td>
                        </tr>
                        
                        <!-- Reference Number -->
                        <tr>
                            <td style='padding: 0 30px 20px 30px;'>
                                <div style='background-color: #f5f5f5; padding: 20px; border-radius: 8px; text-align: center;'>
                                    <p style='margin: 0 0 5px 0; font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 1px;'>Reference Number</p>
                                    <p style='margin: 0; font-size: 24px; color: {$headerColor}; font-weight: bold; letter-spacing: 2px;'>{$referenceNumber}</p>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Items Table -->
                        <tr>
                            <td style='padding: 0 30px 30px 30px;'>
                                <h2 style='margin: 0 0 15px 0; font-size: 18px; color: #333;'>Items Summary</h2>
                                <table width='100%' cellpadding='0' cellspacing='0' style='border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background-color: #fafafa;'>
                                            <th style='padding: 12px; text-align: left; font-size: 12px; color: #666; text-transform: uppercase;'>Item</th>
                                            <th style='padding: 12px; text-align: center; font-size: 12px; color: #666; text-transform: uppercase;'>Qty</th>
                                            <th style='padding: 12px; text-align: right; font-size: 12px; color: #666; text-transform: uppercase;'>Unit Price</th>
                                            <th style='padding: 12px; text-align: right; font-size: 12px; color: #666; text-transform: uppercase;'>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$itemsTableHTML}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background-color: #fafafa;'>
                                            <td colspan='3' style='padding: 15px; text-align: right; font-weight: bold; font-size: 16px;'>Total:</td>
                                            <td style='padding: 15px; text-align: right; font-weight: bold; font-size: 18px; color: {$headerColor};'>{$totalPoints} pts</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </td>
                        </tr>
                        
                        <!-- Balance Computation Section (for approved emails only) -->
                        " . ($balanceInfo && $emailType === 'approved' ? "
                        <tr>
                            <td style='padding: 0 30px 30px 30px;'>
                                <h2 style='margin: 0 0 15px 0; font-size: 18px; color: #333;'>💰 Eco Points Balance</h2>
                                <table width='100%' cellpadding='0' cellspacing='0' style='border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
                                    <tbody>
                                        <tr>
                                            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; font-size: 14px; color: #666;'>Previous Balance:</td>
                                            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: right; font-size: 16px; font-weight: bold; color: #333;'>" . number_format($balanceInfo['previous_balance']) . " pts</td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; font-size: 14px; color: #666;'>Points Used:</td>
                                            <td style='padding: 12px; border-bottom: 1px solid #e0e0e0; text-align: right; font-size: 16px; font-weight: bold; color: #f44336;'>- " . number_format($balanceInfo['points_used']) . " pts</td>
                                        </tr>
                                        <tr style='background-color: #e8f5e9;'>
                                            <td style='padding: 15px; font-size: 16px; font-weight: bold; color: #2e7d32;'>Remaining Balance:</td>
                                            <td style='padding: 15px; text-align: right; font-size: 20px; font-weight: bold; color: #2e7d32;'>" . number_format($balanceInfo['new_balance']) . " pts</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        " : "") . "
                        
                        <!-- Download Receipt Button (for approved emails only) -->
                        " . ($emailType === 'approved' ? "
                        <tr>
                            <td style='padding: 0 30px 20px 30px; text-align: center;'>
                                <a href='http://localhost/project/generate_receipt_pdf_v2.php?ref=" . urlencode($referenceNumber) . "' 
                                   style='display: inline-block; background: linear-gradient(135deg, #2E8B57 0%, #1e5f3d 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);'>
                                    📄 Download Receipt PDF
                                </a>
                                <p style='margin: 10px 0 0 0; font-size: 12px; color: #666;'>Click to download your redemption ticket</p>
                            </td>
                        </tr>
                        " : "") . "
                        
                        <!-- Instructions -->
                        <tr>
                            <td style='padding: 0 30px 30px 30px;'>
                                " . getInstructionsHTML($emailType) . "
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #fafafa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                                <p style='margin: 0 0 5px 0; font-size: 12px; color: #999;'>This is an automated email. Please do not reply.</p>
                                <p style='margin: 0; font-size: 12px; color: #999;'>© 2024 ManGrow Eco Shop. All rights reserved.</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";
    
    // Use PHPMailer for reliable email sending
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
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $emailHTML;
        
        // Send
        $mail->send();
        error_log("sendRedemptionEmail: Email sent successfully to {$recipientEmail}");
        return true;
    } catch (Exception $e) {
        error_log("sendRedemptionEmail: Email send failed - " . $mail->ErrorInfo);
        return false;
    }
}

// Helper function to adjust color brightness
function adjustColor($hexColor, $percent) {
    $hexColor = ltrim($hexColor, '#');
    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// Helper function to get instructions based on email type
function getInstructionsHTML($emailType) {
    switch ($emailType) {
        case 'confirmation':
            return "
            <div style='background-color: #fff3e0; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800;'>
                <h3 style='margin: 0 0 10px 0; font-size: 16px; color: #f57c00;'>Next Steps:</h3>
                <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #666; line-height: 1.8;'>
                    <li>Your redemption is pending admin approval</li>
                    <li>You will receive a notification once approved</li>
                    <li>Keep this reference number for claiming your items</li>
                    <li>Processing time: 1-2 business days</li>
                </ul>
            </div>
            ";
            
        case 'approved':
            return "
            <div style='background-color: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #4caf50;'>
                <h3 style='margin: 0 0 10px 0; font-size: 16px; color: #2e7d32;'>Claim Your Items:</h3>
                <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #666; line-height: 1.8;'>
                    <li>Visit the ManGrow office during office hours</li>
                    <li>Present this reference number to claim your items</li>
                    <li>Bring a valid ID for verification</li>
                    <li>Items must be claimed within 7 days</li>
                </ul>
            </div>
            ";
            
        case 'rejected':
            return "
            <div style='background-color: #ffebee; padding: 15px; border-radius: 8px; border-left: 4px solid #f44336;'>
                <h3 style='margin: 0 0 10px 0; font-size: 16px; color: #c62828;'>What Happens Now:</h3>
                <ul style='margin: 0; padding-left: 20px; font-size: 14px; color: #666; line-height: 1.8;'>
                    <li>Your eco points have been refunded</li>
                    <li>You can try redeeming other items</li>
                    <li>Contact support for more information</li>
                </ul>
            </div>
            ";
            
        default:
            return '';
    }
}
?>
