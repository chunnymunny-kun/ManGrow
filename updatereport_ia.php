<?php
session_start();
require_once 'database.php';

if(!isset($_SESSION["user_id"])){
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Get form data
$report_id = intval($_POST['report_id']);
$incident_type = $_POST['incident_type'] ?? '';
$emergency = isset($_POST['emergency']) ? 'Emergency' : 'Normal'; // Changed to match ENUM
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';
$incident_datetime = $_POST['incident_datetime'] ?? '';
$description = $_POST['description'] ?? '';
$city_municipality = $_POST['city_municipality'] ?? '';
$area_no = $_POST['area_no'] ?? '';
$area_id = $_POST['area_id'] ?? '';
$contact_phone = $_POST['contact_phone'] ?? '';
$anonymous = isset($_POST['anonymous']) ? $_SESSION['user_id'] : null; // Set to user_id or NULL
$existing_evidence = $_POST['existing_evidence'] ?? [];
$edited_at = date('Y-m-d H:i:s');

try {
    // Handle file uploads
    $uploaded_files = [];
    if(!empty($_FILES['evidence']['name'][0])) {
        $upload_dir = 'uploads/illegal_reports/';
        if(!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        for($i = 0; $i < count($_FILES['evidence']['name']); $i++) {
            if($_FILES['evidence']['error'][$i] == UPLOAD_ERR_OK) {
                $file_name = basename($_FILES['evidence']['name'][$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_name = uniqid() . '.' . $file_ext;
                $file_path = $upload_dir . $new_name;
                
                if(move_uploaded_file($_FILES['evidence']['tmp_name'][$i], $file_path)) {
                    $uploaded_files[] = $file_path;
                }
            }
        }
    }
    
    // Combine existing and new files (keep max 3)
    $all_files = array_merge($existing_evidence, $uploaded_files);
    $all_files = array_slice($all_files, 0, 3);
    
    // Assign file paths to variables for binding
    $image_video1 = $all_files[0] ?? null;
    $image_video2 = $all_files[1] ?? null;
    $image_video3 = $all_files[2] ?? null;
    
    // Update report in database
    $query = "UPDATE illegalreportstbl SET 
                incident_type = ?,
                priority = ?,
                latitude = ?,
                longitude = ?,
                report_date = ?,
                description = ?,
                city_municipality = ?,
                area_no = ?,
                area_id = ?,
                contact_no = ?,
                reporter_id = ?,
                image_video1 = ?,
                image_video2 = ?,
                image_video3 = ?,
                edited_at = ?
              WHERE report_id = ?";
    
    $stmt = $connection->prepare($query);
    
    // Bind parameters with correct types
    $stmt->bind_param("ssddssssisissssi", 
        $incident_type,
        $emergency,
        $latitude,
        $longitude,
        $incident_datetime,
        $description,
        $city_municipality,
        $area_no,
        $area_id,
        $contact_phone,
        $anonymous, // This is now either user_id or NULL
        $image_video1,
        $image_video2,
        $image_video3,
        $edited_at,
        $report_id
    );
    
    if($stmt->execute()) {
        // Clear follow-up status
        $updateSql = "UPDATE illegalreportstbl
                    SET action_type = 'Received', follow_up_status = 'submitted', 
                        rejection_timestamp = NULL
                    WHERE report_id = ?";
        $updateStmt = $connection->prepare($updateSql);
        $updateStmt->bind_param("i", $report_id);
        $updateStmt->execute();
        
        // Prepare notification data
        $report_type = 'Illegal Activity Report';
        $account_id = $anonymous; // This is either user_id or NULL for anonymous
        $notif_date = date('Y-m-d H:i:s');
        $action_type = 'Received';
        $admin_notif_description = 'User has submitted a follow-up to their rejected report';
        $notif_description = 'Thank you for your follow-up submission. We will review it shortly.';
        $notifier_type = 'accountstbl';
        
        // Apply the same anonymous logic to notified_by
        $notified_by = isset($_POST['anonymous']) ? 0 : $_SESSION['user_id'];
        
        // Insert notification
        $sql = "INSERT INTO report_notifstbl (
            report_id, 
            account_id, 
            report_type,
            notif_date, 
            action_type, 
            admin_notif_description, 
            notif_description, 
            notified_by,
            notifier_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $connection->prepare($sql);
        $stmt->bind_param(
            "iisssssis", 
            $report_id,
            $account_id,
            $report_type,
            $notif_date,
            $action_type,
            $admin_notif_description,
            $notif_description,
            $notified_by, // Now follows the same anonymous logic
            $notifier_type
        );

        if ($stmt->execute()) {
            $_SESSION['response'] = [
                'status' => 'success',
                'msg' => 'Report updated and notification sent successfully'
            ];
        } else {
            $_SESSION['response'] = [
                'status' => 'warning',
                'msg' => 'Report updated but failed to send notification: ' . $stmt->error
            ];
        }
        $stmt->close();
        
        header("Location: reportspage.php");
        exit();
    }
    
    $stmt->close();
    header("Location: reportspage.php");
    exit();
    
} catch(Exception $e) {
    $_SESSION['response'] = [
        'status' => 'error',
        'msg' => 'Error: ' . $e->getMessage()
    ];
    header("Location: reportspage.php");
    exit();
}
?>