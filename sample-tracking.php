<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$focus_appointment_id = (int)($_GET['appointment_id'] ?? 0);

$sql = "SELECT
            a.appointment_id,
            a.appointment_date,
            a.status AS appointment_status,
            a.sample_status AS appointment_sample_status,
            a.is_home_collection,
            a.collection_address,
            a.collection_time,
            GROUP_CONCAT(DISTINCT t.test_name ORDER BY t.test_name SEPARATOR ', ') AS test_names,
            st.status AS tracking_status,
            st.collected_at,
            st.processing_started_at,
            st.report_ready_at,
            st.completed_at,
            st.notes,
            tech.name AS collector_name
        FROM appointments a
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN sample_tracking st ON a.appointment_id = st.appointment_id
        LEFT JOIN technicians tech ON st.collected_by = tech.technician_id
        WHERE a.user_id = ?
        GROUP BY a.appointment_id
        ORDER BY (a.appointment_id = ?) DESC, a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user_id, $focus_appointment_id);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Tracking - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>
<div class="navbar">
    <a href="dashboard.php" class="logo">DiagnoLab</a>
    <div class="nav-buttons">
        <a href="dashboard.php" class="btn-outline">Dashboard</a>
        <a href="logout.php" class="btn-primary">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>Sample Tracking</h1>
            <p>Monitor collection and processing updates for your appointments.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="appointments-section">
        <?php if ($rows && $rows->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Appt. ID</th>
                        <th>Date</th>
                        <th>Tests</th>
                        <th>Sample Status</th>
                        <th>Progress</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <?php $displayStatus = $row['tracking_status'] ?: ($row['appointment_sample_status'] ?: 'Pending'); ?>
                        <tr>
                            <td>#<?php echo (int)$row['appointment_id']; ?></td>
                            <td>
                                <?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?>
                                <?php if ((int)$row['is_home_collection'] === 1): ?>
                                    <small style="display:block; color:#0ea5e9; margin-top:4px;">Home Collection</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $displayStatus)); ?>">
                                    <?php echo htmlspecialchars($displayStatus); ?>
                                </span>
                                <?php if (!empty($row['collector_name'])): ?>
                                    <small style="display:block; color:#475569; margin-top:5px;">Collected by: <?php echo htmlspecialchars($row['collector_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="display:block; color:#475569;">Collected: <?php echo !empty($row['collected_at']) ? htmlspecialchars($row['collected_at']) : 'N/A'; ?></small>
                                <small style="display:block; color:#475569;">Processing: <?php echo !empty($row['processing_started_at']) ? htmlspecialchars($row['processing_started_at']) : 'N/A'; ?></small>
                                <small style="display:block; color:#475569;">Report Ready: <?php echo !empty($row['report_ready_at']) ? htmlspecialchars($row['report_ready_at']) : 'N/A'; ?></small>
                                <small style="display:block; color:#475569;">Completed: <?php echo !empty($row['completed_at']) ? htmlspecialchars($row['completed_at']) : 'N/A'; ?></small>
                            </td>
                            <td>
                                <?php if (!empty($row['notes'])): ?>
                                    <?php echo nl2br(htmlspecialchars($row['notes'])); ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">No notes yet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No appointments available for sample tracking yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
