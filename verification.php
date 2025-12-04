<?php
session_start();

// Retrieve data from session variables 
if(isset($_SESSION["firstname"])){
    $firstname = $_SESSION["firstname"];
}
if(isset($_SESSION["lastname"])){
    $lastname = $_SESSION["lastname"];
}
if(isset($_SESSION["pemail"])){
    $pemail = $_SESSION["pemail"];
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
    $row = mysqli_fetch_assoc($result);
    $firstname = $row['firstname'];
    $lastname = $row['lastname'];
    $tempemail = $row['personal_email'];
    $censoredTempEmail = censorEmail($tempemail);
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
        <p name="tempemail">Email: <?php echo htmlspecialchars($censoredTempEmail); ?></p> 
        </div>
        <p>We will send an email to <span><?php echo htmlspecialchars($censoredTempEmail); ?></span> for the verification of your account. Please check your inbox or spam to see our message.</p>
    <?php
} else {
    // Data does not exist in the database
    $messagetext = "It seems that you are not locally registered with us, please coordinate with your local authorities to register.";
    ?>
    <div class="verify-container">
                <div class="content-header">
                <h1>Verification</h1>
                </div>       
                <div class="content-mesg">
                <h4><?php echo htmlspecialchars($messagetext)?></h4>
                </div>
                <div class="input-box">
                <button type = "button" name="backbtn" onclick="window.location.href='login.php';">Close</button>
                </div>
        </div>
        <?php
    exit();
}


/* Display the data (debugging)
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
<?php
// Email censoring function
function censorEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) == 2) {
        $username = $parts[0];
        $domain = $parts[1];
        $censored = substr($username, 0, 2) . '***@' . $domain;
        return $censored;
    }
    return $email; // fallback if not a standard email format
}
?>
</body>
</html>
