<?php
session_start();
// Retrieve data from session variables 
if(isset($_SESSION["firstname"])){
    $firstname = $_SESSION["firstname"];
}
if(isset($_SESSION["lastname"])){
    $lastname = $_SESSION["lastname"];
}
if(isset($_SESSION["email"])){
    $email = $_SESSION["email"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification</title>
    <script src="https://smtpjs.com/v3/smtp.js">
    </script>
    <link rel="stylesheet" href="verification.css">
</head>
<body>
<div class="background login"></div>
        <div class="returnbtn">
                    <button type="button" name="backbtn" onclick="window.location.href='login.php';">X</button>
                </div>
<?php
require 'database.php';
//search for the session variables in the database(firstname,lastname)
$statement = mysqli_prepare($connection, "SELECT * FROM tempaccstbl WHERE firstname = ? AND lastname = ?");
mysqli_stmt_bind_param($statement, "ss", $firstname, $lastname);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);

if(mysqli_num_rows($result) > 0) {
    // Data exists in the database
    $row = mysqli_fetch_assoc($result);
    $firstname = $row['firstname'];
    $lastname = $row['lastname'];
    $tempemail = $row['email'];
    $messagetext = "We detected that you have an account with us. Please verify with your email address to continue.";
    ?>
        <div class="verify-container">
            <div class="content-header">
            <h1>Verification</h1>
            </div>
            <div class="content-mesg">
            <h3><?php echo htmlspecialchars($messagetext)?></h3>
            <p name="firstname">Firstname: <?php echo htmlspecialchars($firstname); ?></p>
            <p name="lastname">Lastname: <?php echo htmlspecialchars($lastname); ?></p>
            <p name="tempemail">Email: <?php echo htmlspecialchars($tempemail); ?></p> 
            </div>
            <p>We will send an OTP to <span><?php echo htmlspecialchars($email); ?></span> for the verification of your account. Please enter the OTP code below.</p>
            <div class="input-box">
            <div><label for="pemail">Personal Email:</label>
            <input type="text" id="pemail" name="pemail" value="<?php echo htmlspecialchars($email); ?>" readonly/></div>
            <div><label for="otp">OTP:</label><br>
            <input type="text" id="otp-input" name="otp" placeholder="Enter OTP" required /><button type="button" name="sendotp">Send</button></div>
            <div>
            <button type="submit" id="otp-btn" name="verifybtn" class="loginbtn">Verify</button></div>
            </div>           
        </div>
    <?php
} else {
    // Data does not exist in the database
    $messagetext = "It seems that you are not locally registered with us, please coordinate with your local authorities to register.";
    ?>
    <div class="verify-container">
                <h1>Verification</h1>
                <h3><?php echo htmlspecialchars($messagetext)?></h3>
                <button type = "button" name="backbtn" onclick="window.location.href='login.php';">Close</button>
        </div>
        <?php
    exit();
}


/* Display the data
if ($firstname && $lastname && $email) {
    echo "<h1>Verification</h1>";
    echo "<p>First Name: " . htmlspecialchars($firstname) . "</p>";
    echo "<p>Last Name: " . htmlspecialchars($lastname) . "</p>";
    echo "<p>Email: " . htmlspecialchars($email) . "</p>";

    // Optional: Clear session variables after displaying
    unset($_SESSION['reg_firstname'], $_SESSION['reg_lastname'], $_SESSION['reg_email']);
} else {
    echo "<p>No data to verify.</p>";
}
*/
?>


<script src="emailotp.js"></script>
</body>
</html>
