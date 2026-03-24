<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Handle Reset All Sessions
if(isset($_POST['reset_all_sessions'])) {
    $reset_stmt = $conn->prepare("UPDATE students SET sessions = 30");
    $reset_stmt->execute();
    $reset_stmt->close();
    header("Location: /SYSARCH/manage_students.php?reset_success=1");
    exit;
}

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if($search !== '') {
    // Search by ID number or name
    $search_param = "%$search%";
    $stmt = $conn->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, sessions FROM students WHERE id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ? ORDER BY last_name ASC");
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    // Fetch all students
    $stmt = $conn->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, sessions FROM students ORDER BY last_name ASC");
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students - CCS Sit-in Monitoring System</title>
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
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_students.php" class="active">Manage Students</a></li>
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="#">Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<!-- Main Content -->
<div class="content">
    <div class="header-row">
        <h2 style="text-align: center; width: 100%;">Manage Students</h2>
    </div>
    
    <div class="add-student-row">
        <div class="button-group">
            <button class="add-student-btn" onclick="toggleAddStudentForm()">+ Add Students</button>
            <button class="reset-session-btn" onclick="resetAllSessions()">Reset All Sessions</button>
        </div>
        <form method="GET" action="" class="search-form">
            <input type="text" name="search" placeholder="Search by ID Number or Name..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="search-btn">Search</button>
            <?php if($search !== ''): ?>
                <a href="manage_students.php" class="clear-search-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Floating Add Student Form -->
    <div class="floating-form-overlay" id="addStudentForm" style="display: none;">
        <div class="floating-form-container">
            <div class="floating-form-header">
                <h3>Add New Student</h3>
                <span class="close-form" onclick="toggleAddStudentForm()">&times;</span>
            </div>
            <form action="/SYSARCH/includes/register.php" method="POST">
                <input type="hidden" name="from_admin" value="1">
                <label>ID Number</label>
                <input type="text" name="id_number" placeholder="Enter ID Number" required>

                <label>Last Name</label>
                <input type="text" name="last_name" placeholder="Enter Last Name" required>

                <label>First Name</label>
                <input type="text" name="first_name" placeholder="Enter First Name" required>

                <label>Middle Name</label>
                <input type="text" name="middle_name" placeholder="Optional">

                <label>Course</label>
                <select name="course" required>
                    <option value="">-- Select Course --</option>
                    <option value="BS Accountancy">BS Accountancy</option>
                    <option value="BS Business Administration">BS Business Administration</option>
                    <option value="BS Computer Science">BS Computer Science</option>
                    <option value="BS Information Technology">BS Information Technology</option>
                    <option value="BS Computer Engineering">BS Computer Engineering</option>
                    <option value="BS Criminology">BS Criminology</option>
                    <option value="BS Civil Engineering">BS Civil Engineering</option>
                    <option value="BS Electrical Engineering">BS Electrical Engineering</option>
                    <option value="BS Mechanical Engineering">BS Mechanical Engineering</option>
                    <option value="BS Industrial Engineering">BS Industrial Engineering</option>
                    <option value="BS Commerce">BS Commerce</option>
                    <option value="BS Hotel & Restaurant Management">BS Hotel & Restaurant Management</option>
                    <option value="BS Tourism Management">BS Tourism Management</option>
                    <option value="BS Elementary Education">BS Elementary Education</option>
                    <option value="BS Secondary Education">BS Secondary Education</option>
                    <option value="BS Customs Administration">BS Customs Administration</option>
                    <option value="BS Industrial Psychology">BS Industrial Psychology</option>
                    <option value="BS Real Estate Management">BS Real Estate Management</option>
                    <option value="BS Office Administration">BS Office Administration</option>
                </select>

                <label>Year Level</label>
                <select name="year_level" required>
                    <option value="">-- Select Year --</option>
                    <option>1st Year</option>
                    <option>2nd Year</option>
                    <option>3rd Year</option>
                    <option>4th Year</option>
                </select>

                <label>Email</label>
                <input type="email" name="email" placeholder="example@gmail.com" required>

                <label>Password</label>
                <input type="password" name="password" required>

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>

                <label>Address</label>
                <textarea rows="2" name="address" placeholder="Enter your address" required></textarea>

                <button type="submit" class="submit-btn">Add Student</button>
            </form>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <p id="success-message" style="color: green; margin-bottom: 15px;">✅ Student added successfully!</p>
    <?php endif; ?>

    <?php if(isset($_GET['reset_success'])): ?>
        <p id="success-message" style="color: green; margin-bottom: 15px;">✅ All sessions have been reset to 30!</p>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <p id="error-message" style="color: red; margin-bottom: 15px;"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <table class="students-table">
        <thead>
            <tr>
                <th>ID Number</th>
                <th>Name</th>
                <th>Course</th>
                <th>Year Level</th>
                <th>Remaining Sessions</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['course']); ?></td>
                        <td><?php echo htmlspecialchars($row['year_level']); ?></td>
                        <td><?php echo htmlspecialchars($row['sessions']); ?></td>
                        <td class="action-buttons">
                            <a href="edit_student.php?id=<?php echo $row['id']; ?>" class="btn-edit">Edit</a>
                            <a href="delete_student.php?id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this student?');">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No students found.</td>
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
    
    // Auto-hide error message after 5 seconds
    setTimeout(function() {
        var errorMessage = document.getElementById('error-message');
        if(errorMessage) {
            errorMessage.style.display = 'none';
        }
    }, 5000);
    
    function toggleAddStudentForm() {
        var form = document.getElementById('addStudentForm');
        if (form.classList.contains('show')) {
            form.classList.remove('show');
            setTimeout(function() {
                form.style.display = 'none';
            }, 300);
        } else {
            form.style.display = 'flex';
            setTimeout(function() {
                form.classList.add('show');
            }, 10);
        }
    }
    
    function resetAllSessions() {
        if(confirm('Are you sure you want to reset all student sessions to 30?')) {
            // Create a form and submit it
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_students.php';
            
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reset_all_sessions';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
