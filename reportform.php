<?php
    session_start();

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }else{
        if(isset($_SESSION['qr_url'])){
            $_SESSION['redirect_url'] = $_SESSION['qr_url'];
        }
        header("Location: reportlogin.php");
        exit();
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }
    // Get the event_id parameter from the URL
    $event_id = isset($_GET['event_id']) ? htmlspecialchars($_GET['event_id']) : '';
    $title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : '';
    $program_type = isset($_GET['programType']) ? htmlspecialchars($_GET['programType']) : '';
    $venue = isset($_GET['venue']) ? htmlspecialchars($_GET['venue']) : '';
    $area_no = isset($_GET['areaNo']) ? htmlspecialchars($_GET['areaNo']) : '';
    $barangay = isset($_GET['barangay']) ? htmlspecialchars($_GET['barangay']) : '';
    $city = isset($_GET['city']) ? htmlspecialchars($_GET['city']) : '';
    $start_date = isset($_GET['startDate']) ? htmlspecialchars($_GET['startDate']) : '';

    
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'submitform.php';
    
    // Get program_type from POST or GET
    $program_type = $_POST['program_type'] ?? $_GET['programType'] ?? '';
    
    if (isset($_SESSION['accessrole'])) {
        $success = false;
        
        try {
            switch ($_SESSION['accessrole']) {
                case "Resident":
                    if ($program_type === 'Status Report') {
                        $success = ResidentSR();
                    } elseif ($program_type === 'Tree Planting') {
                        $success = ResidentTP();
                    }
                    break;
                    
                case "Barangay Official":
                    if ($program_type === 'Status Report') {
                        $success = BarangayOfficialSR();
                    } elseif ($program_type === 'Tree Planting') {
                        $success = BarangayOfficialTP();
                    }
                    break;
                    
                case "Environmental Manager":
                    if ($program_type === 'Status Report') {
                        $success = EnvManagerSR($connection, $event_id, $program_type, $title, $venue, $barangay, $city, $area_no);
                    } elseif ($program_type === 'Tree Planting') {
                        $success = EnvManagerTP();
                    }
                    break;
                    
                default:
                    throw new Exception("Unauthorized access role");
            }
            
            if ($success) {
                header("Location: index.php" );
                exit();
            }
            // Error handling is done within the functions
            
        } catch (Exception $e) {
            $_SESSION['response'] = [
                'status' => 'error',
                'msg' => 'System error: ' . $e->getMessage()
            ];
            error_log("Form controller error: " . $e->getMessage());
        }
        
        exit(); // Stop execution if we reach this point
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Form</title>
    <link rel="stylesheet" href="reportform.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<!-- map links/scripts -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.locatecontrol/dist/L.Control.Locate.min.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="leaflet-locatecontrol-gh-pages\dist\L.Control.Locate.min.js"></script>
    <script type ="text/javascript" src ="app.js" defer></script>
    <style>
        @media (max-width: 800px) {
    body {
      font-size: 14px;
      line-height: 1.4;
      grid-template-rows: 56px 1fr auto; 
    }
  
    h1 {
      font-size: 24px;
    }
  
    .form-div {
      width: 100%;
      padding: 0;
      margin: 0;
      background: var(--secondarybase-clr);
      overflow-x: hidden; 
    }
  
    .form-content-strip {
      width: 100%; 
      padding: 10px; 
      box-sizing: border-box;
      background: var(--accent-clr);
    }
  
    .form-content-strip form{
        box-sizing: border-box;
        max-height: none;
        display: flex;
        flex-direction: column;
        gap: 15px;
      }

    .username p {
      font-size: 14px;
    }
  
    footer {
      max-height: 200px;
      min-width:300px;
      flex-direction: column; 
    }

    /* Profile Menu for Mobile */
    .profile-details {
      width: 200px; /* Narrower menu */
      right: 2px; /* Align with screen edge */
    }

    .form-content{
        border-top: 5px solid var(--base-clr);
        border-left: 1px solid var(--base-clr);
        border-right: 1px solid var(--base-clr);
        border-bottom: 3px solid var(--base-clr);
        background: var(--event-clr);
        box-sizing: border-box;
        border-radius: 12px;
        min-width:150px;
        width:calc(100% - 40px);
        min-height:200px;
        padding:15px;
    }

      .radio-group {
        flex-direction: row;
        flex-wrap: wrap;
      }

    .submit-btn{
        width:200px;
        align-self: flex-end;
    }

    .submit-btn:active{
        background: var(--placeholder-text-clr);
    }

    .tree-inputs {
        grid-template-columns: 1fr;
      }
      
      .gps-input {
        flex-direction: column;
      }

      .measurement-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;

        > input{
            max-width: 120px;
        }
      }

      #tree-measurements-container {
        gap: 6px; 
        align-items: center;
    }
  }
    </style>

</head>
<body>
    <header>
    <h1>Compliance Form</h1>
        <nav class = "navbar">
            <?php 
            if (isset($_SESSION["name"])) {
                // Show profile icon when logged in
                echo '<div class="userbox" onclick="toggleProfilePopup(event)">';
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                    echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="profile-icon">';
                } else {
                    echo '<div class="default-profile-icon"><i class="fas fa-user"></i></div>';
                }
                echo '</div>';
            } else {
                // Show login link when not logged in
                echo '<a href="login.php" class="login-link">Login</a>';
            }
            ?>
            </nav>
        </header>
    <main>
        <?php if(!empty($_SESSION['response'])): ?>
        <div class="flash-container">
            <div class="flash-message flash-<?= $_SESSION['response']['status'] ?>">
                <?= $_SESSION['response']['msg'] ?>
            </div>
        </div>
        <?php 
        unset($_SESSION['response']); 
        endif; 
        ?>
            <!-- Profile Details Popup (positioned relative to header) -->
        <div class="profile-details close" id="profile-details">
            <div class="details-box">
                <?php
                if(isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                        echo '<img src="'.$_SESSION['profile_image'].'" alt="Profile Image" class="big-profile-icon">';
                    } else {
                        echo '<div class="big-default-profile-icon"><i class="fas fa-user"></i></div>';
                    }
                ?>
                <h2><?= isset($_SESSION["name"]) ? $_SESSION["name"] : "" ?></h2>
                <p><?= isset($_SESSION["email"]) ? $_SESSION["email"] : "" ?></p>
                <p><?= isset($_SESSION["accessrole"]) ? $_SESSION["accessrole"] : "" ?></p>
                <p><?= isset($_SESSION["organization"]) ? $_SESSION["organization"] : "" ?></p>
                </div>
        <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <!-- reports page -->
        <div class="form-div">
            <?php
                if (isset($_GET['end_date'])) {
                    $end_date = htmlspecialchars($_GET['end_date']);
                    $formatted_start_date = date('Y-m-d', strtotime($start_date));
                    if ($formatted_start_date > $end_date) {
                        ?><div class="form-content-strip">
                        <form action="" method="post" autocomplete="off" class="mangrove-form" enctype="multipart/form-data">
                        <div class="form-content">
                            <h3>The form is not accepting any responses.</h3><br>
                            <div class="form-group">
                                <div class="introduction">
                                <p style="font-size:18px; line-height:2rem;">Thank you for taking your time but unfortunately you cannot submit reports anymore as the event has already ended. </p>
                                </div>
                            </div>
                        </div>
                        </form>
                          </div>
                        <?php
                    }
                } 
                if (!empty($start_date)) {
                    $formatted_start_date = date('Y-m-d', strtotime($start_date));
                    $current_date = date('Y-m-d');
                    if ($current_date < $formatted_start_date) {
                        ?>
                        <div class="form-content-strip">
                            <form action="" method="post" autocomplete="off" class="mangrove-form" enctype="multipart/form-data">
                                <div class="form-content">
                                    <h3>The form is not accepting any responses.</h3><br>
                                    <div class="form-group">
                                        <div class="introduction">
                                            <p style="font-size:18px; line-height:2rem;">
                                                Thank you for your interest, but you cannot submit reports yet as the event has not started.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php
                        return;
                    }
                }
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == "Resident"){
                    //forms for residents
                    //event form will vary depending on what type of status report is detected
                    if ($program_type === 'Status Report') {
                    ?><div class="form-content-strip">
                    <form action="" method="post" autocomplete="off" class="mangrove-form" enctype="multipart/form-data">
                        <div class="form-content">
                            <div class="introduction">
                                <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                                <br>
                                <p>This event compliance form is classified under <strong><?php echo $program_type; ?></strong>. Here are some of the location details from our event:</p>
                                <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                <br>
                                <p>Please provide accurate and detailed information to ensure proper documentation and follow-up.</p>
                            </div>
                        </div>
                        <!-- Measurement Method Selection (Global for all trees) -->
                        <div class="form-content">
                            <h3>Tree Measurement Setup</h3>
                            <div class="form-group">
                                <label>Measurement Type*</label>
                                <div class="radio-group">
                                    <label><input type="radio" name="measurement_type" value="visual_estimate" checked> Visual Estimate</label>
                                    <label><input type="radio" name="measurement_type" value="height_pole"> Height Pole</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="mangrove_species">Mangrove Species*</label>
                                <select id="mangrove_species" name="mangrove_species" required>
                                    <option value="">Select species</option>
                                    <option value="Rhizophora apiculata">Bakawan lalake (Rhizophora apiculata)</option>
                                    <option value="Rhizophora mucronata">Bakawan babae (Rhizophora mucronata)</option>
                                    <option value="Avicennia marina">Bungalon (Avicennia marina)</option>
                                    <option value="Sonneratia alba">Palapat (Sonneratia alba)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-content">
                            <h3>Tree Measurements</h3>
                            <div id="measurements-container">
                                <!-- Measurements will be added here -->
                            </div>
                            
                            <button type="button" id="add-measurement-btn" class="secondary-btn">
                                + Add Measurement
                            </button>
                            
                            <div class="form-group">
                                <label>Average Height:</label>
                                <div id="avg-height-value">No measurements yet</div>
                                <input type="hidden" name="avg_height" id="avg-height-input">
                            </div>
                        </div>

                        <!-- Environment Section -->
                        <div class="form-content">
                            <h3>Environment Details</h3>
                            
                            <div class="form-group">
                                <label for="soil">Soil Condition</label>
                                <select id="soil" name="soil">
                                    <option value="">Select condition</option>
                                    <option value="dry">Dry</option>
                                    <option value="moist">Moist</option>
                                    <option value="waterlogged">Waterlogged</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="water">Water Condition</label>
                                <select id="water" name="water">
                                    <option value="">Select condition</option>
                                    <option value="clear">Clear</option>
                                    <option value="murky">Murky</option>
                                    <option value="polluted">Polluted</option>
                                </select>
                            </div>
                        </div>
                
                        <!-- Photo Documentation -->
                        <div class="form-content">
                        <h3><i class="fa fa-camera"></i> Photo Evidence</h3>
    
                            <div class="photo-guidelines">
                                <p><strong>Please provide clear photos showing:</strong></p>
                                <ol>
                                    <li>The <strong>entire mangrove</strong> (showing its size and overall condition)</li>
                                    <li>A <strong>close-up</strong> of any damage/issues (if applicable)</li>
                                    <li>The <strong>surrounding area</strong> (to show environmental context)</li>
                                </ol>
                                <p><em>At least 2 photos required (full view + either close-up or surroundings).</em></p>
                            </div>

                            <div class="form-group">
                                <label for="photo-full">Full View of Mangrove*</label>
                                <input type="file" id="photo-full" name="photo_full" accept="image/*" capture="environment" required>
                                <small>Capture the entire tree from a few meters away</small>
                            </div>

                            <div class="form-group">
                                <label for="photo-detail">Close-up or Problem Area</label>
                                <input type="file" id="photo-detail" name="photo_detail" accept="image/*" capture="environment">
                                <small>Zoom in on damaged leaves, trunk issues, or healthy features</small>
                            </div>

                            <div class="form-group">
                                <label for="photo-context">Surrounding Area</label>
                                <input type="file" id="photo-context" name="photo_context" accept="image/*" capture="environment">
                                <small>Show nearby water, other plants, or human activities</small>
                            </div>
                        </div>
                
                        <!-- Additional Notes -->
                        <div class="form-content">
                            <h3>Additional Information</h3>
                            
                            <div class="form-group">
                                <label for="notes">Observations</label>
                                <textarea id="notes" name="notes" rows="3" placeholder="Any unusual changes or threats noticed..."></textarea>
                            </div>
                        </div>
                
                        <button type="submit" name="submit_report" class="submit-btn">Submit Report</button>
                    </form>
                </div>
                    <?php
                    }else if($program_type === 'Tree Planting'){
                        ?><div class="form-content-strip">
                        <form action="" method="post" autocomplete="off" class="mangrove-form" enctype="multipart/form-data">
                            <div class="form-content">
                                    <div class="introduction">
                                    <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                                    <br>
                                    <p>This event compliance form is classified under <strong><?php echo $program_type; ?></strong>. Here are some of the location details from our event:</p>
                                    <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                    <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                    <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                    <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                    <br>
                                    <p>Please provide accurate and detailed information to ensure proper documentation and follow-up.</p>
                                </div>
                            </div>

                            <!-- Participant Information -->
                            <div class="form-content">
                                <h3><i class="fas fa-user"></i> Participant Information</h3>
                                <div class="form-group">
                                    <label for="fullname">Full Name*</label>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo $_SESSION['name'];?>" required readonly>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email*</label>
                                        <input type="email" id="email" name="email" value="<?php echo $_SESSION['email'];?>" required readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Mobile Number*</label>
                                        <input type="tel" id="phone" name="phone" pattern="[0-9]{11}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="11" placeholder="11 digit mobile number (e.g. 09171234567)" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity Details -->
                            <div class="form-content">
                                <h3><i class="fas fa-calendar-alt"></i> Activity Details</h3>
                                <div class="form-row">
                                    <?php
                                    date_default_timezone_set('Asia/Manila');
                                    $formatted_start_date = date('Y-m-d', strtotime($start_date));
                                    ?>
                                    <div class="form-group">
                                        <label for="planting_date">Date of Planting*</label>
                                        <input type="date" id="planting_date" name="planting_date" value="<?php echo $formatted_start_date; ?>" min="<?php echo $formatted_start_date; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="planting_time">Time of Activity*</label>
                                        <input type="time" id="planting_time" name="planting_time" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="tree_count">Number of Trees Planted*</label>
                                    <input type="number" id="tree_count" name="tree_count" min="1" required>
                                </div>
                            </div>

                            <!-- Location Information -->
                            <div class="form-content">
                                <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                                <div class="form-group">
                                    <label>GPS Coordinates*</label>
                                    <div id="map" style="height: 300px; margin-bottom: 10px;"></div>
                                    <div class="input-group">
                                        <input type="text" id="latitude" name="latitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Latitude">
                                        <input type="text" id="longitude" name="longitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Longitude">
                                        <button type="button" id="locate-me-btn" class="btn">
                                            <i class="fas fa-location-arrow"></i> Get Precise Location
                                        </button>
                                    </div>
                                    <div class="location-status">
                                        <i class="fas fa-info-circle"></i> Click button to detect location
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="barangay">Barangay*</label>
                                        <input type="text" id="barangay" name="barangay" value="<?php echo $barangay;?>" required readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="site_name">Site Name*</label>
                                        <input type="text" id="site_name" name="site_name" value="<?php echo $venue;?>" required readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Mangrove Species -->
                            <div class="form-content">
                                <h3><i class="fas fa-tree"></i> Mangrove Species Planted</h3>
                                <div class="form-group">
                                    <label>Select all species planted*</label>
                                    
                                    <div class="checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="species[]" value="Rhizophora apiculata">
                                            <span class="checkmark"></span>
                                            Bakawan lalake (Rhizophora apiculata)
                                        </label>
                                        
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="species[]" value="Rhizophora mucronata">
                                            <span class="checkmark"></span>
                                            Bakawan babae (Rhizophora mucronata)
                                        </label>
                                        
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="species[]" value="Avicennia marina">
                                            <span class="checkmark"></span>
                                            Bungalon (Avicennia marina)
                                        </label>
                                        
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="species[]" value="Sonneratia alba">
                                            <span class="checkmark"></span>
                                            Palapat (Sonneratia alba)
                                        </label>
                                        
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="other-species-checkbox" name="species[]" value="Other">
                                            <span class="checkmark"></span>
                                            Other (please specify)
                                        </label>
                                        
                                        <div class="form-group other-species" style="display:none; margin-top:10px;">
                                            <input type="text" name="other_species" placeholder="Specify other species">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Photo Documentation -->
                            <div class="form-content">
                                <h3><i class="fas fa-camera"></i> Photo Evidence*</h3>
                                <div class="photo-guidelines">
                                    <p><strong>Upload clear photos showing:</strong></p>
                                    <ul>
                                        <li>Participants planting mangroves</li>
                                        <li>Close-up of planted seedlings</li>
                                        <li>Wide shot of planting area</li>
                                    </ul>
                                </div>
                                
                                <div class="form-group">
                                    <label for="planting_photo">Planting Activity Photo*</label>
                                    <input type="file" id="planting_photo" name="planting_photo" accept="image/*" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="seedling_photo">Seedling Close-up*</label>
                                    <input type="file" id="seedling_photo" name="seedling_photo" accept="image/*">
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_photo">Site Overview Photo*</label>
                                    <input type="file" id="site_photo" name="site_photo" accept="image/*">
                                </div>
                            </div>

                            <!-- Monitoring Information -->
                            <div class="form-content">
                                <?php
                                date_default_timezone_set('Asia/Manila');
                                $formatted_start_date = date('Y-m-d', strtotime($start_date));
                                ?>
                                <h3><i class="fas fa-calendar-check"></i> Monitoring Schedule</h3>
                                <div class="form-group">
                                    <label for="monitoring_date">Follow-up Date*</label>
                                    <input type="date" id="monitoring_date"  name="monitoring_date" value="<?php echo $formatted_start_date; ?>" min="<?php echo $formatted_start_date; ?>">
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="form-content">
                                <h3><i class="fas fa-edit"></i> Additional Notes</h3>
                                <div class="form-group">
                                    <label for="remarks">Remarks/Observations</label>
                                    <textarea id="remarks" name="remarks" rows="3" placeholder="Any challenges faced, special conditions, or additional information..."></textarea>
                                </div>
                            </div>

                            <div class="form-footer">
                                <button type="submit" name="submit_report" class="submit-btn">Submit Participation Form</button>
                            </div>
                        </form>
                    </div>
                        <?php
                    }
                }
                else if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == "Barangay Official"){
                    //forms for barangay officials
                    if ($program_type === 'Status Report') {
                    ?><div class="form-content-strip">
                    <form id="mangroveMonitoringForm" class="mangrove-form" method="POST" enctype="multipart/form-data">
                        <div class="form-content">
                            <div class="introduction">
                            <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                            <br>
                                <p>This event compliance form is classified under <strong><?php echo $program_type; ?></strong>. Here are some of the location details from our event:</p>
                                <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                <br>
                                <p>Please provide accurate and detailed information to ensure proper documentation and follow-up.</p>
                            </div>
                        </div>
                        <!-- SECTION 1: TREE STATUS AND BASIC INFORMATION -->
                        <div class="form-content">
                            <h3><i class="fa fa-info-circle"></i> Tree Status and Basic Information</h3>
                            
                            <div class="form-group">
                                <label for="tree-status">Mangrove Status*</label>
                                <select id="tree-status" name="tree_status" required>
                                    <option value="">Select status</option>
                                    <option value="Alive">Alive</option>
                                    <option value="Growing">Growing</option>
                                    <option value="Damaged">Damaged</option>
                                    <option value="Dead">Dead</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="growth-stage">Growth Stage*</label>
                                <select id="growth-stage" name="growth_stage" required>
                                    <option value="">Select growth stage</option>
                                    <option value="Seedling">Seedling (0-1.5m)</option>
                                    <option value="Sapling">Sapling (1.5-3m)</option>
                                    <option value="Mature">Mature (>3m)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="mangrove-species">Mangrove Species*</label>
                                <select id="mangrove-species" name="mangrove_species" required>
                                    <option value="">Select species</option>
                                    <option value="Rhizophora apliculata">Bakawan Lalake (Rhizophora apliculata)</option>
                                    <option value="Rhizophora mucronata">Bakawan Babae (Rhizophora mucronata)</option>
                                    <option value="Avicennia marina">Bungalon (Avicennia marina)</option>
                                    <option value="Sonneratia alba">Palapat (Sonneratia alba)</option>
                                    <option value="Other">Other (specify in notes)</option>
                                </select>
                            </div>
                        </div>

                        <!-- SECTION 2: LOCATION INFORMATION -->
                        <div class="form-content">
                            <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                            <div class="form-group">
                                <label>GPS Coordinates*</label>
                                <div id="map" style="height: 300px; margin-bottom: 10px;"></div>
                                <div class="input-group">
                                    <input type="text" id="latitude" name="latitude" required
                                        pattern="-?\d{1,3}\.\d{1,6}"
                                        placeholder="Latitude">
                                    <input type="text" id="longitude" name="longitude" required
                                        pattern="-?\d{1,3}\.\d{1,6}"
                                        placeholder="Longitude">
                                    <button type="button" id="locate-me-btn" class="btn">
                                        <i class="fas fa-location-arrow"></i> Get Precise Location
                                    </button>
                                </div>
                                <div class="location-status">
                                    <i class="fas fa-info-circle"></i> Click button to detect location
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="tidal-zone">Tidal Information*</label>
                                <select id="tidal-zone" name="tidal_zone" required>
                                    <option value="">Select tidal zone</option>
                                    <option value="High">High Tide Area</option>
                                    <option value="Low">Low Tide Area</option>
                                    <option value="Intertidal">Intertidal Zone</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Nearby Human Activities (Select all that apply)</label>
                                <div class="checkbox-group">
                                    <label><input type="checkbox" name="human_activities[]" value="Fishing Activities"> Fishing Activities</label>
                                    <label><input type="checkbox" name="human_activities[]" value="Garbage Dumping"> Garbage Dumping</label>
                                    <label><input type="checkbox" name="human_activities[]" value="Housing Projects"> Housing Projects</label>
                                    <label><input type="checkbox" name="human_activities[]" value="Agriculture"> Agriculture</label>
                                    <label><input type="checkbox" name="human_activities[]" value="Tourism"> Tourism</label>
                                    <label><input type="checkbox" name="human_activities[]" value="None" class="none-option"> None</label>
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 3: COMPLIANCE AND STATUS REPORTING -->
                        <div class="form-content">
                            <h3><i class="fa fa-clipboard-check"></i> Compliance and Status Reporting</h3>
                            
                            <div class="form-group">
                                <label for="compliance-status">Compliance Status*</label>
                                <select id="compliance-status" name="compliance_status" required>
                                    <option value="">Select status</option>
                                    <option value="Compliant">Fully Compliant</option>
                                    <option value="Partial">Partially Compliant</option>
                                    <option value="Non-Compliant">Non-Compliant</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status-report">Status Reporting Details*</label>
                                <textarea id="status-report" name="status_report" placeholder="Describe details about the current status including any issues observed..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea id="notes" name="notes" placeholder="Any additional observations or comments..."></textarea>
                            </div>
                        </div>

                        <!-- SECTION 4: PHOTO DOCUMENTATION -->
                        <div class="form-content">
                            <h3><i class="fa fa-camera"></i> Photo Documentation</h3>
                            
                            <div class="form-group">
                                <label for="tree-photo">Tree Photo* (showing overall condition)</label>
                                <input type="file" id="tree-photo" name="tree_photo" accept="image/*" capture="environment" required>
                            </div>

                            <div class="form-group">
                                <label for="location-photo">Location Photo (showing surrounding area)</label>
                                <input type="file" id="location-photo" name="location_photo" accept="image/*" capture="environment">
                            </div>

                            <div class="form-group">
                                <label for="damage-photo">Damage Photo (if applicable)</label>
                                <input type="file" id="damage-photo" name="damage_photo" accept="image/*" capture="environment">
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" name="submit_report">
                            <i class="fa fa-paper-plane"></i> Submit Report
                        </button>
                    </form>
                </div>
                <?php
                    }else if($program_type === 'Tree Planting') {
                    ?><div class="form-content-strip">
                        <form method="post" id="barangayOfficialForm" class="mangrove-form" enctype="multipart/form-data">
                            <div class="form-content">
                                <div class="introduction">
                                    <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                                    <br>
                                    <p>This event compliance form is classified under <strong><?php echo $program_type; ?></strong>. Here are some of the location details from our event:</p>
                                    <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                    <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                    <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                    <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                    <br>
                                    <p>As a Barangay Official, please provide the following details to verify this planting activity.</p>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-user-tie"></i> Official Information</h3>
                                <div class="form-group">
                                    <label for="official-name">Full Name*</label>
                                    <input type="text" id="official-name" name="official_name" value="<?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : '' ?>" readonly disabled required>
                                    <input type="hidden" name="official_name" value="<?= isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : '' ?>">
                                </div>
                                <div class="form-group">
                                    <label for="organization">Organization / Affiliation*</label>
                                    <input type="text" id="organization" name="organization" value="<?= isset($_SESSION['organization']) ? htmlspecialchars($_SESSION['organization']) : '' ?>" readonly disabled required>
                                    <input type="hidden" name="organization" value="<?= isset($_SESSION['organization']) ? htmlspecialchars($_SESSION['organization']) : '' ?>">
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-tree"></i> Planting Details</h3>
                                <div class="form-group">
                                    <label for="planting-date">Planting Date*</label>
                                    <?php
                                        date_default_timezone_set('Asia/Manila');
                                        $plantingDateValue = !empty($start_date) ? date('Y-m-d\TH:i', strtotime($start_date)) : date('Y-m-d\TH:i');
                                    ?>
                                    <input type="datetime-local" id="planting-date" name="planting_date" value="<?= $plantingDateValue ?>" min="<?= $plantingDateValue ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="tree-count">Number of Trees Planted (per 100 square meter)</label>
                                    <input type="number" id="tree-count" name="tree_count" min="1" placeholder="Optional">
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-map-marked-alt"></i> Site Assessment</h3>
                                <div class="form-group">
                                    <label for="site-condition">Site Condition*</label>
                                    <select id="site-condition" name="site_condition" required>
                                        <option value="">Select condition</option>
                                        <option value="Good">Good</option>
                                        <option value="Needs Restoration">Needs Restoration</option>
                                        <option value="Degraded">Degraded</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-images"></i> Documentation</h3>
                                <div class="form-group">
                                    <label for="planting-photo-1">Planting Activity Photo*</label>
                                    <input type="file" id="planting-photo-1" name="planting_photo_1" accept="image/*" required>
                                    <small class="form-text">Upload a clear photo showing participants planting mangroves.</small>
                                </div>
                                <div class="form-group">
                                    <label for="planting-photo-2">Seedling Close-up</label>
                                    <input type="file" id="planting-photo-2" name="planting_photo_2" accept="image/*">
                                    <small class="form-text">Provide a close-up photo of the planted seedling(s).</small>
                                </div>
                                <div class="form-group">
                                    <label for="planting-photo-3">Site Overview Photo</label>
                                    <input type="file" id="planting-photo-3" name="planting_photo_3" accept="image/*">
                                    <small class="form-text">Show a wide shot of the planting area and surroundings.</small>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-calendar-check"></i> Monitoring</h3>
                                <?php
                                date_default_timezone_set('Asia/Manila');
                                $current_date = date('Y-m-d'); // Get the current date in YYYY-MM-DD format
                                ?>
                                <label for="monitoring_date">Monitoring Schedule</h3>
                                <div class="form-group">
                                    <label for="monitoring_date">Follow-up Date*</label>
                                    <input type="date" id="monitoring_date"  name="monitoring_date" value="<?php echo $current_date; ?>" min="<?php echo $current_date; ?>">
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-comment-dots"></i> Validation Notes</h3>
                                <div class="form-group">
                                    <label for="validation-notes">Comments / Validation Notes*</label>
                                    <textarea id="validation-notes" name="validation_notes" rows="4" placeholder="Your observations and validation notes..." required></textarea>
                                </div>
                            </div>

                            <button type="submit" class="submit-btn">
                                <i class="fa fa-check-circle"></i> Submit Official Report
                            </button>
                        </form>
                    </div>
                    <?php
                }
                    }
                    else if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == "Environmental Manager"){
                        if($program_type === 'Status Report'){
                        ?><div class="form-content-strip">
                        <form method="post" id="envManagerForm" class="mangrove-form" enctype="multipart/form-data">
                            <div class="form-content">
                                <div class="introduction">
                                    <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                                    <br>
                                    <p>This scientific monitoring form is classified under <strong><?php echo $program_type; ?></strong>. Here are the location details:</p>
                                    <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                    <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                    <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                    <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                    <br>
                                    <p>As an Environmental Specialist, please provide the following scientific data.</p>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-user-graduate"></i> Specialist Information</h3>
                                <div class="form-group">
                                    <label for="specialist-name">Full Name*</label>
                                    <input type="text" id="specialist-name" name="specialist_name" placeholder="Your full name" value="<?php echo $_SESSION['name']?>" required readonly>
                                </div>
                                <div class="form-group">
                                    <label for="specialist-org">Organization / Group*</label>
                                    <input type="text" id="specialist-org" name="specialist_org" placeholder="Your scientific organization"  value="<?php echo $_SESSION['organization']?>" required readonly>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-flask"></i> Environmental Data</h3>
                                <div class="form-group">
                                    <label for="soil-type">Soil Condition*</label>
                                    <select id="soil-type" name="soil_type" required>
                                        <option value="">Select soil type</option>
                                        <option value="Sandy">Sandy</option>
                                        <option value="Clay">Clay</option>
                                        <option value="Loamy">Loamy</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <!-- Add Hydrological Conditions here -->
                                <div class="form-group">
                                    <label for="hydrological-conditions">Hydrological Conditions*</label>
                                    <select id="hydrological-conditions" name="hydrological_conditions" required>
                                        <option value="">Select hydrological condition</option>
                                        <option value="Tidal">Tidal</option>
                                        <option value="Subtidal">Subtidal</option>
                                        <option value="Intertidal">Intertidal</option>
                                        <option value="Supratidal">Supratidal</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="water-salinity">Water Salinity (ppt)</label>
                                    <input type="number" id="water-salinity" name="water_salinity" min="0" step="0.1" placeholder="Optional">
                                </div>
                                <div class="form-group">
                                    <label for="pollution">Pollution Indicators</label>
                                    <textarea id="pollution" name="pollution" rows="2" placeholder="Describe any pollution observed"></textarea>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-seedling"></i> Planting Details</h3>
                                <div class="form-group">
                                    <label for="planting-datetime">Date & Time of Planting*</label>
                                    <?php
                                        date_default_timezone_set('Asia/Manila');
                                        $plantingDateValue = !empty($start_date) ? date('Y-m-d\TH:i', strtotime($start_date)) : date('Y-m-d\TH:i');
                                    ?>
                                    <?php
                                        date_default_timezone_set('Asia/Manila');
                                        $currentDateTime = date('Y-m-d\TH:i');
                                    ?>
                                    <input type="datetime-local" id="planting-datetime" name="planting_datetime" value="<?= $currentDateTime ?>" min="<?= $plantingDateValue ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="scientific-species">Mangrove Species (Scientific Name)*</label>
                                    <select id="scientific-species" name="scientific_species" required>
                                        <option value="">Select species</option>
                                        <option value="Rhizophora apiculata">Bakawan lalake (Rhizophora apiculata)</option>
                                        <option value="Rhizophora mucronata">Bakawan babae (Rhizophora mucronata)</option>
                                        <option value="Avicennia marina">Bungalon (Avicennia marina)</option>
                                        <option value="Sonneratia alba">Palapat (Sonneratia alba)</option>
                                    </select>
                                </div>
                                
                                <!-- Add Planting Density here -->
                                <div class="form-group">
                                    <label for="planting-density">Planting Density (trees per m²)*</label>
                                    <input type="number" id="planting-density" name="planting_density" min="0" step="0.01" placeholder="e.g. 1.5" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group half-width">
                                        <label for="tree-quantity">Tree Quantity*</label>
                                        <input type="number" id="tree-quantity" name="tree_quantity" min="1" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                                <div class="form-group">
                                    <label>GPS Coordinates*</label>
                                    <div id="map" style="height: 300px; margin-bottom: 10px;"></div>
                                    <div class="input-group">
                                        <input type="text" id="latitude" name="latitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Latitude">
                                        <input type="text" id="longitude" name="longitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Longitude">
                                        <button type="button" id="locate-me-btn" class="btn">
                                            <i class="fas fa-location-arrow"></i> Get Precise Location
                                        </button>
                                    </div>
                                    <div class="location-status">
                                        <i class="fas fa-info-circle"></i> Click button to detect location
                                    </div>
                                </div>
                            </div>

                            <div class="form-content">
                                <h3><i class="fa fa-users"></i> Community Involvement</h3>
                                <div class="form-group">
                                    <label for="community-participants">Number of Community Participants*</label>
                                    <input type="number" id="community-participants" name="community_participants" min="0" required>
                                </div>
                            </div>
                            
                            <div class="form-content">
                                <h3><i class="fa fa-image"></i> Upload Site Image</h3>
                                <div class="form-group">
                                    <label for="site_image">Site Image*</label>
                                    <input type="file" id="site_image" name="site_image" accept="image/*" required>
                                    <small>Please upload a clear image of the site.</small>
                                </div>
                            </div>

                            <button type="submit" class="submit-btn">
                                <i class="fa fa-microscope"></i> Submit Scientific Report
                            </button>
                        </form>
                    </div>
                    <?php
                    }else if($program_type === 'Tree Planting') {
                    ?><div class="form-content-strip">
                        <form method="post" id="environmentalManagerForm" class="mangrove-form">
                            <!-- SECTION 1: PLANTING ACTIVITY DATA -->
                             <div class="form-content">
                                <div class="introduction">
                                <p>Thank you for participating in the event <strong><?php echo $title; ?></strong>.</p>
                                <br>
                                    <p>This event compliance form is classified under <strong><?php echo $program_type; ?></strong>. Here are some of the location details from our event:</p>
                                    <p>Venue: <strong><?php echo $venue; ?></strong></p>
                                    <p>Barangay: <strong><?php echo $barangay; ?></strong></p>
                                    <p>City/Municipality: <strong><?php echo $city; ?></strong></p>
                                    <p>Area Number: <strong><?php echo $area_no; ?></strong>.</p>
                                    <br>
                                    <p>Please provide accurate and detailed information to ensure proper documentation and follow-up.</p>
                                </div>
                            </div>
                            <div class="form-content">
                                <h3><i class="fa fa-seedling"></i> Planting Details</h3>
                                <div class="form-group">
                                    <label for="planting-datetime">Planting Date*</label>
                                    <?php
                                        date_default_timezone_set('Asia/Manila');
                                        $plantingDateValue = !empty($start_date) ? date('Y-m-d', strtotime($start_date)) : date('Y-m-d\TH:i');
                                    ?>
                                    <?php
                                        date_default_timezone_set('Asia/Manila');
                                        $currentDateTime = date('Y-m-d');
                                    ?>
                                    <input type="date" id="planting-datetime" name="planting_datetime" value="<?= $currentDateTime ?>" min="<?= $plantingDateValue ?>" required>
                                </div>
                    
                                <div class="form-group">
                                    <label for="trees_planted">Number of Mangroves Planted*</label>
                                    <input type="number" name="trees_planted" id="trees_planted" min="1" required>
                                </div>
                    
                                <div class="form-group">
                                    <label for="number_participants">Number of Participants*</label>
                                    <input type="number" name="number_participants" id="number_participants" min="1" required>
                                </div>
                    
                                <div class="form-group">
                                    <label for="group-name">Group/Organization Name</label>
                                    <?php
                                        // Fetch organization from eventstbl using $event_id
                                        $organization = '';
                                        if (!empty($event_id)) {
                                            require_once 'database.php'; // adjust path if needed
                                            $stmt = $connection->prepare("SELECT organization FROM eventstbl WHERE event_id = ?");
                                            $stmt->bind_param("s", $event_id);
                                            $stmt->execute();
                                            $stmt->bind_result($organization);
                                            $stmt->fetch();
                                            $stmt->close();
                                        }
                                    ?>
                                    <input type="text" name="group_name" id="group_name" placeholder="Name of organizing group" value="<?= htmlspecialchars($organization) ?>" required readonly>
                                </div>
                            </div>
                    
                            <!-- SECTION 2: LOCATION DATA -->
                            <div class="form-content">
                                <h3><i class="fas fa-map-marker-alt"></i> Location Details</h3>
                                <div class="form-group">
                                    <label>GPS Coordinates*</label>
                                    <div id="map" style="height: 300px; margin-bottom: 10px;"></div>
                                    <div class="input-group">
                                        <input type="text" id="latitude" name="latitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Latitude">
                                        <input type="text" id="longitude" name="longitude" required
                                            pattern="-?\d{1,3}\.\d{1,6}"
                                            placeholder="Longitude">
                                        <button type="button" id="locate-me-btn" class="btn">
                                            <i class="fas fa-location-arrow"></i> Get Precise Location
                                        </button>
                                    </div>
                                    <div class="location-status">
                                        <i class="fas fa-info-circle"></i> Click button to detect location
                                    </div>
                                </div>
                            
                    
                                <div class="form-group">
                                    <label for="site_description">Site Description*</label>
                                    <textarea id="site_description" name="site_description" placeholder="Describe the planting site characteristics..." required></textarea>
                                </div>
                            </div>
                            <!-- SECTION 3: ENVIRONMENTAL CONDITIONS -->
                            <div class="form-content">
                                <h3><i class="fa fa-flask"></i> Environmental Conditions</h3>
                                
                                <div class="form-group">
                                    <label for="soil_condition">Soil Condition*</label>
                                    <select id="soil_condition" name="soil_condition" required>
                                        <option value="">Select soil condition</option>
                                        <option value="excellent">Excellent (fertile, good texture)</option>
                                        <option value="good">Good</option>
                                        <option value="fair">Fair (some compaction)</option>
                                        <option value="poor">Poor (compacted, infertile)</option>
                                        <option value="contaminated">Contaminated</option>
                                    </select>
                                </div>
                    
                                <div class="form-group">
                                    <label for="water_condition">Water Condition*</label>
                                    <select id="water_condition" name="water_condition" required>
                                        <option value="">Select water condition</option>
                                        <option value="excellent">Excellent (clean, proper salinity)</option>
                                        <option value="good">Good</option>
                                        <option value="fair">Fair (some pollution)</option>
                                        <option value="poor">Poor (polluted, improper salinity)</option>
                                    </select>
                                </div>
                    
                                <div class="form-group">
                                    <label for="environmental_observations">Environmental Observations*</label>
                                    <textarea id="environmental_observations" name="environmental_observations" placeholder="Note any significant observations about the environment..." required></textarea>
                                </div>
                            </div>
                    
                            <!-- SECTION 4: PHOTO DOCUMENTATION -->
                            <div class="form-content">
                                <h3><i class="fa fa-images"></i> Photo Documentation</h3>
                                
                                <div class="form-group">
                                    <label for="before_photo">Before Photo* (pre-planting condition)</label>
                                    <input type="file" id="before_photo" name="before_photo" accept="image/*" required>
                                    <input type="date" id="before_photo_date" name="before_photo_date" placeholder="Date of before photo">
                                </div>
                    
                                <div class="form-group">
                                    <label for="after_photo">After Photo* (current condition)</label>
                                    <input type="file" id="after_photo" name="after_photo" accept="image/*" required>
                                </div>
                    
                                <div class="form-group">
                                    <label for="additional_photos">Additional Photos (optional)</label>
                                    <input type="file" id="additional_photos" name="additional_photos[]" accept="image/*" multiple>
                                </div>
                            </div>
                    
                            <!-- SECTION 5: STATUS REPORTING -->
                            <div class="form-content">
                                <h3><i class="fa fa-clipboard-list"></i> Status Reporting</h3>
                                
                                <div class="form-group">
                                    <label for="survival_rate">Estimated Survival Rate (%)</label>
                                    <input type="number" id="survival_rate" name="survival_rate" min="0" max="100" placeholder="0-100">
                                </div>
                    
                                <div class="form-group">
                                    <label for="progress_report">Progress Report*</label>
                                    <textarea id="progress_report" name="progress_report" placeholder="Detail the progress of the conservation program..." required></textarea>
                                </div>
                    
                                <div class="form-group">
                                    <label for="challenges">Challenges Encountered</label>
                                    <textarea id="challenges" name="challenges" placeholder="List any challenges faced..."></textarea>
                                </div>
                            </div>
                    
                            <button type="submit" class="submit-btn">
                                <i class="fa fa-paper-plane"></i> Submit Conservation<br>Report
                            </button>
                        </form>
                    </div>
                        <?php
                        }
                    }
            ?>
        </div>
    </main>
    <footer>
        <div id="right-footer">
            <p>This website is developed by ManGrow. All Rights Reserved.</p>
        </div>
    </footer>            
    <script type ="text/javascript" src ="reportform.js" defer></script>
    <script type ="text/javascript">

    // 1. Initialize map
    var map = L.map('map').setView([14.64852, 120.47318], 15);
    L.tileLayer('https://api.maptiler.com/maps/openstreetmap/{z}/{x}/{y}.jpg?key=w1gk7TVN9DDwIGdvJ31q', {
        attribution: ''
    }).addTo(map);

    // 2. Location tracking variables
    var currentLocation = {
        marker: null,
        circle: null,
        watchId: null
    };

    // 3. Core function to get location
    function getLocation() {
        // Clear previous
        if (currentLocation.marker) map.removeLayer(currentLocation.marker);
        if (currentLocation.circle) map.removeLayer(currentLocation.circle);
        if (currentLocation.watchId) navigator.geolocation.clearWatch(currentLocation.watchId);
        
        // Update UI
        var status = document.querySelector('.location-status');
        status.innerHTML = '<i class="fas fa-satellite fa-spin"></i> Acquiring precise location...';
        status.style.color = '#0066cc';
        
        // Configuration
        var options = {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 30000
        };
        
        // Get location
        if (navigator.geolocation) {
            currentLocation.watchId = navigator.geolocation.watchPosition(
                function(position) {
                    handleLocationSuccess(position);
                },
                function(error) {
                    handleLocationError(error);
                },
                options
            );
        } else {
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Geolocation not supported';
            status.style.color = 'red';
        }
}

    // 4. Success handler
    function handleLocationSuccess(position) {
        var coords = {
            lat: position.coords.latitude,
            lng: position.coords.longitude
        };
        var accuracy = position.coords.accuracy;
        
        // Update form field
        document.getElementById('latitude').value = coords.lat.toFixed(6);
        document.getElementById('longitude').value = coords.lng.toFixed(6);
        
        // Update map
        map.setView([coords.lat, coords.lng], 16);
    
        if (currentLocation.marker) map.removeLayer(currentLocation.marker);
        if (currentLocation.circle) map.removeLayer(currentLocation.circle);
        
        currentLocation.circle = L.circle([coords.lat, coords.lng], {
            radius: accuracy,
            color: '#3388ff',
            fillColor: '#3388ff',
            fillOpacity: 0.2
        }).addTo(map);
        
        currentLocation.marker = L.marker([coords.lat, coords.lng], {
            icon: L.divIcon({
                className: 'precision-marker',
                html: '<i class="fas fa-crosshairs"></i>',
                iconSize: [24, 24]
            })
        }).addTo(map);
        
        updateAccuracyStatus(accuracy);
    }

    // 5. Error handler
    function handleLocationError(error) {
        var status = document.querySelector('.location-status');
        var message = '';
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = "Location access was denied. Please enable permissions.";
                break;
            case error.POSITION_UNAVAILABLE:
                message = "Location information is unavailable.";
                break;
            case error.TIMEOUT:
                message = "The request timed out. Please try again outdoors.";
                break;
            default:
                message = "An unknown error occurred.";
        }
        
        status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + message;
        status.style.color = 'red';
    }

    // 6. Accuracy display
    function updateAccuracyStatus(accuracy) {
        var status = document.querySelector('.location-status');
        
        if (accuracy < 20) {
            status.innerHTML = '<i class="fas fa-check-circle"></i> High precision location';
            status.style.color = 'green';
        } else if (accuracy < 50) {
            status.innerHTML = '<i class="fas fa-info-circle"></i> Moderate precision';
            status.style.color = 'orange';
        } else {
            status.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Low accuracy - move outdoors';
            status.style.color = 'red';
        }
    }

    // 7. Button click handler
    document.getElementById('locate-me-btn').addEventListener('click', function() {
        getLocation();
    });

    // 8. Initial cleanup on page load
    window.addEventListener('load', function() {
        if (currentLocation.watchId) {
            navigator.geolocation.clearWatch(currentLocation.watchId);
        }
    });

    document.querySelector('form').addEventListener('submit', function(e) {
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    
    if (!lat || !lng) {
        e.preventDefault();
        alert('Please provide both latitude and longitude');
    }
});
    </script>
</body>
</html>