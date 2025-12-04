<?php
session_start();
include 'database.php';
include 'badge_system_db.php'; // Include badge system

// Initialize badge system
BadgeSystem::init($connection);

if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid verification link'
    ];
    header("Location: login.php");
    exit();
}

$token = $_GET['token'];

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');
$current_time = date('Y-m-d H:i:s');

// Check if token exists and is not expired
$verify_query = "SELECT * FROM user_verification WHERE verification_token = ? AND token_expiry > ?";
$stmt = $connection->prepare($verify_query);
$stmt->bind_param("ss", $token, $current_time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Verification link has expired or is invalid. Please register again.'
    ];
    header("Location: register.php");
    exit();
}

$user_data = $result->fetch_assoc();

// Generate unique ManGrow email
function generateUniqueEmail($firstname, $lastname, $connection) {
    // Remove all spaces from names for email generation
    $firstname_clean = str_replace(' ', '', $firstname);
    $lastname_clean = str_replace(' ', '', $lastname);
    
    $base_email = strtolower($firstname_clean) . "." . strtolower($lastname_clean) . "@mangrow.com";
    
    // Check if base email exists
    $check_query = "SELECT email FROM accountstbl WHERE email = ?";
    $stmt = $connection->prepare($check_query);
    $stmt->bind_param("s", $base_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        // Base email is available
        return $base_email;
    }
    
    // Base email exists, find next available number
    $counter = 2;
    while (true) {
        $numbered_email = strtolower($firstname_clean) . "." . strtolower($lastname_clean) . $counter . "@mangrow.com";
        
        $stmt = $connection->prepare($check_query);
        $stmt->bind_param("s", $numbered_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // This numbered email is available
            return $numbered_email;
        }
        
        $counter++;
        
        // Safety check to prevent infinite loop
        if ($counter > 9999) {
            throw new Exception("Unable to generate unique email");
        }
    }
}

// Begin transaction
$connection->begin_transaction();

try {
    // Generate unique ManGrow email
    $unique_email = generateUniqueEmail($user_data['firstname'], $user_data['lastname'], $connection);
    
    // Insert into accountstbl
    $insert_query = "INSERT INTO accountstbl (fullname, email, personal_email, password, barangay, 
                    city_municipality, accessrole, date_registered) 
                    VALUES (?, ?, ?, ?, ?, ?, 'Resident', ?)";
    
    $fullname = $user_data['firstname'] . ' ' . $user_data['lastname'];
    $stmt = $connection->prepare($insert_query);
    $stmt->bind_param("sssssss", $fullname, $unique_email, $user_data['personal_email'], 
                     $user_data['password'], $user_data['barangay'], $user_data['city_municipality'], 
                     $user_data['created_at']);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create account");
    }
    
    // Get the newly created account ID
    $newAccountId = $connection->insert_id;
    
    // Log the registration activity
    $activityDetails = "New Account Registration Details:\n";
    $activityDetails .= "--------------------------------\n";
    $activityDetails .= "Name: " . $fullname . "\n";
    $activityDetails .= "Email: " . $unique_email . "\n";
    $activityDetails .= "Personal Email: " . $user_data['personal_email'] . "\n";
    $activityDetails .= "Barangay: " . $user_data['barangay'] . "\n";
    $activityDetails .= "City/Municipality: " . $user_data['city_municipality'] . "\n";
    $activityDetails .= "Role: Resident\n";
    $activityDetails .= "Organization: N/A\n";
    $activityDetails .= "Registration Date: " . date('Y-m-d H:i:s') . "\n";
    $activityDetails .= "Verified From: User Registration\n";

    // Insert activity log
    $activityQuery = $connection->prepare("
        INSERT INTO account_activitytbl (
            activity_date, 
            action_type, 
            user_id,
            user_role,
            affected_account_id, 
            affected_account_source, 
            activity_details,
            import_count
        ) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?)
    ");

    $userSource = 'accountstbl';
    $actionType = 'Registered';
    $affectedSource = 'accountstbl';
    $importCount = 1; // Since we're processing one registration

    $activityQuery->bind_param(
        "sisissi",
        $actionType,
        $newAccountId,
        $userSource,
        $newAccountId, // For user registration, the affected account is the same as the user
        $affectedSource,
        $activityDetails,
        $importCount
    );
    $activityQuery->execute();
    $activityQuery->close();
    
    // Award "Starting Point" badge to the new user
    $badgeAwarded = BadgeSystem::awardBadgeToUser($newAccountId, 'Starting Point');
    
    // Delete the verification record
    $delete_query = "DELETE FROM user_verification WHERE verification_token = ?";
    $stmt = $connection->prepare($delete_query);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    // Commit transaction
    $connection->commit();
    
    // Set session for badge notification
    $_SESSION['new_badge_awarded'] = [
        'badge_name' => 'Starting Point',
        'badge_awarded' => $badgeAwarded,
        'is_new_registration' => true
    ];
    
    $_SESSION['response'] = [
        'status' => 'success',
        'msg' => "Account verified successfully! Your ManGrow email is: $unique_email. You can now login with this email and your password."
    ];
    header("Location: login.php");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction
    $connection->rollback();
    
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Account verification failed. Please try registering again.'
    ];
    header("Location: register.php");
    exit();
}
?>
