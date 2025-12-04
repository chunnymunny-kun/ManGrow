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

// Handle form submission for regular accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_type']) && $_POST['account_type'] === 'regular') {
    $new_account = [
        'firstname' => $_POST['firstname'],
        'lastname' => $_POST['lastname'],
        'email' => $_POST['email'],
        'personal_email' => $_POST['personal_email'],
        'barangay' => $_POST['barangay'],
        'city_municipality' => $_POST['city_municipality'],
        'accessrole' => $_POST['accessrole'],
        'organization' => $_POST['organization'],
        'is_verified' => 'Not Verified',
        'import_date' => getPhilippineTime(),
        'imported_by' => $_SESSION['email'] ?? 'System',
        'password' => $_POST['password']
    ];

    // Begin transaction
    $connection->begin_transaction();

    try {
        // Insert into tempaccstbl
        $stmt = $connection->prepare("INSERT INTO tempaccstbl 
            (firstname, lastname, email, personal_email, barangay, city_municipality, 
             accessrole, organization, is_verified, import_date, imported_by, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssssssssss", 
            $new_account['firstname'],
            $new_account['lastname'],
            $new_account['email'],
            $new_account['personal_email'],
            $new_account['barangay'],
            $new_account['city_municipality'],
            $new_account['accessrole'],
            $new_account['organization'],
            $new_account['is_verified'],
            $new_account['import_date'],
            $new_account['imported_by'],
            $new_account['password']
        );

        if ($stmt->execute()) {
            $new_temp_id = $connection->insert_id;
            
            // Log the adding activity
            $phTime = getPhilippineTime();
            $userSource = (in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) ? 'adminaccountstbl' : 'accountstbl';
            $actionType = 'Added';
            $affectedSource = 'tempaccstbl';

            $activityDetails = "Account Addition Details:\n";
            $activityDetails .= "----------------------------\n";
            $activityDetails .= "Added by: " . $_SESSION['accessrole'] . " " . $_SESSION['name'] . "\n";
            $activityDetails .= "Addition Date: " . $phTime . "\n";
            $activityDetails .= "\nAccount Information:\n";
            $activityDetails .= "First Name: " . $new_account['firstname'] . "\n";
            $activityDetails .= "Last Name: : " . $new_account['lastname'] . "\n";
            $activityDetails .= "Email: " . $new_account['email'] . "\n";
            $activityDetails .= "Personal Email: " . $new_account['personal_email'] . "\n";
            $activityDetails .= "Barangay: " . $new_account['barangay'] . "\n";
            $activityDetails .= "City/Municipality: " . $new_account['city_municipality'] . "\n";
            $activityDetails .= "Access Role: " . $new_account['accessrole'] . "\n";
            $activityDetails .= "Organization: " . $new_account['organization'] . "\n";
            $activityDetails .= "Verification Status: " . $new_account['is_verified'] . "\n";
            $activityDetails .= "Added via: Manual Entry";

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
                $new_temp_id,
                $affectedSource,
                $activityDetails
            );
            
            if (!$activityQuery->execute()) {
                throw new Exception("Failed to log activity: " . $connection->error);
            }
            
            $connection->commit();
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Temporary account added successfully'
            ];
            header("Location: adminaccspage.php");
            exit;
        } else {
            throw new Exception("Failed to add account: " . $stmt->error);
        }
    } catch (Exception $e) {
        $connection->rollback();
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
        header("Location: adminaccspage.php");
        exit;
    }
}

// Handle form submission for admin accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account_type']) && $_POST['account_type'] === 'admin') {
    $admin_account = [
        'admin_name' => $_POST['admin_name'],
        'email' => $_POST['email'],
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'accessrole' => 'Administrator',
        'organization' => 'LGU'
    ];

    try {
        // Insert into adminaccountstbl
        $stmt = $connection->prepare("INSERT INTO adminaccountstbl 
            (admin_name, email, password, accessrole, organization) 
            VALUES (?, ?, ?, ?, ?)");

        $stmt->bind_param("sssss", 
            $admin_account['admin_name'],
            $admin_account['email'],
            $admin_account['password'],
            $admin_account['accessrole'],
            $admin_account['organization']
        );

        if ($stmt->execute()) {
            $new_admin_id = $connection->insert_id;
            
            // Log the admin creation activity
            $phTime = getPhilippineTime();
            $actionType = 'Admin Account Created';
            $affectedSource = 'adminaccountstbl';
            $actionType = 'Added';

            $activityDetails = "Administrator Account Creation Details:\n";
            $activityDetails .= "----------------------------------------\n";
            $activityDetails .= "Created by: " . $_SESSION['accessrole'] . " " . $_SESSION['name'] . "\n";
            $activityDetails .= "Creation Date: " . $phTime . "\n";
            $activityDetails .= "\nAdmin Information:\n";
            $activityDetails .= "Admin Name: " . $admin_account['admin_name'] . "\n";
            $activityDetails .= "Email: " . $admin_account['email'] . "\n";
            $activityDetails .= "Access Role: " . $admin_account['accessrole'] . "\n";
            $activityDetails .= "Organization: " . $admin_account['organization'] . "\n";

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

            $userRoleValue = 'adminaccountstbl';

            $activityQuery->bind_param(
                "ssissss", 
                $phTime,
                $actionType,
                $_SESSION['user_id'],
                $userRoleValue,  // Use variable instead of direct string
                $new_admin_id,
                $affectedSource,
                $activityDetails
            );
            
            $activityQuery->execute();
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Administrator account created successfully'
            ];
            header("Location: adminaccspage.php");
            exit;
        } else {
            throw new Exception("Failed to create admin account: " . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => $e->getMessage()
        ];
        header("Location: adminaccspage.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        :root {
            --base-clr: #123524;
            --line-clr: indigo;
            --secondarybase-clr: lavenderblush;
            --text-clr: #222533;
            --accent-clr: #EFE3C2;
            --secondary-text-clr: #123524;
            --placeholder-text-clr: #3E7B27;
            --event-clr: #FFFDF6;
        }

        body {
            background-color: var(--secondarybase-clr);
            color: var(--text-clr);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        h2 {
            color: var(--base-clr);
            border-bottom: 2px solid var(--line-clr);
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary-text-clr);
        }

        .form-control, .form-select {
            border: 1px solid #ced4da;
            border-radius: 5px;
            padding: 10px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--base-clr);
            box-shadow: 0 0 0 0.2rem rgba(18, 53, 36, 0.25);
        }

        .btn-primary {
            background-color: var(--base-clr);
            border-color: var(--base-clr);
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #0c2418;
            border-color: #0c2418;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            padding: 10px 20px;
            font-weight: 600;
        }

        .account-type-toggle {
            display: flex;
            margin-bottom: 25px;
            border: 1px solid var(--line-clr);
            border-radius: 8px;
            overflow: hidden;
        }

        .account-type-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            background-color: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .account-type-btn.active {
            background-color: var(--base-clr);
            color: white;
        }

        .account-type-btn:not(.active):hover {
            background-color: #e9ecef;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .admin-only {
            border-left: 4px solid var(--line-clr);
            padding-left: 15px;
            margin-top: 20px;
        }

        .admin-only h3 {
            color: var(--line-clr);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Add New Account</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Account Type Toggle -->
        <div class="account-type-toggle">
            <button type="button" class="account-type-btn active" data-type="regular">Regular Account</button>
            <?php if ($_SESSION['accessrole'] === 'Administrator'): ?>
            <button type="button" class="account-type-btn" data-type="admin">Administrator Account</button>
            <?php endif; ?>
        </div>
        
        <!-- Regular Account Form -->
        <form method="POST" id="regularAccountForm" class="form-section active">
            <input type="hidden" name="account_type" value="regular">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name*</label>
                    <input type="text" class="form-control" name="firstname" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Last Name*</label>
                    <input type="text" class="form-control" name="lastname" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email*</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Personal Email</label>
                    <input type="email" class="form-control" name="personal_email">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Barangay</label>
                    <input type="text" class="form-control" name="barangay" value="<?= isset($_SESSION['barangay']) ? htmlspecialchars($_SESSION['barangay']) : '' ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">City/Municipality</label>
                    <input type="text" class="form-control" name="city_municipality" value="<?= isset($_SESSION['city_municipality']) ? htmlspecialchars($_SESSION['city_municipality']) : '' ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Access Role*</label>
                    <select class="form-select" name="accessrole" required>
                        <option value="">Select Role</option>
                        <option value="Barangay Official">Barangay Official</option>
                        <option value="Resident">Resident</option>
                        <option value="Environmental Manager">Environmental Manager</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Organization</label>
                    <input type="text" class="form-control" name="organization">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Password*</label>
                    <input type="text" class="form-control" name="password" required>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Add Regular Account</button>
                    <a href="adminaccspage.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
        
        <!-- Administrator Account Form (Only for Administrators) -->
        <?php if ($_SESSION['accessrole'] === 'Administrator'): ?>
        <form method="POST" id="adminAccountForm" class="form-section">
            <input type="hidden" name="account_type" value="admin">
            
            <div class="admin-only">
                <h3><i class="fas fa-shield-alt"></i> Create Administrator Account</h3>
                <p class="text-muted">This will create a new administrator account with full system access.</p>
            </div>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name*</label>
                    <input type="text" class="form-control" name="admin_name" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email*</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Password*</label>
                    <input type="password" class="form-control" name="password" required>
                    <div class="form-text">Password will be securely hashed before storage</div>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Access Role</label>
                    <input type="text" class="form-control" value="Administrator" readonly>
                    <input type="hidden" name="accessrole" value="Administrator">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Organization</label>
                    <input type="text" class="form-control" value="LGU" readonly>
                    <input type="hidden" name="organization" value="LGU">
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary">Create Administrator Account</button>
                    <a href="adminaccspage.php" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accountTypeBtns = document.querySelectorAll('.account-type-btn');
            const formSections = document.querySelectorAll('.form-section');
            
            accountTypeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    
                    // Update button states
                    accountTypeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update form visibility
                    formSections.forEach(section => {
                        section.classList.remove('active');
                        if (section.id === type + 'AccountForm') {
                            section.classList.add('active');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>