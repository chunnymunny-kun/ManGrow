<?php
require 'database.php';
session_start();
$_SESSION = array();
session_destroy();
unset($_POST['admin_access_key']);
header("Location: adminlogin.php");
?>