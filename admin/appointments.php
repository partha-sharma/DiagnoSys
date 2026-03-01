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
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
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
