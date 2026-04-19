<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Store id_number before destroying session
$id_number = isset($_SESSION['id_number']) ? $_SESSION['id_number'] : null;
$is_admin = isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : false;

// Only create logout notification for students (not admins)
if ($id_number !== null && $is_admin === false) {
    include '../includes/connect.php';
    
    // Create notification for logout
    $notif_title = "Logged Out";
    $notif_message = "You have successfully logged out of the SIT-IN system.";
    $notif_stmt = $conn->prepare("INSERT INTO notifications (id_number, type, title, message) VALUES (?, 'logout', ?, ?)");
    $notif_stmt->bind_param("sss", $id_number, $notif_title, $notif_message);
    if (!$notif_stmt->execute()) {
        error_log("Logout notification error: " . $conn->error);
    }
    $notif_stmt->close();
    
    $conn->close();
}

session_destroy();
header("Location: /SYSARCH/login.php");
exit;
?>
