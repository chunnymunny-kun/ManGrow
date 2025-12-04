<?php

use Dom\Mysql;
/*
class MyDatabase{
    private $host;
    private $fullname;
    private $password;
    private $accessrole;
    private $database;
    private $link;


    function __construct(){
        $this->host = "localhost";
        $this->fullname = "root";
        $this->password = "";
        $this->database = "mangrowdb";
        $this->link = mysqli_connect($this->host,$this->fullname,$this->password,$this->accessrole,$this->database);
    
        if(mysqli_connect_errno()){
            $log = "MySQL Error Message " . mysqli_connect_error();
            exit($log);
        }
    }

    function __destruct(){
        if(isset($this->link)){
            mysqli_close($this->link);
        }
    }

    public function customers_record(){
        $qstr = "SELECT * FROM mangrowdb";
        $result = mysqli_query($this->link,$qstr);
        $records = array();

        if(mysqli_num_rows($result) > 0){
            while($row = mysqli_fetch_assoc($result)){
                $records[] = [
                    'id' => $row['id'],
                    'fullname' => $row['fullname'],
                    'email' => $row['email'],
                    'accessrole' => $row['accessrole']
                ];
            }
        }else{
            $records = null;
        }

        mysqli_free_result($result);
        return $records;
    }
}

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'mangrowdb');

// Create a connection to the database
$connection = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

// Check if the connection was successful
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}
*/
//$connection = mysqli_connect("localhost","root","","test");
//$connection = mysqli_connect("localhost","root","","mangrowdb");

// Create connection
$connection = mysqli_connect("localhost","root","","mangrowdb");

// No 
//$connection = mysqli_connect("mangrow.42web.io","if0_38793238","JAo4PFGpWqZmc","if0_38793238_mangrowdb");

/**
 * Checks and updates expired follow-up reports
 */
?>
