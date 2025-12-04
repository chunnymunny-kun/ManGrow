<?php
session_start();
require_once 'database.php';

$event_id = $_GET['event_id'] ?? 0;

// Fetch comments with pinned comments first
$query = "SELECT ec.*, a.fullname, a.profile_thumbnail 
          FROM event_comments ec
          JOIN accountstbl a ON ec.commenter_id = a.account_id
          WHERE ec.event_id = ?
          ORDER BY ec.pinned_status DESC, ec.created_at DESC"; // Pinned comments first
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch event author
$authorQuery = "SELECT author FROM eventstbl WHERE event_id = ?";
$authorStmt = $connection->prepare($authorQuery);
$authorStmt->bind_param("i", $event_id);
$authorStmt->execute();
$authorResult = $authorStmt->get_result();
$eventAuthorId = null;
if ($row = $authorResult->fetch_assoc()) {
    $eventAuthorId = $row['author'];
}
$isAuthor = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $eventAuthorId;

// Comments list first
$output = '<div class="comments-list">';
if ($result->num_rows > 0) {
    while ($comment = $result->fetch_assoc()) {
        $isPinned = $comment['pinned_status'] == 1;
        
        $output .= '
        <div class="comment'.($isPinned ? ' pinned' : '').'" data-comment-id="'.htmlspecialchars($comment['comment_id']).'">
            <div class="comment-author">
                <img src="'.htmlspecialchars($comment['profile_thumbnail']).'" alt="Profile" class="comment-avatar">
                <span class="comment-author-name">'.htmlspecialchars($comment['fullname']).'</span>
                <span class="comment-date">'.date('M j, Y g:i a', strtotime($comment['created_at'])).'</span>';
        
        if ($isAuthor) {
            $output .= '
                <button class="pin-comment-btn" data-comment-id="'.htmlspecialchars($comment['comment_id']).'" data-pinned="'.($isPinned ? '1' : '0').'" data-event-id="'.$comment['event_id'].'">
                    <i class="fas fa-thumbtack"></i> '.($isPinned ? 'Unpin' : 'Pin').'
                </button>';
        }
        
        $output .= '
            </div>
            <div class="comment-text">'.nl2br(htmlspecialchars($comment['comment'])).'</div>';
        
        if (!empty($comment['attachment_photo'])) {
            $output .= '
            <div class="comment-image">
                <img src="'.htmlspecialchars($comment['attachment_photo']).'" alt="Comment image">
            </div>';
        }
        
        $output .= '</div>';
    }
} else {
    $output .= '<p class="no-comments">No comments yet. Be the first to comment!</p>';
}
$output .= '</div>';

// Then the form container
$output .= '
<div class="comment-form-container" data-is-author="'.($isAuthor ? 'true' : 'false').'">
    <form id="comment-form-'.$event_id.'" enctype="multipart/form-data" data-is-author="'.($isAuthor ? 'true' : 'false').'">
        <input type="hidden" name="event_id" value="'.$event_id.'">
        <textarea name="comment_text" placeholder="Write your comment..."></textarea>
        
        <div class="image-preview-container" style="display: none;">
            <img id="image-preview-'.$event_id.'" src="#" alt="Preview" style="max-height: 100px; max-width: 100%; display: none;">
            <button type="button" class="btn btn-link btn-sm remove-preview">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>

        <div class="comment-actions">
            <div>
                <label class="attachment-btn">
                    <i class="fas fa-paperclip"></i>
                    <span>Attach</span>
                    <input type="file" class="comment-image-input" name="comment_image" accept="image/jpeg, image/png, image/gif, image/webp" style="display: none;">
                </label>
                <span class="file-name"></span>
            </div>
            <button type="submit" class="post-comment-btn">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M120-160v-640l760 320-760 320Zm80-120 474-200-474-200v140l240 60-240 60v140Zm0 0v-400 400Z"/></svg>
            </button>
        </div>
    </form>
</div>';

echo $output;
?>