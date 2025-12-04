<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Database connection
require_once 'database.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process form data
        $reporter_id = isset($_POST['anonymous']) && $_POST['anonymous'] == '1' ? null : ($_SESSION['user_id'] ?? null);
        $report_type = 'Mangrove Data Report';
        $report_date = $_POST['date_recorded'] ?? date('Y-m-d H:i:s');
        
        // Process species data - handle both old single species and new multiple species format
        $species_data = $_POST['species'] ?? '';
        if (!empty($species_data)) {
            // Check if it's JSON (new multiple species format)
            $decoded_species = json_decode($species_data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_species)) {
                // New format: JSON array of species
                $species = implode(', ', array_map(function($item) use ($connection) {
                    return mysqli_real_escape_string($connection, $item);
                }, $decoded_species));
            } else {
                // Old format: single species string
                $species = mysqli_real_escape_string($connection, $species_data);
            }
        } else {
            $species = '';
        }
        
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : null;
        $area_no = isset($_POST['area_no']) ? mysqli_real_escape_string($connection, $_POST['area_no']) : null;
        $city_municipality = isset($_POST['city_municipality']) ? mysqli_real_escape_string($connection, $_POST['city_municipality']) : null;
        $latitude = floatval($_POST['latitude'] ?? 0);
        $longitude = floatval($_POST['longitude'] ?? 0);
        $address = isset($_POST['address']) ? mysqli_real_escape_string($connection, $_POST['address']) : null;
        
        // Process barangays data
        $barangays = '';
        if (isset($_POST['barangays']) && !empty($_POST['barangays'])) {
            $barangays_array = json_decode($_POST['barangays'], true);
            if (is_array($barangays_array)) {
                $barangays = implode(', ', array_map(function($item) use ($connection) {
                    return mysqli_real_escape_string($connection, $item);
                }, $barangays_array));
            }
        }
        
        $mangrove_status = mysqli_real_escape_string($connection, $_POST['mangrove_status'] ?? '');
        $area_m2 = intval($_POST['area_planted'] ?? 100);
        $remarks = mysqli_real_escape_string($connection, $_POST['remarks'] ?? '');
        
        // Forest metrics data
        $forest_cover = isset($_POST['forest_cover']) ? floatval($_POST['forest_cover']) : null;
        $canopy_density = isset($_POST['canopy_density']) ? floatval($_POST['canopy_density']) : null;
        $tree_count = isset($_POST['tree_count']) && !empty($_POST['tree_count']) ? intval($_POST['tree_count']) : null;
        
        // Calculate tree density if tree count is provided
        $calculated_density = null;
        if ($tree_count !== null && $area_m2 > 0) {
            // Calculate trees per hectare (10,000 m²)
            $calculated_density = ($tree_count / $area_m2) * 10000;
        }
        
        $created_at = date('Y-m-d H:i:s');

        // Handle file uploads
        $image1 = $image2 = $image3 = null;
        $image_paths = [];
        if(!empty($_FILES['images']['name'][0])) {
            $uploadDir = 'uploads/mangrove_reports/';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                    throw new Exception('Failed to create upload directory.');
                }
            }

            for ($i = 0; $i < min(3, count($_FILES['images']['name'])); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validate file size (5MB max)
                    if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                        throw new Exception('File "'.$_FILES['images']['name'][$i].'" exceeds 5MB limit');
                    }

                    // Validate file type
                    if (!preg_match('/^image\//', $_FILES['images']['type'][$i])) {
                        throw new Exception('File "'.$_FILES['images']['name'][$i].'" is not a valid image');
                    }

                    $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $dest)) {
                        ${'image'.($i+1)} = mysqli_real_escape_string($connection, $dest);
                        $image_paths[] = $dest;
                    } else {
                        throw new Exception('Failed to upload image: ' . $_FILES['images']['name'][$i]);
                    }
                } elseif ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception('File upload error: ' . $_FILES['images']['error'][$i]);
                }
            }
        }

        // Insert into database
        $query = "INSERT INTO mangrovereporttbl (
            reporter_id, report_type, report_date, species, area_no, city_municipality,
            barangays, latitude, longitude, address, mangrove_status, area_m2, area_id,
            forest_cover_percent, canopy_density_percent, tree_count, calculated_density,
            image1, image2, image3, remarks, created_at
          ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
          )";

        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $connection->error);
        }

        $stmt->bind_param(
            "issssssddssdiddidsssss",
            $reporter_id,
            $report_type,
            $report_date,
            $species,
            $area_no, 
            $city_municipality,
            $barangays,
            $latitude,
            $longitude,
            $address,
            $mangrove_status,
            $area_m2,
            $area_id,
            $forest_cover,
            $canopy_density,
            $tree_count,
            $calculated_density,
            $image1,
            $image2,
            $image3,
            $remarks,
            $created_at
        );

        if ($stmt->execute()) {
            $report_id = $stmt->insert_id;
            
            // Insert into userreportstbl for tracking (for all reports)
            $userReportQuery = "INSERT INTO userreportstbl (report_id, account_id, report_type) 
                            VALUES (?, ?, ?)";
            $userReportStmt = $connection->prepare($userReportQuery);
            $userReportStmt->bind_param(
                "iis", 
                $report_id, 
                $_SESSION['user_id'],
                $report_type
            );
            $userReportStmt->execute();
            $userReportStmt->close();

            // Get reporter's name if available
            $reporterName = 'Anonymous';
            if ($reporter_id !== null) {
                $getNameQuery = "SELECT fullname FROM accountstbl WHERE account_id = ?";
                $getNameStmt = $connection->prepare($getNameQuery);
                $getNameStmt->bind_param("i", $reporter_id);
                $getNameStmt->execute();
                $getNameResult = $getNameStmt->get_result();
                
                if ($getNameResult->num_rows > 0) {
                    $reporterRow = $getNameResult->fetch_assoc();
                    $reporterName = $reporterRow['fullname'];
                }
                $getNameStmt->close();
            }

            // Prepare values for report_notifstbl
            $notif_date = date('Y-m-d H:i:s');
            $action_type = 'Received';
            $admin_notif_description = "Received new ".$report_type." from: " . $reporterName;
            $notif_description = "Your report has been received by the management";
            $notified_by = 0; // System-generated notification
            $notifier_type = 'adminaccountstbl';

            // Insert into report_notifstbl
            $notifQuery = "INSERT INTO report_notifstbl (
                report_id, account_id, report_type, notif_date, action_type, 
                admin_notif_description, notif_description, 
                notified_by, notifier_type
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $notifStmt = $connection->prepare($notifQuery);
            $notifStmt->bind_param(
                "iisssssis", 
                $report_id,
                $reporter_id,
                $report_type,
                $notif_date,
                $action_type,
                $admin_notif_description,
                $notif_description,
                $notified_by,
                $notifier_type
            );
            $notifStmt->execute();
            $notifStmt->close();

            // Prepare data for JSON
            $report_data = [
                'type' => 'Feature',
                'properties' => [
                    'report_id' => $stmt->insert_id,
                    'reporter_id' => $reporter_id,
                    'report_type' => $report_type,
                    'report_date' => $report_date,
                    'species' => $species,
                    'area_no' => $area_no,
                    'city_municipality' => $city_municipality,
                    'barangays' => $barangays,
                    'address' => $address,
                    'mangrove_status' => $mangrove_status,
                    'area_m2' => $area_m2,
                    'remarks' => $remarks,
                    'created_at' => $created_at,
                    'images' => $image_paths
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$longitude, $latitude] // Note: long,lat format
                ]
            ];

            // Save to JSON file
            $json_file = 'mangrovereports.json';
            $existing_data = [];
            
            if (file_exists($json_file)) {
                $existing_data = json_decode(file_get_contents($json_file), true);
                if (!isset($existing_data['type']) || $existing_data['type'] !== 'FeatureCollection') {
                    $existing_data = [
                        'type' => 'FeatureCollection',
                        'features' => []
                    ];
                }
            } else {
                $existing_data = [
                    'type' => 'FeatureCollection',
                    'features' => []
                ];
            }

            // Add new report
            $existing_data['features'][] = $report_data;

            // Save back to file
            if (file_put_contents($json_file, json_encode($existing_data, JSON_PRETTY_PRINT))) {
                $_SESSION['response'] = [
                    'status' => 'success',
                    'msg' => 'Report submitted successfully!'
                ];
            } else {
                throw new Exception('Failed to save report to JSON file');
            }
        } else {
            throw new Exception('Error submitting report: ' . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error: ' . $e->getMessage()
        ];
        error_log("Report submission error: " . $e->getMessage());
    }

    header("Location: index.php");
    exit();
}