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

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // 1. Ensure computers table exists
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

    // Check if status column exists, add if not
    $check_status = $conn->query("SHOW COLUMNS FROM computers LIKE 'status'");
    if ($check_status->num_rows == 0) {
        $conn->query("ALTER TABLE computers ADD COLUMN status VARCHAR(20) DEFAULT 'available'");
    }

    // Ensure all 6 standard labs are initialized in database
    $labs = ['524', '526', '528', '530', '544', '542'];
    foreach ($labs as $lab_room) {
        $check_lab = $conn->prepare("SELECT COUNT(*) as count FROM computers WHERE lab_room = ?");
        if ($check_lab) {
            $check_lab->bind_param("s", $lab_room);
            $check_lab->execute();
            $lab_result = $check_lab->get_result();
            $lab_count = $lab_result->fetch_assoc();
            $check_lab->close();

            if ($lab_count['count'] == 0) {
                for ($i = 1; $i <= 50; $i++) {
                    $stmt = $conn->prepare("INSERT INTO computers (lab_room, computer_number, status) VALUES (?, ?, 'available')");
                    if ($stmt) {
                        $stmt->bind_param("ss", $lab_room, $i);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
    }

    if ($action === 'get_status') {
        // Query current overall status of all laboratories
        $res = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count FROM computers");
        if ($res) {
            $row = $res->fetch_assoc();
            $total = intval($row['total']);
            $available = intval($row['available_count']);
            $enabled = ($available > 0); // If at least one computer is available, labs are enabled
            echo json_encode([
                'success' => true,
                'enabled' => $enabled,
                'total' => $total,
                'available' => $available
            ]);
        } else {
            throw new Exception("Failed to query status: " . $conn->error);
        }
        $conn->close();
        exit;
    } else if ($action === 'toggle') {
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        if (!in_array($status, ['available', 'unavailable'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE computers SET status = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $status);
        if ($stmt->execute()) {
            $conn->close();
            echo json_encode([
                'success' => true,
                'message' => 'All laboratories have been ' . ($status === 'available' ? 'enabled' : 'disabled') . ' successfully.'
            ]);
        } else {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action specified']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
