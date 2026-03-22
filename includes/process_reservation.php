<?php
session_start();

include 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: /SYSARCH/login.php");
    exit;
}

// Handle reservation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_number = $_SESSION['id_number'];
    $student_name = $_SESSION['first_name'] . ' ' . $_SESSION['middle_name'] . ' ' . $_SESSION['last_name'];
    $lab_room = $_POST['lab_room'];
    $reservation_date = $_POST['reservation_date'];
    $reservation_time = $_POST['reservation_time'];
    $purpose = $_POST['purpose'];
    $additional_notes = $_POST['additional_notes'] ?? '';

    // Insert reservation into database
    $stmt = $conn->prepare("INSERT INTO reservations (id_number, student_name, lab_room, reservation_date, reservation_time, purpose, additional_notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("sssssss", $id_number, $student_name, $lab_room, $reservation_date, $reservation_time, $purpose, $additional_notes);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/userdb.php?reservation_success=1");
        exit;
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        header("Location: /SYSARCH/pages/userdb.php?reservation_error=" . urlencode($error));
        exit;
    }
} else {
    header("Location: /SYSARCH/pages/userdb.php");
    exit;
}
?>
