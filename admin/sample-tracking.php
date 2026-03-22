<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../dashboard.php');
        exit();
    }
    header('Location: ../login.php');
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$allowedStatuses = ['Pending', 'Collected', 'Processing', 'Report Ready', 'Completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
    $sample_status = trim($_POST['sample_status'] ?? 'Pending');
    $collected_by = (int)($_POST['collected_by'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($appointment_id <= 0) {
        $_SESSION['error'] = 'Invalid appointment selected.';
        header('Location: sample-tracking.php');
        exit();
    }

    if (!in_array($sample_status, $allowedStatuses, true)) {
        $_SESSION['error'] = 'Invalid sample status.';
        header('Location: sample-tracking.php');
        exit();
    }

    if (strlen($notes) > 1000) {
        $_SESSION['error'] = 'Notes are too long. Maximum 1000 characters allowed.';
        header('Location: sample-tracking.php');
        exit();
    }

    $conn->begin_transaction();

    try {
        $existsStmt = $conn->prepare('SELECT tracking_id FROM sample_tracking WHERE appointment_id = ? LIMIT 1');
        $existsStmt->bind_param('i', $appointment_id);
        $existsStmt->execute();
        $exists = $existsStmt->get_result()->fetch_assoc();
        $existsStmt->close();

        if (!$exists) {
            $insertStmt = $conn->prepare('INSERT INTO sample_tracking (appointment_id, status, notes) VALUES (?, ?, ?)');
            $insertStmt->bind_param('iss', $appointment_id, $sample_status, $notes);
            $insertStmt->execute();
            $insertStmt->close();
        }

        $collectedByValue = $collected_by > 0 ? $collected_by : 0;

        $updateTrack = $conn->prepare("UPDATE sample_tracking
            SET status = ?,
                collected_by = NULLIF(?, 0),
                notes = ?,
                collected_at = CASE WHEN ? = 'Collected' AND collected_at IS NULL THEN NOW() ELSE collected_at END,
                processing_started_at = CASE WHEN ? = 'Processing' AND processing_started_at IS NULL THEN NOW() ELSE processing_started_at END,
                report_ready_at = CASE WHEN ? = 'Report Ready' AND report_ready_at IS NULL THEN NOW() ELSE report_ready_at END,
                completed_at = CASE WHEN ? = 'Completed' AND completed_at IS NULL THEN NOW() ELSE completed_at END
            WHERE appointment_id = ?");
        $updateTrack->bind_param('sisssssi', $sample_status, $collectedByValue, $notes, $sample_status, $sample_status, $sample_status, $sample_status, $appointment_id);
        $updateTrack->execute();
        $updateTrack->close();

        $updateAppt = $conn->prepare('UPDATE appointments SET sample_status = ? WHERE appointment_id = ?');
        $updateAppt->bind_param('si', $sample_status, $appointment_id);
        $updateAppt->execute();
        $updateAppt->close();

        $historyText = 'Sample status updated to ' . $sample_status;
        $historyStmt = $conn->prepare('INSERT INTO appointment_history (appointment_id, status) VALUES (?, ?)');
        $historyStmt->bind_param('is', $appointment_id, $historyText);
        $historyStmt->execute();
        $historyStmt->close();

        if ($notes !== '') {
            $noteStmt = $conn->prepare('INSERT INTO appointment_notes (appointment_id, admin_id, note_text) VALUES (?, ?, ?)');
            $noteStmt->bind_param('iis', $appointment_id, $admin_id, $notes);
            $noteStmt->execute();
            $noteStmt->close();
        }

        $conn->commit();
        $_SESSION['success'] = 'Sample tracking updated successfully.';
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Could not update sample tracking. Please try again.';
    }

    header('Location: sample-tracking.php');
    exit();
}

$filter_status = trim($_GET['filter_status'] ?? '');
$filter_patient = trim($_GET['filter_patient'] ?? '');
$focus_appointment_id = (int)($_GET['appointment_id'] ?? 0);

$where = [];
$types = '';
$params = [];

if ($filter_status !== '' && in_array($filter_status, $allowedStatuses, true)) {
    $where[] = 'COALESCE(st.status, a.sample_status, "Pending") = ?';
    $types .= 's';
    $params[] = $filter_status;
}
if ($filter_patient !== '') {
    $where[] = 'u.full_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $filter_patient . '%';
}
if ($focus_appointment_id > 0) {
    $where[] = 'a.appointment_id = ?';
    $types .= 'i';
    $params[] = $focus_appointment_id;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "SELECT
            a.appointment_id,
            a.appointment_date,
            a.status AS appointment_status,
            a.sample_status AS appointment_sample_status,
            a.is_home_collection,
            a.collection_address,
            a.collection_time,
            u.full_name,
            GROUP_CONCAT(DISTINCT t.test_name ORDER BY t.test_name SEPARATOR ', ') AS test_names,
            st.status AS tracking_status,
            st.collected_at,
            st.processing_started_at,
            st.report_ready_at,
            st.completed_at,
            st.notes,
            st.collected_by,
            tech.name AS technician_name
        FROM appointments a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN sample_tracking st ON a.appointment_id = st.appointment_id
        LEFT JOIN technicians tech ON st.collected_by = tech.technician_id
        $whereSql
        GROUP BY a.appointment_id
        ORDER BY (a.appointment_id = ?) DESC, a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$bindTypes = $types . 'i';
$bindParams = $params;
$bindParams[] = $focus_appointment_id;
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();

$techResult = $conn->query("SELECT technician_id, name, specialization FROM technicians WHERE status = 'Active' ORDER BY name ASC");
$technicians = [];
if ($techResult) {
    while ($techRow = $techResult->fetch_assoc()) {
        $technicians[] = $techRow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Tracking - DiagnoLab</title>
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
        <a href="sample-tracking.php" class="active">Sample Tracking</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Sample Tracking</h1>
        <p>Track collection, processing, and readiness for each appointment sample.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <form method="GET" class="filter-grid" style="display:grid; grid-template-columns: 1fr 1fr auto; gap:10px; margin-bottom:18px; background:#fff; padding:14px; border-radius:10px;">
            <input type="text" name="filter_patient" class="form-input" placeholder="Search patient" value="<?php echo htmlspecialchars($filter_patient); ?>">
            <select name="filter_status" class="form-input">
                <option value="">All Sample Statuses</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $filter_status === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                <?php endforeach; ?>
            </select>
            <div style="display:flex; gap:8px;">
                <a href="sample-tracking.php" class="btn-outline" style="text-decoration:none;">Reset</a>
                <button type="submit" class="btn-primary">Filter</button>
            </div>
        </form>

        <div class="appointments-section">
            <?php if ($rows && $rows->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Appt. ID</th>
                            <th>Patient</th>
                            <th>Tests</th>
                            <th>Sample Status</th>
                            <th>Timestamps</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $rows->fetch_assoc()): ?>
                            <?php $displayStatus = $row['tracking_status'] ?: ($row['appointment_sample_status'] ?: 'Pending'); ?>
                            <tr>
                                <td>#<?php echo (int)$row['appointment_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <small style="display:block; color:#64748b; margin-top:4px;">
                                        <?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?>
                                    </small>
                                    <?php if ((int)$row['is_home_collection'] === 1): ?>
                                        <small style="display:block; color:#0ea5e9; margin-top:4px;">Home Collection</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></div>
                                    <?php if (!empty($row['collection_address'])): ?>
                                        <small style="display:block; color:#64748b; margin-top:4px;">Address: <?php echo htmlspecialchars($row['collection_address']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $displayStatus)); ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                    <?php if (!empty($row['technician_name'])): ?>
                                        <small style="display:block; color:#475569; margin-top:6px;">Collector: <?php echo htmlspecialchars($row['technician_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small style="display:block; color:#475569;">Collected: <?php echo !empty($row['collected_at']) ? htmlspecialchars($row['collected_at']) : 'N/A'; ?></small>
                                    <small style="display:block; color:#475569;">Processing: <?php echo !empty($row['processing_started_at']) ? htmlspecialchars($row['processing_started_at']) : 'N/A'; ?></small>
                                    <small style="display:block; color:#475569;">Report Ready: <?php echo !empty($row['report_ready_at']) ? htmlspecialchars($row['report_ready_at']) : 'N/A'; ?></small>
                                    <small style="display:block; color:#475569;">Completed: <?php echo !empty($row['completed_at']) ? htmlspecialchars($row['completed_at']) : 'N/A'; ?></small>
                                </td>
                                <td>
                                    <form method="POST" style="display:grid; gap:8px; min-width:220px;">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>">
                                        <select name="sample_status" class="form-input" required>
                                            <?php foreach ($allowedStatuses as $status): ?>
                                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $displayStatus === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="collected_by" class="form-input">
                                            <option value="">Collector Technician (optional)</option>
                                            <?php foreach ($technicians as $tech): ?>
                                                <option value="<?php echo (int)$tech['technician_id']; ?>" <?php echo ((int)($row['collected_by'] ?? 0) === (int)$tech['technician_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tech['name']); ?><?php echo !empty($tech['specialization']) ? ' - ' . htmlspecialchars($tech['specialization']) : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <textarea name="notes" class="form-input" rows="2" placeholder="Tracking notes (optional)"><?php echo htmlspecialchars($row['notes'] ?? ''); ?></textarea>
                                        <button type="submit" class="btn-primary" style="padding:6px 12px;">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No sample tracking records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
