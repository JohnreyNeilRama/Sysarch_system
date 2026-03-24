<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Auto-create sit_in table with status if not exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS sit_in (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    lab VARCHAR(50) NOT NULL,
    remaining_session INT NOT NULL,
    sit_in_date DATE NOT NULL,
    sit_in_time TIME NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Auto-delete sit-in records older than 1 day (refresh daily)
$cleanup_date = date('Y-m-d', strtotime('-1 day'));
$conn->query("DELETE FROM sit_in WHERE sit_in_date < '$cleanup_date'");

// Add status column to sit_in table if it doesn't exist
$check_status_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'status'");
if($check_status_column->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Active'");
}

// Handle Logout action (set status to Inactive and deduct session)
if(isset($_GET['action']) && $_GET['action'] === 'logout' && isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Get the sit-in record first to get the id_number
    $get_stmt = $conn->prepare("SELECT id_number FROM sit_in WHERE id = ?");
    $get_stmt->bind_param("i", $id);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    
    if($row = $get_result->fetch_assoc()) {
        $student_id = $row['id_number'];
        
        // Deduct 1 session from the student
        $update_session = $conn->prepare("UPDATE students SET sessions = sessions - 1 WHERE id_number = ?");
        $update_session->bind_param("s", $student_id);
        $update_session->execute();
        $update_session->close();
    }
    $get_stmt->close();
    
    // Update status to Inactive
    $stmt = $conn->prepare("UPDATE sit_in SET status = 'Inactive' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: /SYSARCH/pages/manage_sitin.php?success=Student logged out and session deducted!");
    exit;
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

$where_clauses = [];

if($search !== '') {
    $search_param = "%$search%";
    $where_clauses[] = "(id_number LIKE '$search_param' OR student_name LIKE '$search_param')";
}

if($status_filter !== 'All' && ($status_filter === 'Active' || $status_filter === 'Inactive')) {
    $where_clauses[] = "status = '$status_filter'";
}

$where_clause = '';
if(!empty($where_clauses)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch all sit-in records for today
$today_date = date('Y-m-d');
$sql = "SELECT * FROM sit_in WHERE sit_in_date = '$today_date' ORDER BY sit_in_time DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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

    <ul class="dashboard-right">    
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php" class="active">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="#">Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<!-- Main Content -->
<div class="content">
    <div class="header-row">
        <h2 style="text-align: center; width: 100%;">Sit-in Logs</h2>
    </div>
    
    <!-- Filter Tabs and Search -->
    <div class="add-student-row">
        <div class="filter-tabs">
            <a href="?status=Active" class="filter-tab <?php echo $status_filter === 'Active' ? 'active' : ''; ?>">Active</a>
            <a href="?status=Inactive" class="filter-tab <?php echo $status_filter === 'Inactive' ? 'active' : ''; ?>">Inactive</a>
            <a href="?" class="filter-tab <?php echo $status_filter === 'All' ? 'active' : ''; ?>">All</a>
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

    <table class="students-table">
        <thead>
            <tr>
                <th>Sit ID</th>
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Sit Lab</th>
                <th>Purpose</th>
                <th>Date & Time</th>
                <th>Remaining Sessions</th>
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
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td>
                            <div class="sit-in-details">
                                <strong>Date:</strong> <?php echo htmlspecialchars($row['sit_in_date']); ?><br>
                                <strong>Time:</strong> <?php echo htmlspecialchars($row['sit_in_time']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['remaining_session']); ?></td>
                        <td>
                            <?php if($row['status'] === 'Active'): ?>
                                <span class="status-active">Active</span>
                            <?php else: ?>
                                <span class="status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <?php if($row['status'] === 'Active'): ?>
                                <a href="?action=logout&id=<?php echo $row['id']; ?>" class="btn-logout" onclick="return confirm('Log out this student from sit-in?');">Logout</a>
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
    // Auto-hide success message after 3 seconds
    setTimeout(function() {
        var message = document.getElementById('success-message');
        if(message) {
            message.style.display = 'none';
        }
    }, 3000);
</script>

</body>
</html>

<?php
if(isset($result)) {
    $result->free();
}
$conn->close();
?>
