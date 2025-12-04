<?php
session_start();
include 'database.php';

// Get parameters
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$incidentCategory = isset($_GET['incident_category']) ? $_GET['incident_category'] : '';
$incidentType = isset($_GET['incident_type']) ? $_GET['incident_type'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

// Build query
$sql = "SELECT report_id, report_date, incident_type, area_no, city_municipality, priority, reporter_id, barangays, address 
        FROM illegalreportstbl 
        WHERE 1=1";

// Add filters
if ($keyword) {
    $sql .= " AND (incident_type LIKE '%".$connection->real_escape_string($keyword)."%' 
              OR city_municipality LIKE '%".$connection->real_escape_string($keyword)."%'
              OR address LIKE '%".$connection->real_escape_string($keyword)."%'
              OR barangays LIKE '%".$connection->real_escape_string($keyword)."%')";
}
if ($date) {
    $sql .= " AND DATE(report_date) = '".$connection->real_escape_string($date)."'";
}
if ($priority) {
    $sql .= " AND priority = '".$connection->real_escape_string($priority)."'";
}
if ($incidentType) {
    $sql .= " AND incident_type = '".$connection->real_escape_string($incidentType)."'";
}
if ($city) {
    $sql .= " AND city_municipality = '".$connection->real_escape_string($city)."'";
}
if ($barangay) {
    $sql .= " AND barangays LIKE '%".$connection->real_escape_string($barangay)."%'";
}

$sql .= " ORDER BY report_date DESC LIMIT 10 OFFSET $offset";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
        $incidentCategory = in_array($row['incident_type'], ['Illegal Cutting', 'Construction', 'Harmful Fishing']) 
            ? 'Illegal Activities' 
            : 'Mangrove-related Incidents';
        
        $reporterName = 'Anonymous';
        if (!empty($row['reporter_id'])) {
            $reporterId = $connection->real_escape_string($row['reporter_id']);
            $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$reporterId' LIMIT 1");
            if ($accResult && $accResult->num_rows > 0) {
                $accRow = $accResult->fetch_assoc();
                $reporterName = htmlspecialchars($accRow['fullname']);
            }
        }
        
        // Format barangays display
        $barangaysDisplay = 'None specified';
        if (!empty($row['barangays'])) {
            $barangaysList = explode(',', $row['barangays']);
            $barangaysDisplay = implode(', ', array_map('trim', $barangaysList));
        }

        echo '
        <li class="report-item '.$priorityClass.'" 
            data-priority="'.htmlspecialchars($row['priority']).'" 
            data-incident-type="'.htmlspecialchars($row['incident_type']).'" 
            data-incident-category="'.$incidentCategory.'"
            data-city-municipality="'.htmlspecialchars($row['city_municipality']).'"
            data-barangay="'.htmlspecialchars($row['barangays']).'"
            data-address="'.htmlspecialchars($row['address']).'">
            <div class="report-content">
                <strong>'.htmlspecialchars($row['incident_type']).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                <div class="report-meta">
                    '.date('Y-m-d', strtotime($row['report_date'])).' | 
                    Area: '.htmlspecialchars($row['area_no']).'<br>
                    <small>
                        <strong>Nearby barangays:</strong> '.$barangaysDisplay.'<br>
                        <strong>Address:</strong> '.htmlspecialchars($row['address']).'
                    </small>
                </div>
                <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
            </div>
            <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="illegal">View</button>
        </li>';
    }
} else {
    echo '<li class="report-item">No reports found</li>';
}
?>