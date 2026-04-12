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

// Auto-create sit_in table if not exists
$create_sitin_table = "CREATE TABLE IF NOT EXISTS sit_in (
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
$conn->query($create_sitin_table);

// Add status column to sit_in table if not exists
$check_status_column = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'status'");
if($check_status_column->num_rows == 0) {
    $conn->query("ALTER TABLE sit_in ADD COLUMN status VARCHAR(20) DEFAULT 'Active'");
} else {
    $check_enum = $conn->query("SHOW COLUMNS FROM sit_in LIKE 'status'");
    if($col = $check_enum->fetch_assoc()) {
        if(strpos($col['Type'], 'enum') !== false) {
            $conn->query("ALTER TABLE sit_in MODIFY COLUMN status VARCHAR(20) DEFAULT 'Active'");
        }
    }
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
    computer_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_reservations_table);

// Rename computer_unit to computer_no if it exists
$check_old_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'computer_unit'");
if($check_old_column->num_rows > 0) {
    $conn->query("ALTER TABLE reservations CHANGE COLUMN computer_unit computer_no VARCHAR(50)");
}

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
            $student_id = $res_row['id_number'];
            $lab = $res_row['lab_room'];
            $purpose = $res_row['purpose'];
            $sit_date = $res_row['reservation_date'];
            $sit_time = $res_row['reservation_time'];
            $computer_no = $res_row['computer_no'];
            
            // Look up student in students table to get correct name and sessions
            $session_stmt = $conn->prepare("SELECT first_name, middle_name, last_name, sessions FROM students WHERE id_number = ?");
            $session_stmt->bind_param("s", $student_id);
            $session_stmt->execute();
            $session_result = $session_stmt->get_result();
            
            if($session_row = $session_result->fetch_assoc()) {
                $remaining_sessions = $session_row['sessions'];
                $student_name = trim($session_row['first_name'] . ' ' . $session_row['middle_name'] . ' ' . $session_row['last_name']);
                
                // Update reservation status to Approved
                $stmt = $conn->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                
                // Determine status based on scheduled date/time
                $now_str = date('Y-m-d H:i:s');
                $scheduled_str = $sit_date . ' ' . $sit_time;
                $sit_in_status = ($scheduled_str <= $now_str) ? 'Active' : 'Pending';

                // Insert into sit_in table with computer number from reservation
                $sit_in_stmt = $conn->prepare("INSERT INTO sit_in (id_number, student_name, purpose, lab, computer_no, sit_in_date, sit_in_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$sit_in_stmt) {
                    $session_stmt->close();
                    $get_stmt->close();
                    header("Location: /SYSARCH/pages/manage_reservations.php?error=" . urlencode("Prepare failed: " . $conn->error));
                    exit;
                }
                $sit_in_stmt->bind_param("ssssisss", $student_id, $student_name, $purpose, $lab, $computer_no, $sit_date, $sit_time, $sit_in_status);
                if (!$sit_in_stmt->execute()) {
                    $sit_in_stmt->close();
                    $session_stmt->close();
                    $get_stmt->close();
                    header("Location: /SYSARCH/pages/manage_reservations.php?error=" . urlencode("Insert failed: " . $sit_in_stmt->error));
                    exit;
                }
                $sit_in_stmt->close();
                
                $session_stmt->close();
                $get_stmt->close();
                
                header("Location: /SYSARCH/pages/manage_reservations.php?success=" . urlencode("Reservation approved! Student will be checked in at the scheduled time ($sit_date $sit_time)."));
                exit;
            } else {
                // Student not found - reject the approval
                $session_stmt->close();
                $get_stmt->close();
                header("Location: /SYSARCH/pages/manage_reservations.php?error=" . urlencode("Student with ID Number '$student_id' not found in the system. Cannot approve reservation."));
                exit;
            }
        }
        $get_stmt->close();
        
        header("Location: /SYSARCH/pages/manage_reservations.php?error=" . urlencode("Reservation not found."));
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

// Auto-activate pending sit-ins where scheduled time has arrived (when admin views reservations)
$now = date('Y-m-d H:i:s');
$conn->query("UPDATE sit_in SET status = 'Active' WHERE status = 'Pending' AND TIMESTAMP(sit_in_date, sit_in_time) <= '$now'");

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filter by lab
$lab_filter = isset($_GET['lab']) ? $_GET['lab'] : '';

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'All';

$res_params = [];
$res_types = '';
$where_clauses = [];

if($lab_filter !== '') {
    $where_clauses[] = "lab_room = ?";
    $res_params[] = $lab_filter;
    $res_types .= 's';
}

if($search !== '') {
    // Validate search input - only allow letters, numbers, spaces
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $search)) {
        $search_error = "Invalid search input. Please enter only letters, numbers, and spaces.";
    } else {
        $search_param = "%$search%";
        $where_clauses[] = "(id_number LIKE ? OR student_name LIKE ?)";
        $res_params[] = $search_param;
        $res_params[] = $search_param;
        $res_types .= 'ss';
    }
}

if($status_filter === 'Pending' || $status_filter === 'Approved' || $status_filter === 'Rejected') {
    $where_clauses[] = "status = ?";
    $res_params[] = $status_filter;
    $res_types .= 's';
}

$where_clause = '';
if(!empty($where_clauses)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch all reservations
$sql = "SELECT * FROM reservations $where_clause ORDER BY created_at DESC";
$res_stmt = $conn->prepare($sql);
if(!empty($res_params)) {
    $res_stmt->bind_param($res_types, ...$res_params);
}
$res_stmt->execute();
$result = $res_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../assets/images/uclogo.png">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .filter-tabs-container {
            text-align: center;
            width: 100%;
        }
        
        .search-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
            padding-right: 20px;
        }
        
        .filter-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 0 20px;
            gap: 20px;
        }
        
        .lab-filter {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }
        
        .lab-select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 150px;
        }
        
        .lab-form {
            display: flex;
            align-items: center;
            gap: 10px;
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
            vertical-align: middle;
            margin: 0;
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
            vertical-align: middle;
            margin: 0;
        }
        
        .btn-reject:hover {
            background: #da190b;
        }
        
        .btn-approve:disabled,
        .btn-reject:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .action-buttons {
            white-space: nowrap;
            text-align: center;
            min-width: 120px;
        }
        
        .action-buttons .btn-approve,
        .action-buttons .btn-reject {
            margin: 2px 4px;
            display: inline-block;
            padding: 6px 12px;
            font-size: 12px;
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
    <ul class="dashboard-right" id="navRight">    
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php" class="active">Reservations</a></li>
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
        <h2 style="text-align: center; width: 100%;">Manage Reservations</h2>
    </div>
    
    <!-- Filter Tabs and Search -->
    <div class="filter-tabs-container">
        <div class="filter-tabs">
            <a href="?status=Pending<?php echo $lab_filter !== '' ? '&lab='.urlencode($lab_filter) : ''; echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=Approved<?php echo $lab_filter !== '' ? '&lab='.urlencode($lab_filter) : ''; echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Approved' ? 'active' : ''; ?>">Approved</a>
            <a href="?status=Rejected<?php echo $lab_filter !== '' ? '&lab='.urlencode($lab_filter) : ''; echo $search !== '' ? '&search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'Rejected' ? 'active' : ''; ?>">Rejected</a>
            <a href="?<?php echo $lab_filter !== '' ? 'lab='.urlencode($lab_filter).'&' : ''; echo $search !== '' ? 'search='.urlencode($search) : ''; ?>" class="filter-tab <?php echo $status_filter === 'All' || $status_filter === '' ? 'active' : ''; ?>">All</a>
        </div>
    </div>
    <div class="filter-row">
        <div class="lab-filter">
            <form method="GET" action="" class="lab-form">
                <select name="lab" onchange="this.form.submit()" class="lab-select">
                    <option value="">All Labs</option>
                    <option value="524" <?php echo $lab_filter === '524' ? 'selected' : ''; ?>>Room 524</option>
                    <option value="525" <?php echo $lab_filter === '525' ? 'selected' : ''; ?>>Room 525</option>
                    <option value="526" <?php echo $lab_filter === '526' ? 'selected' : ''; ?>>Room 526</option>
                    <option value="527" <?php echo $lab_filter === '527' ? 'selected' : ''; ?>>Room 527</option>
                    <option value="528" <?php echo $lab_filter === '528' ? 'selected' : ''; ?>>Room 528</option>
                </select>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            </form>
        </div>
        <div class="search-row">
            <form method="GET" action="" class="search-form">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="hidden" name="lab" value="<?php echo htmlspecialchars($lab_filter); ?>">
                <input type="text" name="search" placeholder="Search by ID Number or Name..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if($search !== ''): ?>
                    <a href="?status=<?php echo $status_filter; ?>&lab=<?php echo urlencode($lab_filter); ?>" class="clear-search-btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <p id="success-message" style="color: green; margin-bottom: 15px; text-align: center;">✅ <?php echo htmlspecialchars($_GET['success']); ?></p>
    <?php endif; ?>

    <?php if(isset($search_error)): ?>
        <p id="error-message" style="color: red; margin-bottom: 15px; text-align: center;">❌ <?php echo htmlspecialchars($search_error); ?></p>
    <?php elseif(isset($_GET['error'])): ?>
        <p id="error-message" style="color: red; margin-bottom: 15px; text-align: center;">❌ <?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <table class="students-table">
        <thead>
            <tr>
                <th>ID Number</th>
                <th>Student Name</th>
                <th>Lab Room</th>
                <th>Computer No.</th>
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
                        <td><?php echo htmlspecialchars($row['computer_no'] ?: '-'); ?></td>
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
if(isset($res_stmt)) {
    $res_stmt->close();
}
$conn->close();
?>
