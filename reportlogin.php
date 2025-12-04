<?php
    session_start();
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["loginemail"])) {
            // Login form submission
            $email = $_POST["loginemail"];
            $password = $_POST["loginpassword"];
            require_once 'reportloginprocess.php';
            $result = loginme($email,$password);
        }
    }
    if(!isset($_SESSION['redirect_url'])){
        header("Location: index.php");
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'You must choose what report to comply before accessing this page.'
        ];
        exit();
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Login</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link rel="stylesheet" href="adminlogin.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<div class="background login"></div>
    <?php if(!empty($_SESSION['response'])): ?>
    <div class="flash-container">
        <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
            <?= $_SESSION['response']['msg'] ?>
        </div>
    </div>
    <?php 
    unset($_SESSION['response']); 
    endif; 
    ?>
    <div class="login-container login">
        <div class="content"> 
            <h2 class="logo"><i class='bx bxs-leaf'></i>ManGrow: Reports Login</h2>
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
                        <input type="email" name="loginemail" placeholder=" " required>
                        <label>Email</label>
                    </div>
                    <div class="input-box">
                        <span class="icon" id="password"><i class='bx bxs-lock-alt' ></i></span>
                        <input id="loginpassword" type="password" name="loginpassword" required>
                        <label>Password</label>
                        <img src="images/show.png" id="logineye" class="hide">
                    </div>
                    <button type="submit" name="logsubmit" class="loginbtn">Sign In</button>
                </form>
            </div>
        </div>
    </div>
    <script src="adminlogin.js" type="text/Javascript"></script>
</body>
</html>