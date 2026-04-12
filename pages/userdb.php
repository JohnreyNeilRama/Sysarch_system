<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['student_id'])){
    header("Location: /SYSARCH/login.php");
    exit;
}

// Include database connection
include '../includes/connect.php';

// Set timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Auto-create reservations table if not exists
$create_reservations_table = "CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_number VARCHAR(50) NOT NULL,
    student_name VARCHAR(200) NOT NULL,
    lab_room VARCHAR(50) NOT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    additional_notes TEXT,
    computer_no VARCHAR(10),
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($create_reservations_table);

// Rename computer_unit to computer_no if it exists
$check_old_column = $conn->query("SHOW COLUMNS FROM reservations LIKE 'computer_unit'");
if ($check_old_column->num_rows > 0) {
    $conn->query("ALTER TABLE reservations CHANGE COLUMN computer_unit computer_no VARCHAR(10)");
}

// Auto-create computers table if not exists
$create_computers_table = "CREATE TABLE IF NOT EXISTS computers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_room VARCHAR(50) NOT NULL,
    computer_number VARCHAR(10) NOT NULL,
    status ENUM('Available', 'Occupied', 'Maintenance') DEFAULT 'Available',
    UNIQUE KEY unique_computer (lab_room, computer_number)
)";
$conn->query($create_computers_table);

// Populate computers for each lab if not exists
$lab_rooms = ['524', '525', '526', '527', '528'];
foreach ($lab_rooms as $lab) {
    for ($i = 1; $i <= 50; $i++) {
        $check = $conn->query("SELECT id FROM computers WHERE lab_room = '$lab' AND computer_number = '$i'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO computers (lab_room, computer_number, status) VALUES ('$lab', '$i', 'Available')");
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="/SYSARCH/assets/css/userdb.css">
<link rel="icon" type="image/png" href="/SYSARCH/assets/images/uclogo.png">
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
        <li><a href="/SYSARCH/history.php">History</a></li>
        <li><a href="#" class="reservation-link" id="openReservation">Reservation</a></li>
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

    <?php if(isset($_GET['reservation_success'])): ?>
        <div id="success-message" class="toast-message toast-success">Your reservation has been submitted successfully!</div>
    <?php endif; ?>

    <?php if(isset($_GET['reservation_error'])): ?>
        <div id="error-message" class="toast-message toast-error">Error: <?php echo htmlspecialchars($_GET['reservation_error']); ?></div>
    <?php endif; ?>

    <!-- LEFT PANEL -->
    <div class="student-info">

        <div class="student-header">
             Student Information
        </div>

        <div class="student-profile">
            <img src="/SYSARCH/assets/images/profile/<?php echo isset($_SESSION['profile_picture']) ? $_SESSION['profile_picture'] : 'default.png'; ?>" 
                 alt="Profile Picture">
        </div>

        <div class="student-details">

            <p><strong>ID Number:</strong> <span><?php echo $_SESSION['id_number']; ?></span></p>
            <p><strong>Full Name:</strong> <span><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['middle_name'] . ' ' . $_SESSION['last_name']; ?></span></p>
            <p><strong>Course:</strong> <span><?php echo $_SESSION['course']; ?></span></p>
            <p><strong>Year Level:</strong> <span><?php echo $_SESSION['year_level']; ?></span></p>
            <p><strong>Email:</strong> <span><?php echo $_SESSION['email']; ?></span></p>
            <p><strong>Address:</strong> <span><?php echo $_SESSION['address']; ?></span></p>
            <p><strong>Remaining Sessions:</strong> <span><?php echo isset($_SESSION['sessions']) ? $_SESSION['sessions'] : '30'; ?></span></p>

        </div>

    </div>

   <div class="dashboard-main">
    <!-- RULES AND REGULATION -->
    <div class="dashboard-card">
        <div class="card-header">Rules and Regulation</div>
        <div class="card-body">
            <div class="rules-content">

            <h3>University of Cebu</h3>
            <h4>COLLEGE OF INFORMATION & COMPUTER STUDIES</h4>

            <h4 class="rules-title">LABORATORY RULES AND REGULATIONS</h4>

            <br> 
            

            <p>
                To avoid embarrassment and maintain camaraderie with your friends and superiors 
                at our laboratories, please observe the following:
            </p>

            <ol>
                <li>
                    Maintain silence, proper decorum and discipline inside the laboratory. 
                    Mobile phones, walkmans and other personal items of equipment must be switched off.
                </li>

                <li>
                    Games are not allowed inside the lab. This includes computer-related games, 
                    card games and other games that may disturb the operation of the lab.
                </li>

                <li>
                    Surfing the Internet is allowed only with the permission of the instructor. 
                    Downloading and installing of software are strictly prohibited.
                </li>

                <li>Getting access to other websites not related to the course (especially pornographic and illicit sites) is strictly prohibited.</li>
                <li>Deleting computer files and changing the set-up of the computer is a major offense.</li>
                <li>Observe computer time usage carefully. A fifteen-minute allowance is given for each use. Otherwise, the unit will be given to those who wish to "sit-in".</li>
                <li>Observe proper decorum while inside the laboratory.<br>
                a. Do not get inside the lab unless the instructor is present.<br>
                b. All bags, knapsacks, and the likes must be deposited at the counter.<br>
                c. Follow the seating arrangement of your instructor.<br>
                d. At the end of class, all software programs must be closed.<br>
                e. Return all chairs to their proper places after using.</li>
                <li>Chewing gum, eating, drinking, smoking, and other forms of vandalism are prohibited inside the lab.</li>
                <li>Anyone causing a continual disturbance will be asked to leave the lab. Acts or gestures offensive to the members of the community, including public display of physical intimacy, are not tolerated.</li>
                <li>Persons exhibiting hostile or threatening behavior such as yelling, swearing, or disregarding requests made by lab personnel will be asked to leave the lab.</li>
                <li>For serious offense, the lab personnel may call the Civil Security Office (CSU) for assistance.</li>
                <li>Any technical problem or difficulty must be addressed to the laboratory supervisor, student assistant or instructor immediately.</li>
            </ol>

            </div>
        </div>
    </div>

    <!-- ANNOUNCEMENT -->
  <div class="dashboard-card">
    <div class="card-header">Announcement</div>

    <div class="card-body announcement-body">
        <?php
        // Fetch announcements from database (latest first)
        include '../includes/connect.php';
        $stmt = $conn->prepare("SELECT admin_name, announcement_date, message FROM announcements ORDER BY announcement_date DESC, created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $first = true;
            while($row = $result->fetch_assoc()) {
                if(!$first) {
                    echo '<hr>';
                }
                echo '<div class="announcement-item">';
                echo '    <div class="announcement-meta">';
                echo '        ' . htmlspecialchars($row['admin_name']) . ' | ' . date('Y-M-d', strtotime($row['announcement_date']));
                echo '    </div>';
                echo '    <div class="announcement-text">';
                echo '        ' . nl2br(htmlspecialchars($row['message']));
                echo '    </div>';
                echo '</div>';
                $first = false;
            }
        } else {
            echo '<div class="announcement-item">';
            echo '    <div class="announcement-text">';
            echo '        No announcements yet.';
            echo '    </div>';
            echo '</div>';
        }
        
        $stmt->close();
        $conn->close();
        ?>
    </div>
</div>

</div>

<!-- FLOATING RESERVATION MODAL - Step 1 -->
<div class="reservation-modal-overlay" id="reservationModal">
    <div class="reservation-modal">
        <div class="reservation-modal-header">
            <h2>Make a Reservation - Step 1</h2>
            <button class="reservation-close-btn" id="closeReservation">&times;</button>
        </div>
        <div class="reservation-modal-body">
            <form id="reservationFormStep1" class="reservation-form">
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
                <input type="hidden" name="computer_no" id="inputComputerNo">
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

.toast-message {
    position: fixed;
    top: 80px;
    left: 50%;
    transform: translateX(-50%);
    padding: 15px 30px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    z-index: 9999;
    animation: slideDown 0.3s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.toast-success {
    background: #4caf50;
    color: white;
}

.toast-error {
    background: #f44336;
    color: white;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

@keyframes fadeOut {
    to {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
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
    justify-content: flex-end;
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
    box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.computer-unit.available:hover {
    background: linear-gradient(135deg, #43a047 0%, #388e3c 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
        const openBtn = document.getElementById('openReservation');
        const closeBtn = document.getElementById('closeReservation');
        const closeComputerBtn = document.getElementById('closeComputerModal');
        const continueBtn = document.getElementById('continueToStep2');
        let selectedComputer = null;
        
        // Open modal
        openBtn.addEventListener('click', function(e) {
            e.preventDefault();
            modal.classList.add('active');
        });
        
        // Close modal with X button
        closeBtn.addEventListener('click', function() {
            modal.classList.remove('active');
        });
        
        // Close computer modal with X button
        closeComputerBtn.addEventListener('click', function() {
            computerModal.classList.remove('active');
        });
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        
        // Close computer modal when clicking outside
        computerModal.addEventListener('click', function(e) {
            if (e.target === computerModal) {
                computerModal.classList.remove('active');
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (computerModal.classList.contains('active')) {
                    computerModal.classList.remove('active');
                } else if (modal.classList.contains('active')) {
                    modal.classList.remove('active');
                }
            }
        });
        
        // Continue to Step 2 - Select Computer
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
            
            // Set info for Step 2
            document.getElementById('selectedRoom').textContent = 'Room ' + labRoom;
            document.getElementById('selectedDate').textContent = reservationDate;
            
            // Format time for display
            const timeParts = reservationTime.split(':');
            let hour = parseInt(timeParts[0]);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12;
            hour = hour ? hour : 12;
            const minute = timeParts[1];
            document.getElementById('selectedTime').textContent = hour + ':' + minute + ' ' + ampm;
            
            // Set hidden form values
            document.getElementById('inputLabRoom').value = labRoom;
            document.getElementById('inputReservationDate').value = reservationDate;
            document.getElementById('inputReservationTime').value = reservationTime;
            document.getElementById('inputPurpose').value = purpose;
            document.getElementById('inputAdditionalNotes').value = document.getElementById('additional-notes').value;
            
            // Fetch available computers
            fetch('/SYSARCH/pages/api/get_computers.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'lab_room=' + encodeURIComponent(labRoom) + 
                      '&reservation_date=' + encodeURIComponent(reservationDate) + 
                      '&reservation_time=' + encodeURIComponent(reservationTime)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    throw new Error('Invalid JSON: ' + text);
                }
                console.log('API Response:', data);
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                const grid = document.getElementById('computerGrid');
                grid.innerHTML = '';
                
                if (data.computers.length === 0) {
                    grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #666;">No computers found for this lab. Please try another lab.</p>';
                    modal.classList.remove('active');
                    computerModal.classList.add('active');
                    return;
                }
                
                // Rightmost column: 1-10 down, next: 20-11 up, next: 21-30 down, etc.
                const computers = data.computers;
                const rowsPerCol = 10;
                const totalCols = Math.ceil(computers.length / rowsPerCol);
                const arranged = [];
                
                // Fill columns from right to left
                for (let col = totalCols - 1; col >= 0; col--) {
                    const start = col * rowsPerCol;
                    const end = Math.min(start + rowsPerCol, computers.length);
                    const colData = computers.slice(start, end);
                    
                    if ((totalCols - 1 - col) % 2 === 0) {
                        // Rightmost, 3rd from right, etc.: top to bottom (1-10, 21-30)
                        arranged.push(...colData);
                    } else {
                        // 2nd from right, 4th from right, etc.: bottom to top (20-11, 40-31)
                        arranged.push(...[...colData].reverse());
                    }
                }
                
                arranged.forEach(comp => {
                    const unit = document.createElement('div');
                    const adminStatus = (comp.admin_status || '').toLowerCase();
                    const isAdminUnavailable = !comp.available || adminStatus === 'unavailable' || adminStatus === '';
                    let statusClass = isAdminUnavailable ? 'unavailable' : 'available';
                    console.log('Computer:', comp.computer_number, 'admin_status:', comp.admin_status, 'isAdminUnavailable:', isAdminUnavailable);
                    unit.className = 'computer-unit ' + statusClass;
                    unit.textContent = comp.computer_number;
                    unit.title = comp.available && adminStatus === 'available' ? 'Click to select' : 'Not available';
                    
                    if (!isAdminUnavailable) {
                        unit.addEventListener('click', function() {
                            document.querySelectorAll('.computer-unit.selected').forEach(el => {
                                el.classList.remove('selected');
                            });
                            unit.classList.add('selected');
                            selectedComputer = comp.computer_number;
                            document.getElementById('inputComputerNo').value = selectedComputer;
                        });
                    }
                    
                    grid.appendChild(unit);
                });
                
                // Hide step 1 modal and show step 2 modal
                modal.classList.remove('active');
                computerModal.classList.add('active');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load computers: ' + error.message + '\nCheck console for details');
            });
        });
        
        // Handle form submission from Step 2
        document.getElementById('reservationFormStep2').addEventListener('submit', function(e) {
            if (!selectedComputer) {
                e.preventDefault();
                alert('Please select a computer unit');
                return;
            }
            // Ensure the hidden input is set before submit
            document.getElementById('inputComputerNo').value = selectedComputer;
        });
        
        // Auto-hide success/error messages after 3 seconds
        setTimeout(function() {
            var successMsg = document.getElementById('success-message');
            if(successMsg) {
                successMsg.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(function() { successMsg.remove(); }, 300);
            }
            var errorMsg = document.getElementById('error-message');
            if(errorMsg) {
                errorMsg.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(function() { errorMsg.remove(); }, 300);
            }
        }, 3000);
    });
</script>

</body>
</html>
