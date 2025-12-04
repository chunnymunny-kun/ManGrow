<?php
header('Content-Type: application/json');
error_reporting(0); // Disable error display
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/pdf_errors.log');

try {
    session_start();
    require 'database.php';

    // Validate authorization
    if (!isset($_SESSION["accessrole"]) || 
        !in_array($_SESSION["accessrole"], ['Barangay Official', 'Administrator', 'Representative'])) {
        throw new Exception('Unauthorized access', 403);
    }

    // Get and validate input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input', 400);
    }

    $reportType = $data['reportType'] ?? '';
    $startDate = $data['startDate'] ?? '';
    $endDate = $data['endDate'] ?? '';
    $city = $data['city'] ?? '';
    $barangay = $data['barangay'] ?? '';
    $isBarangayOfficial = ($_SESSION["accessrole"] === 'Barangay Official');

    // Enforce restrictions for Barangay Officials
    if ($isBarangayOfficial) {
        // Override city and barangay with session values
        $city = $_SESSION["city_municipality"] ?? '';
        $barangay = $_SESSION["barangay"] ?? '';
        
        // Validate that they have assigned area
        if (empty($city)) {
            throw new Exception('Barangay Officials must have an assigned city/municipality', 400);
        }
    }

    if (empty($reportType) || empty($startDate)) {
        throw new Exception('Missing required parameters', 400);
    }

    // For single date (daily), use the same date for start and end
    $endDate = empty($endDate) ? $startDate : $endDate;

    // Prepare query based on report type
    if ($reportType === 'mangrove') {
        $query = "SELECT 
                    m.report_id, 
                    m.species, 
                    m.area_no, 
                    m.city_municipality, 
                    m.barangays,
                    m.mangrove_status, 
                    m.report_date,
                    COALESCE(
                        (SELECT action_type FROM report_notifstbl 
                         WHERE report_id = m.report_id 
                         ORDER BY notif_date DESC LIMIT 1),
                        'Received'
                    ) as status
                  FROM mangrovereporttbl m
                  WHERE DATE(m.report_date) BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1 FROM report_notifstbl rn 
                      WHERE rn.report_id = m.report_id 
                      AND rn.action_type = 'Rejected'
                  )";
    } else {
        $query = "SELECT 
                    i.report_id, 
                    i.incident_type, 
                    i.area_no, 
                    i.city_municipality, 
                    i.barangays,
                    i.priority, 
                    i.report_date,
                    COALESCE(
                        (SELECT action_type FROM report_notifstbl 
                         WHERE report_id = i.report_id 
                         ORDER BY notif_date DESC LIMIT 1),
                        'Received'
                    ) as status
                  FROM illegalreportstbl i
                  WHERE DATE(i.report_date) BETWEEN ? AND ?
                  AND NOT EXISTS (
                      SELECT 1 FROM report_notifstbl rn 
                      WHERE rn.report_id = i.report_id 
                      AND rn.action_type = 'Rejected'
                  )";
    }

    // Add city filter if specified
    if (!empty($city)) {
        $query .= " AND city_municipality = ?";
    } elseif ($_SESSION["accessrole"] === 'Barangay Official' && !empty($_SESSION["city_municipality"])) {
        // For barangay officials, filter by their city if no city specified
        $query .= " AND city_municipality = ?";
    }

    // Add barangay filter if specified (only if city is also specified)
    if (!empty($barangay) && !empty($city)) {
        $query .= " AND barangays LIKE ?";
    }

    $query .= " ORDER BY report_date DESC";

    // Prepare and execute statement
    $stmt = $connection->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Database query preparation failed: ' . $connection->error);
    }

    // Bind parameters
    $params = [$startDate, $endDate];
    $types = 'ss';

    // Add city parameter if needed
    if (!empty($city)) {
        $params[] = $city;
        $types .= 's';
    } elseif ($_SESSION["accessrole"] === 'Barangay Official' && !empty($_SESSION["city_municipality"])) {
        $params[] = $_SESSION["city_municipality"];
        $types .= 's';
    }

    // Add barangay parameter if needed
    if (!empty($barangay) && !empty($city)) {
        $params[] = '%' . $barangay . '%';
        $types .= 's';
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception('Database query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $reports = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $reports
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>