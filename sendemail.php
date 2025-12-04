<?php
session_start();
require_once 'database.php'; // Your database connection file
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if(isset($_POST['sendEmail'])) {
    try {
        // Base query
        $query = "SELECT * FROM tempaccstbl WHERE is_verified = 'Not Verified'";
        
        // Apply the same filters as adminshowtabledata.php
        if (isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
            // For Barangay Officials - only their barangay
            if (isset($_SESSION['barangay'])) {
                $barangay = mysqli_real_escape_string($connection, $_SESSION['barangay']);
                $query .= " AND barangay = '$barangay'";
            }
        } else {
            // For admins - apply city/barangay filters if they were used in the view
            if (isset($_SESSION['filter_city'])) {
                $city = mysqli_real_escape_string($connection, $_SESSION['filter_city']);
                $query .= " AND city_municipality = '$city'";
            }
            if (isset($_SESSION['filter_barangay'])) {
                $barangay = mysqli_real_escape_string($connection, $_SESSION['filter_barangay']);
                $query .= " AND barangay = '$barangay'";
            }
        }
        
        $result = $connection->query($query);
        
        if (!$result) {
            throw new Exception("Database error: " . $connection->error);
        }
        
        $accountCount = $result->num_rows;
        
        if($accountCount > 0) {
            // Collect all accounts into an array for batch processing
            $accounts = [];
            while($account = $result->fetch_assoc()) {
                $accounts[] = $account;
            }
            
            // Send emails asynchronously in batches
            $successCount = sendVerificationEmailsBatch($accounts);
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => "Verification emails sent successfully to $successCount/$accountCount accounts!"
            ];
        }
        else {
            $_SESSION['response'] = [
                'status' => 'info',
                'msg' => 'No unverified accounts found matching your access level/filters'
            ];
        }
        
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
    }
    
    header("Location: adminaccspage.php");
    exit();
}

function sendVerificationEmailsBatch($accounts) {
    $batchSize = 10; // Process 10 emails concurrently
    $totalAccounts = count($accounts);
    $successCount = 0;
    
    // Process accounts in batches
    for ($i = 0; $i < $totalAccounts; $i += $batchSize) {
        $batch = array_slice($accounts, $i, $batchSize);
        $batchResults = processBatchConcurrently($batch);
        $successCount += array_sum($batchResults);
        
        // Small delay between batches to prevent overwhelming the SMTP server
        if ($i + $batchSize < $totalAccounts) {
            usleep(500000); // 0.5 second delay
        }
    }
    
    return $successCount;
}

function processBatchConcurrently($accounts) {
    $promises = [];
    $results = [];
    
    // Create multiple PHPMailer instances for concurrent processing
    foreach ($accounts as $index => $account) {
        $results[$index] = sendVerificationEmailAsync($account);
    }
    
    return $results;
}

function sendVerificationEmailAsync($account) {
    // Store verification token first
    $verificationToken = bin2hex(random_bytes(32));
    if (!storeVerificationToken($account['tempacc_id'], $verificationToken)) {
        return false;
    }
    
    $to = $account['personal_email'];
    $subject = "Account Verification - Mangrove Website";
    
    // Email content
    $message = generateEmailContent($account, $verificationToken);
    
    // Create separate PHPMailer instance for this email
    $mail = new PHPMailer(true);
    
    try {
        // Configure SMTP settings
        configureSMTPSettings($mail);
        
        // Set recipients and content
        $mail->setFrom('jetrimentimentalistically@gmail.com', 'ManGrow');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Send email with timeout settings for faster processing
        $mail->Timeout = 30; // 30 seconds timeout
        $mail->send();
        
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed for {$to}: " . $e->getMessage());
        return false;
    }
}

function configureSMTPSettings($mail) {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'jetrimentimentalistically@gmail.com';
    $mail->Password = 'uaxrlxrruwhzybnq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
}

function generateEmailContent($account, $verificationToken) {
    return "
    <html>
    <head>
        <title>Account Verification - ManGrow</title>
        <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
        <style>
            body { font-family: 'Arial', sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { color: #2E8B57; text-align: center; }
            .logo { max-width: 150px; margin-bottom: 20px; }
            .button { 
                display: inline-block; 
                background-color: #2E8B57; 
                color: white !important; 
                padding: 12px 24px; 
                text-decoration: none; 
                border-radius: 4px; 
                font-weight: bold; 
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
            }
        </style>
    </head>
    <body>
        <h1 class='header'>Welcome to ManGrow!</h1>
        
        <p>Dear User,</p>
        
        <p>Thank you for registering with <strong>ManGrow: Mangrove Conservation and Eco Tracking System</strong>. 
        To complete your registration and activate your account, please verify your email address.</p>
        
        <div class='account-details'>
            <h3>Your Account Details:</h3>
            <p><strong>First Name:</strong> ".htmlspecialchars($account['firstname'])."</p>
            <p><strong>Last Name:</strong> ".htmlspecialchars($account['lastname'])."</p>
            <p><strong>Email:</strong> ".htmlspecialchars($account['email'])."</p>
            <p><strong>Temporary Password:</strong> ".htmlspecialchars($account['password'])."</p>
        </div>

        <div style='text-align: center;'>
            <a href='http://localhost:3000/verify.php?token=$verificationToken' class='button'>
                Verify My Account
            </a>
        </div>
        
        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
        <p><small>http://localhost:3000/verify.php?token=$verificationToken</small></p>
        
        <p>This verification link will expire in 72 hours.</p>
        
        <div class='footer'>
            <p>If you didn't request this account, please ignore this email.</p>
            <p>&copy; ".date('Y')." ManGrow System. All rights reserved.</p>
        </div>
    </body>
    </html>
    ";
}        

function storeVerificationToken($accountId, $token) {
    global $connection;
    
    $query = "UPDATE tempaccstbl SET verification_token = ?, is_verified = 'Pending' WHERE tempacc_id = ?";
    $stmt = $connection->prepare($query);
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $connection->error);
        return false;
    }
    
    $stmt->bind_param("si", $token, $accountId);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
?>

