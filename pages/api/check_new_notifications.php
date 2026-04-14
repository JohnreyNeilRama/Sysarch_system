<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['id_number'])) {
    echo json_encode(['newNotification' => false]);
    exit;
}

include '../../includes/connect.php';

$student_id = $_SESSION['id_number'];
$last_check = isset($_SESSION['last_notif_check']) ? $_SESSION['last_notif_check'] : '1970-01-01 00:00:00';

$sql = "SELECT * FROM notifications WHERE id_number = ? AND is_read = 0 AND created_at > ? ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $student_id, $last_check);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $notification = $result->fetch_assoc();
    $_SESSION['last_notif_check'] = date('Y-m-d H:i:s');
    
    $mark_read = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $mark_read->bind_param("i", $notification['id']);
    $mark_read->execute();
    $mark_read->close();
    
    echo json_encode([
        'newNotification' => true,
        'notification' => $notification
    ]);
} else {
    $_SESSION['last_notif_check'] = date('Y-m-d H:i:s');
    echo json_encode(['newNotification' => false]);
}

$stmt->close();
$conn->close();
