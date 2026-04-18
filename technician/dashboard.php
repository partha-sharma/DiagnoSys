<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../patient/dashboard.php');
        exit();
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
        exit();
    }

    header('Location: ../auth/login.php');
    exit();
}

$technicianId = (int)$_SESSION['user_id'];
$technicianSpecialization = normalize_technician_specialization((string)($_SESSION['technician_specialization'] ?? 'Laboratory'));

$assignedAppointmentsStmt = $conn->prepare("SELECT COUNT(DISTINCT a.appointment_id) AS total_count
    FROM appointments a
    INNER JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
    INNER JOIN tests t ON apt.test_id = t.test_id
    WHERE COALESCE(t.test_category, '') = ?");
$assignedAppointmentsStmt->bind_param('s', $technicianSpecialization);
$assignedAppointmentsStmt->execute();
$assignedAppointments = $assignedAppointmentsStmt->get_result()->fetch_assoc()['total_count'] ?? 0;
$assignedAppointmentsStmt->close();

$uploadedResultsStmt = $conn->prepare("SELECT COUNT(DISTINCT tr.result_id) AS total_count
    FROM test_results tr
    INNER JOIN appointments a ON tr.appointment_id = a.appointment_id
    INNER JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
    INNER JOIN tests t ON apt.test_id = t.test_id
    WHERE COALESCE(t.test_category, '') = ?");
$uploadedResultsStmt->bind_param('s', $technicianSpecialization);
$uploadedResultsStmt->execute();
$uploadedResults = $uploadedResultsStmt->get_result()->fetch_assoc()['total_count'] ?? 0;
$uploadedResultsStmt->close();

$pendingAppointmentsStmt = $conn->prepare("SELECT COUNT(DISTINCT a.appointment_id) AS total_count
    FROM appointments a
    INNER JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
    INNER JOIN tests t ON apt.test_id = t.test_id
    LEFT JOIN test_results tr ON a.appointment_id = tr.appointment_id
    WHERE COALESCE(t.test_category, '') = ? AND tr.result_id IS NULL");
$pendingAppointmentsStmt->bind_param('s', $technicianSpecialization);
$pendingAppointmentsStmt->execute();
$pendingAppointments = $pendingAppointmentsStmt->get_result()->fetch_assoc()['total_count'] ?? 0;
$pendingAppointmentsStmt->close();

$appointmentSql = "SELECT 
        a.appointment_id,
        a.appointment_date,
        a.status,
        a.sample_status,
        u.full_name,
        GROUP_CONCAT(DISTINCT t.test_name ORDER BY t.test_name SEPARATOR ', ') AS test_names,
        GROUP_CONCAT(DISTINCT COALESCE(t.test_category, 'General') ORDER BY t.test_category SEPARATOR ', ') AS test_categories,
        latest.result_id,
        latest.file_path,
        latest.uploaded_at
    FROM appointments a
    INNER JOIN users u ON a.user_id = u.user_id
    LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
    LEFT JOIN tests t ON apt.test_id = t.test_id
    LEFT JOIN (
        SELECT tr1.result_id, tr1.appointment_id, tr1.file_path, tr1.uploaded_at
        FROM test_results tr1
        INNER JOIN (
            SELECT appointment_id, MAX(result_id) AS max_result_id
            FROM test_results
            GROUP BY appointment_id
        ) latest_ids ON latest_ids.max_result_id = tr1.result_id
    ) latest ON latest.appointment_id = a.appointment_id
    WHERE EXISTS (
        SELECT 1 FROM appointment_tests apt2
        INNER JOIN tests t2 ON apt2.test_id = t2.test_id
        WHERE apt2.appointment_id = a.appointment_id
          AND COALESCE(t2.test_category, '') = ?
    )
    GROUP BY a.appointment_id
    ORDER BY a.appointment_date ASC";

$appointmentStmt = $conn->prepare($appointmentSql);
$appointmentStmt->bind_param('s', $technicianSpecialization);
$appointmentStmt->execute();
$appointmentsResult = $appointmentStmt->get_result();
$appointmentStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Technician Panel</h2>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Technician Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>. Specialization: <?php echo htmlspecialchars($technicianSpecialization); ?>.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:16px; margin-bottom:24px;">
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Assigned Appointments</div>
                <div style="font-size:32px; font-weight:700; color:#0f172a; margin-top:8px;"> <?php echo (int)$assignedAppointments; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Pending Reports</div>
                <div style="font-size:32px; font-weight:700; color:#b45309; margin-top:8px;"> <?php echo (int)$pendingAppointments; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Uploaded By You</div>
                <div style="font-size:32px; font-weight:700; color:#059669; margin-top:8px;"> <?php echo (int)$uploadedResults; ?></div>
            </div>
        </div>

        <table class="appointments-table">
            <thead>
                <tr>
                    <th>Appointment</th>
                    <th>Patient</th>
                    <th>Date &amp; Time</th>
                    <th>Tests</th>
                    <th>Categories</th>
                    <th>Sample Status</th>
                    <th>Latest Result</th>
                    <th>Upload Report</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($appointmentsResult && $appointmentsResult->num_rows > 0): ?>
                    <?php while ($row = $appointmentsResult->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int)$row['appointment_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['test_categories'] ?? 'General'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['sample_status'] ?? 'Pending')); ?>">
                                    <?php echo htmlspecialchars($row['sample_status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars('../' . ltrim(str_replace('..\\', '', str_replace('../', '', $row['file_path'])), '/')); ?>" target="_blank" rel="noopener">View File</a>
                                    <small style="display:block; color:#64748b; margin-top:4px;">Uploaded <?php echo date('M d, Y h:i A', strtotime($row['uploaded_at'])); ?></small>
                                <?php else: ?>
                                    <span style="color:#64748b;">No result uploaded yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="upload-process.php" method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:8px; min-width:220px;">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>">
                                    <input type="file" name="report_file" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <button type="submit" class="btn-primary">Upload Result</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No appointments found for your specialization yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>


