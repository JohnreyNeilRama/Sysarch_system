<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

include '../../includes/connect.php';
date_default_timezone_set('Asia/Manila');

$computer_id = isset($_POST['computer_id']) ? $_POST['computer_id'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : '';
$lab_room = isset($_POST['lab_room']) ? $_POST['lab_room'] : '';

if (empty($computer_id) || empty($status) || empty($lab_room)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (!in_array($status, ['available', 'unavailable'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit;
}

try {
    $sql = "UPDATE computers SET status = ? WHERE id = ? AND lab_room = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sis", $status, $computer_id, $lab_room);
    
    if ($stmt->execute()) {
        $conn->close();
        echo json_encode(['success' => true, 'message' => 'Computer status updated successfully']);
    } else {
        throw new Exception($stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}