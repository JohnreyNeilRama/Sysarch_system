<?php
session_start();
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../includes/connect.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lab_room']) && isset($_POST['software_name']) && isset($_POST['category'])){
    $lab_room = $_POST['lab_room'];
    $software_name = $_POST['software_name'];
    $category = $_POST['category'];
    
    $stmt = $conn->prepare("INSERT INTO lab_software (lab_room, software_name, category) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $lab_room, $software_name, $category);
    
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
