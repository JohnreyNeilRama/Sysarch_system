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
    $computer_unit = $_POST['computer_unit'] ?? null;

    // Insert reservation into database
    $stmt = $conn->prepare("INSERT INTO reservations (id_number, student_name, lab_room, reservation_date, reservation_time, purpose, additional_notes, computer_unit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("ssssssss", $id_number, $student_name, $lab_room, $reservation_date, $reservation_time, $purpose, $additional_notes, $computer_unit);

    if ($stmt->execute()) {
        $stmt->close();
        
        // Refresh session data from database to ensure it's up to date
        $refresh_stmt = $conn->prepare("SELECT sessions FROM students WHERE id_number = ?");
        $refresh_stmt->bind_param("s", $id_number);
        $refresh_stmt->execute();
        $refresh_result = $refresh_stmt->get_result();
        if($refresh_row = $refresh_result->fetch_assoc()) {
            $_SESSION['sessions'] = $refresh_row['sessions'];
        }
        $refresh_stmt->close();
        
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
