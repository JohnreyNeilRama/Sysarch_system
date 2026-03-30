<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Get student's sit-in history
$student_id = $_SESSION['id_number'];

// Fetch from sit_in table (approved/completed sessions)
$sql_sitin = "SELECT id, id_number, student_name, purpose, lab, sit_in_time as login_time, sit_in_time as logout_time, sit_in_date, status, 'sit_in' as record_type FROM sit_in WHERE id_number = ?";
$stmt_sitin = $conn->prepare($sql_sitin);
$stmt_sitin->bind_param("s", $student_id);
$stmt_sitin->execute();
$result_sitin = $stmt_sitin->get_result();

// Fetch from reservations table (rejected reservations)
$sql_rejected = "SELECT id, id_number, student_name, purpose, lab_room as lab, reservation_time as login_time, NULL as logout_time, reservation_date as sit_in_date, status, 'reservation' as record_type FROM reservations WHERE id_number = ? AND status = 'Rejected'";
$stmt_rejected = $conn->prepare($sql_rejected);
$stmt_rejected->bind_param("s", $student_id);
$stmt_rejected->execute();
$result_rejected = $stmt_rejected->get_result();

// Combine results
$all_records = [];
while($row = $result_sitin->fetch_assoc()) {
    $all_records[] = $row;
}
while($row = $result_rejected->fetch_assoc()) {
    $all_records[] = $row;
}

// Sort by date descending
usort($all_records, function($a, $b) {
    return strtotime($b['sit_in_date'] . ' ' . $b['login_time']) - strtotime($a['sit_in_date'] . ' ' . $a['login_time']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History - CCS Sit-in Monitoring System</title>
    <link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
    <link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
    <style>
        .history-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .history-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .history-header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .history-header p {
            color: #666;
            font-size: 14px;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .history-table thead {
            background: #0f5bbe;
            color: white;
        }
        
        .history-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
            color: #333;
        }
        
        .history-table tbody tr:hover {
            background: #f5f5f5;
        }
        
        .history-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .status-active {
            color: #4caf50;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #f44336;
            font-weight: bold;
        }
        
        .btn-feedback {
            background: #0f5bbe;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .btn-feedback:hover {
            background: #0d4fa1;
        }
        
        .btn-feedback:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .no-history {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
        }
        
        .feedback-modal-overlay {
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
        
        .feedback-modal-overlay.active {
            display: flex;
        }
        
        .feedback-modal {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .feedback-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .feedback-modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .feedback-close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }
        
        .feedback-modal-body {
            padding: 20px;
        }
        
        .feedback-form .form-group {
            margin-bottom: 15px;
        }
        
        .feedback-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .feedback-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        
        .feedback-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .feedback-submit-btn {
            width: 100%;
            padding: 12px;
            background: #0f5bbe;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .feedback-submit-btn:hover {
            background: #0d4fa1;
        }
    </style>
</head>

<body class="dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        Dashboard
    </div>

    <ul class="dashboard-right">    
        <li><a href="#">Notification</a></li>
        <li><a href="/SYSARCH/userdb.php">Home</a></li>
        <li><a href="/SYSARCH/edit_profile.php">Edit Profile</a></li>
        <li><a href="/SYSARCH/history.php" class="active">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
        <li><a href="/SYSARCH/logout.php" class="logout-btn">Log Out</a></li>
    </ul>

</nav>

<div class="history-container">
    <div class="history-header">
        <h2>Sit-in History</h2>
        <p>View your past and current sit-in sessions</p>
    </div>
    
    <?php if(!empty($all_records)): ?>
        <table class="history-table">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Name</th>
                    <th>Sit Purpose</th>
                    <th>Laboratory</th>
                    <th>Time of Login</th>
                    <th>Time of Logout</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($all_records as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td><?php echo htmlspecialchars($row['lab']); ?></td>
                        <td><?php echo htmlspecialchars($row['login_time']); ?></td>
                        <td><?php echo $row['logout_time'] ? htmlspecialchars($row['logout_time']) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($row['sit_in_date']); ?></td>
                        <td>
                            <?php if($row['status'] === 'Active'): ?>
                                <span class="status-active">Active</span>
                            <?php elseif($row['status'] === 'Rejected'): ?>
                                <span class="status-inactive">Rejected</span>
                            <?php else: ?>
                                <span class="status-inactive">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['status'] === 'Inactive' || $row['status'] === 'Completed'): ?>
                                <button class="btn-feedback" onclick="openFeedbackModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['purpose']); ?>', '<?php echo htmlspecialchars($row['lab']); ?>', '<?php echo htmlspecialchars($row['sit_in_date']); ?>')">Feedback</button>
                            <?php else: ?>
                                <button class="btn-feedback" disabled>Feedback</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-history">
            <p>No sit-in history found.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Feedback Modal -->
<div class="feedback-modal-overlay" id="feedbackModal">
    <div class="feedback-modal">
        <div class="feedback-modal-header">
            <h3>Submit Feedback</h3>
            <button class="feedback-close-btn" id="closeFeedback">&times;</button>
        </div>
        <div class="feedback-modal-body">
            <form class="feedback-form" action="/SYSARCH/includes/process_feedback.php" method="POST">
                <input type="hidden" name="sit_in_id" id="feedback-sit-in-id">
                <input type="hidden" name="student_id" value="<?php echo $_SESSION['id_number']; ?>">
                <input type="hidden" name="student_name" value="<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>">
                
                <div class="form-group">
                    <label>Sit-in Details</label>
                    <p id="feedback-details" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin: 0;"></p>
                </div>
                
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select id="rating" name="rating" required>
                        <option value="">Select Rating</option>
                        <option value="5">Excellent (5)</option>
                        <option value="4">Good (4)</option>
                        <option value="3">Average (3)</option>
                        <option value="2">Poor (2)</option>
                        <option value="1">Very Poor (1)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="feedback-comment">Comments</label>
                    <textarea id="feedback-comment" name="comment" placeholder="Share your experience..." required></textarea>
                </div>
                
                <button type="submit" class="feedback-submit-btn">Submit Feedback</button>
            </form>
        </div>
    </div>
</div>

<!-- FLOATING RESERVATION MODAL -->
<div class="reservation-modal-overlay" id="reservationModal">
    <div class="reservation-modal">
        <div class="reservation-modal-header">
            <h2>Make a Reservation</h2>
            <button class="reservation-close-btn" id="closeReservation">&times;</button>
        </div>
        <div class="reservation-modal-body">
            <form class="reservation-form" action="/SYSARCH/includes/process_reservation.php" method="POST">
                <div class="form-group">
                    <label for="lab-room">Laboratory Room</label>
                    <select id="lab-room" name="lab_room" required>
                        <option value="">Select Laboratory</option>
                        <option value="524">Room 524</option>
                        <option value="525">Room 525</option>
                        <option value="526">Room 526</option>
                        <option value="527">Room 527</option>
                        <option value="528">Room 528</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="reservation-date">Reservation Date</label>
                    <input type="date" id="reservation-date" name="reservation_date" required>
                </div>
                
                <div class="form-group">
                    <label for="reservation-time">Reservation Time</label>
                    <input type="time" id="reservation-time" name="reservation_time" required>
                </div>
                
                <div class="form-group">
                    <label for="purpose">Purpose</label>
                    <select id="purpose" name="purpose" required>
                        <option value="">Select Purpose</option>
                        <option value="C Programming">C Programming</option>
                        <option value="Java Programming">Java Programming</option>
                        <option value="Python Programming">Python Programming</option>
                        <option value="Web Development">Web Development</option>
                        <option value="Database">Database</option>
                        <option value="Research">Research</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Examination">Examination</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="additional-notes">Additional Notes</label>
                    <textarea id="additional-notes" name="additional_notes" placeholder="Enter any additional details..."></textarea>
                </div>
                
                <button type="submit" class="reservation-submit-btn">Submit Reservation</button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for Modal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Feedback Modal
        const feedbackModal = document.getElementById('feedbackModal');
        const closeFeedbackBtn = document.getElementById('closeFeedback');
        
        // Reservation Modal
        const reservationModal = document.getElementById('reservationModal');
        const openReservationBtn = document.getElementById('openReservation');
        const closeReservationBtn = document.getElementById('closeReservation');
        
        // Open reservation modal
        openReservationBtn.addEventListener('click', function(e) {
            e.preventDefault();
            reservationModal.classList.add('active');
        });
        
        // Close feedback modal with X button
        closeFeedbackBtn.addEventListener('click', function() {
            feedbackModal.classList.remove('active');
        });
        
        // Close reservation modal with X button
        closeReservationBtn.addEventListener('click', function() {
            reservationModal.classList.remove('active');
        });
        
        // Close modals when clicking outside
        feedbackModal.addEventListener('click', function(e) {
            if (e.target === feedbackModal) {
                feedbackModal.classList.remove('active');
            }
        });
        
        reservationModal.addEventListener('click', function(e) {
            if (e.target === reservationModal) {
                reservationModal.classList.remove('active');
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (feedbackModal.classList.contains('active')) {
                    feedbackModal.classList.remove('active');
                }
                if (reservationModal.classList.contains('active')) {
                    reservationModal.classList.remove('active');
                }
            }
        });
    });
    
    function openFeedbackModal(sitInId, purpose, lab, date) {
        document.getElementById('feedback-sit-in-id').value = sitInId;
        document.getElementById('feedback-details').innerHTML = 
            '<strong>Purpose:</strong> ' + purpose + '<br>' +
            '<strong>Laboratory:</strong> ' + lab + '<br>' +
            '<strong>Date:</strong> ' + date;
        document.getElementById('feedbackModal').classList.add('active');
    }
</script>

</body>
</html>

<?php
$stmt_sitin->close();
$stmt_rejected->close();
$conn->close();
?>
