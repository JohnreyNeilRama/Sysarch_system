<?php
session_start();

// Refresh session to extend lifetime
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: /SYSARCH/login.php");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Check if admin is logged in
if(!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Auto-create feedback table if not exists
$create_feedback_table = "CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sit_in_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    rating INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sit_in_id) REFERENCES sit_in(id) ON DELETE CASCADE
)";
$conn->query($create_feedback_table);

// Handle Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle Rating Filter
$rating_filter = isset($_GET['rating']) ? $_GET['rating'] : 'All';

$params = [];
$types = '';
$where_clauses = [];

if($search !== '') {
    $search_param = "%$search%";
    $where_clauses[] = "(student_id LIKE ? OR student_name LIKE ? OR comment LIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if($rating_filter !== 'All' && is_numeric($rating_filter)) {
    $where_clauses[] = "rating = ?";
    $params[] = $rating_filter;
    $types .= 'i';
}

$where_clause = '';
if(!empty($where_clauses)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Fetch feedback records with student name and purpose from sit_in table
$sql = "SELECT f.id, f.sit_in_id, f.student_id, f.student_name, f.rating, f.comment, f.created_at, s.lab, s.purpose 
        FROM feedback f 
        LEFT JOIN sit_in s ON f.sit_in_id = s.id 
        $where_clause 
        ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$total_feedback = 0;
$avg_rating = 0;
$five_star = 0;
$four_star = 0;
$three_star = 0;
$two_star = 0;
$one_star = 0;

$stats_sql = "SELECT 
    COUNT(*) as total, 
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM feedback";
$stats_result = $conn->query($stats_sql);
if($stats_row = $stats_result->fetch_assoc()){
    $total_feedback = $stats_row['total'];
    $avg_rating = $stats_row['avg_rating'] ? round($stats_row['avg_rating'], 1) : 0;
    $five_star = $stats_row['five_star'];
    $four_star = $stats_row['four_star'];
    $three_star = $stats_row['three_star'];
    $two_star = $stats_row['two_star'];
    $one_star = $stats_row['one_star'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Reports - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../assets/images/uclogo.png">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .filter-tab {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: #0f5bbe;
            color: white;
        }
        
        .filter-tab:not(.active) {
            background: #e0e0e0;
            color: #333;
        }

        .rating-stars {
            color: #ffc107;
        }
        
        .rating-low {
            color: #f44336;
        }
        
        .rating-medium {
            color: #ff9800;
        }
        
        .rating-high {
            color: #4caf50;
        }

        .btn-view {
            padding: 6px 12px;
            background: #0f5bbe;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .btn-view:hover {
            background: #0d4a9e;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-content h2 {
            margin-top: 0;
            color: #0f5bbe;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .detail-row {
            margin: 15px 0;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: #333;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h4 {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .stat-card p {
            margin: 10px 0 0;
            font-size: 28px;
            font-weight: bold;
            color: #0f5bbe;
        }

        .search-box {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-box input, .search-box select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-box button {
            padding: 10px 20px;
            background: #0f5bbe;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .search-box button:hover {
            background: #0d4a9e;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
            margin: 0 20px 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #1a3a5f;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background: #f5f5f5;
        }
    </style>
</head>
<body class="admin-dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        <img class="admin-logo" src="/SYSARCH/assets/images/uclogo.png" alt="UC Logo">
        <span class="admin-title">Admin Dashboard</span>
    </div>
    <ul class="dashboard-right" id="navRight">    
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="analytics.php">Analytics</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="manage_sitin.php">Sit-in Logs</a></li>
        <li><a href="manage_reservations.php">Reservations</a></li>
        <li><a href="feedback_reports.php" class="active">Feedback Reports</a></li>
        <li><a href="#">Settings</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
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

<div class="dashboard-container">

   <div class="dashboard-main">

    <div class="dashboard-content">
        <h2 style="text-align: center; margin: 20px 0;">Feedback Reports</h2>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Feedback</h4>
                <p><?php echo $total_feedback; ?></p>
            </div>
            <div class="stat-card">
                <h4>Average Rating</h4>
                <p><?php echo $avg_rating; ?>/5</p>
            </div>
            <div class="stat-card">
                <h4>5 Stars</h4>
                <p><?php echo $five_star; ?></p>
            </div>
            <div class="stat-card">
                <h4>4 Stars</h4>
                <p><?php echo $four_star; ?></p>
            </div>
            <div class="stat-card">
                <h4>3 Stars</h4>
                <p><?php echo $three_star; ?></p>
            </div>
            <div class="stat-card">
                <h4>2 Stars</h4>
                <p><?php echo $two_star; ?></p>
            </div>
            <div class="stat-card">
                <h4>1 Star</h4>
                <p><?php echo $one_star; ?></p>
            </div>
        </div>

        <!-- Search and Filter -->
        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by ID, name or comment..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="rating">
                <option value="All" <?php echo $rating_filter === 'All' ? 'selected' : ''; ?>>All Ratings</option>
                <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
            </select>
            <button type="submit">Search</button>
            <a href="feedback_reports.php"><button type="button">Reset</button></a>
        </form>

        <!-- Feedback Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Lab</th>
                        <th>Purpose</th>
                        <th>Rating</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['lab'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['purpose'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $stars = str_repeat('★', $row['rating']);
                                    $empty = str_repeat('☆', 5 - $row['rating']);
                                    $class = $row['rating'] >= 4 ? 'rating-high' : ($row['rating'] >= 3 ? 'rating-medium' : 'rating-low');
                                    echo "<span class='rating-stars $class'>$stars$empty</span>";
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <button class="btn-view" onclick="viewFeedback(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['student_id']); ?>', '<?php echo htmlspecialchars($row['student_name']); ?>', '<?php echo htmlspecialchars($row['lab'] ?? ''); ?>', '<?php echo htmlspecialchars($row['purpose'] ?? ''); ?>', <?php echo $row['rating']; ?>, '<?php echo htmlspecialchars($row['comment']); ?>', '<?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>')">View</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #666;">No feedback records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
   </div>
</div>

<!-- View Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeFeedbackModal()">&times;</span>
        <h2>Feedback Details</h2>
        <div class="detail-row">
            <div class="detail-label">Feedback ID:</div>
            <div class="detail-value" id="modalFeedbackId"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Student ID:</div>
            <div class="detail-value" id="modalStudentId"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Student Name:</div>
            <div class="detail-value" id="modalStudentName"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Lab:</div>
            <div class="detail-value" id="modalLab"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Purpose:</div>
            <div class="detail-value" id="modalPurpose"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Rating:</div>
            <div class="detail-value" id="modalRating"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Comment:</div>
            <div class="detail-value" id="modalComment"></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Date Submitted:</div>
            <div class="detail-value" id="modalDate"></div>
        </div>
    </div>
</div>

<script>
function viewFeedback(id, studentId, studentName, lab, purpose, rating, comment, date) {
    document.getElementById('modalFeedbackId').textContent = id;
    document.getElementById('modalStudentId').textContent = studentId;
    document.getElementById('modalStudentName').textContent = studentName;
    document.getElementById('modalLab').textContent = lab || 'N/A';
    document.getElementById('modalPurpose').textContent = purpose || 'N/A';
    
    var stars = '';
    for(var i = 0; i < rating; i++) {
        stars += '★';
    }
    for(var i = rating; i < 5; i++) {
        stars += '☆';
    }
    var ratingClass = rating >= 4 ? 'rating-high' : (rating >= 3 ? 'rating-medium' : 'rating-low');
    document.getElementById('modalRating').innerHTML = '<span class="rating-stars ' + ratingClass + '">' + stars + '</span> (' + rating + '/5)';
    
    document.getElementById('modalComment').textContent = comment;
    document.getElementById('modalDate').textContent = date;
    
    document.getElementById('feedbackModal').classList.add('active');
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.remove('active');
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if(e.target === this) {
        closeFeedbackModal();
    }
});
</script>

</body>
</html>