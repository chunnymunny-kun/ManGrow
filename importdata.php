<?php
if(!session_id()){ 
    session_start(); 
} 

include 'database.php';
$res_status = $res_msg = ''; 
if (isset($_POST['importSubmit'])) {
    if (is_uploaded_file($_FILES['file']['tmp_name'])) {
        $csvFile = $_FILES['file']['tmp_name'];
        $csvMimes = array('text/x-csv', 'text/csv', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-comma-separated-values', 'text/comma-separated-values', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        if (!empty($_FILES['file']['name']) && in_array($_FILES['file']['type'], $csvMimes)) {
            $csvData = fopen($csvFile, 'r');
            fgetcsv($csvData); 

            while (($line = fgetcsv($csvData)) !== FALSE) {
                $firstname = $line[0];
                $lastname = $line[1];
                $email = $line[2];
                $password = $line[3];
                $barangay = $line[4];
                $city_municipality = $line[5];
                $accessrole = $line[6];
                $organization = $line[7];
                $is_verified = $line[8];

                // Prepare and execute the SQL query to insert data
                $stmt = $connection->prepare("INSERT INTO tempaccstbl (tempacc_id, firstname, lastname, email, password, barangay, city_municipality, accessrole, organization, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssssss", $tempacc_id, $firstname, $lastname, $email, $password, $barangay, $city_municipality, $accessrole, $organization, $is_verified);

                if (!$stmt->execute()) {
                    $error = true;
                    break;
                }
            }
            fclose($csvData);

            $res_status = 'success'; 
            $res_msg = 'Members data has been imported successfully.'; 
        }else{ 
            $res_status = 'danger'; 
            $res_msg = 'Please select a valid CSV file.'; 
        } 
    }else{ 
        $res_status = 'danger'; 
        $res_msg = 'Something went wrong, please try again.'; 
    } 
 
    // Store status in SESSION 
    $_SESSION['response'] = array( 
        'status' => $res_status, 
        'msg' => $res_msg 
    ); 
} 
 
// Redirect to the listing page 
header("Location: adminpage.php"); 
exit(); 
 
?>