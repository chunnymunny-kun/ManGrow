<?php
session_start();
include 'database.php';
include 'badge_system_db.php';

// Check if user is admin
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Unauthorized access'
    ];
    header("Location: admin_badges.php");
    exit();
}

// Initialize badge system
BadgeSystem::init($connection);

// Function to resize and optimize uploaded images
function resizeImageForBadge($sourcePath, $targetPath, $size = 200) {
    // Get image info
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Create image resource based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Calculate dimensions to maintain aspect ratio while fitting in square
    if ($width > $height) {
        $newWidth = $size;
        $newHeight = intval($height * ($size / $width));
    } else {
        $newHeight = $size;
        $newWidth = intval($width * ($size / $height));
    }
    
    // Create new image
    $newImage = imagecreatetruecolor($size, $size);
    
    // Set background color (white)
    $bgColor = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $bgColor);
    
    // Handle transparency for PNG
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefill($newImage, 0, 0, $transparent);
    }
    
    // Calculate position to center the image
    $x = ($size - $newWidth) / 2;
    $y = ($size - $newHeight) / 2;
    
    // Resize and copy
    imagecopyresampled($newImage, $sourceImage, $x, $y, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save the image
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $targetPath, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $targetPath, 6);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $targetPath);
            break;
    }
    
    // Clean up
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image upload
    $imagePath = '';
    if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/badges/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['image_upload']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Please upload a valid image file (JPEG, PNG, or GIF).'
            ];
            header("Location: admin_badges.php");
            exit();
        }
        
        $fileExtension = pathinfo($_FILES['image_upload']['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['badge_name']);
        $fileName = $safeName . '_' . time() . '.' . $fileExtension;
        $imagePath = $uploadDir . $fileName;
        
        // Resize and save the image
        if (!resizeImageForBadge($_FILES['image_upload']['tmp_name'], $imagePath, 200)) {
            $_SESSION['flash_message'] = [
                'type' => 'error',
                'message' => 'Error processing image. Please try a different image.'
            ];
            header("Location: admin_badges.php");
            exit();
        }
    }
    
    $result = BadgeSystem::addBadge(
        $_POST['badge_name'],
        $_POST['description'],
        $_POST['instructions'],
        $imagePath,
        $_POST['icon_class'],
        $_POST['color'],
        $_POST['category']
    );
    
    if ($result) {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => 'Badge added successfully!'
        ];
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Error adding badge. Please try again.'
        ];
    }
} else {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'message' => 'Invalid request method.'
    ];
}

header("Location: admin_badges.php");
exit();
?>
