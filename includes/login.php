<?php
session_start();
include 'connect.php';

$email = $_POST['email'];
$password = $_POST['password'];

// First, check if the user is an admin
$stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
$stmt->bind_param("s", $email);
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
        header("Location: ../pages/admin_dashboard.php");
        exit;
    }
}

// If not admin, check if the user is a student
$stmt = $conn->prepare("SELECT * FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
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
        $_SESSION['is_admin'] = false;
        
        $stmt->close();
        $conn->close();
        header("Location: ../pages/userdb.php");
        exit;
    } else {
        // Invalid password
        $stmt->close();
        $conn->close();
        header("Location: ../pages/login.php?error=1");
        exit;
    }
} else {
    // Invalid email
    $stmt->close();
    $conn->close();
    header("Location: ../pages/login.php?error=1");
    exit;
}
?>
