<?php
require_once 'database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$comment_id = $_POST['comment_id'] ?? 0;
$pin_status = $_POST['pin_status'] ?? 1; // 1 to pin, 0 to unpin
$confirmed = $_POST['confirmed'] ?? false; 

// Verify user is event author
$query = "SELECT e.author, ec.event_id 
          FROM event_comments ec
          JOIN eventstbl e ON ec.event_id = e.event_id
          WHERE ec.comment_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $comment_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event || $event['author'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Check if there's already a pinned comment
$pinned_query = "SELECT comment_id FROM event_comments 
                 WHERE event_id = ? AND pinned_status = 1 AND comment_id != ?";
$pinned_stmt = $connection->prepare($pinned_query);
$pinned_stmt->bind_param("ii", $event['event_id'], $comment_id);
$pinned_stmt->execute();
$pinned_result = $pinned_stmt->get_result();
$existing_pinned = $pinned_result->fetch_assoc();

// If trying to pin and there's already a pinned comment, require confirmation
if ($pin_status == 1 && $existing_pinned && !$confirmed) {
    echo json_encode([
        'success' => false, 
        'needs_confirmation' => true,
        'message' => 'There is already a pinned comment. Do you want to replace it?',
        'existing_pinned_id' => $existing_pinned['comment_id']
    ]);
    exit;
}

// If pinning, first unpin any existing pinned comment
if ($pin_status == 1) {
    $unpin_query = "UPDATE event_comments SET pinned_status = 0 
                    WHERE event_id = ? AND pinned_status = 1";
    $unpin_stmt = $connection->prepare($unpin_query);
    $unpin_stmt->bind_param("i", $event['event_id']);
    $unpin_stmt->execute();
}

// Update the pin status
$query = "UPDATE event_comments SET pinned_status = ? WHERE comment_id = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("ii", $pin_status, $comment_id);
$success = $stmt->execute();

// After the successful pin/unpin operation, add this:
if ($success) {
    // Get the updated comments list for this event
    $comments_query = "SELECT ec.*, a.fullname, a.profile_thumbnail 
                      FROM event_comments ec
                      JOIN accountstbl a ON ec.commenter_id = a.account_id
                      WHERE ec.event_id = ?
                      ORDER BY ec.pinned_status DESC, ec.created_at DESC";
    $comments_stmt = $connection->prepare($comments_query);
    $comments_stmt->bind_param("i", $event['event_id']);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    
    $comments_html = '';
    if ($comments_result->num_rows > 0) {
        while ($comment = $comments_result->fetch_assoc()) {
            $comments_html .= '
            <div class="comment'.($comment['pinned_status'] ? ' pinned' : '').'" data-comment-id="'.$comment['comment_id'].'">
                <div class="comment-author">
                    <img src="'.htmlspecialchars($comment['profile_thumbnail']).'" alt="Profile" class="comment-avatar">
                    <span class="comment-author-name">'.htmlspecialchars($comment['fullname']).'</span>
                    <span class="comment-date">'.date('M j, Y g:i a', strtotime($comment['created_at'])).'</span>';
            
            if ($event['author'] == $_SESSION['user_id']) {
                $comments_html .= '
                    <button class="pin-comment-btn" data-comment-id="'.$comment['comment_id'].'" data-pinned="'.$comment['pinned_status'].'">
                        <i class="fas fa-thumbtack"></i> '.($comment['pinned_status'] ? 'Unpin' : 'Pin').'
                    </button>';
            }
            
            $comments_html .= '
                </div>
                <div class="comment-text">'.nl2br(htmlspecialchars($comment['comment'])).'</div>';
            
            if (!empty($comment['attachment_photo'])) {
                $comments_html .= '
                <div class="comment-image">
                    <img src="'.htmlspecialchars($comment['attachment_photo']).'" alt="Comment image">
                </div>';
            }
            
            $comments_html .= '</div>';
        }
    } else {
        $comments_html .= '<p class="no-comments">No comments yet. Be the first to comment!</p>';
    }

    echo json_encode([
        'success' => true,
        'pinned_status' => $pin_status,
        'message' => $success ? ($pin_status ? 'Comment pinned' : 'Comment unpinned') : 'Failed to update',
        'comments_html' => $comments_html,
        'event_id' => $event['event_id']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update pin status'
    ]);
}