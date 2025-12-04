<?php
session_start();
require_once 'database.php';

// Check admin access
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Administrator' && $_SESSION["accessrole"] != 'Barangay Official')) {
    $_SESSION['response'] = ['status' => 'error', 'msg' => 'Unauthorized access'];
    header("Location: index.php");
    exit();
}

// Reward configuration (ADJUST THESE VALUES AS NEEDED)
$rewardConfig = [
    'individual' => [
        1 => 500, 2 => 300, 3 => 200, 4 => 100, 5 => 100,
        6 => 50, 7 => 50, 8 => 50, 9 => 50, 10 => 50
    ],
    'group' => [ // For barangay, municipality, organization
        1 => 300, 2 => 200, 3 => 150, 4 => 100, 5 => 100
    ]
];

// Handle AJAX actions
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch($_POST['action']) {
        case 'create_cycle':
            $cycleName = trim($_POST['cycle_name']);
            $startDateTime = $_POST['start_datetime'];
            $endDateTime = $_POST['end_datetime'];
            
            // Validate datetime format and logic
            $start = strtotime($startDateTime);
            $end = strtotime($endDateTime);
            
            if($start >= $end) {
                echo json_encode(['success' => false, 'message' => 'End datetime must be after start datetime!']);
                exit;
            }
            
            // End any active cycles
            $connection->query("UPDATE reward_cycles SET status = 'ended', ended_at = NOW() WHERE status = 'active'");
            
            // Create new cycle
            $stmt = $connection->prepare("INSERT INTO reward_cycles (cycle_name, start_date, end_date, created_by, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssi", $cycleName, $startDateTime, $endDateTime, $_SESSION['user_id']);
            
            if($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'New cycle created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $connection->error]);
            }
            exit;
            
        case 'end_cycle':
            $cycleId = intval($_POST['cycle_id']);
            $stmt = $connection->prepare("UPDATE reward_cycles SET status = 'ended', ended_at = NOW() WHERE cycle_id = ?");
            $stmt->bind_param("i", $cycleId);
            
            if($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cycle ended successfully. Ready to calculate rewards.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $connection->error]);
            }
            exit;
            
        case 'calculate_rewards':
            $cycleId = intval($_POST['cycle_id']);
            
            // Include cycle rankings helper
            require_once 'cycle_rankings.php';
            
            $connection->begin_transaction();
            
            try {
                $totalRewards = 0;
                
                // 1. Individual Rewards (Top 10) - Based on cycle activity
                $topUsers = getCycleIndividualRankings($cycleId, 10);
                $rank = 1;
                foreach($topUsers as $user) {
                    $points = $rewardConfig['individual'][$rank] ?? 0;
                    if($points > 0) {
                        $stmt = $connection->prepare("INSERT IGNORE INTO user_rewards (cycle_id, user_id, category, rank_achieved, entity_name, points_awarded) VALUES (?, ?, 'individual', ?, ?, ?)");
                        $stmt->bind_param("iiisi", $cycleId, $user['user_id'], $rank, $user['fullname'], $points);
                        $stmt->execute();
                        $totalRewards++;
                    }
                    $rank++;
                }
                
                // 2. Barangay Rewards (Top 5) - Based on cycle activity
                $topBarangays = getCycleBarangayRankings($cycleId, 5);
                $rank = 1;
                foreach($topBarangays as $barangay) {
                    $points = $rewardConfig['group'][$rank] ?? 0;
                    if($points > 0) {
                        // Get only active members who contributed during the cycle
                        $activeMembers = getCycleGroupActiveMembers($cycleId, 'barangay', $barangay['barangay']);
                        
                        foreach($activeMembers as $memberId) {
                            $stmt = $connection->prepare("INSERT IGNORE INTO user_rewards (cycle_id, user_id, category, rank_achieved, entity_name, points_awarded) VALUES (?, ?, 'barangay', ?, ?, ?)");
                            $stmt->bind_param("iiisi", $cycleId, $memberId, $rank, $barangay['barangay'], $points);
                            $stmt->execute();
                            $totalRewards++;
                        }
                    }
                    $rank++;
                }
                
                // 3. Municipality Rewards (Top 5) - Based on cycle activity
                $topMunicipalities = getCycleMunicipalityRankings($cycleId, 5);
                $rank = 1;
                foreach($topMunicipalities as $muni) {
                    $points = $rewardConfig['group'][$rank] ?? 0;
                    if($points > 0) {
                        // Get only active members who contributed during the cycle
                        $activeMembers = getCycleGroupActiveMembers($cycleId, 'city_municipality', $muni['city_municipality']);
                        
                        foreach($activeMembers as $memberId) {
                            $stmt = $connection->prepare("INSERT IGNORE INTO user_rewards (cycle_id, user_id, category, rank_achieved, entity_name, points_awarded) VALUES (?, ?, 'municipality', ?, ?, ?)");
                            $stmt->bind_param("iiisi", $cycleId, $memberId, $rank, $muni['city_municipality'], $points);
                            $stmt->execute();
                            $totalRewards++;
                        }
                    }
                    $rank++;
                }
                
                // 4. Organization Rewards (Top 5) - Based on cycle activity
                $topOrgs = getCycleOrganizationRankings($cycleId, 5);
                $rank = 1;
                foreach($topOrgs as $org) {
                    $points = $rewardConfig['group'][$rank] ?? 0;
                    if($points > 0) {
                        // Get only active members who contributed during the cycle
                        $activeMembers = getCycleGroupActiveMembers($cycleId, 'organization', $org['organization']);
                        
                        foreach($activeMembers as $memberId) {
                            $stmt = $connection->prepare("INSERT IGNORE INTO user_rewards (cycle_id, user_id, category, rank_achieved, entity_name, points_awarded) VALUES (?, ?, 'organization', ?, ?, ?)");
                            $stmt->bind_param("iiisi", $cycleId, $memberId, $rank, $org['organization'], $points);
                            $stmt->execute();
                            $totalRewards++;
                        }
                    }
                    $rank++;
                }
                
                // Mark cycle as finalized
                $stmt = $connection->prepare("UPDATE reward_cycles SET status = 'finalized', finalized_at = NOW() WHERE cycle_id = ?");
                $stmt->bind_param("i", $cycleId);
                $stmt->execute();
                
                $connection->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rewards calculated successfully!',
                    'total_rewards' => $totalRewards
                ]);
                
            } catch(Exception $e) {
                $connection->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Fetch current data
$currentCycle = $connection->query("SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
$endedCycles = $connection->query("SELECT * FROM reward_cycles WHERE status = 'ended' ORDER BY ended_at DESC")->fetch_all(MYSQLI_ASSOC);
$finalizedCycles = $connection->query("SELECT * FROM reward_cycles WHERE status = 'finalized' ORDER BY finalized_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rewards Manager - ManGrow</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminleaderboards.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        main {
            background: var(--accent-clr);
            min-height: calc(100vh - 120px);
            padding: 2rem 1rem;
        }
        .rewards-manager {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }
        .rewards-manager > h1 {
            color: var(--base-clr);
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        .rewards-manager > h1 i {
            color: #f39c12;
        }
        .cycle-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px rgba(18, 53, 36, 0.15);
            border: 1px solid rgba(18, 53, 36, 0.1);
            transition: all 0.3s ease;
            width: 100%;
            box-sizing: border-box;
        }
        .cycle-card + .cycle-card {
            margin-top: 1.5rem;
        }
        .cycle-card:hover {
            box-shadow: 0 8px 30px rgba(18, 53, 36, 0.2);
            transform: translateY(-2px);
        }
        .cycle-card h2 {
            color: var(--base-clr);
            font-size: 1.8rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        .cycle-card h2 i {
            margin-right: 10px;
            color: #f39c12;
        }
        .cycle-card h3 {
            color: var(--base-clr);
            font-size: 1.4rem;
            margin-bottom: 0.8rem;
        }
        .cycle-card p {
            color: #555;
            font-size: 1rem;
            margin: 0.5rem 0;
        }
        .status-badge {
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            font-weight: bold;
            display: inline-block;
            margin-left: 1rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; box-shadow: 0 2px 10px rgba(76, 175, 80, 0.3); }
        .status-ended { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; box-shadow: 0 2px 10px rgba(243, 156, 18, 0.3); }
        .status-finalized { background: linear-gradient(135deg, #3498db, #2980b9); color: white; box-shadow: 0 2px 10px rgba(52, 152, 219, 0.3); }
        .action-btn {
            padding: 0.9rem 1.8rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin: 0.5rem 0.5rem 0.5rem 0;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .action-btn i {
            font-size: 1.2rem;
        }
        .btn-primary { background: linear-gradient(135deg, #4CAF50, #45a049); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .btn-success { background: linear-gradient(135deg, #27ae60, #229954); color: white; }
        .action-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 6px 20px rgba(0,0,0,0.25); 
        }
        .action-btn:active {
            transform: translateY(-1px);
        }
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.8rem; 
            font-weight: 600;
            color: var(--base-clr);
            font-size: 1rem;
        }

        .form-group input { 
            box-sizing:border-box;
            width: 100%; 
            padding: 1rem; 
            border: 2px solid #ddd; 
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--base-clr);
            box-shadow: 0 0 0 3px rgba(18, 53, 36, 0.1);
        }
        .reward-summary { 
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            padding-top: 1.5rem;
            width: 100%;
        }
        .summary-card { 
            background: linear-gradient(135deg, #f8f9fa, #e9ecef); 
            padding: 1.25rem 1.5rem;
            border-radius: 12px; 
            text-align: center;
            border: 2px solid #dee2e6;
            transition: all 0.3s ease;
            flex: 1 1 calc(25% - 0.75rem);
            min-width: 180px;
            box-sizing: border-box;
        }
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .summary-card h4 { 
            margin: 0; 
            color: #666; 
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card p { 
            margin: 0.8rem 0 0 0; 
            font-size: 1.8rem; 
            font-weight: bold; 
            color: var(--base-clr);
        }
        .cycle-detail-box {
            background: #fff9e6;
            padding: 1.25rem 1.5rem;
            border-radius: 12px;
            border-left: 5px solid #f39c12;
            box-sizing: border-box;
        }
        .cycle-detail-box + .cycle-detail-box {
            margin-top: 1rem;
        }
        .cycle-detail-box-success {
            background: #e8f5e9;
            border-left-color: #4CAF50;
        }
        small {
            display: block;
            margin-top: 0.8rem;
            color: #888;
            font-size: 0.9rem;
        }
        .participants-table th{
            color:var(--text-clr);
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            main {
                padding: 1.5rem 0.75rem;
            }
            .rewards-manager > h1 {
                font-size: 1.8rem;
                margin-bottom: 1rem;
            }
            .cycle-card {
                padding: 1.25rem 1.5rem;
            }
            .cycle-card + .cycle-card {
                margin-top: 1.25rem;
            }
            .cycle-card h2 {
                font-size: 1.4rem;
            }
            .reward-summary {
                gap: 0.75rem;
                padding-top: 1.25rem;
            }
            .summary-card {
                flex: 1 1 calc(50% - 0.375rem);
                min-width: 140px;
                padding: 1rem 1.25rem;
            }
            .action-btn {
                padding: 0.7rem 1.2rem;
                font-size: 0.85rem;
            }
            .form-group {
                margin-bottom: 1.25rem;
            }
        }
        
        @media screen and (max-width: 480px) {
            main {
                padding: 1rem 0.5rem;
            }
            .rewards-manager > h1 {
                font-size: 1.5rem;
            }
            .cycle-card {
                padding: 1rem 1.25rem;
            }
            .cycle-card + .cycle-card {
                margin-top: 1rem;
            }
            .cycle-card h2 {
                font-size: 1.2rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            .cycle-card h2 i {
                margin-right: 5px;
            }
            .status-badge {
                margin-left: 0;
                padding: 0.4rem 1rem;
                font-size: 0.8rem;
            }
            .reward-summary {
                flex-direction: column;
                gap: 0.75rem;
                padding-top: 1rem;
            }
            .summary-card {
                flex: 1 1 100%;
                padding: 1rem;
            }
            .summary-card h4 {
                font-size: 0.85rem;
            }
            .summary-card p {
                font-size: 1.5rem;
            }
            .action-btn {
                padding: 0.65rem 1rem;
                font-size: 0.8rem;
                width: 100%;
                justify-content: center;
            }
            .form-group {
                margin-bottom: 1rem;
            }
            .form-group input {
                padding: 0.85rem;
            }
            .cycle-detail-box {
                padding: 1rem 1.25rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"){ ?>
                <li><a href="adminprofile.php"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>            
                <li class="active"><a href="#"><i class="far fa-chart-bar" style="margin-bottom:-5px"></i></a></li>
                <?php } ?>
            </ul>
        </nav>
        
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
    </header>
    
    <main class="rewards-manager">
        <a href="adminleaderboards.php" class="action-btn btn-primary" style="margin-bottom: 1rem; text-decoration:none;"><i class='bx bx-arrow-back'></i> Back to Leaderboards</a>
        <h1><i class='bx bx-trophy'></i> Leaderboard Rewards Manager</h1>
        <p style="color: #666; margin-bottom: 2rem;">Manage reward cycles and calculate rewards for top performers</p>
        
        <!-- Current Active Cycle -->
        <?php if($currentCycle): ?>
        <div class="cycle-card" id="active-cycle-card">
            <h2>
                Current Cycle: <?= htmlspecialchars($currentCycle['cycle_name']) ?>
                <span class="status-badge status-active" id="cycle-status-badge">ACTIVE</span>
            </h2>
            <p><strong>Period:</strong> <span id="cycle-period"><?= date('M j, Y g:i A', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y g:i A', strtotime($currentCycle['end_date'])) ?></span></p>
            <p><strong>Started:</strong> <?= date('M j, Y g:i A', strtotime($currentCycle['created_at'])) ?></p>
            <p id="cycle-countdown" style="color: #f39c12; font-weight: bold; font-size: 1.1rem; margin-top: 1rem;"></p>
            
            <button class="action-btn btn-danger" onclick="endCycle(<?= $currentCycle['cycle_id'] ?>)" id="end-cycle-btn">
                <i class='bx bx-stop-circle'></i> End This Cycle
            </button>
            <small style="color: #888; display: block; margin-top: 0.5rem;">
                ⚠️ Ending the cycle will prepare it for reward calculation
            </small>
            
            <script>
                // Store cycle end time for countdown
                const cycleEndTime = new Date('<?= $currentCycle['end_date'] ?>').getTime();
                const cycleId = <?= $currentCycle['cycle_id'] ?>;
            </script>
        </div>
        
        <!-- Cycle Participants Table (Real-Time) -->
        <div class="cycle-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2><i class='bx bx-group'></i> Active Participants This Cycle</h2>
                <span id="participants-last-update" style="font-size: 0.85rem; color: #888;">
                    <i class='bx bx-time'></i> Loading...
                </span>
            </div>
            
            <!-- Cycle Statistics -->
            <div class="reward-summary" id="cycle-stats" style="margin-bottom: 1.5rem;">
                <div class="summary-card">
                    <h4>Total Participants</h4>
                    <p id="stat-participants">-</p>
                </div>
                <div class="summary-card">
                    <h4>Total Points Earned</h4>
                    <p id="stat-total-points">-</p>
                </div>
                <div class="summary-card">
                    <h4>Total Activities</h4>
                    <p id="stat-activities">-</p>
                </div>
                <div class="summary-card">
                    <h4>Avg Points/User</h4>
                    <p id="stat-avg-points">-</p>
                </div>
            </div>
            
            <!-- Participants Table -->
            <div style="overflow-x: auto;">
                <table class="participants-table" id="participants-table">
                    <thead>
                        <tr>
                            <th style="width: 60px; text-align: center;">Rank</th>
                            <th>User</th>
                            <th>Barangay</th>
                            <th>Municipality</th>
                            <th>Organization</th>
                            <th style="text-align: center;">Activities</th>
                            <th style="text-align: center;">Points Earned</th>
                            <th style="text-align: center;">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody id="participants-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem; color: #888;">
                                <i class='bx bx-loader-alt bx-spin' style="font-size: 2rem;"></i>
                                <br>Loading participants...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <p style="margin-top: 1rem; color: #888; font-size: 0.9rem;">
                <i class='bx bx-info-circle'></i> Automatically refreshes every 30 seconds
            </p>
        </div>
        <?php else: ?>
        <div class="cycle-card">
            <h2>No Active Cycle</h2>
            <p>Create a new reward cycle to start tracking leaderboards</p>
        </div>
        <?php endif; ?>
        
        <!-- Ended Cycles (Ready for Calculation) -->
        <?php if(!empty($endedCycles)): ?>
        <div class="cycle-card">
            <h2>Ended Cycles <span class="status-badge status-ended">READY FOR CALCULATION</span></h2>
            <?php foreach($endedCycles as $cycle): ?>
            <div class="cycle-detail-box">
                <h3><?= htmlspecialchars($cycle['cycle_name']) ?></h3>
                <p><strong>Period:</strong> <?= date('M j, Y', strtotime($cycle['start_date'])) ?> - <?= date('M j, Y', strtotime($cycle['end_date'])) ?></p>
                <p><strong>Ended:</strong> <?= date('M j, Y g:i A', strtotime($cycle['ended_at'])) ?></p>
                
                <button class="action-btn btn-success" onclick="calculateRewards(<?= $cycle['cycle_id'] ?>, '<?= htmlspecialchars($cycle['cycle_name']) ?>')">
                    <i class='bx bx-calculator'></i> Calculate & Distribute Rewards
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Create New Cycle -->
        <div class="cycle-card">
            <h2><i class='bx bx-plus-circle'></i> Create New Reward Cycle</h2>
            <form id="createCycleForm" onsubmit="createCycle(event)">
                <div class="form-group">
                    <label>Cycle Name:</label>
                    <input type="text" name="cycle_name" placeholder="e.g., November 2025, Q4 2025" required>
                </div>
                <div class="form-group">
                    <label>Start Date & Time:</label>
                    <input type="datetime-local" name="start_datetime" required>
                    <small style="color: #888; display: block; margin-top: 0.5rem;">📅 Exact date and time when the cycle starts (prevents same-day overlap)</small>
                </div>
                <div class="form-group">
                    <label>End Date & Time:</label>
                    <input type="datetime-local" name="end_datetime" required>
                    <small style="color: #888; display: block; margin-top: 0.5rem;">📅 Exact date and time when the cycle ends</small>
                </div>
                <button type="submit" class="action-btn btn-primary">
                    <i class='bx bx-calendar-plus'></i> Create Cycle
                </button>
            </form>
        </div>
        
        <!-- Reward Configuration -->
        <div class="cycle-card">
            <h2><i class='bx bx-cog'></i> Reward Configuration</h2>
            <div class="reward-summary">
                <div class="summary-card">
                    <h4>Individual 1st</h4>
                    <p>500 pts</p>
                </div>
                <div class="summary-card">
                    <h4>Individual 2nd-3rd</h4>
                    <p>300-200 pts</p>
                </div>
                <div class="summary-card">
                    <h4>Group 1st</h4>
                    <p>300 pts/member</p>
                </div>
                <div class="summary-card">
                    <h4>Group 2nd-5th</h4>
                    <p>200-100 pts/member</p>
                </div>
            </div>
            <p style="margin-top: 1rem; color: #888; font-size: 0.9rem;">
                <i class='bx bx-info-circle'></i> Points distributed to ALL members of winning groups
            </p>
        </div>
        
        <!-- Finalized Cycles -->
        <?php if(!empty($finalizedCycles)): ?>
        <div class="cycle-card">
            <h2>Recent Completed Cycles</h2>
            <?php foreach($finalizedCycles as $cycle): ?>
            <?php
                $stmt = $connection->prepare("SELECT COUNT(DISTINCT user_id) as recipients, SUM(points_awarded) as total_points FROM user_rewards WHERE cycle_id = ?");
                $stmt->bind_param("i", $cycle['cycle_id']);
                $stmt->execute();
                $stats = $stmt->get_result()->fetch_assoc();
            ?>
            <div class="cycle-detail-box cycle-detail-box-success">
                <h3><?= htmlspecialchars($cycle['cycle_name']) ?> <span class="status-badge status-finalized">COMPLETED</span></h3>
                <p><strong>Finalized:</strong> <?= date('M j, Y g:i A', strtotime($cycle['finalized_at'])) ?></p>
                <div style="display: flex; gap: 1.5rem; margin-top: 1rem; font-size: 0.95rem; flex-wrap: wrap;">
                    <span><i class='bx bx-group'></i> <?= $stats['recipients'] ?> recipients</span>
                    <span><i class='bx bx-coin'></i> <?= number_format($stats['total_points']) ?> points ready to claim</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    
    <script>
    function createCycle(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.append('action', 'create_cycle');
        
        const button = e.target.querySelector('button[type="submit"]');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Creating...';
        
        fetch('admin_rewards_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            if(data.success) {
                e.target.reset();
                setTimeout(() => {
                    location.reload(); // Need reload for new active cycle structure
                }, 1500);
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred. Please try again.', 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
    
    function endCycle(cycleId) {
        if(!confirm('End this cycle? Users can no longer earn points for this period.')) return;
        
        const button = document.getElementById('end-cycle-btn');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Ending...';
        
        const formData = new FormData();
        formData.append('action', 'end_cycle');
        formData.append('cycle_id', cycleId);
        
        fetch('admin_rewards_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            showMessage(data.message, data.success ? 'success' : 'error');
            if(data.success) {
                updateActiveCycleToEnded();
            } else {
                button.disabled = false;
                button.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('An error occurred. Please try again.', 'error');
            button.disabled = false;
            button.innerHTML = originalText;
        });
    }
    
    function calculateRewards(cycleId, cycleName) {
        if(!confirm(`Calculate rewards for "${cycleName}"?\n\nThis will create claimable rewards for all top performers. This action cannot be undone.`)) return;
        
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Calculating...';
        
        const formData = new FormData();
        formData.append('action', 'calculate_rewards');
        formData.append('cycle_id', cycleId);
        
        fetch('admin_rewards_manager.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            showMessage(`${data.message}\n\nTotal Rewards Created: ${data.total_rewards || 0}`, data.success ? 'success' : 'error');
            if(data.success) {
                removeCycleFromEndedSection(cycleId);
                setTimeout(() => {
                    location.reload(); // Need reload for restructuring sections
                }, 2000);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bx bx-calculator"></i> Calculate & Distribute Rewards';
            }
        })
        .catch(err => {
            showMessage('Error: ' + err, 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bx bx-calculator"></i> Calculate & Distribute Rewards';
        });
    }
    
    // ============================================
    // AJAX HELPER FUNCTIONS
    // ============================================
    
    function showMessage(message, type = 'info') {
        // Remove any existing message
        const existingMessage = document.querySelector('.ajax-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Create new message element
        const messageDiv = document.createElement('div');
        messageDiv.className = `ajax-message ajax-message-${type}`;
        messageDiv.innerHTML = `
            <div style="
                position: fixed; 
                top: 20px; 
                right: 20px; 
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'}; 
                color: white; 
                padding: 1rem 1.5rem; 
                border-radius: 8px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.3); 
                z-index: 10000; 
                max-width: 400px; 
                font-weight: 500;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            ">
                <i class="bx ${type === 'success' ? 'bx-check-circle' : type === 'error' ? 'bx-error-circle' : 'bx-info-circle'}"></i> 
                ${message.replace(/\\n/g, '<br>')}
            </div>
        `;
        
        document.body.appendChild(messageDiv);
        
        // Animate in
        setTimeout(() => {
            messageDiv.firstElementChild.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove after 4 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.firstElementChild.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 300);
            }
        }, 4000);
    }
    
    function updateActiveCycleToEnded() {
        const cycleCard = document.getElementById('active-cycle-card');
        const statusBadge = document.getElementById('cycle-status-badge');
        const endButton = document.getElementById('end-cycle-btn');
        const countdown = document.getElementById('cycle-countdown');
        
        if (statusBadge) {
            statusBadge.className = 'status-badge status-ended';
            statusBadge.textContent = 'ENDED';
        }
        
        if (endButton) {
            endButton.style.display = 'none';
        }
        
        if (countdown) {
            countdown.innerHTML = '<i class="bx bx-check-circle"></i> <strong>Cycle Ended</strong> - Ready for reward calculation';
            countdown.style.color = '#f39c12';
        }
        
        // Stop participant monitoring
        if (window.participantsInterval) {
            clearInterval(window.participantsInterval);
        }
        
        showMessage('Cycle ended successfully! The cycle is now ready for reward calculation.', 'success');
    }
    
    function removeCycleFromEndedSection(cycleId) {
        // Find and remove the cycle from ended cycles section
        const buttons = document.querySelectorAll('button[onclick*="calculateRewards"]');
        buttons.forEach(btn => {
            if (btn.onclick.toString().includes(cycleId)) {
                const cycleBox = btn.closest('.cycle-detail-box');
                if (cycleBox) {
                    cycleBox.style.opacity = '0.5';
                    cycleBox.innerHTML = `
                        <h3>Rewards Calculated Successfully</h3>
                        <p style="color: #4CAF50;"><i class="bx bx-check-circle"></i> Points have been distributed and are ready for users to claim</p>
                    `;
                }
            }
        });
    }

    // Profile popup toggle
    function toggleProfilePopup(event) {
        event.stopPropagation();
        const profileDetails = document.getElementById('profile-details');
        if (profileDetails) {
            profileDetails.classList.toggle('close');
        }
    }
    
    // Close profile popup when clicking outside
    document.addEventListener('click', function(event) {
        const profileDetails = document.getElementById('profile-details');
        const userbox = document.querySelector('.userbox');
        if (profileDetails && !profileDetails.classList.contains('close')) {
            if (!userbox.contains(event.target) && !profileDetails.contains(event.target)) {
                profileDetails.classList.add('close');
            }
        }
    });
    
    // ============================================
    // AUTOMATIC CYCLE STATUS MONITORING
    // ============================================
    
    // Update cycle countdown and auto-end when time expires
    function updateCycleCountdown() {
        const countdownElement = document.getElementById('cycle-countdown');
        const statusBadge = document.getElementById('cycle-status-badge');
        const endButton = document.getElementById('end-cycle-btn');
        
        if (!countdownElement || typeof cycleEndTime === 'undefined') return;
        
        const now = new Date().getTime();
        const distance = cycleEndTime - now;
        
        if (distance < 0) {
            // Cycle has ended
            countdownElement.innerHTML = '⏰ <strong>Cycle Has Ended!</strong> Click "End This Cycle" to proceed with calculations.';
            countdownElement.style.color = '#e74c3c';
            
            if (statusBadge) {
                statusBadge.textContent = 'EXPIRED';
                statusBadge.className = 'status-badge status-ended';
            }
            
            if (endButton) {
                endButton.classList.remove('btn-danger');
                endButton.classList.add('btn-success');
                endButton.innerHTML = '<i class="bx bx-check-circle"></i> End Cycle & Calculate';
            }
        } else {
            // Calculate remaining time
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            let timeString = '⏱️ <strong>Time Remaining:</strong> ';
            if (days > 0) timeString += `${days}d `;
            if (hours > 0 || days > 0) timeString += `${hours}h `;
            if (minutes > 0 || hours > 0 || days > 0) timeString += `${minutes}m `;
            timeString += `${seconds}s`;
            
            countdownElement.innerHTML = timeString;
            
            // Warning colors
            if (distance < 3600000) { // Less than 1 hour
                countdownElement.style.color = '#e74c3c';
            } else if (distance < 86400000) { // Less than 1 day
                countdownElement.style.color = '#f39c12';
            } else {
                countdownElement.style.color = '#27ae60';
            }
        }
    }
    
    // Auto-check cycle status every 10 seconds
    function checkCycleStatus() {
        if (typeof cycleId === 'undefined') return;
        
        fetch('get_cycle_status.php?cycle_id=' + cycleId)
            .then(response => response.json())
            .then(data => {
                console.log('Cycle status check:', data); // Debug log
                
                // Check the correct property path for cycle status
                if (data.success && data.cycle && data.cycle.status !== 'active') {
                    console.log('Cycle ended detected:', data.cycle.status);
                    // Only update UI if THIS specific cycle has been ended
                    updateActiveCycleToEnded();
                    // Stop further status checks
                    clearInterval(window.statusCheckInterval);
                } else if (data.auto_ended) {
                    console.log('Cycle auto-ended due to time expiration');
                    // Cycle was automatically ended due to time expiration
                    updateActiveCycleToEnded();
                    clearInterval(window.statusCheckInterval);
                } else {
                    console.log('Cycle still active:', data.cycle ? data.cycle.status : 'unknown');
                }
            })
            .catch(error => console.error('Error checking cycle status:', error));
    }
    
    // Initialize if active cycle exists
    if (typeof cycleEndTime !== 'undefined') {
        updateCycleCountdown();
        setInterval(updateCycleCountdown, 1000); // Update every second
        window.statusCheckInterval = setInterval(checkCycleStatus, 10000); // Check status every 10 seconds
        console.log('✅ Cycle countdown started');
        
        // Load and auto-refresh cycle participants
        loadCycleParticipants();
        window.participantsInterval = setInterval(loadCycleParticipants, 30000); // Refresh every 30 seconds
        console.log('✅ Cycle participants monitoring started');
    }
    
    // ============================================
    // CYCLE PARTICIPANTS REAL-TIME MONITORING
    // ============================================
    
    function loadCycleParticipants() {
        if (typeof cycleId === 'undefined') return;
        
        fetch('get_cycle_participants.php?cycle_id=' + cycleId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateParticipantsStats(data.stats);
                    displayParticipants(data.participants);
                    updateLastRefreshTime();
                } else {
                    console.error('Failed to load participants:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading participants:', error);
            });
    }
    
    function updateParticipantsStats(stats) {
        document.getElementById('stat-participants').textContent = stats.total_participants.toLocaleString();
        document.getElementById('stat-total-points').textContent = stats.total_points_earned.toLocaleString();
        document.getElementById('stat-activities').textContent = stats.total_activities.toLocaleString();
        document.getElementById('stat-avg-points').textContent = stats.avg_points_per_user.toLocaleString();
    }
    
    function displayParticipants(participants) {
        const tbody = document.getElementById('participants-tbody');
        
        if (participants.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">
                        <i class='bx bx-user-x'></i>
                        <br>No participants yet. Users will appear here when they earn points during this cycle.
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = '';
        
        participants.forEach((user, index) => {
            const rank = index + 1;
            const rankClass = rank <= 3 ? `rank-${rank}` : '';
            const rankEmoji = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : rank;
            
            // Format last activity time
            const lastActivity = user.last_activity ? formatTimeAgo(user.last_activity) : 'N/A';
            const isRecent = isActivityRecent(user.last_activity);
            
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="rank-cell ${rankClass}">${rankEmoji}</td>
                <td>
                    <div class="user-cell">
                        ${user.profile_thumbnail && user.profile_thumbnail !== 'uploads/' 
                            ? `<img src="${user.profile_thumbnail}" alt="${user.fullname}" class="user-avatar">`
                            : `<div class="user-avatar" style="background: linear-gradient(135deg, var(--primary-clr), var(--base-clr)); display: flex; align-items: center; justify-content: center; color: var(--text-clr); font-weight: bold;">
                                ${user.fullname.charAt(0).toUpperCase()}
                               </div>`
                        }
                        <span class="user-name">${user.fullname}</span>
                    </div>
                </td>
                <td>${user.barangay || '-'}</td>
                <td>${user.city_municipality || '-'}</td>
                <td>${user.organization || '-'}</td>
                <td class="activities-cell">${user.activities}</td>
                <td class="points-cell">
                    <i class='bx bx-coin' style="color: #FFD700;"></i> ${user.total_points.toLocaleString()}
                </td>
                <td class="activity-time ${isRecent ? 'recent' : ''}">
                    ${isRecent ? '<i class="bx bx-time-five"></i> ' : ''}${lastActivity}
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }
    
    function formatTimeAgo(dateString) {
        if (!dateString) return 'N/A';
        
        const now = new Date();
        const activityDate = new Date(dateString);
        const diffMs = now - activityDate;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        
        return activityDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    function isActivityRecent(dateString) {
        if (!dateString) return false;
        
        const now = new Date();
        const activityDate = new Date(dateString);
        const diffMs = now - activityDate;
        const diffMins = Math.floor(diffMs / 60000);
        
        return diffMins < 60; // Recent if within last hour
    }
    
    function updateLastRefreshTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        document.getElementById('participants-last-update').innerHTML = 
            `<i class='bx bx-time'></i> Last updated: ${timeString}`;
    }
    </script>
    
    <div class="profile-details close" id="profile-details">
        <div class ="details-box">
            <h2><?php echo isset($_SESSION["name"]) ? $_SESSION["name"] : ""; ?></h2>
            <p><?php echo isset($_SESSION["email"]) ? $_SESSION["email"] : ""; ?></p>
            <p><?php echo isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : ""; ?></p>
            <p><?php echo isset($_SESSION["organization"]) ? $_SESSION["organization"] : ""; ?></p>
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
    </div>
    
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
</body>
</html>
<?php $connection->close(); ?>
