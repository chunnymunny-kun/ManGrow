<?php
session_start();
function loginme($email,$password)
{
    require 'database.php';
    $result = mysqli_query($connection ,"SELECT * FROM accountstbl WHERE email = '$email'");
    $row = mysqli_fetch_assoc($result);
    if(mysqli_num_rows($result) > 0){
        if($password == $row["password"])
        {
            $_SESSION["login"] = true;
            $_SESSION["name"] = $row["fullname"];
            $_SESSION["email"] = $row["email"];
            $_SESSION["accessrole"] = $row["accessrole"];

            header("Location:index.php");
            exit();
        }
        else{
            echo
            "<script> alert('Incorrect username or password!');</script>";
            return;
        }
    }
    else
    {
        echo
        "<script> alert('This user is not registered.');</script>";
        return;
    }
}
?>