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

// Get all sit_in IDs that already have feedback
$feedback_sql = "SELECT sit_in_id FROM feedback WHERE student_id = ?";
$stmt_feedback = $conn->prepare($feedback_sql);
$stmt_feedback->bind_param("s", $student_id);
$stmt_feedback->execute();
$result_feedback = $stmt_feedback->get_result();
$feedback_submitted = [];
while($row = $result_feedback->fetch_assoc()) {
    $feedback_submitted[] = $row['sit_in_id'];
}
$stmt_feedback->close();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .history-container {
                padding: 10px;
            }
            
            .history-header h2 {
                font-size: 22px;
            }
            
            .history-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .history-table thead,
            .history-table tbody,
            .history-table tr,
            .history-table th,
            .history-table td {
                display: block;
            }
            
            .history-table thead {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            
            .history-table tr {
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 10px;
                background: white;
            }
            
            .history-table td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            
            .history-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 10px;
                width: 45%;
                text-align: left;
                font-weight: bold;
                color: #555;
            }
            
            .feedback-modal {
                width: 95%;
                margin: 10px;
            }
            
            .feedback-modal-header {
                padding: 15px;
            }
            
            .feedback-modal-body {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .history-header h2 {
                font-size: 18px;
            }
            
            .history-table td {
                font-size: 12px;
                padding-left: 45%;
            }
            
            .history-table td:before {
                font-size: 11px;
            }
            
            .btn-feedback {
                padding: 6px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>

<body class="dashboard-page">

<!-- Dashboard Navigation -->
<nav class="dashboard-navbar">

    <div class="dashboard-left">
        Dashboard
    </div>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
    <ul class="dashboard-right" id="navRight">    
        <li><a href="#">Notification</a></li>
        <li><a href="/SYSARCH/userdb.php">Home</a></li>
        <li><a href="/SYSARCH/edit_profile.php">Edit Profile</a></li>
        <li><a href="/SYSARCH/history.php" class="active">History</a></li>
        <li><a href="/SYSARCH/userdb.php" class="reservation-link" id="openReservation">Reservation</a></li>
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
                        <td data-label="ID Number"><?php echo htmlspecialchars($row['id_number']); ?></td>
                        <td data-label="Name"><?php echo htmlspecialchars($row['student_name']); ?></td>
                        <td data-label="Purpose"><?php echo htmlspecialchars($row['purpose']); ?></td>
                        <td data-label="Lab"><?php echo htmlspecialchars($row['lab']); ?></td>
                        <td data-label="Login Time"><?php echo htmlspecialchars($row['login_time']); ?></td>
                        <td data-label="Logout Time"><?php echo $row['logout_time'] ? htmlspecialchars($row['logout_time']) : '-'; ?></td>
                        <td data-label="Date"><?php echo htmlspecialchars($row['sit_in_date']); ?></td>
                        <td data-label="Status">
                            <?php if($row['status'] === 'Active'): ?>
                                <span class="status-active">Active</span>
                            <?php elseif($row['status'] === 'Rejected'): ?>
                                <span class="status-inactive">Rejected</span>
                            <?php else: ?>
                                <span class="status-inactive">Completed</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <?php 
                            $sit_in_id = $row['id'];
                            $has_feedback = in_array($sit_in_id, $feedback_submitted);
                            if(($row['status'] === 'Inactive' || $row['status'] === 'Completed') && !$has_feedback): ?>
                                <button class="btn-feedback" onclick="openFeedbackModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['purpose']); ?>', '<?php echo htmlspecialchars($row['lab']); ?>', '<?php echo htmlspecialchars($row['sit_in_date']); ?>')">Feedback</button>
                            <?php elseif($has_feedback): ?>
                                <button class="btn-feedback" disabled style="background: #aaa; cursor: not-allowed;">Submitted</button>
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
            <form id="reservationFormStep1" class="reservation-form" action="/SYSARCH/includes/process_reservation.php" method="POST">
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
                    <select id="reservation-time" name="reservation_time" required>
                        <option value="">Select Time</option>
                        <option value="07:30:00">7:30 AM</option>
                        <option value="08:00:00">8:00 AM</option>
                        <option value="08:30:00">8:30 AM</option>
                        <option value="09:00:00">9:00 AM</option>
                        <option value="09:30:00">9:30 AM</option>
                        <option value="10:00:00">10:00 AM</option>
                        <option value="10:30:00">10:30 AM</option>
                        <option value="11:00:00">11:00 AM</option>
                        <option value="11:30:00">11:30 AM</option>
                        <option value="12:00:00">12:00 PM</option>
                        <option value="12:30:00">12:30 PM</option>
                        <option value="13:00:00">1:00 PM</option>
                        <option value="13:30:00">1:30 PM</option>
                        <option value="14:00:00">2:00 PM</option>
                        <option value="14:30:00">2:30 PM</option>
                        <option value="15:00:00">3:00 PM</option>
                        <option value="15:30:00">3:30 PM</option>
                        <option value="16:00:00">4:00 PM</option>
                        <option value="16:30:00">4:30 PM</option>
                        <option value="17:00:00">5:00 PM</option>
                        <option value="17:30:00">5:30 PM</option>
                        <option value="18:00:00">6:00 PM</option>
                        <option value="18:30:00">6:30 PM</option>
                        <option value="19:00:00">7:00 PM</option>
                        <option value="19:30:00">7:30 PM</option>
                        <option value="20:00:00">8:00 PM</option>
                    </select>
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
                
                <button type="button" class="reservation-submit-btn" id="continueToStep2">Continue</button>
            </form>
        </div>
    </div>
</div>

<!-- COMPUTER SELECTION MODAL - Step 2 -->
<div class="reservation-modal-overlay" id="computerModal">
    <div class="reservation-modal computer-modal">
        <div class="reservation-modal-header">
            <h2>Select Computer - Step 2</h2>
            <button class="reservation-close-btn" id="closeComputerModal">&times;</button>
        </div>
        <div class="reservation-modal-body">
            <div class="computer-info">
                <p><strong>Room:</strong> <span id="selectedRoom"></span></p>
                <p><strong>Date:</strong> <span id="selectedDate"></span></p>
                <p><strong>Time:</strong> <span id="selectedTime"></span></p>
            </div>
            <div class="computer-legend">
                <span class="legend-item"><span class="computer-unit available"></span> Available</span>
                <span class="legend-item"><span class="computer-unit occupied"></span> Unavailable</span>
            </div>
            <div class="computer-grid" id="computerGrid"></div>
            <form id="reservationFormStep2" method="POST" action="/SYSARCH/includes/process_reservation.php">
                <input type="hidden" name="lab_room" id="inputLabRoom">
                <input type="hidden" name="reservation_date" id="inputReservationDate">
                <input type="hidden" name="reservation_time" id="inputReservationTime">
                <input type="hidden" name="purpose" id="inputPurpose">
                <input type="hidden" name="additional_notes" id="inputAdditionalNotes">
                <input type="hidden" name="computer_unit" id="inputComputerUnit">
                <button type="submit" class="reservation-submit-btn">Submit Reservation</button>
            </form>
        </div>
    </div>
</div>

<style>
.reservation-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.reservation-form .form-group {
    margin-bottom: 0;
}
.reservation-form label {
    display: block;
    font-weight: 600;
    color: #333 !important;
    margin-bottom: 8px;
    font-size: 14px;
}
.reservation-form input,
.reservation-form select,
.reservation-form textarea {
    width: 100%;
    padding: 12px 15px !important;
    border: 2px solid #e0e0e0 !important;
    border-radius: 10px !important;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-family: inherit;
    background: white !important;
    color: #333 !important;
}
.reservation-form input:focus,
.reservation-form select:focus,
.reservation-form textarea:focus {
    outline: none;
    border-color: #0f5bbe !important;
    box-shadow: 0 0 0 3px rgba(15, 91, 190, 0.1);
}
.reservation-form textarea {
    resize: vertical;
    min-height: 80px;
}
.computer-info {
    margin-bottom: 15px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 5px;
}
.computer-info p {
    margin: 5px 0;
}
.computer-legend {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
    justify-content: center;
}
.computer-legend .legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.computer-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    grid-template-rows: repeat(10, 1fr);
    grid-auto-flow: column;
    gap: 8px;
    margin-bottom: 20px;
    max-height: 320px;
    overflow-y: auto;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    background: #f9f9f9;
}
.computer-unit {
    width: 100%;
    aspect-ratio: 1;
    min-width: 35px;
    max-width: 50px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}
.computer-unit.available {
    background: linear-gradient(135deg, #4caf50 0%, #43a047 100%);
    color: white;
}
.computer-unit.available:hover {
    background: linear-gradient(135deg, #43a047 0%, #388e3c 100%);
    transform: translateY(-2px);
}
.computer-unit.occupied {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
    cursor: not-allowed;
    opacity: 0.7;
}
.computer-unit.unavailable {
    background: linear-gradient(135deg, #e53935 0%, #c62828 100%);
    color: white;
    cursor: not-allowed;
    opacity: 0.7;
}
.computer-unit.selected {
    border: 3px solid #0f5bbe;
    box-shadow: 0 0 10px rgba(15, 91, 190, 0.5);
}
.computer-modal {
    max-width: 600px;
}
</style>

<!-- JavaScript for Modal -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reservationModal');
    const computerModal = document.getElementById('computerModal');
    const openReservationBtn = document.getElementById('openReservation');
    const closeReservationBtn = document.getElementById('closeReservation');
    const closeComputerBtn = document.getElementById('closeComputerModal');
    const continueBtn = document.getElementById('continueToStep2');
    let selectedComputer = null;
    
    openReservationBtn.addEventListener('click', function(e) {
        e.preventDefault();
        modal.classList.add('active');
    });
    
    closeReservationBtn.addEventListener('click', function() {
        modal.classList.remove('active');
    });
    
    closeComputerBtn.addEventListener('click', function() {
        computerModal.classList.remove('active');
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
    
    computerModal.addEventListener('click', function(e) {
        if (e.target === computerModal) {
            computerModal.classList.remove('active');
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (computerModal.classList.contains('active')) {
                computerModal.classList.remove('active');
            } else if (modal.classList.contains('active')) {
                modal.classList.remove('active');
            }
        }
    });
    
    continueBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        const labRoom = document.getElementById('lab-room').value;
        const reservationDate = document.getElementById('reservation-date').value;
        const reservationTime = document.getElementById('reservation-time').value;
        const purpose = document.getElementById('purpose').value;
        
        if (!labRoom || !reservationDate || !reservationTime || !purpose) {
            alert('Please fill in all required fields');
            return;
        }
        
        document.getElementById('selectedRoom').textContent = 'Room ' + labRoom;
        document.getElementById('selectedDate').textContent = reservationDate;
        
        const timeParts = reservationTime.split(':');
        let hour = parseInt(timeParts[0]);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12;
        document.getElementById('selectedTime').textContent = hour + ':' + timeParts[1] + ' ' + ampm;
        
        document.getElementById('inputLabRoom').value = labRoom;
        document.getElementById('inputReservationDate').value = reservationDate;
        document.getElementById('inputReservationTime').value = reservationTime;
        document.getElementById('inputPurpose').value = purpose;
        document.getElementById('inputAdditionalNotes').value = document.getElementById('additional-notes').value;
        
        fetch('/SYSARCH/pages/api/get_computers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'lab_room=' + encodeURIComponent(labRoom) + 
                  '&reservation_date=' + encodeURIComponent(reservationDate) + 
                  '&reservation_time=' + encodeURIComponent(reservationTime)
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            const grid = document.getElementById('computerGrid');
            grid.innerHTML = '';
            
            if (data.computers.length === 0) {
                grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #666;">No computers found.</p>';
                modal.classList.remove('active');
                computerModal.classList.add('active');
                return;
            }
            
            // Rightmost column: 1-10 down, next: 20-11 up, etc.
            const computers = data.computers;
            const rowsPerCol = 10;
            const totalCols = Math.ceil(computers.length / rowsPerCol);
            const arranged = [];
            
            for (let col = totalCols - 1; col >= 0; col--) {
                const start = col * rowsPerCol;
                const end = Math.min(start + rowsPerCol, computers.length);
                const colData = computers.slice(start, end);
                
                if ((totalCols - 1 - col) % 2 === 0) {
                    arranged.push(...colData);
                } else {
                    arranged.push(...[...colData].reverse());
                }
            }
            
            arranged.forEach(comp => {
                const unit = document.createElement('div');
                const adminStatus = comp.admin_status ? comp.admin_status.toLowerCase() : '';
                let statusClass = comp.available ? 'available' : 'occupied';
                if (!comp.available && adminStatus === 'unavailable') {
                    statusClass = 'unavailable';
                }
                unit.className = 'computer-unit ' + statusClass;
                unit.textContent = comp.computer_number;
                unit.title = comp.available ? 'Click to select' : (adminStatus === 'unavailable' ? 'Marked as unavailable by admin' : 'Already reserved');
                
                if (comp.available) {
                    unit.addEventListener('click', function() {
                        document.querySelectorAll('.computer-unit.selected').forEach(el => el.classList.remove('selected'));
                        unit.classList.add('selected');
                        selectedComputer = comp.computer_number;
                        document.getElementById('inputComputerUnit').value = selectedComputer;
                    });
                }
                
                grid.appendChild(unit);
            });
            
            modal.classList.remove('active');
            computerModal.classList.add('active');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load computers. Please try again.');
        });
    });
    
    document.getElementById('reservationFormStep2').addEventListener('submit', function(e) {
        if (!selectedComputer) {
            e.preventDefault();
            alert('Please select a computer unit');
        }
    });
</script>

<script>
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
