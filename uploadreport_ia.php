<?php
session_start();
date_default_timezone_set('Asia/Manila');

// Database connection
require_once 'database.php';
include 'badge_system_db.php'; // Include badge system

// Initialize badge system
BadgeSystem::init($connection);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Process form data
        $reporter_id = isset($_POST['anonymous']) && $_POST['anonymous'] == '1' ? null : ($_SESSION['user_id'] ?? null);
        $report_type = 'Illegal Activity Report';
        $report_date = $_POST['incident_datetime'] ?? date('Y-m-d H:i:s');
        $priority = isset($_POST['emergency']) && $_POST['emergency'] == 'on' ? 'Emergency' : 'Normal';
        $incident_type = mysqli_real_escape_string($connection, $_POST['incident_type'] ?? '');
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
        
        $description = mysqli_real_escape_string($connection, $_POST['description'] ?? '');
        $contact_no = isset($_POST['contact_phone']) ? mysqli_real_escape_string($connection, $_POST['contact_phone']) : null;
        $created_at = date('Y-m-d H:i:s');
        $consent = isset($_POST['consent']) ? 1 : 0;

        // Validate required fields
        if (!$consent) {
            throw new Exception('You must confirm that the report is made in good faith.');
        }

        if (empty($incident_type)) {
            throw new Exception('Incident type is required.');
        }

        // Handle file uploads
        $image_video1 = $image_video2 = $image_video3 = null;
        $image_paths = [];
        
        if (!empty($_FILES['evidence']['name'][0])) {
            $uploadDir = 'uploads/illegal_reports/';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                    throw new Exception('Failed to create upload directory.');
                }
            }

            // Process up to 3 files
            for ($i = 0; $i < min(3, count($_FILES['evidence']['name'])); $i++) {
                if ($_FILES['evidence']['error'][$i] === UPLOAD_ERR_OK) {
                    // Validate file size (25MB max)
                    if ($_FILES['evidence']['size'][$i] > 25 * 1024 * 1024) {
                        throw new Exception('File "'.$_FILES['evidence']['name'][$i].'" exceeds 25MB limit');
                    }

                    // Validate file type
                    $file_type = $_FILES['evidence']['type'][$i];
                    if (!preg_match('/^(image|video)\//', $file_type)) {
                        throw new Exception('File "'.$_FILES['evidence']['name'][$i].'" is not a valid image or video');
                    }

                    // Generate unique filename
                    $ext = pathinfo($_FILES['evidence']['name'][$i], PATHINFO_EXTENSION);
                    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $uploadDir . $filename;

                    if (move_uploaded_file($_FILES['evidence']['tmp_name'][$i], $dest)) {
                        ${'image_video'.($i+1)} = mysqli_real_escape_string($connection, $dest);
                        $image_paths[] = $dest;
                    } else {
                        throw new Exception('Failed to upload file: ' . $_FILES['evidence']['name'][$i]);
                    }
                } elseif ($_FILES['evidence']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    throw new Exception('File upload error: ' . $_FILES['evidence']['error'][$i]);
                }
            }
        }

        // Insert into database
        $query = "INSERT INTO illegalreportstbl (
            reporter_id, report_type, report_date, priority, incident_type,
            area_no, city_municipality, barangays, area_id, latitude, longitude, address,
            description, image_video1, image_video2, image_video3, contact_no, created_at
          ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
          )";

        $stmt = $connection->prepare($query);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $connection->error);
        }

        $stmt->bind_param(
            "isssssssidssssssss",
            $reporter_id,
            $report_type,
            $report_date,
            $priority,
            $incident_type,
            $area_no,
            $city_municipality,
            $barangays,
            $area_id, 
            $latitude,
            $longitude,
            $address,
            $description,
            $image_video1,
            $image_video2,
            $image_video3,
            $contact_no,
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

            // Award badges for illegal activity reporting
            if ($reporter_id !== null) {
                // Create badge notifications table if it doesn't exist
                $createTableQuery = "CREATE TABLE IF NOT EXISTS badge_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    badge_name VARCHAR(100) NOT NULL,
                    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_badge (user_id, badge_name)
                )";
                $connection->query($createTableQuery);

                // Count user's total illegal activity reports
                $countQuery = "SELECT COUNT(*) as count FROM illegalreportstbl WHERE reporter_id = ?";
                $countStmt = $connection->prepare($countQuery);
                $countStmt->bind_param("i", $reporter_id);
                $countStmt->execute();
                $countResult = $countStmt->get_result();
                $reportCount = $countResult->fetch_assoc()['count'];
                $countStmt->close();

                // Award badges based on report count
                $badgesToAward = [];
                
                if ($reportCount == 1) {
                    $badgesToAward[] = 'Mangrove Guardian';
                } elseif ($reportCount == 5) {
                    $badgesToAward[] = 'Watchful Eye';
                } elseif ($reportCount == 10) {
                    $badgesToAward[] = 'Vigilant Protector';
                } elseif ($reportCount == 20) {
                    $badgesToAward[] = 'Conservation Champion';
                } elseif ($reportCount == 50) {
                    $badgesToAward[] = 'Ecosystem Sentinel';
                } elseif ($reportCount == 100) {
                    $badgesToAward[] = 'Mangrove Legend';
                }

                // Award the badges and set session flag for immediate notification
                foreach ($badgesToAward as $badgeName) {
                    BadgeSystem::awardBadgeToUser($reporter_id, $badgeName);
                    
                    // Set session flag for immediate badge notification if this user is currently logged in
                    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $reporter_id) {
                        $_SESSION['new_badge_awarded'] = [
                            'badge_awarded' => true,
                            'badge_name' => $badgeName,
                            'badge_description' => getBadgeDescription($badgeName)
                        ];
                        break; // Only show one notification at a time
                    }
                }
            }

            $report_data = [
                'type' => 'Feature',
                'properties' => [
                    'report_id' => $stmt->insert_id,
                    'reporter_id' => $reporter_id,
                    'report_type' => $report_type,
                    'report_date' => $report_date,
                    'priority' => $priority,
                    'incident_type' => $incident_type,
                    'area_no' => $area_no,
                    'city_municipality' => $city_municipality,
                    'barangays' => $barangays,
                    'address' => $address,
                    'description' => $description,
                    'contact_no' => $contact_no,
                    'created_at' => $created_at,
                    'media' => $image_paths
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$longitude, $latitude]
                ]
            ];

            // Save to JSON file (optional)
            $json_file = 'illegalreports.json';
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

            $existing_data['features'][] = $report_data;
            file_put_contents($json_file, json_encode($existing_data, JSON_PRETTY_PRINT));

            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Illegal activity report submitted successfully!'
            ];
        } else {
            throw new Exception('Error submitting report: ' . $stmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Error: ' . $e->getMessage()
        ];
        error_log("Illegal report submission error: " . $e->getMessage());
    }

    header("Location: index.php");
    exit();
}

// Helper function to get badge descriptions
function getBadgeDescription($badgeName) {
    $descriptions = [
        'Mangrove Guardian' => 'Awarded for vigilantly protecting mangrove ecosystems by reporting your first illegal activity!',
        'Watchful Eye' => 'Congratulations! You\'ve reported 5 illegal activities and earned the Watchful Eye badge!',
        'Vigilant Protector' => 'Excellent work! Your vigilance in reporting 10 illegal activities has earned you this badge!',
        'Conservation Champion' => 'Outstanding! You\'ve reported 20 illegal activities and are now a Conservation Champion!',
        'Ecosystem Sentinel' => 'Incredible dedication! You\'ve reported 50 illegal activities as an Ecosystem Sentinel!',
        'Mangrove Legend' => 'Legendary achievement! You\'ve reported 100 illegal activities and become a Mangrove Legend!'
    ];
    
    return isset($descriptions[$badgeName]) ? $descriptions[$badgeName] : 'Congratulations on earning this badge!';
}