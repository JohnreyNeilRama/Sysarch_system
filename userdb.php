<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<link rel="stylesheet" href="userdb.css">
<link rel="icon" type="image/png" href="pictures/uclogo.png">
</head>

<body class="dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        Dashboard
    </div>

    <ul class="dashboard-right">    
        <li><a href="#">Notification</a></li>
        <li><a href="userdb.php">Home</a></li>
        <li><a href="edit_profile.php">Edit Profile</a></li>
        <li><a href="#">History</a></li>
        <li><a href="#">Reservation</a></li>
        <li><a href="logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<div class="dashboard-container">

    <!-- LEFT PANEL -->
    <div class="student-info">

        <div class="student-header">
             Student Information
        </div>

        <div class="student-profile">
            <img src="profile_pictures/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.php'; ?>" 
                 alt="Profile Picture">
        </div>

        <div class="student-details">

            <p><strong>ID Number:</strong> <span><?php echo $_SESSION['id_number']; ?></span></p>
            <p><strong>Full Name:</strong> <span><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['middle_name'] . ' ' . $_SESSION['last_name']; ?></span></p>
            <p><strong>Course:</strong> <span><?php echo $_SESSION['course']; ?></span></p>
            <p><strong>Year Level:</strong> <span><?php echo $_SESSION['year_level']; ?></span></p>
            <p><strong>Email:</strong> <span><?php echo $_SESSION['email']; ?></span></p>
            <p><strong>Address:</strong> <span><?php echo $_SESSION['address']; ?></span></p>
            <p><strong>Remaining Sessions:</strong> <span><?php echo isset($_SESSION['sessions']) ? $_SESSION['sessions'] : '30'; ?></span></p>

        </div>

    </div>

   <div class="dashboard-main">
    <!-- RULES AND REGULATION -->
    <div class="dashboard-card">
        <div class="card-header">Rules and Regulation</div>
        <div class="card-body">
            <div class="rules-content">

            <h3>University of Cebu</h3>
            <h4>COLLEGE OF INFORMATION & COMPUTER STUDIES</h4>

            <h4 class="rules-title">LABORATORY RULES AND REGULATIONS</h4>

            <br> 
            

            <p>
                To avoid embarrassment and maintain camaraderie with your friends and superiors 
                at our laboratories, please observe the following:
            </p>

            <ol>
                <li>
                    Maintain silence, proper decorum and discipline inside the laboratory. 
                    Mobile phones, walkmans and other personal items of equipment must be switched off.
                </li>

                <li>
                    Games are not allowed inside the lab. This includes computer-related games, 
                    card games and other games that may disturb the operation of the lab.
                </li>

                <li>
                    Surfing the Internet is allowed only with the permission of the instructor. 
                    Downloading and installing of software are strictly prohibited.
                </li>
            </ol>

            </div>
        </div>
    </div>

    <!-- ANNOUNCEMENT -->
  <div class="dashboard-card">
    <div class="card-header">Announcement</div>

    <div class="card-body">

        <div class="announcement-item">
            <div class="announcement-meta">
                CCS Admin | 2026-Feb-11
            </div>
        </div>

        <hr>

        <div class="announcement-item">
            <div class="announcement-meta">
                CCS Admin | 2024-May-08
            </div>

            <div class="announcement-text">
                Important Announcement! We are excited to announce the launch of our new website! 
                🔔 Explore our latest products and services now!
            </div>
        </div>

    </div>
</div>

</div>

</body>
</html>
