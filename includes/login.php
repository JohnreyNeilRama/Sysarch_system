<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connect.php';

$id_number = $_POST['id_number'];
$password = $_POST['password'];

// First, check if the user is an admin
$stmt = $conn->prepare("SELECT * FROM admins WHERE admin_id = ? OR username = ?");
$stmt->bind_param("ss", $id_number, $id_number);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    // Verify the password
    if(password_verify($password, $row['password'])){
        // Store admin data in session
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_id_number'] = $row['admin_id'];
        $_SESSION['admin_username'] = $row['username'];
        $_SESSION['admin_full_name'] = $row['full_name'];
        $_SESSION['admin_email'] = $row['email'];
        $_SESSION['is_admin'] = true;
        
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/admin_dashboard.php");
        exit;
    }
}

// If not admin, check if the user is a student
$stmt = $conn->prepare("SELECT * FROM students WHERE id_number = ?");
$stmt->bind_param("s", $id_number);
$stmt->execute();
$result = $stmt->get_result();

if($row = $result->fetch_assoc()){
    // Verify the password
    if(password_verify($password, $row['password'])){
        // Store user data in session
        $_SESSION['student_id'] = $row['id'];
        $_SESSION['id_number'] = $row['id_number'];
        $_SESSION['last_name'] = $row['last_name'];
        $_SESSION['first_name'] = $row['first_name'];
        $_SESSION['middle_name'] = $row['middle_name'];
        $_SESSION['course'] = $row['course'];
        $_SESSION['year_level'] = $row['year_level'];
        $_SESSION['email'] = $row['email'];
        $_SESSION['address'] = $row['address'];
        $_SESSION['profile_picture'] = $row['profile_picture'];
        $_SESSION['sessions'] = isset($row['sessions']) ? $row['sessions'] : 30;
        $_SESSION['points_earned'] = isset($row['points_earned']) ? $row['points_earned'] : 0;
        $_SESSION['is_admin'] = false;
        
        // Create notification for login
        $notif_title = "Logged In";
        $notif_message = "You have successfully logged in to the SIT-IN system.";
        $notif_stmt = $conn->prepare("INSERT INTO notifications (id_number, type, title, message) VALUES (?, 'login', ?, ?)");
        $notif_stmt->bind_param("sss", $_SESSION['id_number'], $notif_title, $notif_message);
        if (!$notif_stmt->execute()) {
            error_log("Login notification error: " . $conn->error);
        }
        $notif_stmt->close();
        
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/userdb.php");
        exit;
    } else {
        // Invalid password
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/login.php?error=1");
        exit;
    }
} else {
    // Invalid ID Number
    $stmt->close();
    $conn->close();
    header("Location: /SYSARCH/pages/login.php?error=1");
    exit;
}
?>
