<?php
    session_start();
    include 'database.php';


    if(isset($_SESSION["accessrole"])){
        // Check if role is NOT in allowed list
        if($_SESSION["accessrole"] != 'Barangay Official' && 
           $_SESSION["accessrole"] != 'Administrator' && 
           $_SESSION["accessrole"] != 'Representative') {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'This account is not authorized'
            ];
            header("Location: index.php");
            exit();
        }
        
        // Set variables if logged in with proper role  
        if(isset($_SESSION["fullname"])){
            $email = $_SESSION["fullname"];
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
    <title>Administrator Lobby</title>
    <link rel="stylesheet" href="adminpage.css">
    <link rel="stylesheet" href="adminpagecontent.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script type ="text/javascript" src ="adminusers.js" defer></script>
    <script type ="text/javascript" src ="app.js" defer></script>
</head>
<body>
    <header>
        <div class="header-logo"><span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span></div>
        <nav class = "navbar">
            <ul class = "nav-list">
                <li class="active"><a href="#"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg></a></li>
                <li><a href="adminaccspage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M600-120v-120H440v-400h-80v120H80v-320h280v120h240v-120h280v320H600v-120h-80v320h80v-120h280v320H600ZM160-760v160-160Zm520 400v160-160Zm0-400v160-160Zm0 160h120v-160H680v160Zm0 400h120v-160H680v160ZM160-600h120v-160H160v160Z"/></svg></a></li>
                <li><a href="adminmappage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q152 0 263.5 98T876-538q-20-10-41.5-15.5T790-560q-19-73-68.5-130T600-776v16q0 33-23.5 56.5T520-680h-80v80q0 17-11.5 28.5T400-560h-80v80h240q11 0 20.5 5.5T595-459q-17 27-26 57t-9 62q0 63 32.5 117T659-122q-41 20-86 31t-93 11Zm-40-82v-78q-33 0-56.5-23.5T360-320v-40L168-552q-3 18-5.5 36t-2.5 36q0 121 79.5 212T440-162Zm340 82q-7 0-12-4t-7-10q-11-35-31-65t-43-59q-21-26-34-57t-13-65q0-58 41-99t99-41q58 0 99 41t41 99q0 34-13.5 64.5T873-218q-23 29-43 59t-31 65q-2 6-7 10t-12 4Zm0-113q10-17 22-31.5t23-29.5q14-19 24.5-40.5T860-340q0-33-23.5-56.5T780-420q-33 0-56.5 23.5T700-340q0 24 10.5 45.5T735-254q12 15 23.5 29.5T780-193Zm0-97q-21 0-35.5-14.5T730-340q0-21 14.5-35.5T780-390q21 0 35.5 14.5T830-340q0 21-14.5 35.5T780-290Z"/></svg></a></li>
                <li><a href="adminreportpage.php"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-600q17 0 28.5-11.5T360-640q0-17-11.5-28.5T320-680q-17 0-28.5 11.5T280-640q0 17 11.5 28.5T320-600Zm0 160q17 0 28.5-11.5T360-480q0-17-11.5-28.5T320-520q-17 0-28.5 11.5T280-480q0 17 11.5 28.5T320-440Zm0 160q17 0 28.5-11.5T360-320q0-17-11.5-28.5T320-360q-17 0-28.5 11.5T280-320q0 17 11.5 28.5T320-280ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h440l200 200v440q0 33-23.5 56.5T760-120H200Zm0-80h560v-400H600v-160H200v560Zm0-560v160-160 560-560Z"/></svg></a></li>
            </ul>
        </nav>
        
        <?php    
        if (isset($_SESSION["email"])) {
            $loggeduser = $_SESSION["email"];
            echo "<div class='userbox'><a href='#' id='login' onclick='LoginToggle()'>$loggeduser</a></div>"; // Display the name with a link to profile.php
        }else{
            ?> <div class ="admin-user"></div> <?php
        }
        ?>
    </header>
    <main>
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
            <button type="button" name="logoutbtn" onclick="window.location.href='adminlogout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
            <?php
                if(isset($_SESSION["accessrole"]) && $_SESSION["accessrole"] == "Barangay Official"){
                    ?><button type="button" name="returnbtn" onclick="window.location.href='index.php';">Back to Home <i class="fa fa-angle-double-right"></i></button><?php
                }
            ?>
            </div>
        </div>  
    
            <!-- adminpage content -->
        <div class="adminpage-strip">
            <div class="adminheader">
                <h1>Welcome to Admin Page</h1>
            </div>
        <div class="stats-container">
            <div class="stats-card">
                <h3>Total Status Reports</h3>
                <h2>
                    20        
                </h2>
            </div>
            <div class="stats-card">
                <h3>Total Tree Planting Reports</h3>
                <h2>
                    40        
                </h2>
            </div>
            <div class="stats-card">
                <h3>Ongoing Events</h3>
                <h2>
                    60        
                </h2>
            </div>
            <div class="stats-card">
                <h3>Completed Events</h3>
                <h2>
                    80        
                </h2>
            </div>
        </div>
    <div class="events-card-strip">

    <div class="admin-filters">
    <h2>Filter Events</h2>
    <div class="filter-row">
        <div class="filter-group">
            <label for="filter-type">Event Type</label>
            <select id="filter-type">
                <option value="all">All Types</option>
                <option value="Tree Planting">Tree Planting</option>
                <option value="Status Report">Status Report</option>
                <option value="Announcement">Announcement</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-status">Approval Status</label>
                    <select id="filter-status">
                        <option value="all">All Statuses</option>
                        <option value="Approved">Approved</option>
                        <option value="Pending">Pending</option>
                        <option value="Disapproved">Disapproved</option>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-date-from">From Date</label>
                    <input type="date" id="filter-date-from">
                </div>
                <div class="filter-group">
                    <label for="filter-date-to">Date Posted</label>
                    <input type="date" id="filter-date-to">
                </div>
            </div>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="filter-organization">Organization</label>
                    <select id="filter-organization">
                        <option value="all">All Organizations</option>
                        <?php
                        $orgQuery = "SELECT DISTINCT organization FROM eventstbl ORDER BY organization";
                        $orgResult = mysqli_query($connection, $orgQuery);
                        while($org = mysqli_fetch_assoc($orgResult)) {
                            echo '<option value="'.htmlspecialchars($org['organization']).'">'.htmlspecialchars($org['organization']).'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-location">Location</label>
                    <select id="filter-location">
                        <option value="all">All Locations</option>
                        <?php
                        $locQuery = "SELECT DISTINCT barangay FROM eventstbl ORDER BY barangay";
                        $locResult = mysqli_query($connection, $locQuery);
                        while($loc = mysqli_fetch_assoc($locResult)) {
                            echo '<option value="'.htmlspecialchars($loc['barangay']).'">Brgy. '.htmlspecialchars($loc['barangay']).'</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="filter-btn reset-btn" id="reset-filters">Reset</button>
                <button class="filter-btn apply-btn" id="apply-filters">Apply Filters</button>
            </div>
        </div>
        <div class="events-head" style="text-align:center; padding-top:20px; box-sizing:border-box; border-top:2px solid #123524;"><h1>Mangrow Events</h1></div>
    <?php 
    $query = "SELECT * FROM eventstbl ORDER BY created_at DESC";
    $result = mysqli_query($connection, $query);

        if (mysqli_num_rows($result) > 0) {
            while($items = mysqli_fetch_assoc($result)) {
                $created_date = date("F j, Y, g:i A", strtotime($items['created_at']));
                $edited_date = !empty($items['edited_at']) ? date("F j, Y, g:i A", strtotime($items['edited_at'])) : 'Not Edited';
                $iso_date = date("Y-m-d", strtotime($items['created_at'])); // For filtering
                ?>
                <div class="events-card" 
                    data-type="<?php echo htmlspecialchars($items['program_type']); ?>"
                    data-status="<?php echo htmlspecialchars($items['is_approved']); ?>"
                    data-date="<?php echo $iso_date; ?>"
                    data-organization="<?php echo htmlspecialchars($items['organization']); ?>"
                    data-location="<?php echo htmlspecialchars($items['barangay']); ?>">
                    
                    <!-- View Button (now placed at the top right of the card) -->
                    <button type="button" class="view-btn" data-event-id="<?php echo $items['event_id']; ?>">
                        <i class="fa fa-eye"></i> View
                    </button>

                    <div class="event-thumbnail">
                        <?php echo '<img src="' . $items['thumbnail'] . '" alt="' . htmlspecialchars($items['thumbnail_data']) . '">'; ?>
                    </div>
                    
                    <div class="event-description">
                        <h3><?php echo htmlspecialchars($items['title']); ?> (<?php echo htmlspecialchars($items['is_approved']); ?>)</h3>
                        <div class="event-meta">
                            <p><strong>Location: </strong> 
                                <?php 
                                echo htmlspecialchars($items['venue'] . ', ' . 
                                    htmlspecialchars($items['barangay']) . ', ' . 
                                    htmlspecialchars($items['city_municipality']) . ', ' . 
                                    htmlspecialchars($items['area_no']));
                                ?>
                            </p>
                            <p><strong>Organized by: </strong> <?php echo htmlspecialchars($items['organization']); ?></p>
                            <p><strong>Event Type: </strong> <?php echo htmlspecialchars($items['program_type']); ?></p>
                            <p><strong>
                                <?php
                                    if ($items['is_approved'] == 'Pending') {
                                        echo 'Submitted on: ';
                                    } elseif ($items['is_approved'] == 'Disapproved') {
                                        echo 'Submitted on: ';
                                    } else {
                                        echo 'Posted on: ';
                                    }
                                ?>
                            </strong> <?php echo $created_date; ?>
                            <?php if (!empty($items['edited_at'])) { ?>
                                <strong style="color: gray; margin-left:10px;">Edited on: <?php echo $edited_date; ?></strong>
                            <?php } ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if($items['is_approved'] != 'Approved'){ ?>
                        <form method="post" action="eventapproval.php" class="approval-form">
                            <input type="hidden" name="event_id" value="<?php echo $items['event_id']; ?>">
                            <div class="event-side-footer">
                                <i class="fa fa-angle-double-left"></i>
                                <div class="action-buttons">
                                    <button type="submit" name="approval_status" value="Approved" class="approve-btn" onclick="openApproveModal(this)">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="disapprove-btn" onclick="openDisapprovalModal('<?php echo $items['event_id']; ?>')">
                                        <i class="fa fa-times"></i> Disapprove
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                </div>
                <?php
            }
        } else {
            echo '<div class="events-card">No events available</div>';
        }
    ?>
    <div id="disapprovalModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">×</span>
            <h3>Reason for Disapproval</h3>
            <form id="disapprovalForm" method="post" action="eventapproval.php">
                <input type="hidden" name="event_id" id="modal_event_id">
                <input type="hidden" name="approval_status" value="Disapproved">
                
                <div class="form-group">
                    <label for="disapproval_reason">Please specify the reason:</label>
                    <textarea id="disapproval_reason" name="disapproval_reason" rows="4" required></textarea>
                </div>
                
                <button type="submit" class="modal-submit-btn">Submit Disapproval</button>
            </form>
        </div>
    </div>
</div>
<div id="event-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div id="modal-body">
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.4.4/dist/umd/popper.min.js" defer></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" defer></script>
    <script type = "text/javascript" src="adminevents.js"></script>
    <script type = "text/javascript">
        function selectcitymunicipality(citymunicipality){
            console.log(Barangay);
        }

        function openDisapprovalModal(eventId) {
            document.getElementById('modal_event_id').value = eventId;
            document.getElementById('disapprovalModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('disapprovalModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('disapprovalModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const filterType = document.getElementById('filter-type');
            const filterStatus = document.getElementById('filter-status');
            const filterDateFrom = document.getElementById('filter-date-from');
            const filterDateTo = document.getElementById('filter-date-to');
            const filterOrganization = document.getElementById('filter-organization');
            const filterLocation = document.getElementById('filter-location');
            const applyBtn = document.getElementById('apply-filters');
            const resetBtn = document.getElementById('reset-filters');
            const eventCards = document.querySelectorAll('.events-card');

            // Apply filters
            applyBtn.addEventListener('click', function() {
                const typeValue = filterType.value;
                const statusValue = filterStatus.value;
                const dateFromValue = filterDateFrom.value;
                const dateToValue = filterDateTo.value;
                const orgValue = filterOrganization.value;
                const locValue = filterLocation.value;

                eventCards.forEach(card => {
                    const cardType = card.dataset.type;
                    const cardStatus = card.dataset.status;
                    const cardDate = card.dataset.date;
                    const cardOrg = card.dataset.organization;
                    const cardLoc = card.dataset.location;

                    let matches = true;

                    // Check type filter
                    if (typeValue !== 'all' && cardType !== typeValue) {
                        matches = false;
                    }

                    // Check status filter
                    if (statusValue !== 'all' && cardStatus !== statusValue) {
                        matches = false;
                    }

                    // Check date range
                    if (dateFromValue && cardDate < dateFromValue) {
                        matches = false;
                    }
                    if (dateToValue && cardDate > dateToValue) {
                        matches = false;
                    }

                    // Check organization
                    if (orgValue !== 'all' && cardOrg !== orgValue) {
                        matches = false;
                    }

                    // Check location
                    if (locValue !== 'all' && cardLoc !== locValue) {
                        matches = false;
                    }

                    // Show/hide card based on matches
                    if (matches) {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });

            // Reset filters
            resetBtn.addEventListener('click', function() {
                filterType.value = 'all';
                filterStatus.value = 'all';
                filterDateFrom.value = '';
                filterDateTo.value = '';
                filterOrganization.value = 'all';
                filterLocation.value = 'all';

                eventCards.forEach(card => {
                    card.classList.remove('hidden');
                });
            });
        });
    </script>
</body>
</html>