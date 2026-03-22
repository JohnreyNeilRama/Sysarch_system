<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Auto-create reservations table if not exists
$create_reservations_table = "CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    lab_room VARCHAR(50) NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    additional_notes TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_reservations_table);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Dashboard</title>
<link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
<link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
</head>

<body class="dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        Dashboard
    </div>

    <ul class="dashboard-right">    
        <li><a href="#">Notification</a></li>
        <li><a href="/SYSARCH/userdb.php">Home</a></li>
        <li><a href="/SYSARCH/edit_profile.php">Edit Profile</a></li>
        <li><a href="#">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<div class="dashboard-container">

    <!-- LEFT PANEL -->
    <div class="student-info">

        <div class="student-header">
             Student Information
        </div>

        <div class="student-profile">
            <img src="/SYSARCH/assets/images/profile/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.php'; ?>" 
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

<!-- FLOATING RESERVATION MODAL -->
<div class="reservation-modal-overlay" id="reservationModal">
    <div class="reservation-modal">
        <div class="reservation-modal-header">
            <h2>Make a Reservation</h2>
            <button class="reservation-close-btn" id="closeReservation">&times;</button>
        </div>
        <div class="reservation-modal-body">
            <form class="reservation-form" action="/SYSARCH/includes/process_reservation.php" method="POST">
                <div class="form-group">
                    <label for="lab-room">Laboratory Room</label>
                    <select id="lab-room" name="lab_room" required>
                        <option value="">Select Laboratory</option>
                        <option value="lab-528">Room 528</option>
                        <option value="lab-530">Room 530</option>
                        <option value="lab-532">Room 532</option>
                        <option value="lab-534">Room 534</option>
                        <option value="lab-536">Room 536</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reservation-date">Reservation Date</label>
                    <input type="date" id="reservation-date" name="reservation_date" required>
                </div>
                
                <div class="form-group">
                    <label for="reservation-time">Reservation Time</label>
                    <input type="time" id="reservation-time" name="reservation_time" required>
                </div>
                
                <div class="form-group">
                    <label for="purpose">Purpose</label>
                    <select id="purpose" name="purpose" required>
                        <option value="">Select Purpose</option>
                        <option value="C Programming">C Programming</option>
                        <option value="Java Programming">Java Programming</option>
                        <option value="Python Programming">Python Programming</option>
                        <option value="Web Development">Web Development</option>
                        <option value="Database">Database</option>
                        <option value="Research">Research</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Examination">Examination</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="additional-notes">Additional Notes</label>
                    <textarea id="additional-notes" name="additional_notes" placeholder="Enter any additional details..."></textarea>
                </div>
                
                <button type="submit" class="reservation-submit-btn">Submit Reservation</button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('reservationModal');
        const openBtn = document.getElementById('openReservation');
        const closeBtn = document.getElementById('closeReservation');
        
        // Open modal
        openBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.add('active');
        });
        
        // Close modal with X button
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('active');
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                modal.classList.remove('active');
            }
        });
    });
</script>

</body>
</html>
