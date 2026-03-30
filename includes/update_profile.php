<?php
session_start();
include 'connect.php';

$student_id = $_SESSION['student_id'];
$id_number = $_POST['id_number'];
$last_name = $_POST['last_name'];
$first_name = $_POST['first_name'];
$middle_name = $_POST['middle_name'];
$course = $_POST['course'];
$year_level = $_POST['year_level'];
$email = $_POST['email'];
$address = $_POST['address'];

// Get current profile picture
$current_picture = $_SESSION['profile_picture'];

// Get the project root directory (absolute path)
$project_root = dirname(dirname(__FILE__));
$profile_dir = $project_root . '/assets/images/profile/';

// Create profile directory if it doesn't exist
if (!is_dir($profile_dir)) {
    mkdir($profile_dir, 0755, true);
}

// Handle file upload
if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0){
    $file = $_FILES['profile_picture'];
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Get file extension
    $fileExt = explode('.', $fileName);
    $fileActualExt = strtolower(end($fileExt));
    
    // Allowed extensions
    $allowed = array('jpg', 'jpeg', 'png', 'gif');
    
    if(in_array($fileActualExt, $allowed)){
        // Create new filename: userid_timestamp.extension
        $newFileName = $student_id . '_' . time() . '.' . $fileActualExt;
        $fileDestination = $profile_dir . $newFileName;
        
        // Move file to folder
        if(move_uploaded_file($fileTmpName, $fileDestination)){
            // Delete old profile picture if it's not default
            if($current_picture && $current_picture != 'default.png' && file_exists($profile_dir . $current_picture)){
                unlink($profile_dir . $current_picture);
            }
            $current_picture = $newFileName;
        }
    }
}

// Use prepared statement for security
$stmt = $conn->prepare("UPDATE students SET last_name = ?, first_name = ?, middle_name = ?, course = ?, year_level = ?, email = ?, address = ?, profile_picture = ? WHERE id = ?");
$stmt->bind_param("ssssssssi", $last_name, $first_name, $middle_name, $course, $year_level, $email, $address, $current_picture, $student_id);

if($stmt->execute()){
    // Update session variables
    $_SESSION['last_name'] = $last_name;
    $_SESSION['first_name'] = $first_name;
    $_SESSION['middle_name'] = $middle_name;
    $_SESSION['course'] = $course;
    $_SESSION['year_level'] = $year_level;
    $_SESSION['email'] = $email;
    $_SESSION['address'] = $address;
    $_SESSION['profile_picture'] = $current_picture;
    
    $stmt->close();
    $conn->close();
    header("Location: /SYSARCH/pages/userdb.php?success=1");
    exit;
}else{
    echo "Error: " . $stmt->error;
    $stmt->close();
    $conn->close();
}
?>
