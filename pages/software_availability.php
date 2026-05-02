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
if ($row['count'] != 45) {
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
        Dashboard
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
        <li><a href="#" class="reservation-link">Reservation</a></li>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navRight = document.getElementById('navRight');
    mobileMenuToggle.addEventListener('click', function() {
        navRight.classList.toggle('active');
        this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
    });
});
</script>

</body>
</html>
