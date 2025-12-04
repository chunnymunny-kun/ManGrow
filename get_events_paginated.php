<?php
session_start();
include 'database.php';

// Set default response
$response = [
    'status' => 'error',
    'message' => 'Invalid request',
    'total' => 0,
    'events' => []
];

try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 10;
    $offset = ($page - 1) * $perPage;

    // Get filter parameters
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : 'all';
    $city = isset($_GET['city']) ? trim($_GET['city']) : 'all';
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $barangayRestricted = isset($_GET['barangay_restricted']) ? true : false;

    // Build base query
    $query = "SELECT SQL_CALC_FOUND_ROWS * FROM eventstbl WHERE program_type != 'Announcement'";
    $countQuery = "SELECT COUNT(*) FROM eventstbl WHERE program_type != 'Announcement'";
    
    // Apply filters
    $whereClauses = [];
    $params = [];
    $types = '';

    // Role-based restrictions
    if ($barangayRestricted && isset($_SESSION['barangay']) && isset($_SESSION['city_municipality'])) {
        $whereClauses[] = "barangay = ?";
        $params[] = $_SESSION['barangay'];
        $types .= 's';
        
        $whereClauses[] = "city_municipality = ?";
        $params[] = $_SESSION['city_municipality'];
        $types .= 's';
    }

    // Search term filter
    if (!empty($searchTerm)) {
        $whereClauses[] = "(subject LIKE ? OR description LIKE ? OR organization LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= 'sss';
    }

    // Barangay filter
    if ($barangay !== 'all') {
        $whereClauses[] = "barangay = ?";
        $params[] = $barangay;
        $types .= 's';
    }

    // City filter
    if ($city !== 'all') {
        $whereClauses[] = "city_municipality = ?";
        $params[] = $city;
        $types .= 's';
    }

    // Date range filters
    if (!empty($dateFrom)) {
        $whereClauses[] = "created_at >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }
    if (!empty($dateTo)) {
        $whereClauses[] = "created_at <= ?";
        $params[] = $dateTo . ' 23:59:59'; // Include entire end day
        $types .= 's';
    }

    // Combine where clauses
    if (!empty($whereClauses)) {
        $query .= " AND " . implode(" AND ", $whereClauses);
        $countQuery .= " AND " . implode(" AND ", $whereClauses);
    }

    // Add sorting
    $query .= " ORDER BY barangay ASC, created_at DESC";
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $types .= 'i';
    $params[] = $offset;
    $types .= 'i';

    // Prepare and execute query
    $stmt = $connection->prepare($query);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $events = $result->fetch_all(MYSQLI_ASSOC);

    // Get total count
    $totalResult = $connection->query("SELECT FOUND_ROWS()");
    $total = $totalResult->fetch_row()[0];

    // Prepare response
    $response = [
        'status' => 'success',
        'total' => $total,
        'events' => $events
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>