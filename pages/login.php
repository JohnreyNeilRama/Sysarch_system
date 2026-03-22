<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/style.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
</head>
<body class="login-page">
   <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-left">
            <img class="logo_landing" src="/SYSARCH/assets/images/uclogo.png">College of Computer Studies Sit-in Monitoring System
        </div>

       <ul class="nav-right">
    <li><a href="/SYSARCH/landing.php">Home</a></li>

    <!-- Community Dropdown -->
    <li class="dropdown">
        <a href="#" class="dropbtn">Community ▾</a>
        <div class="dropdown-content">
            <a href="#">Announcements</a>
            <a href="#">Events</a>
            <a href="#">Forums</a>
            <a href="#">Guidelines</a>
        </div>
    </li>

    <li><a href="#">About</a></li>
    <li><a href="/SYSARCH/login.php">Login</a></li>
    <li><a href="/SYSARCH/registration.php">Register</a></li>
</ul>

    </nav>
    <!-- Main Content -->
   <div class="content">  
    <main class="login">

        <div class="login-container">

            <h2>Login</h2>

            <?php if(isset($_GET['error'])): ?>
            <p style="color: red; text-align: center; margin-bottom: 10px;">Invalid email or password!</p>
            <?php endif; ?>

            <form action="/SYSARCH/includes/login.php" method="POST">
                <label>Email</label>
                <input type="email" name="email" placeholder="example@gmail.com" required>

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
                
                <button type="submit">Login</button>

            </form>

        </div>
    </div>

    </main>

</body>
</html>
