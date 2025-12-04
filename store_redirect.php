<?php
session_start();

if (isset($_POST['redirect_url'])) {
    $_SESSION['redirect_url'] = $_POST['redirect_url'];
    echo 'URL stored successfully';
    exit;
}

echo 'No URL provided';
?>