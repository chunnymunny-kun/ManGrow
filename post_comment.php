<?php
require_once 'database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please login to comment',
        'redirect' => 'login.php'
    ]);
    exit;
}

$event_id = $_POST['event_id'] ?? 0;
$comment_text = trim($_POST['comment_text'] ?? '');
$user_id = $_SESSION['user_id'];

// Validate - require either comment text or image
if (empty($comment_text) && (!isset($_FILES['comment_image']) || $_FILES['comment_image']['error'] !== UPLOAD_ERR_OK)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please write a comment or attach an image.'
    ]);
    exit;
}

// Handle file upload
$image_path = '';
if (isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/comments/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $ext = pathinfo($_FILES['comment_image']['name'], PATHINFO_EXTENSION);
    $filename = 'comment_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_file = $upload_dir . $filename;
    
    // Validate image (only allow images)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    $file_type = $_FILES['comment_image']['type'];
    $file_extension = strtolower(pathinfo($_FILES['comment_image']['name'], PATHINFO_EXTENSION));

    if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
        echo json_encode([
            'success' => false,
            'message' => 'Only JPG, PNG, GIF, and WEBP images are allowed'
        ]);
        exit;
    }
    
    if (!move_uploaded_file($_FILES['comment_image']['tmp_name'], $target_file)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to upload image'
        ]);
        exit;
    }
    
    $image_path = $target_file;
}

// Insert comment
$query = "INSERT INTO event_comments (event_id, commenter_id, comment, attachment_photo) 
          VALUES (?, ?, ?, ?)";
$stmt = $connection->prepare($query);
$stmt->bind_param("iiss", $event_id, $user_id, $comment_text, $image_path);
$success = $stmt->execute();

if ($success) {
    // Get the newly inserted comment
    $comment_id = $stmt->insert_id;
    $query = "SELECT ec.*, a.fullname, a.profile_thumbnail 
              FROM event_comments ec
              JOIN accountstbl a ON ec.commenter_id = a.account_id
              WHERE ec.comment_id = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_comment = $result->fetch_assoc();
    
    // Format the date for consistent display
    $new_comment['formatted_date'] = date('M j, Y g:i A', strtotime($new_comment['created_at']));
    
    echo json_encode([
        'success' => true,
        'comment' => $new_comment
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to post comment'
    ]);
}
?>