<?php
session_start();
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["loginemail"])) {
            // Login form submission
            $email = $_POST["loginemail"];
            $password = $_POST["loginpassword"];
            require_once 'loginprocess.php';
            $result = loginme($email,$password);
        }
        if (isset($_POST["firstname"])) {
            $firstname = $_POST['firstname'];
            $lastname = $_POST['lastname'];
            $email = $_POST['email'];
        
            // Store data in session variables
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['email'] = $email;
        
            // Redirect to the verification page
            header("Location: verification.php");
            exit();
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="background login"></div>
    <div class="returnbtn">
            <button type="button" name="backbtn" onclick="window.location.href='index.php';">X</button>
        </div>
    <div class="login-container login">
        <div class="content">
            <h2 class="logo"><i class='bx bxs-leaf'></i>ManGrow</h2>
            <div class="content-mesg">
                <h2>Welcome!<br><span>Let's plant our future together.</span></h2>

                <p>Forsee the growth of our land's most precious natural defense.</p>

                <div class="socials">
                    <a href="https://www.facebook.com/sambrix.perello.1"><i class='bx bxl-facebook-square' id="fb"></i></a>
                    <a href="https://www.instagram.com/ur_s4mmm?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="><i class='bx bxl-instagram' id="ig"></i></a>
                    <a href="#"><i class='bx bxl-twitter' id="twt"></i></a>
                </div>
            </div>
        </div>
        <div class="login-registration-box">
            <div class="form-box login">
                <form action="" method="post" autocomplete="off">
                    <h2>Sign In</h2>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-envelope' ></i></span>
                        <input type="text" name="loginemail" required>
                        <label>Email</label>
                    </div>
                    <div class="input-box">
                        <span class="icon" id="password"><i class='bx bxs-lock-alt' ></i></span>
                        <input id="loginpassword" type="password" name="loginpassword" required>
                        <label>Password</label>
                        <img src="images/show.png" id="logineye" class="hide">
                    </div>
                    <div class="remember-forgot">
                        <label><input type="checkbox"> Remember me</label>
                        <a href="#">Forgot password?</a>
                    </div>
                    <button type="submit" name="logsubmit" class="loginbtn">Sign In</button>
                    <div class ="login-register">
                        <p>Don't have an account? <a href="#" class="register-link">Sign up</a> </p>
                    </div>
                </form>
            </div>
            <div class="form-box register" id="register-form">
                <form action="" method="post" autocomplete="off">
                    <h2>Sign Up</h2>
                    <h5>Let's verify your identity if you are locally registered to us.</h5>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-user' ></i></span>
                        <input type="text" name="firstname" required>
                        <label>First Name</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-user' ></i></span>
                        <input type="text" name="lastname" required>
                        <label>Last Name</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-envelope' ></i></span>
                        <input type="text" name="email" required>
                        <label>Personal Email</label>
                    </div>
                    <div class="remember-forgot">
                        <label><input type="checkbox"> I agree to the terms and conditions</label>
                    </div>
                    <button type="submit" name="regsubmit" class="registerbtn">Verify</button>
                    <div class ="login-register">
                        <p>Already have an account? <a href="#" class="login-link">Sign in</a> </p>
                    </div>
                </form>         
            </div>
        </div>
    </div>

    <script src="loginapp.js" type="text/Javascript"></script>
</body>
</html>
<!--$fullname = $_POST["fullname"];
        $email = $_POST["email"];
        $password = $_POST["password"];
        $confirmpassword = $_POST["confirmpassword"];
        $accessrole = $_POST["accessrole"];
        require_once 'registerprocess.php';
        registerme($fullname,$email,$password,$confirmpassword,$accessrole); */-->