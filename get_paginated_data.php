<?php
session_start();
include 'database.php';

// Check if user is logged in and has proper role
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Barangay Official' && 
    $_SESSION["accessrole"] != 'Administrator' && 
    $_SESSION["accessrole"] != 'Representative')) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Get request parameters
$type = $_GET['type'] ?? ''; // 'profiles' or 'logs'
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get filter parameters
$city_filter = $_GET['city'] ?? '';
$barangay_filter = $_GET['barangay'] ?? '';
$status_filter = $_GET['status'] ?? '';
$action_filter = $_GET['action'] ?? '';

if ($type === 'profiles') {
    // Build WHERE clause for filters
    $where_conditions = ["status = 'published'"];
    $params = [];
    $param_types = '';
    
    if (!empty($city_filter)) {
        $where_conditions[] = "city_municipality = ?";
        $params[] = $city_filter;
        $param_types .= 's';
    }
    
    if (!empty($barangay_filter)) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
        $param_types .= 's';
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Count total profiles with filters
    $count_sql = "SELECT COUNT(*) as total FROM barangayprofiletbl WHERE $where_clause";
    if (!empty($params)) {
        $count_stmt = $connection->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_items = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $connection->query($count_sql);
        $total_items = $count_result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_items / $per_page);
    
    // Fetch profiles with pagination and filters
    $sql = "SELECT * FROM barangayprofiletbl WHERE $where_clause ORDER BY date_created DESC LIMIT $per_page OFFSET $offset";
    if (!empty($params)) {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $connection->query($sql);
    }

    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    // Generate profiles table HTML
    $html = '';
    if (!empty($items)) {
        foreach ($items as $profile) {
            $species_count = !empty($profile['species_present']) ? count(explode(',', $profile['species_present'])) : 0;
            
            // Determine QR status from the database field
            $qr_status = !empty($profile['qr_status']) ? $profile['qr_status'] : 'inactive';
            $status_class = $qr_status === 'active' ? 'status-governed' : 'status-unavailable';
            $display_status = ucfirst($qr_status); // Display as "Active" or "Inactive"
            $button_text = $qr_status === 'active' ? 'Deactivate' : 'Activate';
            $button_class = $qr_status === 'active' ? 'deactivate' : 'activate';
            
            $html .= '<tr data-profile-id="' . $profile['profile_id'] . '" data-profile-key="' . $profile['profile_key'] . '">';
            $html .= '<td>' . htmlspecialchars($profile['barangay']) . '</td>';
            $html .= '<td>' . htmlspecialchars($profile['city_municipality']) . '</td>';
            $html .= '<td>' . htmlspecialchars($profile['mangrove_area']) . '</td>';
            $html .= '<td>' . $species_count . '</td>';
            $html .= '<td><span class="status-badge ' . $status_class . '">' . $display_status . '</span></td>';
            $html .= '<td>';
            $html .= '<div style="display:flex; gap:5px;">';
            $html .= '<form method="post" action="view_barangay_profile.php?profile_key=' . $profile['profile_key'] . '" style="display:inline;">';
            $html .= '<input type="hidden" name="profile_key" value="' . $profile['profile_key'] . '">';
            $html .= '<input type="hidden" name="admin_access_key" value="adminkeynadialamnghindicoderist">';
            $html .= '<button type="submit" class="view-btn">View</button>';
            $html .= '</form>';
            // Add status toggle button
            $html .= '<button class="status-toggle-btn ' . $button_class . '" 
                    data-profile-id="' . $profile['profile_id'] . '"
                    data-current-status="' . $qr_status . '">' . $button_text . '</button>';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No mangrove profiles found. <a href="create_mangrove_profile.php">Create a new profile</a> to get started.</td></tr>';
    }
    
} elseif ($type === 'logs') {
    // Build WHERE clause for filters
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    if (!empty($city_filter)) {
        $where_conditions[] = "city_municipality = ?";
        $params[] = $city_filter;
        $param_types .= 's';
    }
    
    if (!empty($barangay_filter)) {
        $where_conditions[] = "barangay = ?";
        $params[] = $barangay_filter;
        $param_types .= 's';
    }
    
    if (!empty($action_filter)) {
        $where_conditions[] = "action = ?";
        $params[] = $action_filter;
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Count total logs with filters
    $count_sql = "SELECT COUNT(*) as total FROM barangayprofile_logstbl $where_clause";
    if (!empty($params)) {
        $count_stmt = $connection->prepare($count_sql);
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_items = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $count_result = $connection->query($count_sql);
        $total_items = $count_result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_items / $per_page);
    
    // Fetch logs with pagination and filters
    $sql = "SELECT * FROM barangayprofile_logstbl $where_clause ORDER BY log_date DESC LIMIT $per_page OFFSET $offset";
    if (!empty($params)) {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        $result = $connection->query($sql);
    }
    
    $items = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    // Generate logs table HTML
    $html = '';
    if (!empty($items)) {
        foreach ($items as $log) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($log['log_date']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['fullname']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['barangay']) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['city_municipality']) . '</td>';
            $html .= '<td><span class="action-badge ' . htmlspecialchars($log['action']) . '">' . htmlspecialchars($log['action']) . '</span></td>';
            $html .= '<td>' . nl2br(htmlspecialchars($log['description'])) . '</td>';
            $html .= '<td>' . htmlspecialchars($log['account_id']) . '</td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="7" style="text-align: center; padding: 20px;">No activity logs found.</td></tr>';
    }
    
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type parameter']);
    exit();
}

// Generate pagination HTML
$pagination_html = '';
if ($total_pages > 1) {
    $pagination_html .= '<div class="pagination-container">';
    $pagination_html .= '<div class="pagination-info">';
    $pagination_html .= 'Showing ' . ($offset + 1) . ' to ' . min($offset + $per_page, $total_items) . ' of ' . $total_items . ' ' . $type;
    $pagination_html .= '</div>';
    $pagination_html .= '<div class="pagination">';
    
    // Previous button
    if ($page > 1) {
        $pagination_html .= '<a href="#" class="pagination-btn" onclick="loadPage(\'' . $type . '\', ' . ($page - 1) . '); return false;">';
        $pagination_html .= '<i class="fas fa-chevron-left"></i> Previous</a>';
    }
    
    // Page numbers
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);
    
    if ($start_page > 1) {
        $pagination_html .= '<a href="#" class="pagination-btn" onclick="loadPage(\'' . $type . '\', 1); return false;">1</a>';
        if ($start_page > 2) {
            $pagination_html .= '<span class="pagination-dots">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active_class = ($i == $page) ? 'active' : '';
        $pagination_html .= '<a href="#" class="pagination-btn ' . $active_class . '" onclick="loadPage(\'' . $type . '\', ' . $i . '); return false;">' . $i . '</a>';
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination_html .= '<span class="pagination-dots">...</span>';
        }
        $pagination_html .= '<a href="#" class="pagination-btn" onclick="loadPage(\'' . $type . '\', ' . $total_pages . '); return false;">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($page < $total_pages) {
        $pagination_html .= '<a href="#" class="pagination-btn" onclick="loadPage(\'' . $type . '\', ' . ($page + 1) . '); return false;">';
        $pagination_html .= 'Next <i class="fas fa-chevron-right"></i></a>';
    }
    
    $pagination_html .= '</div>';
    $pagination_html .= '</div>';
}

echo json_encode([
    'status' => 'success',
    'html' => $html,
    'pagination_html' => $pagination_html,
    'current_page' => $page,
    'total_pages' => $total_pages,
    'total_items' => $total_items
]);

$connection->close();
?>
