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
    remaining_session INT NOT NULL,
    sit_in_date DATE NOT NULL,
    sit_in_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Add sessions column to students table if not exists
$check_column = $conn->query("SHOW COLUMNS FROM students LIKE 'sessions'");
if($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN sessions INT DEFAULT 30");
}

// Handle Sit-in Form Submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_number'])) {
    $id_number = $_POST['id_number'];
    $student_name = $_POST['student_name'];
    $purpose = $_POST['purpose'];
    $lab = $_POST['lab'];
    $remaining_session = $_POST['remaining_session'];
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
            // Insert sit-in record without deducting session (session will be deducted on logout)
            $stmt = $conn->prepare("INSERT INTO sit_in (id_number, student_name, purpose, lab, remaining_session, sit_in_date, sit_in_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->bind_param("ssssiss", $id_number, $db_student_name, $purpose, $lab, $student_sessions, $sit_in_date, $sit_in_time);
            
            if($stmt->execute()) {
                // Redirect to prevent double submission
                header("Location: /SYSARCH/admin_dashboard.php");
                exit;
            } else {
                $sit_in_error = $stmt->error;
            }
            $stmt->close();
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
        header("Location: /SYSARCH/admin_dashboard.php?success=announcement");
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
        header("Location: /SYSARCH/admin_dashboard.php?success=deleted");
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
        header("Location: /SYSARCH/admin_dashboard.php?success=updated");
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

// Get purpose statistics from sit_in table
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

$stmt = $conn->prepare("SELECT purpose, COUNT(*) as count FROM sit_in GROUP BY purpose");
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
        <li><a href="#">Settings</a></li>
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
                <option value="today" selected>Today</option>
                <option value="week">This Week</option>
                <option value="month">This Month</option>
                <option value="year">This Year</option>
            </select>
        </div>
        <div class="card-body">
            <div class="pie-chart-container">
                <div class="pie-chart-wrapper">
                    <canvas id="pieChart"></canvas>
                </div>
                <div class="pie-chart-legend" id="pieLegend"></div>
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
                    <label>ID Number</label>
                    <input type="text" placeholder="Student ID" readonly>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Year Level</label>
                    <select>
                        <option>Select Year</option>
                        <option>1st Year</option>
                        <option>2nd Year</option>
                        <option>3rd Year</option>
                        <option>4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Course</label>
                    <input type="text" placeholder="Enter course">
                </div>

                <div class="form-group">
                    <label>Remaining Sessions</label>
                    <input type="number" placeholder="Sessions">
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
                <button class="action-btn" id="openAnnouncements" onclick="document.getElementById('announcementsModal').classList.add('active');">
                    <span class="action-icon">📢</span>
                    <span class="action-text">Announcements</span>
                </button>
                <button class="action-btn">
                    <span class="action-icon">📋</span>
                    <span class="action-text">View Reports</span>
                </button>
                <button class="action-btn">
                    <span class="action-icon">⚙️</span>
                    <span class="action-text">Manage Labs</span>
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
                    <label>Admin Name</label>
                    <input type="text" name="admin_name" value="<?php echo $_SESSION['admin_username']; ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="announcement_date" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="4" placeholder="Enter your announcement..." required></textarea>
                </div>
                <button type="submit" class="submit-btn">Post Announcement</button>
            </form>
        </div>
    </div>
    </div>

</div>

 <!-- SIT-IN FORM -->
<!-- SIT-IN MODAL -->
<div class="modal-overlay" id="sitInModal">

    <div class="modal-box">

        <div class="modal-header">
            <h2>Sit In Form</h2>
            <span class="close-btn" id="closeSitIn">&times;</span>
        </div>

        <div class="modal-body">

            <form method="POST" action="">

                <div class="form-group">
                    <label>ID Number:</label>
                    <input type="text" name="id_number" id="idNumber" placeholder="Enter ID Number" onblur="fetchStudentInfo()" required>
                </div>

                <div class="form-group">
                    <label>Student Name:</label>
                    <input type="text" name="student_name" id="studentName" placeholder="Auto-filled after entering ID" readonly required>
                </div>

                <div class="form-group">
                    <label>Purpose:</label>
                    <select name="purpose" required>
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
                    <label>Lab:</label>
                    <select name="lab" required>
                        <option value="">Select Lab</option>
                        <option value="524">Lab 524</option>
                        <option value="525">Lab 525</option>
                        <option value="526">Lab 526</option>
                        <option value="527">Lab 527</option>
                        <option value="528">Lab 528</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Remaining Session:</label>
                    <input type="number" name="remaining_session" id="remainingSession" placeholder="Auto-filled after entering ID" readonly required>
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
    const colors = [
        '#0f5bbe', // Blue
        '#1976D2', // Light Blue
        '#4caf50', // Green
        '#ff9800', // Orange
        '#f44336', // Red
        '#9c27b0', // Purple
        '#00bcd4', // Cyan
        '#795548', // Brown
        '#607d8b'  // Gray
    ];
    
    // Function to update pie chart based on time range
    function updatePieChart(timeRange) {
        console.log('Updating pie chart for range:', timeRange);
        
        // Clear previous chart and legend before fetching new data
        const legendContainer = document.getElementById('pieLegend');
        legendContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">Loading...</p>';
        
        // Destroy existing chart to prevent data overlay
        if (pieChart) {
            pieChart.destroy();
            pieChart = null;
        }
        
        // Clear the canvas
        const canvas = document.getElementById('pieChart');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Fetch data via AJAX with cache-busting
        fetch('get_purpose_stats.php?range=' + timeRange + '&t=' + Date.now())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('Error from server:', data.error);
                    return;
                }
                console.log('Purpose stats data:', data);
                const labels = data.labels;
                // Convert all values to numbers to ensure proper calculations
                const values = data.values.map(v => parseInt(v) || 0);
                
                // Check if there's any data
                const totalCount = values.reduce((a, b) => a + b, 0);
                if (totalCount === 0) {
                    console.log('No sit-in data found');
                    document.getElementById('pieLegend').innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No sit-in data available for this period.</p>';
                    return;
                }
                
                // Find most and lowest used
                let maxVal = Math.max(...values);
                let minVal = Math.min(...values.filter(v => v > 0));
                if (minVal === Infinity) minVal = 0;
                
                // Create pie chart
                const ctx = document.getElementById('pieChart').getContext('2d');
                pieChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        let percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        
                                        let indicator = '';
                                        if (value === maxVal && maxVal > 0) {
                                            indicator = ' (Highest)';
                                        } else if (value === minVal && minVal > 0 && value !== maxVal) {
                                            indicator = ' (Lowest)';
                                        }
                                        
                                        return label + ': ' + value + ' (' + percentage + '%)' + indicator;
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Update custom legend
                const legendContainer = document.getElementById('pieLegend');
                let legendHTML = '<div class="legend-title">Purpose Legend</div><div class="legend-items">';
                
                labels.forEach((label, index) => {
                    const value = values[index];
                    let badge = '';
                    if (value === maxVal && maxVal > 0) {
                        badge = '<span class="legend-badge highest">Highest</span>';
                    } else if (value === minVal && minVal > 0 && value !== maxVal) {
                        badge = '<span class="legend-badge lowest">Lowest</span>';
                    }
                    
                    legendHTML += '<div class="legend-item">' +
                        '<span class="legend-color" style="background-color: ' + colors[index] + '"></span>' +
                        '<span class="legend-label">' + label + ': ' + value + '</span>' +
                        badge +
                        '</div>';
                });
                legendHTML += '</div>';
                legendContainer.innerHTML = legendHTML;
            })
            .catch(error => console.error('Error fetching data:', error));
    }
    
    // Initialize pie chart with default data (today)
    function initPieChart() {
        // Get initial time range from selector (default: today)
        const timeRange = document.getElementById('timeRangeSelector').value;
        updatePieChart(timeRange);
    }
    
    // Initialize on page load
    initPieChart();
</script>

<!-- ANNOUNCEMENTS MODAL -->
<div class="modal-overlay" id="announcementsModal">
    <div class="modal-box" style="width: 500px; max-height: 80vh; overflow-y: auto; display: block;">
        <div class="modal-header">
            <h2>All Announcements</h2>
            <span class="close-btn" id="closeAnnouncements">&times;</span>
        </div>
        <div class="modal-body">
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

<script>
const openBtn = document.getElementById("openSitIn");
const modal = document.getElementById("sitInModal");
const closeBtn = document.getElementById("closeSitIn");
const closeBtn2 = document.getElementById("closeSitIn2");

openBtn.onclick = () => {
    modal.classList.add("active");
};

closeBtn.onclick = () => {
    modal.classList.remove("active");
};

closeBtn2.onclick = () => {
    modal.classList.remove("active");
};

// close when clicking outside
window.addEventListener("click", function(e){
    const sitInModal = document.getElementById("sitInModal");
    const editModal = document.getElementById("editModal");
    const announcementsModal = document.getElementById("announcementsModal");

    if(e.target === sitInModal){
        sitInModal.classList.remove("active");
    }

    if(e.target === editModal){
        editModal.classList.remove("active");
    }

    if(e.target === announcementsModal){
        announcementsModal.classList.remove("active");
    }
});

// Announcements Modal
const openAnnouncementsBtn = document.getElementById("openAnnouncements");
const announcementsModal = document.getElementById("announcementsModal");
const closeAnnouncementsBtn = document.getElementById("closeAnnouncements");

if(openAnnouncementsBtn) {
    openAnnouncementsBtn.onclick = () => {
        announcementsModal.classList.add("active");
    };
}

if(closeAnnouncementsBtn) {
    closeAnnouncementsBtn.onclick = () => {
        announcementsModal.classList.remove("active");
    };
}

// Edit and Delete Announcement Functions
function openEditAnnouncement(id, date, message) {
    document.getElementById('editAnnId').value = id;
    document.getElementById('editAnnDate').value = date;
    document.getElementById('editAnnMessage').value = message;
    document.getElementById('editAnnouncementModal').classList.add('active');
}

function closeEditAnnouncement() {
    document.getElementById('editAnnouncementModal').classList.remove('active');
}

function confirmDeleteAnnouncement(id) {
    if(confirm('Are you sure you want to delete this announcement?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="delete_announcement" value="1"><input type="hidden" name="announcement_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Fetch student info when ID Number loses focus
function fetchStudentInfo() {
    const idNumber = document.getElementById('idNumber').value;
    if(idNumber.trim() === '') return;
    
    // Use fetch to get student info from database
    fetch('admin_dashboard.php?id_lookup=' + encodeURIComponent(idNumber))
    .then(response => response.text())
    .then(data => {
        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const studentData = doc.getElementById('student-data');
            
            if(studentData) {
                const studentName = studentData.getAttribute('data-name');
                const studentSessions = studentData.getAttribute('data-sessions');
                const studentId = studentData.getAttribute('data-id');
                
                if(studentId) {
                    document.getElementById('studentName').value = studentName;
                    document.getElementById('remainingSession').value = studentSessions;
                } else {
                    alert('Student not found! Please check the ID Number.');
                    document.getElementById('studentName').value = '';
                    document.getElementById('remainingSession').value = '';
                }
            }
        } catch(e) {
            console.error('Error parsing student data:', e);
        }
    })
    .catch(error => console.error('Error:', error));
}

// EDIT MODAL ELEMENT
const editModal = document.getElementById("editModal");

// OPEN MODAL
function openEditModal(){
    editModal.classList.add("active");
}

// CLOSE MODAL
function closeEditModal(){
    editModal.classList.remove("active");
}

// CLOSE WHEN CLICKING OUTSIDE (SAFE VERSION)
window.addEventListener("click", function(e){
    if(e.target === editModal){
        editModal.classList.remove("active");
    }
});
</script>

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
                    <label>Date</label>
                    <input type="date" id="editAnnDate" name="edit_date" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea id="editAnnMessage" name="edit_message" rows="4" required></textarea>
                </div>
                <button type="submit" class="submit-btn">Save Changes</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
