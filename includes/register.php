<?php
include 'connect.php';

$id_number = $_POST['id_number'];
$last_name = $_POST['last_name'];
$first_name = $_POST['first_name'];
$middle_name = $_POST['middle_name'];
$course = $_POST['course'];
$year_level = $_POST['year_level'];
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$address = $_POST['address'];

// Check if passwords match
if ($password !== $confirm_password) {
    if(isset($_POST['from_admin']) && $_POST['from_admin'] == '1'){
        header("Location: /SYSARCH/pages/manage_students.php?error=" . urlencode("Password and Confirm Password do not match!"));
    } else {
        echo "Password and Confirm Password do not match!";
    }
    exit;
}

// Check if ID number already exists
$stmt = $conn->prepare("SELECT id FROM students WHERE id_number = ?");
$stmt->bind_param("s", $id_number);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows > 0){
    $error_msg = "Error: ID Number '$id_number' is already registered!";
    if(isset($_POST['from_admin']) && $_POST['from_admin'] == '1'){
        header("Location: /SYSARCH/pages/manage_students.php?error=" . urlencode($error_msg));
    } else {
        echo $error_msg;
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if($result->num_rows > 0){
    $error_msg = "Error: Email '$email' is already registered!";
    if(isset($_POST['from_admin']) && $_POST['from_admin'] == '1'){
        header("Location: /SYSARCH/pages/manage_students.php?error=" . urlencode($error_msg));
    } else {
        echo $error_msg;
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Hash the password for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Use prepared statement for security
$stmt = $conn->prepare("INSERT INTO students (id_number, last_name, first_name, middle_name, course, year_level, email, password, address, sessions) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 30)");
$stmt->bind_param("sssssssss", $id_number, $last_name, $first_name, $middle_name, $course, $year_level, $email, $hashed_password, $address);

if($stmt->execute()){
    $stmt->close();
    $conn->close();
    // Check if request comes from admin dashboard
    if(isset($_POST['from_admin']) && $_POST['from_admin'] == '1'){
        header("Location: /SYSARCH/pages/manage_students.php?success=1");
    } else {
        header("Location: /SYSARCH/pages/login.php");
    }
    exit;
}else{
    $error_msg = "Error: " . $stmt->error;
    if(isset($_POST['from_admin']) && $_POST['from_admin'] == '1'){
        header("Location: /SYSARCH/pages/manage_students.php?error=" . urlencode($error_msg));
    } else {
        echo $error_msg;
    }
    $stmt->close();
    $conn->close();
}
?>
