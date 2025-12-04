<?php

function loginme($email, $password) {
    require 'database.php';

    // Prepared statement
    $stmt = mysqli_prepare($connection, "SELECT * FROM accountstbl WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Password verification with hashing
        if (password_verify($password, $row["password"])) {
            $_SESSION["login"] = true;
            $_SESSION["user_id"] = $row["account_id"];
            $_SESSION["name"] = $row["fullname"];
            $_SESSION["email"] = $row["email"];
            $_SESSION["accessrole"] = $row["accessrole"];
            $_SESSION["profile_image"] = $row["profile_thumbnail"];
            $_SESSION["city_municipality"] = $row["city_municipality"];
            $_SESSION["barangay"] = $row["barangay"];
            $_SESSION["organization"] = $row["organization"];
            $_SESSION["bio"] = $row["bio"];
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Login successful!'
            ];
            if(isset($_SESSION['redirect_url'])){
                header("Location: " . $_SESSION['redirect_url']); // Successful login goes to selected reportform
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Login successful! Welcome back, ' . $_SESSION["name"] . '!'
                ];
                exit();
            } else{
                header("Location: reportform_2.0_.php"); // Successful login goes to reportform.php
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Login successful! Welcome back, ' . $_SESSION["name"] . '!'
                ];
                exit();  
            }
            
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Incorrect password!'
            ];
            header("Location: reportlogin.php"); // Failed login goes back
            exit();
        }
    } else {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Email not found!'
        ];
        header("Location: reportlogin.php"); // Failed login goes back
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Process login when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["logsubmit"])) {
    $email = trim($_POST["loginemail"]);
    $password = $_POST["loginpassword"];
    loginme($email, $password);
}
?>