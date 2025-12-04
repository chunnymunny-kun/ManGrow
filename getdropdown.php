<?php
    function getcitymunicipality(){
        include 'database.php';
        $query = "SELECT * FROM citymunicipalitytbl";
        $result = mysqli_query($connection,$query);
        while($row = $result->fetch_assoc()){
            $data[] = $row;
        }
        return $data;
    }

     if(isset($_GET["city"])){
         // Return a JSON array of barangays with a `barangay` property for the frontend
         header('Content-Type: application/json; charset=utf-8');
         echo getBarangays($_GET["city"]);
         exit;
     }


    function getBarangays($city){
        include 'database.php';
    
        // Set charset to UTF-8 to handle special characters
        mysqli_set_charset($connection, "utf8");
        
        // Sanitize the input
        $citymunicipality = mysqli_real_escape_string($connection, $city);
        
        $query = "SELECT * FROM barangaytbl WHERE city_municipality = '$citymunicipality'";
        $result = mysqli_query($connection, $query);
        
        if(!$result) {
            return json_encode(["error" => mysqli_error($connection)], JSON_UNESCAPED_UNICODE);
        }
        
        $data = [];
        while($row = $result->fetch_assoc()){
            // Normalize JSON keys expected by client-side code
            $data[] = [
                'barangay' => isset($row['barangay']) ? $row['barangay'] : (isset($row['barangay_name']) ? $row['barangay_name'] : ''),
                'city_municipality' => $row['city_municipality'] ?? ''
            ];
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

?>