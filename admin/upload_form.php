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
    <style>
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; }
        .sidebar a { display: block; color: #cbd5e1; padding: 10px 0; text-decoration: none; }
        .sidebar a:hover { color: white; }
        .sidebar a.active { color: white; font-weight: 600; }

        .upload-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .upload-container h1 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .upload-container > p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .upload-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .file-input {
            width: 100%;
            padding: 11px 14px;
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #334155;
            background: #f8fafc;
            cursor: pointer;
        }

        .file-input:hover {
            border-color: #0ea5e9;
            background: #f0f8ff;
        }

        .btn-upload {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-upload:hover { background: #0284c7; }

        .btn-back {
            display: inline-block;
            margin-top: 15px;
            color: #0ea5e9;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-back:hover { text-decoration: underline; }

        .file-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Left Sidebar -->
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">&#128105;&#8205;&#9877;&#65039; Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php" class="active">Appointments</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" style="color: #fca5a5;">Logout</a>
    </div>

    <!-- Right Content -->
    <div style="flex: 1; padding: 40px; background: #f1f5f9;">
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

                    <button type="submit" class="btn-upload">Upload File</button>
                </form>

                <a href="appointments.php" class="btn-back">← Back to Appointments</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
