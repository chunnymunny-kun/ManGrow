<?php
function loginme($email, $password) {
    require 'database.php';

    // Prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($connection, "SELECT * FROM adminaccountstbl WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // First try to verify with password_verify (for hashed passwords)
        if (password_verify($password, $row["password"])) {
            // Successful login with hashed password
            return setAdminSession($row);
        }
        // If password_verify failed, check if it's a plain text match
        else if ($password == $row["password"]) {
            // This account has plain text password - migrate to hashed
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = mysqli_prepare($connection, "UPDATE adminaccountstbl SET password = ? WHERE admin_id = ?");
            mysqli_stmt_bind_param($updateStmt, "si", $hashedPassword, $row["admin_id"]);
            
            if (mysqli_stmt_execute($updateStmt)) {
                // Password migrated successfully, proceed with login
                mysqli_stmt_close($updateStmt);
                return setAdminSession($row);
            } else {
                // Migration failed but plain text password was correct
                $_SESSION['response'] = [
                    'status' => 'error',
                    'msg' => 'Login successful but password migration failed. Please contact administrator.'
                ];
                return false;
            }
        } else {
            // Both password checks failed
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Incorrect username or password!'
            ];
            return false;
        }
    } else {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'This user is not an admin.'
        ];
        return false;
    }
    mysqli_stmt_close($stmt);
}

// Helper function to set admin session
function setAdminSession($row) {
    $_SESSION["login"] = true;
    $_SESSION["user_id"] = $row["admin_id"];
    $_SESSION["name"] = $row["admin_name"];
    $_SESSION["email"] = $row["email"];
    $_SESSION["accessrole"] = $row["accessrole"];
    $_SESSION["organization"] = $row["organization"];

    $_SESSION['response'] = [
        'status' => 'success',
        'msg' => 'Welcome to the Admin Page!'
    ];
    return true;
}
?>