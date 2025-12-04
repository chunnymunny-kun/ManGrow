<?php
session_start();
include 'database.php';

// Function to format species display
function formatSpeciesDisplay($speciesData) {
    if (empty($speciesData)) return 'Not specified';
    
    // Check if it's a JSON string (new multiple species format)
    $decoded = json_decode($speciesData, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        // Convert scientific names to common names for display
        $speciesMap = [
            'Rhizophora Apiculata' => 'Bakawan Lalake',
            'Rhizophora Mucronata' => 'Bakawan Babae',
            'Avicennia Marina' => 'Bungalon',
            'Sonneratia Alba' => 'Palapat'
        ];
        
        $displayNames = array_map(function($species) use ($speciesMap) {
            return isset($speciesMap[$species]) ? $speciesMap[$species] : $species;
        }, $decoded);
        
        return implode(', ', $displayNames);
    }
    
    // Handle old format or single species
    $speciesMap = [
        'Rhizophora Apiculata' => 'Bakawan Lalake',
        'Rhizophora Mucronata' => 'Bakawan Babae',
        'Avicennia Marina' => 'Bungalon',
        'Sonneratia Alba' => 'Palapat'
    ];
    
    return isset($speciesMap[$speciesData]) ? $speciesMap[$speciesData] : $speciesData;
}

// Get parameters
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : '';
$plantType = isset($_GET['plant_type']) ? $_GET['plant_type'] : '';
$city = isset($_GET['city']) ? $_GET['city'] : '';
$barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

// Build query - Use userreportstbl to include all reports (including anonymous)
$sql = "SELECT m.report_id, m.report_date, m.species, m.area_no, m.city_municipality, m.priority, m.reporter_id, m.barangays, m.address, u.account_id 
        FROM mangrovereporttbl m
        INNER JOIN userreportstbl u ON m.report_id = u.report_id AND u.report_type = 'Mangrove Data Report'
        WHERE 1=1";

// Add filters
if ($keyword) {
    $sql .= " AND (m.species LIKE '%".$connection->real_escape_string($keyword)."%' 
              OR m.city_municipality LIKE '%".$connection->real_escape_string($keyword)."%'
              OR m.address LIKE '%".$connection->real_escape_string($keyword)."%'
              OR m.barangays LIKE '%".$connection->real_escape_string($keyword)."%')";
}
if ($date) {
    $sql .= " AND DATE(m.report_date) = '".$connection->real_escape_string($date)."'";
}
if ($plantType) {
    $sql .= " AND m.species = '".$connection->real_escape_string($plantType)."'";
}
if ($city) {
    $sql .= " AND m.city_municipality = '".$connection->real_escape_string($city)."'";
}
if ($barangay) {
    $sql .= " AND m.barangays LIKE '%".$connection->real_escape_string($barangay)."%'";
}

$sql .= " ORDER BY m.report_date DESC LIMIT 10 OFFSET $offset";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
        $reporterName = 'Anonymous';
        // Use account_id from userreportstbl to get reporter name
        if (!empty($row['account_id'])) {
            $accountId = $connection->real_escape_string($row['account_id']);
            $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$accountId' LIMIT 1");
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
            data-plant-type="'.htmlspecialchars($row['species']).'"
            data-city="'.htmlspecialchars($row['city_municipality']).'"
            data-barangay="'.htmlspecialchars($row['barangays']).'"
            data-address="'.htmlspecialchars($row['address']).'">
            <div class="report-content">
                <strong>'.htmlspecialchars(formatSpeciesDisplay($row['species'])).'</strong> - '.htmlspecialchars($row['city_municipality']).'
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
            <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="mangrove">View</button>
        </li>';
    }
} else {
    echo '<li class="report-item">No reports found</li>';
}
?>