<?php
// rewards_claim_handler.php - Handle user reward claims
session_start();
require_once 'database.php';

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to view rewards']);
    exit;
}

// Check if user is Barangay Official (they are excluded from rewards)
$userCheckQuery = "SELECT accessrole FROM accountstbl WHERE account_id = ?";
$userCheckStmt = $connection->prepare($userCheckQuery);
$userCheckStmt->bind_param("i", $_SESSION['user_id']);
$userCheckStmt->execute();
$userCheckResult = $userCheckStmt->get_result()->fetch_assoc();

if($userCheckResult && $userCheckResult['accessrole'] === 'Barangay Official') {
    echo json_encode(['success' => false, 'message' => 'Barangay Officials are not eligible for reward points']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

// ============================================
// ACTION 1: Get User's Rewards
// ============================================
if($action === 'get_rewards') {
    $query = "SELECT 
                ur.reward_id,
                ur.points_awarded,
                ur.category,
                ur.rank_achieved,
                ur.entity_name,
                ur.status,
                ur.calculated_at,
                ur.claimed_at,
                rc.cycle_name,
                rc.start_date,
                rc.end_date
              FROM user_rewards ur
              JOIN reward_cycles rc ON ur.cycle_id = rc.cycle_id
              WHERE ur.user_id = ?
              ORDER BY ur.calculated_at DESC
              LIMIT 20";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pending = [];
    $claimed = [];
    $totalPending = 0;
    
    while($row = $result->fetch_assoc()) {
        if($row['status'] === 'pending') {
            $pending[] = $row;
            $totalPending += $row['points_awarded'];
        } else {
            $claimed[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'pending_rewards' => $pending,
        'claimed_rewards' => $claimed,
        'total_pending_points' => $totalPending,
        'has_pending' => count($pending) > 0
    ]);
    exit;
}

// ============================================
// ACTION 2: Claim Single Reward
// ============================================
if($action === 'claim_reward') {
    $rewardId = intval($_POST['reward_id'] ?? 0);
    
    if($rewardId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid reward ID']);
        exit;
    }
    
    $connection->begin_transaction();
    
    try {
        // Check if reward exists and belongs to user
        $checkQuery = "SELECT user_id, points_awarded, status FROM user_rewards WHERE reward_id = ?";
        $stmt = $connection->prepare($checkQuery);
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();
        $reward = $stmt->get_result()->fetch_assoc();
        
        if(!$reward) {
            throw new Exception('Reward not found');
        }
        
        if($reward['user_id'] != $userId) {
            throw new Exception('This reward does not belong to you');
        }
        
        if($reward['status'] === 'claimed') {
            throw new Exception('Reward already claimed');
        }
        
        // Mark as claimed
        $claimQuery = "UPDATE user_rewards SET status = 'claimed', claimed_at = NOW() WHERE reward_id = ?";
        $stmt = $connection->prepare($claimQuery);
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();
        
        // Add points to user account
        $updateQuery = "UPDATE accountstbl SET eco_points = eco_points + ? WHERE account_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ii", $reward['points_awarded'], $userId);
        $stmt->execute();
        
        $connection->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully claimed {$reward['points_awarded']} eco points!",
            'points_claimed' => $reward['points_awarded']
        ]);
        
    } catch(Exception $e) {
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// ACTION 3: Claim All Pending Rewards
// ============================================
if($action === 'claim_all') {
    $connection->begin_transaction();
    
    try {
        // Get all pending rewards
        $getPending = "SELECT reward_id, points_awarded FROM user_rewards 
                      WHERE user_id = ? AND status = 'pending'";
        $stmt = $connection->prepare($getPending);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rewards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if(empty($rewards)) {
            throw new Exception('No pending rewards to claim');
        }
        
        $totalPoints = 0;
        foreach($rewards as $reward) {
            $totalPoints += $reward['points_awarded'];
        }
        
        // Mark all as claimed
        $claimQuery = "UPDATE user_rewards SET status = 'claimed', claimed_at = NOW() 
                      WHERE user_id = ? AND status = 'pending'";
        $stmt = $connection->prepare($claimQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        // Add points to user account
        $updateQuery = "UPDATE accountstbl SET eco_points = eco_points + ? WHERE account_id = ?";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ii", $totalPoints, $userId);
        $stmt->execute();
        
        $connection->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully claimed all rewards! Total: {$totalPoints} eco points",
            'total_points_claimed' => $totalPoints,
            'rewards_claimed' => count($rewards)
        ]);
        
    } catch(Exception $e) {
        $connection->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
$connection->close();
?>
