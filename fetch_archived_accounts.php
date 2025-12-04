<?php
session_start();
require_once 'database.php';

// Check if the request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

// Authorization check
if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Representative'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Get request parameters
$type = $_POST['type'] ?? 'valid';
$search = $_POST['search'] ?? '';
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$role = $_POST['role'] ?? '';

// Build the base query
if ($type === 'valid') {
    $query = "SELECT * FROM accounts_archive WHERE 1=1";
} else {
    $query = "SELECT * FROM tempaccs_archive WHERE 1=1";
}

// Add search conditions
if (!empty($search)) {
    $searchTerm = "%$search%";
    if ($type === 'valid') {
        $query .= " AND (fullname LIKE ? OR email LIKE ? OR organization LIKE ?)";
    } else {
        $query .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR organization LIKE ?)";
    }
}

// Add role filter
if (!empty($role)) {
    $query .= " AND accessrole = ?";
}

// Add date range filter
if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND date_deleted BETWEEN ? AND ?";
} elseif (!empty($startDate)) {
    $query .= " AND date_deleted >= ?";
} elseif (!empty($endDate)) {
    $query .= " AND date_deleted <= ?";
}

// Order by date deleted
$query .= " ORDER BY date_deleted DESC";

// Prepare and execute the query
$stmt = $connection->prepare($query);

if (!$stmt) {
    die(json_encode(['success' => false, 'message' => 'Database error: ' . $connection->error]));
}

// Bind parameters
$paramTypes = '';
$paramValues = [];

if (!empty($search)) {
    if ($type === 'valid') {
        $paramTypes .= 'sss';
        array_push($paramValues, $searchTerm, $searchTerm, $searchTerm);
    } else {
        $paramTypes .= 'ssss';
        array_push($paramValues, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
}

if (!empty($role)) {
    $paramTypes .= 's';
    array_push($paramValues, $role);
}

if (!empty($startDate) && !empty($endDate)) {
    $paramTypes .= 'ss';
    array_push($paramValues, $startDate, $endDate . ' 23:59:59');
} elseif (!empty($startDate)) {
    $paramTypes .= 's';
    array_push($paramValues, $startDate);
} elseif (!empty($endDate)) {
    $paramTypes .= 's';
    array_push($paramValues, $endDate . ' 23:59:59');
}

if (!empty($paramTypes)) {
    $stmt->bind_param($paramTypes, ...$paramValues);
}

$stmt->execute();
$result = $stmt->get_result();
$accounts = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $accounts
]);
?>