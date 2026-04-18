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

$filter_date = trim($_GET['filter_date'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');
$filter_patient = trim($_GET['filter_patient'] ?? '');
$filter_test = trim($_GET['filter_test'] ?? '');
$filter_sample = trim($_GET['filter_sample'] ?? '');

$where = [];
$types = '';
$params = [];

if ($filter_date !== '') {
    $where[] = 'DATE(a.appointment_date) = ?';
    $types .= 's';
    $params[] = $filter_date;
}
if ($filter_status !== '') {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $filter_status;
}
if ($filter_patient !== '') {
    $where[] = 'u.full_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $filter_patient . '%';
}
if ($filter_test !== '') {
    $where[] = 't.test_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $filter_test . '%';
}
if ($filter_sample !== '') {
    $where[] = 'a.sample_status = ?';
    $types .= 's';
    $params[] = $filter_sample;
}

$where_sql = '';
if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "SELECT 
            a.appointment_id,
            a.appointment_date,
            a.status,
            a.sample_status,
            a.cancellation_reason,
            a.is_home_collection,
            a.collection_address,
            a.collection_time,
            a.collection_charge,
            u.full_name,
            GROUP_CONCAT(DISTINCT t.test_name ORDER BY t.test_name SEPARATOR ', ') AS test_names,
            MAX(an.created_at) AS last_note_at,
            MAX(latest_results.file_path) AS result_file_path,
            MAX(latest_results.uploaded_at) AS result_uploaded_at
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN appointment_notes an ON a.appointment_id = an.appointment_id
        LEFT JOIN (
            SELECT tr1.appointment_id, tr1.file_path, tr1.uploaded_at
            FROM test_results tr1
            INNER JOIN (
                SELECT appointment_id, MAX(result_id) AS max_result_id
                FROM test_results
                GROUP BY appointment_id
            ) latest_ids ON latest_ids.max_result_id = tr1.result_id
        ) latest_results ON latest_results.appointment_id = a.appointment_id
        $where_sql
        GROUP BY a.appointment_id
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $all_appointments = $stmt->get_result();
    $stmt->close();
} else {
    $all_appointments = false;
}

$sampleStatuses = ['Pending', 'Collected', 'Processing', 'Report Ready', 'Completed'];
$appStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
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
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php" class="active">Appointments</a>
        <a href="test-results.php">Test Results</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <h1>All Appointments</h1>
        <p>Filter and manage appointment lifecycle, sample workflow, and notes.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="GET" class="filter-grid" style="display:grid; grid-template-columns: repeat(5,minmax(140px,1fr)); gap:10px; margin-bottom:18px; background:#fff; padding:14px; border-radius:10px;">
            <input type="date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="form-input">
            <select name="filter_status" class="form-input">
                <option value="">All Status</option>
                <?php foreach ($appStatuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="filter_patient" value="<?php echo htmlspecialchars($filter_patient); ?>" class="form-input" placeholder="Patient name">
            <input type="text" name="filter_test" value="<?php echo htmlspecialchars($filter_test); ?>" class="form-input" placeholder="Test type">
            <select name="filter_sample" class="form-input">
                <option value="">Sample Status</option>
                <?php foreach ($sampleStatuses as $sampleStatus): ?>
                    <option value="<?php echo htmlspecialchars($sampleStatus); ?>" <?php echo $filter_sample === $sampleStatus ? 'selected' : ''; ?>><?php echo htmlspecialchars($sampleStatus); ?></option>
                <?php endforeach; ?>
            </select>
            <div style="grid-column:1/-1; display:flex; gap:10px; justify-content:flex-end;">
                <a href="appointments.php" class="btn-outline" style="text-decoration:none;">Reset</a>
                <button type="submit" class="btn-primary">Apply Filters</button>
            </div>
        </form>

        <?php if ($all_appointments && $all_appointments->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Date &amp; Time</th>
                        <th>Tests</th>
                        <th>Appt. Status</th>
                        <th>Sample Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $all_appointments->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int)$row['appointment_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></div>
                                <?php if ((int)$row['is_home_collection'] === 1): ?>
                                    <small style="color:#0ea5e9; display:block; margin-top:4px;">Home Collection</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['sample_status'])); ?>">
                                    <?php echo htmlspecialchars($row['sample_status'] ?? 'Pending'); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <?php if (!empty($row['result_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars('../' . ltrim(str_replace('../', '', $row['result_file_path']), '/')); ?>" target="_blank" rel="noopener" class="btn-upload">View Result</a>
                                    <?php else: ?>
                                        <a href="test-results.php" class="btn-upload">View Results</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-outline" onclick="openReschedule(<?php echo (int)$row['appointment_id']; ?>,'<?php echo htmlspecialchars(date('Y-m-d', strtotime($row['appointment_date'])), ENT_QUOTES); ?>','<?php echo htmlspecialchars(date('H:i', strtotime($row['appointment_date'])), ENT_QUOTES); ?>')">Reschedule</button>
                                    <button type="button" class="btn-outline" onclick="openCancel(<?php echo (int)$row['appointment_id']; ?>)">Cancel</button>
                                    <button type="button" class="btn-outline" onclick="openNote(<?php echo (int)$row['appointment_id']; ?>)">Add Note</button>
                                </div>
                                <?php if (!empty($row['result_file_path'])): ?>
                                    <small style="display:block; color:#059669; margin-top:6px;">Result uploaded <?php echo date('M d, Y h:i A', strtotime($row['result_uploaded_at'])); ?></small>
                                <?php else: ?>
                                    <small style="display:block; color:#64748b; margin-top:6px;">No result uploaded yet</small>
                                <?php endif; ?>
                                <?php if (!empty($row['cancellation_reason'])): ?>
                                    <small style="display:block; color:#991b1b; margin-top:6px;">Reason: <?php echo htmlspecialchars($row['cancellation_reason']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($row['collection_address'])): ?>
                                    <small style="display:block; color:#475569; margin-top:4px;">Address: <?php echo htmlspecialchars($row['collection_address']); ?></small>
                                <?php endif; ?>
                                <?php if (!empty($row['collection_time'])): ?>
                                    <small style="display:block; color:#475569; margin-top:2px;">Collection: <?php echo date('M d @ h:i A', strtotime($row['collection_time'])); ?></small>
                                <?php endif; ?>
                                <form action="appointments-process.php" method="POST" style="margin-top:8px; display:flex; gap:6px;">
                                    <input type="hidden" name="action" value="update_sample_status">
                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>">
                                    <select name="sample_status" class="form-input" style="min-width:130px; padding:6px 8px;">
                                        <?php foreach ($sampleStatuses as $sampleStatus): ?>
                                            <option value="<?php echo htmlspecialchars($sampleStatus); ?>" <?php echo ($row['sample_status'] === $sampleStatus) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sampleStatus); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-primary" style="padding:6px 12px;">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state"><p>No appointments found for current filters.</p></div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="rescheduleModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('rescheduleModal')">×</button>
        <h2>Reschedule Appointment</h2>
        <form action="appointments-process.php" method="POST">
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="appointment_id" id="rescheduleAppointmentId">
            <label>New Date</label>
            <input type="date" name="new_date" id="rescheduleDate" required>
            <label>New Time</label>
            <input type="time" name="new_time" id="rescheduleTime" required>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('rescheduleModal')">Close</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('cancelModal')">×</button>
        <h2>Cancel Appointment</h2>
        <form action="appointments-process.php" method="POST">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="appointment_id" id="cancelAppointmentId">
            <label>Reason</label>
            <textarea name="cancellation_reason" rows="4" required placeholder="Provide cancellation reason..."></textarea>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('cancelModal')">Close</button>
                <button type="submit" class="btn-primary">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="noteModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('noteModal')">×</button>
        <h2>Add Admin Note</h2>
        <form action="appointments-process.php" method="POST">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="appointment_id" id="noteAppointmentId">
            <label>Note</label>
            <textarea name="note_text" rows="4" required placeholder="Add contextual note for this appointment..."></textarea>
            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('noteModal')">Close</button>
                <button type="submit" class="btn-primary">Save Note</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReschedule(id, date, time) {
    document.getElementById('rescheduleAppointmentId').value = id;
    document.getElementById('rescheduleDate').value = date;
    document.getElementById('rescheduleTime').value = time;
    document.getElementById('rescheduleModal').classList.add('active');
}

function openCancel(id) {
    document.getElementById('cancelAppointmentId').value = id;
    document.getElementById('cancelModal').classList.add('active');
}

function openNote(id) {
    document.getElementById('noteAppointmentId').value = id;
    document.getElementById('noteModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>


