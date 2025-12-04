<?php
session_start();
require_once 'database.php';

// Function to get Philippine time
function getPhilippineTime() {
    $date = new DateTime("now", new DateTimeZone('Asia/Manila'));
    return $date->format('Y-m-d H:i:s');
}

// Check authorization
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Representative', 'Barangay Official'])) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Unauthorized access'
    ];
    header("Location: adminaccspage.php");
    exit;
}

// Get account ID from URL
$account_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$account_id) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'No account ID provided'
    ];
    header("Location: adminaccspage.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_data = [
        'fullname' => $_POST['fullname'],
        'email' => $_POST['email'],
        'personal_email' => $_POST['personal_email'],
        'barangay' => $_POST['barangay'],
        'city_municipality' => $_POST['city_municipality'],
        'accessrole' => $_POST['accessrole'],
        'organization' => $_POST['organization'],
        'bio' => $_POST['bio']
    ];

    // Begin transaction
    $connection->begin_transaction();

    try {
        // First get original values for logging
        $originalStmt = $connection->prepare("SELECT * FROM accountstbl WHERE account_id = ?");
        $originalStmt->bind_param("i", $account_id);
        $originalStmt->execute();
        $originalResult = $originalStmt->get_result();
        $originalAccount = $originalResult->fetch_assoc();
        $originalStmt->close();

        // Prepare update statement
        $stmt = $connection->prepare("UPDATE accountstbl SET 
            fullname = ?, 
            email = ?, 
            personal_email = ?, 
            barangay = ?, 
            city_municipality = ?, 
            accessrole = ?, 
            organization = ?,
            bio = ?
            WHERE account_id = ?");

        $stmt->bind_param("ssssssssi", 
            $update_data['fullname'],
            $update_data['email'],
            $update_data['personal_email'],
            $update_data['barangay'],
            $update_data['city_municipality'],
            $update_data['accessrole'],
            $update_data['organization'],
            $update_data['bio'],
            $account_id
        );

        if ($stmt->execute()) {
            // Log the editing activity
            $phTime = getPhilippineTime();
            $userSource = (in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) ? 'adminaccountstbl' : 'accountstbl';
            $actionType = 'Edited';
            $affectedSource = 'accountstbl';
            
            // Build change details
            $activityDetails = "Account Edit Details:\n";
            $activityDetails .= "----------------------------\n";
            $activityDetails .= "Edited by: " . $_SESSION['accessrole'] . " " . $_SESSION['name'] . "\n";
            $activityDetails .= "Edit Date: " . $phTime . "\n";
            $activityDetails .= "Account Email: " . $originalAccount['email'] . "\n";
            $activityDetails .= "\nChanges:\n";

            foreach ($update_data as $field => $newValue) {
                if ($originalAccount[$field] != $newValue) {
                    $fieldName = ucwords(str_replace('_', ' ', $field));
                    $activityDetails .= $fieldName . ": " . $originalAccount[$field] . " → " . $newValue . "\n";
                }
            }

            $activityDetails .= "\nAccount Type: Verified";
            
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
                $account_id,
                $affectedSource,
                $activityDetails
            );
            
            if (!$activityQuery->execute()) {
                throw new Exception("Failed to log activity: " . $connection->error);
            }
            
            $connection->commit();
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Account updated successfully.'
            ];
            header("Location: adminaccspage.php");
            exit;
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
    } catch (Exception $e) {
        $connection->rollback();
        $error = $e->getMessage();
    }
    $stmt->close();
}

// Fetch account data
$stmt = $connection->prepare("SELECT * FROM accountstbl WHERE account_id = ?");
$stmt->bind_param("i", $account_id);
$stmt->execute();
$result = $stmt->get_result();
$account = $result->fetch_assoc();
$stmt->close();

if (!$account) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Account not found.'
    ];
    header("Location: adminaccspage.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Verified Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit Verified Account</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="fullname" 
                           value="<?= htmlspecialchars($account['fullname']) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" 
                           value="<?= htmlspecialchars($account['email']) ?>"  readonly>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Personal Email</label>
                    <input type="email" class="form-control" name="personal_email" 
                           value="<?= htmlspecialchars($account['personal_email']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Barangay</label>
                    <input type="text" class="form-control" name="barangay" 
                           value="<?= htmlspecialchars($account['barangay']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">City/Municipality</label>
                    <input type="text" class="form-control" name="city_municipality" 
                           value="<?= htmlspecialchars($account['city_municipality']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Access Role</label>
                    <select class="form-select" name="accessrole" required>
                        <option value="Barangay Official" <?= $account['accessrole'] === 'Barangay Official' ? 'selected' : '' ?>>Barangay Official</option>
                        <option value="Resident" <?= $account['accessrole'] === 'Resident' ? 'selected' : '' ?>>Resident</option>
                        <option value="Environmental Manager" <?= $account['accessrole'] === 'Environmental Manager' ? 'selected' : '' ?>>Environmental Manager</option>                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Organization</label>
                    <input type="text" class="form-control" name="organization" 
                           value="<?= htmlspecialchars($account['organization']) ?>">
                </div>
                
                <div class="col-12">
                    <label class="form-label">Bio</label>
                    <textarea class="form-control" name="bio" rows="3"><?= htmlspecialchars($account['bio']) ?></textarea>
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="adminaccspage.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>