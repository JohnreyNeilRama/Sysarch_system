<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Auto-create lab_software table if not exists
$create_software_table = "CREATE TABLE IF NOT EXISTS lab_software (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_room VARCHAR(50) NOT NULL,
    software_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_software_table);

// Populate sample software data if table is empty
$check_empty = $conn->query("SELECT COUNT(*) as count FROM lab_software");
$row = $check_empty->fetch_assoc();
if ($row['count'] == 0) {
    $conn->query("TRUNCATE TABLE lab_software");
    $sample_data = [
        // Room 524
        ['524', 'Google Chrome', 'Web Browser'],
        ['524', 'Mozilla Firefox', 'Web Browser'],
        ['524', 'Visual Studio Code', 'Programming & IDEs'],
        ['524', 'NetBeans 12.0', 'Programming & IDEs'],
        ['524', 'Dev C++', 'Programming & IDEs'],
        ['524', 'MySQL Workbench', 'Database Management'],
        ['524', 'XAMPP Control Panel', 'Web Development'],
        ['524', 'Microsoft Word', 'Office Productivity'],
        ['524', 'Microsoft Excel', 'Office Productivity'],
        
        // Room 526
        ['526', 'Google Chrome', 'Web Browser'],
        ['526', 'Microsoft Edge', 'Web Browser'],
        ['526', 'Eclipse IDE', 'Programming & IDEs'],
        ['526', 'PyCharm Community', 'Programming & IDEs'],
        ['526', 'Python 3.9', 'Programming & IDEs'],
        ['526', 'Postman', 'Web Development'],
        ['526', 'Node.js', 'Web Development'],
        ['526', 'Wireshark', 'Network & Security'],
        ['526', 'Cisco Packet Tracer', 'Network & Security'],
        
        // Room 528
        ['528', 'Google Chrome', 'Web Browser'],
        ['528', 'Visual Studio 2022', 'Programming & IDEs'],
        ['528', 'SQL Server Management Studio', 'Database Management'],
        ['528', 'Microsoft SQL Server', 'Database Management'],
        ['528', 'Power BI Desktop', 'Utility'],
        ['528', 'Microsoft PowerPoint', 'Office Productivity'],
        
        // Room 530
        ['530', 'Google Chrome', 'Web Browser'],
        ['530', 'Android Studio', 'Mobile Development'],
        ['530', 'IntelliJ IDEA', 'Programming & IDEs'],
        ['530', 'Kotlin SDK', 'Programming & IDEs'],
        ['530', 'Flutter SDK', 'Mobile Development'],
        ['530', 'SQLite Browser', 'Database Management'],
        ['530', 'Git Bash', 'Utility'],
        
        // Room 544
        ['544', 'Google Chrome', 'Web Browser'],
        ['544', 'Sublime Text 4', 'Web Development'],
        ['544', 'Brackets', 'Web Development'],
        ['544', 'FileZilla', 'Utility'],
        ['544', 'Putty', 'Utility'],
        ['544', 'WinRAR', 'Utility'],
        ['544', 'Adobe Acrobat Reader', 'Office Productivity'],
        
        // Room 542
        ['542', 'Google Chrome', 'Web Browser'],
        ['542', 'Adobe Photoshop 2023', 'Design & Multimedia'],
        ['542', 'Adobe Illustrator 2023', 'Design & Multimedia'],
        ['542', 'Adobe Premiere Pro', 'Design & Multimedia'],
        ['542', 'Blender 3D', 'Design & Multimedia'],
        ['542', 'Canva Desktop', 'Design & Multimedia'],
        ['542', 'VLC Media Player', 'Design & Multimedia']
    ];
    
    $stmt = $conn->prepare("INSERT INTO lab_software (lab_room, software_name, category) VALUES (?, ?, ?)");
    foreach ($sample_data as $data) {
        $stmt->bind_param("sss", $data[0], $data[1], $data[2]);
        $stmt->execute();
    }
    $stmt->close();
}

// Fetch software list grouped by lab room
$labs = ['524', '526', '528', '530', '544', '542'];
$software_by_lab = [];

foreach ($labs as $lab) {
    $result = $conn->query("SELECT software_name, category FROM lab_software WHERE lab_room = '$lab' ORDER BY category, software_name");
    $software_by_lab[$lab] = [];
    while ($row = $result->fetch_assoc()) {
        $software_by_lab[$lab][] = $row;
    }
}

// Fetch unread notifications count for the navbar
$notif_student_id = $_SESSION['id_number'];
$notif_count_sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE id_number = ? AND is_read = 0";
$notif_count_stmt = $conn->prepare($notif_count_sql);
$notif_count_stmt->bind_param("s", $notif_student_id);
$notif_count_stmt->execute();
$notif_count_result = $notif_count_stmt->get_result();
$notif_count_row = $notif_count_result->fetch_assoc();
$unread_notifications = $notif_count_row['unread_count'];
$notif_count_stmt->close();

// Fetch latest notifications
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
    header("Location: /SYSARCH/pages/software_availability.php");
    exit;
}

if(isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id_number = ?");
    $mark_all_stmt->bind_param("s", $notif_student_id);
    $mark_all_stmt->execute();
    $mark_all_stmt->close();
    header("Location: /SYSARCH/pages/software_availability.php");
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
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Availability - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
    <style>
        .software-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .software-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .software-header h1 {
            color: #1a3a5f;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .software-header p {
            color: #666;
            font-size: 16px;
        }

        .lab-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .lab-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid #eef2f7;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .lab-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .lab-card-header {
            background: linear-gradient(135deg, #1a3a5f 0%, #0f5bbe 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lab-card-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .lab-badge {
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(5px);
        }

        .lab-card-body {
            padding: 25px;
        }

        .software-category-group {
            margin-bottom: 20px;
        }

        .software-category-group:last-child {
            margin-bottom: 0;
        }

        .category-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f5bbe;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .category-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .software-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .software-tag {
            background: #f1f5f9;
            color: #475569;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .software-tag:hover {
            background: #e2e8f0;
            color: #1e293b;
            transform: scale(1.05);
        }

        .empty-software {
            text-align: center;
            color: #94a3b8;
            font-style: italic;
            padding: 20px 0;
        }

        @media (max-width: 768px) {
            .lab-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-page">

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
        <li><a href="/SYSARCH/pages/software_availability.php" class="active">Software</a></li>
        <li><a href="/SYSARCH/pages/edit_profile.php">Edit Profile</a></li>
        <li><a href="/SYSARCH/pages/history.php">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>
</nav>

<div class="software-container">
    <div class="software-header">
        <h1>🖥️ Software Availability</h1>
        <p>Find the right laboratory room for your specific software requirements.</p>
    </div>

    <div class="lab-grid">
        <?php foreach ($labs as $lab): ?>
            <div class="lab-card">
                <div class="lab-card-header">
                    <h2>Room <?php echo $lab; ?></h2>
                    <span class="lab-badge">CCS Lab</span>
                </div>
                <div class="lab-card-body">
                    <?php 
                    $current_lab_software = $software_by_lab[$lab];
                    if (empty($current_lab_software)): 
                    ?>
                        <p class="empty-software">No software information available.</p>
                    <?php else: 
                        $grouped = [];
                        foreach ($current_lab_software as $sw) {
                            $grouped[$sw['category']][] = $sw['software_name'];
                        }
                        foreach ($grouped as $cat => $names):
                    ?>
                        <div class="software-category-group">
                            <div class="category-title"><?php echo htmlspecialchars($cat); ?></div>
                            <div class="software-list">
                                <?php foreach ($names as $name): ?>
                                    <span class="software-tag"><?php echo htmlspecialchars($name); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
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
    background: #f0f7ff;
    border-left: 4px solid #1565c0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    checkNewNotifications();
    
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navRight = document.getElementById('navRight');
    
    // Mobile menu toggle
    if(mobileMenuToggle && navRight) {
        mobileMenuToggle.addEventListener('click', function() {
            navRight.classList.toggle('active');
            this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
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
        
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                if(notificationModal.classList.contains('active')) {
                    notificationModal.classList.remove('active');
                }
            }
        });
    }
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
