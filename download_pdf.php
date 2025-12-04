<?php
session_start();

// Validate authorization
if (!isset($_SESSION["accessrole"]) || 
    !in_array($_SESSION["accessrole"], ['Barangay Official', 'Administrator', 'Representative'])) {
    die('Unauthorized access');
}

if (!isset($_GET['file'])) {
    die('No file specified');
}

$filename = basename($_GET['file']);
$filepath = __DIR__ . '/generated_pdfs/' . $filename;

if (!file_exists($filepath)) {
    die('File not found');
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Clean output buffer and send file
ob_clean();
flush();
readfile($filepath);

// Optionally delete the file after download
// unlink($filepath);
exit;
?>