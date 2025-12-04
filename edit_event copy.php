<!--
<?php
    session_start();
    include 'database.php';
    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }

    $event_id = isset($_GET['event_id']) ? htmlspecialchars($_GET['event_id']) : '';
?>
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Post</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="eventform.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <script type="text/javascript" src="app.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script>
</head>
<body>
        <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <a href="about.php">About</a>
            <a href="events.php" class="active">Events</a>
            <a href="leaderboards.php">Leaderboards</a>
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
    <aside id="sidebar" class="close">  
        <ul>
            <li>
                <span class="logo"><i class='bx bxs-leaf'></i>ManGrow</span>
                <button onclick= "SidebarToggle()"id="toggle-btn" class="rotate">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="m313-480 155 156q11 11 11.5 27.5T468-268q-11 11-28 11t-28-11L228-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T468-692q11 11 11 28t-11 28L313-480Zm264 0 155 156q11 11 11.5 27.5T732-268q-11 11-28 11t-28-11L492-452q-6-6-8.5-13t-2.5-15q0-8 2.5-15t8.5-13l184-184q11-11 27.5-11.5T732-692q11 11 11 28t-11 28L577-480Z"/></svg>
                </button>
            </li>
            <hr>
            <li>
                <a href="index.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="profile.php" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="dashboard.html" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M440-690v-100q0-42 29-71t71-29h100v100q0 42-29 71t-71 29H440ZM220-450q-58 0-99-41t-41-99v-140h140q58 0 99 41t41 99v140H220ZM640-90q-39 0-74.5-12T501-135l-33 33q-11 11-28 11t-28-11q-11-11-11-28t11-28l33-33q-21-29-33-64.5T400-330q0-100 70-170.5T640-571h241v241q0 100-70.5 170T640-90Zm0-80q67 0 113-47t46-113v-160H640q-66 0-113 46.5T480-330q0 23 5.5 43.5T502-248l110-110q11-11 28-11t28 11q11 11 11 28t-11 28L558-192q18 11 38.5 16.5T640-170Zm1-161Z"/></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M320-240h320v-80H320v80Zm0-160h320v-80H320v80ZM240-80q-33 0-56.5-23.5T160-160v-640q0-33 23.5-56.5T240-880h320l240 240v480q0 33-23.5 56.5T720-80H240Zm280-520v-200H240v640h480v-440H520ZM240-800v200-200 640-640Z"/></svg>
                <span>View</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="reportspage.php" tabindex="-1">Growth Reports</a></li>
                    <li><a href="#" tabindex="-1">Illegal Activities</a></li>
                    <li><a href="#" tabindex="-1">Conservation Programs</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>To Do Lists</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu">
                    <div>
                    <li><a href="#" tabindex="-1">Work</a></li>
                    <li><a href="#" tabindex="-1">Private</a></li>
                    <li><a href="#" tabindex="-1">Coding</a></li>
                    <li><a href="#" tabindex="-1">Gardening</a></li>
                    <li><a href="#" tabindex="-1">School</a></li>
                    </div>
                </ul>
            </li>
            <li>
                <a href="random.html" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Random</span>
                </a>
            </li>
            <?php
                if(isset($_SESSION['accessrole']) && $_SESSION['accessrole'] == "Barangay Official"){
                    ?>
                        <li class="admin-link">
                            <a href="adminpage.php" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M680-280q25 0 42.5-17.5T740-340q0-25-17.5-42.5T680-400q-25 0-42.5 17.5T620-340q0 25 17.5 42.5T680-280Zm0 120q31 0 57-14.5t42-38.5q-22-13-47-20t-52-7q-27 0-52 7t-47 20q16 24 42 38.5t57 14.5ZM480-80q-139-35-229.5-159.5T160-516v-244l320-120 320 120v227q-19-8-39-14.5t-41-9.5v-147l-240-90-240 90v188q0 47 12.5 94t35 89.5Q310-290 342-254t71 60q11 32 29 61t41 52q-1 0-1.5.5t-1.5.5Zm200 0q-83 0-141.5-58.5T480-280q0-83 58.5-141.5T680-480q83 0 141.5 58.5T880-280q0 83-58.5 141.5T680-80ZM480-494Z"/></svg>
                                <span>Administrator Lobby</span>
                            </a>
                        </li>
                    <?php
                }
            ?>
        </ul>
    </aside>
    <main>
        <?php
            if (!empty($event_id)) {
                $query = "SELECT * FROM eventstbl WHERE event_id = ?";
                $stmt = mysqli_prepare($connection, $query);
                
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "s", $event_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($result) > 0) {
                        $event_data = mysqli_fetch_assoc($result);
                    } else {
                        echo "No event found with ID: " . htmlspecialchars($event_id);
                    }
                    
                    mysqli_stmt_close($stmt);
                } else {
                    echo "Database query preparation failed.";
                }
            } else {
                echo "No event ID specified.";
            }
        ?>
        <div class="another-event-form-container">
            <h2>Edit Event</h2>
            <form id="event-creation-form" enctype="multipart/form-data" action="updateevent.php" method="post">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($event_id); ?>">
                <div class="form-group">
                    <label for="program-type">Program Type*</label>
                    <select id="program-type" name="program_type" required disabled>
                        <option value="">Select program type</option>
                        <option value="Status Report" <?php echo ($event_data['program_type'] == 'Status Report') ? 'selected' : ''; ?>>Status Report</option>
                        <option value="Tree Planting" <?php echo ($event_data['program_type'] == 'Tree Planting') ? 'selected' : ''; ?>>Tree Planting</option>
                        <option value="Announcement" <?php echo ($event_data['program_type'] == 'Announcement') ? 'selected' : ''; ?>>Announcement</option>
                    </select>
                    <input type="hidden" name="program_type" value="<?php echo htmlspecialchars($event_data['program_type']); ?>">
                </div>

                <div class="form-group">
                    <label for="event-title">Title of Event*</label>
                    <input type="text" id="event-title" name="title" value="<?php echo $event_data['title']; ?>" required>
                </div>

                <div class="form-group">
                    <label for="event-description">Description*</label>
                    <textarea id="event-description" name="description" rows="4" required><?php echo $event_data['description']; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="organization">Organization*</label>
                    <input type="text" id="organization" name="organization" value="<?= $event_data['organization'] ?>" required>
                </div>

                <div class="form-group">
                    <label for="thumbnail">Thumbnail (Image)*</label>
                    <div class="file-input-container">
                        <input type="file" id="thumbnail" name="thumbnail" accept="image/*">
                        <span class="file-name" id="file-name">
                            <?php echo !empty($event_data['thumbnail']) ? $event_data['thumbnail'] : 'No file chosen'; ?>
                        </span>
                    </div>
                    <div class="image-preview" id="thumbnail-preview">
                        <?php if (!empty($event_data['thumbnail'])): ?>
                            <img src="<?php echo $event_data['thumbnail']; ?>" alt="Thumbnail">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="venue">Venue*</label>
                        <input type="text" id="venue" name="venue" value="<?php echo $event_data['venue']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="barangay">Barangay*</label>
                        <input type="text" id="barangay" name="barangay" value="<?php echo $event_data['barangay']; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City/Municipality*</label>
                        <input type="text" id="city" name="city" value="<?php echo $event_data['city_municipality']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="area-no">Area No*</label>
                        <input type="text" id="area-no" name="area_no" value="<?php echo $event_data['area_no']; ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="start-date">Start Date*</label>
                        <input type="datetime-local" id="start-date" name="start_date" value="<?php 
                            date_default_timezone_set('Asia/Manila'); 
                            echo date('Y-m-d\TH:i', strtotime($event_data['start_date'])); 
                        ?>" 
                        <?php echo (strtotime($event_data['start_date']) <= time()) ? 'disabled' : ''; ?> required>
                        <?php if (strtotime($event_data['start_date']) <= time()): ?>
                            <input type="hidden" name="start_date" value="<?php 
                                echo date('Y-m-d\TH:i', strtotime($event_data['start_date'])); 
                            ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="end-date">End Date*</label>
                        <input type="datetime-local" id="end-date" name="end_date" value="<?php echo $event_data['end_date']; ?>" required>
                    </div>
                </div>

                <div class="form-group" id="qr-code-section">
                    <label for="qrtext">QR Code (URL/Image)*</label>
                    <input type="text" id="qrtext" name="qrtext" value="<?php echo $event_data['qr_url'] ?? 'http://localhost:3000/reportform.php'; ?>" required readonly>
                    <div class="qr-preview" id="qr-preview"></div>
                </div>

                <div class="form-group">
                    <label for="bounty">Reward (eco-points)</label>
                    <input type="number" id="bounty" name="bounty" min="0" value="<?php echo $event_data['eco_points'] ?? '0'; ?>">
                </div>

                <button type="submit" class="submit-btn" name="submit-btn">Edit Event</button>
            </form>
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
    <script>
        document.getElementById('event-creation-form').addEventListener('submit', function(e) {
            const programType = document.getElementById('program-type').value;

            if (programType !== 'Announcement') {
                const canvas = document.querySelector('#qr-preview canvas');
                if (canvas) {
                    const qrImageData = canvas.toDataURL('image/png');
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'qr_image_data';
                    hiddenInput.value = qrImageData;
                    this.appendChild(hiddenInput);
                }
            }

            // Form validation
            const endDate = new Date(document.getElementById('end-date').value);
            const startDate = new Date(document.getElementById('start-date').value);

            if (endDate <= startDate) {
                e.preventDefault();
                alert('End date must be after start date');
            }
        });

        document.getElementById('qrtext').addEventListener('input', function (e) {
            e.preventDefault();
            
            // Get the base URL value (what's shown in the input)
            const baseUrl = document.getElementById('qrtext').value;
            
            // Construct the full URL for the QR code
            const qrCodeUrl = `http://localhost:3000/save_eventurl.php?redirect_url=${encodeURIComponent(baseUrl)}`;
            
            const qrCodeContainer = document.getElementById('qr-preview');
            qrCodeContainer.innerHTML = '';

            const canvas = document.createElement('canvas');
            qrCodeContainer.appendChild(canvas);

            // Generate QR code with the modified URL
            QRCode.toCanvas(canvas, qrCodeUrl, {
                width: 300,
                margin: 1,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function (error) {
                if (error) {
                    console.error(error);
                    return;
                }

                const ctx = canvas.getContext('2d');
                const logo = new Image();
                logo.src = 'images/mangrow-logo.png';
                logo.onload = function () {
                    const logoSize = 60;
                    const x = (canvas.width - logoSize) / 2;
                    const y = (canvas.height - logoSize) / 2;
                    ctx.drawImage(logo, x, y, logoSize, logoSize);
                    
                    // Store the QR code URL in a data attribute (optional)
                    qrCodeContainer.dataset.qrUrl = qrCodeUrl;
                };
            });
        });

        document.getElementById('event-creation-form').addEventListener('input', function () {
            const programType = document.getElementById('program-type').value;
            if (programType === 'Announcement') return;

            const title = document.getElementById('event-title').value;
            const organization = document.getElementById('organization').value;
            const venue = document.getElementById('venue').value;
            const barangay = document.getElementById('barangay').value;
            const city = document.getElementById('city').value;
            const areaNo = document.getElementById('area-no').value;
            const startDate = document.getElementById('start-date').value;

            const qrTextInput = document.getElementById('qrtext');
            const baseUrl = "http://localhost:3000/reportform.php";
            
            if (title && organization && venue && barangay && city && areaNo) {
                // Update the input field with just the base URL and parameters
                qrTextInput.value = `${baseUrl}?programType=${encodeURIComponent(programType)}&title=${encodeURIComponent(title)}&organization=${encodeURIComponent(organization)}&venue=${encodeURIComponent(venue)}&barangay=${encodeURIComponent(barangay)}&city=${encodeURIComponent(city)}&areaNo=${encodeURIComponent(areaNo)}&startDate=${encodeURIComponent(startDate)}`;
                
                // Trigger QR code generation (which will use the base URL to create the modified QR code URL)
                qrTextInput.dispatchEvent(new Event('input'));
            }
        });

        function resetQRCode() {
            const qrTextInput = document.getElementById('qrtext');
            const qrPreview = document.getElementById('qr-preview');
            
            // Reset to default URL (just the base URL)
            qrTextInput.value = "http://localhost:3000/reportform.php";
            
            // Clear the preview
            qrPreview.innerHTML = '';
            delete qrPreview.dataset.qrUrl;
        }

        function toggleQRCodeVisibility() {
            const programType = document.getElementById('program-type').value;
            const qrCodeSection = document.getElementById('qr-code-section');
            
            if (programType === 'Announcement') {
                qrCodeSection.style.display = 'none';
                document.getElementById('qrtext').removeAttribute('required');
                resetQRCode(); // Reset when hiding
            } else {
                qrCodeSection.style.display = 'block';
                document.getElementById('qrtext').setAttribute('required', '');
                // Trigger QR code generation if there's already content
                if (document.getElementById('event-title').value) {
                    document.getElementById('qrtext').dispatchEvent(new Event('input'));
                }
            }
        }

        document.getElementById('program-type').addEventListener('change', function() {
            toggleQRCodeVisibility();
            
            if (this.value !== 'Announcement') {
                document.getElementById('event-creation-form').dispatchEvent(new Event('input'));
            }
        });

        toggleQRCodeVisibility();

        document.getElementById('thumbnail').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileNameElement = document.getElementById('file-name');
            const previewElement = document.getElementById('thumbnail-preview');
            
            if (file) {
                fileNameElement.textContent = file.name;
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.innerHTML = `<img src="${e.target.result}" alt="Thumbnail Preview">`;
                };
                reader.readAsDataURL(file);
            } else {
                fileNameElement.textContent = 'No file chosen';
                previewElement.innerHTML = '';
            }
        });

    </script>
</body>
</html>
