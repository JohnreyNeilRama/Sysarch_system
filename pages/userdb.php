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
    computer_no VARCHAR(10),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_reservations_table);

// Auto-create notifications table if not exists
$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_notifications_table);

// Rename computer_unit to computer_no if it exists
$check_old_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'computer_unit'");
if ($check_old_column->num_rows > 0) {
    $conn->query("ALTER TABLE reservations CHANGE COLUMN computer_unit computer_no VARCHAR(10)");
}

// Auto-create computers table if not exists
$create_computers_table = "CREATE TABLE IF NOT EXISTS computers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_room VARCHAR(50) NOT NULL,
    computer_number VARCHAR(10) NOT NULL,
    status VARCHAR(20) DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_computer (lab_room, computer_number)
)";
$conn->query($create_computers_table);

// Check if status column is ENUM and convert to VARCHAR
$check_status_type = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
if ($check_status_type->num_rows > 0) {
    $status_row = $check_status_type->fetch_assoc();
    if (strpos($status_row['Type'], 'enum') !== false) {
        $conn->query("ALTER TABLE computers MODIFY COLUMN status VARCHAR(20) DEFAULT 'available'");
    }
}

// Populate computers for each lab if not exists (optimized with bulk insert)
$lab_rooms = ['524', '526', '528', '530', '544', '542'];
$check_existing = $conn->query("SELECT COUNT(*) as cnt FROM computers");
$row = $check_existing->fetch_assoc();
if ($row['cnt'] == 0) {
    $values = [];
    foreach ($lab_rooms as $lab) {
        for ($i = 1; $i <= 50; $i++) {
            $values[] = "('$lab', '$i', 'available')";
        }
    }
    if (!empty($values)) {
        $conn->query("INSERT INTO computers (lab_room, computer_number, status) VALUES " . implode(",", $values));
    }
}

$conn->close();

// Fetch notifications for the logged-in student
include '../includes/connect.php';
$notif_student_id = $_SESSION['id_number'];
$notif_count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE id_number = ? AND is_read = 0";
$notif_count_stmt = $conn->prepare($notif_count_sql);
$notif_count_stmt->bind_param("s", $notif_student_id);
$notif_count_stmt->execute();
$notif_count_result = $notif_count_stmt->get_result();
$notif_count_row = $notif_count_result->fetch_assoc();
$unread_notifications = $notif_count_row['unread_count'];
$notif_count_stmt->close();

$notif_sql = "SELECT * FROM notifications WHERE id_number = ? ORDER BY created_at DESC LIMIT 10";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("s", $notif_student_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
$notifications = [];
while($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_stmt->close();

if(isset($_GET['mark_notif_read'])) {
    $notif_id = intval($_GET['mark_notif_read']);
    $mark_read_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND id_number = ?");
    $mark_read_stmt->bind_param("is", $notif_id, $notif_student_id);
    $mark_read_stmt->execute();
    $mark_read_stmt->close();
    header("Location: /SYSARCH/pages/userdb.php");
    exit;
}

if(isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id_number = ?");
    $mark_all_stmt->bind_param("s", $notif_student_id);
    $mark_all_stmt->execute();
    $mark_all_stmt->close();
    header("Location: /SYSARCH/pages/userdb.php");
    exit;
}

// Fetch current sessions from database before closing connection
$session_fetch_stmt = $conn->prepare("SELECT sessions FROM students WHERE id_number = ?");
$session_fetch_stmt->bind_param("s", $notif_student_id);
$session_fetch_stmt->execute();
$session_fetch_result = $session_fetch_stmt->get_result();
$session_fetch_row = $session_fetch_result->fetch_assoc();
$current_sessions = $session_fetch_row ? intval($session_fetch_row['sessions']) : 30;
$session_fetch_stmt->close();

// Calculate points earned correctly: 3 sessions used = 1 point
// Sessions used = 30 - remaining sessions
$sessions_used = 30 - $current_sessions;
$current_points_earned = floor($sessions_used / 3);

// Fetch sit-in statistics for the student
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

// Convert seconds to hours and minutes
$total_hours = $total_seconds / 3600;
$avg_minutes = $avg_seconds / 60;
$max_hours = $max_seconds / 3600;

$stats_stmt->close();

// Refresh session data with latest student information from database
$refresh_stmt = $conn->prepare("SELECT id, id_number, first_name, middle_name, last_name, course, year_level, email, address, profile_picture, sessions, points_earned FROM students WHERE id_number = ?");
$refresh_stmt->bind_param("s", $notif_student_id);
$refresh_stmt->execute();
$refresh_result = $refresh_stmt->get_result();
if($refresh_row = $refresh_result->fetch_assoc()) {
    $_SESSION['student_id'] = $refresh_row['id'];
    $_SESSION['id_number'] = $refresh_row['id_number'];
    $_SESSION['last_name'] = $refresh_row['last_name'];
    $_SESSION['first_name'] = $refresh_row['first_name'];
    $_SESSION['middle_name'] = $refresh_row['middle_name'];
    $_SESSION['course'] = $refresh_row['course'];
    $_SESSION['year_level'] = $refresh_row['year_level'];
    $_SESSION['email'] = $refresh_row['email'];
    $_SESSION['address'] = $refresh_row['address'];
    $_SESSION['profile_picture'] = $refresh_row['profile_picture'];
    $_SESSION['sessions'] = $refresh_row['sessions'];
    $_SESSION['points_earned'] = $refresh_row['points_earned'];
}
$refresh_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
<link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
</head>

<body class="dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        <img src="/SYSARCH/assets/images/uclogo.png" alt="UC Logo">
        <span>CCS Sit-in System</span>
    </div>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
    <ul class="dashboard-right" id="navRight">    
        <li><a href="#" class="notification-link" id="openNotifications">
            Notification <?php if($unread_notifications > 0): ?><span class="notif-badge"><?php echo $unread_notifications; ?></span><?php endif; ?>
        </a></li>
        <li><a href="/SYSARCH/pages/userdb.php" class="active">Home</a></li>
        <li><a href="/SYSARCH/pages/software_availability.php">Software</a></li>
        <li><a href="/SYSARCH/pages/edit_profile.php">Edit Profile</a></li>
        <li><a href="/SYSARCH/pages/history.php">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<!-- Welcome Message -->
<div class="welcome-message">
    Welcome, <span><?php echo htmlspecialchars($_SESSION['first_name']); ?></span>!
</div>

<script>
    // Auto-show notification pop-up on page load for reservation status changes
    function checkNewNotifications() {
        fetch('/SYSARCH/pages/api/check_new_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.newNotification) {
                    showNotificationPopup(data.notification);
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }
    
    function showNotificationPopup(notification) {
        const popup = document.createElement('div');
        popup.id = 'notif-popup';
        
        let icon = '';
        let iconClass = '';
        
        if (notification.type === 'reservation_approved') {
            icon = '✓';
            iconClass = 'approved';
        } else if (notification.type === 'reservation_rejected') {
            icon = '✕';
            iconClass = 'rejected';
        } else if (notification.type === 'login') {
            icon = '→';
            iconClass = 'login';
        } else if (notification.type === 'logout') {
            icon = '←';
            iconClass = 'logout';
        } else {
            icon = 'ℹ';
            iconClass = 'approved';
        }
        
        popup.innerHTML = `
            <div class="notif-popup-icon ${iconClass}">${icon}</div>
            <div class="notif-popup-content">
                <div class="notif-popup-title">${notification.title}</div>
                <div class="notif-popup-message">${notification.message}</div>
            </div>
            <button class="notif-popup-close" onclick="this.parentElement.remove();">&times;</button>
        `;
        
        document.body.appendChild(popup);
        
        setTimeout(() => {
            popup.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            if (popup.parentElement) {
                popup.classList.remove('show');
                setTimeout(() => popup.remove(), 300);
            }
        }, 8000);
    }
    
    // Notification modal click handlers
    document.addEventListener('DOMContentLoaded', function() {
        const notificationModal = document.getElementById('notificationModal');
        const openNotificationsBtn = document.getElementById('openNotifications');
        const closeNotificationsBtn = document.getElementById('closeNotifications');
        
        if(openNotificationsBtn && notificationModal) {
            openNotificationsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                notificationModal.classList.add('active');
            });
        }
        
        if(closeNotificationsBtn && notificationModal) {
            closeNotificationsBtn.addEventListener('click', function() {
                notificationModal.classList.remove('active');
            });
        }
        
        if(notificationModal) {
            notificationModal.addEventListener('click', function(e) {
                if(e.target === notificationModal) {
                    notificationModal.classList.remove('active');
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if(e.key === 'Escape') {
                    if(notificationModal.classList.contains('active')) {
                        notificationModal.classList.remove('active');
                    }
                }
            });
        }
    });
</script>

<div class="dashboard-container">

    <?php if(isset($_GET['reservation_success'])): ?>
        <div id="success-message" class="toast-message toast-success">Your reservation has been submitted successfully!</div>
<?php endif; ?>

    <!-- LEFT PANEL -->
    <div class="left-column">
        <div class="student-info">

            <div class="student-header">
                 Student Information
            </div>

            <div class="student-profile">
                <img src="/SYSARCH/assets/images/profile/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.png'; ?>" 
                     alt="Profile Picture">
            </div>

            <div class="student-details">

                <p><strong>ID Number:</strong> <span><?php echo $_SESSION['id_number']; ?></span></p>
                <p><strong>Full Name:</strong> <span><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['middle_name'] . ' ' . $_SESSION['last_name']; ?></span></p>
                <p><strong>Course:</strong> <span><?php echo $_SESSION['course']; ?></span></p>
                <p><strong>Year Level:</strong> <span><?php echo $_SESSION['year_level']; ?></span></p>
                <p><strong>Email:</strong> <span><?php echo $_SESSION['email']; ?></span></p>
                <p><strong>Address:</strong> <span><?php echo $_SESSION['address']; ?></span></p>
                <?php 
                    $remaining_sessions = $current_sessions;
                    $points_earned = $current_points_earned;
                ?>
                <p><strong>Remaining Sessions:</strong> <span><?php echo $remaining_sessions; ?></span></p>
                <p><strong>Points Earned:</strong> <span><?php echo $points_earned; ?></span></p>

            </div>

        </div>

       <!-- SIT-IN SUMMARY CARD -->
       <div class="sitin-summary-card">
            <div class="summary-header">Sit-in Summary</div>
            <div class="summary-body">
                <div class="summary-item">
                    <div class="summary-label">Total Sitting Hours</div>
                    <div class="summary-value"><?php echo number_format($total_hours, 2); ?> <span class="summary-unit">hrs</span></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Number of Sessions</div>
                    <div class="summary-value"><?php echo $total_sessions; ?> <span class="summary-unit">sessions</span></div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Average Session Duration</div>
                    <div class="summary-value">
                        <?php 
                            if ($avg_minutes >= 60) {
                                echo number_format($avg_minutes / 60, 2) . ' <span class="summary-unit">hrs</span>';
                            } else {
                                echo number_format($avg_minutes, 2) . ' <span class="summary-unit">mins</span>';
                            }
                        ?>
                    </div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Longest Session</div>
                    <div class="summary-value">
                        <?php 
                            if ($max_hours >= 1) {
                                echo number_format($max_hours, 2) . ' <span class="summary-unit">hrs</span>';
                            } else {
                                $max_minutes = $max_seconds / 60;
                                echo number_format($max_minutes, 2) . ' <span class="summary-unit">mins</span>';
                            }
                        ?>
                    </div>
                </div>
            </div>
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

                <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                <li>Observe proper decorum while inside the laboratory.<br>
                a. Do not get inside the lab unless the instructor is present.<br>
                b. All bags, knapsacks, and the likes must be deposited at the counter.<br>
                c. Follow the seating arrangement of your instructor.<br>
                d. At the end of class, all software programs must be closed.<br>
                e. Return all chairs to their proper places after using.</li>
                <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
            </ol>

            </div>
        </div>
    </div>

    <!-- ANNOUNCEMENT -->
  <div class="dashboard-card">
    <div class="card-header">Announcement</div>

    <div class="card-body announcement-body">
        <?php
        // Fetch announcements from database (latest first)
        include '../includes/connect.php';
        $stmt = $conn->prepare("SELECT admin_name, announcement_date, message FROM announcements ORDER BY announcement_date DESC, created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $first = true;
            while($row = $result->fetch_assoc()) {
                if(!$first) {
                    echo '<hr>';
                }
                echo '<div class="announcement-item">';
                echo '    <div class="announcement-meta">';
                echo '        ' . htmlspecialchars($row['admin_name']) . ' | ' . date('Y-M-d', strtotime($row['announcement_date']));
                echo '    </div>';
                echo '    <div class="announcement-text">';
                echo '        ' . nl2br(htmlspecialchars($row['message']));
                echo '    </div>';
                echo '</div>';
                $first = false;
            }
        } else {
            echo '<div class="announcement-item">';
            echo '    <div class="announcement-text">';
            echo '        No announcements yet.';
            echo '    </div>';
            echo '</div>';
        }
        
        $stmt->close();
        $conn->close();
        ?>
    </div>
</div>

</div>

<?php include '../includes/reservation_system.php'; ?>

<style>
.no-notifications {
    text-align: center;
    padding: 30px;
    color: #666;
}

#notif-popup {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    z-index: 10000;
    transform: translateX(120%);
    transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    max-width: 380px;
    border: none;
}

#notif-popup.show {
    transform: translateX(0);
}

.notif-popup-icon {
    width: 56px;
    height: 56px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.notif-popup-icon.approved {
    background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
}

.notif-popup-icon.rejected {
    background: linear-gradient(135deg, #f44336 0%, #c62828 100%);
}

.notif-popup-icon.login {
    background: linear-gradient(135deg, #2196f3 0%, #1565c0 100%);
}

.notif-popup-icon.logout {
    background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
}

.notif-popup-content {
    flex: 1;
    padding-right: 8px;
}

.notif-popup-title {
    font-weight: 700;
    font-size: 16px;
    color: #1a1a1a;
    margin-bottom: 6px;
    letter-spacing: -0.3px;
}

.notif-popup-message {
    font-size: 14px;
    color: #5a5a5a;
    line-height: 1.5;
}

.notif-popup-close {
    background: #f8f9fa;
    border: none;
    font-size: 18px;
    color: #888;
    cursor: pointer;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.notif-popup-close:hover {
    background: #e9ecef;
    color: #333;
    transform: rotate(90deg);
}

@media (max-width: 480px) {
    #notif-popup {
        bottom: 20px;
        right: 10px;
        left: 10px;
        max-width: none;
        padding: 16px;
    }
    
    .notif-popup-icon {
        width: 44px;
        height: 44px;
        font-size: 20px;
        border-radius: 12px;
    }
}
</style>

<!-- Notification Modal -->
<div class="notification-modal-overlay" id="notificationModal">
    <div class="notification-modal">
        <div class="notification-modal-header">
            <h2>Notifications</h2>
            <button class="notification-close-btn" id="closeNotifications">&times;</button>
        </div>
        <div class="notification-modal-body">
            <?php if(!empty($notifications)): ?>
                <div class="notification-actions">
                    <a href="?mark_all_read=1" class="mark-all-read-btn">Mark all as read</a>
                </div>
                <?php foreach($notifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?>">
                        <div class="notification-header">
                            <span class="notification-title <?php echo $notif['type']; ?>">
                                <?php if($notif['type'] === 'reservation_approved'): ?>
                                    <span class="notif-icon approved">✓</span>
                                <?php elseif($notif['type'] === 'reservation_rejected'): ?>
                                    <span class="notif-icon rejected">✕</span>
                                <?php else: ?>
                                    <span class="notif-icon">ℹ</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($notif['title']); ?>
                            </span>
                            <span class="notification-time"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></span>
                        </div>
                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                        <?php if(!$notif['is_read']): ?>
                            <a href="?mark_notif_read=<?php echo $notif['id']; ?>" class="mark-read-btn">Mark as read</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <div class="no-notifications-icon">🔔</div>
                    <p>No notifications yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notification-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
}

.notif-badge {
    background: #f44336;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

.notification-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    z-index: 2000;
    justify-content: center;
    align-items: center;
    animation: fadeIn 0.3s ease;
}

.notification-modal-overlay.active {
    display: flex;
}

.notification-modal {
    background: white;
    border-radius: 24px;
    width: 90%;
    max-width: 480px;
    max-height: 85vh;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0,0,0,0.25);
    animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.notification-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    background: linear-gradient(135deg, #0a47c2 0%, #1565c0 50%, #1976D2 100%);
    color: white;
}

.notification-modal-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.notification-close-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
}

.notification-close-btn:hover {
    background: rgba(255,255,255,0.25);
    transform: scale(1.1) rotate(90deg);
}

.notification-modal-body {
    padding: 24px;
    max-height: 60vh;
    overflow-y: auto;
}

.notification-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.mark-all-read-btn {
    color: #1565c0;
    font-size: 14px;
    text-decoration: none;
    font-weight: 600;
    padding: 10px 20px;
    border-radius: 25px;
    background: #e3f2fd;
    transition: all 0.2s ease;
}

.mark-all-read-btn:hover {
    background: #bbdefb;
    text-decoration: none;
    transform: translateY(-1px);
}

.notification-item {
    padding: 20px;
    border-radius: 16px;
    margin-bottom: 14px;
    background: #f8f9fa;
    transition: all 0.25s ease;
    position: relative;
    border: 1px solid transparent;
}

.notification-item:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
    border-color: #e0e0e0;
}

.notification-item.unread {
    background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%);
    border-left: 4px solid #1565c0;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 16px;
    right: 16px;
    width: 10px;
    height: 10px;
    background: linear-gradient(135deg, #1565c0 0%, #1976D2 100%);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(25, 118, 210, 0.4);
}

.notification-item.read {
    opacity: 0.65;
    background: #f5f5f5;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.notification-title {
    font-weight: 700;
    color: #1a1a1a;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
}

.notification-title.reservation_approved, .notification-title.login {
    color: #2e7d32;
}

.notification-title.reservation_rejected, .notification-title.logout {
    color: #c62828;
}

.notification-title.login {
    color: #1565c0;
}

.notif-icon {
    font-size: 14px;
}

.notif-icon.approved {
    color: #4caf50;
}

.notif-icon.rejected {
    color: #f44336;
}

.notification-time {
    font-size: 12px;
    color: #888;
    font-weight: 400;
}

.notification-message {
    color: #555;
    font-size: 14px;
    line-height: 1.5;
}

.mark-read-btn {
    display: inline-block;
    margin-top: 10px;
    color: #1976D2;
    font-size: 12px;
    text-decoration: none;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 15px;
    background: rgba(25, 118, 210, 0.1);
    transition: all 0.2s ease;
}

.mark-read-btn:hover {
    text-decoration: underline;
}

.no-notifications {
    text-align: center;
    padding: 60px 30px;
    color: #9e9e9e;
}

.no-notifications p {
    font-size: 16px;
    margin: 0;
    opacity: 0.8;
}

.no-notifications-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}
</style>

<!-- JavaScript for Modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        checkNewNotifications();
        
        const modal = document.getElementById('notificationModal');
        const openBtn = document.getElementById('openNotifications');
        const closeBtn = document.getElementById('closeNotifications');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navRight = document.getElementById('navRight');
        
        // Mobile menu toggle
        if(mobileMenuToggle && navRight) {
            mobileMenuToggle.addEventListener('click', function() {
                navRight.classList.toggle('active');
                this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
            });
        }
        
        // Open modal
        if(openBtn) {
            openBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if(modal) modal.classList.add('active');
            });
        }
        
        // Close modal with X button
        if(closeBtn && modal) {
            closeBtn.addEventListener('click', function() {
                modal.classList.remove('active');
            });
        }
        
        // Close modal when clicking outside
        if(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (modal && modal.classList.contains('active')) {
                    modal.classList.remove('active');
                }
            }
        });
        
        // Auto-hide success/error messages after 3 seconds
        setTimeout(function() {
            var successMsg = document.getElementById('success-message');
            if(successMsg) {
                successMsg.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(function() { successMsg.remove(); }, 300);
            }
            var errorMsg = document.getElementById('error-message');
            if(errorMsg) {
                errorMsg.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(function() { errorMsg.remove(); }, 300);
            }
        }, 3000);
    });
    
    function checkNewNotifications() {
        fetch('/SYSARCH/pages/api/check_new_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.newNotification) {
                    showNotificationPopup(data.notification);
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }
    
    function showNotificationPopup(notification) {
        const popup = document.createElement('div');
        popup.id = 'notif-popup';
        
        let icon = '';
        let iconClass = '';
        
        if (notification.type === 'reservation_approved') {
            icon = '✓';
            iconClass = 'approved';
        } else if (notification.type === 'reservation_rejected') {
            icon = '✕';
            iconClass = 'rejected';
        } else if (notification.type === 'login') {
            icon = '→';
            iconClass = 'login';
        } else if (notification.type === 'logout') {
            icon = '←';
            iconClass = 'logout';
        } else {
            icon = 'ℹ';
            iconClass = 'approved';
        }
        
        popup.innerHTML = `
            <div class="notif-popup-icon ${iconClass}">${icon}</div>
            <div class="notif-popup-content">
                <div class="notif-popup-title">${notification.title}</div>
                <div class="notif-popup-message">${notification.message}</div>
            </div>
            <button class="notif-popup-close" onclick="this.parentElement.remove();">&times;</button>
        `;
        
        document.body.appendChild(popup);
        
        setTimeout(() => {
            popup.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            if (popup.parentElement) {
                popup.classList.remove('show');
                setTimeout(() => popup.remove(), 300);
            }
        }, 8000);
    }
</script>

</body>
</html>
