<?php
session_start();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Check if student ID is provided
if(!isset($_GET['id']) || empty($_GET['id'])){
    header("Location: manage_students.php");
    exit;
}

$student_id = $_GET['id'];

// Delete student from database
$stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);

if($stmt->execute()){
    $stmt->close();
    $conn->close();
    header("Location: manage_students.php?success=deleted");
    exit;
} else {
    $error = "Error deleting student: " . $conn->error;
    $stmt->close();
    $conn->close();
    header("Location: manage_students.php?error=" . urlencode($error));
    exit;
}
?>
