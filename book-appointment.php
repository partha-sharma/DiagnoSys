<?php
require_once 'config/init.php';

// Gatekeeper: Only logged-in patients can book
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment - DiagnoLab</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .appointment-container {
            padding: 40px 20px;
            max-width: 600px;
            margin: 0 auto;
        }

        .appointment-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .appointment-header h1 {
            font-size: 28px;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .appointment-header p {
            color: #64748b;
            font-size: 15px;
        }

        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #1a1a1a;
        }

        .form-input:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 2px rgba(14, 165, 233, 0.1);
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(75px, 1fr));
            gap: 8px;
        }

        .time-btn {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #f8fafc;
            color: #64748b;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }

        .time-btn:hover:not(:disabled) {
            border-color: #0ea5e9;
            background: #f0f8ff;
            color: #0ea5e9;
        }

        .time-btn.selected {
            background: #0ea5e9;
            color: white;
            border-color: #0ea5e9;
        }

        .time-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-primary:hover:not(:disabled) {
            background: #0284c7;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            flex: 1;
            padding: 12px;
            background: white;
            color: #0ea5e9;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
        }

        .btn-secondary:hover {
            background: #0ea5e9;
            color: white;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .info-text {
            background: #f0f8ff;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #0c4a6e;
            margin-top: 15px;
            border-left: 3px solid #0ea5e9;
        }

        @media (max-width: 640px) {
            .appointment-container {
                padding: 20px;
            }

            .form-card {
                padding: 20px;
            }

            .time-slots {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">DiagnoLab</div>
        <div class="nav-buttons">
            <a href="dashboard.php" class="btn-outline">← Back to Dashboard</a>
        </div>
    </div>

    <div class="appointment-container">
        <div class="appointment-header">
            <h1>Schedule Your Appointment</h1>
            <p>Select your preferred date and time</p>
        </div>

        <div class="form-card">
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            ?>

            <form action="book-process.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Select Date</label>
                    <input 
                        type="date" 
                        id="appointment_date" 
                        name="appointment_date" 
                        class="form-input"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label">Select Time</label>
                    <div class="time-slots" id="timeSlotsContainer">
                        <p style="grid-column: 1/-1; text-align: center; color: #94a3b8; padding: 20px 0;">
                            Select a date first
                        </p>
                    </div>
                    <input type="hidden" id="appointment_time" name="appointment_time" required>
                </div>

                <div class="info-text">
                    <strong>Working Hours:</strong> 9:00 AM - 5:00 PM (Monday to Saturday)
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary" id="submitBtn" disabled>
                        Book Appointment
                    </button>
                    <a href="dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        const dateInput = document.getElementById('appointment_date');
        const timeInput = document.getElementById('appointment_time');
        const timeSlotsContainer = document.getElementById('timeSlotsContainer');
        const submitBtn = document.getElementById('submitBtn');

        // Time slots
        const timeSlots = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00'];

        // Set min date to today
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dateInput.min = `${yyyy}-${mm}-${dd}`;

        // Format time to 12-hour
        function formatTime(time) {
            const [h, m] = time.split(':');
            const hour = parseInt(h);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hr = hour % 12 || 12;
            return `${hr}:${m} ${ampm}`;
        }

        // Load time slots when date changes
        dateInput.addEventListener('change', function() {
            if (!this.value) return;

            timeSlotsContainer.innerHTML = '';
            const selectedDate = new Date(this.value);
            const isToday = selectedDate.toDateString() === today.toDateString();

            timeSlots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = formatTime(slot);
                btn.className = 'time-btn';
                btn.dataset.time = slot;

                // Disable past slots
                if (isToday) {
                    const [h, m] = slot.split(':');
                    const slotTime = new Date(today.getFullYear(), today.getMonth(), today.getDate(), h, m);
                    if (slotTime < today) {
                        btn.disabled = true;
                    }
                }

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (!this.disabled) {
                        document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        timeInput.value = this.dataset.time;
                        submitBtn.disabled = false;
                    }
                });

                timeSlotsContainer.appendChild(btn);
            });
        });
    </script>
</body>
</html>