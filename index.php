<?php
    session_start();

    if(isset($_SESSION["name"])){
        $loggeduser = $_SESSION["name"];
    }
    if(isset($_SESSION["email"])){
        $email = $_SESSION["email"];
    }
    if(isset($_SESSION["accessrole"])){
        $accessrole = $_SESSION["accessrole"];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to ManGrow</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script type ="text/javascript" src ="app.js" defer></script>
</head>
<body>
    <header>
        <form action="#" class="searchbar">
            <input type="text" placeholder="Search">
            <button type="submit"><i class='bx bx-search-alt-2'></i></button> 
        </form>
        <nav class = "navbar">
            <a href="#">About</a>
            <a href="#">Events</a>
            <a href="logout.php">Leaderboards</a>
            <?php
            if (isset($_SESSION["name"])) {
                $loggeduser = $_SESSION["name"];
                echo "<a href='logout.php'>$loggeduser</a>"; // Display the name with a link to profile.php
            } else {
                echo "<a href='login.php'>Login</a>";
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
            <li class = "active">
                <a href="index.html" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M240-200h120v-200q0-17 11.5-28.5T400-440h160q17 0 28.5 11.5T600-400v200h120v-360L480-740 240-560v360Zm-80 0v-360q0-19 8.5-36t23.5-28l240-180q21-16 48-16t48 16l240 180q15 11 23.5 28t8.5 36v360q0 33-23.5 56.5T720-120H560q-17 0-28.5-11.5T520-160v-200h-80v200q0 17-11.5 28.5T400-120H240q-33 0-56.5-23.5T160-200Zm320-270Z"/></svg>
                    <span>Home</span>
                </a>
            </li>
            <li>
                <a href="dashboard.html" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M520-640v-160q0-17 11.5-28.5T560-840h240q17 0 28.5 11.5T840-800v160q0 17-11.5 28.5T800-600H560q-17 0-28.5-11.5T520-640ZM120-480v-320q0-17 11.5-28.5T160-840h240q17 0 28.5 11.5T440-800v320q0 17-11.5 28.5T400-440H160q-17 0-28.5-11.5T120-480Zm400 320v-320q0-17 11.5-28.5T560-520h240q17 0 28.5 11.5T840-480v320q0 17-11.5 28.5T800-120H560q-17 0-28.5-11.5T520-160Zm-400 0v-160q0-17 11.5-28.5T160-360h240q17 0 28.5 11.5T440-320v160q0 17-11.5 28.5T400-120H160q-17 0-28.5-11.5T120-160Zm80-360h160v-240H200v240Zm400 320h160v-240H600v240Zm0-480h160v-80H600v80ZM200-200h160v-80H200v80Zm160-320Zm240-160Zm0 240ZM360-280Z"/></svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <button onclick = "DropDownToggle(this)" class="dropdown-btn" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Create</span>
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-361q-8 0-15-2.5t-13-8.5L268-556q-11-11-11-28t11-28q11-11 28-11t28 11l156 156 156-156q11-11 28-11t28 11q11 11 11 28t-11 28L508-372q-6 6-13 8.5t-15 2.5Z"/></svg>                </button>
                <ul class="sub-menu" tabindex="-1">
                    <div>
                    <li><a href="#" tabindex="-1">Folder</a></li>
                    <li><a href="#" tabindex="-1">Document</a></li>
                    <li><a href="#" tabindex="-1">Project</a></li>
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
            <li>
                <a href="profile.html" tabindex="-1">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-240v-32q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v32q0 33-23.5 56.5T720-160H240q-33 0-56.5-23.5T160-240Zm80 0h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>
                    <span>Profile</span>
                </a>
            </li>
        </ul>
    </aside>
    <main>
        <div class="profile-details">
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
            <button type="button" name="logoutbtn" onclick="window.location.href='logout.php';">Log Out</button>
        </div>
        <div class= "home-container">
            <section class="s1">
            <div class ="background-img"><img src="images/mangrove.webp" alt ="Mangrove">
            <h1>ManGrow: Mangrove Conservation and Eco-Tracking System with 2D Mapping</h1>
            <button type="button" class="abt-project" onclick="window.location.href='project.php';"><span class="abt-project-span"></span>About Project</button>
            </div>
            </section>
            <br>
            <section class="s2">
            <div class="one">
                <h3>Mission</h3>
                <p>Our purpose exists to bring modern technology integration with environmental stewardship for mangrove conservation. 
                    We use technology such as GIS-powered mapping with eco-tracking features and promote community engagement to establish sustainable mangrove conservation. 
                    The technology enables mangrove ecosystem protection and strengthens local populations so they actively engage in conservation work.</p>
                </div>
            </section>
            <section class="s3">
            <div class="two">
                <h3>Vision</h3>
                <p>Digital innovation will guide our path toward protecting and promoting a healthy state of mangrove forests in forthcoming years. 
                    Modern technology and collaborative efforts will lead us to develop an eco-oriented community focused on resource conservation. 
                    The coastal resources need to be sustainably preserved through proper utilization for upcoming generations.
                </p>
                </div>
            </section>
            <section class="s4">
            <div class="three">
                <h3>About ManGrow</h3>
                <p>The ManGrow system brings together conservation capabilities that integrate Geographic Information System (GIS) with 2D mapping and QR-based tree tracking features. 
                    and QR-based tree tracking. The organization enables communities officials and environmental organizations to protect mangroves through its support system. 
                    Through the system users track tree planting activities and illegal deforestation activities while participating in tree plantation programs.</p>
                </div>
            </section>
            <section class="s5">
            <div class="four">
                <h3>About Us</h3>
                <p>Our team aspires to connect technology and the mangrove environment closer together with the help of the community's efforts. 
                    We focus on streamlining conservation initiatives, mangrove monitoring and compliance efforts with our eco-tracking and 2D 
                    mapping as our personalized approach in keeping sure of a well-maintained mangrove environment.</p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>