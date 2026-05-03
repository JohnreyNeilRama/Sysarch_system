<?php
session_start();
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../includes/connect.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])){
    $id = intval($_POST['id']);
    
    $stmt = $conn->prepare("DELETE FROM lab_software WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if($stmt->execute()){
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Database error']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
$conn->close();
?>
