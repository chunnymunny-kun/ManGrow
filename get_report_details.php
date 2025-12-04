<?php
session_start();
include 'database.php';

header('Content-Type: application/json');

if (!isset($_GET['report_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing report ID']);
    exit();
}

$reportId = intval($_GET['report_id']);
$type = $_GET['type'] ?? 'illegal';

// Check if user is admin for rating requests
if (isset($_GET['for_rating']) && $_GET['for_rating'] === 'true') {
    if (!isset($_SESSION['accessrole']) || !in_array($_SESSION['accessrole'], ['Administrator', 'Barangay Official'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
}

try {
    if ($type === 'mangrove') {
        $sql = "SELECT * FROM mangrovereporttbl WHERE report_id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $report = $result->fetch_assoc();
            echo json_encode(['success' => true, 'report' => $report]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Mangrove report not found']);
        }
    } else {
        // For illegal activity reports - enhanced query with reporter info
        $sql = "
            SELECT 
                ir.*,
                COALESCE(a.fullname, 'Anonymous') as reporter_name,
                COALESCE(a.email, 'No email') as reporter_email,
                COALESCE(ir.contact_no, 'No contact') as reporter_contact
            FROM illegalreportstbl ir
            LEFT JOIN accountstbl a ON ir.reporter_id = a.account_id
            WHERE ir.report_id = ?
        ";
        
        // Add additional filter for rating requests
        if (isset($_GET['for_rating']) && $_GET['for_rating'] === 'true') {
            $sql .= " AND ir.report_type = 'Illegal Activity Report' AND ir.action_type = 'Resolved'";
        }
        
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("i", $reportId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $report = $result->fetch_assoc();
            
            // Format response for rating modal if requested
            if (isset($_GET['for_rating']) && $_GET['for_rating'] === 'true') {
                $reportData = [
                    'success' => true,
                    'report' => [
                        'reportId' => $report['report_id'],
                        'incidentType' => $report['incident_type'] ?: 'Unknown',
                        'priority' => $report['priority'] ?: 'Normal',
                        'location' => trim(($report['barangays'] ?: 'Unknown Barangay') . ', ' . ($report['city_municipality'] ?: 'Unknown City')),
                        'reporter' => $report['reporter_name'],
                        'reporterEmail' => $report['reporter_email'],
                        'reporterContact' => $report['reporter_contact'],
                        'dateReported' => date('M d, Y g:i A', strtotime($report['report_date'])),
                        'reportType' => $report['report_type'],
                        'actionType' => $report['action_type'],
                        'description' => $report['report_description'] ?: 'No description',
                        'coordinates' => [
                            'lat' => $report['latitude'],
                            'lng' => $report['longitude']
                        ],
                        'currentRating' => $report['rating']
                    ]
                ];
                echo json_encode($reportData);
            } else {
                echo json_encode(['success' => true, 'report' => $report]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Illegal activity report not found']);
        }
    }
    
} catch (Exception $e) {
    error_log("Error fetching report details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$connection->close();
?>