<?php include 'adminheader.php'; ?>
<?php 

if(!session_id()) {
    session_start();
}
include 'database.php';

    $statusType = '';
    $statusMsg = '';

    if(!empty($_SESSION['response'])){ 
        $status = $_SESSION['response']['status']; 
        $statusMsg = $_SESSION['response']['msg']; 
        unset($_SESSION['response']); 
    } 
    ?>
    
    <!-- Display status message -->
    <?php if(!empty($statusMsg)){ ?>
    <div class="col-xs-12">
        <div class="alert alert-<?php echo $status; ?>"><?php echo $statusMsg; ?></div>
    </div>
    <?php } ?>
    <div class="profile-details close" id="profile-details">
            <div class ="details-box">
            <h2><?php 
            if(isset($_SESSION["name"])){
                $loggeduser = $_SESSION["name"];
                echo $loggeduser; 
            }else{
                echo "";
            }
            ?></h2>
            <p><?php
             if(isset($_SESSION["email"])){
                $email = $_SESSION["email"];
                echo $email;
            }else{
                echo "";
            }
             ?></p>
            <p><?php
            if(isset($_SESSION["accessrole"])){
                $accessrole = $_SESSION["accessrole"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            </div>
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out</button>
        </div>    
<h1>Users</h1>
<div class="button-box">    
    <div class="col-md-7">
        <form action ="" method="GET">
            <div class ="input-group mb-3">
                <input type="text" class="form-control" name="search" placeholder="Search user" value="<?php if(isset($_GET['search'])){echo $_GET['search'];}?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
    <div class="filter-box">
    <button class="btn btn-primary" id="add-user-btn" data-toggle="modal" data-target="#addUserModal">Import Users</button>
    </div>
</div>

<table class="table table-hover table-bordered table-striped">
    <thead>
        <tr>
            <th>Temporary Account ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Password</th>
            <th>Barangay</th>
            <th>City/Municipality</th>
            <th>Access Role</th>
            <th>Organization</th>
            <th>Is Verified</th>
        </tr>
    </thead>
    <tbody id="user-table-body">
        <?php
        $query = "SELECT * FROM tempaccstbl";
        $result = mysqli_query($connection, $query);
        if (isset($_GET['search'])) {
            $filtervalues = $_GET['search'];
            $query = "SELECT * FROM tempaccstbl where CONCAT(firstname, lastname, email, password, barangay, city_municipality, accessrole, organization, is_verified) LIKE '%$filtervalues%' ";
            $result = mysqli_query($connection, $query);

            if(mysqli_num_rows($result) > 0){
                foreach($result as $items){
                    ?>
                    <tr>
                        <td><?php echo $items['tempacc_id']; ?></td>
                        <td><?php echo $items['firstname']; ?></td>
                        <td><?php echo $items['lastname']; ?></td>
                        <td><?php echo $items['email']; ?></td>
                        <td><?php echo $items['password']; ?></td>
                        <td><?php echo $items['barangay']; ?></td>
                        <td><?php echo $items['city_municipality']; ?></td>
                        <td><?php echo $items['accessrole']; ?></td>
                        <td><?php echo $items['organization']; ?></td>
                        <td><?php echo $items['is_verified']; ?></td>
                    </tr>
                    <?php
                }
            }else{
                ?>
                <tr>
                    <td colspan="10" class="text-center">No Record Found</td>
                <?php
            }
        }
        if (!$result) {
            die("Query Failed: " . mysqli_error($connection));
        } else {
            while ($row = mysqli_fetch_assoc($result)) {
                ?>
                <tr>
                    <td><?php echo $row['tempacc_id']; ?></td>
                    <td><?php echo $row['firstname']; ?></td>
                    <td><?php echo $row['lastname']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['password']; ?></td>
                    <td><?php echo $row['barangay']; ?></td>
                    <td><?php echo $row['city_municipality']; ?></td>
                    <td><?php echo $row['accessrole']; ?></td>
                    <td><?php echo $row['organization']; ?></td>
                    <td><?php echo $row['is_verified']; ?></td>
                </tr>
                <?php
            }
        }
        ?>
    </tbody>
</table>

<div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add user accounts</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">X</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="col-md-12" id="ImportForm">
                    <form action="importdata.php" method="post" enctype="multipart/form-data">
                        <input type="file" name="file" class="filefind"/>
                        <input type="submit" name="importSubmit" value="IMPORT" class="btn btn-primary">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'adminfooter.php'; ?>