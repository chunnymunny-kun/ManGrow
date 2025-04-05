<?php
    function registerme($fullname,$email,$password,$confirmpassword,$accessrole)
    {
        require "database.php";
        $fullname = $_POST["fullname"];
        $email = $_POST["email"];
        $password = $_POST["password"];
        $confirmpassword = $_POST["confirmpassword"];
        $accessrole = $_POST["accessrole"];

        $duplicate = mysqli_query($connection, "SELECT * FROM accountstbl WHERE fullname = '$fullname' OR email = '$email'");
        if(mysqli_num_rows($duplicate) > 0){
            echo
            "<script> alert('Username or Email Has Already Taken!');</script>";
        }
        else{
            if($password == $confirmpassword){
                $query = "INSERT INTO accountstbl VALUES('','$fullname','$email','$password','$accessrole')";
                mysqli_query($connection,$query);
                echo
                "<script> alert('Registration Successful!');</script>";
            }else{
                echo
                "<script> alert('Passwords Does Not Match!');</script>";
            }
        }
        return;
    }
?>