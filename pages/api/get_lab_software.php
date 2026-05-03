<?php
session_start();
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../../includes/connect.php';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['lab_room'])){
    $lab_room = $_POST['lab_room'];
    
    $stmt = $conn->prepare("SELECT id, software_name, category FROM lab_software WHERE lab_room = ? ORDER BY category, software_name");
    $stmt->bind_param("s", $lab_room);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $software = [];
    while($row = $result->fetch_assoc()){
        $software[] = $row;
    }
    
    echo json_encode(['success' => true, 'software' => $software]);
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request']);
}
$conn->close();
?>
