<?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["loginemail"])) {
            // Login form submission
            $email = $_POST["loginemail"];
            $password = $_POST["loginpassword"];
            require_once 'adminloginprocess.php';
            $result = loginme($email,$password);
        }
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="loginstyle.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="background login"></div>
    <div class="login-container login">
        <div class="content">
            <h2 class="logo"><i class='bx bxs-leaf'></i>ManGrow: Administrator</h2>
            <div class="content-mesg">
                <h2>Welcome!<br><span>Let's plant our future together.</span></h2>

                <p>To protect our homeland is our mangroves' duty.</p>

                <div class="socials">
                    <a href="#"><i class='bx bxl-facebook-square' id="fb"></i></a>
                    <a href="#"><i class='bx bxl-instagram' id="ig"></i></a>
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
                        <label>Administrator Email</label>
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
    <script src="loginapp.js" type="text/Javascript"></script>
</body>
</html>