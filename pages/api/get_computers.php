<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include '../../includes/connect.php';
date_default_timezone_set('Asia/Manila');

// Check and add computer_no column if needed
$check_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'computer_no'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE reservations ADD COLUMN computer_no VARCHAR(10)");
}

$lab_room = isset($_POST['lab_room']) ? $_POST['lab_room'] : '';
$reservation_date = isset($_POST['reservation_date']) ? $_POST['reservation_date'] : '';
$reservation_time = isset($_POST['reservation_time']) ? $_POST['reservation_time'] : '';

if (empty($lab_room) || empty($reservation_date) || empty($reservation_time)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    // Check if status column exists, add if not
    $check_status = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
    if ($check_status->num_rows == 0) {
        $conn->query("ALTER TABLE computers ADD COLUMN status VARCHAR(20) DEFAULT 'available'");
    }
    
    // Get all computers for the selected lab
    $computers = [];
    $sql = "SELECT id, computer_number, status FROM computers WHERE lab_room = ? ORDER BY CAST(computer_number AS UNSIGNED)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $lab_room);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $computers[] = $row;
    }
    $stmt->close();

    // Check which computers are occupied at the selected time
    $occupied_units = [];

    $res_sql = "SELECT computer_no FROM reservations 
                WHERE lab_room = ? 
                AND reservation_date = ? 
                AND reservation_time = ? 
                AND status = 'Approved'
                AND computer_no IS NOT NULL";
    $res_stmt = $conn->prepare($res_sql);
    if (!$res_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $res_stmt->bind_param("sss", $lab_room, $reservation_date, $reservation_time);
    $res_stmt->execute();
    $res_result = $res_stmt->get_result();

    while ($row = $res_result->fetch_assoc()) {
        if ($row['computer_no']) {
            $occupied_units[] = $row['computer_no'];
        }
    }
    $res_stmt->close();

    // Also check active sit-ins for this lab at the selected date
    $sitin_sql = "SELECT lab FROM sit_in 
                  WHERE lab = ? 
                  AND sit_in_date = ? 
                  AND status = 'Active'";
    $sitin_stmt = $conn->prepare($sitin_sql);
    if (!$sitin_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $sitin_stmt->bind_param("ss", $lab_room, $reservation_date);
    $sitin_stmt->execute();
    $sitin_result = $sitin_stmt->get_result();

    if ($sitin_result->num_rows > 0) {
        foreach ($computers as $comp) {
            $occupied_units[] = $comp['computer_number'];
        }
    }
    $sitin_stmt->close();

    $conn->close();

    // Build response with availability
    $response = [];
    foreach ($computers as $comp) {
        $status = isset($comp['status']) && $comp['status'] !== null ? $comp['status'] : 'available';
        $is_admin_unavailable = (strtolower($status) === 'unavailable');
        $is_reserved = in_array($comp['computer_number'], $occupied_units);
        $is_available = !$is_admin_unavailable && !$is_reserved;
        
        $response[] = [
            'id' => $comp['id'],
            'computer_number' => $comp['computer_number'],
            'available' => $is_available,
            'admin_status' => $status
        ];
    }

    echo json_encode([
        'computers' => $response,
        'reservation_time' => $reservation_time
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
