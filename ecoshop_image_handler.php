<?php
// Image upload processing for eco shop items
require_once 'database.php';

function uploadEcoShopImage($file, $item_name) {
    // Create uploads directory if it doesn't exist
    $uploadDir = 'uploads/ecoshop/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed'];
    }
    
    // Validate file size (max 5MB)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File size too large. Maximum 5MB allowed'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'ecoshop_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $item_name) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Resize image to standard size (300x300)
        resizeImage($filepath, 300, 300);
        return ['success' => true, 'path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

function resizeImage($filepath, $width, $height) {
    $info = getimagesize($filepath);
    if (!$info) return false;
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($filepath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($filepath);
            break;
        default:
            return false;
    }
    
    if (!$source) return false;
    
    $oldWidth = imagesx($source);
    $oldHeight = imagesy($source);
    
    // Calculate new dimensions maintaining aspect ratio
    $ratio = min($width / $oldWidth, $height / $oldHeight);
    $newWidth = intval($oldWidth * $ratio);
    $newHeight = intval($oldHeight * $ratio);
    
    // Create new image
    $destination = imagecreatetruecolor($width, $height);
    
    // Handle transparency for PNG and GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
        imagefill($destination, 0, 0, $transparent);
    } else {
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);
    }
    
    // Center the image
    $offsetX = ($width - $newWidth) / 2;
    $offsetY = ($height - $newHeight) / 2;
    
    imagecopyresampled($destination, $source, $offsetX, $offsetY, 0, 0, $newWidth, $newHeight, $oldWidth, $oldHeight);
    
    // Save the resized image
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($destination, $filepath, 90);
            break;
        case 'image/png':
            imagepng($destination, $filepath);
            break;
        case 'image/gif':
            imagegif($destination, $filepath);
            break;
        case 'image/webp':
            imagewebp($destination, $filepath, 90);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($destination);
    
    return true;
}

function deleteEcoShopImage($imagePath) {
    if (!empty($imagePath) && file_exists($imagePath)) {
        return unlink($imagePath);
    }
    return true;
}
?>
