<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

$notif_student_id = $_SESSION['id_number'];

// Fetch unread notifications count
$notif_count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE id_number = ? AND is_read = 0";
$notif_count_stmt = $conn->prepare($notif_count_sql);
$notif_count_stmt->bind_param("s", $notif_student_id);
$notif_count_stmt->execute();
$notif_count_result = $notif_count_stmt->get_result();
$notif_count_row = $notif_count_result->fetch_assoc();
$unread_notifications = $notif_count_row['unread_count'];
$notif_count_stmt->close();

// Fetch current sessions
$session_fetch_stmt = $conn->prepare("SELECT sessions FROM students WHERE id_number = ?");
$session_fetch_stmt->bind_param("s", $notif_student_id);
$session_fetch_stmt->execute();
$session_fetch_result = $session_fetch_stmt->get_result();
$session_fetch_row = $session_fetch_result->fetch_assoc();
$current_sessions = $session_fetch_row ? intval($session_fetch_row['sessions']) : 30;
$session_fetch_stmt->close();

// Statistics logic
$sessions_used = 30 - $current_sessions;
$current_points_earned = floor($sessions_used / 3);

$stats_sql = "SELECT 
    COUNT(*) as total_sessions,
    SUM(CASE WHEN logout_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, CONCAT(sit_in_date, ' ', sit_in_time), CONCAT(sit_in_date, ' ', logout_time)) ELSE 0 END) as total_seconds,
    AVG(CASE WHEN logout_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, CONCAT(sit_in_date, ' ', sit_in_time), CONCAT(sit_in_date, ' ', logout_time)) ELSE NULL END) as avg_seconds,
    MAX(CASE WHEN logout_time IS NOT NULL THEN TIMESTAMPDIFF(SECOND, CONCAT(sit_in_date, ' ', sit_in_time), CONCAT(sit_in_date, ' ', logout_time)) ELSE NULL END) as max_seconds
FROM sit_in 
WHERE id_number = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("s", $notif_student_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_row = $stats_result->fetch_assoc();

$total_sessions = $stats_row['total_sessions'] ? intval($stats_row['total_sessions']) : 0;
$total_seconds = $stats_row['total_seconds'] ? intval($stats_row['total_seconds']) : 0;
$avg_seconds = $stats_row['avg_seconds'] ? floatval($stats_row['avg_seconds']) : 0;
$max_seconds = $stats_row['max_seconds'] ? intval($stats_row['max_seconds']) : 0;

$total_hours = $total_seconds / 3600;
$avg_minutes = $avg_seconds / 60;
$max_hours = $max_seconds / 3600;

$stats_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - CCS Sit-in Monitoring</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/style.css">
    <link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .profile-pic-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-pic-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4CAF50;
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="file"] {
            padding: 5px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            margin-top: 20px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;

        }

        .back-link:hover {
            font-size: 20px;

        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .form-container {
                margin: 20px 10px;
                padding: 20px;
            }
            
            h2 {
                font-size: 22px;
            }
            
            .profile-pic-preview {
                width: 100px;
                height: 100px;
            }
            
            label {
                font-size: 14px;
            }
            
            input, select, textarea {
                padding: 8px;
                font-size: 14px;
            }
            
            button {
                padding: 10px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .form-container {
                margin: 10px;
                padding: 15px;
            }
            
            h2 {
                font-size: 20px;
            }
            
            .profile-pic-preview {
                width: 80px;
                height: 80px;
            }
            
            label {
                font-size: 13px;
            }
            
            input, select, textarea {
                padding: 8px;
                font-size: 13px;
            }
            
            button {
                padding: 10px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body class="dashboard-page">

<nav class="dashboard-navbar">
    <div class="dashboard-left">
        Dashboard
    </div>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
    <ul class="dashboard-right" id="navRight">    
        <li><a href="#" class="notification-link" id="openNotifications">
            Notification <?php if($unread_notifications > 0): ?><span class="notif-badge"><?php echo $unread_notifications; ?></span><?php endif; ?>
        </a></li>
        <li><a href="/SYSARCH/pages/userdb.php">Home</a></li>
        <li><a href="/SYSARCH/pages/software_availability.php">Software</a></li>
        <li><a href="/SYSARCH/pages/edit_profile.php" class="active">Edit Profile</a></li>
        <li><a href="/SYSARCH/pages/history.php">History</a></li>
        <li><a href="#" class="reservation-link">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>
</nav>

<div class="form-container">
    <h2>Edit Profile</h2>
    
    <form action="/SYSARCH/includes/update_profile.php" method="POST" enctype="multipart/form-data">
        
        <div class="profile-pic-section">
            <img src="/SYSARCH/assets/images/profile/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.png'; ?>" 
                 alt="Profile Picture" 
                 class="profile-pic-preview" 
                 id="preview">
            <br>
            <label for="profile_picture" style="display:inline; cursor:pointer; color: #4CAF50; height: 30px; line-height: 30px; border: 3px solid #4CAF50; border-radius: 4px; padding: 0 10px;">
                📷 Change Photo
            </label>
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display:none;" onchange="previewImage(this)">
        </div>
        
        <label>ID Number</label>
        <input type="text" name="id_number" value="<?php echo $_SESSION['id_number']; ?>" readonly>
        
        <label>Last Name</label>
        <input type="text" name="last_name" value="<?php echo $_SESSION['last_name']; ?>" required>
        
        <label>First Name</label>
        <input type="text" name="first_name" value="<?php echo $_SESSION['first_name']; ?>" required>
        
        <label>Middle Name</label>
        <input type="text" name="middle_name" value="<?php echo $_SESSION['middle_name']; ?>">
        
        <label>Course</label>
        <input type="text" name="course" value="<?php echo $_SESSION['course']; ?>" required>
        
        <label>Year Level</label>
        <input type="text" name="year_level" value="<?php echo $_SESSION['year_level']; ?>" required>
        
        <label>Email</label>
        <input type="email" name="email" value="<?php echo $_SESSION['email']; ?>" required>
        
        <label>Address</label>
        <textarea name="address" rows="3" required><?php echo $_SESSION['address']; ?></textarea>
        
        <button type="submit">Update Profile</button>
    </form>
    
    <a href="/SYSARCH/userdb.php" class="back-link">← Back to Dashboard</a>
</div>

<?php include '../includes/reservation_system.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navRight = document.getElementById('navRight');
    
    mobileMenuToggle.addEventListener('click', function() {
        navRight.classList.toggle('active');
        this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
    });
});

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
