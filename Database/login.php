<?php
session_start();
include 'connect.php';

$email = $_POST['email'];
$password = $_POST['password'];

// Use prepared statement for security
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
        
        $stmt->close();
        $conn->close();
        header("Location: ../userdb.php");
        exit;
    } else {
        // Invalid password
        $stmt->close();
        $conn->close();
        header("Location: ../login.php?error=1");
        exit;
    }
} else {
    // Invalid email
    $stmt->close();
    $conn->close();
    header("Location: ../login.php?error=1");
    exit;
}
?>
