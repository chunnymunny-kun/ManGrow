<?php
session_start();

if (isset($_GET['redirect_url'])) {
    $_SESSION['qr_url'] = $_GET['redirect_url'];
    header("Location: reportlogin.php");
    exit();
}
?>