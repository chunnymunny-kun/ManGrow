<?php
session_start();
require_once 'database.php';

// Function to get Philippine time
function getPhilippineTime() {
    $date = new DateTime("now", new DateTimeZone('Asia/Manila'));
    return $date->format('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tempacc_id = $_POST['id'] ?? null;

    if (!$tempacc_id) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'No account ID provided'
        ];
        header("Location: adminaccspage.php");
        exit;
    }

    // Authorization check
    if (!isset($_SESSION['accessrole'])) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Please login first'
        ];
        header("Location: index.php");
        exit;
    }

    if (!in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Unauthorized access'
        ];
        header("Location: adminaccspage.php");
        exit;
    }

    // Begin transaction
    $connection->begin_transaction();

    try {
        // First get account details for logging and archiving
        $accountQuery = $connection->prepare("SELECT * FROM tempaccstbl WHERE tempacc_id = ?");
        $accountQuery->bind_param("i", $tempacc_id);
        $accountQuery->execute();
        $accountResult = $accountQuery->get_result();
        $account = $accountResult->fetch_assoc();
        $accountQuery->close();

        if (!$account) {
            throw new Exception("Account not found");
        }

        // Archive the account first
        $archiveStmt = $connection->prepare("
            INSERT INTO tempaccs_archive (
                original_id, firstname, lastname, email, personal_email, 
                barangay, city_municipality, accessrole, organization, 
                is_verified, date_created, date_deleted, deleted_by, 
                deleted_by_role, deletion_reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $phTime = getPhilippineTime();
        $deletionReason = "Admin deletion";
        $archiveStmt->bind_param(
            "issssssssssssss",
            $account['tempacc_id'],
            $account['firstname'],
            $account['lastname'],
            $account['email'],
            $account['personal_email'],
            $account['barangay'],
            $account['city_municipality'],
            $account['accessrole'],
            $account['organization'],
            $account['is_verified'],
            $account['import_date'],
            $phTime,
            $_SESSION['name'],
            $_SESSION['accessrole'],
            $deletionReason
        );
        
        if (!$archiveStmt->execute()) {
            throw new Exception("Failed to archive account: " . $archiveStmt->error);
        }
        $archiveStmt->close();

        // Now delete from the original table
        $deleteStmt = $connection->prepare("DELETE FROM tempaccstbl WHERE tempacc_id = ?");
        $deleteStmt->bind_param("i", $tempacc_id);

        if ($deleteStmt->execute()) {
            // Log the deletion activity
            $actionType = 'Deleted';
            $userSource = (in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) ? 'adminaccountstbl' : 'accountstbl';
            $activityDetails = "Archived Temporary Account Details:\n";
            $activityDetails .= "----------------------------------\n";
            $activityDetails .= "Archived by: " . $_SESSION['accessrole'] . " " . $_SESSION['name'] . "\n";
            $activityDetails .= "Archive Date: " . $phTime . "\n";
            $activityDetails .= "\nAccount Information:\n";
            $activityDetails .= "First Name: " . $account['firstname'] . "\n";
            $activityDetails .= "Last Name: " . $account['lastname'] . "\n";
            $activityDetails .= "Email: " . $account['email'] . "\n";
            $activityDetails .= "Personal Email: " . $account['personal_email'] . "\n";
            $activityDetails .= "Barangay: " . $account['barangay'] . "\n";
            $activityDetails .= "City/Municipality: " . $account['city_municipality'] . "\n";
            $activityDetails .= "Access Role: " . $account['accessrole'] . "\n";
            $activityDetails .= "Organization: " . $account['organization'] . "\n";
            $activityDetails .= "Verification Status: " . $account['is_verified'] . "\n";
            $affectedSource = 'tempaccstbl';

            $activityQuery = $connection->prepare("
                INSERT INTO account_activitytbl (
                    activity_date, 
                    action_type, 
                    user_id, 
                    user_role, 
                    affected_account_id, 
                    affected_account_source, 
                    activity_details
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $activityQuery->bind_param(
                "ssissss",
                $phTime,
                $actionType,
                $_SESSION['user_id'],
                $userSource,
                $tempacc_id,
                $affectedSource,
                $activityDetails
            );
            
            if (!$activityQuery->execute()) {
                throw new Exception("Failed to log activity: " . $connection->error);
            }
            
            $connection->commit();
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Unverified account archived successfully'
            ];
        } else {
            throw new Exception("Failed to delete account: " . $deleteStmt->error);
        }
        
        $deleteStmt->close();
    } catch (Exception $e) {
        $connection->rollback();
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
    }
} else {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Invalid request method'
    ];
}

header("Location: adminaccspage.php");
exit;
?>