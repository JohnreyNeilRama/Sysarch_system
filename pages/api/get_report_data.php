<?php
session_start();
include '../../includes/connect.php';

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = isset($_GET['type']) ? $_GET['type'] : '';

switch($type) {
    case 'students':
        $query = "SELECT id_number, first_name, last_name, course, year_level, sessions, points_earned FROM students ORDER BY last_name ASC";
        break;
    case 'sitin':
        $query = "SELECT id_number, student_name, purpose, lab, computer_no, sit_in_date, sit_in_time, status FROM sit_in ORDER BY sit_in_date DESC, sit_in_time DESC";
        break;
    case 'reservations':
        $query = "SELECT id_number, student_name, lab_room, reservation_date, reservation_time, purpose, status FROM reservations ORDER BY reservation_date DESC, reservation_time DESC";
        break;
    default:
        echo json_encode(['error' => 'Invalid report type']);
        exit;
}

$result = $conn->query($query);
$data = [];

if($result) {
    while($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

echo json_encode($data);
$conn->close();
?>
