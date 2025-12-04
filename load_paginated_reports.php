<?php
session_start();
include 'database.php';

// Check if user is logged in with proper role
if(!isset($_SESSION["accessrole"]) || 
   ($_SESSION["accessrole"] != 'Barangay Official' && 
    $_SESSION["accessrole"] != 'Administrator' && 
    $_SESSION["accessrole"] != 'Representative')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get request parameters
$reportType = $_GET['report_type'] ?? '';
$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 10);
$offset = ($page - 1) * $limit;

// City/Municipality filtering
$city_municipality = isset($_SESSION["city_municipality"]) ? $_SESSION["city_municipality"] : null;
$isAdminOrRep = ($_SESSION["accessrole"] == 'Administrator' || $_SESSION["accessrole"] == 'Representative');

// Function to format species display
function formatSpeciesDisplay($speciesData) {
    if (empty($speciesData)) return 'Not specified';
    
    $speciesMap = [
        'Rhizophora Apiculata' => 'Bakawan Lalake',
        'Rhizophora Mucronata' => 'Bakawan Babae',
        'Avicennia Marina' => 'Bungalon',
        'Sonneratia Alba' => 'Palapat'
    ];
    
    $decoded = json_decode($speciesData, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $displayNames = array_map(function($species) use ($speciesMap) {
            return isset($speciesMap[trim($species)]) ? $speciesMap[trim($species)] : trim($species);
        }, $decoded);
        return implode(', ', $displayNames);
    }
    
    if (strpos($speciesData, ',') !== false) {
        $speciesArray = explode(',', $speciesData);
        $displayNames = array_map(function($species) use ($speciesMap) {
            $trimmed = trim($species);
            return isset($speciesMap[$trimmed]) ? $speciesMap[$trimmed] : $trimmed;
        }, $speciesArray);
        return implode(', ', $displayNames);
    }
    
    return isset($speciesMap[trim($speciesData)]) ? $speciesMap[trim($speciesData)] : trim($speciesData);
}

try {
    $html = '';
    $totalRecords = 0;
    
    switch($reportType) {
        case 'mangrove':
            // Query for mangrove reports - Use LEFT JOIN like illegal reports
            $countSql = "SELECT COUNT(*) as total 
                        FROM mangrovereporttbl m ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $countSql .= " WHERE m.city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $countResult = $connection->query($countSql);
            $totalRecords = $countResult->fetch_assoc()['total'];
            
            $sql = "SELECT m.*, 
                          COALESCE(a.fullname, 'Anonymous') as reporter_name
                    FROM mangrovereporttbl m
                    LEFT JOIN accountstbl a ON m.reporter_id = a.account_id ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $sql .= " WHERE m.city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $sql .= "ORDER BY m.report_date DESC LIMIT $limit OFFSET $offset";
            $result = $connection->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
                    
                    // Get reporter name from joined data
                    $reporterName = $row['reporter_name'] ?? 'Anonymous';
                    
                    $html .= '
                    <li class="report-item '.$priorityClass.'" 
                        data-priority="'.htmlspecialchars($row['priority']).'" 
                        data-plant-type="'.htmlspecialchars($row['species']).'" 
                        data-mangrove-status="'.htmlspecialchars($row['mangrove_status']).'"
                        data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" 
                        data-barangay="'.htmlspecialchars($row['barangays']).'"
                        data-address="'.htmlspecialchars($row['address'] ?? '').'">
                        <div class="report-content">
                            <strong>'.htmlspecialchars(formatSpeciesDisplay($row['species'])).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                            <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                            <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                        </div>
                        <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="mangrove">View</button>
                    </li>';
                }
            } else {
                $html = '<li class="report-item no-results">No reports found</li>';
            }
            break;
            
        case 'illegal-emergency':
            // Query for emergency illegal reports
            $countSql = "SELECT COUNT(*) as total 
                        FROM illegalreportstbl 
                        WHERE priority = 'Emergency' ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $countSql .= " AND city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $countResult = $connection->query($countSql);
            $totalRecords = $countResult->fetch_assoc()['total'];
            
            $sql = "SELECT * FROM illegalreportstbl WHERE priority = 'Emergency' ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $sql .= " AND city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $sql .= "ORDER BY report_date DESC LIMIT $limit OFFSET $offset";
            $result = $connection->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $incidentCategory = in_array($row['incident_type'], ['Illegal Cutting', 'Construction', 'Harmful Fishing']) 
                        ? 'Illegal Activities' 
                        : 'Mangrove-related Incidents';
                    
                    // Get reporter name
                    $reporterName = 'Anonymous';
                    if (!empty($row['reporter_id'])) {
                        $reporterId = $connection->real_escape_string($row['reporter_id']);
                        $accResult = $connection->query("SELECT fullname FROM accountstbl WHERE account_id = '$reporterId' LIMIT 1");
                        if ($accResult && $accResult->num_rows > 0) {
                            $accRow = $accResult->fetch_assoc();
                            $reporterName = htmlspecialchars($accRow['fullname']);
                        }
                    }
                    
                    $html .= '
                    <li class="report-item emergency" 
                        data-incident-type="'.htmlspecialchars($row['incident_type']).'" 
                        data-incident-category="'.$incidentCategory.'" 
                        data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" 
                        data-barangay="'.htmlspecialchars($row['barangays']).'" 
                        data-address="'.htmlspecialchars($row['address'] ?? '').'">
                        <div class="report-content">
                            <strong>'.htmlspecialchars($row['incident_type']).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                            <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                            <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                        </div>
                        <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="illegal">View</button>
                    </li>';
                }
            } else {
                $html = '<li class="report-item no-results">No emergency reports</li>';
            }
            break;
            
        case 'illegal':
            // Query for all illegal reports
            $countSql = "SELECT COUNT(*) as total 
                        FROM illegalreportstbl ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $countSql .= " WHERE city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $countResult = $connection->query($countSql);
            $totalRecords = $countResult->fetch_assoc()['total'];
            
            $sql = "SELECT ir.*, 
                          COALESCE(a.fullname, 'Anonymous') as reporter_name
                    FROM illegalreportstbl ir
                    LEFT JOIN accountstbl a ON ir.reporter_id = a.account_id ";
            
            if (!$isAdminOrRep && isset($city_municipality)) {
                $sql .= " WHERE ir.city_municipality = '" . $connection->real_escape_string($city_municipality) . "' ";
            }
            
            $sql .= "ORDER BY report_date DESC LIMIT $limit OFFSET $offset";
            $result = $connection->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $incidentCategory = in_array($row['incident_type'], ['Illegal Cutting', 'Construction', 'Harmful Fishing']) 
                        ? 'Illegal Activities' 
                        : 'Mangrove-related Incidents';
                    
                    $priorityClass = $row['priority'] == 'Emergency' ? 'emergency' : '';
                    $reporterName = $row['reporter_name'] ?? 'Anonymous';
                    
                    // Build rating display if resolved
                    $ratingDisplay = '';
                    if ($row['action_type'] === 'Resolved' && !empty($row['rating'])) {
                        $ratingDisplay = '<div class="report-rating">';
                        for ($i = 1; $i <= 5; $i++) {
                            $starClass = $i <= $row['rating'] ? 'filled' : 'empty';
                            $starIcon = $i <= $row['rating'] ? '★' : '☆';
                            $ratingDisplay .= '<span class="star ' . $starClass . '">' . $starIcon . '</span>';
                        }
                        $ratingDisplay .= '<span class="rating-points">+' . ($row['points_awarded'] ?? 0) . ' pts</span>';
                        $ratingDisplay .= '</div>';
                    }
                    
                    $html .= '
                    <li class="report-item '.$priorityClass.'" 
                        data-priority="'.htmlspecialchars($row['priority']).'" 
                        data-incident-type="'.htmlspecialchars($row['incident_type']).'" 
                        data-incident-category="'.$incidentCategory.'"  
                        data-city-municipality="'.htmlspecialchars($row['city_municipality']).'" 
                        data-barangay="'.htmlspecialchars($row['barangays']).'" 
                        data-address="'.htmlspecialchars($row['address'] ?? '').'">
                        <div class="report-content">
                            <strong>'.htmlspecialchars($row['incident_type']).'</strong> - '.htmlspecialchars($row['city_municipality']).'
                            <div class="report-meta">'.date('Y-m-d', strtotime($row['report_date'])).' | Area: '.htmlspecialchars($row['area_no']).'</div>
                            <div class="report-reporter">Reported by: <span class="reporter-name">'.$reporterName.'</span></div>
                            '.$ratingDisplay.'
                        </div>
                        <button class="view-btn" data-report-id="'.htmlspecialchars($row['report_id']).'" data-report-type="illegal">View</button>
                    </li>';
                }
            } else {
                $html = '<li class="report-item no-results">No reports found</li>';
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid report type']);
            exit();
    }
    
    $totalPages = ceil($totalRecords / $limit);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalRecords' => $totalRecords,
        'hasMore' => $page < $totalPages
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
