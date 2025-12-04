<?php
require 'database.php';
require 'badge_system_db.php'; // Include badge system
session_start();

// Initialize badge system
BadgeSystem::init($connection);

if(isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        // Find account with this token
        $query = "SELECT * FROM tempaccstbl WHERE verification_token = ?";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $account = $result->fetch_assoc();
            
            // Begin transaction
            $connection->begin_transaction();
            
            try {
                // Hash the password before storing
                $fullname = htmlspecialchars($account['firstname']) . ' ' . htmlspecialchars($account['lastname']);
                $hashedPassword = password_hash($account['password'], PASSWORD_DEFAULT);
                
                // Insert into main accounts table with hashed password
                $insertQuery = "INSERT INTO accountstbl (
                    fullname,
                    email, 
                    personal_email, 
                    password,
                    barangay, 
                    city_municipality, 
                    accessrole, 
                    organization,
                    date_registered
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $connection->prepare($insertQuery);
                $stmt->bind_param(
                    "ssssssss",
                    $fullname,
                    htmlspecialchars($account['email']),
                    htmlspecialchars($account['personal_email']),
                    $hashedPassword,
                    htmlspecialchars($account['barangay']),
                    htmlspecialchars($account['city_municipality']),
                    htmlspecialchars($account['accessrole']),
                    htmlspecialchars($account['organization'])
                );
                $stmt->execute();
                
                // Get the newly created account ID
                $newAccountId = $connection->insert_id;
                
                // Delete from temporary table
                $deleteQuery = "DELETE FROM tempaccstbl WHERE tempacc_id = ?";
                $stmt = $connection->prepare($deleteQuery);
                $stmt->bind_param("i", $account['tempacc_id']);
                $stmt->execute();
                
                // Log the registration activity
                $activityDetails = "New Account Registration Details:\n";
                $activityDetails .= "--------------------------------\n";
                $activityDetails .= "Name: " . $fullname . "\n";
                $activityDetails .= "Email: " . $account['email'] . "\n";
                $activityDetails .= "Personal Email: " . $account['personal_email'] . "\n";
                $activityDetails .= "Barangay: " . $account['barangay'] . "\n";
                $activityDetails .= "City/Municipality: " . $account['city_municipality'] . "\n";
                $activityDetails .= "Role: " . $account['accessrole'] . "\n";
                $activityDetails .= "Organization: " . $account['organization'] . "\n";
                $activityDetails .= "Registration Date: " . date('Y-m-d H:i:s') . "\n";
                $activityDetails .= "Verified From: Imported Account" . "\n"; // Or "Manual Registration" if different

                // Then in your activity log insertion:
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
                $affectedSource = 'tempaccstbl';
                $importCount = 1; // Since we're processing one registration

                $activityQuery->bind_param(
                    "sisissi",
                    $actionType,
                    $newAccountId,
                    $userSource,
                    $account['tempacc_id'],
                    $affectedSource,
                    $activityDetails,
                    $importCount
                );
                $activityQuery->execute();
                $activityQuery->close();
                
                // Award "Starting Point" badge to the new user
                $badgeAwarded = BadgeSystem::awardBadgeToUser($newAccountId, 'Starting Point');
                
                $connection->commit();
                
                // Set session for badge notification
                $_SESSION['new_badge_awarded'] = [
                    'badge_name' => 'Starting Point',
                    'badge_awarded' => $badgeAwarded,
                    'is_new_registration' => true
                ];
                
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => "Account verified and activated successfully! You can now login."
                ];
                
            } catch (Exception $e) {
                $connection->rollback();
                throw $e;
            }
            
            header("Location: login.php");
            exit();
            
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => "Invalid verification token or account already verified."
            ];
            header("Location: login.php");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => "Error during verification: " . $e->getMessage()
        ];
        header("Location: login.php");
        exit();
    }
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => "No verification token provided."
    ];
    header("Location: login.php");
    exit();
}
?>