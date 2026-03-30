<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include 'connect.php';

// Auto-create feedback table if not exists
$create_feedback_table = "CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sit_in_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    rating INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sit_in_id) REFERENCES sit_in(id) ON DELETE CASCADE
)";
$conn->query($create_feedback_table);

// Process feedback submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sit_in_id = isset($_POST['sit_in_id']) ? intval($_POST['sit_in_id']) : 0;
    $student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
    $student_name = isset($_POST['student_name']) ? $_POST['student_name'] : '';
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Validate input
    if($sit_in_id <= 0 || $student_id === '' || $student_name === '' || $rating <= 0 || $rating > 5 || $comment === '') {
        header("Location: /SYSARCH/pages/history.php?error=Invalid input data");
        exit;
    }
    
    // Check if feedback already exists for this sit-in
    $check_stmt = $conn->prepare("SELECT id FROM feedback WHERE sit_in_id = ?");
    $check_stmt->bind_param("i", $sit_in_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        $check_stmt->close();
        header("Location: /SYSARCH/pages/history.php?error=Feedback already submitted for this sit-in");
        exit;
    }
    $check_stmt->close();
    
    // Insert feedback
    $stmt = $conn->prepare("INSERT INTO feedback (sit_in_id, student_id, student_name, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $sit_in_id, $student_id, $student_name, $rating, $comment);
    
    if($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/history.php?success=Feedback submitted successfully!");
        exit;
    } else {
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/history.php?error=Failed to submit feedback");
        exit;
    }
} else {
    $conn->close();
    header("Location: /SYSARCH/pages/history.php");
    exit;
}
?>
