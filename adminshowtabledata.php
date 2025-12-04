<?php
header('Content-Type: application/json');

function filtertable($city = null, $barangay = null, $table = 'temp') {
    include 'database.php';
    session_start();

    $recordsPerPage = 15;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $recordsPerPage;

    try {
        if ($table === 'temp') {
            $query = "SELECT firstname, lastname, email, personal_email, barangay, city_municipality, 
                             accessrole, organization, is_verified, import_date, imported_by, tempacc_id 
                      FROM tempaccstbl 
                      WHERE tempacc_id IS NOT NULL AND tempacc_id != ''";
        } else {
            $query = "SELECT account_id, fullname, email, personal_email, barangay, city_municipality, 
                             accessrole, organization, date_registered, bio 
                      FROM accountstbl WHERE 1=1";
        }

        // Add access role filter if current user is a Barangay Official
        if (isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
            $barangay = $_SESSION['barangay'] ?? '';
            if (!empty($barangay)) {
                $query .= " AND barangay = '" . mysqli_real_escape_string($connection, $barangay) . "'";
            }
        } else {
            // Regular admin filters
            if (!empty($city)) {
                $citymunicipality = mysqli_real_escape_string($connection, $city);
                $query .= " AND city_municipality = '$citymunicipality'";
            }
            if (!empty($barangay)) {
                $cmbarangay = mysqli_real_escape_string($connection, $barangay);
                $query .= " AND barangay = '$cmbarangay'";
            }
        }

        $query .= " ORDER BY city_municipality ASC, accessrole ASC, barangay ASC";
        $query .= " LIMIT $recordsPerPage OFFSET $offset";

        $result = mysqli_query($connection, $query);

        if (!$result) {
            throw new Exception("Database error: " . mysqli_error($connection));
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if ($table !== 'temp' || (!empty($row['tempacc_id']))) {
                $data[] = $row;
            }
        }

        return $data;
    } catch (Exception $e) {
        http_response_code(500);
        return ['error' => $e->getMessage()];
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'filter') {
    $city = $_GET['city'] ?? null;
    $barangay = $_GET['barangay'] ?? null;
    $table = $_GET['table'] ?? 'temp';
    $page = $_GET['page'] ?? 1;

    $city = $city === '' ? null : $city;
    $barangay = $barangay === '' ? null : $barangay;

    $filteredData = filtertable($city, $barangay, $table);
    echo json_encode($filteredData);
    exit;
}
?>