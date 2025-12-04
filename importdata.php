<?php
if(!session_id()){
    session_start();
}

include 'database.php';

// Function to get current Philippine time
function getPhilippineTime() {
    $date = new DateTime("now", new DateTimeZone('Asia/Manila'));
    return $date->format('Y-m-d H:i:s');
}

$res_status = $res_msg = '';
if (isset($_POST['importSubmit'])) {

    if(isset($_SESSION['email'])){
        $_SESSION['administrator'] = $_SESSION['email'];
    }

    if (is_uploaded_file($_FILES['file']['tmp_name'])) {
        $csvFile = $_FILES['file']['tmp_name'];
        $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        if (!empty($_FILES['file']['name']) && in_array($_FILES['file']['type'], $csvMimes)) {
            // Check if the uploaded file name is exactly "AccountsList.csv"
            if ($_FILES['file']['name'] !== 'AccountsList.csv') {
                $_SESSION['response'] = [
                    'status' => 'error',
                    'msg' => 'Error: Only the master file is allowed for import.'
                ];
            } else {
                $csvData = fopen($csvFile, 'r');
                fgetcsv($csvData); // Skip header row

                // Array to track processed emails
                $processedEmails = array();
                $duplicateCount = 0;
                $importCount = 0;
                $error = false;
                $existingAccountCount = 0;

                // First get all existing emails from accountstbl
                $existingEmails = array();
                $getExistingQuery = $connection->query("SELECT email FROM accountstbl");
                while ($row = $getExistingQuery->fetch_assoc()) {
                    $existingEmails[] = strtolower($row['email']);
                }

                while (($line = fgetcsv($csvData)) !== FALSE) {
                    $filteredline = array_filter($line); // Filter out empty values

                    if(!empty($filteredline)){
                        $firstname = $line[0];
                        $lastname = $line[1];
                        $email = trim($line[2]); // Trim whitespace from email
                        $personal_email = $line[3];
                        $password = $line[4];
                        $barangay = $line[5];
                        $city_municipality = $line[6];
                        $accessrole = $line[7];
                        $organization = $line[8];
                        $is_verified = $line[9];
                        $import_date = getPhilippineTime();
                        $imported_by = $_SESSION["administrator"];

                        // Check if email exists in accountstbl
                        if (in_array(strtolower($email), $existingEmails)) {
                            $existingAccountCount++;
                            continue; // Skip this record
                        }

                        // Check if email is duplicate in current import
                        if(in_array(strtolower($email), array_map('strtolower', $processedEmails))) {
                            $duplicateCount++;
                            continue; // Skip this record
                        }

                        // Check if email exists in tempaccstbl
                        $checkQuery = $connection->prepare("SELECT email FROM tempaccstbl WHERE email = ?");
                        $checkQuery->bind_param("s", $email);
                        $checkQuery->execute();
                        $checkQuery->store_result();

                        if($checkQuery->num_rows > 0) {
                            $duplicateCount++;
                            $checkQuery->close();
                            continue; // Skip this record
                        }
                        $checkQuery->close();

                        // Prepare and execute the SQL query to insert data
                        $stmt = $connection->prepare("INSERT INTO tempaccstbl (firstname, lastname, email, personal_email, password, barangay, city_municipality, accessrole, organization, is_verified, import_date, imported_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssssssss", $firstname, $lastname, $email, $personal_email, $password, $barangay, $city_municipality, $accessrole, $organization, $is_verified, $import_date, $imported_by);

                        if ($stmt->execute()) {
                            $importCount++;
                            $processedEmails[] = $email; // Track successfully imported emails
                        } else {
                            $error = true;
                            break;
                        }
                        $stmt->close();
                    }
                }
                fclose($csvData);

                if (!$error) {
                    // Log the import activity
                    $phTime = getPhilippineTime();
                    $userSource = ($_SESSION['accessrole'] == 'Administrator') ? 'adminaccountstbl' : 'accountstbl';
                    
                    $activityQuery = $connection->prepare("
                        INSERT INTO account_activitytbl (
                            activity_date, 
                            action_type, 
                            user_id, 
                            user_role, 
                            import_count, 
                            activity_details
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    // Build detailed activity log
                    $activityDetails = "Import Summary:\n";
                    $activityDetails .= "----------------\n";
                    $activityDetails .= "Imported by: " . $_SESSION['accessrole'] . " " . $_SESSION['name'] . "\n";
                    $activityDetails .= "Source file: " . $_FILES['file']['name'] . "\n";
                    $activityDetails .= "Import date: " . $phTime . "\n";
                    $activityDetails .= "Total records processed: " . ($importCount + $duplicateCount + $existingAccountCount) . "\n";
                    $activityDetails .= "Successfully imported: " . $importCount . " account(s)\n";

                    if ($duplicateCount > 0) {
                        $activityDetails .= "Duplicate records skipped: " . $duplicateCount . "\n";
                    }

                    if ($existingAccountCount > 0) {
                        $activityDetails .= "Existing accounts skipped: " . $existingAccountCount . "\n";
                    }

                    // Add sample of imported accounts (first 5 for reference)
                    if ($importCount > 0) {
                        $activityDetails .= "\nSample of imported accounts (first 5):\n";
                        $sampleQuery = $connection->query("
                            SELECT firstname, lastname, email, accessrole 
                            FROM tempaccstbl 
                            WHERE imported_by = '".$_SESSION['administrator']."' 
                            AND import_date = '".getPhilippineTime()."'
                            ORDER BY tempacc_id DESC 
                            LIMIT 5
                        ");
                        
                        $sampleCount = 0;
                        while ($sample = $sampleQuery->fetch_assoc()) {
                            $sampleCount++;
                            $activityDetails .= $sampleCount . ". " . $sample['firstname'] . " " . $sample['lastname'] . 
                                            " (" . $sample['email'] . ") - " . $sample['accessrole'] . "\n";
                        }
                        
                        if ($importCount > 5) {
                            $activityDetails .= "... plus " . ($importCount - 5) . " more accounts\n";
                        }
                    }

                    $actionType = 'Imported';


                    $activityQuery->bind_param(
                        "ssisss", 
                        $phTime,
                        $actionType,
                        $_SESSION['user_id'],
                        $userSource,
                        $importCount,
                        $activityDetails
                    );
                    
                    if(!$activityQuery->execute()) {
                        // Log error if activity tracking fails
                        error_log("Failed to log import activity: " . $connection->error);
                    }
                    $activityQuery->close();

                    $msg = "$importCount records imported successfully.";
                    if($duplicateCount > 0) {
                        $msg .= " $duplicateCount duplicates skipped.";
                    }
                    if($existingAccountCount > 0) {
                        $msg .= " $existingAccountCount existing accounts skipped.";
                    }
                    $_SESSION['response'] = [
                        'status' => 'success',
                        'msg' => $msg
                    ];
                } else {
                    $_SESSION['response'] = [
                        'status' => 'error',
                        'msg' => 'Error importing some records. Please check your CSV file.'
                    ];
                }
            }
        } else {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'Please select a valid CSV file.'
            ];
        }
    } else {
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Something went wrong, please try again.'
        ];
    }
}

// Redirect to admin page
header("Location: adminaccspage.php");
exit();
?>