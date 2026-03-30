<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - CCS Sit-in Monitoring</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/style.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .profile-pic-section {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-pic-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4CAF50;
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="file"] {
            padding: 5px;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            margin-top: 20px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #666;
            text-decoration: none;

        }

        .back-link:hover {
            font-size: 20px;

        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit Profile</h2>
    
    <form action="/SYSARCH/includes/update_profile.php" method="POST" enctype="multipart/form-data">
        
        <div class="profile-pic-section">
            <img src="/SYSARCH/assets/images/profile/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.png'; ?>" 
                 alt="Profile Picture" 
                 class="profile-pic-preview" 
                 id="preview">
            <br>
            <label for="profile_picture" style="display:inline; cursor:pointer; color: #4CAF50; height: 30px; line-height: 30px; border: 3px solid #4CAF50; border-radius: 4px; padding: 0 10px;">
                📷 Change Photo
            </label>
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display:none;" onchange="previewImage(this)">
        </div>
        
        <label>ID Number</label>
        <input type="text" name="id_number" value="<?php echo $_SESSION['id_number']; ?>" readonly>
        
        <label>Last Name</label>
        <input type="text" name="last_name" value="<?php echo $_SESSION['last_name']; ?>" required>
        
        <label>First Name</label>
        <input type="text" name="first_name" value="<?php echo $_SESSION['first_name']; ?>" required>
        
        <label>Middle Name</label>
        <input type="text" name="middle_name" value="<?php echo $_SESSION['middle_name']; ?>">
        
        <label>Course</label>
        <input type="text" name="course" value="<?php echo $_SESSION['course']; ?>" required>
        
        <label>Year Level</label>
        <input type="text" name="year_level" value="<?php echo $_SESSION['year_level']; ?>" required>
        
        <label>Email</label>
        <input type="email" name="email" value="<?php echo $_SESSION['email']; ?>" required>
        
        <label>Address</label>
        <textarea name="address" rows="3" required><?php echo $_SESSION['address']; ?></textarea>
        
        <button type="submit">Update Profile</button>
    </form>
    
    <a href="/SYSARCH/userdb.php" class="back-link">← Back to Dashboard</a>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
