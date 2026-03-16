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
        <li><a href="landing.php">Home</a></li>
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

        </div>

    </div>

    <!-- RIGHT SIDE -->
    <div class="dashboard-main">
        <!-- Announcement and Rules will go here -->
    </div>

</div>

</body>
</html>
