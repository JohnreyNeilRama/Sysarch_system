<?php
// Include database connection
include_once __DIR__ . '/../includes/connect.php';

// Get top students by points earned
$leaderboard = [];
$stmt = $conn->prepare("SELECT id_number, first_name, last_name, course, points_earned, profile_picture FROM students WHERE points_earned > 0 ORDER BY points_earned DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $leaderboard[] = $row;
}
$stmt->close();
$conn->close();

function getProfilePicture($picture) {
    if($picture && file_exists(__DIR__ . '/../assets/images/profile/' . $picture)) {
        return '/SYSARCH/assets/images/profile/' . $picture;
    }
    return '/SYSARCH/assets/images/profile/default.png';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/style.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-left">
            <img class="logo_landing" src="/SYSARCH/assets/images/uclogo.png">College of Computer Studies Sit-in Monitoring System
        </div>
        <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
       <ul class="nav-right" id="navRight">
    <li><a href="#">Home</a></li>

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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navRight = document.getElementById('navRight');
            
            mobileMenuToggle.addEventListener('click', function() {
                navRight.classList.toggle('active');
                this.textContent = navRight.classList.contains('active') ? '✕' : '☰';
            });
        });
    </script>

  <!-- Main Content -->
<main class="hero">
    <div class="hero-text">
        <h1>
            Welcome to the CCS Sit-in<br>
            Monitoring System
        </h1>

        <a href="/SYSARCH/login.php" class="start-btn">Get Started →</a>
    </div>
</main>

    <!-- Student Leaderboard Section -->
    <section class="leaderboard-section">
        <div class="leaderboard-container">
            <h2 class="leaderboard-title">🏆 Top 10 Students Leaderboard</h2>
            <p class="leaderboard-subtitle">Students with the highest points earned</p>
            
            <?php if(count($leaderboard) > 0): ?>
            <div class="leaderboard-grid">
                <?php foreach($leaderboard as $index => $student): ?>
                <div class="leaderboard-card <?php echo $index < 3 ? 'top-three' : ''; ?>">
                    <div class="rank-badge">
                        <?php if($index == 0): ?><span class="medal">🥇</span>
                        <?php elseif($index == 1): ?><span class="medal">🥈</span>
                        <?php elseif($index == 2): ?><span class="medal">🥉</span>
                        <?php else: ?><span class="rank-num"><?php echo $index + 1; ?></span><?php endif; ?>
                    </div>
                    <div class="profile-container">
                        <img src="<?php echo getProfilePicture($student['profile_picture']); ?>" alt="Profile" class="profile-pic">
                    </div>
                    <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                    <div class="student-id"><?php echo htmlspecialchars($student['id_number']); ?></div>
                    <div class="student-course"><?php echo htmlspecialchars($student['course']); ?></div>
                    <div class="points-badge">
                        <span class="points-value"><?php echo intval($student['points_earned']); ?></span>
                        <span class="points-label">POINTS</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="no-data">No students have earned points yet.</p>
            <?php endif; ?>
        </div>
    </section>

</body>
</html>
