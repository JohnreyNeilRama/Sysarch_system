<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Check if student ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: /SYSARCH/manage_students.php");
    exit;
}

$student_id = $_GET['id'];

// Fetch student data
$stmt = $conn->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, sessions FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){
    header("Location: /SYSARCH/manage_students.php");
    exit;
}

$student = $result->fetch_assoc();
$stmt->close();

// Handle form submission
$message = '';
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $id_number = $_POST['id_number'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $course = $_POST['course'];
    $year_level = $_POST['year_level'];
    $sessions = $_POST['sessions'];
    
    $update_stmt = $conn->prepare("UPDATE students SET id_number = ?, first_name = ?, last_name = ?, course = ?, year_level = ?, sessions = ? WHERE id = ?");
    $update_stmt->bind_param("sssssii", $id_number, $first_name, $last_name, $course, $year_level, $sessions, $student_id);
    
    if($update_stmt->execute()){
        $message = "Student updated successfully!";
        // Refresh student data
        $stmt = $conn->prepare("SELECT id, id_number, first_name, last_name, course, year_level, email, sessions FROM students WHERE id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $student = $result->fetch_assoc();
        $stmt->close();
    } else {
        $message = "Error updating student: " . $conn->error;
    }
    
    $update_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../assets/images/uclogo.css">
    <style>
        .edit-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .edit-container h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn-save,
        .btn-cancel {
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-save {
            background-color: #007bff;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #0056b3;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #545b62;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        <li><a href="manage_students.php" class="active">Manage Students</a></li>
        <li><a href="#">Sit-in Logs</a></li>
        <li><a href="#">Reservations</a></li>
        <li><a href="#">Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>
</nav>

<div class="edit-container">
    <h2>Edit Student</h2>
    
    <?php if($message): ?>
        <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label>ID Number</label>
            <input type="text" name="id_number" value="<?php echo htmlspecialchars($student['id_number']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Course</label>
            <input type="text" name="course" value="<?php echo htmlspecialchars($student['course']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Year Level</label>
            <select name="year_level" required>
                <option value="1st Year" <?php echo $student['year_level'] == '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                <option value="2nd Year" <?php echo $student['year_level'] == '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                <option value="3rd Year" <?php echo $student['year_level'] == '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                <option value="4th Year" <?php echo $student['year_level'] == '4th Year' ? 'selected' : ''; ?>>4th Year</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Remaining Sessions</label>
            <input type="number" name="sessions" value="<?php echo htmlspecialchars($student['sessions']); ?>" required min="0">
        </div>
        
        <div class="button-group">
            <button type="submit" class="btn-save">Save Changes</button>
            <a href="manage_students.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

</body>
</html>
