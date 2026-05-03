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
                        <option value="526">Room 526</option>
                        <option value="528">Room 528</option>
                        <option value="530">Room 530</option>
                        <option value="544">Room 544</option>
                        <option value="542">Room 542</option>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resModal = document.getElementById('reservationModal');
    const compModal = document.getElementById('computerModal');
    const resOpenBtn = document.getElementById('openReservation') || document.querySelector('.reservation-link');
    const resCloseBtn = document.getElementById('closeReservation');
    const compCloseBtn = document.getElementById('closeComputerModal');
    const resContinueBtn = document.getElementById('continueToStep2');
    let resSelectedComputer = null;
    
    // Open modal
    if(resOpenBtn) {
        resOpenBtn.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#' || !this.getAttribute('href') || this.id === 'openReservation') {
                e.preventDefault();
                if(resModal) resModal.classList.add('active');
            }
        });
    }

    // Handle the open_reservation URL parameter
    const resUrlParams = new URLSearchParams(window.location.search);
    if (resUrlParams.get('open_reservation')) {
        if(resModal) resModal.classList.add('active');
    }
    
    // Close modal with X button
    if(resCloseBtn && resModal) {
        resCloseBtn.addEventListener('click', function() {
            resModal.classList.remove('active');
        });
    }
    
    // Close computer modal with X button
    if(compCloseBtn && compModal) {
        compCloseBtn.addEventListener('click', function() {
            compModal.classList.remove('active');
        });
    }
    
    // Close modal when clicking outside
    if(resModal) {
        resModal.addEventListener('click', function(e) {
            if (e.target === resModal) {
                resModal.classList.remove('active');
            }
        });
    }
    
    // Close computer modal when clicking outside
    if(compModal) {
        compModal.addEventListener('click', function(e) {
            if (e.target === compModal) {
                compModal.classList.remove('active');
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (compModal && compModal.classList.contains('active')) {
                compModal.classList.remove('active');
            } else if (resModal && resModal.classList.contains('active')) {
                resModal.classList.remove('active');
            }
        }
    });
    
    // Continue to Step 2 - Select Computer
    if(resContinueBtn) {
        resContinueBtn.addEventListener('click', function(e) {
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
            
            // Reset computer selection for Step 2
            resSelectedComputer = null;
            if (document.getElementById('inputComputerNo')) {
                document.getElementById('inputComputerNo').value = '';
            }
            
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
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON: ' + text);
                }
                
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }
                
                if (!data.computers) {
                    alert('Error: Invalid response from server');
                    return;
                }
                
                const grid = document.getElementById('computerGrid');
                grid.innerHTML = '';
                
                if (data.computers.length === 0) {
                    grid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #666;">No computers found for this lab.</p>';
                    resModal.classList.remove('active');
                    compModal.classList.add('active');
                    return;
                }
                
                // Snake arrangement logic
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
                    const adminStatus = (comp.admin_status || 'available').toLowerCase();
                    const isAdminUnavailable = adminStatus === 'unavailable';
                    const isOccupied = !comp.available && !isAdminUnavailable;
                    let statusClass = (isAdminUnavailable || isOccupied) ? 'unavailable' : 'available';
                    
                    unit.className = 'computer-unit ' + statusClass;
                    unit.textContent = comp.computer_number;
                    unit.title = statusClass === 'available' ? 'Click to select Computer ' + comp.computer_number : 'Computer ' + comp.computer_number + ' is not available';
                    
                    if (statusClass === 'available') {
                        unit.addEventListener('click', function() {
                            document.querySelectorAll('.computer-unit.selected').forEach(el => {
                                el.classList.remove('selected');
                            });
                            unit.classList.add('selected');
                            resSelectedComputer = comp.computer_number;
                            document.getElementById('inputComputerNo').value = resSelectedComputer;
                        });
                    }
                    
                    grid.appendChild(unit);
                });
                
                resModal.classList.remove('active');
                compModal.classList.add('active');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load computers: ' + error.message);
            });
        });
    }
    
    // Handle form submission from Step 2
    const resFormStep2 = document.getElementById('reservationFormStep2');
    if (resFormStep2) {
        resFormStep2.addEventListener('submit', function(e) {
            // Check the hidden input value directly as the source of truth
            const computerInput = document.getElementById('inputComputerNo');
            if (!computerInput || !computerInput.value) {
                e.preventDefault();
                alert('Please select a computer unit');
                return;
            }
            // Ensure the variable matches the input (just in case)
            resSelectedComputer = computerInput.value;
        });
    }
});
</script>
