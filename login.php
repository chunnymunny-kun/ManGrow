<?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST["loginemail"])) {
            // Login form submission
            $email = $_POST["loginemail"];
            $password = $_POST["loginpassword"];
            require_once 'loginprocess.php';
            $result = loginme($email,$password);
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

                <p>To protect our homeland is our knight's duty.</p>

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
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-user' ></i></span>
                        <input type="text" name="fullname" required>
                        <label>Full Name</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-envelope' ></i></span>
                        <input type="text" name="email" required>
                        <label>Email</label>
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-lock-alt' ></i></span>
                        <input id="regpassword" name="password" type="password" required>
                        <label>Password</label>
                        <img src="images/show.png" id="regpeye" class="hide">
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-lock-alt' ></i></span>
                        <input id="regconfirmpassword" name="confirmpassword" type="password" required>
                        <label>Confirm Password</label>
                        <img src="images/show.png" id="regcpeye" class="hide">
                    </div>
                    <div class="input-box">
                        <span class="icon"><i class='bx bxs-key' ></i></span>
                        <select name ="accessrole" required>
                            <option value="Citizen">Citizen</option>
                            <option value="Barangay Official">Barangay Official</option>
                        </select>
                        <label>Access Role</label>
                    </div>
                    <div class="remember-forgot">
                        <label><input type="checkbox"> I agree to the terms and conditions</label>
                    </div>
                    <button type="submit" name="regsubmit" class="registerbtn">Sign Up</button>
                    <div class ="login-register">
                        <p>Already have an account? <a href="#" class="login-link">Sign in</a> </p>
                    </div>
                </form>         
            </div>
        </div>
    </div>
    <script src="loginapp.js" type="text/Javascript"></script>
    <?php
    if (isset($_POST["fullname"])) {
        $button = $_POST["regsubmit"];
        // Registration form submission
        $fullname = $_POST["fullname"];
        $email = $_POST["email"];
        $password = $_POST["password"];
        $confirmpassword = $_POST["confirmpassword"];
        $accessrole = $_POST["accessrole"];
        require_once 'registerprocess.php';
        registerme($fullname,$email,$password,$confirmpassword,$accessrole);
    }
    ?>
</body>
</html>