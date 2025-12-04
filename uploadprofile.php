<?php
session_start();
require_once 'database.php';

// Configuration
$uploadDir = 'uploads/profiles/';
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image removal (unchanged)
    if (isset($_POST['remove-image'])) {
        if (isset($_SESSION['profile_image']) && file_exists($_SESSION['profile_image'])) {
            unlink($_SESSION['profile_image']);
        }
        $_SESSION['profile_image'] = null;
        
        $stmt = $connection->prepare("UPDATE accountstbl SET profile = NULL, profile_thumbnail = NULL WHERE fullname = ?");
        $stmt->bind_param("s", $_SESSION['name']);
        $stmt->execute();
        
        $_SESSION['response'] = ['status' => 'success', 'msg' => 'Image removed'];
        header("Location: profileform.php");
        exit();
    }

    // Handle image upload
    if (isset($_POST['upload-image']) && isset($_FILES['profile-image'])) {
        try {
            $file = $_FILES['profile-image'];
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error');
            }
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('Only JPG/PNG/GIF allowed');
            }
            
            if ($file['size'] > $maxFileSize) {
                throw new Exception('File too large (max 2MB)');
            }
            
            // Create upload directory
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Process image with cropping
            $imageData = file_get_contents($file['tmp_name']);
            $srcImage = imagecreatefromstring($imageData);
            
            if (!$srcImage) {
                throw new Exception('Failed to process image');
            }
            
            // Get crop coordinates from form
            $x = (int)$_POST['x'];
            $y = (int)$_POST['y'];
            $width = (int)$_POST['width'];
            $height = (int)$_POST['height'];
            
            // Validate crop dimensions
            $srcWidth = imagesx($srcImage);
            $srcHeight = imagesy($srcImage);
            
            if ($x < 0 || $y < 0 || $width <= 0 || $height <= 0 ||
                ($x + $width) > $srcWidth || ($y + $height) > $srcHeight) {
                throw new Exception('Invalid crop dimensions');
            }
            
            // Perform the crop
            $croppedImage = imagecrop($srcImage, [
                'x' => $x,
                'y' => $y,
                'width' => $width,
                'height' => $height
            ]);
            
            if (!$croppedImage) {
                throw new Exception('Failed to crop image');
            }
            
            // Generate filenames
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'profile_' . preg_replace('/[^a-z0-9]/i', '_', $_SESSION['name']) . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;
            
            // Save cropped image
            switch ($file['type']) {
                case 'image/jpeg':
                    imagejpeg($croppedImage, $filepath, 90);
                    break;
                case 'image/png':
                    imagepng($croppedImage, $filepath, 9);
                    break;
                case 'image/gif':
                    imagegif($croppedImage, $filepath);
                    break;
            }
            
            // Create thumbnail (200x200)
            $thumbnail = imagescale($croppedImage, 200, 200);
            ob_start();
            imagejpeg($thumbnail, null, 80);
            $thumbnailBase64 = 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
            
            // Clean up
            imagedestroy($thumbnail);
            imagedestroy($srcImage);
            imagedestroy($croppedImage);
            
            // Delete old image
            if (isset($_SESSION['profile_image']) && file_exists($_SESSION['profile_image'])) {
                @unlink($_SESSION['profile_image']);
            }
            
            // Update database
            $_SESSION['profile_image'] = $filepath;
            $stmt = $connection->prepare("UPDATE accountstbl SET profile = ?, profile_thumbnail = ? WHERE fullname = ?");
            $stmt->bind_param("sss", $filepath, $thumbnailBase64, $_SESSION['name']);
            
            if (!$stmt->execute()) {
                throw new Exception('Database update failed');
            }
            
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Profile updated successfully'
            ];
            
        } catch (Exception $e) {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => $e->getMessage()
            ];
        }
        
        header("Location: profileform.php");
        exit();
    }
}

header("Location: profileform.php");
exit();
?>