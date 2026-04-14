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

$lab_room = isset($_POST['lab_room']) ? $_POST['lab_room'] : '';

if (empty($lab_room)) {
    echo json_encode(['error' => 'Lab room is required']);
    exit;
}

try {
    // Check if status column exists, add if not
    $check_status = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
    if ($check_status->num_rows == 0) {
        $conn->query("ALTER TABLE computers ADD COLUMN status VARCHAR(20) DEFAULT 'available'");
    }
    
    // Check if status column is ENUM and convert to VARCHAR
    $check_status_type = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
    if ($check_status_type->num_rows > 0) {
        $status_row = $check_status_type->fetch_assoc();
        if (strpos($status_row['Type'], 'enum') !== false) {
            $conn->query("ALTER TABLE computers MODIFY COLUMN status VARCHAR(20) DEFAULT 'available'");
        }
    }
    
    $check_table = $conn->query("SHOW TABLES LIKE 'computers'");
    if ($check_table->num_rows == 0) {
        $create_table = "CREATE TABLE IF NOT EXISTS computers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lab_room VARCHAR(50) NOT NULL,
            computer_number VARCHAR(10) NOT NULL,
            status VARCHAR(20) DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_computer (lab_room, computer_number)
        )";
        $conn->query($create_table);
    }
    
    // Check if computers exist for this lab
    $check_lab = $conn->prepare("SELECT COUNT(*) as count FROM computers WHERE lab_room = ?");
    $check_lab->bind_param("s", $lab_room);
    $check_lab->execute();
    $lab_result = $check_lab->get_result();
    $lab_count = $lab_result->fetch_assoc();
    $check_lab->close();
    
    // If no computers for this lab, create them
    if ($lab_count['count'] == 0) {
        $computers = [
            '524' => 50,
            '525' => 50,
            '526' => 50,
            '527' => 50,
            '528' => 50
        ];
        
        if (isset($computers[$lab_room])) {
            for ($i = 1; $i <= $computers[$lab_room]; $i++) {
                $stmt = $conn->prepare("INSERT INTO computers (lab_room, computer_number, status) VALUES (?, ?, 'available')");
                $stmt->bind_param("ss", $lab_room, $i);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    
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
    
    $conn->close();

    echo json_encode([
        'success' => true,
        'computers' => $computers
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}