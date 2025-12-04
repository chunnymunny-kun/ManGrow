<?php
session_start();
include 'database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$current_user_organization = $_SESSION['organization'] ?? '';

header('Content-Type: application/json');

// Handle different AJAX requests
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_organizations':
        // Get location filters
        $location_filter = '';
        $location_params = [];
        $param_types = '';

        if (!empty($_GET['city_municipality'])) {
            $location_filter .= " AND o.city_municipality = ?";
            $location_params[] = $_GET['city_municipality'];
            $param_types .= 's';
        }

        if (!empty($_GET['barangay'])) {
            $location_filter .= " AND o.barangay = ?";
            $location_params[] = $_GET['barangay'];
            $param_types .= 's';
        }

        if (!empty($_GET['privacy_setting'])) {
            $location_filter .= " AND o.privacy_setting = ?";
            $location_params[] = $_GET['privacy_setting'];
            $param_types .= 's';
        }

        // Query all organizations with filters (this will be the browseable list)
        $organizationsQuery = "SELECT o.org_id, o.name as organization, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting,
                                      COUNT(DISTINCT om.account_id) as member_count, 
                                      COALESCE(SUM(a.eco_points), 0) as total_points,
                                      COALESCE(AVG(a.eco_points), 0) as avg_points,
                                      COALESCE(MAX(a.eco_points), 0) as top_member_points
                               FROM organizations o
                               LEFT JOIN organization_members om ON o.org_id = om.org_id
                               LEFT JOIN accountstbl a ON om.account_id = a.account_id
                               WHERE 1=1 $location_filter
                               GROUP BY o.org_id, o.name, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting
                               ORDER BY total_points DESC, member_count DESC";

        $stmt = $connection->prepare($organizationsQuery);
        if (!empty($location_params)) {
            $stmt->bind_param($param_types, ...$location_params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $organizations = [];
        while ($org = $result->fetch_assoc()) {
            $org['is_full'] = $org['member_count'] >= $org['capacity_limit'];
            $org['is_current_user_org'] = $current_user_organization === $org['organization'];
            $organizations[] = $org;
        }
        $stmt->close();

        echo json_encode(['organizations' => $organizations]);
        break;

    case 'get_recommendations':
        // Get top 3 organization recommendations (based on total eco points first, then member count)
        $recommendationsQuery = "SELECT o.org_id, o.name as organization, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting,
                                      COUNT(DISTINCT om.account_id) as member_count, 
                                      COALESCE(SUM(a.eco_points), 0) as total_points,
                                      COALESCE(AVG(a.eco_points), 0) as avg_points,
                                      COALESCE(MAX(a.eco_points), 0) as top_member_points
                               FROM organizations o
                               LEFT JOIN organization_members om ON o.org_id = om.org_id
                               LEFT JOIN accountstbl a ON om.account_id = a.account_id
                               GROUP BY o.org_id, o.name, o.description, o.barangay, o.city_municipality, o.capacity_limit, o.privacy_setting
                               ORDER BY total_points DESC, member_count DESC, o.name ASC
                               LIMIT 3";

        $stmt = $connection->prepare($recommendationsQuery);
        $stmt->execute();
        $result = $stmt->get_result();

        $recommendations = [];
        while ($org = $result->fetch_assoc()) {
            $org['is_full'] = $org['member_count'] >= $org['capacity_limit'];
            $org['is_current_user_org'] = $current_user_organization === $org['organization'];
            $recommendations[] = $org;
        }
        $stmt->close();

        echo json_encode(['recommendations' => $recommendations]);
        break;

    case 'get_barangays':
        if (empty($_GET['city_municipality'])) {
            echo json_encode(['barangays' => []]);
            exit();
        }

        $cityMunicipality = $_GET['city_municipality'];
        $barangayQuery = "SELECT DISTINCT barangay FROM organizations WHERE city_municipality = ? ORDER BY barangay";
        $stmt = $connection->prepare($barangayQuery);
        $stmt->bind_param("s", $cityMunicipality);
        $stmt->execute();
        $result = $stmt->get_result();

        $barangays = [];
        while ($row = $result->fetch_assoc()) {
            $barangays[] = $row['barangay'];
        }
        $stmt->close();

        echo json_encode(['barangays' => $barangays]);
        break;

    case 'load_cities':
        // Load all available cities for dropdown
        include 'getdropdown.php';
        $cities = getcitymunicipality();
        $cityList = [];
        foreach ($cities as $city) {
            $cityList[] = $city['city'];
        }
        echo json_encode($cityList);
        break;

    case 'load_barangays':
        // Load barangays for a specific city
        if (empty($_GET['city'])) {
            echo json_encode([]);
            exit();
        }
        
        include 'getdropdown.php';
        $selectedCity = $_GET['city'];
        $barangaysJson = getBarangays($selectedCity);
        $barangaysData = json_decode($barangaysJson, true);
        
        $barangayList = [];
        if (is_array($barangaysData)) {
            foreach ($barangaysData as $barangay) {
                $barangayList[] = $barangay['barangay'];
            }
        }
        echo json_encode($barangayList);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>