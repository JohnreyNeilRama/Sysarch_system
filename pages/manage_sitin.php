<?php
session_start();

// Refresh session to extend lifetime
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: /SYSARCH/login.php");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Auto-create sit_in table with status if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS sit_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    lab VARCHAR(50) NOT NULL,
    computer_no VARCHAR(50) NOT NULL,
    sit_in_date DATE NOT NULL,
    sit_in_time TIME NOT NULL,
    status VARCHAR(20) DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Add status column to sit_in table if it doesn't exist
$check_status_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'status'");
if($check_status_column->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in ADD COLUMN status VARCHAR(20) DEFAULT 'Active'");
} else {
    $col = $check_status_column->fetch_assoc();
    if(strpos($col['Type'], 'enum') !== false) {
        $conn->query("ALTER TABLE sit_in MODIFY COLUMN status VARCHAR(20) DEFAULT 'Active'");
    }
}

// Add logout_time column to sit_in table if it doesn't exist
$check_logout_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'logout_time'");
if($check_logout_column->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in ADD COLUMN logout_time TIME NULL");
}

// Rename remaining_session to computer_no if it exists
$check_old_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'remaining_session'");
if($check_old_column->num_rows > 0) {
    $conn->query("ALTER TABLE sit_in CHANGE COLUMN remaining_session computer_no VARCHAR(50) NOT NULL");
}

// Drop sessions column if it exists
$check_sessions_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'sessions'");
if($check_sessions_column->num_rows > 0) {
    $conn->query("ALTER TABLE sit_in DROP COLUMN sessions");
}

// Auto-activate pending sit-ins where scheduled time has arrived (BEFORE cleanup)
$now = date('Y-m-d H:i:s');
$activate_sql = "UPDATE sit_in SET status = 'Active' WHERE status = 'Pending' AND TIMESTAMP(sit_in_date, sit_in_time) <= '$now'";
$conn->query($activate_sql);

// Auto-delete inactive sit-in records older than 30 days (keep active/pending)
$cleanup_date = date('Y-m-d', strtotime('-30 days'));
$conn->query("DELETE FROM sit_in WHERE sit_in_date < '$cleanup_date' AND status = 'Inactive'");

// Handle Logout action (set status to Inactive and deduct session)
if(isset($_GET['action']) && $_GET['action'] === 'logout' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Get the sit-in record first to verify it's Active
    $get_stmt = $conn->prepare("SELECT id_number, status, sit_in_date, sit_in_time FROM sit_in WHERE id = ?");
    $get_stmt->bind_param("i", $id);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    
    if($row = $get_result->fetch_assoc()) {
        // Only allow logout for Active sit-ins
        if($row['status'] !== 'Active') {
            $get_stmt->close();
            header("Location: /SYSARCH/pages/manage_sitin.php?error=" . urlencode("Cannot log out a student whose sit-in is not currently active."));
            exit;
        }
        
        $student_id = $row['id_number'];
        
        // Deduct 1 session from the student and update points_earned based on sessions used
        $update_session = $conn->prepare("UPDATE students SET sessions = sessions - 1, points_earned = FLOOR((30 - (sessions - 1)) / 3) WHERE id_number = ?");
        $update_session->bind_param("s", $student_id);
        $update_session->execute();
        $update_session->close();
    }
    $get_stmt->close();
    
    // Update status to Inactive and record logout_time
    $logout_time = date('H:i:s');
    $stmt = $conn->prepare("UPDATE sit_in SET status = 'Inactive', logout_time = ? WHERE id = ?");
    $stmt->bind_param("si", $logout_time, $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: /SYSARCH/pages/manage_sitin.php?success=Student logged out and session deducted!");
    exit;
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

$params = [];
$types = '';
$where_clauses = [];

if($search !== '') {
    $search_param = "%$search%";
    $where_clauses[] = "(id_number LIKE ? OR student_name LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if($status_filter !== 'All' && in_array($status_filter, ['Active', 'Inactive', 'Pending'])) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = '';
if(!empty($where_clauses)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch sit-in records
$sql = "SELECT * FROM sit_in $where_clause ORDER BY sit_in_date DESC, sit_in_time DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-in Logs - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../assets/images/uclogo.png">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .filter-tab {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: #0f5bbe;
            color: white;
        }
        
        .filter-tab:not(.active) {
            background: #e0e0e0;
            color: #333;
        }
        
        .filter-tab:hover:not(.active) {
            background: #d0d0d0;
        }
        
        .status-active {
            color: #4caf50;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #f44336;
            font-weight: bold;
        }
        
        .btn-logout {
            background: #ff9800;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            vertical-align: middle;
            margin: 0;
        }
        
        .btn-logout:hover {
            background: #e68900;
        }
        
        .btn-logout:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .sit-in-details {
            font-size: 13px;
            color: #666;
        }
        
        .sit-in-details strong {
            color: #333;
        }
    </style>
</head>

<body class="admin-dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        <img class="admin-logo" src="/SYSARCH/assets/images/uclogo.png" alt="UC Logo">
        <span class="admin-title">Admin Dashboard</span>
    </div>
    <ul class="dashboard-right" id="navRight">    
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php" class="active">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="feedback_reports.php">Feedback Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

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

<!-- Main Content -->
<div class="content">
    <div class="header-row">
        <h2 style="text-align: center; width: 100%;">Sit-in Logs</h2>
    </div>
    
    <!-- Filter Tabs and Search -->
    <div class="add-student-row">
        <div class="filter-tabs">
            <a href="?status=Active<?php echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Active' ? 'active' : ''; ?>">Active</a>
            <a href="?status=Pending<?php echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=Inactive<?php echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Inactive' ? 'active' : ''; ?>">Inactive</a>
            <a href="?<?php echo $search !== '' ? 'search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'All' ? 'active' : ''; ?>">All</a>
        </div>
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input type="text" name="search" placeholder="Search by ID Number or Name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if($search !== ''): ?>
                <a href="?status=<?php echo $status_filter; ?>" class="clear-search-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <p id="success-message" style="color: green; margin-bottom: 15px; text-align: center;">✅ <?php echo htmlspecialchars($_GET['success']); ?></p>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <p id="error-message" style="color: red; margin-bottom: 15px; text-align: center;">❌ <?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <table class="students-table">
        <thead>
            <tr>
                <th>Sit ID</th>
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Lab Room</th>
                <th>Computer No.</th>
                <th>Purpose</th>
                <th>Date</th>
                <th>Login Time</th>
                <th>Logout Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab']); ?></td>
                        <td><?php echo htmlspecialchars($row['computer_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['sit_in_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['sit_in_time']); ?></td>
                        <td><?php echo isset($row['logout_time']) && $row['logout_time'] ? htmlspecialchars($row['logout_time']) : '-'; ?></td>
                        <td>
                            <?php if($row['status'] === 'Active'): ?>
                                <span class="status-active">Active</span>
                            <?php elseif($row['status'] === 'Pending'): ?>
                                <span style="color: #ff9800; font-weight: bold;">Pending</span>
                            <?php else: ?>
                                <span class="status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <?php if($row['status'] === 'Active'): ?>
                                <a href="?action=logout&id=<?php echo $row['id']; ?>" class="btn-logout" onclick="return confirm('Log out this student from sit-in?');">Logout</a>
                            <?php elseif($row['status'] === 'Pending'): ?>
                                <span style="color: #ff9800;">Scheduled</span>
                            <?php else: ?>
                                <span style="color: #999;">Logged Out</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center;">No sit-in records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
    // Auto-hide success/error messages after 3 seconds
    setTimeout(function() {
        var successMsg = document.getElementById('success-message');
        if(successMsg) {
            successMsg.style.display = 'none';
        }
        var errorMsg = document.getElementById('error-message');
        if(errorMsg) {
            errorMsg.style.display = 'none';
        }
    }, 3000);
</script>

</body>
</html>

<?php
if(isset($result)) {
    $result->free();
}
if(isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
