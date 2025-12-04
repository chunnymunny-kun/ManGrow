<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="stilo.css">
    <script type ="text/javascript" src ="app.js" defer></script>
    <title>Document</title>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <a href="about.php">About</a>
            <a href="events.php">Events</a>
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
            <li class="active">
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
                    <li><a href="#" tabindex="-1">My Events</a></li>
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
    </aside>
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
                <div class="profile-link-container">
                    <a href="profileform.php" class="profile-link">Edit Profile <i class="fa fa-angle-double-right"></i></a>
                </div>
        </div>
        <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out <i class="fa fa-sign-out" aria-hidden="true"></i></button>
        </div>
        <div class= "home-container">
        <section class="s1">
            <div class ="background-img"><img src="images/mangrove.webp" alt ="Mangrove">
            <h1>ManGrow: Mangrove Conservation and Eco-Tracking System with 2D Mapping</h1>
            <button type="button" class="abt-project" onclick="window.location.href='about.php';"><span class="abt-project-span"></span>About Project</button>
            </div>
        </section>
            <br>
        <section class="s2">
            <div class="one">
                <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. 
                    We use technology such as GIS-powered mapping with eco-tracking features and promote community engagement to establish sustainable mangrove conservation. 
                    The technology enables mangrove ecosystem protection and strengthens local populations so they actively engage in conservation work.</p>
                </div>
        </section>
        <section class="s3">
            <div class="two">
            <div class="community-hub">
                <div class="hub-grid">
                    <!-- About -->
                    <div class="grid-item" data-modal="modal-one">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Community News</h3>
                            <p>Latest updates and announcements</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="grid-item" data-modal="modal-two">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Upcoming Events</h3>
                            <p>Join our next gathering</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="grid-item" data-modal="modal-three">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Success Stories</h3>
                            <p>Inspiring member journeys</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                    <!-- About -->
                    <div class="grid-item" data-modal="modal-four">
                        <div class="item-image" style="background-image: url(images/mangrove-conserver-two.jpg);"></div>
                        <div class="item-content">
                            <h3>Helpful Resources</h3>
                            <p>Tools and guides</p>
                            <span class="see-more">Explore →</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for second section -->
            <div class="modal" id="modal-template">
                <div class="modal-content">
                    <span class="close-modal">&times;</span>
                    <div class="modal-body">
                        <!-- Content will be added here via JavaScript -->
                    </div>
                </div>
            </div>
        </section>
        <section class="s4">
            <div class="three">
                <div  class="three-header"><h1>Expect the latest from our community hub</h1></div>
                <div class="programs-box">
                    <div class="programs-details">
                        <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>
                    </div>
                    <div class="programs-details">
                    <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>
                    </div>
                    <div class="programs-details">
                    <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>
                    </div>
                    <div class="programs-details">
                    <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>
                    </div>
                    <div class="programs-details">
                    <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>
                    </div>
                    <div class="programs-details">
                    <div class="programs-img"><img src="images/mangrove.webp" alt="Mangrove"></div>
                        <div class="programs-desc">
                            <h4>Program 1</h4>
                            <div class="programs-tags">
                            <h5>News</h5><h5>Article</h5>
                            </div>
                            <p>ManGrow exists to bring modern technology integration with environmental stewardship for mangrove conservation. overflow: hidden; overflow: hidden; overflow: hidden;</p>
                            <a href="#">Learn More >></a>
                        </div>  
                </div>
            </div>
            <div class="view-more">
                    <button type="button" class="view-more-btn" onclick="window.location.href='project.php';">View More <span><i class="fas fa-angle-double-right"></i></span></button>
                </div>
        </section>
        <section class="s5">
            <div class="four">
            <div  class="three-header"><h1>Your awareness can save the environment!</h1></div>
            <p>We at the ManGrow will encourage you to report unusual activities that might negatively affect the development of the mangroves in your area. In case of that happening, we are actively watching to ensure that your response will reach the authorities in time.</p>
            </div>
        </section>
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
</body>
</html>