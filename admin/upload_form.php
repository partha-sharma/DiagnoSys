<?php
require_once '../config/init.php';

// Admin Gatekeeper
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header("Location: ../dashboard.php");
        exit();
    }
    header("Location: ../login.php");
    exit();
}

$appointment_id = intval($_GET['id'] ?? 0);

if ($appointment_id <= 0) {
    $_SESSION['error'] = "Invalid appointment ID.";
    header("Location: appointments.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Result - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="admin-layout">
    <!-- Left Sidebar -->
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php" class="active">Appointments</a>
            <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <!-- Right Content -->
    <div class="content">
        <div class="upload-container">
            <h1>Upload Result for Appointment #<?php echo $appointment_id; ?></h1>
            <p>Attach the test report file for this appointment.</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="upload-card">
                <!-- IMPORTANT: enctype is required for file uploads -->
                <form action="upload-process.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">

                    <div class="form-group">
                        <label class="form-label">Select Report File</label>
                        <input type="file" name="report_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                        <p class="file-hint">Accepted formats: PDF, JPG, PNG (max 5 MB)</p>
                    </div>

                    <button type="submit" class="btn-upload btn-upload-full">Upload File</button>
                </form>

                <a href="appointments.php" class="btn-back">← Back to Appointments</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
