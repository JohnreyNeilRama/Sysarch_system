<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
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

// Handle Approve/Reject actions
if(isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action === 'approve') {
        // First get the reservation details
        $get_stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $get_stmt->bind_param("i", $id);
        $get_stmt->execute();
        $res_result = $get_stmt->get_result();
        
        if($res_row = $res_result->fetch_assoc()) {
            // Update reservation status to Approved
            $stmt = $conn->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            
            // Create sit_in record
            $student_id = $res_row['id_number'];
            $student_name = $res_row['student_name'];
            $lab = $res_row['lab_room'];
            $purpose = $res_row['purpose'];
            $sit_date = $res_row['reservation_date'];
            $sit_time = $res_row['reservation_time'];
            
            // Get remaining sessions from students table
            $session_stmt = $conn->prepare("SELECT sessions FROM students WHERE id_number = ?");
            $session_stmt->bind_param("s", $student_id);
            $session_stmt->execute();
            $session_result = $session_stmt->get_result();
            $remaining_sessions = 30; // default
            if($session_row = $session_result->fetch_assoc()) {
                $remaining_sessions = $session_row['sessions'];
            }
            $session_stmt->close();
            
            // Insert into sit_in table
            $sit_in_stmt = $conn->prepare("INSERT INTO sit_in (id_number, student_name, purpose, lab, remaining_session, sit_in_date, sit_in_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            $sit_in_stmt->bind_param("ssssiss", $student_id, $student_name, $purpose, $lab, $remaining_sessions, $sit_date, $sit_time);
            $sit_in_stmt->execute();
            $sit_in_stmt->close();
        }
        $get_stmt->close();
        
        header("Location: /SYSARCH/pages/manage_reservations.php?success=Reservation approved and student logged in!");
        exit;
    } elseif($action === 'reject') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: /SYSARCH/pages/manage_reservations.php?success=Reservation rejected!");
        exit;
    }
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

$where_clauses = [];

if($search !== '') {
    $search_param = "%$search%";
    $where_clauses[] = "(id_number LIKE '$search_param' OR student_name LIKE '$search_param' OR lab_room LIKE '$search_param' OR purpose LIKE '$search_param')";
}

if($status_filter === 'Pending' || $status_filter === 'Approved' || $status_filter === 'Rejected') {
    $where_clauses[] = "status = '$status_filter'";
}

$where_clause = '';
if(!empty($where_clauses)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch all reservations
$sql = "SELECT * FROM reservations $where_clause ORDER BY created_at DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Reservations - CCS Sit-in Monitoring System</title>
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
        
        .status-pending {
            color: #ff9800;
            font-weight: bold;
        }
        
        .status-approved {
            color: #4caf50;
            font-weight: bold;
        }
        
        .status-rejected {
            color: #f44336;
            font-weight: bold;
        }
        
        .btn-approve {
            background: #4caf50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-approve:hover {
            background: #45a049;
        }
        
        .btn-reject {
            background: #f44336;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .btn-reject:hover {
            background: #da190b;
        }
        
        .btn-approve:disabled,
        .btn-reject:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .reservation-details {
            font-size: 13px;
            color: #666;
        }
        
        .reservation-details strong {
            color: #333;
        }
        
        .add-student-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-form input[type="text"] {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            width: 250px;
        }
        
        .search-btn {
            padding: 10px 20px;
            background: #0f5bbe;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            background: #0d4fa1;
        }
        
        .clear-search-btn {
            padding: 10px 20px;
            background: #e0e0e0;
            color: #333;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .clear-search-btn:hover {
            background: #d0d0d0;
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
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php" class="active">Reservations</a></li>
        <li><a href="#">Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<!-- Main Content -->
<div class="content">
    <div class="header-row">
        <h2 style="text-align: center; width: 100%;">Manage Reservations</h2>
    </div>
    
    <!-- Filter Tabs and Search -->
    <div class="add-student-row">
        <div class="filter-tabs">
            <a href="?status=Pending" class="filter-tab <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=Approved" class="filter-tab <?php echo $status_filter === 'Approved' ? 'active' : ''; ?>">Approved</a>
            <a href="?status=Rejected" class="filter-tab <?php echo $status_filter === 'Rejected' ? 'active' : ''; ?>">Rejected</a>
            <a href="?" class="filter-tab <?php echo $status_filter === 'All' || $status_filter === '' ? 'active' : ''; ?>">All</a>
        </div>
        <form method="GET" action="" class="search-form">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <input type="text" name="search" placeholder="Search by ID, Name, Lab, or Purpose..." value="<?php echo htmlspecialchars($search); ?>">
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
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Lab Room</th>
                <th>Date & Time</th>
                <th>Purpose</th>
                <th>Notes</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab_room']); ?></td>
                        <td>
                            <div class="reservation-details">
                                <strong>Date:</strong> <?php echo htmlspecialchars($row['reservation_date']); ?><br>
                                <strong>Time:</strong> <?php echo htmlspecialchars($row['reservation_time']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['additional_notes'] ?: '-'); ?></td>
                        <td>
                            <?php if($row['status'] === 'Pending'): ?>
                                <span class="status-pending">Pending</span>
                            <?php elseif($row['status'] === 'Approved'): ?>
                                <span class="status-approved">Approved</span>
                            <?php else: ?>
                                <span class="status-rejected">Rejected</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <?php if($row['status'] === 'Pending'): ?>
                                <a href="?action=approve&id=<?php echo $row['id']; ?>" class="btn-approve" onclick="return confirm('Approve this reservation?');">Approve</a>
                                <a href="?action=reject&id=<?php echo $row['id']; ?>" class="btn-reject" onclick="return confirm('Reject this reservation?');">Reject</a>
                            <?php elseif($row['status'] === 'Approved'): ?>
                                <span style="color: #4caf50;">✓ Approved</span>
                            <?php else: ?>
                                <span style="color: #f44336;">✗ Rejected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No reservations found.</td>
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
