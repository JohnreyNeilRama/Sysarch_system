<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Include database connection
include '../includes/connect.php';

// Handle student ID lookup request
if(isset($_GET['id_lookup'])) {
    $lookup_id = $_GET['id_lookup'];
    
    $stmt = $conn->prepare("SELECT id, first_name, last_name, sessions FROM students WHERE id_number = ?");
    $stmt->bind_param("s", $lookup_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        $student_name = $row['first_name'] . ' ' . $row['last_name'];
        $student_sessions = $row['sessions'];
        echo '<div id="student-data" data-id="' . $row['id'] . '" data-name="' . htmlspecialchars($student_name) . '" data-sessions="' . $student_sessions . '"></div>';
    } else {
        echo '<div id="student-data"></div>';
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// Auto-create sit_in table if not exists
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

// Add sessions column to students table if not exists
$check_column = $conn->query("SHOW COLUMNS FROM students LIKE 'sessions'");
if($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN sessions INT DEFAULT 30");
}

// Add points_earned column to students table if not exists
$check_points_column = $conn->query("SHOW COLUMNS FROM students LIKE 'points_earned'");
if($check_points_column->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN points_earned INT DEFAULT 0 AFTER sessions");
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

// Handle Sit-in Form Submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_number'])) {
    $id_number = $_POST['id_number'];
    $student_name = $_POST['student_name'];
    $purpose = $_POST['purpose'];
    $lab = $_POST['lab'];
    $computer_no = $_POST['computer_no'];
    $sit_in_date = date('Y-m-d');
    $sit_in_time = date('H:i:s');
    
    // Check if student exists in the database
    $check_stmt = $conn->prepare("SELECT id, id_number, first_name, last_name, sessions FROM students WHERE id_number = ?");
    $check_stmt->bind_param("s", $id_number);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows == 0) {
        // Student not found - show error
        echo "<script>alert('Error: Student with ID Number " . $id_number . " is not registered in the system. Please register the student first.');</script>";
    } else {
        // Student found - get their details
        $student_row = $check_result->fetch_assoc();
        $db_student_name = $student_row['first_name'] . ' ' . $student_row['last_name'];
        $student_sessions = $student_row['sessions'];
        
        // Check if student has remaining sessions (just for validation, not deducted yet)
        if($student_sessions <= 0) {
            echo "<script>alert('Error: Student has no remaining sessions. Please renew sessions first.');</script>";
        } else {
            // Check if student already has an active session
            $active_check_stmt = $conn->prepare("SELECT id FROM sit_in WHERE id_number = ? AND status = 'Active'");
            $active_check_stmt->bind_param("s", $id_number);
            $active_check_stmt->execute();
            $active_check_result = $active_check_stmt->get_result();
            
            if($active_check_result->num_rows > 0) {
                $active_check_stmt->close();
                echo "<script>alert('Error: Student already has an active session. Please end the current session before starting a new one.');</script>";
            } else {
                $active_check_stmt->close();
                // Insert sit-in record without deducting session (session will be deducted on logout)
                $stmt = $conn->prepare("INSERT INTO sit_in (id_number, student_name, purpose, lab, computer_no, sit_in_date, sit_in_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
                $stmt->bind_param("sssssss", $id_number, $db_student_name, $purpose, $lab, $computer_no, $sit_in_date, $sit_in_time);
                
                if($stmt->execute()) {
                    // Create notification for the student
                    $notif_title = "Logged In";
                    $notif_message = "You have been logged in to the SIT-IN system by an admin. Lab: $lab, Computer: $computer_no, Purpose: $purpose";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (id_number, type, title, message) VALUES (?, 'login', ?, ?)");
                    $notif_stmt->bind_param("sss", $id_number, $notif_title, $notif_message);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                    
                    // Redirect to prevent double submission
                    header("Location: /SYSARCH/pages/admin_dashboard.php");
                    exit;
                } else {
                    $sit_in_error = $stmt->error;
                }
                $stmt->close();
            }
        }
    }
    $check_stmt->close();
}

// Handle Announcement Form Submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['announcement_date']) && isset($_POST['message'])) {
    $admin_name = $_SESSION['admin_username'];
    $announcement_date = $_POST['announcement_date'];
    $message = $_POST['message'];
    
    $stmt = $conn->prepare("INSERT INTO announcements (admin_name, announcement_date, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $admin_name, $announcement_date, $message);
    
    if($stmt->execute()) {
        header("Location: /SYSARCH/pages/admin_dashboard.php?success=announcement");
        exit;
    }
    $stmt->close();
}

// Handle Delete Announcement
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement'])) {
    $delete_id = intval($_POST['announcement_id']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if($stmt->execute()) {
        header("Location: /SYSARCH/pages/admin_dashboard.php?success=deleted");
        exit;
    }
    $stmt->close();
}

// Handle Edit Announcement
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_announcement'])) {
    $edit_id = intval($_POST['announcement_id']);
    $edit_date = $_POST['edit_date'];
    $edit_message = $_POST['edit_message'];
    $stmt = $conn->prepare("UPDATE announcements SET announcement_date = ?, message = ? WHERE id = ?");
    $stmt->bind_param("ssi", $edit_date, $edit_message, $edit_id);
    if($stmt->execute()) {
        header("Location: /SYSARCH/pages/admin_dashboard.php?success=updated");
        exit;
    }
    $stmt->close();
}

// Get statistics
$student_count = 0;
$announcement_count = 0;
$today_sitin_count = 0;

// Count total students
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students");
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()){
    $student_count = $row['count'];
}

// Count total announcements
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM announcements");
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()){
    $announcement_count = $row['count'];
}

// Count today's sit-ins
$today_date = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sit_in WHERE sit_in_date = ?");
$stmt->bind_param("s", $today_date);
$stmt->execute();
$result = $stmt->get_result();
if($row = $result->fetch_assoc()){
    $today_sitin_count = $row['count'];
}

// Get students per month (for the bar chart)
$monthly_data = array(
    'Jan' => 0, 'Feb' => 0, 'Mar' => 0, 'Apr' => 0, 
    'May' => 0, 'Jun' => 0, 'Jul' => 0, 'Aug' => 0, 
    'Sep' => 0, 'Oct' => 0, 'Nov' => 0, 'Dec' => 0
);

// Get purpose statistics for this year
$purpose_data = array(
    'C Programming' => 0,
    'Java Programming' => 0,
    'Python Programming' => 0,
    'Web Development' => 0,
    'Database' => 0,
    'Research' => 0,
    'Assignment' => 0,
    'Examination' => 0,
    'Other' => 0
);

$first_day_of_year = date('Y-01-01');
$stmt = $conn->prepare("SELECT purpose, COUNT(*) as count FROM sit_in WHERE sit_in_date >= ? GROUP BY purpose");
$stmt->bind_param("s", $first_day_of_year);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $purpose = $row['purpose'];
    if(isset($purpose_data[$purpose])){
        $purpose_data[$purpose] = $row['count'];
    } else {
        $purpose_data['Other'] += $row['count'];
    }
}
$stmt->close();

// Get most visited lab room for this year
$most_visited_lab = "None";
$most_visited_count = 0;
$stmt = $conn->prepare("SELECT lab, COUNT(*) as session_count FROM sit_in WHERE sit_in_date >= ? GROUP BY lab ORDER BY session_count DESC LIMIT 1");
if($stmt) {
    $stmt->bind_param("s", $first_day_of_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if($row = $result->fetch_assoc()){
        $most_visited_lab = $row['lab'];
        $most_visited_count = $row['session_count'];
    }
    $stmt->close();
}
 else {
    $most_visited_lab = "Error: " . $conn->error;
}

$conn->close();

// Convert purpose data to JavaScript arrays
$purpose_json = json_encode(array_values($purpose_data));
$purpose_labels = json_encode(array_keys($purpose_data));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - CCS Sit-in Monitoring System</title>
<link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
<link rel="icon" type="image/png" href="../assets/images/uclogo.png">
</head>

<body class="admin-dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        <img class="admin-logo" src="/SYSARCH/assets/images/uclogo.png" alt="UC Logo">
        <span class="admin-title">Admin Dashboard</span>
    </div>

    <ul class="dashboard-right">    
        <li><a href="admin_dashboard.php" class="active">Dashboard</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="feedback_reports.php">Feedback Reports</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<div class="dashboard-container">

   <div class="dashboard-main">
    
    <!-- STATISTICS CARDS -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon">
                <span class="icon">👥</span>
            </div>
            <div class="stat-info">
                <h3><?php echo $student_count; ?></h3>
                <p>Total Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <span class="icon">📢</span>
            </div>
            <div class="stat-info">
                <h3><?php echo $announcement_count; ?></h3>
                <p>Announcements</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <span class="icon">💻</span>
            </div>
            <div class="stat-info">
                <h3>5</h3>
                <p>Computer Labs</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <span class="icon">📅</span>
            </div>
            <div class="stat-info">
                <h3><?php echo $today_sitin_count; ?></h3>
                <p>Today's Sit-ins</p>
            </div>
        </div>
    </div>

    <!-- PIE CHART SECTION -->
    <div class="dashboard-card chart-card">
        <div class="card-header">
            Purpose Statistics
            <select id="timeRangeSelector" onchange="updatePieChart(this.value)" style="margin-left: 15px; padding: 5px 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 14px;">
                <option value="year" selected>This Year</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
            </select>
        </div>
        <div class="card-body" style="text-align: center;">
            <div class="pie-chart-layout">
                <div class="pie-chart-left">
<div class="pie-chart-wrapper">
                        <canvas id="pieChart"></canvas>
                    </div>
                    <div class="pie-chart-legend" id="pieLegend"></div>
                </div>
                <div class="pie-chart-stats">
                    <div class="stats-label">Most Visited Lab</div>
                    <div class="stats-value" id="mostVisitedLab"><?php echo htmlspecialchars($most_visited_lab); ?></div>
                    <div id="mostVisitedCount" style="font-size: 10px; color: #999; margin-top: 4px;"><?php echo $most_visited_count; ?> sessions</div>
                </div>
            </div>
        </div>
    </div>


<!-- EDIT STUDENT MODAL -->
<div class="modal-overlay" id="editModal">

    <div class="modal-box edit-modal">

        <div class="modal-header">
            <h2>Edit Student</h2>
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
        </div>

        <div class="modal-body">

            <form class="edit-form">

                <div class="form-group">
                    <label for="editIdNumber">ID Number</label>
                    <input type="text" id="editIdNumber" name="id_number" placeholder="Student ID" readonly>
                </div>

                <div class="form-group">
                    <label for="editFullName">Full Name</label>
                    <input type="text" id="editFullName" name="full_name" placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label for="editYearLevel">Year Level</label>
                    <select id="editYearLevel" name="year_level">
                        <option>Select Year</option>
                        <option>1st Year</option>
                        <option>2nd Year</option>
                        <option>3rd Year</option>
                        <option>4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="editCourse">Course</label>
                    <input type="text" id="editCourse" name="course" placeholder="Enter course">
                </div>

                <div class="form-group">
                    <label for="editSessions">Remaining Sessions</label>
                    <input type="number" id="editSessions" name="sessions" placeholder="Sessions" min="0" max="30">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-close" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>

            </form>

        </div>

    </div>
</div>

    <!-- QUICK ACTIONS & ANNOUNCEMENTS -->
    <div class="two-column-container">
    <!-- QUICK ACTIONS -->
    <div class="dashboard-card">
        <div class="card-header">Quick Actions</div>
        <div class="card-body">
            <div class="actions-grid">
                <button class="action-btn" id="openSitIn">
                    <span class="action-icon">👤</span>
                    <span class="action-text">+ Sit-in</span>
                </button>
                <button class="action-btn" id="openAnnouncements">
                    <span class="action-icon">📢</span>
                    <span class="action-text">Announcements</span>
                </button>
                <button class="action-btn" id="openReports">
                    <span class="action-icon">📋</span>
                    <span class="action-text">Generate Reports</span>
                </button>
                <button class="action-btn" id="openManageLabs">
                    <span class="action-icon">⚙️</span>
                    <span class="action-text">Manage Labs</span>
                </button>
                <button class="action-btn" id="openManageSoftware">
                    <span class="action-icon">💿</span>
                    <span class="action-text">Manage Software</span>
                </button>
                <button class="action-btn" id="openAnalytics">
                    <span class="action-icon">📊</span>
                    <span class="action-text">Analytics</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ANNOUNCEMENTS MANAGEMENT -->
    <div class="dashboard-card">
        <div class="card-header">Manage Announcements</div>
        <div class="card-body">
            <form class="announcement-form" method="POST" action="">
                <div class="form-group">
                    <label for="announcementAdminName">Admin Name</label>
                    <input type="text" id="announcementAdminName" name="admin_name" value="<?php echo $_SESSION['admin_username']; ?>" autocomplete="name" readonly>
                </div>
                <div class="form-group">
                    <label for="announcementDate">Date</label>
                    <input type="date" id="announcementDate" name="announcement_date" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="announcementMessage">Message</label>
                    <textarea id="announcementMessage" name="message" rows="4" placeholder="Enter your announcement..." autocomplete="off" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Post Announcement</button>
            </form>

            <?php if (isset($_GET['success']) && $_GET['success'] == 'announcement'): ?>
                <div class="announcement-success-alert">
                    <span class="alert-icon">✓</span>
                    <span class="alert-text">Announcement posted successfully!</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    </div>

</div>

 <!-- SIT-IN FORM -->
<!-- SIT-IN MODAL -->
<div class="modal-overlay" id="sitInModal">

    <div class="modal-box modern-header-modal">

        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">👤</span>
                <h2>Sit In Form</h2>
            </div>
            <span class="close-btn" id="closeSitIn">&times;</span>
        </div>

        <div class="modal-body">

            <form method="POST" action="">

                <div class="form-group">
                    <label for="idNumber">ID Number:</label>
                    <input type="text" name="id_number" id="idNumber" placeholder="Enter ID Number" onblur="fetchStudentInfo()" autocomplete="off" required>
                </div>

                <div class="form-group">
                    <label for="studentName">Student Name:</label>
                    <input type="text" name="student_name" id="studentName" placeholder="Auto-filled after entering ID" autocomplete="name" readonly required>
                </div>

                <div class="form-group">
                    <label for="sitInPurpose">Purpose:</label>
                    <select id="sitInPurpose" name="purpose" autocomplete="off" required>
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
                    <label for="sitInLab">Lab:</label>
                    <select id="sitInLab" name="lab" autocomplete="off" required>
                        <option value="">Select Lab</option>
                        <option value="524">Lab 524</option>
                        <option value="526">Lab 526</option>
                        <option value="528">Lab 528</option>
                        <option value="530">Lab 530</option>
                        <option value="544">Lab 544</option>
                        <option value="542">Lab 542</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="computerNo">Computer No.:</label>
                    <input type="number" name="computer_no" id="computerNo" placeholder="Enter computer number" autocomplete="off" required>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-close" id="closeSitIn2">Close</button>
                    <button type="submit" class="btn-submit">Sit In</button>
                </div>

            </form>

        </div>

    </div>

</div>

<!-- Pie Chart Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Purpose data from PHP (default: all data)
    let pieChart = null;
    
    // Color palette for pie chart
    // Premium Color palette for pie chart (Modern, harmonious HSL-based colors)
    const colors = [
        '#4361ee', // Modern Blue
        '#3a0ca3', // Deep Purple
        '#7209b7', // Purple
        '#f72585', // Pink/Red
        '#4cc9f0', // Sky Blue
        '#4895ef', // Soft Blue
        '#560bad', // Electric Purple
        '#b5179e', // Magenta
        '#3f37c9'  // Indigo
    ];
    
    // Function to update pie chart based on time range
    function updatePieChart(timeRange) {
        console.log('Updating pie chart for range:', timeRange);
        
        // Clear previous legend before fetching new data
        const legendContainer = document.getElementById('pieLegend');
        legendContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Loading...</p>';
        
        // Get canvas for chart creation
        const canvas = document.getElementById('pieChart');
        
        // Fetch data via AJAX with cache-busting
        // Using an absolute-ish path for better reliability across different URL structures
        const fetchUrl = '/SYSARCH/pages/get_purpose_stats.php?range=' + timeRange + '&t=' + Date.now();
        
        fetch(fetchUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // Clear loading state
                legendContainer.style.opacity = '1';
                
                if (data.error) {
                    console.error('Error from server:', data.error);
                    legendContainer.innerHTML = '<p style="text-align: center; color: #f44336; padding: 20px;">Server Error: ' + data.error + '</p>';
                    return;
                }
                
                console.log('Purpose stats data:', data);
                const labels = data.labels || [];
                // Convert all values to numbers to ensure proper calculations
                const values = (data.values || []).map(v => parseInt(v) || 0);
                
                // Update most visited lab stat dynamically
                const labValueElem = document.getElementById('mostVisitedLab');
                const labCountElem = document.getElementById('mostVisitedCount');
                if (labValueElem && labCountElem) {
                    labValueElem.textContent = data.most_visited_lab || 'None';
                    labCountElem.textContent = (data.most_visited_count || 0) + ' sessions';
                }
                
                const totalCount = values.reduce((a, b) => a + b, 0);

                if (totalCount === 0) {
                    if (pieChart) {
                        pieChart.data.datasets[0].data = values;
                        pieChart.update('active'); // Use active mode for smooth transition
                    }
                    document.getElementById('pieLegend').innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No sit-in data available for this period.</p>';
                    return;
                }
                
                // Find most and lowest used for legend badges
                const maxVal = Math.max(...values);
                let minVal = Math.min(...values.filter(v => v > 0));
                if (minVal === Infinity) minVal = 0;
                
                // Update existing chart or create new one
                if (pieChart) {
                    // Update existing chart data for smooth transition
                    pieChart.data.labels = labels;
                    pieChart.data.datasets[0].data = values;
                    
                    // Trigger update with a more dramatic morphing animation
                    pieChart.update({
                        duration: 1200,
                        easing: 'easeInOutBack'
                    });
                } else {
                    // Create new pie chart if it doesn't exist
                    const ctx = document.getElementById('pieChart').getContext('2d');
                    pieChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: values,
                                backgroundColor: colors,
                                hoverBackgroundColor: colors,
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverBorderColor: '#ffffff',
                                hoverBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            layout: {
                                padding: 10
                            },
                            animation: {
                                animateRotate: true,
                                animateScale: true,
                                duration: 1500,
                                easing: 'easeOutQuart'
                            },
                            hover: {
                                mode: 'nearest',
                                intersect: true
                            },
                            elements: {
                                arc: {
                                    hoverOffset: 0,
                                    borderJoinStyle: 'round'
                                }
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    enabled: true,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            let value = context.raw || 0;
                                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                            return label + ': ' + value + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Update custom legend instantly
                let legendHTML = '<div class="legend-title">Purpose Legend</div><div class="legend-items">';
                labels.forEach((label, index) => {
                    const value = values[index];
                    let badge = '';
                    if (value === maxVal && maxVal > 0) {
                        badge = '<span class="legend-badge highest">Highest</span>';
                    } else if (value === minVal && minVal > 0 && value !== maxVal) {
                        badge = '<span class="legend-badge lowest">Lowest</span>';
                    }
                    
                    legendHTML += '<div class="legend-item" style="animation-delay: ' + (index * 0.05) + 's">' +
                        '<span class="legend-color" style="background-color: ' + colors[index] + '"></span>' +
                        '<span class="legend-label">' + label + ': ' + value + '</span>' +
                        badge +
                        '</div>';
                });
                legendHTML += '</div>';
                legendContainer.innerHTML = legendHTML;
                legendContainer.style.opacity = '1';
            })
            .catch(error => {
                console.error('Error fetching data:', error);
                const legendContainer = document.getElementById('pieLegend');
                if (legendContainer) {
                    legendContainer.innerHTML = '<p style="text-align: center; color: #f44336; padding: 20px;">Failed to load statistics. Please try again.</p>';
                    legendContainer.style.opacity = '1';
                }
            });
    }
    
    // Initialize pie chart with default data (today)
    // Initialize pie chart with PHP pre-fetched data
    function initPieChart() {
        // Initial data from PHP
        const initialLabels = <?php echo $purpose_labels; ?>;
        const initialValues = <?php echo $purpose_json; ?>.map(v => parseInt(v) || 0);
        
        // Find most and lowest used for initial legend
        const maxVal = Math.max(...initialValues);
        const minVal = Math.min(...initialValues.filter(v => v > 0)) || 0;
        
        const ctx = document.getElementById('pieChart').getContext('2d');
        pieChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: initialLabels,
                datasets: [{
                    data: initialValues,
                    backgroundColor: colors,
                    hoverBackgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverBorderColor: '#ffffff',
                    hoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: {
                    padding: 10
                },
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeOutBack'
                },
                elements: {
                    arc: {
                        hoverOffset: 0
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let value = context.raw || 0;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
        
        // Update legend with initial data
        const legendContainer = document.getElementById('pieLegend');
        let legendHTML = '<div class="legend-title">Purpose Legend</div><div class="legend-items">';
        initialLabels.forEach((label, index) => {
            const value = initialValues[index];
            let badge = '';
            if (value === maxVal && maxVal > 0) badge = '<span class="legend-badge highest">Highest</span>';
            else if (value === minVal && minVal > 0 && value !== maxVal) badge = '<span class="legend-badge lowest">Lowest</span>';
            
            legendHTML += `<div class="legend-item" style="animation-delay: ${index * 0.1}s">
                <span class="legend-color" style="background-color: ${colors[index]}"></span>
                <span class="legend-label">${label}: ${value}</span>
                ${badge}
            </div>`;
        });
        legendHTML += '</div>';
        legendContainer.innerHTML = legendHTML;
    }
    
    // Initialize on page load
    initPieChart();
</script>

<!-- ANNOUNCEMENTS MODAL -->
<div class="modal-overlay" id="announcementsModal">
    <div class="modal-box modern-header-modal" style="width: 550px; max-height: 85vh; overflow-y: hidden; display: flex; flex-direction: column;">
        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">📢</span>
                <h2>All Announcements</h2>
            </div>
            <span class="close-btn" id="closeAnnouncements">&times;</span>
        </div>
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 25px;">
            <?php
            include '../includes/connect.php';
            $stmt = $conn->query("SELECT id, admin_name, announcement_date, message, created_at FROM announcements ORDER BY announcement_date DESC, created_at DESC");
            if($stmt->num_rows > 0):
                while($row = $stmt->fetch_assoc()): ?>
                    <div class="announcement-item" style="background: #f8f9fa; padding: 15px; margin-bottom: 15px; border-radius: 8px; border-left: 4px solid #4a90d9;">
                        <div class="announcement-meta" style="font-size: 12px; color: #666; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($row['admin_name']); ?> | <?php echo date('Y-M-d', strtotime($row['announcement_date'])); ?>
                        </div>
                        <div class="announcement-text" style="color: #333;">
                            <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                        </div>
                        <div class="announcement-actions" style="margin-top: 10px;">
                            <button class="edit-btn" onclick="openEditAnnouncement(<?php echo $row['id']; ?>, '<?php echo $row['announcement_date']; ?>', '<?php echo htmlspecialchars(addslashes($row['message'])); ?>')">✏️ Edit</button>
                            <button class="delete-btn" onclick="confirmDeleteAnnouncement(<?php echo $row['id']; ?>)">🗑️ Delete</button>
                        </div>
                    </div>
                <?php endwhile; else: ?>
                    <p style="text-align: center; color: #666;">No announcements yet.</p>
                <?php endif; ?>
        </div>
    </div>
</div>



<!-- Edit Announcement Modal -->
<div class="modal-overlay" id="editAnnouncementModal">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Announcement</h2>
            <span class="close-btn" onclick="closeEditAnnouncement()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <input type="hidden" name="edit_announcement" value="1">
                <input type="hidden" id="editAnnId" name="announcement_id" value="">
                <div class="form-group">
                    <label for="editAnnDate">Date</label>
                    <input type="date" id="editAnnDate" name="edit_date" autocomplete="off" required>
                </div>
                <div class="form-group">
                    <label for="editAnnMessage">Message</label>
                    <textarea id="editAnnMessage" name="edit_message" rows="4" autocomplete="off" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</div>

<!-- Manage Labs Modal -->
<div class="modal-overlay" id="manageLabsModal">
    <div class="modal-box manage-labs-modal modern-header-modal">
        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">🖥️</span>
                <h2>Manage Labs</h2>
            </div>
            <span class="close-btn" id="closeManageLabs">&times;</span>
        </div>
        <div class="modal-body">
            <div class="lab-selection-info">
                <p>Select a laboratory to manage its resources, view active sessions, and handle configurations.</p>
            </div>
            <form id="manageLabsForm">
                <div class="form-group">
                    <label for="labSelect"><span class="input-icon">📍</span> Select Laboratory</label>
                    <select name="lab_select" id="labSelect">
                        <option value="">-- Choose a Lab --</option>
                        <option value="524">Lab 524</option>
                        <option value="526">Lab 526</option>
                        <option value="528">Lab 528</option>
                        <option value="530">Lab 530</option>
                        <option value="544">Lab 544</option>
                        <option value="542">Lab 542</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-close" id="closeManageLabs2">Cancel</button>
                    <button type="button" class="btn-submit" id="manageLabBtn">
                        <span>⚙️</span> Manage Lab
                    </button>
                </div>
            </form>
            <!-- Lab Computer Section - Moved outside form -->
            <div id="labComputerSection" class="lab-computer-section" style="display: none;">
                <div class="lab-computer-header">
                    <h3>Computers in <span id="currentLabDisplay">Lab</span></h3>
                    <p class="computer-instructions">Click on a computer to toggle its availability status</p>
                </div>
                <div class="computer-legend">
                    <span class="legend-item"><span class="computer-unit available" style="width: 20px; height: 20px; display: inline-block; border-radius: 4px;"></span> Available</span>
                    <span class="legend-item"><span class="computer-unit unavailable" style="width: 20px; height: 20px; display: inline-block; border-radius: 4px;"></span> Unavailable</span>
                </div>
                <div class="computer-grid" id="labComputersGrid"></div>
                <div class="modal-actions" style="margin-top: 20px;">
                    <button type="button" class="btn-submit" id="saveLabBtn">
                        <span>💾</span> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Modal -->
<div class="modal-overlay" id="analyticsModal">
    <div class="modal-box analytics-modal modern-header-modal">
        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">📊</span>
                <h2>System Analytics</h2>
            </div>
            <span class="close-btn" id="closeAnalytics">&times;</span>
        </div>
        <div class="modal-body">
            <div class="analytics-header">
                <p class="analytics-subtitle">Real-time usage and activity metrics</p>
            </div>

            <!-- Analytics Summary Cards -->
            <div class="analytics-summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Total Students</div>
                    <div class="summary-value" id="ana-total-students">0</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Sit-ins</div>
                    <div class="summary-value" id="ana-total-sitin">0</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Reservations</div>
                    <div class="summary-value" id="ana-total-res">0</div>
                </div>
            </div>

            <div class="analytics-charts-grid">
                <!-- Activity Trend -->
                <div class="ana-chart-card">
                    <h3>📈 Weekly Activity Trend</h3>
                    <div class="ana-chart-container">
                        <canvas id="activityTrendChart"></canvas>
                    </div>
                </div>

                <!-- Peak Hours -->
                <div class="ana-chart-card">
                    <h3>🕒 Peak Usage Hours</h3>
                    <div class="ana-chart-container">
                        <canvas id="peakHoursChart"></canvas>
                    </div>
                </div>

                <!-- Lab Distribution -->
                <div class="ana-chart-card">
                    <h3>🏢 Lab Usage Distribution</h3>
                    <div class="ana-chart-container">
                        <canvas id="labUsageChart"></canvas>
                    </div>
                </div>

                <!-- Top Students -->
                <div class="ana-chart-card">
                    <h3>🏆 Leaderboard</h3>
                    <div class="top-students-list" id="topStudentsList">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manage Software Modal -->
<div class="modal-overlay" id="manageSoftwareModal">
    <div class="modal-box manage-software-modal modern-header-modal">
        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">💿</span>
                <h2>Manage Software</h2>
            </div>
            <span class="close-btn" id="closeManageSoftware">&times;</span>
        </div>
        <div class="modal-body">
            <div class="lab-selection-info">
                <p>Select a laboratory to manage its installed software.</p>
            </div>
            <form id="manageSoftwareForm">
                <div class="form-group">
                    <label for="softwareLabSelect"><span class="input-icon">📍</span> Select Laboratory</label>
                    <select name="lab_select" id="softwareLabSelect">
                        <option value="">-- Choose a Lab --</option>
                        <option value="524">Lab 524</option>
                        <option value="526">Lab 526</option>
                        <option value="528">Lab 528</option>
                        <option value="530">Lab 530</option>
                        <option value="544">Lab 544</option>
                        <option value="542">Lab 542</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-close" id="closeManageSoftware2">Cancel</button>
                    <button type="button" class="btn-submit" id="manageSoftwareBtn">
                        <span>⚙️</span> View Software
                    </button>
                </div>
            </form>
            
            <div id="labSoftwareSection" class="lab-computer-section" style="display: none;">
                <div class="lab-computer-header">
                    <h3>Software in <span id="currentSoftwareLabDisplay">Lab</span></h3>
                    <p class="computer-instructions">Manage installed software categorized by their usage.</p>
                </div>
                <div class="software-list-container" id="labSoftwareList"></div>
                <div class="add-software-form">
                    <h4>Add New Software</h4>
                    <div class="software-input-group">
                        <input type="text" id="newSoftwareName" placeholder="Software Name">
                        <select id="newSoftwareCategory">
                            <option value="Web Browser">Web Browser</option>
                            <option value="Programming & IDEs">Programming & IDEs</option>
                            <option value="Database Management">Database Management</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Office Productivity">Office Productivity</option>
                            <option value="Network & Security">Network & Security</option>
                            <option value="Mobile Development">Mobile Development</option>
                            <option value="Design & Multimedia">Design & Multimedia</option>
                            <option value="Utility">Utility</option>
                        </select>
                        <button type="button" class="btn-submit" id="addSoftwareBtn">Add Software</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Unified Modern Modal Design for All Quick Action Modals */
.modern-header-modal {
    border-radius: 16px;
    overflow: hidden;
}

.modern-header-modal .modal-header {
    background: linear-gradient(135deg, #0f5bbe 0%, #1a73e8 100%);
    color: white;
    padding: 24px 30px;
    border-bottom: none;
    flex-shrink: 0;
}

.modern-header-modal .modal-title-with-icon {
    display: flex;
    align-items: center;
    gap: 16px;
}

.modern-header-modal .modal-title-with-icon h2 {
    margin: 0;
    font-size: 22px;
    color: white;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.modern-header-modal .modal-icon {
    font-size: 24px;
    background: rgba(255, 255, 255, 0.15);
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.modern-header-modal .close-btn {
    color: rgba(255, 255, 255, 0.7);
    font-size: 28px;
    transition: all 0.3s ease;
    text-shadow: none;
}

.modern-header-modal .close-btn:hover {
    color: white;
    transform: scale(1.1);
}

.lab-computer-section {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 2px dashed #e0e0e0;
}

.lab-computer-header {
    margin-bottom: 15px;
}

.lab-computer-header h3 {
    font-size: 18px;
    color: #1a3a5f;
    margin: 0 0 8px 0;
}

.computer-instructions {
    font-size: 13px;
    color: #888;
    margin: 0;
}

.lab-computer-section .computer-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    justify-content: center;
}

.lab-computer-section .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
}

.lab-computer-section .computer-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    grid-template-rows: repeat(10, 1fr);
    grid-auto-flow: column;
    gap: 8px;
    max-height: 350px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}

.lab-computer-section .computer-unit {
    width: 100%;
    aspect-ratio: 1;
    min-width: 40px;
    max-width: 55px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    margin: 0 auto;
}

.lab-computer-section .computer-unit.available {
    background: linear-gradient(135deg, #4caf50 0%, #43a047 100%);
    color: white;
}

.lab-computer-section .computer-unit.available:hover {
    background: linear-gradient(135deg, #43a047 0%, #388e3c 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(76, 175, 80, 0.4);
}

.lab-computer-section .computer-unit.unavailable {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
}

.lab-computer-section .computer-unit.unavailable:hover {
    background: linear-gradient(135deg, #c62828 0%, #b71c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(229, 57, 53, 0.4);
}

/* Modern Manage Software UI */
.manage-software-modal {
    width: 600px !important;
    max-width: 95%;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.manage-software-modal .modal-body {
    padding: 30px;
    background: #f8fafc;
    overflow-y: auto;
    flex: 1;
}

.manage-software-modal .lab-selection-info {
    text-align: center;
    margin-bottom: 25px;
}

.manage-software-modal .lab-selection-info p {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}

.manage-software-modal .form-group {
    background: white;
    padding: 24px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    margin-bottom: 25px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.manage-software-modal .form-group:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
    border-color: #cbd5e1;
}

.manage-software-modal .form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 16px;
    font-size: 15px;
}

.manage-software-modal select#softwareLabSelect {
    width: 100%;
    padding: 16px 20px;
    font-size: 16px;
    font-weight: 500;
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    background-color: #fcfcfc;
    color: #334155;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 20px center;
    background-size: 18px;
}

.manage-software-modal select#softwareLabSelect:focus {
    border-color: #0f5bbe;
    box-shadow: 0 0 0 4px rgba(15, 91, 190, 0.1);
    outline: none;
    background-color: #ffffff;
}

.manage-software-modal select#softwareLabSelect:hover {
    border-color: #94a3b8;
}

.manage-software-modal .modal-actions {
    display: flex;
    gap: 16px;
    border-top: 1px solid #e2e8f0;
    padding-top: 24px;
    margin-top: 10px;
}

.manage-software-modal .modal-actions button {
    flex: 1;
    padding: 14px;
    font-size: 15px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.manage-software-modal .btn-close {
    background: #f1f5f9;
    color: #475569;
    font-weight: 600;
    border: 1px solid #cbd5e1;
}

.manage-software-modal .btn-close:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.manage-software-modal .btn-submit {
    background: linear-gradient(135deg, #0f5bbe 0%, #1976D2 100%);
    box-shadow: 0 4px 12px rgba(15, 91, 190, 0.25);
}

.manage-software-modal .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(15, 91, 190, 0.35);
}

#labSoftwareSection {
    background: white;
    padding: 24px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    margin-top: 25px;
}

.software-list-container {
    margin-bottom: 25px;
}

.software-category-group {
    margin-bottom: 20px;
    animation: fadeIn 0.4s ease;
}

.software-category-group .category-title {
    font-size: 13px;
    font-weight: 700;
    color: #0f5bbe;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.software-category-group .category-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #eef2f7;
}

.software-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.software-tag {
    background: #ffffff;
    color: #334155;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.software-tag:hover {
    border-color: #0f5bbe;
    background: #f8fafc;
    color: #0f5bbe;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 91, 190, 0.1);
}

.delete-sw {
    cursor: pointer;
    font-size: 10px;
    color: #ef4444;
    opacity: 0.6;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #fef2f2;
}

.delete-sw:hover {
    opacity: 1;
    background: #ef4444;
    color: white;
    transform: scale(1.15) rotate(90deg);
}

.add-software-form {
    background: #f8fafc;
    padding: 24px;
    border-radius: 16px;
    border: 1px dashed #cbd5e1;
    margin-top: 25px;
    transition: all 0.3s ease;
}

.add-software-form:hover {
    border-color: #94a3b8;
    background: #f1f5f9;
}

.add-software-form h4 {
    margin: 0 0 16px 0;
    color: #0f5bbe;
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.add-software-form h4::before {
    content: '➕';
    font-size: 14px;
}

.software-input-group {
    display: flex;
    gap: 12px;
    align-items: stretch;
    flex-wrap: wrap;
}

.software-input-group input,
.software-input-group select {
    flex: 1;
    min-width: 160px;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
    color: #334155;
}

.software-input-group input:focus,
.software-input-group select:focus {
    outline: none;
    border-color: #0f5bbe;
    box-shadow: 0 0 0 4px rgba(15, 91, 190, 0.1);
}

.software-input-group input::placeholder {
    color: #94a3b8;
}

.software-input-group .btn-submit {
    padding: 12px 24px;
    white-space: nowrap;
    border-radius: 10px;
    background: linear-gradient(135deg, #0f5bbe 0%, #1976D2 100%);
    color: white;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 12px rgba(15, 91, 190, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
}

.software-input-group .btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(15, 91, 190, 0.3);
    background: linear-gradient(135deg, #1976D2 0%, #0f5bbe 100%);
}

.software-input-group .btn-submit:active {
    transform: translateY(0);
}

/* Custom scrollbar for modal body */
.manage-software-modal .modal-body {
    padding-right: 12px;
}

.manage-software-modal .modal-body::-webkit-scrollbar {
    width: 8px;
}

.manage-software-modal .modal-body::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 8px;
}

.manage-software-modal .modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
    border: 2px solid #f1f5f9;
}

.manage-software-modal .modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* ============================= */
/* ANALYTICS MODAL STYLING       */
/* ============================= */

.analytics-modal {
    width: 1000px !important;
    max-width: 95%;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}

.analytics-modal .modal-body {
    padding: 30px;
    background: #f8fafc;
    overflow-y: auto;
    flex: 1;
}

.analytics-modal .analytics-header {
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.analytics-modal .analytics-subtitle {
    color: #64748b;
    font-size: 15px;
    margin: 0;
}

.analytics-modal .refresh-btn {
    background: #0f5bbe;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(15, 91, 190, 0.2);
}

.analytics-modal .refresh-btn:hover {
    background: #0d4fa1;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(15, 91, 190, 0.3);
}

.analytics-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.analytics-modal .summary-item {
    background: white;
    padding: 24px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    transition: transform 0.3s ease;
}

.analytics-modal .summary-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0,0,0,0.08);
}

.analytics-modal .summary-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.analytics-modal .summary-value {
    font-size: 32px;
    font-weight: 800;
    color: #0f5bbe;
}

.analytics-charts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
}

.ana-chart-card {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.ana-chart-card h3 {
    font-size: 16px;
    color: #1e293b;
    margin: 0 0 20px 0;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ana-chart-container {
    height: 250px;
    position: relative;
}

.top-students-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 250px;
    overflow-y: auto;
    padding-right: 10px;
}

.top-student-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 12px;
    border-left: 4px solid #0f5bbe;
    transition: all 0.2s ease;
}

.top-student-item:hover {
    background: #f1f5f9;
    transform: translateX(5px);
}

.analytics-modal .student-info {
    font-weight: 600;
    color: #334155;
    font-size: 14px;
}

.analytics-modal .student-count {
    background: #0f5bbe;
    color: #fff;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.analytics-modal .modal-body::-webkit-scrollbar,
.top-students-list::-webkit-scrollbar {
    width: 6px;
}

.analytics-modal .modal-body::-webkit-scrollbar-track,
.top-students-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 8px;
}

.analytics-modal .modal-body::-webkit-scrollbar-thumb,
.top-students-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 8px;
}

.analytics-modal .modal-body::-webkit-scrollbar-thumb:hover,
.top-students-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Announcement success alert (Floating Toast at Top) */
.announcement-success-alert {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%) translateY(0);
    z-index: 10000;
    background: #e6f4ea;
    border: 1px solid #34a853;
    color: #137333;
    border-radius: 12px;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
    font-weight: 600;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15), 0 1px 3px rgba(0,0,0,0.05);
    animation: toastSlideDown 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes toastSlideDown {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-40px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

.announcement-success-alert .alert-icon {
    font-size: 18px;
    background: #34a853;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}
</style>

<!-- REPORTS MODAL -->
<div class="modal-overlay" id="reportsModal">
    <div class="modal-box report-modal modern-header-modal">
        <div class="modal-header">
            <div class="modal-title-with-icon">
                <span class="modal-icon">📊</span>
                <h2>Generate Reports</h2>
            </div>
            <span class="close-btn" id="closeReports">&times;</span>
        </div>
        <div class="modal-body">
            <div class="report-options">
                <div class="report-option-card" onclick="generatePDF('students')">
                    <div class="option-icon">👥</div>
                    <div class="option-details">
                        <h3>Students List</h3>
                        <p>Generate a complete list of all registered students.</p>
                    </div>
                    <div class="option-action">
                        <span>Download PDF</span>
                    </div>
                </div>
                
                <div class="report-option-card" onclick="generatePDF('sitin')">
                    <div class="option-icon">💻</div>
                    <div class="option-details">
                        <h3>Sit-in Logs</h3>
                        <p>Export all historical sit-in activity logs.</p>
                    </div>
                    <div class="option-action">
                        <span>Download PDF</span>
                    </div>
                </div>
                
                <div class="report-option-card" onclick="generatePDF('reservations')">
                    <div class="option-icon">📅</div>
                    <div class="option-details">
                        <h3>Reservations</h3>
                        <p>Generate a report of all student laboratory reservations.</p>
                    </div>
                    <div class="option-action">
                        <span>Download PDF</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.report-modal {
    width: 650px !important;
    max-width: 90%;
}

.report-options {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
    padding: 10px 0;
}

.report-option-card {
    display: flex;
    align-items: center;
    background: #ffffff;
    border: 1px solid #eef2f7;
    border-radius: 16px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    gap: 20px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.report-option-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: #0f5bbe;
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.report-option-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border-color: #dbeafe;
}

.report-option-card:hover::before {
    transform: scaleY(1);
}

.option-icon {
    font-size: 32px;
    background: #f0f7ff;
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    color: #0f5bbe;
    transition: all 0.3s ease;
}

.report-option-card:hover .option-icon {
    background: #0f5bbe;
    color: #ffffff;
    transform: scale(1.1);
}

.option-details {
    flex: 1;
}

.option-details h3 {
    margin: 0 0 6px 0;
    color: #1e293b;
    font-size: 19px;
    font-weight: 700;
}

.option-details p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.5;
}

.option-action {
    color: #0f5bbe;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #eff6ff;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.report-option-card:hover .option-action {
    background: #0f5bbe;
    color: #ffffff;
}

.option-action i {
    font-style: normal;
}
</style>

<!-- PDF Generation Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Auto-hide announcement success alert after 3 seconds
    const successAlert = document.querySelector('.announcement-success-alert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.opacity = '0';
            successAlert.style.transform = 'translateX(-50%) translateY(-40px)';
            setTimeout(() => {
                successAlert.remove();
            }, 400);
        }, 3000);
        
        // Clean URL query params so refreshes don't show the alert again
        if (window.history.replaceState) {
            const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            window.history.replaceState({path: cleanUrl}, '', cleanUrl);
        }
    }

    // Modal Config Mapping
    const modalConfig = {
        sitIn: {
            overlay: document.getElementById("sitInModal"),
            open: document.getElementById("openSitIn"),
            close: ["closeSitIn", "closeSitIn2"]
        },
        announcements: {
            overlay: document.getElementById("announcementsModal"),
            open: document.getElementById("openAnnouncements"),
            close: ["closeAnnouncements"]
        },
        reports: {
            overlay: document.getElementById("reportsModal"),
            open: document.getElementById("openReports"),
            close: ["closeReports"]
        },
        manageLabs: {
            overlay: document.getElementById("manageLabsModal"),
            open: document.getElementById("openManageLabs"),
            close: ["closeManageLabs", "closeManageLabs2"],
            onClose: resetManageLabsForm
        },
        manageSoftware: {
            overlay: document.getElementById("manageSoftwareModal"),
            open: document.getElementById("openManageSoftware"),
            close: ["closeManageSoftware", "closeManageSoftware2"],
            onClose: resetManageSoftwareForm
        },
        analytics: {
            overlay: document.getElementById("analyticsModal"),
            open: document.getElementById("openAnalytics"),
            close: ["closeAnalytics"],
            onOpen: fetchAnalytics
        },
        editStudent: {
            overlay: document.getElementById("editModal"),
            close: [document.querySelector("#editModal .close-btn")]
        },
        editAnnouncement: {
            overlay: document.getElementById("editAnnouncementModal"),
            close: [document.querySelector("#editAnnouncementModal .close-btn")]
        }
    };

    // Initialize Modal Logic
    Object.keys(modalConfig).forEach(key => {
        const cfg = modalConfig[key];
        if (cfg.open && cfg.overlay) {
            cfg.open.onclick = () => {
                cfg.overlay.classList.add("active");
                if (cfg.onOpen) cfg.onOpen();
                if (key === 'sitIn') setTimeout(() => document.getElementById("idNumber")?.focus(), 100);
            };
        }
        if (cfg.close && cfg.overlay) {
            cfg.close.forEach(btnRef => {
                const btn = typeof btnRef === 'string' ? document.getElementById(btnRef) : btnRef;
                if (btn) {
                    btn.onclick = () => {
                        cfg.overlay.classList.remove("active");
                        if (cfg.onClose) cfg.onClose();
                    };
                }
            });
        }
    });

    // Outside Click Listener
    window.addEventListener("click", (e) => {
        Object.values(modalConfig).forEach(cfg => {
            if (e.target === cfg.overlay) {
                cfg.overlay.classList.remove("active");
                if (cfg.onClose) cfg.onClose();
            }
        });
    });

    // Manage Labs Features
    const manageLabBtn = document.getElementById("manageLabBtn");
    if (manageLabBtn) {
        manageLabBtn.onclick = (e) => {
            e.preventDefault();
            const labSelect = document.getElementById("labSelect");
            if (labSelect?.value) loadLabComputers(labSelect.value);
            else alert("Please select a lab first");
        };
    }

    // Manage Software Features
    const manageSoftwareBtn = document.getElementById("manageSoftwareBtn");
    if (manageSoftwareBtn) {
        manageSoftwareBtn.onclick = (e) => {
            e.preventDefault();
            const labSelect = document.getElementById("softwareLabSelect");
            if (labSelect?.value) loadLabSoftware(labSelect.value);
            else alert("Please select a lab first");
        };
    }

    const addSoftwareBtn = document.getElementById("addSoftwareBtn");
    if (addSoftwareBtn) {
        addSoftwareBtn.onclick = () => {
            const labRoom = document.getElementById("softwareLabSelect")?.value;
            const nameInput = document.getElementById("newSoftwareName");
            const categorySelect = document.getElementById("newSoftwareCategory");
            if (!labRoom || !nameInput?.value.trim()) return alert("Lab and Software Name are required");
            
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "/SYSARCH/pages/api/add_lab_software.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) { nameInput.value = ""; loadLabSoftware(labRoom); }
                        else alert("Error: " + data.error);
                    } catch(e) { alert("Error adding software"); }
                }
            };
            xhr.send(`lab_room=${encodeURIComponent(labRoom)}&software_name=${encodeURIComponent(nameInput.value.trim())}&category=${encodeURIComponent(categorySelect.value)}`);
        };
    }
});

// Helper Functions
function resetManageLabsForm() {
    const section = document.getElementById("labComputerSection");
    if (section) section.style.display = "none";
    document.getElementById("manageLabsForm")?.reset();
    const grid = document.getElementById("labComputersGrid");
    if (grid) grid.innerHTML = "";
}

function resetManageSoftwareForm() {
    const section = document.getElementById("labSoftwareSection");
    if (section) section.style.display = "none";
    document.getElementById("manageSoftwareForm")?.reset();
    const list = document.getElementById("labSoftwareList");
    if (list) list.innerHTML = "";
}

function loadLabComputers(labRoom) {
    const display = document.getElementById("currentLabDisplay");
    const grid = document.getElementById("labComputersGrid");
    const section = document.getElementById("labComputerSection");
    if (display) display.textContent = "Lab " + labRoom;
    if (grid) grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: #666;">Loading...</div>';
    if (section) section.style.display = "block";

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/SYSARCH/pages/api/get_lab_computers.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.computers) displayComputers(data.computers, labRoom);
            } catch(e) { grid.innerHTML = "Error parsing data"; }
        }
    };
    xhr.send("lab_room=" + labRoom);
}

function displayComputers(computers, labRoom) {
    const grid = document.getElementById("labComputersGrid");
    if (!grid) return;
    grid.innerHTML = "";
    
    // Match student reservation snake arrangement logic
    const rowsPerCol = 10;
    const totalCols = Math.ceil(computers.length / rowsPerCol);
    const arranged = [];
    
    // Iterate from last column to first (Right-to-Left arrangement)
    for (let col = totalCols - 1; col >= 0; col--) {
        const start = col * rowsPerCol;
        const end = Math.min(start + rowsPerCol, computers.length);
        const colData = computers.slice(start, end);
        
        // Flip every other column to create the snake pattern
        if ((totalCols - 1 - col) % 2 === 0) {
            arranged.push(...colData);
        } else {
            arranged.push(...[...colData].reverse());
        }
    }
    
    arranged.forEach(comp => {
        const isAvailable = (comp.status || comp.admin_status || "").toLowerCase() === "available";
        const unit = document.createElement("div");
        unit.className = `computer-unit ${isAvailable ? 'available' : 'unavailable'}`;
        unit.textContent = comp.computer_number;
        unit.onclick = () => toggleComputerStatus(unit, comp.id, comp.status || comp.admin_status, labRoom);
        grid.appendChild(unit);
    });
}

function toggleComputerStatus(element, id, currentStatus, labRoom) {
    const nextStatus = currentStatus.toLowerCase() === 'available' ? 'unavailable' : 'available';
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/SYSARCH/pages/api/update_computer_status.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        if (xhr.status === 200) {
            const data = JSON.parse(xhr.responseText);
            if (data.success) {
                element.className = `computer-unit ${nextStatus}`;
                // Update in-memory status for next click
                element.onclick = () => toggleComputerStatus(element, id, nextStatus, labRoom);
            }
        }
    };
    xhr.send(`computer_id=${id}&status=${nextStatus}&lab_room=${labRoom}`);
}

function loadLabSoftware(labRoom) {
    const display = document.getElementById("currentSoftwareLabDisplay");
    const section = document.getElementById("labSoftwareSection");
    const list = document.getElementById("labSoftwareList");
    if (display) display.textContent = labRoom;
    if (section) section.style.display = "block";
    if (list) list.innerHTML = "Loading...";

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/SYSARCH/pages/api/get_lab_software.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                displaySoftware(data.software || [], labRoom);
            } catch(e) { list.innerHTML = "Error"; }
        }
    };
    xhr.send("lab_room=" + labRoom);
}

function displaySoftware(softwareList, labRoom) {
    const list = document.getElementById("labSoftwareList");
    if (!list) return;
    list.innerHTML = softwareList.length ? "" : "No software found.";
    const grouped = {};
    softwareList.forEach(sw => {
        if (!grouped[sw.category]) grouped[sw.category] = [];
        grouped[sw.category].push(sw);
    });

    for (const category in grouped) {
        const group = document.createElement("div");
        group.className = "software-category-group";
        group.innerHTML = `<div class="category-title">${category}</div>`;
        const tagList = document.createElement("div");
        tagList.className = "software-list";
        grouped[category].forEach(sw => {
            const tag = document.createElement("span");
            tag.className = "software-tag";
            tag.innerHTML = `${sw.software_name} <span class="delete-sw" onclick="deleteSoftware(${sw.id}, '${labRoom}')">✕</span>`;
            tagList.appendChild(tag);
        });
        group.appendChild(tagList);
        list.appendChild(group);
    }
}

function deleteSoftware(id, labRoom) {
    if (!confirm("Remove this software?")) return;
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/SYSARCH/pages/api/delete_lab_software.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() { loadLabSoftware(labRoom); };
    xhr.send("id=" + id);
}

function openEditAnnouncement(id, date, message) {
    const modal = document.getElementById('editAnnouncementModal');
    if (modal) {
        document.getElementById('editAnnId').value = id;
        document.getElementById('editAnnDate').value = date;
        document.getElementById('editAnnMessage').value = message;
        modal.classList.add('active');
    }
}

function confirmDeleteAnnouncement(id) {
    if(!confirm('Delete this announcement?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `<input type="hidden" name="delete_announcement" value="1"><input type="hidden" name="announcement_id" value="${id}">`;
    document.body.appendChild(form);
    form.submit();
}

function fetchStudentInfo() {
    const id = document.getElementById('idNumber')?.value;
    if (!id) return;
    fetch('admin_dashboard.php?id_lookup=' + encodeURIComponent(id))
    .then(r => r.text())
    .then(html => {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const data = doc.getElementById('student-data');
        if (data) {
            const nameInput = document.getElementById('studentName');
            if (nameInput) nameInput.value = data.getAttribute('data-name') || '';
        }
    });
}

function openEditModal() { document.getElementById("editModal")?.classList.add("active"); }
function closeEditModal() { document.getElementById("editModal")?.classList.remove("active"); }

// Charts and Analytics
let analyticsCharts = {};
async function fetchAnalytics() {
    try {
        const r = await fetch('/SYSARCH/pages/api/get_analytics_data.php');
        const d = await r.json();
        document.getElementById('ana-total-students').textContent = d.counts.students;
        document.getElementById('ana-total-sitin').textContent = d.counts.sitin;
        document.getElementById('ana-total-res').textContent = d.counts.reservations;
        updateActivityChart(d.activity_trends);
        updatePeakHoursChart(d.peak_hours);
        updateLabUsageChart(d.lab_usage);
        updateTopStudentsList(d.top_students);
    } catch(e) {}
}

function updateActivityChart(data) {
    const ctx = document.getElementById('activityTrendChart')?.getContext('2d');
    if (!ctx) return;
    if (analyticsCharts.activity) analyticsCharts.activity.destroy();
    analyticsCharts.activity = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.date),
            datasets: [{ data: data.map(d => d.count), borderColor: '#0f5bbe', fill: true, backgroundColor: 'rgba(15, 91, 190, 0.1)' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

function updatePeakHoursChart(data) {
    const ctx = document.getElementById('peakHoursChart')?.getContext('2d');
    if (!ctx) return;
    if (analyticsCharts.peak) analyticsCharts.peak.destroy();
    analyticsCharts.peak = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Array.from({length: 24}, (_, i) => `${i % 12 || 12}${i < 12 ? 'AM' : 'PM'}`),
            datasets: [{ data: data, backgroundColor: '#0f5bbe' }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });
}

function updateLabUsageChart(data) {
    const ctx = document.getElementById('labUsageChart')?.getContext('2d');
    if (!ctx) return;
    if (analyticsCharts.lab) analyticsCharts.lab.destroy();
    analyticsCharts.lab = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => 'Lab ' + d.lab),
            datasets: [{ data: data.map(d => d.count), backgroundColor: ['#0f5bbe', '#1976D2', '#4caf50', '#ff9800', '#f44336'] }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
}

function updateTopStudentsList(students) {
    const list = document.getElementById('topStudentsList');
    if (!list) return;
    list.innerHTML = students.length ? "" : "No data.";
    students.forEach(s => {
        const item = document.createElement('div');
        item.className = 'top-student-item';
        item.innerHTML = `<span>${s.student_name}</span><span style="font-weight:bold; color:#ff9800;">${s.points} pts</span>`;
        list.appendChild(item);
    });
}

async function generatePDF(type) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF((type === 'sitin' || type === 'reservations') ? 'l' : 'p', 'mm', 'a4');
    const r = await fetch(`/SYSARCH/pages/api/get_report_data.php?type=${type}`);
    const data = await r.json();
    if (data.error || !data.length) return alert("No data.");
    
    let title = type === 'students' ? "Students" : type === 'sitin' ? "Sit-in Logs" : "Reservations";
    let columns = type === 'students' ? ["ID", "Name", "Course", "Year"] : ["ID", "Name", "Lab", "Date", "Status"];
    let rows = data.map(i => type === 'students' ? [i.id_number, i.last_name, i.course, i.year_level] : [i.id_number, i.student_name, i.lab || i.lab_room, i.sit_in_date || i.reservation_date, i.status]);

    doc.autoTable({
        head: [columns],
        body: rows,
        startY: 20,
        didDrawPage: (d) => { doc.text(title, 15, 15); }
    });
    doc.save(`${type}_report.pdf`);
}

// Function removed to avoid conflict with the main Purpose Statistics chart logic
</script>







</body>
</html>
