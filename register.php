<?php
session_start();
include 'database.php';
require_once 'getdropdown.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $personal_email = trim($_POST['personal_email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Handle location inputs (either from dropdown or manual input)
    if (!empty($_POST['manual_city']) && !empty($_POST['manual_barangay'])) {
        // Manual input was used
        $city_municipality = trim($_POST['manual_city']);
        $barangay = trim($_POST['manual_barangay']);
    } else {
        // Dropdown was used
        $city_municipality = $_POST['city_municipality'];
        $barangay = $_POST['barangay'];
    }
    
    $errors = [];
    
    // Validation
    if (empty($firstname)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastname)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($city_municipality)) {
        $errors[] = "City/Municipality is required";
    }
    
    if (empty($barangay)) {
        $errors[] = "Barangay is required";
    }
    
    if (empty($personal_email)) {
        $errors[] = "Personal email is required";
    } elseif (!filter_var($personal_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if personal email already exists in either tempaccstbl or accountstbl
    if (empty($errors)) {
        $email_check_query = "SELECT personal_email FROM tempaccstbl WHERE personal_email = ? 
                             UNION 
                             SELECT personal_email FROM accountstbl WHERE personal_email = ?";
        $stmt = $connection->prepare($email_check_query);
        $stmt->bind_param("ss", $personal_email, $personal_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "This email is already registered";
        }
    }
    
    if (empty($errors)) {
        // Generate email and verification token (remove spaces only for email generation)
        $firstname_clean = str_replace(' ', '', $firstname);
        $lastname_clean = str_replace(' ', '', $lastname);
        $email = strtolower($firstname_clean) . "." . strtolower($lastname_clean) . "@mangrow.com";
        $verification_token = bin2hex(random_bytes(32));
        $fullname = $firstname . " " . $lastname;
        
        // Set timezone to Philippines
        date_default_timezone_set('Asia/Manila');
        $date_registered = date('Y-m-d H:i:s');
        $token_expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create verification record (temporary storage for 5 minutes) - Keep original names with spaces
        $verify_query = "INSERT INTO user_verification (firstname, lastname, email, personal_email, password, 
                        barangay, city_municipality, verification_token, token_expiry, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $connection->prepare($verify_query);
        $stmt->bind_param("ssssssssss", $firstname, $lastname, $email, $personal_email, 
                         $hashed_password, $barangay, $city_municipality, $verification_token, 
                         $token_expiry, $date_registered);
        
        if ($stmt->execute()) {
            // Send verification email
            if (sendVerificationEmail($personal_email, $fullname, $email, $password, $verification_token)) {
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Registration successful! Please check your email for verification instructions. The verification link will expire in 5 minutes.'
                ];
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['response'] = [
                    'status' => 'error',
                    'msg' => 'Registration successful but failed to send verification email. Please contact support.'
                ];
            }
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Registration failed. Please try again.'
            ];
        }
    } else {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => implode(", ", $errors)
        ];
    }
}

function sendVerificationEmail($to, $fullname, $email, $password, $token) {
    $subject = "Account Verification - ManGrow Registration";
    
    $message = "
    <html>
    <head>
        <title>Account Verification - ManGrow</title>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                line-height: 1.6; 
                color: #333; 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
            }
            .header { 
                color: #2E8B57; 
                text-align: center; 
                margin-bottom: 30px;
            }
            .account-details {
                background-color: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #2E8B57;
            }
            .button { 
                display: inline-block; 
                background-color: #2E8B57; 
                color: white !important; 
                padding: 15px 30px; 
                text-decoration: none; 
                border-radius: 6px; 
                font-weight: bold; 
                margin: 20px 0;
                text-align: center;
            }
            .warning {
                background-color: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 6px;
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
        <div class='header'>
            <h1>🌱 Welcome to ManGrow!</h1>
            <p>Mangrove Conservation and Eco Tracking System</p>
        </div>
        
        <p>Dear <strong>" . htmlspecialchars($fullname) . "</strong>,</p>
        
        <p>Thank you for registering with ManGrow! To complete your registration and activate your account, 
        please verify your email address by clicking the button below.</p>
        
        <div class='account-details'>
            <h3>🔐 Your Account Details:</h3>
            <p><strong>Full Name:</strong> " . htmlspecialchars($fullname) . "</p>
            <p><strong>ManGrow Email:</strong> " . htmlspecialchars($email) . "</p>
            <p><strong>Personal Email:</strong> " . htmlspecialchars($to) . "</p>
            <p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>
        </div>

        <div style='text-align: center; margin: 30px 0;'>
            <a href='http://mangrow.42web.io/verify_registration.php?token=$token' class='button'>
                ✅ Verify My Account
            </a>
        </div>
        
        <div class='warning'>
            <strong>⚠️ Important:</strong> This verification link will expire in <strong>5 minutes</strong>. 
            If the link expires, you will need to register again.
        </div>
        
        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
        <p style='word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px;'>
            <small>http://mangrow.42web.io/verify_registration.php?token=$token</small>
        </p>
        
        <p>After verification, you can login using your ManGrow email (<strong>$email</strong>) and the password you provided.</p>
        
        <div class='footer'>
            <p>If you didn't request this account, please ignore this email.</p>
            <p>&copy; " . date('Y') . " ManGrow System. All rights reserved.</p>
            <p>🌱 Let's plant our future together! 🌱</p>
        </div>
    </body>
    </html>
    ";

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'jetrimentimentalistically@gmail.com';
        $mail->Password = 'uaxrlxrruwhzybnq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('jetrimentimentalistically@gmail.com', 'ManGrow System');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ManGrow</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .register-container {
            position: absolute;
            top: 52%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            background: rgba(239, 227, 194, 0.95);
            border-radius: 10px;
            padding: 40px;
            color: var(--text-clr);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .register-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--base-clr);
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .input-box {
            flex: 1;
        }
        
        .input-box {
            position: relative;
            margin: 20px 0;
        }
        
        .input-box input,
        .input-box select {
            width: 100%;
            height: 50px;
            background: transparent;
            border: none;
            border-bottom: 2px solid var(--line-clr);
            outline: none;
            font-size: 16px;
            color: var(--text-clr);
            font-weight: 600;
            padding-right: 28px;
            padding-top: 10px;
        }
        
        .input-box label {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            font-size: 16px;
            font-weight: 500;
            pointer-events: none;
            transition: 0.5s ease;
        }
        
        .input-box input:focus ~ label,
        .input-box input:valid ~ label,
        .input-box input:not(:placeholder-shown) ~ label,
        .input-box select ~ label,
        .input-box select ~ label {
            top: -5px;
        }
        
        .input-box .icon {
            position: absolute;
            right: 0;
            top: 13px;
            font-size: 20px;
        }
        
        .register-btn {
            width: 100%;
            height: 45px;
            background: var(--base-clr);
            border: none;
            outline: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            color: white;
            font-weight: 600;
            margin-top: 20px;
            box-shadow: 0 3px 0 0 rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .register-btn:hover {
            background-color: var(--placeholder-text-clr);
        }
        
        .register-btn:active {
            transform: translateY(3px);
            box-shadow: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .login-link a {
            color: var(--text-clr);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            color: var(--placeholder-text-clr);
            text-decoration: underline;
        }
        
        .manual-input-checkbox {
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .manual-input-checkbox input[type="checkbox"] {
            width: auto;
            height: auto;
            accent-color: var(--base-clr);
        }
        
        .manual-input-checkbox label {
            position: static;
            transform: none;
            font-size: 14px;
            cursor: pointer;
        }
        
        .manual-inputs {
            display: none;
        }
        
        .manual-inputs.show {
            display: block;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="background register"></div>
    
    <?php if(!empty($_SESSION['response'])): ?>
    <div class="flash-container">
        <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
            <?= $_SESSION['response']['msg'] ?>
        </div>
    </div>
    <?php 
    unset($_SESSION['response']); 
    endif; 
    ?>
    
    <div class="returnbtn">
        <button type="button" name="backbtn" onclick="window.location.href='index.php';">X</button>
    </div>
    
    <div class="register-container">
        <h2><i class='bx bxs-leaf'></i> Join ManGrow</h2>
        <p style="text-align: center; margin-bottom: 30px; color: #666;">Create your account to start protecting our mangroves</p>
        
        <form action="" method="post" autocomplete="off">
            <div class="form-row">
                <div class="input-box">
                    <span class="icon"><i class='bx bxs-user'></i></span>
                    <input type="text" name="firstname" placeholder=" " required>
                    <label>First Name</label>
                </div>
                <div class="input-box">
                    <span class="icon"><i class='bx bxs-user'></i></span>
                    <input type="text" name="lastname" placeholder=" " required>
                    <label>Last Name</label>
                </div>
            </div>
            
            <div class="input-box" id="city-dropdown">
                <span class="icon"><i class='bx bxs-map'></i></span>
                <select name="city_municipality" id="city-select" onchange="updateBarangayDropdown()">
                    <option value="">Select City/Municipality</option>
                    <?php
                    $cities = getcitymunicipality();
                    foreach ($cities as $city) {
                        echo '<option value="' . htmlspecialchars($city['city']) . '">' . htmlspecialchars($city['city']) . '</option>';
                    }
                    ?>
                </select>
                <label>City/Municipality</label>
            </div>
            
            <div class="input-box" id="barangay-dropdown">
                <span class="icon"><i class='bx bxs-home'></i></span>
                <select name="barangay" id="barangay-select">
                    <option value="">Select Barangay</option>
                </select>
                <label>Barangay</label>
            </div>
            
            <div class="manual-input-checkbox">
                <input type="checkbox" id="manual-location" onchange="toggleManualInput()">
                <label for="manual-location">My location is not listed above</label>
            </div>
            
            <div class="manual-inputs" id="manual-inputs">
                <div class="input-box">
                    <span class="icon"><i class='bx bxs-map'></i></span>
                    <input type="text" name="manual_city" id="manual-city" placeholder=" ">
                    <label>Enter City/Municipality</label>
                </div>
                
                <div class="input-box">
                    <span class="icon"><i class='bx bxs-home'></i></span>
                    <input type="text" name="manual_barangay" id="manual-barangay" placeholder=" ">
                    <label>Enter Barangay</label>
                </div>
            </div>
            
            <div class="input-box">
                <span class="icon"><i class='bx bxs-envelope'></i></span>
                <input type="email" name="personal_email" placeholder=" " required>
                <label>Personal Email</label>
            </div>
            
            <div class="input-box">
                <span class="icon"><i class='bx bxs-lock-alt'></i></span>
                <input type="password" name="password" placeholder=" " required minlength="6">
                <label>Password</label>
            </div>
            
            <div class="input-box">
                <span class="icon"><i class='bx bxs-lock-alt'></i></span>
                <input type="password" name="confirm_password" placeholder=" " required>
                <label>Confirm Password</label>
            </div>
            
            <button type="submit" class="register-btn">Register Account</button>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </form>
    </div>

    <script>
        function toggleManualInput() {
            const checkbox = document.getElementById('manual-location');
            const manualInputs = document.getElementById('manual-inputs');
            const cityDropdown = document.getElementById('city-dropdown');
            const barangayDropdown = document.getElementById('barangay-dropdown');
            const citySelect = document.getElementById('city-select');
            const barangaySelect = document.getElementById('barangay-select');
            const manualCity = document.getElementById('manual-city');
            const manualBarangay = document.getElementById('manual-barangay');
            
            if (checkbox.checked) {
                // Show manual inputs
                manualInputs.classList.add('show');
                cityDropdown.classList.add('hidden');
                barangayDropdown.classList.add('hidden');
                
                // Remove required from dropdowns and add to manual inputs
                citySelect.removeAttribute('required');
                barangaySelect.removeAttribute('required');
                manualCity.setAttribute('required', '');
                manualBarangay.setAttribute('required', '');
                
                // Clear dropdown values
                citySelect.value = '';
                barangaySelect.value = '';
            } else {
                // Hide manual inputs
                manualInputs.classList.remove('show');
                cityDropdown.classList.remove('hidden');
                barangayDropdown.classList.remove('hidden');
                
                // Add required back to dropdowns and remove from manual inputs
                citySelect.setAttribute('required', '');
                barangaySelect.setAttribute('required', '');
                manualCity.removeAttribute('required');
                manualBarangay.removeAttribute('required');
                
                // Clear manual input values
                manualCity.value = '';
                manualBarangay.value = '';
            }
        }
        
        function updateBarangayDropdown() {
            const citySelect = document.getElementById('city-select');
            const barangaySelect = document.getElementById('barangay-select');
            const selectedCity = citySelect.value;
            
            // Clear current barangay options except the first one
            while (barangaySelect.options.length > 1) {
                barangaySelect.remove(1);
            }
            
            // If no city is selected, keep the barangay dropdown with only "Select Barangay"
            if (!selectedCity) {
                return;
            }
            
            // Fetch barangays for the selected city
            fetch(`getdropdown.php?city=${encodeURIComponent(selectedCity)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching barangays:', data.error);
                        return;
                    }
                    
                    // Add barangay options to the dropdown
                    data.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.barangay;
                        option.textContent = barangay.barangay;
                        barangaySelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
        
        // Validate passwords match
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        });
    </script>
</body>
</html>
