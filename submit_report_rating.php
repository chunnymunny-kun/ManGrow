<?php
/**
 * Handle rating submission for resolved illegal activity reports
 */

session_start();
require_once 'database.php';
require_once 'eco_points_integration.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['report_id']) || !isset($input['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$reportId = intval($input['report_id']);
$rating = intval($input['rating']);
$adminId = $_SESSION['user_id'];

// Validate rating range
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

try {
    // Verify report exists and is resolved
    $checkQuery = "SELECT * FROM illegalreportstbl WHERE report_id = ? AND action_type = 'Resolved'";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("i", $reportId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Report not found or not resolved']);
        exit();
    }
    
    $report = $result->fetch_assoc();
    
    // Check if report has already been rated
    if (!empty($report['rating'])) {
        echo json_encode(['success' => false, 'message' => 'Report has already been rated']);
        exit();
    }
    
    // Update the report with the rating
    $updateQuery = "UPDATE illegalreportstbl SET rating = ?, rated_by = ?, rated_at = CURRENT_TIMESTAMP WHERE report_id = ?";
    $updateStmt = $connection->prepare($updateQuery);
    $updateStmt->bind_param("iii", $rating, $adminId, $reportId);
    $updateResult = $updateStmt->execute();
    
    if (!$updateResult) {
        throw new Exception('Failed to update report rating');
    }
    
    // Award eco points to the reporter (including anonymous reports)
    $reporterId = $report['reporter_id'];
    $pointsAwarded = 0;
    $badgeAwarded = null;
    $isAnonymousReport = ($reporterId == 0 || $reporterId === null);
    
    // Always try to award points - the integration function will handle anonymous reports
    $pointsResult = awardReportResolutionPointsWithRating($reportId, $adminId, $rating);
    
    if ($pointsResult['success']) {
        $pointsAwarded = $pointsResult['points_awarded'];
        
        // Log the points calculation for debugging
        $reportType = $isAnonymousReport ? 'Anonymous' : 'Named';
        error_log("Rating System: Report ID {$reportId} - Type: {$reportType}, Priority: {$report['priority']}, Rating: {$rating}/5, Points Awarded: {$pointsAwarded}");
        
        // Check for badge awards (if any badges were awarded, they would be in the result)
        if (isset($pointsResult['badge_awarded'])) {
            $badgeAwarded = $pointsResult['badge_awarded'];
        }
    } else {
        error_log("Failed to award points for rating: " . $pointsResult['message']);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully',
        'rating' => $rating,
        'points_awarded' => $pointsAwarded,
        'badge_awarded' => $badgeAwarded,
        'reporter_id' => $reporterId,
        'priority' => $report['priority'],
        'max_possible_points' => ($report['priority'] === 'Emergency') ? 50 : 25,
        'is_anonymous' => $isAnonymousReport
    ]);
    
} catch (Exception $e) {
    error_log("Error submitting rating: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$connection->close();
?>
