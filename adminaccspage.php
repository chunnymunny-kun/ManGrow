<?php
    session_start();
    include 'database.php';
    if(isset($_SESSION["accessrole"])){
        // Check if role is NOT in allowed list
        if($_SESSION["accessrole"] != 'Administrator' && 
           $_SESSION["accessrole"] != 'Representative' &&
           $_SESSION["accessrole"] != 'Barangay Official') {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'This account is not authorized to enter this page'
            ];
            header("Location: index.php");
            exit();
        }
        
        // Set variables if logged in with proper role
        if(isset($_SESSION["name"])){
            $email = $_SESSION["name"];
        }
        if(isset($_SESSION["accessrole"])){
            $accessrole = $_SESSION["accessrole"];
        }
    } else {
        // No accessrole set at all - redirect to login
        $_SESSION['response'] = [
            'status' => 'error',
            'msg' => 'Please login first'
        ];
        header("Location: index.php");
        exit();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts</title>
    <link rel="stylesheet" href="adminpage.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="adminusers.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
</head>
<body data-accessrole="<?php echo htmlspecialchars($_SESSION['accessrole'] ?? ''); ?>" 
      data-barangay="<?php echo htmlspecialchars($_SESSION['barangay'] ?? ''); ?>">
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li><a href="adminpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
                <?php if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Administrator"){ ?>
                <li><a href="adminprofile.php"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="201" zoomAndPan="magnify" viewBox="0 0 150.75 150.749998" height="201" preserveAspectRatio="xMidYMid meet" version="1.2"><defs><clipPath id="ecb5093e1a"><path d="M 36 33 L 137 33 L 137 146.203125 L 36 146.203125 Z M 36 33 "/></clipPath><clipPath id="7aa2aa7a4d"><path d="M 113 3.9375 L 130 3.9375 L 130 28 L 113 28 Z M 113 3.9375 "/></clipPath><clipPath id="a75b8a9b8d"><path d="M 123 25 L 149.75 25 L 149.75 40 L 123 40 Z M 123 25 "/></clipPath></defs><g id="bfd0c68d80"><g clip-rule="nonzero" clip-path="url(#ecb5093e1a)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 86.320312 96.039062 C 85.785156 96.039062 85.28125 96.101562 84.746094 96.117188 C 82.28125 85.773438 79.214844 77.128906 75.992188 70 C 81.976562 63.910156 102.417969 44.296875 120.019531 41.558594 L 118.824219 33.851562 C 100.386719 36.722656 80.566406 54.503906 72.363281 62.589844 C 64.378906 47.828125 56.628906 41.664062 56.117188 41.265625 L 51.332031 47.421875 C 51.503906 47.554688 68.113281 61.085938 76.929688 96.9375 C 53.460938 101.378906 36.265625 121.769531 36.265625 146.089844 L 44.0625 146.089844 C 44.0625 125.53125 58.683594 108.457031 78.554688 104.742188 C 79.078125 107.402344 79.542969 110.105469 79.949219 112.855469 C 64.179688 115.847656 52.328125 129.613281 52.328125 146.089844 L 60.125 146.089844 C 60.125 132.257812 70.914062 120.78125 84.925781 119.941406 C 85.269531 119.898438 85.617188 119.894531 85.964844 119.894531 C 100.269531 119.960938 112.4375 131.527344 112.4375 146.089844 L 120.234375 146.089844 C 120.234375 127.835938 105.769531 113.007812 87.742188 112.242188 C 87.335938 109.386719 86.835938 106.601562 86.300781 103.835938 C 86.304688 103.835938 86.3125 103.832031 86.320312 103.832031 C 109.578125 103.832031 128.5 122.789062 128.5 146.089844 L 136.292969 146.089844 C 136.292969 118.488281 113.875 96.039062 86.320312 96.039062 Z M 86.320312 96.039062 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 87.175781 42.683594 C 94.929688 24.597656 76.398438 17.925781 76.398438 17.925781 C 68.097656 39.71875 87.175781 42.683594 87.175781 42.683594 Z M 87.175781 42.683594 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 63.292969 4.996094 C 43.0625 16.597656 55.949219 30.980469 55.949219 30.980469 C 73.40625 21.898438 63.292969 4.996094 63.292969 4.996094 Z M 63.292969 4.996094 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 49.507812 41.8125 C 50.511719 22.160156 30.816406 22.328125 30.816406 22.328125 C 30.582031 45.644531 49.507812 41.8125 49.507812 41.8125 Z M 49.507812 41.8125 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 0.0664062 34.476562 C 13.160156 53.773438 26.527344 39.839844 26.527344 39.839844 C 16.152344 23.121094 0.0664062 34.476562 0.0664062 34.476562 Z M 0.0664062 34.476562 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 45.871094 53.867188 C 30.757812 41.269531 19.066406 57.117188 19.066406 57.117188 C 37.574219 71.304688 45.871094 53.867188 45.871094 53.867188 Z M 45.871094 53.867188 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 54.132812 66.046875 C 34.511719 64.550781 34.183594 84.246094 34.183594 84.246094 C 57.492188 85.0625 54.132812 66.046875 54.132812 66.046875 Z M 54.132812 66.046875 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 99.984375 31.394531 C 115.226562 18.949219 101.886719 4.457031 101.886719 4.457031 C 84.441406 19.933594 99.984375 31.394531 99.984375 31.394531 Z M 99.984375 31.394531 "/><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 118.015625 75.492188 C 118.144531 52.171875 99.234375 56.085938 99.234375 56.085938 C 98.320312 75.742188 118.015625 75.492188 118.015625 75.492188 Z M 118.015625 75.492188 "/><g clip-rule="nonzero" clip-path="url(#7aa2aa7a4d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 128.433594 3.9375 C 106.042969 10.457031 115.183594 27.46875 115.183594 27.46875 C 134.289062 22.742188 128.433594 3.9375 128.433594 3.9375 Z M 128.433594 3.9375 "/></g><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 113.792969 48.433594 C 120.164062 67.050781 138.386719 59.582031 138.386719 59.582031 C 129.9375 37.84375 113.792969 48.433594 113.792969 48.433594 Z M 113.792969 48.433594 "/><g clip-rule="nonzero" clip-path="url(#a75b8a9b8d)"><path style=" stroke:none;fill-rule:nonzero;fill:#ffffff;fill-opacity:1;" d="M 123.667969 35.515625 C 140.066406 46.394531 149.960938 29.367188 149.960938 29.367188 C 130.015625 17.28125 123.667969 35.515625 123.667969 35.515625 Z M 123.667969 35.515625 "/></g></g></svg></a></li>
                <li><a href="adminleaderboards.php"><i class="far fa-chart-bar" style="margin-bottom:-5px"></i></a></li>
                <?php } ?>
            </ul>
        </nav>
        
        <?php    
        echo '<div class="userbox" onclick="LoginToggle();">';
        if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
            echo '<img src="'.htmlspecialchars($_SESSION['profile_image']).'" alt="Profile Image" class="profile-icon">';
        } else {
            echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
        }
        echo '</div>';
        ?>
    </header>
    <main>
        <div class ="table-container">
<?php
$recordsPerPage = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $recordsPerPage;

// For temporary accounts
$tempQuery = "SELECT * FROM tempaccstbl";
if (isset($_GET['search'])) {
    $filtervalues = $_GET['search'];
    $tempQuery .= " WHERE CONCAT(firstname, lastname, email, personal_email, barangay, city_municipality, accessrole, organization, is_verified, import_date, imported_by) LIKE '%$filtervalues%'";
}
if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
    $tempQuery .= " WHERE barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'";
}
$tempQuery .= " LIMIT $recordsPerPage OFFSET $offset";

// For verified accounts
$verifiedQuery = "SELECT account_id, fullname, email, personal_email, barangay, city_municipality, accessrole, organization, date_registered, bio FROM accountstbl";
if (isset($_GET['search'])) {
    $filtervalues = $_GET['search'];
    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
    $verifiedQuery .= " WHERE barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'";
}
    $verifiedQuery .= " WHERE CONCAT(fullname, email, personal_email, barangay, city_municipality, accessrole, organization, date_registered, bio) LIKE '%$filtervalues%'";
}
$verifiedQuery .= " LIMIT $recordsPerPage OFFSET $offset";
?>
<?php 
    $statusType = '';
    $statusMsg = '';
    //<!-- Flash Message Display -->
    if(!empty($_SESSION['response'])): ?>
    <div class="flash-container">
        <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
            <?= $_SESSION['response']['msg'] ?>
        </div>
    </div>
    <?php 
    unset($_SESSION['response']); 
    endif; 
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
            <p><?php
            if(isset($_SESSION["organization"])){
                $accessrole = $_SESSION["organization"];
                echo $accessrole;
            }else{
                echo "";
            }
            ?></p>
            <?php
            if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"):
                if(isset($_SESSION["barangay"])){
                    $accessrole = $_SESSION["barangay"];
                }
                if(isset($_SESSION['city_municipality'])){
                    $city_municipality = $_SESSION['city_municipality'];
                }
                ?><p>Barangay <?php echo $accessrole ?>, <?php echo $city_municipality ?></p>
            <?php endif;
            ?>
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
            
        </div>    
        <div class="account-heading">
            <h1>User Accounts</h1>
        </div>
        <?php if(isset($_SESSION["accessrole"]) && ($_SESSION["accessrole"] == "Administrator") || $_SESSION["accessrole"] == "Representative"): ?>
            <div class="admin-div-container">
                <div class="history-logs">
                    <div class="activity-card import-card">
                        <div class="card-header">
                            <i class="fas fa-file-import"></i>
                            <h3>Imports</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            // Get total import count and stats
                            $importsQuery = "SELECT 
                                                COUNT(*) as count, 
                                                SUM(import_count) as total_imported
                                            FROM account_activitytbl 
                                            WHERE action_type = 'Imported'";
                            $importsResult = mysqli_query($connection, $importsQuery);
                            $importData = mysqli_fetch_assoc($importsResult);
                            
                            // Get up to 3 most recent imports
                            $recentQuery = "SELECT 
                                            activity_details, user_id, user_role,
                                            import_count,
                                            DATE_FORMAT(activity_date, '%b %d, %Y %h:%i %p') as formatted_date
                                            FROM account_activitytbl 
                                            WHERE action_type = 'Imported'
                                            ORDER BY activity_date DESC 
                                            LIMIT 2";
                            $recentResult = mysqli_query($connection, $recentQuery);
                            ?>
                            
                            <span class="count"><?= $importData['count'] ?? 0 ?></span>
                            <p>Total import operations</p>
                            
                            <div class="import-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= $importData['total_imported'] ?? 0 ?></span>
                                    <span class="stat-label">Total accounts imported</span>
                                </div>
                                <?php if (mysqli_num_rows($recentResult) > 0): ?>
                                    <div class="recent-imports">
                                        <h4>Recent Imports:</h4>
                                        <?php while ($import = mysqli_fetch_assoc($recentResult)): ?>
                                           <?php $performerName = 'Unknown';
                                                    if (!empty($import['user_role']) && !empty($import['user_id'])) {
                                                        if ($import['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$import['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$import['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }?>
                                            <div class="import-item">
                                                <div class="import-details">Imported by: <strong><?= htmlspecialchars($performerName) ?></strong></div>
                                                <div class="import-meta">
                                                    <span class="import-count"><?= $import['import_count'] ?> accounts</span>
                                                    <span class="import-date"><?= $import['formatted_date'] ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-imports">No import activities yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#activityTabs" data-toggle="tab" data-target="#imported" onclick="document.getElementById('imported-tab').click(); document.getElementById('activity-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Registered Users Card -->
                    <div class="activity-card registered-card">
                        <div class="card-header">
                            <i class="fas fa-users"></i>
                            <h3>Registered</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            // Get total registered count from accountstbl
                            $registeredQuery = "SELECT COUNT(*) as count FROM accountstbl WHERE date_registered IS NOT NULL";
                            $registeredResult = mysqli_query($connection, $registeredQuery);
                            $registeredTotal = mysqli_fetch_assoc($registeredResult);
                            
                            // Get registration activities from activity log
                            $activityQuery = "SELECT 
                                                a.activity_details, a.user_id, a.user_role,
                                                DATE_FORMAT(a.activity_date, '%b %d, %Y %h:%i %p') as formatted_date,
                                                ac.email as registered_email
                                            FROM account_activitytbl a
                                            LEFT JOIN accountstbl ac ON a.affected_account_id = ac.account_id
                                            WHERE a.action_type = 'registered'
                                            ORDER BY a.activity_date DESC 
                                            LIMIT 2";
                            $activityResult = mysqli_query($connection, $activityQuery);
                            ?>
                            
                            <span class="count"><?= $registeredTotal['count'] ?? 0 ?></span>
                            <p>Total registered users</p>
                            
                            <div class="registration-stats">
                                <?php if (mysqli_num_rows($activityResult) > 0): ?>
                                    <div class="recent-registrations">
                                        <h4>Recent Registrations:</h4>
                                        <?php while ($registration = mysqli_fetch_assoc($activityResult)): ?>
                                            <?php $performerName = 'Unknown';
                                                    if (!empty($registration['user_role']) && !empty($registration['user_id'])) {
                                                        if ($registration['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$registration['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$registration['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }?>
                                            <div class="registration-item">
                                                <div class="registration-details">
                                                    Registered as: <strong><?= htmlspecialchars($performerName) ?></strong>
                                                </div>
                                                <div class="registration-meta">
                                                    <span class="registration-date"><?= $registration['formatted_date'] ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-registrations">No registration activities yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#activityTabs" data-toggle="tab" data-target="#registered" onclick="document.getElementById('registered-tab').click(); document.getElementById('activity-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Added Records Card -->
                    <div class="activity-card add-card">
                        <div class="card-header">
                            <i class="fas fa-user-plus"></i>
                            <h3>Added Accounts</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            // Get total added accounts count
                            $addedQuery = "SELECT COUNT(*) as count FROM account_activitytbl WHERE action_type = 'Added'";
                            $addedResult = mysqli_query($connection, $addedQuery);
                            $addedTotal = mysqli_fetch_assoc($addedResult);
                            
                            // Get recent adding activities
                            $activityQuery = "SELECT 
                                                a.activity_details, a.user_id, a.user_role, a.affected_account_id,
                                                DATE_FORMAT(a.activity_date, '%b %d, %Y %h:%i %p') as formatted_date,
                                                t.email as account_email
                                            FROM account_activitytbl a
                                            LEFT JOIN tempaccstbl t ON a.affected_account_id = t.tempacc_id
                                            WHERE a.action_type = 'Added'
                                            ORDER BY a.activity_date DESC 
                                            LIMIT 2";
                            $activityResult = mysqli_query($connection, $activityQuery);
                            ?>
                            
                            <span class="count"><?= $addedTotal['count'] ?? 0 ?></span>
                            <p>Total accounts added</p>
                            
                            <div class="adding-stats">
                                <?php if (mysqli_num_rows($activityResult) > 0): ?>
                                    <div class="recent-additions">
                                        <h4>Recent Additions:</h4>
                                        <?php while ($addition = mysqli_fetch_assoc($activityResult)): ?>
                                             <?php $performerName = 'Unknown';
                                                    if (!empty($addition['user_role']) && !empty($addition['user_id'])) {
                                                        if ($addition['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$addition['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$addition['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }?>
                                            <div class="addition-item">
                                                <div class="addition-details">
                                                    Account no. <strong><?= htmlspecialchars($addition['affected_account_id']) ?></strong> added by: <strong><?= htmlspecialchars($performerName) ?></strong>
                                                </div>
                                                <div class="addition-meta">
                                                    <span class="addition-date"><?= $addition['formatted_date'] ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-additions">No accounts added yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#activityTabs" data-toggle="tab" data-target="#added" onclick="document.getElementById('added-tab').click(); document.getElementById('activity-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Edited Records Card -->
                    <div class="activity-card edit-card">
                        <div class="card-header">
                            <i class="fas fa-user-edit"></i>
                            <h3>Edited</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            // Get total edit operations count
                            $editedQuery = "SELECT COUNT(*) as count FROM account_activitytbl WHERE action_type = 'Edited'";
                            $editedResult = mysqli_query($connection, $editedQuery);
                            $editedTotal = mysqli_fetch_assoc($editedResult);
                            
                            // Get affected accounts count (distinct accounts edited)
                            $affectedQuery = "SELECT COUNT(DISTINCT affected_account_id) as affected FROM account_activitytbl WHERE action_type = 'Edited'";
                            $affectedResult = mysqli_query($connection, $affectedQuery);
                            $affectedTotal = mysqli_fetch_assoc($affectedResult);
                            
                            // Get counts by account type
                            $typeQuery = "SELECT 
                                            SUM(CASE WHEN affected_account_source = 'accountstbl' THEN 1 ELSE 0 END) as verified,
                                            SUM(CASE WHEN affected_account_source = 'tempaccstbl' THEN 1 ELSE 0 END) as unverified
                                        FROM account_activitytbl 
                                        WHERE action_type = 'Edited'";
                            $typeResult = mysqli_query($connection, $typeQuery);
                            $typeCounts = mysqli_fetch_assoc($typeResult);
                            
                            // Get recent edits
                            $recentQuery = "SELECT 
                                            a.activity_date,
                                            a.activity_details, a.user_id, a.user_role, a.affected_account_id,
                                            DATE_FORMAT(a.activity_date, '%b %d, %Y %h:%i %p') as formatted_date,
                                            CASE 
                                                WHEN a.affected_account_source = 'accountstbl' THEN ac.email
                                                WHEN a.affected_account_source = 'tempaccstbl' THEN t.email
                                                ELSE 'Unknown'
                                            END as account_email,
                                            a.affected_account_source as account_type
                                        FROM account_activitytbl a
                                        LEFT JOIN accountstbl ac ON a.affected_account_source = 'accountstbl' AND a.affected_account_id = ac.account_id
                                        LEFT JOIN tempaccstbl t ON a.affected_account_source = 'tempaccstbl' AND a.affected_account_id = t.tempacc_id
                                        WHERE a.action_type = 'Edited'
                                        ORDER BY a.activity_date DESC
                                        LIMIT 2";
                            $recentResult = mysqli_query($connection, $recentQuery);
                            ?>
                            
                            <span class="count"><?= $editedTotal['count'] ?? 0 ?></span>
                            <p>Total edit operations</p>
                            
                            <div class="edit-stats">
                                <div class="stat-grid">
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $affectedTotal['affected'] ?? 0 ?></span>
                                        <span class="stat-label">Accounts modified</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $typeCounts['verified'] ?? 0 ?></span>
                                        <span class="stat-label">Verified accounts</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $typeCounts['unverified'] ?? 0 ?></span>
                                        <span class="stat-label">Unverified accounts</span>
                                    </div>
                                </div>
                                
                                <?php if (mysqli_num_rows($recentResult) > 0): ?>
                                    <div class="recent-edits">
                                        <h4>Recent Edits:</h4>
                                        <?php while ($edit = mysqli_fetch_assoc($recentResult)): ?>
                                            <?php $performerName = 'Unknown';
                                                    if (!empty($edit['user_role']) && !empty($edit['user_id'])) {
                                                        if ($edit['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$edit['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$edit['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }?>
                                            <div class="edit-item">
                                                <div class="edit-details" 
                                                    data-full-details="<?= htmlspecialchars($edit['activity_details']) ?>"
                                                    title="Click to view full details">
                                                    Account no. <strong><?= htmlspecialchars($edit['affected_account_id']) ?></strong> edited by: <strong><?= htmlspecialchars($performerName) ?></strong>
                                                </div>
                                                <div class="edit-meta">
                                                    <span class="account-type-badge <?= $edit['account_type'] === 'accountstbl' ? 'verified' : 'unverified' ?>">
                                                        <?= $edit['account_type'] === 'accountstbl' ? 'Verified' : 'Unverified' ?>
                                                    </span>
                                                    <span class="edit-date"><?= $edit['formatted_date'] ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-edits">No edit activities yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#activityTabs" data-toggle="tab" data-target="#edited" onclick="document.getElementById('edited-tab').click(); document.getElementById('activity-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Deleted Records Card -->
                    <div class="activity-card delete-card">
                        <div class="card-header">
                            <i class="fas fa-user-times"></i>
                            <h3>Deleted</h3>
                        </div>
                        <div class="card-content">
                            <?php
                            // Get total deletions count from activity log
                            $deletedQuery = "SELECT COUNT(*) as count FROM account_activitytbl WHERE action_type = 'Deleted'";
                            $deletedResult = mysqli_query($connection, $deletedQuery);
                            $deletedTotal = mysqli_fetch_assoc($deletedResult);
                            
                            // Get counts by account type
                            $typeQuery = "SELECT 
                                            SUM(CASE WHEN affected_account_source = 'accountstbl' THEN 1 ELSE 0 END) as verified,
                                            SUM(CASE WHEN affected_account_source = 'tempaccstbl' THEN 1 ELSE 0 END) as unverified
                                        FROM account_activitytbl 
                                        WHERE action_type = 'Deleted'";
                            $typeResult = mysqli_query($connection, $typeQuery);
                            $typeCounts = mysqli_fetch_assoc($typeResult);
                            
                            // Get recent deletions
                            $recentQuery = "SELECT 
                                            a.activity_date,
                                            a.activity_details, a.user_id, a.user_role, a.affected_account_id,
                                            DATE_FORMAT(a.activity_date, '%b %d, %Y %h:%i %p') as formatted_date,
                                            CASE 
                                                WHEN a.affected_account_source = 'accountstbl' THEN 'Verified'
                                                WHEN a.affected_account_source = 'tempaccstbl' THEN 'Unverified'
                                                ELSE 'Unknown'
                                            END as account_type
                                        FROM account_activitytbl a
                                        WHERE a.action_type = 'Deleted'
                                        ORDER BY a.activity_date DESC
                                        LIMIT 2";
                            $recentResult = mysqli_query($connection, $recentQuery);
                            ?>
                            
                            <span class="count"><?= $deletedTotal['count'] ?? 0 ?></span>
                            <p>Total deleted accounts</p>
                            
                            <div class="delete-stats">
                                <div class="stat-grid">
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $typeCounts['verified'] ?? 0 ?></span>
                                        <span class="stat-label">Verified accounts</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?= $typeCounts['unverified'] ?? 0 ?></span>
                                        <span class="stat-label">Unverified accounts</span>
                                    </div>
                                </div>
                                
                                <?php if (mysqli_num_rows($recentResult) > 0): ?>
                                    <div class="recent-deletions">
                                        <h4>Recent Deletions:</h4>
                                        <?php while ($deletion = mysqli_fetch_assoc($recentResult)): ?>
                                            <?php $performerName = 'Unknown';
                                                    if (!empty($deletion['user_role']) && !empty($deletion['user_id'])) {
                                                        if ($deletion['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$deletion['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$deletion['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }?>
                                            <div class="deletion-item">
                                                <div class="deletion-details">
                                                    Account no. <strong><?= htmlspecialchars($deletion['affected_account_id']) ?></strong> deleted by: <strong><?= htmlspecialchars($performerName) ?></strong>
                                                </div>
                                                <div class="deletion-meta">
                                                    <span class="account-type-badge <?= strtolower($deletion['account_type']) ?>">
                                                        <?= $deletion['account_type'] ?>
                                                    </span>
                                                    <span class="deletion-date"><?= $deletion['formatted_date'] ?></span>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="no-deletions">No deletion activities yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="#activityTabs" data-toggle="tab" data-target="#deleted" onclick="document.getElementById('deleted-tab').click(); document.getElementById('activity-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="account-heading" id="activity-heading">
                    <h2>Account Activity Logs</h2>
                    <a href="admin_archive_view.php"><i class="fas fa-archive"></i> View Archives</a>
                </div>
                    <!-- activity tables -->
                        <div class="activity-details-tabs">
                            <ul class="nav nav-tabs" id="activityTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="imported-tab" data-toggle="tab" href="#imported" role="tab" aria-controls="imported" aria-selected="true">Imports</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="registered-tab" data-toggle="tab" href="#registered" role="tab" aria-controls="registered" aria-selected="false">Registered</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="added-tab" data-toggle="tab" href="#added" role="tab" aria-controls="added" aria-selected="false">Added</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="edited-tab" data-toggle="tab" href="#edited" role="tab" aria-controls="edited" aria-selected="false">Edited</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="deleted-tab" data-toggle="tab" href="#deleted" role="tab" aria-controls="deleted" aria-selected="false">Deleted</a>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="activityTabsContent">
                                <div class="tab-pane fade show active" id="imported" role="tabpanel" aria-labelledby="imported-tab">
                                    <?php 
                                    // Create a function to display activity details
                                    function displayActivityDetails($type, $connection) {
                                    $recordsPerPage = 10;
                                    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                                    $offset = ($page - 1) * $recordsPerPage;
                                    
                                    // Base query
                                    $query = "SELECT * FROM account_activitytbl WHERE action_type = ?";
                                    $params = [ucfirst($type)];
                                    
                                    // For pagination count
                                    $countQuery = "SELECT COUNT(*) as total FROM account_activitytbl WHERE action_type = ?";
                                    
                                    // Add filtering if needed
                                    $dateFilter = '';
                                    if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
                                        $dateFilter .= " AND activity_date >= ?";
                                        $params[] = $_GET['date_from'];
                                    }
                                    
                                    if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
                                        $dateFilter .= " AND activity_date <= ?";
                                        $params[] = $_GET['date_to'] . ' 23:59:59';
                                    }
                                    
                                    // Add sorting
                                    $query .= $dateFilter . " ORDER BY activity_date DESC LIMIT ? OFFSET ?";
                                    $countQuery .= $dateFilter;
                                    $params[] = $recordsPerPage;
                                    $params[] = $offset;
                                    
                                    // Prepare and execute the query
                                    $stmt = $connection->prepare($query);
                                    $types = str_repeat('s', count($params));
                                    $stmt->bind_param($types, ...$params);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    
                                    // Get total count
                                    $countStmt = $connection->prepare($countQuery);
                                    $countTypes = str_repeat('s', count($params) - 2);
                                    $countStmt->bind_param($countTypes, ...array_slice($params, 0, -2));
                                    $countStmt->execute();
                                    $totalResult = $countStmt->get_result();
                                    $total = $totalResult->fetch_assoc()['total'];
                                    $totalPages = ceil($total / $recordsPerPage);
                                    ?>
                                    
                                    <div class="activity-filters mb-4">
                                        <form method="get" class="form-inline">
                                            <div class="form-groups">
                                                <input type="hidden" name="view" value="<?= $type ?>">
                                                <div class="form-group mr-3">
                                                    <label for="date_from" class="mr-2">From:</label>
                                                    <input type="date" name="date_from" id="date_from" class="form-control" 
                                                        value="<?= $_GET['date_from'] ?? '' ?>">
                                                </div>
                                                <div class="form-group mr-3">
                                                    <label for="date_to" class="mr-2">To:</label>
                                                    <input type="date" name="date_to" id="date_to" class="form-control" 
                                                        value="<?= $_GET['date_to'] ?? '' ?>">
                                                </div>
                                            </div>
                                            <div class="button-filters">
                                                <button type="submit" class="btn btn-primary mr-2">Filter</button>
                                                <a href="?view=<?= $type ?>" class="btn btn-secondary">Reset</a>
                                            </div>
                                        </form>
                                    </div>
                                    
                                   <table class="activity-details-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Action</th>
                                                <th>Details</th>
                                                <?php if ($type === 'imported'): ?>
                                                    <th>Accounts Imported</th>
                                                <?php elseif ($type === 'registered'): ?>
                                                    <th>Account ID</th>
                                                <?php else: ?>
                                                    <th>Account Type</th>
                                                <?php endif; ?>
                                                <th>Performed By</th>
                                                <th>Action</th> <!-- New column for View button -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($result->num_rows > 0): ?>
                                                <?php while ($row = $result->fetch_assoc()): 
                                                    // Get performer name based on user_role and user_id
                                                    $performerName = 'Unknown';
                                                    if (!empty($row['user_role']) && !empty($row['user_id'])) {
                                                        if ($row['user_role'] === 'adminaccountstbl') {
                                                            $performerQuery = "SELECT admin_name AS name FROM adminaccountstbl WHERE admin_id = " . (int)$row['user_id'];
                                                        } else {
                                                            $performerQuery = "SELECT fullname AS name FROM accountstbl WHERE account_id = " . (int)$row['user_id'];
                                                        }
                                                        $performerResult = mysqli_query($connection, $performerQuery);
                                                        if ($performerResult && mysqli_num_rows($performerResult) > 0) {
                                                            $performerData = mysqli_fetch_assoc($performerResult);
                                                            $performerName = htmlspecialchars($performerData['name']);
                                                        }
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?= date('M d, Y h:i A', strtotime($row['activity_date'])) ?></td>
                                                        <td><?= htmlspecialchars($row['action_type']) ?></td>
                                                        <td><?= htmlspecialchars($row['activity_details']) ?></td>
                                                        <?php if ($type === 'imported'): ?>
                                                            <td><?= $row['import_count'] ?? 0 ?></td>
                                                        <?php elseif ($type === 'registered'): ?>
                                                            <td><?= $row['affected_account_id'] ?? 'N/A' ?></td>
                                                        <?php elseif ($type === 'added'): ?>
                                                            <td>
                                                                <span class="badge badge-<?= $row['affected_account_source'] === 'adminaccountstbl' ? 'verified' : 'unverified' ?>">
                                                                    <?= $row['affected_account_source'] === 'adminaccountstbl' ? 'Verified' : 'Unverified' ?>
                                                                </span>
                                                            </td>
                                                        <?php else: ?>
                                                            <td>
                                                                <span class="badge badge-<?= $row['affected_account_source'] === 'accountstbl' ? 'verified' : 'unverified' ?>">
                                                                    <?= $row['affected_account_source'] === 'accountstbl' ? 'Verified' : 'Unverified' ?>
                                                                </span>
                                                            </td>
                                                        <?php endif; ?>
                                                        <td><?= $performerName ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-view" 
                                                                    data-activity-id="<?= $row['activity_id'] ?>"
                                                                    data-toggle="modal" 
                                                                    data-target="#detailsModal">
                                                                <i class="fas fa-eye"></i> View
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="<?= $type === 'imported' ? 6 : ($type === 'registered' ? 6 : 6) ?>" class="text-center">No <?= $type ?> activities found</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    
                                    <div class="activity-pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?view=<?= $type ?>&page=<?= $page - 1 ?><?= isset($_GET['date_from']) ? '&date_from=' . urlencode($_GET['date_from']) : '' ?><?= isset($_GET['date_to']) ? '&date_to=' . urlencode($_GET['date_to']) : '' ?>" 
                                            class="btn btn-sm btn-outline-primary mr-2">&laquo; Previous</a>
                                        <?php endif; ?>
                                        
                                        <span class="mx-2">Page <?= $page ?> of <?= $totalPages ?></span>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?view=<?= $type ?>&page=<?= $page + 1 ?><?= isset($_GET['date_from']) ? '&date_from=' . urlencode($_GET['date_from']) : '' ?><?= isset($_GET['date_to']) ? '&date_to=' . urlencode($_GET['date_to']) : '' ?>" 
                                            class="btn btn-sm btn-outline-primary ml-2">Next &raquo;</a>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                                    
                                    // Call the function for each tab
                                    displayActivityDetails('imported', $connection);
                                    ?>
                                </div>
                                <div class="tab-pane fade" id="registered" role="tabpanel" aria-labelledby="registered-tab">
                                    <?php displayActivityDetails('registered', $connection); ?>
                                </div>
                                <div class="tab-pane fade" id="added" role="tabpanel" aria-labelledby="added-tab">
                                    <?php displayActivityDetails('added', $connection); ?>
                                </div>
                                <div class="tab-pane fade" id="edited" role="tabpanel" aria-labelledby="edited-tab">
                                    <?php displayActivityDetails('edited', $connection); ?>
                                </div>
                                <div class="tab-pane fade" id="deleted" role="tabpanel" aria-labelledby="deleted-tab">
                                    <?php displayActivityDetails('deleted', $connection); ?>
                                </div>
                            </div>
                        </div>                
            </div>
        <?php endif; ?>

        <div class="button-box">    
            <div class="col-md-7">
                <form action="" method="GET">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" name="search" placeholder="Search user" value="<?php if(isset($_GET['search'])){echo $_GET['search'];}?>">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </form>
            </div>
            <div class="filter-box">
                <button class="btn btn-success" id="add-single-user-btn" onclick="window.location.href='add_account.php'">Add User</button>
                <button class="btn btn-primary" id="add-user-btn" data-toggle="modal" data-target="#addUserModal">Import Users</button>
                <form action="sendemail.php" method="post" enctype="multipart/form-data">
                    <input type="submit" name="sendEmail" value="Send Verification" class="btn btn-primary">
                </form>
            </div>
        </div>

        <div class="account-section">
            <div class="head-filter-container">
                <h2>Unverified Accounts</h2>
                <form name="temp-account-filters" class="filter-form">
                    <label>Select Municipality
                        <select name="cmfilter" class="form-control city-filter" onchange="getBarangays(this, 'temp')">
                            <option value="">All</option>
                            <?php
                            require 'getdropdown.php';
                            $citymunicipalities = getcitymunicipality();
                            foreach($citymunicipalities as $citymunicipality){
                                echo '<option value="'.htmlspecialchars($citymunicipality['city']).'">'.htmlspecialchars($citymunicipality['city']).'</option>';
                            }
                            ?>
                        </select>
                    </label>
                    <label>Select Barangay
                        <select name="bfilter" class="form-control barangay-filter" onchange="filterTable('temp')" disabled>
                            <option value="">All</option>
                        </select>
                    </label>
                </form>
            </div>
        <div class="table-container">
            <table class="table table-hover table-bordered table-striped" id="temp-accs-table">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Personal Email</th>
                        <th>Barangay</th>
                        <th>City/Municipality</th>
                        <th>Access Role</th>
                        <th>Organization</th>
                        <th>Is Verified</th>
                        <th>Date Uploaded</th>
                        <th>Administrator</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="temp-accounts-body">
                    <?php
                    $query = "SELECT firstname, lastname, email, personal_email, barangay, city_municipality, accessrole, organization, is_verified, import_date, imported_by, tempacc_id FROM tempaccstbl WHERE tempacc_id IS NOT NULL AND tempacc_id != ''";
                    if (isset($_GET['search'])) {
                        $filtervalues = $_GET['search'];
                        $query = "SELECT firstname, lastname, email, personal_email, barangay, city_municipality, accessrole, organization, is_verified, import_date, imported_by, tempacc_id FROM tempaccstbl WHERE tempacc_id IS NOT NULL AND tempacc_id != '' AND CONCAT(firstname, lastname, email, personal_email, barangay, city_municipality, accessrole, organization, is_verified, import_date, imported_by) LIKE '%$filtervalues%'";
                    }
                    if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                        $query .= " AND barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'";
                    }
                    $result = mysqli_query($connection, $query);
                    if ($result && mysqli_num_rows($result) > 0) {
                        while($items = mysqli_fetch_assoc($result)) {
                            // Only display rows with valid tempacc_id
                            if (!empty($items['tempacc_id'])) {
                                echo '<tr>
                                    <td>'.htmlspecialchars($items['firstname']).'</td>
                                    <td>'.htmlspecialchars($items['lastname']).'</td>
                                    <td>'.htmlspecialchars($items['email']).'</td>
                                    <td>'.htmlspecialchars($items['personal_email']).'</td>
                                    <td>'.htmlspecialchars($items['barangay']).'</td>
                                    <td>'.htmlspecialchars($items['city_municipality']).'</td>
                                    <td>'.htmlspecialchars($items['accessrole']).'</td>
                                    <td>'.htmlspecialchars($items['organization']).'</td>
                                    <td>'.htmlspecialchars($items['is_verified']).'</td>
                                    <td>'.htmlspecialchars($items['import_date']).'</td>
                                    <td>'.htmlspecialchars($items['imported_by']).'</td>
                                    <td>
                                        <button class="btn btn-primary btn-sm temp-accounts-edit-btn" data-id="'.htmlspecialchars($items['tempacc_id']).'" onclick="editTempAccount('.htmlspecialchars($items['tempacc_id']).')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm temp-accounts-delete-btn" data-id="'.htmlspecialchars($items['tempacc_id']).'" onclick="deleteTempAccount('.htmlspecialchars($items['tempacc_id']).')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    </td>
                                </tr>';
                            }
                        }
                    } else {
                        echo '<tr><td colspan="12" class="text-center">No temporary accounts found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <div class="pagination">
                <?php
                $totalTemp = mysqli_query($connection, "SELECT COUNT(*) as total FROM tempaccstbl");
                // if accessrole is Barangay Official, filter by barangay
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                    $totalTemp = mysqli_query($connection, "SELECT COUNT(*) as total FROM tempaccstbl WHERE barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'");
                }
                $totalTemp = mysqli_fetch_assoc($totalTemp)['total'];
                $totalTempPages = ceil($totalTemp / $recordsPerPage);
                
                if ($page > 1) {
                    echo '<a href="?page='.($page - 1).(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">&laquo; Previous</a>';
                }
                
                for ($i = 1; $i <= $totalTempPages; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo '<a class="'.$active.'" href="?page='.$i.(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">'.$i.'</a>';
                }
                
                if ($page < $totalTempPages) {
                    echo '<a href="?page='.($page + 1).(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">Next &raquo;</a>';
                }
                ?>
            </div>
        </div>
        <div class="account-section">
        <div class="head-filter-container">
            <h2>Verified Accounts</h2>
            <form name="verified-account-filters" class="filter-form">
                <label>Select Municipality
                    <select name="cmfilter" class="form-control city-filter" onchange="getBarangays(this, 'verified')">
                        <option value="">All</option>
                        <?php
                        foreach($citymunicipalities as $citymunicipality){
                            echo '<option value="'.htmlspecialchars($citymunicipality['city']).'">'.htmlspecialchars($citymunicipality['city']).'</option>';
                        }
                        ?>
                    </select>
                </label>
                <label>Select Barangay
                    <select name="bfilter" class="form-control barangay-filter" onchange="filterTable('verified')" disabled>
                        <option value="">All</option>
                    </select>
                </label>
            </form>
        </div>
    <div class="table-container">
        <table class="table table-hover table-bordered table-striped" id="verified-accs-table">
        <thead>
            <tr>
                <th>Full Name</th>
                <th>Email</th>
                <th>Personal Email</th>
                <th>Barangay</th>
                <th>City/Municipality</th>
                <th>Access Role</th>
                <th>Organization</th>
                <th>Date Registered</th>
                <th>Bio</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="verified-accounts-body">
            <?php
            $query = "SELECT account_id, fullname, email, personal_email, barangay, city_municipality, accessrole, organization, date_registered, bio FROM accountstbl";
            if (isset($_GET['search'])) {
                $filtervalues = $_GET['search'];
                $query = "SELECT account_id, fullname, email, personal_email, barangay, city_municipality, accessrole, organization, date_registered, bio FROM accountstbl WHERE CONCAT(fullname, email, personal_email, barangay, city_municipality, accessrole, organization, date_registered, bio) LIKE '%$filtervalues%'";
            }
            if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
                $query .= " WHERE barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'";
            }
            $result = mysqli_query($connection, $query);
            if (mysqli_num_rows($result) > 0) {
                while($items = mysqli_fetch_assoc($result)) {
                    echo '<tr>
                        <td>'.$items['fullname'].'</td>
                        <td>'.$items['email'].'</td>
                        <td>'.$items['personal_email'].'</td>
                        <td>'.$items['barangay'].'</td>
                        <td>'.$items['city_municipality'].'</td>
                        <td>'.$items['accessrole'].'</td>
                        <td>'.$items['organization'].'</td>
                        <td>'.$items['date_registered'].'</td>
                        <td>'.substr($items['bio'], 0, 50).(strlen($items['bio']) > 50 ? '...' : '').'</td>
                        <td>
                            <button class="btn btn-primary btn-sm valid-accounts-edit-btn" data-id="'.htmlspecialchars($items['account_id']).'" onclick="editValidAccount('.htmlspecialchars($items['account_id']).')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm valid-accounts-delete-btn" data-id="'.htmlspecialchars($items['account_id']).'" onclick="deleteValidAccount('.htmlspecialchars($items['account_id']).', this.className)">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>';
                }
            } else {
                echo '<tr><td colspan="10" class="text-center">No verified accounts found</td></tr>';
            }
            ?>
        </tbody>
    </table>
    </div>
        <div class="pagination">
        <?php
        $totalVerified = mysqli_query($connection, "SELECT COUNT(*) as total FROM accountstbl");
        // if accessrole is Barangay Official, filter by barangay
        if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == 'Barangay Official') {
            $totalVerified = mysqli_query($connection, "SELECT COUNT(*) as total FROM accountstbl WHERE barangay = '" . mysqli_real_escape_string($connection, $_SESSION['barangay']) . "'");
        }
        $totalVerified = mysqli_fetch_assoc($totalVerified)['total'];
        $totalVerifiedPages = ceil($totalVerified / $recordsPerPage);
        
        if ($page > 1) {
            echo '<a href="?page='.($page - 1).(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">&laquo; Previous</a>';
        }
        
        for ($i = 1; $i <= $totalVerifiedPages; $i++) {
            $active = $i == $page ? 'active' : '';
            echo '<a class="'.$active.'" href="?page='.$i.(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">'.$i.'</a>';
        }
        
        if ($page < $totalVerifiedPages) {
            echo '<a href="?page='.($page + 1).(isset($_GET['search']) ? '&search='.urlencode($_GET['search']) : '').'">Next &raquo;</a>';
        }
        ?>
    </div>
</div>


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
                        <input type="file" name="file" class="filefind" accept=".csv,text/csv" required />
                        <input type="submit" name="importSubmit" value="IMPORT" class="btn btn-primary">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for displaying full details -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Activity Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading details...</p>
                </div>
                <div id="modalDetailsContent" style="white-space: pre-wrap; padding: 15px; background: #f8f9fa; border-radius: 5px; display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<!-- Old verification modal, kept for reference -->
<div class="modal fade" id="sendVerificationModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Send Email Verification by Batch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">X</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="col-md-12" id="SendEmailForm">
                    <form action="sendemail.php" method="post" enctype="multipart/form-data">
                        <label for="date">Select date uploaded: <input type="date" name="date" class="dateInput"/></label>
                        <input type="submit" name="sendEmail" value="Submit" class="btn btn-primary">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</main>
    <footer>
        <div id="right-footer">
            <h3>Follow us on</h3>
            <div id="social-media-footer">
                <ul>
                    <li>
                        <a href="#">
                            <i class="fab fa-facebook"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <p>This website is developed by ManGrow. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js" defer></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" defer></script>
    <script type = "text/javascript">
        function selectcitymunicipality(citymunicipality){
            console.log(Barangay);
        }

        $(document).ready(function() {
            // Edit button click handler
            $('.edit-btn').click(function() {
                var id = $(this).data('id');
                // You can redirect to an edit page or show a modal
                window.location.href = 'edit_temp_account.php?id=' + id;
            });

            // Delete button click handler
            $('.delete-btn').click(function() {
                var id = $(this).data('id');
                if (confirm('Are you sure you want to delete this temporary account?')) {
                    $.ajax({
                        url: 'delete_temp_account.php',
                        type: 'POST',
                        data: { id: id },
                        success: function(response) {
                            location.reload(); // Refresh the page after deletion
                        },
                        error: function() {
                            alert('Error deleting account');
                        }
                    });
                }
            });
        });

        function editTempAccount(id) {
            if (!id) return;
            // Redirect to edit page for temp account
            window.location.href = 'edit_temp_account.php?id=' + encodeURIComponent(id);
        }

        function deleteTempAccount(id) {
            if (!id) return;
                
            if (confirm('Are you sure you want to delete this temporary account?')) {
                // Create a form dynamically to submit the request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_temp_account.php';
                    
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                    
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editValidAccount(id) {
            if (!id) return;
            // Redirect to edit page for valid account
            window.location.href = 'edit_valid_account.php?id=' + encodeURIComponent(id);
        }

        function deleteValidAccount(id) {
            if (!id) return;
            
            if (confirm('Are you sure you want to delete this verified account?')) {
                // Create a form dynamically to submit the request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_valid_account.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

            $(document).ready(function() {
                $('#detailsModal').on('show.bs.modal', function(event) {
                    var button = $(event.relatedTarget); // Button that triggered the modal
                    var activityId = button.data('activity-id');
                    
                    // Show loading, hide content
                    $('#modalLoading').show();
                    $('#modalDetailsContent').hide();
                    
                    // Clear previous content
                    $('#modalDetailsContent').text('');
                    
                    // Make AJAX request
                    $.ajax({
                        url: 'get_activity_details.php',
                        type: 'GET',
                        data: { activity_id: activityId },
                        dataType: 'json',
                        success: function(response) {
                            if(response.success) {
                                $('#modalDetailsContent').text(response.details);
                                $('#modalLoading').hide();
                                $('#modalDetailsContent').show();
                            } else {
                                $('#modalDetailsContent').text('Error loading details: ' + response.error);
                                $('#modalLoading').hide();
                                $('#modalDetailsContent').show();
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#modalDetailsContent').text('AJAX Error: ' + error);
                            $('#modalLoading').hide();
                            $('#modalDetailsContent').show();
                        }
                    });
                });
            });


        document.addEventListener('DOMContentLoaded', function() {
            // Handle tab switching when clicking "View Details" in cards
            const viewDetailLinks = document.querySelectorAll('[data-target]');
            viewDetailLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const targetTab = this.getAttribute('data-target');
                    const tabElement = document.querySelector(targetTab);
                    if (tabElement) {
                        const tabInstance = new bootstrap.Tab(document.querySelector(`a[href="${targetTab}"]`));
                        tabInstance.show();
                    }
                });
            });
            
            // Handle URL hash for direct tab access
            if (window.location.hash) {
                const hash = window.location.hash;
                if (hash.startsWith('#imported') || hash.startsWith('#registered') || 
                    hash.startsWith('#added') || hash.startsWith('#edited') || 
                    hash.startsWith('#deleted')) {
                    const tabElement = document.querySelector(`a[href="${hash}"]`);
                    if (tabElement) {
                        const tabInstance = new bootstrap.Tab(tabElement);
                        tabInstance.show();
                    }
                }
            }
        });
    </script>
</body>
</html>