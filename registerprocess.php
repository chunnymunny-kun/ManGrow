<?php
function registerme($fullname, $email, $password, $confirmpassword, $accessrole)
{
    include "database.php";

    $fullname = $_POST["fullname"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirmpassword = $_POST["confirmpassword"];
    $accessrole = $_POST["accessrole"];

    $stmt_duplicate = mysqli_prepare($connection, "SELECT * FROM accountstbl WHERE fullname = ? OR email = ?");
    mysqli_stmt_bind_param($stmt_duplicate, "ss", $fullname, $email);
    mysqli_stmt_execute($stmt_duplicate);
    $result_duplicate = mysqli_stmt_get_result($stmt_duplicate);

    if (mysqli_num_rows($result_duplicate) > 0) {
        echo "<script>alert('Username or Email Has Already Taken!');</script>";
    } else {
        if ($password == $confirmpassword) {
            // Insert new account using prepared statements
            $stmt_insert = mysqli_prepare($connection, "INSERT INTO accountstbl (fullname, email, password, accessrole) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_insert, "ssss", $fullname, $email, $password, $accessrole);

            if (mysqli_stmt_execute($stmt_insert)) {
                echo "<script>alert('Registration Successful!');</script>";
            } else {
                echo "<script>alert('Registration Failed: " . mysqli_error($connection) . "');</script>";
            }
            mysqli_stmt_close($stmt_insert);
        } else {
            echo "<script>alert('Passwords Does Not Match!');</script>";
        }
    }
    mysqli_stmt_close($stmt_duplicate);
    return;
}
?>