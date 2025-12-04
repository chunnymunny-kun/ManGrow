<?php
    session_start();
    include 'database.php';
    require_once 'getdropdown.php';
    require_once 'ecoshop_image_handler.php';

    if(isset($_SESSION["accessrole"])){
        // Check if role is NOT in allowed list
        if($_SESSION["accessrole"] != 'Barangay Official' && 
           $_SESSION["accessrole"] != 'Administrator' && 
           $_SESSION["accessrole"] != 'Representative') {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'This account is not authorized'
            ];
            header("Location: index.php");
            exit();
        }
        
        // Set variables if logged in with proper role
        if(isset($_SESSION["name"])){
            $email = $_SESSION["name"];
        }
        if(isset($_SESSION["accessrole"])){
            $accessrole = $_SESSION["accessrole"];
        }
    } else {
        // No accessrole set at all - redirect to login
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Please login first'
        ];
        header("Location: index.php");
        exit();
    }

    // Handle form submissions for eco shop management
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_item':
                    $name = mysqli_real_escape_string($connection, $_POST['item_name']);
                    $description = mysqli_real_escape_string($connection, $_POST['item_description']);
                    $points = intval($_POST['points_required']);
                    $category = mysqli_real_escape_string($connection, $_POST['category']);
                    $stock = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? intval($_POST['stock_quantity']) : NULL;
                    
                    // Handle image upload
                    $imagePath = NULL;
                    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadEcoShopImage($_FILES['item_image'], $name);
                        if ($uploadResult['success']) {
                            $imagePath = $uploadResult['path'];
                        } else {
                            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Image upload failed: ' . $uploadResult['message']];
                            break;
                        }
                    }
                    
                    $insertQuery = "INSERT INTO ecoshop_itemstbl (item_name, item_description, points_required, category, stock_quantity, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $connection->prepare($insertQuery);
                    $stmt->bind_param("ssssis", $name, $description, $points, $category, $stock, $imagePath);
                    
                    if ($stmt->execute()) {
                        $new_item_id = $connection->insert_id;
                        logEcoShopActivity($connection, $_SESSION['user_id'], 'item_added', $new_item_id, null, "Added new item: $name");
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Item added successfully!'];
                    } else {
                        // Delete uploaded image if database insert fails
                        if ($imagePath) {
                            deleteEcoShopImage($imagePath);
                        }
                        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error adding item: ' . $connection->error];
                    }
                    break;

                case 'update_item':
                    $item_id = intval($_POST['item_id']);
                    $name = mysqli_real_escape_string($connection, $_POST['item_name']);
                    $description = mysqli_real_escape_string($connection, $_POST['item_description']);
                    $points = intval($_POST['points_required']);
                    $category = mysqli_real_escape_string($connection, $_POST['category']);
                    $stock = isset($_POST['stock_quantity']) && $_POST['stock_quantity'] !== '' ? intval($_POST['stock_quantity']) : NULL;
                    $available = isset($_POST['is_available']) ? 1 : 0;
                    
                    // Get current image path
                    $currentImageQuery = "SELECT image_path FROM ecoshop_itemstbl WHERE item_id = ?";
                    $stmt = $connection->prepare($currentImageQuery);
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $currentImagePath = $result->fetch_assoc()['image_path'] ?? NULL;
                    
                    $imagePath = $currentImagePath; // Keep current image by default
                    
                    // Handle new image upload
                    if (isset($_FILES['edit_item_image']) && $_FILES['edit_item_image']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadEcoShopImage($_FILES['edit_item_image'], $name);
                        if ($uploadResult['success']) {
                            // Delete old image if exists
                            if ($currentImagePath) {
                                deleteEcoShopImage($currentImagePath);
                            }
                            $imagePath = $uploadResult['path'];
                        } else {
                            $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Image upload failed: ' . $uploadResult['message']];
                            break;
                        }
                    }
                    
                    // Handle image removal
                    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                        if ($currentImagePath) {
                            deleteEcoShopImage($currentImagePath);
                        }
                        $imagePath = NULL;
                    }
                    
                    // Auto-unavailable when stock is 0
                    if ($stock !== null && $stock <= 0) {
                        $available = 0;
                        $autoUnavailableMsg = " (automatically made unavailable due to zero stock)";
                    } else {
                        $autoUnavailableMsg = "";
                    }
                    
                    $updateQuery = "UPDATE ecoshop_itemstbl SET item_name=?, item_description=?, points_required=?, category=?, stock_quantity=?, is_available=?, image_path=? WHERE item_id=?";
                    $stmt = $connection->prepare($updateQuery);
                    $stmt->bind_param("ssissssi", $name, $description, $points, $category, $stock, $available, $imagePath, $item_id);
                    
                    if ($stmt->execute()) {
                        logEcoShopActivity($connection, $_SESSION['user_id'], 'item_edited', $item_id, null, "Updated item: $name" . $autoUnavailableMsg);
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Item updated successfully!' . $autoUnavailableMsg];
                    } else {
                        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error updating item: ' . $connection->error];
                    }
                    break;

                case 'delete_item':
                    $item_id = intval($_POST['item_id']);
                    
                    // Get item details before marking unavailable for logging
                    $itemQuery = "SELECT item_name FROM ecoshop_itemstbl WHERE item_id = ?";
                    $stmt = $connection->prepare($itemQuery);
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $itemData = $result->fetch_assoc();
                    $itemName = $itemData['item_name'] ?? 'Unknown Item';
                    
                    // Mark item as unavailable instead of deleting
                    $updateQuery = "UPDATE ecoshop_itemstbl SET is_available = 0 WHERE item_id = ?";
                    $stmt = $connection->prepare($updateQuery);
                    $stmt->bind_param("i", $item_id);
                    
                    if ($stmt->execute()) {
                        logEcoShopActivity($connection, $_SESSION['user_id'], 'item_unavailable', $item_id, null, "Made item unavailable: $itemName");
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Item marked as unavailable successfully!'];
                    } else {
                        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error updating item: ' . $connection->error];
                    }
                    break;

                case 'make_available':
                    $item_id = intval($_POST['item_id']);
                    
                    // Get item details for logging
                    $itemQuery = "SELECT item_name FROM ecoshop_itemstbl WHERE item_id = ?";
                    $stmt = $connection->prepare($itemQuery);
                    $stmt->bind_param("i", $item_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $itemData = $result->fetch_assoc();
                    $itemName = $itemData['item_name'] ?? 'Unknown Item';
                    
                    // Mark item as available
                    $updateQuery = "UPDATE ecoshop_itemstbl SET is_available = 1 WHERE item_id = ?";
                    $stmt = $connection->prepare($updateQuery);
                    $stmt->bind_param("i", $item_id);
                    
                    if ($stmt->execute()) {
                        logEcoShopActivity($connection, $_SESSION['user_id'], 'item_available', $item_id, null, "Made item available: $itemName");
                        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Item marked as available successfully!'];
                    } else {
                        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'Error updating item: ' . $connection->error];
                    }
                    break;
            }
        }
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Function to log eco shop activities
    function logEcoShopActivity($connection, $admin_id, $activity_type, $item_id = null, $transaction_id = null, $details = '') {
        try {
            $query = "INSERT INTO ecoshop_activity_logs (admin_id, activity_type, item_id, transaction_id, details) VALUES (?, ?, ?, ?, ?)";
            $stmt = $connection->prepare($query);
            $stmt->bind_param("issis", $admin_id, $activity_type, $item_id, $transaction_id, $details);
            return $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // Log the error or handle it gracefully - don't break the main functionality
            error_log("Eco shop activity logging failed: " . $e->getMessage());
            return false;
        }
    }

    // Get flash message
    $flashMessage = '';
    $flashType = '';
    if (isset($_SESSION['flash_message'])) {
        $flashMessage = $_SESSION['flash_message']['message'];
        $flashType = $_SESSION['flash_message']['type'];
        unset($_SESSION['flash_message']);
    }

    // Fetch eco shop items
    $itemsQuery = "SELECT * FROM ecoshop_itemstbl ORDER BY category, points_required";
    $itemsResult = $connection->query($itemsQuery);

    // Fetch leaderboard data
    $topUsersQuery = "SELECT fullname, eco_points, profile_thumbnail FROM accountstbl ORDER BY eco_points DESC LIMIT 10";
    $topUsersResult = $connection->query($topUsersQuery);

    // Fetch top barangays by points
    $topBarangaysPointsQuery = "SELECT barangay, SUM(eco_points) as total_points, COUNT(*) as contributors FROM accountstbl WHERE barangay IS NOT NULL AND barangay != '' GROUP BY barangay ORDER BY total_points DESC LIMIT 10";
    $topBarangaysPointsResult = $connection->query($topBarangaysPointsQuery);

    // Fetch top barangays by events
    $topBarangaysEventsQuery = "SELECT barangay, COUNT(*) as event_count FROM eventstbl WHERE program_type != 'Announcement' AND barangay IS NOT NULL AND barangay != '' GROUP BY barangay ORDER BY event_count DESC LIMIT 10";
    $topBarangaysEventsResult = $connection->query($topBarangaysEventsQuery);

    // Fetch top municipalities by points
    $topMunicipalitiesPointsQuery = "SELECT city_municipality, SUM(eco_points) as total_points, COUNT(*) as contributors FROM accountstbl WHERE city_municipality IS NOT NULL AND city_municipality != '' GROUP BY city_municipality ORDER BY total_points DESC LIMIT 10";
    $topMunicipalitiesPointsResult = $connection->query($topMunicipalitiesPointsQuery);

    // Fetch top municipalities by events
    $topMunicipalitiesEventsQuery = "SELECT city_municipality, COUNT(*) as event_count FROM eventstbl WHERE program_type != 'Announcement' AND city_municipality IS NOT NULL AND city_municipality != '' GROUP BY city_municipality ORDER BY event_count DESC LIMIT 10";
    $topMunicipalitiesEventsResult = $connection->query($topMunicipalitiesEventsQuery);

    // Fetch top organizations by points
    $topOrganizationsPointsQuery = "SELECT organization, SUM(eco_points) as total_points, COUNT(*) as members FROM accountstbl WHERE organization IS NOT NULL AND organization != '' AND organization != 'N/A' GROUP BY organization ORDER BY total_points DESC LIMIT 10";
    $topOrganizationsPointsResult = $connection->query($topOrganizationsPointsQuery);

    // Fetch top organizations by events
    $topOrganizationsEventsQuery = "SELECT organization, COUNT(*) as event_count FROM eventstbl WHERE program_type != 'Announcement' AND organization IS NOT NULL AND organization != '' AND organization != 'N/A' GROUP BY organization ORDER BY event_count DESC LIMIT 10";
    $topOrganizationsEventsResult = $connection->query($topOrganizationsEventsQuery);

    // Fetch current active reward cycle
    $currentCycleQuery = "SELECT * FROM reward_cycles WHERE status = 'active' ORDER BY created_at DESC LIMIT 1";
    $currentCycleResult = $connection->query($currentCycleQuery);
    $currentCycle = $currentCycleResult ? $currentCycleResult->fetch_assoc() : null;

    // Fetch eco shop statistics for admin monitoring
    $shopStatsQuery = "SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as available_items,
        SUM(CASE WHEN stock_quantity IS NOT NULL THEN stock_quantity ELSE 0 END) as total_stock,
        AVG(points_required) as avg_points_required
        FROM ecoshop_itemstbl";
    $shopStatsResult = $connection->query($shopStatsQuery);
    $shopStats = $shopStatsResult->fetch_assoc();

    // Fetch recent eco shop activity logs
    $activityLogsQuery = "SELECT 
        l.activity_type, l.details, l.created_at,
        a.admin_name as admin_name,
        i.item_name,
        t.transaction_id
        FROM ecoshop_activity_logs l
        LEFT JOIN adminaccountstbl a ON l.admin_id = a.admin_id
        LEFT JOIN ecoshop_itemstbl i ON l.item_id = i.item_id  
        LEFT JOIN ecoshop_transactions t ON l.transaction_id = t.transaction_id
        ORDER BY l.created_at DESC LIMIT 20";
    $activityLogsResult = $connection->query($activityLogsQuery);

    // Fetch pending redeem transactions with multi-item support
    $pendingTransactionsQuery = "SELECT 
        t.transaction_id, t.reference_number, t.points_used, t.transaction_date,
        u.fullname as user_name, u.eco_points as user_current_points,
        GROUP_CONCAT(CONCAT(i2.item_name, ' (x', ti.quantity, ')') SEPARATOR ', ') as items_list,
        SUM(ti.quantity) as total_quantity,
        COUNT(ti.transaction_item_id) as items_count
        FROM ecoshop_transactions t
        JOIN accountstbl u ON t.user_id = u.account_id
        JOIN ecoshop_transaction_items ti ON t.transaction_id = ti.transaction_id
        JOIN ecoshop_itemstbl i2 ON ti.item_id = i2.item_id
        WHERE t.status = 'pending'
        GROUP BY t.transaction_id, t.reference_number, t.points_used, t.transaction_date, u.fullname, u.eco_points
        ORDER BY t.transaction_date ASC";
    $pendingTransactionsResult = $connection->query($pendingTransactionsQuery);

    // Fetch approved transactions (ready for claiming) with multi-item support
    $approvedTransactionsQuery = "SELECT 
        t.transaction_id, t.reference_number, t.points_used, t.transaction_date, t.approval_date,
        u.fullname as user_name, u.personal_email,
        GROUP_CONCAT(CONCAT(i2.item_name, ' (x', ti.quantity, ')') SEPARATOR ', ') as items_list,
        SUM(ti.quantity) as total_quantity,
        COUNT(ti.transaction_item_id) as items_count
        FROM ecoshop_transactions t
        JOIN accountstbl u ON t.user_id = u.account_id
        JOIN ecoshop_transaction_items ti ON t.transaction_id = ti.transaction_id
        JOIN ecoshop_itemstbl i2 ON ti.item_id = i2.item_id
        WHERE t.status = 'approved'
        GROUP BY t.transaction_id, t.reference_number, t.points_used, t.transaction_date, t.approval_date, u.fullname, u.personal_email
        ORDER BY t.approval_date DESC";
    $approvedTransactionsResult = $connection->query($approvedTransactionsQuery);

    // Fetch low stock items
    $lowStockQuery = "SELECT item_name, stock_quantity, points_required 
        FROM ecoshop_itemstbl 
        WHERE stock_quantity IS NOT NULL AND stock_quantity <= 5 AND is_available = 1
        ORDER BY stock_quantity ASC";
    $lowStockResult = $connection->query($lowStockQuery);



    // Fetch categories
    $categoriesQuery = "SELECT DISTINCT category FROM ecoshop_itemstbl ORDER BY category";
    $categoriesResult = $connection->query($categoriesQuery);
    $categories = [];
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row['category'];
    }

    // Fetch user statistics
    $userStatsQuery = "SELECT 
        COUNT(*) as total_users,
        AVG(eco_points) as avg_user_points,
        SUM(eco_points) as total_points_in_system
        FROM accountstbl";
    $userStatsResult = $connection->query($userStatsQuery);
    $userStats = $userStatsResult->fetch_assoc();

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Lobby</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminleaderboards.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="adminusers.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
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
    <main>
<?php 
    $statusType = '';
    $statusMsg = '';
    //<!-- Flash Message Display -->
    if(!empty($_SESSION['response'])): ?>
    <div class="flash-container">
        <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
            <?= $_SESSION['response']['msg'] ?>
        </div>
    </div>
    <?php 
    unset($_SESSION['response']); 
    endif; 
    ?>
    <!-- Display status message -->
    <?php if(!empty($statusMsg)){ ?>
    <div class="col-xs-12">
        <div class="alert alert-<?php echo $status; ?>"><?php echo $statusMsg; ?></div>
    </div>
    <?php } ?>
    <div class="profile-details close" id="profile-details">
            <div class ="details-box">
            <h2><?php 
            if(isset($_SESSION["name"])){
                $loggeduser = $_SESSION["name"];
                echo $loggeduser; 
            }else{
                echo "";
            }
            ?></h2>
            <p><?php
             if(isset($_SESSION["email"])){
                $email = $_SESSION["email"];
                echo $email;
            }else{
                echo "";
            }
             ?></p>
            <p><?php
            if(isset($_SESSION["accessrole"])){
                $accessrole = $_SESSION["accessrole"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            <p><?php
            if(isset($_SESSION["organization"])){
                $accessrole = $_SESSION["organization"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
        </div>
        <!-- Leaderboards Container Section -->
        <div class="leaderboards-container">
            <!-- Navigation Bar -->
            <div class="admin-nav-section">
                <div class="nav-header">
                    <h1><i class="fas fa-trophy"></i> Leaderboards & Eco Shop Management</h1>
                    <div class="nav-buttons">
                        <a href="admin_badges.php" class="nav-btn badges-btn">
                            <i class="fas fa-medal"></i> Badge Management
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($flashMessage): ?>
            <div class="flash-message flash-<?php echo $flashType; ?>" id="flashMessage">
                <i class="fas <?php echo $flashType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($flashMessage); ?>
                <button type="button" class="close-btn" onclick="closeFlashMessage()">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Statistics Overview -->
            <div class="stats-overview">
                <div class="stat-card">
                    <div class="stat-icon user-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($userStats['total_users']); ?></h3>
                        <p>Total Users</p>
                        <small>Avg: <?php echo number_format($userStats['avg_user_points'], 1); ?> points</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon shop-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($shopStats['total_items']); ?></h3>
                        <p>Shop Items</p>
                        <small><?php echo $shopStats['available_items']; ?> available</small>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon points-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($userStats['total_points_in_system']); ?></h3>
                        <p>Total Eco Points</p>
                        <small>In circulation</small>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="admin-tabs">
                <button class="admin-tab-btn active" onclick="openAdminTab(event, 'leaderboards-tab')">
                    <i class="fas fa-trophy"></i> Leaderboards
                </button>
                <button class="admin-tab-btn" onclick="openAdminTab(event, 'ecoshop-tab')">
                    <i class="fas fa-store"></i> Eco Shop Management
                </button>
                <button class="admin-tab-btn" onclick="openAdminTab(event, 'transactions-tab')">
                    <i class="fas fa-receipt"></i> Redeem Transactions
                    <?php if ($pendingTransactionsResult->num_rows > 0): ?>
                        <span class="notification-badge"><?php echo $pendingTransactionsResult->num_rows; ?></span>
                    <?php endif; ?>
                </button>
                <button class="admin-tab-btn" onclick="openAdminTab(event, 'add-item-tab')">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>

            <!-- Leaderboards Tab -->
            <div id="leaderboards-tab" class="admin-tab-content active">
                <div class="leaderboards-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem; gap: 1rem;">
                        <h2 style="margin: 0;"><i class="fas fa-crown"></i> Top Eco Warriors</h2>
                        <div class="left-div" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <!-- Always show Manage Cycles button -->
                        <a href="admin_rewards_manager.php" class="nav-btn rewards-btn" style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 0.8rem 1.5rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(76, 175, 80, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(76, 175, 80, 0.3)'">
                            <i class='bx bx-calendar-star' style="font-size: 1.5rem;"></i>
                            <div>
                                <div style="font-weight: bold; font-size: 1rem;">Manage Reward Cycles</div>
                                <div style="font-size: 0.85rem; opacity: 0.9;">Create and manage eco reward cycles</div>
                            </div>
                        </a>
                        
                        <!-- Conditional cycle status -->
                        <?php if ($currentCycle): ?>
                        <div style="background: linear-gradient(135deg, #4CAF50, #45a049); color: white; padding: 0.8rem 1.5rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-trophy' style="font-size: 1.5rem;"></i>
                                <div>
                                    <div style="font-weight: bold; font-size: 1.1rem;"><?= htmlspecialchars($currentCycle['cycle_name']) ?></div>
                                    <div style="font-size: 0.85rem; opacity: 0.9;">
                                        <?= date('M j', strtotime($currentCycle['start_date'])) ?> - <?= date('M j, Y', strtotime($currentCycle['end_date'])) ?>
                                    </div>
                                </div>
                                <span style="background: rgba(255,255,255,0.3); padding: 0.3rem 0.8rem; border-radius: 15px; font-size: 0.75rem; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">Active</span>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; padding: 0.8rem 1.5rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class='bx bx-calendar-x' style="font-size: 1.5rem;"></i>
                                <div>
                                    <div style="font-weight: bold; font-size: 1rem;">No Active Cycle</div>
                                    <div style="font-size: 0.85rem; opacity: 0.9;">Default rankings displayed</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Leaderboard Filters -->
                    <div class="leaderboard-filters">
                        <button class="filter-btn active" data-filter="individual">Individuals</button>
                        <button class="filter-btn" data-filter="barangay">Barangays</button>
                        <button class="filter-btn" data-filter="municipality">Municipalities</button>
                        <button class="filter-btn" data-filter="organization">Organizations</button>
                    </div>

                    <!-- Individual Leaderboards -->
                    <div class="leaderboards-grid" id="individual-leaderboards">
                        <div class="leaderboard-table">
                            <h3>Top Individual Contributors</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>User</th>
                                        <th>Eco Points</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $rank = 1;
                                    $topUsersResult->data_seek(0); // Reset result pointer
                                    while ($user = $topUsersResult->fetch_assoc()): 
                                        $statusClass = '';
                                        $statusText = '';
                                        if ($user['eco_points'] >= 5000) {
                                            $statusClass = 'champion';
                                            $statusText = 'Eco Champion';
                                        } elseif ($user['eco_points'] >= 3000) {
                                            $statusClass = 'hero';
                                            $statusText = 'Eco Hero';
                                        } elseif ($user['eco_points'] >= 1000) {
                                            $statusClass = 'warrior';
                                            $statusText = 'Eco Warrior';
                                        } else {
                                            $statusClass = 'beginner';
                                            $statusText = 'Eco Beginner';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="rank-badge rank-<?php echo $rank; ?>">
                                                <?php if ($rank <= 3): ?>
                                                    <i class="fas fa-trophy"></i>
                                                <?php endif; ?>
                                                #<?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <?php if (!empty($user['profile_thumbnail'])): ?>
                                                    <img src="<?php echo htmlspecialchars($user['profile_thumbnail']); ?>" alt="Profile" class="user-avatar">
                                                <?php else: ?>
                                                    <div class="user-avatar-placeholder">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="user-name"><?php echo htmlspecialchars($user['fullname']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="points-badge">
                                                <i class="fas fa-coins"></i>
                                                <?php echo number_format($user['eco_points']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php $rank++; endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Barangay Leaderboards -->
                    <div class="leaderboards-grid" id="barangay-leaderboards" style="display: none;">
                        <div class="leaderboard-table" style="grid-column: 1 / -1;">
                            <h3>Top Barangays by Eco Points</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Barangay</th>
                                        <th>Total Points</th>
                                        <th>Contributors</th>
                                    </tr>
                                </thead>
                                <tbody id="barangay-points-tbody">
                                    <!-- Filled by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Municipality Leaderboards -->
                    <div class="leaderboards-grid" id="municipality-leaderboards" style="display: none;">
                        <div class="leaderboard-table" style="grid-column: 1 / -1;">
                            <h3>Top Municipalities by Eco Points</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Municipality</th>
                                        <th>Total Points</th>
                                        <th>Contributors</th>
                                    </tr>
                                </thead>
                                <tbody id="municipality-points-tbody">
                                    <!-- Filled by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Organization Leaderboards -->
                    <div class="leaderboards-grid" id="organization-leaderboards" style="display: none;">
                        <div class="leaderboard-table" style="grid-column: 1 / -1;">
                            <h3>Top Organizations by Eco Points</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Organization</th>
                                        <th>Total Points</th>
                                        <th>Members</th>
                                    </tr>
                                </thead>
                                <tbody id="organization-points-tbody">
                                    <!-- Filled by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Eco Shop Management Tab -->
            <div id="ecoshop-tab" class="admin-tab-content">
                <div class="shop-management-section">
                    <h2><i class="fas fa-store"></i> Eco Shop Items Management</h2>
                    
                    <!-- Category Filter -->
                    <div class="shop-filters">
                        <div class="filter-group">
                            <label for="category-filter">Filter by Category:</label>
                            <select id="category-filter" onchange="filterItemsByCategory()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>">
                                        <?php echo ucfirst(htmlspecialchars($category)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-stats">
                            <span id="items-count"><?php echo $itemsResult->num_rows; ?></span> items total
                        </div>
                    </div>
                    
                    <div class="items-grid" id="items-grid">
                        <?php
                        $itemsResult->data_seek(0); // Reset result pointer
                        while ($item = $itemsResult->fetch_assoc()): 
                        ?>
                        <div class="item-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                            <div class="item-header">
                                <div class="item-image">
                                    <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" onclick="showImagePreview('<?php echo htmlspecialchars($item['image_path']); ?>', '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                    <?php else: ?>
                                        <span class="item-emoji">🎁</span>
                                    <?php endif; ?>
                                </div>
                                <div class="item-status"><?php if ($item['is_available']): ?>
                                        <span class="status-available">Available</span>
                                    <?php else: ?>
                                        <span class="status-unavailable">Unavailable</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h3 class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                            <p class="item-description"><?php echo htmlspecialchars($item['item_description']); ?></p>
                            
                            <div class="item-details">
                                <div class="item-points">
                                    <i class="fas fa-coins"></i>
                                    <?php echo number_format($item['points_required']); ?> points
                                </div>
                                <div class="item-category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo ucfirst($item['category']); ?>
                                </div>
                                <?php if ($item['stock_quantity'] !== null): ?>
                                <div class="item-stock">
                                    <i class="fas fa-box"></i>
                                    Stock: <?php echo $item['stock_quantity']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-actions">
                                <button class="btn-edit" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($item['is_available']): ?>
                                    <button class="btn-delete" onclick="deleteItem(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                        <i class="fas fa-eye-slash"></i> Make Unavailable
                                    </button>
                                <?php else: ?>
                                    <button class="btn-edit" onclick="makeAvailable(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')" style="background: var(--placeholder-text-clr);">
                                        <i class="fas fa-eye"></i> Make Available
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Eco Shop Activity Monitoring Section -->
                    <div class="shop-monitoring-section" style="margin-top: 3rem;">
                        <h2><i class="fas fa-chart-line"></i> Eco Shop Activity Monitoring</h2>
                        
                        <!-- Shop Statistics Overview -->
                        <div class="monitoring-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                            <div class="stat-card">
                                <div class="stat-icon shop-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $shopStats['total_items']; ?></h3>
                                    <p>Total Items</p>
                                    <small><?php echo $shopStats['available_items']; ?> available</small>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon points-icon">
                                    <i class="fas fa-warehouse"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo number_format($shopStats['total_stock']); ?></h3>
                                    <p>Total Stock</p>
                                    <small>Items in inventory</small>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon user-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $lowStockResult->num_rows; ?></h3>
                                    <p>Low Stock Items</p>
                                    <small>≤ 5 items remaining</small>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity Logs -->
                        <div class="activity-logs-section" style="margin-bottom: 2rem;">
                            <h3><i class="fas fa-history"></i> Recent Activity Logs</h3>
                            <div class="leaderboard-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Admin</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($activityLogsResult->num_rows > 0): ?>
                                            <?php while ($log = $activityLogsResult->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                                    <td>
                                                        <span class="status-badge <?php 
                                                            echo match($log['activity_type']) {
                                                                'item_added' => 'champion',
                                                                'item_edited' => 'hero', 
                                                                'item_deleted' => 'beginner',
                                                                'item_unavailable' => 'beginner',
                                                                'item_available' => 'champion',
                                                                'stock_updated' => 'warrior',
                                                                'transaction_approved' => 'champion',
                                                                'transaction_rejected' => 'beginner',
                                                                default => 'hero'
                                                            };
                                                        ?>">
                                                            <?php echo ucwords(str_replace('_', ' ', $log['activity_type'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center; color: #666;">No activity logs found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Low Stock Alerts -->
                        <?php if ($lowStockResult->num_rows > 0): ?>
                        <div class="low-stock-section">
                            <h3><i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i> Low Stock Alerts</h3>
                            <div class="leaderboard-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th>Stock Remaining</th>
                                            <th>Points Required</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($item = $lowStockResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                <td>
                                                    <span class="points-badge" style="background: <?php echo $item['stock_quantity'] <= 2 ? '#e74c3c' : '#f39c12'; ?>;">
                                                        <?php echo $item['stock_quantity']; ?> left
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($item['points_required']); ?> points</td>
                                                <td>
                                                    <span class="status-badge <?php echo $item['stock_quantity'] <= 2 ? 'beginner' : 'warrior'; ?>">
                                                        <?php echo $item['stock_quantity'] <= 2 ? 'Critical' : 'Low'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Redeem Transactions Tab -->
            <div id="transactions-tab" class="admin-tab-content">
                <div class="transactions-section">
                    <h2><i class="fas fa-receipt"></i> Redeem Transactions Management</h2>
                    
                    <!-- Pending Transactions -->
                    <div class="pending-transactions">
                        <h3><i class="fas fa-clock"></i> Pending Redemption Requests</h3>
                        <?php if ($pendingTransactionsResult->num_rows > 0): ?>
                            <div class="leaderboard-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Item</th>
                                            <th>Points Used</th>
                                            <th>Quantity</th>
                                            <th>User Balance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($transaction = $pendingTransactionsResult->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                                                <td>
                                                    <div class="user-info">
                                                        <span class="user-name"><?php echo htmlspecialchars($transaction['user_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['items_list']); ?></div>
                                                    <?php if ($transaction['items_count'] > 1): ?>
                                                        <small style="color: #666; font-style: italic;"><?php echo $transaction['items_count']; ?> different items</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="points-badge">
                                                        <i class="fas fa-coins"></i>
                                                        <?php echo number_format($transaction['points_used']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $transaction['total_quantity']; ?></td>
                                                <td>
                                                    <span class="points-badge" style="background: <?php echo $transaction['user_current_points'] >= $transaction['points_used'] ? 'var(--placeholder-text-clr)' : '#e74c3c'; ?>;">
                                                        <?php echo number_format($transaction['user_current_points']); ?> points
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn-edit" onclick="approveTransaction(<?php echo $transaction['transaction_id']; ?>)" style="background: var(--placeholder-text-clr); margin-right: 0.5rem;">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn-delete" onclick="rejectTransaction(<?php echo $transaction['transaction_id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem; color: var(--placeholder-text-clr);"></i>
                                <p>No pending redemption requests</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Transaction Guidelines -->
                    <div class="transaction-guidelines" style="margin-top: 2rem; background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                        <h3><i class="fas fa-info-circle"></i> Transaction Management Guidelines</h3>
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <li><strong>Approval Process:</strong> Verify user information before approving redemption requests</li>
                            <li><strong>Stock Management:</strong> Ensure adequate stock before approval</li>
                            <li><strong>Point Validation:</strong> Users must have sufficient eco points for redemption</li>
                            <li><strong>Record Keeping:</strong> All transactions are logged for transparency</li>
                            <li><strong>User Notification:</strong> Users will be notified of transaction status changes</li>
                        </ul>
                    </div>

                    <!-- Approved Transactions (Ready for Claiming) -->
                    <div class="approved-transactions" style="margin-top: 2rem;">
                        <h3><i class="fas fa-clipboard-check"></i> Approved Transactions (Ready for Claiming)</h3>
                        <?php if ($approvedTransactionsResult->num_rows > 0): ?>
                            <div class="table-responsive" style="overflow-x: auto; margin-top: 1rem;">
                                <table class="admin-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                    <thead>
                                        <tr style="background: #2E8B57; color: white;">
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">Reference No.</th>
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">User</th>
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">Email</th>
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">Item</th>
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">Points</th>
                                            <th style="padding: 12px; text-align: left; font-weight: 600;">Approved Date</th>
                                            <th style="padding: 12px; text-align: center; font-weight: 600;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($transaction = $approvedTransactionsResult->fetch_assoc()): ?>
                                            <tr style="border-bottom: 1px solid #eee; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor=''">
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <strong style="color: #2E8B57; font-family: monospace;"><?php echo htmlspecialchars($transaction['reference_number'] ?? 'REF-'.date('Ymd', strtotime($transaction['transaction_date'])).'-'.str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT)); ?></strong>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['user_name']); ?></div>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <small style="color: #666; word-break: break-all;"><?php echo htmlspecialchars($transaction['personal_email']); ?></small>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle;">
                                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($transaction['items_list']); ?></div>
                                                    <?php if ($transaction['items_count'] > 1): ?>
                                                        <small style="color: #666; font-style: italic; display: block; margin-top: 4px;"><?php echo $transaction['items_count']; ?> different items (<?php echo $transaction['total_quantity']; ?> total)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle; min-width: 100px;">
                                                    <span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-block; white-space: nowrap;">
                                                        <i class="fas fa-coins"></i> <?php echo number_format($transaction['points_used']); ?>
                                                    </span>
                                                </td>
                                                <td style="padding: 12px; vertical-align: middle; min-width: 110px;">
                                                    <small style="color: #666; white-space: nowrap;"><?php echo date('M j, Y', strtotime($transaction['approval_date'])); ?><br>
                                                    <?php echo date('g:i A', strtotime($transaction['approval_date'])); ?></small>
                                                </td>
                                                <td style="padding: 12px; text-align: center; vertical-align: middle; min-width: 180px;">
                                                    <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: nowrap;">
                                                        <button class="btn-edit" onclick="markAsClaimed(<?php echo $transaction['transaction_id']; ?>)" 
                                                                style="background: #17a2b8; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 11px; white-space: nowrap; min-width: 80px;" 
                                                                title="Mark as Claimed">
                                                            <i class="fas fa-check-double"></i> Claimed
                                                        </button>
                                                        <button class="btn-view" onclick="viewReceipt('<?php echo htmlspecialchars($transaction['reference_number'] ?? 'REF-'.date('Ymd', strtotime($transaction['transaction_date'])).'-'.str_pad($transaction['transaction_id'], 6, '0', STR_PAD_LEFT)); ?>')" 
                                                                style="background: #6c757d; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 11px; white-space: nowrap; min-width: 80px;" 
                                                                title="View Receipt">
                                                            <i class="fas fa-file-pdf"></i> Receipt
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: #666;">
                                <i class="fas fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; color: #17a2b8;"></i>
                                <p>No approved transactions waiting for claim</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add New Item Tab -->
            <div id="add-item-tab" class="admin-tab-content">
                <div class="add-item-container">
                    <div class="add-item-section">
                        <h2><i class="fas fa-plus"></i> Add New Eco Shop Item</h2>
                        
                        <form method="POST" class="item-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_item">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="item_name">Item Name</label>
                                    <input type="text" id="item_name" name="item_name" required maxlength="255" onkeyup="updatePreview()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="points_required">Points Required</label>
                                    <input type="number" id="points_required" name="points_required" required min="1" max="99999" onchange="updatePreview()">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="item_description">Description</label>
                                <textarea id="item_description" name="item_description" rows="3" placeholder="Describe the item and its benefits..." onkeyup="updatePreview()"></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select id="category" name="category" required onchange="updatePreview()">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo ucfirst($cat); ?></option>
                                        <?php endforeach; ?>
                                        <option value="new_category">Add New Category...</option>
                                    </select>
                                    <input type="text" id="new_category_input" name="new_category" style="display: none;" placeholder="Enter new category name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_quantity">Stock Quantity (Optional)</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" placeholder="Leave empty for unlimited stock">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="item_image">Item Image (Optional)</label>
                                <input type="file" id="item_image" name="item_image" accept="image/*" onchange="previewImage(this, 'add_preview_image')">
                                <small class="form-hint">Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF, WebP</small>
                                <div class="image-preview" id="add_image_preview_container" style="display: none;">
                                    <img id="add_preview_image" src="" alt="Preview">
                                    <button type="button" onclick="removeImagePreview('add')" class="remove-image-btn">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-plus"></i> Add Item
                            </button>
                        </form>
                    </div>
                    
                    <!-- Preview Section -->
                    <div class="preview-section">
                        <h3><i class="fas fa-eye"></i> Shop Item Preview</h3>
                        <div class="shop-item-preview">
                            <div class="preview-card">
                                <div class="preview-image" id="preview_image_container">
                                    <span class="preview-emoji">🎁</span>
                                    <img id="preview_image" src="" alt="Preview" style="display: none;">
                                </div>
                                <div class="preview-content">
                                    <h4 id="preview_title">Item Name</h4>
                                    <p id="preview_description">Item description will appear here...</p>
                                    <div class="preview-details">
                                        <div class="preview-points">
                                            <i class="fas fa-coins"></i>
                                            <span id="preview_points">100</span> points
                                        </div>
                                        <div class="preview-category">
                                            <i class="fas fa-tag"></i>
                                            <span id="preview_category">Category</span>
                                        </div>
                                    </div>
                                    <button class="preview-redeem-btn" disabled>Redeem</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Item Modal -->
            <div id="editItemModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-edit"></i> Edit Item</h3>
                        <span class="close" onclick="closeEditModal()">&times;</span>
                    </div>
                        <form method="POST" class="item-form" enctype="multipart/form-data">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="update_item">
                                <input type="hidden" id="edit_item_id" name="item_id">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="edit_item_name">Item Name</label>
                                        <input type="text" id="edit_item_name" name="item_name" required maxlength="255">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_points_required">Points Required</label>
                                        <input type="number" id="edit_points_required" name="points_required" required min="1" max="99999">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_item_description">Description</label>
                                    <textarea id="edit_item_description" name="item_description" rows="3"></textarea>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="edit_category">Category</label>
                                        <select id="edit_category" name="category" required>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo ucfirst($cat); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="edit_stock_quantity">Stock Quantity</label>
                                        
                                        <!-- Current Stock Display -->
                                        <div class="stock-info-container" id="stock_info_container" style="display: none;">
                                            <div class="previous-stock-display">
                                                <i class="fas fa-box"></i>
                                                <span>Current Stock: <strong id="previous_stock_value">0</strong></span>
                                            </div>
                                            <div class="low-stock-warning" id="low_stock_warning" style="display: none;">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>Low Stock Alert! Only <strong id="low_stock_count">0</strong> items remaining</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Stock Input with Controls -->
                                        <div class="stock-input-controls">
                                            <button type="button" class="stock-btn stock-btn-decrement" onclick="adjustStock(-1)" title="Decrease by 1">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" id="edit_stock_quantity" name="stock_quantity" min="0" value="0">
                                            <button type="button" class="stock-btn stock-btn-increment" onclick="adjustStock(1)" title="Increase by 1">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Quick Increment Buttons -->
                                        <div class="quick-stock-controls">
                                            <button type="button" class="quick-stock-btn" onclick="adjustStock(5)">
                                                <i class="fas fa-plus"></i> 5
                                            </button>
                                            <button type="button" class="quick-stock-btn" onclick="adjustStock(10)">
                                                <i class="fas fa-plus"></i> 10
                                            </button>
                                            <button type="button" class="quick-stock-btn" onclick="adjustStock(-5)">
                                                <i class="fas fa-minus"></i> 5
                                            </button>
                                            <button type="button" class="quick-stock-btn" onclick="adjustStock(-10)">
                                                <i class="fas fa-minus"></i> 10
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Current Image Display -->
                                <div class="form-group">
                                    <label>Current Image</label>
                                    <div class="current-image-container" id="edit_current_image_container">
                                        <img id="edit_current_image" src="" alt="Current image" style="display: none;">
                                        <span id="edit_no_image" class="no-image-text">No image uploaded</span>
                                        <button type="button" id="edit_remove_image_btn" onclick="removeCurrentImage()" style="display: none;" class="remove-current-image-btn">
                                            <i class="fas fa-trash"></i> Remove Image
                                        </button>
                                    </div>
                                    <input type="hidden" id="remove_image_flag" name="remove_image" value="0">
                                </div>
                                
                                <!-- New Image Upload -->
                                <div class="form-group">
                                    <label for="edit_item_image">Upload New Image (Optional)</label>
                                    <input type="file" id="edit_item_image" name="edit_item_image" accept="image/*" onchange="previewImage(this, 'edit_preview_image')">
                                    <small class="form-hint">Maximum file size: 5MB. Supported formats: JPEG, PNG, GIF, WebP</small>
                                    <div class="image-preview" id="edit_image_preview_container" style="display: none;">
                                        <img id="edit_preview_image" src="" alt="New image preview">
                                        <button type="button" onclick="removeImagePreview('edit')" class="remove-image-btn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_is_available" name="is_available" checked>
                                        Item Available
                                    </label>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                                <button type="submit" class="btn-submit">
                                    <i class="fas fa-save"></i> Update Item
                                </button>
                            </div>
                        </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="deleteConfirmModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Make Item Unavailable</h3>
                        <span class="close" onclick="closeDeleteModal()">&times;</span>
                    </div>
                    
                    <div class="modal-body">
                        <p>Are you sure you want to make <strong id="deleteItemName"></strong> unavailable?</p>
                        <p class="warning-text">This item will be hidden from the shop but can be made available again later.</p>
                    </div>
                    
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" id="delete_item_id" name="item_id">
                        
                        <div class="modal-actions">
                            <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                            <button type="submit" class="btn-delete">
                                <i class="fas fa-eye-slash"></i> Make Unavailable
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Image Preview Modal -->
            <div id="imagePreviewModal" class="modal" style="display: none;">
                <div class="modal-content image-modal-content">
                    <div class="modal-header">
                        <h3 id="imagePreviewTitle">Image Preview</h3>
                        <span class="close" onclick="closeImagePreview()">&times;</span>
                    </div>
                    <div class="modal-body image-modal-body">
                        <img id="imagePreviewImg" src="" alt="Image preview">
                    </div>
                </div>
            </div>
        </div>
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

    <!-- Rejection Reason Modal -->
    <div id="rejectionReasonModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px; margin: 5% auto;">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Reject Redemption Request</h3>
                <button type="button" class="close" onclick="closeRejectionModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Please provide a reason for rejecting this redemption request:</p>
                <textarea id="rejectionReason" placeholder="Enter rejection reason (optional)..." 
                         style="width: 100%; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;"></textarea>
                <div style="margin-top: 15px;">
                    <small style="color: #666;">
                        <i class="fas fa-info-circle"></i> 
                        The user will be notified via email with this reason.
                    </small>
                </div>
            </div>
            <div class="modal-footer" style="text-align: right; padding: 15px; border-top: 1px solid #eee;">
                <button type="button" class="btn-secondary" onclick="closeRejectionModal()" 
                        style="margin-right: 10px; padding: 8px 16px; border: 1px solid #ccc; background: #f8f9fa; border-radius: 4px;">
                    Cancel
                </button>
                <button type="button" class="btn-delete" onclick="confirmRejection()" 
                        style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px;">
                    <i class="fas fa-times"></i> Reject Request
                </button>
            </div>
        </div>
    </div>

    <!-- include script for leaderboards here -->
<script>
// Transaction Management Functions
function approveTransaction(transactionId) {
    if (confirm('Are you sure you want to approve this redemption request?')) {
        fetch('eco_shop_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=approve_transaction&transaction_id=${transactionId}`
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get text first to see what we're getting
        })
        .then(text => {
            console.log('Response text:', text);
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('JSON parsing error:', e);
                console.error('Response was:', text);
                throw new Error('Invalid JSON response from server');
            }
            return data;
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                // Set flash message via PHP session
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'success',
                        msg: data.message
                    })
                }).then(() => {
                    location.reload();
                });
            } else {
                // Set flash message via PHP session
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'error',
                        msg: data.message
                    })
                }).then(() => {
                    location.reload();
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            fetch('set_flash_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'error',
                    msg: 'An error occurred while processing the request.'
                })
            }).then(() => {
                location.reload();
            });
        });
    }
}

function rejectTransaction(transactionId) {
    // Store transaction ID globally for use in confirmation
    window.currentTransactionId = transactionId;
    
    // Show custom modal instead of prompt
    document.getElementById('rejectionReasonModal').style.display = 'block';
    document.getElementById('rejectionReason').focus();
}

function closeRejectionModal() {
    document.getElementById('rejectionReasonModal').style.display = 'none';
    document.getElementById('rejectionReason').value = '';
    window.currentTransactionId = null;
}

function confirmRejection() {
    const reason = document.getElementById('rejectionReason').value.trim();
    const transactionId = window.currentTransactionId;
    
    if (!transactionId) {
        alert('Error: No transaction selected');
        return;
    }
    
    // Close modal first
    closeRejectionModal();
    
    // Send rejection request
    fetch('eco_shop_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=reject_transaction&transaction_id=${transactionId}&reason=${encodeURIComponent(reason)}`
    })
    .then(response => {
        console.log('Rejection response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Rejection response text:', text);
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('JSON parsing error:', e);
            console.error('Response was:', text);
            throw new Error('Invalid JSON response from server');
        }
        return data;
    })
    .then(data => {
        console.log('Rejection parsed data:', data);
        if (data.success) {
            // Set flash message via PHP session
            fetch('set_flash_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'success',
                    msg: data.message
                })
            }).then(() => {
                location.reload();
            });
        } else {
            // Set flash message via PHP session
            fetch('set_flash_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'error',
                    msg: data.message
                })
            }).then(() => {
                location.reload();
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        fetch('set_flash_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                status: 'error',
                msg: 'An error occurred while processing the request.'
            })
        }).then(() => {
            location.reload();
        });
    });
}

// Mark transaction as claimed/completed
function markAsClaimed(transactionId) {
    if (confirm('Are you sure you want to mark this transaction as claimed? This action cannot be undone.')) {
        fetch('eco_shop_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_as_claimed&transaction_id=${transactionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set flash message via PHP session
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'success',
                        msg: data.message
                    })
                }).then(() => {
                    location.reload();
                });
            } else {
                // Set flash message via PHP session
                fetch('set_flash_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        status: 'error',
                        msg: data.message
                    })
                }).then(() => {
                    location.reload();
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            fetch('set_flash_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    status: 'error',
                    msg: 'An error occurred while marking transaction as claimed.'
                })
            }).then(() => {
                location.reload();
            });
        });
    }
}

// View receipt PDF
function viewReceipt(referenceNumber) {
    // Open receipt in new window using the enhanced v2 system
    window.open(`generate_receipt_pdf_v2.php?ref=${encodeURIComponent(referenceNumber)}`, '_blank');
}

// Tab functionality
function openAdminTab(evt, tabName) {
    var i, tabcontent, tablinks;
    
    // Hide all tab content
    tabcontent = document.getElementsByClassName("admin-tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].classList.remove("active");
    }
    
    // Remove active class from all tab buttons
    tablinks = document.getElementsByClassName("admin-tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("active");
    }
    
    // Show the selected tab content and mark the button as active
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

// Leaderboard filter functionality
function setupLeaderboardFilters() {
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
            
            // Show selected leaderboard
            const filter = this.getAttribute('data-filter');
            const leaderboardElement = document.getElementById(`${filter}-leaderboards`);
            leaderboardElement.style.display = 'grid'; // Changed to grid
        });
    });
}

// Populate leaderboard data
function populateLeaderboards() {
    // PHP data for leaderboards (Eco Points only)
    const barangaysPoints = <?= json_encode($topBarangaysPointsResult->fetch_all(MYSQLI_ASSOC)) ?>;
    const municipalitiesPoints = <?= json_encode($topMunicipalitiesPointsResult->fetch_all(MYSQLI_ASSOC)) ?>;
    const organizationsPoints = <?= json_encode($topOrganizationsPointsResult->fetch_all(MYSQLI_ASSOC)) ?>;

    // Populate Barangay Points
    populateTable('barangay-points-tbody', barangaysPoints, 'barangay', 'total_points', 'contributors');
    
    // Populate Municipality Points
    populateTable('municipality-points-tbody', municipalitiesPoints, 'city_municipality', 'total_points', 'contributors');
    
    // Populate Organization Points
    populateTable('organization-points-tbody', organizationsPoints, 'organization', 'total_points', 'members');
}

function populateTable(tableId, data, nameField, valueField, extraField) {
    const tbody = document.getElementById(tableId);
    tbody.innerHTML = '';
    
    data.forEach((item, index) => {
        const rank = index + 1;
        const row = document.createElement('tr');
        
        const extraValue = item[extraField] || 0;
        const valueDisplay = number_format(item[valueField]);
        
        row.innerHTML = `
            <td>
                <span class="rank-badge rank-${rank}">
                    ${rank <= 3 ? '<i class="fas fa-trophy"></i>' : ''}
                    #${rank}
                </span>
            </td>
            <td>
                <span class="entity-name">${item[nameField]}</span>
            </td>
            <td>
                <span class="points-badge">
                    <i class="fas fa-coins"></i>
                    ${valueDisplay}
                </span>
            </td>
            <td>
                <span class="extra-info">${extraValue}</span>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

function number_format(num) {
    return new Intl.NumberFormat().format(num);
}

// Improved flash message auto-hide function
function setupFlashMessageAutoHide() {
    // Use querySelector to find by class instead of ID for more reliability
    const flashMessage = document.querySelector('.flash-message');
    
    if (flashMessage) {
        console.log('Flash message found, will auto-hide in 5 seconds');
        
        // Clear any existing timeouts to prevent duplicates
        if (window.flashMessageTimeout) {
            clearTimeout(window.flashMessageTimeout);
        }
        
        window.flashMessageTimeout = setTimeout(function() {
            console.log('Hiding flash message');
            flashMessage.style.transition = 'all 0.5s ease';
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateY(-20px)';
            
            // Remove from DOM after animation completes
            setTimeout(function() {
                flashMessage.style.display = 'none';
                console.log('Flash message hidden');
            }, 500);
        }, 5000); // 5 seconds
    } else {
        console.log('No flash message found');
    }
}

// Also add manual close functionality
function closeFlashMessage() {
    const flashMessage = document.querySelector('.flash-message');
    if (flashMessage) {
        // Clear the auto-hide timeout
        if (window.flashMessageTimeout) {
            clearTimeout(window.flashMessageTimeout);
        }
        
        flashMessage.style.transition = 'all 0.3s ease';
        flashMessage.style.opacity = '0';
        flashMessage.style.transform = 'translateY(-20px)';
        
        setTimeout(function() {
            flashMessage.style.display = 'none';
        }, 300);
    }
}

// Update your DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    // Initialize other functionality first
    const categorySelect = document.getElementById('category');
    const newCategoryInput = document.getElementById('new_category_input');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            if (this.value === 'new_category') {
                newCategoryInput.style.display = 'block';
                newCategoryInput.required = true;
                newCategoryInput.focus();
            } else {
                newCategoryInput.style.display = 'none';
                newCategoryInput.required = false;
                newCategoryInput.value = '';
            }
        });
    }
    
    // Initialize leaderboard functionality
    setupLeaderboardFilters();
    populateLeaderboards();
    
    // Setup flash message auto-hide (call this last)
    setupFlashMessageAutoHide();
    
    // Also setup the close button event listeners
    const closeButtons = document.querySelectorAll('.close-btn');
    closeButtons.forEach(button => {
        button.addEventListener('click', closeFlashMessage);
    });
    
    // Start AJAX transaction monitoring
    startTransactionMonitoring();
});

// ============================================
// AJAX AUTO-REFRESH FOR NEW TRANSACTIONS
// ============================================
let lastTransactionId = 0;
let transactionCheckInterval = null;

function startTransactionMonitoring() {
    // Get highest current transaction ID
    const transactionCards = document.querySelectorAll('[data-transaction-id]');
    transactionCards.forEach(card => {
        const id = parseInt(card.getAttribute('data-transaction-id'));
        if (id > lastTransactionId) lastTransactionId = id;
    });
    
    // Start polling every 10 seconds
    transactionCheckInterval = setInterval(checkForNewTransactions, 10000);
    console.log('Transaction monitoring started, last ID:', lastTransactionId);
}

function checkForNewTransactions() {
    fetch(`check_new_transactions.php?last_id=${lastTransactionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.new_count > 0) {
                console.log(`${data.new_count} new transaction(s) found`);
                showNewTransactionNotification(data.new_count);
                
                // Update lastTransactionId to prevent showing notification again
                if (data.transactions && data.transactions.length > 0) {
                    const maxId = Math.max(...data.transactions.map(t => t.transaction_id));
                    if (maxId > lastTransactionId) {
                        lastTransactionId = maxId;
                        console.log('Updated last transaction ID to:', lastTransactionId);
                    }
                }
                
                // REMOVED: Automatic page reload - admin can manually refresh if needed
                // User can click the notification to reload or use browser refresh (F5)
            }
        })
        .catch(error => {
            console.error('Error checking for new transactions:', error);
        });
}

function showNewTransactionNotification(count) {
    // Remove any existing notification to prevent duplicates
    const existingNotif = document.querySelector('.new-transaction-notification');
    if (existingNotif) {
        existingNotif.remove();
    }
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'new-transaction-notification';
    notification.innerHTML = `
        <i class="fas fa-shopping-cart"></i>
        <div style="flex: 1;">
            <strong>${count}</strong> new redemption request(s)!
            <div style="font-size: 12px; opacity: 0.9; margin-top: 3px;">Click to refresh page or press F5</div>
        </div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px; padding: 0 5px;">×</button>
    `;
    notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInNotif 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        max-width: 350px;
    `;
    
    // Click to reload
    notification.addEventListener('click', function(e) {
        // Don't reload if clicking the close button
        if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'I') {
            location.reload();
        }
    });
    
    document.body.appendChild(notification);
    
    // Auto-remove after 10 seconds (increased from 5)
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutNotif 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 10000);
}

// Add notification animation styles
const notifStyle = document.createElement('style');
notifStyle.textContent = `
    @keyframes slideInNotif {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    @keyframes slideOutNotif {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(notifStyle);

// Flash message close function
function closeFlashMessage() {
    const flashMessage = document.getElementById('flashMessage');
    if (flashMessage) {
        flashMessage.style.opacity = '0';
        flashMessage.style.transform = 'translateY(-20px)';
        setTimeout(function() {
            flashMessage.style.display = 'none';
        }, 300);
    }
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.item-form');
    
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const nameInput = form.querySelector('input[name="item_name"]');
            const pointsInput = form.querySelector('input[name="points_required"]');
            
            // Validate item name
            if (nameInput && nameInput.value.trim().length < 3) {
                e.preventDefault();
                alert('Item name must be at least 3 characters long.');
                nameInput.focus();
                return;
            }
            
            // Validate points
            if (pointsInput && (isNaN(pointsInput.value) || pointsInput.value < 1)) {
                e.preventDefault();
                alert('Points required must be a positive number.');
                pointsInput.focus();
                return;
            }
        });
    });
});

// Search and filter functionality (optional enhancement)
function filterItems(category) {
    const items = document.querySelectorAll('.item-card');
    
    items.forEach(function(item) {
        if (category === 'all' || item.dataset.category === category) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Real-time points calculation
function calculatePointsStats() {
    const pointsInputs = document.querySelectorAll('input[name="points_required"]');
    
    pointsInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            const value = parseInt(this.value);
            const feedback = this.nextElementSibling;
            
            if (feedback && feedback.classList.contains('points-feedback')) {
                feedback.remove();
            }
            
            if (value > 0) {
                const feedbackDiv = document.createElement('div');
                feedbackDiv.className = 'points-feedback';
                feedbackDiv.style.fontSize = '0.8rem';
                feedbackDiv.style.marginTop = '5px';
                
                if (value < 100) {
                    feedbackDiv.style.color = '#28a745';
                    feedbackDiv.textContent = 'Low cost item - Easy to obtain';
                } else if (value < 500) {
                    feedbackDiv.style.color = '#ffc107';
                    feedbackDiv.textContent = 'Medium cost item - Moderate effort required';
                } else if (value < 1000) {
                    feedbackDiv.style.color = '#fd7e14';
                    feedbackDiv.textContent = 'High cost item - Significant effort required';
                } else {
                    feedbackDiv.style.color = '#dc3545';
                    feedbackDiv.textContent = 'Premium item - Exceptional effort required';
                }
                
                this.parentNode.appendChild(feedbackDiv);
            }
        });
    });
}

// Initialize points calculation on page load
document.addEventListener('DOMContentLoaded', calculatePointsStats);

// Confirmation dialogs for important actions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Auto-refresh data every 30 seconds (optional)
let autoRefreshInterval;

function startAutoRefresh() {
    autoRefreshInterval = setInterval(function() {
        // Only refresh if no modals are open
        const modals = document.querySelectorAll('.modal');
        let modalOpen = false;
        
        modals.forEach(function(modal) {
            if (modal.style.display === 'block') {
                modalOpen = true;
            }
        });
        
        if (!modalOpen) {
            // You can implement auto-refresh logic here if needed
            console.log('Auto-refresh check...');
        }
    }, 30000);
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
}

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    // startAutoRefresh(); // Uncomment if you want auto-refresh
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopAutoRefresh();
});

// Image handling functions
function previewImage(input, previewId) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById(previewId);
            const container = preview.parentElement;
            
            preview.src = e.target.result;
            preview.style.display = 'block';
            container.style.display = 'block';
            
            // Update main preview if on add form
            if (previewId === 'add_preview_image') {
                updatePreview();
            }
        };
        reader.readAsDataURL(file);
    }
}

function removeImagePreview(type) {
    const containerId = type + '_image_preview_container';
    const previewId = type + '_preview_image';
    const inputId = type === 'add' ? 'item_image' : 'edit_item_image';
    
    document.getElementById(containerId).style.display = 'none';
    document.getElementById(previewId).src = '';
    document.getElementById(inputId).value = '';
    
    if (type === 'add') {
        updatePreview();
    }
}

function removeCurrentImage() {
    const currentImageContainer = document.getElementById('edit_current_image_container');
    const currentImage = document.getElementById('edit_current_image');
    const noImageText = document.getElementById('edit_no_image');
    const removeButton = document.getElementById('edit_remove_image_btn');
    const removeFlag = document.getElementById('remove_image_flag');
    
    currentImage.style.display = 'none';
    noImageText.style.display = 'block';
    removeButton.style.display = 'none';
    removeFlag.value = '1';
}

function showImagePreview(imagePath, itemName) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('imagePreviewImg');
    const title = document.getElementById('imagePreviewTitle');
    
    img.src = imagePath;
    title.textContent = itemName + ' - Image Preview';
    modal.style.display = 'block';
}

function closeImagePreview() {
    document.getElementById('imagePreviewModal').style.display = 'none';
}

function updatePreview() {
    const name = document.getElementById('item_name').value || 'Item Name';
    const description = document.getElementById('item_description').value || 'Item description will appear here...';
    const points = document.getElementById('points_required').value || '100';
    const category = document.getElementById('category').value || 'general';
    
    // Update preview text
    document.getElementById('preview_title').textContent = name;
    document.getElementById('preview_description').textContent = description;
    document.getElementById('preview_points').textContent = points;
    document.getElementById('preview_category').textContent = category.charAt(0).toUpperCase() + category.slice(1);
    
    // Update preview image
    const previewImageContainer = document.getElementById('preview_image_container');
    const previewImage = document.getElementById('preview_image');
    const previewEmoji = previewImageContainer.querySelector('.preview-emoji');
    const uploadedImage = document.getElementById('add_preview_image');
    
    if (uploadedImage && uploadedImage.src && uploadedImage.style.display !== 'none') {
        previewImage.src = uploadedImage.src;
        previewImage.style.display = 'block';
        previewEmoji.style.display = 'none';
    } else {
        previewImage.style.display = 'none';
        previewEmoji.style.display = 'block';
    }
}

// Update the editItem function to handle images
function editItem(item) {
    const modal = document.getElementById('editItemModal');
    
    // Populate form fields
    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_item_description').value = item.item_description;
    document.getElementById('edit_points_required').value = item.points_required;
    document.getElementById('edit_category').value = item.category;
    document.getElementById('edit_stock_quantity').value = item.stock_quantity || '0';
    document.getElementById('edit_is_available').checked = item.is_available == 1;
    
    // Display previous stock quantity
    const previousStock = item.stock_quantity || 0;
    const stockInfoContainer = document.getElementById('stock_info_container');
    const previousStockValue = document.getElementById('previous_stock_value');
    const lowStockWarning = document.getElementById('low_stock_warning');
    const lowStockCount = document.getElementById('low_stock_count');
    
    // Show stock info container
    stockInfoContainer.style.display = 'block';
    previousStockValue.textContent = previousStock;
    
    // Show low stock warning if stock is 5 or below
    if (previousStock <= 5 && previousStock > 0) {
        lowStockWarning.style.display = 'flex';
        lowStockCount.textContent = previousStock;
    } else if (previousStock === 0) {
        lowStockWarning.style.display = 'flex';
        lowStockWarning.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Out of Stock!</span>';
        lowStockWarning.style.background = '#f8d7da';
        lowStockWarning.style.borderColor = '#f5c6cb';
    } else {
        lowStockWarning.style.display = 'none';
    }
    
    // Handle current image display
    const currentImage = document.getElementById('edit_current_image');
    const noImageText = document.getElementById('edit_no_image');
    const removeButton = document.getElementById('edit_remove_image_btn');
    const removeFlag = document.getElementById('remove_image_flag');
    
    // Reset remove flag
    removeFlag.value = '0';
    
    if (item.image_path && item.image_path.trim() !== '') {
        currentImage.src = item.image_path;
        currentImage.style.display = 'block';
        noImageText.style.display = 'none';
        removeButton.style.display = 'inline-block';
    } else {
        currentImage.style.display = 'none';
        noImageText.style.display = 'block';
        removeButton.style.display = 'none';
    }
    
    // Clear new image preview
    const newImagePreview = document.getElementById('edit_image_preview_container');
    newImagePreview.style.display = 'none';
    document.getElementById('edit_item_image').value = '';
    
    modal.style.display = 'block';
}

// Close edit modal
function closeEditModal() {
    document.getElementById('editItemModal').style.display = 'none';
}

// Adjust stock quantity
function adjustStock(amount) {
    const stockInput = document.getElementById('edit_stock_quantity');
    let currentValue = parseInt(stockInput.value) || 0;
    let newValue = currentValue + amount;
    
    // Don't allow negative values
    if (newValue < 0) {
        newValue = 0;
    }
    
    stockInput.value = newValue;
    
    // Simple visual feedback
    stockInput.classList.add('stock-updated');
    setTimeout(() => {
        stockInput.classList.remove('stock-updated');
    }, 200);
}

// Delete item functionality
function deleteItem(itemId, itemName) {
    document.getElementById('delete_item_id').value = itemId;
    document.getElementById('deleteItemName').textContent = itemName;
    document.getElementById('deleteConfirmModal').style.display = 'block';
}

// Make item available functionality
function makeAvailable(itemId, itemName) {
    if (confirm(`Are you sure you want to make "${itemName}" available in the shop?`)) {
        // Create a form and submit it
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'make_available';
        
        const itemIdInput = document.createElement('input');
        itemIdInput.type = 'hidden';
        itemIdInput.name = 'item_id';
        itemIdInput.value = itemId;
        
        form.appendChild(actionInput);
        form.appendChild(itemIdInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editItemModal');
    const deleteModal = document.getElementById('deleteConfirmModal');
    const rejectionModal = document.getElementById('rejectionReasonModal');
    
    if (event.target == editModal) {
        closeEditModal();
    }
    if (event.target == deleteModal) {
        closeDeleteModal();
    }
    if (event.target == rejectionModal) {
        closeRejectionModal();
    }
}

// Filter items by category
function filterItemsByCategory() {
    const categoryFilter = document.getElementById('category-filter');
    const selectedCategory = categoryFilter.value.toLowerCase();
    const itemCards = document.querySelectorAll('.item-card');
    const itemsCount = document.getElementById('items-count');
    
    let visibleCount = 0;
    
    itemCards.forEach(card => {
        const itemCategory = card.getAttribute('data-category').toLowerCase();
        
        if (selectedCategory === '' || itemCategory === selectedCategory) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update count display
    if (selectedCategory === '') {
        itemsCount.textContent = itemCards.length;
    } else {
        itemsCount.textContent = visibleCount;
    }
}
</script>
</body>
</html>