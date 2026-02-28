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

// Query to see ALL appointments with patient names and booked tests
$sql = "SELECT a.appointment_id, a.appointment_date, a.status, u.full_name,
        GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        GROUP BY a.appointment_id ORDER BY a.appointment_date DESC";
$all_appointments = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; }
        .sidebar a { display: block; color: #cbd5e1; padding: 10px 0; text-decoration: none; }
        .sidebar a:hover { color: white; }
        .sidebar a.active { color: white; font-weight: 600; }
        .content { flex: 1; padding: 40px; background: #f1f5f9; overflow-x: auto; }

        .content h1 { margin-bottom: 5px; color: #0f172a; }
        .content > p { color: #64748b; margin-bottom: 25px; }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .appointments-table thead { background: #f8fafc; }
        .appointments-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .appointments-table td {
            padding: 14px 16px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .appointments-table tbody tr:hover { background: #f8fafc; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending   { background: #fef3c7; color: #92400e; }
        .status-confirmed  { background: #d1fae5; color: #065f46; }
        .status-completed  { background: #dbeafe; color: #1e40af; }
        .status-cancelled  { background: #fee2e2; color: #991b1b; }

        .btn-upload {
            display: inline-block;
            padding: 6px 14px;
            background: #0ea5e9;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn-upload:hover { background: #0284c7; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 3px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 3px solid #ef4444; }
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
    <div class="content">
        <h1>All Appointments</h1>
        <p>View and manage all patient appointments.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($all_appointments && $all_appointments->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Date &amp; Time</th>
                        <th>Tests</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $all_appointments->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $row['appointment_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="upload_form.php?id=<?php echo $row['appointment_id']; ?>" class="btn-upload">Upload Result</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No appointments found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
