<!--
<?php
    session_start();
    include 'badge_system_db.php';

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }

    // Badge notification system
    $showBadgeNotification = false;
    $badgeToShow = null;

    if(isset($_SESSION['new_badge_awarded']) && $_SESSION['new_badge_awarded']['badge_awarded'] && isset($_SESSION['user_id'])) {
        $showBadgeNotification = true;
        $badgeToShow = $_SESSION['new_badge_awarded'];
        
        // Mark as permanently notified in database
        $userId = $_SESSION['user_id'];
        $badgeName = $badgeToShow['badge_name'];
        
        $insertNotificationQuery = "INSERT IGNORE INTO badge_notifications (user_id, badge_name) VALUES (?, ?)";
        $stmt = $connection->prepare($insertNotificationQuery);
        $stmt->bind_param("is", $userId, $badgeName);
        $stmt->execute();
        $stmt->close();
        
        // Clear session to prevent showing again
        unset($_SESSION['new_badge_awarded']);
    }

    // Helper function to get badge descriptions from database
    function getBadgeDescription($badgeName) {
        global $connection;
        
        $query = "SELECT description FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['description'];
        }
        
        $stmt->close();
        return 'Congratulations on earning this badge!';
    }

    // Helper function to get badge icons from database
    function getBadgeIcon($badgeName) {
        global $connection;
        
        $query = "SELECT icon_class FROM badgestbl WHERE badge_name = ? AND is_active = 1";
        $stmt = $connection->prepare($query);
        $stmt->bind_param("s", $badgeName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $badge = $result->fetch_assoc();
            $stmt->close();
            return $badge['icon_class'];
        }
        
        $stmt->close();
        return 'fas fa-star';
    }
?>
<?php
// Database connection
require_once 'database.php';

// Fetch user eco points if logged in
$userEcoPoints = 0;
if(isset($_SESSION["user_id"])) {
    $stmt = $connection->prepare("SELECT eco_points FROM accountstbl WHERE account_id = ?");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $userEcoPoints = $row['eco_points'];
    }
    $stmt->close();
}

// Fetch current active reward cycle
$currentCycle = null;
$cycleQuery = "SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
$cycleResult = $connection->query($cycleQuery);
if($cycleResult && $cycleResult->num_rows > 0) {
    $currentCycle = $cycleResult->fetch_assoc();
}

// Fetch cycle-based rankings if active cycle exists
require_once 'cycle_rankings.php';
$cycleTopIndividuals = [];
$cycleTopBarangays = [];
$cycleTopMunicipalities = [];
$cycleTopOrganizations = [];

if($currentCycle) {
    $cycleTopIndividuals = getCycleIndividualRankings($currentCycle['cycle_id'], 10);
    $cycleTopBarangays = getCycleBarangayRankings($currentCycle['cycle_id'], 5);
    $cycleTopMunicipalities = getCycleMunicipalityRankings($currentCycle['cycle_id'], 5);
    $cycleTopOrganizations = getCycleOrganizationRankings($currentCycle['cycle_id'], 5);
}

// Fetch top contributors (All-Time / Lifetime Rankings) - Exclude Barangay Officials
$topContributors = [];
$stmt = $connection->prepare("SELECT fullname, profile, profile_thumbnail, eco_points FROM accountstbl WHERE (accessrole IS NULL OR accessrole != 'Barangay Official') ORDER BY eco_points DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topContributors[] = $row;
}
$stmt->close();

/* Fetch recent activities
$recentActivities = [];
$stmt = $connection->prepare("SELECT u.name, a.activity_description, a.points_earned, a.activity_type 
                        FROM activity_log a 
                        JOIN accountstbl u ON a.account_id = u.account_id 
                        ORDER BY a.activity_date DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $recentActivities[] = $row;
}
$stmt->close();*/

// Fetch recent badge notifications
$recentBadges = [];
$badgeNotificationsQuery = "
    SELECT 
        bn.user_id,
        bn.badge_name,
        bn.notified_at,
        a.fullname,
        a.profile,
        a.profile_thumbnail,
        b.icon_class,
        b.image_path,
        b.color
    FROM badge_notifications bn
    JOIN accountstbl a ON bn.user_id = a.account_id
    LEFT JOIN badgestbl b ON bn.badge_name = b.badge_name AND b.is_active = 1
    ORDER BY bn.notified_at DESC
    LIMIT 5
";

$stmt = $connection->prepare($badgeNotificationsQuery);
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentBadges[] = $row;
    }
    $stmt->close();
}

// Fetch top barangays by points - Exclude Barangay Officials
$topBarangaysPoints = [];
$stmt = $connection->prepare("SELECT barangay, SUM(eco_points) as total_points 
                        FROM accountstbl 
                        WHERE (accessrole IS NULL OR accessrole != 'Barangay Official')
                        GROUP BY barangay 
                        ORDER BY total_points DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topBarangaysPoints[] = $row;
}
$stmt->close();

// Fetch top barangays by events
$topBarangaysEvents = [];
$stmt = $connection->prepare("SELECT barangay, COUNT(*) as event_count 
                        FROM eventstbl WHERE program_type != 'Announcement'
                        GROUP BY barangay 
                        ORDER BY event_count DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topBarangaysEvents[] = $row;
}
$stmt->close();

/* Fetch top municipalities by points - Exclude Barangay Officials */
$topMunicipalitiesPoints = [];
$stmt = $connection->prepare("SELECT city_municipality, SUM(eco_points) as total_points 
                        FROM accountstbl 
                        WHERE (accessrole IS NULL OR accessrole != 'Barangay Official')
                        GROUP BY city_municipality 
                        ORDER BY total_points DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topMunicipalitiesPoints[] = $row;
}
$stmt->close();

/* Fetch top municipalities by events */
$topMunicipalitiesEvents = [];
$stmt = $connection->prepare("SELECT city_municipality, COUNT(*) as event_count 
                        FROM eventstbl WHERE program_type != 'Announcement'
                        GROUP BY city_municipality 
                        ORDER BY event_count DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topMunicipalitiesEvents[] = $row;
}
$stmt->close();

/* Fetch top organizations by points - Exclude Barangay Officials */
$topOrganizationsPoints = [];
$stmt = $connection->prepare("SELECT organization, SUM(eco_points) as total_points 
                        FROM accountstbl 
                        WHERE organization IS NOT NULL 
                        AND organization != '' 
                        AND organization != 'N/A'
                        AND (accessrole IS NULL OR accessrole != 'Barangay Official')
                        GROUP BY organization 
                        ORDER BY total_points DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topOrganizationsPoints[] = $row;
}
$stmt->close();

/* Fetch top organizations by events */
$topOrganizationsEvents = [];
$stmt = $connection->prepare("SELECT organization, COUNT(*) as event_count 
                        FROM eventstbl WHERE program_type != 'Announcement'
                        AND organization IS NOT NULL 
                        AND organization != '' 
                        AND organization != 'N/A'
                        GROUP BY organization 
                        ORDER BY event_count DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $topOrganizationsEvents[] = $row;
}
$stmt->close();

// Calculate user's barangay points - Exclude Barangay Officials
if (isset($_SESSION['barangay'])) {
$stmt = $connection->prepare("SELECT SUM(eco_points) as barangay_points 
                        FROM accountstbl 
                        WHERE barangay = ?
                        AND (accessrole IS NULL OR accessrole != 'Barangay Official')");
$stmt->bind_param("s", $_SESSION['barangay']);
$stmt->execute();
$result = $stmt->get_result();
$_SESSION['barangay_points'] = $result->fetch_assoc()['barangay_points'] ?? 0;
$stmt->close();
}

// Similarly for municipality and organization
if (isset($_SESSION['city_municipality'])) {
    $stmt = $connection->prepare("SELECT SUM(eco_points) as municipality_points 
                                  FROM accountstbl 
                                  WHERE city_municipality = ?");
    $stmt->bind_param("s", $_SESSION['city_municipality']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['municipality_points'] = $result->fetch_assoc()['municipality_points'] ?? 0;
    $stmt->close();
}

if (isset($_SESSION['organization'])) {
    $stmt = $connection->prepare("SELECT SUM(eco_points) as organization_points 
                                  FROM accountstbl 
                                  WHERE organization = ?");
    $stmt->bind_param("s", $_SESSION['organization']);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['organization_points'] = $result->fetch_assoc()['organization_points'] ?? 0;
    $stmt->close();
}

// Fetch reward items from database
$rewardsQuery = "SELECT item_id as id, item_name as title, points_required as points, 
                        item_description, category, is_available, stock_quantity, image_path 
                 FROM ecoshop_itemstbl 
                 WHERE is_available = 1 
                 ORDER BY points_required ASC";
$rewardsResult = $connection->query($rewardsQuery);
$rewardsArray = [];
while($row = $rewardsResult->fetch_assoc()) {
    $rewardsArray[] = $row;
}

// Fetch unique categories for filtering
$categoriesQuery = "SELECT DISTINCT category FROM ecoshop_itemstbl WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categoriesResult = $connection->query($categoriesQuery);
$categoriesArray = [];
while($row = $categoriesResult->fetch_assoc()) {
    $categoriesArray[] = $row['category'];
}

// Fetch user's redemption transactions if logged in
$userTransactions = [];
$userPendingItemIds = []; // NEW: Track which items are pending

if(isset($_SESSION["user_id"])) {
    $transactionsQuery = "SELECT 
                            t.transaction_id,
                            t.item_id,
                            t.transaction_date,
                            t.status,
                            t.points_used,
                            t.quantity,
                            t.approval_date,
                            t.notes,
                            i.item_name,
                            i.category,
                            i.image_path
                         FROM ecoshop_transactions t
                         JOIN ecoshop_itemstbl i ON t.item_id = i.item_id
                         WHERE t.user_id = ?
                         ORDER BY t.transaction_date DESC
                         LIMIT 10";
    $stmt = $connection->prepare($transactionsQuery);
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $transactionsResult = $stmt->get_result();
    while($row = $transactionsResult->fetch_assoc()) {
        $userTransactions[] = $row;
        
        // NEW: Track pending item IDs
        if ($row['status'] === 'pending') {
            $userPendingItemIds[] = $row['item_id'];
        }
    }
    $stmt->close();
    
    // Remove duplicates
    $userPendingItemIds = array_unique($userPendingItemIds);
}

// Close connection
$connection->close();
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboards</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="leaderboards.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script type ="text/javascript" src ="app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Badge Notification Styles -->
    <style>
        .badge-notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease-in;
        }

        .badge-icon-fallback {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .badge-notification-modal {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            color: white;
            animation: bounceIn 0.6s ease-out;
        }

        .badge-celebration .badge-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }

        .badge-celebration h2 {
            margin: 1rem 0;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .new-badge-display {
            margin: 2rem 0;
            display: flex;
            justify-content: center;
        }

        .badge-card.earned {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .badge {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1rem;
            animation: rotate 3s linear infinite;
        }

        .badge.event-organizer { background: linear-gradient(135deg, #3498db, #2980b9); }
        .badge.mangrove-guardian { background: linear-gradient(135deg, #27ae60, #229954); }
        .badge.watchful-eye { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .badge.vigilant-protector { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .badge.conservation-champion { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .badge.ecosystem-sentinel { background: linear-gradient(135deg, #34495e, #2c3e50); }
        .badge.mangrove-legend { background: linear-gradient(135deg, #f1c40f, #f39c12); }

        .badge-description {
            font-size: 1.1rem;
            margin: 1.5rem 0;
            line-height: 1.5;
            opacity: 0.9;
        }

        .notification-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn-view-badge, .btn-close-notification {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-close-notification {
            background: white;
            color: #4CAF50;
        }

        .btn-view-badge:hover {
            background: white;
            color: #4CAF50;
        }

        .btn-close-notification:hover {
            background: rgba(255, 255, 255, 0.9);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .badge-notification-modal {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .notification-buttons {
                flex-direction: column;
            }
        }

        /* ============================================
           SHOPPING CART STYLES
           ============================================ */

        /* Floating Cart Button */
        .cart-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--base-clr);
            border-radius: 50%;
            box-shadow: 0 4px 20px rgba(18, 53, 36, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1000;
            border: none;
        }

        .cart-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 30px rgba(18, 53, 36, 0.5);
        }

        .cart-button i {
            color: white;
            font-size: 1.8rem;
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
            animation: cartPulse 2s infinite;
        }

        @keyframes cartPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        /* Cart Modal */
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 2000;
            animation: fadeIn 0.3s ease;
        }

        .cart-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-modal-content {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
            animation: slideInUp 0.3s ease;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 2px solid var(--accent-clr);
            background: var(--base-clr);
            color: white;
            border-radius: 20px 20px 0 0;
        }

        .cart-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-header h2 i {
            font-size: 1.6rem;
        }

        .cart-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 5px;
            transition: all 0.3s ease;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        .cart-body {
            padding: 20px 25px;
            overflow-y: auto;
            flex: 1;
            max-height: calc(80vh - 200px);
        }

        .cart-body::-webkit-scrollbar {
            width: 8px;
        }

        .cart-body::-webkit-scrollbar-track {
            background: var(--accent-clr);
            border-radius: 10px;
        }

        .cart-body::-webkit-scrollbar-thumb {
            background: var(--base-clr);
            border-radius: 10px;
        }

        .cart-empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--placeholder-text-clr);
        }

        .cart-empty i {
            font-size: 4rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .cart-empty p {
            font-size: 1.1rem;
            margin: 0;
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: var(--accent-clr);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .cart-item:hover {
            border-color: var(--base-clr);
            box-shadow: 0 4px 15px rgba(18, 53, 36, 0.1);
        }

        .cart-item-image {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            flex-shrink: 0;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-emoji {
            font-size: 2.5rem;
        }

        .cart-item-details {
            flex: 1;
            min-width: 0;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--text-clr);
            margin: 0 0 5px 0;
            font-size: 1rem;
        }

        .cart-item-points {
            color: var(--base-clr);
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cart-item-points i {
            font-size: 1rem;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            border-radius: 8px;
            padding: 5px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--base-clr);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--mangrove-clr);
            transform: scale(1.1);
        }

        .quantity-btn:disabled {
            background: var(--placeholder-text-clr);
            cursor: not-allowed;
            opacity: 0.5;
        }

        .quantity-display {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
            color: var(--text-clr);
            font-size: 1rem;
        }

        .remove-item-btn {
            width: 35px;
            height: 35px;
            border: none;
            background: #e74c3c;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .remove-item-btn:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        .cart-footer {
            padding: 20px 25px;
            border-top: 2px solid var(--accent-clr);
            background: var(--accent-clr);
            border-radius: 0 0 20px 20px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }

        .cart-total-label {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-clr);
        }

        .cart-total-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--base-clr);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cart-total-value i {
            font-size: 1.3rem;
        }

        .cart-checkout-btn {
            width: 100%;
            padding: 15px;
            background: var(--base-clr);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .cart-checkout-btn:hover:not(:disabled) {
            background: var(--mangrove-clr);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(18, 53, 36, 0.3);
        }

        .cart-checkout-btn:disabled {
            background: var(--placeholder-text-clr);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .cart-checkout-btn i {
            font-size: 1.2rem;
        }

        /* Updated Redeem Button Styles */
        .reward-item .redeem-btn {
            background: var(--base-clr);
        }

        .reward-item .redeem-btn:hover:not(:disabled) {
            background: var(--mangrove-clr);
        }

        .reward-item .add-to-cart-btn {
            background: var(--line-clr);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .reward-item .add-to-cart-btn:hover:not(:disabled) {
            background: var(--base-clr);
        }

        .reward-item .add-to-cart-btn i {
            font-size: 1.1rem;
        }

        .reward-item .cart-add-btn.in-cart {
            background: var(--mangrove-clr);
            border: 2px solid var(--base-clr);
            color: var(--base-clr);
        }

        .reward-item .cart-add-btn.in-cart:hover:not(:disabled) {
            background: var(--base-clr);
            color: azure;
        }

        /* Responsive Cart Styles */
        @media (max-width: 768px) {
            .cart-button {
                bottom: 20px;
                right: 20px;
                width: 55px;
                height: 55px;
            }

            .cart-button i {
                font-size: 1.6rem;
            }

            .cart-modal-content {
                width: 95%;
                max-height: 90vh;
            }

            .cart-header h2 {
                font-size: 1.3rem;
            }

            .cart-body {
                padding: 15px;
                max-height: calc(90vh - 180px);
            }

            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .cart-item-details {
                width: 100%;
            }

            .cart-item-controls {
                width: 100%;
                justify-content: space-between;
            }

            .cart-total {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .cart-button {
                bottom: 15px;
                right: 15px;
                width: 50px;
                height: 50px;
            }

            .cart-button i {
                font-size: 1.4rem;
            }

            .cart-badge {
                width: 20px;
                height: 20px;
                font-size: 0.7rem;
            }

            .cart-item-image {
                width: 60px;
                height: 60px;
            }

            .cart-item-emoji {
                font-size: 2rem;
            }

            .quantity-controls {
                gap: 5px;
            }

            .quantity-btn {
                width: 28px;
                height: 28px;
                font-size: 1rem;
            }

            .remove-item-btn {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <ul class="nav-list">
                <li>
                    <i class="bx bx-home"></i>
                    <a href="index.php">Home</a>
                </li>
                <li>
                    <i class="bx bx-bulb"></i>
                    <a href="reportspage.php">Reports</a>
                </li>
                <li>
                    <i class="bx bx-calendar-event"></i>
                    <a href="events.php">Events</a>
                </li>
                <li>
                    <i class="bx bx-trophy"></i>
                    <a class="active" href="leaderboards.php">Leaderboards</a>
                </li>
                <?php if (isset($_SESSION["name"])): ?>
                <li>
                    <i class="bx bx-group"></i>
                    <a href="organizations.php">Organizations</a>
                </li>
                <?php endif; ?>
            </ul>
            <?php 
            if (isset($_SESSION["name"])) {
                // Show profile icon when logged in
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
                // Show login link when not logged in
                echo '<a href="login.php" class="login-link">Login</a>';
            }
            ?>
            </nav>
        </header>
    <aside id="sidebar" class="close">  
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button onclick= "SidebarToggle()"id="toggle-btn" class="rotate">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
                </button>
            </li>
            <hr>
            <li>
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="initiatives.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h168q13-36 43.5-58t68.5-22q38 0 68.5 22t43.5 58h168q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm80-80h280v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80Zm200-190q13 0 21.5-8.5T510-760q0-13-8.5-21.5T480-790q-13 0-21.5 8.5T450-760q0 13 8.5 21.5T480-730Z"/></svg>
                    <span>Initiatives</span>
                </a>
            </li>
            <li>
                <a href="mangrovemappage.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-690v-100q0-42 29-71t71-29h100v100q0 42-29 71t-71 29H440ZM220-450q-58 0-99-41t-41-99v-140h140q58 0 99 41t41 99v140H220ZM640-90q-39 0-74.5-12T501-135l-33 33q-11 11-28 11t-28-11q-11-11-11-28t11-28l33-33q-21-29-33-64.5T400-330q0-100 70-170.5T640-571h241v241q0 100-70.5 170T640-90Zm0-80q67 0 113-47t46-113v-160H640q-66 0-113 46.5T480-330q0 23 5.5 43.5T502-248l110-110q11-11 28-11t28 11q11 11 11 28t-11 28L558-192q18 11 38.5 16.5T640-170Zm1-161Z"/></svg>
                    <span>Explore Map</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="reportspage.php" tabindex="-1">My Reports</a></li>
                    <li><a href="myevents.php" tabindex="-1">My Events</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <a href="about.php" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-29t36.5-11q19 0 35 11t16 29q0 17-12 29.5T484-540q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>
                    <span>About</span>
                </a>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && ($_SESSION['accessrole'] == "Barangay Official" || $_SESSION['accessrole'] == "Administrator" || $_SESSION['accessrole'] == "Representative")) {
                    ?>
                        <li class="admin-link">
                            <a href="adminpage.php" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M680-280q25 0 42.5-17.5T740-340q0-25-17.5-42.5T680-400q-25 0-42.5 17.5T620-340q0 25 17.5 42.5T680-280Zm0 120q31 0 57-14.5t42-38.5q-22-13-47-20t-52-7q-27 0-52 7t-47 20q16 24 42 38.5t57 14.5ZM480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v227q-19-8-39-14.5t-41-9.5v-147l-240-90-240 90v188q0 47 12.5 94t35 89.5Q310-290 342-254t71 60q11 32 29 61t41 52q-1 0-1.5.5t-1.5.5Zm200 0q-83 0-141.5-58.5T480-280q0-83 58.5-141.5T680-480q83 0 141.5 58.5T880-280q0 83-58.5 141.5T680-80ZM480-494Z"/></svg>
                                <span>Administrator Lobby</span>
                            </a>
                        </li>
                    <?php
                }
            ?>
    </aside>
    <main>
        <?php if(!empty($_SESSION['response'])): ?>
        <div class="flash-container">
            <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
                <?= $_SESSION['response']['msg'] ?>
            </div>
        </div>
        <?php 
        unset($_SESSION['response']); 
        endif; 
        ?>

        <!-- Badge Award Notification -->
        <?php if($showBadgeNotification && $badgeToShow): ?>
        <div class="badge-notification-overlay" id="badgeNotification">
            <div class="badge-notification-modal">
                <div class="badge-celebration">
                    <div class="badge-icon">
                        <i class="fas fa-medal"></i>
                    </div>
                    <h2>🎉 Congratulations! 🎉</h2>
                    <p>You've earned a new badge!</p>
                    <div class="new-badge-display">
                        <div class="badge-card earned">
                            <?php 
                            $badgeName = $badgeToShow['badge_name'];
                            $badgeClass = strtolower(str_replace(' ', '-', $badgeName));
                            $badgeIcon = getBadgeIcon($badgeName);
                            $badgeDescription = getBadgeDescription($badgeName);
                            ?>
                            <div class="badge <?= $badgeClass ?>">
                                <i class="<?= $badgeIcon ?>"></i>
                            </div>
                            <p><?= htmlspecialchars($badgeName) ?></p>
                        </div>
                    </div>
                    <p class="badge-description"><?= htmlspecialchars($badgeDescription) ?></p>
                    <div class="notification-buttons">
                        <button onclick="viewBadgeDetails()" class="btn-view-badge">View Badge</button>
                        <button onclick="closeBadgeNotification()" class="btn-close-notification">Awesome!</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
            <!-- Profile Details Popup (positioned relative to header) -->
        <div class="profile-details close" id="profile-details">
            <div class="details-box">
                <?php
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                        echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="big-profile-icon">';
                    } else {
                        echo '<div class="big-default-profile-icon"><i class="fas fa-user"></i></div>';
                    }
                ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["personal_email"]) ? $_SESSION["personal_email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <?php if(isset($_SESSION["organization"])){ 
                    if(!empty($_SESSION["organization"]) || ($_SESSION["organization"] == "N/A")) {?>
                    <p><?= $_SESSION["organization"] ?></p>
                <?php 
                    }
                } ?>
                <p>Barangay <?= isset($_SESSION["barangay"]) ? $_SESSION["barangay"] : "" ?>, <?= isset($_SESSION["city_municipality"]) ? $_SESSION["city_municipality"] : "" ?></p> 
                <div class="profile-link-container">
                    <a href="profileform.php" class="profile-link">Edit Profile <i class="fa fa-angle-double-right"></i></a>
                </div>
            </div>
            <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <!-- Leaderboards Section -->
    <section class="leaderboards">
        <h1>Mangrove Conservation Hub</h1>
        
        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('leaderboards')" data-tab="leaderboards">
                <i class="fas fa-chart-bar"></i> Leaderboards
            </button>
            <button class="tab-btn" onclick="switchTab('eco-guide')" data-tab="eco-guide">
                <i class="fas fa-leaf"></i> Eco Points Guide
            </button>
            <button class="tab-btn" onclick="switchTab('shop')" data-tab="shop">
                <i class="fas fa-shopping-cart"></i> Eco Shop
            </button>
        </div>

        <!-- Leaderboards Tab Content -->
        <div id="leaderboards-tab" class="tab-content active">
            <div class="leaderboards-content">
                <!-- User's Points Summary with Claim Button -->
                <div class="user-points">
                    <div class="points-header">
                        <h2>Your Eco Points</h2>
                        <?php if(isset($_SESSION['user_id'])): ?>
                        <button class="claim-rewards-btn" onclick="openRewardsModal()" title="View & Claim Rewards">
                            <i class="fas fa-gift"></i>
                            <span class="pending-indicator" id="pending-indicator" style="display: none;"></span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="points-value" id="user-points-value"><?= $userEcoPoints ?></div>
                    <p>Keep contributing to earn more points!</p>
                    <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="pending-rewards-notice" id="pending-rewards-notice" style="display: none;">
                        <i class="fas fa-info-circle"></i>
                        You have <strong id="pending-count">0</strong> pending reward(s) to claim!
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Filters -->
                <div class="leaderboard-filters">
                    <button class="filter-btn active" data-filter="individual">Individuals</button>
                    <button class="filter-btn" data-filter="barangay">Barangays</button>
                    <button class="filter-btn" data-filter="municipality">Municipalities</button>
                    <button class="filter-btn" data-filter="organization">Organizations</button>
                </div>
                
                <!-- Top Contributors Section (All Categories) -->
                <div class="leaderboards-list">
                    <!-- Individual Leaderboards -->
                    <div class="leaderboards-grid" id="individual-leaderboards">
                        <!-- Top Contributors -->
                        <div class="leaderboard">
                            <h2><i class="fas fa-trophy"></i> All-Time Top Contributors</h2>
                            <p class="leaderboard-subtitle" style="color: #888; font-size: 0.9em; margin-top: 5px;">Lifetime eco points earned</p>
                            <ul id="top-contributors">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                        
                        <!-- Recent Activities -->
                        <div class="leaderboard">
                            <h2>Recent Activities</h2>
                            <ul class="activities-timeline" id="recent-activities">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Cycle Rewards Sidebar -->
                <div class="leaderboards-cycles">
                    <!-- Cycle Rewards Section for Individuals -->
                    <?php if($currentCycle): ?>
                    <div class="cycle-rewards-section">
                        <div class="cycle-header">
                            <div class="cycle-badge">
                                <i class='bx bx-trophy'></i>
                                <span><?= htmlspecialchars($currentCycle['cycle_name']) ?> Cycle</span>
                            </div>
                            <div class="cycle-dates">
                                <?= date('M j', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y', strtotime($currentCycle['end_date'])) ?>
                            </div>
                        </div>
                        
                        <div class="leaderboards-grid cycle-grid">
                            <div class="leaderboard cycle-leaderboard">
                                <h2><i class="fas fa-medal"></i> Current Cycle Top 10</h2>
                                <p class="cycle-subtitle">Based on activity during this cycle period only. These contributors will receive rewards when the cycle ends.</p>
                                <ul id="cycle-top-individuals">
                                    <!-- Filled by JavaScript -->
                                </ul>
                            </div>
                            
                            <div class="leaderboard cycle-rewards-info">
                                <h2><i class="fas fa-gift"></i> Reward Distribution</h2>
                                <p class="cycle-subtitle">Points awarded per rank at cycle end</p>
                                <div class="rewards-breakdown">
                                    <div class="reward-tier tier-gold">
                                        <div class="tier-rank">#1</div>
                                        <div class="tier-points">500 pts</div>
                                    </div>
                                    <div class="reward-tier tier-silver">
                                        <div class="tier-rank">#2</div>
                                        <div class="tier-points">300 pts</div>
                                    </div>
                                    <div class="reward-tier tier-bronze">
                                        <div class="tier-rank">#3</div>
                                        <div class="tier-points">200 pts</div>
                                    </div>
                                    <div class="reward-tier">
                                        <div class="tier-rank">#4-5</div>
                                        <div class="tier-points">100 pts</div>
                                    </div>
                                    <div class="reward-tier">
                                        <div class="tier-rank">#6-10</div>
                                        <div class="tier-points">50 pts</div>
                                    </div>
                                </div>
                                <div class="cycle-note">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Rewards are claimable after the admin finalizes this cycle. Keep earning points to climb the rankings!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Cycle Rewards Section for Barangays (MOVED TO TOP) -->
                    <?php if($currentCycle): ?>
                    <div class="cycle-rewards-section" id="barangay-cycle-section" style="display:none;">
                        <div class="cycle-header">
                            <div class="cycle-badge">
                                <i class='bx bx-trophy'></i>
                                <span><?= htmlspecialchars($currentCycle['cycle_name']) ?> Cycle</span>
                            </div>
                            <div class="cycle-dates">
                                <?= date('M j', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y', strtotime($currentCycle['end_date'])) ?>
                            </div>
                        </div>
                        
                        <div class="leaderboards-grid cycle-grid">
                            <div class="leaderboard cycle-leaderboard">
                                <h2><i class="fas fa-medal"></i> Current Top 5 Barangays</h2>
                                <p class="cycle-subtitle">All members will receive rewards when cycle ends</p>
                                <ul id="cycle-top-barangays">
                                    <!-- Filled by JavaScript -->
                                </ul>
                            </div>
                            
                            <div class="leaderboard cycle-rewards-info">
                                <h2><i class="fas fa-gift"></i> Group Reward Distribution</h2>
                                <p class="cycle-subtitle">Points per member at cycle end</p>
                                <div class="rewards-breakdown">
                                    <div class="reward-tier tier-gold">
                                        <div class="tier-rank">#1</div>
                                        <div class="tier-points">300 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-silver">
                                        <div class="tier-rank">#2</div>
                                        <div class="tier-points">200 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-bronze">
                                        <div class="tier-rank">#3</div>
                                        <div class="tier-points">150 pts/member</div>
                                    </div>
                                    <div class="reward-tier">
                                        <div class="tier-rank">#4-5</div>
                                        <div class="tier-points">100 pts/member</div>
                                    </div>
                                </div>
                                <div class="cycle-note">
                                    <i class="fas fa-users"></i>
                                    <p>Every member of the winning barangay receives the full reward amount! Collaborate to climb the rankings together!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Barangay Leaderboards (All-Time / Default) -->
                    <div class="leaderboards-grid" id="barangay-leaderboards" style="display:none;">
                        <!-- Barangay Points -->
                        <div class="leaderboard">
                            <h2><i class="fas fa-trophy"></i> All-Time Top Barangays by Points</h2>
                            <p class="leaderboard-subtitle" style="color: #888; font-size: 0.9em; margin-top: 5px;">Lifetime eco points earned</p>
                            <ul id="top-barangays-points">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                        
                        <!-- Barangay Events -->
                        <div class="leaderboard">
                            <h2>Top Barangays by Events</h2>
                            <ul id="top-barangays-events">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                    </div>
                    <!-- Cycle Rewards Section for Municipalities (MOVED TO TOP) -->
                    <?php if($currentCycle): ?>
                    <div class="cycle-rewards-section" id="municipality-cycle-section" style="display:none;">
                        <div class="cycle-header">
                            <div class="cycle-badge">
                                <i class='bx bx-trophy'></i>
                                <span><?= htmlspecialchars($currentCycle['cycle_name']) ?> Cycle</span>
                            </div>
                            <div class="cycle-dates">
                                <?= date('M j', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y', strtotime($currentCycle['end_date'])) ?>
                            </div>
                        </div>
                        
                        <div class="leaderboards-grid cycle-grid">
                            <div class="leaderboard cycle-leaderboard">
                                <h2><i class="fas fa-medal"></i> Current Top 5 Municipalities</h2>
                                <p class="cycle-subtitle">All members will receive rewards when cycle ends</p>
                                <ul id="cycle-top-municipalities">
                                    <!-- Filled by JavaScript -->
                                </ul>
                            </div>
                            
                            <div class="leaderboard cycle-rewards-info">
                                <h2><i class="fas fa-gift"></i> Group Reward Distribution</h2>
                                <p class="cycle-subtitle">Points per member at cycle end</p>
                                <div class="rewards-breakdown">
                                    <div class="reward-tier tier-gold">
                                        <div class="tier-rank">#1</div>
                                        <div class="tier-points">300 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-silver">
                                        <div class="tier-rank">#2</div>
                                        <div class="tier-points">200 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-bronze">
                                        <div class="tier-rank">#3</div>
                                        <div class="tier-points">150 pts/member</div>
                                    </div>
                                    <div class="reward-tier">
                                        <div class="tier-rank">#4-5</div>
                                        <div class="tier-points">100 pts/member</div>
                                    </div>
                                </div>
                                <div class="cycle-note">
                                    <i class="fas fa-users"></i>
                                    <p>Every member of the winning municipality receives the full reward amount! Collaborate to climb the rankings together!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Municipality Leaderboards (All-Time / Default) -->
                    <div class="leaderboards-grid" id="municipality-leaderboards" style="display:none;">
                        <!-- Municipality Points -->
                        <div class="leaderboard">
                            <h2><i class="fas fa-trophy"></i> All-Time Top Municipalities by Points</h2>
                            <p class="leaderboard-subtitle" style="color: #888; font-size: 0.9em; margin-top: 5px;">Lifetime eco points earned</p>
                            <ul id="top-municipalities-points">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                        
                        <!-- Municipality Events -->
                        <div class="leaderboard">
                            <h2>Top Municipalities by Events</h2>
                            <ul id="top-municipalities-events">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Cycle Rewards Section for Organizations (MOVED TO TOP) -->
                    <?php if($currentCycle): ?>
                    <div class="cycle-rewards-section" id="organization-cycle-section" style="display:none;">
                        <div class="cycle-header">
                            <div class="cycle-badge">
                                <i class='bx bx-trophy'></i>
                                <span><?= htmlspecialchars($currentCycle['cycle_name']) ?> Cycle</span>
                            </div>
                            <div class="cycle-dates">
                                <?= date('M j', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y', strtotime($currentCycle['end_date'])) ?>
                            </div>
                        </div>
                        
                        <div class="leaderboards-grid cycle-grid">
                            <div class="leaderboard cycle-leaderboard">
                                <h2><i class="fas fa-medal"></i> Current Top 5 Organizations</h2>
                                <p class="cycle-subtitle">All members will receive rewards when cycle ends</p>
                                <ul id="cycle-top-organizations">
                                    <!-- Filled by JavaScript -->
                                </ul>
                            </div>
                            
                            <div class="leaderboard cycle-rewards-info">
                                <h2><i class="fas fa-gift"></i> Group Reward Distribution</h2>
                                <p class="cycle-subtitle">Points per member at cycle end</p>
                                <div class="rewards-breakdown">
                                    <div class="reward-tier tier-gold">
                                        <div class="tier-rank">#1</div>
                                        <div class="tier-points">300 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-silver">
                                        <div class="tier-rank">#2</div>
                                        <div class="tier-points">200 pts/member</div>
                                    </div>
                                    <div class="reward-tier tier-bronze">
                                        <div class="tier-rank">#3</div>
                                        <div class="tier-points">150 pts/member</div>
                                    </div>
                                    <div class="reward-tier">
                                        <div class="tier-rank">#4-5</div>
                                        <div class="tier-points">100 pts/member</div>
                                    </div>
                                </div>
                                <div class="cycle-note">
                                    <i class="fas fa-users"></i>
                                    <p>Every member of the winning organization receives the full reward amount! Collaborate to climb the rankings together!</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Organization Leaderboards (All-Time / Default) -->
                    <div class="leaderboards-grid" id="organization-leaderboards" style="display:none;">
                        <!-- Organization Points -->
                        <div class="leaderboard">
                            <h2><i class="fas fa-trophy"></i> All-Time Top Organizations by Points</h2>
                            <p class="leaderboard-subtitle" style="color: #888; font-size: 0.9em; margin-top: 5px;">Lifetime eco points earned</p>
                            <ul id="top-organizations-points">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                        
                        <!-- Organization Events -->
                        <div class="leaderboard">
                            <h2>Top Organizations by Events</h2>
                            <ul id="top-organizations-events">
                                <!-- Filled by JavaScript -->
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="leaderboards-charts">
                    <div class="graph-container">
                        <h2 class="section-heading">Performance Overview</h2>
                        <div class="chart-group">
                            <div class="chart-wrapper">
                                <canvas id="pointsChart"></canvas>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="eventsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Eco Points Guide Tab Content -->
        <div id="eco-guide-tab" class="tab-content">
            <div class="eco-points-guide">
                <h2 class="section-heading">How to Earn Eco Points</h2>
                <p style="text-align: center; margin-bottom: 2rem; color: var(--placeholder-text-clr); font-size: 1.1rem;">
                    Participate in mangrove conservation activities and earn points to make a real environmental impact!
                </p>
                
                <div class="points-categories">
                    <div class="points-category">
                        <h3><i class="fas fa-calendar-check"></i> Daily Activities</h3>
                        <ul>
                            <li>Daily login <span class="points-amount">+5 pts</span></li>
                        </ul>
                    </div>
                    
                    <div class="points-category">
                        <h3><i class="fas fa-calendar-alt"></i> Event Participation</h3>
                        <ul>
                            <li>Attend events (check-in & check-out) <span class="points-amount">+20-above pts</span></li>
                        </ul>
                    </div>
                    
                    <div class="points-category">
                        <h3><i class="fas fa-exclamation-triangle"></i> Community Protection</h3>
                        <ul>
                            <li>Submit illegal activity reports <span class="points-amount">+25-50 pts</span></li>
                        </ul>
                    </div>
                    
                    <div class="points-category">
                        <h3><i class="fas fa-users"></i> Organization Membership</h3>
                        <ul>
                            <li>Join an organization <span class="points-amount">+100 pts</span></li>
                        </ul>
                    </div>
                    
                    <div class="points-category">
                        <h3><i class="fas fa-trophy"></i> Leaderboard Rankings</h3>
                        <ul>
                            <li>Monthly leaderboard rewards <span class="points-amount">Depends on placement</span></li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: 2rem; text-align: center; padding: 2rem; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 10px; border-left: 4px solid var(--base-clr);">
                    <h3 style="color: var(--base-clr); margin-bottom: 1rem;">🌿 Conservation Impact</h3>
                    <p style="color: var(--text-clr); font-size: 1.1rem; line-height: 1.6; margin: 0;">
                        Together, every action you take strengthens our mangrove forests and builds a more resilient future for all. 
                        Your points represent real environmental impact!
                    </p>
                </div>
            </div>
        </div>

        <!-- Shop Tab Content -->
        <div id="shop-tab" class="tab-content">
            <div class="eco-shop">
                <h2 class="section-heading">Eco Rewards Shop</h2>
                <p style="text-align: center; margin-bottom: 1rem; color: var(--placeholder-text-clr); font-size: 1.1rem;">
                    Redeem your hard-earned eco points for sustainable rewards and conservation tools!
                </p>
                
                <!-- User Transaction Status Section -->
                <?php if(isset($_SESSION["user_id"]) && !empty($userTransactions)): ?>
                <div class="transaction-status-section" style="margin-bottom: 2rem;">
                    <h3 style="color: #2c5530; margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-history"></i> Your Recent Redemptions
                    </h3>
                    <div class="transactions-container" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 10px; padding: 15px; background: #f9f9f9;">
                        <?php foreach($userTransactions as $transaction): ?>
                        <div class="transaction-item" style="display: flex; align-items: center; padding: 12px; margin-bottom: 10px; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                            <div class="transaction-image" style="width: 50px; height: 50px; margin-right: 15px;">
                                <?php if(!empty($transaction['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($transaction['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($transaction['item_name']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; background: #e0e0e0; border-radius: 6px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-gift" style="color: #888;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-details" style="flex: 1;">
                                <h4 style="margin: 0 0 5px 0; color: #2c5530; font-size: 0.95em;">
                                    <?php echo htmlspecialchars($transaction['item_name']); ?>
                                </h4>
                                <p style="margin: 0; font-size: 0.85em; color: #666;">
                                    <i class="fas fa-coins"></i> <?php echo number_format($transaction['points_used']); ?> points • 
                                    <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?>
                                </p>
                                <?php if(!empty($transaction['notes']) && $transaction['status'] === 'rejected'): ?>
                                <p style="margin: 5px 0 0 0; font-size: 0.8em; color: #e74c3c;">
                                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($transaction['notes']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-status" style="text-align: center; min-width: 100px;">
                                <?php
                                switch($transaction['status']) {
                                    case 'pending':
                                        echo '<span style="background: #f39c12; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600;">
                                                <i class="fas fa-clock"></i> Pending
                                              </span>';
                                        break;
                                    case 'approved':
                                        echo '<span style="background: #27ae60; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600;">
                                                <i class="fas fa-check"></i> Approved
                                              </span>';
                                        if($transaction['approval_date']) {
                                            echo '<br><small style="color: #888; font-size: 0.7em;">'.date('M j', strtotime($transaction['approval_date'])).'</small>';
                                        }
                                        break;
                                    case 'rejected':
                                        echo '<span style="background: #e74c3c; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600;">
                                                <i class="fas fa-times"></i> Rejected
                                              </span>';
                                        break;
                                    default:
                                        echo '<span style="background: #95a5a6; color: white; padding: 6px 12px; border-radius: 15px; font-size: 0.8em; font-weight: 600;">
                                                Unknown
                                              </span>';
                                }
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 10px;">
                        <small style="color: #888;">
                            <i class="fas fa-info-circle"></i> 
                            Only your most recent 10 transactions are shown. Pending items cannot be redeemed again.
                        </small>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Category Filter -->
                <div class="shop-filters">
                    <div class="filter-group">
                        <label for="category-filter">Filter by Category:</label>
                        <select id="category-filter" onchange="filterRewardsByCategory()">
                            <option value="">All Categories</option>
                            <?php foreach ($categoriesArray as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo ucfirst(htmlspecialchars($category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-stats">
                        <span id="rewards-count"><?php echo count($rewardsArray); ?></span> items available
                    </div>
                </div>
                
                <!--container for flash-message-->
                <div class="shop-message"></div>
                
                <div class="rewards-grid" id="rewards-grid">
                    <!-- Rewards will be populated by JavaScript -->
                </div>
                
                <div class="pagination" id="pagination">
                    <!-- Pagination buttons will be added by JavaScript -->
                </div>
            </div>
        </div>
    </section>
    
    <!-- Rewards Claim Modal -->
    <?php if(isset($_SESSION['user_id'])): ?>
    <div id="rewardsModal" class="rewards-modal" style="display: none;">
        <div class="rewards-modal-content">
            <div class="rewards-modal-header">
                <h2><i class="fas fa-gift"></i> Your Leaderboard Rewards</h2>
                <span class="rewards-modal-close" onclick="closeRewardsModal()">&times;</span>
            </div>
            
            <div class="rewards-modal-body">
                <!-- Pending Rewards Section -->
                <div class="rewards-section pending-section">
                    <div class="section-header">
                        <h3><i class="fas fa-clock"></i> Pending Rewards</h3>
                        <button class="claim-all-btn" id="claim-all-btn" onclick="claimAllRewards()" style="display: none;">
                            <i class="fas fa-hand-holding-usd"></i> Claim All
                        </button>
                    </div>
                    <div id="pending-rewards-list" class="rewards-list">
                        <div class="loading-message">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
                
                <!-- Claimed Rewards Section -->
                <div class="rewards-section claimed-section">
                    <h3><i class="fas fa-check-circle"></i> Claimed Rewards</h3>
                    <div id="claimed-rewards-list" class="rewards-list">
                        <div class="loading-message">
                            <i class="fas fa-spinner fa-spin"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    </main>
    <footer>
                <div id="right-footer">
                    <h3>Follow us on</h3>
                    <div id="social-media-footer">
                        <ul>
                            <li>
                                <a href="#">
                                    <i class="fab fa-facebook"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <p>This website is developed by ManGrow. All Rights Reserved.</p>
                </div>
            </footer>
<script>
//write in the console log the session values of points
console.log('User Eco Points:', <?= $userEcoPoints ?>);
console.log('Barangay Points:', <?= isset($_SESSION['barangay_points']) ? $_SESSION['barangay_points'] : 0 ?>);
console.log('Municipality Points:', <?= isset($_SESSION['municipality_points']) ? $_SESSION['municipality_points'] : 0 ?>);
console.log('Organization Points:', <?= isset($_SESSION['organization_points']) ? $_SESSION['organization_points'] : 0 ?>);

// Tab switching functionality
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked tab button
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    
    // Special handling for leaderboards tab - ensure charts are properly sized
    if (tabName === 'leaderboards') {
        setTimeout(() => {
            if (pointsChart) pointsChart.resize();
            if (eventsChart) eventsChart.resize();
        }, 100);
    }
    
    // Special handling for rewards tab - sync pending transactions
    if (tabName === 'rewards') {
        console.log('Switched to rewards tab - syncing pending transactions...');
        syncPendingTransactions();
    }
}

// Reward items data
const rewards = <?= json_encode($rewardsArray) ?>;
let filteredRewards = rewards; // For category filtering

// Pagination variables
const rewardsPerPage = 8;
let currentPage = 1;

// Chart variables
let leaderboardChart;


// recentActivities: <json_encode($recentActivities) >,
const phpData = {
    userEcoPoints: <?= $userEcoPoints ?>,
    topContributors: <?= json_encode($topContributors) ?>,
    topBarangaysPoints: <?= json_encode($topBarangaysPoints) ?>,
    topBarangaysEvents: <?= json_encode($topBarangaysEvents) ?>,
    topMunicipalitiesPoints: <?= json_encode($topMunicipalitiesPoints) ?>,
    topMunicipalitiesEvents: <?= json_encode($topMunicipalitiesEvents) ?>,
    topOrganizationsPoints: <?= json_encode($topOrganizationsPoints) ?>,
    topOrganizationsEvents: <?= json_encode($topOrganizationsEvents) ?>,
    recentBadges: <?= json_encode($recentBadges) ?>,
    userPendingItemIds: <?= json_encode($userPendingItemIds) ?>,
    // Cycle-based rankings (activity during current cycle period)
    cycleTopIndividuals: <?= json_encode($cycleTopIndividuals) ?>,
    cycleTopBarangays: <?= json_encode($cycleTopBarangays) ?>,
    cycleTopMunicipalities: <?= json_encode($cycleTopMunicipalities) ?>,
    cycleTopOrganizations: <?= json_encode($cycleTopOrganizations) ?>
};

document.addEventListener('DOMContentLoaded', function() {
    // Initialize user points
    document.getElementById('user-points-value').textContent = phpData.userEcoPoints;
    
    // Initialize rewards pagination
    displayRewards();
    setupPagination();
    
    // Initialize chart
    initializeCharts();
    
    // Load all leaderboard data
    loadLeaderboardData();
    populateBadgeActivities();
    
    // Set up filter buttons
    setupFilters();
    
    // Check for pending leaderboard rewards
    checkPendingRewards();
    
    // Auto-refresh cycle leaderboards every 30 seconds (like "Earned this week")
    setInterval(refreshCycleLeaderboards, 30000);
});

// Responsive: re-run filter display on window resize
    window.addEventListener('resize', function() {
        // Find the active filter button
        const activeBtn = document.querySelector('.filter-btn.active');
        if (activeBtn) {
            const filter = activeBtn.getAttribute('data-filter');
            const leaderboardElement = document.getElementById(`${filter}-leaderboards`);
            if (window.innerWidth <= 700) {
                leaderboardElement.style.display = 'flex';
            } else {
                leaderboardElement.style.display = 'grid';
            }
        }
    });

function displayRewards(page = 1) {
    const rewardsGrid = document.getElementById('rewards-grid');
    rewardsGrid.innerHTML = '';
    
    const startIndex = (page - 1) * rewardsPerPage;
    const endIndex = startIndex + rewardsPerPage;
    const paginatedRewards = filteredRewards.slice(startIndex, endIndex);
    
    paginatedRewards.forEach(reward => {
        const rewardItem = document.createElement('div');
        rewardItem.className = 'reward-item';
        
        // Determine image source
        const imageHtml = reward.image_path && reward.image_path.trim() !== '' 
            ? `<img src="${reward.image_path}" alt="${reward.title}" onclick="showRewardImagePreview('${reward.image_path}', '${reward.title}')">` 
            : '<span class="reward-emoji">🎁</span>';
        
        // Create description with truncation for long descriptions
        let displayDescription = reward.item_description || 'No description available';
        const isLongDescription = displayDescription.length > 80;
        const shortDescription = isLongDescription ? displayDescription.substring(0, 80) + '...' : displayDescription;
        
        rewardItem.innerHTML = `
            <div class="reward-image">${imageHtml}</div>
            <div class="reward-content">
                <div class="reward-title">${reward.title}</div>
                <div class="reward-description">
                    <span class="description-text">${shortDescription}</span>
                    ${isLongDescription ? `<button class="show-more-btn" onclick="toggleDescription(this, '${reward.id}')">Show more</button>` : ''}
                    <div class="full-description" style="display: none;">${displayDescription}</div>
                </div>
                <div class="reward-details">
                    <div class="reward-points">
                        <i class="fas fa-coins"></i>
                        ${reward.points} Eco Points
                    </div>
                    <div class="reward-category">
                        <i class="fas fa-tag"></i>
                        ${reward.category ? reward.category.charAt(0).toUpperCase() + reward.category.slice(1) : 'General'}
                    </div>
                    ${reward.stock_quantity ? `<div class="reward-stock"><i class="fas fa-box"></i> Stock: ${reward.stock_quantity}</div>` : ''}
                </div>
                <button class="redeem-btn cart-add-btn" 
                    data-reward-id="${reward.id}"
                    data-item-name="${reward.title.replace(/'/g, '&apos;')}"
                    data-points="${reward.points}"
                    data-image="${reward.image_path || ''}"
                    ${reward.is_available == 0 || (reward.stock_quantity && reward.stock_quantity <= 0) ? 'disabled' : ''}>
                    <i class='bx bx-cart-add'></i>
                    ${reward.is_available == 0 || (reward.stock_quantity && reward.stock_quantity <= 0) ? 'Out of Stock' : 'Add to Cart'}
                </button>
            </div>
        `;
        rewardsGrid.appendChild(rewardItem);
    });
    
    // Setup cart button handlers after rendering
    setupCartButtonHandlers();
    
    // Update cart UI to reflect current cart state
    updateCartUI();
    
    // Update active page button
    document.querySelectorAll('.page-btn').forEach(btn => {
        btn.classList.remove('active');
        if(parseInt(btn.dataset.page) === page) {
            btn.classList.add('active');
        }
    });
    
    currentPage = page;
}

function setupPagination() {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    const pageCount = Math.ceil(filteredRewards.length / rewardsPerPage);
    
    // Previous button
    const prevButton = document.createElement('button');
    prevButton.className = 'page-btn';
    prevButton.textContent = 'Previous';
    prevButton.dataset.page = 'prev';
    prevButton.addEventListener('click', () => {
        if(currentPage > 1) {
            displayRewards(currentPage - 1);
        }
    });
    pagination.appendChild(prevButton);
    
    // Page buttons
    for(let i = 1; i <= pageCount; i++) {
        const pageButton = document.createElement('button');
        pageButton.className = `page-btn ${i === 1 ? 'active' : ''}`;
        pageButton.textContent = i;
        pageButton.dataset.page = i;
        pageButton.addEventListener('click', () => displayRewards(i));
        pagination.appendChild(pageButton);
    }
    
    // Next button
    const nextButton = document.createElement('button');
    nextButton.className = 'page-btn';
    nextButton.textContent = 'Next';
    nextButton.dataset.page = 'next';
    nextButton.addEventListener('click', () => {
        if(currentPage < pageCount) {
            displayRewards(currentPage + 1);
        }
    });
    pagination.appendChild(nextButton);
}

let pointsChart, eventsChart;

function initializeCharts() {
    // Points chart
    const pointsCtx = document.getElementById('pointsChart').getContext('2d');
    pointsChart = new Chart(pointsCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Eco Points',
                data: [],
                backgroundColor: 'rgba(62, 123, 39, 0.7)',
                borderColor: 'rgba(62, 123, 39, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Eco Points'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Points Comparison',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            if (pointsChart.data.performerNames && pointsChart.data.performerNames[context.dataIndex]) {
                                return 'Name: ' + pointsChart.data.performerNames[context.dataIndex];
                            }
                            return '';
                        }
                    }
                }
            }
        }
    });

    // Events chart
    const eventsCtx = document.getElementById('eventsChart').getContext('2d');
    eventsChart = new Chart(eventsCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Events Count',
                data: [],
                backgroundColor: 'rgba(241, 196, 15, 0.7)',
                borderColor: 'rgba(241, 196, 15, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Events Count'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Events Comparison',
                    font: {
                        size: 16
                    }
                },
                tooltip: {
                    callbacks: {
                        afterLabel: function(context) {
                            if (eventsChart.data.performerNames && eventsChart.data.performerNames[context.dataIndex]) {
                                return 'Name: ' + eventsChart.data.performerNames[context.dataIndex];
                            }
                            return '';
                        }
                    }
                }
            }
        }
    });
}

function loadLeaderboardData() {
    // Individual contributors
    const contributors = phpData.topContributors.map(user => ({
        name: user.fullname,
        points: user.eco_points,
        avatar: user.profile 
            ? `<img src="${user.profile}" alt="${user.profile_thumbnail || user.fullname}" class="profile-icon small">`
            : user.fullname.substring(0, 2).toUpperCase()
    }));
    
    populateLeaderboard('top-contributors', contributors, true);
    
    // Barangay by points
    const topBarangaysPoints = phpData.topBarangaysPoints.map(barangay => ({
        name: barangay.barangay,
        points: barangay.total_points
    }));
    
    populateLeaderboard('top-barangays-points', topBarangaysPoints);
    
    // Barangay by events
    const topBarangaysEvents = phpData.topBarangaysEvents.map(barangay => ({
        name: barangay.barangay,
        events: barangay.event_count
    }));
    
    populateLeaderboard('top-barangays-events', topBarangaysEvents, false, 'events');
    
    // Municipality by points
    const topMunicipalitiesPoints = phpData.topMunicipalitiesPoints.map(muni => ({
        name: muni.city_municipality,
        points: muni.total_points
    }));

    populateLeaderboard('top-municipalities-points', topMunicipalitiesPoints);

    // Municipality by events
    const topMunicipalitiesEvents = phpData.topMunicipalitiesEvents.map(muni => ({
        name: muni.city_municipality,
        events: muni.event_count
    }));

    populateLeaderboard('top-municipalities-events', topMunicipalitiesEvents, false, 'events');

    // Organization by points
    const topOrganizationsPoints = phpData.topOrganizationsPoints.map(org => ({
        name: org.organization,
        points: org.total_points
    }));

    populateLeaderboard('top-organizations-points', topOrganizationsPoints);

    // Organization by events
    const topOrganizationsEvents = phpData.topOrganizationsEvents.map(org => ({
        name: org.organization,
        events: org.event_count
    }));
    
    populateLeaderboard('top-organizations-events', topOrganizationsEvents, false, 'events');
    
    // Update chart with initial data
    updateCharts('individual');
    
    // Populate cycle leaderboards if active cycle exists
    populateCycleLeaderboards();
}

// Real-time cycle leaderboard refresh (like "Earned this week" feature)
function refreshCycleLeaderboards() {
    fetch('get_cycle_rankings_ajax.php')
        .then(response => response.json())
        .then(data => {
            if(data.success && data.hasActiveCycle) {
                // Update phpData with fresh cycle rankings
                phpData.cycleTopIndividuals = data.cycleTopIndividuals || [];
                phpData.cycleTopBarangays = data.cycleTopBarangays || [];
                phpData.cycleTopMunicipalities = data.cycleTopMunicipalities || [];
                phpData.cycleTopOrganizations = data.cycleTopOrganizations || [];
                phpData.currentCycle = data.currentCycle;
                
                // Re-populate the cycle leaderboards with fresh data
                populateCycleLeaderboards();
                
                console.log('✅ Cycle leaderboards refreshed:', data.cycleStats);
            } else {
                console.log('ℹ️ No active cycle found');
            }
        })
        .catch(error => {
            console.error('❌ Error refreshing cycle leaderboards:', error);
        });
}

// New function to populate cycle-specific leaderboards
function populateCycleLeaderboards() {
    // Populate cycle individuals (top 10) - using actual cycle activity data
    const cycleIndividuals = phpData.cycleTopIndividuals.map((user, index) => ({
        rank: index + 1,
        name: user.fullname,
        points: user.cycle_points, // Cycle-specific points
        avatar: user.profile 
            ? `<img src="${user.profile}" alt="${user.profile_thumbnail || user.fullname}" class="profile-icon small">`
            : user.fullname.substring(0, 2).toUpperCase(),
        activityBreakdown: `Events: ${user.events_attended || 0} | Reports: ${user.reports_resolved || 0} | Days Active: ${user.active_days || 0}`
    }));
    populateCycleList('cycle-top-individuals', cycleIndividuals, true);
    
    // Populate cycle barangays (top 5) - using actual cycle activity data
    const cycleBarangays = phpData.cycleTopBarangays.map((barangay, index) => ({
        rank: index + 1,
        name: barangay.barangay,
        points: barangay.cycle_points, // Cycle-specific points
        members: `${barangay.active_members || 0} active members`
    }));
    populateCycleList('cycle-top-barangays', cycleBarangays, false);
    
    // Populate cycle municipalities (top 5) - using actual cycle activity data
    const cycleMunicipalities = phpData.cycleTopMunicipalities.map((muni, index) => ({
        rank: index + 1,
        name: muni.city_municipality,
        points: muni.cycle_points, // Cycle-specific points
        members: `${muni.active_members || 0} active members`
    }));
    populateCycleList('cycle-top-municipalities', cycleMunicipalities, false);
    
    // Populate cycle organizations (top 5) - using actual cycle activity data
    const cycleOrganizations = phpData.cycleTopOrganizations.map((org, index) => ({
        rank: index + 1,
        name: org.organization,
        points: org.cycle_points, // Cycle-specific points
        members: `${org.active_members || 0} active members`
    }));
    populateCycleList('cycle-top-organizations', cycleOrganizations, false);
}

// Helper function to populate cycle lists
function populateCycleList(elementId, data, showAvatar = false) {
    const listElement = document.getElementById(elementId);
    if(!listElement) return;
    
    listElement.innerHTML = '';
    
    data.forEach((item) => {
        const li = document.createElement('li');
        li.className = 'cycle-list-item';
        
        // Add medal for top 3
        let medalIcon = '';
        if(item.rank === 1) medalIcon = '<i class="fas fa-medal" style="color: #FFD700; margin-right: 8px;"></i>';
        else if(item.rank === 2) medalIcon = '<i class="fas fa-medal" style="color: #C0C0C0; margin-right: 8px;"></i>';
        else if(item.rank === 3) medalIcon = '<i class="fas fa-medal" style="color: #CD7F32; margin-right: 8px;"></i>';
        
        if (showAvatar) {
            li.innerHTML = `
                <span class="cycle-rank">${medalIcon}#${item.rank}</span>
                <div class="default-profile-icon small">${item.avatar}</div>
                <span class="cycle-name">${item.name}</span>
                <span class="cycle-points"><i class="fas fa-coins"></i> ${item.points.toLocaleString()}</span>
            `;
        } else {
            li.innerHTML = `
                <span class="cycle-rank">${medalIcon}#${item.rank}</span>
                <span class="cycle-name">${item.name}</span>
                <span class="cycle-points"><i class="fas fa-coins"></i> ${item.points.toLocaleString()}</span>
            `;
        }
        
        listElement.appendChild(li);
    });
}

function populateLeaderboard(elementId, data, showAvatar = false, valueField = 'points', isOrganization = false) {
    const listElement = document.getElementById(elementId);
    listElement.innerHTML = '';
    
    data.forEach((item, index) => {
        const li = document.createElement('li');
        
        if (isOrganization) {
            li.innerHTML = `
                <span class="rank">${index + 1}.</span>
                <div class="organization-item">
                    <div class="org-logo">${item.logo}</div>
                    <span>${item.name} - ${item[valueField]} ${valueField === 'points' ? 'Points' : 'Events'}</span>
                </div>
            `;
        } else if (showAvatar) {
            li.innerHTML = `
                <span class="rank">${index + 1}.</span>
                <div class="default-profile-icon small">${item.avatar}</div>
                <span style="margin-left: 10px;">${item.name} - ${item[valueField]} ${valueField === 'points' ? 'Eco Points' : 'Events'}</span>
            `;
        } else {
            li.innerHTML = `
                <span class="rank">${index + 1}.</span>
                <span>${item.name} - ${item[valueField]} ${valueField === 'points' ? 'Points' : 'Events'}</span>
            `;
        }
        
        listElement.appendChild(li);
    });
}

function populateActivities(elementId, activities) {
    const activitiesElement = document.getElementById(elementId);
    activitiesElement.innerHTML = '';
    
    activities.forEach(activity => {
        const activityItem = document.createElement('div');
        activityItem.className = 'activity-item';
        activityItem.innerHTML = `
            <div class="default-profile-icon">${activity.icon}</div>
            <div class="activity-details">
                <div class="activity-user">${activity.user}</div>
                <div>${activity.action}</div>
                <div class="activity-points">${activity.points} points</div>
            </div>
        `;
        activitiesElement.appendChild(activityItem);
    });
}

function setupFilters() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Hide all leaderboards
            document.getElementById('individual-leaderboards').style.display = 'none';
            document.getElementById('barangay-leaderboards').style.display = 'none';
            document.getElementById('municipality-leaderboards').style.display = 'none';
            document.getElementById('organization-leaderboards').style.display = 'none';
            
            // Hide all cycle sections
            const barangayCycle = document.getElementById('barangay-cycle-section');
            const municipalityCycle = document.getElementById('municipality-cycle-section');
            const organizationCycle = document.getElementById('organization-cycle-section');
            if(barangayCycle) barangayCycle.style.display = 'none';
            if(municipalityCycle) municipalityCycle.style.display = 'none';
            if(organizationCycle) organizationCycle.style.display = 'none';
            
            // Show selected leaderboard
            const filter = this.getAttribute('data-filter');
            const leaderboardElement = document.getElementById(`${filter}-leaderboards`);
            const leaderboardsContent = document.querySelector('.leaderboards-content');
            
            // LAYOUT REORDERING: For non-individual filters, cycle sections should appear FIRST
            if (filter === 'individual') {
                // Individual: Top Contributors first, Cycles second
                leaderboardsContent.classList.remove('cycle-first');
            } else {
                // Barangay/Municipality/Organization: Cycles first, Top Contributors second
                leaderboardsContent.classList.add('cycle-first');
            }
            
            // Check if screen is mobile (width <= 600px)
            if (window.innerWidth <= 700) {
                leaderboardElement.style.display = 'flex';
            } else {
                leaderboardElement.style.display = 'grid';
            }
            
            // Show corresponding cycle section
            const cycleSection = document.getElementById(`${filter}-cycle-section`);
            if(cycleSection) {
                cycleSection.style.display = 'block';
            }
            
            // Update chart based on filter
            updateCharts(filter);
        });
    });
}

function updateCharts(filterType) {
    let labels = [], pointsData = [], eventsData = [];
    const showEventsChart = ['barangay', 'municipality', 'organization'].includes(filterType);

    switch (filterType) {
        case 'individual': {
            const first = phpData.topContributors[0] || null;
            const second = phpData.topContributors[1] || null;
            const third = phpData.topContributors[2] || null;
            
            const firstPoints = first?.eco_points || 0;
            const secondPoints = second?.eco_points || 0;
            const thirdPoints = third?.eco_points || 0;
            
            const firstName = first?.fullname || 'No Data';
            const secondName = second?.fullname || 'No Data';
            const thirdName = third?.fullname || 'No Data';
            
            labels = ['1st Place', '2nd Place', '3rd Place'];
            pointsData = [firstPoints, secondPoints, thirdPoints];
            
            // Store names for tooltip display
            pointsChart.data.performerNames = [firstName, secondName, thirdName];
            break;
        }
        case 'barangay': {
            const firstPoints = phpData.topBarangaysPoints[0] || null;
            const secondPoints = phpData.topBarangaysPoints[1] || null;
            const thirdPoints = phpData.topBarangaysPoints[2] || null;
            
            const firstEvents = phpData.topBarangaysEvents[0] || null;
            const secondEvents = phpData.topBarangaysEvents[1] || null;
            const thirdEvents = phpData.topBarangaysEvents[2] || null;
            
            const firstPointsValue = firstPoints?.total_points ? parseInt(firstPoints.total_points) : 0;
            const secondPointsValue = secondPoints?.total_points ? parseInt(secondPoints.total_points) : 0;
            const thirdPointsValue = thirdPoints?.total_points ? parseInt(thirdPoints.total_points) : 0;
            
            const firstEventsValue = firstEvents?.event_count || 0;
            const secondEventsValue = secondEvents?.event_count || 0;
            const thirdEventsValue = thirdEvents?.event_count || 0;
            
            const firstPointsName = firstPoints?.barangay || 'No Data';
            const secondPointsName = secondPoints?.barangay || 'No Data';
            const thirdPointsName = thirdPoints?.barangay || 'No Data';
            
            const firstEventsName = firstEvents?.barangay || 'No Data';
            const secondEventsName = secondEvents?.barangay || 'No Data';
            const thirdEventsName = thirdEvents?.barangay || 'No Data';
            
            labels = ['1st Place', '2nd Place', '3rd Place'];
            pointsData = [firstPointsValue, secondPointsValue, thirdPointsValue];
            eventsData = [firstEventsValue, secondEventsValue, thirdEventsValue];
            
            // Store names for tooltip display
            pointsChart.data.performerNames = [firstPointsName, secondPointsName, thirdPointsName];
            if (eventsChart) {
                eventsChart.data.performerNames = [firstEventsName, secondEventsName, thirdEventsName];
            }
            break;
        }
        case 'municipality': {
            const firstPoints = phpData.topMunicipalitiesPoints[0] || null;
            const secondPoints = phpData.topMunicipalitiesPoints[1] || null;
            const thirdPoints = phpData.topMunicipalitiesPoints[2] || null;
            
            const firstEvents = phpData.topMunicipalitiesEvents[0] || null;
            const secondEvents = phpData.topMunicipalitiesEvents[1] || null;
            const thirdEvents = phpData.topMunicipalitiesEvents[2] || null;
            
            const firstPointsValue = firstPoints?.total_points ? parseInt(firstPoints.total_points) : 0;
            const secondPointsValue = secondPoints?.total_points ? parseInt(secondPoints.total_points) : 0;
            const thirdPointsValue = thirdPoints?.total_points ? parseInt(thirdPoints.total_points) : 0;
            
            const firstEventsValue = firstEvents?.event_count || 0;
            const secondEventsValue = secondEvents?.event_count || 0;
            const thirdEventsValue = thirdEvents?.event_count || 0;
            
            const firstPointsName = firstPoints?.city_municipality || 'No Data';
            const secondPointsName = secondPoints?.city_municipality || 'No Data';
            const thirdPointsName = thirdPoints?.city_municipality || 'No Data';
            
            const firstEventsName = firstEvents?.city_municipality || 'No Data';
            const secondEventsName = secondEvents?.city_municipality || 'No Data';
            const thirdEventsName = thirdEvents?.city_municipality || 'No Data';
            
            labels = ['1st Place', '2nd Place', '3rd Place'];
            pointsData = [firstPointsValue, secondPointsValue, thirdPointsValue];
            eventsData = [firstEventsValue, secondEventsValue, thirdEventsValue];
            
            // Store names for tooltip display
            pointsChart.data.performerNames = [firstPointsName, secondPointsName, thirdPointsName];
            if (eventsChart) {
                eventsChart.data.performerNames = [firstEventsName, secondEventsName, thirdEventsName];
            }
            break;
        }
        case 'organization': {
            const firstPoints = phpData.topOrganizationsPoints[0] || null;
            const secondPoints = phpData.topOrganizationsPoints[1] || null;
            const thirdPoints = phpData.topOrganizationsPoints[2] || null;
            
            const firstEvents = phpData.topOrganizationsEvents[0] || null;
            const secondEvents = phpData.topOrganizationsEvents[1] || null;
            const thirdEvents = phpData.topOrganizationsEvents[2] || null;
            
            const firstPointsValue = firstPoints?.total_points ? parseInt(firstPoints.total_points) : 0;
            const secondPointsValue = secondPoints?.total_points ? parseInt(secondPoints.total_points) : 0;
            const thirdPointsValue = thirdPoints?.total_points ? parseInt(thirdPoints.total_points) : 0;
            
            const firstEventsValue = firstEvents?.event_count || 0;
            const secondEventsValue = secondEvents?.event_count || 0;
            const thirdEventsValue = thirdEvents?.event_count || 0;
            
            const firstPointsName = firstPoints?.organization || 'No Data';
            const secondPointsName = secondPoints?.organization || 'No Data';
            const thirdPointsName = thirdPoints?.organization || 'No Data';
            
            const firstEventsName = firstEvents?.organization || 'No Data';
            const secondEventsName = secondEvents?.organization || 'No Data';
            const thirdEventsName = thirdEvents?.organization || 'No Data';
            
            labels = ['1st Place', '2nd Place', '3rd Place'];
            pointsData = [firstPointsValue, secondPointsValue, thirdPointsValue];
            eventsData = [firstEventsValue, secondEventsValue, thirdEventsValue];
            
            // Store names for tooltip display
            pointsChart.data.performerNames = [firstPointsName, secondPointsName, thirdPointsName];
            if (eventsChart) {
                eventsChart.data.performerNames = [firstEventsName, secondEventsName, thirdEventsName];
            }
            break;
        }
    }

    // Update points chart
    pointsChart.data.labels = labels;
    pointsChart.data.datasets[0].data = pointsData;
    pointsChart.update();

    // Update events chart if applicable
    if (showEventsChart && eventsChart) {
        eventsChart.data.labels = labels;
        eventsChart.data.datasets[0].data = eventsData;
        eventsChart.update();
        document.getElementById('eventsChart').parentElement.style.display = 'block';
    } else {
        if (document.getElementById('eventsChart')) {
            document.getElementById('eventsChart').parentElement.style.display = 'none';
        }
    }
}

// Filter rewards by category
function filterRewardsByCategory() {
    const categoryFilter = document.getElementById('category-filter');
    const selectedCategory = categoryFilter.value.toLowerCase();
    const rewardsCount = document.getElementById('rewards-count');
    
    if (selectedCategory === '') {
        filteredRewards = rewards;
    } else {
        filteredRewards = rewards.filter(reward => 
            reward.category && reward.category.toLowerCase() === selectedCategory
        );
    }
    
    // Update count display
    rewardsCount.textContent = filteredRewards.length;
    
    // Reset to first page and update display
    currentPage = 1;
    displayRewards(1);
    setupPagination();
}

// New functions for reward item enhancement
function toggleDescription(button, rewardId) {
    // Find the reward data
    const reward = rewards.find(r => r.id == rewardId);
    if (!reward) return;
    
    // Show detailed modal instead of toggling description
    showRewardDetailsModal(reward);
}

function showRewardDetailsModal(reward) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('rewardDetailsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'rewardDetailsModal';
        modal.className = 'reward-image-modal';
        modal.innerHTML = `
            <div class="reward-modal-content reward-details-modal-content">
                <div class="reward-modal-header">
                    <h3 id="rewardDetailsTitle">Item Details</h3>
                    <span class="reward-modal-close" onclick="closeRewardDetailsModal()">&times;</span>
                </div>
                <div class="reward-modal-body reward-details-modal-body">
                    <div class="reward-details-container">
                        <div class="reward-modal-image">
                            <img id="rewardDetailsImage" src="" alt="Reward image" style="display: none;">
                            <span id="rewardDetailsEmoji" class="reward-modal-emoji">🎁</span>
                        </div>
                        <div class="reward-modal-info">
                            <h4 id="rewardDetailsName">Item Name</h4>
                            <div class="reward-modal-description">
                                <p id="rewardDetailsDescription">Description</p>
                            </div>
                            <div class="reward-modal-meta">
                                <div class="reward-modal-points">
                                    <i class="fas fa-coins"></i>
                                    <span id="rewardDetailsPoints">0</span> Eco Points
                                </div>
                                <div class="reward-modal-category">
                                    <i class="fas fa-tag"></i>
                                    <span id="rewardDetailsCategory">Category</span>
                                </div>
                                <div class="reward-modal-stock" id="rewardDetailsStockContainer" style="display: none;">
                                    <i class="fas fa-box"></i>
                                    Stock: <span id="rewardDetailsStock">0</span>
                                </div>
                            </div>
                            <button class="redeem-btn modal-redeem-btn" id="modalRedeemBtn" data-points="" data-reward-id="">
                                Redeem
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Populate modal with reward details
    document.getElementById('rewardDetailsTitle').textContent = reward.title + ' - Details';
    document.getElementById('rewardDetailsName').textContent = reward.title;
    document.getElementById('rewardDetailsDescription').textContent = reward.item_description || 'No description available';
    document.getElementById('rewardDetailsPoints').textContent = reward.points;
    document.getElementById('rewardDetailsCategory').textContent = reward.category ? reward.category.charAt(0).toUpperCase() + reward.category.slice(1) : 'General';
    
    // Handle image
    const modalImage = document.getElementById('rewardDetailsImage');
    const modalEmoji = document.getElementById('rewardDetailsEmoji');
    if (reward.image_path && reward.image_path.trim() !== '') {
        modalImage.src = reward.image_path;
        modalImage.style.display = 'block';
        modalEmoji.style.display = 'none';
        modalImage.onclick = () => showRewardImagePreview(reward.image_path, reward.title);
    } else {
        modalImage.style.display = 'none';
        modalEmoji.style.display = 'block';
    }
    
    // Handle stock
    const stockContainer = document.getElementById('rewardDetailsStockContainer');
    if (reward.stock_quantity && reward.stock_quantity > 0) {
        document.getElementById('rewardDetailsStock').textContent = reward.stock_quantity;
        stockContainer.style.display = 'block';
    } else {
        stockContainer.style.display = 'none';
    }
    
    // Handle redeem button
    const modalRedeemBtn = document.getElementById('modalRedeemBtn');
    modalRedeemBtn.setAttribute('data-points', reward.points);
    modalRedeemBtn.setAttribute('data-reward-id', reward.id);
    
    modal.style.display = 'block';
}

function closeRewardDetailsModal() {
    const modal = document.getElementById('rewardDetailsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function showRewardImagePreview(imagePath, title) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('rewardImageModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'rewardImageModal';
        modal.className = 'reward-image-modal';
        modal.innerHTML = `
            <div class="reward-modal-content">
                <div class="reward-modal-header">
                    <h3 id="rewardImageTitle">Image Preview</h3>
                    <span class="reward-modal-close" onclick="closeRewardImagePreview()">&times;</span>
                </div>
                <div class="reward-modal-body">
                    <img id="rewardImagePreview" src="" alt="Reward image">
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('rewardImagePreview').src = imagePath;
    document.getElementById('rewardImageTitle').textContent = title + ' - Image Preview';
    modal.style.display = 'block';
}

function closeRewardImagePreview() {
    document.getElementById('rewardImageModal').style.display = 'none';
}

// Function to update stock display on the page
function updateStockDisplay(itemId, newStock) {
    // Find all stock displays for this item
    const rewardItems = document.querySelectorAll('.reward-item');
    
    rewardItems.forEach(item => {
        const redeemBtn = item.querySelector('.redeem-btn');
        if (redeemBtn && redeemBtn.getAttribute('data-reward-id') == itemId) {
            const stockDiv = item.querySelector('.reward-stock');
            if (stockDiv) {
                stockDiv.innerHTML = `<i class="fas fa-box"></i> Stock: ${newStock}`;
            }
        }
    });
    
    // Also update in the modal if it's open
    const modal = document.getElementById('rewardDetailsModal');
    if (modal && modal.style.display === 'block') {
        const modalRedeemBtn = document.getElementById('modalRedeemBtn');
        if (modalRedeemBtn && modalRedeemBtn.getAttribute('data-reward-id') == itemId) {
            const modalStock = document.getElementById('rewardDetailsStock');
            if (modalStock) {
                modalStock.textContent = newStock;
            }
        }
    }
    
    console.log(`Stock updated for item ${itemId}: ${newStock}`);
}

// Close modal when clicking outside of it
document.addEventListener('click', function(e) {
    const imageModal = document.getElementById('rewardImageModal');
    const detailsModal = document.getElementById('rewardDetailsModal');
    
    if (imageModal && e.target === imageModal) {
        closeRewardImagePreview();
    }
    
    if (detailsModal && e.target === detailsModal) {
        closeRewardDetailsModal();
    }
});

// OLD FUNCTION - Disabled to prevent unwanted popup
// Now using cart system exclusively
function setupRewardRedemption(userPoints) {
    // Only mark pending items, no click handlers
    const pendingItemIds = phpData.userPendingItemIds || [];
    console.log('Pending item IDs (marking only):', pendingItemIds);
    
    // Disable buttons for items that have pending transactions
    pendingItemIds.forEach(itemId => {
        const buttons = document.querySelectorAll(`[data-reward-id="${itemId}"]`);
        buttons.forEach(button => {
            button.innerHTML = '<i class="fas fa-clock"></i> Pending Approval';
            button.classList.add('disabled');
            button.style.backgroundColor = '#f39c12';
            button.style.cursor = 'not-allowed';
            button.disabled = true;
            console.log(`Disabled button for pending item ${itemId}`);
        });
    });
    
    // REMOVED: Old click handler that caused unwanted popup
    // Cart system handles all clicks now via setupCartButtonHandlers()
}

// OLD FUNCTION - Completely disabled, cart system only
function redeemItem(itemId, itemName, points, button) {
    console.warn('⚠️ OLD redeemItem function called - redirecting to cart system');
    showFlashMessage('info', 'Please use the shopping cart to redeem items.');
    return;
}

// Cleanup function - removes old redemption artifacts (empty stub)
function _cleanupOldRedemptionCode() {
    // This function is no longer needed with the cart system
    console.log('Old redemption code cleanup - no action needed');
}

function showFlashMessage(type, message) {
    // Remove existing flash messages
    const existingFlash = document.querySelector('.flash-message');
    if (existingFlash) {
        existingFlash.remove();
    }
    
    // Create new flash message
    const flashDiv = document.createElement('div');
    flashDiv.className = `flash-message flash-${type}`;
    flashDiv.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        ${message}
    `;
    
    // Find the parent container where you want to prepend the message
    const ecoShopDiv = document.querySelector('.shop-message');

    // Check if the container exists and prepend the new element to it
    if (ecoShopDiv) {
        ecoShopDiv.prepend(flashDiv);
    } else {
        // Fallback to inserting into the body if the main container isn't found
        document.body.prepend(flashDiv);
    }
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (flashDiv.parentElement) {
            flashDiv.style.opacity = '0';
            flashDiv.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                flashDiv.remove();
            }, 300);
        }
    }, 5000);
}

// Badge Notification Functions
function closeBadgeNotification() {
    const notification = document.getElementById('badgeNotification');
    if (notification) {
        notification.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }
}

function viewBadgeDetails() {
    // Close notification and redirect to profile page
    closeBadgeNotification();
    setTimeout(() => {
        window.location.href = 'profile.php#badges';
    }, 400);
}

// CSS for fade out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.8); }
    }
    
    .redemption-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s ease-out;
    }
    
    .redemption-modal-content {
        background-color: #fefefe;
        margin: 10% auto;
        padding: 30px;
        border: none;
        border-radius: 15px;
        width: 90%;
        max-width: 450px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        text-align: center;
        position: relative;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideDown {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .redemption-modal h3 {
        color: #2c5530;
        margin-bottom: 20px;
        font-size: 1.5em;
    }
    
    .redemption-modal p {
        color: #666;
        margin-bottom: 25px;
        font-size: 1.1em;
        line-height: 1.5;
    }
    
    .redemption-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
    }
    
    .modal-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1em;
        font-weight: 600;
        transition: all 0.3s ease;
        min-width: 120px;
    }
    
    .modal-btn-confirm {
        background: linear-gradient(135deg, #2c5530, #4a7c59);
        color: white;
    }
    
    .modal-btn-confirm:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(44, 85, 48, 0.3);
    }
    
    .modal-btn-cancel {
        background: #e74c3c;
        color: white;
    }
    
    .modal-btn-cancel:hover {
        background: #c0392b;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
    }

    /* Checkout confirmation modal */
    .checkout-confirm-modal {
        display: none;
        position: fixed;
        z-index: 10001;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.6);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease-out;
    }

    .checkout-confirm-modal-content {
        background: linear-gradient(135deg, #ffffff, #f8f9fa);
        margin: 10% auto;
        padding: 35px;
        border: none;
        border-radius: 20px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        text-align: center;
        position: relative;
        animation: slideDown 0.3s ease-out;
    }

    .checkout-confirm-modal h3 {
        color: #2c5530;
        margin-bottom: 15px;
        font-size: 1.8em;
        font-weight: 700;
    }

    .checkout-confirm-modal .checkout-summary {
        background: #f0f4f1;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        border-left: 4px solid #2c5530;
    }

    .checkout-confirm-modal .checkout-summary p {
        color: #333;
        margin: 10px 0;
        font-size: 1.1em;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .checkout-confirm-modal .checkout-summary strong {
        color: #2c5530;
        font-size: 1.2em;
    }

    .checkout-confirm-modal .warning-text {
        color: #e67e22;
        font-size: 0.95em;
        margin-top: 15px;
        font-style: italic;
    }

    .checkout-confirm-modal-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
    }
`;
document.head.appendChild(style);

// Custom confirmation modal functions
// OLD MODAL SYSTEM - DISABLED - Use cart system instead
function showRedemptionConfirmModal(itemId, itemName, points, button) {
    console.warn('⚠️ OLD showRedemptionConfirmModal called - redirecting to cart system');
    showFlashMessage('info', 'Please use the shopping cart to redeem items.');
    return; // Early exit prevents modal popup
}

function closeRedemptionModal() {
    // OLD function - no longer needed
    console.log('closeRedemptionModal called but modal system disabled');
}

function confirmRedemption(itemId, itemName, points, confirmButton) {
    // OLD function - no longer needed
    console.warn('⚠️ OLD confirmRedemption called - use cart system instead');
    showFlashMessage('info', 'Please use the shopping cart to redeem items.');
}

// Clear expired pending transactions (older than 24 hours)
function clearExpiredPendingTransactions() {
    const pendingTransactions = JSON.parse(localStorage.getItem('pendingTransactions') || '[]');
    const oneDayAgo = Date.now() - (24 * 60 * 60 * 1000);
    
    const activePending = pendingTransactions.filter(pending => pending.timestamp > oneDayAgo);
    localStorage.setItem('pendingTransactions', JSON.stringify(activePending));
}

// Clean localStorage based on database state
function cleanupLocalStoragePending(dbPendingItems) {
    const pendingTransactions = JSON.parse(localStorage.getItem('pendingTransactions') || '[]');
    const dbPendingItemIds = dbPendingItems.map(item => item.itemId.toString());
    
    console.log('Cleaning localStorage. DB pending IDs:', dbPendingItemIds);
    console.log('LocalStorage before cleanup:', pendingTransactions);
    
    // Keep only localStorage items that:
    // 1. Are still pending in database, OR
    // 2. Are very recent (less than 5 minutes old)
    const fiveMinutesAgo = Date.now() - (5 * 60 * 1000);
    const cleanedPending = pendingTransactions.filter(localItem => {
        const isStillPendingInDb = dbPendingItemIds.includes(localItem.itemId.toString());
        const isVeryRecent = localItem.timestamp && localItem.timestamp > fiveMinutesAgo;
        
        console.log(`Item ${localItem.itemId}: pendingInDb=${isStillPendingInDb}, recent=${isVeryRecent}`);
        
        return isStillPendingInDb || isVeryRecent;
    });
    
    console.log('LocalStorage after cleanup:', cleanedPending);
    localStorage.setItem('pendingTransactions', JSON.stringify(cleanedPending));
}

// Clear expired transactions on page load
document.addEventListener('DOMContentLoaded', function() {
    clearExpiredPendingTransactions();
    
    // Add periodic sync with database every 30 seconds
    setInterval(function() {
        // Check if we need to refresh pending transactions
        syncPendingTransactions();
    }, 30000);
});

// Sync pending transactions with database
function syncPendingTransactions() {
    // Get current localStorage pending
    const localPending = JSON.parse(localStorage.getItem('pendingTransactions') || '[]');
    
    if (localPending.length === 0) {
        return; // Nothing to sync
    }
    
    console.log('Syncing pending transactions with database...');
    
    // Check actual database status via AJAX
    fetch('check_transaction_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const dbPendingItemIds = data.pending_items.map(item => item.item_id.toString());
                console.log('Database pending item IDs:', dbPendingItemIds);
                
                // Clean localStorage based on database reality
                const cleanedPending = localPending.filter(localItem => {
                    const stillPending = dbPendingItemIds.includes(localItem.itemId.toString());
                    
                    if (!stillPending) {
                        // Transaction was processed - re-enable the button
                        const button = document.querySelector(`[data-reward-id=\"${localItem.itemId}\"]`);
                        if (button) {
                            console.log(`Re-enabling button for processed item ${localItem.itemId}`);
                            button.innerHTML = '<i class=\"fas fa-shopping-cart\"></i> Redeem';
                            button.classList.remove('disabled');
                            button.style.backgroundColor = '';
                            button.style.cursor = '';
                            button.disabled = false;
                        }
                    }
                    
                    return stillPending;
                });
                
                // Update localStorage
                localStorage.setItem('pendingTransactions', JSON.stringify(cleanedPending));
                console.log('Updated localStorage after sync:', cleanedPending);
                
                // If items were processed, show success message
                if (cleanedPending.length < localPending.length) {
                    const processedCount = localPending.length - cleanedPending.length;
                    showFlashMessage('info', `${processedCount} transaction(s) have been processed by admin. You can now redeem those items again if needed.`);
                }
            }
        })
        .catch(error => {
            console.error('Error syncing transactions:', error);
        });
}

// Track user activity to prevent unnecessary refreshes
document.addEventListener('click', function() {
    localStorage.setItem('lastActivity', Date.now());
});

document.addEventListener('scroll', function() {
    localStorage.setItem('lastActivity', Date.now());
});

// Debug function to manually clear pending transactions (for testing)
window.clearAllPendingTransactions = function() {
    console.log('All pending transactions cleared from localStorage');
    // Re-enable all buttons
    document.querySelectorAll('.redeem-btn.disabled').forEach(button => {
        button.innerHTML = '<i class="fas fa-shopping-cart"></i> Redeem';
        button.classList.remove('disabled');
        button.style.backgroundColor = '';
        button.style.cursor = '';
        button.disabled = false;
    });
    showFlashMessage('success', 'All pending transactions cleared. All items are now available for redemption.');
};

// Debug function to show current localStorage state
window.debugPendingTransactions = function() {
    const pending = JSON.parse(localStorage.getItem('pendingTransactions') || '[]');
    console.log('Current localStorage pending transactions:', pending);
    console.log('Count:', pending.length);
    return pending;
};

function populateBadgeActivities() {
    const activitiesElement = document.getElementById('recent-activities');
    activitiesElement.innerHTML = '';
    
    if (phpData.recentBadges && phpData.recentBadges.length > 0) {
        phpData.recentBadges.forEach(badge => {
            const activityItem = document.createElement('li');
            activityItem.className = 'activity-item';
            
            // Format the date
            const badgeDate = new Date(badge.notified_at);
            const formattedDate = badgeDate.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
            
            activityItem.innerHTML = `
                <div class="activity-icon">
                    ${badge.profile_thumbnail 
                        ? `<img src="${badge.profile_thumbnail}" alt="${badge.fullname}" class="profile-icon small">`
                        : `<div class="activity-icon small">${badge.fullname.substring(0, 2).toUpperCase()}</div>`
                    }
                </div>
                <div class="activity-details">
                    <div class="activity-user">${badge.fullname}</div>
                    <div>has obtained <span class="badge-name">${badge.badge_name}</span> badge</div>
                    <div class="activity-date">${formattedDate}</div>
                </div>
                <div class="badge-icon-activity">
                    ${createBadgeDisplay(badge)}
                </div>
            `;
            activitiesElement.appendChild(activityItem);
        });
    } else {
        activitiesElement.innerHTML = `
            <div class="no-activities">
                <i class="fas fa-medal"></i>
                <p>No recent badge activities yet</p>
            </div>
        `;
    }
}

// Create badge display with proper error handling
function createBadgeDisplay(badge) {
    // Always use the fallback icon for simplicity
    return getFallbackBadgeIcon(badge);
}

function getFallbackBadgeIcon(badge) {
    // First try to use the icon_class from database
    if (badge.icon_class && badge.icon_class.trim() !== '') {
        return `<i class="${badge.icon_class} badge-icon-fallback"></i>`;
    }
    
    // If no icon_class, use badge color for styling
    if (badge.color) {
        return `<div class="badge-icon-fallback" style="background-color: ${badge.color}">
                    ${badge.badge_name.substring(0, 1).toUpperCase()}
                </div>`;
    }
    
    // Default emoji mapping as last resort
    const badgeEmojis = {
        'Event Organizer': '🎉',
        'Starting Point': '🚀',
        'Mangrove Guardian': '🌿',
        'Tree Planter': '🌱',
        'Eco Warrior': '🛡️',
        'Enthusiast': '🔥',
        'Badge Collector': '🏆',
        'Watchful Eye': '👁️',
        'Vigilant Protector': '🛡️',
        'Conservation Champion': '🏆',
        'Ecosystem Sentinel': '🌐',
        'Mangrove Legend': '🌳'
    };
    
    const emoji = badgeEmojis[badge.badge_name] || '🏅';
    return `<div class="badge-icon-fallback">${emoji}</div>`;
}

// ============================================
// REWARDS CLAIM SYSTEM
// ============================================

// Check for pending rewards on page load
function checkPendingRewards() {
    <?php if(!isset($_SESSION['user_id'])): ?>
    return; // Not logged in
    <?php endif; ?>
    
    fetch('rewards_claim_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_rewards'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success && data.has_pending) {
            document.getElementById('pending-indicator').style.display = 'block';
            const notice = document.getElementById('pending-rewards-notice');
            const count = document.getElementById('pending-count');
            if(notice && count) {
                count.textContent = data.pending_rewards.length;
                notice.style.display = 'block';
            }
        }
    })
    .catch(error => console.error('Error checking rewards:', error));
}

// Open rewards modal
function openRewardsModal() {
    document.getElementById('rewardsModal').style.display = 'block';
    loadRewardsData();
}

// Close rewards modal
function closeRewardsModal() {
    document.getElementById('rewardsModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('rewardsModal');
    if (event.target === modal) {
        closeRewardsModal();
    }
});

// Load rewards data
function loadRewardsData() {
    fetch('rewards_claim_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_rewards'
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            displayPendingRewards(data.pending_rewards);
            displayClaimedRewards(data.claimed_rewards);
            
            const claimAllBtn = document.getElementById('claim-all-btn');
            if(data.pending_rewards.length > 0) {
                claimAllBtn.style.display = 'flex';
            } else {
                claimAllBtn.style.display = 'none';
            }
        }
    })
    .catch(error => {
        console.error('Error loading rewards:', error);
        showFlashMessage('error', 'Failed to load rewards data');
    });
}

// Display pending rewards
function displayPendingRewards(rewards) {
    const container = document.getElementById('pending-rewards-list');
    
    if(rewards.length === 0) {
        container.innerHTML = `
            <div class="no-rewards-message">
                <i class="fas fa-inbox"></i>
                <p>No pending rewards at the moment</p>
                <p style="font-size: 0.9rem; color: #999;">Keep participating to earn rewards!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = rewards.map(reward => `
        <div class="reward-card">
            <div class="reward-info">
                <div class="reward-cycle">
                    <i class="fas fa-calendar"></i> ${reward.cycle_name}
                </div>
                <div class="reward-details">
                    <span class="reward-detail-item">
                        <i class="fas fa-trophy"></i> 
                        Rank #${reward.rank_achieved} in ${reward.category}
                    </span>
                    <span class="reward-detail-item">
                        <i class="fas fa-map-marker-alt"></i> 
                        ${reward.entity_name}
                    </span>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                <div class="reward-points">
                    <i class="fas fa-coins"></i>
                    ${reward.points_awarded} points
                </div>
                <button class="claim-single-btn" onclick="claimSingleReward(${reward.reward_id})">
                    <i class="fas fa-hand-holding-usd"></i> Claim
                </button>
            </div>
        </div>
    `).join('');
}

// Display claimed rewards
function displayClaimedRewards(rewards) {
    const container = document.getElementById('claimed-rewards-list');
    
    if(rewards.length === 0) {
        container.innerHTML = `
            <div class="no-rewards-message">
                <i class="fas fa-history"></i>
                <p>No claimed rewards yet</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = rewards.map(reward => `
        <div class="reward-card claimed">
            <div class="reward-info">
                <div class="reward-cycle">
                    <i class="fas fa-calendar-check"></i> ${reward.cycle_name}
                </div>
                <div class="reward-details">
                    <span class="reward-detail-item">
                        <i class="fas fa-trophy"></i> 
                        Rank #${reward.rank_achieved} in ${reward.category}
                    </span>
                    <span class="reward-detail-item">
                        <i class="fas fa-check"></i> 
                        Claimed ${formatDate(reward.claimed_at)}
                    </span>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 10px;">
                <div class="reward-points">
                    <i class="fas fa-coins"></i>
                    ${reward.points_awarded} points
                </div>
                <div class="claimed-badge">
                    <i class="fas fa-check-circle"></i> Claimed
                </div>
            </div>
        </div>
    `).join('');
}

// Claim single reward
function claimSingleReward(rewardId) {
    if(!confirm('Claim this reward? Points will be added to your account immediately.')) {
        return;
    }
    
    fetch('rewards_claim_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=claim_reward&reward_id=${rewardId}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showFlashMessage('success', data.message);
            loadRewardsData();
            
            const currentPoints = parseInt(document.getElementById('user-points-value').textContent);
            document.getElementById('user-points-value').textContent = currentPoints + data.points_claimed;
            
            checkPendingRewards();
        } else {
            showFlashMessage('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error claiming reward:', error);
        showFlashMessage('error', 'Failed to claim reward');
    });
}

// Claim all rewards
function claimAllRewards() {
    if(!confirm('Claim all pending rewards? All points will be added to your account immediately.')) {
        return;
    }
    
    const btn = document.getElementById('claim-all-btn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
    btn.disabled = true;
    
    fetch('rewards_claim_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=claim_all'
    })
    .then(response => response.json())
    .then(data => {
        btn.innerHTML = originalText;
        btn.disabled = false;
        
        if(data.success) {
            showFlashMessage('success', data.message);
            loadRewardsData();
            
            const currentPoints = parseInt(document.getElementById('user-points-value').textContent);
            document.getElementById('user-points-value').textContent = currentPoints + data.total_points_claimed;
            
            document.getElementById('pending-indicator').style.display = 'none';
            document.getElementById('pending-rewards-notice').style.display = 'none';
        } else {
            showFlashMessage('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error claiming all rewards:', error);
        showFlashMessage('error', 'Failed to claim rewards');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Format date helper
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
}

// ============================================
// SHOPPING CART SYSTEM
// ============================================

let shoppingCart = [];
const CART_STORAGE_KEY = 'ecoshop_cart';

// Load cart from localStorage on page load
function loadCartFromStorage() {
    try {
        const storedCart = localStorage.getItem(CART_STORAGE_KEY);
        if (storedCart) {
            shoppingCart = JSON.parse(storedCart);
            updateCartUI();
        }
    } catch (error) {
        console.error('Error loading cart from storage:', error);
        shoppingCart = [];
    }
}

// Save cart to localStorage
function saveCartToStorage() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(shoppingCart));
    } catch (error) {
        console.error('Error saving cart to storage:', error);
    }
}

// Add item to cart
function addToCart(itemId, itemName, points, imagePath) {
    // Check if item already in cart
    const existingItem = shoppingCart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        shoppingCart.push({
            id: itemId,
            name: itemName,
            points: points,
            image: imagePath,
            quantity: 1
        });
    }
    
    saveCartToStorage();
    updateCartUI();
    showFlashMessage('success', `${itemName} added to cart!`);
}

// Remove item from cart
function removeFromCart(itemId) {
    const itemIndex = shoppingCart.findIndex(item => item.id === itemId);
    if (itemIndex > -1) {
        const itemName = shoppingCart[itemIndex].name;
        shoppingCart.splice(itemIndex, 1);
        saveCartToStorage();
        updateCartUI();
        showFlashMessage('success', `${itemName} removed from cart`);
    }
}

// Update item quantity
function updateQuantity(itemId, change) {
    const item = shoppingCart.find(item => item.id === itemId);
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            removeFromCart(itemId);
        } else {
            saveCartToStorage();
            updateCartUI();
        }
    }
}

// Calculate total points
function calculateTotalPoints() {
    return shoppingCart.reduce((total, item) => {
        return total + (item.points * item.quantity);
    }, 0);
}

// Update cart UI
function updateCartUI() {
    const cartBadge = document.getElementById('cart-badge');
    const cartEmpty = document.getElementById('cart-empty');
    const cartItems = document.getElementById('cart-items');
    const cartFooter = document.getElementById('cart-footer');
    const cartTotalPoints = document.getElementById('cart-total-points');
    const cartCheckoutBtn = document.getElementById('cart-checkout-btn');
    
    // Update cart badge
    const totalItems = shoppingCart.reduce((sum, item) => sum + item.quantity, 0);
    if (totalItems > 0) {
        cartBadge.textContent = totalItems;
        cartBadge.style.display = 'flex';
    } else {
        cartBadge.style.display = 'none';
    }
    
    // Update cart content
    if (shoppingCart.length === 0) {
        cartEmpty.style.display = 'block';
        cartItems.style.display = 'none';
        cartFooter.style.display = 'none';
    } else {
        cartEmpty.style.display = 'none';
        cartItems.style.display = 'flex';
        cartFooter.style.display = 'block';
        
        // Render cart items
        renderCartItems();
        
        // Update total
        const totalPoints = calculateTotalPoints();
        cartTotalPoints.textContent = totalPoints.toLocaleString();
        
        // Check if user has enough points
        const userPoints = <?= $userEcoPoints ?>;
        if (totalPoints > userPoints) {
            cartCheckoutBtn.disabled = true;
            cartCheckoutBtn.innerHTML = '<i class="bx bx-error"></i> Insufficient Points';
        } else {
            cartCheckoutBtn.disabled = false;
            cartCheckoutBtn.innerHTML = '<i class="bx bx-check-circle"></i> Proceed to Checkout';
        }
    }
    
    // Update "Add to Cart" buttons on the page
    updateAddToCartButtons();
}

// Render cart items
function renderCartItems() {
    const cartItemsContainer = document.getElementById('cart-items');
    cartItemsContainer.innerHTML = '';
    
    shoppingCart.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.className = 'cart-item';
        
        const imageHTML = item.image 
            ? `<img src="${item.image}" alt="${item.name}">`
            : `<div class="cart-item-emoji">🎁</div>`;
        
        itemElement.innerHTML = `
            <div class="cart-item-image">
                ${imageHTML}
            </div>
            <div class="cart-item-details">
                <h4 class="cart-item-name">${item.name}</h4>
                <div class="cart-item-points">
                    <i class='bx bx-leaf'></i>
                    ${item.points.toLocaleString()} pts each
                </div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-controls">
                    <button class="quantity-btn" onclick="updateQuantity(${item.id}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>
                        <i class='bx bx-minus'></i>
                    </button>
                    <span class="quantity-display">${item.quantity}</span>
                    <button class="quantity-btn" onclick="updateQuantity(${item.id}, 1)">
                        <i class='bx bx-plus'></i>
                    </button>
                </div>
                <button class="remove-item-btn" onclick="removeFromCart(${item.id})" title="Remove from cart">
                    <i class='bx bx-trash'></i>
                </button>
            </div>
        `;
        
        cartItemsContainer.appendChild(itemElement);
    });
}

// Update "Add to Cart" buttons on the page to show quantities
function updateAddToCartButtons() {
    // Update all buttons to show if they're in cart
    document.querySelectorAll('.cart-add-btn').forEach(button => {
        if (!button.disabled) {
            const itemId = parseInt(button.getAttribute('data-reward-id'));
            const cartItem = shoppingCart.find(item => item.id === itemId);
            
            if (cartItem) {
                // Item is in cart - show quantity
                button.innerHTML = `<i class='bx bx-cart-add'></i> In Cart (${cartItem.quantity})`;
                button.classList.add('in-cart');
            } else {
                // Item not in cart - show default
                button.innerHTML = `<i class='bx bx-cart-add'></i> Add to Cart`;
                button.classList.remove('in-cart');
            }
        }
    });
}

// Open cart modal
function openCartModal() {
    const modal = document.getElementById('cart-modal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    updateCartUI();
}

// Close cart modal
function closeCartModal() {
    const modal = document.getElementById('cart-modal');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('cart-modal');
    if (e.target === modal) {
        closeCartModal();
    }
});

// Show checkout confirmation modal
function showCheckoutConfirmModal() {
    const totalPoints = calculateTotalPoints();
    const itemCount = shoppingCart.length;
    
    // Create modal HTML
    const modal = document.createElement('div');
    modal.className = 'checkout-confirm-modal';
    modal.id = 'checkoutConfirmModal';
    
    modal.innerHTML = `
        <div class="checkout-confirm-modal-content">
            <h3>🛒 Confirm Checkout?</h3>
            <div class="checkout-summary">
                <p><span>Total Points:</span> <strong>🌿 ${totalPoints.toLocaleString()} points</strong></p>
                <p><span>Items:</span> <strong>${itemCount} item${itemCount > 1 ? 's' : ''}</strong></p>
            </div>
            <p class="warning-text">⚠️ Your request will be sent to admin for approval. Points will be deducted after approval.</p>
            <div class="checkout-confirm-modal-buttons">
                <button class="modal-btn modal-btn-cancel" onclick="closeCheckoutConfirmModal()">Cancel</button>
                <button class="modal-btn modal-btn-confirm" onclick="confirmCheckout()">Confirm Checkout</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // Close modal when clicking outside
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeCheckoutConfirmModal();
        }
    });
}

function closeCheckoutConfirmModal() {
    const modal = document.getElementById('checkoutConfirmModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function confirmCheckout() {
    closeCheckoutConfirmModal();
    processCheckout();
}

// Proceed to checkout
function proceedToCheckout() {
    const totalPoints = calculateTotalPoints();
    const userPoints = <?= $userEcoPoints ?>;
    
    if (totalPoints > userPoints) {
        showFlashMessage('error', 'You do not have enough points for this purchase.');
        return;
    }
    
    if (shoppingCart.length === 0) {
        showFlashMessage('error', 'Your cart is empty.');
        return;
    }
    
    // Show custom confirmation modal instead of browser alert
    showCheckoutConfirmModal();
}

// Process the actual checkout
function processCheckout() {
    
    // Disable checkout button
    const checkoutBtn = document.getElementById('cart-checkout-btn');
    if (checkoutBtn) {
        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';
    }
    
    // Prepare cart data
    const cartData = shoppingCart.map(item => ({
        itemId: item.id,
        itemName: item.name,
        quantity: item.quantity
    }));
    
    console.log('Sending checkout data:', { items: cartData });
    
    // Send checkout request
    fetch('redeem_multiple_items.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ items: cartData })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear cart
            shoppingCart = [];
            saveCartToStorage();
            updateCartUI();
            
            showFlashMessage('success', `✅ Checkout successful! Reference: ${data.data.reference_number}\n\nPlease wait for admin approval.`);
            
            closeCartModal();
            
            // Reload page after 3 seconds
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            showFlashMessage('error', data.message || 'Checkout failed. Please try again.');
            if (checkoutBtn) {
                checkoutBtn.disabled = false;
                checkoutBtn.innerHTML = '<i class="bx bx-check-circle"></i> Proceed to Checkout';
            }
        }
    })
    .catch(error => {
        console.error('Checkout error:', error);
        showFlashMessage('error', 'An error occurred during checkout. Please try again.');
        if (checkoutBtn) {
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = '<i class="bx bx-check-circle"></i> Proceed to Checkout';
        }
    });
}

// Setup click handlers for "Add to Cart" buttons
function setupCartButtonHandlers() {
    document.querySelectorAll('.cart-add-btn').forEach(button => {
        // Remove old listeners by cloning
        const newButton = button.cloneNode(true);
        button.parentNode.replaceChild(newButton, button);
        
        // Add new click listener
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (this.disabled) return;
            
            const itemId = parseInt(this.getAttribute('data-reward-id'));
            const itemName = this.getAttribute('data-item-name');
            const points = parseInt(this.getAttribute('data-points'));
            const imagePath = this.getAttribute('data-image');
            
            addToCart(itemId, itemName, points, imagePath);
        });
    });
}

// Initialize cart on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCartFromStorage();
    updateCartUI();
});
</script>

<!-- Shopping Cart UI -->
<button class="cart-button" onclick="openCartModal()" title="View Shopping Cart">
    <i class='bx bx-cart'></i>
    <span class="cart-badge" id="cart-badge" style="display: none;">0</span>
</button>

<div class="cart-modal" id="cart-modal">
    <div class="cart-modal-content">
        <div class="cart-header">
            <h2><i class='bx bx-cart'></i> Shopping Cart</h2>
            <button class="cart-close" onclick="closeCartModal()">
                <i class='bx bx-x'></i>
            </button>
        </div>
        
        <div class="cart-body" id="cart-body">
            <div class="cart-empty" id="cart-empty">
                <i class='bx bx-cart-alt'></i>
                <p>Your cart is empty</p>
            </div>
            
            <div class="cart-items" id="cart-items" style="display: none;">
                <!-- Cart items will be dynamically inserted here -->
            </div>
        </div>
        
        <div class="cart-footer" id="cart-footer" style="display: none;">
            <div class="cart-total">
                <span class="cart-total-label">Total Points:</span>
                <span class="cart-total-value">
                    <i class='bx bx-leaf'></i>
                    <span id="cart-total-points">0</span>
                </span>
            </div>
            <button class="cart-checkout-btn" id="cart-checkout-btn" onclick="proceedToCheckout()">
                <i class='bx bx-check-circle'></i>
                Proceed to Checkout
            </button>
        </div>
    </div>
</div>

</body>
</html>