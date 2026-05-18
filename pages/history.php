<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Get student's sit-in history
$student_id = $_SESSION['id_number'];

// Fetch from sit_in table (approved/completed sessions)
$sql_sitin = "SELECT id, id_number, student_name, purpose, lab, sit_in_time as login_time, sit_in_time as logout_time, sit_in_date, status, 'sit_in' as record_type FROM sit_in WHERE id_number = ?";
$stmt_sitin = $conn->prepare($sql_sitin);
$stmt_sitin->bind_param("s", $student_id);
$stmt_sitin->execute();
$result_sitin = $stmt_sitin->get_result();

// Fetch from reservations table (rejected reservations)
$sql_rejected = "SELECT id, id_number, student_name, purpose, lab_room as lab, reservation_time as login_time, NULL as logout_time, reservation_date as sit_in_date, status, 'reservation' as record_type FROM reservations WHERE id_number = ? AND status = 'Rejected'";
$stmt_rejected = $conn->prepare($sql_rejected);
$stmt_rejected->bind_param("s", $student_id);
$stmt_rejected->execute();
$result_rejected = $stmt_rejected->get_result();

// Get all sit_in IDs that already have feedback
$feedback_sql = "SELECT sit_in_id FROM feedback WHERE student_id = ?";
$stmt_feedback = $conn->prepare($feedback_sql);
$stmt_feedback->bind_param("s", $student_id);
$stmt_feedback->execute();
$result_feedback = $stmt_feedback->get_result();
$feedback_submitted = [];
while($row = $result_feedback->fetch_assoc()) {
    $feedback_submitted[] = $row['sit_in_id'];
}
$stmt_feedback->close();

// Combine results
$all_records = [];
while($row = $result_sitin->fetch_assoc()) {
    $all_records[] = $row;
}
while($row = $result_rejected->fetch_assoc()) {
    $all_records[] = $row;
}

// Sort by date descending
usort($all_records, function($a, $b) {
    return strtotime($b['sit_in_date'] . ' ' . $b['login_time']) - strtotime($a['sit_in_date'] . ' ' . $a['login_time']);
});

// Fetch notifications for the logged-in student
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
    header("Location: /SYSARCH/pages/history.php");
    exit;
}

if(isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id_number = ?");
    $mark_all_stmt->bind_param("s", $notif_student_id);
    $mark_all_stmt->execute();
    $mark_all_stmt->close();
    header("Location: /SYSARCH/pages/history.php");
    exit;
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
    <style>
        .history-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .history-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .history-header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .history-header p {
            color: #666;
            font-size: 14px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .history-table thead {
            background: #0f5bbe;
            color: white;
        }
        
        .history-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #333;
        }
        
        .history-table tbody tr:hover {
            background: #f5f5f5;
        }
        
        .history-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .status-active {
            color: #4caf50;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #f44336;
            font-weight: bold;
        }
        
        .btn-feedback {
            background: #0f5bbe;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-feedback:hover {
            background: #0d4fa1;
        }
        
        .btn-feedback:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .no-history {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }
        
        .feedback-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .feedback-modal-overlay.active {
            display: flex;
        }
        
        .feedback-modal {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0,3);
            animation: slideUp 0.3s ease;
        }
        
        .feedback-modal-content {
            max-height: calc(90vh - 80px);
            overflow-y: auto;
            border-radius: 0 0 16px 16px;
        }
        
        .feedback-modal-content::-webkit-scrollbar {
            width: 8px;
        }
        
        .feedback-modal-content::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .feedback-modal-content::-webkit-scrollbar-thumb {
            background: rgba(15, 91, 190, 0.3);
            border-radius: 4px;
        }
        
        .feedback-modal-content::-webkit-scrollbar-thumb:hover {
            background: rgba(15, 91, 190, 0.5);
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .feedback-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #0f5bbe 0%, #1a73e8 100%);
            border-radius: 16px 16px 0 0;
        }
        
        .feedback-modal-header h3 {
            margin: 0;
            color: white;
            font-size: 20px;
            font-weight: 600;
        }
        
        .feedback-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .feedback-close-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .feedback-modal-body {
            padding: 24px;
        }
        
        .feedback-form .form-group {
            margin-bottom: 20px;
        }
        
        .feedback-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .feedback-form textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }
        
        .feedback-form textarea:focus {
            outline: none;
            border-color: #0f5bbe;
        }
        
        .feedback-form select {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: white;
            cursor: pointer;
        }
        
        .feedback-form select:focus {
            outline: none;
            border-color: #0f5bbe;
        }
        
        .feedback-submit-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #0f5bbe 0%, #1a73e8 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(15, 91, 190, 0.3);
        }
        
        .feedback-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(15, 91, 190, 0.4);
        }
        
        .feedback-details-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 16px;
            border-radius: 10px;
            border-left: 4px solid #0f5bbe;
        }
        
        .feedback-details-card p {
            margin: 0;
            color: #495057;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .feedback-details-card strong {
            color: #0f5bbe;
            font-weight: 600;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .history-container {
                padding: 10px;
            }
            
            .history-header h2 {
                font-size: 22px;
            }
            
            .history-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .history-table thead,
            .history-table tbody,
            .history-table tr,
            .history-table th,
            .history-table td {
                display: block;
            }
            
            .history-table thead {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .history-table tr {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px;
                background: white;
            }
            
            .history-table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            
            .history-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 45%;
                text-align: left;
                font-weight: bold;
                color: #555;
            }
            
            .feedback-modal {
                width: 95%;
                margin: 10px;
            }
            
            .feedback-modal-header {
                padding: 15px;
            }
            
            .feedback-modal-body {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .history-header h2 {
                font-size: 18px;
            }
            
            .history-table td {
                font-size: 12px;
                padding-left: 45%;
            }
            
            .history-table td:before {
                font-size: 11px;
            }
            
            .btn-feedback {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
        
        #notif-popup {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 10000;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 400px;
        }

        #notif-popup.show {
            transform: translateX(0);
        }

        .notif-popup-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .notif-popup-content {
            flex: 1;
        }

        .notif-popup-title {
            font-weight: 700;
            font-size: 16px;
            color: #333;
            margin-bottom: 4px;
        }

        .notif-popup-message {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }

        .notif-popup-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }

        .notif-popup-close:hover {
            color: #333;
        }

        @media (max-width: 480px) {
            #notif-popup {
                bottom: 20px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    </style>
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
        <li><a href="/SYSARCH/pages/userdb.php">Home</a></li>
        <li><a href="/SYSARCH/pages/software_availability.php">Software</a></li>
        <li><a href="/SYSARCH/pages/edit_profile.php">Edit Profile</a></li>
        <li><a href="/SYSARCH/pages/history.php" class="active">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        checkNewNotifications();
        
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const navRight = document.getElementById('navRight');
        
        // Notification modal
        const notificationModal = document.getElementById('notificationModal');
        const openNotificationsBtn = document.getElementById('openNotifications');
        const closeNotificationsBtn = document.getElementById('closeNotifications');
        
        if(openNotificationsBtn) {
            openNotificationsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                notificationModal.classList.add('active');
            });
        }
        
        if(closeNotificationsBtn) {
            closeNotificationsBtn.addEventListener('click', function() {
                notificationModal.classList.remove('active');
            });
        }
        
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
        
        mobileMenuToggle.addEventListener('click', function() {
            navRight.classList.toggle('active');
            this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
        });
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
        let bgColor = '';
        
        if (notification.type === 'reservation_approved') {
            icon = '✓';
            bgColor = 'linear-gradient(135deg, #4caf50 0%, #43a047 100%)';
        } else if (notification.type === 'reservation_rejected') {
            icon = '✕';
            bgColor = 'linear-gradient(135deg, #f44336 0%, #c62828 100%)';
        } else {
            icon = 'ℹ';
            bgColor = 'linear-gradient(135deg, #0f5bbe 0%, #1976D2 100%)';
        }
        
        popup.innerHTML = `
            <div class="notif-popup-icon" style="background: ${bgColor};">${icon}</div>
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
    color: white;
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
    background: #f0f7ff;
    border-left: 4px solid #1565c0;
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

.notification-link {
    position: relative;
    display: flex;
    align-items: center;
    gap: 5px;
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
    <div class="history-header">
        <h2>Sit-in History</h2>
        <p>View your past and current sit-in sessions</p>
    </div>
    
    <?php if(!empty($all_records)): ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Sit Purpose</th>
                    <th>Laboratory</th>
                    <th>Time of Login</th>
                    <th>Time of Logout</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_records as $row): ?>
                    <tr>
                        <td data-label="ID Number"><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td data-label="Name"><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td data-label="Purpose"><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td data-label="Lab"><?php echo htmlspecialchars($row['lab']); ?></td>
                        <td data-label="Login Time"><?php echo htmlspecialchars($row['login_time']); ?></td>
                        <td data-label="Logout Time"><?php echo $row['logout_time'] ? htmlspecialchars($row['logout_time']) : '-'; ?></td>
                        <td data-label="Date"><?php echo htmlspecialchars($row['sit_in_date']); ?></td>
                        <td data-label="Status">
                            <?php if($row['status'] === 'Active'): ?>
                                <span class="status-active">Active</span>
                            <?php elseif($row['status'] === 'Rejected'): ?>
                                <span class="status-inactive">Rejected</span>
                            <?php else: ?>
                                <span class="status-inactive">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <?php 
                            $sit_in_id = $row['id'];
                            $has_feedback = in_array($sit_in_id, $feedback_submitted);
                            if(($row['status'] === 'Inactive' || $row['status'] === 'Completed') && !$has_feedback): ?>
                                <button class="btn-feedback" onclick="openFeedbackModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['purpose']); ?>', '<?php echo htmlspecialchars($row['lab']); ?>', '<?php echo htmlspecialchars($row['sit_in_date']); ?>')">Feedback</button>
                            <?php elseif($has_feedback): ?>
                                <button class="btn-feedback" disabled style="background: #aaa; cursor: not-allowed;">Submitted</button>
                            <?php else: ?>
                                <button class="btn-feedback" disabled>Feedback</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-history">
            <p>No sit-in history found.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Feedback Modal -->
<div class="feedback-modal-overlay" id="feedbackModal">
    <div class="feedback-modal">
        <div class="feedback-modal-header">
            <h3>Submit Feedback</h3>
            <button class="feedback-close-btn" id="closeFeedback">&times;</button>
        </div>
        <div class="feedback-modal-content">
            <div class="feedback-modal-body">
            <form class="feedback-form" action="/SYSARCH/includes/process_feedback.php" method="POST">
                <input type="hidden" name="sit_in_id" id="feedback-sit-in-id">
                <input type="hidden" name="student_id" value="<?php echo $_SESSION['id_number']; ?>">
                <input type="hidden" name="student_name" value="<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>">
                
                <div class="form-group">
                    <label>Sit-in Details</label>
                    <div class="feedback-details-card">
                        <p id="feedback-details"></p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" required>
                        <option value="">Select Rating</option>
                        <option value="5">Excellent (5)</option>
                        <option value="4">Good (4)</option>
                        <option value="3">Average (3)</option>
                        <option value="2">Poor (2)</option>
                        <option value="1">Very Poor (1)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="feedback-comment">Comments</label>
                    <textarea id="feedback-comment" name="comment" placeholder="Share your experience..." required></textarea>
                </div>
                
                <button type="submit" class="feedback-submit-btn">Submit Feedback</button>
            </form>
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
            <form id="reservationFormStep1" class="reservation-form" action="/SYSARCH/includes/process_reservation.php" method="POST">
                <div class="form-group">
                    <label for="lab-room">Laboratory Room</label>
                    <select id="lab-room" name="lab_room" required>
                        <option value="">Select Laboratory</option>
                        <option value="524">Room 524</option>
                        <option value="525">Room 525</option>
                        <option value="526">Room 526</option>
                        <option value="527">Room 527</option>
                        <option value="528">Room 528</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reservation-date">Reservation Date</label>
                    <input type="date" id="reservation-date" name="reservation_date" required>
                </div>
                
                <div class="form-group">
                    <label for="reservation-time">Reservation Time</label>
                    <select id="reservation-time" name="reservation_time" required>
                        <option value="">Select Time</option>
                        <option value="07:30:00">7:30 AM</option>
                        <option value="08:00:00">8:00 AM</option>
                        <option value="08:30:00">8:30 AM</option>
                        <option value="09:00:00">9:00 AM</option>
                        <option value="09:30:00">9:30 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="10:30:00">10:30 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="11:30:00">11:30 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="12:30:00">12:30 PM</option>
                        <option value="13:00:00">1:00 PM</option>
                        <option value="13:30:00">1:30 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="14:30:00">2:30 PM</option>
                        <option value="15:00:00">3:00 PM</option>
                        <option value="15:30:00">3:30 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                        <option value="16:30:00">4:30 PM</option>
                        <option value="17:00:00">5:00 PM</option>
                        <option value="17:30:00">5:30 PM</option>
                        <option value="18:00:00">6:00 PM</option>
                        <option value="18:30:00">6:30 PM</option>
                        <option value="19:00:00">7:00 PM</option>
                        <option value="19:30:00">7:30 PM</option>
                        <option value="20:00:00">8:00 PM</option>
                    </select>
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
                
                <button type="button" class="reservation-submit-btn" id="continueToStep2">Continue</button>
            </form>
        </div>
    </div>
</div>

<!-- COMPUTER SELECTION MODAL - Step 2 -->
<div class="reservation-modal-overlay" id="computerModal">
    <div class="reservation-modal computer-modal">
        <div class="reservation-modal-header">
            <h2>Select Computer - Step 2</h2>
            <button class="reservation-close-btn" id="closeComputerModal">&times;</button>
        </div>
        <div class="reservation-modal-body">
            <div class="computer-info">
                <p><strong>Room:</strong> <span id="selectedRoom"></span></p>
                <p><strong>Date:</strong> <span id="selectedDate"></span></p>
                <p><strong>Time:</strong> <span id="selectedTime"></span></p>
            </div>
            <div class="computer-legend">
                <span class="legend-item"><span class="computer-unit available"></span> Available</span>
                <span class="legend-item"><span class="computer-unit occupied"></span> Unavailable</span>
            </div>
            <div class="computer-grid" id="computerGrid"></div>
            <form id="reservationFormStep2" method="POST" action="/SYSARCH/includes/process_reservation.php">
                <input type="hidden" name="lab_room" id="inputLabRoom">
                <input type="hidden" name="reservation_date" id="inputReservationDate">
                <input type="hidden" name="reservation_time" id="inputReservationTime">
                <input type="hidden" name="purpose" id="inputPurpose">
                <input type="hidden" name="additional_notes" id="inputAdditionalNotes">
                <input type="hidden" name="computer_unit" id="inputComputerUnit">
                <button type="submit" class="reservation-submit-btn">Submit Reservation</button>
            </form>
        </div>
    </div>
</div>

<style>
.reservation-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.reservation-form .form-group {
    margin-bottom: 0;
}
.reservation-form label {
    display: block;
    font-weight: 600;
    color: #333 !important;
    margin-bottom: 8px;
    font-size: 14px;
}
.reservation-form input,
.reservation-form select,
.reservation-form textarea {
    width: 100%;
    padding: 12px 15px !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 10px !important;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-family: inherit;
    background: white !important;
    color: #333 !important;
}
.reservation-form input:focus,
.reservation-form select:focus,
.reservation-form textarea:focus {
    outline: none;
    border-color: #0f5bbe !important;
    box-shadow: 0 0 0 3px rgba(15, 91, 190, 0.1);
}
.reservation-form textarea {
    resize: vertical;
    min-height: 80px;
}
.computer-info {
    margin-bottom: 15px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 5px;
}
.computer-info p {
    margin: 5px 0;
}
.computer-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    justify-content: center;
}
.computer-legend .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.computer-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    grid-template-rows: repeat(10, 1fr);
    grid-auto-flow: column;
    gap: 8px;
    margin-bottom: 20px;
    max-height: 320px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}
.computer-unit {
    width: 100%;
    aspect-ratio: 1;
    min-width: 35px;
    max-width: 50px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.computer-unit.available {
    background: linear-gradient(135deg, #4caf50 0%, #43a047 100%);
    color: white;
}
.computer-unit.available:hover {
    background: linear-gradient(135deg, #43a047 0%, #388e3c 100%);
    transform: translateY(-2px);
}
.computer-unit.occupied {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
    cursor: not-allowed;
    opacity: 0.7;
}
.computer-unit.unavailable {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
    cursor: not-allowed;
    opacity: 0.7;
}
.computer-unit.selected {
    border: 3px solid #0f5bbe;
    box-shadow: 0 0 10px rgba(15, 91, 190, 0.5);
}
.computer-modal {
    max-width: 600px;
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
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.notification-modal-overlay.active {
    display: flex;
}

.notification-modal {
    background: white;
    border-radius: 10px;
    width: 90%;
    max-width: 450px;
    max-height: 80vh;
    overflow-y: auto;
}

.notification-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.notification-modal-header h2 {
    margin: 0;
    color: #333;
    font-size: 20px;
}

.notification-close-btn {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #666;
}

.notification-modal-body {
    padding: 15px;
}

.notification-actions {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 10px;
}

.mark-all-read-btn {
    color: #0f5bbe;
    font-size: 13px;
    text-decoration: none;
}

.mark-all-read-btn:hover {
    text-decoration: underline;
}

.notification-item {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #f5f5f5;
    border-left: 4px solid #0f5bbe;
}

.notification-item.unread {
    background: #e3f2fd;
    border-left-color: #0f5bbe;
}

.notification-item.read {
    opacity: 0.7;
    border-left-color: #ccc;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.notification-title {
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.notification-title.reservation_approved {
    color: #4caf50;
}

.notification-title.reservation_rejected {
    color: #f44336;
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
    color: #666;
}

.notification-message {
    color: #555;
    font-size: 14px;
    line-height: 1.4;
}

.mark-read-btn {
    display: inline-block;
    margin-top: 8px;
    color: #0f5bbe;
    font-size: 12px;
    text-decoration: none;
}

.mark-read-btn:hover {
    text-decoration: underline;
}

.no-notifications {
    text-align: center;
    padding: 30px;
    color: #666;
}
</style>

<?php include '../includes/reservation_system.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkNewNotifications();
    
    const feedbackModal = document.getElementById('feedbackModal');
    const closeFeedbackBtn = document.getElementById('closeFeedback');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navRight = document.getElementById('navRight');
    
    // Mobile menu toggle
    if(mobileMenuToggle && navRight) {
        mobileMenuToggle.addEventListener('click', function() {
            navRight.classList.toggle('active');
            this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
        });
    }
    
    // Feedback modal logic
    if (closeFeedbackBtn) {
        closeFeedbackBtn.addEventListener('click', function() {
            feedbackModal.classList.remove('active');
        });
    }
    
    if (feedbackModal) {
        feedbackModal.addEventListener('click', function(e) {
            if (e.target === feedbackModal) {
                feedbackModal.classList.remove('active');
            }
        });
    }
    
    // Notification modal logic
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
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (feedbackModal && feedbackModal.classList.contains('active')) {
                feedbackModal.classList.remove('active');
            }
            if (notificationModal && notificationModal.classList.contains('active')) {
                notificationModal.classList.remove('active');
            }
        }
    });
});

function openFeedbackModal(sitInId, purpose, lab, date) {
    document.getElementById('feedback-sit-in-id').value = sitInId;
    document.getElementById('feedback-details').innerHTML = 
        '<strong>Purpose:</strong> ' + purpose + '<br>' +
        '<strong>Laboratory:</strong> ' + lab + '<br>' +
        '<strong>Date:</strong> ' + date;
    
    // Reset form fields
    document.getElementById('rating').value = '';
    document.getElementById('feedback-comment').value = '';
    
    document.getElementById('feedbackModal').classList.add('active');
}

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

<?php
$stmt_sitin->close();
$stmt_rejected->close();
$conn->close();
?>
