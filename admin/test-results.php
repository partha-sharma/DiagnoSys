<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../patient/dashboard.php');
        exit();
    }

    header('Location: ../auth/login.php');
    exit();
}

$sql = "SELECT 
            tr.result_id,
            tr.file_path,
            tr.uploaded_at,
            a.appointment_id,
            a.appointment_date,
            a.status AS appointment_status,
            a.sample_status,
            u.full_name,
            tech.name AS technician_name,
            adm.username AS admin_name
        FROM test_results tr
        INNER JOIN appointments a ON tr.appointment_id = a.appointment_id
        INNER JOIN users u ON a.user_id = u.user_id
        LEFT JOIN technicians tech ON tr.technician_id = tech.technician_id
        LEFT JOIN admins adm ON tr.admin_id = adm.admin_id
        ORDER BY tr.uploaded_at DESC, tr.result_id DESC";

$resultSet = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="test-results.php" class="active">Test Results</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Test Results</h1>
        <p>All uploaded reports are listed here with their uploader and appointment context.</p>

        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Result</th>
                    <th>Appointment</th>
                    <th>Patient</th>
                    <th>Uploader</th>
                    <th>Uploaded At</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultSet && $resultSet->num_rows > 0): ?>
                    <?php while ($row = $resultSet->fetch_assoc()): ?>
                        <?php
                            $uploader = $row['technician_name'] ?: ($row['admin_name'] ?: 'System');
                            $fileLink = '../' . ltrim(str_replace('../', '', $row['file_path']), '/');
                        ?>
                        <tr>
                            <td>#<?php echo (int)$row['result_id']; ?></td>
                            <td>#<?php echo (int)$row['appointment_id']; ?><br><small style="color:#64748b;"><?php echo htmlspecialchars($row['appointment_status']); ?> / <?php echo htmlspecialchars($row['sample_status']); ?></small></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($uploader); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($row['uploaded_at'])); ?></td>
                            <td><a href="<?php echo htmlspecialchars($fileLink); ?>" target="_blank" rel="noopener">Open File</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No uploaded test results yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>


